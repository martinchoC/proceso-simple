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

            // Validar tamaño máximo (5MB)
            $tamanio_maximo = 5 * 1024 * 1024; // 5MB
            if ($archivo['size'] > $tamanio_maximo) {
                echo json_encode(['resultado' => false, 'error' => 'El archivo es demasiado grande. Tamaño máximo: 5MB'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // ========== NUEVA ESTRUCTURA DE DIRECTORIOS ==========
            // Directorio: modules/gestion/imagenes/productos/
            
            $directorio_actual = dirname(__FILE__); // modules/gestion/
            $directorio_imagenes = $directorio_actual . '/imagenes/';
            $directorio_productos = $directorio_actual . '/imagenes/productos/';
            
            // Para debugging
            error_log("========= SUBIDA DE IMAGEN =========");
            error_log("Directorio actual: " . $directorio_actual);
            error_log("Directorio imágenes: " . $directorio_imagenes);
            error_log("Directorio productos: " . $directorio_productos);
            
            // Crear directorios si no existen
            if (!file_exists($directorio_imagenes)) {
                if (!mkdir($directorio_imagenes, 0755, true)) {
                    echo json_encode(['resultado' => false, 'error' => 'No se pudo crear el directorio de imágenes: ' . $directorio_imagenes], JSON_UNESCAPED_UNICODE);
                    break;
                } else {
                    error_log("✓ Directorio imágenes creado: " . $directorio_imagenes);
                }
            }
            
            if (!file_exists($directorio_productos)) {
                if (!mkdir($directorio_productos, 0755, true)) {
                    echo json_encode(['resultado' => false, 'error' => 'No se pudo crear el directorio de productos: ' . $directorio_productos], JSON_UNESCAPED_UNICODE);
                    break;
                } else {
                    error_log("✓ Directorio productos creado: " . $directorio_productos);
                }
            }
            
            // Verificar permisos de escritura
            if (!is_writable($directorio_productos)) {
                error_log("Directorio no tiene permisos de escritura. Intentando cambiar permisos...");
                if (!chmod($directorio_productos, 0755)) {
                    echo json_encode(['resultado' => false, 'error' => 'El directorio no tiene permisos de escritura: ' . $directorio_productos], JSON_UNESCAPED_UNICODE);
                    break;
                }
            }

            // Generar nombre único para el archivo
            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $nombre_archivo = 'prod_' . $producto_id . '_' . time() . '_' . uniqid() . '.' . strtolower($extension);
            $ruta_completa = $directorio_productos . $nombre_archivo;

            // Para debugging
            error_log("Nombre archivo: " . $nombre_archivo);
            error_log("Ruta completa destino: " . $ruta_completa);
            error_log("Ruta temporal archivo: " . $archivo['tmp_name']);

            // Mover el archivo
            if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                error_log("✓ Archivo movido exitosamente a: " . $ruta_completa);
                error_log("✓ Archivo existe: " . (file_exists($ruta_completa) ? 'SÍ' : 'NO'));
                error_log("✓ Tamaño: " . filesize($ruta_completa) . " bytes");
                
                // Preparar datos para la base de datos
                // RUTA RELATIVA desde la raíz del sitio
                $ruta_relativa_bd = 'modules/gestion/imagenes/productos/' . $nombre_archivo;
                
                error_log("Ruta para BD: " . $ruta_relativa_bd);
                
                $data = [
                    'producto_id' => $producto_id,
                    'empresa_id' => $empresa_idx,
                    'descripcion' => $descripcion,
                    'es_principal' => $es_principal,
                    'orden' => $orden,
                    'imagen_nombre' => $nombre_archivo,
                    'imagen_ruta' => $ruta_relativa_bd,
                    'imagen_tipo' => $archivo['type'],
                    'imagen_tamanio' => $archivo['size']
                ];

                $resultado = subirImagenProducto($conexion, $data);
                echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            } else {
                $error_info = error_get_last();
                error_log("✗ ERROR al mover archivo:");
                error_log(print_r($error_info, true));
                
                echo json_encode([
                    'resultado' => false, 
                    'error' => 'Error al mover el archivo subido',
                    'debug_info' => [
                        'error_message' => $error_info['message'] ?? 'Sin mensaje',
                        'source' => $archivo['tmp_name'],
                        'destination' => $ruta_completa,
                        'upload_error' => $archivo['error'],
                        'php_upload_max' => ini_get('upload_max_filesize'),
                        'php_post_max' => ini_get('post_max_size')
                    ]
                ], JSON_UNESCAPED_UNICODE);
            }
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