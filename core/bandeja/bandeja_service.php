<?php
/**
 * Bandeja de captura de documentos (fase 1: bandeja interna). Diseño: _local/docs/gestion/15_bandeja_captura.md
 * Script: _local/sql/mantenimiento/20261109_bandeja_documentos_01.sql
 *
 * Una fila de gestion__bandeja_documentos por archivo recibido. La escriben:
 *   - core/facturas_lectura/fp_lectura_model.php: al guardar el archivo (bdj_registrar), al leerlo (bdj_lectura) y al
 *     ligarlo a una factura (bdj_procesado). Así la pantalla "Cargar desde archivo" y el cron de la carpeta de entrada
 *     escriben en la bandeja sin cambiar lo que hacen;
 *   - la página Bandeja de Documentos (141 / 641, core/bandeja/bandeja_ajax.php): subir, leer, guardar, descartar.
 * Mismo código para Gestión (2) y Gestión Normal (5). Toda consulta filtra por empresa.
 */

require_once __DIR__ . '/../facturas_lectura/FacturaExtractor.php';
require_once __DIR__ . '/../bancos/bancos_gastos_service.php';

const BDJ_ESTADOS = ['RECIBIDO', 'LEYENDO', 'LISTO', 'REVISAR', 'ERROR', 'PROCESADO', 'DESCARTADO', 'DUPLICADO'];
/** Sin terminar: la lectura puede cambiarles el estado y se pueden descartar. */
const BDJ_PENDIENTES = ['RECIBIDO', 'LEYENDO', 'LISTO', 'REVISAR', 'ERROR'];
const BDJ_TABLA_FACTURAS_PROVEEDORES = 84;
const BDJ_TABLA_BANCOS_IMPORTACIONES = 445;

function bdj_resp(bool $ok, string $msg = '', $data = null): array
{
    return ['success' => $ok, 'data' => $data, 'message' => $msg];
}

function bdj_fila(mysqli $c, string $sql, string $tipos, array $params): ?array
{
    $stmt = mysqli_prepare($c, $sql);
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
    return $r;
}

function bdj_filas(mysqli $c, string $sql, string $tipos, array $params): array
{
    $stmt = mysqli_prepare($c, $sql);
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
    mysqli_stmt_close($stmt);
    return $out;
}

function bdj_ejecutar(mysqli $c, string $sql, string $tipos, array $params): int
{
    $stmt = mysqli_prepare($c, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// ---------------------------------------------------------------------------------------------------
// Escritura desde la lectura
// ---------------------------------------------------------------------------------------------------

/**
 * Registra un archivo recibido. $a: adjunto_id, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256.
 * Si el mismo archivo ya está PROCESADO en la empresa queda DUPLICADO (con duplicado_de_id); los documentos anteriores
 * del mismo archivo que quedaron sin terminar pasan a DESCARTADO ("se volvió a subir"). Devuelve la fila nueva.
 */
function bdj_registrar(mysqli $c, int $empresa_id, ?int $usuario_id, array $a, string $origen, int $estudio_cliente_id = 0, ?int $enlace_id = null, ?int $requerimiento_id = null): array
{
    $sha = (string)$a['sha256'];
    // Procesado = en una factura no eliminada o en una importación bancaria no anulada
    $previo = bdj_fila($c, "SELECT b.bandeja_documento_id FROM gestion__bandeja_documentos b
                             WHERE b.empresa_id = ? AND b.sha256 = ? AND b.estado = 'PROCESADO'
                               AND NOT EXISTS (SELECT 1 FROM gestion__facturas_proveedores f
                                                WHERE b.destino_tabla_id = " . BDJ_TABLA_FACTURAS_PROVEEDORES . "
                                                  AND f.factura_proveedor_id = b.destino_registro_id AND f.empresa_id = b.empresa_id
                                                  AND f.tabla_estado_registro_id IN (6, 38))
                               AND NOT EXISTS (SELECT 1 FROM gestion__bancos_importaciones i
                                                WHERE b.destino_tabla_id = " . BDJ_TABLA_BANCOS_IMPORTACIONES . "
                                                  AND i.importacion_id = b.destino_registro_id AND i.empresa_id = b.empresa_id
                                                  AND i.tabla_estado_registro_id = 38)
                             ORDER BY b.bandeja_documento_id LIMIT 1",
                       "is", [$empresa_id, $sha]);
    $dup = $previo ? (int)$previo['bandeja_documento_id'] : null;
    $estado = $dup ? 'DUPLICADO' : 'RECIBIDO';
    $motivo = $dup ? 'El mismo archivo ya está cargado (documento ' . $dup . ')' : null;
    $adjunto_id = !empty($a['adjunto_id']) ? (int)$a['adjunto_id'] : null;
    $tipo = in_array($a['tipo_documento'] ?? '', ['FACTURA_COMPRA', 'EXTRACTO'], true) ? (string)$a['tipo_documento'] : 'FACTURA_COMPRA';

    $stmt = mysqli_prepare($c, "INSERT INTO gestion__bandeja_documentos
            (empresa_id, estudio_cliente_id, origen, enlace_id, tipo_documento, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256,
             estado, motivo, adjunto_id, duplicado_de_id, requerimiento_id, usuario_id, fecha_alta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $ruta = (string)$a['archivo_ruta'];
    $nombre = (string)$a['nombre_original'];
    $mime = (string)$a['mime'];
    $bytes = (int)$a['tamanio_bytes'];
    mysqli_stmt_bind_param($stmt, "iisissssisssiiii", $empresa_id, $estudio_cliente_id, $origen, $enlace_id, $tipo, $ruta, $nombre, $mime, $bytes,
                           $sha, $estado, $motivo, $adjunto_id, $dup, $requerimiento_id, $usuario_id);
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($c);
    mysqli_stmt_close($stmt);

    // Los anteriores del mismo archivo que no se terminaron quedan reemplazados por este
    $motivo_desc = 'Se volvió a subir el mismo archivo (documento ' . $id . ')';
    $n = bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                              SET estado = 'DESCARTADO', motivo = ?, usuario_proceso_id = ?, fecha_proceso = NOW()
                            WHERE empresa_id = ? AND sha256 = ? AND bandeja_documento_id <> ?
                              AND estado IN ('RECIBIDO', 'LISTO', 'REVISAR', 'ERROR')",
                      "siisi", [$motivo_desc, $usuario_id, $empresa_id, $sha, $id]);
    error_log("=== bdj_registrar: documento $id empresa $empresa_id origen $origen estado $estado"
              . ($n ? " ($n anterior(es) descartado(s))" : '') . ' ===');
    return ['bandeja_documento_id' => $id, 'estado' => $estado, 'duplicado_de_id' => $dup];
}

function bdj_por_adjunto(mysqli $c, int $empresa_id, int $adjunto_id): ?array
{
    if ($adjunto_id <= 0) return null;
    return bdj_fila($c, "SELECT * FROM gestion__bandeja_documentos WHERE empresa_id = ? AND adjunto_id = ?", "ii", [$empresa_id, $adjunto_id]);
}

function bdj_obtener(mysqli $c, int $empresa_id, int $id): ?array
{
    return bdj_fila($c, "SELECT * FROM gestion__bandeja_documentos WHERE empresa_id = ? AND bandeja_documento_id = ?", "ii", [$empresa_id, $id]);
}

/** Empieza la lectura de un documento sin terminar. */
function bdj_leyendo(mysqli $c, int $empresa_id, int $adjunto_id): void
{
    bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos SET estado = 'LEYENDO'
                       WHERE empresa_id = ? AND adjunto_id = ? AND estado IN ('RECIBIDO', 'LISTO', 'REVISAR', 'ERROR')",
                 "ii", [$empresa_id, $adjunto_id]);
}

/**
 * Resultado de una lectura (o de rearmar la propuesta): LISTO / REVISAR / ERROR con el motivo y la última lectura.
 * Solo cambia documentos sin terminar: un DUPLICADO o PROCESADO conserva su estado.
 */
function bdj_lectura(mysqli $c, int $empresa_id, int $adjunto_id, string $estado, ?string $motivo, ?int $extraccion_id = null): void
{
    if (!in_array($estado, ['LISTO', 'REVISAR', 'ERROR'], true)) return;
    $motivo = $motivo !== null && $motivo !== '' ? mb_substr($motivo, 0, 1000) : null;
    bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                         SET estado = ?, motivo = ?, extraccion_id = COALESCE(?, extraccion_id)
                       WHERE empresa_id = ? AND adjunto_id = ? AND estado IN ('RECIBIDO', 'LEYENDO', 'LISTO', 'REVISAR', 'ERROR')",
                 "ssiii", [$estado, $motivo, $extraccion_id, $empresa_id, $adjunto_id]);
}

/** El adjunto quedó en una factura (formulario, "Guardar los listos", cron o la bandeja). */
function bdj_procesado(mysqli $c, int $empresa_id, int $adjunto_id, int $tabla_id, int $registro_id, int $usuario_id): void
{
    bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                         SET estado = 'PROCESADO', motivo = NULL, destino_tabla_id = ?, destino_registro_id = ?,
                             usuario_proceso_id = ?, fecha_proceso = NOW()
                       WHERE empresa_id = ? AND adjunto_id = ? AND estado <> 'PROCESADO'",
                 "iiiii", [$tabla_id, $registro_id, $usuario_id, $empresa_id, $adjunto_id]);
}

/** Marca como PROCESADO un documento de la bandeja por su propio id (p. ej. un EXTRACTO bancario). */
function bdj_procesado_documento(mysqli $c, int $empresa_id, int $bandeja_documento_id, int $tabla_id, int $registro_id, int $usuario_id, ?string $motivo = null): void
{
    bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                         SET estado = 'PROCESADO', motivo = ?, destino_tabla_id = ?, destino_registro_id = ?,
                             usuario_proceso_id = ?, fecha_proceso = NOW()
                       WHERE empresa_id = ? AND bandeja_documento_id = ? AND estado <> 'PROCESADO'",
                 "siiiii", [$motivo, $tabla_id, $registro_id, $usuario_id, $empresa_id, $bandeja_documento_id]);
}

/**
 * Guarda en la bandeja un archivo de extracto bancario (PDF, Excel, CSV, TXT, JPG, PNG) en uploads/extractos_bancarios/<empresa>/.
 */
function bdj_guardar_archivo_extracto(mysqli $c, int $empresa_id, int $usuario_id, ?array $archivo, string $origen = 'PANTALLA'): array
{
    [$tmp, $nombre] = bco_archivo_subido($archivo);
    $tamanio = is_file($tmp) ? (int)filesize($tmp) : 0;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $sha = hash_file('sha256', $tmp);
    $ruta = bco_archivo_guardar($tmp, $empresa_id, $nombre);
    $bandeja = bdj_registrar($c, $empresa_id, $usuario_id, [
        'adjunto_id' => null, 'tipo_documento' => 'EXTRACTO', 'archivo_ruta' => $ruta,
        'nombre_original' => $nombre, 'mime' => $mime, 'tamanio_bytes' => $tamanio, 'sha256' => $sha,
    ], $origen);
    return ['bandeja_documento_id' => (int)$bandeja['bandeja_documento_id'], 'bandeja_estado' => $bandeja['estado']];
}

/**
 * Cambia el tipo de un documento pendiente entre FACTURA_COMPRA y EXTRACTO.
 */
function bdj_cambiar_tipo(mysqli $c, int $empresa_id, int $usuario_id, int $id, string $nuevo_tipo): array
{
    if (!in_array($nuevo_tipo, ['FACTURA_COMPRA', 'EXTRACTO'], true)) {
        throw new RuntimeException('Tipo de documento no válido.');
    }
    $doc = bdj_obtener($c, $empresa_id, $id);
    if (!$doc || !in_array($doc['estado'], ['RECIBIDO', 'LISTO', 'REVISAR', 'ERROR'], true)) {
        throw new RuntimeException('Solo se puede cambiar el tipo de documentos pendientes.');
    }
    if ($doc['tipo_documento'] === $nuevo_tipo) {
        return $doc;
    }
    if ($nuevo_tipo === 'FACTURA_COMPRA') {
        if (!in_array($doc['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Las facturas de proveedores deben ser archivos PDF, JPG o PNG.');
        }
        $adjunto_id = (int)($doc['adjunto_id'] ?? 0);
        $ruta_rel = (string)$doc['archivo_ruta'];
        if ($adjunto_id <= 0) {
            $ruta_abs = bdj_archivo_ruta($doc, $empresa_id);
            if (!$ruta_abs || !is_file($ruta_abs)) {
                throw new RuntimeException('No se encontró el archivo original.');
            }
            $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$doc['mime']] ?? 'pdf';
            $base = __DIR__ . '/../../uploads/facturas_proveedores';
            $dir = $base . '/' . $empresa_id;
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException('No se pudo crear la carpeta de facturas.');
            }
            $nombre_arch = date('Ymd') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
            if (!copy($ruta_abs, $dir . '/' . $nombre_arch)) {
                throw new RuntimeException('No se pudo copiar el archivo.');
            }
            $ruta_rel = 'uploads/facturas_proveedores/' . $empresa_id . '/' . $nombre_arch;
            $stmt = mysqli_prepare($c, "INSERT INTO gestion__facturas_proveedores_adjuntos
                    (empresa_id, factura_proveedor_id, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256, usuario_id, fecha_alta, tabla_estado_registro_id)
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NOW(), 1)");
            $nom = (string)$doc['nombre_original'];
            $mime = (string)$doc['mime'];
            $bytes = (int)$doc['tamanio_bytes'];
            $sha = (string)$doc['sha256'];
            mysqli_stmt_bind_param($stmt, "isssisi", $empresa_id, $ruta_rel, $nom, $mime, $bytes, $sha, $usuario_id);
            mysqli_stmt_execute($stmt);
            $adjunto_id = (int)mysqli_insert_id($c);
            mysqli_stmt_close($stmt);
        }
        bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                             SET tipo_documento = 'FACTURA_COMPRA', adjunto_id = ?, archivo_ruta = ?, estado = 'RECIBIDO', motivo = NULL, extraccion_id = NULL
                           WHERE empresa_id = ? AND bandeja_documento_id = ?",
                     "isii", [$adjunto_id, $ruta_rel, $empresa_id, $id]);
    } else {
        bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                             SET tipo_documento = 'EXTRACTO', estado = 'RECIBIDO', motivo = NULL, extraccion_id = NULL
                           WHERE empresa_id = ? AND bandeja_documento_id = ?",
                     "ii", [$empresa_id, $id]);
    }
    return bdj_obtener($c, $empresa_id, $id);
}

/**
 * Lee un documento de tipo EXTRACTO con IA (cacheando el resultado por sha256 para el asistente de revisión).
 */
function bdj_leer_extracto(mysqli $c, int $empresa_id, int $usuario_id, array $doc): array
{
    $id = (int)$doc['bandeja_documento_id'];
    bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos SET estado = 'LEYENDO'
                       WHERE empresa_id = ? AND bandeja_documento_id = ? AND estado IN ('RECIBIDO', 'LISTO', 'REVISAR', 'ERROR')",
                 "ii", [$empresa_id, $id]);
    $ruta = bdj_archivo_ruta($doc, $empresa_id);
    if (!$ruta || !is_file($ruta)) {
        throw new RuntimeException('No se encontró el archivo del extracto.');
    }
    try {
        $x = bco_extracto_desde_archivo_ia($c, $empresa_id, $ruta);
        $cuentas = bco_cuentas_banco($c, $empresa_id);
        $cta_sug = null;
        if (!empty($x['cuenta_archivo'])) {
            foreach ($cuentas as $cta) {
                if (trim((string)$cta['numero']) !== '' && bco_cuenta_coincide((string)$x['cuenta_archivo'], (string)$cta['numero'])) {
                    $cta_sug = $cta;
                    break;
                }
            }
        }
        if (!$cta_sug && count($cuentas) === 1) {
            $cta_sug = $cuentas[0];
        }
        $cant = count($x['movimientos'] ?? []);
        $datos = [
            '_tipo' => 'EXTRACTO',
            'cuenta_archivo' => $x['cuenta_archivo'] ?? null,
            'cuenta_id_sugerida' => $cta_sug ? (int)$cta_sug['tesoreria_cuenta_id'] : null,
            'cuenta_nombre' => $cta_sug ? (string)$cta_sug['tesoreria_cuenta'] : null,
            'banco_nombre' => $cta_sug ? (string)($cta_sug['banco'] ?? '') : null,
            'f_desde' => $x['f_desde'] ?? null,
            'f_hasta' => $x['f_hasta'] ?? null,
            'cantidad' => $cant,
            'debitos' => $x['debitos'] ?? 0.0,
            'creditos' => $x['creditos'] ?? 0.0,
            'saldo_inicial' => $x['saldo_inicial'] ?? null,
            'saldo_final' => $x['saldo_final'] ?? null,
        ];
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $cliente_id = (int)($doc['estudio_cliente_id'] ?? 0);
        $stmt = mysqli_prepare($c, "INSERT INTO gestion__documentos_extracciones
                (empresa_id, empresa_ia_id, estudio_cliente_id, usuario_id, adjunto_id, bandeja_documento_id, capa, resultado, mensaje, datos, fecha)
                VALUES (?, ?, ?, ?, 0, ?, 'IA', 'OK', NULL, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iiiiis", $empresa_id, $empresa_id, $cliente_id, $usuario_id, $id, $json);
        mysqli_stmt_execute($stmt);
        $ext_id = (int)mysqli_insert_id($c);
        mysqli_stmt_close($stmt);

        $periodo = (!empty($x['f_desde']) && !empty($x['f_hasta']))
            ? ' (' . date('d/m/Y', strtotime($x['f_desde'])) . ' al ' . date('d/m/Y', strtotime($x['f_hasta'])) . ')'
            : '';
        $motivo = "Extracto leído: $cant movimientos$periodo. Hacé clic en Revisar para clasificar gastos y generar el Resumen Bancario.";
        bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos SET estado = 'REVISAR', motivo = ?, extraccion_id = ?
                           WHERE empresa_id = ? AND bandeja_documento_id = ?",
                     "siii", [$motivo, $ext_id, $empresa_id, $id]);
    } catch (Throwable $e) {
        $msg = mb_substr($e->getMessage(), 0, 1000);
        bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos SET estado = 'REVISAR', motivo = ?
                           WHERE empresa_id = ? AND bandeja_documento_id = ?",
                     "sii", [$msg, $empresa_id, $id]);
    }
    return bdj_obtener($c, $empresa_id, $id);
}

// ---------------------------------------------------------------------------------------------------
// Página
// ---------------------------------------------------------------------------------------------------

function bdj_resumen(mysqli $c, int $empresa_id): array
{
    $out = array_fill_keys(BDJ_ESTADOS, 0);
    foreach (bdj_filas($c, "SELECT estado, COUNT(*) n FROM gestion__bandeja_documentos WHERE empresa_id = ? GROUP BY estado",
                       "i", [$empresa_id]) as $r) {
        $out[$r['estado']] = (int)$r['n'];
    }
    return [
        'por_estado' => $out,
        'pendientes' => $out['RECIBIDO'] + $out['LEYENDO'] + $out['ERROR'],
        'listos' => $out['LISTO'],
        'revisar' => $out['REVISAR'],
        'procesados_mes' => (int)(bdj_fila($c, "SELECT COUNT(*) n FROM gestion__bandeja_documentos
                                                  WHERE empresa_id = ? AND estado = 'PROCESADO' AND fecha_proceso >= ?",
                                             "is", [$empresa_id, date('Y-m-01')])['n'] ?? 0),
    ];
}

/**
 * Documentos de la empresa. $f: vista (pendientes | procesados | descartados | todos), desde, hasta (fecha de alta),
 * origen, texto (nombre del archivo, proveedor o CUIT leídos). Hasta 500 filas, las más nuevas primero.
 */
function bdj_listar(mysqli $c, int $empresa_id, array $f): array
{
    $vistas = [
        'pendientes' => BDJ_PENDIENTES,
        'procesados' => ['PROCESADO'],
        'descartados' => ['DESCARTADO', 'DUPLICADO'],
    ];
    $vista = (string)($f['vista'] ?? 'pendientes');
    $sql = "SELECT b.bandeja_documento_id, b.origen, b.tipo_documento, b.nombre_original, b.mime, b.tamanio_bytes, b.estado,
                   b.motivo, b.adjunto_id, b.duplicado_de_id, b.destino_tabla_id, b.destino_registro_id, b.fecha_alta,
                   b.fecha_proceso, b.enlace_id, be.etiqueta AS enlace_etiqueta, ec.razon_social AS cliente_razon_social,
                   u.usuario_nombre, x.datos, x.capa, x.resultado AS lectura_resultado,
                   fp.comprobante_pv AS f_pv, fp.comprobante_nro AS f_nro, fp.total AS f_total, fp.tabla_estado_registro_id AS f_estado,
                   ct.comprobante_tipo AS f_tipo_nombre, ef.entidad_nombre AS f_proveedor,
                   bi.importacion_id AS bi_id, bi.cantidad_movimientos AS bi_movs, tc.tesoreria_cuenta AS bi_cuenta
              FROM gestion__bandeja_documentos b
              LEFT JOIN gestion__bandeja_enlaces be ON be.enlace_id = b.enlace_id
              LEFT JOIN estudio__clientes ec ON ec.cliente_id = b.estudio_cliente_id
              LEFT JOIN conf__usuarios u ON u.usuario_id = b.usuario_id
              LEFT JOIN gestion__documentos_extracciones x ON x.extraccion_id = b.extraccion_id AND x.empresa_id = b.empresa_id
              LEFT JOIN gestion__facturas_proveedores fp ON b.destino_tabla_id = " . BDJ_TABLA_FACTURAS_PROVEEDORES . "
                        AND fp.factura_proveedor_id = b.destino_registro_id AND fp.empresa_id = b.empresa_id
              LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id
              LEFT JOIN gestion__entidades ef ON ef.entidad_id = fp.entidad_id
              LEFT JOIN gestion__bancos_importaciones bi ON b.destino_tabla_id = " . BDJ_TABLA_BANCOS_IMPORTACIONES . "
                        AND bi.importacion_id = b.destino_registro_id AND bi.empresa_id = b.empresa_id
              LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = bi.tesoreria_cuenta_id AND tc.empresa_id = bi.empresa_id
             WHERE b.empresa_id = ?";
    $tipos = "i";
    $params = [$empresa_id];
    if (isset($vistas[$vista])) {
        $sql .= " AND b.estado IN ('" . implode("','", $vistas[$vista]) . "')";   // constantes, no datos del request
    }
    if (!empty($f['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$f['desde'])) {
        $sql .= " AND b.fecha_alta >= ?";
        $tipos .= "s";
        $params[] = $f['desde'] . ' 00:00:00';
    }
    if (!empty($f['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$f['hasta'])) {
        $sql .= " AND b.fecha_alta <= ?";
        $tipos .= "s";
        $params[] = $f['hasta'] . ' 23:59:59';
    }
    if (!empty($f['origen']) && in_array($f['origen'], ['PANTALLA', 'CARPETA', 'PORTAL', 'ANTERIOR'], true)) {
        $sql .= " AND b.origen = ?";
        $tipos .= "s";
        $params[] = $f['origen'];
    }
    $sql .= " ORDER BY b.bandeja_documento_id DESC LIMIT 500";

    $texto = mb_strtolower(trim((string)($f['texto'] ?? '')));
    $out = [];
    foreach (bdj_filas($c, $sql, $tipos, $params) as $r) {
        $leido = bdj_resumen_lectura($r['datos']);
        unset($r['datos']);
        $r += $leido;
        if ($texto !== '') {
            $pajar = mb_strtolower($r['nombre_original'] . ' ' . $leido['proveedor'] . ' ' . $leido['cuit'] . ' ' . ($r['f_proveedor'] ?? '') . ' ' . ($r['enlace_etiqueta'] ?? '') . ' ' . ($r['cliente_razon_social'] ?? ''));
            if (mb_strpos($pajar, $texto) === false) continue;
        }
        $out[] = $r;
    }
    return $out;
}

/** Lo que se muestra de una lectura: proveedor, CUIT, comprobante, fecha y total leídos. */
function bdj_resumen_lectura(?string $datos): array
{
    $vacio = ['proveedor' => '', 'cuit' => '', 'comprobante' => '', 'fecha' => '', 'total' => null];
    $d = $datos !== null ? json_decode($datos, true) : null;
    if (!is_array($d)) return $vacio;
    if (($d['_tipo'] ?? '') === 'EXTRACTO') {
        $cant = (int)($d['cantidad'] ?? 0);
        $cta = (string)($d['cuenta_nombre'] ?: ($d['cuenta_archivo'] ? 'Cta. ' . $d['cuenta_archivo'] : ''));
        return [
            'proveedor' => $cta !== '' ? $cta : 'Extracto bancario',
            'cuit' => (string)($d['banco_nombre'] ?? ''),
            'comprobante' => 'Extracto bancario' . ($cant > 0 ? " ($cant mov.)" : ''),
            'fecha' => (string)($d['f_hasta'] ?? $d['f_desde'] ?? ''),
            'total' => isset($d['debitos']) ? abs((float)$d['debitos']) : null,
        ];
    }
    $r = ResultadoExtraccion::desdeArray($d);
    $pv = (int)$r->valor('punto_venta');
    $nro = (int)$r->valor('numero');
    $letra = (string)($r->valor('letra') ?? '');
    $codigo = (int)$r->valor('tipo_comprobante');
    $clase = (isset(FL_TIPOS_ARCA[$codigo]) ? (FL_NOMBRES_CLASE[FL_TIPOS_ARCA[$codigo][0]] ?? '') : '');
    return [
        'proveedor' => (string)($r->valor('emisor_razon_social') ?? ''),
        'cuit' => preg_replace('/\D/', '', (string)($r->valor('emisor_cuit') ?? '')),
        'comprobante' => trim(($clase ?: 'Comprobante') . ' ' . $letra) . ($pv || $nro
            ? ' ' . str_pad((string)$pv, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string)$nro, 8, '0', STR_PAD_LEFT) : ''),
        'fecha' => (string)($r->valor('fecha_emision') ?? ''),
        'total' => $r->valor('total') !== null ? (float)$r->valor('total') : null,
    ];
}

/** Descarta documentos sin terminar o duplicados. Devuelve cuántos. */
function bdj_descartar(mysqli $c, int $empresa_id, int $usuario_id, array $ids, string $motivo): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return 0;
    $motivo = mb_substr(trim($motivo) !== '' ? trim($motivo) : 'Descartado por el usuario', 0, 1000);
    $n = 0;
    foreach ($ids as $id) {
        $n += bdj_ejecutar($c, "UPDATE gestion__bandeja_documentos
                                   SET estado = 'DESCARTADO', motivo = ?, usuario_proceso_id = ?, fecha_proceso = NOW()
                                 WHERE empresa_id = ? AND bandeja_documento_id = ?
                                   AND estado IN ('RECIBIDO', 'LISTO', 'REVISAR', 'ERROR', 'DUPLICADO')",
                           "siii", [$motivo, $usuario_id, $empresa_id, $id]);
    }
    error_log("=== bdj_descartar: $n documento(s) empresa $empresa_id usuario $usuario_id ===");
    return $n;
}

/** Ruta absoluta del archivo de un documento de la empresa, solo dentro de uploads/<tipo>/<empresa>/, o null. */
function bdj_archivo_ruta(array $doc, int $empresa_id): ?string
{
    $rel = str_replace('\\', '/', (string)$doc['archivo_ruta']);
    if (!preg_match('#^uploads/[a-z_]+/' . $empresa_id . '/[^/]+$#', $rel)) return null;
    $raiz = realpath(__DIR__ . '/../../uploads');
    $ruta = realpath(__DIR__ . '/../../' . $rel);
    if (!$raiz || !$ruta || strpos($ruta, $raiz . DIRECTORY_SEPARATOR) !== 0) return null;
    return $ruta;
}

// ---------------------------------------------------------------------------------------------------
// Enlaces externos de captura (Fase 2: portal público para clientes del estudio)
// ---------------------------------------------------------------------------------------------------

function bdj_enlace_url(string $token): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $folder = basename(dirname(dirname(__DIR__)));
    $base = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : ($protocol . '://' . $host . '/' . $folder);
    return rtrim($base, '/') . '/public/subir_documento?t=' . urlencode($token);
}

function bdj_enlace_crear(mysqli $c, int $empresa_id, ?int $usuario_id, array $data): array
{
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $token_prefijo = substr($token, 0, 8);
    $etiqueta = mb_substr(trim((string)($data['etiqueta'] ?? 'Comprobantes')), 0, 150);
    if ($etiqueta === '') $etiqueta = 'Recepción de comprobantes';
    $estudio_cliente_id = max(0, intval($data['estudio_cliente_id'] ?? 0));
    $f_venc = !empty($data['f_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['f_vencimiento'])
        ? (string)$data['f_vencimiento'] : null;
    $max_archivos = max(1, min(500, intval($data['max_archivos_dia'] ?? 50)));
    $max_bytes = max(1048576, min(52428800, intval($data['max_bytes_archivo'] ?? (15 * 1024 * 1024))));

    $stmt = mysqli_prepare($c, "INSERT INTO gestion__bandeja_enlaces
            (empresa_id, estudio_cliente_id, token_hash, token_prefijo, etiqueta, f_vencimiento, activo, max_archivos_dia, max_bytes_archivo, creado_por, fecha_alta)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iissssiii", $empresa_id, $estudio_cliente_id, $token_hash, $token_prefijo, $etiqueta, $f_venc, $max_archivos, $max_bytes, $usuario_id);
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($c);
    mysqli_stmt_close($stmt);

    $url = bdj_enlace_url($token);
    return [
        'enlace_id' => $id,
        'token' => $token,
        'token_prefijo' => $token_prefijo,
        'etiqueta' => $etiqueta,
        'url' => $url,
        'f_vencimiento' => $f_venc,
        'max_archivos_dia' => $max_archivos,
    ];
}

function bdj_enlace_listar(mysqli $c, int $empresa_id, int $estudio_cliente_id = 0): array
{
    $sql = "SELECT e.enlace_id, e.empresa_id, e.estudio_cliente_id, e.token_prefijo, e.etiqueta,
                   e.f_vencimiento, e.activo, e.max_archivos_dia, e.max_bytes_archivo, e.fecha_alta,
                   u.usuario_nombre AS creado_por_nombre,
                   (SELECT COUNT(*) FROM gestion__bandeja_documentos d WHERE d.enlace_id = e.enlace_id) AS total_subidos,
                   (SELECT COUNT(*) FROM gestion__bandeja_documentos d WHERE d.enlace_id = e.enlace_id AND DATE(d.fecha_alta) = CURDATE()) AS subidos_hoy
              FROM gestion__bandeja_enlaces e
              LEFT JOIN conf__usuarios u ON u.usuario_id = e.creado_por
             WHERE e.empresa_id = ?";
    $tipos = "i";
    $params = [$empresa_id];
    if ($estudio_cliente_id > 0) {
        $sql .= " AND e.estudio_cliente_id = ?";
        $tipos .= "i";
        $params[] = $estudio_cliente_id;
    }
    $sql .= " ORDER BY e.enlace_id DESC";
    return bdj_filas($c, $sql, $tipos, $params);
}

function bdj_enlace_revocar(mysqli $c, int $empresa_id, int $enlace_id): bool
{
    return bdj_ejecutar($c, "UPDATE gestion__bandeja_enlaces SET activo = 0 WHERE empresa_id = ? AND enlace_id = ?",
                        "ii", [$empresa_id, $enlace_id]) > 0;
}

function bdj_enlace_validar(mysqli $c, string $token): ?array
{
    $token = trim($token);
    if (strlen($token) < 16) return null;
    $hash = hash('sha256', $token);
    $r = bdj_fila($c, "SELECT e.*, emp.empresa AS empresa_nombre, ec.razon_social AS cliente_razon_social
                         FROM gestion__bandeja_enlaces e
                         JOIN conf__empresas emp ON emp.empresa_id = e.empresa_id
                         LEFT JOIN estudio__clientes ec ON ec.cliente_id = e.estudio_cliente_id
                        WHERE e.token_hash = ? AND e.activo = 1
                          AND (e.f_vencimiento IS NULL OR e.f_vencimiento >= CURDATE())
                        LIMIT 1", "s", [$hash]);
    if (!$r) return null;
    $subidos_hoy = (int)(bdj_fila($c, "SELECT COUNT(*) n FROM gestion__bandeja_documentos WHERE enlace_id = ? AND DATE(fecha_alta) = CURDATE()", "i", [(int)$r['enlace_id']])['n'] ?? 0);
    $r['subidos_hoy'] = $subidos_hoy;
    $r['cupo_disponible'] = max(0, (int)$r['max_archivos_dia'] - $subidos_hoy);
    return $r;
}

// ---------------------------------------------------------------------------------------------------
// Asistente de Revisión de Extractos y Gastos Bancarios (C_RB) en Bandeja de Documentos
// ---------------------------------------------------------------------------------------------------

/**
 * Estado completo del asistente para revisar un documento EXTRACTO en la bandeja:
 *   - Si aún no fue importado en gestion__bancos_importaciones para la cuenta elegida, devuelve la previsualización
 *     (bco_importar_previsualizar) con las líneas pre-clasificadas por reglas.
 *   - Si ya fue importado, devuelve las líneas de ese lote (bco_extracto_lineas), los meses del extracto / pendientes
 *     y la vista previa del comprobante Resumen Bancario (C_RB) del mes seleccionado (bco_gastos_resumen).
 */
function bdj_ext_estado(mysqli $c, int $empresa_id, int $id, int $cuenta_id = 0, string $mes = '', array $excluir = []): array
{
    $doc = bdj_obtener($c, $empresa_id, $id);
    if (!$doc) throw new RuntimeException('Documento no encontrado en la bandeja.');
    $ruta = bdj_archivo_ruta($doc, $empresa_id);
    if (!$ruta || !is_file($ruta)) throw new RuntimeException('Archivo del extracto no encontrado.');

    $cuentas = bco_cuentas_banco($c, $empresa_id);
    $conceptos = bco_conceptos_listar($c, $empresa_id);
    $productos = bco_productos_servicio($c, $empresa_id);
    $jurisdicciones = bco_jurisdicciones_iibb($c, $empresa_id);
    $puntos_venta = function_exists('pagoPuntosVentaOp') ? pagoPuntosVentaOp($c, $empresa_id) : [];

    // ¿Ya existe una importación vigente de este archivo en la empresa?
    $imp_previa = bdj_fila($c, "SELECT importacion_id, tesoreria_cuenta_id, f_desde, f_hasta FROM gestion__bancos_importaciones
                                 WHERE empresa_id = ? AND archivo_hash = ? AND tabla_estado_registro_id = 5
                                 ORDER BY importacion_id DESC LIMIT 1", "is", [$empresa_id, (string)$doc['sha256']]);

    if ($cuenta_id <= 0) {
        if ($imp_previa) {
            $cuenta_id = (int)$imp_previa['tesoreria_cuenta_id'];
        } elseif (!empty($doc['extraccion_id'])) {
            $ext = bdj_fila($c, "SELECT datos FROM gestion__documentos_extracciones WHERE extraccion_id = ? AND empresa_id = ?", "ii", [(int)$doc['extraccion_id'], $empresa_id]);
            $d = $ext && $ext['datos'] ? json_decode($ext['datos'], true) : null;
            if (!empty($d['cuenta_id_sugerida'])) {
                $cuenta_id = (int)$d['cuenta_id_sugerida'];
            }
        }
        if ($cuenta_id <= 0 && count($cuentas) === 1) {
            $cuenta_id = (int)$cuentas[0]['tesoreria_cuenta_id'];
        }
    }

    $out = [
        'doc' => [
            'id' => (int)$doc['bandeja_documento_id'],
            'nombre_original' => $doc['nombre_original'],
            'mime' => $doc['mime'],
            'estado' => $doc['estado'],
            'tipo_documento' => $doc['tipo_documento'],
        ],
        'cuentas' => $cuentas,
        'conceptos' => $conceptos,
        'productos' => $productos,
        'jurisdicciones' => $jurisdicciones,
        'puntos_venta' => $puntos_venta,
        'cuenta_id' => $cuenta_id,
        'importado' => false,
        'importacion_id' => 0,
        'previa' => null,
        'lineas_extracto' => [],
        'meses' => [],
        'mes_actual' => '',
        'resumen_gastos' => null,
    ];

    if ($cuenta_id <= 0) {
        return $out;
    }

    $imp = bdj_fila($c, "SELECT importacion_id, f_desde, f_hasta FROM gestion__bancos_importaciones
                          WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND archivo_hash = ? AND tabla_estado_registro_id = 5
                          ORDER BY importacion_id DESC LIMIT 1", "iis", [$empresa_id, $cuenta_id, (string)$doc['sha256']]);

    if ($imp) {
        $imp_id = (int)$imp['importacion_id'];
        $out['importado'] = true;
        $out['importacion_id'] = $imp_id;
        $lineas = bco_extracto_lineas($c, $empresa_id, ['importacion_id' => $imp_id]);
        $out['lineas_extracto'] = $lineas;

        // Meses cubiertos por este extracto y meses con gastos pendientes de la cuenta
        $pend_map = [];
        foreach (bco_gastos_pendientes($c, $empresa_id, $cuenta_id) as $p) {
            $pend_map[$p['mes']] = [
                'mes' => $p['mes'],
                'lineas' => (int)$p['lineas'],
                'importe' => (float)$p['importe'],
                'sin_concepto' => (int)$p['sin_concepto'],
            ];
        }
        foreach ($lineas as $l) {
            $m = substr((string)$l['f_movimiento'], 0, 7);
            if ($m !== '' && !isset($pend_map[$m])) {
                $pend_map[$m] = ['mes' => $m, 'lineas' => 0, 'importe' => 0.0, 'sin_concepto' => 0];
            }
        }
        ksort($pend_map);
        $out['meses'] = array_values($pend_map);

        $mes_sel = '';
        if ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes_sel = $mes;
        } else {
            foreach ($out['meses'] as $m_info) {
                if ($m_info['lineas'] > 0 || $m_info['sin_concepto'] > 0) {
                    $mes_sel = $m_info['mes'];
                    break;
                }
            }
            if ($mes_sel === '' && $out['meses']) {
                $mes_sel = $out['meses'][0]['mes'];
            }
        }
        $out['mes_actual'] = $mes_sel;
        if ($mes_sel !== '') {
            $out['resumen_gastos'] = bco_gastos_resumen($c, $empresa_id, $cuenta_id, $mes_sel, $excluir);
        }
    } else {
        $out['previa'] = bco_importar_previsualizar($c, $empresa_id, $cuenta_id, 0, $ruta);
    }

    return $out;
}

/**
 * Importa el archivo del extracto de la bandeja en la cuenta bancaria elegida y aplica las asignaciones manuales
 * hechas en la vista previa (aprendiendo sus reglas). Devuelve el estado actualizado (bdj_ext_estado).
 */
function bdj_ext_importar(mysqli $c, int $empresa_id, int $usuario_id, int $id, int $cuenta_id, array $asignaciones = []): array
{
    $doc = bdj_obtener($c, $empresa_id, $id);
    if (!$doc) throw new RuntimeException('Documento no encontrado en la bandeja.');
    $ruta = bdj_archivo_ruta($doc, $empresa_id);
    if (!$ruta || !is_file($ruta)) throw new RuntimeException('Archivo del extracto no encontrado.');

    $imp = bdj_fila($c, "SELECT importacion_id FROM gestion__bancos_importaciones
                          WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND archivo_hash = ? AND tabla_estado_registro_id = 5
                          LIMIT 1", "iis", [$empresa_id, $cuenta_id, (string)$doc['sha256']]);
    if (!$imp) {
        $r = bco_importar_confirmar($c, $empresa_id, $usuario_id, $cuenta_id, 0, $ruta, (string)$doc['nombre_original']);
        $imp_id = (int)$r['importacion_id'];
    } else {
        $imp_id = (int)$imp['importacion_id'];
    }

    // Si el usuario eligió conceptos en la previsualización antes de importar, aplicarlos ahora
    if ($asignaciones) {
        $lineas = bco_extracto_lineas($c, $empresa_id, ['importacion_id' => $imp_id]);
        $por_concepto = [];
        foreach ($lineas as $l) {
            $nro = (int)$l['nro_linea'];
            if (isset($asignaciones[$nro])) {
                $cid = (int)$asignaciones[$nro];
                if ($cid > 0 && (int)($l['concepto_id'] ?? 0) !== $cid) {
                    $por_concepto[$cid][] = (int)$l['extracto_id'];
                }
            }
        }
        foreach ($por_concepto as $cid => $ext_ids) {
            bco_lineas_clasificar($c, $empresa_id, $usuario_id, $ext_ids, 'asignar', (int)$cid);
        }
    }

    return bdj_ext_estado($c, $empresa_id, $id, $cuenta_id);
}
