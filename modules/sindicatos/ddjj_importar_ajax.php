<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ddjj_importar_ajax.php
 * Controlador AJAX de Importación F.931 / Planilla (Página 1021).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1021]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ddjj_importar_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1021;

try {
    switch ($accion) {
        case 'listar':
            echo json_encode(['data' => sindi_listar_importaciones($conexion, $empresa_id, $usuario_id, $pagina_idx)], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(sindi_obtener_boton_agregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'combos':
            $filtro = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'emp.empleador_id');
            $stmt = mysqli_prepare($conexion, "SELECT emp.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit
                                               FROM sindi__empleadores emp
                                               INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
                                               WHERE emp.empresa_id = ? AND emp.tabla_estado_registro_id = 1 AND {$filtro}
                                               ORDER BY ent.entidad_nombre");
            mysqli_stmt_bind_param($stmt, "i", $empresa_id);
            mysqli_stmt_execute($stmt);
            $empleadores = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conexion, "SELECT categoria_id, codigo, nombre FROM sindi__categorias
                                               WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY codigo");
            mysqli_stmt_bind_param($stmt, "i", $empresa_id);
            mysqli_stmt_execute($stmt);
            $categorias = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            echo json_encode(['success' => true, 'data' => ['empleadores' => $empleadores, 'categorias' => $categorias]], JSON_UNESCAPED_UNICODE);
            break;

        case 'importar':
            sindi_exigir_funcion($conexion, $pagina_idx, 'agregar');
            $archivo = $_FILES['archivo'] ?? null;
            if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'No se recibió el archivo.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $res = sindi_importar_nomina(
                $conexion,
                $empresa_id,
                $usuario_id,
                (int)($_POST['empleador_id'] ?? 0),
                (int)($_POST['periodo'] ?? 0),
                (string)($_POST['formato'] ?? ''),
                (string)($archivo['name'] ?? 'archivo.txt'),
                (string)file_get_contents($archivo['tmp_name']),
                !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null
            );
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $imp = sindi_obtener_importacion($conexion, $empresa_id, $usuario_id, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
            echo json_encode(['success' => (bool)$imp, 'data' => $imp, 'message' => $imp ? '' : 'Importación no encontrada.'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[DDJJ_IMPORTAR_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
