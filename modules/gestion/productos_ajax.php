<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "productos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $productos = obtenerProductos($conexion);
        echo json_encode($productos);
        break;
    
    case 'agregar':
        $data = [
            'producto_codigo' => $_POST['producto_codigo'] ?? '',
            'producto_nombre' => $_POST['producto_nombre'] ?? '',
            'producto_descripcion' => $_POST['producto_descripcion'] ?? '',
            'producto_categoria_id' => $_POST['producto_categoria_id'] ?? '',
            'lado' => $_POST['lado'] ?? '',
            'material' => $_POST['material'] ?? '',
            'color' => $_POST['color'] ?? '',
            'peso' => $_POST['peso'] ?? '',
            'dimensiones' => $_POST['dimensiones'] ?? '',
            'garantia' => $_POST['garantia'] ?? '',
            'unidad_medida_id' => $_POST['unidad_medida_id'] ?? null,
            'tabla_estado_registro_id' => $_POST['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['producto_codigo']) || empty($data['producto_nombre']) || empty($data['producto_categoria_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos código, nombre y categoría son obligatorios']);
            break;
        }
        
        $resultado = agregarProducto($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['producto_id']);
        $data = [
            'producto_codigo' => $_POST['producto_codigo'] ?? '',
            'producto_nombre' => $_POST['producto_nombre'] ?? '',
            'producto_descripcion' => $_POST['producto_descripcion'] ?? '',
            'producto_categoria_id' => $_POST['producto_categoria_id'] ?? '',
            'lado' => $_POST['lado'] ?? '',
            'material' => $_POST['material'] ?? '',
            'color' => $_POST['color'] ?? '',
            'peso' => $_POST['peso'] ?? '',
            'dimensiones' => $_POST['dimensiones'] ?? '',
            'garantia' => $_POST['garantia'] ?? '',
            'unidad_medida_id' => $_POST['unidad_medida_id'] ?? null,
            'tabla_estado_registro_id' => $_POST['tabla_estado_registro_id'] ?? 1
        ];
        
        if (empty($data['producto_codigo']) || empty($data['producto_nombre']) || empty($data['producto_categoria_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos código, nombre y categoría son obligatorios']);
            break;
        }
        
        $resultado = editarProducto($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['producto_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoProducto($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['producto_id']);
        $resultado = eliminarProducto($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['producto_id']);
        $producto = obtenerProductoPorId($conexion, $id);
        echo json_encode($producto);
        break;

    case 'obtener_opciones':
    $categorias = obtenerCategoriasProductos($conexion);
    $unidades_medida = obtenerUnidadesMedida($conexion);
    
    echo json_encode([
        'categorias' => $categorias,  // Ahora es array indexado, no asociativo
        'unidades_medida' => $unidades_medida
    ]);
    break;
    case 'obtener_compatibilidad':
    $producto_id = intval($_GET['producto_id']);
    $compatibilidad = obtenerCompatibilidadProducto($conexion, $producto_id);
    echo json_encode($compatibilidad);
    break;

   

    case 'obtener_modelos':
        $marca_id = intval($_GET['marca_id']);
        $modelos = obtenerModelosPorMarca($conexion, $marca_id);
        echo json_encode($modelos);
        break;

    case 'obtener_submodelos':
        $modelo_id = intval($_GET['modelo_id']);
        $submodelos = obtenerSubmodelosPorModelo($conexion, $modelo_id);
        echo json_encode($submodelos);
        break;

    case 'obtener_opciones_compatibilidad':
        $marcas = obtenerMarcas($conexion);
        echo json_encode(['marcas' => $marcas]);
        break;

    case 'obtener_sucursales':
        $sucursales = obtenerSucursales($conexion);
        echo json_encode(['sucursales' => $sucursales]);
        break;

    case 'obtener_ubicaciones_sucursal':
        $sucursal_id = intval($_GET['sucursal_id']);
        $ubicaciones = obtenerUbicacionesSucursal($conexion, $sucursal_id);
        echo json_encode(['ubicaciones' => $ubicaciones]);
        break;

    case 'obtener_ubicaciones_producto':
        $producto_id = intval($_GET['producto_id']);
        $ubicaciones = obtenerUbicacionesProducto($conexion, $producto_id);
        echo json_encode($ubicaciones);
        break;

    case 'agregar_ubicacion_producto':
        $data = [
            'producto_id' => $_POST['producto_id'] ?? '',
            'sucursal_id' => $_POST['sucursal_id'] ?? '',
            'sucursal_ubicacion_id' => $_POST['sucursal_ubicacion_id'] ?? '',
            'stock_minimo' => $_POST['stock_minimo'] ?? 0,
            'stock_maximo' => $_POST['stock_maximo'] ?? null
        ];
        
        if (empty($data['producto_id']) || empty($data['sucursal_id']) || empty($data['sucursal_ubicacion_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos sucursal y ubicación son obligatorios']);
            break;
        }
        
        $resultado = agregarUbicacionProducto($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_ubicacion_producto':
        $producto_ubicacion_id = intval($_GET['producto_ubicacion_id']);
        $resultado = eliminarUbicacionProducto($conexion, $producto_ubicacion_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar_compatibilidad':
        $compatibilidad_id = intval($_GET['compatibilidad_id']);
        $resultado = eliminarCompatibilidad($conexion, $compatibilidad_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'agregar_compatibilidad':
        $data = [
            'producto_id' => $_POST['producto_id'] ?? '',
            'marca_id' => $_POST['marca_id'] ?? '',
            'modelo_id' => $_POST['modelo_id'] ?? '',
            'submodelo_id' => $_POST['submodelo_id'] ?? '',
            'anio_desde' => $_POST['anio_desde'] ?? '',
            'anio_hasta' => $_POST['anio_hasta'] ?? ''
        ];
        
        if (empty($data['producto_id']) || empty($data['marca_id']) || empty($data['modelo_id']) || empty($data['anio_desde'])) {
            echo json_encode(['resultado' => false, 'error' => 'Los campos marca, modelo y año desde son obligatorios']);
            break;
        }
        
        $resultado = agregarCompatibilidad($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}