<?php
/**
 * Controlador AJAX: Matriz RACI y Asignaciones (Página 344 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/ciclos_raci_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/ciclos_raci_model.php';
require_once __DIR__ . '/ciclos_generador_service.php';
require_once __DIR__ . '/posiciones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 344; // Matriz RACI y Asignaciones

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 344
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
            // Entidades activas de la empresa
            $res_e = mysqli_query($conexion, "SELECT DISTINCT e.entidad_id, e.codigo, e.nombre 
                                               FROM ml__entidades e 
                                               INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id 
                                               WHERE (ee.empresa_id = {$empresa_idx} OR {$empresa_idx} = 1) AND e.tabla_estado_registro_id = 1 
                                               ORDER BY e.nombre ASC");
            $entidades = [];
            while ($r = mysqli_fetch_assoc($res_e)) { $entidades[] = $r; }

            // Posiciones / Cargos de la empresa
            $res_p = mysqli_query($conexion, "SELECT p.posicion_id, p.codigo, p.nombre, p.area_departamento,
                                                     u.usuario_id AS titular_id, u.usuario_nombre AS titular_nombre
                                              FROM ml__posiciones p
                                              LEFT JOIN ml__usuarios_posiciones up ON up.posicion_id = p.posicion_id 
                                                   AND up.es_titular = 1 
                                                   AND (up.vigente_hasta IS NULL OR up.vigente_hasta >= CURDATE())
                                              LEFT JOIN conf__usuarios u ON u.usuario_id = up.usuario_id
                                              WHERE p.empresa_id = {$empresa_idx} AND p.tabla_estado_registro_id = 1
                                              ORDER BY p.area_departamento ASC, p.nombre ASC");
            $posiciones = [];
            while ($r = mysqli_fetch_assoc($res_p)) { $posiciones[] = $r; }

            // Usuarios
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

            // Tareas abiertas recientes (para asignación rápida)
            $res_t = mysqli_query($conexion, "SELECT ct.ciclo_tarea_id, ct.titulo, c.ciclo_id, o.codigo as obligacion_codigo
                                              FROM ml__ciclos_tareas ct
                                              INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
                                              INNER JOIN ml__obligaciones_asignadas oa ON oa.obligacion_asignada_id = c.obligacion_asignada_id
                                              INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                                              WHERE ct.empresa_id = {$empresa_idx} AND ct.tabla_estado_registro_id IN (2077, 2078, 2079, 2080)
                                              ORDER BY ct.ciclo_tarea_id DESC LIMIT 50");
            $tareas = [];
            while ($r = mysqli_fetch_assoc($res_t)) { $tareas[] = $r; }

            echo json_encode([
                'success' => true,
                'entidades' => $entidades,
                'posiciones' => $posiciones,
                'usuarios' => $usuarios,
                'tareas' => $tareas,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'listar':
            $filtros = [
                'usuario_id' => $_GET['filtro_usuario'] ?? null,
                'posicion_id' => $_GET['filtro_posicion'] ?? null,
                'raci_rol_id' => $_GET['filtro_rol'] ?? null,
                'entidad_id' => $_GET['filtro_entidad'] ?? null,
                'ciclo_id' => $_GET['filtro_ciclo'] ?? null,
            ];

            $asignaciones = ciclos_raci_listar($conexion, $empresa_idx, $filtros);
            echo json_encode([
                'success' => true,
                'data' => $asignaciones,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'obtener_info_posicion':
            $pos_id = intval($_GET['posicion_id'] ?? 0);
            $titular = posicion_obtener_titular($conexion, $pos_id);
            $supervisor = posicion_obtener_supervisor_de_posicion($conexion, $pos_id);
            echo json_encode([
                'success' => true,
                'titular' => $titular,
                'supervisor' => $supervisor
            ]);
            break;

        case 'guardar':
            $ciclo_tarea_id = intval($_POST['ciclo_tarea_id'] ?? 0);
            $raci_rol_id = intval($_POST['raci_rol_id'] ?? 0);
            $usuario_destino = !empty($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
            $posicion_id = !empty($_POST['posicion_id']) ? intval($_POST['posicion_id']) : null;
            $usar_supervisor = !empty($_POST['usar_supervisor']) ? intval($_POST['usar_supervisor']) : 0;

            $res = ciclos_raci_guardar($conexion, $ciclo_tarea_id, $raci_rol_id, $usuario_destino, $empresa_idx, $usuario_id, $posicion_id, $usar_supervisor);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error']
            ]);
            break;

        case 'eliminar':
            $ciclo_tarea_raci_id = intval($_POST['ciclo_tarea_raci_id'] ?? 0);
            $res = ciclos_raci_eliminar($conexion, $ciclo_tarea_raci_id, $empresa_idx);
            echo json_encode([
                'success' => $res['ok'],
                'message' => $res['ok'] ? $res['mensaje'] : $res['error']
            ]);
            break;

        case 'reasignar_bloque':
            // Caso C27: Reasignación masiva de tareas cuando un usuario deja la empresa
            $usuario_origen_id = intval($_POST['usuario_origen_id'] ?? 0);
            $usuario_destino_id = intval($_POST['usuario_destino_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $fecha_efecto = trim($_POST['fecha_efecto'] ?? date('Y-m-d'));

            if ($usuario_origen_id <= 0 || $usuario_destino_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar usuario de origen y destino.']);
                exit;
            }

            if ($usuario_origen_id === $usuario_destino_id) {
                echo json_encode(['success' => false, 'message' => 'El usuario de origen y destino no pueden ser el mismo.']);
                exit;
            }

            $res_reasig = ciclos_reasignar_responsable_bloque($conexion, $empresa_idx, $usuario_origen_id, $usuario_destino_id, $motivo, $usuario_id);
            echo json_encode($res_reasig);
            break;

        case 'listar_reasignaciones':
            $lista = ciclos_raci_listar_reasignaciones($conexion);
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
