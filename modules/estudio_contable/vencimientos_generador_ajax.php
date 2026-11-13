<?php
/**
 * Controlador AJAX: Generador Masivo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_generador_ajax.php)
 * Página ID: 404
 */

declare(strict_types=1);

// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 404]);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/vencimientos_generador_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $ctx['usuario_id'];
$empresa_idx = $ctx['empresa_id'];
$pagina_idx = 404;

$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 404, (string)$accion, [
    'ejecutar' => 'agregar',
]);

switch ($accion) {
    case 'obtener_combos':
        $combos = vencimientos_generador_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'preview':
        $periodo = intval($_GET['periodo_fiscal'] ?? 0);
        $obligacion_id = intval($_GET['impuesto_obligacion_id'] ?? 0);
        $cliente_id = intval($_GET['cliente_id'] ?? 0);

        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido. Formato requerido: YYYYMM (Ej: 202610)',
                'data' => null,
                'error' => 'INVALID_PERIODO'
            ]);
            exit;
        }

        $items = vencimientos_generador_preview($conexion, $empresa_idx, $periodo, $obligacion_id, $cliente_id);
        echo json_encode([
            'success' => true,
            'message' => 'Vista previa generada.',
            'data' => $items,
            'error' => null
        ]);
        break;

    case 'ejecutar':
        $periodo = intval($_POST['periodo_fiscal'] ?? 0);
        $obligacion_id = intval($_POST['impuesto_obligacion_id'] ?? 0);
        $cliente_id = intval($_POST['cliente_id'] ?? 0);

        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido.',
                'data' => null,
                'error' => 'INVALID_PERIODO'
            ]);
            exit;
        }

        $res = vencimientos_generador_ejecutar($conexion, $empresa_idx, $periodo, $obligacion_id, $cliente_id, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => "Acción '{$accion}' no reconocida.",
            'data' => null,
            'error' => 'UNKNOWN_ACTION'
        ]);
        break;
}
