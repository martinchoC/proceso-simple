<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/precios_core_model.php';
// ========== FUNCIONES AUXILIARES (AGREGAR ESTAS FUNCIONES AL INICIO) ==========
function obtenerAnioMes($fecha) {
    $timestamp = strtotime($fecha);
    return [
        'anio' => date('Y', $timestamp),
        'mes' => date('m', $timestamp)
    ];
}

// Función para formatear números (si la necesitas)
function formatearNumero($numero, $decimales = 2) {
    return number_format($numero, $decimales, ',', '.');
}

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
        // El botón Agregar (origen_id = 0) se maneja aparte
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            continue;
        }
        
        // Solo mostrar botones cuyo origen coincide con el estado actual del registro
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $es_confirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_origen_id' => $funcion['tabla_estado_registro_origen_id'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $es_confirmable
            ];
        }
    }
    
    return $botones;
}

function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
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

    return [
        'nombre_funcion' => 'Nueva Factura',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return 1;
    }

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

function ejecutarTransicionEstado($conexion, $factura_proveedor_id, $accion_js, $empresa_idx, $pagina_id, $motivo = '')
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $pagina_id = intval($pagina_id);
    
    error_log("=== ejecutarTransicionEstado INICIO ===");
    error_log("factura_proveedor_id: $factura_proveedor_id, accion_js: $accion_js");

    // Obtener estado actual de la factura
    $sql_check = "SELECT factura_proveedor_id, tabla_estado_registro_id
                  FROM gestion__facturas_proveedores
                  WHERE factura_proveedor_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $factura = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$factura)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $factura['tabla_estado_registro_id'];
    error_log("Estado actual ID: $estado_actual_id");

    // Buscar función de transición
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion)
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];
    error_log("Estado destino ID: $estado_destino_id");

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    // INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conexion);
    
    try {
        // Actualizar estado de la factura
        $sql_update = "UPDATE gestion__facturas_proveedores SET tabla_estado_registro_id = ? WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // ============================================================
        // Obtener TODOS los datos de la factura para syncComprobante
        // ============================================================
        $sql_factura_completa = "SELECT 
                                    empresa_id, sucursal_id, comprobante_tipo_id, 
                                    comprobante_pv, comprobante_nro, entidad_id, entidad_sucursal_id,
                                    f_emision, f_contabilidad, f_vencimiento, moneda_id, tipo_cambio,
                                    subtotal, descuentos, no_gravado, exento, impuestos, otros_impuestos, total,
                                    observaciones
                                 FROM gestion__facturas_proveedores
                                 WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_factura_completa);
        mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        $result_factura = mysqli_stmt_get_result($stmt);
        $factura_data = mysqli_fetch_assoc($result_factura);
        mysqli_stmt_close($stmt);

        if (!$factura_data) {
            throw new Exception('No se pudieron obtener los datos completos de la factura');
        }

        // Obtener tabla_origen_id desde la página
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) {
            throw new Exception('No se pudo determinar la tabla de origen');
        }

        // Preparar datos para syncComprobante
        $comprobante_data = [
            'empresa_id' => $factura_data['empresa_id'],
            'sucursal_id' => $factura_data['sucursal_id'],
            'comprobante_pv' => $factura_data['comprobante_pv'],
            'comprobante_tipo_id' => $factura_data['comprobante_tipo_id'],
            'comprobante_nro' => $factura_data['comprobante_nro'],
            'entidad_id' => $factura_data['entidad_id'],
            'entidad_sucursal_id' => $factura_data['entidad_sucursal_id'],
            'f_emision' => $factura_data['f_emision'],
            'f_contabilidad' => $factura_data['f_contabilidad'] ?? $factura_data['f_emision'],
            'f_vto' => $factura_data['f_vencimiento'],
            'moneda_id' => $factura_data['moneda_id'],
            'tipo_cambio' => $factura_data['tipo_cambio'],
            'registro_origen_id' => $factura_proveedor_id,
            'tabla_estado_registro_id' => $estado_destino_id,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $factura_data['observaciones'],
            // Campos financieros
            'importe_neto' => floatval($factura_data['subtotal'] ?? 0),
            'descuento_general' => floatval($factura_data['descuentos'] ?? 0),
            'importe_no_gravado' => floatval($factura_data['no_gravado'] ?? 0),
            'importe_exento' => floatval($factura_data['exento'] ?? 0),
            'importe_iva' => floatval($factura_data['impuestos'] ?? 0),
            'importe_otros_impuestos' => floatval($factura_data['otros_impuestos'] ?? 0),
            'importe_total' => floatval($factura_data['total'] ?? 0)
        ];

        // Sincronizar comprobante (se pasa el tercer argumento)
        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception('No se pudo obtener/crear el comprobante');
        }

        // Actualizar el comprobante_id en la factura si es necesario
        $sql_upd_comprobante = "UPDATE gestion__facturas_proveedores SET comprobante_id = ? WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_upd_comprobante);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Obtener el tipo de comprobante (necesario para stock)
        $comprobante_tipo_id = $factura_data['comprobante_tipo_id'];

        // Verificar si el estado destino es CONFIRMADO
        $estado_info = obtenerInfoEstado($conexion, $estado_destino_id);
        $es_confirmado = $estado_info && $estado_info['codigo_estandar'] === 'CONFIRMADO';

        // Stock: lo decide el servicio según el tipo (stock_signo) y el estado (aplica_stock). Es
        // idempotente, así que va en toda transición: confirmar ingresa, eliminar una confirmada revierte.
        $stock_result = registrarStockFacturaProveedor($conexion, $factura_proveedor_id, $comprobante_id, $estado_destino_id, $tabla_origen_id, $es_confirmado);
        if (!$stock_result['success']) {
            throw new Exception('Error al registrar stock: ' . $stock_result['message']);
        }

        // OC vinculadas: lo recibido = líneas de facturas confirmadas; estado derivado 41 / 8.
        ocRecalcularRecepcionPorFactura($conexion, $factura_proveedor_id);

        // Anular una confirmada (5 -> 38): OP automática, imputaciones, asiento y comprobante
        $mensajes_anulacion = [];
        if (intval($estado_destino_id) === 38) {
            $mensajes_anulacion = fpAnularComprobanteConfirmado($conexion, $factura_proveedor_id, $comprobante_id, $empresa_idx,
                                                                intval($_SESSION['usuario_id'] ?? 0), $motivo);
        }

        if ($es_confirmado) {

            // Asiento contable dentro de la misma transacción: si no se puede generar, no se confirma
            $asiento_result = generarAsientoContableFactura($conexion, $factura_proveedor_id, $empresa_idx, true);
            if (!$asiento_result['success']) {
                throw new Exception('No se pudo generar el asiento contable: ' . $asiento_result['message']);
            }
        }

        // Confirmada: una NC se aplica a su comprobante asociado (OP en $0)
        $post = ['mensajes' => $mensajes_anulacion];
        if ($es_confirmado) {
            $post = fpPostConfirmacion($conexion, $factura_proveedor_id, $empresa_idx, intval($_SESSION['usuario_id'] ?? 0));
        }

        mysqli_commit($conexion);

        // Lista del proveedor / listas de venta según Parámetros de Empresa: después del commit,
        // porque esas escrituras abren su propia transacción (ver fpActualizarPreciosPorCompra).
        if ($es_confirmado) {
            $precios = fpActualizarPreciosPorCompra($conexion, $factura_proveedor_id, $empresa_idx, intval($_SESSION['usuario_id'] ?? 0));
            $post['mensajes'] = array_merge($post['mensajes'] ?? [], $precios['mensajes']);
        }

        $mensaje = 'Estado actualizado y stock registrado correctamente';
        if (!empty($post['mensajes'])) $mensaje .= '. ' . implode(' ', $post['mensajes']);
        return ['success' => true, 'message' => $mensaje,
                'notas_credito_disponibles' => $es_confirmado ? fpNotasCreditoDisponiblesFactura($conexion, $factura_proveedor_id, $empresa_idx) : null];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en ejecutarTransicionEstado: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function obtenerFacturasProveedor($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT fp.*, 
               er.$estado_column as estado_registro, 
               er.codigo_estandar,
               c.color_clase, c.bg_clase, c.text_clase,
               ct.comprobante_tipo,
               e.entidad_nombre, e.entidad_fantasia,
               m.moneda, m.simbolo,
               s.sucursal_nombre,
               d.boca_nombre AS deposito_nombre
        FROM gestion__facturas_proveedores fp
        LEFT JOIN conf__estados_registros er ON fp.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN conf__colores c ON er.color_id = c.color_id
        LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
        LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
        LEFT JOIN gestion__monedas m ON fp.moneda_id = m.moneda_id
        LEFT JOIN gestion__sucursales s ON fp.sucursal_id = s.sucursal_id AND fp.empresa_id = s.empresa_id
        LEFT JOIN gestion__bocas d ON fp.deposito_id = d.boca_id AND fp.empresa_id = d.empresa_id
        WHERE fp.empresa_id = ?";

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $uid = intval($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_idx, 'fp.sucursal_id');
        }
    }

    $sql .= " ORDER BY fp.f_emision DESC, fp.factura_proveedor_id DESC";

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

function agregarFacturaProveedor($conexion, $data)
{
    error_log("=== INICIO agregarFacturaProveedor ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        error_log("Error: Conexión a BD no disponible");
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    // Validaciones básicas
    if (empty($data['comprobante_nro']) && $data['comprobante_nro'] !== '0') {
        return ['resultado' => false, 'error' => 'El número de comprobante es obligatorio'];
    }
    if (empty($data['f_emision'])) {
        return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
    }
    if (empty($data['entidad_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un proveedor'];
    }
    if (empty($data['comprobante_tipo_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
    }
    if (empty($data['moneda_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la moneda'];
    }
    if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
    }
     $pagina_idx = isset($data['pagina_idx']) ? intval($data['pagina_idx']) : 57;
    $estado_inicial = obtenerEstadoInicialPagina($conexion, $pagina_idx);
    error_log("Estado inicial para nueva factura: $estado_inicial");
    mysqli_begin_transaction($conexion);

    try {
        // Verificar duplicados (incluyendo proveedor / entidad_id)
        $sql_check = "SELECT COUNT(*) as total FROM gestion__facturas_proveedores 
                      WHERE comprobante_pv = ? AND comprobante_nro = ? AND comprobante_tipo_id = ? AND empresa_id = ? AND entidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        }

        $comprobante_pv_check = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_check = intval($data['comprobante_nro'] ?? 0);
        $comprobante_tipo_id_check = intval($data['comprobante_tipo_id'] ?? 0);
        $empresa_idx_check = intval($data['empresa_idx'] ?? 0);
        $entidad_id_check = intval($data['entidad_id'] ?? 0);

        mysqli_stmt_bind_param($stmt, "iiiii", 
            $comprobante_pv_check,
            $comprobante_nro_check,
            $comprobante_tipo_id_check,
            $empresa_idx_check,
            $entidad_id_check
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe una factura de este proveedor con este número de comprobante');
        }

        // Obtener estado inicial
        $pagina_idx = isset($data['pagina_idx']) ? intval($data['pagina_idx']) : 57;
        $estado_inicial = obtenerEstadoInicialPagina($conexion, $pagina_idx);
        if (!$estado_inicial) {
            $estado_inicial = 3; // Fallback a "Borrador" (ajustar según necesidades)
        }

        // Manejar valores NULL
        $f_vencimiento = (!empty($data['f_vencimiento'])) ? $data['f_vencimiento'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : 1.000000;
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        $descuento_general_pct = isset($data['descuento_general_pct']) ? floatval($data['descuento_general_pct']) : null;

        if ($sucursal_id !== null && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx_check, $sucursal_id);
        }
        
        $direccion = isset($data['direccion']) ? trim($data['direccion']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        // Insertar factura - CORREGIDO con 23 columnas
       // Insertar factura con descuento_general_pct
        $sql = "INSERT INTO gestion__facturas_proveedores 
                (empresa_id, sucursal_id, deposito_id, comprobante_tipo_id, comprobante_pv, comprobante_nro, 
                 entidad_id, entidad_sucursal_id, f_emision, f_contabilidad, f_vencimiento, f_entrega_estimada,
                 condicion_pago_id, moneda_id, tipo_cambio, direccion_entrega, 
                 subtotal, no_gravado, exento, descuentos, impuestos, total, 
                 descuento_general_pct, observaciones, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        // Asignar variables
        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = $sucursal_id;
        $deposito_id_val = isset($data['deposito_id']) ? intval($data['deposito_id']) : null;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_val = intval($data['comprobante_nro'] ?? 0);
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_contabilidad_val = $data['f_contabilidad'] ?? $data['f_emision'] ?? '';
        $f_vencimiento_val = $f_vencimiento;
        $f_entrega_estimada_val = null;
        $condicion_pago_id_val = $condicion_pago_id;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = $tipo_cambio;
        $direccion_val = $direccion;
        $subtotal_val = floatval($data['subtotal'] ?? 0);
        $no_gravado_val = floatval($data['no_gravado'] ?? 0);
        $exento_val = floatval($data['exento'] ?? 0);
        $descuentos_val = floatval($data['descuentos'] ?? 0);
        $impuestos_val = floatval($data['impuestos'] ?? 0);
        $total_val = floatval($data['total'] ?? 0);
        $descuento_general_pct_val = $descuento_general_pct;
        $observaciones_val = $observaciones;
        $estado_inicial_val = intval($estado_inicial);

        // Cadena de tipos: 23 parámetros
        $types = "iiiiiiiissssiidsdddddddsi";
        mysqli_stmt_bind_param($stmt, $types,
            $empresa_id_val,
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_contabilidad_val,
            $f_vencimiento_val,
            $f_entrega_estimada_val,
            $condicion_pago_id_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $direccion_val,
            $subtotal_val,
            $no_gravado_val,
            $exento_val,
            $descuentos_val,
            $impuestos_val,
            $total_val,
            $descuento_general_pct_val,
            $observaciones_val,
            $estado_inicial_val
        );

        

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $factura_proveedor_id = mysqli_insert_id($conexion);
        error_log("Factura creada con ID: " . $factura_proveedor_id);
        mysqli_stmt_close($stmt);

       // Obtener tabla_origen_id
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $data['pagina_idx']);
        if (!$tabla_origen_id) {
            throw new Exception("No se pudo determinar la tabla de origen");
        }

        // Preparar datos para syncComprobante
        $comprobante_data = [
            'empresa_id' => $empresa_id_val,
            'sucursal_id' => $sucursal_id_val,
            'comprobante_pv' => $comprobante_pv_val,
            'comprobante_tipo_id' => $comprobante_tipo_id_val,
            'comprobante_nro' => $comprobante_nro_val,
            'entidad_id' => $entidad_id_val,
            'entidad_sucursal_id' => $entidad_sucursal_id,
            'f_emision' => $f_emision_val,
            'f_contabilidad' => $f_contabilidad_val,
            'f_vto' => $f_vencimiento_val,
            'moneda_id' => $moneda_id_val,
            'tipo_cambio' => $tipo_cambio_val,
            'registro_origen_id' => $factura_proveedor_id,
            'tabla_estado_registro_id' => $estado_inicial,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $observaciones_val,
            // Mapeo de campos financieros
            'importe_neto' => floatval($data['subtotal'] ?? 0),
            'descuento_general' => floatval($data['descuentos'] ?? 0),
            'importe_no_gravado' => floatval($data['no_gravado'] ?? 0),
            'importe_exento' => floatval($data['exento'] ?? 0),
            'importe_iva' => floatval($data['impuestos'] ?? 0),
            'importe_otros_impuestos' => floatval($data['otros_impuestos'] ?? 0),
            'importe_total' => floatval($data['total'] ?? 0)
        ];

        $comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$comprobante_id) {
            throw new Exception("Error al sincronizar el comprobante");
        }

        // Actualizar la factura con el comprobante_id
        $sql_update_comprobante = "UPDATE gestion__facturas_proveedores SET comprobante_id = ? WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update_comprobante);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        error_log("Factura actualizada con comprobante_id = $comprobante_id");


        // Vínculo con la orden de compra (opcional)
        list($orden_compra_id, $data['detalles']) = fpValidarVinculoOC(
            $conexion, $empresa_id_val, $entidad_id_val, $data['orden_compra_id'] ?? 0, $data['detalles']);
        fpGuardarOrdenCompraFactura($conexion, $factura_proveedor_id, $orden_compra_id);
        fpGuardarComprobanteAsociado($conexion, $empresa_id_val, $factura_proveedor_id, $comprobante_tipo_id_val, $entidad_id_val,
                                     intval($data['comprobante_asociado_id'] ?? 0));
        fpGuardarCuentaDebito($conexion, $empresa_id_val, $factura_proveedor_id, $comprobante_tipo_id_val, $moneda_id_val,
                              intval($data['tesoreria_cuenta_debito_id'] ?? 0));

        // Insertar detalles
        $detalles_success = insertarDetallesFactura($conexion, $factura_proveedor_id, $data['empresa_idx'], $data['detalles'], $reclasif);
        fpAjustarTotalesReclasificados($conexion, $factura_proveedor_id, $reclasif);
        
        if (!$detalles_success) {
            throw new Exception("Error al insertar los detalles");
        }
        // Percepciones / impuestos adicionales: si vienen (aunque sea vacíos) reemplazan a los guardados
        if (isset($data['impuestos_adicionales'])) {
            $impuestos = is_array($data['impuestos_adicionales']) ? $data['impuestos_adicionales']
                                                                   : (trim((string)$data['impuestos_adicionales']) === '' ? [] : json_decode((string)$data['impuestos_adicionales'], true));
            if (!is_array($impuestos)) {
                throw new Exception('Impuestos adicionales con formato inválido');
            }
            guardarImpuestosFactura($conexion, $factura_proveedor_id, $impuestos, $empresa_id_val, true);
        }
        mysqli_commit($conexion);
        error_log("=== FIN agregarFacturaProveedor - ÉXITO ===");
        return ['resultado' => true, 'factura_proveedor_id' => $factura_proveedor_id];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarFacturaProveedor: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarFacturaProveedor($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarFacturaProveedor ID: $id ===");
    error_log("Datos recibidos en función: " . print_r($data, true));

    mysqli_begin_transaction($conexion);

    try {
        // Solo se edita en Borrador (función Editar 3->3): una factura confirmada ya movió stock y OC.
        $empresa_chk = intval($data['empresa_idx']);
        $stmt_chk = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id, orden_compra_id FROM gestion__facturas_proveedores
                                               WHERE factura_proveedor_id = ? AND empresa_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt_chk, "ii", $id, $empresa_chk);
        mysqli_stmt_execute($stmt_chk);
        $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));
        mysqli_stmt_close($stmt_chk);
        if (!$actual) {
            throw new Exception('Factura de proveedor no encontrada');
        }
        if (intval($actual['tabla_estado_registro_id']) !== 3) {
            throw new Exception('Solo se puede editar una factura en Borrador');
        }

        // Manejar valores NULL
        $f_vencimiento = (!empty($data['f_vencimiento'])) ? $data['f_vencimiento'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.000000);
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        $direccion = isset($data['direccion']) ? trim($data['direccion']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
        $descuento_general_pct = isset($data['descuento_general_pct']) ? floatval($data['descuento_general_pct']) : null;
        
        // Asegurar valores para campos que ahora son INT
        $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro = intval($data['comprobante_nro'] ?? 0);

        
        // Actualizar la factura - CORREGIDO
        $sql = "UPDATE gestion__facturas_proveedores 
            SET sucursal_id = ?,
                deposito_id = ?, 
                comprobante_tipo_id = ?, 
                comprobante_pv = ?, 
                comprobante_nro = ?, 
                entidad_id = ?, 
                entidad_sucursal_id = ?, 
                f_emision = ?,
                f_contabilidad = ?, 
                f_vencimiento = ?, 
                f_entrega_estimada = ?,
                condicion_pago_id = ?, 
                moneda_id = ?, 
                tipo_cambio = ?, 
                direccion_entrega = ?, 
                subtotal = ?, 
                no_gravado = ?,
                exento = ?,
                descuentos = ?, 
                impuestos = ?, 
                total = ?,
                descuento_general_pct = ?,
                observaciones = ?
            WHERE factura_proveedor_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        // Asignar variables (incluir $descuento_general_pct_val)
        $sucursal_id_val = $sucursal_id;
        $deposito_id_val = isset($data['deposito_id']) ? intval($data['deposito_id']) : null;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_val = intval($data['comprobante_nro'] ?? 0);
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_contabilidad_val = $data['f_contabilidad'];
        $f_vencimiento_val = $f_vencimiento;
        $f_entrega_estimada_val = null;
        $condicion_pago_id_val = $condicion_pago_id;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = $tipo_cambio;
        $direccion_val = $direccion;
        $subtotal_val = floatval($data['subtotal'] ?? 0);
        $descuentos_val = floatval($data['descuentos'] ?? 0);
        $no_gravado_val = floatval($data['no_gravado'] ?? 0);
        $exento_val = floatval($data['exento'] ?? 0);
        $impuestos_val = floatval($data['impuestos'] ?? 0);
        $total_val = floatval($data['total'] ?? 0);
        $descuento_general_pct_val = $descuento_general_pct;
        $observaciones_val = $observaciones;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        // Cadena de tipos: 23 parámetros (21 SET + 2 WHERE)
        $types = "iiiiiiissssiidsdddddddsii";
         //1, 34, 1, 90, 2, 2, '2026-03-09', '2026-03-19', NULL, 1, 1, 1.0, '', 667555.3, 36711.7, 0.0, 0.0, 140186.61, 807741.91, 0.0, '', 8, 2
        mysqli_stmt_bind_param($stmt, $types,
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_contabilidad_val,
            $f_vencimiento_val,
            $f_entrega_estimada_val,
            $condicion_pago_id_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $direccion_val,
            $subtotal_val,
            $descuentos_val,
            $no_gravado_val,
            $exento_val,
            $impuestos_val,
            $total_val,
            $descuento_general_pct_val,
            $observaciones_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }

        $affected_rows = mysqli_stmt_affected_rows($stmt);
        error_log("Filas afectadas en update: " . $affected_rows);
        mysqli_stmt_close($stmt);
        
        // Obtener estado actual de la factura
        $sql_estado = "SELECT tabla_estado_registro_id, comprobante_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_estado);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result_estado = mysqli_stmt_get_result($stmt);
        $row_estado = mysqli_fetch_assoc($result_estado);
        $estado_actual_id = $row_estado['tabla_estado_registro_id'];
        $comprobante_id_existente = $row_estado['comprobante_id'];
        mysqli_stmt_close($stmt);

        // Obtener tabla_origen_id
        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $data['pagina_idx'] ?? 57);
        if (!$tabla_origen_id) {
            throw new Exception("No se pudo determinar la tabla de origen");
        }

        $comprobante_data = [
            'empresa_id' => $empresa_idx_val,
            'sucursal_id' => $sucursal_id_val,
            'comprobante_pv' => $comprobante_pv_val,
            'comprobante_tipo_id' => $comprobante_tipo_id_val,
            'comprobante_nro' => $comprobante_nro_val,
            'entidad_id' => $entidad_id_val,
            'entidad_sucursal_id' => $entidad_sucursal_id,
            'f_emision' => $f_emision_val,
            'f_contabilidad' => $f_contabilidad_val,
            'f_vto' => $f_vencimiento_val,
            'moneda_id' => $moneda_id_val,
            'tipo_cambio' => $tipo_cambio_val,
            'registro_origen_id' => $id,
            'tabla_estado_registro_id' => $estado_actual_id,
            'usuario_id' => $_SESSION['usuario_id'] ?? 0,
            'observaciones' => $observaciones_val,
            'importe_neto' => floatval($data['subtotal'] ?? 0),
            'descuento_general' => floatval($data['descuentos'] ?? 0),
            'importe_no_gravado' => floatval($data['no_gravado'] ?? 0),
            'importe_exento' => floatval($data['exento'] ?? 0),
            'importe_iva' => floatval($data['impuestos'] ?? 0),
            'importe_otros_impuestos' => floatval($data['otros_impuestos'] ?? 0),
            'importe_total' => floatval($data['total'] ?? 0)
        ];

        $nuevo_comprobante_id = syncComprobante($conexion, $comprobante_data, $tabla_origen_id);
        if (!$nuevo_comprobante_id) {
            throw new Exception("Error al sincronizar el comprobante");
        }

        // Si el comprobante_id cambió (no debería, pero por si acaso), actualizar factura
        if ($comprobante_id_existente != $nuevo_comprobante_id) {
            $sql_upd = "UPDATE gestion__facturas_proveedores SET comprobante_id = ? WHERE factura_proveedor_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_upd);
            mysqli_stmt_bind_param($stmt, "ii", $nuevo_comprobante_id, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }



        // ===== PROCESAR DETALLES =====
        // Primero, eliminar todos los detalles existentes
        $sql_delete = "DELETE FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if (!$stmt_delete) {
            throw new Exception("Error preparando delete de detalles: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Error eliminando detalles existentes: " . mysqli_stmt_error($stmt_delete));
        }
        mysqli_stmt_close($stmt_delete);
        
        // Luego, insertar los nuevos detalles
        if (isset($data['detalles']) && is_array($data['detalles']) && count($data['detalles']) > 0) {
            list($orden_compra_id, $data['detalles']) = fpValidarVinculoOC(
                $conexion, $empresa_idx_val, $entidad_id_val, $data['orden_compra_id'] ?? 0, $data['detalles'],
                intval($actual['orden_compra_id'] ?? 0));
            fpGuardarOrdenCompraFactura($conexion, $id, $orden_compra_id);
            fpGuardarComprobanteAsociado($conexion, $empresa_idx_val, $id, $comprobante_tipo_id_val, $entidad_id_val,
                                         intval($data['comprobante_asociado_id'] ?? 0));
            fpGuardarCuentaDebito($conexion, $empresa_idx_val, $id, $comprobante_tipo_id_val, $moneda_id_val,
                                  intval($data['tesoreria_cuenta_debito_id'] ?? 0));

            $detalles_success = insertarDetallesFactura($conexion, $id, $empresa_idx_val, $data['detalles'], $reclasif);
            fpAjustarTotalesReclasificados($conexion, $id, $reclasif);
            
            if (!$detalles_success) {
                throw new Exception("Error al insertar los nuevos detalles");
            }
        } else {
            throw new Exception("Debe haber al menos un detalle en la factura");
        }
        // Percepciones / impuestos adicionales: si vienen (aunque sea vacíos) reemplazan a los guardados
        if (isset($data['impuestos_adicionales'])) {
            $impuestos = is_array($data['impuestos_adicionales']) ? $data['impuestos_adicionales']
                                                                   : (trim((string)$data['impuestos_adicionales']) === '' ? [] : json_decode((string)$data['impuestos_adicionales'], true));
            if (!is_array($impuestos)) {
                throw new Exception('Impuestos adicionales con formato inválido');
            }
            guardarImpuestosFactura($conexion, $id, $impuestos, $empresa_idx_val, true);
        }

        mysqli_commit($conexion);
        error_log("=== FIN editarFacturaProveedor - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Factura actualizada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarFacturaProveedor: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerFacturaProveedorPorId($conexion, $id, $empresa_idx = null){
    $id = intval($id);
    
    // Si no se pasa empresa_idx, obtenerlo de la factura primero
    if ($empresa_idx === null) {
        $sql_get_empresa = "SELECT empresa_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_get_empresa);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            if ($row) {
                $empresa_idx = $row['empresa_id'];
            } else {
                error_log("Factura ID $id no encontrada para obtener empresa_idx");
                return null;
            }
        } else {
            return null;
        }
    }
    
    $empresa_idx = intval($empresa_idx);

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

    $sql = "SELECT fp.*, fp.sucursal_id, fp.descuento_general_pct, fp.f_contabilidad, fp.deposito_id,
                er.$estado_column as estado_registro, 
                er.codigo_estandar,
                ct.comprobante_tipo,
                e.entidad_nombre, e.entidad_fantasia,
                m.moneda, m.simbolo,
                oc.comprobante_nro AS orden_compra_nro
            FROM gestion__facturas_proveedores fp
            LEFT JOIN gestion__ordenes_compra oc ON oc.orden_compra_id = fp.orden_compra_id AND oc.empresa_id = fp.empresa_id
            LEFT JOIN conf__estados_registros er ON fp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON fp.moneda_id = m.moneda_id
            WHERE fp.factura_proveedor_id = ? AND fp.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta factura: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $factura = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$factura) {
        error_log("Factura no encontrada para ID: $id, empresa: $empresa_idx");
        return null;
    }
    
    // Consulta para obtener los detalles
    $sql_detalles = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                            p.iva_alicuota_id as producto_iva_id,
                            pp.codigo_proveedor
                     FROM gestion__facturas_proveedores_detalle d
                     LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                     LEFT JOIN (
                         SELECT producto_id, MAX(codigo_proveedor) AS codigo_proveedor
                         FROM gestion__productos_proveedores
                         WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                         GROUP BY producto_id
                     ) pp ON d.producto_id = pp.producto_id
                     WHERE d.factura_proveedor_id = ?
                     ORDER BY d.factura_proveedor_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt) {
        error_log("Error preparando consulta detalles: " . mysqli_error($conexion));
        return $factura;
    }

    $entidad_id = intval($factura['entidad_id'] ?? 0);
    $empresa_det = intval($factura['empresa_id'] ?? $empresa_idx);
    mysqli_stmt_bind_param($stmt, "iii", $entidad_id, $empresa_det, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'factura_proveedor_detalle_id' => intval($detalle['factura_proveedor_detalle_id']),
            'producto_id' => intval($detalle['producto_id']),
            'producto_nombre' => $detalle['producto_nombre'] . ' (' . $detalle['producto_codigo'] . ')',
            'cantidad' => floatval($detalle['cantidad']),
            'precio_unitario' => floatval($detalle['precio_unitario']),
            'precio_unitario_neto' => floatval($detalle['precio_unitario_neto'] ?? 0),  // NUEVO
            'descuento_item_pct' => floatval($detalle['descuento_item_pct'] ?? 0),
            'descuento_general_pct' => floatval($detalle['descuento_general_pct'] ?? 0),
            'descuento_item' => floatval($detalle['descuento_item'] ?? 0),
            'descuento_general' => floatval($detalle['descuento_general'] ?? 0),
            'descuento' => floatval($detalle['descuento'] ?? 0),
            'precio_unitario_bruto' => floatval($detalle['precio_unitario_bruto'] ?? $detalle['precio_unitario']),
            'no_gravado' => floatval($detalle['no_gravado'] ?? 0),
            'exento' => floatval($detalle['exento'] ?? 0),
            'iva_alicuota_id' => intval($detalle['iva_alicuota_id'] ?? $detalle['producto_iva_id'] ?? 1),
            'iva_porcentaje' => floatval($detalle['iva_porcentaje'] ?? 21),
            'neto_gravado' => floatval($detalle['neto_gravado']),
            'iva_importe' => floatval($detalle['iva_importe']),
            'total_linea' => floatval($detalle['total_linea']),
            'codigo_proveedor' => $detalle['codigo_proveedor'] ?? '',
            'ordenes_compra_detalle_id' => !empty($detalle['ordenes_compra_detalle_id']) ? intval($detalle['ordenes_compra_detalle_id']) : null
        ];
    }
    mysqli_stmt_close($stmt);

    $factura['detalles'] = $detalles;
    return $factura;
}

function obtenerComprobantesTipos($conexion, $empresa_idx = null)
{
    $empresa_idx = intval($empresa_idx ?? ($_SESSION['empresa_id'] ?? 0));
    $subgrupo_id = emp_subgrupo_id($conexion, $empresa_idx, 'FC');   // Facturas de Compra de la empresa
    if ($subgrupo_id <= 0) return [];   // la empresa no tiene ese subgrupo activo
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra, signo"
         . (fpDebitoCuentaHabilitado($conexion) ? ", requiere_debito_cuenta" : ", 0 AS requiere_debito_cuenta") . "
            FROM gestion__comprobantes_tipos
            WHERE empresa_id = $empresa_idx AND comprobante_subgrupo_id = $subgrupo_id
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

function obtenerProveedores($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia 
            FROM gestion__entidades 
            WHERE empresa_id = ? 
            AND es_proveedor = 1
            AND tabla_estado_registro_id = 1 
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $proveedores = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $proveedores[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $proveedores;
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
    $sql = "SELECT condicion_pago_id, codigo, condicion_pago, tipo, dias_primer_vencimiento 
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

function obtenerProductosPorProveedor($conexion, $empresa_idx, $entidad_id)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   pp.codigo_proveedor, 
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   iva.iva_alicuota as iva_nombre
            FROM gestion__productos p
            INNER JOIN gestion__productos_proveedores pp ON p.producto_id = pp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id AND iva.empresa_id = p.empresa_id
            WHERE p.empresa_id = ? 
            AND pp.entidad_id = ?
            AND p.tabla_estado_registro_id = 1
            AND pp.tabla_estado_registro_id = 1
            ORDER BY p.producto_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos proveedor: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = [
            'producto_id' => $fila['producto_id'],
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'codigo_proveedor' => $fila['codigo_proveedor'],
            'iva_alicuota_id' => $fila['iva_alicuota_id'],
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 21),
            'iva_nombre' => $fila['iva_nombre'] ?? 'IVA ' . ($fila['iva_porcentaje'] ?? 21) . '%'
        ];
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

function obtenerCodigoProveedor($conexion, $producto_id, $entidad_id, $empresa_id)
{
    $sql = "SELECT codigo_proveedor 
            FROM gestion__productos_proveedores 
            WHERE producto_id = ? 
            AND entidad_id = ? 
            AND empresa_id = ? 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return '';
    
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row ? $row['codigo_proveedor'] : '';
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

/* =========================================================================
 * Alta rápida de producto desde la carga del comprobante, ya vinculado al
 * proveedor con su código. Producto + vínculo en una sola transacción y
 * recálculo de gestion__productos.proveedores_busqueda (si no, el producto
 * nuevo no aparece al buscar por código de proveedor en otras pantallas).
 * ========================================================================= */
function agregarProductoRapido($conexion, $data)
{
    error_log("=== INICIO agregarProductoRapido codigo: " . ($data['producto_codigo'] ?? '') . " ===");

    if (empty($data['producto_codigo']) || empty($data['producto_nombre'])) {
        return ['success' => false, 'error' => 'Código y nombre son obligatorios'];
    }
    
    if (empty($data['producto_categoria_id'])) {
        return ['success' => false, 'error' => 'La categoría es obligatoria'];
    }
    
    if (empty($data['codigo_proveedor'])) {
        return ['success' => false, 'error' => 'El código del proveedor es obligatorio'];
    }
    
    if (empty($data['entidad_id'])) {
        return ['success' => false, 'error' => 'Debe seleccionar un proveedor'];
    }

    $empresa_id = intval($data['empresa_idx']);
    $entidad_id = intval($data['entidad_id']);
    $categoria_id = intval($data['producto_categoria_id']);
    $iva_alicuota_id = intval($data['iva_alicuota_id']);
    $unidad_medida_id = intval($data['unidad_medida_id']);

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) as total FROM gestion__productos 
                                           WHERE producto_codigo = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $data['producto_codigo'], $empresa_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row['total'] > 0) {
            throw new RuntimeException('Ya existe un producto con este código');
        }

        // Solo cuentan los vínculos activos (los inactivos no se usan al buscar)
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) as total FROM gestion__productos_proveedores
                                           WHERE empresa_id = ? AND entidad_id = ? AND codigo_proveedor = ?
                                           AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "iis", $empresa_id, $entidad_id, $data['codigo_proveedor']);
        mysqli_stmt_execute($stmt);
        $row_proveedor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row_proveedor && $row_proveedor['total'] > 0) {
            throw new RuntimeException('Ya existe un producto con este código de proveedor para el proveedor seleccionado');
        }

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos 
                (empresa_id, producto_codigo, producto_nombre, codigo_barras, 
                 producto_descripcion, producto_categoria_id, producto_tipo_id, 
                 iva_alicuota_id, unidad_medida_id, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $producto_tipo_id = emp_producto_tipo_id($conexion, $empresa_id, true);
        mysqli_stmt_bind_param($stmt, "issssiiii", 
            $empresa_id,
            $data['producto_codigo'],
            $data['producto_nombre'],
            $data['codigo_barras'],
            $data['producto_descripcion'],
            $categoria_id,
            $producto_tipo_id,
            $iva_alicuota_id,
            $unidad_medida_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Error al crear el producto: ' . mysqli_stmt_error($stmt));
        }
        $producto_id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos_proveedores 
                (empresa_id, producto_id, entidad_id, codigo_proveedor, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "iiis", $empresa_id, $producto_id, $entidad_id, $data['codigo_proveedor']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Error al asociar el producto con el proveedor: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        recalcularProveedoresBusqueda($conexion, $producto_id);
        mysqli_commit($conexion);

        return ['success' => true, 'producto_id' => $producto_id];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("Error agregarProductoRapido: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/* =========================================================================
 * Busca entre TODOS los productos activos de la empresa, no solo los
 * vinculados al proveedor. Los vinculados (vínculo activo en
 * gestion__productos_proveedores) vienen primero con vinculado = 1 y su
 * código de proveedor; el resto con vinculado = 0, para que el front ofrezca
 * vincularlos (vincularProductoProveedor) al seleccionarlos.
 * ========================================================================= */
function buscarProductosPorProveedor($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   pp.codigo_proveedor,
                   IF(pp.producto_id IS NULL, 0, 1) AS vinculado,
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   iva.iva_alicuota as iva_nombre
            FROM gestion__productos p
            LEFT JOIN (
                SELECT producto_id, MAX(codigo_proveedor) AS codigo_proveedor
                FROM gestion__productos_proveedores
                WHERE empresa_id = ?
                AND entidad_id = ?
                AND tabla_estado_registro_id = 1
                GROUP BY producto_id
            ) pp ON pp.producto_id = p.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id AND iva.empresa_id = p.empresa_id
            WHERE p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR pp.codigo_proveedor LIKE ?)
            ORDER BY vinculado DESC, p.producto_nombre
            LIMIT 30";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos: " . mysqli_error($conexion));
        return [];
    }

    $search = "%$q%";
    mysqli_stmt_bind_param($stmt, "iiisss", $empresa_idx, $entidad_id, $empresa_idx, $search, $search, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = [
            'producto_id' => $fila['producto_id'],
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'codigo_proveedor' => $fila['codigo_proveedor'],
            'vinculado' => intval($fila['vinculado']),
            'iva_alicuota_id' => $fila['iva_alicuota_id'],
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 21),
            'iva_nombre' => $fila['iva_nombre'] ?? 'IVA ' . ($fila['iva_porcentaje'] ?? 21) . '%'
        ];
    }

    mysqli_stmt_close($stmt);
    return $productos;
}

/* =========================================================================
 * Vincula un producto existente al proveedor con su código, desde la carga
 * de la factura. Si ya hay una fila INACTIVA para producto + proveedor la
 * reactiva en vez de duplicarla (mismo criterio que
 * productos_proveedores_model.php::agregarVinculoProveedor). El código de
 * proveedor no puede repetirse en otro producto del mismo proveedor.
 * ========================================================================= */
function vincularProductoProveedor($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $codigo_proveedor = trim($data['codigo_proveedor'] ?? '');

    error_log("=== INICIO vincularProductoProveedor producto: $producto_id proveedor: $entidad_id ===");

    if ($producto_id <= 0) {
        return ['success' => false, 'message' => 'Producto no válido'];
    }
    if ($entidad_id <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar un proveedor'];
    }
    if ($codigo_proveedor === '') {
        return ['success' => false, 'message' => 'El código del proveedor es obligatorio'];
    }
    if (mb_strlen($codigo_proveedor) > 50) {
        return ['success' => false, 'message' => 'El código del proveedor no puede superar los 50 caracteres'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // El producto tiene que ser de la empresa y estar activo
        $stmt = mysqli_prepare($conexion, "SELECT producto_id FROM gestion__productos
                                           WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                           FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$producto) {
            throw new RuntimeException('El producto no existe o está inactivo');
        }

        // Código repetido en otro producto del mismo proveedor
        $stmt = mysqli_prepare($conexion, "SELECT p.producto_codigo, p.producto_nombre
                                           FROM gestion__productos_proveedores pp
                                           INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id
                                           WHERE pp.empresa_id = ? AND pp.entidad_id = ? AND pp.codigo_proveedor = ?
                                           AND pp.producto_id <> ? AND pp.tabla_estado_registro_id = 1
                                           LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iisi", $empresa_id, $entidad_id, $codigo_proveedor, $producto_id);
        mysqli_stmt_execute($stmt);
        $repetido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($repetido) {
            throw new RuntimeException('El código ' . $codigo_proveedor . ' ya está asignado para este proveedor al producto '
                . $repetido['producto_codigo'] . ' - ' . $repetido['producto_nombre']);
        }

        $stmt = mysqli_prepare($conexion, "SELECT producto_proveedor_id, tabla_estado_registro_id
                                           FROM gestion__productos_proveedores
                                           WHERE producto_id = ? AND entidad_id = ? AND empresa_id = ?
                                           ORDER BY (tabla_estado_registro_id = 1) DESC, producto_proveedor_id
                                           LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existente) {
            // Activo (vinculado mientras se cargaba la factura) o inactivo: se deja activo con el código informado
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores
                                               SET tabla_estado_registro_id = 1, codigo_proveedor = ?
                                               WHERE producto_proveedor_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $codigo_proveedor, $existente['producto_proveedor_id']);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Error al reactivar el vínculo: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            $producto_proveedor_id = intval($existente['producto_proveedor_id']);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos_proveedores
                                               (producto_id, entidad_id, empresa_id, codigo_proveedor, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "iiis", $producto_id, $entidad_id, $empresa_id, $codigo_proveedor);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Error al vincular el producto: ' . mysqli_stmt_error($stmt));
            }
            $producto_proveedor_id = intval(mysqli_insert_id($conexion));
            mysqli_stmt_close($stmt);
        }

        recalcularProveedoresBusqueda($conexion, $producto_id);
        mysqli_commit($conexion);

        return [
            'success' => true,
            'data' => ['producto_proveedor_id' => $producto_proveedor_id, 'codigo_proveedor' => $codigo_proveedor],
            'message' => 'Producto vinculado al proveedor'
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("Error vincularProductoProveedor: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Mantiene materializada gestion__productos.proveedores_busqueda — MISMA
 * función que productos_model.php / productos_proveedores_model.php, duplicada
 * acá por la convención de no cruzar includes entre archivos de página.
 * ========================================================================= */
function recalcularProveedoresBusqueda($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    if ($producto_id <= 0) return false;

    $sql = "UPDATE gestion__productos p
            SET p.proveedores_busqueda = (
                SELECT GROUP_CONCAT(DISTINCT pp.codigo_proveedor SEPARATOR ' ')
                FROM gestion__productos_proveedores pp
                WHERE pp.producto_id = p.producto_id
                AND pp.tabla_estado_registro_id = 1
                AND pp.codigo_proveedor IS NOT NULL
                AND pp.codigo_proveedor <> ''
            )
            WHERE p.producto_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando UPDATE proveedores_busqueda: " . mysqli_error($conexion));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log("Error ejecutando UPDATE proveedores_busqueda($producto_id): " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $ok;
}

function obtenerUltimoPrecioProducto($conexion, $producto_id, $entidad_id, $empresa_id)
{
    $sql = "SELECT precio_unitario 
            FROM gestion__facturas_proveedores_detalle d
            INNER JOIN gestion__facturas_proveedores f ON d.factura_proveedor_id = f.factura_proveedor_id
            WHERE d.producto_id = ? 
            AND f.entidad_id = ? 
            AND f.empresa_id = ?
            ORDER BY f.factura_proveedor_id DESC 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false];
    
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        return ['success' => true, 'precio' => $row['precio_unitario']];
    }
    
    return ['success' => false];
}

// Corrige la cabecera y el comprobante cuando insertarDetallesFactura pasó la base de líneas con alícuota
// No gravado / Exento de neto gravado a no gravado / exento (el total no cambia).
function fpAjustarTotalesReclasificados($conexion, $factura_proveedor_id, $r)
{
    $neto = round(floatval($r['neto'] ?? 0), 2);
    $ng = round(floatval($r['no_gravado'] ?? 0), 2);
    $ex = round(floatval($r['exento'] ?? 0), 2);
    if (abs($neto) < 0.005 && abs($ng) < 0.005 && abs($ex) < 0.005) return;
    $factura_proveedor_id = intval($factura_proveedor_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores
                                       SET subtotal = subtotal - ?, no_gravado = no_gravado + ?, exento = exento + ?
                                       WHERE factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "dddi", $neto, $ng, $ex, $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes c
                                       INNER JOIN gestion__facturas_proveedores f ON f.comprobante_id = c.comprobante_id
                                       SET c.importe_neto = c.importe_neto - ?, c.importe_no_gravado = c.importe_no_gravado + ?,
                                           c.importe_exento = c.importe_exento + ?
                                       WHERE f.factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "dddi", $neto, $ng, $ex, $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// $reclasificado (salida): lo que se movió de neto gravado (según lo que mandó el front) a no gravado / exento,
// para que el llamador corrija la cabecera con fpAjustarTotalesReclasificados.
function insertarDetallesFactura($conexion, $factura_proveedor_id, $empresa_id, $detalles, &$reclasificado = null)
{
    error_log("Insertando " . count($detalles) . " detalles para factura $factura_proveedor_id");
    $reclasificado = ['neto' => 0.0, 'no_gravado' => 0.0, 'exento' => 0.0];

    // Alícuotas No gravado / Exento de la empresa
    $alicuotas_sin_iva = [];
    $empresa_id_alic = intval($empresa_id);
    $stmt_alic = mysqli_prepare($conexion, "SELECT iva_alicuota_id, es_no_gravado, es_exento FROM gestion__impuestos__iva_alicuotas
                                            WHERE empresa_id = ? AND (es_no_gravado = 1 OR es_exento = 1)");
    mysqli_stmt_bind_param($stmt_alic, "i", $empresa_id_alic);
    mysqli_stmt_execute($stmt_alic);
    $res_alic = mysqli_stmt_get_result($stmt_alic);
    while ($a = mysqli_fetch_assoc($res_alic)) {
        $alicuotas_sin_iva[intval($a['iva_alicuota_id'])] = intval($a['es_no_gravado']) === 1 ? 'no_gravado' : 'exento';
    }
    mysqli_stmt_close($stmt_alic);
    
    if (!is_array($detalles) || count($detalles) === 0) {
        error_log("Error: No hay detalles para insertar");
        return false;
    }
    
    $insertados = 0;
    
    foreach ($detalles as $index => $detalle) {
        // Validar campos requeridos
        if (empty($detalle['producto_id'])) {
            error_log("Error: producto_id vacío en detalle $index");
            return false;
        }
        
        $cantidad = floatval($detalle['cantidad'] ?? 0);
        $precio_unitario = floatval($detalle['precio_unitario'] ?? 0);
        
        if ($cantidad <= 0) {
            error_log("Error: cantidad inválida en detalle $index: $cantidad");
            return false;
        }
        
        if ($precio_unitario <= 0) {
            error_log("Error: precio_unitario inválido en detalle $index: $precio_unitario");
            return false;
        }
        
        // Calcular valores
        $descuento_item_pct = floatval($detalle['descuento_item_pct'] ?? 0);
        $descuento_general_pct = floatval($detalle['descuento_general_pct'] ?? 0);
        
        $precio_unitario_bruto = $precio_unitario;
        $descuento_item = $cantidad * $precio_unitario * ($descuento_item_pct / 100);
        $descuento_general = ($cantidad * $precio_unitario - $descuento_item) * ($descuento_general_pct / 100);
        $descuento_total = $descuento_item + $descuento_general;
        
        $neto_gravado = ($cantidad * $precio_unitario) - $descuento_total;
        $no_gravado = floatval($detalle['no_gravado'] ?? 0);
        $exento = floatval($detalle['exento'] ?? 0);
        
        // CALCULAR PRECIO UNITARIO NETO (valor final por unidad)
        // precio_unitario_neto = (neto_gravado + no_gravado + exento) / cantidad
        
        $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : 0;
        $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 0);

        // Alícuota No gravado / Exento: la base de la línea no es neto gravado (Libro IVA). El front nuevo ya la manda
        // en no_gravado / exento con neto 0; el viejo la mandaba como neto gravado al 0 %.
        if (isset($alicuotas_sin_iva[$iva_alicuota_id]) && $neto_gravado > 0) {
            $campo = $alicuotas_sin_iva[$iva_alicuota_id];
            $actual = ($campo === 'no_gravado') ? $no_gravado : $exento;
            $agregado = max($actual, $neto_gravado) - $actual;
            if ($campo === 'no_gravado') $no_gravado += $agregado; else $exento += $agregado;
            $reclasificado[$campo] += $agregado;
            $reclasificado['neto'] += floatval($detalle['neto_gravado'] ?? 0);
            $neto_gravado = 0;
            $iva_porcentaje = 0;
        }
        $precio_unitario_neto = ($neto_gravado + $no_gravado + $exento) / $cantidad;
        $iva_importe = $neto_gravado * ($iva_porcentaje / 100);
        $total_linea = $neto_gravado + $iva_importe + $no_gravado + $exento;
        
        // Insertar detalle con todos los campos (incluyendo precio_unitario_neto)
        $sql = "INSERT INTO gestion__facturas_proveedores_detalle 
            (factura_proveedor_id, empresa_id, producto_id, cantidad, cantidad_recibida, 
            precio_unitario, precio_unitario_neto, descuento_item_pct, descuento_general_pct, 
            precio_unitario_bruto, descuento, descuento_general, descuento_item,
            neto_gravado, no_gravado, exento, 
            iva_alicuota_id, iva_porcentaje, iva_importe, total_linea, ordenes_compra_detalle_id)
            VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
         $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando insert detalle: " . mysqli_error($conexion));
            return false;
        }

        // Tipos: 3 integers + 16 doubles = 19 caracteres
        // "i i d d d d d d d d d d d d d i d d d"
        // "i i d d d d d d d d d d d d d i d d d" = 19
        $types = "iiidddddddddddddddd";
        // Posiciones: 1,2,3 = i, luego 4-16 = d? No, revisemos...
        // Mejor usar la forma explícita:

        $oc_detalle_id = !empty($detalle['ordenes_compra_detalle_id']) ? intval($detalle['ordenes_compra_detalle_id']) : null;

        mysqli_stmt_bind_param($stmt, "iiiddddddddddddidddi",  
            $factura_proveedor_id,  // 1 - i
            $empresa_id,            // 2 - i
            $detalle['producto_id'], // 3 - i
            $cantidad,              // 4 - d
            $precio_unitario,       // 5 - d (corresponde a posición 6 en VALUES)
            $precio_unitario_neto,  // 6 - d (posición 7)
            $descuento_item_pct,    // 7 - d (posición 8)
            $descuento_general_pct, // 8 - d (posición 9)
            $precio_unitario_bruto, // 9 - d (posición 10)
            $descuento_total,       // 10 - d (posición 11)
            $descuento_general,     // 11 - d (posición 12)
            $descuento_item,        // 12 - d (posición 13)
            $neto_gravado,          // 13 - d (posición 14)
            $no_gravado,            // 14 - d (posición 15)
            $exento,                // 15 - d (posición 16)
            $iva_alicuota_id,       // 16 - i (posición 17)
            $iva_porcentaje,        // 17 - d (posición 18)
            $iva_importe,           // 18 - d (posición 19)
            $total_linea,           // 19 - d (posición 20)
            $oc_detalle_id          // 20 - i (línea de OC o NULL)
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            error_log("Error ejecutando insert detalle $index: " . $error);
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $detalle_id = mysqli_insert_id($conexion);
        error_log("Detalle $index insertado con ID: $detalle_id, precio_unitario_neto: $precio_unitario_neto");
        $insertados++;
        mysqli_stmt_close($stmt);
    }
    
    error_log("Se insertaron $insertados detalles correctamente");
    return $insertados === count($detalles);
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

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $sucursales = AccesosSucursalesHelper::filtrarSucursales($conexion, $empresa_idx, $sucursales);
    }

    return $sucursales;
}
function obtenerCondicionesProveedor($conexion, $entidad_id, $empresa_idx) {
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT condicion_pago_id, proveedor_descuento_general 
            FROM gestion__entidades_condiciones_proveedores 
            WHERE entidad_id = ? 
            AND tabla_estado_registro_id = 1
            AND (f_hasta IS NULL OR f_hasta >= CURDATE())
            ORDER BY f_desde DESC
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta condiciones proveedor: " . mysqli_error($conexion));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $condiciones = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Log para depuración
    error_log("Condiciones para entidad $entidad_id: " . print_r($condiciones, true));
    
    return $condiciones;
}
// Stock de una factura de proveedor: lo decide stock_core_model.php según el tipo (stock_signo) y el
// estado (aplica_stock), por diferencia contra el libro, así que se llama en TODA transición.
// Corre dentro de la transacción de ejecutarTransicionEstado(): no abre una propia.
// deposito_id de la factura guarda gestion__bocas.boca_id. Los costos se actualizan solo al confirmar.
function registrarStockFacturaProveedor($conexion, $factura_proveedor_id, $comprobante_id, $estado_id, $tabla_origen_id, $actualizar_costos) {
    error_log("=== INICIO registrarStockFacturaProveedor factura_id: $factura_proveedor_id, estado: $estado_id ===");
    require_once __DIR__ . '/stock_core_model.php';

    $stmt = mysqli_prepare($conexion, "SELECT empresa_id, deposito_id, comprobante_tipo_id, comprobante_pv, comprobante_nro
                                       FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$factura) {
        return ['success' => false, 'message' => 'Factura no encontrada'];
    }
    $empresa_id = intval($factura['empresa_id']);

    // precio_unitario_neto es el costo de la compra
    $stmt = mysqli_prepare($conexion, "SELECT factura_proveedor_detalle_id, producto_id, cantidad, precio_unitario_neto
                                       FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $lineas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $lineas[] = [
            'registro_detalle_id' => intval($row['factura_proveedor_detalle_id']),
            'producto_id' => intval($row['producto_id']),
            'cantidad' => floatval($row['cantidad']),
            'costo_unitario' => floatval($row['precio_unitario_neto'] ?? 0),
        ];
    }
    mysqli_stmt_close($stmt);

    try {
        $res = stockSincronizarComprobante($conexion, [
            'empresa_id' => $empresa_id,
            'comprobante_id' => intval($comprobante_id),
            'comprobante_tipo_id' => intval($factura['comprobante_tipo_id']),
            'tabla_origen_id' => intval($tabla_origen_id),
            'registro_origen_id' => intval($factura_proveedor_id),
            'estado_registro_id' => intval($estado_id),
            'boca_id' => intval($factura['deposito_id'] ?? 0),
            'usuario_id' => $_SESSION['usuario_id'] ?? null,
            'observaciones' => 'Factura de proveedor N° ' . ($factura['comprobante_pv'] ?? '') . '-' . ($factura['comprobante_nro'] ?? ''),
        ], $lineas);

        if ($actualizar_costos) {
            $usuario_id_actual = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;
            foreach ($lineas as $linea) {
                if ($linea['cantidad'] <= 0) continue;
                $costo_result = actualizarCostosProducto($conexion, $linea['producto_id'], $empresa_id, $linea['costo_unitario'],
                                                         $comprobante_id, $usuario_id_actual, true);
                if (!$costo_result['success']) {
                    throw new Exception('Error al actualizar costo del producto: ' . $costo_result['message']);
                }
            }
        }
        return ['success' => true, 'message' => 'Stock registrado correctamente', 'movimientos' => $res['movimientos']];
    } catch (Exception $e) {
        // El rollback lo hace el llamador al recibir success = false.
        error_log("ERROR en registrarStockFacturaProveedor: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function obtenerDepositosPorSucursal($conexion, $sucursal_id, $empresa_idx) {
    // gestion__depositos se reemplazó por gestion__bocas (mismos ids): depósito = boca con es_deposito = 1.
    $sql = "SELECT boca_id AS deposito_id, boca_nombre AS deposito_nombre, codigo, es_principal, permite_ingresos
            FROM gestion__bocas
            WHERE empresa_id = ? 
            AND sucursal_id = ?
            AND tabla_estado_registro_id = 1
            AND es_deposito = 1
            AND permite_ingresos = 1
            ORDER BY es_principal DESC, orden, boca_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta depósitos: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $sucursal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $depositos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $depositos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $depositos;
}

function syncComprobante($conexion, $data, $tabla_origen_id)
{
    error_log("=== syncComprobante INICIO ===");
    error_log("tabla_origen_id: $tabla_origen_id");
    error_log("Datos: " . print_r($data, true));
    
    if (empty($tabla_origen_id)) {
        error_log("ERROR: tabla_origen_id no proporcionado");
        return null;
    }
    
    // Extraer valores con defaults seguros
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
    
    // --- Campos financieros mapeados desde factura_proveedores ---
    $importe_neto = floatval($data['importe_neto'] ?? 0);       // subtotal
    $descuento_general = floatval($data['descuento_general'] ?? 0); // total descuentos
    $importe_no_gravado = floatval($data['importe_no_gravado'] ?? 0); // no_gravado
    $importe_exento = floatval($data['importe_exento'] ?? 0);       // exento
    $importe_iva = floatval($data['importe_iva'] ?? 0);             // impuestos (IVA)
    $importe_otros_impuestos = floatval($data['importe_otros_impuestos'] ?? 0); // otros_impuestos
    $importe_total = floatval($data['importe_total'] ?? 0);         // total
    
    // Calcular importe_neto (opcional: bruto - descuentos)
    $importe_bruto = $importe_neto + $descuento_general;
    
    if ($registro_origen_id <= 0) {
        error_log("ERROR: registro_origen_id inválido: $registro_origen_id");
        return null;
    }
    
    // Verificar si ya existe comprobante
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
        // UPDATE - Respeta imputaciones vigentes en importe_pendiente (C5 / D2 / §2.4)
        $comprobante_id = $existe['comprobante_id'];
        $importe_pendiente = $importe_total;

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
        error_log("Comprobante actualizado ID: $comprobante_id");
    } else {
        // INSERT - Ahora incluye importe_pendiente
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
            $importe_total, // importe_pendiente = importe_total
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
        error_log("Comprobante creado ID: $comprobante_id");
    }
    
    // Signo del comprobante según su tipo ('-' = nota de crédito): la columna quedaba siempre en 1.
    $stmt_signo = mysqli_prepare($conexion, "UPDATE gestion__comprobantes c
                                             JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                             SET c.signo = IF(ct.signo = '-', -1, 1)
                                             WHERE c.comprobante_id = ?");
    mysqli_stmt_bind_param($stmt_signo, "i", $comprobante_id);
    mysqli_stmt_execute($stmt_signo);
    mysqli_stmt_close($stmt_signo);

    return $comprobante_id;
}
function obtenerImpuestosConfig($conexion, $empresa_idx, $comprobante_subgrupo_id = 0)
{
    $empresa_idx = intval($empresa_idx);
    // Subgrupo de la empresa; si no viene o es de otra empresa, Facturas de Compra (FC)
    $comprobante_subgrupo_id = emp_subgrupo_valido_id($conexion, $empresa_idx, $comprobante_subgrupo_id, 'FC');
    
    $sql = "SELECT 
                config.empresa_impuesto_config_id,
                config.empresa_id,
                -- Impuesto
                config.impuesto_tipo_id,
                it.impuesto_tipo,
                it.codigo_afip,
                it.es_retencion,
                it.es_percepcion,
                -- Jurisdicción
                config.jurisdiccion_id,
                j.jurisdiccion_nombre,
                j.jurisdiccion_codigo,
                -- Condición fiscal
                config.condicion_fiscal_id,
                cf.condicion_fiscal,
                cf.condicion_fiscal_codigo,
                -- Configuración
                config.base_calculo,
                config.alicuota,
                config.minimo_imponible,
                config.monto_fijo,
                config.aplica_siempre,
                config.prioridad,
                config.tipo_calculo,
                config.f_desde,
                config.f_hasta,
                -- Cuenta contable
                cc.cont_cuenta_id,
                CONCAT(cc.codigo, ' - ', cc.nombre) as cuenta_contable
            FROM 
                gestion__empresas_impuestos_config AS config
            INNER JOIN 
                gestion__empresas_impuestos_config_subgrupos AS sub
                ON config.empresa_impuesto_config_id = sub.empresa_impuesto_config_id
            INNER JOIN 
                gestion__impuestos_tipos AS it
                ON config.impuesto_tipo_id = it.impuesto_tipo_id
            LEFT JOIN 
                gestion__jurisdicciones AS j
                ON config.jurisdiccion_id = j.jurisdiccion_id
            LEFT JOIN 
                gestion__condiciones_fiscales AS cf
                ON config.condicion_fiscal_id = cf.condicion_fiscal_id
            LEFT JOIN
                gestion__cont_cuentas AS cc
                ON config.cont_cuenta_id = cc.cont_cuenta_id
            WHERE 
                config.empresa_id = ?
                AND sub.comprobante_subgrupo_id = ?
                -- Estados activos (motor de estados)
                AND config.tabla_estado_registro_id = 1
                AND sub.tabla_estado_registro_id = 1
                AND it.tabla_estado_registro_id = 1
                AND (j.tabla_estado_registro_id = 1 OR j.jurisdiccion_id IS NULL)
                AND (cf.tabla_estado_registro_id = 1 OR cf.condicion_fiscal_id IS NULL)
                AND (config.f_hasta IS NULL OR config.f_hasta >= CURDATE())
            ORDER BY config.prioridad, it.impuesto_tipo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta impuestos config: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $comprobante_subgrupo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $impuestos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // También obtener las operaciones (métodos de cálculo por tipo de bien)
        $sql_operaciones = "SELECT 
                                eico.*, 
                                pt.producto_tipo,
                                cf_op.condicion_fiscal
                            FROM gestion__empresas_impuestos_config_operaciones eico
                            LEFT JOIN gestion__productos_tipos pt ON eico.producto_tipo_id = pt.producto_tipo_id
                            LEFT JOIN gestion__condiciones_fiscales cf_op ON eico.condicion_fiscal_id = cf_op.condicion_fiscal_id
                            WHERE eico.empresa_impuesto_config_id = ?
                                AND eico.tabla_estado_registro_id = 1
                                AND (eico.f_hasta IS NULL OR eico.f_hasta >= CURDATE())
                            ORDER BY 
                                CASE WHEN eico.producto_tipo_id = 0 THEN 0 ELSE 1 END,
                                eico.producto_tipo_id,
                                eico.f_desde DESC";
        
        $stmt_op = mysqli_prepare($conexion, $sql_operaciones);
        if ($stmt_op) {
            mysqli_stmt_bind_param($stmt_op, "i", $fila['empresa_impuesto_config_id']);
            mysqli_stmt_execute($stmt_op);
            $result_op = mysqli_stmt_get_result($stmt_op);
            $operaciones = [];
            while ($op = mysqli_fetch_assoc($result_op)) {
                $operaciones[] = $op;
            }
            mysqli_stmt_close($stmt_op);
            $fila['operaciones'] = $operaciones;
        }
        
        $impuestos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    
    error_log("Impuestos config cargados: " . count($impuestos) . " registros");
    return $impuestos;
}
// Percepciones e impuestos adicionales recibidos en una factura / NC / ND de proveedor (gestion__comprobantes_impuestos).
// Reemplaza los del comprobante: baja lógica (estado 2) de los anteriores y alta de los que vienen con importe.
// Cada config tiene que ser de la empresa, estar activa y ser de un impuesto de compras (aplica_compra = 1).
// Deja el total en gestion__facturas_proveedores.otros_impuestos y en gestion__comprobantes.importe_otros_impuestos
// (el asiento toma la cuenta de cada config). Solo en Borrador. $en_transaccion = true: corre dentro de la
// transacción del llamador (agregar / editar) y lanza la excepción; false: abre y confirma la suya.
function guardarImpuestosFactura($conexion, $factura_proveedor_id, $impuestos, $empresa_idx, $en_transaccion = false)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO guardarImpuestosFactura factura: $factura_proveedor_id ===");

    if (!$en_transaccion) {
        mysqli_begin_transaction($conexion);
    }
    try {
        $stmt = mysqli_prepare($conexion, "SELECT comprobante_id, tabla_estado_registro_id FROM gestion__facturas_proveedores
                                           WHERE factura_proveedor_id = ? AND empresa_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$factura) {
            throw new Exception('Factura de proveedor no encontrada');
        }
        if (intval($factura['tabla_estado_registro_id']) !== 3) {
            throw new Exception('Los impuestos solo se modifican con la factura en Borrador');
        }
        $comprobante_id = intval($factura['comprobante_id']);
        if ($comprobante_id <= 0) {
            throw new Exception('La factura no tiene comprobante asociado');
        }

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET tabla_estado_registro_id = 2
                                           WHERE comprobante_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt_cfg = mysqli_prepare($conexion, "SELECT cfg.impuesto_tipo_id, cfg.jurisdiccion_id, it.impuesto_tipo
                                               FROM gestion__empresas_impuestos_config cfg
                                               INNER JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
                                               WHERE cfg.empresa_impuesto_config_id = ? AND cfg.empresa_id = ?
                                                 AND cfg.tabla_estado_registro_id = 1 AND it.aplica_compra = 1");
        $stmt_ins = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_impuestos
                                               (comprobante_id, empresa_impuesto_config_id, impuesto_tipo_id, jurisdiccion_id, tipo_origen,
                                                base_imponible, alicuota, importe, detalle_origen, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $total = 0.0;
        $cantidad = 0;
        foreach ((is_array($impuestos) ? $impuestos : []) as $imp) {
            $config_id = intval($imp['empresa_impuesto_config_id'] ?? 0);
            $importe = round(floatval($imp['importe'] ?? 0), 2);
            if ($config_id <= 0 || abs($importe) < 0.005) {
                continue;   // filas sugeridas por la configuración que quedaron en cero
            }
            if ($importe < 0) {
                throw new Exception('El importe de un impuesto no puede ser negativo (en una nota de crédito lo invierte el tipo de comprobante)');
            }
            mysqli_stmt_bind_param($stmt_cfg, "ii", $config_id, $empresa_idx);
            mysqli_stmt_execute($stmt_cfg);
            $cfg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cfg));
            if (!$cfg) {
                throw new Exception("La configuración de impuesto $config_id no es un impuesto de compras activo de la empresa");
            }
            $tipo_origen = in_array($imp['tipo_origen'] ?? '', ['manual', 'padron', 'regla'], true) ? $imp['tipo_origen'] : 'manual';
            $impuesto_tipo_id = intval($cfg['impuesto_tipo_id']);
            $jurisdiccion_id = $cfg['jurisdiccion_id'] !== null ? intval($cfg['jurisdiccion_id']) : null;
            $base = round(floatval($imp['base_imponible'] ?? 0), 2);
            $alicuota = floatval($imp['alicuota'] ?? 0);
            $detalle = (isset($imp['detalle_origen']) && $imp['detalle_origen'] !== '') ? mb_substr(trim((string)$imp['detalle_origen']), 0, 255) : null;
            mysqli_stmt_bind_param($stmt_ins, "iiiisddds", $comprobante_id, $config_id, $impuesto_tipo_id, $jurisdiccion_id,
                                   $tipo_origen, $base, $alicuota, $importe, $detalle);
            if (!mysqli_stmt_execute($stmt_ins)) {
                throw new Exception('Error al guardar impuesto: ' . mysqli_stmt_error($stmt_ins));
            }
            $total += $importe;
            $cantidad++;
        }
        mysqli_stmt_close($stmt_cfg);
        mysqli_stmt_close($stmt_ins);
        $total = round($total, 2);

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET otros_impuestos = ? WHERE factura_proveedor_id = ?");
        mysqli_stmt_bind_param($stmt, "di", $total, $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET importe_otros_impuestos = ? WHERE comprobante_id = ?");
        mysqli_stmt_bind_param($stmt, "di", $total, $comprobante_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$en_transaccion) {
            mysqli_commit($conexion);
        }
        error_log("=== FIN guardarImpuestosFactura: $cantidad impuestos, total $total ===");
        return ['success' => true, 'cantidad' => $cantidad, 'total' => $total];
    } catch (Exception $e) {
        if ($en_transaccion) {
            throw $e;
        }
        mysqli_rollback($conexion);
        error_log("ERROR en guardarImpuestosFactura: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function obtenerImpuestosFactura($conexion, $factura_proveedor_id) {
    // Primero obtener el comprobante_id
    $tabla_origen_id = 84;
    
    $sql_comprobante = "SELECT comprobante_id 
                        FROM gestion__comprobantes 
                        WHERE tabla_origen_id = ? 
                        AND registro_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_comprobante);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comprobante = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$comprobante) return [];
    
    // Obtener los impuestos del comprobante junto con la configuración completa
    $sql = "SELECT ci.*, 
                   config.impuesto_tipo_id,
                   it.impuesto_tipo,
                   config.jurisdiccion_id,
                   j.jurisdiccion_nombre,
                   config.condicion_fiscal_id,
                   cf.condicion_fiscal,
                   config.base_calculo,
                   config.minimo_imponible,
                   config.monto_fijo,
                   config.aplica_siempre,
                   config.prioridad,
                   config.tipo_calculo
            FROM gestion__comprobantes_impuestos ci
            LEFT JOIN gestion__empresas_impuestos_config config 
                ON ci.empresa_impuesto_config_id = config.empresa_impuesto_config_id
            LEFT JOIN gestion__impuestos_tipos it 
                ON config.impuesto_tipo_id = it.impuesto_tipo_id
            LEFT JOIN gestion__jurisdicciones j 
                ON config.jurisdiccion_id = j.jurisdiccion_id
            LEFT JOIN gestion__condiciones_fiscales cf 
                ON config.condicion_fiscal_id = cf.condicion_fiscal_id
            WHERE ci.comprobante_id = ?
            AND ci.tabla_estado_registro_id = 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $comprobante['comprobante_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $impuestos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $impuestos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $impuestos;
}

// $en_transaccion = true: corre dentro de la transacción del llamador (no abre ni confirma la suya).
function actualizarCostosProducto($conexion, $producto_id, $empresa_id, $nuevo_costo_unitario, $comprobante_id, $usuario_id = null, $en_transaccion = false) {
    error_log("=== actualizarCostosProducto INICIO ===");
    error_log("Producto ID: $producto_id, Nuevo costo: $nuevo_costo_unitario, Comprobante ID: $comprobante_id");
    
    if ($nuevo_costo_unitario <= 0) {
        error_log("Costo inválido, no se actualiza");
        return ['success' => false, 'message' => 'Costo inválido'];
    }
    
    // Origen ID para compras (ajustar según tu BD)
    $origen_compra_id = 1;
    
    // Obtener el costo actual si existe
    $sql_select = "SELECT costo_actual FROM gestion__productos_costos 
                   WHERE empresa_id = ? AND producto_id = ? 
                   AND tabla_estado_registro_id = 1";
    
    $stmt = mysqli_prepare($conexion, $sql_select);
    if (!$stmt) {
        error_log("Error preparando SELECT: " . mysqli_error($conexion));
        return ['success' => false, 'message' => 'Error en consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $costo_anterior = $row ? floatval($row['costo_actual']) : null;
    mysqli_stmt_close($stmt);
    
    $fecha_actual = date('Y-m-d');
    $moneda_id = emp_moneda_base_id($conexion, $empresa_id);
    $creado_por = $usuario_id ?? 0;
    $estado_registro_id = 1;
    
    // INICIAR TRANSACCIÓN
    if (!$en_transaccion) mysqli_begin_transaction($conexion);
    
    try {
        // 1. INSERTAR EN HISTORIAL
        $sql_historial = "INSERT INTO gestion__productos_costos_historial 
                          (empresa_id, producto_id, costo_anterior, costo_nuevo, moneda_id, 
                           producto_costo_origen_id, comprobante_id, f_desde, 
                           tabla_estado_registro_id, creado_por) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_hist = mysqli_prepare($conexion, $sql_historial);
        if (!$stmt_hist) {
            throw new Exception("Error preparando INSERT historial: " . mysqli_error($conexion));
        }
        
        // TODOS los valores deben ser variables, NO literales
        mysqli_stmt_bind_param($stmt_hist, "iiddiiisii", 
            $empresa_id,           // i
            $producto_id,          // i
            $costo_anterior,       // d (puede ser NULL)
            $nuevo_costo_unitario, // d
            $moneda_id,            // i
            $origen_compra_id,     // i
            $comprobante_id,       // i
            $fecha_actual,         // s
            $estado_registro_id,   // i (variable, no literal 1)
            $creado_por            // i
        );
        
        if (!mysqli_stmt_execute($stmt_hist)) {
            throw new Exception("Error ejecutando INSERT historial: " . mysqli_stmt_error($stmt_hist));
        }
        mysqli_stmt_close($stmt_hist);
        error_log("Historial insertado para producto $producto_id");
        
        // 2. ACTUALIZAR O INSERTAR EN gestion__productos_costos
        if ($row) {
            // Actualizar costo existente
            $sql_update = "UPDATE gestion__productos_costos 
                           SET costo_actual = ?, 
                               moneda_id = ?,
                               producto_costo_origen_id = ?,
                               comprobante_id = ?,
                               f_actualizacion = ?,
                               observaciones = ?
                           WHERE empresa_id = ? AND producto_id = ? 
                           AND tabla_estado_registro_id = 1";
            
            $stmt_update = mysqli_prepare($conexion, $sql_update);
            if (!$stmt_update) {
                throw new Exception("Error preparando UPDATE costos: " . mysqli_error($conexion));
            }
            
            $observaciones = "Actualizado por compra (comprobante ID: $comprobante_id)";
            
            // TODOS los valores como variables
            mysqli_stmt_bind_param($stmt_update, "diiissii", 
                $nuevo_costo_unitario,  // d
                $moneda_id,             // i
                $origen_compra_id,      // i
                $comprobante_id,        // i
                $fecha_actual,          // s
                $observaciones,         // s
                $empresa_id,            // i
                $producto_id            // i
            );
            
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Error ejecutando UPDATE costos: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
            error_log("Costo actualizado para producto $producto_id: " . ($costo_anterior ?? 'NULL') . " -> $nuevo_costo_unitario");
            
        } else {
            // Insertar nuevo registro de costo
            $sql_insert = "INSERT INTO gestion__productos_costos 
                          (empresa_id, producto_id, costo_actual, moneda_id, 
                           producto_costo_origen_id, comprobante_id, f_actualizacion, 
                           observaciones, tabla_estado_registro_id, creado_por) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_insert = mysqli_prepare($conexion, $sql_insert);
            if (!$stmt_insert) {
                throw new Exception("Error preparando INSERT costos: " . mysqli_error($conexion));
            }
            
            $observaciones = "Creado (comprobante ID: $comprobante_id)";
            
            // TODOS los valores como variables
            mysqli_stmt_bind_param($stmt_insert, "iidiiissii", 
                $empresa_id,            // i
                $producto_id,           // i
                $nuevo_costo_unitario,  // d
                $moneda_id,             // i
                $origen_compra_id,      // i
                $comprobante_id,        // i
                $fecha_actual,          // s
                $observaciones,         // s
                $estado_registro_id,    // i (variable)
                $creado_por             // i
            );
            
            if (!mysqli_stmt_execute($stmt_insert)) {
                throw new Exception("Error ejecutando INSERT costos: " . mysqli_stmt_error($stmt_insert));
            }
            mysqli_stmt_close($stmt_insert);
            error_log("Nuevo registro de costo insertado para producto $producto_id: $nuevo_costo_unitario");
        }
        
        if (!$en_transaccion) mysqli_commit($conexion);
        error_log("=== actualizarCostosProducto EXITO ===");
        return ['success' => true, 'message' => 'Costo actualizado correctamente'];
        
    } catch (Exception $e) {
        if (!$en_transaccion) mysqli_rollback($conexion);
        error_log("ERROR en actualizarCostosProducto: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
// Agregar al final del archivo, antes del último 

/**
 * Generar asiento contable a partir de una factura de proveedor
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $factura_proveedor_id ID de la factura
 * @param int $empresa_id ID de la empresa
 * @return array Resultado de la operación
 */
/**
 * Construye un SQL ejecutable a partir de una consulta preparada y sus parámetros
 * @param string $sql Consulta con marcadores ?
 * @param string $types Cadena de tipos (i, d, s)
 * @param array $params Valores de los parámetros
 * @return string SQL listo para ejecutar en HeidiSQL/phpMyAdmin
 */
function buildExecutableSQL($sql, $types, $params) {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $type_arr = str_split($types);
    $i = 0;
    $result = '';
    $pos = 0;
    $len = strlen($sql);
    $param_idx = 0;
    $num_params = count($params);
    
    while ($pos < $len) {
        $next = strpos($sql, '?', $pos);
        if ($next === false) {
            $result .= substr($sql, $pos);
            break;
        }
        $result .= substr($sql, $pos, $next - $pos);
        // Reemplazar ? por el valor correspondiente
        if ($param_idx < $num_params) {
            $val = $params[$param_idx];
            $type = $type_arr[$param_idx] ?? 's';
            if ($type == 'i' || $type == 'd') {
                // Números
                $result .= is_null($val) ? 'NULL' : (string)$val;
            } else {
                // Strings y fechas
                $result .= is_null($val) ? 'NULL' : "'" . mysqli_real_escape_string($GLOBALS['conexion'], $val) . "'";
            }
            $param_idx++;
        } else {
            $result .= 'NULL';
        }
        $pos = $next + 1;
    }
    return $result;
}
// Asiento de una factura / NC / ND de proveedor. Un solo importe con signo por renglón (> 0 Debe, < 0 Haber).
// Cuentas (sin ids fijos):
//   proveedor: entidad.cont_cuenta_id_proveedor -> gestion__cont_parametros.cuenta_proveedores_id (default de la empresa)
//   compra:    producto.cont_cuenta_compra_id -> producto.cont_cuenta_id -> tipo de producto.cont_cuenta_compra_default_id
//   IVA:       alícuota de la línea.cont_cuenta_compra_id
//   impuestos adicionales: gestion__empresas_impuestos_config.cont_cuenta_id
// El signo del tipo de comprobante ('-' = nota de crédito) invierte todo el asiento. Un tipo con
// impacta_contabilidad = 0 no genera asiento. $en_transaccion = true: corre dentro de la transacción del
// llamador (ejecutarTransicionEstado), sin abrir ni confirmar la suya.
function generarAsientoContableFactura($conexion, $factura_proveedor_id, $empresa_id, $en_transaccion = false)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    error_log("=== generarAsientoContableFactura INICIO factura: $factura_proveedor_id, empresa: $empresa_id ===");

    $stmt = mysqli_prepare($conexion, "SELECT fp.*, ct.comprobante_tipo, ct.signo AS tipo_signo, ct.impacta_contabilidad,
                                              e.entidad_nombre, e.cont_cuenta_id_proveedor, cp.cuenta_proveedores_id, cp.cuenta_gastos_defecto_id
                                       FROM gestion__facturas_proveedores fp
                                       LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
                                       LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
                                       LEFT JOIN gestion__cont_parametros cp ON cp.empresa_id = fp.empresa_id AND cp.tabla_estado_registro_id = 1
                                       WHERE fp.factura_proveedor_id = ? AND fp.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$factura) {
        return ['success' => false, 'message' => 'Factura no encontrada'];
    }
    if (intval($factura['impacta_contabilidad'] ?? 1) === 0) {
        error_log("Tipo {$factura['comprobante_tipo_id']} sin impacto contable: no se genera asiento");
        return ['success' => true, 'message' => 'El tipo de comprobante no impacta contabilidad', 'asiento_id' => null];
    }
    $comprobante_id = intval($factura['comprobante_id'] ?? 0);
    if ($comprobante_id <= 0) {
        return ['success' => false, 'message' => 'La factura no tiene comprobante asociado'];
    }

    $signo = (($factura['tipo_signo'] ?? '+') === '-') ? -1 : 1;
    $pv = str_pad($factura['comprobante_pv'] ?? 0, 4, '0', STR_PAD_LEFT);
    $nro = str_pad($factura['comprobante_nro'] ?? 0, 8, '0', STR_PAD_LEFT);
    $descripcion = ($factura['comprobante_tipo'] ?? 'Factura') . " $pv-$nro - " . ($factura['entidad_nombre'] ?? 'Proveedor')
                 . ' - ' . date('d/m/Y', strtotime($factura['f_emision']));

    $cuenta_proveedor_id = intval($factura['cont_cuenta_id_proveedor'] ?? 0) ?: intval($factura['cuenta_proveedores_id'] ?? 0);
    if ($cuenta_proveedor_id <= 0) {
        return ['success' => false, 'message' => 'el proveedor no tiene cuenta contable (cargarla en Proveedores) y la empresa no tiene cuenta de proveedores por defecto (Parámetros contables)'];
    }

    // Líneas con su cuenta de compra y la cuenta de IVA de su alícuota
    $stmt = mysqli_prepare($conexion, "SELECT d.producto_id, d.neto_gravado, d.no_gravado, d.exento, d.iva_importe, d.iva_alicuota_id,
                                              COALESCE(NULLIF(p.cont_cuenta_compra_id, 0), NULLIF(p.cont_cuenta_id, 0),
                                                       NULLIF(pt.cont_cuenta_compra_default_id, 0)) AS cuenta_compra_id,
                                              NULLIF(iva.cont_cuenta_compra_id, 0) AS cuenta_iva_id,
                                              p.producto_codigo
                                       FROM gestion__facturas_proveedores_detalle d
                                       LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                                       LEFT JOIN gestion__productos_tipos pt ON pt.producto_tipo_id = p.producto_tipo_id AND pt.empresa_id = p.empresa_id
                                       LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = d.iva_alicuota_id AND iva.empresa_id = d.empresa_id
                                       WHERE d.factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $renglones = [];      // cuenta|producto => importe
    $faltantes = [];
    $hay_lineas = false;
    while ($d = mysqli_fetch_assoc($result)) {
        $hay_lineas = true;
        $importe_compra = floatval($d['neto_gravado']) + floatval($d['no_gravado']) + floatval($d['exento']);
        if (abs($importe_compra) > 0.0001) {
            if (empty($d['cuenta_compra_id'])) {
                $faltantes[] = 'cuenta contable de compra del producto ' . ($d['producto_codigo'] ?? $d['producto_id'])
                             . ' (cargarla en el producto o en su tipo de producto)';
            } else {
                $clave = $d['cuenta_compra_id'] . '|' . intval($d['producto_id']);
                $renglones[$clave] = ($renglones[$clave] ?? 0) + $importe_compra;
            }
        }
        $iva = floatval($d['iva_importe']);
        if (abs($iva) > 0.0001) {
            if (empty($d['cuenta_iva_id'])) {
                $faltantes[] = 'cuenta de IVA compras de la alícuota ' . intval($d['iva_alicuota_id']) . ' (Alícuotas de IVA)';
            } else {
                $clave = $d['cuenta_iva_id'] . '|0';
                $renglones[$clave] = ($renglones[$clave] ?? 0) + $iva;
            }
        }
    }
    mysqli_stmt_close($stmt);
    if (!$hay_lineas) {
        return ['success' => false, 'message' => 'La factura no tiene productos'];
    }

    // Impuestos adicionales (percepciones, etc.) del comprobante
    $stmt = mysqli_prepare($conexion, "SELECT ci.importe, cfg.cont_cuenta_id, it.impuesto_tipo
                                       FROM gestion__comprobantes_impuestos ci
                                       LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
                                       LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
                                       WHERE ci.comprobante_id = ? AND ci.tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_impuestos_ci = 0.0;
    while ($imp = mysqli_fetch_assoc($result)) {
        if (abs(floatval($imp['importe'])) < 0.0001) continue;
        if (empty($imp['cont_cuenta_id'])) {
            $faltantes[] = 'cuenta contable del impuesto ' . ($imp['impuesto_tipo'] ?? '') . ' (configuración de impuestos de la empresa)';
            continue;
        }
        $clave = $imp['cont_cuenta_id'] . '|0';
        $renglones[$clave] = ($renglones[$clave] ?? 0) + floatval($imp['importe']);
        $total_impuestos_ci += floatval($imp['importe']);
    }
    mysqli_stmt_close($stmt);

    // Si la factura tiene otros_impuestos en cabecera no desglosados en comprobantes_impuestos:
    $otros_imp_factura = floatval($factura['otros_impuestos'] ?? 0);
    $dif_imp = round($otros_imp_factura - $total_impuestos_ci, 2);
    if ($dif_imp > 0.009) {
        $cuenta_imp_id = 0;
        $q_cfg = mysqli_query($conexion, "SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config 
                                          WHERE empresa_id = $empresa_id AND cont_cuenta_id > 0 AND tabla_estado_registro_id = 1 
                                          ORDER BY prioridad ASC LIMIT 1");
        if ($q_cfg && ($r_cfg = mysqli_fetch_assoc($q_cfg))) {
            $cuenta_imp_id = intval($r_cfg['cont_cuenta_id']);
        }
        if (!$cuenta_imp_id) {
            $cuenta_imp_id = intval($factura['cuenta_gastos_defecto_id'] ?? 0);
        }
        if ($cuenta_imp_id > 0) {
            $clave = $cuenta_imp_id . '|0';
            $renglones[$clave] = ($renglones[$clave] ?? 0) + $dif_imp;
        }
    }

    if (!empty($faltantes)) {
        return ['success' => false, 'message' => 'falta configurar ' . implode('; ', array_unique($faltantes))];
    }

    $total_factura = round(floatval($factura['total']), 2);
    $total_debe = round(array_sum($renglones), 2);
    if (abs($total_debe - $total_factura) > 0.01) {
        return ['success' => false, 'message' => "Asiento no balanceado: compras + IVA + impuestos = $total_debe, total de la factura = $total_factura"];
    }

    $detalles_asiento = [];
    foreach ($renglones as $clave => $importe) {
        list($cuenta_id, $producto_id) = array_map('intval', explode('|', $clave));
        $detalles_asiento[] = [
            'cuenta_id' => $cuenta_id,
            'producto_id' => $producto_id ?: null,
            'importe' => round($signo * $importe, 2),
            'descripcion' => $producto_id ? 'Compra - Producto ID: ' . $producto_id : 'Impuestos'
        ];
    }
    // Proveedor: en facturas ('+') va al Haber (importe negativo); en NC ('-') va al Debe (importe positivo)
    $detalles_asiento[] = [
        'cuenta_id' => $cuenta_proveedor_id,
        'producto_id' => null,
        'importe' => round(-$signo * $total_factura, 2),
        'descripcion' => 'Proveedor: ' . ($factura['entidad_nombre'] ?? '')
    ];

    $stmt = mysqli_prepare($conexion, "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $asiento_id = $fila ? intval($fila['cont_asiento_id']) : null;

    if (!$en_transaccion) {
        mysqli_begin_transaction($conexion);
    }
    try {
        // Todas las cuentas tienen que ser del plan de cuentas de la empresa
        emp_validar_cuentas_asiento($conexion, $empresa_id, array_column($detalles_asiento, 'cuenta_id'));
        $f_asiento = $factura['f_contabilidad'] ?: $factura['f_emision'];
        $timestamp = strtotime($f_asiento);
        $anio = intval(date('Y', $timestamp));
        $mes = intval(date('m', $timestamp));
        $estado_asiento = 3;
        $sucursal_id = intval($factura['sucursal_id'] ?? 0);
        $deposito_id = intval($factura['deposito_id'] ?? 0);
        $entidad_id = intval($factura['entidad_id'] ?? 0);
        $moneda_id = emp_moneda_id($conexion, $empresa_id, $factura['moneda_id'] ?? 0);
        $tipo_cambio = floatval($factura['tipo_cambio'] ?? 1);
        $tipo_asiento_id = emp_tipo_asiento_id($conexion, $empresa_id, 'AUT');

        if ($asiento_id) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_asientos
                                               SET sucursal_id = ?, deposito_id = ?, entidad_id = ?,
                                                   f_asiento = ?, anio = ?, mes = ?, descripcion = ?,
                                                   moneda_id = ?, tipo_cambio = ?, tabla_estado_registro_id = ?
                                               WHERE cont_asiento_id = ?");
            mysqli_stmt_bind_param($stmt, "iiisiisidii", $sucursal_id, $deposito_id, $entidad_id, $f_asiento, $anio, $mes,
                                   $descripcion, $moneda_id, $tipo_cambio, $estado_asiento, $asiento_id);
        } else {
            $usuario_id = intval($_SESSION['usuario_id'] ?? 0);
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__cont_asientos
                                               (empresa_id, sucursal_id, deposito_id, comprobante_id, entidad_id,
                                                cont_tipo_asiento_id, f_asiento, anio, mes, descripcion,
                                                moneda_id, tipo_cambio, usuario_creacion_id, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiiiiisiisidii", $empresa_id, $sucursal_id, $deposito_id, $comprobante_id, $entidad_id, $tipo_asiento_id,
                                   $f_asiento, $anio, $mes, $descripcion, $moneda_id, $tipo_cambio, $usuario_id, $estado_asiento);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error guardando asiento: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        if (!$asiento_id) {
            $asiento_id = intval(mysqli_insert_id($conexion));
        } else {
            $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $asiento_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // entidad_id (el proveedor) va en todos los renglones; producto_id solo en los de compra de un producto.
        $entidad_det_id = $entidad_id ?: null;
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__cont_asientos_detalles
                                           (cont_asiento_id, cuenta_id, entidad_id, producto_id, importe_local, moneda_id, tipo_cambio, descripcion, tipo, estado, comprobante_id)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'A', 'activo', ?)");
        foreach ($detalles_asiento as $det) {
            $cuenta_det_id = $det['cuenta_id'];
            $producto_det_id = $det['producto_id'];
            $importe_det = $det['importe'];
            $descripcion_det = $det['descripcion'];
            mysqli_stmt_bind_param($stmt, "iiiididsi", $asiento_id, $cuenta_det_id, $entidad_det_id, $producto_det_id,
                                   $importe_det, $moneda_id, $tipo_cambio, $descripcion_det, $comprobante_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error insertando detalle: ' . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);

        if (!$en_transaccion) {
            mysqli_commit($conexion);
        }
        error_log("Asiento $asiento_id guardado para comprobante $comprobante_id (signo $signo)");
        return ['success' => true, 'message' => 'Asiento generado correctamente', 'asiento_id' => $asiento_id];
    } catch (Exception $e) {
        if (!$en_transaccion) {
            mysqli_rollback($conexion);
        }
        error_log("ERROR generarAsientoContableFactura: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function obtenerEstadoInicialPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    
    // Buscar la función con origen_id = 0 (botón agregar)
    // El destino_id será el estado inicial
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
        return 3; // Fallback a Borrador
    }
    
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row && $row['tabla_estado_registro_destino_id']) {
        error_log("Estado inicial obtenido de configuración: " . $row['tabla_estado_registro_destino_id']);
        return $row['tabla_estado_registro_destino_id'];
    }
    
    error_log("No se encontró configuración de estado inicial, usando fallback: 3");
    return 3; // Borrador por defecto
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

/* =========================================================================
 * ORDEN DE COMPRA -> FACTURA DE PROVEEDOR
 * (sql/mantenimiento/20261008_compras_oc_factura_01_vinculo.sql)
 *
 * La factura importa las líneas pendientes de una OC del mismo proveedor:
 * cabecera orden_compra_id + ordenes_compra_detalle_id por línea. Puede
 * traer más cantidad o productos que no están en la OC (líneas sin vínculo).
 * Solo las facturas CONFIRMADAS (5) cuentan como recibido; el stock lo mueve
 * la factura (registrarStockFacturaProveedor), nunca la OC.
 * ========================================================================= */

// Estados de OC desde los que se puede importar / que se recalculan solos.
const FP_OC_ESTADOS_IMPORTABLES = [5, 7, 41];   // Confirmado, Pend. Recepción, Recibido parcial
const FP_OC_ESTADO_PEND_RECEPCION = 7;
const FP_OC_ESTADO_RECIBIDO = 8;
const FP_OC_ESTADO_RECIBIDO_PARCIAL = 41;
const FP_ESTADO_CONFIRMADA = 5;

/**
 * Líneas de una OC con lo pendiente de recibir. "en_otras_facturas" = cantidad
 * ya cargada en facturas sin confirmar (Borrador / Pend. Validación) que no
 * son $factura_excluir_id, para no importar dos veces lo mismo.
 */
function fpOcLineas($conexion, $empresa_id, $orden_compra_id, $factura_excluir_id = 0)
{
    $sql = "SELECT d.ordenes_compra_detalle_id, d.producto_id, d.cantidad, d.cantidad_recibida,
                   d.precio_unitario, d.no_gravado, d.exento, d.iva_alicuota_id, d.iva_porcentaje,
                   p.producto_codigo, p.producto_nombre,
                   COALESCE((SELECT SUM(fd.cantidad)
                             FROM gestion__facturas_proveedores_detalle fd
                             JOIN gestion__facturas_proveedores f ON f.factura_proveedor_id = fd.factura_proveedor_id
                             WHERE fd.ordenes_compra_detalle_id = d.ordenes_compra_detalle_id
                               AND f.tabla_estado_registro_id IN (3, 4)
                               AND f.factura_proveedor_id <> ?), 0) AS en_otras_facturas
            FROM gestion__ordenes_compra_detalle d
            JOIN gestion__ordenes_compra oc ON oc.orden_compra_id = d.orden_compra_id
            LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
            WHERE d.orden_compra_id = ? AND oc.empresa_id = ?
            ORDER BY d.ordenes_compra_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando fpOcLineas: " . mysqli_error($conexion));
        return [];
    }
    $factura_excluir_id = intval($factura_excluir_id);
    $orden_compra_id = intval($orden_compra_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, "iii", $factura_excluir_id, $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $lineas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $cantidad = floatval($fila['cantidad']);
        $recibida = floatval($fila['cantidad_recibida']);
        $en_otras = floatval($fila['en_otras_facturas']);
        $lineas[] = [
            'ordenes_compra_detalle_id' => intval($fila['ordenes_compra_detalle_id']),
            'producto_id' => intval($fila['producto_id']),
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'cantidad' => $cantidad,
            'cantidad_recibida' => $recibida,
            'en_otras_facturas' => $en_otras,
            'pendiente' => max(0, round($cantidad - $recibida - $en_otras, 4)),
            'precio_unitario' => floatval($fila['precio_unitario']),
            'no_gravado' => floatval($fila['no_gravado']),
            'exento' => floatval($fila['exento']),
            'iva_alicuota_id' => intval($fila['iva_alicuota_id'] ?? 0),
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 0),
        ];
    }
    mysqli_stmt_close($stmt);
    return $lineas;
}

/** OC del proveedor en estados importables que todavía tienen algo pendiente. */
function fpOcPendientesProveedor($conexion, $empresa_id, $entidad_id, $factura_excluir_id = 0)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $estados = implode(',', array_map('intval', FP_OC_ESTADOS_IMPORTABLES));

    $sql = "SELECT oc.orden_compra_id, oc.comprobante_nro, oc.f_emision, oc.f_entrega_estimada, oc.total,
                   oc.tabla_estado_registro_id, er.estado_registro, s.sucursal_nombre
            FROM gestion__ordenes_compra oc
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = oc.tabla_estado_registro_id
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = oc.sucursal_id AND s.empresa_id = oc.empresa_id
            WHERE oc.empresa_id = ? AND oc.entidad_id = ?
              AND oc.tabla_estado_registro_id IN ($estados)";

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $uid = intval($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_id, 'oc.sucursal_id');
        }
    }
    $sql .= " ORDER BY oc.f_emision DESC, oc.orden_compra_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando fpOcPendientesProveedor: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ordenes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $ordenes[] = $fila;
    }
    mysqli_stmt_close($stmt);

    // Solo las que tienen alguna línea pendiente
    $salida = [];
    foreach ($ordenes as $oc) {
        $lineas = fpOcLineas($conexion, $empresa_id, $oc['orden_compra_id'], $factura_excluir_id);
        $pendientes = array_filter($lineas, function ($l) { return $l['pendiente'] > 0; });
        if (count($pendientes) === 0) {
            continue;
        }
        $oc['orden_compra_id'] = intval($oc['orden_compra_id']);
        $oc['lineas_pendientes'] = count($pendientes);
        $oc['lineas_total'] = count($lineas);
        $salida[] = $oc;
    }
    return $salida;
}

/**
 * Valida el vínculo con la OC antes de grabar la factura y devuelve los
 * detalles saneados: un ordenes_compra_detalle_id que no es de esa OC o cuyo
 * producto no coincide se descarta (la línea queda como "sin OC").
 * $orden_previa_id: OC que la factura ya tenía (al editar se acepta aunque la
 * OC ya esté cerrada en 8). Lanza Exception si la OC no es válida.
 */
function fpValidarVinculoOC($conexion, $empresa_id, $entidad_id, $orden_compra_id, $detalles, $orden_previa_id = 0)
{
    $orden_compra_id = intval($orden_compra_id);
    if ($orden_compra_id <= 0) {
        foreach ($detalles as &$d) {
            $d['ordenes_compra_detalle_id'] = null;
        }
        unset($d);
        return [null, $detalles];
    }

    $stmt = mysqli_prepare($conexion, "SELECT entidad_id, tabla_estado_registro_id FROM gestion__ordenes_compra
                                       WHERE orden_compra_id = ? AND empresa_id = ?");
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $oc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$oc) {
        throw new Exception('La orden de compra vinculada no existe');
    }
    if (intval($oc['entidad_id']) !== intval($entidad_id)) {
        throw new Exception('La orden de compra vinculada es de otro proveedor');
    }
    $estado_oc = intval($oc['tabla_estado_registro_id']);
    $aceptables = FP_OC_ESTADOS_IMPORTABLES;
    if ($orden_compra_id === intval($orden_previa_id)) {
        $aceptables[] = FP_OC_ESTADO_RECIBIDO;
    }
    if (!in_array($estado_oc, $aceptables, true)) {
        throw new Exception('La orden de compra vinculada no está en un estado que permita recibir mercadería');
    }

    $producto_por_linea = [];
    foreach (fpOcLineas($conexion, $empresa_id, $orden_compra_id) as $l) {
        $producto_por_linea[$l['ordenes_compra_detalle_id']] = $l['producto_id'];
    }
    foreach ($detalles as &$d) {
        $linea_id = intval($d['ordenes_compra_detalle_id'] ?? 0);
        if ($linea_id <= 0 || !isset($producto_por_linea[$linea_id])
            || intval($producto_por_linea[$linea_id]) !== intval($d['producto_id'] ?? 0)) {
            $d['ordenes_compra_detalle_id'] = null;
        } else {
            $d['ordenes_compra_detalle_id'] = $linea_id;
        }
    }
    unset($d);

    return [$orden_compra_id, $detalles];
}

/** Factura -> OC (cabecera). NULL desvincula. */
function fpGuardarOrdenCompraFactura($conexion, $factura_proveedor_id, $orden_compra_id)
{
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET orden_compra_id = ? WHERE factura_proveedor_id = ?");
    $factura_proveedor_id = intval($factura_proveedor_id);
    mysqli_stmt_bind_param($stmt, "ii", $orden_compra_id, $factura_proveedor_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error al vincular la orden de compra: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
}

/**
 * Recalcula cantidad_recibida de cada línea de la OC (suma de las líneas de
 * facturas CONFIRMADAS vinculadas) y su estado derivado. Idempotente.
 * Solo mueve el estado de OC en Confirmado / Pend. Recepción / Recibido
 * parcial: todo cubierto -> 8, algo recibido -> 41. Una OC en 8 (completa o
 * cerrada a mano) no se toca. Corre dentro de la transacción del llamador.
 */
function ocRecalcularRecepcion($conexion, $orden_compra_id)
{
    $orden_compra_id = intval($orden_compra_id);
    error_log("=== INICIO ocRecalcularRecepcion OC: $orden_compra_id ===");

    $stmt = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id FROM gestion__ordenes_compra
                                       WHERE orden_compra_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $oc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$oc) {
        return;
    }

    $estado_confirmada = FP_ESTADO_CONFIRMADA;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__ordenes_compra_detalle d
            SET d.cantidad_recibida = COALESCE((
                SELECT SUM(fd.cantidad)
                FROM gestion__facturas_proveedores_detalle fd
                JOIN gestion__facturas_proveedores f ON f.factura_proveedor_id = fd.factura_proveedor_id
                WHERE fd.ordenes_compra_detalle_id = d.ordenes_compra_detalle_id
                  AND f.tabla_estado_registro_id = ?
            ), 0)
            WHERE d.orden_compra_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $estado_confirmada, $orden_compra_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error al recalcular lo recibido de la OC: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    $estado_actual = intval($oc['tabla_estado_registro_id']);
    if (!in_array($estado_actual, FP_OC_ESTADOS_IMPORTABLES, true)) {
        return;
    }

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS lineas,
                                              SUM(cantidad_recibida >= cantidad) AS cubiertas,
                                              SUM(cantidad_recibida > 0) AS con_recepcion
                                       FROM gestion__ordenes_compra_detalle WHERE orden_compra_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $lineas = intval($r['lineas'] ?? 0);
    if ($lineas > 0 && intval($r['cubiertas']) === $lineas) {
        $nuevo = FP_OC_ESTADO_RECIBIDO;
    } elseif (intval($r['con_recepcion']) > 0) {
        $nuevo = FP_OC_ESTADO_RECIBIDO_PARCIAL;
    } elseif ($estado_actual === FP_OC_ESTADO_RECIBIDO_PARCIAL) {
        $nuevo = FP_OC_ESTADO_PEND_RECEPCION;   // ya no queda nada recibido
    } else {
        $nuevo = $estado_actual;
    }

    if ($nuevo !== $estado_actual) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ordenes_compra SET tabla_estado_registro_id = ? WHERE orden_compra_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $nuevo, $orden_compra_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al actualizar el estado de la OC: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        error_log("OC $orden_compra_id: estado $estado_actual -> $nuevo");
    }
}

/** Recalcula todas las OC que toca una factura (cabecera y líneas), en orden de id. */
function ocRecalcularRecepcionPorFactura($conexion, $factura_proveedor_id)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $stmt = mysqli_prepare($conexion, "SELECT orden_compra_id FROM gestion__facturas_proveedores
                                       WHERE factura_proveedor_id = ? AND orden_compra_id IS NOT NULL
                                       UNION
                                       SELECT ocd.orden_compra_id
                                       FROM gestion__facturas_proveedores_detalle fd
                                       JOIN gestion__ordenes_compra_detalle ocd ON ocd.ordenes_compra_detalle_id = fd.ordenes_compra_detalle_id
                                       WHERE fd.factura_proveedor_id = ?
                                       ORDER BY 1");
    mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ordenes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $ordenes[] = intval($fila['orden_compra_id']);
    }
    mysqli_stmt_close($stmt);

    foreach ($ordenes as $orden_compra_id) {
        ocRecalcularRecepcion($conexion, $orden_compra_id);
    }
}

/* =========================================================================
 * NOTAS DE CRÉDITO DE PROVEEDOR (decisión Pablo 2026-10-09)
 * sql/mantenimiento/20261009_notas_credito_aplicacion_01.sql
 *
 * Igual que en ventas (ventas_facturas_model.php): la NC/ND guarda su comprobante asociado; al confirmarse,
 * una NC cuyo asociado tiene saldo se le aplica (imputación directa) con una OP en $0 que la documenta.
 * Al confirmarse una factura/ND con NC del proveedor con saldo, la respuesta trae notas_credito_disponibles.
 * La devolución de dinero de una NC con saldo se cobra en Cobranzas (recibo del proveedor -> NC).
 * ========================================================================= */

function fpTipoEsNota($conexion, $empresa_id, $tipo_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__comprobantes_tipos_dependencias
                                       WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?) AND tabla_estado_registro_id = 1 LIMIT 1");
    $tipo_id = intval($tipo_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $es = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $es;
}

/** Comprobantes confirmados del proveedor a los que se puede asociar una NC/ND (con su saldo). */
function fpComprobantesAsociables($conexion, $empresa_id, $entidad_id, $tipo_id = 0)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $tipo_id = intval($tipo_id);
    if ($entidad_id <= 0) return [];
    $tabla_id = intval(obtenerTablaOrigenPorPagina($conexion, 57) ?: 84);
    $filtro_tipo = $tipo_id > 0
        ? " AND c.comprobante_tipo_id IN (SELECT d.comprobante_tipo_padre_id FROM gestion__comprobantes_tipos_dependencias d
                                          WHERE d.comprobante_tipo_id = $tipo_id AND d.empresa_id IN (0, $empresa_id) AND d.tabla_estado_registro_id = 1)"
        : '';
    $sql = "SELECT c.comprobante_id, ct.comprobante_tipo, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro, c.f_emision,
                   c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.tabla_origen_id = ?
              AND c.tabla_estado_registro_id IN (5, 39, 40) $filtro_tipo
            ORDER BY c.f_emision DESC, c.comprobante_id DESC
            LIMIT 100";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $entidad_id, $tabla_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['comprobante_id'] = intval($r['comprobante_id']);
        $r['importe_total'] = floatval($r['importe_total']);
        $r['importe_pendiente'] = floatval($r['importe_pendiente']);
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Valida y guarda el comprobante asociado de una NC/ND de proveedor (NULL para facturas). */
function fpGuardarComprobanteAsociado($conexion, $empresa_id, $factura_proveedor_id, $tipo_id, $entidad_id, $comprobante_asociado_id)
{
    $comprobante_asociado_id = intval($comprobante_asociado_id);
    $asociado = null;
    if ($comprobante_asociado_id > 0 && fpTipoEsNota($conexion, $empresa_id, $tipo_id)) {
        $validos = array_column(fpComprobantesAsociables($conexion, $empresa_id, $entidad_id, $tipo_id), 'comprobante_id');
        if (!in_array($comprobante_asociado_id, $validos, true)) {
            throw new Exception('El comprobante asociado no es un comprobante confirmado de este proveedor compatible con el tipo de nota.');
        }
        $asociado = $comprobante_asociado_id;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET comprobante_asociado_id = ? WHERE factura_proveedor_id = ? AND empresa_id = ?");
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'iii', $asociado, $factura_proveedor_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Aplica NC de proveedor con saldo a un comprobante del proveedor y emite la OP en $0 que lo documenta.
 * $aplicaciones: [['nc_comprobante_id' => int, 'importe' => float (<= 0: todo lo posible)], ...]
 */
function fpAplicarNotasCredito($conexion, $empresa_id, $destino_comprobante_id, $aplicaciones, $usuario_id, $en_transaccion = false)
{
    require_once __DIR__ . '/tesoreria_core_model.php';
    $conn = $conexion;   // el modelo hace "$conexion = $conn" al cargarse; acá se carga dentro de una función
    require_once __DIR__ . '/pagos_model.php';
    $empresa_id = intval($empresa_id);
    $destino_comprobante_id = intval($destino_comprobante_id);
    $usuario_id = intval($usuario_id);
    if (empty($aplicaciones)) return ['success' => false, 'error' => 'No se indicaron notas de crédito a aplicar.'];

    $stmt = mysqli_prepare($conexion, "SELECT c.comprobante_id, c.entidad_id, c.sucursal_id, c.moneda_id, c.comprobante_pv, c.comprobante_nro, ct.comprobante_tipo
                                       FROM gestion__comprobantes c
                                       JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                       WHERE c.comprobante_id = ? AND c.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $destino_comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $dest = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$dest) return ['success' => false, 'error' => 'Comprobante destino no encontrado.'];

    if (!$en_transaccion) mysqli_begin_transaction($conexion);
    try {
        $aplicado_total = 0.0;
        $detalle = [];
        foreach ($aplicaciones as $a) {
            $nc_id = intval($a['nc_comprobante_id'] ?? 0);
            $importe = tesoreriaAplicarNotaCredito($conexion, $empresa_id, $nc_id, $destino_comprobante_id, floatval($a['importe'] ?? 0), $usuario_id);
            if ($importe > 0) {
                $aplicado_total += $importe;
                $nc = $conexion->query("SELECT ct.comprobante_tipo, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro FROM gestion__comprobantes c
                                         JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                         WHERE c.comprobante_id = $nc_id")->fetch_assoc();
                $detalle[] = ($nc ? $nc['comprobante_tipo'] . ' ' . str_pad($nc['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-'
                                    . str_pad($nc['comprobante_nro'], 8, '0', STR_PAD_LEFT) : "NC #$nc_id")
                           . ' por $' . number_format($importe, 2, ',', '.');
            }
        }
        if ($aplicado_total <= 0) throw new Exception('No había saldo para aplicar.');

        // OP en $0 que documenta la aplicación
        $pago_id = guardarPagoBorrador($conexion, [
            'empresa_id' => $empresa_id, 'sucursal_id' => intval($dest['sucursal_id']), 'entidad_id' => intval($dest['entidad_id']),
            'moneda_id' => intval($dest['moneda_id'] ?: 1), 'tipo_cambio' => 1, 'f_emision' => date('Y-m-d'), 'usuario_id' => $usuario_id,
            'pago_cero' => true, 'medios' => [], 'imputaciones' => [],
            'observaciones' => 'Aplicación de NC a ' . $dest['comprobante_tipo'] . ' ' . str_pad($dest['comprobante_pv'], 4, '0', STR_PAD_LEFT)
                             . '-' . str_pad($dest['comprobante_nro'], 8, '0', STR_PAD_LEFT) . ': ' . implode('; ', $detalle),
        ]);
        $op = confirmarPago($conexion, $pago_id, $empresa_id, $usuario_id, true);

        if (!$en_transaccion) mysqli_commit($conexion);
        return ['success' => true, 'aplicado' => round($aplicado_total, 2), 'pago_id' => $pago_id, 'op_nro' => $op['comprobante_nro'] ?? null,
                'message' => 'Notas de crédito aplicadas por $' . number_format($aplicado_total, 2, ',', '.') . ' (OP en $0 N° ' . ($op['comprobante_nro'] ?? '') . ').'];
    } catch (Exception $e) {
        if (!$en_transaccion) mysqli_rollback($conexion);
        if ($en_transaccion) throw $e;
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Genera y confirma una Orden de Pago automática para una factura de proveedor: en efectivo (de contado) o, con
 * $tesoreria_cuenta_id, por débito en esa cuenta bancaria (medio DEB_CTA; Resumen bancario y débitos automáticos).
 * Cumple con el circuito completo: emisión de OP, cancelación de la factura a 0 (CANCELADO),
 * egreso de libro de tesorería y asiento contable balanceado (Debe: Proveedor / Haber: Caja o Banco).
 */
function fpGenerarOrdenPagoAutomatica($conexion, $factura_proveedor_id, $empresa_id, $usuario_id = 1, $en_transaccion = false, $tesoreria_cuenta_id = 0)
{
    require_once __DIR__ . '/tesoreria_core_model.php';
    $conn = $conexion;
    require_once __DIR__ . '/pagos_model.php';

    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id ?: 1);

    $sql = "SELECT fp.factura_proveedor_id, fp.empresa_id, fp.sucursal_id, fp.comprobante_id,
                   fp.comprobante_tipo_id, fp.comprobante_pv, fp.comprobante_nro,
                   fp.entidad_id, fp.f_emision, fp.f_contabilidad, fp.moneda_id, fp.tipo_cambio,
                   fp.total, c.importe_pendiente, ct.comprobante_tipo
            FROM gestion__facturas_proveedores fp
            JOIN gestion__comprobantes c ON c.comprobante_id = fp.comprobante_id
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
            WHERE fp.factura_proveedor_id = ? AND fp.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$f) {
        return ['success' => false, 'error' => "Factura de proveedor ID $factura_proveedor_id no encontrada."];
    }

    $saldo = round(floatval($f['importe_pendiente']), 2);
    if ($saldo <= 0.001) {
        return ['success' => true, 'message' => "La factura ya se encuentra cancelada."];
    }

    $sucursal_id = intval($f['sucursal_id']);

    $tesoreria_cuenta_id = intval($tesoreria_cuenta_id);
    $debito = $tesoreria_cuenta_id > 0;
    if ($debito) {
        // 1-2. Débito en cuenta: la cuenta BANCO indicada y el medio DEB_CTA (o, si la empresa no lo tiene, una transferencia)
        $stmt = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_id, tesoreria_cuenta FROM gestion__tesoreria_cuentas
                                           WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND cuenta_tipo = 'BANCO' AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "ii", $tesoreria_cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $caja = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$caja) {
            throw new Exception('La cuenta bancaria debitada no es una cuenta BANCO activa de la empresa.');
        }
        $stmt = mysqli_prepare($conexion, "SELECT medio_pago_id FROM gestion__medios_pago
                                           WHERE empresa_id = ? AND medio_pago_tipo = 'TRANSFERENCIA' AND aplica_pago = 1 AND tabla_estado_registro_id = 1
                                           ORDER BY (codigo = 'DEB_CTA') DESC, medio_pago_id LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $efectivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$efectivo) {
            throw new Exception('La empresa no tiene el medio de pago "Débito en cuenta" (DEB_CTA) ni una transferencia para pagos.');
        }
    } else {
    // 1. Cuenta de tesorería de tipo CAJA activa
    $caja = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas
                              WHERE empresa_id = $empresa_id AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1
                              ORDER BY (sucursal_id = $sucursal_id) DESC, tesoreria_cuenta_id LIMIT 1")->fetch_assoc();
    if (!$caja) {
        throw new Exception("No hay una cuenta de tesorería de tipo CAJA configurada para la empresa $empresa_id.");
    }

    // 2. Medio de pago en efectivo
    $efectivo = $conexion->query("SELECT medio_pago_id FROM gestion__medios_pago 
                                  WHERE empresa_id = $empresa_id AND medio_pago_tipo = 'EFECTIVO' AND tabla_estado_registro_id = 1 
                                  ORDER BY medio_pago_id LIMIT 1")->fetch_assoc();
    if (!$efectivo) {
        $efectivo = $conexion->query("SELECT medio_pago_id FROM gestion__medios_pago 
                                      WHERE medio_pago_tipo = 'EFECTIVO' AND tabla_estado_registro_id = 1 
                                      ORDER BY medio_pago_id LIMIT 1")->fetch_assoc();
    }
    if (!$efectivo) {
        throw new Exception("No hay un medio de pago en EFECTIVO configurado.");
    }
    }

    // 3. Crear borrador de pago
    $moneda_id = emp_moneda_id($conexion, $empresa_id, $f['moneda_id'] ?? 0);
    $tipo_cambio = floatval($f['tipo_cambio'] ?? 1.0);
    $comp_factura_id = intval($f['comprobante_id']);

    $pago_data = [
        'empresa_id'     => $empresa_id,
        'sucursal_id'    => $sucursal_id,
        'entidad_id'     => intval($f['entidad_id']),
        'moneda_id'      => $moneda_id,
        'tipo_cambio'    => $tipo_cambio,
        'f_emision'      => $f['f_emision'],
        'f_contabilidad' => $f['f_contabilidad'] ?: $f['f_emision'],
        'usuario_id'     => $usuario_id,
        'observaciones'  => ($debito ? "Débito en cuenta " . $caja['tesoreria_cuenta'] : "Pago automático en efectivo")
                            . " - " . $f['comprobante_tipo'] . " " . sprintf('%04d-%08d', $f['comprobante_pv'], $f['comprobante_nro']),
        'medios'         => [
            [
                'medio_pago_id'       => intval($efectivo['medio_pago_id']),
                'importe'             => $saldo,
                'importe_comprobante' => $saldo,
                'moneda_id'           => $moneda_id,
                'tipo_cambio'         => $tipo_cambio,
                'tesoreria_cuenta_id' => intval($caja['tesoreria_cuenta_id'])
            ]
        ],
        'imputaciones'   => [
            [
                'comprobante_destino_id' => $comp_factura_id,
                'importe_imputado'       => $saldo
            ]
        ]
    ];

    $pago_id = guardarPagoBorrador($conexion, $pago_data);
    if (!$pago_id) {
        throw new Exception("No se pudo crear el borrador de la Orden de Pago.");
    }

    // 4. Confirmar el pago
    $res_conf = confirmarPago($conexion, $pago_id, $empresa_id, $usuario_id, $en_transaccion);

    // 5. Vínculo para anularla junto con la factura (script 20261011_facturas_proveedores_anular_01)
    $r_col = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__facturas_proveedores LIKE 'pago_automatico_id'");
    if ($r_col && mysqli_num_rows($r_col) > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET pago_automatico_id = ? WHERE factura_proveedor_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $pago_id, $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    return [
        'success'        => true,
        'pago_id'        => $pago_id,
        'comprobante_id' => $res_conf['comprobante_id'] ?? null,
        'op_nro'         => $res_conf['comprobante_nro'] ?? null,
        'message'        => "Orden de Pago automática emitida " . ($debito ? "por débito en " . $caja['tesoreria_cuenta'] : "en efectivo")
                            . " (OP N° " . ($res_conf['comprobante_nro'] ?? '') . ") por $" . number_format($saldo, 2, ',', '.') . "."
    ];
}

// ===== Débito directo en cuenta bancaria (scripts 20261011_debito_en_cuenta_01/02) =====
// Un comprobante de compra (Resumen bancario, o una factura / ND debitada automáticamente) indica la cuenta
// BANCO de tesorería que el proveedor debitó; al confirmarlo se cancela con una OP automática (medio DEB_CTA).
// Sin el script 01 (columna inexistente) el circuito sigue como antes, sin débito en cuenta.
function fpDebitoCuentaHabilitado($conexion)
{
    static $ok = null;
    if ($ok === null) {
        $r = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__facturas_proveedores LIKE 'tesoreria_cuenta_debito_id'");
        $ok = $r && mysqli_num_rows($r) > 0;
    }
    return $ok;
}

// Cuentas bancarias activas de la empresa que se pueden debitar (combo de la pantalla)
function fpCuentasDebito($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $stmt = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_id, tesoreria_cuenta, moneda_id FROM gestion__tesoreria_cuentas
                                       WHERE empresa_id = ? AND cuenta_tipo = 'BANCO' AND tabla_estado_registro_id = 1
                                       ORDER BY orden, tesoreria_cuenta");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cuentas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $cuentas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $cuentas;
}

// Valida y guarda la cuenta debitada de un comprobante en Borrador (dentro de la transacción de agregar / editar).
// El tipo con requiere_debito_cuenta = 1 la exige; solo se admite en comprobantes a pagar (signo '+'), y la cuenta
// tiene que ser BANCO, activa, de la empresa y de la moneda del comprobante.
function fpGuardarCuentaDebito($conexion, $empresa_id, $factura_proveedor_id, $tipo_id, $moneda_id, $cuenta_id)
{
    if (!fpDebitoCuentaHabilitado($conexion)) return;
    $empresa_id = intval($empresa_id);
    $factura_proveedor_id = intval($factura_proveedor_id);
    $tipo_id = intval($tipo_id);
    $cuenta_id = intval($cuenta_id);

    $stmt = mysqli_prepare($conexion, "SELECT signo, requiere_debito_cuenta, comprobante_tipo FROM gestion__comprobantes_tipos
                                       WHERE comprobante_tipo_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $tipo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$tipo) {
        throw new Exception('Tipo de comprobante inválido');
    }
    if (intval($tipo['requiere_debito_cuenta']) === 1 && $cuenta_id <= 0) {
        throw new Exception('El ' . $tipo['comprobante_tipo'] . ' requiere la cuenta bancaria debitada');
    }
    if ($cuenta_id > 0) {
        if ($tipo['signo'] !== '+') {
            throw new Exception('Solo un comprobante a pagar (factura o nota de débito) se cancela por débito en cuenta');
        }
        $stmt = mysqli_prepare($conexion, "SELECT moneda_id FROM gestion__tesoreria_cuentas
                                           WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND cuenta_tipo = 'BANCO' AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "ii", $cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $cuenta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$cuenta) {
            throw new Exception('La cuenta debitada tiene que ser una cuenta bancaria activa de la empresa');
        }
        if (intval($cuenta['moneda_id']) !== intval($moneda_id)) {
            throw new Exception('La cuenta bancaria debitada es de otra moneda que el comprobante');
        }
    }
    $valor = $cuenta_id > 0 ? $cuenta_id : null;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET tesoreria_cuenta_debito_id = ? WHERE factura_proveedor_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $valor, $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ===== Anular un comprobante de proveedor confirmado (5 -> 38, script 20261011_facturas_proveedores_anular_01) =====
// Corre dentro de la transacción de ejecutarTransicionEstado(), después de pasar la factura a 38, sincronizar el
// comprobante y revertir el stock (estado 38 sin aplica_stock). Revierte la cuenta corriente y la contabilidad:
//   1) la OP automática emitida al confirmarlo (contado / débito en cuenta) se anula entera: devuelve caja o banco;
//   2) el resto de las imputaciones vigentes donde el comprobante es origen o destino se anulan: la OP / NC que lo
//      cancelaba queda con saldo a favor; si el anulado es una NC aplicada, la factura destino recupera su saldo;
//   3) asiento anulado (2) y comprobante universal en 38 sin saldo.
function fpAnularComprobanteConfirmado($conexion, $factura_proveedor_id, $comprobante_id, $empresa_id, $usuario_id, $motivo = '')
{
    require_once __DIR__ . '/tesoreria_core_model.php';
    $conn = $conexion;   // pagos_model hace "$conexion = $conn" al cargarse
    require_once __DIR__ . '/pagos_model.php';
    $factura_proveedor_id = intval($factura_proveedor_id);
    $comprobante_id = intval($comprobante_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $motivo = trim((string)$motivo) !== '' ? trim((string)$motivo) : 'Anulación del comprobante de proveedor';
    $mensajes = [];

    // Período de IVA ya liquidado (página IVA - Posición Mensual): anular la liquidación antes
    $r_iva = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__iva_posiciones'");
    if ($r_iva && mysqli_num_rows($r_iva) > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT p.periodo FROM gestion__facturas_proveedores f
                                           INNER JOIN gestion__iva_posiciones p ON p.empresa_id = f.empresa_id AND p.tabla_estado_registro_id = 5
                                                  AND p.periodo = DATE_FORMAT(COALESCE(f.f_contabilidad, f.f_emision), '%Y-%m')
                                           WHERE f.factura_proveedor_id = ? AND f.empresa_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $liq = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($liq) {
            throw new Exception('El período de IVA ' . $liq['periodo'] . ' ya está liquidado: anule la liquidación (IVA - Posición Mensual) antes de anular el comprobante.');
        }
    }

    // 1) OP automática
    $r = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__facturas_proveedores LIKE 'pago_automatico_id'");
    if ($r && mysqli_num_rows($r) > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT p.pago_id, p.comprobante_nro, p.tabla_estado_registro_id
                                           FROM gestion__facturas_proveedores f
                                           INNER JOIN gestion__pagos p ON p.pago_id = f.pago_automatico_id AND p.empresa_id = f.empresa_id
                                           WHERE f.factura_proveedor_id = ? AND f.empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $op = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($op && intval($op['tabla_estado_registro_id']) === ESTADO_PAGO_CONFIRMADO) {
            anularPago($conexion, intval($op['pago_id']), $empresa_id, $usuario_id, $motivo, true);
            $mensajes[] = 'Se anuló la OP automática N° ' . $op['comprobante_nro'] . ' (se revirtió el movimiento de tesorería).';
        }
    }

    // 2) Imputaciones vigentes restantes (el comprobante como destino o como origen)
    $stmt = mysqli_prepare($conexion, "SELECT i.imputacion_id, i.comp_origen_id, o.tabla_origen_id AS origen_tabla
                                       FROM gestion__comprobantes_imputaciones i
                                       INNER JOIN gestion__comprobantes o ON o.comprobante_id = i.comp_origen_id
                                       WHERE i.empresa_id = ? AND (i.comp_destino_id = ? OR i.comp_origen_id = ?) AND i.tabla_estado_registro_id = ?
                                       ORDER BY i.imputacion_id");
    $vigente = ESTADO_IMPUTACION_CONFIRMADO;
    mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $comprobante_id, $comprobante_id, $vigente);
    mysqli_stmt_execute($stmt);
    $imps = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $ids = array_column($imps, 'imputacion_id');
    foreach ($ids as $imp_id) {
        tesoreriaAnularImputacion($conexion, intval($imp_id), $usuario_id, $motivo);
    }
    // tesoreriaAnularImputacion recalcula solo el destino: una NC de proveedor (tabla 84) que cancelaba este
    // comprobante recupera su saldo y tiene que volver de 40 / 39 al estado que le corresponde
    foreach (array_unique(array_column($imps, 'comp_origen_id')) as $origen_id) {
        $fila_o = array_values(array_filter($imps, function ($x) use ($origen_id) { return $x['comp_origen_id'] == $origen_id; }))[0];
        if (intval($origen_id) !== $comprobante_id && intval($fila_o['origen_tabla']) === 84) {
            recalcularEstadoDestino($conexion, intval($origen_id), $usuario_id);
        }
    }
    if ($ids) {
        $mensajes[] = 'Se anularon ' . count($ids) . ' imputación(es): los pagos / notas de crédito quedan con saldo a favor.';
    }

    // 3) Asiento y comprobante universal
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                       WHERE comprobante_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0,
                                              observaciones = CONCAT(IFNULL(observaciones, ''), IF(IFNULL(observaciones, '') = '', '', ' | '), ?)
                                       WHERE comprobante_id = ? AND empresa_id = ?");
    $obs = 'ANULADO: ' . $motivo;
    mysqli_stmt_bind_param($stmt, "sii", $obs, $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $mensajes;
}

/** Post confirmación de facturas de proveedores: NC asociadas o Facturas de contado con OP automática */
function fpPostConfirmacion($conexion, $factura_proveedor_id, $empresa_id, $usuario_id)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    $mensajes = [];
    $f = $conexion->query("SELECT fp.comprobante_id, fp.comprobante_asociado_id, ct.signo
                           FROM gestion__facturas_proveedores fp
                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
                           WHERE fp.factura_proveedor_id = $factura_proveedor_id AND fp.empresa_id = $empresa_id")->fetch_assoc();

    // 1. Nota de Crédito confirmada: aplicar a factura asociada
    if ($f && $f['signo'] === '-' && intval($f['comprobante_asociado_id']) > 0) {
        $asociado = intval($f['comprobante_asociado_id']);
        $saldo = floatval($conexion->query("SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = $asociado AND tabla_estado_registro_id IN (5, 39)")->fetch_assoc()['importe_pendiente'] ?? 0);
        if ($saldo > 0.001) {
            $r = fpAplicarNotasCredito($conexion, $empresa_id, $asociado, [['nc_comprobante_id' => intval($f['comprobante_id']), 'importe' => 0]], $usuario_id, true);
            $mensajes[] = $r['message'];
        } else {
            $mensajes[] = 'El comprobante asociado no tiene saldo: la nota de crédito queda con saldo a favor (se aplica a otra factura o se cobra la devolución en Cobranzas).';
        }
    }

    // 2. Factura de compra (+): si es de contado / pago en efectivo, generar Orden de Pago automática
    if ($f && $f['signo'] === '+') {
        $sql_fp = "SELECT fp.factura_proveedor_id, fp.condicion_pago_id, c.importe_pendiente,
                          cp.tipo AS cond_tipo, cp.codigo AS cond_codigo
                   FROM gestion__facturas_proveedores fp
                   JOIN gestion__comprobantes c ON c.comprobante_id = fp.comprobante_id
                   LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = fp.condicion_pago_id
                   WHERE fp.factura_proveedor_id = $factura_proveedor_id AND fp.empresa_id = $empresa_id";
        $row_fp = $conexion->query($sql_fp)->fetch_assoc();
        $cuenta_debito = 0;
        if (fpDebitoCuentaHabilitado($conexion)) {
            $stmt = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_debito_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
            mysqli_stmt_execute($stmt);
            $cuenta_debito = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['tesoreria_cuenta_debito_id'] ?? 0);
            mysqli_stmt_close($stmt);
        }
        if ($row_fp && floatval($row_fp['importe_pendiente']) > 0.001 && $cuenta_debito > 0) {
            // Débito directo en cuenta bancaria (Resumen bancario, débitos automáticos): OP automática desde el banco
            $res_op = fpGenerarOrdenPagoAutomatica($conexion, $factura_proveedor_id, $empresa_id, $usuario_id, true, $cuenta_debito);
            if (!empty($res_op['message'])) {
                $mensajes[] = $res_op['message'];
            }
        } elseif ($row_fp && floatval($row_fp['importe_pendiente']) > 0.001) {
            $tipo_cond = strtoupper(trim($row_fp['cond_tipo'] ?? ''));
            $cod_cond  = strtoupper(trim($row_fp['cond_codigo'] ?? ''));
            if ($tipo_cond === 'CONTADO' || $cod_cond === 'CONT') {
                $res_op = fpGenerarOrdenPagoAutomatica($conexion, $factura_proveedor_id, $empresa_id, $usuario_id, true);
                if (!empty($res_op['message'])) {
                    $mensajes[] = $res_op['message'];
                }
            }
        }
    }

    return ['mensajes' => $mensajes];
}

/**
 * Qué hace la confirmación de una compra con los precios, según la vigencia abierta de
 * Parámetros de Empresa (página 111). Sin las columnas (script
 * 20261010_precios_compra_01_estructura.sql sin correr) o sin vigencia: nada (0 / 0).
 */
function fpOpcionesPreciosCompra($conexion, $empresa_id)
{
    $opciones = ['lista_proveedor' => 0, 'listas_venta' => 0];
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'compra_actualiza_listas_venta'");
    if (!$res || mysqli_num_rows($res) === 0) return $opciones;
    $stmt = mysqli_prepare($conexion, "SELECT compra_actualiza_lista_proveedor, compra_actualiza_listas_venta
                                       FROM gestion__empresas_parametros
                                       WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                       ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) {
        $opciones['lista_proveedor'] = intval($row['compra_actualiza_lista_proveedor']);
        $opciones['listas_venta'] = intval($row['compra_actualiza_listas_venta']);
    }
    return $opciones;
}

/**
 * Después del COMMIT de la confirmación de una factura de proveedor (no adentro: las
 * escrituras de precios_core_model.php abren su propia transacción y un START TRANSACTION
 * anidado commitearía la de la factura a medio hacer). El costo ya lo actualizó
 * registrarStockFacturaProveedor; esto suma, según fpOpcionesPreciosCompra:
 *  - lista_proveedor 1/2: precio de lista del proveedor = precio unitario de la línea antes
 *    de descuentos, en la moneda de la factura (1 = solo productos sin precio en la lista,
 *    2 = también pisa el existente). Crea el vínculo producto-proveedor si falta.
 *  - listas_venta 1: recalcula las listas de venta con regla aplicable usando el costo
 *    registrado (mismo motor que "Actualizar Precios de Venta" de Lista Precios Proveedores).
 * Solo comprobantes con signo '+': una NC no fija precios. Si el producto está en varias
 * líneas manda la última. Un error acá no revierte la factura (ya confirmada): vuelve como
 * mensaje y queda en el log.
 */
function fpActualizarPreciosPorCompra($conexion, $factura_proveedor_id, $empresa_id, $usuario_id)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    $mensajes = [];

    $opciones = fpOpcionesPreciosCompra($conexion, $empresa_id);
    if ($opciones['lista_proveedor'] === 0 && $opciones['listas_venta'] === 0) {
        return ['mensajes' => $mensajes];
    }

    error_log("=== INICIO fpActualizarPreciosPorCompra factura: $factura_proveedor_id lista_proveedor: {$opciones['lista_proveedor']} listas_venta: {$opciones['listas_venta']} ===");
    try {
        $stmt = mysqli_prepare($conexion, "SELECT fp.entidad_id, fp.moneda_id, fp.comprobante_id, ct.signo
                                           FROM gestion__facturas_proveedores fp
                                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
                                           WHERE fp.factura_proveedor_id = ? AND fp.empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $factura_proveedor_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$factura || $factura['signo'] !== '+') {
            return ['mensajes' => $mensajes];
        }
        $entidad_id = intval($factura['entidad_id']);
        $moneda_id = intval($factura['moneda_id']) ?: 1;
        $comprobante_id = intval($factura['comprobante_id']);

        $stmt = mysqli_prepare($conexion, "SELECT producto_id, precio_unitario
                                           FROM gestion__facturas_proveedores_detalle
                                           WHERE factura_proveedor_id = ? AND cantidad > 0 AND precio_unitario > 0
                                           ORDER BY factura_proveedor_detalle_id");
        mysqli_stmt_bind_param($stmt, 'i', $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $precios = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $precios[intval($row['producto_id'])] = round(floatval($row['precio_unitario']), 4);
        }
        mysqli_stmt_close($stmt);
        if (empty($precios)) {
            return ['mensajes' => $mensajes];
        }

        if ($opciones['lista_proveedor'] > 0) {
            $origen = preciosOrigenCargaValido($conexion, 'COMPRA');
            $cargados = 0;
            $actualizados = 0;
            foreach ($precios as $producto_id => $precio) {
                $producto_proveedor_id = obtenerOCrearProductoProveedor($conexion, $empresa_id, $entidad_id, $producto_id, $usuario_id);
                if (empty($producto_proveedor_id)) {
                    error_log("fpActualizarPreciosPorCompra: no se pudo vincular producto $producto_id con entidad $entidad_id");
                    continue;
                }
                if (insertarPrecioListaProveedor($conexion, $producto_proveedor_id, $empresa_id, $entidad_id, $precio, $moneda_id, $origen, $usuario_id, 1)) {
                    $cargados++;
                } elseif ($opciones['lista_proveedor'] === 2) {
                    $res = actualizarPrecioListaProveedor($conexion, $producto_proveedor_id, $precio, $moneda_id, $usuario_id, $origen);
                    if (!$res['success']) {
                        error_log("fpActualizarPreciosPorCompra: lista proveedor producto $producto_id: " . ($res['message'] ?? ''));
                    } elseif (!empty($res['actualizado'])) {
                        $actualizados++;
                    }
                }
            }
            if ($cargados || $actualizados) {
                $mensajes[] = "Lista del proveedor: $cargados precio(s) cargado(s)" . ($actualizados ? ", $actualizados actualizado(s)" : '') . '.';
            }
        }

        if ($opciones['listas_venta'] === 1) {
            $productoIds = array_keys($precios);
            $categorias = obtenerCategoriasPorProductos($conexion, $productoIds);
            $contexto = obtenerContextoCalculoPrecios($conexion, $empresa_id, $entidad_id);
            $precios_venta = 0;
            foreach ($productoIds as $producto_id) {
                $stmt = mysqli_prepare($conexion, "SELECT producto_costo_id, costo_actual FROM gestion__productos_costos
                                                   WHERE empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1");
                mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $producto_id);
                mysqli_stmt_execute($stmt);
                $costo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if (!$costo || floatval($costo['costo_actual']) <= 0) continue;

                // Costo previo a esta compra (lo dejó actualizarCostosProducto en el historial):
                // lo usa el ajuste proporcional de la lista Mostrador.
                $stmt = mysqli_prepare($conexion, "SELECT costo_anterior FROM gestion__productos_costos_historial
                                                   WHERE empresa_id = ? AND producto_id = ? AND comprobante_id = ?
                                                   ORDER BY producto_costo_historial_id DESC LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $producto_id, $comprobante_id);
                mysqli_stmt_execute($stmt);
                $hist = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                $costo_anterior = ($hist && $hist['costo_anterior'] !== null) ? floatval($hist['costo_anterior']) : null;

                $costo_actual = floatval($costo['costo_actual']);
                $res = actualizarListasPreciosPorRegla($conexion, $producto_id, $categorias[$producto_id] ?? 0, $entidad_id,
                    $costo_actual, intval($costo['producto_costo_id']), $empresa_id, $usuario_id, $contexto, $costo_anterior, $costo_actual);
                $precios_venta += $res['precios_actualizados'];
            }
            if ($precios_venta) {
                $mensajes[] = "Listas de venta: $precios_venta precio(s) actualizado(s).";
            }
        }
    } catch (Exception $e) {
        error_log("ERROR en fpActualizarPreciosPorCompra: " . $e->getMessage());
        $mensajes[] = 'La factura quedó confirmada, pero no se pudieron actualizar los precios: ' . $e->getMessage();
    }

    return ['mensajes' => $mensajes];
}

/** NC con saldo del proveedor que la pantalla puede ofrecer imputar a un comprobante recién confirmado. */
function fpNotasCreditoDisponiblesFactura($conexion, $factura_proveedor_id, $empresa_id)
{
    require_once __DIR__ . '/tesoreria_core_model.php';
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);
    $f = $conexion->query("SELECT fp.comprobante_id, ct.signo, c.importe_pendiente, c.tabla_estado_registro_id
                           FROM gestion__facturas_proveedores fp
                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
                           JOIN gestion__comprobantes c ON c.comprobante_id = fp.comprobante_id
                           WHERE fp.factura_proveedor_id = $factura_proveedor_id AND fp.empresa_id = $empresa_id")->fetch_assoc();
    if (!$f || $f['signo'] !== '+' || floatval($f['importe_pendiente']) <= 0.001 || !in_array(intval($f['tabla_estado_registro_id']), [5, 39], true)) {
        return null;
    }
    $ncs = tesoreriaNotasCreditoDisponibles($conexion, $empresa_id, intval($f['comprobante_id']));
    if (empty($ncs)) return null;
    return ['comprobante_id' => intval($f['comprobante_id']), 'saldo' => floatval($f['importe_pendiente']), 'notas' => $ncs];
}

/** Asiento contable de una factura de proveedor (cabecera + renglones con Debe/Haber), o null si no tiene. */
function fpObtenerAsientoFactura($conexion, $factura_proveedor_id, $empresa_id)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $empresa_id = intval($empresa_id);

    $stmt = mysqli_prepare($conexion, "SELECT a.cont_asiento_id, a.comprobante_id, a.f_asiento, a.descripcion,
                                              a.tabla_estado_registro_id, ta.cont_tipo_asiento, er.estado_registro
                                       FROM gestion__cont_asientos a
                                       INNER JOIN gestion__facturas_proveedores f ON f.comprobante_id = a.comprobante_id
                                       LEFT JOIN gestion__cont_tipos_asientos ta ON ta.cont_tipo_asiento_id = a.cont_tipo_asiento_id
                                       LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
                                       WHERE f.factura_proveedor_id = ? AND f.empresa_id = ? AND f.comprobante_id > 0
                                       ORDER BY (a.tabla_estado_registro_id = 2), a.cont_asiento_id DESC
                                       LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $asiento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$asiento) return null;

    $stmt = mysqli_prepare($conexion, "SELECT d.cuenta_id, d.importe_local, d.descripcion,
                                              c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre,
                                              IF(d.importe_local > 0, d.importe_local, 0) AS debe,
                                              IF(d.importe_local < 0, ABS(d.importe_local), 0) AS haber
                                       FROM gestion__cont_asientos_detalles d
                                       LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id
                                       WHERE d.cont_asiento_id = ?
                                       ORDER BY d.importe_local DESC, d.cuenta_id ASC");
    $asiento_id = intval($asiento['cont_asiento_id']);
    mysqli_stmt_bind_param($stmt, "i", $asiento_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $detalles = [];
    $total_debe = 0.0;
    $total_haber = 0.0;
    while ($r = mysqli_fetch_assoc($res)) {
        $total_debe += floatval($r['debe']);
        $total_haber += floatval($r['haber']);
        $detalles[] = $r;
    }
    mysqli_stmt_close($stmt);

    $asiento['detalles'] = $detalles;
    $asiento['total_debe'] = round($total_debe, 2);
    $asiento['total_haber'] = round($total_haber, 2);
    return $asiento;
}
?>
