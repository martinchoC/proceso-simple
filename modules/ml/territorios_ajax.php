<?php
/**
 * Dispatcher AJAX para Territorios y Husos Horarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/territorios_ajax.php
 * Seguridad copiada del estándar arca_integraciones_ajax.php
 */

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
    error_log("Error PHP [territorios_ajax]: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/territorios_model.php';

$conexion = $conn;

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

// Parámetros de contexto
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 0);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 305);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos');
}

// Validación de sesión activa
if (empty($_SESSION['logueado']) || empty($_SESSION['usuario_id'])) {
    manejarError('Sesión expirada o no iniciada', 401);
}

$usuario_id = intval($_SESSION['usuario_id']);

// Validación multi-tenant y acceso a página
if ($empresa_idx <= 0 || !usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    manejarError('Sin permisos para acceder a esta funcionalidad en la empresa seleccionada', 403);
}

// Liberar el lock de sesión
session_write_close();

try {
    switch ($accion) {
        case 'listar':
            $filtro_tipo = $_GET['filtro_tipo'] ?? $_POST['filtro_tipo'] ?? '';
            $filtro_estado = $_GET['filtro_estado'] ?? $_POST['filtro_estado'] ?? '';
            $datos = listarTerritorios($conexion, $pagina_idx, $filtro_tipo, $filtro_estado);
            enviarRespuesta(['success' => true, 'data' => $datos]);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID de territorio no válido', 400);
            }
            $territorio = obtenerTerritorioPorId($conexion, $id);
            if (!$territorio) {
                manejarError('Territorio no encontrado', 404);
            }
            enviarRespuesta(['success' => true, 'data' => $territorio]);
            break;

        case 'obtener_boton_agregar':
            // Buscar función de alta (origen 0 -> destino 1)
            $funciones = obtenerFuncionesPagina($conexion, $pagina_idx);
            $botonHtml = '';
            foreach ($funciones as $f) {
                if (intval($f['tabla_estado_registro_origen_id']) === 0) {
                    $nombre = htmlspecialchars($f['nombre_funcion'] ?? 'Agregar Territorio', ENT_QUOTES, 'UTF-8');
                    $botonHtml = "<button type=\"button\" class=\"btn btn-primary btn-sm\" id=\"btnNuevoTerritorio\">" .
                                 "<i class=\"fas fa-plus-circle me-1\"></i> {$nombre}</button>";
                    break;
                }
            }
            enviarRespuesta(['success' => true, 'data' => $botonHtml]);
            break;

        case 'obtener_tipos':
            $tipos = obtenerTiposTerritorios($conexion);
            enviarRespuesta(['success' => true, 'data' => $tipos]);
            break;

        case 'obtener_superiores':
            $excluir_id = intval($_GET['excluir_id'] ?? $_POST['excluir_id'] ?? 0);
            $superiores = obtenerTerritoriosSuperiores($conexion, $excluir_id);
            enviarRespuesta(['success' => true, 'data' => $superiores]);
            break;

        case 'agregar':
            $codigo = $_POST['codigo'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $tipo_id = intval($_POST['territorio_tipo_id'] ?? 0);
            $huso_horario = $_POST['huso_horario'] ?? 'America/Argentina/Buenos_Aires';
            $superior_id = intval($_POST['territorio_superior_id'] ?? 0);
            $vigente_desde = $_POST['vigente_desde'] ?? date('Y-m-d');

            $res = agregarTerritorio($conexion, $codigo, $nombre, $tipo_id, $huso_horario, $superior_id, $vigente_desde, $usuario_id);
            enviarRespuesta($res);
            break;

        case 'editar':
            $id = intval($_POST['territorio_id'] ?? 0);
            $codigo = $_POST['codigo'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $tipo_id = intval($_POST['territorio_tipo_id'] ?? 0);
            $huso_horario = $_POST['huso_horario'] ?? '';

            $res = editarTerritorio($conexion, $id, $codigo, $nombre, $tipo_id, $huso_horario, $usuario_id);
            enviarRespuesta($res);
            break;

        case 'cambiar_pertenencia':
            $id = intval($_POST['territorio_id'] ?? 0);
            $superior_id = intval($_POST['nuevo_superior_id'] ?? 0);
            $fecha_cambio = $_POST['fecha_cambio'] ?? date('Y-m-d');
            $es_principal = !empty($_POST['es_principal']) ? 1 : 0;

            $res = cambiarPertenenciaTerritorio($conexion, $id, $superior_id, $fecha_cambio, $es_principal, $usuario_id);
            enviarRespuesta($res);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_ejecutar = $_POST['accion_ejecutar'] ?? '';
            $estado_destino = intval($_POST['estado_destino'] ?? 0);

            if ($id <= 0 || $estado_destino <= 0) {
                manejarError('Parámetros de transición incompletos', 400);
            }

            $res = cambiarEstadoTerritorio($conexion, $id, $estado_destino, $usuario_id);
            enviarRespuesta($res);
            break;

        default:
            manejarError("Acción '{$accion}' no implementada", 400);
            break;
    }
} catch (Throwable $t) {
    error_log("Excepción no controlada [territorios_ajax]: " . $t->getMessage());
    manejarError("Error al procesar solicitud: " . $t->getMessage());
}
