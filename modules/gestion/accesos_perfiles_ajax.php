<?php
declare(strict_types=1);
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'modulo_id' => 2, 'paginas_url' => ['accesos_perfiles.php']]);

/**
 * accesos_perfiles_ajax.php
 * Endpoint JSON para administración de accesos por perfil a Sucursales / Bocas / Puntos de Venta
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/accesos_perfiles_model.php';

header('Content-Type: application/json; charset=utf-8');

ob_start();

function responderError(string $mensaje, int $httpCode = 400): never
{
    ob_end_clean();
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $mensaje, 'message' => $mensaje]);
    exit;
}

function responderOk(array $data = []): never
{
    ob_end_clean();
    echo json_encode(['success' => true] + $data);
    exit;
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logueado'])) {
    responderError('Sesión no válida o expirada', 401);
}

$usuarioId = (int)$_SESSION['usuario_id'];
$empresaId = (int)$ctx['empresa_id'];

if ($empresaId <= 0) {
    responderError('Empresa no especificada', 400);
}

$model = new AccesosPerfilesModel($conn);
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'listar_perfiles':
            responderOk(['data' => $model->listarPerfiles($empresaId)]);
            break;

        case 'listar_sucursales':
            responderOk(['data' => $model->listarSucursales($empresaId)]);
            break;

        case 'listar_bocas':
            $sucursalId = (int)($_POST['sucursal_id'] ?? 0);
            if ($sucursalId <= 0 || !$model->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
                responderError('Sucursal inválida');
            }
            responderOk(['data' => $model->listarBocas($sucursalId, $empresaId)]);
            break;

        case 'listar_puntos_venta':
            $sucursalId = (int)($_POST['sucursal_id'] ?? 0);
            $bocaId = !empty($_POST['boca_id']) ? (int)$_POST['boca_id'] : null;
            if ($sucursalId <= 0 || !$model->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
                responderError('Sucursal inválida');
            }
            responderOk(['data' => $model->listarPuntosVenta($sucursalId, $bocaId)]);
            break;

        case 'listar_tipos_comprobante':
            $sucursalId = (int)($_POST['sucursal_id'] ?? 0);
            $puntoVentaId = (int)($_POST['punto_venta_id'] ?? 0);
            if ($puntoVentaId <= 0 || !$model->puntoVentaPerteneceASucursal($puntoVentaId, $sucursalId)) {
                responderError('Punto de venta inválido');
            }
            responderOk(['data' => $model->listarTiposComprobante($puntoVentaId)]);
            break;

        case 'listar_grants':
            $empresaPerfilId = !empty($_POST['empresa_perfil_id']) ? (int)$_POST['empresa_perfil_id'] : null;
            if ($empresaPerfilId !== null && !$model->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
                responderError('Perfil no pertenece a la empresa');
            }
            responderOk(['data' => $model->listarGrants($empresaPerfilId, $empresaId)]);
            break;

        case 'agregar_grant':
            $empresaPerfilId = (int)($_POST['empresa_perfil_id'] ?? 0);
            $sucursalId = !empty($_POST['sucursal_id']) ? (int)$_POST['sucursal_id'] : null;
            $bocaId = !empty($_POST['boca_id']) ? (int)$_POST['boca_id'] : null;
            $puntoVentaId = !empty($_POST['punto_venta_id']) ? (int)$_POST['punto_venta_id'] : null;
            $comprobanteTipoId = !empty($_POST['comprobante_tipo_id']) ? (int)$_POST['comprobante_tipo_id'] : null;
            $esPredeterminado = !empty($_POST['es_predeterminado']) ? 1 : 0;

            if ($empresaPerfilId <= 0) {
                responderError('Debe seleccionar un perfil');
            }

            try {
                $id = $model->agregarGrant(
                    $empresaPerfilId,
                    $sucursalId,
                    $bocaId,
                    $puntoVentaId,
                    $comprobanteTipoId,
                    $esPredeterminado,
                    $empresaId,
                    $usuarioId
                );
                responderOk(['perfil_sucursal_id' => $id, 'message' => 'Acceso otorgado correctamente']);
            } catch (InvalidArgumentException $e) {
                responderError($e->getMessage());
            }
            break;

        case 'eliminar_grant':
            $empresaPerfilId = (int)($_POST['empresa_perfil_id'] ?? 0);
            $perfilSucursalId = (int)($_POST['perfil_sucursal_id'] ?? 0);
            if (!$model->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
                responderError('Perfil inválido');
            }
            if (!$model->eliminarGrant($perfilSucursalId, $empresaPerfilId)) {
                responderError('No se encontró el acceso a eliminar', 404);
            }
            responderOk(['message' => 'Acceso eliminado']);
            break;

        default:
            responderError('Acción no reconocida', 404);
    }
} catch (Throwable $e) {
    error_log('[accesos_perfiles_ajax] ' . $e->getMessage());
    responderError('Error interno del servidor', 500);
}
