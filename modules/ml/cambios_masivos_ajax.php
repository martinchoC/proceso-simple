<?php
/**
 * Controlador AJAX: Cambios Masivos e Impacto (Página 363 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cambios_masivos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/cambios_masivos_model.php';
require_once __DIR__ . '/cargas_masivas_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 363; // Cambios Masivos e Impacto

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 363
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
            // Versiones de normas vigentes
            $sql_nv = "SELECT nv.norma_version_id, nv.version as numero_version, n.codigo as norma_codigo, n.titulo as norma_titulo
                       FROM ml__normas_versiones nv
                       INNER JOIN ml__normas n ON n.norma_id = nv.norma_id
                       WHERE n.empresa_id = ? AND nv.tabla_estado_registro_id = 1
                       ORDER BY n.codigo ASC, nv.norma_version_id DESC";
            $stmt_nv = mysqli_prepare($conexion, $sql_nv);
            mysqli_stmt_bind_param($stmt_nv, "i", $empresa_idx);
            mysqli_stmt_execute($stmt_nv);
            $res_nv = mysqli_stmt_get_result($stmt_nv);
            $normas = [];
            while ($row = mysqli_fetch_assoc($res_nv)) {
                $normas[] = $row;
            }

            echo json_encode([
                'success' => true,
                'normas' => $normas,
                'funciones_permitidas' => $funciones_permitidas
            ]);
            break;

        case 'listar_cambios':
            if (!in_array('consultar_cambios_masivos', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar cambios masivos.']);
                exit;
            }

            $filtros = [
                'estado_id' => isset($_GET['filtro_estado_id']) && $_GET['filtro_estado_id'] !== '' ? (int)$_GET['filtro_estado_id'] : null,
                'norma_id' => isset($_GET['filtro_norma_id']) && $_GET['filtro_norma_id'] !== '' ? (int)$_GET['filtro_norma_id'] : null,
            ];

            $lista = cambios_masivos_listar($conexion, $empresa_idx, $filtros);
            echo json_encode(['success' => true, 'data' => $lista]);
            break;

        case 'obtener_detalles':
            $cambio_masivo_id = isset($_GET['cambio_masivo_id']) ? (int)$_GET['cambio_masivo_id'] : 0;
            $detalles = cambios_masivos_obtener_detalles($conexion, $cambio_masivo_id);
            echo json_encode(['success' => true, 'detalles' => $detalles]);
            break;

        case 'simular_impacto':
            if (!in_array('simular_impacto', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para simular impacto de cambios masivos.']);
                exit;
            }

            $norma_version_id = isset($_POST['norma_version_id']) ? (int)$_POST['norma_version_id'] : 0;
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($norma_version_id <= 0 || empty($descripcion)) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar una norma y detallar el motivo del cambio normativo.']);
                exit;
            }

            $res = cambios_masivos_simular_impacto($conexion, $norma_version_id, $descripcion, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'aprobar_cambio':
            if (!in_array('aprobar_cambio', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para aprobar cambios masivos.']);
                exit;
            }

            $cambio_masivo_id = isset($_POST['cambio_masivo_id']) ? (int)$_POST['cambio_masivo_id'] : 0;
            if ($cambio_masivo_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de cambio masivo inválido.']);
                exit;
            }

            $res = cambios_masivos_aprobar($conexion, $cambio_masivo_id, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'aplicar_cambio':
            if (!in_array('aplicar_cambio', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para aplicar cambios masivos.']);
                exit;
            }

            $cambio_masivo_id = isset($_POST['cambio_masivo_id']) ? (int)$_POST['cambio_masivo_id'] : 0;
            if ($cambio_masivo_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de cambio masivo inválido.']);
                exit;
            }

            $res = cambios_masivos_aplicar($conexion, $cambio_masivo_id, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
