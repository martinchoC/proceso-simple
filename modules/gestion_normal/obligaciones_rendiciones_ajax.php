<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['obligaciones_rendiciones.php']]);
$teso_pagina_id = pagina_id_por_url($conn, 5, 'obligaciones_rendiciones.php');
/**
 * obligaciones_rendiciones_ajax.php
 *
 * Dispatcher AJAX para Obligaciones Impositivas y Rendiciones de Gastos
 * Sistema gestion_multipyme - Módulo Tesorería
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

function enviarRespuesta($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function manejarError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuesta(['success' => false, 'error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP en obligaciones AJAX: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/tesoreria_movimientos_model.php';
require_once __DIR__ . '/obligaciones_rendiciones_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = intval($_SESSION['usuario_id'] ?? 1);

// Cada acción exige la función de la página asignada al perfil del usuario (los botones se arman con el mismo criterio)
function tesoExigirFuncion($conexion, int $pagina_id, string $accion_js): void
{
    if (!permisos_puede($conexion, $pagina_id, $accion_js)) {
        manejarError('Tu perfil no tiene permiso para esta acción.', 403);
    }
}

try {
    switch ($accion) {
        // --- OBLIGACIONES IMPOSITIVAS ---
        case 'listar_obligaciones':
            $filtros = [
                'periodo'   => $_GET['periodo'] ?? $_POST['periodo'] ?? null,
                'desde'     => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'     => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'estado_id' => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : null
            ];
            $resultado = obtenerObligacionesListado($conexion, $empresa_idx, $filtros);
            enviarRespuesta($resultado);
            break;

        case 'guardar_obligacion':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $resultado = registrarObligacionImpositiva($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'pagar_obligacion':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $resultado = pagarObligacionImpositiva($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        // --- RENDICIONES DE GASTOS ---
        case 'listar_rendiciones':
            $filtros = [
                'desde' => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta' => $_GET['hasta'] ?? $_POST['hasta'] ?? null
            ];
            $resultado = obtenerRendicionesListado($conexion, $empresa_idx, $filtros, $teso_pagina_id);
            enviarRespuesta($resultado);
            break;

        case 'guardar_anticipo':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            tesoExigirFuncion($conexion, $teso_pagina_id, 'agregar_anticipo');
            $resultado = registrarAnticipoRendir($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'guardar_rendicion':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            tesoExigirFuncion($conexion, $teso_pagina_id, 'agregar_rendicion');
            $resultado = registrarRendicionGastos($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'reembolsar_rendicion':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            tesoExigirFuncion($conexion, $teso_pagina_id, 'reembolsar');
            $resultado = reembolsarRendicion($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        // --- MAESTROS ---
        case 'maestros':
            $cuentas = obtenerCuentasEmpresa($conexion, $empresa_idx);
            
            $res_suc = $conexion->query("SELECT sucursal_id, sucursal_nombre AS sucursal FROM gestion__sucursales WHERE empresa_id = $empresa_idx AND tabla_estado_registro_id = 1");
            $sucursales = $res_suc ? $res_suc->fetch_all(MYSQLI_ASSOC) : [];

            $res_ent = $conexion->query("SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE empresa_id = $empresa_idx AND tabla_estado_registro_id = 1 ORDER BY entidad_nombre ASC");
            $entidades = $res_ent ? $res_ent->fetch_all(MYSQLI_ASSOC) : [];

            $res_cat = $conexion->query("SELECT categoria_gasto_id, categoria_gasto, cont_cuenta_id FROM gestion__categorias_gasto WHERE empresa_id = $empresa_idx OR empresa_id = 0");
            $categorias = $res_cat ? $res_cat->fetch_all(MYSQLI_ASSOC) : [];

            // Anticipos pendientes de rendir
            $res_ant = $conexion->query("SELECT ra.rendicion_anticipo_id, ra.descripcion, c.importe_pendiente, ra.comprobante_pv, ra.comprobante_nro, e.entidad_nombre
                                         FROM gestion__rendiciones_anticipos ra
                                         JOIN gestion__comprobantes c ON c.comprobante_id = ra.comprobante_id
                                         LEFT JOIN gestion__entidades e ON e.entidad_id = ra.entidad_id
                                         WHERE ra.empresa_id = $empresa_idx AND c.importe_pendiente > 0");
            $anticipos_pendientes = $res_ant ? $res_ant->fetch_all(MYSQLI_ASSOC) : [];

            enviarRespuesta([
                'success' => true,
                'data' => [
                    'cuentas'              => $cuentas,
                    'sucursales'           => $sucursales,
                    'entidades'            => $entidades,
                    'categorias_gasto'     => $categorias,
                    'anticipos_pendientes' => $anticipos_pendientes
                ]
            ]);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
