<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['productos.php']]);
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
require_once "productos_model.php";
require_once __DIR__ . '/stock_niveles_model.php';

/**
 * Stock mínimo / máximo por boca cargado en el modal de productos.php.
 * Se graba después del producto, en su propia transacción (snvGuardarNivelesProducto, que también
 * reevalúa las alertas de reposición). Solo para quien tiene acceso a productos.php (página 540).
 * @return string|null aviso para el usuario si los niveles no se pudieron guardar
 */
function productosGuardarNivelesStock($conexion, array $ctx, int $producto_id): ?string
{
    if (!isset($_POST['niveles_stock']) || $producto_id <= 0) return null;
    $empresa_id = (int)$ctx['empresa_id'];
    $usuario_id = (int)$ctx['usuario_id'];
    if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_id, 540)) {
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
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 540);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos');
}

try {
    switch ($accion) {
        case 'listar':
            $data = obtenerProductosPaginados($conexion, $empresa_idx, $pagina_idx, [
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
            enviarRespuesta(['success' => true, 'data' => obtenerEstadosProductos($conexion)]);
            break;

        case 'obtener_combos':
            enviarRespuesta(['success' => true, 'data' => [
                'tipos' => obtenerTiposProducto($conexion, $empresa_idx),
                'categorias' => obtenerCategoriasProducto($conexion, $empresa_idx),
                'unidades' => obtenerUnidadesMedida($conexion, $empresa_idx),
                'iva_alicuotas' => obtenerIvaAlicuotas($conexion, $empresa_idx),
                'cuentas' => obtenerCuentasContables($conexion, $empresa_idx)
            ]]);
            break;

        case 'obtener':
            $id = intval($_GET['producto_id'] ?? $_POST['producto_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            $producto = obtenerProductoPorId($conexion, $id, $empresa_idx);
            if ($producto) {
                enviarRespuesta(['success' => true, 'data' => $producto]);
            }
            enviarRespuesta(['success' => false, 'message' => 'Producto no encontrado']);
            break;

        case 'agregar':
            $resultado = agregarProducto($conexion, $_POST, $empresa_idx, $pagina_idx);
            if (!empty($resultado['success'])) {
                $aviso = productosGuardarNivelesStock($conexion, $ctx, intval($resultado['producto_id'] ?? 0));
                if ($aviso) $resultado['aviso'] = $aviso;
            }
            enviarRespuesta($resultado);
            break;

        case 'editar':
            $resultado = editarProducto($conexion, $_POST, $empresa_idx, $pagina_idx);
            if (!empty($resultado['success'])) {
                $aviso = productosGuardarNivelesStock($conexion, $ctx, intval($_POST['producto_id'] ?? 0));
                if ($aviso) $resultado['aviso'] = $aviso;
            }
            enviarRespuesta($resultado);
            break;

        case 'obtener_niveles_stock':
            // Bocas del usuario con el mínimo / máximo y el stock actual del producto (0 = nuevo).
            enviarRespuesta(['success' => true, 'data' => snvNivelesProducto($conexion, (int)$ctx['empresa_id'], (int)$ctx['usuario_id'],
                intval($_GET['producto_id'] ?? $_POST['producto_id'] ?? 0))]);
            break;

        case 'ejecutar_accion':
            $producto_id = intval($_POST['producto_id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            if ($producto_id <= 0 || empty($accion_js)) {
                manejarError('Parámetros de acción inválidos', 400);
            }
            enviarRespuesta(ejecutarAccionProducto($conexion, $producto_id, $accion_js, $empresa_idx, $pagina_idx));
            break;

        default:
            manejarError("Acción '$accion' no válida", 400);
    }
} catch (Exception $e) {
    error_log("Excepción en productos_ajax.php: " . $e->getMessage());
    manejarError("Error al procesar la solicitud: " . $e->getMessage());
}