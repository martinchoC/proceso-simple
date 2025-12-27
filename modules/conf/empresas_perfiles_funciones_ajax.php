<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "empresas_perfiles_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $perfiles = obtenerEmpresasPerfiles($conexion);
        echo json_encode($perfiles);
        break;
    
    case 'agregar':
        $data = [
            'empresa_perfil_nombre' => $_GET['empresa_perfil_nombre'] ?? '',
            'empresa_id' => $_GET['empresa_id'] ?? null,
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'perfil_id_base' => $_GET['perfil_id_base'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['empresa_perfil_nombre']) || empty($data['empresa_id']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos empresa, módulo y nombre son obligatorios']);
            break;
        }
        
        $resultado = agregarEmpresaPerfil($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['empresa_perfil_id']);
        $data = [
            'empresa_perfil_nombre' => $_GET['empresa_perfil_nombre'] ?? '',
            'empresa_id' => $_GET['empresa_id'] ?? null,
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'perfil_id_base' => $_GET['perfil_id_base'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['empresa_perfil_nombre']) || empty($data['empresa_id']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos empresa, módulo y nombre son obligatorios']);
            break;
        }
        
        $resultado = editarEmpresaPerfil($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['empresa_perfil_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoEmpresaPerfil($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['empresa_perfil_id']);
        $perfil = obtenerEmpresaPerfilPorId($conexion, $id);
        echo json_encode($perfil);
        break;
        
    case 'obtener_empresas':
        $empresas = obtenerEmpresas($conexion);
        echo json_encode($empresas);
        break;
        
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;
        
    case 'obtener_perfiles_base':
        $perfiles = obtenerPerfilesBase($conexion);
        echo json_encode($perfiles);
        break;
        
    case 'obtener_perfiles_base_por_modulo':
        $modulo_id = intval($_GET['modulo_id']);
        $perfiles = obtenerPerfilesBasePorModulo($conexion, $modulo_id);
        echo json_encode($perfiles);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}