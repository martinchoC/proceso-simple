<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
// Lo usan la página de cuenta corriente y la solapa "Cuenta Corriente" de proveedores.php.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['proveedores_ctacte.php', 'proveedores.php']]);
/**
 * proveedores_ctacte_ajax.php
 *
 * Dispatcher de la cuenta corriente de proveedores (solo lectura).
 * Acciones: saldos, buscar_proveedores, proveedor, resumen, movimientos, pendientes,
 *           comprobantes, obtener_comprobante.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

function ctacteResponder(array $data, int $http = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/proveedores_ctacte_model.php';
require_once __DIR__ . '/comprobantes_consulta_model.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

/** Fecha Y-m-d opcional del request; corta con 400 si viene mal formada. */
function ctacteFechaParam($clave)
{
    $valor = trim((string) ($_REQUEST[$clave] ?? ''));
    if ($valor === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        ctacteResponder(['success' => false, 'data' => null, 'message' => 'Fecha inválida.'], 400);
    }
    return $valor;
}

/** entidad_id obligatorio del request. */
function ctacteEntidadParam()
{
    $entidad_id = intval($_REQUEST['entidad_id'] ?? 0);
    if ($entidad_id <= 0) {
        ctacteResponder(['success' => false, 'data' => null, 'message' => 'Seleccione un proveedor.'], 400);
    }
    return $entidad_id;
}

try {
    switch ($accion) {
        case 'saldos':
            $resultado = ctacteSaldosProveedores($conexion, $empresa_id, [
                'texto'          => $_REQUEST['texto'] ?? '',
                'solo_con_saldo' => !empty($_REQUEST['solo_con_saldo']),
                'solo_vencidos'  => !empty($_REQUEST['solo_vencidos']),
            ]);
            ctacteResponder([
                'success' => true,
                'data'    => $resultado['filas'],
                'message' => $resultado['configurado'] ? '' : 'La empresa no tiene configurada la matriz de cancelaciones de la orden de pago (OP).',
            ]);
            break;

        case 'buscar_proveedores':
            ctacteResponder(['success' => true, 'data' => ctacteBuscarProveedores($conexion, $_REQUEST['q'] ?? ''), 'message' => '']);
            break;

        case 'proveedor':
            $datos = ctacteDatosProveedor($conexion, ctacteEntidadParam());
            if (!$datos) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Proveedor no encontrado.'], 404);
            }
            ctacteResponder(['success' => true, 'data' => $datos, 'message' => '']);
            break;

        case 'resumen':
            $resumen = ctacteResumen($conexion, $empresa_id, ctacteEntidadParam());
            ctacteResponder([
                'success' => true,
                'data'    => $resumen,
                'message' => $resumen['configurado'] ? '' : 'La empresa no tiene configurada la matriz de cancelaciones de la orden de pago (OP).',
            ]);
            break;

        case 'movimientos':
            $entidad_id = ctacteEntidadParam();
            $datos = ctacteMovimientos($conexion, $empresa_id, $entidad_id, ctacteFechaParam('desde'), ctacteFechaParam('hasta'));
            ctacteResponder(['success' => true, 'data' => $datos, 'message' => '']);
            break;

        case 'pendientes':
            ctacteResponder(['success' => true, 'data' => ctactePendientes($conexion, $empresa_id, ctacteEntidadParam()), 'message' => '']);
            break;

        case 'comprobantes':
            // Todos los comprobantes del proveedor (cualquier tipo y estado), con accesos por sucursal
            $resultado = cconsListar($conexion, $empresa_id, $usuario_id, [
                'entidad_id' => ctacteEntidadParam(),
                'desde'      => ctacteFechaParam('desde'),
                'hasta'      => ctacteFechaParam('hasta'),
                'grupo_id'   => $_REQUEST['grupo_id'] ?? null,
            ]);
            ctacteResponder([
                'success'  => true,
                'data'     => $resultado['filas'],
                'truncado' => $resultado['truncado'],
                'message'  => $resultado['truncado'] ? "Se muestran los primeros {$resultado['limite']} comprobantes." : '',
            ]);
            break;

        case 'obtener_comprobante':
            $comprobante_id = intval($_REQUEST['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Comprobante inválido.'], 400);
            }
            // Solo comprobantes del proveedor consultado
            $detalle = cconsObtener($conexion, $empresa_id, $usuario_id, $comprobante_id, ctacteEntidadParam());
            if (!$detalle) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Comprobante no encontrado.'], 404);
            }
            ctacteResponder(['success' => true, 'data' => $detalle, 'message' => '']);
            break;

        default:
            ctacteResponder(['success' => false, 'data' => null, 'message' => 'Acción no definida.'], 400);
    }
} catch (Throwable $e) {
    error_log("=== ERROR proveedores_ctacte_ajax [$accion]: " . $e->getMessage());
    ctacteResponder(['success' => false, 'data' => null, 'message' => 'Error al consultar la cuenta corriente.'], 500);
}
