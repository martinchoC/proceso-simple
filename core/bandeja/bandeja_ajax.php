<?php
/**
 * Despachador AJAX de la Bandeja de Documentos, común a Gestión (141) y Gestión Normal (641).
 * El bandeja_documentos_ajax.php de cada módulo llama antes a ajax_guard con su página, incluye el modelo de facturas
 * de proveedores y el de lectura de ese módulo (fijan FPL_PAGINA_IDX = 57 / 557) y después:
 *     bdj_despachar($conexion, $ctx, $pagina_idx);
 * Respuesta {success, data, message}. Escrituras: POST + CSRF + función de la página (data-funcion en la vista).
 * Guardar en Borrador exige además "Agregar" de Facturas de Proveedores del mismo módulo.
 */

require_once __DIR__ . '/bandeja_service.php';
require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../csrf.php';

function bdj_json(array $r, int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    if ($http !== 200) http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

function bdj_despachar(mysqli $conn, array $ctx, int $pagina_idx): void
{
    if (!defined('FPL_PAGINA_IDX') || !function_exists('fplProcesar')) {
        bdj_json(bdj_resp(false, 'Falta el modelo de lectura del módulo.'), 500);
    }
    $empresa_id = (int)$ctx['empresa_id'];
    $usuario_id = (int)$ctx['usuario_id'];
    $accion = (string)($_REQUEST['accion'] ?? '');

    $escrituras = [
        'subir' => 'agregar', 'leer' => 'agregar', 'guardar' => 'agregar', 'descartar' => 'inhabilitar',
        'enlace_crear' => 'agregar', 'enlace_revocar' => 'inhabilitar',
        'cambiar_tipo' => 'agregar', 'ext_importar' => 'agregar', 'ext_clasificar' => 'agregar',
        'ext_agregar_concepto' => 'agregar', 'ext_proveedor' => 'agregar', 'ext_generar_gastos' => 'agregar',
        'ext_finalizar_importacion' => 'agregar',
    ];
    if (isset($escrituras[$accion])) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') bdj_json(bdj_resp(false, 'Método no permitido.'), 405);
        csrf_exigir();
    }
    permisos_exigir_accion($conn, $pagina_idx, $accion, $escrituras);

    try {
        switch ($accion) {
            case 'enlaces_listar':
                $estudio_cliente_id = (int)($_GET['estudio_cliente_id'] ?? 0);
                bdj_json(bdj_resp(true, 'OK', bdj_enlace_listar($conn, $empresa_id, $estudio_cliente_id)));

            case 'enlace_crear':
                $res = bdj_enlace_crear($conn, $empresa_id, $usuario_id, $_POST);
                bdj_json(bdj_resp(true, 'Enlace generado correctamente.', $res));

            case 'enlace_revocar':
                $enlace_id = (int)($_POST['enlace_id'] ?? 0);
                $ok = bdj_enlace_revocar($conn, $empresa_id, $enlace_id);
                if (!$ok) bdj_json(bdj_resp(false, 'No se pudo revocar el enlace o no existe.'), 400);
                bdj_json(bdj_resp(true, 'Enlace revocado correctamente.'));

            case 'resumen':
                bdj_json(bdj_resp(true, 'OK', bdj_resumen($conn, $empresa_id)));

            case 'listar':
                bdj_json(bdj_resp(true, 'OK', bdj_listar($conn, $empresa_id, $_GET)));

            case 'opciones':   // sucursales para guardar y si el usuario puede guardar facturas
                bdj_json(bdj_resp(true, 'OK', [
                    'sucursales' => array_map(fn($s) => ['id' => (int)$s['sucursal_id'], 'nombre' => (string)($s['sucursal_nombre'] ?? $s['nombre'] ?? $s['sucursal_id'])],
                                              obtenerSucursalesEmpresa($conn, $empresa_id)),
                    'puede_guardar' => usuario_puede_funcion($conn, $usuario_id, $empresa_id, FPL_PAGINA_IDX, 'agregar'),
                    'ia' => array_intersect_key(fplEstadoIa($conn, $empresa_id), array_flip(['disponible', 'motivo', 'tope', 'usos_hoy'])),
                ]));

            case 'archivo':   // ver / descargar el original (solo de la empresa de la sesión)
                $doc = bdj_obtener($conn, $empresa_id, (int)($_GET['id'] ?? 0));
                $ruta = $doc ? bdj_archivo_ruta($doc, $empresa_id) : null;
                if (!$ruta) bdj_json(bdj_resp(false, 'Archivo no encontrado.'), 404);
                if (ob_get_length()) ob_clean();
                $nombre = preg_replace('/[^\w.\- ]/u', '_', $doc['nombre_original']);
                header('Content-Type: ' . $doc['mime']);
                header('Content-Length: ' . filesize($ruta));
                header('Content-Disposition: ' . (!empty($_GET['descargar']) ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
                header('X-Content-Type-Options: nosniff');
                readfile($ruta);
                exit;

            case 'subir':   // guarda el archivo sin leerlo (FACTURA_COMPRA o EXTRACTO)
                $tipo_doc = (string)($_POST['tipo_documento'] ?? '');
                $archivo = $_FILES['archivo'] ?? null;
                $ext_up = strtoupper(pathinfo((string)($archivo['name'] ?? ''), PATHINFO_EXTENSION));
                if ($tipo_doc === 'EXTRACTO' || in_array($ext_up, ['XLS', 'XLSX', 'CSV', 'TXT'], true)) {
                    $a = bdj_guardar_archivo_extracto($conn, $empresa_id, $usuario_id, $archivo);
                } else {
                    $a = fplGuardarArchivo($conn, $empresa_id, $usuario_id, $archivo);
                }
                bdj_json(bdj_resp(true, $a['bandeja_estado'] === 'DUPLICADO' ? 'El mismo archivo ya está cargado.' : 'Recibido.', [
                    'id' => (int)$a['bandeja_documento_id'], 'estado' => $a['bandeja_estado'],
                ]));

            case 'cambiar_tipo':
                $id = (int)($_POST['id'] ?? 0);
                $nuevo = (string)($_POST['tipo_documento'] ?? '');
                $doc = bdj_cambiar_tipo($conn, $empresa_id, $usuario_id, $id, $nuevo);
                bdj_json(bdj_resp(true, 'Tipo de documento actualizado.', [
                    'id' => (int)$doc['bandeja_documento_id'], 'tipo_documento' => $doc['tipo_documento'], 'estado' => $doc['estado'],
                ]));

            case 'leer':   // lee un documento (de a uno: el navegador los manda en orden)
                $doc = bdj_obtener($conn, $empresa_id, (int)($_POST['id'] ?? 0));
                if (!$doc || !in_array($doc['estado'], ['RECIBIDO', 'ERROR', 'REVISAR'], true)) {
                    throw new RuntimeException('El documento no está para leer.');
                }
                set_time_limit(240);
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // la IA puede tardar: no trabar las otras pantallas
                if (($doc['tipo_documento'] ?? '') === 'EXTRACTO') {
                    $doc = bdj_leer_extracto($conn, $empresa_id, $usuario_id, $doc);
                } else {
                    $adjunto = fplAdjunto($conn, $empresa_id, (int)$doc['adjunto_id']);
                    if (!$adjunto) throw new RuntimeException('Archivo no encontrado.');
                    fplProcesar($conn, $empresa_id, $usuario_id, $adjunto, [], true);
                    $doc = bdj_obtener($conn, $empresa_id, (int)$doc['bandeja_documento_id']);
                }
                bdj_json(bdj_resp(true, 'Leído.', ['id' => (int)$doc['bandeja_documento_id'], 'estado' => $doc['estado'], 'motivo' => $doc['motivo']]));

            case 'ext_estado':
                $id = (int)($_REQUEST['id'] ?? 0);
                $cuenta_id = (int)($_REQUEST['cuenta_id'] ?? 0);
                $mes = (string)($_REQUEST['mes'] ?? '');
                $excluir = (array)($_REQUEST['excluir'] ?? []);
                bdj_json(bdj_resp(true, 'OK', bdj_ext_estado($conn, $empresa_id, $id, $cuenta_id, $mes, $excluir)));

            case 'ext_importar':
                $id = (int)($_POST['id'] ?? 0);
                $cuenta_id = (int)($_POST['cuenta_id'] ?? 0);
                $asig_raw = $_POST['asignaciones'] ?? [];
                $asignaciones = is_string($asig_raw) ? (json_decode($asig_raw, true) ?: []) : (array)$asig_raw;
                set_time_limit(240);
                $data = bdj_ext_importar($conn, $empresa_id, $usuario_id, $id, $cuenta_id, $asignaciones);
                bdj_json(bdj_resp(true, 'Movimientos del extracto importados en la cuenta.', $data));

            case 'ext_clasificar':
                $r = bco_lineas_clasificar($conn, $empresa_id, $usuario_id, (array)($_POST['extracto_ids'] ?? []),
                                           (string)($_POST['operacion'] ?? ''), (int)($_POST['concepto_id'] ?? 0));
                $msg = "Se actualizaron {$r['lineas']} líneas.";
                if (!empty($r['mismas'])) $msg .= " Además, {$r['mismas']} líneas sin concepto con la misma descripción.";
                if (!empty($r['reglas'])) $msg .= ' Los próximos extractos con esa descripción se clasifican solos.';
                bdj_json(bdj_resp(true, $msg, $r));

            case 'ext_agregar_concepto':
                $d = $_POST;
                $d['concepto_id'] = 0;
                $cid = bco_concepto_guardar($conn, $empresa_id, $usuario_id, $d);
                bdj_json(bdj_resp(true, 'Concepto creado.', bco_concepto_obtener($conn, $empresa_id, $cid)));

            case 'ext_proveedores':
                bdj_json(bdj_resp(true, 'OK', bco_proveedores_buscar($conn, $empresa_id, (string)($_REQUEST['q'] ?? ''))));

            case 'ext_proveedor':
                bco_gastos_proveedor_guardar($conn, $empresa_id, (int)($_POST['cuenta_id'] ?? 0), (int)($_POST['entidad_id'] ?? 0));
                bdj_json(bdj_resp(true, 'Proveedor del banco guardado.'));

            case 'ext_generar_gastos':
                foreach (['agregar', 'aprobar', 'confirmar'] as $fn_fp) {
                    if (!usuario_puede_funcion($conn, $usuario_id, $empresa_id, FPL_PAGINA_IDX, $fn_fp)) {
                        bdj_json(bdj_resp(false, "Tu perfil no tiene permiso para '$fn_fp' en Facturas de Proveedores."), 403);
                    }
                }
                $id = (int)($_POST['id'] ?? 0);
                $cuenta_id = (int)($_POST['cuenta_id'] ?? 0);
                $mes = (string)($_POST['mes'] ?? '');
                $excluir = (array)($_POST['excluir'] ?? []);
                $op_pv_id = (int)($_POST['op_punto_venta_id'] ?? 0);
                set_time_limit(240);
                $r = bco_gastos_generar($conn, $empresa_id, $usuario_id, $cuenta_id, $mes, $excluir, FPL_PAGINA_IDX, $op_pv_id);
                if ($id > 0 && !empty($r['factura_proveedor_id'])) {
                    bdj_procesado_documento($conn, $empresa_id, $id, BDJ_TABLA_FACTURAS_PROVEEDORES, (int)$r['factura_proveedor_id'], $usuario_id);
                }
                $msg = "Se generó el {$r['comprobante']} por $ " . bco_fmt($r['total']) . ' y se concilió con el débito del banco.';
                bdj_json(bdj_resp(true, $msg, $r));

            case 'ext_finalizar_importacion':
                $id = (int)($_POST['id'] ?? 0);
                $imp_id = (int)($_POST['importacion_id'] ?? 0);
                if ($id <= 0 || $imp_id <= 0) throw new RuntimeException('Faltan datos para finalizar.');
                bdj_procesado_documento($conn, $empresa_id, $id, BDJ_TABLA_BANCOS_IMPORTACIONES, $imp_id, $usuario_id, 'Extracto bancario importado');
                bdj_json(bdj_resp(true, 'Extracto bancario marcado como guardado en la bandeja.'));

            case 'guardar':   // guarda en Borrador los LISTOS elegidos (uno que falla no corta a los demás)
                if (!usuario_puede_funcion($conn, $usuario_id, $empresa_id, FPL_PAGINA_IDX, 'agregar')) {
                    bdj_json(bdj_resp(false, 'Tu perfil no puede cargar facturas de proveedores.'), 403);
                }
                $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))))), 0, 200);
                if (!$ids) throw new RuntimeException('Elegí los documentos.');
                set_time_limit(240);
                $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
                $items = [];
                foreach ($ids as $id) {
                    $doc = bdj_obtener($conn, $empresa_id, $id);
                    if (!$doc || $doc['estado'] !== 'LISTO' || (int)$doc['adjunto_id'] <= 0) {
                        $items[] = ['id' => $id, 'success' => false, 'message' => 'No está listo para guardar.'];
                        continue;
                    }
                    try {
                        $r = fplGuardarBorrador($conn, $empresa_id, $usuario_id, (int)$doc['adjunto_id'], $sucursal_id);
                        $items[] = ['id' => $id, 'success' => !empty($r['success']),
                                    'message' => $r['message'] ?? '', 'motivos' => $r['motivos'] ?? [],
                                    'factura_proveedor_id' => $r['factura_proveedor_id'] ?? null];
                    } catch (Throwable $ex) {
                        error_log("bandeja guardar documento $id: " . $ex->getMessage());
                        bdj_lectura($conn, $empresa_id, (int)$doc['adjunto_id'], 'REVISAR', 'Al guardar: ' . $ex->getMessage());
                        $items[] = ['id' => $id, 'success' => false, 'message' => $ex->getMessage()];
                    }
                }
                $ok = count(array_filter(array_column($items, 'success')));
                bdj_json(bdj_resp(true, $ok . ' de ' . count($items) . ' guardada(s) en Borrador.', $items));

            case 'descartar':
                $n = bdj_descartar($conn, $empresa_id, $usuario_id, (array)($_POST['ids'] ?? []), (string)($_POST['motivo'] ?? ''));
                bdj_json(bdj_resp($n > 0, $n > 0 ? "$n documento(s) descartado(s)." : 'No había documentos para descartar.', ['descartados' => $n]));

            default:
                bdj_json(bdj_resp(false, 'Acción no válida.'), 400);
        }
    } catch (RuntimeException $ex) {
        error_log("bandeja_documentos ($accion): " . $ex->getMessage());
        bdj_json(bdj_resp(false, $ex->getMessage()));
    } catch (Throwable $ex) {
        error_log("bandeja_documentos ($accion) ERROR: " . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
        bdj_json(bdj_resp(false, 'No se pudo completar la operación.'), 500);
    }
}
