<?php
require_once "conexion.php";

function obtenerUsuarios($conexion) {
    $sql = "SELECT * FROM conf__usuarios ORDER BY usuario_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarUsuario($conexion, $data) {
    if (empty($data['usuario_nombre']) || empty($data['usuario']) || empty($data['email']) || empty($data['password'])) {
        return false;
    }
    
    $usuario_nombre = mysqli_real_escape_string($conexion, $data['usuario_nombre']);
    $usuario = mysqli_real_escape_string($conexion, $data['usuario']);
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $duracion_sid_minutos = intval($data['duracion_sid_minutos']);

    $sql = "INSERT INTO conf__usuarios 
            (usuario_nombre, usuario, email, password, duracion_sid_minutos) 
            VALUES 
            ('$usuario_nombre', '$usuario', '$email', '$password', $duracion_sid_minutos)";
    
    return mysqli_query($conexion, $sql);
}

function editarUsuario($conexion, $id, $data) {
    if (empty($data['usuario_nombre']) || empty($data['usuario']) || empty($data['email'])) {
        return false;
    }
    
    $id = intval($id);
    $usuario_nombre = mysqli_real_escape_string($conexion, $data['usuario_nombre']);
    $usuario = mysqli_real_escape_string($conexion, $data['usuario']);
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $duracion_sid_minutos = intval($data['duracion_sid_minutos']);
    
    // Construir la consulta SQL
    $sql = "UPDATE conf__usuarios SET
            usuario_nombre = '$usuario_nombre',
            usuario = '$usuario',
            email = '$email',
            duracion_sid_minutos = $duracion_sid_minutos";
    
    // Agregar la contraseña solo si se proporcionó una nueva
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $sql .= ", password = '$password'";
    }
    
    $sql .= " WHERE usuario_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoUsuario($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__usuarios SET tabla_estado_registro_id = $nuevo_estado WHERE usuario_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerUsuarioPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__usuarios WHERE usuario_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}