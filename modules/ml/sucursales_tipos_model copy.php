<?php
require_once "conexion.php";

function obtenerLocalesTipos($conexion) {
    $sql = "SELECT * FROM gestion__sucursales_tipos ORDER BY nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarLocalTipo($conexion, $data) {
    if (empty($data['nombre'])) {
        return false;
    }
    
    $nombre = mysqli_real_escape_string($conexion, $data['nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "INSERT INTO gestion__sucursales_tipos 
            (nombre, descripcion, estado_registro_id, usuario_creacion_id) 
            VALUES 
            ('$nombre', '$descripcion', $estado_registro_id, $usuario_creacion_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarLocalTipo($conexion, $id, $data) {
    if (empty($data['nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $nombre = mysqli_real_escape_string($conexion, $data['nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "UPDATE gestion__sucursales_tipos SET
            nombre = '$nombre',
            descripcion = '$descripcion',
            estado_registro_id = $estado_registro_id,
            usuario_creacion_id = $usuario_creacion_id
            WHERE sucursal_tipo_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoLocalTipo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__sucursales_tipos SET estado_registro_id = $nuevo_estado WHERE sucursal_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarLocalTipo($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM gestion__sucursales_tipos WHERE sucursal_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerLocalTipoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__sucursales_tipos WHERE sucursal_tipo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}