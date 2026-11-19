<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
// Lo usan la página de cuenta corriente y la solapa "Cuenta Corriente" de clientes.php.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['clientes_ctacte.php', 'clientes.php']]);
/**
 * clientes_ctacte_ajax.php
 *
 * Dispatcher de la cuenta corriente de clientes (solo lectura, salvo el envío por correo).
 * Acciones: saldos, buscar_clientes, cliente, resumen, movimientos, pendientes,
 *           comprobantes, obtener_comprobante, emails_cliente, enviar_email (CSRF).
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

require_once __DIR__ . '/clientes_ctacte_model.php';
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
    global $conexion, $empresa_id, $usuario_id;
    $entidad_id = intval($_REQUEST['entidad_id'] ?? 0);
    if ($entidad_id <= 0) {
        ctacteResponder(['success' => false, 'data' => null, 'message' => 'Seleccione un cliente.'], 400);
    }
    vcar_exigir_cliente($conexion, $empresa_id, $usuario_id, $entidad_id);
    return $entidad_id;
}

try {
    switch ($accion) {
        case 'saldos':
            $resultado = ctacteSaldosClientes($conexion, $empresa_id, [
                'texto'          => $_REQUEST['texto'] ?? '',
                'solo_con_saldo' => !empty($_REQUEST['solo_con_saldo']),
                'solo_vencidos'  => !empty($_REQUEST['solo_vencidos']),
                'usuario_id'     => $usuario_id,
            ]);
            ctacteResponder([
                'success' => true,
                'data'    => $resultado['filas'],
                'message' => $resultado['configurado'] ? '' : 'La empresa no tiene configurada la matriz de cancelaciones del recibo de cobro (RCX).',
            ]);
            break;

        case 'buscar_clientes':
            ctacteResponder(['success' => true, 'data' => cconsBuscarEntidades($conexion, $_REQUEST['q'] ?? '', true, 30, $empresa_id, $usuario_id), 'message' => '']);
            break;

        case 'cliente':
            $datos = ctacteDatosCliente($conexion, ctacteEntidadParam());
            if (!$datos) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Cliente no encontrado.'], 404);
            }
            ctacteResponder(['success' => true, 'data' => $datos, 'message' => '']);
            break;

        case 'resumen':
            $resumen = ctacteResumen($conexion, $empresa_id, ctacteEntidadParam());
            ctacteResponder([
                'success' => true,
                'data'    => $resumen,
                'message' => $resumen['configurado'] ? '' : 'La empresa no tiene configurada la matriz de cancelaciones del recibo de cobro (RCX).',
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
            // Todos los comprobantes emitidos al cliente (cualquier tipo y estado), con accesos por sucursal
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
            // Solo comprobantes del cliente consultado: desde esta pantalla no se ve cualquier comprobante
            $detalle = cconsObtener($conexion, $empresa_id, $usuario_id, $comprobante_id, ctacteEntidadParam());
            if (!$detalle) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Comprobante no encontrado.'], 404);
            }
            ctacteResponder(['success' => true, 'data' => $detalle, 'message' => '']);
            break;

        case 'emails_cliente':
            // Correos de las sucursales del cliente, para precargar el destinatario del envío
            ctacteResponder(['success' => true, 'data' => ctacteEmailsCliente($conexion, $empresa_id, ctacteEntidadParam()), 'message' => '']);
            break;

        case 'enviar_email':
            // Detalle de una solapa por correo: lo arma el servidor con los mismos datos de la pantalla
            require_once __DIR__ . '/../../core/csrf.php';
            require_once __DIR__ . '/../../core/correo.php';
            csrf_exigir();
            $entidad_id = ctacteEntidadParam();
            if (!ctacteEntidadDeEmpresa($conexion, $empresa_id, $entidad_id)) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Cliente no encontrado.'], 404);
            }
            $solapa = $_POST['solapa'] ?? '';
            if (!in_array($solapa, ['deuda', 'cta', 'comp'], true)) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Solapa inválida.'], 400);
            }
            $para = trim((string) ($_POST['para'] ?? ''));
            if (!correo_email_valido($para)) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Ingresá un correo válido.'], 400);
            }
            $mensaje = mb_substr((string) ($_POST['mensaje'] ?? ''), 0, 2000);
            $correo = ctacteCorreoSolapa($conexion, $empresa_id, $usuario_id, $entidad_id, $solapa,
                                         ctacteFechaParam('desde'), ctacteFechaParam('hasta'), $mensaje);
            error_log("=== enviar_email ctacte cliente $entidad_id solapa $solapa a $para (usuario $usuario_id) ===");
            $envio = correo_enviar($para, $correo['asunto'], $correo['html']);
            ctacteResponder(['success' => $envio['success'], 'data' => null, 'message' => $envio['message']]);
            break;

        case 'aplicar_saldo_favor':
            require_once __DIR__ . '/../../core/csrf.php';
            csrf_exigir();
            $entidad_id = ctacteEntidadParam();
            if (!ctacteEntidadDeEmpresa($conexion, $empresa_id, $entidad_id)) {
                ctacteResponder(['success' => false, 'data' => null, 'message' => 'Cliente no encontrado.'], 404);
            }
            $comp_origen_id = intval($_POST['comp_origen_id'] ?? 0);
            $comp_destino_id = intval($_POST['comp_destino_id'] ?? 0);
            $importe = floatval($_POST['importe'] ?? 0);
            $res = ctacteAplicarSaldoFavor($conexion, $empresa_id, $usuario_id, $entidad_id, $comp_origen_id, $comp_destino_id, $importe);
            $msg = 'Saldo a favor aplicado ($ ' . number_format($res['importe_aplicado'], 2, ',', '.') . ') · Recibo en $0 N° ' . $res['recibo_numero'];
            if (!empty($res['asiento_id'])) {
                $msg .= ' · Asiento #' . $res['asiento_id'];
            }
            ctacteResponder([
                'success' => true,
                'data'    => $res,
                'message' => $msg,
            ]);
            break;

        default:
            ctacteResponder(['success' => false, 'data' => null, 'message' => 'Acción no definida.'], 400);
    }
} catch (InvalidArgumentException $e) {
    ctacteResponder(['success' => false, 'data' => null, 'message' => $e->getMessage()], 403);
} catch (RuntimeException $e) {
    ctacteResponder(['success' => false, 'data' => null, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log("=== ERROR clientes_ctacte_ajax [$accion]: " . $e->getMessage());
    ctacteResponder(['success' => false, 'data' => null, 'message' => 'Error al consultar la cuenta corriente.'], 500);
}
