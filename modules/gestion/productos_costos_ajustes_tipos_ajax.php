<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);  // mostrar errores en pantalla

require_once __DIR__ . '/../../db.php';
require_once "productos_costos_ajustes_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 80);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $tipos = obtenerTiposAjuste($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'producto_costo_ajuste_tipo_codigo' => trim($_POST['producto_costo_ajuste_tipo_codigo'] ?? ''),
                'producto_costo_ajuste_tipo_nombre' => trim($_POST['producto_costo_ajuste_tipo_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = agregarTipoAjuste($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['producto_costo_ajuste_tipo_id'] ?? 0);
            $data = [
                'producto_costo_ajuste_tipo_codigo' => trim($_POST['producto_costo_ajuste_tipo_codigo'] ?? ''),
                'producto_costo_ajuste_tipo_nombre' => trim($_POST['producto_costo_ajuste_tipo_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = editarTipoAjuste($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $tipo_id = intval($_POST['producto_costo_ajuste_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['producto_costo_ajuste_tipo_id'] ?? $_GET['producto_costo_ajuste_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $tipo = obtenerTipoAjustePorId($conexion, $id);
            if ($tipo) {
                echo json_encode($tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de ajuste no encontrado'], JSON_UNESCAPED_UNICODE);
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