<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 614]);

/**
 * stock_dashboard_ajax.php — Dashboard de stock (página 114). Solo lectura.
 * Respuesta: {success: bool, data: ..., message: string}. Las grillas server-side
 * (listar_stock, listar_movimientos) devuelven además draw/recordsTotal/recordsFiltered
 * para DataTables, con las filas en data.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/stock_dashboard_model.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

function sdbResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200, array $extra = []): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(array_merge(['success' => $ok, 'data' => $data, 'message' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'obtener_filtros':
            sdbResponder(true, [
                'bocas' => sdbBocas($conexion, $empresa_id, $usuario_id),
                'categorias' => sdbCategorias($conexion, $empresa_id),
                'tipos' => sdbTiposMovimiento($conexion, $empresa_id),
            ]);
            break;

        case 'resumen':
            [$desde, $hasta] = sdbRango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
            sdbResponder(true, sdbResumen($conexion, $empresa_id, $usuario_id, (int)($_GET['boca_id'] ?? 0), $desde, $hasta));
            break;

        case 'listar_stock':
            $r = sdbListarStock($conexion, $empresa_id, $usuario_id, $_GET);
            sdbResponder(true, $r['data'], '', 200, array_diff_key($r, ['data' => 1]));
            break;

        case 'listar_movimientos':
            $r = sdbListarMovimientos($conexion, $empresa_id, $usuario_id, $_GET);
            sdbResponder(true, $r['data'], '', 200, array_diff_key($r, ['data' => 1]));
            break;

        case 'kardex':
            [$desde, $hasta] = sdbRango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
            sdbResponder(true, sdbKardex($conexion, $empresa_id, $usuario_id, (int)($_GET['producto_id'] ?? 0),
                (int)($_GET['boca_id'] ?? 0), $desde, $hasta));
            break;

        default:
            sdbResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[stock_dashboard_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    sdbResponder(false, null, 'Error de base de datos', 500);
} catch (InvalidArgumentException $e) {
    sdbResponder(false, null, $e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('[stock_dashboard_ajax] ' . $accion . ': ' . $e->getMessage());
    sdbResponder(false, null, 'Error interno del servidor', 500);
}
