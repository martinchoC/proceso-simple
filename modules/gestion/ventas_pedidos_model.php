<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $funciones[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $funciones;
}

function obtenerInfoEstado($conexion, $estado_registro_id)
{
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } else {
        return [
            'estado_registro' => 'Estado ' . $estado_registro_id,
            'codigo_estandar' => 'ESTADO_' . $estado_registro_id
        ];
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $info;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            
            $esConfirmable = 0;
            if ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) {
                $esConfirmable = 1;
            }
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $esConfirmable
            ];
        }
    }

    return $botones;
}

function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);

    // Buscar función con origen_id = 0 (creación)
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            error_log("Función agregar encontrada: " . print_r($funcion, true));
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }

    // Si no se encuentra en la configuración, retornar valores por defecto
    error_log("No se encontró configuración para botón agregar, usando valores por defecto");
    return [
        'nombre_funcion' => 'Nuevo Pedido',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion, $pagina_id)
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

function obtenerProximoNumeroComprobante($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id) {
    $sql_check = "SELECT numerador_id, ultimo_numero 
                  FROM gestion__comprobantes_numeradores 
                  WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?";
    
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

function ejecutarTransicionEstado($conexion, $venta_pedido_id, $accion_js, $empresa_idx, $pagina_id)
{
    $venta_pedido_id = intval($venta_pedido_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT vp.venta_pedido_id, vp.tabla_estado_registro_id, vp.comprobante_nro, 
                         vp.comprobante_tipo_id, vp.punto_venta_id, vp.empresa_id
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
        
        if ($accion_js === 'confirmar') {
            
            if (empty($pedido['punto_venta_id'])) {
                throw new Exception('El pedido no tiene punto de venta asignado. No se puede generar número de comprobante.');
            }
            
            if (!empty($pedido['comprobante_nro']) && $pedido['comprobante_nro'] > 0) {
                $numero_asignado = $pedido['comprobante_nro'];
            } else {
                $proximo_numero = obtenerProximoNumeroComprobante(
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
                       SET tabla_estado_registro_id = ? 
                       WHERE venta_pedido_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            throw new Exception('Error preparando update de estado: ' . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $venta_pedido_id);
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
        mysqli_stmt_bind_param($stmt, "i", $venta_pedido_id);
        mysqli_stmt_execute($stmt);
        $result_pedido = mysqli_stmt_get_result($stmt);
        $pedido_data = mysqli_fetch_assoc($result_pedido);
        mysqli_stmt_close($stmt);

        if (!$pedido_data) {
            throw new Exception('No se pudieron obtener los datos completos del pedido');
        }

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
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
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $pedido_data['observaciones'],
            'importe_neto' => floatval($pedido_data['subtotal'] ?? 0),
            'descuento_general' => floatval($pedido_data['descuentos'] ?? 0),
            'importe_no_gravado' => 0,
            'importe_exento' => 0,
            'importe_iva' => floatval($pedido_data['impuestos'] ?? 0),
            'importe_otros_impuestos' => 0,
            'importe_total' => floatval($pedido_data['total'] ?? 0)
        ];

        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception('No se pudo obtener/crear el comprobante');
        }

        $sql_upd_comprobante = "UPDATE gestion__ventas_pedidos SET comprobante_id = ? WHERE venta_pedido_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_upd_comprobante);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $venta_pedido_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        
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

function obtenerPedidosVenta($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }

    $sql = "SELECT vp.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   m.moneda, m.simbolo,
                   s.sucursal_nombre,
                   pv.nombre as punto_venta_nombre, pv.codigo_fiscal as punto_venta_codigo
            FROM gestion__ventas_pedidos vp
            LEFT JOIN conf__estados_registros er ON vp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vp.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON vp.moneda_id = m.moneda_id
            LEFT JOIN gestion__sucursales s ON vp.sucursal_id = s.sucursal_id AND s.empresa_id = vp.empresa_id
            LEFT JOIN gestion__puntos_venta pv ON vp.punto_venta_id = pv.punto_venta_id AND pv.empresa_id = vp.empresa_id
            WHERE vp.empresa_id = ?
            ORDER BY vp.f_emision DESC, vp.venta_pedido_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function agregarPedidoVenta($conexion, $data)
{
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

    mysqli_begin_transaction($conexion);

    try {
        $estado_inicial = obtenerEstadoInicialPagina($conexion, $data['pagina_idx']);
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
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $data['pagina_idx']);
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
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $observaciones_val,
            'importe_neto' => $subtotal_val,
            'descuento_general' => $descuentos_val,
            'importe_no_gravado' => 0,
            'importe_exento' => 0,
            'importe_iva' => $impuestos_val,
            'importe_otros_impuestos' => 0,
            'importe_total' => $total_val
        ];

        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception("Error al sincronizar el comprobante");
        }

        $sql_update_comprobante = "UPDATE gestion__ventas_pedidos SET comprobante_id = ? WHERE venta_pedido_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update_comprobante);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $venta_pedido_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $detalles_success = insertarDetallesPedido($conexion, $venta_pedido_id, $data['empresa_idx'], $data['detalles']);
        
        if (!$detalles_success) {
            throw new Exception("Error al insertar los detalles");
        }

        mysqli_commit($conexion);
        return ['resultado' => true, 'venta_pedido_id' => $venta_pedido_id, 'comprobante_id' => $comprobante_id];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarPedidoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarPedidoVenta($conexion, $id, $data)
{
    $id = intval($id);

    mysqli_begin_transaction($conexion);

    try {
        $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? $data['f_entrega_estimada'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.000000);
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        $punto_venta_id = (!empty($data['punto_venta_id']) && $data['punto_venta_id'] > 0) ? intval($data['punto_venta_id']) : null;
        $direccion_entrega = isset($data['direccion_entrega']) ? trim($data['direccion_entrega']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
        
        $comprobante_nro = intval($data['comprobante_nro'] ?? 0);

        $sql = "UPDATE gestion__ventas_pedidos 
                SET sucursal_id = ?,
                    punto_venta_id = ?,
                    comprobante_tipo_id = ?, 
                    comprobante_nro = ?, 
                    entidad_id = ?, 
                    entidad_sucursal_id = ?, 
                    f_emision = ?, 
                    f_entrega_estimada = ?, 
                    condicion_pago_id = ?, 
                    moneda_id = ?, 
                    tipo_cambio = ?, 
                    direccion_entrega = ?, 
                    subtotal = ?, 
                    descuento_general_pct = ?,
                    descuentos = ?, 
                    impuestos = ?, 
                    total = ?, 
                    observaciones = ?
                WHERE venta_pedido_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $sucursal_id_val = $sucursal_id;
        $punto_venta_id_val = $punto_venta_id;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_nro_val = $comprobante_nro;
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_entrega_estimada_val = $f_entrega_estimada;
        $condicion_pago_id_val = $condicion_pago_id;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = $tipo_cambio;
        $direccion_entrega_val = $direccion_entrega;
        $subtotal_val = floatval($data['subtotal'] ?? 0);
        $descuento_general_pct_val = floatval($data['descuento_general_pct'] ?? 0);
        $descuentos_val = floatval($data['descuentos'] ?? 0);
        $impuestos_val = floatval($data['impuestos'] ?? 0);
        $total_val = floatval($data['total'] ?? 0);
        $observaciones_val = $observaciones;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        // Antes "iiiiiissiiddddddsii" tipaba direccion_entrega (VARCHAR) como 'd' (double),
        // corrompiendo cualquier dirección no numérica a "0". Corregido a 's' en esa posición.
        mysqli_stmt_bind_param($stmt, 
            "iiiiiissiidsdddddsii",
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
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Re-sincronizar gestion__comprobantes con los datos actualizados del pedido
        $sql_estado = "SELECT tabla_estado_registro_id, comprobante_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_estado);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result_estado = mysqli_stmt_get_result($stmt);
        $row_estado = mysqli_fetch_assoc($result_estado);
        $estado_actual_id = $row_estado['tabla_estado_registro_id'];
        $comprobante_id_existente = $row_estado['comprobante_id'];
        mysqli_stmt_close($stmt);

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $data['pagina_idx'] ?? 65);
        if (!$tabla_origen_id) {
            throw new Exception("No se pudo determinar la tabla de origen");
        }

        $comprobante_data = [
            'empresa_id' => $empresa_idx_val,
            'sucursal_id' => $sucursal_id_val,
            'comprobante_pv' => $punto_venta_id_val,
            'comprobante_tipo_id' => $comprobante_tipo_id_val,
            'comprobante_nro' => $comprobante_nro_val,
            'entidad_id' => $entidad_id_val,
            'entidad_sucursal_id' => $entidad_sucursal_id,
            'f_emision' => $f_emision_val,
            'f_contabilidad' => $f_emision_val,
            'f_vto' => null,
            'moneda_id' => $moneda_id_val,
            'tipo_cambio' => $tipo_cambio_val,
            'registro_origen_id' => $id,
            'tabla_estado_registro_id' => $estado_actual_id,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $observaciones_val,
            'importe_neto' => $subtotal_val,
            'descuento_general' => $descuentos_val,
            'importe_no_gravado' => 0,
            'importe_exento' => 0,
            'importe_iva' => $impuestos_val,
            'importe_otros_impuestos' => 0,
            'importe_total' => $total_val
        ];

        $nuevo_comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$nuevo_comprobante_id) {
            throw new Exception("Error al sincronizar el comprobante");
        }

        if ($comprobante_id_existente != $nuevo_comprobante_id) {
            $sql_upd = "UPDATE gestion__ventas_pedidos SET comprobante_id = ? WHERE venta_pedido_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_upd);
            mysqli_stmt_bind_param($stmt, "ii", $nuevo_comprobante_id, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $sql_delete = "DELETE FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if (!$stmt_delete) {
            throw new Exception("Error preparando delete de detalles: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Error eliminando detalles existentes: " . mysqli_stmt_error($stmt_delete));
        }
        mysqli_stmt_close($stmt_delete);
        
        if (isset($data['detalles']) && is_array($data['detalles']) && count($data['detalles']) > 0) {
            $detalles_success = insertarDetallesPedido($conexion, $id, $empresa_idx_val, $data['detalles']);
            
            if (!$detalles_success) {
                throw new Exception("Error al insertar los nuevos detalles");
            }
        } else {
            throw new Exception("Debe haber al menos un detalle en el pedido");
        }

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Pedido actualizado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarPedidoVenta: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerPedidoVentaPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }

    $sql = "SELECT vp.*, vp.sucursal_id, vp.punto_venta_id,
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   m.moneda, m.simbolo
            FROM gestion__ventas_pedidos vp
            LEFT JOIN conf__estados_registros er ON vp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON vp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON vp.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON vp.moneda_id = m.moneda_id
            WHERE vp.venta_pedido_id = ? AND vp.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pedido = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$pedido) {
        return null;
    }

    $sql_detalles = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                            p.iva_alicuota_id as producto_iva_id,
                            iva.porcentaje as iva_porcentaje
                     FROM gestion__ventas_pedidos_detalles d
                     LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                     LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
                     WHERE d.venta_pedido_id = ?
                     ORDER BY d.venta_pedido_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt)
        return $pedido;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'venta_pedido_detalle_id' => $detalle['venta_pedido_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_codigo' => $detalle['producto_codigo'],
            'producto_nombre' => $detalle['producto_nombre'],
            'cantidad' => floatval($detalle['cantidad']),
            'cantidad_entregada' => floatval($detalle['cantidad_entregada'] ?? 0),
            'precio_unitario' => floatval($detalle['precio_unitario']),
            'no_gravado' => floatval($detalle['no_gravado'] ?? 0),
            'exento' => floatval($detalle['exento'] ?? 0),
            'iva_alicuota_id' => $detalle['producto_iva_id'] ?? $detalle['iva_alicuota_id'] ?? 1,
            'iva_porcentaje' => floatval($detalle['iva_porcentaje'] ?? 21),
            'neto_gravado' => floatval($detalle['neto_gravado']),
            'iva_importe' => floatval($detalle['iva_importe']),
            'total_linea' => floatval($detalle['total_linea']),
            'descuento_general_pct' => floatval($detalle['descuento_general_pct'] ?? 0),
            'descuento_general' => floatval($detalle['descuento_general'] ?? 0),
            'precio_unitario_neto' => floatval($detalle['precio_unitario_neto'] ?? $detalle['precio_unitario'])
        ];
    }
    mysqli_stmt_close($stmt);

    $pedido['detalles'] = $detalles;
    return $pedido;
}

function obtenerComprobantesTipos($conexion)
{
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra 
            FROM gestion__comprobantes_tipos 
            WHERE comprobante_grupo_id = 1 
            AND comprobante_subgrupo_id = 17
            AND tabla_estado_registro_id = 1 
            ORDER BY comprobante_tipo";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    return $tipos;
}

function obtenerClientes($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia 
            FROM gestion__entidades 
            WHERE empresa_id = ? 
            AND es_cliente = 1
            AND tabla_estado_registro_id = 1 
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $clientes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $clientes[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $clientes;
}

function obtenerSucursales($conexion, $entidad_id, $empresa_idx)
{
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__entidades_sucursales 
            WHERE entidad_id = ? 
            AND empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $sucursales;
}

function obtenerCondicionesPago($conexion, $empresa_idx)
{
    $sql = "SELECT condicion_pago_id, codigo, condicion_pago, tipo 
            FROM gestion__condiciones_pago 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY orden, condicion_pago";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $condiciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $condiciones[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $condiciones;
}

function obtenerMonedas($conexion, $empresa_idx)
{
    $sql = "SELECT moneda_id, codigo, moneda, simbolo, es_moneda_base, cotizacion_actual 
            FROM gestion__monedas 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY es_moneda_base DESC, orden, moneda";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta monedas: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $monedas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $monedas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $monedas;
}

// Resuelve la condición comercial vigente del cliente (lista de precios, condición de pago,
// descuento general, límite de crédito) desde gestion__entidades_condiciones_clientes.
// Nota: esta tabla no tiene empresa_id propio; la empresa se resuelve vía entidad_id.
function obtenerListaPrecioVigenteCliente($conexion, $entidad_id)
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

    return $row ?: null;
}

function obtenerCondicionesCliente($conexion, $entidad_id, $empresa_idx)
{
    return obtenerListaPrecioVigenteCliente($conexion, $entidad_id);
}

// Antes filtraba por gestion__productos_clientes (INNER JOIN), una tabla de "código de
// cliente" opcional, no de habilitación de compra. Corregido para resolver los productos
// desde la lista de precios vigente asignada al cliente (gestion__entidades_condiciones_clientes
// -> gestion__listas_precios_productos), que es la fuente real de "qué puede comprar y a qué precio".
function obtenerProductosPorCliente($conexion, $empresa_idx, $entidad_id)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);

    $condicion = obtenerListaPrecioVigenteCliente($conexion, $entidad_id);
    if (!$condicion || empty($condicion['lista_precio_id'])) {
        error_log("Cliente $entidad_id sin lista de precios vigente en gestion__entidades_condiciones_clientes");
        return [];
    }
    $lista_precio_id = intval($condicion['lista_precio_id']);

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   lp.precio_final
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
            WHERE lp.lista_precio_id = ?
            AND lp.empresa_id = ?
            AND p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND lp.tabla_estado_registro_id = 1
            AND lp.f_desde <= CURDATE()
            AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())
            ORDER BY p.producto_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos cliente: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $productos;
}

function obtenerCategoriasProductos($conexion, $empresa_idx)
{
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre as categoria_nombre
            FROM gestion__productos_categorias 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY producto_categoria_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $categorias = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $categorias[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $categorias;
}

function obtenerUnidadesMedida($conexion)
{
    $sql = "SELECT unidad_medida_id, unidad_nombre 
            FROM conf__unidades_medida 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY unidad_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $unidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $unidades[] = $fila;
    }
    
    return $unidades;
}

function agregarProductoRapido($conexion, $data)
{
    if (empty($data['producto_codigo']) || empty($data['producto_nombre'])) {
        return ['success' => false, 'error' => 'Código y nombre son obligatorios'];
    }
    
    if (empty($data['producto_categoria_id'])) {
        return ['success' => false, 'error' => 'La categoría es obligatoria'];
    }
    
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos 
                  WHERE producto_codigo = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "si", $data['producto_codigo'], $data['empresa_idx']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['success' => false, 'error' => 'Ya existe un producto con este código'];
    }
    
    $sql = "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, codigo_barras, 
             producto_descripcion, producto_categoria_id, producto_tipo_id, 
             iva_alicuota_id, unidad_medida_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 1)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "issssiii", 
        $data['empresa_idx'],
        $data['producto_codigo'],
        $data['producto_nombre'],
        $data['codigo_barras'],
        $data['producto_descripcion'],
        $data['producto_categoria_id'],
        $data['iva_alicuota_id'],
        $data['unidad_medida_id']
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $producto_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        // Nota: el producto se crea en gestion__productos pero no queda automáticamente
        // en ninguna gestion__listas_precios_productos. Hasta que se le cargue un precio
        // en la lista correspondiente, buscarProductosPorCliente/obtenerProductosPorCliente
        // no lo van a mostrar para ningún cliente (por diseño: sin precio de lista no
        // debería poder venderse). Si esto molesta en el uso diario, avisame y vemos cómo
        // resolverlo (¿cargar precio en el mismo modal? ¿advertencia post-alta?).
        return ['success' => true, 'producto_id' => $producto_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Error al crear el producto'];
    }
}

function buscarProductosPorCliente($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);
    $q = mysqli_real_escape_string($conexion, $q);

    $condicion = obtenerListaPrecioVigenteCliente($conexion, $entidad_id);
    if (!$condicion || empty($condicion['lista_precio_id'])) {
        error_log("Cliente $entidad_id sin lista de precios vigente en gestion__entidades_condiciones_clientes");
        return [];
    }
    $lista_precio_id = intval($condicion['lista_precio_id']);

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   lp.precio_final
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
            WHERE lp.lista_precio_id = ?
            AND lp.empresa_id = ?
            AND p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND lp.tabla_estado_registro_id = 1
            AND lp.f_desde <= CURDATE()
            AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.compatibilidad_busqueda LIKE ?)
            ORDER BY p.producto_nombre
            LIMIT 20";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    $search = "%$q%";
    mysqli_stmt_bind_param($stmt, "iiisss", $lista_precio_id, $empresa_idx, $empresa_idx, $search, $search, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

// Antes solo miraba el último precio_unitario usado en pedidos previos a este cliente.
// Ahora prioriza el precio_final vigente de la lista de precios del cliente (fuente
// canónica) y cae al último precio usado solo si el producto no está en su lista vigente.
function obtenerUltimoPrecioProducto($conexion, $producto_id, $entidad_id, $empresa_id)
{
    $producto_id = intval($producto_id);
    $entidad_id = intval($entidad_id);
    $empresa_id = intval($empresa_id);

    $condicion = obtenerListaPrecioVigenteCliente($conexion, $entidad_id);
    if ($condicion && !empty($condicion['lista_precio_id'])) {
        $lista_precio_id = intval($condicion['lista_precio_id']);
        $sql = "SELECT precio_final 
                FROM gestion__listas_precios_productos
                WHERE lista_precio_id = ? AND producto_id = ? AND empresa_id = ?
                AND tabla_estado_registro_id = 1
                AND f_desde <= CURDATE()
                AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                ORDER BY f_desde DESC
                LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $producto_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            if ($row) {
                return ['success' => true, 'precio' => $row['precio_final'], 'origen' => 'lista_precio'];
            }
        }
    }

    // Fallback: último precio usado en pedidos anteriores a este cliente
    $sql = "SELECT precio_unitario 
            FROM gestion__ventas_pedidos_detalles d
            INNER JOIN gestion__ventas_pedidos v ON d.venta_pedido_id = v.venta_pedido_id
            WHERE d.producto_id = ? 
            AND v.entidad_id = ? 
            AND v.empresa_id = ?
            ORDER BY v.venta_pedido_id DESC 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false];
    
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        return ['success' => true, 'precio' => $row['precio_unitario'], 'origen' => 'ultimo_pedido'];
    }
    

    return ['success' => false];
}

function insertarDetallesPedido($conexion, $venta_pedido_id, $empresa_id, $detalles)
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
    }
    
    return true;
}

function obtenerSucursalesEmpresa($conexion, $empresa_idx) {
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__sucursales 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta sucursales: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $sucursales;
}


// ============================================================
// INTEGRACIÓN CON EL MOTOR DE COMPROBANTES
// (funciones alineadas con el patrón de facturas_proveedores_model.php;
//  candidatas a extraerse a un helper compartido para no duplicar
//  esta lógica en cada módulo)
// ============================================================

// Reemplaza a obtenerEstadoInicial($conexion, $pagina_id): unifica el criterio
// de "estado inicial" con el que ya usa facturas_proveedores (botón agregar,
// origen_id = 0, accion_js = 'agregar'), en vez de leer es_inicial de
// conf__tablas_estados_registros.
function obtenerEstadoInicialPagina($conexion, $pagina_id)
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

    error_log("No se encontró configuración de estado inicial para pagina_id=$pagina_id, usando fallback: 1");
    return 1;
}

function obtenerTablaOrigenPorPagina($conexion, $pagina_id)
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

// Copia funcional de syncComprobante() tal como está en facturas_proveedores_model.php.
// Se duplica aquí a propósito para no introducir un include cruzado entre módulos;
// si se resuelve extraer un helper común, esta función debería eliminarse de acá.
function syncComprobante($conexion, $data, $tabla_origen_id)
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
    $moneda_id = intval($data['moneda_id'] ?? 1);
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
        mysqli_stmt_bind_param($stmt, "iiiiiiisssiddddddddddiisi",
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