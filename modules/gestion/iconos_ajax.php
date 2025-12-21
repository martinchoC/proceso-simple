<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "iconos_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $iconos = obtenerIconos($conexion);
        echo json_encode($iconos);
        break;
    
    case 'agregar':
        $data = [
            'icono_nombre' => $_GET['icono_nombre'] ?? '',
            'icono_clase' => $_GET['icono_clase'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre y clase son obligatorios']);
            break;
        }
        
        $resultado = agregarIcono($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['icono_id']);
        $data = [
            'icono_nombre' => $_GET['icono_nombre'] ?? '',
            'icono_clase' => $_GET['icono_clase'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre y clase son obligatorios']);
            break;
        }
        
        $resultado = editarIcono($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['icono_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoIcono($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['icono_id']);
        $icono = obtenerIconoPorId($conexion, $id);
        echo json_encode($icono);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}