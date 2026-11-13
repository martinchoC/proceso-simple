<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['condiciones_pago.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "condiciones_pago_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = 67; // fijo: nunca del request (ver CLAUDE.md)

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $condiciones = obtenerCondicionesPago($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'condicion_pago' => trim($_POST['condicion_pago'] ?? ''),
                'tipo' => $_POST['tipo'] ?? 'CONTADO',
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarCondicionPago($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['condicion_pago_id'] ?? 0);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'condicion_pago' => trim($_POST['condicion_pago'] ?? ''),
                'tipo' => $_POST['tipo'] ?? 'CONTADO',
                'orden' => intval($_POST['orden'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarCondicionPago($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $condicion_pago_id = intval($_POST['condicion_pago_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($condicion_pago_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $condicion_pago_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['condicion_pago_id'] ?? $_GET['condicion_pago_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $condicion = obtenerCondicionPagoPorId($conexion, $id, $empresa_idx);
            if ($condicion) {
                echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Condición de pago no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>