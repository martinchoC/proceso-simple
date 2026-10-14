<?php
declare(strict_types=1);

/**
 * modules/sindicatos/rendiciones_ajax.php
 * Controlador AJAX para Rendiciones y Cobranzas (Página 1031).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1031]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/rendiciones_model.php';
require_once __DIR__ . '/sindi_cobros_core_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1031;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_rendiciones($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(sindi_obtener_boton_agregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'combo_medios_pago':
            $sql = "SELECT medio_pago_id, codigo, nombre, driver FROM sindi__medios_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden ASC";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "i", $empresa_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $medios = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $medios[] = $r;
            }
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'data' => $medios], JSON_UNESCAPED_UNICODE);
            break;

        case 'subir_archivo':
            sindi_exigir_funcion($conexion, 1031, 'agregar');
            $medio_pago_id = (int)($_POST['medio_pago_id'] ?? 0);
            $f_rendicion = (string)($_POST['f_rendicion'] ?? date('Y-m-d'));

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No se subió ningún archivo válido.']);
                break;
            }

            $nombreArchivo = (string)$_FILES['archivo']['name'];
            $contenido = (string)file_get_contents($_FILES['archivo']['tmp_name']);

            $res = sindi_registrar_rendicion($conexion, $empresa_id, $usuario_id, $medio_pago_id, $f_rendicion, $nombreArchivo, $contenido);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'procesar':
            sindi_exigir_funcion($conexion, 1031, 'procesar');
            $rendicion_id = (int)($_POST['rendicion_id'] ?? 0);
            $res = sindi_procesar_cobranza_rendicion($conexion, $empresa_id, $usuario_id, $rendicion_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $rendicion_id = (int)($_GET['rendicion_id'] ?? 0);
            $det = sindi_obtener_detalle_rendicion($conexion, $empresa_id, $rendicion_id);
            if (!$det) {
                echo json_encode(['success' => false, 'message' => 'Rendición no encontrada.']);
                break;
            }
            echo json_encode(['success' => true, 'rendicion' => $det], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
