<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_idx'], 'pagina_idx' => 408]);
/**
 * Controlador AJAX: Matriz RACI y Time Tracking
 * Módulo 4: Estudio Contable (tareas_raci_ajax.php)
 * Página ID: 408
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tareas_raci_model.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {
        case 'combos':
            $combos = tareas_raci_combos($conn, $empresa_id);
            echo json_encode([
                'success' => true,
                'message' => 'Combos obtenidos.',
                'data' => $combos,
                'error' => null
            ]);
            break;

        case 'listar':
            $filtros = [
                'usuario_r_id' => $_POST['usuario_r_id'] ?? $_GET['usuario_r_id'] ?? '',
                'usuario_a_id' => $_POST['usuario_a_id'] ?? $_GET['usuario_a_id'] ?? '',
                'cliente_id' => $_POST['cliente_id'] ?? $_GET['cliente_id'] ?? '',
                'tabla_estado_registro_id' => $_POST['tabla_estado_registro_id'] ?? $_GET['tabla_estado_registro_id'] ?? ''
            ];
            $lista = tareas_raci_listar($conn, $empresa_id, $filtros);
            echo json_encode([
                'success' => true,
                'message' => 'Tareas obtenidas.',
                'data' => $lista,
                'error' => null
            ]);
            break;

        case 'guardar':
            $datos = [
                'tarea_id' => $_POST['tarea_id'] ?? 0,
                'cliente_id' => $_POST['cliente_id'] ?? 0,
                'vencimiento_id' => $_POST['vencimiento_id'] ?? null,
                'titulo' => $_POST['titulo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'f_inicio' => $_POST['f_inicio'] ?? '',
                'f_limite' => $_POST['f_limite'] ?? '',
                'usuario_r_id' => $_POST['usuario_r_id'] ?? 0,
                'usuario_a_id' => $_POST['usuario_a_id'] ?? 0,
                'contactos_c' => $_POST['contactos_c'] ?? '',
                'contactos_i' => $_POST['contactos_i'] ?? '',
                'horas_estimadas' => $_POST['horas_estimadas'] ?? 1.0,
                'tabla_estado_registro_id' => $_POST['tabla_estado_registro_id'] ?? 4
            ];
            $res = tarea_raci_guardar($conn, $empresa_id, $datos, $usuario_id);
            echo json_encode($res);
            break;

        case 'cambiar_estado':
            $tarea_id = intval($_POST['tarea_id'] ?? 0);
            $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 0);
            $res = tarea_raci_cambiar_estado($conn, $empresa_id, $tarea_id, $nuevo_estado_id);
            echo json_encode($res);
            break;

        case 'registrar_tiempo':
            $tarea_id = intval($_POST['tarea_id'] ?? 0);
            $minutos = intval($_POST['minutos'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $res = tarea_tiempo_registrar($conn, $empresa_id, $tarea_id, $usuario_id, $minutos, $descripcion);
            echo json_encode($res);
            break;

        case 'listar_tiempos':
            $tarea_id = intval($_POST['tarea_id'] ?? $_GET['tarea_id'] ?? 0);
            $tiempos = tarea_tiempos_listar($conn, $empresa_id, $tarea_id);
            echo json_encode([
                'success' => true,
                'message' => 'Tiempos obtenidos.',
                'data' => $tiempos,
                'error' => null
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => "Acción '{$action}' no reconocida.",
                'data' => null,
                'error' => 'INVALID_ACTION'
            ]);
            break;
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción en servidor: ' . $e->getMessage(),
        'data' => null,
        'error' => $e->getMessage()
    ]);
}
