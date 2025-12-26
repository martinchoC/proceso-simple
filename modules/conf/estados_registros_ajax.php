<?php
require_once "estados_registros_model.php";

header('Content-Type: application/json');

$accion = $_GET['accion'] ?? '';

switch ($accion) {
    case 'obtenerColores':
        $colores = obtenerColores($conexion);
        echo json_encode($colores);
        break;
        
    case 'listar':
        $estados = obtenerEstadosRegistros($conexion);
        echo json_encode($estados);
        break;
        
    case 'obtener':
        $id = $_GET['estado_registro_id'] ?? 0;
        $estado = obtenerEstadoRegistroPorId($conexion, $id);
        echo json_encode($estado);
        break;
        
    case 'agregar':
        $data = [
            'estado_registro' => $_POST['estado_registro'] ?? '',
            'codigo_estandar' => $_POST['codigo_estandar'] ?? '',
            'valor_estandar' => $_POST['valor_estandar'] ?? null,
            'color_id' => $_POST['color_id'] ?? '1',
            'orden_estandar' => $_POST['orden_estandar'] ?? null
        ];
        
        $resultado = agregarEstadoRegistro($conexion, $data);
        
        if ($resultado) {
            echo json_encode(['resultado' => true]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al agregar el estado']);
        }
        break;
        
    case 'editar':
        $id = $_POST['estado_registro_id'] ?? 0;
        $data = [
            'estado_registro' => $_POST['estado_registro'] ?? '',
            'codigo_estandar' => $_POST['codigo_estandar'] ?? '',
            'valor_estandar' => $_POST['valor_estandar'] ?? null,
            'color_id' => $_POST['color_id'] ?? '1',
            'orden_estandar' => $_POST['orden_estandar'] ?? null
        ];
        
        $resultado = editarEstadoRegistro($conexion, $id, $data);
        
        if ($resultado) {
            echo json_encode(['resultado' => true]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al editar el estado']);
        }
        break;
        
    case 'eliminar':
        $id = $_GET['estado_registro_id'] ?? 0;
        $resultado = eliminarEstadoRegistro($conexion, $id);
        
        if ($resultado) {
            echo json_encode(['resultado' => true]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al eliminar el estado']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}

// Cerrar conexión
mysqli_close($conexion);
?>