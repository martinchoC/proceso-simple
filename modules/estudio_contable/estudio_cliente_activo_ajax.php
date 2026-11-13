<?php
/**
 * AJAX: guarda en la sesión el cliente activo del estudio (ver estudio_cliente_activo.php). Solo clientes del estudio.
 * Acceso: cualquiera de las páginas que comparten el cliente activo.
 */
declare(strict_types=1);

require_once __DIR__ . '/estudio_cliente_activo.php';
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 4, 'paginas_url' => ESTUDIO_CLIENTE_ACTIVO_PAGINAS]);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../db.php';

$empresa_id = (int) $ctx['empresa_id'];
$cliente_id = intval($_POST['cliente_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['accion'] ?? '') !== 'guardar') {
    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.', 'data' => null]);
    exit;
}
if ($cliente_id > 0) {
    $st = mysqli_prepare($conn, "SELECT 1 FROM estudio__clientes WHERE empresa_id = ? AND cliente_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $cliente_id);
    mysqli_stmt_execute($st);
    $existe = (bool) mysqli_fetch_row(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$existe) {
        echo json_encode(['success' => false, 'message' => 'El cliente no existe.', 'data' => null]);
        exit;
    }
}
estudio_cliente_activo_set($empresa_id, $cliente_id);
echo json_encode(['success' => true, 'message' => 'OK', 'data' => ['cliente_id' => $cliente_id]]);
