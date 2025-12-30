<?php
require_once "conexion.php";

function obtenerProductos($conexion) {
    $sql = "SELECT p.*, 
                   c.producto_categoria_nombre, 
                   u.unidad_nombre as unidad_medida_nombre,
                   u.unidad_abreviatura
            FROM gestion__productos p
            LEFT JOIN gestion__productos_categorias c ON p.producto_categoria_id = c.producto_categoria_id
            LEFT JOIN gestion__unidades_medida u ON p.unidad_medida_id = u.unidad_medida_id
            ORDER BY p.producto_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarProducto($conexion, $data) {
    if (empty($data['producto_codigo']) || empty($data['producto_nombre']) || empty($data['producto_categoria_id'])) {
        return false;
    }
    
    $producto_codigo = mysqli_real_escape_string($conexion, $data['producto_codigo']);
    $producto_nombre = mysqli_real_escape_string($conexion, $data['producto_nombre']);
    $producto_descripcion = mysqli_real_escape_string($conexion, $data['producto_descripcion']);
    $producto_categoria_id = intval($data['producto_categoria_id']);
    $lado = mysqli_real_escape_string($conexion, $data['lado']);
    $material = mysqli_real_escape_string($conexion, $data['material']);
    $color = mysqli_real_escape_string($conexion, $data['color']);
    $peso = !empty($data['peso']) ? floatval($data['peso']) : 'NULL';
    $dimensiones = mysqli_real_escape_string($conexion, $data['dimensiones']);
    $garantia = mysqli_real_escape_string($conexion, $data['garantia']);
    $unidad_medida_id = !empty($data['unidad_medida_id']) ? intval($data['unidad_medida_id']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO gestion__productos 
            (producto_codigo, producto_nombre, producto_descripcion, producto_categoria_id, 
             lado, material, color, peso, dimensiones, garantia, unidad_medida_id, tabla_estado_registro_id) 
            VALUES 
            ('$producto_codigo', '$producto_nombre', '$producto_descripcion', $producto_categoria_id,
             '$lado', '$material', '$color', $peso, '$dimensiones', '$garantia', $unidad_medida_id, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarProducto($conexion, $id, $data) {
    if (empty($data['producto_codigo']) || empty($data['producto_nombre']) || empty($data['producto_categoria_id'])) {
        return false;
    }
    
    $id = intval($id);
    $producto_codigo = mysqli_real_escape_string($conexion, $data['producto_codigo']);
    $producto_nombre = mysqli_real_escape_string($conexion, $data['producto_nombre']);
    $producto_descripcion = mysqli_real_escape_string($conexion, $data['producto_descripcion']);
    $producto_categoria_id = intval($data['producto_categoria_id']);
    $lado = mysqli_real_escape_string($conexion, $data['lado']);
    $material = mysqli_real_escape_string($conexion, $data['material']);
    $color = mysqli_real_escape_string($conexion, $data['color']);
    $peso = !empty($data['peso']) ? floatval($data['peso']) : 'NULL';
    $dimensiones = mysqli_real_escape_string($conexion, $data['dimensiones']);
    $garantia = mysqli_real_escape_string($conexion, $data['garantia']);
    $unidad_medida_id = !empty($data['unidad_medida_id']) ? intval($data['unidad_medida_id']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE gestion__productos SET
            producto_codigo = '$producto_codigo',
            producto_nombre = '$producto_nombre',
            producto_descripcion = '$producto_descripcion',
            producto_categoria_id = $producto_categoria_id,
            lado = '$lado',
            material = '$material',
            color = '$color',
            peso = $peso,
            dimensiones = '$dimensiones',
            garantia = '$garantia',
            unidad_medida_id = $unidad_medida_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE producto_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoProducto($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__productos SET tabla_estado_registro_id = $nuevo_estado WHERE producto_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarProducto($conexion, $id) {
    $id = intval($id);    
    $sql = "DELETE FROM gestion__productos WHERE producto_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerProductoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__productos WHERE producto_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function obtenerCategoriasProductos($conexion) {
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id 
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
            'id' => $fila['producto_categoria_id'],
            'nombre' => $prefijo . $fila['producto_categoria_nombre']
        ];
    }
    
    return $categorias;
}
function obtenerUnidadesMedida($conexion) {
    $sql = "SELECT unidad_medida_id, unidad_nombre, unidad_abreviatura 
            FROM gestion__unidades_medida 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY unidad_nombre";
    $res = mysqli_query($conexion, $sql);
    $unidades = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $unidades[$fila['unidad_medida_id']] = $fila['unidad_nombre'] . ' (' . $fila['unidad_abreviatura'] . ')';
    }
    return $unidades;
}


function agregarCompatibilidad($conexion, $data) {
    $producto_id = intval($data['producto_id']);
    $marca_id = intval($data['marca_id']);
    $modelo_id = intval($data['modelo_id']);
    $submodelo_id = !empty($data['submodelo_id']) ? intval($data['submodelo_id']) : 'NULL';
    $anio_desde = intval($data['anio_desde']);
    $anio_hasta = !empty($data['anio_hasta']) ? intval($data['anio_hasta']) : 'NULL';
    
    $sql = "INSERT INTO gestion__productos_compatibilidad 
            (producto_id, marca_id, modelo_id, submodelo_id, anio_desde, anio_hasta, tabla_estado_registro_id) 
            VALUES 
            ($producto_id, $marca_id, $modelo_id, $submodelo_id, $anio_desde, $anio_hasta, 1)";
    
    return mysqli_query($conexion, $sql);
}

function eliminarCompatibilidad($conexion, $compatibilidad_id) {
    $compatibilidad_id = intval($compatibilidad_id);
    $sql = "DELETE FROM gestion__productos_compatibilidad WHERE compatibilidad_id = $compatibilidad_id";
    return mysqli_query($conexion, $sql);
}

function obtenerMarcas($conexion) {
    $sql = "SELECT marca_id, marca_nombre FROM gestion__marcas WHERE tabla_estado_registro_id = 1 ORDER BY marca_nombre";
    $res = mysqli_query($conexion, $sql);
    $marcas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $marcas[$fila['marca_id']] = $fila['marca_nombre'];
    }
    return $marcas;
}

function obtenerModelosPorMarca($conexion, $marca_id) {
    $marca_id = intval($marca_id);
    $sql = "SELECT modelo_id, modelo_nombre FROM gestion__modelos 
            WHERE marca_id = $marca_id AND tabla_estado_registro_id = 1 
            ORDER BY modelo_nombre";
    $res = mysqli_query($conexion, $sql);
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $modelos[$fila['modelo_id']] = $fila['modelo_nombre'];
    }
    return $modelos;
}

function obtenerSubmodelosPorModelo($conexion, $modelo_id) {
    $modelo_id = intval($modelo_id);
    $sql = "SELECT submodelo_id, submodelo_nombre FROM gestion__submodelos 
            WHERE modelo_id = $modelo_id AND tabla_estado_registro_id = 1 
            ORDER BY submodelo_nombre";
    $res = mysqli_query($conexion, $sql);
    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $submodelos[$fila['submodelo_id']] = $fila['submodelo_nombre'];
    }
    return $submodelos;
}
// Agregar estas funciones al final de productos_model.php

function obtenerSucursales($conexion) {
    $sql = "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE tabla_estado_registro_id = 1 ORDER BY sucursal_nombre";
    $res = mysqli_query($conexion, $sql);
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $sucursales[$fila['sucursal_id']] = $fila['sucursal_nombre'];
    }
    return $sucursales;
}

function obtenerUbicacionesSucursal($conexion, $sucursal_id) {
    $sucursal_id = intval($sucursal_id);
    $sql = "SELECT sucursal_ubicacion_id, 
                   CONCAT(seccion, ' - ', estanteria, ' - ', estante) as ubicacion_nombre,
                   descripcion
            FROM gestion__sucursales_ubicaciones 
            WHERE sucursal_id = $sucursal_id AND tabla_estado_registro_id = 1 
            ORDER BY seccion ASC, estanteria ASC, estante ASC";
    $res = mysqli_query($conexion, $sql);
    $ubicaciones = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $ubicaciones[$fila['sucursal_ubicacion_id']] = $fila['ubicacion_nombre'] . 
                                                     ($fila['descripcion'] ? ' (' . $fila['descripcion'] . ')' : '');
    }
    return $ubicaciones;
}

function obtenerUbicacionesProducto($conexion, $producto_id) {
    $producto_id = intval($producto_id);
    $sql = "SELECT pu.*, 
                   s.sucursal_nombre,
                   CONCAT(su.seccion, ' - ', su.estanteria, ' - ', su.estante) as ubicacion_nombre,
                   su.descripcion as ubicacion_descripcion
            FROM gestion__productos_ubicaciones pu
            JOIN gestion__sucursales s ON pu.sucursal_id = s.sucursal_id
            JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
            WHERE pu.producto_id = $producto_id AND pu.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, su.seccion, su.estanteria, su.estante";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarUbicacionProducto($conexion, $data) {
    $producto_id = intval($data['producto_id']);
    $sucursal_id = intval($data['sucursal_id']);
    $sucursal_ubicacion_id = intval($data['sucursal_ubicacion_id']);
    $stock_minimo = !empty($data['stock_minimo']) ? intval($data['stock_minimo']) : 0;
    $stock_maximo = !empty($data['stock_maximo']) ? intval($data['stock_maximo']) : 'NULL';
    
    $sql = "INSERT INTO gestion__productos_ubicaciones 
            (producto_id, sucursal_id, sucursal_ubicacion_id, stock_minimo, stock_maximo, tabla_estado_registro_id) 
            VALUES 
            ($producto_id, $sucursal_id, $sucursal_ubicacion_id, $stock_minimo, $stock_maximo, 1)";
    
    return mysqli_query($conexion, $sql);
}

function eliminarUbicacionProducto($conexion, $producto_ubicacion_id) {
    $producto_ubicacion_id = intval($producto_ubicacion_id);
    $sql = "DELETE FROM gestion__productos_ubicaciones WHERE producto_ubicacion_id = $producto_ubicacion_id";
    return mysqli_query($conexion, $sql);
}
function obtenerCompatibilidadProducto($conexion, $producto_id) {
    $producto_id = intval($producto_id);
    $sql = "SELECT pc.*, 
                   m.marca_nombre,
                   mo.modelo_nombre,
                   s.submodelo_nombre
            FROM gestion__productos_compatibilidad pc
            LEFT JOIN gestion__marcas m ON pc.marca_id = m.marca_id
            LEFT JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
            LEFT JOIN gestion__submodelos s ON pc.submodelo_id = s.submodelo_id
            WHERE pc.producto_id = $producto_id AND pc.tabla_estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}