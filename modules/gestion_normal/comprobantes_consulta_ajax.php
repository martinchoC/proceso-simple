<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['comprobantes_consulta.php']]);
/**
 * comprobantes_consulta_ajax.php
 *
 * Dispatcher de la consulta universal de comprobantes (solo lectura).
 * Acciones: maestros, listar, obtener, buscar_entidades.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

function cconsResponder(array $data, int $http = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/comprobantes_consulta_model.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'maestros':
            cconsResponder(['success' => true, 'data' => cconsMaestros($conexion, $empresa_id, $usuario_id), 'message' => '']);
            break;

        case 'listar':
            $filtros = [];
            foreach (['desde', 'hasta', 'grupo_id', 'tipo_id', 'sucursal_id', 'entidad_id', 'estado_id',
                      'moneda_id', 'pv', 'numero', 'texto', 'solo_con_saldo'] as $clave) {
                $filtros[$clave] = $_REQUEST[$clave] ?? null;
            }
            foreach (['desde', 'hasta'] as $clave) {
                if (!empty($filtros[$clave]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$clave])) {
                    cconsResponder(['success' => false, 'data' => null, 'message' => 'Fecha inválida.'], 400);
                }
            }
            $resultado = cconsListar($conexion, $empresa_id, $usuario_id, $filtros);
            cconsResponder([
                'success'  => true,
                'data'     => $resultado['filas'],
                'truncado' => $resultado['truncado'],
                'limite'   => $resultado['limite'],
                'message'  => $resultado['truncado']
                    ? "Se muestran los primeros {$resultado['limite']} comprobantes: acote los filtros."
                    : '',
            ]);
            break;

        case 'obtener':
            $comprobante_id = intval($_REQUEST['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) {
                cconsResponder(['success' => false, 'data' => null, 'message' => 'Comprobante inválido.'], 400);
            }
            $detalle = cconsObtener($conexion, $empresa_id, $usuario_id, $comprobante_id);
            if (!$detalle) {
                cconsResponder(['success' => false, 'data' => null, 'message' => 'Comprobante no encontrado.'], 404);
            }
            cconsResponder(['success' => true, 'data' => $detalle, 'message' => '']);
            break;

        case 'buscar_entidades':
            $lista = cconsBuscarEntidades($conexion, $_REQUEST['q'] ?? '', false);
            cconsResponder(['success' => true, 'data' => $lista, 'message' => '']);
            break;

        default:
            cconsResponder(['success' => false, 'data' => null, 'message' => 'Acción no definida.'], 400);
    }
} catch (Throwable $e) {
    error_log("=== ERROR comprobantes_consulta_ajax [$accion]: " . $e->getMessage());
    cconsResponder(['success' => false, 'data' => null, 'message' => 'Error al procesar la consulta.'], 500);
}
