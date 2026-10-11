<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 612]);

/**
 * stock_niveles_ajax.php — Stock mínimo / máximo por boca (página 112).
 * Respuesta: {success: bool, data: ..., message: string}
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/stock_niveles_model.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

function snvResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina_idx = 612;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'listar':
            snvResponder(true, snvListar($conexion, $empresa_id, $pagina_idx, $usuario_id, (int)($_GET['boca_id'] ?? $_POST['boca_id'] ?? 0)));
            break;

        case 'obtener_boton_agregar':
            snvResponder(true, snvBotonesCreacion($conexion, $pagina_idx));
            break;

        case 'obtener_bocas':
            snvResponder(true, snvBocas($conexion, $empresa_id, $usuario_id));
            break;

        case 'buscar_productos':
            snvResponder(true, snvBuscarProductos($conexion, $empresa_id, (string)($_GET['q'] ?? $_POST['q'] ?? '')));
            break;

        case 'obtener':
            $nivel = snvObtener($conexion, $empresa_id, (int)($_GET['stock_nivel_id'] ?? $_POST['stock_nivel_id'] ?? 0));
            if (!$nivel) snvResponder(false, null, 'Nivel no encontrado', 404);
            snvExigirBoca($conexion, $empresa_id, $usuario_id, (int)$nivel['boca_id']);
            snvResponder(true, $nivel);
            break;

        case 'agregar':
            if (!snvFuncionCreacionExiste($conexion, $pagina_idx, 'agregar')) snvResponder(false, null, 'Acción no habilitada', 403);
            snvResponder(true, snvAgregar($conexion, $empresa_id, $usuario_id, $_POST), 'Nivel cargado');
            break;

        case 'editar':
            snvEditar($conexion, $empresa_id, $usuario_id, $pagina_idx, (int)($_POST['stock_nivel_id'] ?? 0), $_POST);
            snvResponder(true, null, 'Nivel actualizado');
            break;

        case 'ejecutar_accion':
            $nombre = snvEjecutarAccion($conexion, $empresa_id, $usuario_id, $pagina_idx,
                (int)($_POST['stock_nivel_id'] ?? 0), (string)($_POST['accion_js'] ?? ''));
            snvResponder(true, null, "$nombre: listo");
            break;

        case 'copiar_boca':
            if (!snvFuncionCreacionExiste($conexion, $pagina_idx, 'copiar_boca')) snvResponder(false, null, 'Acción no habilitada', 403);
            $r = snvCopiarBoca($conexion, $empresa_id, $usuario_id, (int)($_POST['boca_origen_id'] ?? 0),
                (int)($_POST['boca_destino_id'] ?? 0), !empty($_POST['sobrescribir']));
            snvResponder(true, $r, "Copiados: {$r['insertados']} nuevos, {$r['actualizados']} actualizados, {$r['salteados']} ya existían");
            break;

        default:
            snvResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[stock_niveles_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    snvResponder(false, null, 'Error de base de datos', 500);
} catch (InvalidArgumentException | RuntimeException $e) {
    snvResponder(false, null, $e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('[stock_niveles_ajax] ' . $accion . ': ' . $e->getMessage());
    snvResponder(false, null, 'Error interno del servidor', 500);
}
