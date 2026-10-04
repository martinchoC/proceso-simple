<?php
/**
 * Controlador AJAX para Eventos Societarios y Cambios de Razón Social (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/empresas_eventos_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/empresas_eventos_model.php';
require_once __DIR__ . '/grupos_regiones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 303; // Eventos Societarios

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Obtener permisos de la página 303
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

        $lista = empresas_eventos_listar($conexion, $filtro_empresa, $filtro_tipo, $filtro_estado);
        $data = [];

        foreach ($lista as $row) {
            $botones = [];
            $estado_actual = intval($row['tabla_estado_registro_id']);

            // En Borrador (3): editar, enviar a aprobación, eliminar
            if ($estado_actual === 3) {
                if (isset($funciones_permitidas['editar'])) {
                    $botones[] = '<button type="button" class="btn btn-sm btn-outline-primary btn-editar me-1" data-id="' . $row['empresa_evento_id'] . '" title="Editar evento borrador"><i class="fas fa-edit"></i></button>';
                }
                if (isset($funciones_permitidas['enviar_aprobacion'])) {
                    $botones[] = '<button type="button" class="btn btn-sm btn-outline-warning btn-transicionar me-1" data-id="' . $row['empresa_evento_id'] . '" data-estado="4" title="Enviar a Aprobación"><i class="fas fa-paper-plane"></i></button>';
                }
                if (isset($funciones_permitidas['eliminar'])) {
                    $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-transicionar me-1" data-id="' . $row['empresa_evento_id'] . '" data-estado="6" title="Desestimar / Eliminar"><i class="fas fa-trash-alt"></i></button>';
                }
            }

            // En Pendiente de Aprobación (4): confirmar (legal/admin) o eliminar
            if ($estado_actual === 4) {
                if (isset($funciones_permitidas['confirmar'])) {
                    $botones[] = '<button type="button" class="btn btn-sm btn-success btn-confirmar-modal me-1" data-id="' . $row['empresa_evento_id'] . '" data-tipo="' . $row['tipo_codigo'] . '" title="Confirmar y Consolidar Evento"><i class="fas fa-check-double me-1"></i>Confirmar</button>';
                }
                if (isset($funciones_permitidas['eliminar'])) {
                    $botones[] = '<button type="button" class="btn btn-sm btn-outline-danger btn-transicionar me-1" data-id="' . $row['empresa_evento_id'] . '" data-estado="6" title="Desestimar"><i class="fas fa-times"></i></button>';
                }
            }

            // Ver detalle siempre disponible
            $botones[] = '<button type="button" class="btn btn-sm btn-outline-info btn-ver me-1" data-id="' . $row['empresa_evento_id'] . '" title="Ver detalle"><i class="fas fa-eye"></i></button>';

            $row['acciones'] = '<div class="btn-group btn-group-sm" role="group">' . implode('', $botones) . '</div>';
            $data[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'obtener_combos':
        $tipos = empresas_eventos_tipos_combo($conexion);
        $empresas = obtener_empresas_combo($conexion);
        echo json_encode([
            'success' => true,
            'data' => [
                'tipos' => $tipos,
                'empresas' => $empresas,
                'permite_agregar' => isset($funciones_permitidas['agregar'])
            ]
        ]);
        break;

    case 'obtener_evento':
        $id = intval($_GET['evento_id'] ?? 0);
        $evento = empresas_eventos_obtener_por_id($conexion, $id);
        if ($evento) {
            echo json_encode(['success' => true, 'data' => $evento]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Evento no encontrado.']);
        }
        break;

    case 'guardar':
        $evento_id = intval($_POST['empresa_evento_id'] ?? 0);
        $datos = [
            'empresa_evento_tipo_id' => intval($_POST['empresa_evento_tipo_id'] ?? 0),
            'empresa_origen_id' => intval($_POST['empresa_origen_id'] ?? 0),
            'empresa_destino_id' => !empty($_POST['empresa_destino_id']) ? intval($_POST['empresa_destino_id']) : null,
            'fecha' => trim($_POST['fecha'] ?? ''),
            'observaciones' => trim($_POST['observaciones'] ?? '')
        ];

        if ($evento_id > 0) {
            if (!isset($funciones_permitidas['editar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para editar eventos.']);
                exit;
            }
            $res = empresas_eventos_actualizar($conexion, $evento_id, $datos, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['agregar'])) {
                echo json_encode(['success' => false, 'message' => 'Permiso denegado para registrar eventos.']);
                exit;
            }
            $res = empresas_eventos_crear($conexion, $datos, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'transicionar':
        $evento_id = intval($_POST['evento_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);
        $nueva_razon_social = trim($_POST['nueva_razon_social'] ?? '');

        if ($nuevo_estado === 4 && !isset($funciones_permitidas['enviar_aprobacion'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para enviar a aprobación.']);
            exit;
        }
        if ($nuevo_estado === 5 && !isset($funciones_permitidas['confirmar'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para confirmar eventos societarios.']);
            exit;
        }
        if ($nuevo_estado === 6 && !isset($funciones_permitidas['eliminar'])) {
            echo json_encode(['success' => false, 'message' => 'Permiso denegado para desestimar eventos.']);
            exit;
        }

        $res = empresas_eventos_transicionar($conexion, $evento_id, $nuevo_estado, $usuario_id, $nueva_razon_social);
        echo json_encode($res);
        break;

    case 'obtener_historial_nombres':
        $empresa_id = intval($_GET['empresa_id'] ?? 0);
        $historial = empresas_nombres_listar($conexion, $empresa_id);
        echo json_encode(['success' => true, 'data' => $historial]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida o no soportada.']);
        break;
}
