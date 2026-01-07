<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerListasPreciosProductos($conexion, $filtro_lista = '', $filtro_producto = '')
{
    $sql = "SELECT lpp.*, 
                   lp.nombre as lista_nombre,
                   p.producto_codigo, 
                   p.producto_nombre
            FROM `gestion__listas_precios_productos` lpp
            INNER JOIN `gestion__listas_precios` lp ON lpp.lista_id = lp.lista_id
            INNER JOIN `gestion__productos` p ON lpp.producto_id = p.producto_id
            WHERE 1=1";

    if (!empty($filtro_lista)) {
        $filtro_lista = intval($filtro_lista);
        $sql .= " AND lpp.lista_id = $filtro_lista";
    }

    if (!empty($filtro_producto)) {
        $filtro_producto = mysqli_real_escape_string($conexion, $filtro_producto);
        $sql .= " AND (p.producto_codigo LIKE '%$filtro_producto%' OR p.producto_nombre LIKE '%$filtro_producto%')";
    }

    $sql .= " ORDER BY lp.nombre, p.producto_codigo";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarListaPrecioProducto($conexion, $data)
{
    if (empty($data['lista_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $lista_id = intval($data['lista_id']);
    $producto_id = intval($data['producto_id']);
    $precio_unitario = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : 'NULL';

    // Verificar si ya existe el producto en la lista
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios_productos` 
                  WHERE lista_id = $lista_id AND producto_id = $producto_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];

    if ($existe > 0) {
        return false; // Ya existe este producto en la lista
    }

    $sql = "INSERT INTO `gestion__listas_precios_productos` 
            (lista_id, producto_id, precio_unitario, ajuste_id) 
            VALUES 
            ($lista_id, $producto_id, $precio_unitario, $ajuste_id)";

    return mysqli_query($conexion, $sql);
}

function editarListaPrecioProducto($conexion, $id, $data)
{
    if (empty($data['lista_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $id = intval($id);
    $lista_id = intval($data['lista_id']);
    $producto_id = intval($data['producto_id']);
    $precio_unitario = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : 'NULL';

    // Verificar si ya existe el producto en la lista (excluyendo el registro actual)
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios_productos` 
                  WHERE lista_id = $lista_id 
                  AND producto_id = $producto_id
                  AND lista_precio_producto_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];

    if ($existe > 0) {
        return false; // Ya existe este producto en la lista
    }

    $sql = "UPDATE `gestion__listas_precios_productos` SET
            lista_id = $lista_id,
            producto_id = $producto_id,
            precio_unitario = $precio_unitario,
            ajuste_id = $ajuste_id,
            f_actualizacion = NOW()
            WHERE lista_precio_producto_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarListaPrecioProducto($conexion, $id)
{
    $id = intval($id);

    $sql = "DELETE FROM `gestion__listas_precios_productos` 
            WHERE lista_precio_producto_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerListaPrecioProductoPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM `gestion__listas_precios_productos` 
            WHERE lista_precio_producto_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function obtenerListasPrecios($conexion)
{
    $sql = "SELECT lista_id, nombre 
            FROM `gestion__listas_precios` 
            WHERE estado = 'activa'
            ORDER BY nombre";
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
            FROM `gestion__productos` 
            WHERE estado_registro_id = 1
            ORDER BY producto_codigo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>