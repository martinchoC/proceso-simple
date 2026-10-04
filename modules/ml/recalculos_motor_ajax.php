<?php
/**
 * Controlador AJAX para la Consola del Motor de Herencia y Recálculos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/recalculos_motor_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/recalculos_motor_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 334; // Motor de Herencia y Recálculos

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 334
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
            'detalles' => $funciones_permitidas
        ]);
        break;

    case 'estadisticas':
        $stats = motor_obtener_estadisticas($conexion, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    case 'combos':
        $combos = recalculos_motor_combos($conexion, $empresa_idx);
        echo json_encode(['success' => true, 'data' => $combos]);
        break;

    case 'listar_cola':
        $filtros = [];
        if (!empty($_GET['filtro_estado'])) {
            $filtros['tabla_estado_registro_id'] = intval($_GET['filtro_estado']);
        }
        if (!empty($_GET['filtro_causa'])) {
            $filtros['causa'] = trim($_GET['filtro_causa']);
        }
        $filas = recalculos_pendientes_listar($conexion, $filtros);
        echo json_encode(['success' => true, 'data' => $filas]);
        break;

    case 'ejecutar':
        if (!isset($funciones_permitidas['ejecutar_recalculo_motor'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para ejecutar el motor de cálculo.']);
            exit;
        }
        $entidad_id = !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : 0;
        if ($entidad_id > 0) {
            $res = motor_recalcular_entidad($conexion, $entidad_id, $usuario_id);
        } else {
            $res = motor_recalcular_empresa($conexion, $empresa_idx, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'simular':
        if (!isset($funciones_permitidas['simular_impacto_motor'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar simulaciones de impacto.']);
            exit;
        }
        $res = motor_simular_impacto($conexion, $empresa_idx);
        echo json_encode($res);
        break;

    case 'procesar_cola':
        if (!isset($funciones_permitidas['procesar_cola_recalculos'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para procesar la cola de recálculos.']);
            exit;
        }
        $res = motor_procesar_cola($conexion, 20);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción desconocida: {$accion}"]);
        break;
}
