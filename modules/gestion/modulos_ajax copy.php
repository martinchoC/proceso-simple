<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "modulos_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;

    case 'agregar':
        $data = [
            'modulo' => $_GET['modulo'] ?? '',
            'base_datos' => $_GET['base_datos'] ?? '',
            'modulo_url' => $_GET['modulo_url'] ?? '',
            'email_envio_modulo' => $_GET['email_envio_modulo'] ?? '',
            'layout_nombre' => $_GET['layout_nombre'] ?? 'default',
            'usuario_temp' => $_GET['usuario_temp'] ?? null,
            'session_temp' => $_GET['session_temp'] ?? '',
            'imagen_id' => $_GET['imagen_id'] ?? null,
            'depende_id' => $_GET['depende_id'] ?? 0,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        $resultado = agregarModulo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['modulo_id']);
        $data = [
            'modulo' => $_GET['modulo'] ?? '',
            'base_datos' => $_GET['base_datos'] ?? '',
            'modulo_url' => $_GET['modulo_url'] ?? '',
            'email_envio_modulo' => $_GET['email_envio_modulo'] ?? '',
            'layout_nombre' => $_GET['layout_nombre'] ?? 'default',
            'usuario_temp' => $_GET['usuario_temp'] ?? null,
            'session_temp' => $_GET['session_temp'] ?? '',
            'imagen_id' => $_GET['imagen_id'] ?? null,
            'depende_id' => $_GET['depende_id'] ?? 0,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        $resultado = editarModulo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['modulo_id']);
        $resultado = eliminarModulo($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['modulo_id']);
        $modulo = obtenerModuloPorId($conexion, $id);
        echo json_encode($modulo);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
