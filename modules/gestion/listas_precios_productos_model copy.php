<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerPreciosProductos($conexion, $lista_id) {
    $lista_id = intval($lista_id);
    $sql = "SELECT gestion__listas_precios_productos.*, gestion__productos.producto_nombre
            FROM `gestion__listas_precios_productos`
            INNER JOIN `gestion__productos` ON gestion__listas_precios_productos.producto_id = gestion__productos.producto_id
            WHERE gestion__listas_precios_productos.lista_id = $lista_id 
            ORDER BY gestion__productos.producto_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPrecioProducto($conexion, $data) {
    if (empty($data['lista_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
        return false;
    }
    
    $lista_id = intval($data['lista_id']);
    $producto_id = intval($data['producto_id']);
    $precio_unitario = floatval($data['precio_unitario']);

    // Verificar si ya existe el producto en la lista
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios_productos` 
                  WHERE lista_id = $lista_id AND producto_id = $producto_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe > 0) {
        return false; // Ya existe este producto en la lista
    }

    $sql = "INSERT INTO `gestion__listas_precios_productos` 
            (lista_id, producto_id, precio_unitario) 
            VALUES 
            ($lista_id, $producto_id, $precio_unitario)";
    
    return mysqli_query($conexion, $sql);
}

function editarPrecioProducto($conexion, $id, $data) {
    if ($data['precio_unitario'] <= 0) {
        return false;
    }
    
    $id = intval($id);
    $precio_unitario = floatval($data['precio_unitario']);

    // Obtener datos actuales para el historial
    $sql_current = "SELECT * FROM `gestion__listas_precios_productos` WHERE lista_precio_producto_id = $id";
    $res_current = mysqli_query($conexion, $sql_current);
    $current_data = mysqli_fetch_assoc($res_current);

    // Guardar en historial antes de actualizar
    if ($current_data) {
        $sql_historial = "INSERT INTO `gestion__listas_precios_productos_historial` 
                         (lista_precio_producto_id, lista_id, producto_id, precio_unitario) 
                         VALUES 
                         ($id, {$current_data['lista_id']}, {$current_data['producto_id']}, {$current_data['precio_unitario']})";
        mysqli_query($conexion, $sql_historial);
    }

    $sql = "UPDATE `gestion__listas_precios_productos` SET
            precio_unitario = $precio_unitario,
            f_actualizacion = CURRENT_TIMESTAMP
            WHERE lista_precio_producto_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarPrecioProducto($conexion, $id) {
    $id = intval($id);
    
    // Verificar que existe antes de eliminar
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__listas_precios_productos` 
                  WHERE lista_precio_producto_id = $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe == 0) {
        return false; // No existe el registro
    }
    
    $sql = "DELETE FROM `gestion__listas_precios_productos` 
            WHERE lista_precio_producto_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerPrecioProductoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM `gestion__listas_precios_productos` 
            WHERE lista_precio_producto_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function obtenerListasPreciosActivas($conexion) {
    $sql = "SELECT lista_id, nombre, tipo FROM `gestion__listas_precios` 
            WHERE estado = 'activa' 
            ORDER BY nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion) {
    $sql = "SELECT producto_id, producto_nombre, producto_codigo FROM `gestion__productos` 
            WHERE estado_registro_id=1 
            ORDER BY producto_codigo, producto_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerHistorialPrecios($conexion, $lista_id) {
    $lista_id = intval($lista_id);
    $sql = "SELECT h.*, p.producto_nombre as producto_nombre, a.descripcion, a.tipo_ajuste
            FROM `gestion__listas_precios_productos_historial` h
            INNER JOIN `gestion__productos` p ON h.producto_id = p.producto_id
            LEFT JOIN `gestion__listas_precios_ajustes` a ON h.ajuste_id = a.ajuste_id
            WHERE h.lista_id = $lista_id 
            ORDER BY h.f_alta DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function aplicarAjustePrecios($conexion, $data) {
    $lista_id = intval($data['lista_id']);
    $tipo_ajuste = mysqli_real_escape_string($conexion, $data['tipo_ajuste']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $porcentaje = floatval($data['porcentaje']);
    $monto_fijo = floatval($data['monto_fijo']);
    $criterio = mysqli_real_escape_string($conexion, $data['criterio']);
    $usuario_id_alta = intval($data['usuario_id_alta']);
    $ip_origen = mysqli_real_escape_string($conexion, $data['ip_origen']);

    // Insertar ajuste
    $sql_ajuste = "INSERT INTO `gestion__listas_precios_ajustes` 
                  (lista_id, tipo_ajuste, descripcion, porcentaje, monto_fijo, criterio, usuario_id_alta, ip_origen) 
                  VALUES 
                  ($lista_id, '$tipo_ajuste', '$descripcion', $porcentaje, $monto_fijo, '$criterio', $usuario_id_alta, '$ip_origen')";
    
    if (!mysqli_query($conexion, $sql_ajuste)) {
        return false;
    }
    
    $ajuste_id = mysqli_insert_id($conexion);

    // Obtener todos los productos de la lista
    $sql_productos = "SELECT * FROM `gestion__listas_precios_productos` WHERE lista_id = $lista_id";
    $res_productos = mysqli_query($conexion, $sql_productos);
    
    while ($producto = mysqli_fetch_assoc($res_productos)) {
        $nuevo_precio = floatval($producto['precio_unitario']);
        
        // Aplicar ajuste según criterio
        switch ($criterio) {
            case 'aumento':
                if ($porcentaje > 0) {
                    $nuevo_precio = $nuevo_precio * (1 + ($porcentaje / 100));
                }
                if ($monto_fijo > 0) {
                    $nuevo_precio += $monto_fijo;
                }
                break;
                
            case 'reduccion':
                if ($porcentaje > 0) {
                    $nuevo_precio = $nuevo_precio * (1 - ($porcentaje / 100));
                }
                if ($monto_fijo > 0) {
                    $nuevo_precio -= $monto_fijo;
                }
                break;
                
            case 'reemplazo':
                if ($monto_fijo > 0) {
                    $nuevo_precio = $monto_fijo;
                }
                break;
        }
        
        // Guardar en historial antes de actualizar
        $sql_historial = "INSERT INTO `gestion__listas_precios_productos_historial` 
                         (lista_precio_producto_id, lista_id, producto_id, precio_unitario, ajuste_id) 
                         VALUES 
                         ({$producto['lista_precio_producto_id']}, $lista_id, {$producto['producto_id']}, {$producto['precio_unitario']}, $ajuste_id)";
        mysqli_query($conexion, $sql_historial);
        
        // Actualizar precio
        $sql_update = "UPDATE `gestion__listas_precios_productos` 
                      SET precio_unitario = $nuevo_precio, 
                          ajuste_id = $ajuste_id,
                          f_actualizacion = CURRENT_TIMESTAMP
                      WHERE lista_precio_producto_id = {$producto['lista_precio_producto_id']}";
        mysqli_query($conexion, $sql_update);
    }
    
    return true;
}
?>