<?php
/**
 * Controlador AJAX para Accesos Confidenciales y Auditoría (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/accesos_confidenciales_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/accesos_confidenciales_model.php';
require_once __DIR__ . '/normas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 316; // Accesos Confidenciales

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 316
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
        $q_p = mysqli_query($conexion, "SELECT empresa_perfil_id, empresa_perfil_nombre FROM conf__empresas_perfiles WHERE modulo_id = 3 AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_nombre ASC");
        $perfiles = [];
        while ($r = mysqli_fetch_assoc($q_p)) $perfiles[] = $r;

        $q_n = mysqli_query($conexion, "SELECT norma_id, codigo, titulo FROM ml__normas WHERE confidencial = 1 ORDER BY codigo ASC");
        $normas = [];
        while ($r = mysqli_fetch_assoc($q_n)) $normas[] = $r;

        $q_pr = mysqli_query($conexion, "SELECT procedimiento_id, codigo, nombre FROM ml__procedimientos WHERE confidencial = 1 ORDER BY codigo ASC");
        $procedimientos = [];
        while ($r = mysqli_fetch_assoc($q_pr)) $procedimientos[] = $r;

        $ambitos = normas_combos($conexion);

        echo json_encode([
            'success' => true,
            'data' => [
                'perfiles' => $perfiles,
                'normas_confidenciales' => $normas,
                'procedimientos_confidenciales' => $procedimientos,
                'ambitos_tipos' => $ambitos['ambitos_tipos'],
                'grupos' => $ambitos['grupos'],
                'regiones' => $ambitos['regiones'],
                'empresas' => $ambitos['empresas'],
                'territorios' => $ambitos['territorios'],
                'permite_autorizar' => isset($funciones_permitidas['autorizar_acceso'])
            ]
        ]);
        break;

    case 'listar_accesos':
        $lista = accesos_confidenciales_listar($conexion);
        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permite_revocar' => isset($funciones_permitidas['autorizar_acceso'])
        ]);
        break;

    case 'guardar_acceso':
        if (!isset($funciones_permitidas['autorizar_acceso'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para autorizar accesos confidenciales.']);
            exit;
        }
        $res = acceso_confidencial_crear($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'revocar_acceso':
        if (!isset($funciones_permitidas['autorizar_acceso'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para revocar autorizaciones.']);
            exit;
        }
        $acceso_id = intval($_POST['acceso_confidencial_id'] ?? 0);
        $res = acceso_confidencial_revocar($conexion, $acceso_id);
        echo json_encode($res);
        break;

    case 'listar_auditoria':
        $lista = auditoria_accesos_listar($conexion, 100);
        echo json_encode(['success' => true, 'data' => $lista]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
