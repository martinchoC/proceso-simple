<?php
/**
 * Controlador AJAX: Calendario y Ciclos (Página 342 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/ciclos_model.php';
require_once __DIR__ . '/ciclos_generador_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 342; // Calendario y Ciclos

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 342
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

            // Obligaciones
            $res_o = mysqli_query($conexion, "SELECT DISTINCT o.obligacion_id, o.codigo, o.descripcion as titulo 
                                               FROM ml__obligaciones_asignadas oa
                                               INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                                               WHERE oa.empresa_id = {$empresa_idx}
                                               ORDER BY o.descripcion ASC");
            $obligaciones = [];
            while ($r = mysqli_fetch_assoc($res_o)) { $obligaciones[] = $r; }

            // Estados de ciclo (2069 a 2076)
            $res_est = mysqli_query($conexion, "SELECT tabla_estado_registro_id, tabla_estado_registro as descripcion 
                                                FROM conf__tablas_estados_registros 
                                                WHERE tabla_estado_registro_id BETWEEN 2069 AND 2076 
                                                ORDER BY tabla_estado_registro_id ASC");
            $estados = [];
            while ($r = mysqli_fetch_assoc($res_est)) { $estados[] = $r; }

            // Tipos de vínculos
            $res_v = mysqli_query($conexion, "SELECT ciclo_vinculo_tipo_id, codigo, nombre FROM ml__ciclos_vinculos_tipos WHERE activo = 1 ORDER BY ciclo_vinculo_tipo_id ASC");
            $vinculos = [];
            while ($r = mysqli_fetch_assoc($res_v)) { $vinculos[] = $r; }

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'obligaciones' => $obligaciones,
                'estados' => $estados,
                'vinculos' => $vinculos,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'listar':
            $filtros = [
                'entidad_id' => $_GET['filtro_entidad'] ?? null,
                'obligacion_id' => $_GET['filtro_obligacion'] ?? null,
                'estado_id' => $_GET['filtro_estado'] ?? null,
                'fecha_desde' => $_GET['filtro_desde'] ?? null,
                'fecha_hasta' => $_GET['filtro_hasta'] ?? null,
                'vinculo_tipo_id' => $_GET['filtro_vinculo'] ?? null,
            ];

            $ciclos = ciclos_listar($conexion, $empresa_idx, $filtros);
            echo json_encode([
                'success' => true,
                'data' => $ciclos,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'obtener':
            $ciclo_id = intval($_GET['ciclo_id'] ?? 0);
            $ciclo = ciclos_obtener_por_id($conexion, $ciclo_id, $empresa_idx);
            if (!$ciclo) {
                echo json_encode(['success' => false, 'message' => 'Ciclo no encontrado']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'ciclo' => $ciclo
            ]);
            break;

        case 'cerrar':
            $ciclo_id = intval($_POST['ciclo_id'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');
            $res = ciclos_cerrar($conexion, $ciclo_id, $empresa_idx, $usuario_id, $observaciones);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error']
            ]);
            break;

        case 'eximir':
            $ciclo_id = intval($_POST['ciclo_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $res = ciclos_eximir($conexion, $ciclo_id, $empresa_idx, $usuario_id, $motivo);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error']
            ]);
            break;

        case 'cancelar':
            $ciclo_id = intval($_POST['ciclo_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $res = ciclos_cancelar($conexion, $ciclo_id, $empresa_idx, $usuario_id, $motivo);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error']
            ]);
            break;

        case 'crear_complementario':
            // Caso C28: Crear ciclo complementario sin alterar el ciclo de origen
            $ciclo_origen_id = intval($_POST['ciclo_origen_id'] ?? 0);
        case 'crear_complementario':
            // Caso C28: Crear ciclo complementario sin alterar el ciclo de origen
            $ciclo_origen_id = intval($_POST['ciclo_origen_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');

            if ($ciclo_origen_id <= 0 || empty($motivo)) {
                echo json_encode(['success' => false, 'message' => 'Parámetros incompletos para crear ciclo complementario.']);
                exit;
            }

            $res_comp = ciclos_crear_complementario($conexion, $ciclo_origen_id, $motivo, $usuario_id);
            echo json_encode($res_comp);
            break;

        case 'crear_seguimiento':
            // Caso C29: Ciclo de seguimiento con plan de acción
            $ciclo_origen_id = intval($_POST['ciclo_origen_id'] ?? 0);
            $plan_accion = trim($_POST['plan_accion'] ?? '');

            if ($ciclo_origen_id <= 0 || empty($plan_accion)) {
                echo json_encode(['success' => false, 'message' => 'Parámetros incompletos para crear ciclo de seguimiento.']);
                exit;
            }

            $res_seg = ciclos_crear_seguimiento($conexion, $ciclo_origen_id, $plan_accion, $usuario_id);
            echo json_encode($res_seg);
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
