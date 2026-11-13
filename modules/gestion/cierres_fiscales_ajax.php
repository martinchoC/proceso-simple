<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 125]);

/**
 * cierres_fiscales_ajax.php — Control Fiscal (página 125).
 * Respuesta: {success: bool, data: ..., message: string}; 'exportar_registro' y 'exportar_diferencias' devuelven un CSV.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/cierres_fiscales_model.php';
require_once __DIR__ . '/retenciones_liquidacion_model.php';
require_once __DIR__ . '/iibb_liquidacion_model.php';

ob_start();

function cfResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina_idx = 125;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');
$req = $_POST + $_GET;

try {
    switch ($accion) {
        case 'maestros':
            cfResponder(true, [
                'obligaciones' => pfObligaciones($conexion),
                'jurisdicciones' => cfJurisdicciones($conexion),
                'origenes' => PF_ORIGENES,
                'circuitos' => CF_CIRCUITOS,
                'impuestos' => pfFilas($conexion, "SELECT impuesto_tipo_id, impuesto_tipo FROM gestion__impuestos_tipos
                                                   WHERE tabla_estado_registro_id = 1 AND (es_retencion = 1 OR es_percepcion = 1) ORDER BY impuesto_tipo"),
                'regimenes_iibb' => cfRegimenesIibb($conexion),
                'puede_cerrar' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'cerrar'),
                'puede_reabrir' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'reabrir'),
                'puede_inscribir' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'agregar_inscripcion'),
                'puede_inhabilitar_inscripcion' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'inhabilitar_inscripcion'),
                'puede_liquidar_retenciones' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar_retenciones'),
                'puede_anular_liquidacion' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular_liquidacion_retenciones'),
                'liquidacion' => rliqMaestros($conexion, $empresa_id),
                'certificado_estados' => CF_CERT_ESTADOS,
                'puede_actualizar_certificado' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'actualizar_certificado'),
                'puede_liquidar_iibb' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar_iibb'),
                'puede_anular_liquidacion_iibb' => cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular_liquidacion_iibb'),
                'iibb_disponible' => iibbDisponible($conexion),
            ]);
            break;

        case 'listar':
            cfResponder(true, pfListar($conexion, $empresa_id, [
                'impuesto_tipo_id' => (int)($req['impuesto_tipo_id'] ?? 0),
                'anio' => (string)($req['anio'] ?? ''),
                'incluir_reabiertos' => !empty($req['incluir_reabiertos']) && $req['incluir_reabiertos'] !== '0',
            ]));
            break;

        case 'cerrar':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'cerrar')) cfResponder(false, null, 'No tiene permiso para cerrar períodos.', 403);
            $r = cfCerrar($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'reabrir':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'reabrir')) cfResponder(false, null, 'No tiene permiso para reabrir períodos.', 403);
            $r = cfReabrir($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'cuadre':
            cfResponder(true, pfCuadre($conexion, $empresa_id, (string)($req['desde'] ?? ''), (string)($req['hasta'] ?? '')));
            break;

        case 'registro':
            cfResponder(true, cfRegistro($conexion, $empresa_id, $req));
            break;

        case 'exportar_registro':
            $g = cfRegistro($conexion, $empresa_id, $req);
            $csv = fopen('php://temp', 'r+');
            fputcsv($csv, ['Circuito', 'Fecha', 'Período', 'Comprobante', 'Tercero', 'CUIT', 'Impuesto', 'Jurisdicción', 'Base', 'Alícuota',
                           'Importe', 'Certificado', 'Estado certificado', 'Fecha certificado', 'Régimen', 'Declarada'], ';');
            $n = function ($v) { return $v === null ? '' : number_format(floatval($v), 2, ',', ''); };
            foreach ($g['filas'] as $f) {
                fputcsv($csv, [$f['circuito_nombre'], $f['fecha'], $f['periodo'], $f['comprobante'], $f['entidad'], $f['cuit'], $f['impuesto'],
                               $f['jurisdiccion'] ?? '', $n($f['base']), $f['alicuota'] === null ? '' : number_format(floatval($f['alicuota']), 4, ',', ''),
                               $n($f['importe']), $f['certificado'] ?? '', $f['certificado_estado'] ?? '', $f['f_certificado'] ?? '', $f['regimen'] ?? '',
                               intval($f['declarada']) ? 'Sí' : 'No'], ';');
            }
            rewind($csv);
            $contenido = "\xEF\xBB\xBF" . stream_get_contents($csv);
            if (ob_get_length()) ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="retenciones_percepciones_' . substr($g['desde'], 0, 7) . '_' . substr($g['hasta'], 0, 7) . '.csv"');
            echo $contenido;
            exit;

        case 'inscripciones':
            cfResponder(true, cfInscripciones($conexion, $empresa_id, !empty($req['incluir_inactivas']) && $req['incluir_inactivas'] !== '0'));
            break;

        case 'agregar_inscripcion':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'agregar_inscripcion')) cfResponder(false, null, 'No tiene permiso para cargar inscripciones.', 403);
            $r = cfAgregarInscripcion($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'inhabilitar_inscripcion':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'inhabilitar_inscripcion')) cfResponder(false, null, 'No tiene permiso para inhabilitar inscripciones.', 403);
            $r = cfInhabilitarInscripcion($conexion, $empresa_id, $usuario_id, (int)($_POST['inscripcion_id'] ?? 0));
            cfResponder(true, $r, $r['message']);
            break;

        case 'actualizar_certificado':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'actualizar_certificado')) cfResponder(false, null, 'No tiene permiso para actualizar certificados.', 403);
            $r = cfActualizarCertificado($conexion, $empresa_id, $usuario_id, $_POST);
            if (!empty($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $r = cfSubirCertificado($conexion, $empresa_id, $usuario_id, (int)($_POST['cobranza_medio_id'] ?? 0), $_FILES['archivo']);
                $r['message'] = 'Certificado actualizado y archivo guardado.';
            }
            cfResponder(true, $r, $r['message']);
            break;

        case 'ver_certificado':
            $ruta = cfArchivoCertificado($conexion, $empresa_id, (int)($req['cobranza_medio_id'] ?? 0));
            if ($ruta === null) cfResponder(false, null, 'La retención no tiene el archivo del certificado.', 404);
            $tipos = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
            if (ob_get_length()) ob_clean();
            header('Content-Type: ' . ($tipos[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($ruta);
            exit;

        case 'liquidacion_resumen':
            cfResponder(true, rliqResumenPeriodo($conexion, $empresa_id, (string)($req['periodo'] ?? '')));
            break;

        case 'liquidacion_detalle':
            cfResponder(true, rliqDetalle($conexion, $empresa_id, $req));
            break;

        case 'liquidar_retenciones':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar_retenciones')) cfResponder(false, null, 'No tiene permiso para liquidar retenciones.', 403);
            $r = rliqLiquidar($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'anular_liquidacion_retenciones':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular_liquidacion_retenciones')) cfResponder(false, null, 'No tiene permiso para anular liquidaciones.', 403);
            $r = rliqAnular($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'actividades':
            cfResponder(true, cfActividades($conexion, $empresa_id, (int)($req['inscripcion_id'] ?? 0),
                                            !empty($req['incluir_inactivas']) && $req['incluir_inactivas'] !== '0'));
            break;

        case 'agregar_actividad':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'agregar_inscripcion')) cfResponder(false, null, 'No tiene permiso para cargar actividades.', 403);
            $r = cfAgregarActividad($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'inhabilitar_actividad':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'inhabilitar_inscripcion')) cfResponder(false, null, 'No tiene permiso para inhabilitar actividades.', 403);
            $r = cfInhabilitarActividad($conexion, $empresa_id, $usuario_id, (int)($_POST['actividad_id'] ?? 0));
            cfResponder(true, $r, $r['message']);
            break;

        case 'iibb_resumen':
            cfResponder(true, iibbResumenPeriodo($conexion, $empresa_id, (string)($req['periodo'] ?? '')));
            break;

        case 'iibb_deducciones':
            cfResponder(true, iibbDetalleDeducciones($conexion, $empresa_id, (string)($req['periodo'] ?? ''), (int)($req['jurisdiccion_id'] ?? 0)));
            break;

        case 'iibb_liquidacion_actividades':
            cfResponder(true, iibbActividadesLiquidacion($conexion, $empresa_id, (int)($req['iibb_posicion_id'] ?? 0)));
            break;

        case 'liquidar_iibb':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar_iibb')) cfResponder(false, null, 'No tiene permiso para liquidar Ingresos Brutos.', 403);
            $r = iibbLiquidar($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'anular_liquidacion_iibb':
            if (!cfUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular_liquidacion_iibb')) cfResponder(false, null, 'No tiene permiso para anular liquidaciones de Ingresos Brutos.', 403);
            $r = iibbAnular($conexion, $empresa_id, $usuario_id, $_POST);
            cfResponder(true, $r, $r['message']);
            break;

        case 'exportar_diferencias':
            $c = pfCuadre($conexion, $empresa_id, (string)($req['desde'] ?? ''), (string)($req['hasta'] ?? ''));
            $csv = fopen('php://temp', 'r+');
            fputcsv($csv, ['Origen', 'Fecha', 'Comprobante', 'Entidad', 'Impuestos', 'Cuenta', 'Según comprobante', 'Contabilizado', 'Diferencia', 'Motivo'], ';');
            $n = function ($v) { return number_format(floatval($v), 2, ',', ''); };
            foreach ($c['diferencias'] as $d) {
                fputcsv($csv, [$d['origen'], $d['fecha'], $d['comprobante'], $d['entidad'], $d['impuestos'], $d['cuenta'] ?? '',
                               $n($d['esperado']), $n($d['contabilizado']), $n($d['diferencia']), $d['motivo']], ';');
            }
            rewind($csv);
            $contenido = "\xEF\xBB\xBF" . stream_get_contents($csv);
            if (ob_get_length()) ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="cuadre_diferencias_' . substr($c['desde'], 0, 7) . '_' . substr($c['hasta'], 0, 7) . '.csv"');
            echo $contenido;
            exit;

        default:
            cfResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[cierres_fiscales_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    cfResponder(false, null, 'Error de base de datos', 500);
} catch (Throwable $e) {
    error_log('[cierres_fiscales_ajax] ' . $accion . ': ' . $e->getMessage());
    cfResponder(false, null, $e->getMessage(), 400);
}
