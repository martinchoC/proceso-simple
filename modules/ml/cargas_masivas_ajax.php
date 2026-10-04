<?php
/**
 * Controlador AJAX: Cargas Masivas (Página 362 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cargas_masivas_ajax.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/cargas_masivas_model.php';
require_once __DIR__ . '/cargas_masivas_service.php';

$conexion = $conn;

$accion = $_REQUEST['accion'] ?? '';

// Descarga directa de archivo de plantilla
if ($accion === 'descargar_plantilla') {
    $tipo = $_GET['tipo'] ?? 'NORMAS';
    try {
        $plantilla = cargas_generar_plantilla($tipo);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $plantilla['filename'] . '"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
        echo $plantilla['content'];
        exit;
    } catch (Throwable $e) {
        die("Error al descargar plantilla: " . $e->getMessage());
    }
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$empresa_idx = isset($_REQUEST['empresa_idx']) ? intval($_REQUEST['empresa_idx']) : (isset($_SESSION['empresa_id']) ? intval($_SESSION['empresa_id']) : 0);
$pagina_idx = 362; // Cargas Masivas

if ($empresa_idx <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa no especificada o inválida.']);
    exit;
}

if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_idx, $pagina_idx)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado a esta página.']);
    exit;
}

// Permisos en página 362
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

try {
    switch ($accion) {
        case 'combos':
            $res_t = mysqli_query($conexion, "SELECT carga_tipo_id, codigo, nombre FROM ml__cargas_tipos ORDER BY carga_tipo_id ASC");
            $tipos = [];
            while ($r = mysqli_fetch_assoc($res_t)) {
                $tipos[] = $r;
            }

            echo json_encode([
                'success' => true,
                'tipos' => $tipos,
                'funciones_permitidas' => $funciones_permitidas
            ]);
            break;

        case 'listar_cargas':
            if (!in_array('consultar_cargas', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para consultar cargas masivas.']);
                exit;
            }

            $filtros = [
                'carga_tipo_id' => isset($_GET['filtro_tipo_id']) && $_GET['filtro_tipo_id'] !== '' ? (int)$_GET['filtro_tipo_id'] : null,
                'estado_id' => isset($_GET['filtro_estado_id']) && $_GET['filtro_estado_id'] !== '' ? (int)$_GET['filtro_estado_id'] : null,
            ];

            $lista = cargas_listar($conexion, $empresa_idx, $filtros);
            echo json_encode([
                'success' => true, 
                'data' => $lista,
                'permisos' => $funciones_permitidas
            ]);
            break;

        case 'obtener_filas':
            $carga_id = isset($_GET['carga_id']) ? (int)$_GET['carga_id'] : 0;
            $filas = cargas_obtener_filas($conexion, $carga_id, $empresa_idx);
            echo json_encode(['success' => true, 'filas' => $filas]);
            break;

        case 'subir_validar':
            if (!in_array('subir_validar_carga', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para subir o validar cargas masivas.']);
                exit;
            }

            $carga_tipo_id = isset($_POST['carga_tipo_id']) ? (int)$_POST['carga_tipo_id'] : 0;
            if ($carga_tipo_id <= 0 || empty($_FILES['archivo']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar el tipo de carga y adjuntar un archivo CSV.']);
                exit;
            }

            $nombre_original = $_FILES['archivo']['name'];
            $tmp_path = $_FILES['archivo']['tmp_name'];

            $res = cargas_procesar_archivo($conexion, $carga_tipo_id, $tmp_path, $nombre_original, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'confirmar_carga':
            if (!in_array('confirmar_carga', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para confirmar cargas masivas.']);
                exit;
            }

            $carga_id = isset($_POST['carga_id']) ? (int)$_POST['carga_id'] : 0;
            if ($carga_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de carga inválido.']);
                exit;
            }

            $res = cargas_confirmar($conexion, $carga_id, $empresa_idx, $usuario_id);
            echo json_encode($res);
            break;

        case 'eliminar_carga':
            if (!in_array('eliminar_carga', $funciones_permitidas, true)) {
                echo json_encode(['success' => false, 'message' => 'No tiene permiso para eliminar lotes de carga.']);
                exit;
            }

            $carga_id = isset($_POST['carga_id']) ? (int)$_POST['carga_id'] : 0;
            if ($carga_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de carga inválido.']);
                exit;
            }

            $res = cargas_eliminar($conexion, $carga_id, $empresa_idx);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida o no especificada.']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
