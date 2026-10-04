<?php
/**
 * Controlador AJAX para Feriados y Calendario Normativo (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/feriados_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/feriados_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 306; // Feriados

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Obtener permisos del usuario en esta página
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
        $filtro_territorio = intval($_GET['filtro_territorio'] ?? 0);
        $filtro_anio = intval($_GET['filtro_anio'] ?? 0);
        $filtro_estado = intval($_GET['filtro_estado'] ?? 0);

        $lista = feriados_listar($conexion, $filtro_territorio, $filtro_anio, $filtro_estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            // Botón Editar
            if (isset($funciones_permitidas['editar'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar me-1" data-id="' . $row['feriado_id'] . '" title="Editar feriado"><i class="fas fa-edit"></i></button>';
            }

            // Botón Eliminar / Inactivar
            if (isset($funciones_permitidas['eliminar']) && $estado_actual === 1) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar me-1" data-id="' . $row['feriado_id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" title="Eliminar feriado"><i class="fas fa-trash-alt"></i></button>';
            }

            // Botón Reactivar si está inactivo
            if (isset($funciones_permitidas['editar']) && $estado_actual === 2) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-reactivar me-1" data-id="' . $row['feriado_id'] . '" title="Reactivar feriado"><i class="fas fa-undo"></i></button>';
            }

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_boton_agregar':
        $html = '';
        if (isset($funciones_permitidas['agregar'])) {
            $html = '<button type="button" class="btn btn-sm btn-primary" id="btnNuevoFeriado">
                        <i class="fas fa-plus me-1"></i>Nuevo Feriado
                     </button>';
        }
        echo json_encode(['success' => true, 'data' => $html]);
        break;

    case 'obtener_territorios':
        $territorios = feriados_obtener_territorios_combo($conexion);
        echo json_encode(['success' => true, 'data' => $territorios]);
        break;

    case 'obtener_feriado':
        $feriado_id = intval($_GET['feriado_id'] ?? 0);
        $feriado = feriados_obtener_por_id($conexion, $feriado_id);
        if ($feriado) {
            echo json_encode(['success' => true, 'data' => $feriado]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Feriado no encontrado.']);
        }
        break;

    case 'guardar':
        $feriado_id = intval($_POST['feriado_id'] ?? 0);
        $datos = [
            'territorio_id' => intval($_POST['territorio_id'] ?? 0),
            'fecha' => trim($_POST['fecha'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? '')
        ];

        if ($feriado_id > 0) {
            if (!isset($funciones_permitidas['editar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar feriados.']);
                exit;
            }
            $res = feriados_actualizar($conexion, $feriado_id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para registrar feriados.']);
                exit;
            }
            $res = feriados_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $feriado_id = intval($_POST['feriado_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 2);

        if (!isset($funciones_permitidas['eliminar']) && !isset($funciones_permitidas['editar'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para cambiar el estado.']);
            exit;
        }

        $res = feriados_cambiar_estado($conexion, $feriado_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
