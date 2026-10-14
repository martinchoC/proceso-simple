<?php
declare(strict_types=1);

/**
 * modules/sindicatos/empleadores_ajax.php
 * Controlador AJAX para padrón de empleadores (Página 1010).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1010]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/empleadores_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1010;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_empleadores($conexion, $empresa_id, $usuario_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $emp = sindi_obtener_empleador($conexion, $empresa_id, $usuario_id, $id);

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
                'success' => (bool)$emp,
                'data' => [
                    'empleador' => $emp,
                    'convenios' => $convenios
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_empleador($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_sucursales':
            $empleador_id = intval($_GET['empleador_id'] ?? 0);
            $lista = sindi_listar_sucursales_empleador($conexion, $empresa_id, $usuario_id, $empleador_id);
            echo json_encode(['success' => true, 'data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_sucursal':
            $res = sindi_guardar_sucursal_empleador($conexion, $empresa_id, $usuario_id, $_POST);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'estado_sucursal':
            $res = sindi_cambiar_estado_sucursal_empleador(
                $conexion,
                $empresa_id,
                $usuario_id,
                intval($_POST['empleador_id'] ?? 0),
                intval($_POST['sucursal_id'] ?? 0),
                intval($_POST['estado'] ?? 0)
            );
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'buscar_localidades':
            $lista = sindi_buscar_localidades($conexion, (string)($_GET['q'] ?? ''));
            echo json_encode(['success' => true, 'data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $id);
            $res = sindi_ejecutar_transicion($conexion, 'sindi__empleadores', 'empleador_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[EMPLEADORES_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
