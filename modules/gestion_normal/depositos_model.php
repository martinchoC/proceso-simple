<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
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

function obtenerInfoEstado($conexion, $estado_registro_id) {
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar FROM conf__estados_registros WHERE estado_registro_id = ?";
    } else {
        return [
            'estado_registro' => 'Estado ' . $estado_registro_id,
            'codigo_estandar' => 'ESTADO_' . $estado_registro_id
        ];
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $info;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id) {
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

function obtenerBotonAgregar($conexion, $pagina_id) {
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
        'nombre_funcion' => 'Nuevo Depósito',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id FROM conf__estados_registros WHERE valor_estandar IS NOT NULL ORDER BY valor_estandar ASC LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

function ejecutarTransicionEstado($conexion, $deposito_id, $accion_js, $empresa_idx, $pagina_id) {
    $deposito_id = intval($deposito_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT deposito_id, tabla_estado_registro_id FROM gestion__depositos WHERE deposito_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $deposito_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $deposito = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$deposito) return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $deposito['tabla_estado_registro_id'];

    $sql_funcion = "SELECT pf.* FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    $sql_update = "UPDATE gestion__depositos SET tabla_estado_registro_id = ? WHERE deposito_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $deposito_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

function obtenerDepositos($conexion, $empresa_idx, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) $estado_column = 'nombre_estado';
        elseif (in_array('descripcion', $columns)) $estado_column = 'descripcion';
    }

    $sql = "SELECT d.*, 
                   e.empresa,
                   s.sucursal_nombre,
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__depositos d
            LEFT JOIN conf__empresas e ON d.empresa_id = e.empresa_id
            LEFT JOIN gestion__sucursales s ON d.sucursal_id = s.sucursal_id
            LEFT JOIN conf__estados_registros er ON d.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE d.empresa_id = ?
            ORDER BY s.sucursal_nombre, d.deposito_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';
        $fila['empresa_nombre'] = $fila['empresa'] ?? 'Sin empresa';
        $fila['sucursal_nombre'] = $fila['sucursal_nombre'] ?? 'Sin sucursal';
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

function agregarDeposito($conexion, $data) {
    error_log("=== INICIO agregarDeposito ===");
    error_log("Datos: " . print_r($data, true));
    if (!$conexion) return ['resultado' => false, 'error' => 'Error de conexión'];
    if (empty($data['sucursal_id'])) return ['resultado' => false, 'error' => 'Debe seleccionar una sucursal'];
    if (empty($data['deposito_nombre'])) return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    if (empty($data['codigo'])) return ['resultado' => false, 'error' => 'El código es obligatorio'];

    mysqli_begin_transaction($conexion);
    try {
        // Validar unicidad de código (empresa + sucursal)
        $sql_check = "SELECT COUNT(*) as total FROM gestion__depositos 
                      WHERE empresa_id = ? AND sucursal_id = ? AND codigo = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        $empresa_idx = intval($data['empresa_idx']);
        $sucursal_id = intval($data['sucursal_id']);
        $codigo = trim($data['codigo']);
        mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $sucursal_id, $codigo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($row['total'] > 0) throw new Exception('Ya existe un depósito con este código en la sucursal seleccionada');

        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) $estado_inicial = 1;

        $sql = "INSERT INTO gestion__depositos 
                (empresa_id, sucursal_id, deposito_nombre, codigo, descripcion, 
                 permite_ingresos, permite_egresos, es_principal, orden, 
                 tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando insert: " . mysqli_error($conexion));

        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = intval($data['sucursal_id']);
        $nombre_val = trim($data['deposito_nombre']);
        $codigo_val = trim($data['codigo']);
        $descripcion_val = isset($data['descripcion']) ? trim($data['descripcion']) : null;
        $permite_ingresos = isset($data['permite_ingresos']) ? (int)$data['permite_ingresos'] : 1;
        $permite_egresos = isset($data['permite_egresos']) ? (int)$data['permite_egresos'] : 1;
        $es_principal = isset($data['es_principal']) ? (int)$data['es_principal'] : 0;
        $orden = isset($data['orden']) ? (int)$data['orden'] : 1;
        $estado_val = $estado_inicial;

        mysqli_stmt_bind_param($stmt, "iisssiiiii",
            $empresa_id_val, $sucursal_id_val, $nombre_val, $codigo_val, $descripcion_val,
            $permite_ingresos, $permite_egresos, $es_principal, $orden, $estado_val
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        $deposito_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        error_log("Depósito creado ID: " . $deposito_id);
        return ['resultado' => true, 'deposito_id' => $deposito_id];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR agregarDeposito: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarDeposito($conexion, $id, $data) {
    $id = intval($id);
    error_log("=== INICIO editarDeposito ID: $id ===");
    error_log("Datos: " . print_r($data, true));
    mysqli_begin_transaction($conexion);
    try {
        // Validar unicidad de código excluyendo el actual
        $sql_check = "SELECT COUNT(*) as total FROM gestion__depositos 
                      WHERE empresa_id = ? AND sucursal_id = ? AND codigo = ? AND deposito_id != ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) throw new Exception("Error preparando consulta duplicados: " . mysqli_error($conexion));
        $empresa_idx = intval($data['empresa_idx']);
        $sucursal_id = intval($data['sucursal_id']);
        $codigo = trim($data['codigo']);
        mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $sucursal_id, $codigo, $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($row['total'] > 0) throw new Exception('Ya existe otro depósito con este código en la sucursal');

        $sql = "UPDATE gestion__depositos 
                SET sucursal_id = ?, deposito_nombre = ?, codigo = ?, descripcion = ?,
                    permite_ingresos = ?, permite_egresos = ?, es_principal = ?, orden = ?
                WHERE deposito_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando update: " . mysqli_error($conexion));

        $sucursal_id_val = intval($data['sucursal_id']);
        $nombre_val = trim($data['deposito_nombre']);
        $codigo_val = trim($data['codigo']);
        $descripcion_val = isset($data['descripcion']) ? trim($data['descripcion']) : null;
        $permite_ingresos = isset($data['permite_ingresos']) ? (int)$data['permite_ingresos'] : 1;
        $permite_egresos = isset($data['permite_egresos']) ? (int)$data['permite_egresos'] : 1;
        $es_principal = isset($data['es_principal']) ? (int)$data['es_principal'] : 0;
        $orden = isset($data['orden']) ? (int)$data['orden'] : 1;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "isssiiiiii",
            $sucursal_id_val, $nombre_val, $codigo_val, $descripcion_val,
            $permite_ingresos, $permite_egresos, $es_principal, $orden,
            $id_val, $empresa_idx_val
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        $affected = mysqli_stmt_affected_rows($stmt);
        error_log("Filas afectadas: $affected");
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Depósito actualizado correctamente'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR editarDeposito: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerDepositoPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $sql = "SELECT d.*, s.sucursal_nombre, e.empresa
            FROM gestion__depositos d
            LEFT JOIN gestion__sucursales s ON d.sucursal_id = s.sucursal_id
            LEFT JOIN conf__empresas e ON d.empresa_id = e.empresa_id
            WHERE d.deposito_id = ? AND d.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $deposito = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $deposito;
}

function obtenerSucursalesEmpresa($conexion, $empresa_idx) {
    $sql = "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_nombre";
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

function obtenerEstadosRegistro($conexion) {
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    $nombre_columna = 'estado_registro';
    if (in_array('nombre_estado', $columns)) $nombre_columna = 'nombre_estado';
    elseif (in_array('descripcion', $columns)) $nombre_columna = 'descripcion';

    $sql = "SELECT estado_registro_id, $nombre_columna as estado_nombre, codigo_estandar
            FROM conf__estados_registros ORDER BY orden, $nombre_columna";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];
    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = [
            'estado_registro_id' => $fila['estado_registro_id'],
            'estado_registro' => $fila['estado_nombre'],
            'codigo_estandar' => $fila['codigo_estandar']
        ];
    }
    return $estados;
}
?>