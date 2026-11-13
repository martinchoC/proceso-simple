<?php
/**
 * Despachador AJAX de Libros Societarios, común a Gestión (140), Gestión Normal (640) y Estudio Contable (424).
 * El _ajax.php de cada módulo llama antes a ajax_guard con su página y después a:
 *     lsoc_despachar($conn, $ctx, $pagina_idx, 'empresa' | 'estudio');
 * Respuesta {success, data, message}. Escrituras: POST + CSRF + función de la página (data-funcion en la vista).
 */

require_once __DIR__ . '/libros_societarios_service.php';
require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../csrf.php';

function lsoc_despachar(mysqli $conn, array $ctx, int $pagina_idx, string $modo): void
{
    $empresa_id = (int) $ctx['empresa_id'];
    $usuario_id = (int) $ctx['usuario_id'];
    $modo = $modo === 'estudio' ? 'estudio' : 'empresa';
    $accion = (string) ($_REQUEST['accion'] ?? '');

    $escrituras = [
        'guardar_libro' => ((int) ($_POST['libro_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
        'inhabilitar_libro' => 'inhabilitar',
        'guardar_acta' => ((int) ($_POST['acta_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
        'transcribir' => 'editar',
        'volver_borrador' => 'editar',
        'inscripcion' => 'editar',
        'subir_archivo' => 'editar',
        'anular_acta' => 'inhabilitar',
        'pasar_a_empresa' => 'editar',
    ];
    if (isset($escrituras[$accion])) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            lsoc_json(lsoc_resp(false, 'Método no permitido.'), 405);
        }
        csrf_exigir();
    }
    permisos_exigir_accion($conn, $pagina_idx, $accion, $escrituras);

    try {
        switch ($accion) {
            case 'combos':
                lsoc_json(lsoc_resp(true, 'OK', lsoc_combos($conn, $empresa_id, $modo)));
                break;
            case 'resumen':
                $amb = lsoc_ambito($conn, $empresa_id, $modo, $_GET['cliente_id'] ?? 0, false);
                lsoc_json(lsoc_resp(true, 'OK', lsoc_resumen($conn, $amb)));
                break;
            case 'listar_actas':
                $amb = lsoc_ambito($conn, $empresa_id, $modo, $_GET['cliente_id'] ?? 0, false);
                lsoc_json(lsoc_resp(true, 'OK', lsoc_actas_listar($conn, $amb, $_GET)));
                break;
            case 'listar_libros':
                $amb = lsoc_ambito($conn, $empresa_id, $modo, $_GET['cliente_id'] ?? 0, false);
                lsoc_json(lsoc_resp(true, 'OK', lsoc_libros_listar($conn, $amb, !empty($_GET['incluir_inactivos']))));
                break;
            case 'autoridades':
                $amb = lsoc_ambito($conn, $empresa_id, $modo, $_GET['cliente_id'] ?? 0, false);
                lsoc_json(lsoc_resp(true, 'OK', lsoc_autoridades($conn, $amb)));
                break;
            case 'obtener_libro':
                $l = lsoc_libro_obtener($conn, $empresa_id, $modo, (int) ($_GET['libro_id'] ?? 0));
                lsoc_json($l ? lsoc_resp(true, 'OK', $l) : lsoc_resp(false, 'El libro no existe.'));
                break;
            case 'obtener_acta':
                $a = lsoc_acta_detalle($conn, $empresa_id, $modo, (int) ($_GET['acta_id'] ?? 0));
                lsoc_json($a ? lsoc_resp(true, 'OK', $a) : lsoc_resp(false, 'El acta no existe.'));
                break;
            case 'sugerencia':
                lsoc_json(lsoc_sugerencia($conn, $empresa_id, $modo, (int) ($_GET['libro_id'] ?? 0)));
                break;
            case 'descargar':
                if (!lsoc_archivo_enviar($conn, $empresa_id, $modo, (int) ($_GET['acta_id'] ?? 0))) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'El acta no tiene archivo.';
                }
                exit;
            case 'guardar_libro':
                lsoc_json(lsoc_libro_guardar($conn, $empresa_id, $usuario_id, $modo, $_POST));
                break;
            case 'inhabilitar_libro':
                lsoc_json(lsoc_libro_inhabilitar($conn, $empresa_id, $modo, (int) ($_POST['libro_id'] ?? 0)));
                break;
            case 'guardar_acta':
                lsoc_json(lsoc_acta_guardar($conn, $empresa_id, $usuario_id, $modo, $_POST));
                break;
            case 'transcribir':
                lsoc_json(lsoc_acta_transcribir($conn, $empresa_id, $modo, $_POST));
                break;
            case 'volver_borrador':
                lsoc_json(lsoc_acta_volver_borrador($conn, $empresa_id, $modo, (int) ($_POST['acta_id'] ?? 0)));
                break;
            case 'inscripcion':
                lsoc_json(lsoc_acta_inscripcion($conn, $empresa_id, $modo, $_POST));
                break;
            case 'subir_archivo':
                lsoc_json(lsoc_archivo_subir($conn, $empresa_id, $modo, (int) ($_POST['acta_id'] ?? 0), $_FILES['archivo'] ?? []));
                break;
            case 'anular_acta':
                lsoc_json(lsoc_acta_anular($conn, $empresa_id, $usuario_id, $modo, (int) ($_POST['acta_id'] ?? 0), (string) ($_POST['motivo'] ?? '')));
                break;
            case 'pasar_a_empresa':
                if ($modo !== 'estudio') {
                    lsoc_json(lsoc_resp(false, 'Acción no disponible.'), 400);
                }
                lsoc_json(lsoc_pasar_a_empresa($conn, $empresa_id, $usuario_id, (int) ($_POST['cliente_id'] ?? 0)));
                break;
            default:
                lsoc_json(lsoc_resp(false, 'Acción no reconocida.'), 400);
        }
    } catch (RuntimeException $e) {
        lsoc_json(lsoc_resp(false, $e->getMessage()));
    } catch (Throwable $e) {
        error_log('=== libros_societarios ' . $accion . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        lsoc_json(lsoc_resp(false, 'Error en el servidor.'), 500);
    }
}

function lsoc_json(array $r, int $http = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    if ($http !== 200) {
        http_response_code($http);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
