<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "empresas_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $empresas = obtenerempresas($conexion);
        echo json_encode($empresas);
        break;

    case 'agregar':
        $data = [
            'empresa' => $_GET['empresa'] ?? '',
            'documento_tipo_id' => $_GET['documento_tipo_id'] ?? '',
            'documento_numero' => $_GET['documento_numero'] ?? '',
            'telefono' => $_GET['telefono'] ?? '',
            'domicilio' => $_GET['domicilio'] ?? 'default',
            'localidad_id' => $_GET['localidad_id'] ?? null,
            'email' => $_GET['email'] ?? '',
            'base_conf' => $_GET['base_conf'] ?? null,
            
            'estado_registro_id' => $_GET['estado_registro_id'] ?? 1
        ];
        $resultado = agregarempresa($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['empresa_id']);
        $data = [
            'empresa' => $_GET['empresa'] ?? '',
            'documento_tipo_id' => $_GET['documento_tipo_id'] ?? '',
            'documento_numero' => $_GET['documento_numero'] ?? '',
            'telefono' => $_GET['telefono'] ?? '',
            'domicilio' => $_GET['domicilio'] ?? 'default',
            'localidad_id' => $_GET['localidad_id'] ?? null,
            'email' => $_GET['email'] ?? '',
            'base_conf' => $_GET['base_conf'] ?? null,
            
            'estado_registro_id' => $_GET['estado_registro_id'] ?? 1
        ];
        $resultado = editarempresa($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['empresa_id']);
        $resultado = eliminarempresa($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['empresa_id']);
        $empresa = obtenerempresaPorId($conexion, $id);
        echo json_encode($empresa);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
