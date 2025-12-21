<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php"; // Añadir esta línea
require_once "tablas_estados_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $relaciones = obtenerTablasEstados($conexion);
        echo json_encode($relaciones);
        break;
    
    case 'agregar':
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? '',
            'orden' => $_GET['orden'] ?? 1
        ];
        
        if (empty($data['tabla_id']) || empty($data['estado_registro_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'La tabla y el estado son obligatorios']);
            break;
        }
        
        $resultado = agregarTablaEstado($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['tabla_estado_id']);
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? '',
            'orden' => $_GET['orden'] ?? 1
        ];
        
        if (empty($data['tabla_id']) || empty($data['estado_registro_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'La tabla y el estado son obligatorios']);
            break;
        }
        
        $resultado = editarTablaEstado($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['tabla_estado_id']);
        $resultado = eliminarTablaEstado($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['tabla_estado_id']);
        $relacion = obtenerTablaEstadoPorId($conexion, $id);
        echo json_encode($relacion);
        break;
        
    case 'obtener_tablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;
        
    case 'obtener_estados':
        $estados = obtenerEstadosRegistros($conexion);
        echo json_encode($estados);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}