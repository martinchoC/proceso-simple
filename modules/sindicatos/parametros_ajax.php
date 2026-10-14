<?php
declare(strict_types=1);

/**
 * modules/sindicatos/parametros_ajax.php
 * Controlador AJAX para parámetros del sindicato (Página 1001).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1001]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/parametros_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];

try {
    switch ($accion) {
        case 'obtener':
            $parametros = sindi_obtener_parametros($conexion, $empresa_id);
            // También obtener convenios activos para el combo
            $stmtC = mysqli_prepare($conexion, "SELECT convenio_id, codigo, nombre FROM sindi__convenios WHERE empresa_id = ? AND tabla_estado_registro_id = 1");
            $convenios = [];
            if ($stmtC) {
                mysqli_stmt_bind_param($stmtC, "i", $empresa_id);
                mysqli_stmt_execute($stmtC);
                $resC = mysqli_stmt_get_result($stmtC);
                while ($c = mysqli_fetch_assoc($resC)) {
                    $convenios[] = $c;
                }
                mysqli_stmt_close($stmtC);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'parametros' => $parametros,
                    'convenios' => $convenios
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $data = $_POST;
            $res = sindi_guardar_parametros($conexion, $empresa_id, $data, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[PARAMETROS_SINDICATO] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
