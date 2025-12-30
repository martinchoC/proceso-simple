<?php
require_once "conexion.php";

function obtenerUbicaciones($conexion) {
    $sql = "SELECT u.*, l.sucursal_nombre
            FROM gestion__sucursales_ubicaciones u
            INNER JOIN gestion__sucursales l ON u.sucursal_id = l.sucursal_id
            ORDER BY u.sucursal_id, u.seccion, u.estanteria, u.estante";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerLocales($conexion) {
    $sql = "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE tabla_estado_registro_id = 1 ORDER BY sucursal_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarUbicacion($conexion, $data) {
    if (empty($data['sucursal_id']) || empty($data['seccion']) || empty($data['estanteria']) || empty($data['estante'])) {
        return false;
    }
    
    $sucursal_id = intval($data['sucursal_id']);
    $seccion = mysqli_real_escape_string($conexion, $data['seccion']);
    $estanteria = mysqli_real_escape_string($conexion, $data['estanteria']);
    $estante = mysqli_real_escape_string($conexion, $data['estante']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "INSERT INTO gestion__sucursales_ubicaciones 
            (sucursal_id, seccion, estanteria, estante, descripcion, tabla_estado_registro_id, usuario_creacion_id) 
            VALUES 
            ($sucursal_id, '$seccion', '$estanteria', '$estante', '$descripcion', $tabla_estado_registro_id, $usuario_creacion_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarUbicacion($conexion, $id, $data) {
    if (empty($data['sucursal_id']) || empty($data['seccion']) || empty($data['estanteria']) || empty($data['estante'])) {
        return false;
    }
    
    $id = intval($id);
    $sucursal_id = intval($data['sucursal_id']);
    $seccion = mysqli_real_escape_string($conexion, $data['seccion']);
    $estanteria = mysqli_real_escape_string($conexion, $data['estanteria']);
    $estante = mysqli_real_escape_string($conexion, $data['estante']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "UPDATE gestion__sucursales_ubicaciones SET
            sucursal_id = $sucursal_id,
            seccion = '$seccion',
            estanteria = '$estanteria',
            estante = '$estante',
            descripcion = '$descripcion',
            tabla_estado_registro_id = $tabla_estado_registro_id,
            usuario_creacion_id = $usuario_creacion_id
            WHERE sucursal_ubicacion_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoUbicacion($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__sucursales_ubicaciones SET tabla_estado_registro_id = $nuevo_estado WHERE sucursal_ubicacion_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarUbicacion($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM gestion__sucursales_ubicaciones WHERE sucursal_ubicacion_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerUbicacionPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__sucursales_ubicaciones WHERE sucursal_ubicacion_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}