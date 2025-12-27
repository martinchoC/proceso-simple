<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "perfiles_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $perfiles = obtenerPerfiles($conexion);
        echo json_encode($perfiles);
        break;
    
    case 'agregar':
        $data = [
            'perfil_nombre' => $_GET['perfil_nombre'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['perfil_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = agregarPerfil($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['perfil_id']);
        $data = [
            'perfil_nombre' => $_GET['perfil_nombre'] ?? '',
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['perfil_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }
        
        $resultado = editarPerfil($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['perfil_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoPerfil($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['perfil_id']);
        $perfil = obtenerPerfilPorId($conexion, $id);
        echo json_encode($perfil);
        break;
        
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}