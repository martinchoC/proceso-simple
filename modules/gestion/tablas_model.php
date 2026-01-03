<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerModulos($conexion) {
    $sql = "SELECT * FROM conf__modulos WHERE modulo_id=2 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTiposTabla($conexion) {
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablas($conexion) {
    $sql = "SELECT t.*, m.modulo, tt.tabla_tipo 
            FROM conf__tablas t
            LEFT JOIN conf__modulos m ON t.modulo_id = m.modulo_id
            LEFT JOIN conf__tablas_tipos tt ON t.tabla_tipo_id = tt.tabla_tipo_id
            WHERE m.modulo_id=2 
            ORDER BY t.tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTabla($conexion, $data) {
    if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $tabla_nombre = mysqli_real_escape_string($conexion, $data['tabla_nombre']);
    $tabla_descripcion = mysqli_real_escape_string($conexion, $data['tabla_descripcion']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__tablas 
            (tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_estado_registro_id) 
            VALUES 
            ('$tabla_nombre', '$tabla_descripcion', $modulo_id, $tabla_tipo_id, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTabla($conexion, $id, $data) {
    if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_nombre = mysqli_real_escape_string($conexion, $data['tabla_nombre']);
    $tabla_descripcion = mysqli_real_escape_string($conexion, $data['tabla_descripcion']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__tablas SET
            tabla_nombre = '$tabla_nombre',
            tabla_descripcion = '$tabla_descripcion',
            modulo_id = $modulo_id,
            tabla_tipo_id = $tabla_tipo_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE tabla_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTabla($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas SET tabla_estado_registro_id = $nuevo_estado WHERE tabla_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerTablaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas WHERE tabla_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>