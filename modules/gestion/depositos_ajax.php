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
    enviarRespuesta(['error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "depositos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) manejarError('Acción no especificada', 400);

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 71);

if (!$conexion) manejarError('Error de conexión a la base de datos', 500);

try {
    switch ($accion) {
        case 'listar':
            $puntos = obtenerDepositos($conexion, $empresa_idx, $pagina_idx);
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

        case 'obtener_estados':
            $estados = obtenerEstadosRegistro($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $deposito_id = intval($_POST['deposito_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);
            $resultado = ejecutarTransicionEstado($conexion, $deposito_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_nombre' => trim($_POST['deposito_nombre'] ?? ''),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'permite_ingresos' => isset($_POST['permite_ingresos']) ? intval($_POST['permite_ingresos']) : 1,
                'permite_egresos' => isset($_POST['permite_egresos']) ? intval($_POST['permite_egresos']) : 1,
                'es_principal' => isset($_POST['es_principal']) ? intval($_POST['es_principal']) : 0,
                'orden' => isset($_POST['orden']) ? intval($_POST['orden']) : 1,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            $resultado = agregarDeposito($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['deposito_id'] ?? 0);
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_nombre' => trim($_POST['deposito_nombre'] ?? ''),
                'codigo' => trim($_POST['codigo'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'permite_ingresos' => isset($_POST['permite_ingresos']) ? intval($_POST['permite_ingresos']) : 1,
                'permite_egresos' => isset($_POST['permite_egresos']) ? intval($_POST['permite_egresos']) : 1,
                'es_principal' => isset($_POST['es_principal']) ? intval($_POST['es_principal']) : 0,
                'orden' => isset($_POST['orden']) ? intval($_POST['orden']) : 1,
                'empresa_idx' => $empresa_idx
            ];
            $resultado = editarDeposito($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['deposito_id'] ?? $_GET['deposito_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $deposito = obtenerDepositoPorId($conexion, $id, $empresa_idx);
            if ($deposito) {
                echo json_encode($deposito, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Depósito no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en depositos_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) mysqli_close($conexion);
?>