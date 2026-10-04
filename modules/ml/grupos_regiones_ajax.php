<?php
/**
 * Controlador AJAX para Grupos Corporativos y Regiones de Gestión (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/grupos_regiones_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/grupos_regiones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 302; // Grupos y Regiones

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Obtener permisos de la página 302
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
    // -------------------------------------------------------------------------
    // COMBOS Y BOTONES
    // -------------------------------------------------------------------------
    case 'obtener_combos':
        $empresas = obtener_empresas_combo($conexion);
        $grupos = grupos_listar($conexion, 1);
        $regiones = regiones_listar($conexion, 0, 1);
        echo json_encode([
            'success' => true,
            'data' => [
                'empresas' => $empresas,
                'grupos' => $grupos,
                'regiones' => $regiones,
                'permisos' => array_keys($funciones_permitidas)
            ]
        ]);
        break;

    // -------------------------------------------------------------------------
    // GRUPOS
    // -------------------------------------------------------------------------
    case 'listar_grupos':
        $estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = grupos_listar($conexion, $estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            if (isset($funciones_permitidas['editar_grupo'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-grupo me-1" data-id="' . $row['grupo_id'] . '" title="Editar grupo"><i class="fas fa-edit"></i></button>';
            }

            if ($estado_actual === 1) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-estado-grupo me-1" data-id="' . $row['grupo_id'] . '" data-estado="2" title="Inactivar grupo"><i class="fas fa-ban"></i></button>';
            } else {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-estado-grupo me-1" data-id="' . $row['grupo_id'] . '" data-estado="1" title="Activar grupo"><i class="fas fa-check"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_grupo':
        $id = intval($_GET['grupo_id'] ?? 0);
        $grupo = grupos_obtener_por_id($conexion, $id);
        if ($grupo) {
            echo json_encode(['success' => true, 'data' => $grupo]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Grupo no encontrado.']);
        }
        break;

    case 'guardar_grupo':
        $grupo_id = intval($_POST['grupo_id'] ?? 0);
        $datos = [
            'empresa_titular_id' => intval($_POST['empresa_titular_id'] ?? 0),
            'nombre' => trim($_POST['nombre'] ?? '')
        ];

        if ($grupo_id > 0) {
            if (!isset($funciones_permitidas['editar_grupo'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar grupos.']);
                exit;
            }
            $res = grupos_actualizar($conexion, $grupo_id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar_grupo'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para crear grupos.']);
                exit;
            }
            $res = grupos_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado_grupo':
        $grupo_id = intval($_POST['grupo_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 2);
        if (!isset($funciones_permitidas['editar_grupo'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado.']);
            exit;
        }
        $res = grupos_cambiar_estado($conexion, $grupo_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    // -------------------------------------------------------------------------
    // REGIONES
    // -------------------------------------------------------------------------
    case 'listar_regiones':
        $filtro_grupo = intval($_GET['filtro_grupo'] ?? 0);
        $filtro_estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = regiones_listar($conexion, $filtro_grupo, $filtro_estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            if (isset($funciones_permitidas['editar_region'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-region me-1" data-id="' . $row['region_grupo_id'] . '" title="Editar región"><i class="fas fa-edit"></i></button>';
            }
            if (isset($funciones_permitidas['asignar_empresa'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-asignar-empresa me-1" data-id="' . $row['region_grupo_id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" title="Asignar empresa"><i class="fas fa-building"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_region':
        $id = intval($_GET['region_grupo_id'] ?? 0);
        $region = regiones_obtener_por_id($conexion, $id);
        if ($region) {
            echo json_encode(['success' => true, 'data' => $region]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Región no encontrada.']);
        }
        break;

    case 'guardar_region':
        $region_grupo_id = intval($_POST['region_grupo_id'] ?? 0);
        $datos = [
            'grupo_id' => intval($_POST['grupo_id'] ?? 0),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'vigente_desde' => trim($_POST['vigente_desde'] ?? ''),
            'vigente_hasta' => trim($_POST['vigente_hasta'] ?? '')
        ];

        if ($region_grupo_id > 0) {
            if (!isset($funciones_permitidas['editar_region'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar regiones.']);
                exit;
            }
            $res = regiones_actualizar($conexion, $region_grupo_id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar_region'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para crear regiones.']);
                exit;
            }
            $res = regiones_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    // -------------------------------------------------------------------------
    // EMPRESAS POR REGIÓN
    // -------------------------------------------------------------------------
    case 'listar_empresas_regiones':
        $filtro_region = intval($_GET['filtro_region'] ?? 0);
        $filtro_empresa = intval($_GET['filtro_empresa'] ?? 0);
        $lista = empresas_regiones_listar($conexion, $filtro_region, $filtro_empresa);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            if (isset($funciones_permitidas['asignar_empresa']) && intval($row['es_activa']) === 1) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-warning btn-cerrar-vigencia me-1" data-id="' . $row['empresa_region_id'] . '" data-empresa="' . htmlspecialchars($row['empresa_nombre']) . '" title="Cerrar vigencia en la región"><i class="fas fa-calendar-times"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'asignar_empresa':
        if (!isset($funciones_permitidas['asignar_empresa'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para vincular empresas a regiones.']);
            exit;
        }

        $datos = [
            'empresa_id' => intval($_POST['empresa_id'] ?? 0),
            'region_grupo_id' => intval($_POST['region_grupo_id'] ?? 0),
            'vigente_desde' => trim($_POST['vigente_desde'] ?? ''),
            'vigente_hasta' => trim($_POST['vigente_hasta'] ?? '')
        ];
        $res = empresas_regiones_asignar($conexion, $datos, $usuario_id);
        echo json_encode($res);
        break;

    case 'cerrar_vigencia_empresa':
        if (!isset($funciones_permitidas['asignar_empresa'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para modificar vigencias.']);
            exit;
        }

        $id = intval($_POST['empresa_region_id'] ?? 0);
        $fecha = trim($_POST['fecha_cierre'] ?? date('Y-m-d'));
        $res = empresas_regiones_cerrar_vigencia($conexion, $id, $fecha, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
