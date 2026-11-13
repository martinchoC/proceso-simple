<?php
/**
 * Controlador AJAX para Plantillas de Tareas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tareas_plantillas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tareas_plantillas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 324; // Plantillas de Tareas

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 324
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
                  FROM conf__perfiles_funciones ppf
                  INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = ppf.pagina_funcion_id
                  WHERE ppf.perfil_id IN ($placeholders)
                    AND ppf.asignado = 1
                    AND pf.pagina_id = ?
                    AND pf.tabla_estado_registro_id = 1";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        $types = str_repeat('i', count($perfil_ids)) . 'i';
        $params = array_merge($perfil_ids, [$pagina_idx]);
        mysqli_stmt_bind_param($stmt_f, $types, ...$params);
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
            'funciones' => array_keys($funciones_permitidas),
            'detalles' => $funciones_permitidas
        ]);
        break;

    case 'combos':
        $datos = tareas_plantillas_combos($conexion, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $datos]);
        break;

    case 'listar':
        $filtros = [];
        if (!empty($_GET['obligacion_id'])) {
            $filtros['obligacion_id'] = intval($_GET['obligacion_id']);
        }
        if (!empty($_GET['norma_id'])) {
            $filtros['norma_id'] = intval($_GET['norma_id']);
        }
        if (isset($_GET['filtro_estado']) && $_GET['filtro_estado'] !== '') {
            $filtros['tabla_estado_registro_id'] = intval($_GET['filtro_estado']);
        }
        $filas = tareas_plantillas_listar($conexion, $filtros);
        echo json_encode(['success' => true, 'data' => $filas]);
        break;

    case 'obtener':
        $id = intval($_GET['tarea_plantilla_id'] ?? 0);
        $item = tarea_plantilla_obtener($conexion, $id);
        if ($item) {
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Plantilla de tarea no encontrada.']);
        }
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['guardar_tarea_plantilla'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para guardar o modificar plantillas de tareas.']);
            exit;
        }
        $res = tarea_plantilla_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar':
        if (!isset($funciones_permitidas['guardar_tarea_plantilla'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar plantillas de tareas.']);
            exit;
        }
        $id = intval($_POST['tarea_plantilla_id'] ?? 0);
        $res = tarea_plantilla_eliminar($conexion, $id);
        echo json_encode($res);
        break;

    case 'listar_dependencias':
        $tarea_id = intval($_GET['tarea_plantilla_id'] ?? 0);
        $obligacion_id = intval($_GET['obligacion_id'] ?? 0);
        if ($tarea_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de tarea inválido.']);
            exit;
        }
        $deps = tarea_plantilla_dependencias_listar($conexion, $tarea_id);
        $candidatas = [];
        if ($obligacion_id > 0) {
            $candidatas = tarea_plantilla_candidatas_predecesoras($conexion, $tarea_id, $obligacion_id);
        }
        echo json_encode([
            'success' => true,
            'dependencias' => $deps,
            'candidatas' => $candidatas
        ]);
        break;

    case 'guardar_dependencia':
        if (!isset($funciones_permitidas['vincular_dependencia_tarea'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para vincular dependencias entre tareas.']);
            exit;
        }
        $res = tarea_plantilla_dependencia_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_dependencia':
        if (!isset($funciones_permitidas['vincular_dependencia_tarea'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar dependencias entre tareas.']);
            exit;
        }
        $dep_id = intval($_POST['tarea_plantilla_dependencia_id'] ?? 0);
        $res = tarea_plantilla_dependencia_eliminar($conexion, $dep_id);
        echo json_encode($res);
        break;

    case 'listar_raci':
        $tarea_id = intval($_GET['tarea_plantilla_id'] ?? 0);
        if ($tarea_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de tarea inválido.']);
            exit;
        }
        $roles = tarea_plantilla_raci_listar($conexion, $tarea_id);
        echo json_encode([
            'success' => true,
            'raci' => $roles
        ]);
        break;

    case 'guardar_raci':
        $res = tarea_plantilla_raci_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_raci':
        $raci_id = intval($_POST['tarea_plantilla_raci_id'] ?? 0);
        $res = tarea_plantilla_raci_eliminar($conexion, $raci_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción desconocida o no implementada: {$accion}"]);
        break;
}
