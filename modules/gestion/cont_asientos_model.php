<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// ========== FUNCIONES AUXILIARES ==========
function obtenerAnioMes($fecha) {
    $timestamp = strtotime($fecha);
    return [
        'anio' => date('Y', $timestamp),
        'mes' => date('m', $timestamp)
    ];
}

// ========== FUNCIÓN PARA GENERAR NÚMERO DE ASIENTO ==========
function generarNumeroAsiento($conexion, $empresa_idx, $anio)
{
    $empresa_idx = intval($empresa_idx);
    $anio = intval($anio);
    
    $sql = "SELECT MAX(cont_asiento_id) as max_id 
            FROM gestion__cont_asientos 
            WHERE empresa_id = ? AND anio = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return $anio . '000001';
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $anio);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $max_id = intval($row['max_id'] ?? 0);
    
    if ($max_id == 0) {
        $secuencial = 1;
    } else {
        $sql_ids = "SELECT cont_asiento_id 
                    FROM gestion__cont_asientos 
                    WHERE empresa_id = ? AND anio = ? 
                    ORDER BY cont_asiento_id";
        
        $stmt_ids = mysqli_prepare($conexion, $sql_ids);
        mysqli_stmt_bind_param($stmt_ids, "ii", $empresa_idx, $anio);
        mysqli_stmt_execute($stmt_ids);
        $result_ids = mysqli_stmt_get_result($stmt_ids);
        
        $ids = [];
        while ($fila = mysqli_fetch_assoc($result_ids)) {
            $ids[] = $fila['cont_asiento_id'];
        }
        mysqli_stmt_close($stmt_ids);
        
        $secuencial = count($ids) + 1;
        for ($i = 1; $i <= count($ids); $i++) {
            if (!in_array($i, $ids)) {
                $secuencial = $i;
                break;
            }
        }
    }
    
    $secuencial_str = str_pad($secuencial, 6, '0', STR_PAD_LEFT);
    return $anio . $secuencial_str;
}

function obtenerSecuencialAsiento($conexion, $empresa_idx, $anio, $cont_asiento_id = null)
{
    $empresa_idx = intval($empresa_idx);
    $anio = intval($anio);
    
    if ($cont_asiento_id) {
        $sql = "SELECT COUNT(*) as total 
                FROM gestion__cont_asientos 
                WHERE empresa_id = ? AND anio = ? AND cont_asiento_id <= ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $empresa_idx, $anio, $cont_asiento_id);
    } else {
        $sql = "SELECT COUNT(*) as total 
                FROM gestion__cont_asientos 
                WHERE empresa_id = ? AND anio = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $anio);
    }
    
    if (!$stmt) {
        return 1;
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return intval($row['total']);
}

function formatearNumeroAsiento($anio, $secuencial)
{
    return $anio . str_pad($secuencial, 6, '0', STR_PAD_LEFT);
}

// ========== FUNCIONES PARA ASIENTOS ==========

function obtenerAsientosContables($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Obtener tabla_id de la página
    $info_pagina = obtenerInfoPagina($conexion, $pagina_id);
    $tabla_id = $info_pagina ? $info_pagina['tabla_id'] : 103;
    
    error_log("=== obtenerAsientosContables - tabla_id: " . $tabla_id);

    // IMPORTANTE: Usar estado_registro_id (no tabla_estado_registro_id) para el JOIN
    $sql = "SELECT a.*, 
                   m.moneda as moneda_nombre,
                   s.sucursal_nombre,
                   d.deposito_nombre,
                   ctas.cont_tipo_asiento,
                   ter.tabla_estado_registro as estado_desc,
                   c.bg_clase as estado_bg_clase,
                   c.color_clase as estado_color
            FROM gestion__cont_asientos a
            LEFT JOIN gestion__monedas m ON a.moneda_id = m.moneda_id
            LEFT JOIN gestion__sucursales s ON a.sucursal_id = s.sucursal_id
            LEFT JOIN gestion__depositos d ON a.deposito_id = d.deposito_id
            LEFT JOIN gestion__cont_tipos_asientos ctas ON a.cont_tipo_asiento_id = ctas.cont_tipo_asiento_id
            -- CORREGIDO: Usar estado_registro_id en lugar de tabla_estado_registro_id
            LEFT JOIN conf__tablas_estados_registros ter ON a.tabla_estado_registro_id = ter.estado_registro_id AND ter.tabla_id = ?
            LEFT JOIN conf__colores c ON ter.color_id = c.color_id
            WHERE a.empresa_id = ?
            ORDER BY a.f_asiento DESC, a.cont_asiento_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query asientos: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $tabla_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $totales = obtenerTotalesAsiento($conexion, $fila['cont_asiento_id']);
        $fila['total_debe'] = $totales['total_debe'];
        $fila['total_haber'] = $totales['total_haber'];
        $fila['fecha'] = $fila['f_asiento'];
        
        $secuencial = obtenerSecuencialAsiento($conexion, $empresa_idx, $fila['anio'], $fila['cont_asiento_id']);
        $fila['numero_asiento'] = formatearNumeroAsiento($fila['anio'], $secuencial);
        
        // Estado HTML
        $estado_color = $fila['estado_bg_clase'] ?? ($fila['estado_color'] ?? 'dark');
        $estado_texto = $fila['estado_desc'] ?? $fila['tabla_estado_registro_id'] . ' - Sin Estado';
        //$fila['estado_html'] = '<span class="badge bg-' . $estado_color . ' text-white">' . htmlspecialchars($estado_texto) . '</span>';
        $fila['estado_html'] = $estado_texto;

        // Estado para lógica JS
        $fila['estado'] = match($fila['tabla_estado_registro_id']) {
            1,2,5 => 'registrado',
            3 => 'borrador',
            4,6 => 'anulado',
            default => 'borrador'
        };
        
        // Agregar botones según estado actual
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}
function obtenerTotalesAsiento($conexion, $cont_asiento_id)
{
    $cont_asiento_id = intval($cont_asiento_id);
    
    $sql = "SELECT 
                COALESCE(SUM(CASE WHEN importe_local > 0 THEN importe_local ELSE 0 END), 0) as total_debe,
                COALESCE(SUM(CASE WHEN importe_local < 0 THEN ABS(importe_local) ELSE 0 END), 0) as total_haber
            FROM gestion__cont_asientos_detalles 
            WHERE cont_asiento_id = ? AND estado = 'activo'";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['total_debe' => 0, 'total_haber' => 0];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $cont_asiento_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return [
        'total_debe' => floatval($row['total_debe']),
        'total_haber' => floatval($row['total_haber'])
    ];
}

function obtenerAsientoPorId($conexion, $id, $empresa_idx, $pagina_id)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Obtener tabla_id de la página
    $info_pagina = obtenerInfoPagina($conexion, $pagina_id);
    $tabla_id = $info_pagina ? $info_pagina['tabla_id'] : null;
    
    if (!$tabla_id) {
        error_log("No se pudo obtener tabla_id para pagina_id: " . $pagina_id);
        return null;
    }

    $sql = "SELECT a.*, 
                   a.f_asiento as fecha,
                   ctas.cont_tipo_asiento as cont_tipo_asiento_nombre,
                   ter.tabla_estado_registro as estado_desc
            FROM gestion__cont_asientos a
            LEFT JOIN gestion__cont_tipos_asientos ctas ON a.cont_tipo_asiento_id = ctas.cont_tipo_asiento_id
            LEFT JOIN conf__tablas_estados_registros ter ON a.tabla_estado_registro_id = ter.estado_registro_id AND ter.tabla_id = ?
            WHERE a.cont_asiento_id = ? AND a.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "iii", $tabla_id, $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $asiento = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($asiento) {
        $secuencial = obtenerSecuencialAsiento($conexion, $empresa_idx, $asiento['anio'], $asiento['cont_asiento_id']);
        $asiento['numero_asiento'] = formatearNumeroAsiento($asiento['anio'], $secuencial);
        
        $asiento['estado'] = match($asiento['tabla_estado_registro_id']) {
            1, 2, 5 => 'registrado',
            3 => 'borrador',
            4, 6 => 'anulado',
            default => 'borrador'
        };
    }

    return $asiento;
}

function agregarAsiento($conexion, $data)
{
    error_log("=== INICIO agregarAsiento ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    if (empty($data['fecha'])) {
        return ['resultado' => false, 'error' => 'La fecha es obligatoria'];
    }
    if (empty($data['sucursal_id'])) {
        return ['resultado' => false, 'error' => 'La sucursal es obligatoria'];
    }
    if (empty($data['deposito_id'])) {
        return ['resultado' => false, 'error' => 'El depósito es obligatorio'];
    }
    if (empty($data['moneda_id'])) {
        return ['resultado' => false, 'error' => 'La moneda es obligatoria'];
    }
    
    $fecha_valida = date('Y-m-d', strtotime($data['fecha']));
    if (!$fecha_valida || $fecha_valida == '1970-01-01') {
        return ['resultado' => false, 'error' => 'La fecha no es válida'];
    }
    $data['fecha'] = $fecha_valida;
    
    $fecha_data = obtenerAnioMes($data['fecha']);
    $anio = $fecha_data['anio'];
    $mes = $fecha_data['mes'];
    
    $tipo_asiento_id = 1;
    
    mysqli_begin_transaction($conexion);

    try {
        $sql = "INSERT INTO gestion__cont_asientos 
                (empresa_id, sucursal_id, deposito_id, comprobante_id, entidad_id, 
                 cont_tipo_asiento_id, f_asiento, anio, mes, 
                 descripcion, moneda_id, tipo_cambio, usuario_creacion_id, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_id_val = intval($data['comprobante_id'] ?? 0);
        $entidad_id_val = intval($data['entidad_id'] ?? 0);
        $cont_tipo_asiento_id_val = $tipo_asiento_id;
        $f_asiento_val = $data['fecha'];
        $anio_val = $anio;
        $mes_val = $mes;
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = !empty($data['tipo_cambio']) ? floatval($data['tipo_cambio']) : 1.000000;
        $usuario_creacion_val = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;
        $tabla_estado_registro_id_val = 3;

        mysqli_stmt_bind_param($stmt, "iiiiiiisiiisdi", 
            $empresa_id_val,
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_id_val,
            $entidad_id_val,
            $cont_tipo_asiento_id_val,
            $f_asiento_val,
            $anio_val,
            $mes_val,
            $descripcion_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $usuario_creacion_val,
            $tabla_estado_registro_id_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $asiento_id = mysqli_insert_id($conexion);
        $secuencial = obtenerSecuencialAsiento($conexion, $empresa_id_val, $anio_val, $asiento_id);
        $numero_asiento = formatearNumeroAsiento($anio_val, $secuencial);
        
        error_log("Asiento creado con ID: " . $asiento_id . " - Número: " . $numero_asiento . " - Fecha: " . $f_asiento_val);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN agregarAsiento - ÉXITO ===");
        return ['resultado' => true, 'cont_asiento_id' => $asiento_id, 'numero_asiento' => $numero_asiento, 'message' => 'Asiento creado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarAsiento: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// SOLO UNA VEZ - Función editarAsiento (eliminé la duplicada)
function editarAsiento($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarAsiento ID: $id ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (empty($data['fecha'])) {
        return ['resultado' => false, 'error' => 'La fecha es obligatoria'];
    }
    
    $fecha_valida = date('Y-m-d', strtotime($data['fecha']));
    if (!$fecha_valida || $fecha_valida == '1970-01-01') {
        return ['resultado' => false, 'error' => 'La fecha no es válida'];
    }
    $data['fecha'] = $fecha_valida;
    
    $fecha_data = obtenerAnioMes($data['fecha']);
    $anio = $fecha_data['anio'];
    $mes = $fecha_data['mes'];
    
    mysqli_begin_transaction($conexion);

    try {
        $sql = "UPDATE gestion__cont_asientos 
                SET sucursal_id = ?,
                    deposito_id = ?,
                    comprobante_id = ?,
                    entidad_id = ?,
                    f_asiento = ?,
                    anio = ?,
                    mes = ?,
                    descripcion = ?,
                    moneda_id = ?,
                    tipo_cambio = ?
                WHERE cont_asiento_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $sucursal_id_val = intval($data['sucursal_id']);
        $deposito_id_val = intval($data['deposito_id']);
        $comprobante_id_val = intval($data['comprobante_id'] ?? 0);
        $entidad_id_val = intval($data['entidad_id'] ?? 0);
        $f_asiento_val = $data['fecha'];
        $anio_val = $anio;
        $mes_val = $mes;
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = !empty($data['tipo_cambio']) ? floatval($data['tipo_cambio']) : 1.000000;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "iiiisiisidii", 
            $sucursal_id_val,
            $deposito_id_val,
            $comprobante_id_val,
            $entidad_id_val,
            $f_asiento_val,
            $anio_val,
            $mes_val,
            $descripcion_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $id_val,
            $empresa_idx_val
        );
            
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN editarAsiento - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Asiento actualizado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarAsiento: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function eliminarAsiento($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    mysqli_begin_transaction($conexion);
    
    try {
        $sql_delete_detalles = "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_delete_detalles);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $sql_delete = "DELETE FROM gestion__cont_asientos WHERE cont_asiento_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_delete);
        mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Asiento eliminado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function anularAsiento($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 4 
            WHERE cont_asiento_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al preparar la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($filas == 0) {
        return ['success' => false, 'error' => 'El asiento no está en estado borrador o no existe'];
    }
    
    return ['success' => true, 'message' => 'Asiento anulado correctamente'];
}

function registrarAsiento($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $totales = obtenerTotalesAsiento($conexion, $id);
    if (abs($totales['total_debe'] - $totales['total_haber']) > 0.01) {
        return ['success' => false, 'error' => 'No se puede registrar: El Debe debe ser igual al Haber'];
    }
    
    $sql = "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 1 
            WHERE cont_asiento_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al preparar la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($filas == 0) {
        return ['success' => false, 'error' => 'El asiento no está en estado borrador o no existe'];
    }
    
    return ['success' => true, 'message' => 'Asiento registrado correctamente'];
}
// ========== FUNCIONES PARA DETALLES DE ASIENTO ==========

function obtenerDetallesAsiento($conexion, $cont_asiento_id, $empresa_idx)
{
    $cont_asiento_id = intval($cont_asiento_id);

    $sql = "SELECT d.*, 
                   c.codigo as cuenta_codigo, 
                   c.nombre as cuenta_nombre
            FROM gestion__cont_asientos_detalles d
            LEFT JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id
            WHERE d.cont_asiento_id = ? AND d.estado = 'activo'
            ORDER BY d.cuenta_id";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query detalles: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $cont_asiento_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    $contador = 1;
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['id'] = $contador++;
        $fila['cuenta_nombre_completo'] = $fila['cuenta_codigo'] . ' - ' . $fila['cuenta_nombre'];
        $fila['importe_local_debe'] = $fila['importe_local'] > 0 ? $fila['importe_local'] : 0;
        $fila['importe_local_haber'] = $fila['importe_local'] < 0 ? abs($fila['importe_local']) : 0;
        // Asegurar que tipo tenga valor por defecto 'M'
        $fila['tipo'] = $fila['tipo'] ?? 'M';
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function obtenerDetallePorId($conexion, $cont_asiento_id, $cuenta_id, $empresa_idx)
{
    $cont_asiento_id = intval($cont_asiento_id);
    $cuenta_id = intval($cuenta_id);

    error_log("=== obtenerDetallePorId ===");
    error_log("cont_asiento_id: " . $cont_asiento_id);
    error_log("cuenta_id: " . $cuenta_id);

    $sql = "SELECT d.*, 
                   c.codigo as cuenta_codigo, 
                   c.nombre as cuenta_nombre
            FROM gestion__cont_asientos_detalles d
            LEFT JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id
            WHERE d.cont_asiento_id = ? AND d.cuenta_id = ? AND d.estado = 'activo'";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $cont_asiento_id, $cuenta_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalle = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($detalle) {
        $detalle['importe_local_debe'] = $detalle['importe_local'] > 0 ? $detalle['importe_local'] : 0;
        $detalle['importe_local_haber'] = $detalle['importe_local'] < 0 ? abs($detalle['importe_local']) : 0;
        error_log("Detalle encontrado - importe_local: " . $detalle['importe_local']);
    } else {
        error_log("No se encontró detalle para cont_asiento_id: $cont_asiento_id, cuenta_id: $cuenta_id");
    }

    return $detalle;
}

function agregarDetalleAsiento($conexion, $data)
{
    error_log("=== INICIO agregarDetalleAsiento ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    if (empty($data['cont_asiento_id'])) {
        return ['resultado' => false, 'error' => 'El asiento es obligatorio'];
    }
    if (empty($data['cuenta_id'])) {
        return ['resultado' => false, 'error' => 'La cuenta contable es obligatoria'];
    }
    
    $importeDebe = floatval($data['importe_local_debe'] ?? 0);
    $importeHaber = floatval($data['importe_local_haber'] ?? 0);
    $importe_local = $importeDebe - $importeHaber;
    
    if ($importeDebe == 0 && $importeHaber == 0) {
        return ['resultado' => false, 'error' => 'Debe ingresar un importe (Debe o Haber)'];
    }
    
    mysqli_begin_transaction($conexion);

    try {
        $sql_asiento = "SELECT moneda_id, tipo_cambio FROM gestion__cont_asientos WHERE cont_asiento_id = ?";
        $stmt_as = mysqli_prepare($conexion, $sql_asiento);
        if (!$stmt_as) {
            throw new Exception("Error preparando consulta asiento: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt_as, "i", $data['cont_asiento_id']);
        mysqli_stmt_execute($stmt_as);
        $result_as = mysqli_stmt_get_result($stmt_as);
        $asiento = mysqli_fetch_assoc($result_as);
        mysqli_stmt_close($stmt_as);
        
        if (!$asiento) {
            throw new Exception("No se encontró el asiento con ID: " . $data['cont_asiento_id']);
        }
        
        $moneda_id = $asiento['moneda_id'];
        $tipo_cambio = $asiento['tipo_cambio'];
        
        $sql_check = "SELECT COUNT(*) as total, tipo FROM gestion__cont_asientos_detalles 
                      WHERE cont_asiento_id = ? AND cuenta_id = ? AND estado = 'activo'";
        $stmt_check = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ii", $data['cont_asiento_id'], $data['cuenta_id']);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $row_check = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        if ($row_check['total'] > 0) {
            // Verificar que el detalle existente sea tipo MANUAL para poder editarlo
            if ($row_check['tipo'] !== 'M') {
                throw new Exception("No se puede editar una línea de tipo Automático");
            }
            
            $sql = "UPDATE gestion__cont_asientos_detalles 
                    SET importe_local = ?,
                        moneda_id = ?,
                        tipo_cambio = ?,
                        descripcion = ?
                    WHERE cont_asiento_id = ? AND cuenta_id = ? AND estado = 'activo'";
            
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                throw new Exception("Error preparando update: " . mysqli_error($conexion));
            }
            
            $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
            
            mysqli_stmt_bind_param($stmt, "didssi", 
                $importe_local,
                $moneda_id,
                $tipo_cambio,
                $descripcion_val,
                $data['cont_asiento_id'],
                $data['cuenta_id']
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            
            $mensaje = 'Detalle actualizado correctamente';
        } else {
            // Insertar nuevo detalle con tipo MANUAL por defecto
            $sql = "INSERT INTO gestion__cont_asientos_detalles 
                    (cont_asiento_id, cuenta_id, importe_local, moneda_id, tipo_cambio, descripcion, estado, tipo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'M')";

            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                throw new Exception("Error preparando insert: " . mysqli_error($conexion));
            }

            $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
            $estado_val = 'activo';

            mysqli_stmt_bind_param($stmt, "iidddss", 
                $data['cont_asiento_id'],
                $data['cuenta_id'],
                $importe_local,
                $moneda_id,
                $tipo_cambio,
                $descripcion_val,
                $estado_val
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            
            $mensaje = 'Detalle agregado correctamente';
        }

        $sql_update = "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 3 
                       WHERE cont_asiento_id = ? AND tabla_estado_registro_id = 1";
        $stmt_up = mysqli_prepare($conexion, $sql_update);
        if ($stmt_up) {
            mysqli_stmt_bind_param($stmt_up, "i", $data['cont_asiento_id']);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
        }

        mysqli_commit($conexion);
        error_log("=== FIN agregarDetalleAsiento - ÉXITO ===");
        return ['resultado' => true, 'message' => $mensaje];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarDetalleAsiento: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarDetalleAsiento($conexion, $id, $data)
{
    $cont_asiento_id = intval($data['cont_asiento_id']);
    $cuenta_id = intval($data['cuenta_id']);
    
    error_log("=== INICIO editarDetalleAsiento - Asiento: $cont_asiento_id, Cuenta: $cuenta_id ===");

    $importeDebe = floatval($data['importe_local_debe'] ?? 0);
    $importeHaber = floatval($data['importe_local_haber'] ?? 0);
    $importe_local = $importeDebe - $importeHaber;
    
    mysqli_begin_transaction($conexion);

    try {
        $sql = "UPDATE gestion__cont_asientos_detalles 
                SET importe_local = ?,
                    descripcion = ?
                WHERE cont_asiento_id = ? AND cuenta_id = ? AND estado = 'activo'";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;

        mysqli_stmt_bind_param($stmt, "dsii", 
            $importe_local,
            $descripcion_val,
            $cont_asiento_id,
            $cuenta_id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        $sql_update = "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 3 
                       WHERE cont_asiento_id = ? AND tabla_estado_registro_id = 1";
        $stmt_up = mysqli_prepare($conexion, $sql_update);
        if ($stmt_up) {
            mysqli_stmt_bind_param($stmt_up, "i", $cont_asiento_id);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
        }

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Detalle actualizado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarDetalleAsiento: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function eliminarDetalleAsiento($conexion, $cuenta_id, $cont_asiento_id, $empresa_idx)
{
    $cuenta_id = intval($cuenta_id);
    $cont_asiento_id = intval($cont_asiento_id);
    
    error_log("=== INICIO eliminarDetalleAsiento ===");
    error_log("cuenta_id: " . $cuenta_id);
    error_log("cont_asiento_id: " . $cont_asiento_id);
    error_log("empresa_idx: " . $empresa_idx);
    
    if ($cuenta_id <= 0) {
        return ['success' => false, 'error' => 'ID de cuenta no válido'];
    }
    
    if ($cont_asiento_id <= 0) {
        return ['success' => false, 'error' => 'ID de asiento no válido'];
    }
    
    mysqli_begin_transaction($conexion);
    
    try {
        // Primero verificar si existe el detalle
        $sql_check = "SELECT * FROM gestion__cont_asientos_detalles 
                      WHERE cont_asiento_id = ? AND cuenta_id = ? AND estado = 'activo'";
        
        $stmt_check = mysqli_prepare($conexion, $sql_check);
        if (!$stmt_check) {
            throw new Exception("Error preparando consulta check: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt_check, "ii", $cont_asiento_id, $cuenta_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) == 0) {
            mysqli_stmt_close($stmt_check);
            throw new Exception("No se encontró el detalle para eliminar (cont_asiento_id: $cont_asiento_id, cuenta_id: $cuenta_id)");
        }
        mysqli_stmt_close($stmt_check);
        
        // Soft delete: cambiar estado a 'inactivo'
        $sql = "UPDATE gestion__cont_asientos_detalles SET estado = 'inactivo' 
                WHERE cont_asiento_id = ? AND cuenta_id = ? AND estado = 'activo'";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $cont_asiento_id, $cuenta_id);
        mysqli_stmt_execute($stmt);
        
        $filas_afectadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        error_log("Filas afectadas en delete: " . $filas_afectadas);
        
        if ($filas_afectadas == 0) {
            throw new Exception("No se pudo eliminar el detalle (filas afectadas: 0)");
        }
        
        // Si el asiento estaba registrado, volver a borrador
        $sql_update = "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 3 
                       WHERE cont_asiento_id = ? AND tabla_estado_registro_id = 1";
        $stmt_up = mysqli_prepare($conexion, $sql_update);
        if ($stmt_up) {
            mysqli_stmt_bind_param($stmt_up, "i", $cont_asiento_id);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
        }
        
        mysqli_commit($conexion);
        error_log("=== FIN eliminarDetalleAsiento - ÉXITO ===");
        return ['success' => true, 'message' => 'Detalle eliminado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en eliminarDetalleAsiento: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
// ========== FUNCIONES PARA COMBOS ==========

function obtenerComprobantes($conexion, $empresa_idx)
{
    $sql = "SELECT c.comprobante_id, 
                   ct.comprobante_tipo_nombre, 
                   c.comprobante_nro,
                   c.comprobante_pv,
                   c.f_emision
            FROM gestion__comprobantes c
            LEFT JOIN gestion__comprobantes_tipos ct ON c.comprobante_tipo_id = ct.comprobante_tipo_id
            WHERE c.empresa_id = ? 
            AND c.tabla_estado_registro_id = 1
            ORDER BY c.f_emision DESC, c.comprobante_id DESC
            LIMIT 500";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $comprobantes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fecha = $fila['f_emision'] ? date('d/m/Y', strtotime($fila['f_emision'])) : '';
        $fila['comprobante_nombre_completo'] = $fila['comprobante_tipo_nombre'] . ' ' . 
            ($fila['comprobante_pv'] ? $fila['comprobante_pv'] . '-' : '') . 
            $fila['comprobante_nro'] . ($fecha ? ' - ' . $fecha : '');
        $comprobantes[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $comprobantes;
}

function obtenerSucursales($conexion, $empresa_idx)
{
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__sucursales 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
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

function obtenerDepositos($conexion, $empresa_idx)
{
    $sql = "SELECT deposito_id, deposito_nombre, codigo 
            FROM gestion__depositos 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY deposito_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $depositos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $depositos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $depositos;
}

function obtenerCuentasContables($conexion, $empresa_idx)
{
    $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza 
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND es_imputable = 1
            ORDER BY codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $cuentas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $cuentas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $cuentas;
}

function obtenerMonedas($conexion, $empresa_idx)
{
    $sql = "SELECT moneda_id, codigo, moneda, simbolo, es_moneda_base 
            FROM gestion__monedas 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY es_moneda_base DESC, moneda";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
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

function obtenerEntidades($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, entidad_nro_documento 
            FROM gestion__entidades 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $entidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $entidades[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $entidades;
}



// ========== FUNCIONES PARA BOTONES Y ESTADOS ==========

// Obtener información de la página (incluyendo tabla_id)
    function obtenerInfoPagina($conexion, $pagina_id)
    {
        $pagina_id = intval($pagina_id);
        
        $sql = "SELECT pagina_id, pagina, url, tabla_id, modulo_id
                FROM conf__paginas 
                WHERE pagina_id = ? AND tabla_estado_registro_id = 1";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, "i", $pagina_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $pagina = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        error_log("Info página - ID: " . $pagina_id . ", tabla_id: " . ($pagina['tabla_id'] ?? 'null'));
        
        return $pagina;
    }

    // Obtener el estado_registro_id a partir de tabla_id y estado_actual_id
    function obtenerEstadoRegistroId($conexion, $tabla_id, $estado_actual_id)
    {
        $sql = "SELECT estado_registro_id 
                FROM conf__tablas_estados_registros 
                WHERE tabla_id = ? AND tabla_estado_registro_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, "ii", $tabla_id, $estado_actual_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row ? $row['estado_registro_id'] : null;
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
    function obtenerInfoEstado($conexion, $tabla_id, $tabla_estado_registro_id)
    {
        $sql = "SELECT ter.tabla_estado_registro_id, 
                    ter.tabla_id,
                    ter.tabla_estado_registro,
                    ter.color_id,
                    c.bg_clase, 
                    c.color_clase
                FROM conf__tablas_estados_registros ter
                LEFT JOIN conf__colores c ON ter.color_id = c.color_id
                WHERE ter.tabla_id = ? AND ter.tabla_estado_registro_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, "ii", $tabla_id, $tabla_estado_registro_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $info = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $info;
    }


   function obtenerBotonesPorEstado($conexion, $pagina_id, $tabla_estado_registro_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        // El botón Agregar tiene origen_id = 0, se maneja aparte
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            continue;
        }
        
        // Solo mostrar botones cuyo origen coincide con el estado actual del registro
        if ($funcion['tabla_estado_registro_origen_id'] == $tabla_estado_registro_id) {
            $es_confirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;
            
            $accion_js = $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']);
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $accion_js,
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
                'accion_js' => 'agregar',
                'icono_clase' => $funcion['icono_clase'] ?? 'fas fa-plus',
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }

    return [
        'nombre_funcion' => 'Agregar Asiento',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}
// Ejecutar transición de estado
// Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $tabla_id, $registro_id, $accion_js, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    
    // Obtener información de la página para saber tabla_id
    $info_pagina = obtenerInfoPagina($conexion, $pagina_id);
    if (!$info_pagina) {
        return ['success' => false, 'error' => 'Página no encontrada'];
    }
    
    $tabla_id = intval($info_pagina['tabla_id']);
    
    // Mapeo de tabla_id a nombre de tabla real y nombre de columna ID
    $tablas = [
        103 => ['nombre' => 'gestion__cont_asientos', 'id_columna' => 'cont_asiento_id'],
        // Agregar más mapeos según sea necesario
    ];
    
    if (!isset($tablas[$tabla_id])) {
        error_log("Tabla no mapeada para tabla_id: " . $tabla_id);
        return ['success' => false, 'error' => 'Tabla no mapeada para estado (tabla_id: ' . $tabla_id . ')'];
    }
    
    $nombre_tabla = $tablas[$tabla_id]['nombre'];
    $id_columna = $tablas[$tabla_id]['id_columna'];
    
    // Verificar que el registro exista
    $sql_check = "SELECT * FROM $nombre_tabla WHERE $id_columna = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta: ' . mysqli_error($conexion)];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $registro = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$registro) {
        return ['success' => false, 'error' => 'Registro no encontrado en ' . $nombre_tabla . ' con ID ' . $registro_id];
    }
    
    // El estado actual es tabla_estado_registro_id (de la tabla de datos)
    $tabla_estado_actual_id = $registro['tabla_estado_registro_id'];
    
    // Buscar la función correspondiente
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta de función'];
    }
    
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $tabla_estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$funcion) {
        return ['success' => false, 'error' => 'Acción "' . $accion_js . '" no permitida para este estado (tabla_estado_actual_id: ' . $tabla_estado_actual_id . ')'];
    }
    
    $tabla_estado_destino_id = $funcion['tabla_estado_registro_destino_id'];
    
    // ===== VALIDACIÓN ESPECIAL PARA CONFIRMAR (REGISTRAR) =====
    // Si la acción es 'confirmar', verificar que Debe = Haber
    if ($accion_js === 'confirmar') {
        // Calcular totales del asiento
        $totales = obtenerTotalesAsiento($conexion, $registro_id);
        $total_debe = $totales['total_debe'];
        $total_haber = $totales['total_haber'];
        
        if (abs($total_debe - $total_haber) > 0.01) {
            return [
                'success' => false, 
                'error' => 'No se puede confirmar el asiento: El Debe (' . number_format($total_debe, 2) . 
                           ') debe ser igual al Haber (' . number_format($total_haber, 2) . '). Diferencia: ' . 
                           number_format(abs($total_debe - $total_haber), 2)
            ];
        }
    }
    
    // Si el estado destino es igual al actual, solo ejecutar acción sin cambio
    if ($tabla_estado_destino_id == $tabla_estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }
    
    // Actualizar el estado del registro
    $sql_update = "UPDATE $nombre_tabla SET tabla_estado_registro_id = ? WHERE $id_columna = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta de actualización'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $tabla_estado_destino_id, $registro_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}
?>