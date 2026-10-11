<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['cont_asientos.php', 'listas_precios_productos.php', 'productos.php', 'productos_consulta.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "productos_model.php";
require_once __DIR__ . '/stock_niveles_model.php';

/**
 * Stock mínimo / máximo por boca cargado en la solapa Información de productos.php.
 * Se graba después del producto, en su propia transacción (snvGuardarNivelesProducto, que también
 * reevalúa las alertas de reposición). Solo para quien tiene acceso a productos.php (página 40).
 * @return string|null aviso para el usuario si los niveles no se pudieron guardar
 */
function productosGuardarNivelesStock($conexion, array $ctx, int $producto_id): ?string
{
    if (!isset($_POST['niveles_stock']) || $producto_id <= 0) return null;
    $empresa_id = (int)$ctx['empresa_id'];
    $usuario_id = (int)$ctx['usuario_id'];
    if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_id, 40)) {
        return 'No tiene permiso para cargar stock mínimo y máximo.';
    }
    $niveles = json_decode((string)$_POST['niveles_stock'], true);
    if (!is_array($niveles)) return 'Los niveles de stock llegaron con un formato inválido.';
    try {
        snvGuardarNivelesProducto($conexion, $empresa_id, $usuario_id, $producto_id, $niveles);
        return null;
    } catch (InvalidArgumentException | RuntimeException $e) {
        if ($e instanceof mysqli_sql_exception) {
            error_log('productosGuardarNivelesStock SQL: ' . $e->getMessage());
            return 'El producto se guardó, pero no el stock mínimo / máximo (error de base de datos).';
        }
        return 'El producto se guardó, pero no el stock mínimo / máximo: ' . $e->getMessage();
    } catch (Throwable $e) {
        error_log('productosGuardarNivelesStock: ' . $e->getMessage());
        return 'El producto se guardó, pero no el stock mínimo / máximo (error interno).';
    }
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 40);

// Parámetros de DataTables para paginación
$start = intval($_GET['start'] ?? $_POST['start'] ?? 0);
$length = intval($_GET['length'] ?? $_POST['length'] ?? 10);
$draw = intval($_GET['draw'] ?? $_POST['draw'] ?? 1);
$orderColumn = intval($_GET['order'][0]['column'] ?? $_POST['order'][0]['column'] ?? 1);
$orderDir = $_GET['order'][0]['dir'] ?? $_POST['order'][0]['dir'] ?? 'asc';
$searchValue = $_GET['search']['value'] ?? $_POST['search']['value'] ?? '';

// Filtros adicionales
$filtro_marca = $_GET['filtro_marca'] ?? $_POST['filtro_marca'] ?? '';
$filtro_modelo = $_GET['filtro_modelo'] ?? $_POST['filtro_modelo'] ?? '';
$filtro_submodelo = $_GET['filtro_submodelo'] ?? $_POST['filtro_submodelo'] ?? '';
$filtro_codigo = $_GET['filtro_codigo'] ?? $_POST['filtro_codigo'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? $_POST['filtro_estado'] ?? '';

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
                'filtro_codigo' => $filtro_codigo,
                'filtro_estado' => $filtro_estado
            ]);
            
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $data['total'],
                'recordsFiltered' => $data['filtered'],
                'data' => $data['productos']
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_bocas_por_sucursal':
                $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
                if ($sucursal_id <= 0) {
                    echo json_encode(['error' => 'ID de sucursal inválido'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                $bocas = obtenerBocasPorSucursal($conexion, $sucursal_id, $empresa_idx);
                echo json_encode($bocas, JSON_UNESCAPED_UNICODE);
                break;
        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;
        case 'buscar_ubicaciones':
            $termino = $_GET['termino'] ?? '';
            $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
            
            if (strlen($termino) < 2) {
                echo json_encode(['results' => []]);
                break;
            }
            
            $ubicaciones = buscarUbicacionesSucursales($conexion, $termino, $empresa_idx);
            echo json_encode(['results' => $ubicaciones], JSON_UNESCAPED_UNICODE);
            break;
        case 'buscar_ubicaciones_rapidas':
            $termino = $_GET['termino'] ?? '';
            $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
            $limite = intval($_GET['limite'] ?? 15);
            
            if (strlen($termino) < 2) {
                echo json_encode(['results' => []]);
                break;
            }
            
            $ubicaciones = buscarUbicacionesRapidas($conexion, $termino, $empresa_idx, $limite);
            
            // Formato para Select2
            $results = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'text' => $item['text'],
                    'sucursal_nombre' => $item['sucursal_nombre'],
                    'boca_nombre' => $item['boca_nombre'],
                    'seccion' => $item['seccion'],
                    'estanteria' => $item['estanteria'],
                    'estante' => $item['estante'],
                    'posicion' => $item['posicion'],
                    'descripcion' => $item['descripcion']
                ];
            }, $ubicaciones);
            
            echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
            break;
        case 'agregar':
            $data = [
                'empresa_id' => $empresa_idx,
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 0),
                'unidad_medida_id' => !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null,
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => !empty($_POST['peso']) ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'iva_alicuota_id' => $_POST['iva_alicuota_id'] !== '' ? $_POST['iva_alicuota_id'] : null,
                // productos.php solo maneja productos con control de stock; los servicios y
                // productos sin stock se administran desde productos_generales.php
                'controla_stock' => 1,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarProducto($conexion, $data);
            if (!empty($resultado['resultado'])) {
                $aviso = productosGuardarNivelesStock($conexion, $ctx, intval($resultado['producto_id'] ?? 0));
                if ($aviso) $resultado['aviso'] = $aviso;
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['producto_id'] ?? 0);
            $data = [
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_tipo_id' => intval($_POST['producto_tipo_id'] ?? 0),
                'unidad_medida_id' => !empty($_POST['unidad_medida_id']) ? intval($_POST['unidad_medida_id']) : null,
                'lado' => trim($_POST['lado'] ?? ''),
                'material' => trim($_POST['material'] ?? ''),
                'color' => trim($_POST['color'] ?? ''),
                'peso' => !empty($_POST['peso']) ? floatval($_POST['peso']) : null,
                'dimensiones' => trim($_POST['dimensiones'] ?? ''),
                'garantia' => trim($_POST['garantia'] ?? ''),
                'iva_alicuota_id' => $_POST['iva_alicuota_id'] !== '' ? $_POST['iva_alicuota_id'] : null,
                'controla_stock' => 1,  // ver comentario en 'agregar'
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarProducto($conexion, $id, $data);
            if (!empty($resultado['resultado'])) {
                $aviso = productosGuardarNivelesStock($conexion, $ctx, $id);
                if ($aviso) $resultado['aviso'] = $aviso;
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_niveles_stock':
            // Bocas del usuario con el mínimo / máximo y el stock actual del producto (0 = nuevo).
            echo json_encode(snvNivelesProducto($conexion, (int)$ctx['empresa_id'], (int)$ctx['usuario_id'],
                intval($_GET['producto_id'] ?? $_POST['producto_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_iva_alicuotas':
            $alicuotas = obtenerIvaAlicuotas($conexion, $empresa_idx);
            echo json_encode($alicuotas, JSON_UNESCAPED_UNICODE);
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
            $estados = obtenerEstadosProductos($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            $marcas = obtenerMarcas($conexion, $empresa_idx);
            echo json_encode($marcas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_listas_precios':
            $listas_precios = obtenerListasPrecios($conexion, $empresa_idx);
            echo json_encode($listas_precios, JSON_UNESCAPED_UNICODE);
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

        // Alta múltiple: cada item trae su propio marca/modelo/submodelo/años.
        // 'items' llega como JSON string (un array) por POST.
        case 'agregar_compatibilidades_multiple':
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $items = json_decode($_POST['items'] ?? '[]', true);
            if (!is_array($items)) {
                echo json_encode(['resultado' => false, 'error' => 'Formato de items inválido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $resultado = agregarCompatibilidadesMultiple($conexion, $producto_id, $empresa_idx, $items);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // Búsqueda de combinaciones marca+modelo+submodelo por texto libre,
        // para el buscador del alta múltiple de compatibilidad.
        case 'buscar_marca_modelo_submodelo':
            $termino = $_GET['termino'] ?? '';
            $limite = intval($_GET['limite'] ?? 30);
            $combinaciones = buscarMarcaModeloSubmodelo($conexion, $termino, $limite);
            echo json_encode($combinaciones, JSON_UNESCAPED_UNICODE);
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
            $producto_ubicacion_id = intval($_POST['producto_ubicacion_id'] ?? 0);
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $sucursal_ubicacion_id = intval($_POST['sucursal_ubicacion_id'] ?? 0);
            
            if ($producto_ubicacion_id > 0) {
                // Es una edición
                $resultado = editarUbicacionProducto($conexion, $producto_ubicacion_id, $sucursal_ubicacion_id);
                if ($resultado['resultado']) {
                    $resultado['producto_ubicacion_id'] = $producto_ubicacion_id;
                }
            } else {
                // Es una nueva asignación
                $data = [
                    'producto_id' => $producto_id,
                    'sucursal_ubicacion_id' => $sucursal_ubicacion_id
                ];
                $resultado = agregarUbicacionProducto($conexion, $data);
            }
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
                'boca_id' => intval($_POST['boca_id'] ?? 0),  // CORREGIDO: lee boca_id
                'seccion' => trim($_POST['seccion'] ?? ''),
                'estanteria' => trim($_POST['estanteria'] ?? ''),
                'estante' => trim($_POST['estante'] ?? ''),
                'posicion' => trim($_POST['posicion'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? '')
            ];
            $resultado = crearUbicacionSucursal($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // ========== CASOS PARA PROVEEDORES (SOLO CÓDIGO) ==========
            
        case 'obtener_proveedores_producto':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            if (empty($producto_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $proveedores = obtenerProveedoresProducto($conexion, $producto_id, $empresa_idx);
            echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_proveedor_producto_por_id':
            $producto_proveedor_id = intval($_GET['producto_proveedor_id'] ?? 0);
            if (empty($producto_proveedor_id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $proveedor = obtenerProveedorProductoPorId($conexion, $producto_proveedor_id, $empresa_idx);
            if ($proveedor) {
                echo json_encode($proveedor, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Proveedor no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_entidades_proveedores':
            $entidades = obtenerEntidadesProveedores($conexion, $empresa_idx);
            echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_proveedor_producto':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'empresa_id' => $empresa_idx,
                'codigo_proveedor' => trim($_POST['codigo_proveedor'] ?? '')
            ];
            $resultado = agregarProveedorProducto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_proveedor_producto':
            $producto_proveedor_id = intval($_POST['producto_proveedor_id'] ?? 0);
            $data = [
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'codigo_proveedor' => trim($_POST['codigo_proveedor'] ?? '')
            ];
            $resultado = editarProveedorProducto($conexion, $producto_proveedor_id, $data, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_proveedor_producto':
            $producto_proveedor_id = intval($_POST['producto_proveedor_id'] ?? 0);
            $resultado = eliminarProveedorProducto($conexion, $producto_proveedor_id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_secciones':
    $boca_id = intval($_GET['boca_id'] ?? 0);
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($boca_id == 0) {
        echo json_encode([]);
        break;
    }
    
    $secciones = obtenerSeccionesPorBoca($conexion, $boca_id, $empresa_idx);
    echo json_encode($secciones, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_estanterias':
    $boca_id = intval($_GET['boca_id'] ?? 0);
    $seccion = $_GET['seccion'] ?? '';
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($boca_id == 0 || empty($seccion)) {
        echo json_encode([]);
        break;
    }
    
    $estanterias = obtenerEstanteriasPorSeccion($conexion, $boca_id, $seccion, $empresa_idx);
    echo json_encode($estanterias, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_estantes':
    $boca_id = intval($_GET['boca_id'] ?? 0);
    $seccion = $_GET['seccion'] ?? '';
    $estanteria = $_GET['estanteria'] ?? '';
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($boca_id == 0 || empty($seccion) || empty($estanteria)) {
        echo json_encode([]);
        break;
    }
    
    $estantes = obtenerEstantesPorEstanteria($conexion, $boca_id, $seccion, $estanteria, $empresa_idx);
    echo json_encode($estantes, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_posiciones':
    $boca_id = intval($_GET['boca_id'] ?? 0);
    $seccion = $_GET['seccion'] ?? '';
    $estanteria = $_GET['estanteria'] ?? '';
    $estante = $_GET['estante'] ?? '';
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($boca_id == 0 || empty($seccion) || empty($estanteria) || empty($estante)) {
        echo json_encode([]);
        break;
    }
    
    $posiciones = obtenerPosicionesPorEstante($conexion, $boca_id, $seccion, $estanteria, $estante, $empresa_idx);
    echo json_encode($posiciones, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_ubicacion_detalle':
    $sucursal_ubicacion_id = intval($_GET['sucursal_ubicacion_id'] ?? 0);
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($sucursal_ubicacion_id == 0) {
        echo json_encode(null);
        break;
    }
    
    $ubicacion = obtenerUbicacionCompletaPorId($conexion, $sucursal_ubicacion_id, $empresa_idx);
    echo json_encode($ubicacion, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_detalle_ubicacion_sucursal':
    $sucursal_ubicacion_id = intval($_GET['sucursal_ubicacion_id'] ?? 0);
    $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
    
    if ($sucursal_ubicacion_id == 0) {
        echo json_encode(null);
        break;
    }
    
    $ubicacion = obtenerDetalleUbicacionSucursal($conexion, $sucursal_ubicacion_id, $empresa_idx);
    echo json_encode($ubicacion, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_ubicacion_producto_por_id':
    $producto_ubicacion_id = intval($_GET['producto_ubicacion_id'] ?? 0);
    if (empty($producto_ubicacion_id)) {
        echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
        break;
    }
    $ubicacion = obtenerUbicacionProductoPorId($conexion, $producto_ubicacion_id);
    if ($ubicacion) {
        echo json_encode($ubicacion, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => 'Ubicación no encontrada'], JSON_UNESCAPED_UNICODE);
    }
    break;

case 'obtener_ubicacion_producto_por_id':
    $producto_ubicacion_id = intval($_GET['producto_ubicacion_id'] ?? 0);
    if (empty($producto_ubicacion_id)) {
        echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
        break;
    }
    $ubicacion = obtenerUbicacionProductoPorId($conexion, $producto_ubicacion_id);
    if ($ubicacion) {
        echo json_encode($ubicacion, JSON_UNESCAPED_UNICODE);
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