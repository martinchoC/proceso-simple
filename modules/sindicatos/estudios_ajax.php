<?php
declare(strict_types=1);

/**
 * modules/sindicatos/estudios_ajax.php
 * Controlador AJAX para estudios contables y vinculaciones (Página 1011).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1011]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/estudios_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1011;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_estudios($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $est = sindi_obtener_estudio($conexion, $empresa_id, $id);
            echo json_encode(['success' => (bool)$est, 'data' => $est], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_estudio($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__estudios', 'estudio_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_vinculaciones':
            $estudio_id = intval($_GET['estudio_id'] ?? 0);
            $vincs = sindi_listar_vinculaciones($conexion, $empresa_id, $estudio_id);
            // Empleadores activos para combo de vinculación
            $stmtEmp = mysqli_prepare($conexion, "SELECT emp.empleador_id, ent.entidad_razon_social, ent.entidad_cuit
                                                  FROM sindi__empleadores emp
                                                  INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                                                  WHERE emp.empresa_id = ? AND emp.tabla_estado_registro_id = 1
                                                  ORDER BY ent.entidad_razon_social ASC");
            $empleadores = [];
            if ($stmtEmp) {
                mysqli_stmt_bind_param($stmtEmp, "i", $empresa_id);
                mysqli_stmt_execute($stmtEmp);
                $resEmp = mysqli_stmt_get_result($stmtEmp);
                while ($e = mysqli_fetch_assoc($resEmp)) {
                    $empleadores[] = $e;
                }
                mysqli_stmt_close($stmtEmp);
            }

            echo json_encode(['success' => true, 'data' => ['vinculaciones' => $vincs, 'empleadores' => $empleadores]], JSON_UNESCAPED_UNICODE);
            break;

        case 'vincular':
            $estudio_id = intval($_POST['estudio_id'] ?? 0);
            $empleador_id = intval($_POST['empleador_id'] ?? 0);
            $iniciado_por = 'SINDICATO';
            $res = sindi_vincular_estudio_empleador($conexion, $empresa_id, $estudio_id, $empleador_id, $iniciado_por, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[ESTUDIOS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
