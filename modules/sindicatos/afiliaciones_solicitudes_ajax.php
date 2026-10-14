<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliaciones_solicitudes_ajax.php
 * Controlador AJAX de Solicitudes de Afiliación (Página 1051).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1051]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/afiliaciones_solicitudes_model.php';
require_once __DIR__ . '/afiliados_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1051;

try {
    switch ($accion) {
        case 'listar':
            $estado = !empty($_GET['estado']) ? (int)$_GET['estado'] : null;
            echo json_encode(['data' => sindi_listar_solicitudes_afiliacion($conexion, $empresa_id, $pagina_idx, $estado)], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(sindi_obtener_boton_agregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'combos':
            echo json_encode(['success' => true, 'data' => sindi_afiliados_combos($conexion, $empresa_id, $usuario_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'buscar_titulares':
            echo json_encode(['success' => true, 'data' => sindi_buscar_titulares_vigentes($conexion, $empresa_id, (string)($_GET['q'] ?? ''))], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            echo json_encode(['success' => true, 'data' => sindi_obtener_solicitud_afiliacion($conexion, $empresa_id, (int)($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            sindi_exigir_funcion($conexion, $pagina_idx, ((int)($_POST['solicitud_id'] ?? 0)) > 0 ? 'editar' : 'agregar');
            echo json_encode(sindi_guardar_solicitud_afiliacion($conexion, $empresa_id, $usuario_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            echo json_encode(sindi_transicion_solicitud_afiliacion($conexion, $empresa_id, $usuario_id, (int)($_POST['id'] ?? 0),
                (string)($_POST['accion_js'] ?? ''), (string)($_POST['motivo'] ?? '')), JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[AFIL_SOLICITUDES_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
