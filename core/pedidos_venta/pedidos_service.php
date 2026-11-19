<?php
/**
 * Pedidos de venta (doc 28, paso 9-2): alta, detalle, numeración, espejo en gestion__comprobantes y transiciones de
 * estado, en un solo lugar para el ERP (módulos 2 y 5), el carrito del cliente y la aplicación del vendedor.
 *
 * Es el código que estaba en modules/<gestion|gestion_normal>/ventas_pedidos_model.php, movido sin cambios de
 * lógica: las funciones del modelo (agregarPedidoVenta, ejecutarTransicionEstado, insertarDetallesPedido,
 * syncComprobante, obtenerProximoNumeroComprobante, ...) quedan con su nombre y llaman a estas.
 * Lo único nuevo es $ctx en lugar de $_SESSION:
 *   - 'usuario_id' (int): quién graba / confirma (comprobante, reglas de precio, cartera).
 *   - 'accesos' (bool, por defecto true): exigir los accesos por sucursal del usuario (core/accesos_sucursales.php).
 *     El ERP los exige siempre; un canal sin usuario del ERP con perfil (carrito) los pide en false y valida lo suyo.
 * El origen del pedido (CARRITO / APP) viaja en $data['origen'] y lo resuelve vcar_resolver_pedido (doc 23).
 * No incluye db.php ni nada de modules/: solo core/.
 */

require_once __DIR__ . '/../empresa_config.php';
require_once __DIR__ . '/../ventas_presentaciones.php';
require_once __DIR__ . '/../canales_venta.php';
require_once __DIR__ . '/../precios_venta.php';
require_once __DIR__ . '/../vendedores.php';
require_once __DIR__ . '/../distribucion/distribucion_service.php';
require_once __DIR__ . '/indicadores.php';

if (!function_exists('pven_ctx')) {
    /** Contexto normalizado: ['usuario_id' => int, 'accesos' => bool]. */
    function pven_ctx(array $ctx): array
    {
        return ['usuario_id' => intval($ctx['usuario_id'] ?? 0), 'accesos' => !array_key_exists('accesos', $ctx) || !empty($ctx['accesos'])];
    }
}


if (!function_exists('pven_estado_inicial_catalogo')) {
    function pven_estado_inicial_catalogo($conexion, $pagina_id)
    {
        $pagina_id = intval($pagina_id);

        $sql = "SELECT ter.estado_registro_id
                FROM conf__paginas p
                JOIN conf__tablas_estados_registros ter ON ter.tabla_id = p.tabla_id
                WHERE p.pagina_id = ?
                AND ter.es_inicial = 1
                LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando consulta estado inicial: " . mysqli_error($conexion));
            return 1;
        }

        mysqli_stmt_bind_param($stmt, "i", $pagina_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $fila = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($fila) {
            return $fila['estado_registro_id'];
        }

        return 1;
    }
}

if (!function_exists('pven_proximo_numero')) {
    function pven_proximo_numero($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id) {
        $sql_check = "SELECT numerador_id, ultimo_numero 
                      FROM gestion__comprobantes_numeradores 
                      WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                      FOR UPDATE";

        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            error_log("Error preparando consulta numerador: " . mysqli_error($conexion));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $numerador = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($numerador) {
            $nuevo_numero = $numerador['ultimo_numero'] + 1;

            $sql_update = "UPDATE gestion__comprobantes_numeradores 
                           SET ultimo_numero = ? 
                           WHERE numerador_id = ?";

            $stmt_update = mysqli_prepare($conexion, $sql_update);
            if (!$stmt_update) {
                error_log("Error preparando update numerador: " . mysqli_error($conexion));
                return false;
            }

            mysqli_stmt_bind_param($stmt_update, "ii", $nuevo_numero, $numerador['numerador_id']);
            $success = mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            if ($success) {
                return $nuevo_numero;
            } else {
                error_log("Error actualizando numerador: " . mysqli_error($conexion));
                return false;
            }
        } else {
            $sql_insert = "INSERT INTO gestion__comprobantes_numeradores 
                           (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) 
                           VALUES (?, ?, ?, 1)";

            $stmt_insert = mysqli_prepare($conexion, $sql_insert);
            if (!$stmt_insert) {
                error_log("Error preparando insert numerador: " . mysqli_error($conexion));
                return false;
            }

            mysqli_stmt_bind_param($stmt_insert, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
            $success = mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);

            if ($success) {
                return 1;
            } else {
                error_log("Error insertando numerador: " . mysqli_error($conexion));
                return false;
            }
        }
    }
}

if (!function_exists('pven_ejecutar_transicion')) {
    function pven_ejecutar_transicion($conexion, $venta_pedido_id, $accion_js, $empresa_idx, $pagina_id, array $ctx = [])
    {
        $ctx = pven_ctx($ctx);
        $venta_pedido_id = intval($venta_pedido_id);
        $pagina_id = intval($pagina_id);

        $sql_check = "SELECT vp.venta_pedido_id, vp.tabla_estado_registro_id, vp.comprobante_nro, 
                             vp.comprobante_tipo_id, vp.punto_venta_id, vp.empresa_id, vp.sucursal_id
                      FROM gestion__ventas_pedidos vp
                      WHERE vp.venta_pedido_id = ?";

        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            error_log("Error preparando consulta: " . mysqli_error($conexion));
            return ['success' => false, 'error' => 'Error en la consulta'];
        }

        mysqli_stmt_bind_param($stmt, "i", $venta_pedido_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $pedido = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$pedido) {
            return ['success' => false, 'error' => 'Registro no encontrado'];
        }

        if ($ctx['accesos'] && file_exists(__DIR__ . '/../accesos_sucursales.php')) {
            require_once __DIR__ . '/../accesos_sucursales.php';
            try {
                AccesosSucursalesHelper::exigirAccesoSucursal($conexion, intval($pedido['empresa_id']), intval($pedido['sucursal_id']));
            } catch (RuntimeException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $estado_actual_id = $pedido['tabla_estado_registro_id'];

        $sql_funcion = "SELECT pf.* 
                        FROM conf__paginas_funciones pf
                        WHERE pf.pagina_id = ? 
                        AND pf.tabla_estado_registro_origen_id = ? 
                        AND pf.accion_js = ?
                        LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql_funcion);
        if (!$stmt) {
            error_log("Error preparando consulta función: " . mysqli_error($conexion));
            return ['success' => false, 'error' => 'Error en la consulta'];
        }

        mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $funcion = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$funcion) {
            error_log("Acción no permitida - página: $pagina_id, estado origen: $estado_actual_id, acción: $accion_js");
            return ['success' => false, 'error' => 'Acción no permitida para este estado'];
        }

        $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

        if ($estado_destino_id == $estado_actual_id) {
            return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
        }

        mysqli_begin_transaction($conexion);

        try {
            $numero_asignado = null;

            // Distribución: al liberar (-> 42) exige el indicador y completa el depósito de despacho; al volver (42 -> 5) lo saca de la hoja en borrador
            vdis_antes_de_transicion($conexion, intval($pedido['empresa_id']), $pedido, intval($estado_destino_id));

            if ($accion_js === 'confirmar_pedido') {

                // Reglas de precio de venta: un descuento manual que supera el tope de quien confirma no deja confirmar
                $ids_det_prv = array_column(prv_filas($conexion, "SELECT venta_pedido_detalle_id FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = ?",
                    'i', [intval($venta_pedido_id)]), 'venta_pedido_detalle_id');
                $msg_prv = prv_aprobar_pendientes($conexion, intval($pedido['empresa_id']), 'pedido', $ids_det_prv, $ctx['usuario_id']);
                if ($msg_prv !== null) {
                    throw new Exception($msg_prv);
                }

                if (empty($pedido['punto_venta_id'])) {
                    throw new Exception('El pedido no tiene punto de venta asignado. No se puede generar número de comprobante.');
                }

                if (!empty($pedido['comprobante_nro']) && $pedido['comprobante_nro'] > 0) {
                    $numero_asignado = $pedido['comprobante_nro'];
                } else {
                    $proximo_numero = pven_proximo_numero(
                        $conexion, 
                        $pedido['empresa_id'], 
                        $pedido['punto_venta_id'], 
                        $pedido['comprobante_tipo_id']
                    );

                    if ($proximo_numero === false) {
                        throw new Exception('Error al obtener próximo número de comprobante');
                    }

                    $sql_update_numero = "UPDATE gestion__ventas_pedidos 
                                          SET comprobante_nro = ? 
                                          WHERE venta_pedido_id = ?";

                    $stmt_numero = mysqli_prepare($conexion, $sql_update_numero);
                    if (!$stmt_numero) {
                        throw new Exception('Error preparando update de número: ' . mysqli_error($conexion));
                    }

                    mysqli_stmt_bind_param($stmt_numero, "ii", $proximo_numero, $venta_pedido_id);
                    if (!mysqli_stmt_execute($stmt_numero)) {
                        throw new Exception('Error actualizando número: ' . mysqli_stmt_error($stmt_numero));
                    }
                    mysqli_stmt_close($stmt_numero);

                    $numero_asignado = $proximo_numero;
                }
            }

            $sql_update = "UPDATE gestion__ventas_pedidos 
                           SET tabla_estado_registro_id = ?,
                               f_confirmacion = IF(? = 5 AND f_confirmacion IS NULL, NOW(), f_confirmacion)
                           WHERE venta_pedido_id = ?";

            $stmt = mysqli_prepare($conexion, $sql_update);
            if (!$stmt) {
                throw new Exception('Error preparando update de estado: ' . mysqli_error($conexion));
            }

            $estado_dest_int = intval($estado_destino_id);
            mysqli_stmt_bind_param($stmt, "iii", $estado_dest_int, $estado_dest_int, $venta_pedido_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error actualizando estado: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            // Propagar el nuevo estado (y el número asignado, si corresponde) a gestion__comprobantes.
            // NOTA: a diferencia de facturas_proveedores, acá NO se dispara registrarStockPorConfirmacion
            // ni generarAsientoContableFactura al llegar a CONFIRMADO: el pedido de venta no afecta
            // stock ni contabilidad; eso queda para cuando se genera la factura de venta.
            $sql_pedido_completo = "SELECT empresa_id, sucursal_id, punto_venta_id, comprobante_tipo_id,
                                            comprobante_nro, entidad_id, entidad_sucursal_id, f_emision,
                                            moneda_id, tipo_cambio, subtotal, descuentos, impuestos, total,
                                            observaciones
                                     FROM gestion__ventas_pedidos
                                     WHERE venta_pedido_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_pedido_completo);
            if (!$stmt) {
                throw new Exception('Error preparando consulta de datos completos: ' . mysqli_error($conexion));
            }
            mysqli_stmt_bind_param($stmt, "i", $venta_pedido_id);
            mysqli_stmt_execute($stmt);
            $result_pedido = mysqli_stmt_get_result($stmt);
            $pedido_data = mysqli_fetch_assoc($result_pedido);
            mysqli_stmt_close($stmt);

            if (!$pedido_data) {
                throw new Exception('No se pudieron obtener los datos completos del pedido');
            }

            $tabla_origen_id = pven_tabla_origen_pagina($conexion, $pagina_id);
            if (!$tabla_origen_id) {
                throw new Exception('No se pudo determinar la tabla de origen');
            }

            $comprobante_data = [
                'empresa_id' => $pedido_data['empresa_id'],
                'sucursal_id' => $pedido_data['sucursal_id'],
                'comprobante_pv' => $pedido_data['punto_venta_id'],
                'comprobante_tipo_id' => $pedido_data['comprobante_tipo_id'],
                'comprobante_nro' => $pedido_data['comprobante_nro'],
                'entidad_id' => $pedido_data['entidad_id'],
                'entidad_sucursal_id' => $pedido_data['entidad_sucursal_id'],
                'f_emision' => $pedido_data['f_emision'],
                'f_contabilidad' => $pedido_data['f_emision'],
                'f_vto' => null,
                'moneda_id' => $pedido_data['moneda_id'],
                'tipo_cambio' => $pedido_data['tipo_cambio'],
                'registro_origen_id' => $venta_pedido_id,
                'tabla_estado_registro_id' => $estado_destino_id,
                'usuario_id' => $ctx['usuario_id'],
                'observaciones' => $pedido_data['observaciones'],
                'importe_neto' => floatval($pedido_data['subtotal'] ?? 0),
                'descuento_general' => floatval($pedido_data['descuentos'] ?? 0),
                'importe_no_gravado' => 0,
                'importe_exento' => 0,
                'importe_iva' => floatval($pedido_data['impuestos'] ?? 0),
                'importe_otros_impuestos' => 0,
                'importe_total' => floatval($pedido_data['total'] ?? 0)
            ];

            $comprobante_id = pven_sync_comprobante($conexion, $comprobante_data, $tabla_origen_id);
            if (!$comprobante_id) {
                throw new Exception('No se pudo obtener/crear el comprobante');
            }

            $sql_upd_comprobante = "UPDATE gestion__ventas_pedidos SET comprobante_id = ? WHERE venta_pedido_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_upd_comprobante);
            mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $venta_pedido_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conexion);

            if (function_exists('auditoria_registrar_operacion')) {
                $accion_aud = (intval($estado_destino_id) === 5 || $accion_js === 'confirmar_pedido') ? 'CONFIRMAR' : 'MODIFICACION';
                auditoria_registrar_operacion(
                    $conexion,
                    $accion_aud,
                    'gestion__ventas_pedidos',
                    $venta_pedido_id,
                    ['tabla_estado_registro_id' => intval($estado_actual_id)],
                    ['tabla_estado_registro_id' => intval($estado_destino_id), 'comprobante_nro' => $pedido_data['comprobante_nro'] ?? null],
                    "Cambio de estado de pedido #{$venta_pedido_id} ({$accion_js}: {$estado_actual_id} -> {$estado_destino_id})"
                );
            }

            $mensaje = 'Estado actualizado correctamente';
            if ($numero_asignado) {
                $mensaje .= ' - Número asignado: ' . $numero_asignado;
            }

            return ['success' => true, 'message' => $mensaje, 'comprobante_nro' => $numero_asignado];

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            error_log("ERROR en ejecutarTransicionEstado: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('pven_agregar')) {
    function pven_agregar($conexion, $data, array $ctx = [])
    {
        $ctx = pven_ctx($ctx);
        if (!$conexion) {
            return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
        }

        if (empty($data['f_emision'])) {
            return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
        }
        if (empty($data['entidad_id'])) {
            return ['resultado' => false, 'error' => 'Debe seleccionar un cliente'];
        }
        if (empty($data['comprobante_tipo_id'])) {
            return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
        }
        if (empty($data['moneda_id'])) {
            return ['resultado' => false, 'error' => 'Debe seleccionar la moneda'];
        }
        if (empty($data['sucursal_id'])) {
            return ['resultado' => false, 'error' => 'Debe seleccionar la sucursal'];
        }
        if (empty($data['punto_venta_id'])) {
            return ['resultado' => false, 'error' => 'Debe seleccionar el punto de venta'];
        }
        if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
        }
        // Venta por presentación: valida y pasa las líneas a unidad base (sin el indicador, no hace nada)
        try {
            $data['detalles'] = vpre_preparar_lineas($conexion, intval($data['empresa_idx'] ?? 0),
                vpre_activo($conexion, intval($data['empresa_idx'] ?? 0), 'pedido') ? intval(pven_condicion_cliente($conexion, intval($data['entidad_id']))['lista_precio_id'] ?? 0) : 0, $data['detalles'], 'pedido');
        } catch (InvalidArgumentException $e) {
            return ['resultado' => false, 'error' => $e->getMessage()];
        }
        // Canales de venta: la sucursal tiene que vender al canal del cliente (sin el indicador, no hace nada)
        try {
            cvta_exigir_venta_sucursal($conexion, intval($data['empresa_idx'] ?? 0), intval($data['sucursal_id'] ?? 0), intval($data['entidad_id'] ?? 0));
        } catch (InvalidArgumentException $e) {
            return ['resultado' => false, 'error' => $e->getMessage()];
        }
        // Reglas de precio de venta: regla y descuento manual de cada línea, para grabarlos aparte (sin el indicador, nada)
        if (isset($data['detalles']) && is_array($data['detalles'])) {
            $data['detalles'] = prv_preparar_lineas($conexion, intval($data['empresa_idx'] ?? 0), [
                'entidad_id' => intval($data['entidad_id'] ?? 0), 'sucursal_id' => intval($data['sucursal_id'] ?? 0),
                'fecha' => $data['f_emision'] ?? null, 'usuario_id' => $ctx['usuario_id'],
            ], $data['detalles'], 'pedido');
        }
        // Vendedores y carteras: vendedor y origen del pedido; el usuario vendedor solo carga clientes de su cartera (sin el indicador, nada)
        try {
            $vcar_pedido = vcar_resolver_pedido($conexion, intval($data['empresa_idx'] ?? 0), $ctx['usuario_id'],
                intval($data['entidad_id'] ?? 0), $data['f_emision'] ?? null, $data, null);
        } catch (InvalidArgumentException $e) {
            return ['resultado' => false, 'error' => $e->getMessage()];
        }

        if ($ctx['accesos'] && file_exists(__DIR__ . '/../accesos_sucursales.php')) {
            require_once __DIR__ . '/../accesos_sucursales.php';
            $empresa_idx_val = intval($data['empresa_idx'] ?? $_SESSION['empresa_id'] ?? 0);
            try {
                AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx_val, intval($data['sucursal_id']));
                AccesosSucursalesHelper::exigirAccesoPuntoVenta($conexion, $empresa_idx_val, intval($data['punto_venta_id']));
            } catch (RuntimeException $e) {
                return ['resultado' => false, 'error' => $e->getMessage()];
            }
        }

        mysqli_begin_transaction($conexion);

        try {
            $estado_inicial = pven_estado_inicial_pagina($conexion, $data['pagina_idx']);
            if (!$estado_inicial) {
                $estado_inicial = 1;
            }

            $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? $data['f_entrega_estimada'] : null;
            $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
            $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : 1.000000;
            $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
            $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
            $punto_venta_id = (!empty($data['punto_venta_id']) && $data['punto_venta_id'] > 0) ? intval($data['punto_venta_id']) : null;

            $direccion_entrega = isset($data['direccion_entrega']) ? trim($data['direccion_entrega']) : '';
            if ($direccion_entrega === '' && $entidad_sucursal_id) {
                $direccion_entrega = pven_direccion_sucursal_entidad($conexion, $entidad_sucursal_id, intval($data['entidad_id'] ?? 0), intval($data['empresa_idx'] ?? 0));
            }
            $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';

            $sql = "INSERT INTO gestion__ventas_pedidos 
                    (empresa_id, sucursal_id, punto_venta_id, comprobante_tipo_id, comprobante_nro, 
                     entidad_id, entidad_sucursal_id, f_emision, f_entrega_estimada, condicion_pago_id, 
                     moneda_id, tipo_cambio, direccion_entrega, subtotal, descuento_general_pct, 
                     descuentos, impuestos, total, observaciones, tabla_estado_registro_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                throw new Exception("Error preparando insert: " . mysqli_error($conexion));
            }

            $empresa_id_val = intval($data['empresa_idx']);
            $sucursal_id_val = $sucursal_id;
            $punto_venta_id_val = $punto_venta_id;
            $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
            $comprobante_nro_val = 0;
            $entidad_id_val = intval($data['entidad_id']);
            $entidad_sucursal_id_val = $entidad_sucursal_id;
            $f_emision_val = $data['f_emision'];
            $f_entrega_estimada_val = $f_entrega_estimada;
            $condicion_pago_id_val = $condicion_pago_id;
            $moneda_id_val = intval($data['moneda_id']);
            $tipo_cambio_val = $tipo_cambio;
            $direccion_entrega_val = $direccion_entrega;
            $subtotal_val = floatval($data['subtotal'] ?? 0);
            // Descuento general del cliente vigente al momento de crear el pedido
            // (gestion__entidades_condiciones_clientes.cliente_descuento_general),
            // enviado desde el frontend. Queda materializado en la cabecera del pedido.
            $descuento_general_pct_val = floatval($data['descuento_general_pct'] ?? 0);
            $descuentos_val = floatval($data['descuentos'] ?? 0);
            $impuestos_val = floatval($data['impuestos'] ?? 0);
            $total_val = floatval($data['total'] ?? 0);
            $observaciones_val = $observaciones;
            $estado_inicial_val = intval($estado_inicial);

            mysqli_stmt_bind_param($stmt, "iiiiiiissiidsdddddsi",
                $empresa_id_val,
                $sucursal_id_val,
                $punto_venta_id_val,
                $comprobante_tipo_id_val,
                $comprobante_nro_val,
                $entidad_id_val,
                $entidad_sucursal_id_val,
                $f_emision_val,
                $f_entrega_estimada_val,
                $condicion_pago_id_val,
                $moneda_id_val,
                $tipo_cambio_val,
                $direccion_entrega_val,
                $subtotal_val,
                $descuento_general_pct_val,
                $descuentos_val,
                $impuestos_val,
                $total_val,
                $observaciones_val,
                $estado_inicial_val
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
            }

            $venta_pedido_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);

            // Sincronizar con gestion__comprobantes (mismo patrón que facturas_proveedores).
            // Requiere que gestion__ventas_pedidos tenga columna comprobante_id (ver DDL pendiente).
            $tabla_origen_id = pven_tabla_origen_pagina($conexion, $data['pagina_idx']);
            if (!$tabla_origen_id) {
                throw new Exception("No se pudo determinar la tabla de origen");
            }

            $comprobante_data = [
                'empresa_id' => $empresa_id_val,
                'sucursal_id' => $sucursal_id_val,
                'comprobante_pv' => $punto_venta_id_val,
                'comprobante_tipo_id' => $comprobante_tipo_id_val,
                'comprobante_nro' => $comprobante_nro_val, // 0 hasta que se confirme y se numere
                'entidad_id' => $entidad_id_val,
                'entidad_sucursal_id' => $entidad_sucursal_id,
                'f_emision' => $f_emision_val,
                'f_contabilidad' => $f_emision_val,
                'f_vto' => null,
                'moneda_id' => $moneda_id_val,
                'tipo_cambio' => $tipo_cambio_val,
                'registro_origen_id' => $venta_pedido_id,
                'tabla_estado_registro_id' => $estado_inicial_val,
                'usuario_id' => $ctx['usuario_id'],
                'observaciones' => $observaciones_val,
                'importe_neto' => $subtotal_val,
                'descuento_general' => $descuentos_val,
                'importe_no_gravado' => 0,
                'importe_exento' => 0,
                'importe_iva' => $impuestos_val,
                'importe_otros_impuestos' => 0,
                'importe_total' => $total_val
            ];

            $comprobante_id = pven_sync_comprobante($conexion, $comprobante_data, $tabla_origen_id);
            if (!$comprobante_id) {
                throw new Exception("Error al sincronizar el comprobante");
            }

            $sql_update_comprobante = "UPDATE gestion__ventas_pedidos SET comprobante_id = ?, f_confirmacion = IF(tabla_estado_registro_id = 5 AND f_confirmacion IS NULL, NOW(), f_confirmacion) WHERE venta_pedido_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_update_comprobante);
            mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $venta_pedido_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $detalles_success = pven_insertar_detalles($conexion, $venta_pedido_id, $data['empresa_idx'], $data['detalles']);

            if (!$detalles_success) {
                throw new Exception("Error al insertar los detalles");
            }
            vcar_guardar_pedido($conexion, (int) $venta_pedido_id, $vcar_pedido);

            mysqli_commit($conexion);
            return ['resultado' => true, 'venta_pedido_id' => $venta_pedido_id, 'comprobante_id' => $comprobante_id];

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            error_log("ERROR en agregarPedidoVenta: " . $e->getMessage());
            return ['resultado' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('pven_direccion_sucursal_entidad')) {
    function pven_direccion_sucursal_entidad($conexion, $entidad_sucursal_id, $entidad_id, $empresa_idx)
    {
        $sql = "SELECT es.sucursal_direccion, l.localidad, p.provincia
                FROM gestion__entidades_sucursales es
                LEFT JOIN conf__localidades l ON l.localidad_id = es.localidad_id
                LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                WHERE es.sucursal_id = ? AND es.entidad_id = ? AND es.empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando dirección de sucursal: " . mysqli_error($conexion));
            return '';
        }
        mysqli_stmt_bind_param($stmt, "iii", $entidad_sucursal_id, $entidad_id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$fila) {
            return '';
        }

        $partes = array_filter(array_map('trim', [
            (string) $fila['sucursal_direccion'],
            (string) $fila['localidad'],
            (string) $fila['provincia'],
        ]), 'strlen');
        return mb_substr(implode(', ', $partes), 0, 255);
    }
}

if (!function_exists('pven_condicion_cliente')) {
    function pven_condicion_cliente($conexion, $entidad_id)
    {
        $entidad_id = intval($entidad_id);

        $sql = "SELECT ecc.lista_precio_id, ecc.condicion_pago_id, 
                       ecc.cliente_descuento_general, ecc.limite_credito
                FROM gestion__entidades_condiciones_clientes ecc
                WHERE ecc.entidad_id = ?
                AND ecc.tabla_estado_registro_id = 1
                AND ecc.f_desde <= CURDATE()
                AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE())
                ORDER BY ecc.f_desde DESC
                LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando consulta condición cliente: " . mysqli_error($conexion));
            return null;
        }

        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return cvta_completar_condicion($conexion, $entidad_id, $row ?: null); // canales de venta: lista unificada (sin el indicador, igual que antes)
    }
}

if (!function_exists('pven_insertar_detalles')) {
    function pven_insertar_detalles($conexion, $venta_pedido_id, $empresa_id, $detalles)
    {
        if (!is_array($detalles) || count($detalles) === 0) {
            return false;
        }

        $insertados = 0;

        foreach ($detalles as $index => $detalle) {
            if (empty($detalle['producto_id'])) {
                return false;
            }

            $cantidad = floatval($detalle['cantidad'] ?? 0);
            $precio_unitario = floatval($detalle['precio_unitario'] ?? 0);

            if ($cantidad <= 0) {
                return false;
            }

            $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : 1;
            $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 21);

            // Descuento general del cliente (gestion__entidades_condiciones_clientes.cliente_descuento_general),
            // aplicado por unidad. Antes se insertaba siempre en 0 aunque el frontend lo calculara.
            $precio_unitario_bruto = $precio_unitario;
            $descuento_general_pct = floatval($detalle['descuento_general_pct'] ?? 0);
            $descuento_general = floatval($detalle['descuento_general'] ?? ($precio_unitario_bruto * $descuento_general_pct / 100));
            $precio_unitario_neto = floatval($detalle['precio_unitario_neto'] ?? ($precio_unitario_bruto - $descuento_general));

            $neto_gravado = floatval($detalle['neto_gravado'] ?? ($cantidad * $precio_unitario_neto));
            $no_gravado = floatval($detalle['no_gravado'] ?? 0);
            $exento = floatval($detalle['exento'] ?? 0);
            $iva_importe = floatval($detalle['iva_importe'] ?? ($neto_gravado * $iva_porcentaje / 100));
            $total_linea = floatval($detalle['total_linea'] ?? ($neto_gravado + $iva_importe + $no_gravado + $exento));

            $sql = "INSERT INTO gestion__ventas_pedidos_detalles 
                    (venta_pedido_id, producto_id, cantidad, cantidad_entregada, 
                     precio_unitario, descuento_item_pct, descuento_general_pct, 
                     descuento_general, descuento_item, precio_unitario_bruto, 
                     precio_unitario_neto, neto_gravado, iva_alicuota_id, iva_porcentaje, 
                     iva_importe, no_gravado, exento, total_linea, tabla_estado_registro_id) 
                    VALUES (?, ?, ?, 0, ?, 0, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                return false;
            }

            // Antes: "iiiddddididid" tipaba cantidad, iva_importe y exento como enteros ('i'),
            // lo que trunca decimales al hacer el bind. Corregido a 'd' en esas 3 posiciones.
            mysqli_stmt_bind_param($stmt, "iidddddddiddddd",
                $venta_pedido_id,
                $detalle['producto_id'],
                $cantidad,
                $precio_unitario,
                $descuento_general_pct,
                $descuento_general,
                $precio_unitario_bruto,
                $precio_unitario_neto,
                $neto_gravado,
                $iva_alicuota_id,
                $iva_porcentaje,
                $iva_importe,
                $no_gravado,
                $exento,
                $total_linea
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $insertados++;
            mysqli_stmt_close($stmt);
            $nuevo_vpd_id = (int) mysqli_insert_id($conexion);
            // Venta por presentación: '_vpre' solo viene con el indicador
            if (!empty($detalle['_vpre'])) {
                vpre_guardar_foto($conexion, 'pedido', (int) mysqli_insert_id($conexion), $detalle['_vpre']);
            }
            // Reglas de precio de venta: '_prv' solo viene con el indicador
            if (!empty($detalle['_prv'])) {
                prv_guardar_linea($conexion, 'pedido', $nuevo_vpd_id, $detalle['_prv'], $detalle['_prv']['_aprobado_por'] ?? null);
            }
        }

        return true;
    }
}

if (!function_exists('pven_estado_inicial_pagina')) {
    function pven_estado_inicial_pagina($conexion, $pagina_id)
    {
        $pagina_id = intval($pagina_id);

        $sql = "SELECT pf.tabla_estado_registro_destino_id 
                FROM conf__paginas_funciones pf
                WHERE pf.pagina_id = ? 
                AND pf.tabla_estado_registro_origen_id = 0
                AND pf.accion_js = 'agregar'
                AND pf.tabla_estado_registro_id = 1
                LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando consulta estado inicial: " . mysqli_error($conexion));
            return 1;
        }

        mysqli_stmt_bind_param($stmt, "i", $pagina_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && $row['tabla_estado_registro_destino_id']) {
            return $row['tabla_estado_registro_destino_id'];
        }

        // Página sin función "agregar" (p. ej. 91, Nuevos Pedidos de Clientes): el estado inicial sale del
        // catálogo de la tabla (es_inicial). Con el fallback fijo en 1 (Activo) el pedido nacía en un estado
        // sin transiciones y no se podía confirmar ni editar.
        $estado_catalogo = intval(pven_estado_inicial_catalogo($conexion, $pagina_id));
        error_log("Sin función 'agregar' para pagina_id=$pagina_id, estado inicial por es_inicial: $estado_catalogo");
        return $estado_catalogo;
    }
}

if (!function_exists('pven_tabla_origen_pagina')) {
    function pven_tabla_origen_pagina($conexion, $pagina_id)
    {
        $pagina_id = intval($pagina_id);
        $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando consulta tabla_id: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "i", $pagina_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && !empty($row['tabla_id'])) {
            return (int)$row['tabla_id'];
        }

        error_log("No se encontró tabla_id para la página $pagina_id");
        return null;
    }
}

if (!function_exists('pven_sync_comprobante')) {
    function pven_sync_comprobante($conexion, $data, $tabla_origen_id)
    {
        if (empty($tabla_origen_id)) {
            error_log("ERROR: tabla_origen_id no proporcionado");
            return null;
        }

        $empresa_id = intval($data['empresa_id'] ?? 0);
        $sucursal_id = !empty($data['sucursal_id']) ? intval($data['sucursal_id']) : null;
        $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
        $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
        $comprobante_nro = intval($data['comprobante_nro'] ?? 0);
        $entidad_id = intval($data['entidad_id'] ?? 0);
        $entidad_sucursal_id = !empty($data['entidad_sucursal_id']) ? intval($data['entidad_sucursal_id']) : null;
        $f_emision = $data['f_emision'] ?? date('Y-m-d');
        $f_contabilidad = $data['f_contabilidad'] ?? $f_emision;
        $f_vto = $data['f_vto'] ?? null;
        $moneda_id = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
        $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.0);
        $registro_origen_id = intval($data['registro_origen_id'] ?? 0);
        $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 3);
        $usuario_id = intval($data['usuario_id'] ?? 0);
        $observaciones = trim($data['observaciones'] ?? '');

        $importe_neto = floatval($data['importe_neto'] ?? 0);
        $descuento_general = floatval($data['descuento_general'] ?? 0);
        $importe_no_gravado = floatval($data['importe_no_gravado'] ?? 0);
        $importe_exento = floatval($data['importe_exento'] ?? 0);
        $importe_iva = floatval($data['importe_iva'] ?? 0);
        $importe_otros_impuestos = floatval($data['importe_otros_impuestos'] ?? 0);
        $importe_total = floatval($data['importe_total'] ?? 0);

        $importe_bruto = $importe_neto + $descuento_general;

        if ($registro_origen_id <= 0) {
            error_log("ERROR: registro_origen_id inválido: $registro_origen_id");
            return null;
        }

        $sql_check = "SELECT comprobante_id FROM gestion__comprobantes 
                      WHERE tabla_origen_id = ? AND registro_origen_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            error_log("Error preparando SELECT: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $registro_origen_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existe = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $comprobante_id = null;

        if ($existe) {
            $comprobante_id = $existe['comprobante_id'];
            $importe_pendiente = $importe_total;

            // C5 / D2 / §2.4: No pisar importe_pendiente si el comprobante ya tiene imputaciones vigentes
            $sql_imp = "SELECT 
                            COALESCE(SUM(CASE WHEN ci.comp_destino_id = ? THEN ci.importe_imputado ELSE 0 END), 0) AS imp_destino,
                            COALESCE(SUM(CASE WHEN ci.comp_origen_id = ? THEN ci.importe_origen ELSE 0 END), 0) AS imp_origen,
                            COUNT(*) AS total_imputaciones
                        FROM gestion__comprobantes_imputaciones ci
                        JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                        WHERE (ci.comp_destino_id = ? OR ci.comp_origen_id = ?)
                          AND er.codigo_estandar = 'CONFIRMADO'";
            $stmt_imp = mysqli_prepare($conexion, $sql_imp);
            if ($stmt_imp) {
                mysqli_stmt_bind_param($stmt_imp, "iiii", $comprobante_id, $comprobante_id, $comprobante_id, $comprobante_id);
                mysqli_stmt_execute($stmt_imp);
                $res_imp = mysqli_stmt_get_result($stmt_imp);
                if ($row_imp = mysqli_fetch_assoc($res_imp)) {
                    if (intval($row_imp['total_imputaciones']) > 0) {
                        $imputado_total = floatval($row_imp['imp_destino']) + floatval($row_imp['imp_origen']);
                        $importe_pendiente = max(0.0, round($importe_total - $imputado_total, 2));
                    }
                }
                mysqli_stmt_close($stmt_imp);
            }

            // Estado derivado del saldo (mismo criterio que estadoComprobantePorSaldo() en
            // tesoreria_core_model.php): un comprobante confirmado con imputaciones vigentes queda en
            // Cancelado parcial (39) / Cancelado total (40) y no vuelve a Confirmado al re-sincronizar.
            if ($tabla_estado_registro_id === 5 && $importe_pendiente < $importe_total - 0.001) {
                $tabla_estado_registro_id = ($importe_pendiente <= 0.001) ? 40 : 39;
            }

            $sql_update = "UPDATE gestion__comprobantes SET
                                empresa_id = ?,
                                sucursal_id = ?,
                                comprobante_pv = ?,
                                comprobante_tipo_id = ?,
                                comprobante_nro = ?,
                                entidad_id = ?,
                                entidad_sucursal_id = ?,
                                f_emision = ?,
                                f_contabilidad = ?,
                                f_vto = ?,
                                moneda_id = ?,
                                tipo_cambio = ?,
                                importe_bruto = ?,
                                descuento_general = ?,
                                importe_no_gravado = ?,
                                importe_exento = ?,
                                importe_neto = ?,
                                importe_iva = ?,
                                importe_otros_impuestos = ?,
                                importe_total = ?,
                                importe_pendiente = ?,
                                tabla_estado_registro_id = ?,
                                observaciones = ?,
                                usuario_modificacion_id = ?
                            WHERE comprobante_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_update);
            if (!$stmt) {
                error_log("Error preparando UPDATE: " . mysqli_error($conexion));
                return null;
            }
            mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddisii",
                $empresa_id, $sucursal_id, $comprobante_pv,
                $comprobante_tipo_id, $comprobante_nro,
                $entidad_id, $entidad_sucursal_id,
                $f_emision, $f_contabilidad, $f_vto,
                $moneda_id, $tipo_cambio,
                $importe_bruto, $descuento_general,
                $importe_no_gravado, $importe_exento,
                $importe_neto, $importe_iva, $importe_otros_impuestos,
                $importe_total,
                $importe_pendiente,
                $tabla_estado_registro_id, $observaciones,
                $usuario_id,
                $comprobante_id
            );
            if (!mysqli_stmt_execute($stmt)) {
                error_log("Error ejecutando UPDATE: " . mysqli_stmt_error($stmt));
                return null;
            }
            mysqli_stmt_close($stmt);
        } else {
            $sql_insert = "INSERT INTO gestion__comprobantes (
                                empresa_id, sucursal_id, comprobante_pv,
                                comprobante_tipo_id, comprobante_nro,
                                entidad_id, entidad_sucursal_id,
                                f_emision, f_contabilidad, f_vto,
                                moneda_id, tipo_cambio,
                                importe_bruto, descuento_general,
                                importe_no_gravado, importe_exento,
                                importe_neto, importe_iva, importe_otros_impuestos,
                                importe_total, importe_pendiente,
                                tabla_origen_id, registro_origen_id,
                                tabla_estado_registro_id,
                                usuario_id,
                                observaciones
                            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = mysqli_prepare($conexion, $sql_insert);
            if (!$stmt) {
                error_log("Error preparando INSERT: " . mysqli_error($conexion));
                return null;
            }
            mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddiiiis",
                $empresa_id, $sucursal_id, $comprobante_pv,
                $comprobante_tipo_id, $comprobante_nro,
                $entidad_id, $entidad_sucursal_id,
                $f_emision, $f_contabilidad, $f_vto,
                $moneda_id, $tipo_cambio,
                $importe_bruto, $descuento_general,
                $importe_no_gravado, $importe_exento,
                $importe_neto, $importe_iva, $importe_otros_impuestos,
                $importe_total,
                $importe_total,
                $tabla_origen_id, $registro_origen_id,
                $tabla_estado_registro_id,
                $usuario_id,
                $observaciones
            );
            if (!mysqli_stmt_execute($stmt)) {
                error_log("Error ejecutando INSERT: " . mysqli_stmt_error($stmt));
                return null;
            }
            $comprobante_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        return $comprobante_id;
    }
}
