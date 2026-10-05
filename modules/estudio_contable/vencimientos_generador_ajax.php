<?php
/**
 * Controlador AJAX: Generador Masivo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_generador_ajax.php)
 * Página ID: 404
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/vencimientos_generador_model.php';

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
$pagina_idx = 404;

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
        $combos = vencimientos_generador_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'preview':
        $periodo = intval($_GET['periodo_fiscal'] ?? 0);
        $obligacion_id = intval($_GET['impuesto_obligacion_id'] ?? 0);
        $cliente_id = intval($_GET['cliente_id'] ?? 0);

        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido. Formato requerido: YYYYMM (Ej: 202610)',
                'data' => null,
                'error' => 'INVALID_PERIODO'
            ]);
            exit;
        }

        $items = vencimientos_generador_preview($conexion, $empresa_idx, $periodo, $obligacion_id, $cliente_id);
        echo json_encode([
            'success' => true,
            'message' => 'Vista previa generada.',
            'data' => $items,
            'error' => null
        ]);
        break;

    case 'ejecutar':
        $periodo = intval($_POST['periodo_fiscal'] ?? 0);
        $obligacion_id = intval($_POST['impuesto_obligacion_id'] ?? 0);
        $cliente_id = intval($_POST['cliente_id'] ?? 0);

        if ($periodo < 200001 || $periodo > 209912) {
            echo json_encode([
                'success' => false,
                'message' => 'Período fiscal inválido.',
                'data' => null,
                'error' => 'INVALID_PERIODO'
            ]);
            exit;
        }

        $res = vencimientos_generador_ejecutar($conexion, $empresa_idx, $periodo, $obligacion_id, $cliente_id, $usuario_id);
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
