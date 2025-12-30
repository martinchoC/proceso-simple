<?php
require_once "conexion.php";

function obtenerLocalidades($conexion) {
    $sql = "SELECT localidad_id, localidad FROM conf__localidades ORDER BY localidad";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerempresas($conexion) {
    $sql = "SELECT e.*, l.localidad 
            FROM conf__empresas e
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            ORDER BY e.empresa_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarempresa($conexion, $data) {
    // Validar campos obligatorios
    if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
        return false;
    }
    $empresa = mysqli_real_escape_string($conexion, $data['empresa']);
    $documento_tipo_id = mysqli_real_escape_string($conexion, $data['documento_tipo_id']);
    $documento_numero = mysqli_real_escape_string($conexion, $data['documento_numero']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $domicilio = mysqli_real_escape_string($conexion, $data['domicilio']);
    $localidad_id = is_numeric($data['localidad_id']) ? $data['localidad_id'] : 'NULL';
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $base_conf = is_numeric($data['base_conf']) ? $data['base_conf'] : 'NULL';
    
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__empresas (empresa, documento_tipo_id, documento_numero, telefono, domicilio, localidad_id, email, base_conf,  tabla_estado_registro_id) 
    VALUES ('$empresa', '$documento_tipo_id', '$documento_numero', '$telefono', '$domicilio', $localidad_id, '$email', $base_conf,  $tabla_estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarempresa($conexion, $id, $data) {
     // Validar campos obligatorios
    if (empty($data['empresa']) || empty($data['documento_numero']) || empty($data['localidad_id'])) {
        return false;
    }
    $id = intval($id);
    $empresa = mysqli_real_escape_string($conexion, $data['empresa']);
    $documento_tipo_id = mysqli_real_escape_string($conexion, $data['documento_tipo_id']);
    $documento_numero = mysqli_real_escape_string($conexion, $data['documento_numero']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $domicilio = mysqli_real_escape_string($conexion, $data['domicilio']);
    $localidad_id = is_numeric($data['localidad_id']) ? $data['localidad_id'] : 'NULL';
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $base_conf = is_numeric($data['base_conf']) ? $data['base_conf'] : 'NULL';
    
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__empresas SET
        empresa='$empresa',
        documento_tipo_id='$documento_tipo_id',
        documento_numero='$documento_numero',
        telefono='$telefono',
        domicilio='$domicilio',
        localidad_id=$localidad_id,
        email='$email',
        base_conf=$base_conf,
        
        tabla_estado_registro_id=$tabla_estado_registro_id
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
