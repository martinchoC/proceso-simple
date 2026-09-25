<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function enviarRespuesta($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function manejarError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuesta(['success' => false, 'message' => $mensaje]);
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "proveedor_precios_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 92);
$usuario_id = intval($_SESSION['usuario_id'] ?? 0);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

// Declarar el Content-Type ACÁ, para toda respuesta de éxito del switch de
// abajo — antes, solo lo fijaba manejarError() en el camino de error, así
// que una respuesta exitosa salía como text/html y el jQuery del front la
// traía como string en vez de array/objeto ya parseado.
header('Content-Type: application/json; charset=utf-8');

try {
    switch ($accion) {

        case 'obtener_proveedores':
            $proveedores = obtenerProveedores($conexion, $empresa_idx);
            echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_monedas':
            $monedas = obtenerMonedas($conexion, $empresa_idx);
            echo json_encode($monedas, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $filtros = [
                'filtro_codigo' => $_GET['filtro_codigo'] ?? '',
                'filtro_marca' => $_GET['filtro_marca'] ?? '',
                'filtro_modelo' => $_GET['filtro_modelo'] ?? '',
                'filtro_submodelo' => $_GET['filtro_submodelo'] ?? ''
            ];
            $precios = obtenerPreciosProveedor($conexion, $empresa_idx, $entidad_id, $pagina_idx, $filtros);
            echo json_encode($precios, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_dashboard':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $filtros = [
                'filtro_codigo' => $_GET['filtro_codigo'] ?? '',
                'filtro_marca' => $_GET['filtro_marca'] ?? '',
                'filtro_modelo' => $_GET['filtro_modelo'] ?? '',
                'filtro_submodelo' => $_GET['filtro_submodelo'] ?? ''
            ];
            $dashboard = obtenerDashboardPreciosProveedor($conexion, $empresa_idx, $entidad_id, $filtros);
            echo json_encode($dashboard, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            echo json_encode(obtenerMarcas($conexion), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos':
            echo json_encode(obtenerModelosPorMarca($conexion, $_GET['marca_id'] ?? 0), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_submodelos':
            echo json_encode(obtenerSubmodelosPorModelo($conexion, $_GET['modelo_id'] ?? 0), JSON_UNESCAPED_UNICODE);
            break;

        case 'buscar_productos_catalogo':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $q = $_GET['q'] ?? '';
            if (empty($entidad_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }
            $productos = buscarProductosCatalogo($conexion, $empresa_idx, $entidad_id, $q);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
        case 'editar':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'empresa_id' => $empresa_idx,
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'precio_lista' => floatval($_POST['precio_lista'] ?? -1),
                'moneda_id' => intval($_POST['moneda_id'] ?? 1),
                'f_vigencia_desde' => $_POST['f_vigencia_desde'] ?? date('Y-m-d'),
                'usuario_id' => $usuario_id,
                'pagina_idx' => $pagina_idx
            ];
            $resultado = guardarPrecioProveedor($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['producto_proveedor_precio_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $resultado = ejecutarTransicionEstadoPrecioProveedor($conexion, $id, $accion_js, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_historial':
            $producto_proveedor_id = intval($_GET['producto_proveedor_id'] ?? 0);
            $historial = obtenerHistorialPrecioProveedor($conexion, $producto_proveedor_id);
            echo json_encode($historial, JSON_UNESCAPED_UNICODE);
            break;

        // Recibe UN lote (ideal: 300 filas) desde proveedor_precios.js; el
        // Excel completo se trocea en el front, igual que en
        // listas_precios_productos.php, para no mandar miles de filas en
        // un solo POST ni bloquear el hilo de PHP con un array gigante.
        case 'importar_lote':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                manejarError('Debe indicar el proveedor', 400);
            }
            if (!isset($_POST['items'])) {
                manejarError('No se recibieron los items del lote', 400);
            }
            $items = json_decode($_POST['items'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                manejarError('Error al decodificar el lote: ' . json_last_error_msg(), 400);
            }
            $archivo_nombre = trim($_POST['archivo_nombre'] ?? '');

            $resultado = importarListaProveedorDesdeExcel($conexion, $empresa_idx, $entidad_id, $items, $usuario_id, $archivo_nombre, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_costo':
            // Solo costo — NO toca listas de precio (para eso están los
            // otros 2 botones de la fila).
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $costo_neto_compra = floatval($_POST['costo_neto_compra'] ?? -1);
            $moneda_id = intval($_POST['moneda_id'] ?? 1);

            $resultado = actualizarCostoDesdeListaProveedor($conexion, $producto_id, $empresa_idx, $entidad_id, $costo_neto_compra, $moneda_id, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_precio_venta':
            // Solo lista(s) de precios — usa el costo YA registrado (o el
            // propuesto, si el producto no tiene costo todavía), sin tocar
            // gestion__productos_costos. Para cuando cambió la REGLA, no el costo.
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $costo_neto_compra = floatval($_POST['costo_neto_compra'] ?? -1);

            $categorias = obtenerCategoriasPorProductos($conexion, [$producto_id]);
            $categoria_id = $categorias[$producto_id] ?? 0;
            $contexto = obtenerContextoCalculoPrecios($conexion, $empresa_idx, $entidad_id);

            $resultado = actualizarSoloPreciosVenta($conexion, $producto_id, $categoria_id, $empresa_idx, $entidad_id, $costo_neto_compra, $usuario_id, $contexto);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_costo_y_precio':
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $costo_neto_compra = floatval($_POST['costo_neto_compra'] ?? -1);
            $moneda_id = intval($_POST['moneda_id'] ?? 1);

            $categorias = obtenerCategoriasPorProductos($conexion, [$producto_id]);
            $categoria_id = $categorias[$producto_id] ?? 0;
            $contexto = obtenerContextoCalculoPrecios($conexion, $empresa_idx, $entidad_id);

            $resultado = aplicarActualizacionCostoYPrecios($conexion, $producto_id, $categoria_id, $empresa_idx, $entidad_id, $costo_neto_compra, $moneda_id, $usuario_id, $contexto);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_costos_masivo':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                manejarError('Debe indicar el proveedor', 400);
            }
            $resultado = actualizarCostosMasivoProveedor($conexion, $empresa_idx, $entidad_id, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_solo_costos_masivo':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                manejarError('Debe indicar el proveedor', 400);
            }
            $resultado = actualizarSoloCostosMasivoProveedor($conexion, $empresa_idx, $entidad_id, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_solo_precios_masivo':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                manejarError('Debe indicar el proveedor', 400);
            }
            $resultado = actualizarSoloPreciosVentaMasivoProveedor($conexion, $empresa_idx, $entidad_id, $pagina_idx, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'aplicar_porcentaje_lista':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $porcentaje = $_POST['porcentaje'] ?? null;
            if (empty($entidad_id)) {
                manejarError('Debe indicar el proveedor', 400);
            }
            if ($porcentaje === null || $porcentaje === '' || !is_numeric($porcentaje)) {
                manejarError('Debe indicar un porcentaje válido', 400);
            }
            $filtros = [
                'filtro_codigo' => $_POST['filtro_codigo'] ?? '',
                'filtro_marca' => $_POST['filtro_marca'] ?? '',
                'filtro_modelo' => $_POST['filtro_modelo'] ?? '',
                'filtro_submodelo' => $_POST['filtro_submodelo'] ?? ''
            ];
            $resultado = aplicarPorcentajeListaFiltrados($conexion, $empresa_idx, $entidad_id, $pagina_idx, $filtros, $porcentaje, $usuario_id);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en proveedor_precios_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
