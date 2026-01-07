<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "comprobantes_fiscales_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 49);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $comprobantes = obtenerComprobantesFiscales($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($comprobantes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'codigo' => intval($_POST['codigo'] ?? 0),
                'comprobante_fiscal' => trim($_POST['comprobante_fiscal'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarComprobanteFiscal($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['comprobante_fiscal_id'] ?? 0);
            $data = [
                'codigo' => intval($_POST['codigo'] ?? 0),
                'comprobante_fiscal' => trim($_POST['comprobante_fiscal'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarComprobanteFiscal($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $comprobante_fiscal_id = intval($_POST['comprobante_fiscal_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($comprobante_fiscal_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $comprobante_fiscal_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['comprobante_fiscal_id'] ?? $_GET['comprobante_fiscal_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $comprobante = obtenerComprobanteFiscalPorId($conexion, $id, $empresa_idx);
            if ($comprobante) {
                echo json_encode($comprobante, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Comprobante fiscal no encontrado'], JSON_UNESCAPED_UNICODE);
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