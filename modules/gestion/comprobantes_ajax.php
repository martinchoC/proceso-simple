<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "comprobantes_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $comprobantes = obtenerComprobantesSucursales($conexion);
        echo json_encode($comprobantes);
        break;
    
    case 'listar_sucursales':
        $sucursales = obtenerSucursales($conexion);
        echo json_encode($sucursales);
        break;
    
    case 'listar_comprobantes_tipos':
        $comprobantes_tipos = obtenerComprobantesTipos($conexion);
        echo json_encode($comprobantes_tipos);
        break;
    
    case 'listar_comprobantes_grupos':
        $comprobantes_grupos = obtenerComprobantesGrupos($conexion);
        echo json_encode($comprobantes_grupos);
        break;
    
    case 'agregar':
        $data = [
            'sucursal_id' => $_POST['sucursal_id'] ?? '',
            'comprobante_tipo_id' => $_POST['comprobante_tipo_id'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['sucursal_id']) || empty($data['comprobante_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Sucursal y tipo de comprobante son obligatorios']);
            break;
        }
        
        $resultado = agregarComprobanteSucursal($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe esta combinación de sucursal y tipo de comprobante']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['comprobante_sucursal_id']);
        $data = [
            'sucursal_id' => $_POST['sucursal_id'] ?? '',
            'comprobante_tipo_id' => $_POST['comprobante_tipo_id'] ?? '',
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        if (empty($data['sucursal_id']) || empty($data['comprobante_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Sucursal y tipo de comprobante son obligatorios']);
            break;
        }
        
        $resultado = editarComprobanteSucursal($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe esta combinación de sucursal y tipo de comprobante']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['comprobante_sucursal_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoComprobanteSucursal($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['comprobante_sucursal_id']);
        $resultado = eliminarComprobanteSucursal($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['comprobante_sucursal_id']);
        $comprobante = obtenerComprobanteSucursalPorId($conexion, $id);
        echo json_encode($comprobante);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}