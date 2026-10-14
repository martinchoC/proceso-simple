<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliados_ajax.php
 * Controlador AJAX de Afiliados y Grupo Familiar (Página 1050).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1050]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/afiliados_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1050;

try {
    switch ($accion) {
        case 'listar':
            $filtros = ['tipo_codigo' => $_GET['tipo_codigo'] ?? '', 'estado' => $_GET['estado'] ?? '', 'texto' => $_GET['texto'] ?? ''];
            echo json_encode(['data' => sindi_listar_afiliados($conexion, $empresa_id, $pagina_idx, $filtros)], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(sindi_obtener_boton_agregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'combos':
            echo json_encode(['success' => true, 'data' => sindi_afiliados_combos($conexion, $empresa_id, $usuario_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'ficha':
            sindi_exigir_funcion($conexion, $pagina_idx, 'ver');
            echo json_encode(['success' => true, 'data' => sindi_obtener_ficha_afiliado($conexion, $empresa_id, (int)($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;

        case 'alta_titular':
            sindi_exigir_funcion($conexion, $pagina_idx, 'agregar');
            echo json_encode(sindi_afiliados_alta_titular($conexion, $empresa_id, $usuario_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'alta_familiar':
            sindi_exigir_funcion($conexion, $pagina_idx, 'agregar_familiar');
            echo json_encode(sindi_afiliados_alta_familiar($conexion, $empresa_id, $usuario_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'documento':
            sindi_exigir_funcion($conexion, $pagina_idx, 'documento');
            echo json_encode(sindi_afiliados_documento($conexion, $empresa_id, $usuario_id, $_POST, $_FILES['archivo'] ?? null), JSON_UNESCAPED_UNICODE);
            break;

        case 'baja':
            sindi_exigir_funcion($conexion, $pagina_idx, 'baja');
            echo json_encode(sindi_afiliados_baja($conexion, $empresa_id, $usuario_id, $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'generar_cuotas':
            // Normalmente lo hace el cron; a pedido, con la función de alta
            sindi_exigir_funcion($conexion, $pagina_idx, 'agregar');
            $periodo = (int)($_POST['periodo'] ?? date('Ym'));
            $res = sindi_afil_generar_cuotas($conexion, $empresa_id, $usuario_id, $periodo);
            echo json_encode(['success' => empty($res['errores']), 'message' => "Cuotas generadas: {$res['generadas']}.", 'data' => $res], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[AFILIADOS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
