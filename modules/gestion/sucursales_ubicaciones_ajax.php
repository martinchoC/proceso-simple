<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "sucursales_ubicaciones_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $ubicaciones = obtenerUbicaciones($conexion);
        echo json_encode($ubicaciones);
        break;
    
    case 'listar_sucursales':
        $sucursales = obtenerLocales($conexion);
        echo json_encode($sucursales);
        break;
    
    case 'agregar':
        $data = [
            'sucursal_id' => $_POST['sucursal_id'] ?? '',
            'seccion' => $_POST['seccion'] ?? '',
            'estanteria' => $_POST['estanteria'] ?? '',
            'estante' => $_POST['estante'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['sucursal_id']) || empty($data['seccion']) || empty($data['estanteria']) || empty($data['estante'])) {
            echo json_encode(['resultado' => false, 'error' => 'sucursal, sección, estantería y estante son obligatorios']);
            break;
        }
        
        $resultado = agregarUbicacion($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['sucursal_ubicacion_id']);
        $data = [
            'sucursal_id' => $_POST['sucursal_id'] ?? '',
            'seccion' => $_POST['seccion'] ?? '',
            'estanteria' => $_POST['estanteria'] ?? '',
            'estante' => $_POST['estante'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['sucursal_id']) || empty($data['seccion']) || empty($data['estanteria']) || empty($data['estante'])) {
            echo json_encode(['resultado' => false, 'error' => 'sucursal, sección, estantería y estante son obligatorios']);
            break;
        }
        
        $resultado = editarUbicacion($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['sucursal_ubicacion_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoUbicacion($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['sucursal_ubicacion_id']);
        $resultado = eliminarUbicacion($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['sucursal_ubicacion_id']);
        $ubicacion = obtenerUbicacionPorId($conexion, $id);
        echo json_encode($ubicacion);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}