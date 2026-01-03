<?php
require_once "conexion.php";

/**
 * Modelo para gestión de comprobantes fiscales
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
    // Primero verifiquemos la estructura real de la tabla
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    // Determinar el nombre correcto de la columna
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
        // Si no encontramos una columna adecuada, usar estado_registro_id como fallback
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
        'nombre_funcion' => 'Agregar Comprobante',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos comprobantes fiscales
function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        // Si hay error, usar estado por defecto
        return 1;
    }
    
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $comprobante_fiscal_id, $accion_js, $empresa_idx, $pagina_id) {
    $comprobante_fiscal_id = intval($comprobante_fiscal_id);
    $pagina_id = intval($pagina_id);
    
    // Verificar que el comprobante exista
    $sql_check = "SELECT comprobante_fiscal_id, tabla_estado_registro_id 
                  FROM gestion__comprobantes_fiscales 
                  WHERE comprobante_fiscal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $comprobante_fiscal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comprobante = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$comprobante) return ['success' => false, 'error' => 'Registro no encontrado'];
    
    $estado_actual_id = $comprobante['tabla_estado_registro_id'];
    
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
    $sql_update = "UPDATE gestion__comprobantes_fiscales 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_fiscal_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $comprobante_fiscal_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener todos los comprobantes fiscales - VERSIÓN CORREGIDA
function obtenerComprobantesFiscales($conexion, $empresa_idx, $pagina_id) {
    $pagina_id = intval($pagina_id);
    
    // Primero verifiquemos la estructura de la tabla conf__estados_registros
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    // Determinar el nombre correcto de la columna para el estado
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }
    
    // Construir la consulta SQL con el nombre correcto de columna
    $sql = "SELECT cf.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__comprobantes_fiscales cf
            LEFT JOIN conf__estados_registros er ON cf.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            ORDER BY cf.codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar dark por defecto
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

// ✅ Agregar nuevo comprobante fiscal (con estado inicial)
function agregarComprobanteFiscal($conexion, $data) {
    $codigo = intval($data['codigo'] ?? 0);
    $comprobante_fiscal = mysqli_real_escape_string($conexion, trim($data['comprobante_fiscal'] ?? ''));
    
    if (empty($comprobante_fiscal)) {
        return ['resultado' => false, 'error' => 'El nombre del comprobante es obligatorio'];
    }
    
    if ($codigo < 0 || $codigo > 255) {
        return ['resultado' => false, 'error' => 'El código debe estar entre 0 y 255'];
    }
    
    if (strlen($comprobante_fiscal) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    // Verificar duplicados (mismo código o mismo nombre)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_fiscales 
                  WHERE codigo = ? OR LOWER(comprobante_fiscal) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $comprobante_lower = strtolower($comprobante_fiscal);
    mysqli_stmt_bind_param($stmt, "is", $codigo, $comprobante_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un comprobante con este código o nombre'];
    }
    
    // Insertar nuevo comprobante fiscal
    $sql = "INSERT INTO gestion__comprobantes_fiscales (codigo, comprobante_fiscal, tabla_estado_registro_id) 
            VALUES (?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "isi", $codigo, $comprobante_fiscal, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $comprobante_fiscal_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_fiscal_id' => $comprobante_fiscal_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el comprobante fiscal'];
    }
}

// ✅ Editar comprobante fiscal existente
function editarComprobanteFiscal($conexion, $id, $data) {
    $id = intval($id);
    $codigo = intval($data['codigo'] ?? 0);
    $comprobante_fiscal = mysqli_real_escape_string($conexion, trim($data['comprobante_fiscal'] ?? ''));
    
    if (empty($comprobante_fiscal)) {
        return ['resultado' => false, 'error' => 'El nombre del comprobante es obligatorio'];
    }
    
    if ($codigo < 0 || $codigo > 255) {
        return ['resultado' => false, 'error' => 'El código debe estar entre 0 y 255'];
    }
    
    if (strlen($comprobante_fiscal) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }
    
    // Verificar que el comprobante exista
    $sql_check = "SELECT comprobante_fiscal_id FROM gestion__comprobantes_fiscales 
                  WHERE comprobante_fiscal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }
    
    // Verificar duplicados (mismo código o mismo nombre, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_fiscales 
                      WHERE (codigo = ? OR LOWER(comprobante_fiscal) = LOWER(?)) 
                      AND comprobante_fiscal_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    $comprobante_lower = strtolower($comprobante_fiscal);
    mysqli_stmt_bind_param($stmt, "isi", $codigo, $comprobante_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro comprobante con este código o nombre'];
    }
    
    // Actualizar comprobante fiscal
    $sql = "UPDATE gestion__comprobantes_fiscales 
            SET codigo = ?, comprobante_fiscal = ? 
            WHERE comprobante_fiscal_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "isi", $codigo, $comprobante_fiscal, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el comprobante fiscal'];
    }
}

// ✅ Obtener comprobante fiscal específico
function obtenerComprobanteFiscalPorId($conexion, $id, $empresa_idx) {
    $id = intval($id);
    
    // Primero verifiquemos la estructura de la tabla conf__estados_registros
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    // Determinar el nombre correcto de la columna para el estado
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }
    
    $sql = "SELECT cf.*, er.$estado_column as estado_registro, er.codigo_estandar
            FROM gestion__comprobantes_fiscales cf
            LEFT JOIN conf__estados_registros er ON cf.tabla_estado_registro_id = er.estado_registro_id
            WHERE cf.comprobante_fiscal_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comprobante = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $comprobante;
}
?>