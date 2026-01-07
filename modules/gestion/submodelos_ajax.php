<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once "submodelos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 42); // Ajustar según tu BD

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $submodelos = obtenerSubmodelos($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos_activos':
            $modelos = obtenerModelosActivos($conexion, $empresa_idx);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'submodelo_nombre' => trim($_POST['submodelo_nombre'] ?? ''),
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarSubmodelo($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['submodelo_id'] ?? 0);
            $data = [
                'submodelo_nombre' => trim($_POST['submodelo_nombre'] ?? ''),
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarSubmodelo($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $submodelo_id = intval($_POST['submodelo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($submodelo_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $submodelo_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['submodelo_id'] ?? $_GET['submodelo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $submodelo = obtenerSubmodeloPorId($conexion, $id, $empresa_idx);
            if ($submodelo) {
                echo json_encode($submodelo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Submodelo no encontrado'], JSON_UNESCAPED_UNICODE);
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