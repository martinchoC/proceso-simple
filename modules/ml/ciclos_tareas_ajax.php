<?php
/**
 * Controlador AJAX: Tareas Operativas (Página 343 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_tareas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/ciclos_tareas_model.php';
require_once __DIR__ . '/ciclos_generador_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 343; // Tareas Operativas

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 343
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
        $funciones_permitidas[] = $rf['accion_js'];
    }
} else {
    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_idx);
    if (!empty($perfiles)) {
        $perfil_ids = array_column($perfiles, 'empresa_perfil_id');
        $placeholders = implode(',', array_fill(0, count($perfil_ids), '?'));
        $sql_f = "SELECT DISTINCT pf.accion_js 
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
            $funciones_permitidas[] = $rf['accion_js'];
        }
        mysqli_stmt_close($stmt_f);
    }
}

$accion = $_REQUEST['accion'] ?? '';

try {
    switch ($accion) {
        case 'combos':
            // Entidades
            $res_e = mysqli_query($conexion, "SELECT DISTINCT e.entidad_id, e.codigo, e.nombre 
                                               FROM ml__entidades e 
                                               LEFT JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id 
                                               WHERE (ee.empresa_id = {$empresa_idx} OR ee.empresa_id IS NULL) AND e.tabla_estado_registro_id = 1 
                                               ORDER BY e.nombre ASC");
            $entidades = [];
            while ($r = mysqli_fetch_assoc($res_e)) { $entidades[] = $r; }

            // Estados Tareas (2077 a 2083)
            $res_est = mysqli_query($conexion, "SELECT tabla_estado_registro_id, tabla_estado_registro as descripcion 
                                                FROM conf__tablas_estados_registros 
                                                WHERE tabla_estado_registro_id BETWEEN 2077 AND 2083 
                                                ORDER BY tabla_estado_registro_id ASC");
            $estados = [];
            while ($r = mysqli_fetch_assoc($res_est)) { $estados[] = $r; }

            // Usuarios de la empresa
            $res_u = mysqli_query($conexion, "SELECT DISTINCT u.usuario_id, u.usuario_nombre as nombre_completo 
                                              FROM conf__usuarios u
                                              INNER JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id
                                              INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                                              WHERE (ep.empresa_id = {$empresa_idx} OR {$empresa_idx} = 0)
                                                AND u.tabla_estado_registro_id = 1
                                                AND up.tabla_estado_registro_id = 1
                                              ORDER BY u.usuario_nombre ASC");
            $usuarios = [];
            while ($r = mysqli_fetch_assoc($res_u)) { $usuarios[] = $r; }

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'estados' => $estados,
                'usuarios' => $usuarios,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'listar':
            $filtros = [
                'ciclo_id' => $_GET['filtro_ciclo'] ?? null,
                'entidad_id' => $_GET['filtro_entidad'] ?? null,
                'estado_id' => $_GET['filtro_estado'] ?? null,
                'usuario_id' => $_GET['filtro_usuario'] ?? null,
                'fecha_desde' => $_GET['filtro_desde'] ?? null,
                'fecha_hasta' => $_GET['filtro_hasta'] ?? null,
            ];

            $tareas = ciclos_tareas_listar($conexion, $empresa_idx, $filtros);
            echo json_encode([
                'success' => true,
                'data' => $tareas,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'obtener':
            $ciclo_tarea_id = intval($_GET['ciclo_tarea_id'] ?? 0);
            $tarea = ciclos_tareas_obtener_por_id($conexion, $ciclo_tarea_id, $empresa_idx);
            if (!$tarea) {
                echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'tarea' => $tarea
            ]);
            break;

        case 'cambiar_estado':
            $ciclo_tarea_id = intval($_POST['ciclo_tarea_id'] ?? 0);
            $nuevo_estado_id = intval($_POST['nuevo_estado_id'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($ciclo_tarea_id <= 0 || $nuevo_estado_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Parámetros inválidos para cambiar estado.']);
                exit;
            }

            $res = ciclos_tareas_ejecutar_transicion($conexion, $ciclo_tarea_id, $nuevo_estado_id, $empresa_idx, $usuario_id, $observaciones);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error'],
                'nuevo_estado_id' => $res['nuevo_estado_id'] ?? null
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => "Acción no reconocida: '{$accion}'"]);
            break;
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción en el servidor: ' . $e->getMessage()
    ]);
}
