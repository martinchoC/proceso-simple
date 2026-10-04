<?php
/**
 * Controlador AJAX para Excepciones de Cumplimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/excepciones_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/excepciones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 333; // Excepciones de Cumplimiento

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 333
$funciones_permitidas = [];
$es_abogado = false;

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
    $es_abogado = true;
} else {
    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_idx);
    if (!empty($perfiles)) {
        $perfil_ids = array_column($perfiles, 'empresa_perfil_id');
        // Chequear si tiene perfil 303 (Legal) o 301 (Admin)
        foreach ($perfil_ids as $pid) {
            if (in_array($pid, [301, 303, 306, 308], true)) {
                $es_abogado = true;
                break;
            }
        }

        $placeholders = implode(',', array_fill(0, count($perfil_ids), '?'));
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion
                  FROM conf__empresas_perfiles_funciones epf
                  INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
                  WHERE epf.empresa_perfil_id IN ($placeholders)
                    AND epf.asignado = 1
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
            'detalles' => $funciones_permitidas,
            'es_abogado' => $es_abogado
        ]);
        break;

    case 'combos':
        $combos = excepciones_combos($conexion, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $combos]);
        break;

    case 'listar':
        $filtros = ['empresa_id' => $empresa_idx];
        if (!empty($_GET['obligacion_asignada_id'])) {
            $filtros['obligacion_asignada_id'] = intval($_GET['obligacion_asignada_id']);
        }
        if (!empty($_GET['filtro_estado'])) {
            $filtros['tabla_estado_registro_id'] = intval($_GET['filtro_estado']);
        }
        $filas = excepciones_listar($conexion, $filtros);
        echo json_encode(['success' => true, 'data' => $filas]);
        break;

    case 'obtener':
        $id = intval($_GET['excepcion_id'] ?? 0);
        $item = excepcion_obtener($conexion, $id);
        if ($item) {
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Excepción no encontrada.']);
        }
        break;

    case 'solicitar':
        if (!isset($funciones_permitidas['solicitar_excepcion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para solicitar excepciones.']);
            exit;
        }
        $res = excepcion_solicitar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'escalar_legal':
        if (!isset($funciones_permitidas['enviar_aprobacion_legal_excepcion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para escalar al área legal.']);
            exit;
        }
        $id = intval($_POST['excepcion_id'] ?? 0);
        $res = excepcion_escalar_legal($conexion, $id, $usuario_id);
        echo json_encode($res);
        break;

    case 'aprobar':
        if (!isset($funciones_permitidas['aprobar_excepcion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar excepciones.']);
            exit;
        }
        $id = intval($_POST['excepcion_id'] ?? 0);
        $res = excepcion_aprobar($conexion, $id, $usuario_id, $es_abogado);
        echo json_encode($res);
        break;

    case 'rechazar':
        if (!isset($funciones_permitidas['rechazar_excepcion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para rechazar excepciones.']);
            exit;
        }
        $id = intval($_POST['excepcion_id'] ?? 0);
        $motivo = trim($_POST['motivo_rechazo'] ?? '');
        $res = excepcion_rechazar($conexion, $id, $usuario_id, $motivo);
        echo json_encode($res);
        break;

    case 'revocar':
        if (!isset($funciones_permitidas['revocar_excepcion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para revocar excepciones.']);
            exit;
        }
        $id = intval($_POST['excepcion_id'] ?? 0);
        $res = excepcion_revocar($conexion, $id, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción desconocida: {$accion}"]);
        break;
}
