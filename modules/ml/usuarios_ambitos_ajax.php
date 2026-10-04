<?php
/**
 * Controlador AJAX para Ámbitos de Usuarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/usuarios_ambitos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/usuarios_ambitos_model.php';
require_once __DIR__ . '/grupos_regiones_model.php';
require_once __DIR__ . '/feriados_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 309; // Ámbitos de Usuarios

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 309
$funciones_permitidas = [];
if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
    $sql_f = "SELECT pagina_funcion_id, accion_js, nombre_funcion, icono_id, color_id, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id
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
        $sql_f = "SELECT DISTINCT pf.pagina_funcion_id, pf.accion_js, pf.nombre_funcion, pf.icono_id, pf.color_id, pf.tabla_estado_registro_origen_id, pf.tabla_estado_registro_destino_id
                  FROM conf__paginas_funciones pf
                  INNER JOIN conf__empresas_perfiles_funciones epf 
                      ON epf.pagina_funcion_id = pf.pagina_funcion_id 
                      AND epf.empresa_perfil_id IN ($placeholders)
                  WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_id = 1 
                    AND epf.asignado = 1";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        $types = str_repeat('i', count($perfil_ids)) . 'i';
        $params = array_merge($perfil_ids, [$pagina_idx]);
        mysqli_stmt_bind_param($stmt_f, $types, ...$params);
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
    case 'listar':
        $filtro_usuario = intval($_GET['filtro_usuario'] ?? 0);
        $filtro_tipo = intval($_GET['filtro_tipo'] ?? 0);
        $filtro_estado = intval($_GET['filtro_estado'] ?? 1); // 1 = solo activos, 0 = todos

        $lista = usuarios_ambitos_listar($conexion, $filtro_usuario, $filtro_tipo, $filtro_estado === 1);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $es_activo = intval($row['es_activo']);

            if ($es_activo === 1 && isset($funciones_permitidas['cerrar_ambito'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-warning btn-cerrar-ambito me-1" data-id="' . $row['usuario_ambito_id'] . '" data-usuario="' . htmlspecialchars($row['usuario_login']) . '" title="Cerrar vigencia del ámbito"><i class="fas fa-calendar-times"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_combos':
        $combos_base = usuarios_ambitos_combos($conexion);
        $grupos = grupos_listar($conexion, 1);
        $regiones = regiones_listar($conexion, 0, 1);
        $empresas = obtener_empresas_combo($conexion);
        $territorios = feriados_obtener_territorios_combo($conexion);

        echo json_encode([
            'success' => true,
            'data' => array_merge($combos_base, [
                'grupos' => $grupos,
                'regiones' => $regiones,
                'empresas' => $empresas,
                'territorios' => $territorios,
                'permite_agregar' => isset($funciones_permitidas['agregar_ambito'])
            ])
        ]);
        break;

    case 'guardar':
        if (!isset($funciones_permitidas['agregar_ambito'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para asignar ámbitos.']);
            exit;
        }

        $datos = [
            'usuario_id' => intval($_POST['usuario_id'] ?? 0),
            'empresa_perfil_id' => intval($_POST['empresa_perfil_id'] ?? 0),
            'ambito_tipo_id' => intval($_POST['ambito_tipo_id'] ?? 0),
            'vigente_desde' => trim($_POST['vigente_desde'] ?? ''),
            'vigente_hasta' => !empty($_POST['vigente_hasta']) ? trim($_POST['vigente_hasta']) : null,
            'grupo_id' => !empty($_POST['grupo_id']) ? intval($_POST['grupo_id']) : null,
            'region_grupo_id' => !empty($_POST['region_grupo_id']) ? intval($_POST['region_grupo_id']) : null,
            'empresa_id' => !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null,
            'territorio_id' => !empty($_POST['territorio_id']) ? intval($_POST['territorio_id']) : null
        ];

        $res = usuarios_ambitos_crear($conexion, $datos, $usuario_id);
        echo json_encode($res);
        break;

    case 'cerrar':
        if (!isset($funciones_permitidas['cerrar_ambito'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para cerrar ámbitos.']);
            exit;
        }

        $id = intval($_POST['usuario_ambito_id'] ?? 0);
        $fecha = trim($_POST['fecha_cierre'] ?? date('Y-m-d'));

        $res = usuarios_ambitos_cerrar($conexion, $id, $fecha, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
