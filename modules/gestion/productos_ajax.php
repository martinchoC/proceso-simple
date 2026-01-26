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
$filtro_marca = $_GET['filtro_marca'] ?? $_POST['filtro_marca'] ?? '';
$filtro_modelo = $_GET['filtro_modelo'] ?? $_POST['filtro_modelo'] ?? '';
$filtro_submodelo = $_GET['filtro_submodelo'] ?? $_POST['filtro_submodelo'] ?? '';
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
                'filtro_marca' => $filtro_marca,
                'filtro_modelo' => $filtro_modelo,
                'filtro_submodelo' => $filtro_submodelo,
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

        case 'obtener_categorias':
            $categorias = obtenerCategoriasProducto($conexion, $empresa_idx);
            echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_unidades_medida':
            $unidades = obtenerUnidadesMedida($conexion, $empresa_idx);
            echo json_encode($unidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados':
            $estados = obtenerEstados($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

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

        case 'obtener_imagenes_producto':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            if (empty($producto_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $imagenes = obtenerImagenesProducto($conexion, $producto_id, $empresa_idx);
            echo json_encode($imagenes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_imagen_por_id':
            $producto_imagen_id = intval($_GET['producto_imagen_id'] ?? 0);
            if (empty($producto_imagen_id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $imagen = obtenerImagenPorId($conexion, $producto_imagen_id, $empresa_idx);
            if ($imagen) {
                echo json_encode($imagen, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Imagen no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'subir_imagen_producto':
            // Verificar que se haya subido un archivo
            if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['resultado' => false, 'error' => 'No se ha subido ninguna imagen o hay un error en el archivo'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $archivo = $_FILES['imagen'];
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $es_principal = intval($_POST['es_principal'] ?? 0);
            $orden = intval($_POST['orden'] ?? 0);

            // Validaciones
            if ($producto_id == 0) {
                echo json_encode(['resultado' => false, 'error' => 'Producto no válido'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Validar tipo de archivo
            $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($archivo['type'], $tipos_permitidos)) {
                echo json_encode(['resultado' => false, 'error' => 'Tipo de archivo no permitido. Solo se permiten JPG, PNG, GIF y WebP'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // REDUCIR TAMAÑO MÁXIMO - De 5MB a 1MB
            $tamanio_maximo = 1 * 1024 * 1024; // 1MB
            if ($archivo['size'] > $tamanio_maximo) {
                echo json_encode(['resultado' => false, 'error' => 'El archivo es demasiado grande. Tamaño máximo: 1MB'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // OPTIMIZAR Y REDUCIR IMAGEN ANTES DE GUARDAR
            $imagen_data = null;
            $imagen_tipo = $archivo['type'];
            $imagen_tamanio = $archivo['size'];
            
            // Reducir calidad si es JPEG
            if (in_array($archivo['type'], ['image/jpeg', 'image/jpg'])) {
                $imagen = imagecreatefromjpeg($archivo['tmp_name']);
                if ($imagen !== false) {
                    // Reducir calidad al 80%
                    ob_start();
                    imagejpeg($imagen, null, 80); // 80% de calidad
                    $imagen_data = ob_get_clean();
                    $imagen_tamanio = strlen($imagen_data);
                    imagedestroy($imagen);
                    
                    // Si aún es mayor a 500KB, reducir más
                    if ($imagen_tamanio > 500 * 1024) {
                        // Redimensionar imagen manteniendo aspecto
                        list($ancho_orig, $alto_orig) = getimagesize($archivo['tmp_name']);
                        $nuevo_ancho = 800; // Ancho máximo
                        $nuevo_alto = intval($alto_orig * ($nuevo_ancho / $ancho_orig));
                        
                        $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
                        $imagen_orig = imagecreatefromjpeg($archivo['tmp_name']);
                        imagecopyresampled($imagen_nueva, $imagen_orig, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_orig, $alto_orig);
                        
                        ob_start();
                        imagejpeg($imagen_nueva, null, 70); // 70% de calidad
                        $imagen_data = ob_get_clean();
                        $imagen_tamanio = strlen($imagen_data);
                        
                        imagedestroy($imagen_orig);
                        imagedestroy($imagen_nueva);
                    }
                }
            }
            
            // Para otros tipos de imagen
            if ($imagen_data === null && in_array($archivo['type'], ['image/png', 'image/gif', 'image/webp'])) {
                // Leer el archivo sin optimizar
                $imagen_data = file_get_contents($archivo['tmp_name']);
                if ($imagen_data === false) {
                    echo json_encode(['resultado' => false, 'error' => 'Error al leer el archivo'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $imagen_tamanio = strlen($imagen_data);
            }
            
            // Si no es una imagen compatible, leer como está
            if ($imagen_data === null) {
                $imagen_data = file_get_contents($archivo['tmp_name']);
                if ($imagen_data === false) {
                    echo json_encode(['resultado' => false, 'error' => 'Error al leer el archivo'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $imagen_tamanio = strlen($imagen_data);
            }

            // Preparar datos para la base de datos
            $data = [
                'producto_id' => $producto_id,
                'empresa_id' => $empresa_idx,
                'descripcion' => $descripcion,
                'es_principal' => $es_principal,
                'orden' => $orden,
                'imagen_nombre' => basename($archivo['name']),
                'imagen_tipo' => $imagen_tipo,
                'imagen_tamanio' => $imagen_tamanio,
                'imagen_data' => $imagen_data
            ];

            // Llamar a la función del modelo
            $resultado = subirImagenProducto($conexion, $data);
            
            // Si se subió correctamente, agregar la URL de la imagen
            if ($resultado['resultado'] && isset($resultado['imagen_id'])) {
                $resultado['imagen_url'] = 'get_imagen.php?id=' . $resultado['imagen_id'];
            }
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_imagen_producto':
            $producto_imagen_id = intval($_POST['producto_imagen_id'] ?? 0);
            $data = [
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'es_principal' => intval($_POST['es_principal'] ?? 0),
                'orden' => intval($_POST['orden'] ?? 0)
            ];
            $resultado = actualizarImagenProducto($conexion, $producto_imagen_id, $data, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_imagen_producto':
            $producto_imagen_id = intval($_POST['producto_imagen_id'] ?? 0);
            $resultado = eliminarImagenProducto($conexion, $producto_imagen_id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_ubicaciones_producto':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            if (empty($producto_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ubicaciones = obtenerUbicacionesProducto($conexion, $producto_id, $empresa_idx);
            echo json_encode($ubicaciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales':
            $sucursales = obtenerSucursales($conexion, $empresa_idx);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_ubicaciones_sucursales':
            $ubicaciones = obtenerUbicacionesSucursales($conexion, $empresa_idx);
            echo json_encode($ubicaciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_ubicacion_producto':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'sucursal_ubicacion_id' => intval($_POST['sucursal_ubicacion_id'] ?? 0)
            ];
            $resultado = agregarUbicacionProducto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_ubicacion_producto':
            $producto_ubicacion_id = intval($_POST['producto_ubicacion_id'] ?? 0);
            $resultado = eliminarUbicacionProducto($conexion, $producto_ubicacion_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'crear_ubicacion_sucursal':
            $data = [
                'empresa_id' => $empresa_idx,
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? '')
            ];
            $resultado = crearUbicacionSucursal($conexion, $data);
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