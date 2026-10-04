<?php
/**
 * Controlador AJAX: Tablero de Cumplimiento (Página 355 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/tablero_cumplimiento_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tablero_cumplimiento_model.php';
require_once __DIR__ . '/ejecucion_seguimiento_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 355; // Tablero de Cumplimiento

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 355
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
        case 'indicadores_actuales':
            if (!in_array('consultar_tablero', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar el tablero.']);
                exit;
            }

            $actuales = tablero_indicadores_actuales($conexion, $empresa_idx);
            $criticidades = tablero_cumplimiento_por_criticidad($conexion, $empresa_idx);
            $entidades = tablero_cumplimiento_por_entidad($conexion, $empresa_idx);

            echo json_encode([
                'success' => true,
                'actuales' => $actuales,
                'criticidades' => $criticidades,
                'entidades' => $entidades,
                'funciones_permitidas' => $funciones_permitidas
            ]);
            break;

        case 'listar_cierres':
            if (!in_array('consultar_tablero', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar cierres.']);
                exit;
            }

            $cierres = tablero_cierres_historicos($conexion, $empresa_idx);
            echo json_encode(['success' => true, 'data' => $cierres]);
            break;

        case 'calcular_cierre':
            if (!in_array('calcular_cierre_periodo', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para calcular cierres de período.']);
                exit;
            }

            $periodo_desde = trim($_POST['periodo_desde'] ?? '');
            $periodo_hasta = trim($_POST['periodo_hasta'] ?? '');

            if (empty($periodo_desde) || empty($periodo_hasta)) {
                echo json_encode(['success' => false, 'message' => 'Debe indicar fecha desde y fecha hasta del período a consolidar.']);
                exit;
            }

            if ($periodo_desde > $periodo_hasta) {
                echo json_encode(['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin.']);
                exit;
            }

            $res = indicadores_calcular_cierre($conexion, $empresa_idx, $periodo_desde, $periodo_hasta, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
