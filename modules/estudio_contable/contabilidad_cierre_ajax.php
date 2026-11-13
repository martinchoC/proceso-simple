<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 413]);
/**
 * AJAX Dispatcher: Cierre de Ejercicio Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_cierre_model.php';

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];
$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 413, (string)$accion, [
    'ejecutar_cierre' => 'agregar',
]);

$model = new ContabilidadCierreModel($conn);

function responderJson($success, $message, $data = null, $error = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data'    => $data,
        'error'   => $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar_cierres':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $cierres = $model->listarCierres($empresa_sel);
            responderJson(true, "Cierres de ejercicio listados.", $cierres);
            break;

        case 'rango_ejercicio':
            // Fechas del ejercicio según el mes de cierre de Parámetros de Empresa
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $rango = $model->rangoEjercicio($empresa_sel, intval($_REQUEST['anio_ejercicio'] ?? 0));
            responderJson(true, "Ejercicio obtenido.", $rango);
            break;

        case 'calcular_balance':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $rango = $model->rangoEjercicio($empresa_sel, intval($_REQUEST['anio_ejercicio'] ?? 0));
            $balance = $model->calcularBalanceComprobacion($empresa_sel, $rango['desde'], $rango['hasta']);
            $balance['ejercicio'] = ['desde' => $rango['desde'], 'hasta' => $rango['hasta'], 'texto' => $rango['texto']];
            responderJson(true, "Balance de comprobación calculado.", $balance);
            break;

        case 'ejecutar_cierre':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $anio = intval($_POST['anio_ejercicio'] ?? 0);

            $resultado = $model->ejecutarCierre($empresa_sel, $anio, $usuario_id);
            responderJson(true, "Cierre de ejercicio {$anio} ejecutado con éxito.", $resultado);
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no implementada.");
            break;
    }
} catch (Throwable $e) {
    error_log("=== contabilidad_cierre_ajax {$accion}: " . $e->getMessage());
    responderJson(false, $e->getMessage(), null, $e->getMessage());
}
