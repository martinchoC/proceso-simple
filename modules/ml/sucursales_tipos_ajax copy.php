<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "sucursales_tipos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $sucursales_tipos = obtenerLocalesTipos($conexion);
        echo json_encode($sucursales_tipos);
        break;
    
    case 'agregar':
        $data = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = agregarLocalTipo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['sucursal_tipo_id']);
        $data = [
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = editarLocalTipo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['sucursal_tipo_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoLocalTipo($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['sucursal_tipo_id']);
        $resultado = eliminarLocalTipo($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['sucursal_tipo_id']);
        $sucursal_tipo = obtenerLocalTipoPorId($conexion, $id);
        echo json_encode($sucursal_tipo);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}