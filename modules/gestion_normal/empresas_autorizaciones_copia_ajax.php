<?php
/**
 * AJAX Handler: ABM de Autorizaciones de Acceso a Información Básica
 * Módulo: Gestión (mod 2) / Gestión Normal (mod 5)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard([
    'modulo_id' => 5,
    'paginas_url' => ['empresas_autorizaciones_copia.php', 'empresa_inicio.php'],
    'params' => ['empresa_id', 'empresa_idx'],
]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/empresas_autorizaciones_copia_model.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$empresa_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];

try {
    // Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php)
    permisos_exigir_accion($conn, pagina_id_por_url($conn, 5, 'empresas_autorizaciones_copia.php'), (string)$action, [
        'guardar' => (intval($_POST['autorizacion_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
        'cambiar_estado' => 'inactivar',
        'eliminar' => 'inactivar',
    ]);
    switch ($action) {
        case 'obtener_empresas_disponibles':
            $empresas = autCopiaObtenerEmpresasDisponibles($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $empresas]);
            break;

        case 'obtener_otorgadas':
            $items = autCopiaObtenerOtorgadas($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'obtener_recibidas':
            $items = autCopiaObtenerRecibidas($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'obtener_por_id':
            $autorizacion_id = intval($_POST['autorizacion_id'] ?? $_GET['autorizacion_id'] ?? 0);
            $item = autCopiaObtenerPorId($conexion, $autorizacion_id, $empresa_id);
            if ($item) {
                echo json_encode(['success' => true, 'data' => $item]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Autorización no encontrada']);
            }
            break;

        case 'guardar':
            $res = autCopiaGuardar($conexion, $_POST, $empresa_id, $usuario_id);
            echo json_encode($res);
            break;

        case 'cambiar_estado':
            $autorizacion_id = intval($_POST['autorizacion_id'] ?? 0);
            $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 1);
            $res = autCopiaCambiarEstado($conexion, $autorizacion_id, $empresa_id, $nuevo_estado_id, $usuario_id);
            echo json_encode($res);
            break;

        case 'eliminar':
            $autorizacion_id = intval($_POST['autorizacion_id'] ?? 0);
            $res = autCopiaEliminar($conexion, $autorizacion_id, $empresa_id);
            echo json_encode($res);
            break;

        case 'obtener_log':
            $logs = autCopiaObtenerLog($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $logs]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Excepción en el servidor: ' . $e->getMessage()]);
}
