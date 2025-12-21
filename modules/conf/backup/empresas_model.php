<?php
require_once "conexion.php";


function obtenerempresas($conexion) {
    $sql = "SELECT * FROM conf__empresas ORDER BY empresa_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarempresa($conexion, $data) {
    $empresa = mysqli_real_escape_string($conexion, $data['empresa']);
    $documento_tipo_id = mysqli_real_escape_string($conexion, $data['documento_tipo_id']);
    $documento_numero = mysqli_real_escape_string($conexion, $data['documento_numero']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $domicilio = mysqli_real_escape_string($conexion, $data['domicilio']);
    $localidad_id = is_numeric($data['localidad_id']) ? $data['localidad_id'] : 'NULL';
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $base_conf = is_numeric($data['base_conf']) ? $data['base_conf'] : 'NULL';
    
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "INSERT INTO conf__empresas (empresa, documento_tipo_id, documento_numero, telefono, domicilio, localidad_id, email, base_conf,  estado_registro_id) 
    VALUES ('$empresa', '$documento_tipo_id', '$documento_numero', '$telefono', '$domicilio', $localidad_id, '$email', $base_conf,  $estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarempresa($conexion, $id, $data) {
    $id = intval($id);
    $empresa = mysqli_real_escape_string($conexion, $data['empresa']);
    $documento_tipo_id = mysqli_real_escape_string($conexion, $data['documento_tipo_id']);
    $documento_numero = mysqli_real_escape_string($conexion, $data['documento_numero']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $domicilio = mysqli_real_escape_string($conexion, $data['domicilio']);
    $localidad_id = is_numeric($data['localidad_id']) ? $data['localidad_id'] : 'NULL';
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $base_conf = is_numeric($data['base_conf']) ? $data['base_conf'] : 'NULL';
    
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "UPDATE conf__empresas SET
        empresa='$empresa',
        documento_tipo_id='$documento_tipo_id',
        documento_numero='$documento_numero',
        telefono='$telefono',
        domicilio='$domicilio',
        localidad_id=$localidad_id,
        email='$email',
        base_conf=$base_conf,
        
        estado_registro_id=$estado_registro_id
        WHERE empresa_id=$id";

    return mysqli_query($conexion, $sql);
}

function eliminarempresa($conexion, $id) {
    $id = intval($id);
    $sql = "DELETE FROM conf__empresas WHERE empresa_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerempresaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__empresas WHERE empresa_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
