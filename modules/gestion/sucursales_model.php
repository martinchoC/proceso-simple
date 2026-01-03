<?php
require_once "conexion.php";

/**
 * Modelo para gestión de sucursales
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

// ✅ Obtener botón "Agregar" específico para la página (pagina_id=36)
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
    
    // Si no hay configuración, usar valores por defecto
    return [
        'nombre_funcion' => 'Agregar Sucursal',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevas sucursales
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
function ejecutarTransicionEstado($conexion, $sucursal_id, $accion_js, $empresa_idx, $pagina_id) {
    $sucursal_id = intval($sucursal_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Verificar que la sucursal pertenezca a la empresa
    $sql_check = "SELECT sucursal_id, tabla_estado_registro_id 
                  FROM gestion__sucursales 
                  WHERE sucursal_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursal = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$sucursal) return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    
    $estado_actual_id = $sucursal['tabla_estado_registro_id'];
    
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
    $sql_update = "UPDATE gestion__sucursales 
                   SET tabla_estado_registro_id = ? 
                   WHERE sucursal_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $sucursal_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener tipos de sucursales activos para el select
function obtenerTiposSucursalesActivos($conexion, $empresa_idx) {
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT sucursal_tipo_id, sucursal_tipo 
            FROM gestion__sucursales_tipos 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1  -- Solo tipos activos
            ORDER BY sucursal_tipo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $tipos;
}

// ✅ Obtener localidades activas para el select
function obtenerLocalidadesActivas($conexion) {
    $sql = "SELECT localidad_id, localidad 
            FROM conf__localidades 
            WHERE tabla_estado_registro_id = 1  -- Solo localidades activas
            ORDER BY localidad";
    
    $result = mysqli_query($conexion, $sql);
    $localidades = [];
    
    if ($result) {
        while ($fila = mysqli_fetch_assoc($result)) {
            $localidades[] = $fila;
        }
    }
    
    return $localidades;
}

// ✅ Obtener todas las sucursales (con filtro multiempresa y joins)
function obtenerSucursales($conexion, $empresa_idx, $pagina_id) {
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT s.*, 
                   st.sucursal_tipo,
                   cl.localidad,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__sucursales s
            INNER JOIN gestion__sucursales_tipos st ON s.sucursal_tipo_id = st.sucursal_tipo_id AND st.empresa_id = ?
            LEFT JOIN conf__localidades cl ON s.localidad_id = cl.localidad_id
            LEFT JOIN conf__estados_registros er ON s.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE s.empresa_id = ?
            ORDER BY s.sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar black por defecto
        $color_clase = $fila['color_clase'] ?? '';
        $bg_clase = $fila['bg_clase'] ?? '';
        $text_clase = $fila['text_clase'] ?? '';
        
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

// ✅ Agregar nueva sucursal (con estado inicial)
function agregarSucursal($conexion, $data) {
    $sucursal_nombre = mysqli_real_escape_string($conexion, trim($data['sucursal_nombre'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $sucursal_tipo_id = intval($data['sucursal_tipo_id'] ?? 0);
    $localidad_id = isset($data['localidad_id']) && $data['localidad_id'] ? intval($data['localidad_id']) : null;
    $direccion = mysqli_real_escape_string($conexion, trim($data['direccion'] ?? ''));
    $telefono = mysqli_real_escape_string($conexion, trim($data['telefono'] ?? ''));
    $email = mysqli_real_escape_string($conexion, trim($data['email'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($sucursal_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la sucursal es obligatorio'];
    }
    
    if ($sucursal_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de sucursal válido'];
    }
    
    if (strlen($sucursal_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }
    
    if (strlen($descripcion) > 255) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 255 caracteres'];
    }
    
    if (strlen($direccion) > 255) {
        return ['resultado' => false, 'error' => 'La dirección no puede exceder los 255 caracteres'];
    }
    
    if (strlen($telefono) > 50) {
        return ['resultado' => false, 'error' => 'El teléfono no puede exceder los 50 caracteres'];
    }
    
    if (strlen($email) > 100) {
        return ['resultado' => false, 'error' => 'El email no puede exceder los 100 caracteres'];
    }
    
    // Validar formato de email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['resultado' => false, 'error' => 'El formato del email es inválido'];
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    // Verificar que el tipo de sucursal pertenezca a la empresa
    $sql_check_tipo = "SELECT sucursal_tipo_id FROM gestion__sucursales_tipos 
                       WHERE sucursal_tipo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_tipo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $sucursal_tipo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El tipo de sucursal seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar que la localidad exista si se proporciona
    if ($localidad_id) {
        $sql_check_localidad = "SELECT localidad_id FROM conf__localidades WHERE localidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check_localidad);
        if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
        
        mysqli_stmt_bind_param($stmt, "i", $localidad_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            return ['resultado' => false, 'error' => 'La localidad seleccionada no existe'];
        }
    }
    
    // Verificar duplicados (mismo nombre + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__sucursales 
                  WHERE empresa_id = ? AND LOWER(sucursal_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $sucursal_nombre_lower = strtolower($sucursal_nombre);
    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $sucursal_nombre_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una sucursal con este nombre en la empresa'];
    }
    
    // Insertar nueva sucursal
    $sql = "INSERT INTO gestion__sucursales 
            (sucursal_nombre, descripcion, sucursal_tipo_id, localidad_id, direccion, telefono, email, empresa_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ssiissssi", $sucursal_nombre, $descripcion, $sucursal_tipo_id, 
                           $localidad_id, $direccion, $telefono, $email, $empresa_idx, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $sucursal_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'sucursal_id' => $sucursal_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la sucursal: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar sucursal existente
function editarSucursal($conexion, $id, $data) {
    $id = intval($id);
    $sucursal_nombre = mysqli_real_escape_string($conexion, trim($data['sucursal_nombre'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $sucursal_tipo_id = intval($data['sucursal_tipo_id'] ?? 0);
    $localidad_id = isset($data['localidad_id']) && $data['localidad_id'] ? intval($data['localidad_id']) : null;
    $direccion = mysqli_real_escape_string($conexion, trim($data['direccion'] ?? ''));
    $telefono = mysqli_real_escape_string($conexion, trim($data['telefono'] ?? ''));
    $email = mysqli_real_escape_string($conexion, trim($data['email'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    if (empty($sucursal_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la sucursal es obligatorio'];
    }
    
    if ($sucursal_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de sucursal válido'];
    }
    
    if (strlen($sucursal_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }
    
    if (strlen($descripcion) > 255) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 255 caracteres'];
    }
    
    if (strlen($direccion) > 255) {
        return ['resultado' => false, 'error' => 'La dirección no puede exceder los 255 caracteres'];
    }
    
    if (strlen($telefono) > 50) {
        return ['resultado' => false, 'error' => 'El teléfono no puede exceder los 50 caracteres'];
    }
    
    if (strlen($email) > 100) {
        return ['resultado' => false, 'error' => 'El email no puede exceder los 100 caracteres'];
    }
    
    // Validar formato de email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['resultado' => false, 'error' => 'El formato del email es inválido'];
    }
    
    // Verificar que la sucursal pertenezca a la empresa
    $sql_check = "SELECT sucursal_id FROM gestion__sucursales 
                  WHERE sucursal_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    }
    
    // Verificar que el tipo de sucursal pertenezca a la empresa
    $sql_check_tipo = "SELECT sucursal_tipo_id FROM gestion__sucursales_tipos 
                       WHERE sucursal_tipo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_tipo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $sucursal_tipo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El tipo de sucursal seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar que la localidad exista si se proporciona
    if ($localidad_id) {
        $sql_check_localidad = "SELECT localidad_id FROM conf__localidades WHERE localidad_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check_localidad);
        if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
        
        mysqli_stmt_bind_param($stmt, "i", $localidad_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            return ['resultado' => false, 'error' => 'La localidad seleccionada no existe'];
        }
    }
    
    // Verificar duplicados (mismo nombre + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__sucursales 
                      WHERE empresa_id = ? 
                      AND LOWER(sucursal_nombre) = LOWER(?) 
                      AND sucursal_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $sucursal_nombre_lower = strtolower($sucursal_nombre);
    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $sucursal_nombre_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra sucursal con este nombre en la empresa'];
    }
    
    // Actualizar sucursal
    $sql = "UPDATE gestion__sucursales 
            SET sucursal_nombre = ?, descripcion = ?, sucursal_tipo_id = ?, 
                localidad_id = ?, direccion = ?, telefono = ?, email = ? 
            WHERE sucursal_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ssiisssii", $sucursal_nombre, $descripcion, $sucursal_tipo_id, 
                           $localidad_id, $direccion, $telefono, $email, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la sucursal: ' . mysqli_error($conexion)];
    }
}
//text

// ✅ Obtener sucursal específica
function obtenerSucursalPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT s.*, er.estado_registro, er.codigo_estandar
            FROM gestion__sucursales s
            LEFT JOIN conf__estados_registros er ON s.tabla_estado_registro_id = er.estado_registro_id
            WHERE s.sucursal_id = ? AND s.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursal = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $sucursal;
}
?>