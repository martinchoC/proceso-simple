<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "modelos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'modelos.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 41;

try {
    switch ($accion) {
        case 'listar':
            $modelos = obtenerModelos($conexion, $pagina_id);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            $marcas = obtenerMarcasActivas($conexion);
            echo json_encode($marcas, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'modelo_nombre' => $_POST['modelo_nombre'] ?? '',
                'marca_id' => intval($_POST['marca_id'] ?? 0)
            ];
            
            if (empty($data['modelo_nombre']) || empty($data['marca_id'])) {
                echo json_encode(['resultado' => false, 'error' => 'Todos los campos son obligatorios']);
                break;
            }
            
            $resultado = agregarModelo($conexion, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['modelo_id'] ?? 0);
            $data = [
                'modelo_nombre' => $_POST['modelo_nombre'] ?? '',
                'marca_id' => intval($_POST['marca_id'] ?? 0)
            ];
            
            if (empty($data['modelo_nombre']) || empty($data['marca_id'])) {
                echo json_encode(['resultado' => false, 'error' => 'Todos los campos son obligatorios']);
                break;
            }
            
            $resultado = editarModelo($conexion, $id, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $modelo_id = intval($_POST['modelo_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            if (empty($modelo_id) || empty($funcion_nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $modelo_id, $funcion_nombre, $pagina_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['modelo_id'] ?? $_GET['modelo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado']);
                break;
            }
            
            $modelo = obtenerModeloPorId($conexion, $id);
            echo json_encode($modelo ?: [], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conexion);
?>