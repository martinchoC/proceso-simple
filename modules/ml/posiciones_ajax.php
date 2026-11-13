<?php
/**
 * Controlador AJAX para Posiciones y Organigrama (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/posiciones_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/posiciones_model.php';

$conexion = $conn;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 317; // Posiciones y Organigrama

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

$accion = $_REQUEST['accion'] ?? '';

try {
    switch ($accion) {
        case 'listar':
            $filtros = [];
            if (!empty($_GET['area'])) {
                $filtros['area'] = trim($_GET['area']);
            }
            if (isset($_GET['estado_id']) && $_GET['estado_id'] !== '') {
                $filtros['estado_id'] = intval($_GET['estado_id']);
            }
            if (!empty($_GET['buscar'])) {
                $filtros['buscar'] = trim($_GET['buscar']);
            }

            $datos = posiciones_listar($conexion, $empresa_idx, $filtros);
            echo json_encode(['success' => true, 'data' => $datos]);
            break;

        case 'obtener':
            $posicion_id = intval($_GET['posicion_id'] ?? 0);
            if ($posicion_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de posición inválido.']);
                exit;
            }
            $pos = posicion_obtener($conexion, $posicion_id, $empresa_idx);
            if (!$pos) {
                echo json_encode(['success' => false, 'message' => 'Posición no encontrada.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $pos]);
            break;

        case 'guardar_posicion':
            $datos = [
                'posicion_id'          => intval($_POST['posicion_id'] ?? 0),
                'codigo'               => trim($_POST['codigo'] ?? ''),
                'nombre'               => trim($_POST['nombre'] ?? ''),
                'area_departamento'    => trim($_POST['area_departamento'] ?? ''),
                'descripcion'          => trim($_POST['descripcion'] ?? ''),
                'posicion_superior_id' => !empty($_POST['posicion_superior_id']) ? intval($_POST['posicion_superior_id']) : null,
            ];
            $res = posicion_guardar($conexion, $empresa_idx, $datos, $usuario_id);
            echo json_encode($res);
            break;

        case 'asignar_usuario':
            $posicion_id = intval($_POST['posicion_id'] ?? 0);
            $usuario_asignado_id = intval($_POST['usuario_id'] ?? 0);
            $vigente_desde = trim($_POST['vigente_desde'] ?? date('Y-m-d'));
            $es_titular = intval($_POST['es_titular'] ?? 1);

            $res = posicion_asignar_usuario($conexion, $posicion_id, $usuario_asignado_id, $vigente_desde, $es_titular, $usuario_id);
            echo json_encode($res);
            break;

        case 'finalizar_ocupacion':
            $usuario_posicion_id = intval($_POST['usuario_posicion_id'] ?? 0);
            $vigente_hasta = trim($_POST['vigente_hasta'] ?? date('Y-m-d'));

            $res = posicion_finalizar_ocupacion($conexion, $usuario_posicion_id, $vigente_hasta, $usuario_id);
            echo json_encode($res);
            break;

        case 'inactivar':
            $posicion_id = intval($_POST['posicion_id'] ?? 0);
            $sql = "UPDATE ml__posiciones SET tabla_estado_registro_id = 2, actualizado_por = ? WHERE posicion_id = ? AND empresa_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $posicion_id, $empresa_idx);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Posición inactivada correctamente.' : 'Error al inactivar.']);
            break;

        case 'activar':
            $posicion_id = intval($_POST['posicion_id'] ?? 0);
            $sql = "UPDATE ml__posiciones SET tabla_estado_registro_id = 1, actualizado_por = ? WHERE posicion_id = ? AND empresa_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $posicion_id, $empresa_idx);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => $ok, 'message' => $ok ? 'Posición activada con éxito.' : 'Error al activar.']);
            break;

        case 'obtener_arbol':
            $arbol = organigrama_obtener_arbol($conexion, $empresa_idx);
            echo json_encode(['success' => true, 'data' => $arbol]);
            break;

        case 'consultar_supervisor_usuario':
            $target_usuario_id = intval($_GET['usuario_id'] ?? 0);
            $info = usuario_obtener_supervisor($conexion, $target_usuario_id, $empresa_idx);
            echo json_encode(['success' => true, 'data' => $info]);
            break;

        case 'listar_usuarios_candidatos':
            $sql = "SELECT usuario_id, usuario_nombre, usuario, email 
                    FROM conf__usuarios 
                    WHERE tabla_estado_registro_id = 1 
                    ORDER BY usuario_nombre ASC";
            $res = mysqli_query($conexion, $sql);
            $usuarios = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $usuarios[] = $r;
            }
            echo json_encode(['success' => true, 'data' => $usuarios]);
            break;

        case 'listar_areas':
            $sql = "SELECT DISTINCT area_departamento 
                    FROM ml__posiciones 
                    WHERE empresa_id = ? AND area_departamento != '' 
                    ORDER BY area_departamento ASC";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $areas = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $areas[] = $r['area_departamento'];
            }
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'data' => $areas]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no soportada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
