<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/auditoria.php';
$conexion = $conn;

function obtenerUsuarios($conexion)
{
    $sql = "SELECT * FROM conf__usuarios ORDER BY usuario_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        unset($fila['password'], $fila['password_temporal']); // nunca enviar hashes al navegador
        $data[] = $fila;
    }
    return $data;
}

function agregarUsuario($conexion, $data)
{
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

    $res = mysqli_query($conexion, $sql);
    if ($res) {
        $nuevoId = mysqli_insert_id($conexion);
        $payloadAlta = [
            'usuario_id' => $nuevoId,
            'usuario_nombre' => $data['usuario_nombre'],
            'usuario' => $data['usuario'],
            'email' => $data['email'],
            'duracion_sid_minutos' => $duracion_sid_minutos,
        ];
        auditoria_registrar_alta($conexion, 'conf__usuarios', $nuevoId, $payloadAlta, "Alta de usuario '{$usuario_nombre}' ({$usuario})");
    }
    return $res;
}

function editarUsuario($conexion, $id, $data)
{
    if (empty($data['usuario_nombre']) || empty($data['usuario']) || empty($data['email'])) {
        return false;
    }

    $id = intval($id);
    $datosAntes = obtenerUsuarioPorId($conexion, $id);

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

    $res = mysqli_query($conexion, $sql);
    if ($res) {
        $datosDespues = obtenerUsuarioPorId($conexion, $id);
        auditoria_registrar_modificacion($conexion, 'conf__usuarios', $id, $datosAntes ?: [], $datosDespues ?: [], "Modificación de datos del usuario '{$usuario_nombre}' ({$usuario})");
    }
    return $res;
}

function cambiarEstadoUsuario($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    $datosAntes = obtenerUsuarioPorId($conexion, $id);

    $sql = "UPDATE conf__usuarios SET tabla_estado_registro_id = $nuevo_estado WHERE usuario_id = $id";
    $res = mysqli_query($conexion, $sql);
    if ($res) {
        $datosDespues = obtenerUsuarioPorId($conexion, $id);
        $accion = ($nuevo_estado == 1) ? 'ALTA' : 'BAJA';
        $desc = ($nuevo_estado == 1)
            ? "Habilitación / Reactivación del usuario #{$id}"
            : "Inhabilitación / Baja lógica del usuario #{$id}";
        auditoria_registrar_operacion($conexion, $accion, 'conf__usuarios', $id, $datosAntes ?: [], $datosDespues ?: [], $desc);
    }
    return $res;
}

function obtenerUsuarioPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__usuarios WHERE usuario_id = $id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    if ($fila) {
        unset($fila['password'], $fila['password_temporal']); // nunca enviar hashes al navegador
    }
    return $fila;
}