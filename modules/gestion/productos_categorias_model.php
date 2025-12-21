<?php
require_once "conexion.php";

function obtenerCategorias($conexion) {
    // Obtenemos todas las categorías
    $sql = "SELECT c.*, p.producto_categoria_nombre as padre_nombre 
            FROM gestion__productos_categorias c
            LEFT JOIN gestion__productos_categorias p ON c.producto_categoria_padre_id = p.producto_categoria_id
            ORDER BY c.producto_categoria_padre_id, c.producto_categoria_nombre";
    $res = mysqli_query($conexion, $sql);
    
    $categorias = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $categorias[] = $fila;
    }
    
    return $categorias;
}

function obtenerEstructuraJerarquica($conexion) {
    // Obtenemos todas las categorías
    $sql = "SELECT * FROM gestion__productos_categorias ORDER BY producto_categoria_padre_id, producto_categoria_nombre";
    $res = mysqli_query($conexion, $sql);
    
    // Creamos un array indexado por ID
    $todasCategorias = [];
    while ($categoria = mysqli_fetch_assoc($res)) {
        $todasCategorias[$categoria['producto_categoria_id']] = $categoria;
    }
    
    // Construimos la estructura jerárquica
    $arbol = [];
    foreach ($todasCategorias as $id => $categoria) {
        if (empty($categoria['producto_categoria_padre_id'])) {
            // Es una categoría raíz
            $arbol[$id] = $categoria;
            $arbol[$id]['hijos'] = obtenerHijos($todasCategorias, $id);
        }
    }
    
    return $arbol;
}

function obtenerHijos($categorias, $padre_id) {
    $hijos = [];
    foreach ($categorias as $id => $categoria) {
        if ($categoria['producto_categoria_padre_id'] == $padre_id) {
            $hijos[$id] = $categoria;
            $hijos[$id]['hijos'] = obtenerHijos($categorias, $id);
        }
    }
    return $hijos;
}

function obtenerCategoriasPadre($conexion, $excluir_id = null) {
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre 
            FROM gestion__productos_categorias 
            WHERE (producto_categoria_padre_id IS NULL OR producto_categoria_padre_id = 0)";
    
    if ($excluir_id) {
        $excluir_id = intval($excluir_id);
        $sql .= " AND producto_categoria_id != $excluir_id";
    }
    
    $sql .= " ORDER BY producto_categoria_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTodasCategorias($conexion, $excluir_id = null) {
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id 
            FROM gestion__productos_categorias";
    
    if ($excluir_id) {
        $excluir_id = intval($excluir_id);
        $sql .= " WHERE producto_categoria_id != $excluir_id";
    }
    
    $sql .= " ORDER BY producto_categoria_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarCategoria($conexion, $data) {
    if (empty($data['producto_categoria_nombre'])) {
        return false;
    }
    
    $nombre = mysqli_real_escape_string($conexion, $data['producto_categoria_nombre']);
    $padre_id = !empty($data['producto_categoria_padre_id']) ? intval($data['producto_categoria_padre_id']) : 'NULL';

    $sql = "INSERT INTO gestion__productos_categorias 
            (producto_categoria_nombre, producto_categoria_padre_id) 
            VALUES 
            ('$nombre', $padre_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarCategoria($conexion, $id, $data) {
    if (empty($data['producto_categoria_nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $nombre = mysqli_real_escape_string($conexion, $data['producto_categoria_nombre']);
    $padre_id = !empty($data['producto_categoria_padre_id']) ? intval($data['producto_categoria_padre_id']) : 'NULL';

    // Evitar que una categoría sea su propio padre
    if ($padre_id == $id) {
        return false;
    }

    $sql = "UPDATE gestion__productos_categorias SET
            producto_categoria_nombre = '$nombre',
            producto_categoria_padre_id = $padre_id
            WHERE producto_categoria_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarCategoria($conexion, $id) {
    $id = intval($id);
    
    // Verificar si la categoría tiene subcategorías
    $check_sql = "SELECT COUNT(*) as count FROM gestion__productos_categorias WHERE producto_categoria_padre_id = $id";
    $check_res = mysqli_query($conexion, $check_sql);
    $check_data = mysqli_fetch_assoc($check_res);
    
    if ($check_data['count'] > 0) {
        return false; // No se puede eliminar porque tiene subcategorías
    }
    
    $sql = "DELETE FROM gestion__productos_categorias WHERE producto_categoria_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerCategoriaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__productos_categorias WHERE producto_categoria_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}