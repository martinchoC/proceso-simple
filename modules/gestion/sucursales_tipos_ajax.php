<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "conexion.php";
require_once "sucursales_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ✅ Obtener información de la página
$pagina_info = obtenerPaginaPorUrl($conexion, 'sucursales_tipos.php');
$pagina_id = $pagina_info ? $pagina_info['pagina_id'] : 33;

try {
    switch ($accion) {
        case 'listar':
            $sucursales_tipos = obtenerLocalesTipos($conexion, $pagina_id);
            echo json_encode($sucursales_tipos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'agregar':
            $data = [
                'sucursal_tipo' => $_POST['sucursal_tipo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? ''
            ];
            
            if (empty($data['sucursal_tipo'])) {
                echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
                break;
            }
            
            $resultado = agregarLocalTipo($conexion, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['sucursal_tipo_id'] ?? 0);
            $data = [
                'sucursal_tipo' => $_POST['sucursal_tipo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? ''
            ];
            
            if (empty($data['sucursal_tipo'])) {
                echo json_encode(['resultado' => false, 'error' => 'El nombre es obligatorio']);
                break;
            }
            
            $resultado = editarLocalTipo($conexion, $id, $data);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'cambiar_estado':
            $id = intval($_GET['sucursal_tipo_id'] ?? $_POST['sucursal_tipo_id'] ?? 0);
            $nuevo_estado = intval($_GET['nuevo_estado'] ?? $_POST['nuevo_estado'] ?? 0);
            $resultado = cambiarEstadoLocalTipo($conexion, $id, $nuevo_estado);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = intval($_GET['sucursal_tipo_id'] ?? $_POST['sucursal_tipo_id'] ?? 0);
            $resultado = eliminarLocalTipo($conexion, $id);
            echo json_encode(['resultado' => $resultado], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['sucursal_tipo_id'] ?? $_POST['sucursal_tipo_id'] ?? 0);
            $sucursal_tipo = obtenerLocalTipoPorId($conexion, $id);
            echo json_encode($sucursal_tipo ?: [], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_funcion':
            $sucursal_tipo_id = intval($_POST['sucursal_tipo_id'] ?? 0);
            $funcion_nombre = $_POST['funcion_nombre'] ?? '';
            
            if (empty($sucursal_tipo_id) || empty($funcion_nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $sucursal_tipo_id, $funcion_nombre, $pagina_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conexion);
?>