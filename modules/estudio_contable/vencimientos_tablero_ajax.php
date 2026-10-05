<?php
/**
 * Controlador AJAX: Tablero Operativo de Vencimientos
 * Módulo 4: Estudio Contable (vencimientos_tablero_ajax.php)
 * Página ID: 403
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/vencimientos_tablero_model.php';

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
$pagina_idx = 403;

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
        $combos = vencimientos_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'kpis':
        $periodo = intval($_GET['periodo_fiscal'] ?? 0);
        $kpis = vencimientos_tablero_kpis($conexion, $empresa_idx, $periodo);
        echo json_encode([
            'success' => true,
            'message' => 'KPIs calculados.',
            'data' => $kpis,
            'error' => null
        ]);
        break;

    case 'listar':
        $filtros = [
            'periodo_fiscal' => $_GET['periodo_fiscal'] ?? '',
            'cliente_id' => $_GET['cliente_id'] ?? '',
            'impuesto_obligacion_id' => $_GET['impuesto_obligacion_id'] ?? '',
            'tabla_estado_registro_id' => $_GET['tabla_estado_registro_id'] ?? '',
            'urgencia' => $_GET['urgencia'] ?? ''
        ];
        $lista = vencimientos_tablero_listar($conexion, $empresa_idx, $filtros);
        echo json_encode([
            'success' => true,
            'message' => 'Vencimientos recuperados.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'obtener':
        $vencimiento_id = intval($_GET['vencimiento_id'] ?? 0);
        $vto = vencimiento_obtener($conexion, $empresa_idx, $vencimiento_id);
        if (!$vto) {
            echo json_encode([
                'success' => false,
                'message' => 'Vencimiento no encontrado.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Vencimiento obtenido.',
            'data' => $vto,
            'error' => null
        ]);
        break;

    case 'cambiar_estado':
        $vencimiento_id = intval($_POST['vencimiento_id'] ?? 0);
        $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 0);
        $res = vencimiento_cambiar_estado($conexion, $empresa_idx, $vencimiento_id, $nuevo_estado_id, $_POST, $usuario_id);
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
