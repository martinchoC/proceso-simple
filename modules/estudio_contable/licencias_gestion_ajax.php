<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_idx'], 'pagina_idx' => 409]);
/**
 * Controlador AJAX: Wizard de Licencias y Delegación Automática
 * Módulo 4: Estudio Contable (licencias_gestion_ajax.php)
 * Página ID: 409
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/licencias_gestion_model.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 409, (string)$action, [
    'registrar' => 'agregar',
    'revertir' => 'inhabilitar',
]);

try {
    switch ($action) {
        case 'combos':
            $combos = licencias_combos($conn, $empresa_id);
            echo json_encode([
                'success' => true,
                'message' => 'Combos obtenidos.',
                'data' => $combos,
                'error' => null
            ]);
            break;

        case 'listar':
            $filtros = [
                'usuario_id' => $_POST['usuario_id'] ?? $_GET['usuario_id'] ?? '',
                'tipo_licencia' => $_POST['tipo_licencia'] ?? $_GET['tipo_licencia'] ?? ''
            ];
            $lista = licencias_listar($conn, $empresa_id, $filtros);
            echo json_encode([
                'success' => true,
                'message' => 'Licencias obtenidas.',
                'data' => $lista,
                'error' => null
            ]);
            break;

        case 'preview_afectadas':
            $u_id = intval($_POST['usuario_id'] ?? 0);
            $f_desde = trim($_POST['f_desde'] ?? '');
            $f_hasta = trim($_POST['f_hasta'] ?? '');
            if ($u_id <= 0 || empty($f_desde) || empty($f_hasta)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario y rango de fechas requeridos para la vista previa.',
                    'data' => [],
                    'error' => 'VALIDATION'
                ]);
                break;
            }
            $tareas = licencias_preview_tareas_afectadas($conn, $empresa_id, $u_id, $f_desde, $f_hasta);
            echo json_encode([
                'success' => true,
                'message' => 'Vista previa de tareas calculada.',
                'data' => $tareas,
                'error' => null
            ]);
            break;

        case 'registrar':
            $datos = [
                'usuario_id' => $_POST['usuario_id'] ?? 0,
                'tipo_licencia' => $_POST['tipo_licencia'] ?? 'VACACIONES',
                'f_desde' => $_POST['f_desde'] ?? '',
                'f_hasta' => $_POST['f_hasta'] ?? '',
                'usuario_suplente_r_id' => $_POST['usuario_suplente_r_id'] ?? null,
                'usuario_suplente_a_id' => $_POST['usuario_suplente_a_id'] ?? null,
                'observaciones' => $_POST['observaciones'] ?? ''
            ];
            $res = licencia_registrar_y_delegar($conn, $empresa_id, $datos);
            echo json_encode($res);
            break;

        case 'revertir':
            $licencia_id = intval($_POST['licencia_id'] ?? 0);
            $res = licencia_revertir_delegaciones($conn, $empresa_id, $licencia_id);
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
