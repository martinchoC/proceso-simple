<?php
/**
 * Controlador AJAX: Facturación Masiva de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion_ajax.php)
 * Página ID: 406
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/honorarios_facturacion_model.php';

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
$pagina_idx = 406;

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
    case 'consolidar':
        $periodo = intval($_GET['periodo_fiscal'] ?? 0);
        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido.',
                'data' => null,
                'error' => 'INVALID_PERIOD'
            ]);
            exit;
        }

        $items = honorarios_facturacion_consolidar($conexion, $empresa_idx, $periodo);
        echo json_encode([
            'success' => true,
            'message' => 'Abonos consolidados para el período.',
            'data' => $items,
            'error' => null
        ]);
        break;

    case 'emitir_lote':
        $periodo = intval($_POST['periodo_fiscal'] ?? 0);
        $abonos = $_POST['abonos'] ?? [];

        if (!is_array($abonos) || empty($abonos)) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe seleccionar al menos un abono para emitir.',
                'data' => null,
                'error' => 'NO_SELECTION'
            ]);
            exit;
        }

        $abono_ids = array_map('intval', $abonos);
        $res = honorarios_facturacion_emitir_lote($conexion, $empresa_idx, $periodo, $abono_ids, $usuario_id);
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
