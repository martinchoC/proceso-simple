<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "empresas_modulos_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $empresasModulos = obtenerEmpresasModulos($conexion);
        echo json_encode($empresasModulos);
        break;
    
    case 'agregar':
        $data = [
            'empresa_id' => $_GET['empresa_id'] ?? null,
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        // Validaciones
        if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Empresa y módulo son obligatorios']);
            break;
        }
        
        // Verificar si ya existe la combinación
        if (existeEmpresaModulo($conexion, $data['empresa_id'], $data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Esta empresa ya tiene asignado este módulo']);
            break;
        }
        
        $resultado = agregarEmpresaModulo($conexion, $data);
        
        // Si se agregó exitosamente, preguntar si copiar perfiles
        if ($resultado) {
            echo json_encode([
                'resultado' => true, 
                'empresa_modulo_id' => mysqli_insert_id($conexion),
                'preguntar_copiar_perfiles' => true,
                'empresa_id' => $data['empresa_id'],
                'modulo_id' => $data['modulo_id']
            ]);
        } else {
            echo json_encode(['resultado' => false]);
        }
        break;

    case 'editar':
        $id = intval($_GET['empresa_modulo_id']);
        $data = [
            'empresa_id' => $_GET['empresa_id'] ?? null,
            'modulo_id' => $_GET['modulo_id'] ?? null,
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? 1
        ];
        
        // Validaciones
        if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Empresa y módulo son obligatorios']);
            break;
        }
        
        // Verificar si ya existe la combinación (excluyendo el registro actual)
        if (existeEmpresaModuloEditando($conexion, $data['empresa_id'], $data['modulo_id'], $id)) {
            echo json_encode(['resultado' => false, 'error' => 'Esta empresa ya tiene asignado este módulo']);
            break;
        }
        
        $resultado = editarEmpresaModulo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['empresa_modulo_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoEmpresaModulo($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['empresa_modulo_id']);
        $empresaModulo = obtenerEmpresaModuloPorId($conexion, $id);
        echo json_encode($empresaModulo);
        break;
        
    case 'obtener_empresas':
        $empresas = obtenerEmpresas($conexion);
        echo json_encode($empresas);
        break;
        
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;
        
    case 'copiar_perfiles':
        $empresa_id = intval($_GET['empresa_id']);
        $modulo_id = intval($_GET['modulo_id']);
        $resultado = copiarPerfilesModulo($conexion, $empresa_id, $modulo_id);
        echo json_encode($resultado);
        break;
        
    case 'verificar_perfiles':
        $empresa_id = intval($_GET['empresa_id']);
        $modulo_id = intval($_GET['modulo_id']);
        $perfiles = verificarPerfilesExistentes($conexion, $empresa_id, $modulo_id);
        echo json_encode($perfiles);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>