<?php
require_once __DIR__ . '/../../conexion.php';

/**
 * Modelo para gestión de tipos de comprobantes
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

// ✅ Obtener todos los estados disponibles
function obtenerEstadosRegistro($conexion) {
    $sql = "SELECT estado_registro_id, estado_registro, codigo_estandar 
            FROM conf__estados_registros 
            WHERE tabla_estado_registro_id = 1
            ORDER BY estado_registro";
    
    $result = mysqli_query($conexion, $sql);
    $estados = [];
    
    if ($result) {
        while ($fila = mysqli_fetch_assoc($result)) {
            $estados[] = $fila;
        }
    }
    
    return $estados;
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
    
    // Si no hay configuración, usar valores por defecto
    return [
        'nombre_funcion' => 'Agregar Tipo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos tipos de comprobantes
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
function ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $accion_js, $empresa_idx, $pagina_id) {
    $comprobante_tipo_id = intval($comprobante_tipo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Verificar que el tipo pertenezca a la empresa
    $sql_check = "SELECT ct.comprobante_tipo_id, ct.tabla_estado_registro_id 
                  FROM gestion__comprobantes_tipos ct
                  WHERE ct.comprobante_tipo_id = ? AND ct.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_tipo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$tipo) return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    
    $estado_actual_id = $tipo['tabla_estado_registro_id'];
    
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
    $sql_update = "UPDATE gestion__comprobantes_tipos 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_tipo_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $comprobante_tipo_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener grupos de comprobantes activos
function obtenerGruposActivos($conexion, $empresa_idx) {
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT cg.comprobante_grupo_id, cg.comprobante_grupo
            FROM gestion__comprobantes_grupos cg
            WHERE cg.empresa_id = ? 
            AND cg.tabla_estado_registro_id = 1
            ORDER BY cg.comprobante_grupo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $grupos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $grupos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $grupos;
}

// ✅ Obtener comprobantes fiscales
function obtenerComprobantesFiscales($conexion) {
    $sql = "SELECT cf.comprobante_fiscal_id, cf.codigo, cf.comprobante_fiscal
            FROM gestion__comprobantes_fiscales cf
            WHERE cf.tabla_estado_registro_id = 1
            ORDER BY cf.codigo, cf.comprobante_fiscal";
    
    $result = mysqli_query($conexion, $sql);
    $comprobantes = [];
    
    if ($result) {
        while ($fila = mysqli_fetch_assoc($result)) {
            $comprobantes[] = $fila;
        }
    }
    
    return $comprobantes;
}

// ✅ Obtener todos los tipos de comprobantes con filtros
function obtenerComprobantesTipos($conexion, $empresa_idx, $pagina_id, $filters = []) {
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT ct.*, 
                   cg.comprobante_grupo,
                   cf.comprobante_fiscal, cf.codigo as fiscal_codigo,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__comprobantes_tipos ct
            LEFT JOIN gestion__comprobantes_grupos cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON ct.comprobante_fiscal_id = cf.comprobante_fiscal_id
            LEFT JOIN conf__estados_registros er ON ct.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE ct.empresa_id = ?";
    
    $params = [$empresa_idx];
    $types = "i";
    
    // Aplicar filtros
    if (!empty($filters['grupo'])) {
        $sql .= " AND ct.comprobante_grupo_id = ?";
        $params[] = intval($filters['grupo']);
        $types .= "i";
    }
    
    if (!empty($filters['estado'])) {
        $sql .= " AND ct.tabla_estado_registro_id = ?";
        $params[] = intval($filters['estado']);
        $types .= "i";
    }
    
    if (!empty($filters['busqueda'])) {
        $sql .= " AND (ct.codigo LIKE ? OR ct.comprobante_tipo LIKE ? OR ct.comentario LIKE ?)";
        $search_term = '%' . $filters['busqueda'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    $sql .= " ORDER BY ct.orden ASC, ct.comprobante_tipo ASC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
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
        
        $fila['grupo_info'] = [
            'comprobante_grupo' => $fila['comprobante_grupo'] ?? 'Sin grupo'
        ];
        
        $fila['comprobante_fiscal_info'] = [
            'comprobante_fiscal' => $fila['comprobante_fiscal'] ?? 'Sin comprobante',
            'codigo' => $fila['fiscal_codigo'] ?? null
        ];
        
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar nuevo tipo de comprobante
function agregarComprobanteTipo($conexion, $data) {
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $comprobante_tipo = mysqli_real_escape_string($conexion, trim($data['comprobante_tipo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $letra = mysqli_real_escape_string($conexion, trim($data['letra'] ?? ''));
    $signo = mysqli_real_escape_string($conexion, trim($data['signo'] ?? '+'));
    $orden = intval($data['orden'] ?? 1);
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $comentario = mysqli_real_escape_string($conexion, trim($data['comentario'] ?? ''));
    $estado_registro_id = isset($data['estado_registro_id']) ? intval($data['estado_registro_id']) : obtenerEstadoInicial($conexion);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    // Validaciones básicas
    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }
    
    if (empty($comprobante_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante es obligatorio'];
    }
    
    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un grupo válido'];
    }
    
    if ($comprobante_fiscal_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un comprobante fiscal válido'];
    }
    
    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }
    
    if (strlen($comprobante_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante no puede exceder los 100 caracteres'];
    }
    
    if (strlen($letra) > 1) {
        return ['resultado' => false, 'error' => 'La letra no puede exceder 1 caracter'];
    }
    
    if (!in_array($signo, ['+', '-', '+/-'])) {
        return ['resultado' => false, 'error' => 'El signo debe ser +, - o +/-'];
    }
    
    if ($orden < 1 || $orden > 999) {
        return ['resultado' => false, 'error' => 'El orden debe estar entre 1 y 999'];
    }
    
    if (strlen($comentario) > 255) {
        return ['resultado' => false, 'error' => 'El comentario no puede exceder los 255 caracteres'];
    }
    
    // Verificar que el grupo pertenezca a la empresa
    $sql_check_grupo = "SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos 
                       WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_grupo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_grupo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El grupo seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar que el comprobante fiscal exista
    $sql_check_fiscal = "SELECT comprobante_fiscal_id FROM gestion__comprobantes_fiscales 
                         WHERE comprobante_fiscal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_fiscal);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $comprobante_fiscal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El comprobante fiscal seleccionado no existe'];
    }
    
    // Verificar duplicados (misma empresa + mismo código)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_tipos 
                  WHERE empresa_id = ? AND codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un tipo de comprobante con este código en la empresa'];
    }
    
    // Insertar nuevo tipo de comprobante
    $sql = "INSERT INTO gestion__comprobantes_tipos 
            (empresa_id, comprobante_grupo_id, comprobante_fiscal_id, 
             impacta_stock, impacta_contabilidad, impacta_ctacte,
             comprobante_tipo, codigo, letra, signo, comentario, orden, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iiiiiisssssii", 
        $empresa_idx, $comprobante_grupo_id, $comprobante_fiscal_id,
        $impacta_stock, $impacta_contabilidad, $impacta_ctacte,
        $comprobante_tipo, $codigo, $letra, $signo, $comentario, $orden, $estado_registro_id
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $comprobante_tipo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_tipo_id' => $comprobante_tipo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el tipo de comprobante: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar tipo de comprobante existente
function editarComprobanteTipo($conexion, $id, $data) {
    $id = intval($id);
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $comprobante_tipo = mysqli_real_escape_string($conexion, trim($data['comprobante_tipo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $letra = mysqli_real_escape_string($conexion, trim($data['letra'] ?? ''));
    $signo = mysqli_real_escape_string($conexion, trim($data['signo'] ?? '+'));
    $orden = intval($data['orden'] ?? 1);
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $comentario = mysqli_real_escape_string($conexion, trim($data['comentario'] ?? ''));
    $estado_registro_id = isset($data['estado_registro_id']) ? intval($data['estado_registro_id']) : null;
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    
    // Validaciones básicas
    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }
    
    if (empty($comprobante_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante es obligatorio'];
    }
    
    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un grupo válido'];
    }
    
    if ($comprobante_fiscal_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un comprobante fiscal válido'];
    }
    
    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }
    
    if (strlen($comprobante_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante no puede exceder los 100 caracteres'];
    }
    
    if (strlen($letra) > 1) {
        return ['resultado' => false, 'error' => 'La letra no puede exceder 1 caracter'];
    }
    
    if (!in_array($signo, ['+', '-', '+/-'])) {
        return ['resultado' => false, 'error' => 'El signo debe ser +, - o +/-'];
    }
    
    if ($orden < 1 || $orden > 999) {
        return ['resultado' => false, 'error' => 'El orden debe estar entre 1 y 999'];
    }
    
    if (strlen($comentario) > 255) {
        return ['resultado' => false, 'error' => 'El comentario no puede exceder los 255 caracteres'];
    }
    
    // Verificar que el tipo exista y pertenezca a la empresa
    $sql_check = "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos 
                  WHERE comprobante_tipo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    }
    
    // Verificar que el grupo pertenezca a la empresa
    $sql_check_grupo = "SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos 
                       WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_grupo);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_grupo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El grupo seleccionado no pertenece a esta empresa'];
    }
    
    // Verificar que el comprobante fiscal exista
    $sql_check_fiscal = "SELECT comprobante_fiscal_id FROM gestion__comprobantes_fiscales 
                         WHERE comprobante_fiscal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_fiscal);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $comprobante_fiscal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El comprobante fiscal seleccionado no existe'];
    }
    
    // Verificar duplicados (excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_tipos 
                      WHERE empresa_id = ? AND codigo = ? AND comprobante_tipo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $codigo, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro tipo de comprobante con este código en la empresa'];
    }
    
    // Construir consulta de actualización
    $sql = "UPDATE gestion__comprobantes_tipos 
            SET comprobante_grupo_id = ?, comprobante_fiscal_id = ?, 
                impacta_stock = ?, impacta_contabilidad = ?, impacta_ctacte = ?,
                comprobante_tipo = ?, codigo = ?, letra = ?, signo = ?, 
                comentario = ?, orden = ?";
    
    $params = [$comprobante_grupo_id, $comprobante_fiscal_id,
               $impacta_stock, $impacta_contabilidad, $impacta_ctacte,
               $comprobante_tipo, $codigo, $letra, $signo, $comentario, $orden];
    $types = "iiiiiissssi";
    
    if ($estado_registro_id) {
        $sql .= ", tabla_estado_registro_id = ?";
        $params[] = $estado_registro_id;
        $types .= "i";
    }
    
    $sql .= " WHERE comprobante_tipo_id = ?";
    $params[] = $id;
    $types .= "i";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el tipo de comprobante: ' . mysqli_error($conexion)];
    }
}

// ✅ Obtener tipo de comprobante específico
function obtenerComprobanteTipoPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT ct.*, 
                   cg.comprobante_grupo, cg.comprobante_grupo_id,
                   cf.comprobante_fiscal, cf.comprobante_fiscal_id,
                   er.estado_registro, er.estado_registro_id, 
                   er.codigo_estandar
            FROM gestion__comprobantes_tipos ct
            LEFT JOIN gestion__comprobantes_grupos cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON ct.comprobante_fiscal_id = cf.comprobante_fiscal_id
            LEFT JOIN conf__estados_registros er ON ct.tabla_estado_registro_id = er.estado_registro_id
            WHERE ct.comprobante_tipo_id = ? AND ct.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comprobante_tipo = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $comprobante_tipo;
}
?>