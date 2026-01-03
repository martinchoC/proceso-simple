<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "estados_registros_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $estados = obtenerEstadosRegistros($conexion);
        echo json_encode($estados);
        break;
    
    case 'obtener_tablas_tipos':
        $tablas_tipos = obtenerTablasTipos($conexion);
        echo json_encode($tablas_tipos);
        break;
    
    case 'agregar':
        $data = [
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'estado_registro' => $_GET['estado_registro'] ?? '',
            'estado_registro_descripcion' => $_GET['estado_registro_descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? 0
        ];
        
        if (empty($data['estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del estado es obligatorio']);
            break;
        }
        
        $resultado = agregarEstadoRegistro($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['estado_registro_id']);
        $data = [
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'estado_registro' => $_GET['estado_registro'] ?? '',
            'estado_registro_descripcion' => $_GET['estado_registro_descripcion'] ?? '',
            'orden' => $_GET['orden'] ?? 0
        ];
        
        if (empty($data['estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del estado es obligatorio']);
            break;
        }
        
        $resultado = editarEstadoRegistro($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['estado_registro_id']);
        $resultado = eliminarEstadoRegistro($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['estado_registro_id']);
        $estado = obtenerEstadoRegistroPorId($conexion, $id);
        echo json_encode($estado);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>