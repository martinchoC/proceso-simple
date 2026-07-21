<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
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

function ejecutarTransicionEstado($conexion, $factura_proveedor_id, $accion_js, $empresa_idx, $pagina_id)
{
    $factura_proveedor_id = intval($factura_proveedor_id);
    $pagina_id = intval($pagina_id);
    
    error_log("=== ejecutarTransicionEstado INICIO ===");
    error_log("factura_proveedor_id: $factura_proveedor_id, accion_js: $accion_js");

    // Obtener estado actual de la factura
    $sql_check = "SELECT factura_proveedor_id, tabla_estado_registro_id 
                  FROM gestion__facturas_proveedores 
                  WHERE factura_proveedor_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
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
        if ($estado_info && $estado_info['codigo_estandar'] === 'CONFIRMADO') {            
            // Registrar stock (solo una vez)
            $stock_result = registrarStockPorConfirmacion($conexion, $factura_proveedor_id, $comprobante_id, $comprobante_tipo_id);
            if (!$stock_result['success']) {
                throw new Exception('Error al registrar stock: ' . $stock_result['message']);
            }
            
            // Generar asiento contable automático
            $asiento_result = generarAsientoContableFactura($conexion, $factura_proveedor_id, $empresa_idx);
            if (!$asiento_result['success']) {
                error_log("ERROR al generar asiento contable: " . $asiento_result['message']);
            } else {
                error_log("Asiento contable generado ID: " . $asiento_result['asiento_id']);
            }
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado y stock registrado correctamente'];
        
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
               d.deposito_nombre
        FROM gestion__facturas_proveedores fp
        LEFT JOIN conf__estados_registros er ON fp.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN conf__colores c ON er.color_id = c.color_id
        LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
        LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
        LEFT JOIN gestion__monedas m ON fp.moneda_id = m.moneda_id
        LEFT JOIN gestion__sucursales s ON fp.sucursal_id = s.sucursal_id AND fp.empresa_id = s.empresa_id
        LEFT JOIN gestion__depositos d ON fp.deposito_id = d.deposito_id AND fp.empresa_id = d.empresa_id
        WHERE fp.empresa_id = ?
        ORDER BY fp.f_emision DESC, fp.factura_proveedor_id DESC";

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
        // Verificar duplicados
        $sql_check = "SELECT COUNT(*) as total FROM gestion__facturas_proveedores 
                      WHERE comprobante_pv = ? AND comprobante_nro = ? AND comprobante_tipo_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        }

        $comprobante_pv_check = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_check = intval($data['comprobante_nro'] ?? 0);
        $comprobante_tipo_id_check = intval($data['comprobante_tipo_id'] ?? 0);
        $empresa_idx_check = intval($data['empresa_idx'] ?? 0);

        mysqli_stmt_bind_param($stmt, "iiii", 
            $comprobante_pv_check,
            $comprobante_nro_check,
            $comprobante_tipo_id_check,
            $empresa_idx_check
        );
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe una factura con este número de comprobante');
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


        // Insertar detalles
        $detalles_success = insertarDetallesFactura($conexion, $factura_proveedor_id, $data['empresa_idx'], $data['detalles']);
        
        if (!$detalles_success) {
            throw new Exception("Error al insertar los detalles");
        }
        // Guardar impuestos adicionales
        if (isset($data['impuestos_adicionales']) && !empty($data['impuestos_adicionales'])) {
            $impuestos = json_decode($data['impuestos_adicionales'], true);
            if (is_array($impuestos) && count($impuestos) > 0) {
                $resultado_impuestos = guardarImpuestosFactura($conexion, $factura_proveedor_id, $impuestos, $empresa_idx);
                if (!$resultado_impuestos['success']) {
                    throw new Exception('Error al guardar impuestos: ' . ($resultado_impuestos['error'] ?? 'Error desconocido'));
                }
                error_log("Impuestos guardados: " . print_r($resultado_impuestos, true));
            }
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
            $detalles_success = insertarDetallesFactura($conexion, $id, $empresa_idx_val, $data['detalles']);
            
            if (!$detalles_success) {
                throw new Exception("Error al insertar los nuevos detalles");
            }
        } else {
            throw new Exception("Debe haber al menos un detalle en la factura");
        }
        // Guardar impuestos adicionales
        if (isset($data['impuestos_adicionales']) && !empty($data['impuestos_adicionales'])) {
            $impuestos = json_decode($data['impuestos_adicionales'], true);
            if (is_array($impuestos) && count($impuestos) > 0) {
                $resultado_impuestos = guardarImpuestosFactura($conexion, $id, $impuestos, $empresa_idx);
                if (!$resultado_impuestos['success']) {
                    throw new Exception('Error al guardar impuestos: ' . ($resultado_impuestos['error'] ?? 'Error desconocido'));
                }
                error_log("Impuestos actualizados: " . print_r($resultado_impuestos, true));
            }
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
                m.moneda, m.simbolo
            FROM gestion__facturas_proveedores fp
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
                     LEFT JOIN gestion__productos_proveedores pp ON d.producto_id = pp.producto_id AND pp.entidad_id = ?
                     WHERE d.factura_proveedor_id = ?
                     ORDER BY d.factura_proveedor_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt) {
        error_log("Error preparando consulta detalles: " . mysqli_error($conexion));
        return $factura;
    }

    $entidad_id = intval($factura['entidad_id'] ?? 0);
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $id);
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
            'codigo_proveedor' => $detalle['codigo_proveedor'] ?? ''
        ];
    }
    mysqli_stmt_close($stmt);

    $factura['detalles'] = $detalles;
    return $factura;
}

function obtenerComprobantesTipos($conexion)
{
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra 
            FROM gestion__comprobantes_tipos 
            WHERE comprobante_subgrupo_id = 5
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

function agregarProductoRapido($conexion, $data)
{
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
    
    // Verificar si ya existe el código de proveedor para este proveedor
    $sql_check_proveedor = "SELECT COUNT(*) as total FROM gestion__productos_proveedores pp
                            INNER JOIN gestion__productos p ON pp.producto_id = p.producto_id
                            WHERE pp.entidad_id = ? AND pp.codigo_proveedor = ? AND p.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_proveedor);
    mysqli_stmt_bind_param($stmt, "isi", $data['entidad_id'], $data['codigo_proveedor'], $data['empresa_idx']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row_proveedor = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row_proveedor && $row_proveedor['total'] > 0) {
        return ['success' => false, 'error' => 'Ya existe un producto con este código de proveedor para el proveedor seleccionado'];
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
        
        // Insertar la relación con el proveedor
        $sql_proveedor = "INSERT INTO gestion__productos_proveedores 
                          (empresa_id, producto_id, entidad_id, codigo_proveedor, tabla_estado_registro_id) 
                          VALUES (?, ?, ?, ?, 1)";
        
        $stmt_proveedor = mysqli_prepare($conexion, $sql_proveedor);
        if ($stmt_proveedor) {
            mysqli_stmt_bind_param($stmt_proveedor, "iiis", 
                $data['empresa_idx'],
                $producto_id,
                $data['entidad_id'],
                $data['codigo_proveedor']
            );
            $success_proveedor = mysqli_stmt_execute($stmt_proveedor);
            mysqli_stmt_close($stmt_proveedor);
            
            if (!$success_proveedor) {
                // Si falla la inserción del proveedor, eliminar el producto creado
                $sql_delete = "DELETE FROM gestion__productos WHERE producto_id = ?";
                $stmt_delete = mysqli_prepare($conexion, $sql_delete);
                mysqli_stmt_bind_param($stmt_delete, "i", $producto_id);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
                
                return ['success' => false, 'error' => 'Error al asociar el producto con el proveedor'];
            }
        }
        
        return ['success' => true, 'producto_id' => $producto_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Error al crear el producto'];
    }
}

function buscarProductosPorProveedor($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);
    $q = mysqli_real_escape_string($conexion, $q);
    
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
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR pp.codigo_proveedor LIKE ?)
            ORDER BY p.producto_nombre
            LIMIT 20";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos: " . mysqli_error($conexion));
        return [];
    }
    
    $search = "%$q%";
    mysqli_stmt_bind_param($stmt, "iisss", $empresa_idx, $entidad_id, $search, $search, $search);
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

function insertarDetallesFactura($conexion, $factura_proveedor_id, $empresa_id, $detalles)
{
    error_log("Insertando " . count($detalles) . " detalles para factura $factura_proveedor_id");
    
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
        $precio_unitario_neto = ($neto_gravado + $no_gravado + $exento) / $cantidad;
        
        $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : 0;
        $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 0);
        $iva_importe = $neto_gravado * ($iva_porcentaje / 100);
        $total_linea = $neto_gravado + $iva_importe + $no_gravado + $exento;
        
        // Insertar detalle con todos los campos (incluyendo precio_unitario_neto)
        $sql = "INSERT INTO gestion__facturas_proveedores_detalle 
            (factura_proveedor_id, empresa_id, producto_id, cantidad, cantidad_recibida, 
            precio_unitario, precio_unitario_neto, descuento_item_pct, descuento_general_pct, 
            precio_unitario_bruto, descuento, descuento_general, descuento_item,
            neto_gravado, no_gravado, exento, 
            iva_alicuota_id, iva_porcentaje, iva_importe, total_linea) 
            VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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

        mysqli_stmt_bind_param($stmt, "iiidddddddddddddddd", 
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
            $total_linea            // 19 - d (posición 20)
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
// Agregar al final del archivo, antes del último 

function registrarStockPorConfirmacion($conexion, $factura_proveedor_id, $comprobante_id, $comprobante_tipo_id) {
    error_log("=== registrarStockPorConfirmacion INICIO ===");
    error_log("factura_id: $factura_proveedor_id, comprobante_id: $comprobante_id, comprobante_tipo_id: $comprobante_tipo_id");
    
    // 1. Obtener datos de la factura
    $sql_factura = "SELECT fp.empresa_id, fp.sucursal_id, fp.deposito_id,
                           fp.comprobante_pv, fp.comprobante_nro
                    FROM gestion__facturas_proveedores fp
                    WHERE fp.factura_proveedor_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_factura);
    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $factura = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$factura) {
        error_log("Factura no encontrada");
        return ['success' => false, 'message' => 'Factura no encontrada'];
    }
    
    if (empty($factura['deposito_id'])) {
        error_log("La factura no tiene depósito asignado");
        return ['success' => false, 'message' => 'La factura no tiene un depósito asignado'];
    }
    
    $empresa_id = intval($factura['empresa_id']);
    $sucursal_id = intval($factura['sucursal_id']);
    $deposito_id = intval($factura['deposito_id']);
    
    // 2. Obtener detalles de la factura (incluyendo precio_unitario_neto)
    $sql_detalles = "SELECT d.producto_id, d.cantidad, d.precio_unitario_neto
                     FROM gestion__facturas_proveedores_detalle d
                     WHERE d.factura_proveedor_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_detalles);
    mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $detalles[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    if (empty($detalles)) {
        error_log("La factura no tiene productos");
        return ['success' => false, 'message' => 'La factura no tiene productos'];
    }
    
    // INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conexion);
    
    try {
        $fecha = date('Y-m-d H:i:s');
        $descripcion = "Ingreso por compra - Factura N° " . ($factura['comprobante_pv'] ?? '') . '-' . ($factura['comprobante_nro'] ?? '');
        
        // ============================================================
        // 3. INSERTAR CABECERA DEL MOVIMIENTO DE STOCK
        // ============================================================
        $sql_movimiento = "INSERT INTO gestion__stock_movimientos 
                           (empresa_id, sucursal_id, deposito_id, comprobante_tipo_id, comprobante_id, 
                            stock_movimiento_tipo_id, fecha, descripcion, tabla_estado_registro_id) 
                           VALUES (?, ?, ?, ?, ?, 1, ?, ?, 1)";
        
        $stmt = mysqli_prepare($conexion, $sql_movimiento);
        mysqli_stmt_bind_param($stmt, "iiiiiss", 
            $empresa_id, 
            $sucursal_id, 
            $deposito_id, 
            $comprobante_tipo_id, 
            $comprobante_id, 
            $fecha, 
            $descripcion
        );
        mysqli_stmt_execute($stmt);
        $stock_movimiento_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        error_log("Movimiento de stock creado ID: $stock_movimiento_id");
        
        // ============================================================
        // 4. PROCESAR CADA DETALLE
        // ============================================================
        foreach ($detalles as $detalle) {
            $producto_id = intval($detalle['producto_id']);
            $cantidad = floatval($detalle['cantidad']);
            
            // USAR precio_unitario_neto como costo_unitario
            $costo_unitario = floatval($detalle['precio_unitario_neto'] ?? 0);
            $costo_total = $cantidad * $costo_unitario;
            
            if ($cantidad <= 0) {
                error_log("Cantidad inválida para producto $producto_id, saltando...");
                continue;
            }
            
            error_log("Producto $producto_id: cantidad=$cantidad, costo_unitario (precio_unitario_neto)=$costo_unitario, costo_total=$costo_total");
            
            // 4a. Insertar DETALLE del movimiento de stock
            $sql_detalle_mov = "INSERT INTO gestion__stock_movimientos_detalles 
                                (stock_movimiento_id, producto_id, cantidad, costo_unitario, costo_total, deposito_id, tabla_estado_registro_id) 
                                VALUES (?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = mysqli_prepare($conexion, $sql_detalle_mov);
            mysqli_stmt_bind_param($stmt, "iidddi", 
                $stock_movimiento_id, 
                $producto_id, 
                $cantidad, 
                $costo_unitario, 
                $costo_total, 
                $deposito_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            error_log("Detalle movimiento insertado para producto $producto_id");
            
            // 4b. ACTUALIZAR TABLA DE STOCK (gestion__stock)
            // Primero obtener el stock actual si existe
            $sql_stock_actual = "SELECT cantidad, costo_promedio 
                                 FROM gestion__stock 
                                 WHERE empresa_id = ? AND sucursal_id = ? AND deposito_id = ? AND producto_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_stock_actual);
            mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $sucursal_id, $deposito_id, $producto_id);
            mysqli_stmt_execute($stmt);
            $result_stock = mysqli_stmt_get_result($stmt);
            $stock_actual = mysqli_fetch_assoc($result_stock);
            mysqli_stmt_close($stmt);
            
            if ($stock_actual) {
                // Existe stock previo - actualizar
                $cantidad_anterior = floatval($stock_actual['cantidad']);
                $costo_promedio_anterior = floatval($stock_actual['costo_promedio']);
                
                $nueva_cantidad = $cantidad_anterior + $cantidad;
                // Nuevo costo promedio = (stock_anterior * costo_promedio_anterior + costo_total_entrada) / nueva_cantidad
                $nuevo_costo_promedio = ($cantidad_anterior * $costo_promedio_anterior + $costo_total) / $nueva_cantidad;
                
                $sql_update_stock = "UPDATE gestion__stock 
                                     SET cantidad = ?,
                                         costo_promedio = ?,
                                         costo_ultima_compra = ?
                                     WHERE empresa_id = ? AND sucursal_id = ? AND deposito_id = ? AND producto_id = ?";
                $stmt = mysqli_prepare($conexion, $sql_update_stock);
                mysqli_stmt_bind_param($stmt, "dddiiii", 
                    $nueva_cantidad, 
                    $nuevo_costo_promedio, 
                    $costo_unitario,
                    $empresa_id, 
                    $sucursal_id, 
                    $deposito_id, 
                    $producto_id
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                error_log("Stock actualizado para producto $producto_id: nueva_cantidad=$nueva_cantidad, nuevo_promedio=$nuevo_costo_promedio");
            } else {
                // No existe stock previo - insertar nuevo registro
                $sql_insert_stock = "INSERT INTO gestion__stock 
                                    (empresa_id, sucursal_id, deposito_id, producto_id, cantidad, costo_promedio, costo_ultima_compra) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conexion, $sql_insert_stock);
                mysqli_stmt_bind_param($stmt, "iiiiddd", 
                    $empresa_id, 
                    $sucursal_id, 
                    $deposito_id, 
                    $producto_id, 
                    $cantidad, 
                    $costo_unitario, 
                    $costo_unitario
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                error_log("Nuevo registro de stock para producto $producto_id: cantidad=$cantidad, costo=$costo_unitario");
            }
            // USAR precio_unitario_neto como costo_unitario
            $costo_unitario = floatval($detalle['precio_unitario_neto'] ?? 0);
            $costo_total = $cantidad * $costo_unitario;
            
            if ($cantidad <= 0) {
                error_log("Cantidad inválida para producto $producto_id, saltando...");
                continue;
            }
            
            error_log("Producto $producto_id: cantidad=$cantidad, costo_unitario (precio_unitario_neto)=$costo_unitario, costo_total=$costo_total");
            
            // 4a. Insertar DETALLE del movimiento de stock
            $sql_detalle_mov = "INSERT INTO gestion__stock_movimientos_detalles 
                                (stock_movimiento_id, producto_id, cantidad, costo_unitario, costo_total, deposito_id, tabla_estado_registro_id) 
                                VALUES (?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = mysqli_prepare($conexion, $sql_detalle_mov);
            mysqli_stmt_bind_param($stmt, "iidddi", 
                $stock_movimiento_id, 
                $producto_id, 
                $cantidad, 
                $costo_unitario, 
                $costo_total, 
                $deposito_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            error_log("Detalle movimiento insertado para producto $producto_id");
            
            // 4c. ACTUALIZAR COSTO DEL PRODUCTO
            $usuario_id_actual = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;
            
            $costo_result = actualizarCostosProducto(
                $conexion, 
                $producto_id,    // producto_id
                $empresa_id,     // empresa_id
                $costo_unitario, // nuevo_costo_unitario
                $comprobante_id, // comprobante_id
                $usuario_id_actual  // usuario_id (entero, no string)
            );
            
            if (!$costo_result['success']) {
                throw new Exception('Error al actualizar costo del producto: ' . $costo_result['message']);
            }
            
        }
        
        mysqli_commit($conexion);
        error_log("=== registrarStockPorConfirmacion EXITO ===");
        return ['success' => true, 'message' => 'Stock registrado correctamente', 'movimiento_id' => $stock_movimiento_id];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en registrarStockPorConfirmacion: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function obtenerDepositosPorSucursal($conexion, $sucursal_id, $empresa_idx) {
    $sql = "SELECT deposito_id, deposito_nombre, codigo, es_principal, permite_ingresos
            FROM gestion__depositos 
            WHERE empresa_id = ? 
            AND sucursal_id = ?
            AND tabla_estado_registro_id = 1
            AND permite_ingresos = 1
            ORDER BY es_principal DESC, orden, deposito_nombre";
    
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
    $moneda_id = intval($data['moneda_id'] ?? 1);
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
        // UPDATE
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
                            tabla_estado_registro_id = ?,
                            observaciones = ?,
                            usuario_modificacion_id = ?
                        WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            error_log("Error preparando UPDATE: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssidddddddddiisi",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
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
        error_log("Comprobante actualizado ID: $comprobante_id");
    } else {
        // INSERT
        $sql_insert = "INSERT INTO gestion__comprobantes (
                            empresa_id, sucursal_id, comprobante_pv,
                            comprobante_tipo_id, comprobante_nro,
                            entidad_id, entidad_sucursal_id,
                            f_emision, f_contabilidad, f_vto,
                            moneda_id, tipo_cambio,
                            importe_bruto, descuento_general,
                            importe_no_gravado, importe_exento,
                            importe_neto, importe_iva, importe_otros_impuestos,
                            importe_total,
                            tabla_origen_id, registro_origen_id,
                            tabla_estado_registro_id,
                            usuario_id,
                            observaciones
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) {
            error_log("Error preparando INSERT: " . mysqli_error($conexion));
            return null;
        }
        mysqli_stmt_bind_param($stmt, "iiiiiiisssidddddddddiiiis",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad, $f_vto,
            $moneda_id, $tipo_cambio,
            $importe_bruto, $descuento_general,
            $importe_no_gravado, $importe_exento,
            $importe_neto, $importe_iva, $importe_otros_impuestos,
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
        error_log("Comprobante creado ID: $comprobante_id");
    }
    
    return $comprobante_id;
}

function obtenerImpuestosConfig($conexion, $empresa_idx, $comprobante_subgrupo_id = 5)
{
    $empresa_idx = intval($empresa_idx);
    $comprobante_subgrupo_id = intval($comprobante_subgrupo_id);
    
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

function actualizarCostosProducto($conexion, $producto_id, $empresa_id, $nuevo_costo_unitario, $comprobante_id, $usuario_id = null) {
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
    $moneda_id = 1;
    $creado_por = $usuario_id ?? 0;
    $estado_registro_id = 1;
    
    // INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conexion);
    
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
        
        mysqli_commit($conexion);
        error_log("=== actualizarCostosProducto EXITO ===");
        return ['success' => true, 'message' => 'Costo actualizado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
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

function generarAsientoContableFactura($conexion, $factura_proveedor_id, $empresa_id)
{
    error_log("=== generarAsientoContableFactura INICIO ===");
    error_log("Factura ID: $factura_proveedor_id, Empresa ID: $empresa_id");
    
    try {
        // Obtener datos de la factura (incluyendo tipo de comprobante y proveedor)
        $sql_factura = "SELECT fp.*, fp.total, fp.impuestos, fp.subtotal,
                               fp.comprobante_pv, fp.comprobante_nro,
                               ct.comprobante_tipo,
                               e.entidad_nombre
                        FROM gestion__facturas_proveedores fp
                        LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
                        LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
                        WHERE fp.factura_proveedor_id = ? AND fp.empresa_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql_factura);
        mysqli_stmt_bind_param($stmt, "ii", $factura_proveedor_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $factura = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$factura) {
            return ['success' => false, 'message' => 'Factura no encontrada'];
        }
        
        // Construir descripción legible
        $comprobante_tipo = $factura['comprobante_tipo'] ?? 'Factura';
        $pv = str_pad($factura['comprobante_pv'] ?? 0, 4, '0', STR_PAD_LEFT);
        $nro = str_pad($factura['comprobante_nro'] ?? 0, 8, '0', STR_PAD_LEFT);
        $proveedor = $factura['entidad_nombre'] ?? 'Proveedor';
        $fecha_emision = date('d/m/Y', strtotime($factura['f_emision']));
        $descripcion = "$comprobante_tipo $pv-$nro - $proveedor - $fecha_emision";
        
        if (empty($descripcion)) {
            $descripcion = "Factura Proveedor N° $pv-$nro";
        }
        error_log("Descripción generada: '$descripcion' (longitud: " . strlen($descripcion) . ")");
        
        // Obtener cuenta del proveedor
        $sql_proveedor = "SELECT cont_cuenta_id_proveedor FROM gestion__entidades WHERE entidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_proveedor);
        mysqli_stmt_bind_param($stmt, "i", $factura['entidad_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $proveedor_cuenta = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $cuenta_proveedor_id = $proveedor_cuenta['cont_cuenta_id_proveedor'] ?? null;
        if (!$cuenta_proveedor_id) {
            return ['success' => false, 'message' => 'El proveedor no tiene cuenta contable configurada (cont_cuenta_id_proveedor)'];
        }
        
        // Obtener cuenta del IVA
        $sql_iva = "SELECT cont_cuenta_id FROM gestion__impuestos__iva_alicuotas 
                    WHERE empresa_id = ? AND iva_alicuota_id = 1 LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql_iva);
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $iva_row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        $cuenta_iva_id = $iva_row['cont_cuenta_id'] ?? null;
        
        // Obtener detalles de productos
        $sql_detalles = "SELECT d.*, p.cont_cuenta_id 
                         FROM gestion__facturas_proveedores_detalle d
                         LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                         WHERE d.factura_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_detalles);
        mysqli_stmt_bind_param($stmt, "i", $factura_proveedor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $detalles = [];
        $productos_sin_cuenta = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $detalles[] = $row;
            if (empty($row['cont_cuenta_id'])) {
                $productos_sin_cuenta[] = "Producto ID: " . $row['producto_id'];
            }
        }
        mysqli_stmt_close($stmt);
        
        if (empty($detalles)) {
            return ['success' => false, 'message' => 'La factura no tiene productos'];
        }
        if (!empty($productos_sin_cuenta)) {
            return ['success' => false, 'message' => 'Productos sin cuenta contable: ' . implode(', ', $productos_sin_cuenta)];
        }
        
        // Preparar movimientos del asiento
        $detalles_asiento = [];
        $total_debe = 0;
        $total_haber = 0;
        
        foreach ($detalles as $detalle) {
            $importe = floatval($detalle['neto_gravado'] ?? 0);
            if ($importe > 0 && $detalle['cont_cuenta_id']) {
                $detalles_asiento[] = [
                    'cuenta_id' => $detalle['cont_cuenta_id'],
                    'importe' => $importe,
                    'descripcion' => 'Compra - Producto ID: ' . $detalle['producto_id']
                ];
                $total_debe += $importe;
            }
        }
        
        $iva_importe = floatval($factura['impuestos'] ?? 0);
        if ($iva_importe > 0 && $cuenta_iva_id) {
            $detalles_asiento[] = [
                'cuenta_id' => $cuenta_iva_id,
                'importe' => $iva_importe,
                'descripcion' => 'IVA Crédito Fiscal'
            ];
            $total_debe += $iva_importe;
        }
        
        $total_factura = floatval($factura['total']);
        $detalles_asiento[] = [
            'cuenta_id' => $cuenta_proveedor_id,
            'importe' => -$total_factura,
            'descripcion' => 'Proveedor: ' . ($factura['entidad_nombre'] ?? '')
        ];
        $total_haber += $total_factura;
        
        if (abs($total_debe - $total_haber) > 0.01) {
            return ['success' => false, 'message' => "Asiento no balanceado: Debe=$total_debe, Haber=$total_haber"];
        }
        
        $comprobante_id = $factura['comprobante_id'];
        if (empty($comprobante_id)) {
            return ['success' => false, 'message' => 'La factura no tiene comprobante asociado'];
        }
        
        // Verificar si ya existe asiento
        $sql_check = "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $existe_asiento = false;
        $asiento_id = null;
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $asiento_id);
            mysqli_stmt_fetch($stmt);
            $existe_asiento = true;
        }
        mysqli_stmt_close($stmt);
        
        mysqli_begin_transaction($conexion);
        
        try {
            $timestamp = strtotime($factura['f_emision']);
            $anio = date('Y', $timestamp);
            $mes = date('m', $timestamp);
            $estado_asiento = 3;
            
            if ($existe_asiento) {
                // UPDATE
                $sql_update = "UPDATE gestion__cont_asientos 
                               SET sucursal_id = ?, deposito_id = ?, entidad_id = ?,
                                   f_asiento = ?, anio = ?, mes = ?, descripcion = ?,
                                   moneda_id = ?, tipo_cambio = ?, tabla_estado_registro_id = ?
                               WHERE cont_asiento_id = ?";
                
                $params = [
                    $factura['sucursal_id'],
                    $factura['deposito_id'],
                    $factura['entidad_id'],
                    $factura['f_emision'],
                    $anio,
                    $mes,
                    $descripcion,
                    $factura['moneda_id'],
                    $factura['tipo_cambio'],
                    $estado_asiento,
                    $asiento_id
                ];
                
                $types = "iiisiisidii";
                $sql_executable = buildExecutableSQL($sql_update, $types, $params);
                error_log("=== SQL ACTUALIZACIÓN ASIENTO (ejecutar en HeidiSQL) ===");
                error_log($sql_executable);
                
                $stmt = mysqli_prepare($conexion, $sql_update);
                mysqli_stmt_bind_param($stmt, $types, 
                    $params[0], $params[1], $params[2], $params[3], $params[4], 
                    $params[5], $params[6], $params[7], $params[8], $params[9], $params[10]);
            } else {
                // INSERT
                $sql_insert = "INSERT INTO gestion__cont_asientos 
                               (empresa_id, sucursal_id, deposito_id, comprobante_id, entidad_id, 
                                cont_tipo_asiento_id, f_asiento, anio, mes, descripcion, 
                                moneda_id, tipo_cambio, usuario_creacion_id, tabla_estado_registro_id) 
                               VALUES (?, ?, ?, ?, ?, 2, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $usuario_id = $_SESSION['usuario_id'] ?? 0;
                $params = [
                    $empresa_id,
                    $factura['sucursal_id'],
                    $factura['deposito_id'],
                    $comprobante_id,
                    $factura['entidad_id'],
                    $factura['f_emision'],
                    $anio,
                    $mes,
                    $descripcion,
                    $factura['moneda_id'],
                    $factura['tipo_cambio'],
                    $usuario_id,
                    $estado_asiento
                ];
                
                $types = "iiiiisiisidii";
                $sql_executable = buildExecutableSQL($sql_insert, $types, $params);
                error_log("=== SQL INSERCIÓN NUEVO ASIENTO (ejecutar en HeidiSQL) ===");
                error_log($sql_executable);
                
                $stmt = mysqli_prepare($conexion, $sql_insert);
                mysqli_stmt_bind_param($stmt, $types, 
                    $params[0], $params[1], $params[2], $params[3], $params[4],
                    $params[5], $params[6], $params[7], $params[8], $params[9],
                    $params[10], $params[11], $params[12]);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                $error_msg = mysqli_stmt_error($stmt);
                error_log("ERROR ejecutando consulta: $error_msg");
                throw new Exception("Error guardando asiento: " . $error_msg);
            }
            
            $affected = mysqli_stmt_affected_rows($stmt);
            error_log("Filas afectadas por la consulta: $affected");
            mysqli_stmt_close($stmt);
            
            if (!$existe_asiento) {
                $asiento_id = mysqli_insert_id($conexion);
            }
            error_log("Asiento guardado ID: $asiento_id - Descripción: '$descripcion' - Estado: $estado_asiento");
            
            // Eliminar detalles antiguos si existe
            if ($existe_asiento) {
                $sql_del = "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?";
                $stmt = mysqli_prepare($conexion, $sql_del);
                mysqli_stmt_bind_param($stmt, "i", $asiento_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                error_log("Detalles antiguos eliminados del asiento ID: $asiento_id");
            }
            
            // Insertar nuevos detalles
            $detalles_insertados = 0;
            foreach ($detalles_asiento as $det) {
                $sql_det = "INSERT INTO gestion__cont_asientos_detalles 
                            (cont_asiento_id, cuenta_id, importe_local, moneda_id, tipo_cambio, descripcion, tipo, estado, comprobante_id) 
                            VALUES (?, ?, ?, ?, ?, ?, 'A', 'activo', ?)";
                $stmt = mysqli_prepare($conexion, $sql_det);
                mysqli_stmt_bind_param($stmt, "iididsi", 
                    $asiento_id, 
                    $det['cuenta_id'], 
                    $det['importe'],
                    $factura['moneda_id'], 
                    $factura['tipo_cambio'], 
                    $det['descripcion'], 
                    $comprobante_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error insertando detalle: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
                $detalles_insertados++;
            }
            error_log("Detalles insertados: $detalles_insertados");
            mysqli_commit($conexion);
            
            return [
                'success' => true,
                'message' => $existe_asiento ? 'Asiento actualizado correctamente' : 'Asiento creado correctamente',
                'asiento_id' => $asiento_id
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            error_log("ERROR en transacción: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
        
    } catch (Exception $e) {
        error_log("ERROR general: " . $e->getMessage());
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
?>