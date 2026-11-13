<?php
/**
 * Conciliación bancaria: respuesta y controles comunes de los bancos_*_ajax.php. El _ajax.php ya llamó a
 * ajax_guard() con su pagina_idx fijo y abrió el buffer (ob_start) antes de incluir los modelos: acá solo se valida
 * la función (usuario_puede_funcion) y el CSRF de las escrituras. Respuesta estándar: {success, data, message}.
 * Errores: RuntimeException = mensaje para el usuario; error de base (mysqli_sql_exception) u otro = se loguea y se
 * responde un mensaje genérico.
 */

require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../csrf.php';

function bco_responder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if ($http !== 200) http_response_code($http);
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Escritura: exige CSRF y la función de la página. */
function bco_exigir(mysqli $c, array $ctx, int $pagina_id, string $accion_js): void
{
    csrf_exigir();
    if (!usuario_puede_funcion($c, intval($ctx['usuario_id']), intval($ctx['empresa_id']), $pagina_id, $accion_js)) {
        bco_responder(false, null, 'No tenés permiso para esta acción.', 403);
    }
}

/** Exige además funciones de otra página (Facturas de Proveedores para Gastos Bancarios, Cobranzas para los recibos de Conciliación). */
function bco_exigir_funciones(mysqli $c, array $ctx, int $pagina_id, array $acciones, string $que, string $pagina_nombre = 'Facturas de Proveedores'): void
{
    foreach ($acciones as $a) {
        if (!usuario_puede_funcion($c, intval($ctx['usuario_id']), intval($ctx['empresa_id']), $pagina_id, $a)) {
            bco_responder(false, null, "No tenés permiso para $que (función \"$a\" de $pagina_nombre).", 403);
        }
    }
}

/** Corre el despachador y convierte las excepciones en respuesta JSON. */
function bco_despachar(string $pagina, callable $fn): void
{
    if (ob_get_level() === 0) ob_start();
    $accion = (string)($_GET['accion'] ?? $_POST['accion'] ?? '');
    if ($accion === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && intval($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        bco_responder(false, null, 'El archivo es demasiado grande.', 413);   // superó post_max_size: PHP vació $_POST y $_FILES
    }
    try {
        $fn($accion);
        bco_responder(false, null, 'Acción no válida.', 400);
    } catch (mysqli_sql_exception $e) {
        error_log("ERROR SQL $pagina ($accion): " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        bco_responder(false, null, 'No se pudo completar la operación (error de base de datos). Si se repite, avisá a soporte.', 500);
    } catch (RuntimeException $e) {
        bco_responder(false, null, $e->getMessage());
    } catch (Throwable $e) {
        error_log("ERROR $pagina ($accion): " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        bco_responder(false, null, 'Error interno: avisá a soporte.', 500);
    }
}
