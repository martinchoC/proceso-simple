<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "sucursales_ubicaciones_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 38);

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $filters = [
                'sucursal' => $_GET['filter_sucursal'] ?? '',
                'deposito' => $_GET['filter_deposito'] ?? '',
                'estado' => $_GET['filter_estado'] ?? '',
                'busqueda' => $_GET['filter_busqueda'] ?? ''
            ];
            $data = obtenerSucursalesUbicaciones($conexion, $empresa_idx, $pagina_idx, $filters);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(obtenerBotonAgregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_sucursales_activas':
            echo json_encode(obtenerSucursalesActivas($conexion, $empresa_idx), JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_depositos_por_sucursal':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            echo json_encode($sucursal_id > 0 ? obtenerDepositosPorSucursal($conexion, $sucursal_id) : [], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados_registro':
            echo json_encode(obtenerEstadosRegistro($conexion), JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            echo json_encode(agregarSucursalUbicacion($conexion, $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'estado_registro_id' => !empty($_POST['estado_registro_id']) ? intval($_POST['estado_registro_id']) : null,
                'empresa_idx' => $empresa_idx
            ];
            echo json_encode(editarSucursalUbicacion($conexion, intval($_POST['sucursal_ubicacion_id'] ?? 0), $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $resultado = ejecutarTransicionEstado(
                $conexion,
                intval($_POST['sucursal_ubicacion_id'] ?? 0),
                $_POST['accion_js'] ?? '',
                $empresa_idx,
                $pagina_idx
            );
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_valores_por_defecto':
            $valores = obtenerValoresPorDefecto(
                $conexion,
                $_GET['parent_type'] ?? '',
                $_GET['parent_id'] ?? '',
                $empresa_idx
            );
            echo json_encode($valores, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $data = obtenerSucursalUbicacionPorId(
                $conexion,
                intval($_POST['sucursal_ubicacion_id'] ?? $_GET['sucursal_ubicacion_id'] ?? 0),
                $empresa_idx
            );
            echo json_encode($data ?: ['error' => 'Ubicación no encontrada'], JSON_UNESCAPED_UNICODE);
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