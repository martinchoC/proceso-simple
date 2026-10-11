<?php
/**
 * cont__balances_ajax.php
 *
 * Dispatcher AJAX para el Dashboard de Balance Contable y Listados de Sumas y Saldos.
 * Sistema gestion_multipyme - Módulo 2 Gestión
 */

// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['cont__balances.php']]);

error_reporting(E_ALL);
ini_set('display_errors', 0);

function responderJson($data, $codigo = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function responderError($mensaje, $codigo = 400) {
    responderJson([
        'success' => false,
        'resultado' => false,
        'error' => $mensaje,
        'message' => $mensaje
    ], $codigo);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/cont__balances_model.php';

if (!isset($conn) || !$conn) {
    responderError('Error de conexión a la base de datos', 500);
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    responderError('Acción no especificada');
}

$empresa_idx = intval($ctx['empresa_id']);

try {
    switch ($accion) {
        case 'obtener_ejercicios':
            $ejercicios = obtenerEjerciciosContables($conn, $empresa_idx);
            responderJson([
                'success' => true,
                'data' => $ejercicios
            ]);
            break;

        case 'obtener_balance':
            $fecha_desde = $_GET['fecha_desde'] ?? $_POST['fecha_desde'] ?? date('Y-01-01');
            $fecha_hasta = $_GET['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? date('Y-12-31');
            $incluir_borradores = filter_var($_GET['incluir_borradores'] ?? $_POST['incluir_borradores'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $resultado = obtenerBalanceCompleto($conn, $empresa_idx, $fecha_desde, $fecha_hasta, $incluir_borradores);
            if (!$resultado['success']) {
                responderError($resultado['error'] ?? 'Error al procesar el balance contable', 500);
            }

            responderJson($resultado);
            break;

        case 'obtener_sumas_saldos':
            $fecha_desde = $_GET['fecha_desde'] ?? $_POST['fecha_desde'] ?? date('Y-01-01');
            $fecha_hasta = $_GET['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? date('Y-12-31');
            $incluir_borradores = filter_var($_GET['incluir_borradores'] ?? $_POST['incluir_borradores'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $modo = $_GET['modo'] ?? 'analitico'; // analitico, sintetico_nivel1, sintetico_nivel2, sintetico_nivel3, arbol
            $solo_con_movimiento = filter_var($_GET['solo_con_movimiento'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $resultado = obtenerBalanceCompleto($conn, $empresa_idx, $fecha_desde, $fecha_hasta, $incluir_borradores);
            if (!$resultado['success']) {
                responderError($resultado['error'] ?? 'Error al procesar el listado de sumas y saldos', 500);
            }

            // Filtrado según modo
            $cuentas_filtradas = [];
            foreach ($resultado['cuentas'] as $c) {
                if ($solo_con_movimiento && !$c['tiene_movimientos']) {
                    continue;
                }

                if ($modo === 'analitico' && $c['es_imputable'] != 1) {
                    continue;
                } elseif ($modo === 'sintetico_nivel1' && $c['nivel'] != 1) {
                    continue;
                } elseif ($modo === 'sintetico_nivel2' && $c['nivel'] > 2) {
                    continue;
                } elseif ($modo === 'sintetico_nivel3' && $c['nivel'] > 3) {
                    continue;
                }

                $cuentas_filtradas[] = $c;
            }

            responderJson([
                'success' => true,
                'periodo' => $resultado['periodo'],
                'modo' => $modo,
                'cuentas' => $cuentas_filtradas,
                'totales' => $resultado['totales_sumas_saldos']
            ]);
            break;

        default:
            responderError('Acción no reconocida: ' . $accion);
            break;
    }
} catch (Throwable $e) {
    error_log("Excepción en cont__balances_ajax.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    responderError('Error interno del servidor: ' . $e->getMessage(), 500);
}
