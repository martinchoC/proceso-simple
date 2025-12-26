<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Primero incluimos directamente la conexión
require_once "conexion.php";

// Ahora incluimos el modelo que usa la conexión
require_once "tablas_estados_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $estados = obtenerTablasEstados($conexion);
        echo json_encode($estados);
        break;
    
    case 'obtenerTablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;

    case 'obtenerEstadosRegistro':
        $estados = obtenerEstadosRegistro($conexion);
        echo json_encode($estados);
        break;

    case 'obtenerColores':
        $colores = obtenerColores($conexion);
        echo json_encode($colores);
        break;
    
    case 'agregar':
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? '',
            'tabla_estado_registro' => $_GET['tabla_estado_registro'] ?? '',
            'color_id' => $_GET['color_id'] ?? 1,
            'es_inicial' => $_GET['es_inicial'] ?? 0,
            'orden' => $_GET['orden'] ?? 1
        ];
        
        // Validación de campos obligatorios
        if (empty($data['tabla_id']) || 
            empty($data['estado_registro_id']) || 
            empty($data['tabla_estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'Tabla, estado registro y nombre de estado son obligatorios']);
            break;
        }
        
        $resultado = agregarTablaEstado($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['tabla_estado_registro_id']);
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro_id' => $_GET['estado_registro_id'] ?? '',
            'tabla_estado_registro' => $_GET['tabla_estado_registro'] ?? '',
            'color_id' => $_GET['color_id'] ?? 1,
            'es_inicial' => $_GET['es_inicial'] ?? 0,
            'orden' => $_GET['orden'] ?? 1
        ];
        
        // Validación de campos obligatorios
        if (empty($data['tabla_id']) || 
            empty($data['estado_registro_id']) || 
            empty($data['tabla_estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'Tabla, estado registro y nombre de estado son obligatorios']);
            break;
        }
        
        $resultado = editarTablaEstado($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['tabla_estado_registro_id']);
        $resultado = eliminarTablaEstado($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['tabla_estado_registro_id']);
        $estado = obtenerTablaEstadoPorId($conexion, $id);
        echo json_encode($estado);
        break;

    case 'verificarEstadoInicial':
        $tabla_id = intval($_GET['tabla_id']);
        $excluir_id = intval($_GET['excluir_id'] ?? 0);
        $existe = verificarEstadoInicial($conexion, $tabla_id, $excluir_id);
        echo json_encode(['existe' => $existe]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}