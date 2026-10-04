<?php
/**
 * Controlador AJAX para Conflictos Normativos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_conflictos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/normas_conflictos_model.php';
require_once __DIR__ . '/normas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 314; // Conflictos Normativos

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 314
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
    case 'combos':
        $abogados = normas_conflictos_abogados($conexion);
        $normas = normas_listar($conexion, ['solo_vigentes' => true]);
        echo json_encode([
            'success' => true,
            'data' => [
                'abogados' => $abogados,
                'normas' => $normas,
                'permite_registrar' => isset($funciones_permitidas['registrar_conflicto'])
            ]
        ]);
        break;

    case 'listar':
        $estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = normas_conflictos_listar($conexion, $estado);

        $permite_escalar = isset($funciones_permitidas['escalar_conflicto']);
        $permite_resolver = isset($funciones_permitidas['resolver_conflicto']);

        foreach ($lista as &$c) {
            $est = intval($c['tabla_estado_registro_id']);
            $c['permite_escalar'] = $permite_escalar && $est === 2084;
            $c['permite_resolver'] = $permite_resolver && in_array($est, [2084, 2085], true);
        }
        unset($c);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permisos' => [
                'permite_registrar' => isset($funciones_permitidas['registrar_conflicto']),
                'permite_escalar' => $permite_escalar,
                'permite_resolver' => $permite_resolver
            ]
        ]);
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['registrar_conflicto'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permiso para registrar conflictos.']);
            exit;
        }
        $res = normas_conflictos_crear($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'escalar':
        if (!isset($funciones_permitidas['escalar_conflicto'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permiso para escalar conflictos.']);
            exit;
        }
        $conflicto_id = intval($_POST['norma_conflicto_id'] ?? 0);
        $abogado_id = intval($_POST['abogado_id'] ?? 0);
        $res = normas_conflictos_escalar($conexion, $conflicto_id, $abogado_id, $usuario_id);
        echo json_encode($res);
        break;

    case 'resolver':
        if (!isset($funciones_permitidas['resolver_conflicto'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permiso para dictaminar o resolver conflictos.']);
            exit;
        }
        $conflicto_id = intval($_POST['norma_conflicto_id'] ?? 0);
        $decision = strval($_POST['decision'] ?? '');
        $res = normas_conflictos_resolver($conexion, $conflicto_id, $decision, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
