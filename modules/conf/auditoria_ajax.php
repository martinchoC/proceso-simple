<?php
/**
 * modules/conf/auditoria_ajax.php
 * Controlador AJAX para la gestión y visualización de logs de auditoría.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
// Acceso a la página Logs de Auditoría (142; solo perfiles de administración)
$ctx = ajax_guard(['pagina_idx' => 142]);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/auditoria_model.php';

header('Content-Type: application/json; charset=utf-8');

$accion = $_REQUEST['accion'] ?? '';
$anio = filter_input(INPUT_POST, 'anio', FILTER_VALIDATE_INT) 
        ?: filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT) 
        ?: (int)date('Y');

try {
    switch ($accion) {
        case 'catalogos':
            $catalogos = audModelObtenerCatalogosFiltros($conexion, $anio);
            $anios = audModelObtenerAnios($conexion);
            echo json_encode([
                'success' => true,
                'anios' => $anios,
                'usuarios' => $catalogos['usuarios'],
                'empresas' => $catalogos['empresas']
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'kpis':
            $kpis = audModelObtenerKPIs($conexion, $anio);
            echo json_encode([
                'success' => true,
                'kpis' => $kpis
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar':
            $filtros = [
                'usuario_id'  => filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT),
                'empresa_id'  => filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT),
                'accion'      => trim((string)($_REQUEST['filtro_accion'] ?? '')),
                'fecha_desde' => trim((string)($_REQUEST['fecha_desde'] ?? '')),
                'fecha_hasta' => trim((string)($_REQUEST['fecha_hasta'] ?? '')),
                'busqueda'    => trim((string)($_REQUEST['busqueda'] ?? '')),
            ];

            $registros = audModelListar($conexion, $anio, $filtros);
            echo json_encode([
                'success' => true,
                'data' => $registros
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'detalle':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Identificador de log no válido']);
                break;
            }

            $detalle = audModelObtenerDetalle($conexion, $anio, $id);
            if (!$detalle) {
                echo json_encode(['success' => false, 'error' => 'Registro no encontrado en el año seleccionado']);
                break;
            }

            echo json_encode([
                'success' => true,
                'data' => $detalle
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'historia_registro':
            $tablaAfectada = trim((string)($_REQUEST['tabla'] ?? ''));
            $registroId = trim((string)($_REQUEST['registro_id'] ?? ''));
            if ($tablaAfectada === '' || $registroId === '') {
                echo json_encode(['success' => false, 'error' => 'Debe indicar la tabla y el ID del registro para consultar su historial.']);
                break;
            }

            $todosLosAnios = !empty($_REQUEST['todos_anios']) && $_REQUEST['todos_anios'] === '1';
            $anioFiltro = $todosLosAnios ? null : $anio;

            $historia = audModelObtenerHistoriaRegistro($conexion, $tablaAfectada, $registroId, $anioFiltro);
            $tablaId = auditoria_obtener_tabla_id($conexion, $tablaAfectada);
            echo json_encode([
                'success' => true,
                'tabla' => $tablaAfectada,
                'tabla_id' => $tablaId,
                'registro_id' => $registroId,
                'total' => count($historia),
                'data' => $historia
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

