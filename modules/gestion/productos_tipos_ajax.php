<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['productos_tipos.php']]);
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
require_once "productos_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 96);
// Solo una página de este módulo (si no, la de productos_tipos.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 2, (int)$pagina_idx, 'productos_tipos.php');

if (!$conexion) {
    manejarError('Error de conexión a la base de datos');
}

try {
    switch ($accion) {
        case 'listar':
            $filtro_estado = $_GET['filtro_estado'] ?? $_POST['filtro_estado'] ?? '';
            $tipos = obtenerProductosTipos($conexion, $empresa_idx, $pagina_idx, $filtro_estado);
            enviarRespuesta(['success' => true, 'data' => $tipos]);
            break;

        case 'obtener_boton_agregar':
            enviarRespuesta(['success' => true, 'data' => obtenerBotonAgregar($conexion, $pagina_idx)]);
            break;

        case 'obtener_estados':
            enviarRespuesta(['success' => true, 'data' => obtenerEstadosProductosTipos($conexion)]);
            break;

        case 'obtener_cuentas':
            enviarRespuesta(['success' => true, 'data' => obtenerCuentasContables($conexion, $empresa_idx)]);
            break;

        case 'obtener':
            $id = intval($_GET['producto_tipo_id'] ?? $_POST['producto_tipo_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            $tipo = obtenerProductoTipoPorId($conexion, $id, $empresa_idx);
            if ($tipo) {
                enviarRespuesta(['success' => true, 'data' => $tipo]);
            }
            enviarRespuesta(['success' => false, 'message' => 'Tipo de producto no encontrado']);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            enviarRespuesta(agregarProductoTipo($conexion, $_POST, $empresa_idx, $pagina_idx));
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['producto_tipo_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            enviarRespuesta(editarProductoTipo($conexion, $id, $_POST, $empresa_idx));
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['producto_tipo_id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            if ($id <= 0 || $accion_js === '') {
                enviarRespuesta(['success' => false, 'message' => 'Datos incompletos']);
            }
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            enviarRespuesta(ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_idx, $pagina_idx));
            break;

        default:
            manejarError('Acción no válida: ' . $accion, 400);
    }
} catch (Throwable $e) {
    error_log("productos_tipos_ajax [$accion]: " . $e->getMessage());
    manejarError('Error interno del servidor');
}
