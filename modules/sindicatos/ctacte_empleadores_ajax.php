<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ctacte_empleadores_ajax.php
 * Controlador AJAX para Cuenta Corriente de Empleadores (Página 1032).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1032]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/ctacte_empleadores_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];

try {
    switch ($accion) {
        case 'consultar':
            $empleador_id = (int)($_GET['empleador_id'] ?? 0);
            if ($empleador_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar un empleador.']);
                break;
            }
            $data = sindi_obtener_ctacte_empleador($conexion, $empresa_id, $usuario_id, $empleador_id);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
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

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
