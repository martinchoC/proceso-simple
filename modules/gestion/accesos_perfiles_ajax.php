<?php

declare(strict_types=1);

/**
 * accesos_perfiles_ajax.php
 * Endpoint JSON consumido de forma asíncrona por accesos_perfiles.js
 *
 * ASUMIDO (ajustar a los includes/nombres reales del sistema):
 *   - require de sesión: setea $_SESSION y corta si no hay login activo
 *   - require de conexión: deja disponible $conexion (mysqli)
 *   - $_SESSION['usuario_id'], $_SESSION['empresa_id'], $_SESSION['csrf_token']
 */
require_once __DIR__ . '/../sesion.php';   // ASUMIDO
require_once __DIR__ . '/../conexion.php'; // ASUMIDO — deja $conexion (mysqli) disponible
require_once __DIR__ . '/accesos_perfiles_model.php';

header('Content-Type: application/json; charset=utf-8');

// display_errors=1 + error_reporting(E_ALL) están activos en AJAX (documentado como vector
// de bug recurrente): un warning impreso antes del json_encode rompe la respuesta. Se
// bufferea la salida y se descarta cualquier cosa que no sea el JSON de las funciones responder*.
ob_start();

function responderError(string $mensaje, int $httpCode = 400): never
{
    ob_end_clean();
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => $mensaje]);
    exit;
}

function responderOk(array $data = []): never
{
    ob_end_clean();
    echo json_encode(['ok' => true] + $data);
    exit;
}

if (empty($_SESSION['usuario_id'])) { // ASUMIDO: nombre real de la variable de sesión de login
    responderError('Sesión no válida', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responderError('Método no permitido', 405);
}

// ASUMIDO: token CSRF generado por el sistema y expuesto en el <meta> de la vista
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    responderError('Token inválido', 403);
}

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0); // ASUMIDO
$accion = (string) ($_POST['accion'] ?? '');

if ($empresaId <= 0) {
    responderError('No hay empresa activa en la sesión', 400);
}

$model = new AccesosPerfilesModel($conexion);

try {
    switch ($accion) {
        case 'listar_perfiles':
            responderOk(['data' => $model->listarPerfiles($empresaId)]);
            break;

        case 'listar_sucursales':
            responderOk(['data' => $model->listarSucursales($empresaId)]);
            break;

        case 'listar_puntos_venta':
            $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
            if ($sucursalId <= 0 || !$model->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
                responderError('Sucursal inválida');
            }
            responderOk(['data' => $model->listarPuntosVenta($sucursalId)]);
            break;

        case 'listar_tipos_comprobante':
            $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
            $puntoVentaId = (int) ($_POST['punto_venta_id'] ?? 0);
            if ($puntoVentaId <= 0 || !$model->puntoVentaPerteneceASucursal($puntoVentaId, $sucursalId)) {
                responderError('Punto de venta inválido');
            }
            responderOk(['data' => $model->listarTiposComprobante($puntoVentaId)]);
            break;

        case 'listar_grants':
            $empresaPerfilId = (int) ($_POST['empresa_perfil_id'] ?? 0);
            if (!$model->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
                responderError('Perfil inválido');
            }
            responderOk(['data' => $model->listarGrants($empresaPerfilId)]);
            break;

        case 'agregar_grant':
            $empresaPerfilId = (int) ($_POST['empresa_perfil_id'] ?? 0);
            $sucursalId = (int) ($_POST['sucursal_id'] ?? 0);
            $puntoVentaId = (isset($_POST['punto_venta_id']) && $_POST['punto_venta_id'] !== '')
                ? (int) $_POST['punto_venta_id'] : null;
            $comprobanteTipoId = (isset($_POST['comprobante_tipo_id']) && $_POST['comprobante_tipo_id'] !== '')
                ? (int) $_POST['comprobante_tipo_id'] : null;

            if ($empresaPerfilId <= 0 || $sucursalId <= 0) {
                responderError('Faltan datos obligatorios');
            }

            try {
                $id = $model->agregarGrant(
                    $empresaPerfilId,
                    $sucursalId,
                    $puntoVentaId,
                    $comprobanteTipoId,
                    $empresaId
                );
                responderOk(['perfil_sucursal_id' => $id]);
            } catch (InvalidArgumentException $e) {
                responderError($e->getMessage());
            }
            break;

        case 'eliminar_grant':
            $empresaPerfilId = (int) ($_POST['empresa_perfil_id'] ?? 0);
            $perfilSucursalId = (int) ($_POST['perfil_sucursal_id'] ?? 0);
            if (!$model->perfilPerteneceAEmpresa($empresaPerfilId, $empresaId)) {
                responderError('Perfil inválido');
            }
            if (!$model->eliminarGrant($perfilSucursalId, $empresaPerfilId)) {
                responderError('No se encontró el acceso a eliminar', 404);
            }
            responderOk();
            break;

        default:
            responderError('Acción no reconocida', 404);
    }
} catch (Throwable $e) {
    error_log('[accesos_perfiles_ajax] ' . $e->getMessage()); // nunca exponer el detalle al cliente
    responderError('Error interno', 500);
}
