<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_idx'], 'pagina_idx' => 410]);
/**
 * Controlador AJAX: Mini-Gestión y Amortización de Activos Fijos
 * Módulo 4: Estudio Contable (activos_amortizaciones_ajax.php)
 * Página ID: 410
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/activos_amortizaciones_model.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {
        case 'combos':
            $combos = activos_combos($conn, $empresa_id);
            echo json_encode([
                'success' => true,
                'message' => 'Combos obtenidos.',
                'data' => $combos,
                'error' => null
            ]);
            break;

        case 'listar':
            $filtros = [
                'categoria_activo' => $_POST['categoria_activo'] ?? $_GET['categoria_activo'] ?? '',
                'buscar' => $_POST['buscar'] ?? $_GET['buscar'] ?? ''
            ];
            $lista = activos_listar($conn, $empresa_id, $filtros);
            echo json_encode([
                'success' => true,
                'message' => 'Activos obtenidos.',
                'data' => $lista,
                'error' => null
            ]);
            break;

        case 'guardar':
            $datos = [
                'activo_id' => $_POST['activo_id'] ?? 0,
                'codigo' => $_POST['codigo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'categoria_activo' => $_POST['categoria_activo'] ?? 'MUEBLES_UTILES',
                'f_adquisicion' => $_POST['f_adquisicion'] ?? '',
                'valor_origen' => $_POST['valor_origen'] ?? 0.0,
                'valor_residual' => $_POST['valor_residual'] ?? 0.0,
                'vida_util_meses' => $_POST['vida_util_meses'] ?? 60,
                'metodo_amortizacion' => $_POST['metodo_amortizacion'] ?? 'LINEAL_MENSUAL',
                'cuenta_activo_id' => $_POST['cuenta_activo_id'] ?? 0,
                'cuenta_amort_acum_id' => $_POST['cuenta_amort_acum_id'] ?? 0,
                'cuenta_gasto_amort_id' => $_POST['cuenta_gasto_amort_id'] ?? 0
            ];
            $res = activo_guardar($conn, $empresa_id, $datos);
            echo json_encode($res);
            break;

        case 'listar_amortizaciones':
            $activo_id = intval($_POST['activo_id'] ?? $_GET['activo_id'] ?? 0);
            $cuotas = activo_amortizaciones_listar($conn, $empresa_id, $activo_id);
            echo json_encode([
                'success' => true,
                'message' => 'Historial de amortizaciones obtenido.',
                'data' => $cuotas,
                'error' => null
            ]);
            break;

        case 'amortizar_mensual':
            $periodo = intval($_POST['periodo'] ?? date('Ym'));
            $res = activos_generar_amortizacion_mensual($conn, $empresa_id, $periodo, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => "Acción '{$action}' no reconocida.",
                'data' => null,
                'error' => 'INVALID_ACTION'
            ]);
            break;
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción en servidor: ' . $e->getMessage(),
        'data' => null,
        'error' => $e->getMessage()
    ]);
}
