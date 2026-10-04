<?php
/**
 * Controlador AJAX para Tipos de Entidad y Tipos de Datos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_tipos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/entidades_tipos_model.php';
require_once __DIR__ . '/grupos_regiones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 307; // Tipos de Entidad y Datos

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 307
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
    case 'obtener_combos':
        $grupos = grupos_listar($conexion, 1);
        echo json_encode([
            'success' => true,
            'data' => [
                'grupos' => $grupos,
                'permite_agregar_tipo' => isset($funciones_permitidas['agregar_tipo']),
                'permite_agregar_dato' => isset($funciones_permitidas['agregar_dato'])
            ]
        ]);
        break;

    // -------------------------------------------------------------------------
    // TIPOS DE ENTIDAD
    // -------------------------------------------------------------------------
    case 'listar_tipos_entidad':
        $filtro_grupo = intval($_GET['filtro_grupo'] ?? 0);
        $filtro_estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = tipos_entidad_listar($conexion, $filtro_grupo, $filtro_estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            if (isset($funciones_permitidas['editar_tipo'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-tipo me-1" data-id="' . $row['entidad_tipo_id'] . '" title="Editar tipo"><i class="fas fa-edit"></i></button>';
            }

            if ($estado_actual === 1) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-estado-tipo me-1" data-id="' . $row['entidad_tipo_id'] . '" data-estado="2" title="Inactivar"><i class="fas fa-ban"></i></button>';
            } else {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-estado-tipo me-1" data-id="' . $row['entidad_tipo_id'] . '" data-estado="1" title="Activar"><i class="fas fa-check"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_tipo_entidad':
        $id = intval($_GET['entidad_tipo_id'] ?? 0);
        $tipo = tipos_entidad_obtener_por_id($conexion, $id);
        if ($tipo) {
            echo json_encode(['success' => true, 'data' => $tipo]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo de entidad no encontrado.']);
        }
        break;

    case 'guardar_tipo_entidad':
        $id = intval($_POST['entidad_tipo_id'] ?? 0);
        $datos = [
            'grupo_id' => intval($_POST['grupo_id'] ?? 1),
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'es_fisica' => !empty($_POST['es_fisica']) ? 1 : 0,
            'es_logica' => !empty($_POST['es_logica']) ? 1 : 0,
            'es_movil' => !empty($_POST['es_movil']) ? 1 : 0
        ];

        if ($id > 0) {
            if (!isset($funciones_permitidas['editar_tipo'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar tipos de entidad.']);
                exit;
            }
            $res = tipos_entidad_actualizar($conexion, $id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar_tipo'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para crear tipos de entidad.']);
                exit;
            }
            $res = tipos_entidad_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado_tipo_entidad':
        $id = intval($_POST['entidad_tipo_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 2);
        $res = tipos_entidad_cambiar_estado($conexion, $id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    // -------------------------------------------------------------------------
    // TIPOS DE DATOS COMPLEMENTARIOS
    // -------------------------------------------------------------------------
    case 'listar_tipos_datos':
        $estado = intval($_GET['filtro_estado'] ?? 0);
        $lista = datos_tipos_listar($conexion, $estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            if (isset($funciones_permitidas['editar_dato'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-dato me-1" data-id="' . $row['dato_tipo_id'] . '" title="Editar dato"><i class="fas fa-edit"></i></button>';
            }

            if ($estado_actual === 1) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-estado-dato me-1" data-id="' . $row['dato_tipo_id'] . '" data-estado="2" title="Inactivar"><i class="fas fa-ban"></i></button>';
            } else {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-estado-dato me-1" data-id="' . $row['dato_tipo_id'] . '" data-estado="1" title="Activar"><i class="fas fa-check"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_tipo_dato':
        $id = intval($_GET['dato_tipo_id'] ?? 0);
        $dato = datos_tipos_obtener_por_id($conexion, $id);
        if ($dato) {
            echo json_encode(['success' => true, 'data' => $dato]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo de dato no encontrado.']);
        }
        break;

    case 'guardar_tipo_dato':
        $id = intval($_POST['dato_tipo_id'] ?? 0);
        $datos = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'tipo_valor' => trim($_POST['tipo_valor'] ?? 'TEXTO')
        ];

        if ($id > 0) {
            if (!isset($funciones_permitidas['editar_dato'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar tipos de datos.']);
                exit;
            }
            $res = datos_tipos_actualizar($conexion, $id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar_dato'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para crear tipos de datos.']);
                exit;
            }
            $res = datos_tipos_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado_tipo_dato':
        $id = intval($_POST['dato_tipo_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 2);
        $res = datos_tipos_cambiar_estado($conexion, $id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
