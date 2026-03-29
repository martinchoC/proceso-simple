<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "iva_alicuotas_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 81);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $alicuotas = obtenerIvaAlicuotas($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($alicuotas, JSON_UNESCAPED_UNICODE);
            break;
            
        // NUEVO: Listar cuentas contables
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
                'empresa_id' => strval($empresa_idx),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'iva_alicuota' => trim($_POST['iva_alicuota'] ?? ''),
                'porcentaje' => floatval($_POST['porcentaje'] ?? 0),
                'cont_cuenta_id' => intval($_POST['cont_cuenta_id'] ?? 0),
                'es_gravado' => intval($_POST['es_gravado'] ?? 1),
                'es_exento' => intval($_POST['es_exento'] ?? 0),
                'es_no_gravado' => intval($_POST['es_no_gravado'] ?? 0),
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarIvaAlicuota($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['iva_alicuota_id'] ?? 0);
            $data = [
                'empresa_id' => strval($empresa_idx),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'iva_alicuota' => trim($_POST['iva_alicuota'] ?? ''),
                'porcentaje' => floatval($_POST['porcentaje'] ?? 0),
                'cont_cuenta_id' => intval($_POST['cont_cuenta_id'] ?? 0),
                'es_gravado' => intval($_POST['es_gravado'] ?? 1),
                'es_exento' => intval($_POST['es_exento'] ?? 0),
                'es_no_gravado' => intval($_POST['es_no_gravado'] ?? 0)
            ];

            $resultado = editarIvaAlicuota($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $iva_alicuota_id = intval($_POST['iva_alicuota_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($iva_alicuota_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $iva_alicuota_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['iva_alicuota_id'] ?? $_GET['iva_alicuota_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $alicuota = obtenerIvaAlicuotaPorId($conexion, $id, $empresa_idx);
            if ($alicuota) {
                echo json_encode($alicuota, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Alícuota no encontrada'], JSON_UNESCAPED_UNICODE);
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