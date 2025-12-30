<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "submodelos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'submodelos.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 42;

try {
    switch ($accion) {
        case 'listar':
            $submodelos = obtenerSubmodelos($conexion, $pagina_id);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            $marcas = obtenerMarcas($conexion);
            echo json_encode($marcas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos':
            $marca_id = intval($_GET['marca_id'] ?? $_POST['marca_id'] ?? 0);
            $modelos = obtenerModelosPorMarca($conexion, $marca_id);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'submodelo_nombre' => $_POST['submodelo_nombre'] ?? ''
            ];
            
            if (empty($data['modelo_id']) || empty($data['submodelo_nombre'])) {
                echo json_encode(['resultado' => false, 'error' => 'Todos los campos son obligatorios']);
                break;
            }
            
            $resultado = agregarSubmodelo($conexion, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['submodelo_id'] ?? 0);
            $data = [
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'submodelo_nombre' => $_POST['submodelo_nombre'] ?? ''
            ];
            
            if (empty($data['modelo_id']) || empty($data['submodelo_nombre'])) {
                echo json_encode(['resultado' => false, 'error' => 'Todos los campos son obligatorios']);
                break;
            }
            
            $resultado = editarSubmodelo($conexion, $id, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $submodelo_id = intval($_POST['submodelo_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            if (empty($submodelo_id) || empty($funcion_nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $submodelo_id, $funcion_nombre, $pagina_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['submodelo_id'] ?? $_GET['submodelo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                break;
            }
            
            $submodelo = obtenerSubmodeloPorId($conexion, $id);
            echo json_encode($submodelo ?: [], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conexion);
?>