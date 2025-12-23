<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "tablas_tipos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar_tipos':
        $tipos = obtenerTiposTablas($conexion);
        echo json_encode($tipos);
        break;
    
    case 'agregar_tipo':
        $tabla_tipo = trim($_POST['tabla_tipo'] ?? '');
        
        if (empty($tabla_tipo)) {
            echo json_encode(['resultado' => false, 'error' => 'El tipo de tabla es obligatorio']);
            break;
        }
        
        $resultado = agregarTipoTabla($conexion, $tabla_tipo);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_tipo':
        $id = intval($_POST['tabla_tipo_id']);
        $tabla_tipo = trim($_POST['tabla_tipo'] ?? '');
        
        if (empty($tabla_tipo)) {
            echo json_encode(['resultado' => false, 'error' => 'El tipo de tabla es obligatorio']);
            break;
        }
        
        $resultado = editarTipoTabla($conexion, $id, $tabla_tipo);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoTipoTabla($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $resultado = eliminarTipoTabla($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $tipo = obtenerTipoTablaPorId($conexion, $id);
        echo json_encode($tipo);
        break;

    case 'listar_estados_tipo':
        $id = intval($_GET['tabla_tipo_id']);
        $estados = obtenerEstadosPorTipo($conexion, $id);
        echo json_encode($estados);
        break;

    case 'estados_disponibles':
        $tipo_id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
        $estados = obtenerEstadosDisponibles($conexion, $tipo_id);
        echo json_encode($estados);
        break;

    case 'agregar_estado':
        $data = [
            'tabla_tipo_id' => intval($_POST['tabla_tipo_id']),
            'estado_registro_id' => intval($_POST['estado_registro_id']),
            'orden' => intval($_POST['orden']),
            'es_inicial' => intval($_POST['es_inicial'] ?? 0)
        ];
        
        $resultado = agregarEstadoTipo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_estado':
        $id = intval($_POST['tabla_tipo_estado_id']);
        $data = [
            'estado_registro_id' => intval($_POST['estado_registro_id']),
            'orden' => intval($_POST['orden']),
            'es_inicial' => intval($_POST['es_inicial'] ?? 0)
        ];
        
        $resultado = editarEstadoTipo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoEstadoTipo($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $resultado = eliminarEstadoTipo($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener_estado':
        $id = intval($_GET['tabla_tipo_estado_id']);
        $estado = obtenerEstadoTipoPorId($conexion, $id);
        echo json_encode($estado);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}