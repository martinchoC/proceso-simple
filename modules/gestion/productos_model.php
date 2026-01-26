<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// ✅ Obtener productos con paginación del servidor
function obtenerProductosPaginados($conexion, $empresa_idx, $pagina_id, $params = [])
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    
    // Parámetros de paginación
    $start = intval($params['start'] ?? 0);
    $length = intval($params['length'] ?? 50);
    $search = trim($params['search'] ?? '');
    $order_column = intval($params['order_column'] ?? 1);
    $order_dir = strtoupper($params['order_dir'] ?? 'ASC');
    $filtro_marca = $params['filtro_marca'] ?? '';
    $filtro_modelo = $params['filtro_modelo'] ?? '';
    $filtro_submodelo = $params['filtro_submodelo'] ?? '';
    $filtro_codigo = $params['filtro_codigo'] ?? '';

    // Validar dirección de orden
    $order_dir = ($order_dir === 'ASC' || $order_dir === 'DESC') ? $order_dir : 'ASC';

    // Mapear columnas DataTables a columnas de base de datos
    $column_mapping = [
        0 => 'p.producto_id',
        1 => 'p.producto_codigo',
        2 => 'p.producto_nombre',
        3 => 'marcas_compatibles',
        4 => 'modelos_compatibles',
        5 => 'submodelos_compatibles',
        6 => 'um.unidad_abreviatura',
        7 => 'er.estado_registro'
    ];

    $order_by = $column_mapping[$order_column] ?? 'p.producto_codigo';

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

    // Construir WHERE clause
    $where_conditions = ["p.empresa_id = ?"];
    $where_params = [$empresa_idx];
    $where_types = "i";

    // Filtro por código
    if (!empty($filtro_codigo)) {
        $where_conditions[] = "p.producto_codigo LIKE ?";
        $where_params[] = '%' . $filtro_codigo . '%';
        $where_types .= "s";
    }

    // Filtros de compatibilidad
    if (!empty($filtro_marca)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                WHERE pc.producto_id = p.producto_id 
                AND pc.empresa_id = p.empresa_id
                AND pc.tabla_estado_registro_id = 1
                AND pc.marca_id = ?)";
        $where_params[] = intval($filtro_marca);
        $where_types .= "i";
    }

    if (!empty($filtro_modelo)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                WHERE pc.producto_id = p.producto_id 
                AND pc.empresa_id = p.empresa_id
                AND pc.tabla_estado_registro_id = 1
                AND pc.modelo_id = ?)";
        $where_params[] = intval($filtro_modelo);
        $where_types .= "i";
    }

    if (!empty($filtro_submodelo)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                WHERE pc.producto_id = p.producto_id 
                AND pc.empresa_id = p.empresa_id
                AND pc.tabla_estado_registro_id = 1
                AND pc.submodelo_id = ?)";
        $where_params[] = intval($filtro_submodelo);
        $where_types .= "i";
    }

    // Filtro de búsqueda global
    if (!empty($search)) {
        $search_conditions = [
            "p.producto_codigo LIKE ?",
            "p.producto_nombre LIKE ?",
            "p.codigo_barras LIKE ?",
            "er.$estado_column LIKE ?",
            "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                    LEFT JOIN gestion__marcas m ON pc.marca_id = m.marca_id
                    WHERE pc.producto_id = p.producto_id 
                    AND pc.empresa_id = p.empresa_id
                    AND pc.tabla_estado_registro_id = 1
                    AND m.marca_nombre LIKE ?)",
            "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                    LEFT JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
                    WHERE pc.producto_id = p.producto_id 
                    AND pc.empresa_id = p.empresa_id
                    AND pc.tabla_estado_registro_id = 1
                    AND mo.modelo_nombre LIKE ?)",
            "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc 
                    LEFT JOIN gestion__submodelos s ON pc.submodelo_id = s.submodelo_id
                    WHERE pc.producto_id = p.producto_id 
                    AND pc.empresa_id = p.empresa_id
                    AND pc.tabla_estado_registro_id = 1
                    AND s.submodelo_nombre LIKE ?)"
        ];
        
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        
        // Agregar parámetros para todas las condiciones de búsqueda
        for ($i = 0; $i < 7; $i++) {
            $where_params[] = '%' . $search . '%';
            $where_types .= "s";
        }
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Consulta para contar total de registros
    $sql_count = "SELECT COUNT(DISTINCT p.producto_id) as total 
                  FROM gestion__productos p
                  LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
                  $where_clause";

    $stmt_count = mysqli_prepare($conexion, $sql_count);
    if ($stmt_count) {
        if (!empty($where_params)) {
            mysqli_stmt_bind_param($stmt_count, $where_types, ...$where_params);
        }
        mysqli_stmt_execute($stmt_count);
        $result_count = mysqli_stmt_get_result($stmt_count);
        $total_row = mysqli_fetch_assoc($result_count);
        $total_records = $total_row['total'];
        mysqli_stmt_close($stmt_count);
    } else {
        $total_records = 0;
    }

    // Consulta principal con información de compatibilidad
    $sql = "SELECT 
            p.*,
            er.$estado_column as estado_registro,
            er.codigo_estandar,
            c.color_clase, c.bg_clase, c.text_clase,
            um.unidad_nombre, um.unidad_abreviatura,
            GROUP_CONCAT(DISTINCT m.marca_nombre ORDER BY m.marca_nombre SEPARATOR ', ') as marcas_compatibles,
            GROUP_CONCAT(DISTINCT mo.modelo_nombre ORDER BY mo.modelo_nombre SEPARATOR ', ') as modelos_compatibles,
            GROUP_CONCAT(DISTINCT s.submodelo_nombre ORDER BY s.submodelo_nombre SEPARATOR ', ') as submodelos_compatibles,
            GROUP_CONCAT(DISTINCT CONCAT(su.sucursal_nombre, ': ', s_ubic.seccion, ' ', s_ubic.estanteria, '-', s_ubic.estante, s_ubic.posicion) 
               ORDER BY su.sucursal_nombre, s_ubic.seccion, s_ubic.estanteria, s_ubic.estante, s_ubic.posicion SEPARATOR '; ') as ubicaciones_info,
            (SELECT ci.imagen_id  -- CAMBIO IMPORTANTE: obtener imagen_id en lugar de ruta
             FROM gestion__productos_imagenes pi
             INNER JOIN conf__imagenes ci ON pi.imagen_id = ci.imagen_id
             WHERE pi.producto_id = p.producto_id 
             AND pi.empresa_id = p.empresa_id
             AND pi.es_principal = 1
             AND pi.tabla_estado_registro_id = 1
             LIMIT 1) as imagen_id_principal  -- CAMBIO IMPORTANTE: cambiar nombre del campo
        FROM gestion__productos p
        LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN conf__colores c ON er.color_id = c.color_id
        LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
        LEFT JOIN gestion__productos_compatibilidad pc ON p.producto_id = pc.producto_id 
            AND p.empresa_id = pc.empresa_id 
            AND pc.tabla_estado_registro_id = 1
        LEFT JOIN gestion__marcas m ON pc.marca_id = m.marca_id
        LEFT JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
        LEFT JOIN gestion__submodelos s ON pc.submodelo_id = s.submodelo_id
        LEFT JOIN gestion__productos_ubicaciones pu ON p.producto_id = pu.producto_id 
            AND pu.tabla_estado_registro_id = 1
        LEFT JOIN gestion__sucursales_ubicaciones s_ubic ON pu.sucursal_ubicacion_id = s_ubic.sucursal_ubicacion_id
        LEFT JOIN gestion__sucursales su ON s_ubic.sucursal_id = su.sucursal_id
        $where_clause
        GROUP BY p.producto_id
        ORDER BY $order_by $order_dir
        LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['total' => 0, 'filtered' => 0, 'productos' => []];
    }

    // Agregar parámetros de paginación
    $where_params[] = $length;
    $where_params[] = $start;
    $where_types .= "ii";

    mysqli_stmt_bind_param($stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
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

        $fila['unidad_medida_info'] = $fila['unidad_medida_id'] ? [
            'unidad_nombre' => $fila['unidad_nombre'] ?? '',
            'unidad_abreviatura' => $fila['unidad_abreviatura'] ?? ''
        ] : null;

        // Limitar texto largo para columnas de compatibilidad
        $fila['marcas_compatibles'] = limitarTexto($fila['marcas_compatibles'] ?? '', 30);
        $fila['modelos_compatibles'] = limitarTexto($fila['modelos_compatibles'] ?? '', 30);
        $fila['submodelos_compatibles'] = limitarTexto($fila['submodelos_compatibles'] ?? '', 30);

        // Agregar URL para la imagen principal si existe
        if (!empty($fila['imagen_id_principal'])) {
            $fila['imagen_url_principal'] = 'get_imagen.php?id=' . $fila['imagen_id_principal'];
        }

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $productos[] = $fila;
    }

    mysqli_stmt_close($stmt);

    return [
        'total' => $total_records,
        'filtered' => $total_records,
        'productos' => $productos
    ];
}

// ✅ Función para limitar texto largo
function limitarTexto($texto, $limite = 30) {
    if (strlen($texto) <= $limite) {
        return $texto;
    }
    return substr($texto, 0, $limite) . '...';
}

// ✅ Obtener todos los estados disponibles
function obtenerEstados($conexion)
{
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

    $sql = "SELECT estado_registro_id, $estado_column as estado_registro, codigo_estandar
            FROM conf__estados_registros
            WHERE tabla_estado_registro_id = 1
            ORDER BY estado_registro_id";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }

    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = $fila;
    }

    return $estados;
}

// ✅ Obtener funciones configuradas para la página desde conf__paginas_funciones
function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1 -- Solo funciones activas
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

// ✅ Obtener información de un estado específico
function obtenerInfoEstado($conexion, $estado_registro_id)
{
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
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $info;
}

// ✅ Obtener botones disponibles según el estado actual (CORREGIDA)
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            // Asegurar que la función esté activa
            if ($funcion['tabla_estado_registro_id'] != 1) {
                continue;
            }
            
            // Determinar si es confirmable (solo si cambia el estado)
            $es_confirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $es_confirmable
            ];
        }
    }

    return $botones;
}

// ✅ Obtener botón "Agregar" específico para la página
function obtenerBotonAgregar($conexion, $pagina_id)
{
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
        'nombre_funcion' => 'Agregar Producto',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos productos
function obtenerEstadoInicial($conexion)
{
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
function ejecutarTransicionEstado($conexion, $producto_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_id = intval($producto_id);
    $pagina_id = intval($pagina_id);

    // Verificar que el producto exista
    $sql_check = "SELECT producto_id, tabla_estado_registro_id 
                  FROM gestion__productos 
                  WHERE producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$producto)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $producto['tabla_estado_registro_id'];

    // Buscar la función correspondiente en conf__paginas_funciones
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion)
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    // Actualizar el estado
    $sql_update = "UPDATE gestion__productos 
                   SET tabla_estado_registro_id = ? 
                   WHERE producto_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $producto_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener tipos de producto
function obtenerTiposProducto($conexion, $empresa_idx)
{
    $sql = "SELECT producto_tipo_id, producto_tipo, producto_tipo_codigo
            FROM gestion__productos_tipos
            WHERE tabla_estado_registro_id = 1
            ORDER BY producto_tipo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $tipos;
}

// ✅ Obtener categorías de productos
function obtenerCategoriasProducto($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id
            FROM gestion__productos_categorias
            WHERE (empresa_id = 0 OR empresa_id = ?)
            AND tabla_estado_registro_id = 1
            ORDER BY producto_categoria_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $categorias = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $categorias[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $categorias;
}

// ✅ Obtener unidades de medida
function obtenerUnidadesMedida($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT unidad_medida_id, unidad_nombre, unidad_abreviatura
            FROM gestion__unidades_medida
            WHERE (empresa_id = 0 OR empresa_id = ?)
            AND tabla_estado_registro_id = 1
            ORDER BY unidad_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $unidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $unidades[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $unidades;
}

// ✅ Agregar nuevo producto (con estado inicial)
function agregarProducto($conexion, $data)
{
    $producto_codigo = mysqli_real_escape_string($conexion, trim($data['producto_codigo'] ?? ''));
    $producto_nombre = mysqli_real_escape_string($conexion, trim($data['producto_nombre'] ?? ''));
    $codigo_barras = mysqli_real_escape_string($conexion, trim($data['codigo_barras'] ?? ''));
    $producto_descripcion = mysqli_real_escape_string($conexion, trim($data['producto_descripcion'] ?? ''));
    $producto_categoria_id = intval($data['producto_categoria_id'] ?? 0);
    $producto_tipo_id = intval($data['producto_tipo_id'] ?? 0);
    $unidad_medida_id = !empty($data['unidad_medida_id']) ? intval($data['unidad_medida_id']) : null;
    $lado = mysqli_real_escape_string($conexion, trim($data['lado'] ?? ''));
    $material = mysqli_real_escape_string($conexion, trim($data['material'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($data['color'] ?? ''));
    $peso = !empty($data['peso']) ? floatval($data['peso']) : null;
    $dimensiones = mysqli_real_escape_string($conexion, trim($data['dimensiones'] ?? ''));
    $garantia = mysqli_real_escape_string($conexion, trim($data['garantia'] ?? ''));
    $empresa_id = intval($data['empresa_id'] ?? 0);

    if (empty($producto_codigo)) {
        return ['resultado' => false, 'error' => 'El código del producto es obligatorio'];
    }

    if (empty($producto_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del producto es obligatorio'];
    }

    if ($producto_tipo_id == 0) {
        return ['resultado' => false, 'error' => 'El tipo de producto es obligatorio'];
    }

    if ($producto_categoria_id == 0) {
        return ['resultado' => false, 'error' => 'La categoría del producto es obligatoria'];
    }

    if (strlen($producto_codigo) > 50) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 50 caracteres'];
    }

    if (strlen($producto_nombre) > 150) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 150 caracteres'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo código)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos 
                  WHERE producto_codigo = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "si", $producto_codigo, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un producto con este código'];
    }

    // Insertar nuevo producto
    $sql = "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, codigo_barras, producto_descripcion, 
             producto_categoria_id, producto_tipo_id, unidad_medida_id, lado, material, color, 
             peso, dimensiones, garantia, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issssiiisssdssi", 
        $empresa_id, $producto_codigo, $producto_nombre, $codigo_barras, $producto_descripcion,
        $producto_categoria_id, $producto_tipo_id, $unidad_medida_id, $lado, $material, $color,
        $peso, $dimensiones, $garantia, $estado_inicial);
    
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $producto_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'producto_id' => $producto_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el producto: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar producto existente
function editarProducto($conexion, $id, $data)
{
    $id = intval($id);
    $producto_codigo = mysqli_real_escape_string($conexion, trim($data['producto_codigo'] ?? ''));
    $producto_nombre = mysqli_real_escape_string($conexion, trim($data['producto_nombre'] ?? ''));
    $codigo_barras = mysqli_real_escape_string($conexion, trim($data['codigo_barras'] ?? ''));
    $producto_descripcion = mysqli_real_escape_string($conexion, trim($data['producto_descripcion'] ?? ''));
    $producto_categoria_id = intval($data['producto_categoria_id'] ?? 0);
    $producto_tipo_id = intval($data['producto_tipo_id'] ?? 0);
    $unidad_medida_id = !empty($data['unidad_medida_id']) ? intval($data['unidad_medida_id']) : null;
    $lado = mysqli_real_escape_string($conexion, trim($data['lado'] ?? ''));
    $material = mysqli_real_escape_string($conexion, trim($data['material'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($data['color'] ?? ''));
    $peso = !empty($data['peso']) ? floatval($data['peso']) : null;
    $dimensiones = mysqli_real_escape_string($conexion, trim($data['dimensiones'] ?? ''));
    $garantia = mysqli_real_escape_string($conexion, trim($data['garantia'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($producto_codigo)) {
        return ['resultado' => false, 'error' => 'El código del producto es obligatorio'];
    }

    if (empty($producto_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del producto es obligatorio'];
    }

    if ($producto_tipo_id == 0) {
        return ['resultado' => false, 'error' => 'El tipo de producto es obligatorio'];
    }

    if ($producto_categoria_id == 0) {
        return ['resultado' => false, 'error' => 'La categoría del producto es obligatoria'];
    }

    if (strlen($producto_codigo) > 50) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 50 caracteres'];
    }

    if (strlen($producto_nombre) > 150) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 150 caracteres'];
    }

    // Verificar que el producto exista
    $sql_check = "SELECT producto_id, empresa_id FROM gestion__productos 
                  WHERE producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    $empresa_id = $row['empresa_id'];

    // Verificar duplicados (mismo código, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__productos 
                      WHERE producto_codigo = ? 
                      AND empresa_id = ?
                      AND producto_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sii", $producto_codigo, $empresa_id, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro producto con este código'];
    }

    // Actualizar producto
    $sql = "UPDATE gestion__productos 
            SET producto_codigo = ?, producto_nombre = ?, codigo_barras = ?, 
                producto_descripcion = ?, producto_categoria_id = ?, producto_tipo_id = ?, 
                unidad_medida_id = ?, lado = ?, material = ?, color = ?, peso = ?, 
                dimensiones = ?, garantia = ?
            WHERE producto_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssssiiisssdssi", 
        $producto_codigo, $producto_nombre, $codigo_barras, $producto_descripcion,
        $producto_categoria_id, $producto_tipo_id, $unidad_medida_id, $lado, $material, $color,
        $peso, $dimensiones, $garantia, $id);
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el producto: ' . mysqli_error($conexion)];
    }
}

// ✅ Obtener producto específico
function obtenerProductoPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

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

    $sql = "SELECT p.*, er.$estado_column as estado_registro, er.codigo_estandar,
                   pt.producto_tipo, pt.producto_tipo_codigo,
                   um.unidad_nombre, um.unidad_abreviatura,
                   pc.producto_categoria_nombre
            FROM gestion__productos p
            LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__productos_tipos pt ON p.producto_tipo_id = pt.producto_tipo_id
            LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.producto_categoria_id
            WHERE p.producto_id = ? AND p.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $producto;
}

// ✅ Obtener marcas para compatibilidad
function obtenerMarcas($conexion, $empresa_idx)
{
    $sql = "SELECT marca_id, marca_nombre
            FROM gestion__marcas
            WHERE tabla_estado_registro_id = 1
            ORDER BY marca_nombre";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }

    $marcas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $marcas[] = $fila;
    }

    return $marcas;
}

// ✅ Obtener modelos por marca
function obtenerModelos($conexion, $empresa_idx, $marca_id)
{
    $marca_id = intval($marca_id);
    
    $sql = "SELECT modelo_id, modelo_nombre
            FROM gestion__modelos
            WHERE marca_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY modelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $marca_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $modelos;
}

// ✅ Obtener submodelos por modelo
function obtenerSubmodelos($conexion, $empresa_idx, $modelo_id)
{
    $modelo_id = intval($modelo_id);
    
    $sql = "SELECT submodelo_id, submodelo_nombre
            FROM gestion__submodelos
            WHERE modelo_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY submodelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $modelo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $submodelos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $submodelos;
}

// ✅ Obtener compatibilidad de un producto
function obtenerCompatibilidad($conexion, $producto_id, $empresa_idx)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT 
                c.compatibilidad_id,
                m.marca_nombre,
                mo.modelo_nombre,
                s.submodelo_nombre,
                c.anio_desde,
                c.anio_hasta,
                c.tabla_estado_registro_id
            FROM gestion__productos_compatibilidad c
            LEFT JOIN gestion__marcas m ON c.marca_id = m.marca_id
            LEFT JOIN gestion__modelos mo ON c.modelo_id = mo.modelo_id
            LEFT JOIN gestion__submodelos s ON c.submodelo_id = s.submodelo_id
            WHERE c.producto_id = ?
            AND c.tabla_estado_registro_id = 1
            ORDER BY m.marca_nombre, mo.modelo_nombre, s.submodelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $compatibilidad = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Obtener información del estado
        $info_estado = obtenerInfoEstado($conexion, $fila['tabla_estado_registro_id']);
        $fila['estado_info'] = $info_estado;
        $compatibilidad[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $compatibilidad;
}

// ✅ Obtener compatibilidad por ID
function obtenerCompatibilidadPorId($conexion, $compatibilidad_id, $empresa_idx)
{
    $compatibilidad_id = intval($compatibilidad_id);
    
    $sql = "SELECT 
                c.*,
                m.marca_nombre,
                mo.modelo_nombre,
                s.submodelo_nombre
            FROM gestion__productos_compatibilidad c
            LEFT JOIN gestion__marcas m ON c.marca_id = m.marca_id
            LEFT JOIN gestion__modelos mo ON c.modelo_id = mo.modelo_id
            LEFT JOIN gestion__submodelos s ON c.submodelo_id = s.submodelo_id
            WHERE c.compatibilidad_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $compatibilidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $compatibilidad = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $compatibilidad;
}

// ✅ Agregar compatibilidad
function agregarCompatibilidad($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $marca_id = intval($data['marca_id'] ?? 0);
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $submodelo_id = !empty($data['submodelo_id']) ? intval($data['submodelo_id']) : null;
    $anio_desde = intval($data['anio_desde'] ?? 2000);
    $anio_hasta = !empty($data['anio_hasta']) ? intval($data['anio_hasta']) : null;
    $empresa_id = intval($data['empresa_id'] ?? 0);

    if ($producto_id == 0) {
        return ['resultado' => false, 'error' => 'Producto no válido'];
    }

    if ($marca_id == 0) {
        return ['resultado' => false, 'error' => 'Marca no válida'];
    }

    if ($modelo_id == 0) {
        return ['resultado' => false, 'error' => 'Modelo no válido'];
    }

    // Verificar si ya existe esta compatibilidad
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_compatibilidad 
                  WHERE producto_id = ? 
                  AND marca_id = ? 
                  AND modelo_id = ? 
                  AND (submodelo_id = ? OR (submodelo_id IS NULL AND ? IS NULL))
                  AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "iiiiii", $producto_id, $marca_id, $modelo_id, $submodelo_id, $submodelo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Esta compatibilidad ya existe'];
    }

    // Insertar nueva compatibilidad
    $sql = "INSERT INTO gestion__productos_compatibilidad 
            (empresa_id, producto_id, marca_id, modelo_id, submodelo_id, anio_desde, anio_hasta, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "iiiiiii", 
        $empresa_id, $producto_id, $marca_id, $modelo_id, $submodelo_id, $anio_desde, $anio_hasta);
    
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $compatibilidad_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'compatibilidad_id' => $compatibilidad_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la compatibilidad: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar compatibilidad
function editarCompatibilidad($conexion, $compatibilidad_id, $data, $empresa_idx)
{
    $compatibilidad_id = intval($compatibilidad_id);
    $marca_id = intval($data['marca_id'] ?? 0);
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $submodelo_id = !empty($data['submodelo_id']) ? intval($data['submodelo_id']) : null;
    $anio_desde = intval($data['anio_desde'] ?? 2000);
    $anio_hasta = !empty($data['anio_hasta']) ? intval($data['anio_hasta']) : null;

    if ($marca_id == 0) {
        return ['resultado' => false, 'error' => 'Marca no válida'];
    }

    if ($modelo_id == 0) {
        return ['resultado' => false, 'error' => 'Modelo no válido'];
    }

    // Verificar que la compatibilidad exista y pertenezca a la empresa
    $sql_check = "SELECT compatibilidad_id FROM gestion__productos_compatibilidad 
                  WHERE compatibilidad_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "ii", $compatibilidad_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $compatibilidad = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$compatibilidad) {
        return ['resultado' => false, 'error' => 'Compatibilidad no encontrada'];
    }

    // Actualizar compatibilidad
    $sql = "UPDATE gestion__productos_compatibilidad 
            SET marca_id = ?, modelo_id = ?, submodelo_id = ?, anio_desde = ?, anio_hasta = ?
            WHERE compatibilidad_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "iiiiiii", 
        $marca_id, $modelo_id, $submodelo_id, $anio_desde, $anio_hasta, $compatibilidad_id, $empresa_idx);
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la compatibilidad: ' . mysqli_error($conexion)];
    }
}

// ✅ Eliminar compatibilidad (cambiar estado a inactivo)
function eliminarCompatibilidad($conexion, $compatibilidad_id, $empresa_idx)
{
    $compatibilidad_id = intval($compatibilidad_id);
    
    $sql = "UPDATE gestion__productos_compatibilidad 
            SET tabla_estado_registro_id = 2 -- Cambiar a estado inactivo
            WHERE compatibilidad_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "ii", $compatibilidad_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Error al eliminar la compatibilidad'];
    }
}

// ✅ Obtener imágenes de un producto (modificada para manejar BLOB)
function obtenerImagenesProducto($conexion, $producto_id, $empresa_idx)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT 
                pi.*,
                ci.imagen_id,
                ci.imagen_nombre,
                ci.imagen_ruta,
                ci.imagen_tipo,
                ci.imagen_tamanio,
                ci.imagen_data
            FROM gestion__productos_imagenes pi
            INNER JOIN conf__imagenes ci ON pi.imagen_id = ci.imagen_id
            WHERE pi.producto_id = ?
            AND pi.empresa_id = ?
            AND pi.tabla_estado_registro_id = 1
            ORDER BY pi.es_principal DESC, pi.orden ASC, pi.fecha_creacion DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $imagenes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Agregar URL para obtener la imagen
        $fila['imagen_url'] = 'get_imagen.php?id=' . $fila['imagen_id'];
        $imagenes[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $imagenes;
}

// ✅ Obtener imagen por ID (modificada para BLOB)
function obtenerImagenPorId($conexion, $imagen_id, $empresa_idx)
{
    $imagen_id = intval($imagen_id);
    
    $sql = "SELECT 
                pi.*,
                ci.imagen_id,
                ci.imagen_nombre,
                ci.imagen_ruta,
                ci.imagen_tipo,
                ci.imagen_tamanio,
                ci.imagen_data
            FROM gestion__productos_imagenes pi
            INNER JOIN conf__imagenes ci ON pi.imagen_id = ci.imagen_id
            WHERE pi.producto_imagen_id = ?
            AND pi.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $imagen_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $imagen = mysqli_fetch_assoc($result);

    if ($imagen && !empty($imagen['imagen_id'])) {
        // Agregar URL para obtener la imagen
        $imagen['imagen_url'] = 'get_imagen.php?id=' . $imagen['imagen_id'];
    }

    mysqli_stmt_close($stmt);
    return $imagen;
}

// ✅ Eliminar imagen (simplificada ya que no hay archivo físico)
function eliminarImagenProducto($conexion, $producto_imagen_id, $empresa_idx)
{
    $producto_imagen_id = intval($producto_imagen_id);
    
    // Cambiar estado a inactivo
    $sql_update = "UPDATE gestion__productos_imagenes 
                   SET tabla_estado_registro_id = 2 
                   WHERE producto_imagen_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_imagen_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Error al eliminar la imagen'];
    }
}

// ✅ Subir imagen y crear registro (con BLOB) - MODIFICADA
function subirImagenProducto($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $es_principal = intval($data['es_principal'] ?? 0);
    $orden = intval($data['orden'] ?? 0);
    
    // Información del archivo
    $imagen_nombre = mysqli_real_escape_string($conexion, trim($data['imagen_nombre'] ?? ''));
    $imagen_tipo = mysqli_real_escape_string($conexion, trim($data['imagen_tipo'] ?? ''));
    $imagen_tamanio = intval($data['imagen_tamanio'] ?? 0);
    $imagen_data = $data['imagen_data'] ?? null;

    if ($producto_id == 0) {
        return ['resultado' => false, 'error' => 'Producto no válido'];
    }

    if (empty($imagen_nombre) || empty($imagen_data)) {
        return ['resultado' => false, 'error' => 'Información de imagen incompleta'];
    }

    // Si esta imagen será principal, quitar principalidad de otras
    if ($es_principal) {
        $sql_quitar_principal = "UPDATE gestion__productos_imagenes 
                                 SET es_principal = 0 
                                 WHERE producto_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_quitar_principal);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Insertar en conf__imagenes con BLOB
    $sql_imagen = "INSERT INTO conf__imagenes 
                   (imagen_nombre, imagen_ruta, imagen_tipo, imagen_tamanio, imagen_data, tabla_estado_registro_id) 
                   VALUES (?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql_imagen);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error al insertar imagen'];
    }

    // Generar una ruta lógica para referencia
    $ruta_logica = 'productos/' . $producto_id . '/' . time() . '_' . $imagen_nombre;
    
    // Usar bind_param para BLOB
    mysqli_stmt_bind_param($stmt, "sssib", 
        $imagen_nombre, 
        $ruta_logica, 
        $imagen_tipo, 
        $imagen_tamanio, 
        $imagen_data
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if (!$success) {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al guardar imagen: ' . mysqli_error($conexion)];
    }

    $nueva_imagen_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    // Insertar en gestion__productos_imagenes
    $sql_producto_imagen = "INSERT INTO gestion__productos_imagenes 
                           (producto_id, empresa_id, imagen_id, descripcion, es_principal, orden, tabla_estado_registro_id) 
                           VALUES (?, ?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql_producto_imagen);
    if (!$stmt) {
        // Revertir la inserción en conf__imagenes si falla
        $sql_revertir = "DELETE FROM conf__imagenes WHERE imagen_id = ?";
        $stmt_revertir = mysqli_prepare($conexion, $sql_revertir);
        if ($stmt_revertir) {
            mysqli_stmt_bind_param($stmt_revertir, "i", $nueva_imagen_id);
            mysqli_stmt_execute($stmt_revertir);
            mysqli_stmt_close($stmt_revertir);
        }
        return ['resultado' => false, 'error' => 'Error al vincular imagen al producto'];
    }

    mysqli_stmt_bind_param($stmt, "iiisii", 
        $producto_id, $empresa_id, $nueva_imagen_id, $descripcion, $es_principal, $orden
    );
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $producto_imagen_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return [
            'resultado' => true, 
            'producto_imagen_id' => $producto_imagen_id, 
            'imagen_id' => $nueva_imagen_id,
            'imagen_url' => 'get_imagen.php?id=' . $nueva_imagen_id
        ];
    } else {
        mysqli_stmt_close($stmt);
        // Revertir la inserción en conf__imagenes
        $sql_revertir = "DELETE FROM conf__imagenes WHERE imagen_id = ?";
        $stmt_revertir = mysqli_prepare($conexion, $sql_revertir);
        if ($stmt_revertir) {
            mysqli_stmt_bind_param($stmt_revertir, "i", $nueva_imagen_id);
            mysqli_stmt_execute($stmt_revertir);
            mysqli_stmt_close($stmt_revertir);
        }
        return ['resultado' => false, 'error' => 'Error al vincular imagen: ' . mysqli_error($conexion)];
    }
}

// ✅ Actualizar información de imagen
function actualizarImagenProducto($conexion, $producto_imagen_id, $data, $empresa_idx)
{
    $producto_imagen_id = intval($producto_imagen_id);
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $es_principal = intval($data['es_principal'] ?? 0);
    $orden = intval($data['orden'] ?? 0);

    // Verificar que la imagen exista y pertenezca a la empresa
    $sql_check = "SELECT producto_imagen_id, producto_id FROM gestion__productos_imagenes 
                  WHERE producto_imagen_id = ? AND empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "ii", $producto_imagen_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $imagen = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$imagen) {
        return ['resultado' => false, 'error' => 'Imagen no encontrada'];
    }

    $producto_id = $imagen['producto_id'];

    // Si esta imagen será principal, quitar principalidad de otras
    if ($es_principal) {
        $sql_quitar_principal = "UPDATE gestion__productos_imagenes 
                                 SET es_principal = 0 
                                 WHERE producto_id = ? AND empresa_id = ? AND producto_imagen_id != ?";
        $stmt = mysqli_prepare($conexion, $sql_quitar_principal);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $producto_id, $empresa_idx, $producto_imagen_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Actualizar información de la imagen
    $sql = "UPDATE gestion__productos_imagenes 
            SET descripcion = ?, es_principal = ?, orden = ?
            WHERE producto_imagen_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "siiii", $descripcion, $es_principal, $orden, $producto_imagen_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la imagen: ' . mysqli_error($conexion)];
    }
}

// ✅ Obtener ubicaciones de sucursales
function obtenerUbicacionesSucursales($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT 
                su.sucursal_ubicacion_id,
                su.sucursal_id,
                su.seccion,
                su.estanteria,
                su.estante,
                su.posicion,
                su.descripcion,
                s.sucursal_nombre
            FROM gestion__sucursales_ubicaciones su
            LEFT JOIN gestion__sucursales s ON su.sucursal_id = s.sucursal_id
            WHERE (su.empresa_id = 0 OR su.empresa_id = ?)
            AND su.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, su.seccion, su.estanteria, su.estante, su.posicion";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $ubicaciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $ubicaciones[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $ubicaciones;
}

// ✅ Obtener sucursales disponibles
function obtenerSucursales($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT sucursal_id, sucursal_nombre
            FROM gestion__sucursales
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
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

// ✅ Obtener ubicaciones de un producto
function obtenerUbicacionesProducto($conexion, $producto_id, $empresa_idx)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT 
                pu.producto_ubicacion_id,
                pu.sucursal_ubicacion_id,
                su.sucursal_id,
                su.seccion,
                su.estanteria,
                su.estante,
                su.posicion,
                su.descripcion,
                s.sucursal_nombre
            FROM gestion__productos_ubicaciones pu
            INNER JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
            LEFT JOIN gestion__sucursales s ON su.sucursal_id = s.sucursal_id
            WHERE pu.producto_id = ?
            AND pu.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, su.seccion, su.estanteria, su.estante, su.posicion";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $ubicaciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $ubicaciones[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $ubicaciones;
}

// ✅ Obtener ubicación por ID
function obtenerUbicacionPorId($conexion, $producto_ubicacion_id)
{
    $producto_ubicacion_id = intval($producto_ubicacion_id);
    
    $sql = "SELECT 
                pu.*,
                su.sucursal_id,
                su.seccion,
                su.estanteria,
                su.estante,
                su.posicion,
                su.descripcion as ubicacion_descripcion,
                s.sucursal_nombre
            FROM gestion__productos_ubicaciones pu
            INNER JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
            LEFT JOIN gestion__sucursales s ON su.sucursal_id = s.sucursal_id
            WHERE pu.producto_ubicacion_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $producto_ubicacion_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ubicacion = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $ubicacion;
}

// ✅ Agregar ubicación a producto
function agregarUbicacionProducto($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $sucursal_ubicacion_id = intval($data['sucursal_ubicacion_id'] ?? 0);
    
    if ($producto_id == 0) {
        return ['resultado' => false, 'error' => 'Producto no válido'];
    }
    
    if ($sucursal_ubicacion_id == 0) {
        return ['resultado' => false, 'error' => 'Ubicación no válida'];
    }
    
    // Verificar si ya existe esta ubicación para el producto
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_ubicaciones 
                  WHERE producto_id = ? 
                  AND sucursal_ubicacion_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $sucursal_ubicacion_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Esta ubicación ya está asignada al producto'];
    }
    
    // Insertar nueva ubicación
    $sql = "INSERT INTO gestion__productos_ubicaciones 
            (producto_id, sucursal_ubicacion_id, tabla_estado_registro_id) 
            VALUES (?, ?, 1)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $sucursal_ubicacion_id);
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $producto_ubicacion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'producto_ubicacion_id' => $producto_ubicacion_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al asignar ubicación: ' . mysqli_error($conexion)];
    }
}

// ✅ Eliminar ubicación de producto
function eliminarUbicacionProducto($conexion, $producto_ubicacion_id)
{
    $producto_ubicacion_id = intval($producto_ubicacion_id);
    
    // Cambiar estado a inactivo en lugar de eliminar físicamente
    $sql = "UPDATE gestion__productos_ubicaciones 
            SET tabla_estado_registro_id = 2 
            WHERE producto_ubicacion_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $producto_ubicacion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Error al eliminar la ubicación'];
    }
}

// ✅ Crear nueva ubicación de sucursal
function crearUbicacionSucursal($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $seccion = mysqli_real_escape_string($conexion, trim($data['seccion'] ?? ''));
    $estanteria = mysqli_real_escape_string($conexion, trim($data['estanteria'] ?? ''));
    $estante = mysqli_real_escape_string($conexion, trim($data['estante'] ?? ''));
    $posicion = mysqli_real_escape_string($conexion, trim($data['posicion'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    
    // Validaciones
    if ($sucursal_id == 0) {
        return ['resultado' => false, 'error' => 'Seleccione una sucursal'];
    }
    
    if (empty($seccion)) {
        return ['resultado' => false, 'error' => 'La sección es obligatoria'];
    }
    
    if (empty($estanteria)) {
        return ['resultado' => false, 'error' => 'La estantería es obligatoria'];
    }
    
    if (empty($estante)) {
        return ['resultado' => false, 'error' => 'El estante es obligatorio'];
    }
    
    if (empty($posicion)) {
        return ['resultado' => false, 'error' => 'La posición es obligatoria'];
    }
    
    // Verificar si ya existe esta ubicación
    $sql_check = "SELECT COUNT(*) as total FROM gestion__sucursales_ubicaciones 
                  WHERE sucursal_id = ? 
                  AND seccion = ? 
                  AND estanteria = ? 
                  AND estante = ? 
                  AND posicion = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "issss", $sucursal_id, $seccion, $estanteria, $estante, $posicion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Esta ubicación ya existe en la sucursal'];
    }
    
    // Insertar nueva ubicación
    $sql = "INSERT INTO gestion__sucursales_ubicaciones 
            (empresa_id, sucursal_id, seccion, estanteria, estante, posicion, descripcion, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }
    
    mysqli_stmt_bind_param($stmt, "iisssss", 
        $empresa_id, $sucursal_id, $seccion, $estanteria, $estante, $posicion, $descripcion);
    
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $sucursal_ubicacion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'sucursal_ubicacion_id' => $sucursal_ubicacion_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la ubicación: ' . mysqli_error($conexion)];
    }
}
?>