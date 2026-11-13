<?php
/**
 * Controlador AJAX: Normas Legales y Motor de Reglas
 * Módulo 4: Estudio Contable (normas_gestion_ajax.php)
 * Página ID: 407
 */

declare(strict_types=1);

// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 407]);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/normas_gestion_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

$usuario_id = $ctx['usuario_id'];
$empresa_idx = $ctx['empresa_id'];
$pagina_idx = 407;

$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 407, (string)$accion, [
    'guardar' => (intval($_POST['norma_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'evaluar_impacto' => 'editar',
]);

switch ($accion) {
    case 'obtener_combos':
        $combos = normas_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar':
        $filtros = [
            'organismo' => $_GET['organismo'] ?? '',
            'estado_id' => $_GET['estado_id'] ?? ''
        ];
        $lista = normas_listar($conexion, $empresa_idx, $filtros);
        echo json_encode([
            'success' => true,
            'message' => 'Normas listadas.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'obtener':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $norma = norma_obtener($conexion, $empresa_idx, $norma_id);
        if (!$norma) {
            echo json_encode([
                'success' => false,
                'message' => 'Norma no encontrada.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Norma obtenida.',
            'data' => $norma,
            'error' => null
        ]);
        break;

    case 'guardar':
        $res = norma_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'evaluar_impacto':
        $norma_id = intval($_POST['norma_id'] ?? 0);
        $res = norma_evaluar_impacto($conexion, $empresa_idx, $norma_id, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_impacto':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $lista = norma_impacto_listar($conexion, $norma_id);
        echo json_encode([
            'success' => true,
            'message' => 'Bitácora de impacto listada.',
            'data' => $lista,
            'error' => null
        ]);
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
