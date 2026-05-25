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
require_once "cont_tipos_asientos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_id = intval($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 2);
$pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 78);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $tipos = obtenerTiposAsientos($conexion, $empresa_id, $pagina_id);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            error_log("Botón agregar obtenido: " . json_encode($boton_agregar));
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $cont_tipo_asiento_id = intval($_POST['cont_tipo_asiento_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_id_local = intval($_POST['empresa_id'] ?? $empresa_id);
            $pagina_id_local = intval($_POST['pagina_id'] ?? $pagina_id);
            $resultado = ejecutarTransicionEstado($conexion, $cont_tipo_asiento_id, $accion_js, $empresa_id_local, $pagina_id_local);
            echo json_encode($resultado);
            break;

        case 'agregar':
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'cont_tipo_asiento' => trim($_POST['cont_tipo_asiento'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'origen' => $_POST['origen'] ?? 'manual',
                'modulo_origen' => trim($_POST['modulo_origen'] ?? ''),
                'estado' => $_POST['estado'] ?? 'activo',
                'empresa_id' => $empresa_id,
                'pagina_id' => $pagina_id
            ];
            $resultado = agregarTipoAsiento($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['cont_tipo_asiento_id'] ?? 0);
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'cont_tipo_asiento' => trim($_POST['cont_tipo_asiento'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'origen' => $_POST['origen'] ?? 'manual',
                'modulo_origen' => trim($_POST['modulo_origen'] ?? ''),
                'estado' => $_POST['estado'] ?? 'activo',
                'empresa_id' => $empresa_id
            ];
            $resultado = editarTipoAsiento($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['cont_tipo_asiento_id'] ?? $_GET['cont_tipo_asiento_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $tipo = obtenerTipoAsientoPorId($conexion, $id, $empresa_id);
            if ($tipo) {
                echo json_encode($tipo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Tipo de asiento no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en cont_tipos_asientos_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>