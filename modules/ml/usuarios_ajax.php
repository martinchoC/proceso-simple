<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "usuarios_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $usuarios = obtenerUsuarios($conexion);
        echo json_encode($usuarios);
        break;
    
    case 'agregar':
        $data = [
            'usuario_nombre' => $_POST['usuario_nombre'] ?? '',
            'usuario' => $_POST['usuario'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'duracion_sid_minutos' => $_POST['duracion_sid_minutos'] ?? 60
        ];
        
        if (empty($data['usuario_nombre']) || empty($data['usuario']) || empty($data['email']) || empty($data['password'])) {
            echo json_encode(['resultado' => false, 'error' => 'Todos los campos obligatorios deben completarse']);
            break;
        }
        
        $resultado = agregarUsuario($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['usuario_id']);
        $data = [
            'usuario_nombre' => $_POST['usuario_nombre'] ?? '',
            'usuario' => $_POST['usuario'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'duracion_sid_minutos' => $_POST['duracion_sid_minutos'] ?? 60
        ];
        
        if (empty($data['usuario_nombre']) || empty($data['usuario']) || empty($data['email'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos nombre, usuario y email son obligatorios']);
            break;
        }
        
        $resultado = editarUsuario($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['usuario_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoUsuario($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['usuario_id']);
        $usuario = obtenerUsuarioPorId($conexion, $id);
        echo json_encode($usuario);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}