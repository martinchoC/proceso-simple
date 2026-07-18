<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerListasPreciosProductos($conexion, $filtro_lista = '', $filtro_producto = '', $filtro_marca = '', $filtro_modelo = '', $filtro_submodelo = '')
{
    $sql = "SELECT lpp.*, 
                   lp.lista_precio_nombre as lista_nombre,
                   p.producto_codigo, 
                   p.producto_nombre,
                   lpp.precio_final as precio_unitario,
                   lpp.actualizado_en as f_actualizacion
            FROM gestion__listas_precios_productos lpp
            INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
            INNER JOIN gestion__productos p ON lpp.producto_id = p.producto_id
            WHERE lpp.tabla_estado_registro_id = 1";

    $params = [];
    $types = '';

    if (!empty($filtro_lista)) {
        $sql .= " AND lpp.lista_precio_id = ?";
        $params[] = intval($filtro_lista);
        $types .= 'i';
    }

    if (!empty($filtro_producto)) {
        $sql .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)";
        $params[] = '%' . $filtro_producto . '%';
        $params[] = '%' . $filtro_producto . '%';
        $types .= 'ss';
    }

    // Filtros por compatibilidad
    if (!empty($filtro_marca) || !empty($filtro_modelo) || !empty($filtro_submodelo)) {
        $sql .= " AND EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc
                              WHERE pc.producto_id = p.producto_id
                              AND pc.tabla_estado_registro_id = 1";
        if (!empty($filtro_marca)) {
            $sql .= " AND pc.marca_id = ?";
            $params[] = intval($filtro_marca);
            $types .= 'i';
        }
        if (!empty($filtro_modelo)) {
            $sql .= " AND pc.modelo_id = ?";
            $params[] = intval($filtro_modelo);
            $types .= 'i';
        }
        if (!empty($filtro_submodelo)) {
            $sql .= " AND pc.submodelo_id = ?";
            $params[] = intval($filtro_submodelo);
            $types .= 'i';
        }
        $sql .= ")";
    }

    $sql .= " ORDER BY lp.lista_precio_nombre, p.producto_codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } elseif (!$stmt) {
        return [];
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $data;
}

function agregarListaPrecioProducto($conexion, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;
    $empresa_id = 2; // o tomarlo de sesión

    // Verificar duplicado activo
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $producto_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    // Insertar registro manual (es_manual = 1)
    $sql = "INSERT INTO gestion__listas_precios_productos 
            (empresa_id, lista_precio_id, producto_id, precio_final, es_manual, precio_manual, f_desde, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, 1, ?, CURDATE(), 1)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiidd", $empresa_id, $lista_precio_id, $producto_id, $precio_final, $precio_final);
    return mysqli_stmt_execute($stmt);
}

function editarListaPrecioProducto($conexion, $id, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $id = intval($id);
    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;

    // Verificar duplicado excluyendo el actual
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND lista_precio_producto_id != ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $producto_id, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    $sql = "UPDATE gestion__listas_precios_productos 
            SET lista_precio_id = ?, producto_id = ?, precio_final = ?, es_manual = 1, precio_manual = ?
            WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiddi", $lista_precio_id, $producto_id, $precio_final, $precio_final, $id);
    return mysqli_stmt_execute($stmt);
}

function eliminarListaPrecioProducto($conexion, $id)
{
    $id = intval($id);
    // Desactivación lógica
    $sql = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    return mysqli_stmt_execute($stmt);
}

function obtenerListaPrecioProductoPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT *, precio_final as precio_unitario FROM gestion__listas_precios_productos WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function obtenerListasPrecios($conexion)
{
    $sql = "SELECT lista_precio_id, lista_precio_nombre as nombre
            FROM gestion__listas_precios 
            WHERE tabla_estado_registro_id = 1
            ORDER BY lista_precio_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion)
{
    $sql = "SELECT producto_id, producto_codigo, producto_nombre 
            FROM gestion__productos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY producto_codigo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>