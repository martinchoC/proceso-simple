<?php
/**
 * Controlador AJAX para Procedimientos y Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/procedimientos_model.php';
require_once __DIR__ . '/normas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 315; // Procedimientos y Soporte

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 315
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
        $normas = normas_listar($conexion, ['solo_vigentes' => true]);
        $q_e = mysqli_query($conexion, "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
        $empresas = [];
        while ($r = mysqli_fetch_assoc($q_e)) $empresas[] = $r;

        echo json_encode([
            'success' => true,
            'data' => [
                'normas' => $normas,
                'empresas' => $empresas,
                'permite_guardar' => isset($funciones_permitidas['guardar_procedimiento']),
                'permite_aprobar' => isset($funciones_permitidas['aprobar_procedimiento'])
            ]
        ]);
        break;

    case 'listar':
        $estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = procedimientos_listar($conexion, $empresa_idx, $estado);

        $permite_guardar = isset($funciones_permitidas['guardar_procedimiento']);
        $permite_aprobar = isset($funciones_permitidas['aprobar_procedimiento']);

        foreach ($lista as &$p) {
            $est = intval($p['tabla_estado_registro_id']);
            $p['permite_editar'] = $permite_guardar && $est === 2053;
            $p['permite_aprobar'] = $permite_aprobar && $est === 2054;
            $p['permite_enviar'] = $permite_guardar && $est === 2053;
        }
        unset($p);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permisos' => [
                'permite_guardar' => $permite_guardar,
                'permite_aprobar' => $permite_aprobar
            ]
        ]);
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para guardar procedimientos.']);
            exit;
        }
        $res = procedimiento_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $procedimiento_id = intval($_POST['procedimiento_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        if ($nuevo_estado === 2055 && !isset($funciones_permitidas['aprobar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar procedimientos.']);
            exit;
        }

        $res = procedimiento_cambiar_estado($conexion, $procedimiento_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_versiones':
        $procedimiento_id = intval($_GET['procedimiento_id'] ?? 0);
        $versiones = procedimiento_versiones_listar($conexion, $procedimiento_id);
        echo json_encode(['success' => true, 'data' => $versiones]);
        break;

    case 'guardar_version':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para agregar versiones.']);
            exit;
        }
        $procedimiento_id = intval($_POST['procedimiento_id'] ?? 0);
        $res = procedimiento_version_crear($conexion, $procedimiento_id, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_instrucciones':
        $version_id = intval($_GET['procedimiento_version_id'] ?? 0);
        $instrucciones = procedimiento_instrucciones_listar($conexion, $version_id);
        echo json_encode(['success' => true, 'data' => $instrucciones]);
        break;

    case 'guardar_instruccion':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para agregar instrucciones.']);
            exit;
        }
        $res = procedimiento_instruccion_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_instruccion':
        if (!isset($funciones_permitidas['guardar_procedimiento'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar instrucciones.']);
            exit;
        }
        $ins_id = intval($_POST['instruccion_id'] ?? 0);
        $res = procedimiento_instruccion_eliminar($conexion, $ins_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
