<?php
/**
 * Controlador AJAX: Normas Legales y Motor de Reglas
 * Módulo 4: Estudio Contable (normas_gestion_ajax.php)
 * Página ID: 407
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/normas_gestion_model.php';

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
$pagina_idx = 407;

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
        $combos = normas_combos($conexion, $empresa_idx);
        echo json_encode([
            'success' => true,
            'message' => 'Combos obtenidos.',
            'data' => $combos,
            'error' => null
        ]);
        break;

    case 'listar':
        $filtros = [
            'organismo' => $_GET['organismo'] ?? '',
            'estado_id' => $_GET['estado_id'] ?? ''
        ];
        $lista = normas_listar($conexion, $empresa_idx, $filtros);
        echo json_encode([
            'success' => true,
            'message' => 'Normas listadas.',
            'data' => $lista,
            'error' => null
        ]);
        break;

    case 'obtener':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $norma = norma_obtener($conexion, $empresa_idx, $norma_id);
        if (!$norma) {
            echo json_encode([
                'success' => false,
                'message' => 'Norma no encontrada.',
                'data' => null,
                'error' => 'NOT_FOUND'
            ]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Norma obtenida.',
            'data' => $norma,
            'error' => null
        ]);
        break;

    case 'guardar':
        $res = norma_guardar($conexion, $empresa_idx, $_POST);
        echo json_encode($res);
        break;

    case 'evaluar_impacto':
        $norma_id = intval($_POST['norma_id'] ?? 0);
        $res = norma_evaluar_impacto($conexion, $empresa_idx, $norma_id, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_impacto':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $lista = norma_impacto_listar($conexion, $norma_id);
        echo json_encode([
            'success' => true,
            'message' => 'Bitácora de impacto listada.',
            'data' => $lista,
            'error' => null
        ]);
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
