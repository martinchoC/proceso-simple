<?php
require_once 'config/db.php';
session_start();
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? null;

switch ($accion) {
    case 'listar':
        $query = "SELECT * FROM conf__modulos WHERE estado_registro_id = 1 ORDER BY modulo_id";
        $res = $conn->query($query);
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;

    case 'guardar':
        $modulo_id = $_POST['modulo_id'] ?? null;
        $modulo = $_POST['modulo'];
        $base_datos = $_POST['base_datos'];
        $modulo_url = $_POST['modulo_url'];
        $email_envio_modulo = $_POST['email_envio_modulo'];
        $layout_nombre = $_POST['layout_nombre'];
        $usuario_temp = $_POST['usuario_temp'] ?: null;
        $session_temp = $_POST['session_temp'];
        $imagen_id = $_POST['imagen_id'] ?: null;
        $depende_id = $_POST['depende_id'] ?: 0;

        if ($modulo_id) {
            $stmt = $conn->prepare("UPDATE conf__modulos SET modulo=?, base_datos=?, modulo_url=?, email_envio_modulo=?, layout_nombre=?, usuario_temp=?, session_temp=?, imagen_id=?, depende_id=? WHERE modulo_id=?");
            $stmt->bind_param("ssssssdiii", $modulo, $base_datos, $modulo_url, $email_envio_modulo, $layout_nombre, $usuario_temp, $session_temp, $imagen_id, $depende_id, $modulo_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO conf__modulos (modulo, base_datos, modulo_url, email_envio_modulo, layout_nombre, usuario_temp, session_temp, imagen_id, depende_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdii", $modulo, $base_datos, $modulo_url, $email_envio_modulo, $layout_nombre, $usuario_temp, $session_temp, $imagen_id, $depende_id);
        }

        $stmt->execute();
        echo json_encode(['estado' => 'ok']);
        break;

    case 'obtener':
        $id = $_GET['id'] ?? null;
        $stmt = $conn->prepare("SELECT * FROM conf__modulos WHERE modulo_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        echo json_encode($res->fetch_assoc());
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? null;
        $stmt = $conn->prepare("UPDATE conf__modulos SET estado_registro_id = 2 WHERE modulo_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['estado' => 'ok']);
        break;
   
    default:
        echo json_encode(['error' => 'Acción no válida']);
}
