<?php
/**
 * Controlador AJAX: Tarifarios y Conceptos de Honorarios
 * Módulo 4: Estudio Contable (honorarios_conceptos_ajax.php)
 * Página ID: 405
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/honorarios_conceptos_model.php';

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
$pagina_idx = 405;

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
        $combos = honorarios_conceptos_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar_conceptos':
        $lista = honorarios_conceptos_listar($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Conceptos listados.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'guardar_concepto':
        $res = honorarios_concepto_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'listar_abonos':
        $cliente_id = intval($_GET['cliente_id'] ?? 0);
        $lista = clientes_abonos_listar($conexion, $empresa_idx, $cliente_id);
        echo json_encode([
            'success' => true,
            'message' => 'Abonos listados.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'guardar_abono':
        $res = cliente_abono_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'eliminar_abono':
        $abono_id = intval($_POST['abono_id'] ?? 0);
        $res = cliente_abono_eliminar($conexion, $empresa_idx, $abono_id);
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
