<?php
// Consulta de padrón de ARCA por CUIT (botón "Buscar en ARCA" de Clientes, Proveedores y Entidades, assets/js/arca-padron.js).
// Lógica en core/arca_padron_service.php. Script: _local/sql/mantenimiento/20261104_arca_padron_01.sql
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['clientes.php', 'proveedores.php', 'entidades.php']]);
ob_start();   // antes de incluir los modelos: un aviso al cargarlos no rompe el JSON

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/arca_padron_service.php';

function arcaPadronResponder(bool $ok, $data = null, string $mensaje = ''): void
{
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = intval($ctx['empresa_id']);
try {
    switch ($_GET['accion'] ?? '') {
        case 'estado':
            arcaPadronResponder(true, ['disponible' => arca_padron_disponible($conn, $empresa_id)]);
        case 'consultar':
            @set_time_limit(90);
            arcaPadronResponder(true, arca_padron_consultar_erp($conn, $empresa_id, (string)($_GET['cuit'] ?? '')));
    }
    arcaPadronResponder(false, null, 'Acción no válida.');
} catch (RuntimeException $e) {
    arcaPadronResponder(false, null, $e->getMessage());
} catch (Throwable $e) {
    error_log('arca_padron_ajax: ' . $e);
    arcaPadronResponder(false, null, 'Error interno al consultar ARCA.');
}
