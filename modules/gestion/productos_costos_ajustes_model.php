<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de Ajustes de Costos de Productos
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// Obtener tipos de ajuste
function obtenerTiposAjuste($conexion) {
    $sql = "SELECT producto_costo_ajuste_tipo_id, producto_costo_ajuste_tipo_nombre, descripcion
            FROM gestion__productos_costos_ajustes_tipos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY orden, producto_costo_ajuste_tipo_nombre";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];
    $tipos = [];
    while ($row = mysqli_fetch_assoc($result)) $tipos[] = $row;
    return $tipos;
}

// Obtener tipos de valor
function obtenerTiposValor($conexion) {
    $sql = "SELECT producto_costo_ajuste_valor_tipo_id, producto_costo_ajuste_valor_tipo_nombre, descripcion
            FROM gestion__productos_costos_ajustes_valores_tipos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY orden, producto_costo_ajuste_valor_tipo_nombre";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];
    $tipos = [];
    while ($row = mysqli_fetch_assoc($result)) $tipos[] = $row;
    return $tipos;
}

// Obtener entidades (proveedores)
// Obtener entidades (solo proveedores)
function obtenerEntidades($conexion, $empresa_idx) {
    $sql = "SELECT entidad_id, entidad_nombre 
            FROM gestion__entidades 
            WHERE empresa_id = ? 
            AND es_proveedor = 1
            AND tabla_estado_registro_id = 1
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $entidades = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $entidades[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $entidades;
}

// Obtener listas de costo de proveedor
function obtenerListasCostoProveedor($conexion, $empresa_idx) {
    $sql = "SELECT proveedor_lista_costo_id, lista_nombre 
            FROM gestion__proveedores_listas_costos 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY lista_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $listas = [];
    while ($row = mysqli_fetch_assoc($result)) $listas[] = $row;
    mysqli_stmt_close($stmt);
    return $listas;
}

// Buscar productos
function buscarProductos($conexion, $empresa_idx, $busqueda) {
    $busqueda = '%' . mysqli_real_escape_string($conexion, $busqueda) . '%';
    $sql = "SELECT producto_id, producto_codigo, producto_nombre, producto_precio 
            FROM gestion__productos 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            AND (producto_nombre LIKE ? OR producto_codigo LIKE ?)
            ORDER BY producto_nombre LIMIT 50";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "iss", $empresa_idx, $busqueda, $busqueda);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) $productos[] = $row;
    mysqli_stmt_close($stmt);
    return $productos;
}

// Obtener funciones configuradas para la página
function obtenerFuncionesPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
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
    while ($fila = mysqli_fetch_assoc($result)) $funciones[] = $fila;
    mysqli_stmt_close($stmt);
    return $funciones;
}

// Obtener información de un estado específico
function obtenerInfoEstado($conexion, $estado_registro_id) {
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) $columns[] = $row['Field'];
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

// Obtener botones disponibles según el estado actual
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

// Obtener botón "Agregar" específico para la página
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
        'nombre_funcion' => 'Agregar Ajuste',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// Obtener estado inicial para nuevos registros
function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id FROM conf__estados_registros WHERE valor_estandar IS NOT NULL ORDER BY valor_estandar ASC LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $ajuste_id, $accion_js, $empresa_idx, $pagina_id) {
    $ajuste_id = intval($ajuste_id);
    $pagina_id = intval($pagina_id);
    $sql_check = "SELECT producto_costo_ajuste_id, tabla_estado_registro_id FROM gestion__productos_costos_ajustes WHERE producto_costo_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "i", $ajuste_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ajuste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$ajuste) return ['success' => false, 'error' => 'Registro no encontrado'];
    $estado_actual_id = $ajuste['tabla_estado_registro_id'];
    $sql_funcion = "SELECT pf.* FROM conf__paginas_funciones pf WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.accion_js = ? LIMIT 1";
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
    $sql_update = "UPDATE gestion__productos_costos_ajustes SET tabla_estado_registro_id = ? WHERE producto_costo_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $ajuste_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener detalles de un ajuste
function obtenerDetallesAjuste($conexion, $ajuste_id) {
    $ajuste_id = intval($ajuste_id);
    $sql = "SELECT d.*, p.producto_codigo, p.producto_nombre
            FROM gestion__productos_costos_ajustes_detalles d
            LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
            WHERE d.producto_costo_ajuste_id = ?
            ORDER BY d.producto_costo_ajuste_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $ajuste_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $detalles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $detalles[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $detalles;
}

// Obtener todos los ajustes
function obtenerAjustes($conexion, $empresa_idx, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) $columns[] = $row['Field'];
    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) $estado_column = 'nombre_estado';
        elseif (in_array('descripcion', $columns)) $estado_column = 'descripcion';
    }
    $sql = "SELECT a.*, 
                   er.$estado_column as estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   t.producto_costo_ajuste_tipo_nombre,
                   vt.producto_costo_ajuste_valor_tipo_nombre
            FROM gestion__productos_costos_ajustes a
            LEFT JOIN conf__estados_registros er ON a.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__productos_costos_ajustes_tipos t ON a.producto_costo_ajuste_tipo_id = t.producto_costo_ajuste_tipo_id
            LEFT JOIN gestion__productos_costos_ajustes_valores_tipos vt ON a.producto_costo_ajuste_valor_tipo_id = vt.producto_costo_ajuste_valor_tipo_id
            WHERE a.empresa_id = ?
            ORDER BY a.producto_costo_ajuste_id DESC";
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

// Agregar nuevo ajuste
function agregarAjuste($conexion, $data) {
    $empresa_idx = intval($data['empresa_idx']);
    $ajuste_descripcion = mysqli_real_escape_string($conexion, trim($data['ajuste_descripcion'] ?? ''));
    $producto_costo_ajuste_tipo_id = intval($data['producto_costo_ajuste_tipo_id'] ?? 0);
    $producto_costo_ajuste_valor_tipo_id = !empty($data['producto_costo_ajuste_valor_tipo_id']) ? intval($data['producto_costo_ajuste_valor_tipo_id']) : null;
    $valor_ajuste = !empty($data['valor_ajuste']) ? floatval($data['valor_ajuste']) : null;
    $entidad_id = !empty($data['entidad_id']) ? intval($data['entidad_id']) : null;
    $producto_id = !empty($data['producto_id']) ? intval($data['producto_id']) : null;
    $proveedor_lista_costo_id = !empty($data['proveedor_lista_costo_id']) ? intval($data['proveedor_lista_costo_id']) : null;
    $f_informado = mysqli_real_escape_string($conexion, $data['f_informado'] ?? '');
    $f_vigencia_desde = mysqli_real_escape_string($conexion, $data['f_vigencia_desde'] ?? '');
    $f_vigencia_hasta = !empty($data['f_vigencia_hasta']) ? mysqli_real_escape_string($conexion, $data['f_vigencia_hasta']) : null;
    $requiere_aprobacion = intval($data['requiere_aprobacion'] ?? 1);
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));
    $detalles_json = $data['detalles'] ?? '[]';
    $detalles = json_decode($detalles_json, true);
    if (empty($ajuste_descripcion)) return ['resultado' => false, 'error' => 'La descripción es obligatoria'];
    if ($producto_costo_ajuste_tipo_id <= 0) return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de ajuste'];
    if (empty($f_informado)) return ['resultado' => false, 'error' => 'La fecha informado es obligatoria'];
    if (empty($f_vigencia_desde)) return ['resultado' => false, 'error' => 'La fecha de vigencia desde es obligatoria'];
    $estado_inicial = obtenerEstadoInicial($conexion);
    $sql = "INSERT INTO gestion__productos_costos_ajustes 
            (empresa_id, producto_costo_ajuste_tipo_id, producto_costo_ajuste_valor_tipo_id, 
             ajuste_descripcion, valor_ajuste, entidad_id, producto_id, proveedor_lista_costo_id,
             f_informado, f_vigencia_desde, f_vigencia_hasta, requiere_aprobacion, observaciones, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt, "iiisdsiissssis", 
        $empresa_idx, $producto_costo_ajuste_tipo_id, $producto_costo_ajuste_valor_tipo_id,
        $ajuste_descripcion, $valor_ajuste, $entidad_id, $producto_id, $proveedor_lista_costo_id,
        $f_informado, $f_vigencia_desde, $f_vigencia_hasta, $requiere_aprobacion, $observaciones, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el ajuste: ' . mysqli_error($conexion)];
    }
    $ajuste_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    // Guardar detalles
    foreach ($detalles as $detalle) {
        $producto_id_det = intval($detalle['producto_id']);
        $costo_anterior = floatval($detalle['costo_anterior'] ?? 0);
        $valor_ajuste_det = floatval($detalle['valor_ajuste'] ?? 0);
        $costo_nuevo = floatval($detalle['costo_nuevo'] ?? 0);
        $observaciones_det = mysqli_real_escape_string($conexion, trim($detalle['observaciones'] ?? ''));
        $sql_det = "INSERT INTO gestion__productos_costos_ajustes_detalles 
                    (producto_costo_ajuste_id, empresa_id, producto_id, costo_anterior, valor_ajuste, costo_nuevo, observaciones, tabla_estado_registro_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_det = mysqli_prepare($conexion, $sql_det);
        if ($stmt_det) {
            $estado_det_inicial = obtenerEstadoInicial($conexion);
            mysqli_stmt_bind_param($stmt_det, "iiidddsi", $ajuste_id, $empresa_idx, $producto_id_det, $costo_anterior, $valor_ajuste_det, $costo_nuevo, $observaciones_det, $estado_det_inicial);
            mysqli_stmt_execute($stmt_det);
            mysqli_stmt_close($stmt_det);
        }
    }
    return ['resultado' => true, 'producto_costo_ajuste_id' => $ajuste_id];
}

// Editar ajuste existente
function editarAjuste($conexion, $id, $data) {
    $id = intval($id);
    $ajuste_descripcion = mysqli_real_escape_string($conexion, trim($data['ajuste_descripcion'] ?? ''));
    $producto_costo_ajuste_tipo_id = intval($data['producto_costo_ajuste_tipo_id'] ?? 0);
    $producto_costo_ajuste_valor_tipo_id = !empty($data['producto_costo_ajuste_valor_tipo_id']) ? intval($data['producto_costo_ajuste_valor_tipo_id']) : null;
    $valor_ajuste = !empty($data['valor_ajuste']) ? floatval($data['valor_ajuste']) : null;
    $entidad_id = !empty($data['entidad_id']) ? intval($data['entidad_id']) : null;
    $producto_id = !empty($data['producto_id']) ? intval($data['producto_id']) : null;
    $proveedor_lista_costo_id = !empty($data['proveedor_lista_costo_id']) ? intval($data['proveedor_lista_costo_id']) : null;
    $f_informado = mysqli_real_escape_string($conexion, $data['f_informado'] ?? '');
    $f_vigencia_desde = mysqli_real_escape_string($conexion, $data['f_vigencia_desde'] ?? '');
    $f_vigencia_hasta = !empty($data['f_vigencia_hasta']) ? mysqli_real_escape_string($conexion, $data['f_vigencia_hasta']) : null;
    $requiere_aprobacion = intval($data['requiere_aprobacion'] ?? 1);
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));
    $detalles_json = $data['detalles'] ?? '[]';
    $detalles = json_decode($detalles_json, true);
    if (empty($ajuste_descripcion)) return ['resultado' => false, 'error' => 'La descripción es obligatoria'];
    if ($producto_costo_ajuste_tipo_id <= 0) return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de ajuste'];
    if (empty($f_informado)) return ['resultado' => false, 'error' => 'La fecha informado es obligatoria'];
    if (empty($f_vigencia_desde)) return ['resultado' => false, 'error' => 'La fecha de vigencia desde es obligatoria'];
    $sql = "UPDATE gestion__productos_costos_ajustes 
            SET producto_costo_ajuste_tipo_id = ?, producto_costo_ajuste_valor_tipo_id = ?,
                ajuste_descripcion = ?, valor_ajuste = ?, entidad_id = ?, producto_id = ?, 
                proveedor_lista_costo_id = ?, f_informado = ?, f_vigencia_desde = ?, 
                f_vigencia_hasta = ?, requiere_aprobacion = ?, observaciones = ?
            WHERE producto_costo_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error en la consulta: ' . mysqli_error($conexion)];
    mysqli_stmt_bind_param($stmt, "iisdsiisssssi", 
        $producto_costo_ajuste_tipo_id, $producto_costo_ajuste_valor_tipo_id,
        $ajuste_descripcion, $valor_ajuste, $entidad_id, $producto_id, $proveedor_lista_costo_id,
        $f_informado, $f_vigencia_desde, $f_vigencia_hasta, $requiere_aprobacion, $observaciones, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$success) return ['resultado' => false, 'error' => 'Error al actualizar el ajuste: ' . mysqli_error($conexion)];
    // Eliminar detalles existentes y volver a insertar
    $sql_del = "DELETE FROM gestion__productos_costos_ajustes_detalles WHERE producto_costo_ajuste_id = ?";
    $stmt_del = mysqli_prepare($conexion, $sql_del);
    if ($stmt_del) {
        mysqli_stmt_bind_param($stmt_del, "i", $id);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }
    // Insertar nuevos detalles
    $empresa_idx = intval($data['empresa_idx']);
    foreach ($detalles as $detalle) {
        $producto_id_det = intval($detalle['producto_id']);
        $costo_anterior = floatval($detalle['costo_anterior'] ?? 0);
        $valor_ajuste_det = floatval($detalle['valor_ajuste'] ?? 0);
        $costo_nuevo = floatval($detalle['costo_nuevo'] ?? 0);
        $observaciones_det = mysqli_real_escape_string($conexion, trim($detalle['observaciones'] ?? ''));
        $sql_det = "INSERT INTO gestion__productos_costos_ajustes_detalles 
                    (producto_costo_ajuste_id, empresa_id, producto_id, costo_anterior, valor_ajuste, costo_nuevo, observaciones, tabla_estado_registro_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_det = mysqli_prepare($conexion, $sql_det);
        if ($stmt_det) {
            $estado_det_inicial = obtenerEstadoInicial($conexion);
            mysqli_stmt_bind_param($stmt_det, "iiidddsi", $id, $empresa_idx, $producto_id_det, $costo_anterior, $valor_ajuste_det, $costo_nuevo, $observaciones_det, $estado_det_inicial);
            mysqli_stmt_execute($stmt_det);
            mysqli_stmt_close($stmt_det);
        }
    }
    return ['resultado' => true];
}

// Obtener ajuste específico
function obtenerAjustePorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__productos_costos_ajustes WHERE producto_costo_ajuste_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ajuste = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($ajuste) {
        $ajuste['detalles'] = obtenerDetallesAjuste($conexion, $id);
    }
    return $ajuste;
}
?>