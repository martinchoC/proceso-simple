<?php
/**
 * AJAX: Contabilidad del Cliente (página 423): balance, sumas y saldos, mayores y asientos de la empresa del cliente en
 * el ERP. Exige vinculación de contabilidad (lectura) salvo que el cliente sea el propio estudio.
 * Editar un asiento (asiento_guardar, POST) exige la función "editar" de la página, vinculación con escritura y el
 * período contable abierto (ccon_asiento_guardar). Un asiento automático solo cambia la cuenta de los renglones de
 * productos (asiento_cuentas_guardar, mismas exigencias; core/cont_asientos_edicion.php).
 * Respuesta: {success, message, data, error}.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 423]);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/cliente_contabilidad_model.php';

$estudio_id = (int) $ctx['empresa_id'];
$accion = (string) ($_REQUEST['accion'] ?? '');
permisos_exigir_accion($conn, 423, $accion, ['asiento_guardar' => 'editar', 'asiento_cuentas_guardar' => 'editar']);

function ccon_resp(bool $ok, string $msg, $data = null, ?string $err = null): void
{
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data, 'error' => $err], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($accion === 'clientes') {
        ccon_resp(true, 'OK', estudio_clientes_con_empresa($conn, $estudio_id, 'contabilidad'));
    }

    $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
    if ($accion === 'asiento_guardar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ccon_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION');
        }
        $cli = estudio_cliente_empresa($conn, $estudio_id, $cliente_id, 'contabilidad', true);
        $lineas = json_decode((string) ($_POST['lineas'] ?? '[]'), true);
        ccon_resp(true, 'Asiento guardado.', ccon_asiento_guardar($conn, $cli['empresa_id'], (int) $ctx['usuario_id'],
            intval($_POST['asiento_id'] ?? 0), (string) ($_POST['fecha'] ?? ''), (string) ($_POST['descripcion'] ?? ''),
            is_array($lineas) ? $lineas : []));
    }
    if ($accion === 'asiento_cuentas_guardar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ccon_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION');
        }
        $cli = estudio_cliente_empresa($conn, $estudio_id, $cliente_id, 'contabilidad', true);
        $cambios = json_decode((string) ($_POST['cambios'] ?? '[]'), true);
        ccon_resp(true, 'Asiento guardado.', ccon_asiento_cambiar_cuentas($conn, $cli['empresa_id'], (int) $ctx['usuario_id'],
            intval($_POST['asiento_id'] ?? 0), is_array($cambios) ? $cambios : []));
    }

    $cli = estudio_cliente_empresa($conn, $estudio_id, $cliente_id, 'contabilidad', false);
    $emp = $cli['empresa_id'];
    if ($accion === 'periodo') {
        ccon_resp(true, 'OK', ccon_periodo($conn, $emp, intval($_GET['ejercicio'] ?? 0)));
    }

    $desde = ccon_fecha($_GET['desde'] ?? null, date('Y-01-01'));
    $hasta = ccon_fecha($_GET['hasta'] ?? null, date('Y-12-31'));
    if ($desde > $hasta) {
        ccon_resp(false, 'La fecha desde es posterior a la fecha hasta.', null, 'VALIDATION');
    }
    $borradores = !empty($_GET['borradores']);

    switch ($accion) {
        case 'balance':
            ccon_resp(true, 'OK', ccon_balance($conn, $emp, $desde, $hasta, $borradores));
        case 'mayor_cuentas':
            ccon_resp(true, 'OK', ccon_mayor_cuentas($conn, $emp, $desde, $hasta));
        case 'mayor_detalle':
            ccon_resp(true, 'OK', ccon_mayor_detalle($conn, $emp, intval($_GET['cuenta_id'] ?? 0), $desde, $hasta));
        case 'asientos':
            $estado = $borradores ? intval($_GET['estado'] ?? 0) : 1;
            ccon_resp(true, 'OK', ccon_asientos($conn, $emp, $desde, $hasta, $estado, (string) ($_GET['texto'] ?? '')));
        case 'asiento_detalle':
            $d = ccon_asiento_detalle($conn, $emp, intval($_GET['asiento_id'] ?? 0));
            // Puede editar: función de la página + vinculación con escritura + período abierto (lo último lo dice el modelo)
            if ($d['edicion']['puede']) {
                $motivo = null;
                if (!permisos_puede($conn, 423, 'editar')) {
                    $motivo = 'Tu perfil no tiene permiso para editar asientos.';
                } else {
                    try {
                        estudio_cliente_empresa($conn, $estudio_id, $cliente_id, 'contabilidad', true);
                    } catch (RuntimeException $e) {
                        $motivo = $e->getMessage();
                    }
                }
                $d['edicion'] = ['puede' => $motivo === null, 'motivo' => $motivo];
            }
            ccon_resp(true, 'OK', $d);
        case 'cuentas':
            ccon_resp(true, 'OK', ccon_cuentas_imputables($conn, $emp));
        case 'plan':
            ccon_resp(true, 'OK', ccon_plan_cuentas($conn, $emp));
        default:
            ccon_resp(false, 'Acción no reconocida.', null, 'INVALID_ACTION');
    }
} catch (mysqli_sql_exception $e) {
    error_log("cliente_contabilidad_ajax [$accion] SQL: " . $e->getMessage());
    ccon_resp(false, 'No se pudo cargar la contabilidad.', null, 'SERVER_ERROR');
} catch (RuntimeException $e) {
    ccon_resp(false, $e->getMessage(), null, 'VALIDATION');
} catch (Throwable $e) {
    error_log("cliente_contabilidad_ajax [$accion]: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    ccon_resp(false, 'No se pudo cargar la contabilidad.', null, 'SERVER_ERROR');
}
