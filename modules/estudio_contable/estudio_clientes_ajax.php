<?php
/**
 * Controlador AJAX: Padrón de Clientes
 * Módulo 4: Estudio Contable (estudio_clientes_ajax.php)
 * Página ID: 402
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/estudio_clientes_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión expirada o no iniciada.',
        'data' => null,
        'error' => 'SESSION_EXPIRED'
    ]);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 2);
$pagina_idx = 402;

if ($empresa_idx <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Empresa no especificada o inválida.',
        'data' => null,
        'error' => 'INVALID_TENANT'
    ]);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado a esta funcionalidad.',
        'data' => null,
        'error' => 'ACCESS_DENIED'
    ]);
    exit;
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'obtener_combos':
        $combos = estudio_clientes_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos recuperados exitosamente.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar':
        $filtros = [
            'cliente_tipo_id' => $_GET['cliente_tipo_id'] ?? '',
            'condicion_iva_id' => $_GET['condicion_iva_id'] ?? '',
            'terminacion_cuit' => $_GET['terminacion_cuit'] ?? '',
            'estado_id' => $_GET['estado_id'] ?? '',
            'busqueda' => $_GET['busqueda'] ?? ''
        ];
        $lista = estudio_clientes_listar($conexion, $empresa_idx, $filtros);
        echo json_encode([
            'success' => true,
            'message' => 'Clientes recuperados exitosamente.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'obtener':
        $cliente_id = intval($_GET['cliente_id'] ?? 0);
        $cliente = estudio_cliente_obtener($conexion, $empresa_idx, $cliente_id);
        if (!$cliente) {
            echo json_encode([
                'success' => false,
                'message' => 'Cliente no encontrado.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Cliente obtenido.',
            'data' => $cliente,
            'error' => null
        ]);
        break;

    case 'guardar':
        $res = estudio_cliente_guardar($conexion, $empresa_idx, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'guardar_jurisdiccion':
        $res = estudio_cliente_guardar_jurisdiccion($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'eliminar_jurisdiccion':
        $cliente_jurisdiccion_id = intval($_POST['cliente_jurisdiccion_id'] ?? 0);
        $res = estudio_cliente_eliminar_jurisdiccion($conexion, $empresa_idx, $cliente_jurisdiccion_id);
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 1);
        $res = estudio_cliente_cambiar_estado($conexion, $empresa_idx, $cliente_id, $nuevo_estado_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => "Acción '{$accion}' no reconocida.",
            'data' => null,
            'error' => 'UNKNOWN_ACTION'
        ]);
        break;
}
