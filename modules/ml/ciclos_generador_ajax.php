<?php
/**
 * Controlador AJAX: Consola del Generador y Eventos (Página 345 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_generador_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/ciclos_generador_model.php';
require_once __DIR__ . '/ciclos_generador_service.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 345; // Generador y Eventos

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 345
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
        case 'metricas':
            $metricas = ciclos_generador_obtener_metricas($conexion, $empresa_idx);
            echo json_encode([
                'success' => true,
                'metricas' => $metricas,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'combos':
            // Entidades
            $res_e = mysqli_query($conexion, "SELECT DISTINCT e.entidad_id, e.codigo, e.nombre 
                                               FROM ml__entidades e 
                                               LEFT JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id 
                                               WHERE (ee.empresa_id = {$empresa_idx} OR ee.empresa_id IS NULL) AND e.tabla_estado_registro_id = 1 
                                               ORDER BY e.nombre ASC");
            $entidades = [];
            while ($r = mysqli_fetch_assoc($res_e)) { $entidades[] = $r; }

            // Obligaciones por evento
            $obs_evento = ciclos_generador_listar_obligaciones_evento($conexion, $empresa_idx);

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'obligaciones_evento' => $obs_evento
            ]);
            break;

        case 'proyectar':
            $entidad_id = !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null;
            $meses_adelante = !empty($_POST['meses_adelante']) ? intval($_POST['meses_adelante']) : 12;

            $res_proy = ciclos_generador_ejecutar_proyeccion($conexion, $empresa_idx, $entidad_id, $meses_adelante, $usuario_id);
            echo json_encode([
                'success' => true,
                'message' => "Proyección masiva completada: {$res_proy['ciclos_creados']} ciclos y {$res_proy['tareas_creadas']} tareas operativas generadas.",
                'resultado' => $res_proy
            ]);
            break;

        case 'disparar_evento':
            // Casos C07 y C31: Gatillo por evento (accidente, derrame)
            $obligacion_asignada_id = intval($_POST['obligacion_asignada_id'] ?? 0);
            $fecha_hora_evento = trim($_POST['fecha_hora_evento'] ?? date('Y-m-d H:i:s'));
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($obligacion_asignada_id <= 0 || empty($fecha_hora_evento)) {
                echo json_encode(['success' => false, 'message' => 'Debe indicar la obligación asignada y la fecha/hora del evento.']);
                exit;
            }

            $datos_evento = [
                'obligacion_asignada_id' => $obligacion_asignada_id,
                'fecha_hora_evento' => $fecha_hora_evento,
                'descripcion' => $descripcion,
                'plazo_horas' => 48
            ];

            $res_evt = ciclos_disparar_evento($conexion, $datos_evento, $usuario_id);
            echo json_encode($res_evt);
            break;

        case 'ultimos_ciclos':
            $sql_uc = "SELECT 
                        c.ciclo_id,
                        DATE_FORMAT(c.periodo_desde, '%d/%m/%Y') as periodo_desde_fmt,
                        DATE_FORMAT(c.periodo_hasta, '%d/%m/%Y') as periodo_hasta_fmt,
                        DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y %H:%i') as fecha_vencimiento_fmt,
                        DATE_FORMAT(c.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                        est.tabla_estado_registro as estado_nombre,
                        o.codigo as obligacion_codigo,
                        o.descripcion as obligacion_titulo,
                        e.nombre as entidad_nombre,
                        (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id) as total_tareas
                       FROM ml__ciclos c
                       INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
                       INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                       INNER JOIN ml__entidades e ON e.entidad_id = c.entidad_id
                       INNER JOIN conf__tablas_estados_registros est ON est.tabla_estado_registro_id = c.tabla_estado_registro_id
                       WHERE c.empresa_id = {$empresa_idx}
                       ORDER BY c.ciclo_id DESC
                       LIMIT 15";
            $res_uc = mysqli_query($conexion, $sql_uc);
            $lista = [];
            if ($res_uc) {
                while ($r = mysqli_fetch_assoc($res_uc)) {
                    $lista[] = $r;
                }
            }
            echo json_encode([
                'success' => true,
                'data' => $lista
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
