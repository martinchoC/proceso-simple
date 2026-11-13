<?php
require_once __DIR__ . '/../../core/empresa_config.php';
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once "arca_integraciones_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 0);
// El pagina_idx no se toma del request: con él se valida el permiso, y un valor elegido por el
// cliente permitiría validar contra cualquier otra página a la que el usuario tenga acceso.
$pagina_idx = pagina_id_por_url($conn, 2, 'arca_integraciones.php');

if (!$conexion) {
    manejarError('Error de conexión a la base de datos');
}

// Esta página firma con el certificado de la empresa y llama a ARCA: a diferencia
// de los ABM de diccionario, exige sesión y acceso a la página para la empresa
// (mismo chequeo que templates/adminlte/header1.php).
if (empty($_SESSION['logueado']) || empty($_SESSION['usuario_id'])) {
    manejarError('Sesión expirada', 401);
}
$usuario_id = intval($_SESSION['usuario_id']);
if ($empresa_idx <= 0 || !usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    manejarError('Sin permisos para esta página en la empresa seleccionada', 403);
}
// La sesión no se modifica: se libera para no bloquear otras pestañas durante
// llamadas a ARCA que pueden tardar.
session_write_close();

try {
    switch ($accion) {
        case 'listar':
            $filtro_estado = $_GET['filtro_estado'] ?? $_POST['filtro_estado'] ?? '';
            enviarRespuesta(['success' => true, 'data' => obtenerIntegracionesEmpresa($conexion, $empresa_idx, $pagina_idx, $filtro_estado)]);
            break;

        case 'obtener_boton_agregar':
            enviarRespuesta(['success' => true, 'data' => obtenerBotonAgregar($conexion, $pagina_idx)]);
            break;

        case 'obtener_estados':
            enviarRespuesta(['success' => true, 'data' => obtenerEstadosIntegraciones($conexion)]);
            break;

        case 'obtener_combos':
            enviarRespuesta(['success' => true, 'data' => [
                'ambientes' => obtenerAmbientesActivos($conexion),
                'archivos' => arca_listar_archivos_certificados(emp_cuit($conexion, $empresa_idx)),
                'puntos_venta' => obtenerPuntosVentaFiscales($conexion, $empresa_idx),
                'comprobantes' => obtenerComprobantesFiscales($conexion)
            ]]);
            break;

        case 'obtener':
            $id = intval($_GET['integracion_empresa_id'] ?? $_POST['integracion_empresa_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            $registro = obtenerIntegracionEmpresaPorId($conexion, $id, $empresa_idx);
            if ($registro) {
                enviarRespuesta(['success' => true, 'data' => $registro]);
            }
            enviarRespuesta(['success' => false, 'message' => 'Integración no encontrada']);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            enviarRespuesta(agregarIntegracionEmpresa($conexion, $_POST, $empresa_idx, $pagina_idx, $usuario_id));
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['integracion_empresa_id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no proporcionado', 400);
            }
            enviarRespuesta(editarIntegracionEmpresa($conexion, $id, $_POST, $empresa_idx));
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['integracion_empresa_id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            if ($id <= 0 || $accion_js === '') {
                enviarRespuesta(['success' => false, 'message' => 'Datos incompletos']);
            }
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            enviarRespuesta(ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_idx, $pagina_idx));
            break;

        case 'prueba':
            $id = intval($_POST['integracion_empresa_id'] ?? 0);
            $operacion = trim($_POST['operacion'] ?? '');
            if ($id <= 0 || $operacion === '') {
                enviarRespuesta(['success' => false, 'message' => 'Datos incompletos']);
            }
            enviarRespuesta(ejecutarPruebaArca($conexion, $id, $operacion, $_POST, $empresa_idx, $pagina_idx));
            break;

        // ---- Delegación del certificado a otras empresas ----
        case 'listar_delegaciones':
            $id = intval($_GET['integracion_empresa_id'] ?? 0);
            enviarRespuesta(obtenerDelegacionesIntegracion($conexion, $id, $empresa_idx, $pagina_idx));
            break;

        case 'empresas_delegables':
            enviarRespuesta(['success' => true, 'data' => obtenerEmpresasDelegables($conexion, $usuario_id, $empresa_idx)]);
            break;

        case 'guardar_delegacion':
            $id = intval($_POST['integracion_empresa_id'] ?? 0);
            enviarRespuesta(guardarDelegacionIntegracion($conexion, $id, $_POST, $empresa_idx, $pagina_idx, $usuario_id));
            break;

        case 'revocar_delegacion':
            $id = intval($_POST['integracion_empresa_id'] ?? 0);
            $delegacion_id = intval($_POST['integracion_delegacion_id'] ?? 0);
            enviarRespuesta(revocarDelegacionIntegracion($conexion, $id, $delegacion_id, $empresa_idx, $pagina_idx));
            break;

        case 'listar_resultados':
            $ambiente_id = intval($_GET['integracion_ambiente_id'] ?? 0);
            enviarRespuesta(['success' => true, 'data' => obtenerResultadosIntegraciones($conexion, $empresa_idx, $ambiente_id)]);
            break;

        case 'obtener_resultado':
            $id = intval($_GET['integracion_resultado_id'] ?? 0);
            $detalle = $id > 0 ? obtenerResultadoDetalle($conexion, $id, $empresa_idx) : null;
            if ($detalle) {
                enviarRespuesta(['success' => true, 'data' => $detalle]);
            }
            enviarRespuesta(['success' => false, 'message' => 'Registro no encontrado']);
            break;

        default:
            manejarError('Acción no válida: ' . $accion, 400);
    }
} catch (Throwable $e) {
    error_log("arca_integraciones_ajax [$accion]: " . $e->getMessage());
    manejarError('Error interno del servidor');
}
