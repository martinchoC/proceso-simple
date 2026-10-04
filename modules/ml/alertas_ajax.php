<?php
/**
 * Controlador AJAX: Consola de Alertas (Página 354 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/alertas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/alertas_model.php';
require_once __DIR__ . '/ejecucion_seguimiento_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 354; // Consola de Alertas

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 354
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
            // Usuarios disponibles en la empresa
            $sql_u = "SELECT DISTINCT u.usuario_id, u.usuario_nombre 
                      FROM conf__usuarios u
                      INNER JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id
                      INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                      WHERE (ep.empresa_id = ? OR ? = 0)
                        AND u.tabla_estado_registro_id = 1
                        AND up.tabla_estado_registro_id = 1
                      ORDER BY u.usuario_nombre ASC";
            $stmt_u = mysqli_prepare($conexion, $sql_u);
            mysqli_stmt_bind_param($stmt_u, "ii", $empresa_idx, $empresa_idx);
            mysqli_stmt_execute($stmt_u);
            $res_u = mysqli_stmt_get_result($stmt_u);
            $usuarios = [];
            while ($row = mysqli_fetch_assoc($res_u)) {
                $usuarios[] = $row;
            }

            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios,
                'funciones_permitidas' => $funciones_permitidas
            ]);
            break;

        case 'kpis':
            $kpis = alertas_kpis_resumen($conexion, $empresa_idx);
            echo json_encode(['success' => true, 'kpis' => $kpis]);
            break;

        case 'listar_alertas':
            if (!in_array('consultar_alertas', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar alertas.']);
                exit;
            }

            $filtros = [
                'canal' => isset($_GET['filtro_canal']) && $_GET['filtro_canal'] !== '' ? $_GET['filtro_canal'] : null,
                'estado_id' => isset($_GET['filtro_estado_id']) && $_GET['filtro_estado_id'] !== '' ? (int)$_GET['filtro_estado_id'] : null,
                'usuario_id' => isset($_GET['filtro_usuario_id']) && $_GET['filtro_usuario_id'] !== '' ? (int)$_GET['filtro_usuario_id'] : null,
            ];

            $lista = alertas_listar($conexion, $empresa_idx, $filtros);
            echo json_encode(['success' => true, 'data' => $lista]);
            break;

        case 'disparar_alertas':
            if (!in_array('disparar_alertas', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para disparar alertas preventivas.']);
                exit;
            }

            $res_gen = alertas_generar_preventivas($conexion, $empresa_idx);
            echo json_encode($res_gen);
            break;

        case 'despachar_cola':
            if (!in_array('disparar_alertas', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para despachar alertas.']);
                exit;
            }

            $res_desp = alertas_procesar_cola($conexion, $empresa_idx);
            echo json_encode($res_desp);
            break;

        case 'reenviar_alerta':
            if (!in_array('reenviar_alerta', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para reenviar alertas.']);
                exit;
            }

            $alerta_id = isset($_POST['alerta_id']) ? (int)$_POST['alerta_id'] : 0;
            if ($alerta_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de alerta inválido.']);
                exit;
            }

            $res = alertas_reenviar($conexion, $alerta_id, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
