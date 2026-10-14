<?php
declare(strict_types=1);

/**
 * modules/sindicatos/conceptos_ajax.php
 * Controlador AJAX para conceptos de aporte sindical (Página 1003).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1003]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/conceptos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1003;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_conceptos($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $conc = sindi_obtener_concepto($conexion, $empresa_id, $id);
            // Convenios para el combo
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
                'success' => (bool)$conc,
                'data' => [
                    'concepto' => $conc,
                    'convenios' => $convenios,
                    'tipos' => sindi_tipos_comprobante_conceptos($conexion, $empresa_id)
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_concepto($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__conceptos', 'concepto_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[CONCEPTOS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
