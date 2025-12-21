<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "imagenes_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $imagenes = obtenerImagenes($conexion);
        echo json_encode($imagenes);
        break;
    
    case 'agregar':
        $data = [
            'imagen_nombre' => $_POST['imagen_nombre'] ?? ''
        ];
        
        if (empty($data['imagen_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        // Verificar si se subió un archivo
        if (!isset($_FILES['archivo_imagen']) || $_FILES['archivo_imagen']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['resultado' => false, 'error' => 'Debe seleccionar una imagen válida']);
            break;
        }
        
        $resultado = agregarImagen($conexion, $data, $_FILES['archivo_imagen']);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['imagen_id']);
        $data = [
            'imagen_nombre' => $_POST['imagen_nombre'] ?? ''
        ];
        
        if (empty($data['imagen_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        // Verificar si se subió un nuevo archivo (opcional en edición)
        $archivo = null;
        if (isset($_FILES['archivo_imagen']) && $_FILES['archivo_imagen']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo_imagen'];
        }
        
        $resultado = editarImagen($conexion, $id, $data, $archivo);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['imagen_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoImagen($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['imagen_id']);
        $resultado = eliminarImagen($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['imagen_id']);
        $imagen = obtenerImagenPorId($conexion, $id);
        echo json_encode($imagen);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}