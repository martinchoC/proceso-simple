<?php
/**
 * Controlador AJAX: Seguimiento de inspecciones de los clientes
 * Módulo 4: Estudio Contable (inspecciones_ajax.php)
 * Página ID: 418
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 418]);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/inspecciones_model.php';

header('Content-Type: application/json; charset=utf-8');

$empresa_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];
$accion = (string) ($_REQUEST['accion'] ?? '');

$escribe = ['guardar', 'guardar_hito', 'anular', 'cumplir_hito'];
if (in_array($accion, $escribe, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(insp_resp(false, 'Método no permitido.', null, 'METHOD'));
    exit;
}

// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php)
permisos_exigir_accion($conn, 418, $accion, [
    'guardar' => (intval($_POST['inspeccion_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'guardar_hito' => 'agregar',
    'anular' => 'editar',
    'cumplir_hito' => 'editar',
]);

try {
    switch ($accion) {
        case 'combos':
            echo json_encode(insp_resp(true, 'OK', inspecciones_combos($conn, $empresa_id)));
            break;
        case 'listar':
            echo json_encode(insp_resp(true, 'OK', [
                'items' => inspecciones_listar($conn, $empresa_id, $_GET),
                'kpis' => inspecciones_kpis($conn, $empresa_id),
            ]));
            break;
        case 'obtener':
            $i = inspeccion_obtener($conn, $empresa_id, intval($_GET['inspeccion_id'] ?? 0));
            echo json_encode($i ? insp_resp(true, 'OK', $i) : insp_resp(false, 'La inspección no existe.', null, 'NOT_FOUND'));
            break;
        case 'guardar':
            echo json_encode(inspeccion_guardar($conn, $empresa_id, $usuario_id, $_POST));
            break;
        case 'guardar_hito':
            echo json_encode(inspeccion_hito_guardar($conn, $empresa_id, $usuario_id, $_POST));
            break;
        case 'cumplir_hito':
            echo json_encode(inspeccion_hito_cumplir($conn, $empresa_id, intval($_POST['hito_id'] ?? 0), !empty($_POST['cumplido'])));
            break;
        case 'anular':
            echo json_encode(inspeccion_anular($conn, $empresa_id, intval($_POST['inspeccion_id'] ?? 0)));
            break;
        default:
            echo json_encode(insp_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION'));
    }
} catch (Throwable $e) {
    error_log('inspecciones_ajax: ' . $e->getMessage());
    echo json_encode(insp_resp(false, 'Error en el servidor.', null, 'SERVER_ERROR'));
}
