<?php
/**
 * Controlador AJAX para Delegaciones y Suplencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/delegaciones_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/delegaciones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 310; // Delegaciones y Suplencias

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 310
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
    case 'obtener_combos':
        $combos = delegaciones_combos($conexion);
        echo json_encode([
            'success' => true,
            'data' => [
                'usuarios' => $combos['usuarios'],
                'perfiles' => $combos['perfiles'],
                'permite_agregar' => isset($funciones_permitidas['agregar_delegacion'])
            ]
        ]);
        break;

    case 'listar':
        $titular_id = intval($_GET['filtro_titular'] ?? 0);
        $suplente_id = intval($_GET['filtro_suplente'] ?? 0);
        $estado = trim($_GET['filtro_estado'] ?? '');

        $lista = delegaciones_listar($conexion, $titular_id, $suplente_id, $estado);

        // Inyectar banderas de permisos
        $permite_cancelar = isset($funciones_permitidas['cancelar_delegacion']);
        foreach ($lista as &$item) {
            $item['permite_cancelar'] = $permite_cancelar && in_array($item['estado_vigencia'], ['ACTIVA', 'FUTURA'], true);
            $item['permite_eliminar'] = $permite_cancelar;
        }
        unset($item);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permisos' => [
                'permite_agregar' => isset($funciones_permitidas['agregar_delegacion']),
                'permite_cancelar' => $permite_cancelar
            ]
        ]);
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['agregar_delegacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para registrar delegaciones.']);
            exit;
        }

        $res = delegaciones_crear($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'cancelar':
        if (!isset($funciones_permitidas['cancelar_delegacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para dar de baja delegaciones.']);
            exit;
        }

        $delegacion_id = intval($_POST['delegacion_id'] ?? 0);
        $res = delegaciones_cancelar($conexion, $delegacion_id, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar':
        if (!isset($funciones_permitidas['cancelar_delegacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar delegaciones.']);
            exit;
        }

        $delegacion_id = intval($_POST['delegacion_id'] ?? 0);
        $res = delegaciones_eliminar($conexion, $delegacion_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
