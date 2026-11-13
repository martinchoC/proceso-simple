<?php
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
// La página no tiene permisos para perfiles: en la práctica solo la usa el superadmin (catálogo global).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 124]);
/**
 * regimenes_retencion_ajax.php — catálogo global de regímenes de retención.
 * Respuesta: {success: bool, data: ..., message: string}
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/regimenes_retencion_model.php';

header('Content-Type: application/json; charset=utf-8');

$pagina_idx = 124;
$usuario_id = intval($ctx['usuario_id']);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

function rregResponder($r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            rregResponder(['success' => true, 'data' => rregListar($conexion, $pagina_idx), 'message' => '']);

        case 'catalogos':
            rregResponder(['success' => true, 'data' => rregCatalogos($conexion), 'message' => '']);

        case 'obtener_boton_agregar':
            rregResponder(['success' => true, 'data' => rregBotonAgregar($conexion, $pagina_idx), 'message' => '']);

        case 'obtener':
            $r = rregObtener($conexion, intval($_GET['regimen_id'] ?? $_POST['regimen_id'] ?? 0));
            rregResponder($r ? ['success' => true, 'data' => $r, 'message' => ''] : ['success' => false, 'message' => 'Régimen no encontrado.']);

        case 'agregar':
        case 'editar':
            // Si la página tiene la función, el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), $accion === 'agregar' ? 'agregar' : 'editar', true);
            $data = $_POST;
            if ($accion === 'agregar') $data['regimen_id'] = 0;
            rregResponder(rregGuardar($conexion, $data, $usuario_id));

        case 'ejecutar_accion':
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)((string)($_POST['accion_js'] ?? '')));
            rregResponder(rregEjecutarAccion($conexion, intval($_POST['regimen_id'] ?? 0), (string)($_POST['accion_js'] ?? ''),
                $pagina_idx, $usuario_id));

        default:
            http_response_code(400);
            rregResponder(['success' => false, 'message' => 'Acción no válida.']);
    }
} catch (Throwable $e) {
    error_log('ERROR regimenes_retencion_ajax: ' . $e->getMessage());
    http_response_code(500);
    rregResponder(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
