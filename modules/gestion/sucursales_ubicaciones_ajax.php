<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "sucursales_ubicaciones_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 38);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $filters = [
                'sucursal' => $_GET['filter_sucursal'] ?? '',
                'estado' => $_GET['filter_estado'] ?? '',
                'busqueda' => $_GET['filter_busqueda'] ?? ''
            ];
            
            $sucursales_ubicaciones = obtenerSucursalesUbicaciones($conexion, $empresa_idx, $pagina_idx, $filters);
            echo json_encode($sucursales_ubicaciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_sucursales_activas':
            $sucursales = obtenerSucursalesActivas($conexion, $empresa_idx);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados_registro':
            $estados = obtenerEstadosRegistro($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''), // AÑADIDO
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            
            $resultado = agregarSucursalUbicacion($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['sucursal_ubicacion_id'] ?? 0);
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''), // AÑADIDO
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx
            ];
            
            $resultado = editarSucursalUbicacion($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $sucursal_ubicacion_id = intval($_POST['sucursal_ubicacion_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            
            if (empty($sucursal_ubicacion_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, $sucursal_ubicacion_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_valores_por_defecto':
            $parent_type = $_GET['parent_type'] ?? '';
            $parent_id = $_GET['parent_id'] ?? '';
            
            if (empty($parent_type) || empty($parent_id)) {
                echo json_encode(['error' => 'Tipo o ID de padre no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $valores = obtenerValoresPorDefecto($conexion, $parent_type, $parent_id, $empresa_idx);
            echo json_encode($valores, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['sucursal_ubicacion_id'] ?? $_GET['sucursal_ubicacion_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $sucursal_ubicacion = obtenerSucursalUbicacionPorId($conexion, $id, $empresa_idx);
            if ($sucursal_ubicacion) {
                echo json_encode($sucursal_ubicacion, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Ubicación no encontrada'], JSON_UNESCAPED_UNICODE);
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