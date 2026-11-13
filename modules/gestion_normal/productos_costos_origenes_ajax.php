<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['productos_costos_origenes.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

require_once __DIR__ . '/../../db.php';
require_once "productos_costos_origenes_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 79);
// Solo una página de este módulo (si no, la de productos_costos_origenes.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'productos_costos_origenes.php');

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $datos = obtenerOrigenesCostos($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($datos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'producto_costo_origen_codigo' => trim($_POST['producto_costo_origen_codigo'] ?? ''),
                'producto_costo_origen_nombre' => trim($_POST['producto_costo_origen_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = agregarOrigenCosto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['producto_costo_origen_id'] ?? 0);
            $data = [
                'producto_costo_origen_codigo' => trim($_POST['producto_costo_origen_codigo'] ?? ''),
                'producto_costo_origen_nombre' => trim($_POST['producto_costo_origen_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = editarOrigenCosto($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['producto_costo_origen_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['producto_costo_origen_id'] ?? $_GET['producto_costo_origen_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $registro = obtenerOrigenCostoPorId($conexion, $id);
            if ($registro) {
                echo json_encode($registro, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Registro no encontrado'], JSON_UNESCAPED_UNICODE);
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