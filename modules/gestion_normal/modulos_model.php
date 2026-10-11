<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;


function obtenerModulos($conexion)
{
    $sql = "SELECT * FROM conf__modulos ORDER BY modulo_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarModulo($conexion, $data)
{
    $modulo = mysqli_real_escape_string($conexion, $data['modulo']);
    $base_datos = mysqli_real_escape_string($conexion, $data['base_datos']);
    $modulo_url = mysqli_real_escape_string($conexion, $data['modulo_url']);
    $email_envio_modulo = mysqli_real_escape_string($conexion, $data['email_envio_modulo']);
    $layout_nombre = mysqli_real_escape_string($conexion, $data['layout_nombre']);
    $usuario_temp = is_numeric($data['usuario_temp']) ? $data['usuario_temp'] : 'NULL';
    $session_temp = mysqli_real_escape_string($conexion, $data['session_temp']);
    $imagen_id = is_numeric($data['imagen_id']) ? $data['imagen_id'] : 'NULL';
    $depende_id = intval($data['depende_id']);
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "INSERT INTO conf__modulos (modulo, base_datos, modulo_url, email_envio_modulo, layout_nombre, usuario_temp, session_temp, imagen_id, depende_id, estado_registro_id) 
    VALUES ('$modulo', '$base_datos', '$modulo_url', '$email_envio_modulo', '$layout_nombre', $usuario_temp, '$session_temp', $imagen_id, $depende_id, $estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarModulo($conexion, $id, $data)
{
    $id = intval($id);
    $modulo = mysqli_real_escape_string($conexion, $data['modulo']);
    $base_datos = mysqli_real_escape_string($conexion, $data['base_datos']);
    $modulo_url = mysqli_real_escape_string($conexion, $data['modulo_url']);
    $email_envio_modulo = mysqli_real_escape_string($conexion, $data['email_envio_modulo']);
    $layout_nombre = mysqli_real_escape_string($conexion, $data['layout_nombre']);
    $usuario_temp = is_numeric($data['usuario_temp']) ? $data['usuario_temp'] : 'NULL';
    $session_temp = mysqli_real_escape_string($conexion, $data['session_temp']);
    $imagen_id = is_numeric($data['imagen_id']) ? $data['imagen_id'] : 'NULL';
    $depende_id = intval($data['depende_id']);
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "UPDATE conf__modulos SET
        modulo='$modulo',
        base_datos='$base_datos',
        modulo_url='$modulo_url',
        email_envio_modulo='$email_envio_modulo',
        layout_nombre='$layout_nombre',
        usuario_temp=$usuario_temp,
        session_temp='$session_temp',
        imagen_id=$imagen_id,
        depende_id=$depende_id,
        estado_registro_id=$estado_registro_id
        WHERE modulo_id=$id";

    return mysqli_query($conexion, $sql);
}

function eliminarModulo($conexion, $id)
{
    $id = intval($id);
    $sql = "DELETE FROM conf__modulos WHERE modulo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerModuloPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__modulos WHERE modulo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
