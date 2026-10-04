<?php
/**
 * Controlador AJAX: Bandeja de Evidencias (Página 352 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/evidencias_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/evidencias_model.php';
require_once __DIR__ . '/ejecucion_seguimiento_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 352; // Bandeja de Evidencias

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 352
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

            // Tareas abiertas que requieren evidencia o activas
            $sql_t = "SELECT ct.ciclo_tarea_id, ct.titulo, c.ciclo_id, o.codigo as obligacion_codigo
                      FROM ml__ciclos_tareas ct
                      INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
                      INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
                      INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                      WHERE ct.empresa_id = {$empresa_idx} 
                        AND ct.tabla_estado_registro_id IN (2078, 2079, 2080)
                      ORDER BY ct.ciclo_tarea_id DESC LIMIT 50";
            $res_t = mysqli_query($conexion, $sql_t);
            $tareas = [];
            while ($r = mysqli_fetch_assoc($res_t)) { $tareas[] = $r; }

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'tareas' => $tareas,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'listar':
            $filtros = [
                'entidad_id' => $_GET['filtro_entidad'] ?? null,
                'ciclo_id' => $_GET['filtro_ciclo'] ?? null,
                'es_comprobante' => !empty($_GET['filtro_comprobante']) ? 1 : null,
                'estado_id' => $_GET['filtro_estado'] ?? null,
            ];

            $evidencias = evidencias_listar($conexion, $empresa_idx, $filtros);
            echo json_encode([
                'success' => true,
                'data' => $evidencias,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'obtener':
            $evidencia_id = intval($_GET['evidencia_id'] ?? 0);
            $evi = evidencias_obtener_por_id($conexion, $evidencia_id, $empresa_idx);
            if (!$evi) {
                echo json_encode(['success' => false, 'message' => 'Evidencia no encontrada']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'evidencia' => $evi
            ]);
            break;

        case 'subir':
            $ciclo_tarea_id = intval($_POST['ciclo_tarea_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $es_comprobante = !empty($_POST['es_comprobante']) ? 1 : 0;
            $valida_hasta = !empty($_POST['valida_hasta']) ? trim($_POST['valida_hasta']) : null;
            $retener_hasta = !empty($_POST['retener_hasta']) ? trim($_POST['retener_hasta']) : null;
            $confidencial = !empty($_POST['confidencial']) ? 1 : 0;

            if ($ciclo_tarea_id <= 0 || empty($titulo)) {
                echo json_encode(['success' => false, 'message' => 'Debe indicar la tarea y el título de la evidencia.']);
                exit;
            }

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar un archivo válido para adjuntar.']);
                exit;
            }

            $nombre_original = $_FILES['archivo']['name'];
            $ruta_temp = $_FILES['archivo']['tmp_name'];

            $res_reg = evidencias_registrar(
                $conexion, 
                $ciclo_tarea_id, 
                $titulo, 
                $descripcion, 
                $nombre_original, 
                $ruta_temp, 
                $es_comprobante, 
                $valida_hasta, 
                $retener_hasta, 
                $confidencial, 
                $empresa_idx, 
                $usuario_id
            );

            echo json_encode($res_reg);
            break;

        case 'anular':
            $evidencia_id = intval($_POST['evidencia_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');

            if ($evidencia_id <= 0 || empty($motivo)) {
                echo json_encode(['success' => false, 'message' => 'Debe especificar el motivo de anulación.']);
                exit;
            }

            $res_an = evidencias_anular($conexion, $evidencia_id, $motivo, $empresa_idx, $usuario_id);
            echo json_encode($res_an);
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
