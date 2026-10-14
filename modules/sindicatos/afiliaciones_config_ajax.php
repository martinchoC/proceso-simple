<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliaciones_config_ajax.php
 * Controlador AJAX de Tipos, Parentescos y Vigencias (Página 1054).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1054]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/afiliaciones_config_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$pagina_idx = 1054;

try {
    switch ($accion) {
        case 'listar':
            echo json_encode(['data' => sindi_afcfg_listar_tipos($conexion, $empresa_id, $pagina_idx)], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_tipo':
            sindi_exigir_funcion($conexion, $pagina_idx, 'editar');
            echo json_encode(sindi_afcfg_guardar_tipo($conexion, $empresa_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $res = sindi_ejecutar_transicion($conexion, 'sindi__afiliaciones_tipos', 'afiliacion_tipo_id', (int)($_POST['id'] ?? 0),
                (string)($_POST['accion_js'] ?? ''), $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'catalogos':
            echo json_encode(['success' => true, 'data' => sindi_afcfg_catalogos($conexion, $empresa_id),
                              'puede_editar' => sindi_usuario_puede($conexion, $pagina_idx, 'catalogos')], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_parentesco':
            sindi_exigir_funcion($conexion, $pagina_idx, 'catalogos');
            echo json_encode(sindi_afcfg_guardar_parentesco($conexion, $empresa_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_vigencia':
            sindi_exigir_funcion($conexion, $pagina_idx, 'catalogos');
            echo json_encode(sindi_afcfg_guardar_vigencia($conexion, $empresa_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[AFIL_CONFIG_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
