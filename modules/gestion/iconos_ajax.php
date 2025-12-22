<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "iconos_model.php";

// Crear conexión
$conexion = conectar(); // O la función que uses para conectar en conexion.php

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $iconos = obtenerIconos($conexion);
        echo json_encode(['success' => true, 'data' => $iconos]);
        break;
    
    case 'agregar':
        $data = [
            'icono_nombre' => trim($_POST['icono_nombre'] ?? $_GET['icono_nombre'] ?? ''),
            'icono_clase' => trim($_POST['icono_clase'] ?? $_GET['icono_clase'] ?? ''),
            'tabla_estados_registro_id' => intval($_POST['tabla_estados_registro_id'] ?? $_GET['tabla_estados_registro_id'] ?? 1)
        ];
        
        if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
            echo json_encode(['success' => false, 'error' => 'Nombre y clase son obligatorios']);
            break;
        }
        
        $resultado = agregarIcono($conexion, $data);
        if ($resultado) {
            // Registrar el cambio de estado si tu sistema lo requiere
            $nuevo_id = mysqli_insert_id($conexion);
            echo json_encode(['success' => true, 'message' => 'Icono agregado correctamente', 'id' => $nuevo_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al agregar el icono']);
        }
        break;

    case 'editar':
        $id = intval($_POST['icono_id'] ?? $_GET['icono_id'] ?? 0);
        $data = [
            'icono_nombre' => trim($_POST['icono_nombre'] ?? $_GET['icono_nombre'] ?? ''),
            'icono_clase' => trim($_POST['icono_clase'] ?? $_GET['icono_clase'] ?? ''),
            'tabla_estados_registro_id' => intval($_POST['tabla_estados_registro_id'] ?? $_GET['tabla_estados_registro_id'] ?? 1)
        ];
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de icono inválido']);
            break;
        }
        
        if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
            echo json_encode(['success' => false, 'error' => 'Nombre y clase son obligatorios']);
            break;
        }
        
        // Obtener estado anterior antes de editar
        $estado_anterior = obtenerEstadoIcono($conexion, $id);
        
        $resultado = editarIcono($conexion, $id, $data);
        if ($resultado) {
            // Registrar cambio de estado si es diferente
            if ($estado_anterior != $data['tabla_estados_registro_id']) {
                // registrarCambioEstado($conexion, 'conf__iconos', $id, $estado_anterior, $data['tabla_estados_registro_id']);
            }
            echo json_encode(['success' => true, 'message' => 'Icono actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar el icono']);
        }
        break;

    case 'cambiar_estado':
        $id = intval($_POST['icono_id'] ?? $_GET['icono_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? $_GET['nuevo_estado'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de icono inválido']);
            break;
        }
        
        // Obtener estado anterior
        $estado_anterior = obtenerEstadoIcono($conexion, $id);
        
        $resultado = cambiarEstadoIcono($conexion, $id, $nuevo_estado);
        if ($resultado) {
            // Registrar cambio de estado
            // registrarCambioEstado($conexion, 'conf__iconos', $id, $estado_anterior, $nuevo_estado);
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al cambiar el estado']);
        }
        break;

    case 'obtener':
        $id = intval($_GET['icono_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            break;
        }
        $icono = obtenerIconoPorId($conexion, $id);
        if ($icono) {
            echo json_encode(['success' => true, 'data' => $icono]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Icono no encontrado']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no definida']);
}


// Cerrar conexión si es necesario
if (isset($conexion)) {
    mysqli_close($conexion);
}
?>