<?php
/**
 * Controlador AJAX para Matriz de Obligaciones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/obligaciones_reglas_model.php';
require_once __DIR__ . '/obligaciones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 323; // Matriz de Obligaciones

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 323
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
        $funciones_permitidas[$rf['accion_js']] = $rf;
    }
    mysqli_stmt_close($stmt_f);
} else {
    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_idx);
    if (!empty($perfiles)) {
        $perfil_ids = array_column($perfiles, 'empresa_perfil_id');
        $placeholders = implode(',', array_fill(0, count($perfil_ids), '?'));
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion
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
    case 'combos':
        $combos = obligaciones_combos($conexion);
        echo json_encode([
            'success' => true,
            'data' => $combos,
            'permisos' => [
                'permite_crear' => isset($funciones_permitidas['crear_obligacion']),
                'permite_enviar' => isset($funciones_permitidas['enviar_revision_obligacion']),
                'permite_aprobar' => isset($funciones_permitidas['aprobar_obligacion']),
                'permite_suspender' => isset($funciones_permitidas['suspender_obligacion']),
                'permite_entidad' => isset($funciones_permitidas['asignar_tipo_entidad']),
                'permite_dependencia' => isset($funciones_permitidas['vincular_dependencia_obligacion']),
                'permite_alerta' => isset($funciones_permitidas['configurar_alerta'])
            ]
        ]);
        break;

    case 'listar':
        $filtros = [
            'norma_id' => $_GET['filtro_norma'] ?? '',
            'periodicidad_id' => $_GET['filtro_periodicidad'] ?? '',
            'criticidad_id' => $_GET['filtro_criticidad'] ?? '',
            'tabla_estado_registro_id' => $_GET['filtro_estado'] ?? ''
        ];
        $lista = obligaciones_listar($conexion, $filtros);

        foreach ($lista as &$o) {
            $est = intval($o['tabla_estado_registro_id']);
            $o['permite_editar'] = isset($funciones_permitidas['crear_obligacion']) && $est === 2061;
            $o['permite_enviar'] = isset($funciones_permitidas['enviar_revision_obligacion']) && $est === 2061;
            $o['permite_aprobar'] = isset($funciones_permitidas['aprobar_obligacion']) && $est === 2062;
            $o['permite_suspender'] = isset($funciones_permitidas['suspender_obligacion']) && $est === 2063;
        }
        unset($o);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permite_crear' => isset($funciones_permitidas['crear_obligacion'])
        ]);
        break;

    case 'obtener':
        $id = intval($_GET['obligacion_id'] ?? 0);
        $ob = obligaciones_obtener_por_id($conexion, $id);
        if ($ob) echo json_encode(['success' => true, 'data' => $ob]);
        else echo json_encode(['success' => false, 'message' => 'Obligación no encontrada.']);
        break;

    case 'guardar':
        $id = intval($_POST['obligacion_id'] ?? 0);
        if ($id > 0) {
            $res = obligaciones_actualizar($conexion, $id, $_POST, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['crear_obligacion'])) {
                echo json_encode(['success' => false, 'message' => 'No tiene permisos para crear obligaciones.']);
                exit;
            }
            $res = obligaciones_crear($conexion, $_POST, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $id = intval($_POST['obligacion_id'] ?? 0);
        $estado = intval($_POST['nuevo_estado'] ?? 0);

        if ($estado === 2063 && !isset($funciones_permitidas['aprobar_obligacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar obligaciones.']);
            exit;
        }
        if ($estado === 2064 && !isset($funciones_permitidas['suspender_obligacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para suspender obligaciones.']);
            exit;
        }

        $res = obligaciones_cambiar_estado($conexion, $id, $estado, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_tipos_entidad':
        $id = intval($_GET['obligacion_id'] ?? 0);
        $res = obligaciones_tipos_entidades_listar($conexion, $id);
        echo json_encode(['success' => true, 'data' => $res]);
        break;

    case 'guardar_tipo_entidad':
        if (!isset($funciones_permitidas['asignar_tipo_entidad'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para asignar tipos de entidad.']);
            exit;
        }
        $res = obligaciones_tipo_entidad_asignar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_tipo_entidad':
        if (!isset($funciones_permitidas['asignar_tipo_entidad'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para desvincular tipo de entidad.']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        $res = obligaciones_tipo_entidad_eliminar($conexion, $id);
        echo json_encode($res);
        break;

    case 'listar_dependencias':
        $id = intval($_GET['obligacion_id'] ?? 0);
        $res = obligaciones_dependencias_listar($conexion, $id);
        echo json_encode(['success' => true, 'data' => $res]);
        break;

    case 'guardar_dependencia':
        if (!isset($funciones_permitidas['vincular_dependencia_obligacion'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para vincular dependencias.']);
            exit;
        }
        $res = obligaciones_dependencia_vincular($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_dependencia':
        if (!isset($funciones_permitidas['vincular_dependencia_obligacion'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para eliminar dependencias.']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        $res = obligaciones_dependencia_eliminar($conexion, $id);
        echo json_encode($res);
        break;

    case 'listar_alertas':
        $id = intval($_GET['obligacion_id'] ?? 0);
        $res = obligaciones_alertas_listar($conexion, $id);
        echo json_encode(['success' => true, 'data' => $res]);
        break;

    case 'guardar_alerta':
        if (!isset($funciones_permitidas['configurar_alerta'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para configurar alertas.']);
            exit;
        }
        $res = obligaciones_alerta_guardar($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_alerta':
        if (!isset($funciones_permitidas['configurar_alerta'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para eliminar alertas.']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        $res = obligaciones_alerta_eliminar($conexion, $id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
