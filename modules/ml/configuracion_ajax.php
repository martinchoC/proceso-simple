<?php
/**
 * Controlador AJAX para Configuración del Grupo y RACI (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/configuracion_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/configuracion_model.php';
require_once __DIR__ . '/grupos_regiones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 308; // Configuración

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 308
$funciones_permitidas = [];
if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
    $sql_f = "SELECT pagina_funcion_id, accion_js, nombre_funcion, icono_id, color_id, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id
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
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion, pf.icono_id, pf.color_id, pf.tabla_estado_registro_origen_id, pf.tabla_estado_registro_destino_id
                  FROM conf__paginas_funciones pf
                  INNER JOIN conf__empresas_perfiles_funciones epf 
                      ON epf.pagina_funcion_id = pf.pagina_funcion_id 
                      AND epf.empresa_perfil_id IN ($placeholders)
                  WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_id = 1 
                    AND epf.asignado = 1";
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
    case 'obtener_combos':
        $grupos = grupos_listar($conexion, 1);
        echo json_encode([
            'success' => true,
            'data' => [
                'grupos' => $grupos,
                'permite_modificar' => isset($funciones_permitidas['modificar_opcion'])
            ]
        ]);
        break;

    case 'listar_opciones':
        $grupo_id = intval($_GET['grupo_id'] ?? 1);
        $opciones = configuracion_grupo_listar($conexion, $grupo_id);
        echo json_encode(['success' => true, 'data' => $opciones]);
        break;

    case 'guardar_opcion':
        if (!isset($funciones_permitidas['modificar_opcion'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para modificar directrices corporativas.']);
            exit;
        }

        $grupo_id = intval($_POST['grupo_id'] ?? 1);
        $opcion_id = intval($_POST['opcion_id'] ?? 0);
        $nuevo_valor_id = intval($_POST['opcion_valor_id'] ?? 0);

        $res = configuracion_grupo_actualizar($conexion, $grupo_id, $opcion_id, $nuevo_valor_id, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_raci':
        $raci = raci_roles_listar($conexion);
        echo json_encode(['success' => true, 'data' => $raci]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
