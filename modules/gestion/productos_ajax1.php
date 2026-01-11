<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "productos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 50); // ✅ CORREGIDO: 50

header('Content-Type: application/json; charset=utf-8');
// Añadir headers para cache
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            // Optimizar consulta para mejor rendimiento
            $productos = obtenerProductos($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'empresa_idx' => $empresa_idx,
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_categoria_id' => intval($_POST['producto_categoria_id'] ?? 0),
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 1),
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => $_POST['peso'] ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'unidad_medida_id' => $_POST['unidad_medida_id'] ? intval($_POST['unidad_medida_id']) : null,
                'estado_registro_id' => intval($_POST['estado_registro_id'] ?? 1),
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
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 1),
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => $_POST['peso'] ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'unidad_medida_id' => $_POST['unidad_medida_id'] ? intval($_POST['unidad_medida_id']) : null,
                'estado_registro_id' => intval($_POST['estado_registro_id'] ?? 1),
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

        case 'obtener_opciones':
            $categorias = obtenerCategoriasProductos($conexion);
            $unidades_medida = obtenerUnidadesMedida($conexion);
            $tipos_producto = obtenerTiposProducto($conexion);
            
            echo json_encode([
                'categorias' => $categorias,
                'unidades_medida' => $unidades_medida,
                'tipos_producto' => $tipos_producto
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_opciones_compatibilidad':
            $marcas = obtenerMarcas($conexion);
            echo json_encode(['marcas' => $marcas], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            $marcas = obtenerMarcas($conexion);
            echo json_encode(['marcas' => $marcas], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos':
            $marca_id = intval($_GET['marca_id'] ?? 0);
            $modelos = obtenerModelosPorMarca($conexion, $marca_id);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_submodelos':
            $modelo_id = intval($_GET['modelo_id'] ?? 0);
            $submodelos = obtenerSubmodelosPorModelo($conexion, $modelo_id);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales':
            $sucursales = obtenerSucursales($conexion);
            echo json_encode(['sucursales' => $sucursales], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_proveedores':
            $proveedores = obtenerProveedores($conexion);
            echo json_encode(['proveedores' => $proveedores], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_compatibilidad':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $compatibilidad = obtenerCompatibilidadProducto($conexion, $producto_id);
            echo json_encode($compatibilidad, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_compatibilidad':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'marca_id' => intval($_POST['marca_id'] ?? 0),
                'modelo_id' => intval($_POST['modelo_id'] ?? 0),
                'submodelo_id' => $_POST['submodelo_id'] ? intval($_POST['submodelo_id']) : null,
                'anio_desde' => intval($_POST['anio_desde'] ?? 0),
                'anio_hasta' => $_POST['anio_hasta'] ? intval($_POST['anio_hasta']) : null
            ];

            $resultado = agregarCompatibilidad($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_compatibilidad':
            $compatibilidad_id = intval($_GET['compatibilidad_id'] ?? 0);
            $resultado = eliminarCompatibilidad($conexion, $compatibilidad_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_ubicaciones_sucursal':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            $ubicaciones = obtenerUbicacionesSucursal($conexion, $sucursal_id);
            echo json_encode(['ubicaciones' => $ubicaciones], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_ubicaciones_producto':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $ubicaciones = obtenerUbicacionesProducto($conexion, $producto_id);
            echo json_encode($ubicaciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_ubicacion_producto':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'sucursal_ubicacion_id' => intval($_POST['sucursal_ubicacion_id'] ?? 0),
                'stock_minimo' => intval($_POST['stock_minimo'] ?? 0),
                'stock_maximo' => $_POST['stock_maximo'] ? intval($_POST['stock_maximo']) : null
            ];
            
            $resultado = agregarUbicacionProducto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_ubicacion_producto':
            $producto_ubicacion_id = intval($_GET['producto_ubicacion_id'] ?? 0);
            $resultado = eliminarUbicacionProducto($conexion, $producto_ubicacion_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_proveedores_producto':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $proveedores = obtenerProveedoresProducto($conexion, $producto_id);
            echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_proveedor':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'proveedor_id' => intval($_POST['proveedor_id'] ?? 0),
                'codigo_proveedor' => trim($_POST['codigo_proveedor'] ?? ''),
                'precio_compra' => $_POST['precio_compra'] ? floatval($_POST['precio_compra']) : null
            ];

            $resultado = agregarProveedorProducto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_proveedor':
            $proveedor_id = intval($_GET['proveedor_id'] ?? 0);
            $resultado = eliminarProveedorProducto($conexion, $proveedor_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_fotos':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $fotos = obtenerFotosProducto($conexion, $producto_id);
            echo json_encode($fotos, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_foto':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $resultado = agregarFotoProducto($conexion, $data, $_FILES['imagen']);
                echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['resultado' => false, 'error' => 'No se ha seleccionado una imagen válida'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'marcar_principal_foto':
            $foto_id = intval($_GET['foto_id'] ?? 0);
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $resultado = marcarFotoComoPrincipal($conexion, $foto_id, $producto_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_foto':
            $foto_id = intval($_GET['foto_id'] ?? 0);
            $resultado = eliminarFotoProducto($conexion, $foto_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // ✅ Nuevas acciones agregadas
        case 'cambiar_estado':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $nuevo_estado = intval($_GET['nuevo_estado'] ?? 1);
            
            $resultado = cambiarEstadoProducto($conexion, $producto_id, $nuevo_estado);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $resultado = eliminarProducto($conexion, $producto_id, $empresa_idx);
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