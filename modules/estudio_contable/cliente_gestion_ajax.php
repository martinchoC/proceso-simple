<?php
/**
 * Controlador AJAX: Gestión del Cliente (ficha del cliente del estudio). Solo lectura.
 * Módulo 4: Estudio Contable (cliente_gestion_ajax.php) - Página ID: 422
 * Respuesta: {success, message, data, error}.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 422]);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/cliente_gestion_model.php';

$estudio_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];
$accion = (string) ($_GET['accion'] ?? '');

function cg_salir(array $r): void
{
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($accion === 'clientes') {
        cg_salir(cg_resp(true, 'OK', cg_clientes($conn, $estudio_id)));
    }

    $cliente = cg_cliente($conn, $estudio_id, intval($_GET['cliente_id'] ?? 0));
    if (!$cliente) {
        cg_salir(cg_resp(false, 'El cliente no existe o está inactivo.', null, 'NOT_FOUND'));
    }

    switch ($accion) {
        case 'resumen':
            cg_salir(cg_resp(true, 'OK', cg_resumen($conn, $estudio_id, $usuario_id, $cliente)));
        case 'vencimientos':
            cg_salir(cg_resp(true, 'OK', cg_vencimientos($conn, $estudio_id, $cliente)));
        case 'tareas':
            cg_salir(cg_resp(true, 'OK', cg_tareas($conn, $estudio_id, $cliente)));
        case 'honorarios':
            cg_salir(cg_resp(true, 'OK', cg_honorarios($conn, $estudio_id, $cliente)));
        case 'inspecciones':
            cg_salir(cg_resp(true, 'OK', cg_inspecciones($conn, $estudio_id, $cliente)));
        case 'fiscal':
            cg_salir(cg_resp(true, 'OK', cg_fiscal($conn, $estudio_id, $cliente)));
        case 'contable':
            cg_salir(cg_resp(true, 'OK', cg_contable($conn, $estudio_id, $cliente)));
        case 'gestion':
            cg_salir(cg_resp(true, 'OK', cg_gestion($conn, $estudio_id, $cliente)));
        default:
            cg_salir(cg_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION'));
    }
} catch (mysqli_sql_exception $e) { // extiende RuntimeException: no mostrar el SQL al usuario
    error_log('cliente_gestion_ajax [' . $accion . '] SQL: ' . $e->getMessage());
    cg_salir(cg_resp(false, 'No se pudo cargar la información del cliente.', null, 'SERVER_ERROR'));
} catch (RuntimeException $e) {
    // Mensajes pensados para el usuario (servicio que no corresponde, vinculación faltante)
    cg_salir(cg_resp(false, $e->getMessage(), null, 'SIN_ACCESO'));
} catch (Throwable $e) {
    error_log('cliente_gestion_ajax [' . $accion . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    cg_salir(cg_resp(false, 'No se pudo cargar la información del cliente.', null, 'SERVER_ERROR'));
}
