<?php
require_once "conexion.php";

/**
 * Modelo para gestión de grupos de comprobantes
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// ✅ Obtener funciones configuradas para la página desde conf__paginas_funciones
function obtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1 -- Solo funciones activas
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

// ✅ Obtener información de un estado específico
function obtenerInfoEstado($conexion, $estado_registro_id) {
    $sql = "SELECT estado_registro, codigo_estandar 
            FROM conf__estados_registros 
            WHERE estado_registro_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $info;
}

// ✅ Obtener botones disponibles según el estado actual
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id) {
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];
    
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0
            ];
        }
    }
    
    return $botones;
}

// ✅ Obtener botón "Agregar" específico para la página
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
        'nombre_funcion' => 'Agregar Grupo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos grupos de comprobantes
function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;
    
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $comprobante_grupo_id, $accion_js, $empresa_idx, $pagina_id) {
    $comprobante_grupo_id = intval($comprobante_grupo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Verificar que el grupo pertenezca a la empresa
    $sql_check = "SELECT comprobante_grupo_id, tabla_estado_registro_id 
                  FROM gestion__comprobantes_grupos 
                  WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_grupo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $grupo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$grupo) return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    
    $estado_actual_id = $grupo['tabla_estado_registro_id'];
    
    // Buscar la función correspondiente en conf__paginas_funciones
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
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
    
    // Actualizar el estado
    $sql_update = "UPDATE gestion__comprobantes_grupos 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $comprobante_grupo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener todos los grupos de comprobantes (con filtro multiempresa)
function obtenerComprobantesGrupos($conexion, $empresa_idx, $pagina_id) {
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT cg.*, 
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__comprobantes_grupos cg
            LEFT JOIN conf__estados_registros er ON cg.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE cg.empresa_id = ?
            ORDER BY cg.comprobante_grupo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar black por defecto
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

// ✅ Agregar nuevo grupo de comprobantes (con estado inicial)
function agregarComprobanteGrupo($conexion, $data) {
    $comprobante_grupo = mysqli_real_escape_string($conexion, trim($data['comprobante_grupo'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($comprobante_grupo)) {
        return ['resultado' => false, 'error' => 'El nombre del grupo es obligatorio'];
    }
    
    if (strlen($comprobante_grupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    // Verificar duplicados (mismo nombre + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_grupos 
                  WHERE empresa_id = ? AND LOWER(comprobante_grupo) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $comprobante_grupo_lower = strtolower($comprobante_grupo);
    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $comprobante_grupo_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un grupo de comprobante con este nombre'];
    }
    
    // Insertar nuevo grupo
    $sql = "INSERT INTO gestion__comprobantes_grupos (comprobante_grupo, empresa_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "sii", $comprobante_grupo, $empresa_idx, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $comprobante_grupo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_grupo_id' => $comprobante_grupo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el grupo de comprobante'];
    }
}

// ✅ Editar grupo de comprobantes existente
function editarComprobanteGrupo($conexion, $id, $data) {
    $id = intval($id);
    $comprobante_grupo = mysqli_real_escape_string($conexion, trim($data['comprobante_grupo'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($comprobante_grupo)) {
        return ['resultado' => false, 'error' => 'El nombre del grupo es obligatorio'];
    }
    
    if (strlen($comprobante_grupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }
    
    // Verificar que el grupo pertenezca a la empresa
    $sql_check = "SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos 
                  WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    }
    
    // Verificar duplicados (mismo nombre + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_grupos 
                      WHERE empresa_id = ? 
                      AND LOWER(comprobante_grupo) = LOWER(?) 
                      AND comprobante_grupo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $comprobante_grupo_lower = strtolower($comprobante_grupo);
    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $comprobante_grupo_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro grupo de comprobante con este nombre'];
    }
    
    // Actualizar grupo
    $sql = "UPDATE gestion__comprobantes_grupos 
            SET comprobante_grupo = ? 
            WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "sii", $comprobante_grupo, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el grupo de comprobante'];
    }
}

// ✅ Obtener grupo específico
function obtenerComprobanteGrupoPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT cg.*, er.estado_registro, er.codigo_estandar
            FROM gestion__comprobantes_grupos cg
            LEFT JOIN conf__estados_registros er ON cg.tabla_estado_registro_id = er.estado_registro_id
            WHERE cg.comprobante_grupo_id = ? AND cg.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $grupo = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $grupo;
}
?>