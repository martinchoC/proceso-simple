<?php
declare(strict_types=1);
// Sesión, empresa y acceso a alguna de las pantallas de proveedores validados antes de cualquier otra cosa.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['proveedores.php', 'entidades.php']]);

/**
 * retenciones_proveedores_ajax.php — condición del proveedor frente a las retenciones que practica la empresa,
 * régimen habitual y certificados de exclusión. Respuesta: {success: bool, data: ..., message: string}
 */

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/retenciones_proveedores_model.php';

header('Content-Type: application/json; charset=utf-8');

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = (string)($_GET['accion'] ?? $_POST['accion'] ?? '');
$entidad_id = intval($_GET['entidad_id'] ?? $_POST['entidad_id'] ?? 0);

try {
    switch ($accion) {
        case 'datos':
            $r = ['success' => true, 'data' => rprovDatos($conexion, $empresa_id, $entidad_id), 'message' => ''];
            break;
        case 'agregar_condicion':
            rprovAgregarCondicion($conexion, $empresa_id, $entidad_id, $_POST, $usuario_id);
            $r = ['success' => true, 'data' => null, 'message' => 'Condición registrada.'];
            break;
        case 'inhabilitar_condicion':
            rprovInhabilitar($conexion, $empresa_id, 'condicion', intval($_POST['id'] ?? 0), $usuario_id);
            $r = ['success' => true, 'data' => null, 'message' => 'Condición inhabilitada.'];
            break;
        case 'agregar_exclusion':
            rprovAgregarExclusion($conexion, $empresa_id, $entidad_id, $_POST);
            $r = ['success' => true, 'data' => null, 'message' => 'Certificado de exclusión registrado.'];
            break;
        case 'inhabilitar_exclusion':
            rprovInhabilitar($conexion, $empresa_id, 'exclusion', intval($_POST['id'] ?? 0), $usuario_id);
            $r = ['success' => true, 'data' => null, 'message' => 'Certificado inhabilitado.'];
            break;
        default:
            http_response_code(400);
            $r = ['success' => false, 'data' => null, 'message' => 'Acción no válida.'];
    }
} catch (Throwable $e) {
    error_log('ERROR retenciones_proveedores_ajax: ' . $e->getMessage());
    $r = ['success' => false, 'data' => null, 'message' => $e->getMessage()];
}
echo json_encode($r, JSON_UNESCAPED_UNICODE);
