<?php
/**
 * core/dashboard/dashboard_endpoint.php
 * Despachador de la pantalla de inicio. Lo incluyen modules/<gestion|gestion_normal>/inicio_ajax.php
 * después de ajax_guard(), con $ctx (sesión validada) y $modudo_idx fijos en el archivo.
 *
 * GET  layout | config | badges | widget (widget_id, forzar)
 * POST guardar (widgets, accesos) | restablecer      — exigen CSRF (header X-CSRF-Token)
 *
 * Respuesta: {success, data, message[, meta][, codigo]}. Los errores internos van a error_log y al
 * usuario le llega un mensaje genérico.
 */

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/dashboard_service.php';

/** Responde y corta. Descarta cualquier salida previa (warnings) para que el JSON llegue limpio. */
function dash_responder(array $cuerpo, int $http = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $json = json_encode($cuerpo + ['success' => true, 'data' => null, 'message' => ''],
                        JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    echo $json === false ? '{"success":false,"data":null,"message":"No se pudo armar la respuesta."}' : $json;
    exit;
}

$dash_accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
$dash_metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $dash_ctx = dash_contexto($conexion, (int) $ctx['usuario_id'], (int) $ctx['empresa_id'], (int) $modudo_idx);

    switch ($dash_accion) {
        case 'layout':
            dash_responder(['data' => dash_layout($conexion, $dash_ctx)]);

        case 'config':
            dash_responder(['data' => dash_config($conexion, $dash_ctx)]);

        case 'badges':
            dash_responder(['data' => dash_badges($conexion, $dash_ctx)]);

        case 'widget':
            $widget_id = (int) ($_GET['widget_id'] ?? 0);
            try {
                $r = dash_ejecutar_widget($conexion, $dash_ctx, $widget_id, !empty($_GET['forzar']));
            } catch (DashPermisoException $e) {
                throw $e;
            } catch (Throwable $e) {
                error_log("=== dashboard widget $widget_id empresa {$dash_ctx['empresa_id']}: " . $e->getMessage()
                          . ' @ ' . $e->getFile() . ':' . $e->getLine() . ' ===');
                dash_responder(['success' => false, 'codigo' => 'WIDGET_ERROR',
                                'message' => 'No se pudo cargar este indicador.']);
            }
            dash_responder(['data' => $r['data'], 'meta' => $r['meta']]);

        case 'guardar':
        case 'restablecer':
            if ($dash_metodo !== 'POST') {
                dash_responder(['success' => false, 'message' => 'Método no permitido.'], 405);
            }
            csrf_exigir();
            if ($dash_accion === 'restablecer') {
                dash_restablecer($conexion, $dash_ctx);
                dash_responder(['data' => dash_layout($conexion, $dash_ctx), 'message' => 'Se restableció la pantalla de inicio.']);
            }
            // El JS manda JSON en el cuerpo; se aceptan también campos de formulario con JSON adentro.
            $cuerpo = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($cuerpo)) {
                $cuerpo = [
                    'widgets' => json_decode((string) ($_POST['widgets'] ?? '[]'), true),
                    'accesos' => json_decode((string) ($_POST['accesos'] ?? '[]'), true),
                ];
            }
            $widgets = is_array($cuerpo['widgets'] ?? null) ? $cuerpo['widgets'] : [];
            $accesos = is_array($cuerpo['accesos'] ?? null) ? $cuerpo['accesos'] : [];
            dash_guardar($conexion, $dash_ctx, $widgets, $accesos);
            dash_responder(['message' => 'Guardado']);

        default:
            dash_responder(['success' => false, 'message' => 'Acción no válida.'], 400);
    }
} catch (DashValidacionException $e) {
    dash_responder(['success' => false, 'codigo' => 'VALIDACION', 'message' => $e->getMessage()], 400);
} catch (DashPermisoException $e) {
    dash_responder(['success' => false, 'codigo' => 'SIN_PERMISO', 'message' => $e->getMessage()], 403);
} catch (Throwable $e) {
    error_log("=== dashboard accion $dash_accion: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . ' ===');
    dash_responder(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
}
