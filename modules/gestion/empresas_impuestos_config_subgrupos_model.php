<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// Obtener subgrupos disponibles (excluyendo los ya asignados a esta configuración)
function obtenerSubgruposDisponibles($conexion, $empresa_id, $empresa_impuesto_config_id) {
    $empresa_id = intval($empresa_id);
    $empresa_impuesto_config_id = intval($empresa_impuesto_config_id);
    
    $sql = "SELECT cs.comprobante_subgrupo_id, cs.codigo, cs.comprobante_subgrupo
            FROM gestion__comprobantes_subgrupos cs
            WHERE cs.empresa_id = ? 
            AND cs.tabla_estado_registro_id = 1
            AND cs.comprobante_subgrupo_id NOT IN (
                SELECT eics.comprobante_subgrupo_id 
                FROM gestion__empresas_impuestos_config_subgrupos eics
                WHERE eics.empresa_impuesto_config_id = ?
            )
            ORDER BY cs.orden, cs.comprobante_subgrupo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $empresa_impuesto_config_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $subgrupos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subgrupos[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $subgrupos;
}

// Obtener información de la configuración de impuesto
function obtenerConfiguracionInfo($conexion, $empresa_impuesto_config_id, $empresa_id) {
    $sql = "SELECT it.impuesto_tipo, eic.alicuota, eic.base_calculo
            FROM gestion__empresas_impuestos_config eic
            INNER JOIN gestion__impuestos_tipos it ON eic.impuesto_tipo_id = it.impuesto_tipo_id
            WHERE eic.empresa_impuesto_config_id = ? AND eic.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_impuesto_config_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $config = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($config) {
        $info = $config['impuesto_tipo'] . ' - ' . $config['base_calculo'] . ' ' . number_format($config['alicuota'], 2) . '%';
        return ['success' => true, 'info' => $info];
    }
    return ['success' => false, 'error' => 'Configuración no encontrada'];
}

// Obtener funciones de la página
function obtenerFuncionesPagina($conexion, $pagina_id) {
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
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

// Obtener botones por estado
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
                'es_confirmable' => ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0
            ];
        }
    }
    return $botones;
}

// Obtener botón agregar
function obtenerBotonAgregar($conexion, $pagina_id) {
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'bg_clase' => $funcion['bg_clase'] ?? 'btn-primary'
            ];
        }
    }
    
    return [
        'nombre_funcion' => 'Agregar Subgrupo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'bg_clase' => 'btn-primary'
    ];
}

// Obtener estado inicial
function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id FROM conf__estados_registros WHERE valor_estandar IS NOT NULL ORDER BY valor_estandar ASC LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_id, $pagina_id) {
    $sql_check = "SELECT tabla_estado_registro_id FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_subgrupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $config = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$config) return ['success' => false, 'error' => 'Registro no encontrado'];
    
    $sql_funcion = "SELECT tabla_estado_registro_destino_id FROM conf__paginas_funciones 
                    WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $config['tabla_estado_registro_id'], $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida'];
    
    $estado_destino = $funcion['tabla_estado_registro_destino_id'];
    if ($estado_destino == $config['tabla_estado_registro_id']) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }
    
    $sql_update = "UPDATE gestion__empresas_impuestos_config_subgrupos SET tabla_estado_registro_id = ? WHERE empresa_impuesto_config_subgrupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success ? ['success' => true, 'message' => 'Estado actualizado'] : ['success' => false, 'error' => 'Error al actualizar'];
}

// Obtener todas las configuraciones
function obtenerConfiguracionesSubgrupo($conexion, $empresa_impuesto_config_id, $empresa_id, $pagina_id) {
    $sql = "SELECT eics.*, 
                   er.estado_registro, er.codigo_estandar,
                   c.bg_clase,
                   cs.codigo as subgrupo_codigo, cs.comprobante_subgrupo
            FROM gestion__empresas_impuestos_config_subgrupos eics
            LEFT JOIN gestion__comprobantes_subgrupos cs ON eics.comprobante_subgrupo_id = cs.comprobante_subgrupo_id
            LEFT JOIN conf__estados_registros er ON eics.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE eics.empresa_impuesto_config_id = ?
            ORDER BY eics.empresa_impuesto_config_subgrupo_id";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_impuesto_config_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['estado_info'] = [
            'estado_registro' => $row['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $row['codigo_estandar'] ?? 'DESCONOCIDO',
            'bg_clase' => $row['bg_clase'] ?? 'bg-secondary'
        ];
        $row['subgrupo_info'] = $row['subgrupo_codigo'] ? '[' . $row['subgrupo_codigo'] . '] ' . $row['comprobante_subgrupo'] : $row['comprobante_subgrupo'];
        $row['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $row['tabla_estado_registro_id']);
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

// Agregar nueva configuración
function agregarConfiguracionSubgrupo($conexion, $data) {
    $empresa_impuesto_config_id = intval($data['empresa_impuesto_config_id'] ?? 0);
    $comprobante_subgrupo_id = intval($data['comprobante_subgrupo_id'] ?? 0);
    
    if (empty($empresa_impuesto_config_id)) return ['resultado' => false, 'error' => 'Configuración de impuesto requerida'];
    if (empty($comprobante_subgrupo_id)) return ['resultado' => false, 'error' => 'Subgrupo requerido'];
    
    // Verificar duplicado
    $sql_check = "SELECT empresa_impuesto_config_subgrupo_id FROM gestion__empresas_impuestos_config_subgrupos 
                  WHERE empresa_impuesto_config_id = ? AND comprobante_subgrupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $empresa_impuesto_config_id, $comprobante_subgrupo_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'Ya existe este subgrupo para esta configuración'];
        }
        mysqli_stmt_close($stmt);
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    $sql = "INSERT INTO gestion__empresas_impuestos_config_subgrupos (empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iii", $empresa_impuesto_config_id, $comprobante_subgrupo_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    
    return $success ? ['resultado' => true, 'empresa_impuesto_config_subgrupo_id' => $id] : ['resultado' => false, 'error' => 'Error al crear'];
}

// Editar configuración
function editarConfiguracionSubgrupo($conexion, $id, $data) {
    $id = intval($id);
    $comprobante_subgrupo_id = intval($data['comprobante_subgrupo_id'] ?? 0);
    
    if (empty($comprobante_subgrupo_id)) return ['resultado' => false, 'error' => 'Subgrupo requerido'];
    
    // Obtener empresa_impuesto_config_id
    $sql_get = "SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_subgrupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_get);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existe = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$existe) return ['resultado' => false, 'error' => 'Registro no encontrado'];
    
    // Verificar duplicado excluyendo el actual
    $sql_check = "SELECT empresa_impuesto_config_subgrupo_id FROM gestion__empresas_impuestos_config_subgrupos 
                  WHERE empresa_impuesto_config_id = ? AND comprobante_subgrupo_id = ? AND empresa_impuesto_config_subgrupo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "iii", $existe['empresa_impuesto_config_id'], $comprobante_subgrupo_id, $id);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Ya existe este subgrupo para esta configuración'];
    }
    mysqli_stmt_close($stmt);
    
    $sql = "UPDATE gestion__empresas_impuestos_config_subgrupos SET comprobante_subgrupo_id = ? WHERE empresa_impuesto_config_subgrupo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_subgrupo_id, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $success ? ['resultado' => true] : ['resultado' => false, 'error' => 'Error al actualizar'];
}

// Obtener configuración por ID
function obtenerConfiguracionSubgrupoPorId($conexion, $id, $empresa_id) {
    $sql = "SELECT eics.*, it.impuesto_tipo, eic.alicuota, eic.base_calculo
            FROM gestion__empresas_impuestos_config_subgrupos eics
            INNER JOIN gestion__empresas_impuestos_config eic ON eics.empresa_impuesto_config_id = eic.empresa_impuesto_config_id
            INNER JOIN gestion__impuestos_tipos it ON eic.impuesto_tipo_id = it.impuesto_tipo_id
            WHERE eics.empresa_impuesto_config_subgrupo_id = ? AND eic.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $config = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($config) {
        $config['configuracion_info'] = $config['impuesto_tipo'] . ' - ' . $config['base_calculo'] . ' ' . number_format($config['alicuota'], 2) . '%';
    }
    return $config;
}
// Eliminar operación (cambiar estado a inactivo)
function eliminarOperacion($conexion, $id) {
    $id = intval($id);
    
    if ($id <= 0) {
        return ['success' => false, 'error' => 'ID de operación no válido'];
    }
    
    // Verificar que la operación existe
    $sql_check = "SELECT empresa_impuesto_config_operacion_id FROM gestion__empresas_impuestos_config_operaciones WHERE empresa_impuesto_config_operacion_id = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) == 0) {
            mysqli_stmt_close($stmt_check);
            return ['success' => false, 'error' => 'La operación no existe'];
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // Obtener estado inactivo (generalmente 2)
    $estado_inactivo = 2; // Por defecto
    
    $sql_estado = "SELECT estado_registro_id 
                  FROM conf__estados_registros 
                  WHERE codigo_estandar = 'INACTIVO' OR nombre_estado = 'Inactivo' OR descripcion = 'Inactivo'
                  LIMIT 1";
    $result_estado = mysqli_query($conexion, $sql_estado);
    if ($result_estado && mysqli_num_rows($result_estado) > 0) {
        $row = mysqli_fetch_assoc($result_estado);
        $estado_inactivo = $row['estado_registro_id'];
    }
    
    // Actualizar el estado a inactivo
    $sql = "UPDATE gestion__empresas_impuestos_config_operaciones 
            SET tabla_estado_registro_id = ? 
            WHERE empresa_impuesto_config_operacion_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta: ' . mysqli_error($conexion)];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $estado_inactivo, $id);
    $success = mysqli_stmt_execute($stmt);
    $error = mysqli_error($conexion);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Método de cálculo eliminado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al eliminar: ' . $error];
    }
}
?>