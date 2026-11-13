<?php
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 134;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);

/**
 * cont_periodos_cierres_ajax.php — Cierre de Períodos Contables (página 134).
 * Respuesta: {success: bool, data: ..., message: string}.
 */
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/cont_periodos_cierres_model.php';

ob_start();

function cpcResponder($ok, $data = null, $mensaje = '', $http = 200)
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');
$req = $_POST + $_GET;

try {
    switch ($accion) {
        case 'maestros':
            $botones = cpcBotonesUsuario($conexion, $usuario_id, $empresa_id, $pagina_idx);
            cpcResponder(true, [
                'meses' => CONT_PERIODO_MESES,
                'ejercicios' => cont_ejercicios_lista($conexion, $empresa_id),
                'ejercicio_actual' => cont_ejercicio_de_fecha($conexion, $empresa_id, date('Y-m-d')),
                'mes_cierre' => cont_mes_cierre($conexion, $empresa_id, date('Y-m-d')),
                'boton_cerrar' => $botones[0][0] ?? null,
                'boton_reabrir' => $botones[CONT_PERIODO_CERRADO][0] ?? null,
                'disponible' => cont_periodo_disponible($conexion),
            ]);
            break;

        case 'obtener_boton_agregar':
            $botones = cpcBotonesUsuario($conexion, $usuario_id, $empresa_id, $pagina_idx);
            cpcResponder(true, $botones[0][0] ?? null);
            break;

        case 'resumen':
            cpcResponder(true, cont_periodo_resumen_ejercicio($conexion, $empresa_id,
                                                              intval($req['ejercicio'] ?? cont_ejercicio_de_fecha($conexion, $empresa_id, date('Y-m-d')))));
            break;

        case 'listar':
            cpcResponder(true, cont_periodo_listar($conexion, $empresa_id, intval($req['ejercicio'] ?? 0),
                                                   !empty($req['incluir_reabiertos']) && $req['incluir_reabiertos'] !== '0'));
            break;

        case 'cerrar':
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, 'cerrar')) {
                cpcResponder(false, null, 'No tiene permiso para cerrar períodos.', 403);
            }
            // mes > 0: ese mes (anio + mes); si no, los meses abiertos de todo el ejercicio
            $obs = (string)($_POST['observaciones'] ?? '');
            $forzar = !empty($_POST['forzar']) && $_POST['forzar'] !== '0';
            $ejercicio = intval($_POST['ejercicio'] ?? 0);
            $r = intval($_POST['mes'] ?? 0) > 0
                ? cont_periodo_cerrar($conexion, $empresa_id, $usuario_id, intval($_POST['anio'] ?? 0), intval($_POST['mes']), $obs, $forzar)
                : cont_periodo_cerrar_ejercicio($conexion, $empresa_id, $usuario_id, $ejercicio, $obs, $forzar);
            if ($r['requiere_confirmacion']) {
                cpcResponder(true, $r, 'Hay asientos en borrador en el período.');
            }
            $txt = count($r['cerrados']) === 1
                ? 'Período ' . cont_periodo_texto($r['cerrados'][0]['anio'], $r['cerrados'][0]['mes']) . ' cerrado.'
                : count($r['cerrados']) . " meses del ejercicio $ejercicio cerrados.";
            cpcResponder(true, $r, $txt);
            break;

        case 'reabrir':
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, 'reabrir')) {
                cpcResponder(false, null, 'No tiene permiso para reabrir períodos.', 403);
            }
            $r = cont_periodo_reabrir($conexion, $empresa_id, $usuario_id, intval($_POST['cierre_id'] ?? 0), (string)($_POST['motivo'] ?? ''));
            cpcResponder(true, $r, "Período {$r['periodo']} reabierto.");
            break;

        default:
            cpcResponder(false, null, 'Acción no válida.', 400);
    }
} catch (RuntimeException $e) {
    cpcResponder(false, null, $e->getMessage());
} catch (Throwable $e) {
    error_log('ERROR cont_periodos_cierres_ajax (' . $accion . '): ' . $e->getMessage());
    cpcResponder(false, null, 'No se pudo completar la operación: ' . $e->getMessage());
}
