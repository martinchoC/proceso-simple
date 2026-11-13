<?php
/**
 * Controlador AJAX: Tablero entre clientes (dashboard del estudio). Solo lectura.
 * Página ID: 401
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 401]);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/estudio_tablero_clientes_model.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $datos = estudio_tablero_clientes($conn, (int) $ctx['empresa_id']);
    echo json_encode(['success' => true, 'message' => 'OK', 'data' => $datos, 'error' => null]);
} catch (Throwable $e) {
    error_log('estudio_tablero_clientes: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No se pudo cargar el tablero.', 'data' => null, 'error' => 'SERVER_ERROR']);
}
