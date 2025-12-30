<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "tablas_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;
    
    case 'obtener_Modulos':
        $Modulos = obtenerModulos($conexion);
        echo json_encode($Modulos);
        break;
    
    case 'obtener_Tipos_Tabla':
        $tiposTabla = obtenerTiposTabla($conexion);
        echo json_encode($tiposTabla);
        break;
    
    case 'agregar':
        $data = [
            'tabla_nombre' => $_GET['tabla_nombre'] ?? '',
            'tabla_descripcion' => $_GET['tabla_descripcion'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'tabla_tabla_estado_registro_id' => $_GET['tabla_tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de tabla, módulo y tipo son obligatorios']);
            break;
        }
        
        $resultado = agregarTabla($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['tabla_id']);
        $data = [
            'tabla_nombre' => $_GET['tabla_nombre'] ?? '',
            'tabla_descripcion' => $_GET['tabla_descripcion'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_tipo_id' => $_GET['tabla_tipo_id'] ?? null,
            'tabla_tabla_estado_registro_id' => $_GET['tabla_tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Nombre de tabla, módulo y tipo son obligatorios']);
            break;
        }
        
        $resultado = editarTabla($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['tabla_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoTabla($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['tabla_id']);
        $tabla = obtenerTablaPorId($conexion, $id);
        echo json_encode($tabla);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>