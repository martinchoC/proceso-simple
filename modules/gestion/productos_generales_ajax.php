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

ob_start();

require_once __DIR__ . '/../../db.php';
require_once "productos_generales_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 97);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos');
}

try {
    switch ($accion) {
        case 'listar':
            // Respuesta en el formato server-side de DataTables (draw / recordsTotal / recordsFiltered / data)
            $data = obtenerProductosGeneralesPaginados($conexion, $empresa_idx, $pagina_idx, [
                'start' => $_GET['start'] ?? 0,
                'length' => $_GET['length'] ?? 25,
                'search' => $_GET['search']['value'] ?? '',
                'order_column' => $_GET['order'][0]['column'] ?? 1,
                'order_dir' => $_GET['order'][0]['dir'] ?? 'asc',
                'filtro_estado' => $_GET['filtro_estado'] ?? '',
                'filtro_tipo' => $_GET['filtro_tipo'] ?? '',
                'filtro_servicio' => $_GET['filtro_servicio'] ?? '',
                'filtro_codigo' => $_GET['filtro_codigo'] ?? ''
            ]);
            enviarRespuesta([
                'success' => true,
                'draw' => intval($_GET['draw'] ?? 1),
                'recordsTotal' => $data['total'],
                'recordsFiltered' => $data['filtered'],
                'data' => $data['productos']
            ]);
            break;

        case 'obtener_boton_agregar':
            enviarRespuesta(['success' => true, 'data' => obtenerBotonAgregar($conexion, $pagina_idx)]);
            break;

        case 'obtener_estados':
            enviarRespuesta(['success' => true, 'data' => obtenerEstadosProductosGenerales($conexion)]);
            break;

        case 'obtener_combos':
            // Todos los combos del formulario en una sola vuelta
            enviarRespuesta(['success' => true, 'data' => [
                'tipos' => obtenerTiposProductoGenerales($conexion),
                'categorias' => obtenerCategoriasProductoGenerales($conexion, $empresa_idx),
                'unidades' => obtenerUnidadesMedidaGenerales($conexion, $empresa_idx),
                'iva_alicuotas' => obtenerIvaAlicuotasGenerales($conexion),
                'cuentas' => obtenerCuentasContablesGenerales($conexion, $empresa_idx)
            ]]);
            break;

        case 'obtener':
            $id = intval($_GET['producto_id'] ?? $_POST['producto_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            $producto = obtenerProductoGeneralPorId($conexion, $id, $empresa_idx);
            if ($producto) {
                enviarRespuesta(['success' => true, 'data' => $producto]);
            }
            enviarRespuesta(['success' => false, 'message' => 'Producto no encontrado']);
            break;

        case 'agregar':
            enviarRespuesta(agregarProductoGeneral($conexion, $_POST, $empresa_idx, $pagina_idx));
            break;

        case 'editar':
            $id = intval($_POST['producto_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            enviarRespuesta(editarProductoGeneral($conexion, $id, $_POST, $empresa_idx));
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['producto_id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            if ($id <= 0 || $accion_js === '') {
                enviarRespuesta(['success' => false, 'message' => 'Datos incompletos']);
            }
            enviarRespuesta(ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_idx, $pagina_idx));
            break;

        default:
            manejarError('Acción no válida: ' . $accion, 400);
    }
} catch (Throwable $e) {
    error_log("productos_generales_ajax [$accion]: " . $e->getMessage());
    manejarError('Error interno del servidor');
}
