<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 617]);

/**
 * iva_posiciones_ajax.php — IVA - Posición Mensual (página 117).
 * Respuesta: {success: bool, data: ..., message: string}; 'exportar_libro' devuelve un CSV (con libro=VENTAS / COMPRAS,
 * el Libro IVA con columnas por alícuota y por percepción; sin libro, el listado combinado de comprobantes).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/iva_posiciones_model.php';

ob_start();

function ivaResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// El usuario tiene asignada la función (accion_js) de la página en alguno de sus perfiles de la empresa
function ivaUsuarioPuede(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id, string $accion_js): bool
{
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return (bool) ivaFila($conexion, "SELECT 1 FROM conf__paginas_funciones WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1",
                              'is', $pagina_id, $accion_js);
    }
    $perfiles = array_column(usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id), 'empresa_perfil_id');
    if (!$perfiles) return false;
    $in = implode(',', array_map('intval', $perfiles));
    return (bool) ivaFila($conexion,
        "SELECT 1 FROM conf__empresas_perfiles_funciones epf
         JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
         WHERE epf.empresa_perfil_id IN ($in) AND epf.asignado = 1 AND pf.pagina_id = ? AND pf.accion_js = ? AND pf.tabla_estado_registro_id = 1
         LIMIT 1", 'is', $pagina_id, $accion_js);
}

$pagina_idx = 617;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');
$req = $_POST + $_GET;

try {
    switch ($accion) {
        case 'maestros':
            ivaResponder(true, [
                'sucursales' => ivaSucursales($conexion, $empresa_id),
                'organismos' => ivaOrganismos($conexion, $empresa_id),
                'puede_liquidar' => ivaUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar'),
                'puede_anular' => ivaUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular'),
            ]);
            break;

        case 'resumen':
            $hasta = (string)($req['hasta'] ?? date('Y-m'));
            ivaResponder(true, ivaResumenMeses($conexion, $empresa_id, $hasta, (int)($req['meses'] ?? 12)));
            break;

        case 'detalle':
            ivaResponder(true, ivaDetallePeriodo($conexion, $empresa_id, (string)($req['periodo'] ?? '')));
            break;

        case 'comprobantes':
            ivaResponder(true, ivaComprobantesPeriodo($conexion, $empresa_id, (string)($req['periodo'] ?? '')));
            break;

        case 'libro':
            ivaResponder(true, ivaLibro($conexion, $empresa_id, ivaValidarPeriodo((string)($req['periodo'] ?? '')), (string)($req['libro'] ?? '')));
            break;

        case 'exportar_libro':
            $periodo = ivaValidarPeriodo((string)($req['periodo'] ?? ''));
            $libro = (string)($req['libro'] ?? '');
            if ($libro !== '') {
                $l = ivaLibro($conexion, $empresa_id, $periodo, $libro);
                $csv = fopen('php://temp', 'r+');
                fputcsv($csv, array_merge(['Fecha', 'Tipo', 'Letra', 'Cod. ARCA', 'PV', 'Número', $libro === 'VENTAS' ? 'Cliente' : 'Proveedor', 'CUIT'],
                                          array_column($l['columnas'], 'titulo')), ';');
                $n = function ($v) { return number_format(floatval($v), 2, ',', ''); };
                foreach ($l['filas'] as $f) {
                    $fila = [$f['fecha'], $f['comprobante_tipo'], $f['letra'], $f['codigo_arca'], str_pad((string)$f['pv'], 4, '0', STR_PAD_LEFT),
                             str_pad((string)$f['numero'], 8, '0', STR_PAD_LEFT), $f['entidad'], $f['cuit']];
                    foreach ($l['columnas'] as $c) $fila[] = $n($f['valores'][$c['clave']] ?? 0);
                    fputcsv($csv, $fila, ';');
                }
                $tot = ['Totales', '', '', '', '', '', '', ''];
                foreach ($l['columnas'] as $c) $tot[] = $n($l['totales'][$c['clave']] ?? 0);
                fputcsv($csv, $tot, ';');
                rewind($csv);
                $contenido = "\xEF\xBB\xBF" . stream_get_contents($csv);
                if (ob_get_length()) ob_clean();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="libro_iva_' . strtolower($libro) . '_' . $periodo . '.csv"');
                echo $contenido;
                exit;
            }
            $filas = ivaComprobantesPeriodo($conexion, $empresa_id, $periodo);
            $csv = fopen('php://temp', 'r+');
            fputcsv($csv, ['Libro', 'Fecha', 'Tipo', 'Letra', 'Cod. ARCA', 'PV', 'Número', 'Entidad', 'CUIT',
                           'Neto gravado', 'IVA', 'No gravado', 'Exento', 'Percepciones / otros', 'Total'], ';');
            foreach ($filas as $f) {
                $s = intval($f['signo']);
                $n = function ($v) use ($s) { return number_format($s * floatval($v), 2, ',', ''); };
                fputcsv($csv, [$f['libro'], $f['fecha'], $f['comprobante_tipo'], $f['letra'], $f['codigo_arca'],
                               str_pad((string)$f['pv'], 4, '0', STR_PAD_LEFT), str_pad((string)$f['numero'], 8, '0', STR_PAD_LEFT),
                               $f['entidad'], $f['cuit'], $n($f['neto']), $n($f['iva']), $n($f['no_gravado']), $n($f['exento']),
                               $n($f['otros']), $n($f['total'])], ';');
            }
            rewind($csv);
            $contenido = "\xEF\xBB\xBF" . stream_get_contents($csv);
            if (ob_get_length()) ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="libro_iva_' . $periodo . '.csv"');
            echo $contenido;
            exit;

        case 'liquidar':
            if (!ivaUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'liquidar')) ivaResponder(false, null, 'No tiene permiso para liquidar.', 403);
            $r = ivaLiquidar($conexion, $empresa_id, $usuario_id, $_POST);
            ivaResponder(true, $r, $r['message']);
            break;

        case 'anular':
            if (!ivaUsuarioPuede($conexion, $usuario_id, $empresa_id, $pagina_idx, 'anular')) ivaResponder(false, null, 'No tiene permiso para anular.', 403);
            $r = ivaAnularLiquidacion($conexion, $empresa_id, $usuario_id, $_POST);
            ivaResponder(true, $r, $r['message']);
            break;

        default:
            ivaResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[iva_posiciones_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    ivaResponder(false, null, 'Error de base de datos', 500);
} catch (Throwable $e) {
    error_log('[iva_posiciones_ajax] ' . $accion . ': ' . $e->getMessage());
    ivaResponder(false, null, $e->getMessage(), 400);
}
