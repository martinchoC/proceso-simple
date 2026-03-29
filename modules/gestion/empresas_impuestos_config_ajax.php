<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);  // mostrar errores en pantal

require_once __DIR__ . '/../../db.php';
require_once "gestion__impuestos_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 72); // ← Cambiado a 72

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $tipos = obtenerTiposImpuesto($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_cuentas_contables':
            $cuentas = obtenerCuentasContables($conexion, $empresa_idx);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'empresa_id' => $empresa_idx,
                'impuesto_tipo' => trim($_POST['impuesto_tipo'] ?? ''),
                'codigo_afip' => trim($_POST['codigo_afip'] ?? ''),
                'cuenta_contable_id' => !empty($_POST['cuenta_contable_id']) ? intval($_POST['cuenta_contable_id']) : null,
                'aplica_compra' => intval($_POST['aplica_compra'] ?? 1),
                'aplica_venta' => intval($_POST['aplica_venta'] ?? 0),
                'es_retencion' => intval($_POST['es_retencion'] ?? 0),
                'es_percepcion' => intval($_POST['es_percepcion'] ?? 0)
            ];

            $resultado = agregarTipoImpuesto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['impuesto_tipo_id'] ?? 0);
            $data = [
                'impuesto_tipo' => trim($_POST['impuesto_tipo'] ?? ''),
                'codigo_afip' => trim($_POST['codigo_afip'] ?? ''),
                'cuenta_contable_id' => !empty($_POST['cuenta_contable_id']) ? intval($_POST['cuenta_contable_id']) : null,
                'aplica_compra' => intval($_POST['aplica_compra'] ?? 1),
                'aplica_venta' => intval($_POST['aplica_venta'] ?? 0),
                'es_retencion' => intval($_POST['es_retencion'] ?? 0),
                'es_percepcion' => intval($_POST['es_percepcion'] ?? 0)
            ];

            $resultado = editarTipoImpuesto($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $impuesto_tipo_id = intval($_POST['impuesto_tipo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($impuesto_tipo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $impuesto_tipo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['impuesto_tipo_id'] ?? $_GET['impuesto_tipo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $tipo = obtenerTipoImpuestoPorId($conexion, $id);
            if ($tipo) {
                echo json_encode($tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de impuesto no encontrado'], JSON_UNESCAPED_UNICODE);
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