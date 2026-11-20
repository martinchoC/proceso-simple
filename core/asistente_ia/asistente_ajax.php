<?php
/**
 * Asistente IA: despachador común de asistente_ia_ajax.php de gestion y gestion_normal (que fijan la página y validan sesión,
 * empresa y acceso con ajax_guard). Respuesta {success, data, message}. Las escrituras validan CSRF.
 */

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/asistente_service.php';

function asis_responder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function asis_despachar(mysqli $c, array $guard, int $pagina_idx, string $modulo_dir): void
{
    $accion = (string)($_GET['accion'] ?? $_POST['accion'] ?? '');
    if ($accion === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && intval($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        asis_responder(false, null, 'El archivo es demasiado grande.', 413);
    }
    try {
        $ctx = asis_contexto($c, $guard, $pagina_idx, $modulo_dir);
        fmt_usar_empresa($c, $ctx['empresa_id']);
        switch ($accion) {
            case 'estado':
                $e = asis_estado($c, $ctx['empresa_id']);
                asis_responder(true, ['disponible' => $e['disponible'], 'motivo' => $e['motivo'],
                                      'usos_hoy' => $e['usos_hoy'], 'tope' => $e['tope']]);
            case 'conversaciones':
                asis_responder(true, asis_conversaciones($ctx));
            case 'mensajes':
                $id = intval($_GET['conversacion_id'] ?? 0);
                asis_responder(true, asis_mensajes($ctx, $id) + ['conversacion_id' => $id]);
            case 'enviar':
                csrf_exigir();
                // La IA puede tardar: se libera la sesión para no trabar las otras pantallas del usuario
                session_write_close();
                @set_time_limit(300);
                $archivo = !empty($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $_FILES['archivo'] : null;
                asis_responder(true, asis_enviar($ctx, intval($_POST['conversacion_id'] ?? 0), (string)($_POST['texto'] ?? ''), $archivo));
            case 'confirmar':
                csrf_exigir();
                asis_responder(true, asis_confirmar_propuesta($ctx, intval($_POST['propuesta_id'] ?? 0), intval($_POST['punto_venta_id'] ?? 0)), 'Guardado en Borrador.');
            case 'sugerencias':
                asis_responder(true, asis_sugerencias($ctx));
            case 'accion_registro':
                // Botón de un pendiente de la bienvenida: devuelve el registro con su estado y acciones nuevas
                csrf_exigir();
                $clave = (string)($_POST['clave'] ?? '');
                $id = intval($_POST['id'] ?? 0);
                [$texto] = asis_ciclo_ejecutar_registro($ctx, $clave, $id, (string)($_POST['accion_ciclo'] ?? ''), (string)($_POST['motivo'] ?? ''));
                $r = asis_ciclo_leer($ctx, $clave, $id);
                asis_responder(true, $r ? $r + ['clave' => $clave, 'id' => $id, 'nombre' => ASIS_CICLOS[$clave]['nombre'],
                                                'acciones' => asis_ciclo_acciones($ctx, $clave, $r['estado_id'])] : null, $texto);
            case 'accion_ciclo':
                csrf_exigir();
                $r = asis_ciclo_ejecutar($ctx, intval($_POST['propuesta_id'] ?? 0), (string)($_POST['accion_ciclo'] ?? ''), (string)($_POST['motivo'] ?? ''));
                asis_responder(true, $r, (string)$r['mensaje_accion']);
            case 'descartar':
                csrf_exigir();
                asis_responder(true, asis_descartar_propuesta($ctx, intval($_POST['propuesta_id'] ?? 0)));
            case 'archivar':
                csrf_exigir();
                asis_archivar_conversacion($ctx, intval($_POST['conversacion_id'] ?? 0));
                asis_responder(true);
        }
        asis_responder(false, null, 'Acción no válida.', 400);
    } catch (RuntimeException $ex) {
        asis_responder(false, null, $ex->getMessage());
    } catch (Throwable $ex) {
        error_log('asistente_ia_ajax ' . $accion . ': ' . $ex);
        asis_responder(false, null, 'Error interno: ' . $ex->getMessage(), 500);
    }
}
