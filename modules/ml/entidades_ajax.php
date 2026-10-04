<?php
/**
 * Controlador AJAX para Entidades Sujetas a Control (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/entidades_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/entidades_model.php';
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
$pagina_idx = 304; // Entidades

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 304
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
        $filtro_empresa = intval($_GET['filtro_empresa'] ?? 0);
        $filtro_tipo = intval($_GET['filtro_tipo'] ?? 0);
        $filtro_estado = intval($_GET['filtro_estado'] ?? 0);
        $filtro_movil = isset($_GET['filtro_movil']) ? intval($_GET['filtro_movil']) : -1;

        $lista = entidades_listar($conexion, $filtro_empresa, $filtro_tipo, $filtro_estado, $filtro_movil);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            if (isset($funciones_permitidas['editar'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar me-1" data-id="' . $row['entidad_id'] . '" title="Editar datos"><i class="fas fa-edit"></i></button>';
            }

            if (isset($funciones_permitidas['transferir'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-warning btn-transferir me-1" data-id="' . $row['entidad_id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" data-empresa="' . $row['empresa_titular_id'] . '" title="Transferir titularidad a otra empresa"><i class="fas fa-exchange-alt"></i></button>';
            }

            if (isset($funciones_permitidas['cambiar_ubicacion'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-info btn-ubicacion me-1" data-id="' . $row['entidad_id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" data-territorio="' . $row['ubicacion_territorio_id'] . '" title="Cambiar ubicación base"><i class="fas fa-map-marker-alt"></i></button>';
            }

            if ($estado_actual === 1 && isset($funciones_permitidas['inactivar'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-estado me-1" data-id="' . $row['entidad_id'] . '" data-estado="2" title="Inactivar entidad"><i class="fas fa-ban"></i></button>';
            } elseif ($estado_actual === 2 && isset($funciones_permitidas['activar'])) {
                $botones[] = '<button type="button" class="btn btn-sm btn-outline-success btn-estado me-1" data-id="' . $row['entidad_id'] . '" data-estado="1" title="Activar entidad"><i class="fas fa-check"></i></button>';
            }

            // Botón Historial
            $botones[] = '<button type="button" class="btn btn-sm btn-outline-secondary btn-historial me-1" data-id="' . $row['entidad_id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" title="Ver historial de titularidad y sedes"><i class="fas fa-history"></i></button>';

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_combos':
        $tipos = entidades_tipos_combo($conexion);
        $empresas = obtener_empresas_combo($conexion);
        $territorios = feriados_obtener_territorios_combo($conexion);
        $padres = entidades_padres_combo($conexion);

        echo json_encode([
            'success' => true,
            'data' => [
                'tipos' => $tipos,
                'empresas' => $empresas,
                'territorios' => $territorios,
                'padres' => $padres,
                'permite_agregar' => isset($funciones_permitidas['agregar'])
            ]
        ]);
        break;

    case 'obtener_entidad':
        $id = intval($_GET['entidad_id'] ?? 0);
        $entidad = entidades_obtener_por_id($conexion, $id);
        if ($entidad) {
            echo json_encode(['success' => true, 'data' => $entidad]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Entidad no encontrada.']);
        }
        break;

    case 'guardar':
        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        $datos = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'entidad_tipo_id' => intval($_POST['entidad_tipo_id'] ?? 0),
            'entidad_padre_id' => !empty($_POST['entidad_padre_id']) ? intval($_POST['entidad_padre_id']) : null,
            'empresa_titular_id' => intval($_POST['empresa_titular_id'] ?? 0),
            'territorio_id' => intval($_POST['territorio_id'] ?? 0),
            'vigente_desde' => trim($_POST['vigente_desde'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? '')
        ];

        if ($entidad_id > 0) {
            if (!isset($funciones_permitidas['editar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar entidades.']);
                exit;
            }
            $res = entidades_actualizar($conexion, $entidad_id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para crear entidades.']);
                exit;
            }
            $res = entidades_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'transferir':
        if (!isset($funciones_permitidas['transferir'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para transferir titularidad.']);
            exit;
        }

        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        $nueva_empresa_id = intval($_POST['nueva_empresa_id'] ?? 0);
        $fecha = trim($_POST['fecha_transferencia'] ?? date('Y-m-d'));

        $res = entidades_transferir_titularidad($conexion, $entidad_id, $nueva_empresa_id, $fecha, $usuario_id);
        echo json_encode($res);
        break;

    case 'cambiar_ubicacion':
        if (!isset($funciones_permitidas['cambiar_ubicacion'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para cambiar la ubicación territorial.']);
            exit;
        }

        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        $nuevo_territorio_id = intval($_POST['nuevo_territorio_id'] ?? 0);
        $fecha = trim($_POST['fecha_traslado'] ?? date('Y-m-d'));

        $res = entidades_cambiar_ubicacion($conexion, $entidad_id, $nuevo_territorio_id, $fecha, $usuario_id);
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);

        if ($nuevo_estado === 2 && !isset($funciones_permitidas['inactivar'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para inactivar.']);
            exit;
        }
        if ($nuevo_estado === 1 && !isset($funciones_permitidas['activar'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para activar.']);
            exit;
        }

        $res = entidades_cambiar_estado($conexion, $entidad_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    case 'historial':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        $hist_emp = entidades_historial_empresas($conexion, $entidad_id);
        $hist_ubi = entidades_historial_ubicaciones($conexion, $entidad_id);

        echo json_encode([
            'success' => true,
            'data' => [
                'empresas' => $hist_emp,
                'ubicaciones' => $hist_ubi
            ]
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}