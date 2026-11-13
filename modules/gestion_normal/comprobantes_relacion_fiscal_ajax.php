<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['comprobantes_relacion_fiscal.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "comprobantes_relacion_fiscal_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 110);
// Solo una página de este módulo (si no, la de comprobantes_relacion_fiscal.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'comprobantes_relacion_fiscal.php');

header('Content-Type: application/json; charset=utf-8');

if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $registros = obtenerComprobantesRelacionFiscal($conexion, $pagina_idx);
            echo json_encode($registros, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['relacion_fiscal_id'] ?? $_POST['relacion_fiscal_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $registro = obtenerComprobanteRelacionFiscalPorId($conexion, $id);
            if ($registro) {
                echo json_encode($registro, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Registro no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_catalogos':
            $condiciones = obtenerCondicionesFiscales($conexion);
            $comprobantes = obtenerComprobantesFiscales($conexion);
            echo json_encode([
                'condiciones' => $condiciones,
                'comprobantes' => $comprobantes
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            $data = [
                'condicion_fiscal_emisor_id' => intval($_POST['condicion_fiscal_emisor_id'] ?? 0),
                'condicion_fiscal_receptor_id' => intval($_POST['condicion_fiscal_receptor_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'discrimina_iva' => intval($_POST['discrimina_iva'] ?? 0)
            ];
            $resultado = agregarComprobanteRelacionFiscal($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['relacion_fiscal_id'] ?? 0);
            $data = [
                'condicion_fiscal_emisor_id' => intval($_POST['condicion_fiscal_emisor_id'] ?? 0),
                'condicion_fiscal_receptor_id' => intval($_POST['condicion_fiscal_receptor_id'] ?? 0),
                'comprobante_fiscal_id' => intval($_POST['comprobante_fiscal_id'] ?? 0),
                'discrimina_iva' => intval($_POST['discrimina_iva'] ?? 0)
            ];
            $resultado = editarComprobanteRelacionFiscal($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['relacion_fiscal_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            if (empty($id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $id, $accion_js, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
