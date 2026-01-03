<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../conexion.php';
require_once "sucursales_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 43); // Ajustar según tu BD

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $sucursales_tipos = obtenerSucursalesTipos($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($sucursales_tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'sucursal_tipo' => trim($_POST['sucursal_tipo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            
            $resultado = agregarSucursalTipo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['sucursal_tipo_id'] ?? 0);
            $data = [
                'sucursal_tipo' => trim($_POST['sucursal_tipo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            
            $resultado = editarSucursalTipo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $sucursal_tipo_id = intval($_POST['sucursal_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            
            if (empty($sucursal_tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $sucursal_tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['sucursal_tipo_id'] ?? $_GET['sucursal_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $sucursal_tipo = obtenerSucursalTipoPorId($conexion, $id, $empresa_idx);
            if ($sucursal_tipo) {
                echo json_encode($sucursal_tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de sucursal no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>