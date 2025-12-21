<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "marcas_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'marcas.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 43;

try {
    switch ($accion) {
        case 'listar':
            $marcas = obtenerMarcas($conexion, $pagina_id);
            echo json_encode($marcas);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar);
            break;

        case 'agregar':
            $data = [
                'marca_nombre' => $_POST['marca_nombre'] ?? ''
            ];
            $resultado = agregarMarca($conexion, $data);
            echo json_encode(['resultado' => $resultado]);
            break;

        case 'editar':
            $id = intval($_POST['marca_id'] ?? 0);
            $data = [
                'marca_nombre' => $_POST['marca_nombre'] ?? ''
            ];
            $resultado = editarMarca($conexion, $id, $data);
            echo json_encode(['resultado' => $resultado]);
            break;

        case 'ejecutar_funcion':
            $marca_id = intval($_POST['marca_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            $resultado = ejecutarTransicionEstado($conexion, $marca_id, $funcion_nombre, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'obtener':
            $id = intval($_POST['marca_id'] ?? $_GET['marca_id'] ?? 0);
            $marca = obtenerMarcaPorId($conexion, $id);
            echo json_encode($marca);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>