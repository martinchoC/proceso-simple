<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['puntos_venta.php']]);
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
    enviarRespuesta(['error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "puntos_venta_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 70);
// Solo una página de este módulo (si no, la de puntos_venta.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'puntos_venta.php');

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $puntos = obtenerPuntosVenta($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($puntos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales_empresa':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_bocas_por_sucursal':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $bocas = $sucursal_id > 0 ? obtenerBocasPorSucursal($conexion, $sucursal_id, $empresa_idx_local) : [];
            echo json_encode($bocas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_tipos':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $tipos = obtenerComprobantesTipos($conexion, $empresa_idx_local);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados':
            $estados = obtenerEstadosRegistro($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $punto_venta_id = intval($_POST['punto_venta_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $punto_venta_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'obtener_sistemas_emision':
            echo json_encode(array_values(obtenerCatalogoSistemasEmision()), JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'boca_id' => intval($_POST['boca_id'] ?? 0),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'codigo_fiscal' => trim($_POST['codigo_fiscal'] ?? ''),
                'sistema_emision' => trim($_POST['sistema_emision'] ?? 'WSFE'),
                'es_web' => (!empty($_POST['sistema_emision']) && $_POST['sistema_emision'] === 'WSFE') ? 1 : intval($_POST['es_web'] ?? 0),
                'comprobantes' => json_decode($_POST['comprobantes'] ?? '[]', true) ?: [],
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarPuntoVenta($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['punto_venta_id'] ?? 0);

            error_log("=== EDITAR - ID recibido: $id ===");
            error_log("POST completo: " . print_r($_POST, true));

            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'boca_id' => intval($_POST['boca_id'] ?? 0),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'codigo_fiscal' => trim($_POST['codigo_fiscal'] ?? ''),
                'sistema_emision' => trim($_POST['sistema_emision'] ?? 'WSFE'),
                'es_web' => (!empty($_POST['sistema_emision']) && $_POST['sistema_emision'] === 'WSFE') ? 1 : intval($_POST['es_web'] ?? 0),
                'comprobantes' => json_decode($_POST['comprobantes'] ?? '[]', true) ?: [],
                'empresa_idx' => $empresa_idx
            ];

            error_log("data armada para editarPuntoVenta: " . print_r($data, true));

            $resultado = editarPuntoVenta($conexion, $id, $data);
            error_log("resultado de editarPuntoVenta: " . print_r($resultado, true));

            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['punto_venta_id'] ?? $_GET['punto_venta_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $punto = obtenerPuntoVentaPorId($conexion, $id, $empresa_idx);
            if ($punto) {
                echo json_encode($punto, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Punto de venta no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en puntos_venta_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>