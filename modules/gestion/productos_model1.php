<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de productos
 * Similar a comprobantes_fiscales_model.php
 */

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

// ✅ Obtener botones disponibles según el estado actual
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
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
        'nombre_funcion' => 'Nuevo Producto',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
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

// ✅ Obtener todos los productos
function obtenerProductos($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    // Verificar estructura de la tabla conf__estados_registros
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

    $sql = "SELECT p.*, 
                   pc.producto_categoria_nombre as categoria_nombre,
                   pt.producto_tipo as tipo_nombre,
                   um.unidad_nombre as unidad_nombre,
                   um.unidad_abreviatura,
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__productos p
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.producto_categoria_id
            LEFT JOIN gestion__productos_tipos pt ON p.producto_tipo_id = pt.producto_tipo_id
            LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
            LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE p.empresa_id = ?
            ORDER BY p.producto_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
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

// ✅ Agregar nuevo producto
function agregarProducto($conexion, $data)
{
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    $producto_codigo = mysqli_real_escape_string($conexion, trim($data['producto_codigo'] ?? ''));
    $producto_nombre = mysqli_real_escape_string($conexion, trim($data['producto_nombre'] ?? ''));
    $codigo_barras = mysqli_real_escape_string($conexion, trim($data['codigo_barras'] ?? ''));
    $producto_descripcion = mysqli_real_escape_string($conexion, trim($data['producto_descripcion'] ?? ''));
    $producto_categoria_id = intval($data['producto_categoria_id'] ?? 0);
    $producto_tipo_id = intval($data['producto_tipo_id'] ?? 1);
    $lado = mysqli_real_escape_string($conexion, trim($data['lado'] ?? ''));
    $material = mysqli_real_escape_string($conexion, trim($data['material'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($data['color'] ?? ''));
    $peso = isset($data['peso']) && $data['peso'] !== '' ? floatval($data['peso']) : 'NULL';
    $dimensiones = mysqli_real_escape_string($conexion, trim($data['dimensiones'] ?? ''));
    $garantia = mysqli_real_escape_string($conexion, trim($data['garantia'] ?? ''));
    $unidad_medida_id = isset($data['unidad_medida_id']) && $data['unidad_medida_id'] ? intval($data['unidad_medida_id']) : 'NULL';

    if (empty($producto_codigo) || empty($producto_nombre) || empty($producto_categoria_id)) {
        return ['resultado' => false, 'error' => 'Los campos código, nombre y categoría son obligatorios'];
    }

    // Verificar duplicados (mismo código)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos 
                  WHERE producto_codigo = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "si", $producto_codigo, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un producto con este código'];
    }

    // Obtener estado inicial
    $estado_inicial = obtenerEstadoInicial($conexion);

    // Insertar nuevo producto
    $sql = "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, codigo_barras, 
             producto_descripcion, producto_categoria_id, producto_tipo_id,
             lado, material, color, peso, dimensiones, garantia, 
             unidad_medida_id, tabla_estado_registro_id) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issssiisssdssii", 
        $empresa_idx, $producto_codigo, $producto_nombre, $codigo_barras,
        $producto_descripcion, $producto_categoria_id, $producto_tipo_id,
        $lado, $material, $color, $peso, $dimensiones, $garantia,
        $unidad_medida_id, $estado_inicial);

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
        return 1; // Estado por defecto
    }

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
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
    $producto_tipo_id = intval($data['producto_tipo_id'] ?? 1);
    $lado = mysqli_real_escape_string($conexion, trim($data['lado'] ?? ''));
    $material = mysqli_real_escape_string($conexion, trim($data['material'] ?? ''));
    $color = mysqli_real_escape_string($conexion, trim($data['color'] ?? ''));
    $peso = isset($data['peso']) && $data['peso'] !== '' ? floatval($data['peso']) : 'NULL';
    $dimensiones = mysqli_real_escape_string($conexion, trim($data['dimensiones'] ?? ''));
    $garantia = mysqli_real_escape_string($conexion, trim($data['garantia'] ?? ''));
    $unidad_medida_id = isset($data['unidad_medida_id']) && $data['unidad_medida_id'] ? intval($data['unidad_medida_id']) : 'NULL';
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($producto_codigo) || empty($producto_nombre) || empty($producto_categoria_id)) {
        return ['resultado' => false, 'error' => 'Los campos código, nombre y categoría son obligatorios'];
    }

    // Verificar que el producto exista
    $sql_check = "SELECT producto_id FROM gestion__productos 
                  WHERE producto_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    // Verificar duplicados (mismo código, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__productos 
                      WHERE producto_codigo = ? 
                      AND producto_id != ? 
                      AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sii", $producto_codigo, $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro producto con este código'];
    }

    // Actualizar producto
    $sql = "UPDATE gestion__productos 
            SET producto_codigo = ?, 
                producto_nombre = ?, 
                codigo_barras = ?,
                producto_descripcion = ?, 
                producto_categoria_id = ?, 
                producto_tipo_id = ?,
                lado = ?, 
                material = ?, 
                color = ?, 
                peso = ?, 
                dimensiones = ?, 
                garantia = ?, 
                unidad_medida_id = ?
            WHERE producto_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    // Construir el array de parámetros
    $params = [
        $producto_codigo, $producto_nombre, $codigo_barras,
        $producto_descripcion, $producto_categoria_id, $producto_tipo_id,
        $lado, $material, $color, $peso, $dimensiones, $garantia,
        $unidad_medida_id, $id, $empresa_idx
    ];

    // Convertir tipos para bind_param
    $types = str_repeat('s', count($params));
    
    // Reemplazar 'NULL' por null para los parámetros
    foreach ($params as $key => $value) {
        if ($value === 'NULL') {
            $params[$key] = null;
        }
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
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

    $sql = "SELECT p.*, 
                   pc.producto_categoria_nombre,
                   pt.producto_tipo,
                   um.unidad_nombre
            FROM gestion__productos p
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.producto_categoria_id
            LEFT JOIN gestion__productos_tipos pt ON p.producto_tipo_id = pt.producto_tipo_id
            LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
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

// ✅ Obtener categorías de productos
function obtenerCategoriasProductos($conexion)
{
    $sql = "SELECT producto_categoria_id as id, 
                   producto_categoria_nombre as nombre,
                   producto_categoria_padre_id
            FROM gestion__productos_categorias 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY COALESCE(producto_categoria_padre_id, producto_categoria_id), 
                     producto_categoria_padre_id IS NULL DESC, 
                     producto_categoria_nombre";

    $res = mysqli_query($conexion, $sql);

    if (!$res) {
        return [];
    }

    $categorias = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $prefijo = $fila['producto_categoria_padre_id'] ? '&nbsp;&nbsp;&nbsp;' : '';
        $categorias[] = [
            'id' => $fila['id'],
            'nombre' => $prefijo . $fila['nombre']
        ];
    }

    return $categorias;
}

// ✅ Obtener unidades de medida
function obtenerUnidadesMedida($conexion)
{
    $sql = "SELECT unidad_medida_id as id, 
                   unidad_nombre as nombre,
                   unidad_abreviatura
            FROM gestion__unidades_medida 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY unidad_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $unidades = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $unidades[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre'] . ($fila['unidad_abreviatura'] ? ' (' . $fila['unidad_abreviatura'] . ')' : '')
        ];
    }
    
    return $unidades;
}

// ✅ Obtener tipos de producto
function obtenerTiposProducto($conexion)
{
    $sql = "SELECT producto_tipo_id as id, 
                   producto_tipo as nombre
            FROM gestion__productos_tipos 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY producto_tipo";
    
    $res = mysqli_query($conexion, $sql);
    $tipos = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $tipos[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    return $tipos;
}

// ✅ Obtener marcas
function obtenerMarcas($conexion)
{
    $sql = "SELECT marca_id as id, marca_nombre as nombre 
            FROM gestion__marcas 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY marca_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $marcas = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $marcas[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    return $marcas;
}

// ✅ Obtener modelos por marca
function obtenerModelosPorMarca($conexion, $marca_id)
{
    $marca_id = intval($marca_id);
    $sql = "SELECT modelo_id as id, modelo_nombre as nombre 
            FROM gestion__modelos 
            WHERE marca_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY modelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $marca_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    mysqli_stmt_close($stmt);
    return $modelos;
}

// ✅ Obtener submodelos por modelo
function obtenerSubmodelosPorModelo($conexion, $modelo_id)
{
    $modelo_id = intval($modelo_id);
    $sql = "SELECT submodelo_id as id, submodelo_nombre as nombre 
            FROM gestion__submodelos 
            WHERE modelo_id = ? AND tabla_estado_registro_id = 1 
            ORDER BY submodelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $modelo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $submodelos[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    mysqli_stmt_close($stmt);
    return $submodelos;
}

// ✅ Obtener compatibilidad del producto
function obtenerCompatibilidadProducto($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT pc.*, 
                   m.marca_nombre,
                   mo.modelo_nombre,
                   s.submodelo_nombre
            FROM gestion__productos_compatibilidad pc
            LEFT JOIN gestion__marcas m ON pc.marca_id = m.marca_id
            LEFT JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
            LEFT JOIN gestion__submodelos s ON pc.submodelo_id = s.submodelo_id
            WHERE pc.producto_id = ? AND pc.tabla_estado_registro_id = 1
            ORDER BY m.marca_nombre, mo.modelo_nombre, s.submodelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar compatibilidad
function agregarCompatibilidad($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $marca_id = intval($data['marca_id'] ?? 0);
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $submodelo_id = isset($data['submodelo_id']) && $data['submodelo_id'] ? intval($data['submodelo_id']) : 'NULL';
    $anio_desde = intval($data['anio_desde'] ?? 0);
    $anio_hasta = isset($data['anio_hasta']) && $data['anio_hasta'] ? intval($data['anio_hasta']) : 'NULL';

    if (empty($producto_id) || empty($marca_id) || empty($anio_desde)) {
        return ['resultado' => false, 'error' => 'Los campos producto, marca y año desde son obligatorios'];
    }

    // Verificar duplicado
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_compatibilidad 
                  WHERE producto_id = ? AND marca_id = ? AND modelo_id = ? 
                  AND (submodelo_id = ? OR (? IS NULL AND submodelo_id IS NULL))";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iiiii", $producto_id, $marca_id, $modelo_id, $submodelo_id, $submodelo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe esta compatibilidad para el producto'];
    }

    $sql = "INSERT INTO gestion__productos_compatibilidad 
            (producto_id, marca_id, modelo_id, submodelo_id, anio_desde, anio_hasta, tabla_estado_registro_id) 
            VALUES 
            (?, ?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iiiidd", $producto_id, $marca_id, $modelo_id, $submodelo_id, $anio_desde, $anio_hasta);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al agregar compatibilidad'];
    }
}

// ✅ Eliminar compatibilidad
function eliminarCompatibilidad($conexion, $compatibilidad_id)
{
    $compatibilidad_id = intval($compatibilidad_id);
    
    $sql = "DELETE FROM gestion__productos_compatibilidad WHERE compatibilidad_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $compatibilidad_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return ['resultado' => $success];
}

// ✅ Obtener sucursales
function obtenerSucursales($conexion)
{
    $sql = "SELECT sucursal_id as id, sucursal_nombre as nombre 
            FROM gestion__sucursales 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $sucursales = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $sucursales[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    return $sucursales;
}

// ✅ Obtener ubicaciones del producto
function obtenerUbicacionesProducto($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT pl.*, 
                   s.sucursal_nombre,
                   l.local_nombre
            FROM gestion__productos_locaciones pl
            LEFT JOIN gestion__sucursales s ON pl.sucursal_id = s.sucursal_id
            LEFT JOIN gestion__locales l ON pl.local_id = l.local_id
            WHERE pl.producto_id = ? AND pl.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, l.local_nombre, pl.seccion, pl.estanteria, pl.estante";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar ubicación
function agregarUbicacion($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $local_id = isset($data['local_id']) && $data['local_id'] ? intval($data['local_id']) : 'NULL';
    $seccion = mysqli_real_escape_string($conexion, trim($data['seccion'] ?? ''));
    $estanteria = mysqli_real_escape_string($conexion, trim($data['estanteria'] ?? ''));
    $estante = mysqli_real_escape_string($conexion, trim($data['estante'] ?? ''));

    if (empty($producto_id) || empty($sucursal_id)) {
        return ['resultado' => false, 'error' => 'Los campos producto y sucursal son obligatorios'];
    }

    // Verificar duplicado
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_locaciones 
                  WHERE producto_id = ? AND sucursal_id = ? 
                  AND (local_id = ? OR (? IS NULL AND local_id IS NULL))
                  AND seccion = ? AND estanteria = ? AND estante = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iiiisss", $producto_id, $sucursal_id, $local_id, $local_id, $seccion, $estanteria, $estante);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe esta ubicación para el producto'];
    }

    $sql = "INSERT INTO gestion__productos_locaciones 
            (producto_id, sucursal_id, local_id, seccion, estanteria, estante, tabla_estado_registro_id) 
            VALUES 
            (?, ?, ?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iiisss", $producto_id, $sucursal_id, $local_id, $seccion, $estanteria, $estante);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al agregar ubicación'];
    }
}

// ✅ Eliminar ubicación
function eliminarUbicacion($conexion, $ubicacion_id)
{
    $ubicacion_id = intval($ubicacion_id);
    
    $sql = "DELETE FROM gestion__productos_locaciones WHERE producto_locacion_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $ubicacion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return ['resultado' => $success];
}

// ✅ Obtener proveedores
function obtenerProveedores($conexion)
{
    $sql = "SELECT proveedor_id as id, proveedor_nombre as nombre 
            FROM gestion__proveedores 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY proveedor_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $proveedores = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $proveedores[] = [
            'id' => $fila['id'],
            'nombre' => $fila['nombre']
        ];
    }
    
    return $proveedores;
}

// ✅ Obtener proveedores del producto
function obtenerProveedoresProducto($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT pp.*, 
                   p.proveedor_nombre
            FROM gestion__productos_proveedores pp
            LEFT JOIN gestion__proveedores p ON pp.proveedor_id = p.proveedor_id
            WHERE pp.producto_id = ? AND pp.tabla_estado_registro_id = 1
            ORDER BY p.proveedor_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar proveedor al producto
function agregarProveedorProducto($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $proveedor_id = intval($data['proveedor_id'] ?? 0);
    $codigo_proveedor = mysqli_real_escape_string($conexion, trim($data['codigo_proveedor'] ?? ''));
    $precio_compra = isset($data['precio_compra']) && $data['precio_compra'] !== '' ? floatval($data['precio_compra']) : 'NULL';

    if (empty($producto_id) || empty($proveedor_id)) {
        return ['resultado' => false, 'error' => 'Los campos producto y proveedor son obligatorios'];
    }

    // Verificar duplicado
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_proveedores 
                  WHERE producto_id = ? AND proveedor_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Este proveedor ya está asociado al producto'];
    }

    $sql = "INSERT INTO gestion__productos_proveedores 
            (producto_id, proveedor_id, codigo_proveedor, precio_compra, tabla_estado_registro_id) 
            VALUES 
            (?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iisd", $producto_id, $proveedor_id, $codigo_proveedor, $precio_compra);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al agregar proveedor'];
    }
}

// ✅ Eliminar proveedor del producto
function eliminarProveedorProducto($conexion, $proveedor_id)
{
    $proveedor_id = intval($proveedor_id);
    
    $sql = "DELETE FROM gestion__productos_proveedores WHERE producto_proveedor_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $proveedor_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return ['resultado' => $success];
}

// ✅ Obtener fotos del producto
function obtenerFotosProducto($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    
    $sql = "SELECT * FROM gestion__productos_imagenes 
            WHERE producto_id = ? AND tabla_estado_registro_id = 1
            ORDER BY es_principal DESC, orden, fecha_creacion DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Agregar URL de la imagen (ajustar según tu estructura de archivos)
        $fila['url'] = 'uploads/productos/' . $fila['nombre_archivo'];
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar foto al producto
function agregarFotoProducto($conexion, $data, $file)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($producto_id)) {
        return ['resultado' => false, 'error' => 'Producto no especificado'];
    }

    // Verificar que el archivo sea una imagen
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['resultado' => false, 'error' => 'Solo se permiten imágenes JPEG, PNG, GIF o WebP'];
    }

    // Verificar tamaño máximo (5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['resultado' => false, 'error' => 'La imagen no debe superar los 5MB'];
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'producto_' . $producto_id . '_' . time() . '_' . uniqid() . '.' . strtolower($extension);
    
    // Ruta donde se guardarán las imágenes (ajustar según tu estructura)
    $upload_dir = __DIR__ . '/../../uploads/productos/';
    
    // Crear directorio si no existe
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $upload_path = $upload_dir . $nombre_archivo;

    // Mover el archivo subido
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['resultado' => false, 'error' => 'Error al guardar la imagen'];
    }

    // Verificar si es la primera imagen (será principal)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_imagenes 
                  WHERE producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $es_principal = ($row['total'] == 0) ? 1 : 0;

    // Insertar en la base de datos
    $sql = "INSERT INTO gestion__productos_imagenes 
            (producto_id, empresa_id, nombre_archivo, descripcion, es_principal, orden, tabla_estado_registro_id) 
            VALUES 
            (?, ?, ?, ?, ?, 0, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        // Eliminar archivo si hay error en la consulta
        unlink($upload_path);
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "iissi", $producto_id, $empresa_idx, $nombre_archivo, $descripcion, $es_principal);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        // Eliminar archivo si hay error al insertar
        unlink($upload_path);
        return ['resultado' => false, 'error' => 'Error al guardar la información de la imagen'];
    }
}

// ✅ Marcar foto como principal
function marcarFotoComoPrincipal($conexion, $foto_id, $producto_id)
{
    $foto_id = intval($foto_id);
    $producto_id = intval($producto_id);

    // Primero, quitar principal de todas las fotos del producto
    $sql_update_all = "UPDATE gestion__productos_imagenes 
                       SET es_principal = 0 
                       WHERE producto_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update_all);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Ahora marcar la foto específica como principal
    $sql_update_one = "UPDATE gestion__productos_imagenes 
                       SET es_principal = 1 
                       WHERE producto_imagen_id = ? AND producto_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update_one);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $foto_id, $producto_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['resultado' => $success];
}

// ✅ Eliminar foto del producto
function eliminarFotoProducto($conexion, $foto_id)
{
    $foto_id = intval($foto_id);

    // Primero obtener información de la foto para eliminar el archivo
    $sql_select = "SELECT nombre_archivo, producto_id, es_principal 
                   FROM gestion__productos_imagenes 
                   WHERE producto_imagen_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_select);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $foto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $foto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$foto) {
        return ['resultado' => false, 'error' => 'Foto no encontrada'];
    }

    // Eliminar el archivo físico
    $upload_dir = __DIR__ . '/../../uploads/productos/';
    $file_path = $upload_dir . $foto['nombre_archivo'];
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Eliminar el registro de la base de datos
    $sql_delete = "DELETE FROM gestion__productos_imagenes WHERE producto_imagen_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_delete);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $foto_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Si la foto eliminada era principal, marcar otra como principal
    if ($success && $foto['es_principal']) {
        $producto_id = $foto['producto_id'];
        
        // Buscar la primera foto disponible para hacerla principal
        $sql_new_principal = "SELECT producto_imagen_id 
                              FROM gestion__productos_imagenes 
                              WHERE producto_id = ? 
                              ORDER BY orden, fecha_creacion DESC 
                              LIMIT 1";
        
        $stmt = mysqli_prepare($conexion, $sql_new_principal);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $producto_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $nueva_principal = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($nueva_principal) {
                marcarFotoComoPrincipal($conexion, $nueva_principal['producto_imagen_id'], $producto_id);
            }
        }
    }

    return ['resultado' => $success];
}
?>