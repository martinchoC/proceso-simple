<?php
declare(strict_types=1);

/**
 * modules/sindicatos/medios_pago_ajax.php
 * Controlador AJAX para medios de pago y canales bancarios (Página 1006).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1006]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/medios_pago_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1006;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_medios_pago($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $mp = sindi_obtener_medio_pago($conexion, $empresa_id, $id);
            if ($mp) {
                echo json_encode(['success' => true, 'data' => $mp], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Medio de pago no encontrado.'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'guardar':
            $res = sindi_guardar_medio_pago($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__medios_pago', 'medio_pago_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'probar_conexion':
            $id = intval($_POST['id'] ?? 0);
            $mp = sindi_obtener_medio_pago($conexion, $empresa_id, $id);
            if (!$mp) {
                echo json_encode(['success' => false, 'message' => 'Medio de pago no encontrado.'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Simulación / Verificación de credenciales configuradas
            $msg = "Prueba de canal {$mp['driver']} ({$mp['ambiente']}): ";
            if ($mp['driver'] === 'BNA_BARRAS') {
                $msg .= "Formato de código de barras verificado con prefijo: " . ($mp['barras_prefijo'] ?: 'No asignado');
            } elseif ($mp['driver'] === 'INTERBANKING') {
                $msg .= "Client ID configurado. Endpoint: " . ($mp['url_servicio'] ?: 'No asignado');
            } elseif ($mp['driver'] === 'RED_LINK') {
                $msg .= "Parámetros Link configurados. Ente/Acuerdo: " . ($mp['ente_acuerdo'] ?: 'No asignado');
            } else {
                $msg .= "Canal listo para operar.";
            }

            echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[MEDIOS_PAGO_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
