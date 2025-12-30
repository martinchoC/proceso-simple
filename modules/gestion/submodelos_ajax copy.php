<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "submodelos_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $submodelos = obtenerSubmodelos($conexion);
        echo json_encode($submodelos);
        break;

    case 'obtener_modelos':
        $marca_id = intval($_GET['marca_id']);
        $modelos = obtenerModelosPorMarca($conexion, $marca_id);
        echo json_encode($modelos);
        break;

    case 'agregar':
        $data = [
            'modelo_id' => $_GET['modelo_id'] ?? '',
            'submodelo_nombre' => $_GET['submodelo_nombre'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        $resultado = agregarSubmodelo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['submodelo_id']);
        $data = [
            'modelo_id' => $_GET['modelo_id'] ?? '',
            'submodelo_nombre' => $_GET['submodelo_nombre'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        $resultado = editarSubmodelo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['submodelo_id']);
        $resultado = eliminarSubmodelo($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['submodelo_id']);
        $submodelo = obtenerSubmodeloPorId($conexion, $id);
        echo json_encode($submodelo);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}