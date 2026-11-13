<?php
/**
 * Controlador AJAX: Facturación Masiva de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion_ajax.php)
 * Página ID: 406
 */

declare(strict_types=1);

// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 406]);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/honorarios_facturacion_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $ctx['usuario_id'];
$empresa_idx = $ctx['empresa_id'];
$pagina_idx = 406;

$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 406, (string)$accion, [
    'emitir_lote' => 'agregar',
]);

switch ($accion) {
    case 'consolidar':
        $periodo = intval($_GET['periodo_fiscal'] ?? 0);
        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido.',
                'data' => null,
                'error' => 'INVALID_PERIOD'
            ]);
            exit;
        }

        $items = honorarios_facturacion_consolidar($conexion, $empresa_idx, $periodo);
        echo json_encode([
            'success' => true,
            'message' => 'Abonos consolidados para el período.',
            'data' => $items,
            'error' => null
        ]);
        break;

    case 'emitir_lote':
        $periodo = intval($_POST['periodo_fiscal'] ?? 0);
        $abonos = $_POST['abonos'] ?? [];

        if (!is_array($abonos) || empty($abonos)) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe seleccionar al menos un abono para emitir.',
                'data' => null,
                'error' => 'NO_SELECTION'
            ]);
            exit;
        }

        $abono_ids = array_map('intval', $abonos);
        $res = honorarios_facturacion_emitir_lote($conexion, $empresa_idx, $periodo, $abono_ids, $usuario_id);
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
