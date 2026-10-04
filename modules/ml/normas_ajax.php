<?php
/**
 * Controlador AJAX para Matriz de Normas y Versiones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/normas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/normas_catalogos_model.php';
require_once __DIR__ . '/normas_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 313; // Matriz de Normas

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 313
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
        $combos = normas_combos($conexion);
        echo json_encode([
            'success' => true,
            'data' => $combos,
            'permisos' => [
                'permite_crear' => isset($funciones_permitidas['crear_norma']),
                'permite_enviar_revision' => isset($funciones_permitidas['enviar_revision_norma']),
                'permite_aprobar' => isset($funciones_permitidas['aprobar_norma']),
                'permite_derogar' => isset($funciones_permitidas['derogar_norma']),
                'permite_version' => isset($funciones_permitidas['agregar_version_norma']),
                'permite_relacion' => isset($funciones_permitidas['vincular_relacion'])
            ]
        ]);
        break;

    case 'listar':
        $filtros = [
            'norma_tipo_id' => $_GET['filtro_tipo'] ?? '',
            'organismo_id' => $_GET['filtro_organismo'] ?? '',
            'ambito_tipo_id' => $_GET['filtro_ambito'] ?? '',
            'tabla_estado_registro_id' => $_GET['filtro_estado'] ?? '',
            'confidencial' => $_GET['filtro_confidencial'] ?? '',
            'solo_vigentes' => !empty($_GET['solo_vigentes'])
        ];
        $lista = normas_listar($conexion, $filtros);

        // Control de permisos fila a fila
        foreach ($lista as &$item) {
            $estado = intval($item['tabla_estado_registro_id']);
            $item['permite_editar'] = isset($funciones_permitidas['crear_norma']) && $estado === 2045; // Solo borrador
            $item['permite_enviar_revision'] = isset($funciones_permitidas['enviar_revision_norma']) && $estado === 2045;
            $item['permite_aprobar'] = isset($funciones_permitidas['aprobar_norma']) && $estado === 2046;
            $item['permite_derogar'] = isset($funciones_permitidas['derogar_norma']) && $estado === 2047;
            $item['permite_version'] = isset($funciones_permitidas['agregar_version_norma']);
            $item['permite_relacion'] = isset($funciones_permitidas['vincular_relacion']);
        }
        unset($item);

        echo json_encode([
            'success' => true,
            'data' => $lista,
            'permite_crear' => isset($funciones_permitidas['crear_norma'])
        ]);
        break;

    case 'obtener_norma':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $norma = normas_obtener_por_id($conexion, $norma_id);
        if ($norma) {
            // Auditoría si es confidencial
            if (!empty($norma['confidencial'])) {
                mysqli_query($conexion, "INSERT INTO ml__auditoria_accesos (usuario_id, objeto_tabla, objeto_id, accedido_en, motivo) 
                                         VALUES ({$usuario_id}, 'ml__normas', {$norma_id}, NOW(), 'Consulta de detalle de norma confidencial')");
            }
            echo json_encode(['success' => true, 'data' => $norma]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Norma no encontrada.']);
        }
        break;

    case 'guardar_norma':
        $norma_id = intval($_POST['norma_id'] ?? 0);
        if ($norma_id > 0) {
            if (!isset($funciones_permitidas['crear_norma'])) {
                echo json_encode(['success' => false, 'message' => 'No tiene permisos para modificar la norma.']);
                exit;
            }
            $res = normas_actualizar($conexion, $norma_id, $_POST, $usuario_id);
        } else {
            if (!isset($funciones_permitidas['crear_norma'])) {
                echo json_encode(['success' => false, 'message' => 'No tiene permisos para dar de alta normas.']);
                exit;
            }
            $res = normas_crear($conexion, $_POST, $usuario_id);
        }
        echo json_encode($res);
        break;

    case 'cambiar_estado':
        $norma_id = intval($_POST['norma_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 0);

        if ($nuevo_estado === 2046 && !isset($funciones_permitidas['enviar_revision_norma'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para enviar a revisión.']);
            exit;
        }
        if ($nuevo_estado === 2047 && !isset($funciones_permitidas['aprobar_norma'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para aprobar normas.']);
            exit;
        }
        if ($nuevo_estado === 2048 && !isset($funciones_permitidas['derogar_norma'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para derogar normas.']);
            exit;
        }

        $res = normas_cambiar_estado($conexion, $norma_id, $nuevo_estado, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_versiones':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $versiones = normas_versiones_listar($conexion, $norma_id);
        echo json_encode(['success' => true, 'data' => $versiones]);
        break;

    case 'guardar_version':
        if (!isset($funciones_permitidas['agregar_version_norma'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para registrar versiones.']);
            exit;
        }
        $norma_id = intval($_POST['norma_id'] ?? 0);
        $res = normas_versiones_crear($conexion, $norma_id, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'listar_relaciones':
        $norma_id = intval($_GET['norma_id'] ?? 0);
        $relaciones = normas_relaciones_listar($conexion, $norma_id);
        echo json_encode(['success' => true, 'data' => $relaciones]);
        break;

    case 'guardar_relacion':
        if (!isset($funciones_permitidas['vincular_relacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para vincular relaciones.']);
            exit;
        }
        $res = normas_relaciones_crear($conexion, $_POST, $usuario_id);
        echo json_encode($res);
        break;

    case 'eliminar_relacion':
        if (!isset($funciones_permitidas['vincular_relacion'])) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar relaciones.']);
            exit;
        }
        $rel_id = intval($_POST['norma_relacion_id'] ?? 0);
        $res = normas_relaciones_eliminar($conexion, $rel_id);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Acción '{$accion}' no reconocida."]);
        break;
}
