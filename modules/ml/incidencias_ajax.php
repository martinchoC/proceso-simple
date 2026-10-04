<?php
/**
 * Controlador AJAX: Hallazgos e Incidencias (Página 353 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/incidencias_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/incidencias_model.php';
require_once __DIR__ . '/ejecucion_seguimiento_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 353; // Hallazgos e Incidencias

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 353
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
            // Entidades asignadas a la empresa
            $sql_ent = "SELECT e.entidad_id, e.nombre 
                        FROM ml__entidades e
                        INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id
                        WHERE ee.empresa_id = ? AND e.tabla_estado_registro_id = 1
                        ORDER BY e.nombre ASC";
            $stmt_ent = mysqli_prepare($conexion, $sql_ent);
            mysqli_stmt_bind_param($stmt_ent, "i", $empresa_idx);
            mysqli_stmt_execute($stmt_ent);
            $res_ent = mysqli_stmt_get_result($stmt_ent);
            $entidades = [];
            while ($row = mysqli_fetch_assoc($res_ent)) {
                $entidades[] = $row;
            }

            // Ciclos operativos de la empresa para selección
            $sql_cic = "SELECT c.ciclo_id, o.codigo as obligacion_codigo, o.descripcion as obligacion_titulo,
                               e.nombre as entidad_nombre, DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y') as vencimiento_fmt
                        FROM ml__ciclos c
                        INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
                        INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                        INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
                        WHERE c.empresa_id = ?
                        ORDER BY c.ciclo_id DESC
                        LIMIT 100";
            $stmt_cic = mysqli_prepare($conexion, $sql_cic);
            mysqli_stmt_bind_param($stmt_cic, "i", $empresa_idx);
            mysqli_stmt_execute($stmt_cic);
            $res_cic = mysqli_stmt_get_result($stmt_cic);
            $ciclos = [];
            while ($row = mysqli_fetch_assoc($res_cic)) {
                $ciclos[] = $row;
            }

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'ciclos' => $ciclos,
                'funciones_permitidas' => $funciones_permitidas
            ]);
            break;

        case 'listar_incidencias':
            if (!in_array('consultar_incidencias', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar incidencias.']);
                exit;
            }

            $filtros = [
                'entidad_id' => isset($_GET['filtro_entidad_id']) ? (int)$_GET['filtro_entidad_id'] : null,
                'ciclo_id' => isset($_GET['filtro_ciclo_id']) ? (int)$_GET['filtro_ciclo_id'] : null,
                'estado_id' => isset($_GET['filtro_estado_id']) ? (int)$_GET['filtro_estado_id'] : null,
            ];

            $lista = incidencias_listar($conexion, $empresa_idx, $filtros);
            echo json_encode(['success' => true, 'data' => $lista]);
            break;

        case 'obtener_incidencia':
            $incidencia_id = isset($_GET['incidencia_id']) ? (int)$_GET['incidencia_id'] : 0;
            $inc = incidencias_obtener_por_id($conexion, $incidencia_id, $empresa_idx);
            if (!$inc) {
                echo json_encode(['success' => false, 'message' => 'Incidencia no encontrada.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $inc]);
            break;

        case 'registrar_incidencia':
            if (!in_array('registrar_incidencia', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para registrar incidencias.']);
                exit;
            }

            $ciclo_id = isset($_POST['ciclo_id']) ? (int)$_POST['ciclo_id'] : 0;
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($ciclo_id <= 0 || empty($descripcion)) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar un ciclo y detallar la desviación / hallazgo.']);
                exit;
            }

            $res = incidencias_registrar($conexion, $ciclo_id, $descripcion, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'cerrar_incidencia':
            if (!in_array('cerrar_incidencia', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para cerrar incidencias.']);
                exit;
            }

            $incidencia_id = isset($_POST['incidencia_id']) ? (int)$_POST['incidencia_id'] : 0;
            $motivo = trim($_POST['motivo'] ?? '');

            if ($incidencia_id <= 0 || empty($motivo)) {
                echo json_encode(['success' => false, 'message' => 'Debe indicar el motivo de subsanación o resolución del cierre.']);
                exit;
            }

            $res = incidencias_cerrar($conexion, $incidencia_id, $motivo, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'vincular_seguimiento':
            if (!in_array('vincular_seguimiento', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para vincular planes de acción / seguimiento.']);
                exit;
            }

            $incidencia_id = isset($_POST['incidencia_id']) ? (int)$_POST['incidencia_id'] : 0;
            $plan_accion = trim($_POST['plan_accion'] ?? '');

            if ($incidencia_id <= 0 || empty($plan_accion)) {
                echo json_encode(['success' => false, 'message' => 'Debe detallar el plan de acción correctivo a programar en el ciclo de seguimiento.']);
                exit;
            }

            $res = incidencias_vincular_seguimiento($conexion, $incidencia_id, $plan_accion, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
