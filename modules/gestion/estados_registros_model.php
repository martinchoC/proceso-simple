<?php
require_once "conexion.php";

function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT er.*, tt.tabla_tipo 
            FROM conf__estados_registros er
            LEFT JOIN conf__tablas_tipos tt ON er.tabla_tipo_id = tt.tabla_tipo_id
            ORDER BY er.orden, er.estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablasTipos($conexion) {
    $sql = "SELECT tabla_tipo_id, tabla_tipo FROM conf__tablas_tipos ORDER BY tabla_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadoRegistroPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__estados_registros WHERE tabla_estado_registro_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function agregarEstadoRegistro($conexion, $data) {
    if (empty($data['estado_registro'])) {
        return false;
    }
    
    $tabla_tipo_id = $data['tabla_tipo_id'] ? intval($data['tabla_tipo_id']) : 'NULL';
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $estado_registro_descripcion = mysqli_real_escape_string($conexion, $data['estado_registro_descripcion'] ?? '');
    $orden = intval($data['orden'] ?? 0);

    $sql = "INSERT INTO conf__estados_registros 
            (tabla_tipo_id, estado_registro, estado_registro_descripcion, orden) 
            VALUES 
            ($tabla_tipo_id, '$estado_registro', '$estado_registro_descripcion', $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarEstadoRegistro($conexion, $id, $data) {
    if (empty($data['estado_registro'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_tipo_id = $data['tabla_tipo_id'] ? intval($data['tabla_tipo_id']) : 'NULL';
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $estado_registro_descripcion = mysqli_real_escape_string($conexion, $data['estado_registro_descripcion'] ?? '');
    $orden = intval($data['orden'] ?? 0);

    $sql = "UPDATE conf__estados_registros SET
            tabla_tipo_id = $tabla_tipo_id,
            estado_registro = '$estado_registro',
            estado_registro_descripcion = '$estado_registro_descripcion',
            orden = $orden
            WHERE tabla_estado_registro_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarEstadoRegistro($conexion, $id) {
    $id = intval($id);
    $sql = "DELETE FROM conf__estados_registros WHERE tabla_estado_registro_id = $id";
    return mysqli_query($conexion, $sql);
}
?>