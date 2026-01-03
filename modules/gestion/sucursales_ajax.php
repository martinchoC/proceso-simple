<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "sucursales_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $sucursales = obtenerLocales($conexion);
        echo json_encode($sucursales);
        break;
    
    case 'listar_empresas':
        $empresas = obtenerEmpresas($conexion);
        echo json_encode($empresas);
        break;
    
    case 'listar_sucursales_tipos':
        $sucursales_tipos = obtenerLocalesTipos($conexion);
        echo json_encode($sucursales_tipos);
        break;
    
    // Nueva acción para obtener localidades
    case 'listar_localidades':
        $localidades = obtenerLocalidades($conexion);
        echo json_encode($localidades);
        break;
    
    case 'agregar':
        $data = [
            'empresa_id' => $_POST['empresa_id'] ?? '',
            'sucursal_tipo_id' => $_POST['sucursal_tipo_id'] ?? '',
            'sucursal_nombre' => $_POST['sucursal_nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? null,
            'direccion' => $_POST['direccion'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['empresa_id']) || empty($data['sucursal_tipo_id']) || empty($data['sucursal_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'Empresa, tipo de sucursal y nombre son obligatorios']);
            break;
        }
        
        $resultado = agregarLocal($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['sucursal_id']);
        $data = [
            'empresa_id' => $_POST['empresa_id'] ?? '',
            'sucursal_tipo_id' => $_POST['sucursal_tipo_id'] ?? '',
            'sucursal_nombre' => $_POST['sucursal_nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'localidad_id' => $_POST['localidad_id'] ?? null,
            'direccion' => $_POST['direccion'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1,
            'usuario_creacion_id' => $_POST['usuario_creacion_id'] ?? null
        ];
        
        if (empty($data['empresa_id']) || empty($data['sucursal_tipo_id']) || empty($data['sucursal_nombre'])) {
            echo json_encode(['resultado' => false, 'error' => 'Empresa, tipo de sucursal y nombre son obligatorios']);
            break;
        }
        
        $resultado = editarLocal($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['sucursal_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoLocal($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['sucursal_id']);
        $resultado = eliminarLocal($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['sucursal_id']);
        $sucursal = obtenerLocalPorId($conexion, $id);
        echo json_encode($sucursal);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}