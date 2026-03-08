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
        // Incluir botones donde el origen coincide con el estado actual
        // O donde el origen es 0 (botón agregar, pero eso se maneja aparte)
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            
            // Determinar si es confirmable (cambia de estado)
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

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    $sql_update = "UPDATE gestion__facturas_proveedores 
                   SET tabla_estado_registro_id = ? 
                   WHERE factura_proveedor_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $factura_proveedor_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
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
                   m.moneda, m.simbolo
            FROM gestion__facturas_proveedores fp
            LEFT JOIN conf__estados_registros er ON fp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON fp.moneda_id = m.moneda_id
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
        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) {
            $estado_inicial = 1;
        }

        // Manejar valores NULL
        $f_vencimiento = (!empty($data['f_vencimiento'])) ? $data['f_vencimiento'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : 1.000000;
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        
        $direccion = isset($data['direccion']) ? trim($data['direccion']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        // Insertar factura
        $sql = "INSERT INTO gestion__facturas_proveedores 
                (empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro, 
                 entidad_id, entidad_sucursal_id, f_emision, f_vencimiento, condicion_pago_id, 
                 moneda_id, tipo_cambio, direccion_entrega, subtotal, descuentos, no_gravado, exento, impuestos, total, 
                 observaciones, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        // Asignar variables
        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = $sucursal_id;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_val = intval($data['comprobante_nro'] ?? 0);
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_vencimiento_val = $f_vencimiento;
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
        $observaciones_val = $observaciones;
        $estado_inicial_val = intval($estado_inicial);

        // Cadena de tipos: i,i,i,i,i,i,i,s,s,i,i,d,s,d,d,d,d,d,d,s,i (21 caracteres)
        mysqli_stmt_bind_param($stmt, "iiiiiiissiidsddddddddsi",
            $empresa_id_val,
            $sucursal_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_vencimiento_val,
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
            $observaciones_val,
            $estado_inicial_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $factura_proveedor_id = mysqli_insert_id($conexion);
        error_log("Factura creada con ID: " . $factura_proveedor_id);
        mysqli_stmt_close($stmt);

        // Insertar detalles
        $detalles_success = insertarDetallesFactura($conexion, $factura_proveedor_id, $data['empresa_idx'], $data['detalles']);
        
        if (!$detalles_success) {
            throw new Exception("Error al insertar los detalles");
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
        
        // Asegurar valores para campos que ahora son INT
        $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro = intval($data['comprobante_nro'] ?? 0);

        // Actualizar la factura
        $sql = "UPDATE gestion__facturas_proveedores 
                SET sucursal_id = ?,
                    comprobante_tipo_id = ?, 
                    comprobante_pv = ?, 
                    comprobante_nro = ?, 
                    entidad_id = ?, 
                    entidad_sucursal_id = ?, 
                    f_emision = ?, 
                    f_vencimiento = ?, 
                    condicion_pago_id = ?, 
                    moneda_id = ?, 
                    tipo_cambio = ?, 
                    direccion_entrega = ?, 
                    subtotal = ?, 
                    descuentos = ?, 
                    no_gravado = ?,
                    exento = ?,
                    impuestos = ?, 
                    total = ?, 
                    observaciones = ?
                WHERE factura_proveedor_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        // ASIGNAR TODOS LOS VALORES A VARIABLES ANTES DE bind_param
        $sucursal_id_val = $sucursal_id;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_pv_val = intval($data['comprobante_pv'] ?? 0);
        $comprobante_nro_val = intval($data['comprobante_nro'] ?? 0);
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_vencimiento_val = $f_vencimiento;
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
        $observaciones_val = $observaciones;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        error_log("Valores para update:");
        error_log("sucursal_id: " . ($sucursal_id_val ?? 'null'));

        // Cadena de tipos: i,i,i,i,i,i,i,s,s,i,i,d,s,d,d,d,d,d,d,s,i,i (22 caracteres)
        mysqli_stmt_bind_param($stmt, 
            "iiiiiissiidsddddddddsii",
            $sucursal_id_val,
            $comprobante_tipo_id_val,
            $comprobante_pv_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_vencimiento_val,
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

        mysqli_commit($conexion);
        error_log("=== FIN editarFacturaProveedor - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Factura actualizada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarFacturaProveedor: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerFacturaProveedorPorId($conexion, $id, $empresa_idx)
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

    $sql = "SELECT fp.*, fp.sucursal_id,
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
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $factura = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$factura) {
        return null;
    }
    
    // Log para verificar que sucursal_id viene
    error_log("Factura cargada - sucursal_id: " . ($factura['sucursal_id'] ?? 'null'));
    
    $sql_detalles = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                            p.iva_alicuota_id as producto_iva_id,
                            pp.codigo_proveedor
                     FROM gestion__facturas_proveedores_detalle d
                     LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                     LEFT JOIN gestion__productos_proveedores pp ON d.producto_id = pp.producto_id AND pp.entidad_id = ?
                     WHERE d.factura_proveedor_id = ?
                     ORDER BY d.factura_proveedor_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt)
        return $factura;

    mysqli_stmt_bind_param($stmt, "ii", $factura['entidad_id'], $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'factura_proveedor_detalle_id' => $detalle['factura_proveedor_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_nombre' => $detalle['producto_nombre'] . ' (' . $detalle['producto_codigo'] . ')',
            'cantidad' => floatval($detalle['cantidad']),
            'precio_unitario' => floatval($detalle['precio_unitario']),
            'descuento_porcentaje' => floatval($detalle['descuento_porcentaje'] ?? 0),
            'descuento' => floatval($detalle['descuento'] ?? 0),
            'no_gravado' => floatval($detalle['no_gravado'] ?? 0),
            'exento' => floatval($detalle['exento'] ?? 0),
            'iva_alicuota_id' => $detalle['iva_alicuota_id'] ?? $detalle['producto_iva_id'] ?? 1,
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
            WHERE comprobante_grupo_id = 2 
            AND comprobante_subgrupo_id = 6
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

function obtenerProductosPorProveedor($conexion, $empresa_idx, $entidad_id)
{
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   pp.codigo_proveedor, p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje
            FROM gestion__productos p
            INNER JOIN gestion__productos_proveedores pp ON p.producto_id = pp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
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
        $productos[] = $fila;
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
    $q = mysqli_real_escape_string($conexion, $q);
    
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   pp.codigo_proveedor, p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje
            FROM gestion__productos p
            INNER JOIN gestion__productos_proveedores pp ON p.producto_id = pp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
            WHERE p.empresa_id = ? 
            AND pp.entidad_id = ?
            AND p.tabla_estado_registro_id = 1
            AND pp.tabla_estado_registro_id = 1
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR pp.codigo_proveedor LIKE ?)
            ORDER BY p.producto_nombre
            LIMIT 20";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    $search = "%$q%";
    mysqli_stmt_bind_param($stmt, "iisss", $empresa_idx, $entidad_id, $search, $search, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
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
        
        // Calcular valores con defaults
        $neto_gravado = floatval($detalle['neto_gravado'] ?? ($cantidad * $precio_unitario));
        $no_gravado = floatval($detalle['no_gravado'] ?? 0);
        $exento = floatval($detalle['exento'] ?? 0);
        $descuento_porcentaje = floatval($detalle['descuento_porcentaje'] ?? 0);
        $descuento = floatval($detalle['descuento'] ?? 0);
        $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : 0;
        $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 0);
        $iva_importe = floatval($detalle['iva_importe'] ?? ($neto_gravado * $iva_porcentaje / 100));
        $total_linea = floatval($detalle['total_linea'] ?? ($neto_gravado + $iva_importe + $no_gravado + $exento));
        
        // Insertar detalle
        $sql = "INSERT INTO gestion__facturas_proveedores_detalle 
                (factura_proveedor_id, empresa_id, producto_id, cantidad, cantidad_recibida, 
                 precio_unitario, descuento_porcentaje, descuento, neto_gravado, no_gravado, exento, 
                 iva_alicuota_id, iva_porcentaje, iva_importe, total_linea) 
                VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando insert detalle: " . mysqli_error($conexion));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "iiiddddddddiddd",
            $factura_proveedor_id,
            $empresa_id,
            $detalle['producto_id'],
            $cantidad,
            $precio_unitario,
            $descuento_porcentaje,
            $descuento,
            $neto_gravado,
            $no_gravado,
            $exento,
            $iva_alicuota_id,
            $iva_porcentaje,
            $iva_importe,
            $total_linea
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            error_log("Error ejecutando insert detalle $index: " . $error);
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $detalle_id = mysqli_insert_id($conexion);
        error_log("Detalle $index insertado con ID: " . $detalle_id);
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

?>