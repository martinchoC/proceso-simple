<?php
require_once __DIR__ . '/../../conexion.php';

/**
 * Modelo para gestión de submodelos - Versión simplificada
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
        'nombre_funcion' => 'Agregar Submodelo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos submodelos
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
function ejecutarTransicionEstado($conexion, $submodelo_id, $accion_js, $empresa_idx, $pagina_id) {
    $submodelo_id = intval($submodelo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Verificar que el submodelo pertenezca a la empresa
    $sql_check = "SELECT submodelo_id, tabla_estado_registro_id 
                  FROM gestion__submodelos 
                  WHERE submodelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $submodelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $submodelo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$submodelo) return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    
    $estado_actual_id = $submodelo['tabla_estado_registro_id'];
    
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
    $sql_update = "UPDATE gestion__submodelos 
                   SET tabla_estado_registro_id = ? 
                   WHERE submodelo_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $submodelo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener modelos activos para el select (con sus marcas)
function obtenerModelosActivos($conexion, $empresa_idx) {
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT m.modelo_id, m.modelo_nombre, ma.marca_nombre
            FROM gestion__modelos m
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id AND ma.empresa_id = ?
            WHERE m.empresa_id = ? 
            AND m.tabla_estado_registro_id = 1  -- Solo modelos activos
            AND ma.tabla_estado_registro_id = 1 -- Solo marcas activas
            ORDER BY ma.marca_nombre, m.modelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $modelos;
}

// ✅ Obtener todos los submodelos (con filtro multiempresa y joins para modelo y marca)
function obtenerSubmodelos($conexion, $empresa_idx, $pagina_id) {
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT sm.*, 
                   m.modelo_nombre,
                   ma.marca_nombre,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__submodelos sm
            INNER JOIN gestion__modelos m ON sm.modelo_id = m.modelo_id AND m.empresa_id = ?
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id AND ma.empresa_id = ?
            LEFT JOIN conf__estados_registros er ON sm.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE sm.empresa_id = ?
            ORDER BY ma.marca_nombre, m.modelo_nombre, sm.submodelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "iii", $empresa_idx, $empresa_idx, $empresa_idx);
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

// ✅ Agregar nuevo submodelo (con estado inicial)
function agregarSubmodelo($conexion, $data) {
    $submodelo_nombre = mysqli_real_escape_string($conexion, trim($data['submodelo_nombre'] ?? ''));
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($submodelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del submodelo es obligatorio'];
    }
    
    if ($modelo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un modelo válido'];
    }
    
    if (strlen($submodelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    // Verificar que el modelo pertenezca a la empresa
    $sql_check_modelo = "SELECT modelo_id FROM gestion__modelos 
                         WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_modelo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $modelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El modelo seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar duplicados (mismo nombre + mismo modelo + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__submodelos 
                  WHERE empresa_id = ? AND modelo_id = ? AND LOWER(submodelo_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $submodelo_nombre_lower = strtolower($submodelo_nombre);
    mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $modelo_id, $submodelo_nombre_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un submodelo con este nombre en el modelo seleccionado'];
    }
    
    // Insertar nuevo submodelo
    $sql = "INSERT INTO gestion__submodelos (submodelo_nombre, empresa_id, modelo_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "siii", $submodelo_nombre, $empresa_idx, $modelo_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $submodelo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'submodelo_id' => $submodelo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el submodelo'];
    }
}

// ✅ Editar submodelo existente
function editarSubmodelo($conexion, $id, $data) {
    $id = intval($id);
    $submodelo_nombre = mysqli_real_escape_string($conexion, trim($data['submodelo_nombre'] ?? ''));
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($submodelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del submodelo es obligatorio'];
    }
    
    if ($modelo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un modelo válido'];
    }
    
    if (strlen($submodelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }
    
    // Verificar que el submodelo pertenezca a la empresa
    $sql_check = "SELECT submodelo_id FROM gestion__submodelos 
                  WHERE submodelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    }
    
    // Verificar que el modelo pertenezca a la empresa
    $sql_check_modelo = "SELECT modelo_id FROM gestion__modelos 
                         WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_modelo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $modelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El modelo seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar duplicados (mismo nombre + mismo modelo + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__submodelos 
                      WHERE empresa_id = ? AND modelo_id = ? 
                      AND LOWER(submodelo_nombre) = LOWER(?) AND submodelo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $submodelo_nombre_lower = strtolower($submodelo_nombre);
    mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $modelo_id, $submodelo_nombre_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro submodelo con este nombre en el modelo seleccionado'];
    }
    
    // Actualizar submodelo
    $sql = "UPDATE gestion__submodelos 
            SET submodelo_nombre = ?, modelo_id = ? 
            WHERE submodelo_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "siii", $submodelo_nombre, $modelo_id, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el submodelo'];
    }
}

// ✅ Obtener submodelo específico
function obtenerSubmodeloPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT sm.*, er.estado_registro, er.codigo_estandar
            FROM gestion__submodelos sm
            LEFT JOIN conf__estados_registros er ON sm.tabla_estado_registro_id = er.estado_registro_id
            WHERE sm.submodelo_id = ? AND sm.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $submodelo = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $submodelo;
}
?>