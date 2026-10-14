<?php
declare(strict_types=1);

/**
 * modules/sindicatos/volantes_ajax.php
 * Controlador AJAX para Volantes de Pago (Página 1030).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1030]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/sindi_volantes_core_model.php';
require_once __DIR__ . '/sindi_intereses_core_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1030;

try {
    switch ($accion) {
        case 'listar':
            $empleador_id = !empty($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : null;
            $lista = sindi_listar_volantes($conexion, $empresa_id, $usuario_id, $pagina_idx, $empleador_id);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'combo_empleadores':
            $filtroAcceso = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'emp.empleador_id');
            $sqlEmp = "SELECT emp.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit
                       FROM sindi__empleadores emp
                       INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                       WHERE emp.empresa_id = ? AND emp.tabla_estado_registro_id = 1 AND {$filtroAcceso}
                       ORDER BY ent.entidad_nombre ASC";
            $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
            mysqli_stmt_bind_param($stmtEmp, "i", $empresa_id);
            mysqli_stmt_execute($stmtEmp);
            $resEmp = mysqli_stmt_get_result($stmtEmp);
            $empleadores = [];
            while ($r = mysqli_fetch_assoc($resEmp)) {
                $empleadores[] = $r;
            }
            mysqli_stmt_close($stmtEmp);
            echo json_encode(['success' => true, 'data' => $empleadores], JSON_UNESCAPED_UNICODE);
            break;

        case 'deudas_pendientes':
            $empleador_id = (int)($_GET['empleador_id'] ?? 0);
            if ($empleador_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Empleador no especificado.']);
                break;
            }
            $deudas = sindi_obtener_deudas_pendientes_empleador($conexion, $empresa_id, $empleador_id);
            echo json_encode(['success' => true, 'data' => $deudas], JSON_UNESCAPED_UNICODE);
            break;

        case 'simular':
            $raw = file_get_contents('php://input');
            $datos = json_decode($raw, true) ?: $_POST;
            $deudas = $datos['deudas'] ?? [];
            $f_pago_1 = (string)($datos['f_pago_1'] ?? date('Y-m-d', strtotime('+10 days')));
            $f_pago_2 = (string)($datos['f_pago_2'] ?? date('Y-m-d', strtotime('+20 days')));

            if (empty($deudas)) {
                echo json_encode(['success' => false, 'message' => 'No hay deudas seleccionadas para simular.']);
                break;
            }

            $tasas = sindi_obtener_tasas_interes($conexion, $empresa_id, 1);
            $sim = sindi_calcular_intereses_volante_completo($deudas, $f_pago_1, $f_pago_2, $tasas);
            echo json_encode(['success' => true, 'calculo' => $sim], JSON_UNESCAPED_UNICODE);
            break;

        case 'emitir':
            sindi_exigir_funcion($conexion, 1030, 'agregar');
            $raw = file_get_contents('php://input');
            $datos = json_decode($raw, true) ?: $_POST;

            $empleador_id = (int)($datos['empleador_id'] ?? 0);
            $compIds = $datos['comprobante_ids'] ?? [];
            $f_pago_1 = (string)($datos['f_pago_1'] ?? '');
            $f_pago_2 = (string)($datos['f_pago_2'] ?? '');
            $observaciones = (string)($datos['observaciones'] ?? '');

            $res = sindi_emitir_volante($conexion, $empresa_id, $usuario_id, [
                'empleador_id' => $empleador_id,
                'f_pago_1' => $f_pago_1,
                'f_pago_2' => $f_pago_2,
                'observaciones' => $observaciones
            ], $compIds);

            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $volante_id = (int)($_GET['volante_id'] ?? 0);
            $volante = sindi_obtener_volante($conexion, $empresa_id, $usuario_id, $volante_id);
            if (!$volante) {
                echo json_encode(['success' => false, 'message' => 'Volante no encontrado.']);
                break;
            }
            echo json_encode(['success' => true, 'volante' => $volante], JSON_UNESCAPED_UNICODE);
            break;

        case 'anular':
            sindi_exigir_funcion($conexion, 1030, 'anular');
            $volante_id = (int)($_POST['volante_id'] ?? 0);
            $res = sindi_anular_volante($conexion, $empresa_id, $usuario_id, $volante_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
