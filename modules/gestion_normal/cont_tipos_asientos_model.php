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
        return ['estado_registro' => 'Estado ' . $estado_registro_id, 'codigo_estandar' => 'ESTADO_' . $estado_registro_id];
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
        'nombre_funcion' => 'Nuevo Tipo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT ter.estado_registro_id
            FROM conf__paginas p
            JOIN conf__tablas_estados_registros ter ON ter.tabla_id = p.tabla_id
            WHERE p.pagina_id = ?
            AND ter.es_inicial = 1
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return 1;
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($fila) return $fila['estado_registro_id'];
    return 1;
}

function obtenerEstadoPorNombre($conexion, $nombre_estado) {
    $sql = "SELECT estado_registro_id FROM conf__estados_registros WHERE LOWER(estado_registro) = LOWER(?) LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "s", $nombre_estado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($fila) return $fila['estado_registro_id'];
    return null;
}

function ejecutarTransicionEstado($conexion, $cont_tipo_asiento_id, $accion_js, $empresa_id, $pagina_id) {
    $cont_tipo_asiento_id = intval($cont_tipo_asiento_id);
    $pagina_id = intval($pagina_id);

    // Primero obtener el estado actual del registro
    $sql_check = "SELECT cta.tabla_estado_registro_id
                  FROM gestion__cont_tipos_asientos cta
                  WHERE cta.cont_tipo_asiento_id = ? AND cta.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $cont_tipo_asiento_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$tipo) return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $tipo['tabla_estado_registro_id'];

    // Buscar la función que permite la transición
    $sql_funcion = "SELECT pf.* FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.accion_js = ?
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
    
    // Si el estado destino es el mismo que el actual, solo retornamos éxito sin cambios
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    // Actualizar el estado
    $sql_update = "UPDATE gestion__cont_tipos_asientos SET tabla_estado_registro_id = ? WHERE cont_tipo_asiento_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error preparando update: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $cont_tipo_asiento_id, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Error actualizando estado: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Estado actualizado correctamente'];
}

function obtenerTiposAsientos($conexion, $empresa_id, $pagina_id) {
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

    $sql = "SELECT cta.*, 
                   er.$estado_column as estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__cont_tipos_asientos cta
            LEFT JOIN conf__estados_registros er ON cta.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE cta.empresa_id = ?
            ORDER BY cta.cont_tipo_asiento_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
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

        $fila['estado_actual'] = $fila['estado_registro'];
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function agregarTipoAsiento($conexion, $data) {
    error_log("=== INICIO agregarTipoAsiento ===");

    if (empty($data['codigo'])) return ['resultado' => false, 'error' => 'El código es obligatorio'];
    if (empty($data['cont_tipo_asiento'])) return ['resultado' => false, 'error' => 'El tipo de asiento es obligatorio'];

    // Verificar código duplicado
    $sql_check = "SELECT cont_tipo_asiento_id FROM gestion__cont_tipos_asientos WHERE codigo = ? AND empresa_id = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "si", $data['codigo'], $data['empresa_id']);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            return ['resultado' => false, 'error' => 'El código ya existe para esta empresa'];
        }
        mysqli_stmt_close($stmt_check);
    }

    mysqli_begin_transaction($conexion);
    try {
        $estado_inicial = obtenerEstadoInicial($conexion, $data['pagina_id']);
        if (!$estado_inicial) $estado_inicial = 1;

        // Mapear estado 'activo'/'inactivo' a estado_registro_id
        $estado_texto = $data['estado'] ?? 'activo';
        $estado_id = obtenerEstadoPorNombre($conexion, $estado_texto);
        if (!$estado_id) $estado_id = $estado_inicial;

        $sql = "INSERT INTO gestion__cont_tipos_asientos 
                (empresa_id, codigo, cont_tipo_asiento, descripcion, origen, modulo_origen, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando insert: " . mysqli_error($conexion));

        $empresa_id_val = intval($data['empresa_id']);
        $codigo_val = $data['codigo'];
        $tipo_val = $data['cont_tipo_asiento'];
        $descripcion_val = $data['descripcion'] ?? '';
        $origen_val = $data['origen'] ?? 'manual';
        $modulo_origen_val = $data['modulo_origen'] ?? '';
        $estado_id_val = intval($estado_id);

        mysqli_stmt_bind_param($stmt, "isssssi",
            $empresa_id_val,
            $codigo_val,
            $tipo_val,
            $descripcion_val,
            $origen_val,
            $modulo_origen_val,
            $estado_id_val
        );

        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        $cont_tipo_asiento_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['resultado' => true, 'cont_tipo_asiento_id' => $cont_tipo_asiento_id];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarTipoAsiento: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarTipoAsiento($conexion, $id, $data) {
    $id = intval($id);
    error_log("=== INICIO editarTipoAsiento ID: $id ===");

    // Verificar código duplicado (excluyendo el actual)
    $sql_check = "SELECT cont_tipo_asiento_id FROM gestion__cont_tipos_asientos WHERE codigo = ? AND empresa_id = ? AND cont_tipo_asiento_id != ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "sii", $data['codigo'], $data['empresa_id'], $id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            return ['resultado' => false, 'error' => 'El código ya existe para esta empresa'];
        }
        mysqli_stmt_close($stmt_check);
    }

    mysqli_begin_transaction($conexion);
    try {
        // Mapear estado 'activo'/'inactivo' a estado_registro_id
        $estado_texto = $data['estado'] ?? 'activo';
        $estado_id = obtenerEstadoPorNombre($conexion, $estado_texto);
        if (!$estado_id) $estado_id = 1;

        $sql = "UPDATE gestion__cont_tipos_asientos 
                SET codigo = ?, cont_tipo_asiento = ?, descripcion = ?, origen = ?, modulo_origen = ?, tabla_estado_registro_id = ?
                WHERE cont_tipo_asiento_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) throw new Exception("Error preparando update: " . mysqli_error($conexion));

        $codigo_val = $data['codigo'];
        $tipo_val = $data['cont_tipo_asiento'];
        $descripcion_val = $data['descripcion'] ?? '';
        $origen_val = $data['origen'] ?? 'manual';
        $modulo_origen_val = $data['modulo_origen'] ?? '';
        $estado_id_val = intval($estado_id);
        $id_val = $id;
        $empresa_id_val = intval($data['empresa_id']);

        mysqli_stmt_bind_param($stmt, "sssssiii",
            $codigo_val,
            $tipo_val,
            $descripcion_val,
            $origen_val,
            $modulo_origen_val,
            $estado_id_val,
            $id_val,
            $empresa_id_val
        );

        if (!mysqli_stmt_execute($stmt)) throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Tipo de asiento actualizado correctamente'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarTipoAsiento: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerTipoAsientoPorId($conexion, $id, $empresa_id) {
    $id = intval($id);
    $sql = "SELECT cta.*, er.estado_registro
            FROM gestion__cont_tipos_asientos cta
            LEFT JOIN conf__estados_registros er ON cta.tabla_estado_registro_id = er.estado_registro_id
            WHERE cta.cont_tipo_asiento_id = ? AND cta.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($tipo) {
        $tipo['estado_actual'] = $tipo['estado_registro'] ?? 'activo';
    }
    return $tipo;
}
?>