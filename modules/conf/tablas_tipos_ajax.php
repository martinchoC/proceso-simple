<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "tablas_tipos_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar_tipos':
        $tipos = obtenerTablasTipos($conexion);
        echo json_encode($tipos);
        break;
    
    case 'obtener_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $tipo = obtenerTablaTipoPorId($conexion, $id);
        echo json_encode($tipo);
        break;
    
    case 'agregar_tipo':
        $data = [
            'tabla_tipo' => $_GET['tabla_tipo'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_tipo'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del tipo de tabla es obligatorio']);
            break;
        }
        
        $resultado = agregarTablaTipo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $data = [
            'tabla_tipo' => $_GET['tabla_tipo'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_tipo'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del tipo de tabla es obligatorio']);
            break;
        }
        
        $resultado = editarTablaTipo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoTablaTipo($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    // Estados de tipos de tablas
    case 'listar_estados':
        $tabla_tipo_id = intval($_GET['tabla_tipo_id']);
        $estados = obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id);
        echo json_encode($estados);
        break;
    
    case 'obtener_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $estado = obtenerTablaTipoEstadoPorId($conexion, $id);
        echo json_encode($estado);
        break;
    
    case 'agregar_estado':
        $data = [
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'tabla_tipo_estado' => $_GET['tabla_tipo_estado'] ?? '',
            'tabla_tipo_estado_descripcion' => $_GET['tabla_tipo_estado_descripcion'] ?? '',
            'valor' => $_GET['valor'] ?? 0,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_tipo_estado']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del estado y el tipo de tabla son obligatorios']);
            break;
        }
        
        $resultado = agregarTablaTipoEstado($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $data = [
            'tabla_tipo_estado' => $_GET['tabla_tipo_estado'] ?? '',
            'tabla_tipo_estado_descripcion' => $_GET['tabla_tipo_estado_descripcion'] ?? '',
            'valor' => $_GET['valor'] ?? 0,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_tipo_estado'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del estado es obligatorio']);
            break;
        }
        
        $resultado = editarTablaTipoEstado($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>