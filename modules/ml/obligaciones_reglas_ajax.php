<?php
/**
 * Controlador AJAX para Reglas y Periodicidades (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_reglas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/obligaciones_reglas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 322; // Reglas y Periodicidades

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 322
$funciones_permitidas = [];
if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
    $sql_f = "SELECT pagina_funcion_id, accion_js, nombre_funcion 
              FROM conf__paginas_funciones 
              WHERE pagina_id = ? AND tabla_estado_registro_id = 1";
    $stmt_f = mysqli_prepare($conexion, $sql_f);
    mysqli_stmt_bind_param($stmt_f, "i", $pagina_idx);
    mysqli_stmt_execute($stmt_f);
    $res_f = mysqli_stmt_get_result($stmt_f);
    while ($rf = mysqli_fetch_assoc($res_f)) {
        $funciones_permitidas[$rf['accion_js']] = $rf;
    }
    mysqli_stmt_close($stmt_f);
} else {
    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_idx);
    if (!empty($perfiles)) {
        $perfil_ids = array_column($perfiles, 'empresa_perfil_id');
        $placeholders = implode(',', array_fill(0, count($perfil_ids), '?'));
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion
                  FROM conf__paginas_funciones pf
                  INNER JOIN conf__empresas_perfiles_funciones epf 
                      ON epf.pagina_funcion_id = pf.pagina_funcion_id 
                      AND epf.empresa_perfil_id IN ($placeholders)
                  WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_id = 1 
                    AND epf.asignado = 1";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        $types = str_repeat('i', count($perfil_ids)) . 'i';
        $params_f = array_merge($perfil_ids, [$pagina_idx]);
        mysqli_stmt_bind_param($stmt_f, $types, ...$params_f);
        mysqli_stmt_execute($stmt_f);
        $res_f = mysqli_stmt_get_result($stmt_f);
        while ($rf = mysqli_fetch_assoc($res_f)) {
            $funciones_permitidas[$rf['accion_js']] = $rf;
        }
        mysqli_stmt_close($stmt_f);
    }
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'permisos':
        echo json_encode([
            'success' => true,
            'permisos' => [
                'permite_criticidad' => isset($funciones_permitidas['guardar_criticidad']),
                'permite_periodicidad' => isset($funciones_permitidas['guardar_periodicidad']),
                'permite_regla' => isset($funciones_permitidas['guardar_regla_vencimiento'])
            ]
        ]);
        break;

    case 'listar_criticidades':
        $lista = criticidades_listar($conexion);
        echo json_encode(['success' => true, 'data' => $lista]);
        break;

    case 'guardar_criticidad':
        if (!isset($funciones_permitidas['guardar_criticidad'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para guardar criticidad.']);
            exit;
        }
        $res = criticidad_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_periodicidades':
        $lista = periodicidades_listar($conexion);
        echo json_encode(['success' => true, 'data' => $lista]);
        break;

    case 'guardar_periodicidad':
        if (!isset($funciones_permitidas['guardar_periodicidad'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para guardar periodicidad.']);
            exit;
        }
        $res = periodicidad_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_reglas':
        $lista = vencimientos_reglas_listar($conexion);
        echo json_encode(['success' => true, 'data' => $lista]);
        break;

    case 'guardar_regla':
        if (!isset($funciones_permitidas['guardar_regla_vencimiento'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para guardar regla de vencimiento.']);
            exit;
        }
        $res = vencimiento_regla_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
