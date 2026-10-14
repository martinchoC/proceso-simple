<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ddjj_ajax.php
 * Controlador AJAX para declaraciones juradas (Página 1020).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1020]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/sindi_ddjj_core_model.php';
require_once __DIR__ . '/sindi_importadores.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1020;

try {
    switch ($accion) {
        case 'listar':
            $empleador_id = !empty($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : null;
            $periodo = !empty($_GET['periodo']) ? (int)$_GET['periodo'] : null;
            $lista = sindi_listar_ddjj($conexion, $empresa_id, $usuario_id, $pagina_idx, $empleador_id, $periodo);
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

        case 'combo_sucursales':
            $lista = sindi_sucursales_empleador_combo($conexion, $empresa_id, $usuario_id, (int)($_GET['empleador_id'] ?? 0));
            echo json_encode(['success' => true, 'data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'combo_categorias':
            $sqlCat = "SELECT categoria_id, codigo, nombre FROM sindi__categorias WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY codigo ASC";
            $stmtCat = mysqli_prepare($conexion, $sqlCat);
            mysqli_stmt_bind_param($stmtCat, "i", $empresa_id);
            mysqli_stmt_execute($stmtCat);
            $resCat = mysqli_stmt_get_result($stmtCat);
            $categorias = [];
            while ($c = mysqli_fetch_assoc($resCat)) {
                $categorias[] = $c;
            }
            mysqli_stmt_close($stmtCat);
            echo json_encode(['success' => true, 'data' => $categorias], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $ddjj = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $id);
            if (!$ddjj) {
                echo json_encode(['success' => false, 'message' => 'Declaración jurada no encontrada o sin acceso.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $ddjj], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_borrador':
            sindi_exigir_funcion($conexion, 1020, ((int)(json_decode($_POST['cabecera'] ?? '{}', true)['ddjj_id'] ?? 0)) > 0 ? 'editar' : 'agregar');
            $cabeceraJson = $_POST['cabecera'] ?? '{}';
            $nominaJson = $_POST['nomina'] ?? '[]';
            $cabecera = json_decode($cabeceraJson, true);
            $nomina = json_decode($nominaJson, true);

            if (!is_array($cabecera) || !is_array($nomina)) {
                echo json_encode(['success' => false, 'message' => 'Estructura de datos inválida.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $res = sindi_guardar_borrador_ddjj($conexion, $empresa_id, $usuario_id, $cabecera, $nomina);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'confirmar':
            sindi_exigir_funcion($conexion, 1020, 'confirmar');
            $id = intval($_POST['id'] ?? 0);
            $res = sindi_confirmar_ddjj($conexion, $empresa_id, $usuario_id, $id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'iniciar_rectificativa':
            sindi_exigir_funcion($conexion, 1020, 'rectificar');
            $id = intval($_POST['id'] ?? 0);
            $res = sindi_iniciar_rectificativa($conexion, $empresa_id, $usuario_id, $id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'importar_archivo':
            sindi_exigir_funcion($conexion, 1020, 'agregar');
            if (empty($_FILES['archivoNomina']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $formato = trim($_POST['tipoFormato'] ?? 'F931_SICOSS');
            $contenido = file_get_contents($_FILES['archivoNomina']['tmp_name']);

            if ($formato === 'F931_SICOSS') {
                $parseRes = sindi_parsear_f931($contenido, $conexion, $empresa_id);
            } else {
                $parseRes = sindi_parsear_csv($contenido, $conexion, $empresa_id);
            }

            echo json_encode($parseRes, JSON_UNESCAPED_UNICODE);
            break;

        case 'anular':
            sindi_exigir_funcion($conexion, 1020, 'anular');
            $id = intval($_POST['id'] ?? 0);
            $ddjj = sindi_obtener_ddjj($conexion, $empresa_id, $usuario_id, $id);
            if (!$ddjj || (int)$ddjj['tabla_estado_registro_id'] !== 3) {
                echo json_encode(['success' => false, 'message' => 'Solo se pueden anular borradores.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stmtAnu = mysqli_prepare($conexion, "UPDATE sindi__ddjj SET tabla_estado_registro_id = 38 WHERE ddjj_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmtAnu, "ii", $id, $empresa_id);
            $ok = mysqli_stmt_execute($stmtAnu);
            mysqli_stmt_close($stmtAnu);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Borrador anulado.' : 'Error al anular.'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no permitida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[DDJJ_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
