<?php
/**
 * Controlador AJAX: Tarifarios y Conceptos de Honorarios
 * Módulo 4: Estudio Contable (honorarios_conceptos_ajax.php)
 * Página ID: 405
 */

declare(strict_types=1);

// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 405]);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/honorarios_conceptos_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $ctx['usuario_id'];
$empresa_idx = $ctx['empresa_id'];
$pagina_idx = 405;

$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 405, (string)$accion, [
    'guardar_concepto' => (intval($_POST['concepto_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'guardar_abono' => (intval($_POST['abono_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'eliminar_abono' => 'inhabilitar',
]);

switch ($accion) {
    case 'obtener_combos':
        $combos = honorarios_conceptos_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar_conceptos':
        $lista = honorarios_conceptos_listar($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Conceptos listados.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'guardar_concepto':
        $res = honorarios_concepto_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'listar_abonos':
        $cliente_id = intval($_GET['cliente_id'] ?? 0);
        $lista = clientes_abonos_listar($conexion, $empresa_idx, $cliente_id);
        echo json_encode([
            'success' => true,
            'message' => 'Abonos listados.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'guardar_abono':
        $res = cliente_abono_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'eliminar_abono':
        $abono_id = intval($_POST['abono_id'] ?? 0);
        $res = cliente_abono_eliminar($conexion, $empresa_idx, $abono_id);
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
