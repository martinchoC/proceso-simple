<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "productos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 40);

// Parámetros de DataTables para paginación
$start = intval($_GET['start'] ?? $_POST['start'] ?? 0);
$length = intval($_GET['length'] ?? $_POST['length'] ?? 50);
$draw = intval($_GET['draw'] ?? $_POST['draw'] ?? 1);
$orderColumn = intval($_GET['order'][0]['column'] ?? $_POST['order'][0]['column'] ?? 1);
$orderDir = $_GET['order'][0]['dir'] ?? $_POST['order'][0]['dir'] ?? 'asc';
$searchValue = $_GET['search']['value'] ?? $_POST['search']['value'] ?? '';

// Filtros adicionales
$filtro_tipo = $_GET['filtro_tipo'] ?? $_POST['filtro_tipo'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? $_POST['filtro_estado'] ?? '';
$filtro_codigo = $_GET['filtro_codigo'] ?? $_POST['filtro_codigo'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $data = obtenerProductosPaginados($conexion, $empresa_idx, $pagina_idx, [
                'start' => $start,
                'length' => $length,
                'search' => $searchValue,
                'order_column' => $orderColumn,
                'order_dir' => $orderDir,
                'filtro_tipo' => $filtro_tipo,
                'filtro_estado' => $filtro_estado,
                'filtro_codigo' => $filtro_codigo
            ]);
            
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $data['total'],
                'recordsFiltered' => $data['filtered'],
                'data' => $data['productos']
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'empresa_id' => $empresa_idx,
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_categoria_id' => intval($_POST['producto_categoria_id'] ?? 0),
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 0),
                'unidad_medida_id' => !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null,
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => !empty($_POST['peso']) ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarProducto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['producto_id'] ?? 0);
            $data = [
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_categoria_id' => intval($_POST['producto_categoria_id'] ?? 0),
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 0),
                'unidad_medida_id' => !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null,
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => !empty($_POST['peso']) ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarProducto($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($producto_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $producto_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['producto_id'] ?? $_GET['producto_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $producto = obtenerProductoPorId($conexion, $id, $empresa_idx);
            if ($producto) {
                echo json_encode($producto, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Producto no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_tipos_producto':
            $tipos = obtenerTiposProducto($conexion, $empresa_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_unidades_medida':
            $unidades = obtenerUnidadesMedida($conexion, $empresa_idx);
            echo json_encode($unidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados':
            $estados = obtenerEstados($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        // Nuevas funciones para compatibilidad
        case 'obtener_marcas':
            $marcas = obtenerMarcas($conexion, $empresa_idx);
            echo json_encode($marcas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos':
            $marca_id = intval($_GET['marca_id'] ?? 0);
            if (empty($marca_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $modelos = obtenerModelos($conexion, $empresa_idx, $marca_id);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_submodelos':
            $modelo_id = intval($_GET['modelo_id'] ?? 0);
            if (empty($modelo_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $submodelos = obtenerSubmodelos($conexion, $empresa_idx, $modelo_id);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_compatibilidad':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            if (empty($producto_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $compatibilidad = obtenerCompatibilidad($conexion, $producto_id, $empresa_idx);
            echo json_encode($compatibilidad, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_compatibilidad_por_id':
            $compatibilidad_id = intval($_GET['compatibilidad_id'] ?? 0);
            if (empty($compatibilidad_id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $compatibilidad = obtenerCompatibilidadPorId($conexion, $compatibilidad_id, $empresa_idx);
            if ($compatibilidad) {
                echo json_encode($compatibilidad, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Compatibilidad no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'agregar_compatibilidad':
            $data = [
                'empresa_id' => $empresa_idx,
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'marca_id' => intval($_POST['marca_id'] ?? 0),
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'submodelo_id' => !empty($_POST['submodelo_id']) ? intval($_POST['submodelo_id']) : null,
                'anio_desde' => intval($_POST['anio_desde'] ?? 2000),
                'anio_hasta' => !empty($_POST['anio_hasta']) ? intval($_POST['anio_hasta']) : null
            ];
            $resultado = agregarCompatibilidad($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_compatibilidad':
            $compatibilidad_id = intval($_POST['compatibilidad_id'] ?? 0);
            $data = [
                'marca_id' => intval($_POST['marca_id'] ?? 0),
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'submodelo_id' => !empty($_POST['submodelo_id']) ? intval($_POST['submodelo_id']) : null,
                'anio_desde' => intval($_POST['anio_desde'] ?? 2000),
                'anio_hasta' => !empty($_POST['anio_hasta']) ? intval($_POST['anio_hasta']) : null
            ];
            $resultado = editarCompatibilidad($conexion, $compatibilidad_id, $data, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_compatibilidad':
            $compatibilidad_id = intval($_POST['compatibilidad_id'] ?? 0);
            $resultado = eliminarCompatibilidad($conexion, $compatibilidad_id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
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