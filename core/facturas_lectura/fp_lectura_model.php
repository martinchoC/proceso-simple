<?php
/**
 * Facturas de proveedores - carga desde archivo (PDF / JPG / PNG).
 *
 * El extractor (core/facturas_lectura/FacturaExtractor.php) lee el archivo; acá se resuelve lo que depende de la
 * empresa y se arma la propuesta para el formulario de siempre, que el usuario revisa y guarda en Borrador:
 *   - tipo de compras por código ARCA, proveedor por CUIT, duplicados, alícuotas (lo mismo que la importación de
 *     ARCA: fpArcaTipoComprobante, fpArcaBuscarProveedor, fpArcaAlicuotas, fpArcaUltimaImputacion, fpArcaGastosVarios);
 *   - una línea por alícuota con la imputación de la última compra del proveedor o "Gastos varios"; los ítems cuyo
 *     código de proveedor coincide con un producto (gestion__productos_proveedores.codigo_proveedor) van como
 *     líneas propias y el resto de cada alícuota queda en la línea de imputación; los demás ítems, como referencia;
 *     Si el proveedor tiene compra_lectura_items = 0 (servicios: teléfono, gas, luz) no se cargan ítems: solo una
 *     línea por alícuota. Un descuento general al pie (descuento_general / _pct leídos) con ítems a precio de lista
 *     se aplica a los ítems cargados (descuento_general_pct de cada línea): precio de lista → precio a importar;
 *   - percepciones: IVA directo a la configuración de la empresa; IIBB por provincia leída, y si no, pendiente;
 *   - archivo original en uploads/facturas_proveedores/<empresa>/ (gestion__facturas_proveedores_adjuntos) y log
 *     de cada lectura en gestion__documentos_extracciones, donde también se cuenta el tope diario de IA.
 * Mismo código para Gestión (2) y Gestión Normal (5): lo incluye modules/<carpeta>/facturas_proveedores_lectura_model.php,
 * que fija FPL_PAGINA_IDX (57 / 557, la página de Facturas de proveedores del módulo). Requiere el
 * facturas_proveedores_model.php de ese módulo (lo incluyen el _ajax.php y el cron antes que este archivo).
 * Script: _local/sql/mantenimiento/20261027_fp_lectura_archivo_01.sql
 * Bandeja de captura (2026-11-09, core/bandeja/, script 20261109_bandeja_documentos_01.sql): cada archivo guardado
 * queda en gestion__bandeja_documentos (origen PANTALLA o CARPETA) y cada lectura / alta en la factura actualiza su
 * estado. El log de lecturas guarda además quién paga la IA (empresa_ia_id) y el cliente del estudio.
 */

require_once __DIR__ . '/FacturaExtractor.php';
require_once __DIR__ . '/fp_lectura_preferencias.php';
require_once __DIR__ . '/../bandeja/bandeja_service.php';

if (!defined('FPL_PAGINA_IDX')) {
    throw new LogicException('fp_lectura_model.php: falta definir FPL_PAGINA_IDX (incluirlo desde el modelo del módulo).');
}

const FPL_DIR_REL = 'uploads/facturas_proveedores';
const FPL_MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
const FPL_ETIQUETAS_CAPA = ['QR' => 'QR ARCA', 'TEXTO' => 'Texto PDF', 'OCR' => 'OCR', 'IA' => 'IA'];

// ---------------------------------------------------------------------------------------------------
// Archivo
// ---------------------------------------------------------------------------------------------------

/**
 * Valida y guarda el archivo subido (tipo real por finfo, tamaño máximo de la configuración, nombre aleatorio,
 * carpeta de la empresa sin acceso web). Devuelve el adjunto y, si el mismo archivo ya está en una factura, cuál.
 */
function fplGuardarArchivo($conexion, $empresa_id, $usuario_id, $archivo)
{
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        $err = intval($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        throw new RuntimeException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'El archivo es demasiado grande.' : 'No se recibió el archivo.');
    }
    return fplGuardarArchivoDesdeRuta($conexion, $empresa_id, $usuario_id, $archivo['tmp_name'], (string)($archivo['name'] ?? ''), true);
}

/**
 * Igual que fplGuardarArchivo pero desde un archivo del servidor (cron de la carpeta de entrada): lo copia, el
 * original lo mueve después el cron a procesados / no_procesados. $subido = true: archivo de $_FILES (se mueve).
 */
function fplGuardarArchivoDesdeRuta($conexion, $empresa_id, $usuario_id, $origen, $nombre_original, bool $subido = false, ?string $origen_bandeja = null, int $estudio_cliente_id = 0, ?int $enlace_id = null, ?int $requerimiento_id = null)
{
    $e = intval($empresa_id);
    $tamanio = is_file($origen) ? intval(filesize($origen)) : 0;
    $max = intval(fl_config()['max_bytes']);
    if ($tamanio <= 0 || $tamanio > $max) {
        throw new RuntimeException($tamanio <= 0 ? 'El archivo está vacío.' : 'El archivo no puede superar ' . round($max / 1048576) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($origen);
    $ext = FPL_MIMES[$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('El archivo tiene que ser PDF, JPG o PNG.');
    }
    if ($ext !== 'pdf' && @getimagesize($origen) === false) {
        throw new RuntimeException('La imagen está dañada o no se puede abrir.');
    }

    $base = __DIR__ . '/../../' . FPL_DIR_REL;
    $dir = $base . '/' . $e;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de archivos.');
    }
    if (!file_exists($base . '/.htaccess')) {
        @file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $nombre = date('Ymd') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $ok = $subido ? move_uploaded_file($origen, $dir . '/' . $nombre) : copy($origen, $dir . '/' . $nombre);
    if (!$ok) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    $ruta = FPL_DIR_REL . '/' . $e . '/' . $nombre;
    $sha = hash_file('sha256', $dir . '/' . $nombre);
    $original = mb_substr(basename(str_replace('\\', '/', $nombre_original !== '' ? $nombre_original : 'archivo.' . $ext)), 0, 255);
    $uid = intval($usuario_id);

    // ¿El mismo archivo ya está en una factura de la empresa?
    $stmt = mysqli_prepare($conexion, "SELECT a.factura_proveedor_id, f.comprobante_pv, f.comprobante_nro, e.entidad_nombre
            FROM gestion__facturas_proveedores_adjuntos a
            JOIN gestion__facturas_proveedores f ON f.factura_proveedor_id = a.factura_proveedor_id AND f.empresa_id = a.empresa_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
           WHERE a.empresa_id = ? AND a.sha256 = ? AND a.tabla_estado_registro_id = 1 AND f.tabla_estado_registro_id <> 6
           ORDER BY a.adjunto_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $e, $sha);
    mysqli_stmt_execute($stmt);
    $previo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__facturas_proveedores_adjuntos
            (empresa_id, factura_proveedor_id, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256, usuario_id, fecha_alta, tabla_estado_registro_id)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    mysqli_stmt_bind_param($stmt, "isssisi", $e, $ruta, $original, $mime, $tamanio, $sha, $uid);
    mysqli_stmt_execute($stmt);
    $adjunto_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    error_log("=== fplGuardarArchivo adjunto $adjunto_id empresa $e ($mime, $tamanio bytes) ===");

    // Bandeja de captura: archivo subido desde una pantalla o tomado de la carpeta del cron o portal
    $bandeja = bdj_registrar($conexion, $e, $uid, [
        'adjunto_id' => $adjunto_id, 'archivo_ruta' => $ruta, 'nombre_original' => $original, 'mime' => $mime,
        'tamanio_bytes' => $tamanio, 'sha256' => $sha,
    ], $origen_bandeja ?? ($subido ? 'PANTALLA' : 'CARPETA'), $estudio_cliente_id, $enlace_id, $requerimiento_id);

    return ['adjunto_id' => $adjunto_id, 'ruta_abs' => $dir . '/' . $nombre, 'mime' => $mime, 'previo' => $previo,
            'bandeja_documento_id' => $bandeja['bandeja_documento_id'], 'bandeja_estado' => $bandeja['estado']];
}

/** Adjunto de la empresa con su ruta absoluta (solo dentro de la carpeta de la empresa), o null. */
function fplAdjunto($conexion, $empresa_id, $adjunto_id)
{
    $e = intval($empresa_id);
    $id = intval($adjunto_id);
    $stmt = mysqli_prepare($conexion, "SELECT adjunto_id, factura_proveedor_id, archivo_ruta, nombre_original, mime, tamanio_bytes, fecha_alta
            FROM gestion__facturas_proveedores_adjuntos WHERE adjunto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "ii", $id, $e);
    mysqli_stmt_execute($stmt);
    $a = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$a) return null;
    $base = realpath(__DIR__ . '/../../' . FPL_DIR_REL . '/' . $e);
    $ruta = realpath(__DIR__ . '/../../' . $a['archivo_ruta']);
    if (!$base || !$ruta || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0) return null;
    $a['ruta_abs'] = $ruta;
    return $a;
}

/** Adjuntos de una factura de la empresa (para verlos desde el formulario). */
function fplAdjuntosFactura($conexion, $empresa_id, $factura_proveedor_id)
{
    $e = intval($empresa_id);
    $f = intval($factura_proveedor_id);
    $stmt = mysqli_prepare($conexion, "SELECT adjunto_id, nombre_original, mime, tamanio_bytes, fecha_alta
            FROM gestion__facturas_proveedores_adjuntos
           WHERE empresa_id = ? AND factura_proveedor_id = ? AND tabla_estado_registro_id = 1 ORDER BY adjunto_id");
    mysqli_stmt_bind_param($stmt, "ii", $e, $f);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Después de "agregar": liga el adjunto (todavía sin factura) a la factura nueva y le copia el CAE y su vencimiento
 * de la última lectura (los toma el servidor, no el navegador). Fuera de la transacción del alta: si falla, la
 * factura queda igual y se registra en el log.
 */
function fplVincularAdjunto($conexion, $empresa_id, $factura_proveedor_id, $adjunto_id, $usuario_id = 0)
{
    $e = intval($empresa_id);
    $f = intval($factura_proveedor_id);
    $a = intval($adjunto_id);
    if ($f <= 0 || $a <= 0) return false;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores_adjuntos SET factura_proveedor_id = ?
            WHERE adjunto_id = ? AND empresa_id = ? AND factura_proveedor_id IS NULL");
    mysqli_stmt_bind_param($stmt, "iii", $f, $a, $e);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    if (!$ok) {
        error_log("fplVincularAdjunto: adjunto $a no se pudo ligar a la factura $f (empresa $e)");
        return false;
    }
    // Bandeja: procesado (el alta desde el formulario no pasa el usuario: el de la sesión que validó ajax_guard)
    $uid = intval($usuario_id) ?: intval($_SESSION['usuario_id'] ?? 0);
    bdj_procesado($conexion, $e, $a, BDJ_TABLA_FACTURAS_PROVEEDORES, $f, $uid);
    $lectura = fplUltimaLectura($conexion, $e, $a);
    if ($lectura) {
        $cae = preg_match('/^\d{14}$/', (string)$lectura->valor('cae')) ? (string)$lectura->valor('cae') : null;
        $vto = fl_fecha((string)$lectura->valor('cae_vencimiento'));
        if ($cae !== null || $vto !== null) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__facturas_proveedores SET CAE = COALESCE(?, CAE), f_vto_CAE = COALESCE(?, f_vto_CAE)
                    WHERE factura_proveedor_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3");
            mysqli_stmt_bind_param($stmt, "ssii", $cae, $vto, $f, $e);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    error_log("=== fplVincularAdjunto: adjunto $a -> factura $f ===");
    return true;
}

// ---------------------------------------------------------------------------------------------------
// IA: habilitación por empresa, tope diario y log
// ---------------------------------------------------------------------------------------------------

/**
 * Clave de la API de la IA de la empresa (ia_api_key_cifrada de gestion__empresas_parametros, descifrada), o ''
 * si no tiene o no se puede descifrar. Solo para armar la CapaIa: nunca se devuelve al navegador ni se loguea.
 */
function fplIaClaveEmpresa($conexion, $empresa_id)
{
    return fl_ia_clave_empresa($conexion, intval($empresa_id));
}

/** CapaIa con la clave de la empresa (propia, de la principal o del servidor); null si no hay ninguna o está deshabilitada. */
function fplCapaIa($conexion, $empresa_id)
{
    require_once __DIR__ . '/../ia_empresa.php';
    if (!ia_empresa_habilitada($conexion, intval($empresa_id))) return null;
    return CapaIa::desdeConfig(fplIaClaveEmpresa($conexion, $empresa_id));
}

/**
 * Habilitación (Configuración > Empresas, core/ia_empresa.php), tope diario de la vigencia de parámetros a hoy
 * (ia_lectura_tope_diario, 20 si no hay), usos de hoy y si hay API key.
 */
function fplEstadoIa($conexion, $empresa_id)
{
    require_once __DIR__ . '/../ia_empresa.php';
    $e = intval($empresa_id);
    $estado = ['habilitada' => ia_empresa_habilitada($conexion, $e), 'tope' => 20, 'usos_hoy' => 0, 'configurada' => fplCapaIa($conexion, $e) !== null];
    $col = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'ia_lectura_tope_diario'");
    if ($col && mysqli_num_rows($col) > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT ia_lectura_tope_diario FROM gestion__empresas_parametros
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $e);
        mysqli_stmt_execute($stmt);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($p) {
            $estado['tope'] = max(0, intval($p['ia_lectura_tope_diario']));
        }
    }
    // El tope se cuenta por la empresa que paga la IA (en un documento de un cliente del estudio, el estudio)
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) FROM gestion__documentos_extracciones WHERE empresa_ia_id = ? AND capa = 'IA' AND fecha >= CURDATE()");
    mysqli_stmt_bind_param($stmt, "i", $e);
    mysqli_stmt_execute($stmt);
    $estado['usos_hoy'] = intval(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
    mysqli_stmt_close($stmt);
    $estado['disponible'] = $estado['habilitada'] && $estado['configurada'] && $estado['usos_hoy'] < $estado['tope'];
    if (!$estado['habilitada']) $estado['motivo'] = 'La IA está deshabilitada para esta empresa (Configuración > Empresas).';
    elseif (!$estado['configurada']) $estado['motivo'] = 'Falta la clave de la API de la IA: cargala en Configuración > Empresas (en la empresa o en la principal).';
    elseif (!$estado['disponible']) $estado['motivo'] = 'Se alcanzó el tope de ' . $estado['tope'] . ' lecturas con IA de hoy.';
    return $estado;
}

function fplLog($conexion, $empresa_id, $usuario_id, $adjunto_id, $capa, $resultado, $mensaje, $datos, array $uso = [], $ms = null)
{
    $e = intval($empresa_id);
    $u = intval($usuario_id);
    $a = intval($adjunto_id);
    $capa = mb_substr((string)$capa, 0, 10);
    $resultado = mb_substr((string)$resultado, 0, 15);
    $mensaje = $mensaje !== null ? mb_substr((string)$mensaje, 0, 500) : null;
    $json = $datos !== null ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null;
    $prov = $uso['proveedor'] ?? null;
    $modelo = $uso['modelo'] ?? null;
    $te = isset($uso['tokens_entrada']) ? intval($uso['tokens_entrada']) : null;
    $ts = isset($uso['tokens_salida']) ? intval($uso['tokens_salida']) : null;
    $costo = isset($uso['costo']) ? floatval($uso['costo']) : null;
    $ms = $ms !== null ? intval($ms) : null;
    // Documento de la bandeja y quién paga la IA. Fase 1: la empresa del documento (fase 2: un documento de un cliente
    // del estudio lo paga el estudio, decisión 1 del doc 15)
    $doc = bdj_por_adjunto($conexion, $e, $a);
    $bandeja_id = $doc ? intval($doc['bandeja_documento_id']) : null;
    $cliente = $doc ? intval($doc['estudio_cliente_id']) : 0;
    $empresa_ia = $e;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__documentos_extracciones
            (empresa_id, empresa_ia_id, estudio_cliente_id, usuario_id, adjunto_id, bandeja_documento_id, capa, proveedor_ia, modelo,
             tokens_entrada, tokens_salida, costo_estimado, duracion_ms, resultado, mensaje, datos, fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iiiiiisssiidisss", $e, $empresa_ia, $cliente, $u, $a, $bandeja_id, $capa, $prov, $modelo,
                           $te, $ts, $costo, $ms, $resultado, $mensaje, $json);
    mysqli_stmt_execute($stmt);
    $id = intval(mysqli_insert_id($conexion));
    mysqli_stmt_close($stmt);
    if ($bandeja_id && $json !== null) {   // última lectura con datos del documento (la que muestra la bandeja)
        bdj_ejecutar($conexion, "UPDATE gestion__bandeja_documentos SET extraccion_id = ? WHERE bandeja_documento_id = ? AND empresa_id = ?",
                     "iii", [$id, $bandeja_id, $e]);
    }
    return $id;
}

/**
 * Estado de la bandeja que corresponde a una propuesta: LISTO, REVISAR (con los motivos) o ERROR (no se leyó nada).
 * Devuelve [estado, motivo].
 */
function fplEstadoBandeja(array $p)
{
    if (!empty($p['listo'])) return ['LISTO', null];
    if (empty($p['capas'])) {   // no se leyó nada: la causa es la lectura, no cada campo
        return ['ERROR', !empty($p['ia']['error']) ? 'IA: ' . $p['ia']['error'] : 'No se pudieron leer datos del archivo'];
    }
    $motivos = fplMotivosNoListo($p);
    if (!empty($p['proveedor_nuevo'])) $motivos[] = 'Proveedor no cargado: darlo de alta al revisarlo';
    if (!empty($p['ia']['error'])) $motivos[] = 'IA: ' . $p['ia']['error'];
    return ['REVISAR', implode('; ', array_unique($motivos)) ?: 'Datos incompletos'];
}

/** Último resultado leído de un adjunto (para rearmar la propuesta sin volver a leer). */
function fplUltimaLectura($conexion, $empresa_id, $adjunto_id)
{
    $e = intval($empresa_id);
    $a = intval($adjunto_id);
    $stmt = mysqli_prepare($conexion, "SELECT datos FROM gestion__documentos_extracciones
            WHERE empresa_id = ? AND adjunto_id = ? AND datos IS NOT NULL AND resultado IN ('OK', 'INCOMPLETO')
            ORDER BY extraccion_id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $e, $a);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $datos = $row ? json_decode((string)$row['datos'], true) : null;
    return is_array($datos) ? ResultadoExtraccion::desdeArray($datos) : null;
}

// ---------------------------------------------------------------------------------------------------
// Proceso
// ---------------------------------------------------------------------------------------------------

/**
 * Lee el archivo directamente con IA (sin capas locales de QR / texto / OCR) cuando la empresa la tiene
 * habilitada y dentro del tope diario.
 */
function fplProcesar($conexion, $empresa_id, $usuario_id, array $adjunto, array $cliente = [], bool $forzar_ia = true)
{
    $e = intval($empresa_id);
    error_log("=== INICIO fplProcesar (IA directa) adjunto {$adjunto['adjunto_id']} empresa $e ===");
    $doc = new DocumentoFactura($adjunto['ruta_abs'], $adjunto['mime'], emp_cuit($conexion, $e), []);
    $extractor = new FacturaExtractor([]);
    $traza = [];
    $resultado = null;
    $validacion = null;
    bdj_leyendo($conexion, $e, intval($adjunto['adjunto_id']));

    $ia = fplEstadoIa($conexion, $e);
    $ia['usada'] = false;
    if ($ia['disponible']) {
        $capa_ia = fplCapaIa($conexion, $e);
        $t0 = microtime(true);
        $con_ia = $extractor->extraerConIa($doc, null, $capa_ia);
        $uso = $con_ia['uso'];
        $leyo = in_array(CapaIa::NOMBRE, $con_ia['resultado']->capas, true) && $uso['error'] === null;
        fplLog($conexion, $e, $usuario_id, $adjunto['adjunto_id'], CapaIa::NOMBRE, $leyo ? ($con_ia['completo'] ? 'OK' : 'INCOMPLETO') : 'ERROR',
               $uso['error'] ?? ($con_ia['validacion']['errores'] ? 'Falta / falla: ' . implode(', ', array_keys($con_ia['validacion']['errores'])) : null),
               $leyo ? $con_ia['resultado']->aArray() : null, $uso, intval((microtime(true) - $t0) * 1000));
        $traza = $con_ia['traza'];
        $ia['usos_hoy']++;
        $ia['disponible'] = $ia['usos_hoy'] < $ia['tope'];
        if ($leyo) {
            $resultado = $con_ia['resultado'];
            $validacion = $con_ia['validacion'];
            $ia['usada'] = true;
        } else {
            $ia['error'] = $uso['error'] ?? 'La IA no devolvió datos.';
        }
    } else {
        $ia['error'] = $ia['motivo'] ?? 'La lectura con IA no está disponible.';
    }

    if ($resultado === null) $resultado = new ResultadoExtraccion();
    if ($validacion === null) $validacion = ValidadorFactura::validar($resultado, $doc->cuitEmpresa);
    try {
        $propuesta = fplArmarPropuesta($conexion, $e, $resultado, $validacion);
    } catch (Throwable $ex) {   // que el documento no quede en LEYENDO
        bdj_lectura($conexion, $e, intval($adjunto['adjunto_id']), 'ERROR', $ex->getMessage());
        throw $ex;
    }
    $propuesta['adjunto_id'] = intval($adjunto['adjunto_id']);
    $propuesta['traza'] = $traza;
    $propuesta['ia'] = $ia;
    [$estado_bdj, $motivo_bdj] = fplEstadoBandeja($propuesta);
    bdj_lectura($conexion, $e, intval($adjunto['adjunto_id']), $estado_bdj, $motivo_bdj);
    return $propuesta;
}

/** Vuelve a armar la propuesta con la última lectura (p. ej. después de dar de alta el proveedor). */
function fplPropuestaGuardada($conexion, $empresa_id, $adjunto_id)
{
    $r = fplUltimaLectura($conexion, $empresa_id, $adjunto_id);
    if (!$r) throw new RuntimeException('No hay una lectura de este archivo.');
    $propuesta = fplArmarPropuesta($conexion, $empresa_id, $r, ValidadorFactura::validar($r, emp_cuit($conexion, intval($empresa_id))));
    $propuesta['adjunto_id'] = intval($adjunto_id);
    $propuesta['ia'] = fplEstadoIa($conexion, $empresa_id) + ['usada' => in_array(CapaIa::NOMBRE, $r->capas, true)];
    // El estado del documento puede cambiar (p. ej. REVISAR → LISTO al dar de alta el proveedor)
    $adj = fplAdjunto($conexion, $empresa_id, $adjunto_id);
    $propuesta['mime'] = $adj['mime'] ?? '';
    [$estado_bdj, $motivo_bdj] = fplEstadoBandeja($propuesta);
    bdj_lectura($conexion, intval($empresa_id), intval($adjunto_id), $estado_bdj, $motivo_bdj);
    return $propuesta;
}

/**
 * Da de alta el proveedor con el CUIT y la razón social de la lectura (no los del navegador), con la condición de
 * pago por defecto de la empresa. Lo confirma el usuario antes de llamar.
 */
function fplCrearProveedor($conexion, $empresa_id, $usuario_id, $adjunto_id)
{
    $e = intval($empresa_id);
    $r = fplUltimaLectura($conexion, $e, $adjunto_id);
    if (!$r) throw new RuntimeException('No hay una lectura de este archivo.');
    $cuit = preg_replace('/\D/', '', (string)$r->valor('emisor_cuit'));
    if (!fl_cuit_valido($cuit) || $cuit === emp_cuit($conexion, $e)) {
        throw new RuntimeException('El CUIT del emisor no es válido: corríjalo antes de dar de alta el proveedor.');
    }
    $condicion_pago_id = fplCondicionPagoDefecto($conexion, $e);
    if (!$condicion_pago_id) throw new RuntimeException('La empresa no tiene condiciones de pago.');
    $letra = strtoupper((string)$r->valor('letra'));
    mysqli_begin_transaction($conexion);
    try {
        $prov = fpAsegurarProveedorArca($conexion, $e, $cuit, trim((string)$r->valor('emisor_razon_social')), $condicion_pago_id, $letra, intval($usuario_id));
        mysqli_commit($conexion);
    } catch (Throwable $ex) {
        mysqli_rollback($conexion);
        error_log('fplCrearProveedor: ' . $ex->getMessage());
        throw $ex;
    }
    error_log("=== fplCrearProveedor: entidad {$prov['entidad_id']} (CUIT $cuit) empresa $e" . ($prov['creado'] ? ' creada' : ' existente') . " ===");
    return $prov;
}

function fplCondicionPagoDefecto($conexion, $empresa_id)
{
    $e = intval($empresa_id);
    $stmt = mysqli_prepare($conexion, "SELECT condicion_pago_defecto_id FROM gestion__empresas_parametros
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE())
            ORDER BY f_desde DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $e);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $condiciones = obtenerCondicionesPago($conexion, $e);
    $ids = array_map('intval', array_column($condiciones, 'condicion_pago_id'));
    $defecto = intval($p['condicion_pago_defecto_id'] ?? 0);
    return in_array($defecto, $ids, true) ? $defecto : ($ids[0] ?? 0);
}

/** Por qué una propuesta no quedó lista: errores por campo, percepciones sin asignar, proveedor nuevo. */
function fplMotivosNoListo(array $p)
{
    $motivos = [];
    foreach ((array)($p['errores'] ?? []) as $campo => $msg) {
        $motivos[] = $campo . ': ' . $msg;
    }
    foreach ((array)($p['percepciones_pendientes'] ?? []) as $pp) {
        $motivos[] = 'Percepción ' . $pp['tipo'] . ' $' . number_format((float)$pp['importe'], 2, ',', '.') . ': ' . $pp['motivo'];
    }
    if (empty($p['form']['detalles'])) $motivos[] = 'No se pudieron armar las líneas del comprobante';
    return $motivos;
}

/**
 * Guarda en Borrador la factura de un adjunto ya leído, sin pasar por el formulario: carga en lote ("Guardar los
 * listos") y cron de la carpeta de entrada. Solo si la propuesta quedó lista (sin errores, con proveedor, sin
 * percepciones pendientes ni duplicado). Usa la condición de pago del proveedor (o la de la empresa) para el
 * vencimiento, la moneda base y la sucursal pedida o la primera a la que el usuario tiene acceso.
 * Devuelve ['success', 'factura_proveedor_id' | 'message', 'motivos'].
 */
function fplGuardarBorrador($conexion, $empresa_id, $usuario_id, $adjunto_id, $sucursal_id = 0)
{
    $e = intval($empresa_id);
    $adjunto = fplAdjunto($conexion, $e, $adjunto_id);
    if (!$adjunto) return ['success' => false, 'message' => 'Archivo no encontrado.', 'motivos' => []];
    if (intval($adjunto['factura_proveedor_id']) > 0) {
        return ['success' => false, 'message' => 'Este archivo ya está cargado en una factura.', 'motivos' => []];
    }
    $p = fplPropuestaGuardada($conexion, $e, $adjunto['adjunto_id']);
    if (!$p['listo']) {
        return ['success' => false, 'message' => 'Hay datos para revisar.', 'motivos' => fplMotivosNoListo($p)];
    }
    $f = $p['form'];

    // Sucursal: la pedida si el usuario la puede usar, o la primera disponible
    $sucursales = array_map('intval', array_column(obtenerSucursalesEmpresa($conexion, $e), 'sucursal_id'));
    $sucursal_id = intval($sucursal_id);
    if (!$sucursal_id || !in_array($sucursal_id, $sucursales, true)) $sucursal_id = $sucursales[0] ?? 0;
    if (!$sucursal_id) {
        bdj_lectura($conexion, $e, intval($adjunto['adjunto_id']), 'REVISAR', 'El usuario no tiene acceso a ninguna sucursal de la empresa');
        return ['success' => false, 'message' => 'El usuario no tiene acceso a ninguna sucursal de la empresa.', 'motivos' => []];
    }

    // Condición de pago del proveedor (o la de la empresa) y vencimiento
    $cond_prov = obtenerCondicionesProveedor($conexion, $f['entidad_id'], $e);
    $condicion_pago_id = intval($cond_prov['condicion_pago_id'] ?? 0) ?: fplCondicionPagoDefecto($conexion, $e);
    $dias = 0;
    foreach (obtenerCondicionesPago($conexion, $e) as $c) {
        if (intval($c['condicion_pago_id']) === $condicion_pago_id) $dias = intval($c['dias_primer_vencimiento'] ?? 0);
    }
    $f_vencimiento = date('Y-m-d', strtotime($f['f_emision'] . " +$dias days"));

    $subtotal = $no_gravado = $exento = $iva = $descuentos = 0.0;
    foreach ($f['detalles'] as $d) {
        $descuentos += $d['descuento'];
        $subtotal += $d['neto_gravado'];
        $no_gravado += $d['no_gravado'];
        $exento += $d['exento'];
        $iva += $d['iva_importe'];
    }
    $otros = array_sum(array_map(fn($i) => (float)$i['importe'], $f['impuestos']));

    $data = [
        'comprobante_tipo_id' => intval($f['comprobante_tipo_id']),
        'comprobante_pv' => (string)$f['comprobante_pv'],
        'comprobante_nro' => (string)$f['comprobante_nro'],
        'sucursal_id' => $sucursal_id,
        'deposito_id' => 0,
        'entidad_id' => intval($f['entidad_id']),
        'entidad_sucursal_id' => intval($f['entidad_sucursal_id'] ?? 0),
        'f_emision' => $f['f_emision'],
        'f_contabilidad' => $f['f_contabilidad'],
        'f_vencimiento' => $f_vencimiento,
        'condicion_pago_id' => $condicion_pago_id,
        'moneda_id' => emp_moneda_base_id($conexion, $e),
        'tipo_cambio' => 1.0,
        'direccion' => '',
        'subtotal' => round($subtotal, 2),
        'descuentos' => round($descuentos, 2),
        'no_gravado' => round($no_gravado, 2),
        'exento' => round($exento, 2),
        'impuestos' => round($iva, 2),
        'otros_impuestos' => round($otros, 2),
        'total' => round($subtotal + $no_gravado + $exento + $iva + $otros, 2),
        'descuento_general_pct' => floatval($f['descuento_general_pct'] ?? 0),
        'modalidad_descuento_factura' => $f['modalidad_descuento_factura'] ?? ($cond_prov['modalidad_descuento_factura'] ?? 'EN_FACTURA_GLOBAL'),
        'precio_compra_contempla_descuento' => isset($f['precio_compra_contempla_descuento']) ? intval($f['precio_compra_contempla_descuento']) : (isset($cond_prov['precio_compra_contempla_descuento']) ? intval($cond_prov['precio_compra_contempla_descuento']) : 1),
        'descuento_nc_pct' => floatval($f['descuento_nc_pct'] ?? (($cond_prov['modalidad_descuento_factura'] ?? '') === 'NOTA_CREDITO' ? ($cond_prov['proveedor_descuento_general'] ?? 0) : 0)),
        'observaciones' => $f['observaciones'],
        'orden_compra_id' => 0,
        'comprobante_asociado_id' => 0,
        'tesoreria_cuenta_debito_id' => 0,
        'detalles' => $f['detalles'],
        'impuestos_adicionales' => $f['impuestos'],
        'empresa_idx' => $e,
        'pagina_idx' => FPL_PAGINA_IDX,
    ];
    $res = agregarFacturaProveedor($conexion, $data);
    if (empty($res['resultado'])) {
        $msg = $res['error'] ?? 'No se pudo guardar la factura.';
        bdj_lectura($conexion, $e, intval($adjunto['adjunto_id']), 'REVISAR', 'Al guardar: ' . $msg);
        return ['success' => false, 'message' => $msg, 'motivos' => []];
    }
    $factura_id = intval($res['factura_proveedor_id']);
    fplVincularAdjunto($conexion, $e, $factura_id, $adjunto['adjunto_id'], $usuario_id);
    error_log("=== fplGuardarBorrador: adjunto {$adjunto['adjunto_id']} -> factura $factura_id (empresa $e, usuario " . intval($usuario_id) . ") ===");
    return ['success' => true, 'factura_proveedor_id' => $factura_id, 'motivos' => []];
}

// ---------------------------------------------------------------------------------------------------
// Propuesta para el formulario
// ---------------------------------------------------------------------------------------------------

/**
 * Convierte la lectura en los datos del formulario: cabecera, líneas, percepciones, ítems de referencia, campos con
 * confianza y capa, errores por campo y avisos. 'listo' = sin errores (se puede guardar como está).
 */
function fplArmarPropuesta($conexion, $empresa_id, ResultadoExtraccion $r, array $validacion)
{
    $e = intval($empresa_id);
    $tol = (float)fl_config()['tolerancia'];
    $errores = $validacion['errores'];
    $avisos = $validacion['avisos'];

    // Tipo de comprobante de la empresa por código ARCA (con los tiques a su equivalente)
    $codigo = intval($r->valor('tipo_comprobante'));
    $tipo = null;
    if ($codigo > 0) {
        $tipo = fpArcaTipoComprobante($conexion, $e, $codigo . ' - ');
        if (!$tipo) $errores['tipo_comprobante'] = 'La empresa no tiene un tipo de Facturas de Compra con el código ARCA ' . $codigo;
        elseif (!empty($tipo['aviso'])) $avisos[] = $tipo['aviso'];
    }
    $letra = strtoupper((string)$r->valor('letra'));
    if (isset(FL_TIPOS_ARCA[$codigo]) && FL_TIPOS_ARCA[$codigo][0] !== 'FACTURA') {
        $avisos[] = 'Es una ' . FL_NOMBRES_CLASE[FL_TIPOS_ARCA[$codigo][0]] . ': elija después el comprobante asociado.';
    }

    // Proveedor por CUIT
    $cuit = preg_replace('/\D/', '', (string)$r->valor('emisor_cuit'));
    $prov = (strlen($cuit) === 11 && !isset($errores['emisor_cuit'])) ? fpArcaBuscarProveedor($conexion, $e, $cuit) : null;
    $entidad_id = $prov ? intval($prov['entidad_id']) : 0;
    $entidad_sucursal_id = $entidad_id ? fplSucursalProveedor($conexion, $e, $entidad_id) : null;
    $proveedor_nuevo = null;
    if (!$prov && strlen($cuit) === 11 && !isset($errores['emisor_cuit'])) {
        $proveedor_nuevo = ['cuit' => $cuit, 'razon_social' => (string)($r->valor('emisor_razon_social') ?? '')];
        $errores['emisor_cuit'] = 'El proveedor no está cargado: puede darlo de alta con los datos leídos';
    }

    // Duplicado: mismo proveedor + tipo + PV + número (sin las eliminadas, como la importación de ARCA)
    $pv = intval($r->valor('punto_venta'));
    $nro = intval($r->valor('numero'));
    $duplicado = null;
    if ($entidad_id && $tipo && $pv > 0 && $nro > 0) {
        $tipo_id = intval($tipo['comprobante_tipo_id']);
        $stmt = mysqli_prepare($conexion, "SELECT f.factura_proveedor_id, f.tabla_estado_registro_id, er.estado_registro
                FROM gestion__facturas_proveedores f
                LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
               WHERE f.empresa_id = ? AND f.entidad_id = ? AND f.comprobante_tipo_id = ? AND f.comprobante_pv = ? AND f.comprobante_nro = ?
                 AND f.tabla_estado_registro_id <> 6 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iiiii", $e, $entidad_id, $tipo_id, $pv, $nro);
        mysqli_stmt_execute($stmt);
        $duplicado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);
        if ($duplicado) {
            $errores['numero'] = 'Este comprobante ya está cargado (' . ($duplicado['estado_registro'] ?? 'estado ' . $duplicado['tabla_estado_registro_id']) . ')';
        }
    }

    // Líneas (producto por producto salvo que el proveedor esté configurado para no leer ítems)
    $leer_items = fplLecturaItemsProveedor($conexion, $e, $entidad_id);
    [$detalles, $items_ref, $avisos_lineas, $error_lineas, $descuento] = fplArmarLineas($conexion, $e, $r, $entidad_id, $letra, $leer_items);
    $avisos = array_merge($avisos, $avisos_lineas);
    if ($error_lineas) $errores['iva_alicuotas'] = $errores['iva_alicuotas'] ?? $error_lineas;

    // Percepciones
    [$impuestos, $pendientes, $avisos_perc] = fplArmarPercepciones($conexion, $e, $r);
    $avisos = array_merge($avisos, $avisos_perc);

    // Total que va a dar el formulario (el IVA por línea se recalcula: puede variar en centavos)
    $total_form = round(array_sum(array_map(fn($d) => $d['total_linea'], $detalles)) + array_sum(array_column($impuestos, 'importe'))
                        + array_sum(array_column($pendientes, 'importe')), 2);
    $total_leido = $r->valor('total');
    if ($total_leido !== null && $detalles && !isset($errores['total']) && abs($total_form - (float)$total_leido) > 0.01) {
        $avisos[] = 'Con el IVA calculado por línea el total da $' . number_format($total_form, 2, ',', '.')
                  . ' (el comprobante dice $' . number_format((float)$total_leido, 2, ',', '.') . '): diferencia de redondeo.';
    }

    $capas = array_map(fn($c) => FPL_ETIQUETAS_CAPA[$c] ?? $c, $r->capas);
    $obs = 'Cargada desde archivo (' . (implode(' + ', $capas) ?: 'sin datos leídos') . ')';
    if ($r->valor('condicion_pago')) $obs .= '. Condición de venta del comprobante: ' . $r->valor('condicion_pago');

    // Errores de campos internos a los que se muestran
    if (isset($errores['iva_alicuotas'])) { $errores['iva_21'] = $errores['iva_alicuotas']; unset($errores['iva_alicuotas']); }

    $f_emision = (string)$r->valor('fecha_emision');
    $cond_prov = $entidad_id ? obtenerCondicionesProveedor($conexion, $entidad_id, $e) : null;
    $modalidad_descuento = $cond_prov['modalidad_descuento_factura'] ?? 'EN_FACTURA_GLOBAL';
    $contempla_descuento = isset($cond_prov['precio_compra_contempla_descuento']) ? intval($cond_prov['precio_compra_contempla_descuento']) : 1;
    $descuento_gral_prov = floatval($cond_prov['proveedor_descuento_general'] ?? 0);

    return [
        'success' => true,
        'listo' => !$errores && !$pendientes,
        'capas' => $capas,
        'campos' => $r->camposParaMostrar(),
        'errores' => $errores,
        'avisos' => array_values(array_unique($avisos)),
        'duplicado' => $duplicado,
        'proveedor_nuevo' => $proveedor_nuevo,
        'items_referencia' => $items_ref,
        'percepciones_pendientes' => $pendientes,
        'lectura_items' => $leer_items,
        'descuento_general' => $descuento,
        'condicion_proveedor' => $cond_prov,
        'form' => [
            'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => $entidad_sucursal_id,
            'proveedor_nombre' => $prov['entidad_nombre'] ?? '',
            'comprobante_tipo_id' => $tipo ? intval($tipo['comprobante_tipo_id']) : 0,
            'comprobante_tipo_nombre' => $tipo['comprobante_tipo'] ?? '',
            'comprobante_pv' => $pv,
            'comprobante_nro' => $nro,
            'f_emision' => $f_emision,
            'f_contabilidad' => $f_emision,
            'cae' => (string)($r->valor('cae') ?? ''),
            'f_vto_cae' => (string)($r->valor('cae_vencimiento') ?? ''),
            'observaciones' => $obs,
            'detalles' => $detalles,
            'impuestos' => $impuestos,
            'descuento_general_pct' => $descuento ? $descuento['pct'] : 0,
            'modalidad_descuento_factura' => $modalidad_descuento,
            'precio_compra_contempla_descuento' => $contempla_descuento,
            'descuento_nc_pct' => ($modalidad_descuento === 'NOTA_CREDITO') ? $descuento_gral_prov : 0.0,
        ],
    ];
}

function fplSucursalProveedor($conexion, $empresa_id, $entidad_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__entidades_sucursales
            WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $s = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $s ? intval($s['sucursal_id']) : null;
}

/**
 * Líneas del formulario. Por alícuota: base = IVA / porcentaje (o el neto si hay una sola). Los ítems con código del
 * proveedor conocido van como líneas propias y se descuentan de su alícuota; si no cierran, se usa solo el detalle
 * por alícuota. Con $leer_items = false (preferencia del proveedor) no se cargan ítems. Si hay descuento general al
 * pie y los ítems vienen a precio de lista, las líneas de ítems llevan ese descuento_general_pct.
 * Devuelve [detalles, ítems de referencia, avisos, error o null, descuento general aplicado o null].
 */
function fplArmarLineas($conexion, $empresa_id, ResultadoExtraccion $r, $entidad_id, $letra, bool $leer_items = true)
{
    $tol = (float)fl_config()['tolerancia'];
    $avisos = [];
    $alic = fpArcaAlicuotas($conexion, $empresa_id);
    $iva = array_filter((array)($r->valor('iva_alicuotas') ?? []), fn($v) => abs((float)$v) >= 0.005);
    $neto = (float)($r->valor('neto_gravado') ?? 0);
    $no_gravado = (float)($r->valor('no_gravado') ?? 0) + (float)($r->valor('otros_tributos') ?? 0);
    $exento = (float)($r->valor('exento') ?? 0);
    if ((float)($r->valor('otros_tributos') ?? 0) > 0) {
        $avisos[] = 'Otros tributos por $' . number_format((float)$r->valor('otros_tributos'), 2, ',', '.') . ' (impuestos internos u otros) se cargan como no gravado.';
    }

    // Bases por alícuota
    $grupos = [];   // pct => ['base' => , 'iva' => ]
    if (count($iva) === 1 && $neto > 0) {
        $grupos[array_key_first($iva)] = ['base' => $neto, 'iva' => (float)reset($iva)];
    } else {
        foreach ($iva as $pct => $importe) {
            if ((float)$pct <= 0) continue;
            $grupos[$pct] = ['base' => round((float)$importe * 100 / (float)$pct, 2), 'iva' => (float)$importe];
        }
        if ($grupos && $neto > 0) {   // las bases deducidas no suman el neto: la diferencia va a la de mayor base
            $dif = round($neto - array_sum(array_column($grupos, 'base')), 2);
            if (abs($dif) > $tol) {
                $bases = array_column($grupos, 'base');
                $mayor = array_keys($grupos)[array_search(max($bases), $bases, true)];
                $grupos[$mayor]['base'] = round($grupos[$mayor]['base'] + $dif, 2);
            }
        }
    }
    if (!$grupos && $neto > $tol) {
        // neto sin IVA discriminado en A / M: si la aritmética cerró, es neto sin IVA → no gravado
        $no_gravado += $neto;
        $avisos[] = 'El neto gravado de $' . number_format($neto, 2, ',', '.') . ' no tiene IVA discriminado: se carga como no gravado.';
    }
    $error = null;
    foreach (array_keys($grupos) as $pct) {
        if (empty($alic['gravadas'][$pct])) {
            $error = 'La empresa no tiene configurada la alícuota de IVA del ' . rtrim(rtrim($pct, '0'), '.') . '%';
        }
    }

    // Imputación de lo que no tiene código de proveedor asociado: el último producto de las compras anteriores del
    // proveedor (controle stock o no), o "Gastos varios" (se crea si falta, como en la importación de ARCA)
    $imputacion = $entidad_id ? fplUltimoProductoProveedor($conexion, $empresa_id, $entidad_id) : null;
    if ($imputacion && intval($imputacion['controla_stock']) === 1) {
        $avisos[] = 'Lo que no tiene código del proveedor se resume en "' . $imputacion['producto_nombre']
                  . '", que controla stock: revise la cantidad de esa línea antes de confirmar (va con cantidad 1).';
    }
    if (!$imputacion) {
        $imputacion = fpArcaGastosVarios($conexion, $empresa_id, false);
        if (!$imputacion) {
            try {
                $imputacion = fpArcaGastosVarios($conexion, $empresa_id, true);
                $avisos[] = 'Se dio de alta el producto "Gastos varios" para imputar el comprobante.';
            } catch (Throwable $ex) {
                $error = $error ?? $ex->getMessage();
                $imputacion = ['producto_id' => 0, 'producto_nombre' => 'Gastos varios'];
            }
        }
    }

    // Descuento general al pie con los ítems a precio de lista: se aplica a los ítems que se cargan
    $descuento = $leer_items ? fplDescuentoGeneral($r, $grupos, $tol) : null;
    $factor = $descuento ? 1 - $descuento['pct'] / 100 : 1.0;
    if ($descuento) {
        $avisos[] = 'Descuento general del ' . rtrim(rtrim(number_format($descuento['pct'], 2, ',', ''), '0'), ',') . '%'
                  . ($descuento['importe'] > 0 ? ' ($' . number_format($descuento['importe'], 2, ',', '.') . ')' : '')
                  . ' al pie del comprobante: los ítems están a precio de lista y se cargan con ese descuento (ver "Precio a importar").';
    }

    // Ítems con código de proveedor conocido
    $items_ref = [];
    $lineas_items = [];
    $productos = ($entidad_id && $leer_items) ? fplProductosPorCodigo($conexion, $empresa_id, $entidad_id, array_column($r->items, 'codigo')) : [];
    $restos = $grupos;
    $coinciden = true;
    foreach ($r->items as $it) {
        $p = $productos[fplCodigoClave($it['codigo'])] ?? null;
        $pct = $it['alicuota'] !== null ? number_format((float)$it['alicuota'], 2, '.', '') : ($p ? number_format((float)$p['porcentaje'], 2, '.', '') : null);
        $subtotal = (float)($it['subtotal'] ?? 0) ?: round((float)$it['cantidad'] * (float)$it['precio_unitario'], 2);
        $cant = (float)$it['cantidad'] ?: 1.0;
        // Precio que se importa: el de lista menos el descuento general (lo que se guarda como costo)
        $ref = $it + ['precio_lista' => round($subtotal / $cant, 4), 'descuento_pct' => $descuento ? $descuento['pct'] : 0,
                      'precio_importar' => round($subtotal * $factor / $cant, 4), 'subtotal_importar' => round($subtotal * $factor, 2)];
        if (!$p || $pct === null || !isset($restos[$pct]) || $subtotal <= 0) {
            $items_ref[] = $ref + ['producto_id' => null];
            continue;
        }
        $restos[$pct]['base'] = round($restos[$pct]['base'] - $ref['subtotal_importar'], 2);
        $lineas_items[] = ['producto_id' => intval($p['producto_id']), 'producto_nombre' => $p['producto_nombre'],
                           'cantidad' => $cant, 'precio' => round($subtotal / $cant, 2),
                           'pct' => $pct, 'base' => $subtotal, 'codigo_proveedor' => trim((string)$it['codigo'])];
        $items_ref[] = $ref + ['producto_id' => intval($p['producto_id']), 'producto_nombre' => $p['producto_nombre']];
    }
    // Con descuento, el redondeo por línea puede dejar unos centavos de más o de menos en cada alícuota
    $tol_restos = $descuento ? $tol + 0.01 * count($lineas_items) : $tol;
    foreach ($restos as $g) {
        if ($g['base'] < -$tol_restos) $coinciden = false;
    }
    if (!$coinciden) {
        $avisos[] = 'Los ítems reconocidos suman más que el neto de su alícuota: se carga una línea por alícuota.';
        $lineas_items = [];
        $restos = $grupos;
        foreach ($items_ref as &$ir) $ir['producto_id'] = null;
        unset($ir);
    } elseif ($lineas_items) {
        $avisos[] = count($lineas_items) . ' ítem(s) se cargaron con su producto por el código del proveedor.';
    }
    if (!$leer_items) {
        $avisos[] = 'Este proveedor está configurado para no cargar los ítems producto por producto: se carga una línea por alícuota.';
    }
    $con_items = array_flip(array_column($lineas_items, 'pct'));

    $detalles = [];
    $n = 0;
    $linea = function ($producto_id, $nombre, $cantidad, $precio, $alicuota_id, $pct, $ng, $ex, $codigo_proveedor = '', $dto_pct = 0.0) use (&$n) {
        $bruto = round($cantidad * $precio, 2);
        // Como insertarDetallesFactura: descuento general sobre cantidad × precio
        $dto = round($bruto * (float)$dto_pct / 100, 2);
        $base = round($bruto - $dto, 2);
        $neto_g = ($ng || $ex) ? 0.0 : $base;
        $iva = round($neto_g * (float)$pct / 100, 2);
        $n++;
        return ['detalle_idx' => 'arch_' . $n, 'factura_proveedor_detalle_id' => 0, 'ordenes_compra_detalle_id' => null,
                'producto_id' => intval($producto_id), 'producto_nombre' => $nombre, 'cantidad' => $cantidad,
                'precio_unitario' => $precio, 'precio_unitario_bruto' => $precio, 'descuento_item_pct' => 0,
                'descuento_general_pct' => (float)$dto_pct, 'descuento_item' => 0, 'descuento_general' => $dto, 'descuento' => $dto,
                'no_gravado' => $ng ? $base : 0.0, 'exento' => $ex ? $base : 0.0, 'iva_alicuota_id' => intval($alicuota_id),
                'iva_porcentaje' => ($ng || $ex) ? 0.0 : (float)$pct, 'neto_gravado' => $neto_g, 'iva_importe' => $iva,
                'total_linea' => round($base + $iva, 2), 'producto_codigo' => '', 'codigo_proveedor' => $codigo_proveedor];
    };
    foreach ($lineas_items as $li) {
        $detalles[] = $linea($li['producto_id'], $li['producto_nombre'], $li['cantidad'], $li['precio'],
                             $alic['gravadas'][$li['pct']] ?? 0, $li['pct'], false, false, $li['codigo_proveedor'],
                             $descuento ? $descuento['pct'] : 0);
    }
    foreach ($restos as $pct => $g) {
        // Unos centavos de redondeo del descuento en una alícuota con ítems no justifican otra línea
        if ($descuento && isset($con_items[$pct]) && abs($g['base']) <= $tol_restos) continue;
        if ($g['base'] > $tol) {
            $detalles[] = $linea($imputacion['producto_id'], $imputacion['producto_nombre'], 1, $g['base'],
                                 $alic['gravadas'][$pct] ?? 0, $pct, false, false);
        }
    }
    if ($no_gravado > $tol) {
        if (!$alic['no_gravado']) $error = $error ?? 'La empresa no tiene la alícuota de IVA "No gravado"';
        $detalles[] = $linea($imputacion['producto_id'], $imputacion['producto_nombre'], 1, round($no_gravado, 2), $alic['no_gravado'], 0, true, false);
    }
    if ($exento > $tol) {
        if (!$alic['exento']) $error = $error ?? 'La empresa no tiene la alícuota de IVA "Exento"';
        $detalles[] = $linea($imputacion['producto_id'], $imputacion['producto_nombre'], 1, round($exento, 2), $alic['exento'], 0, false, true);
    }
    // Código nuestro de cada producto, para mostrarlo junto al del proveedor
    $codigos = fplCodigosProductos($conexion, $empresa_id, array_merge(array_column($detalles, 'producto_id'), array_column($items_ref, 'producto_id')));
    foreach ($detalles as &$d) $d['producto_codigo'] = $codigos[$d['producto_id']] ?? '';
    unset($d);
    foreach ($items_ref as &$ir) $ir['producto_codigo'] = $ir['producto_id'] ? ($codigos[$ir['producto_id']] ?? '') : '';
    unset($ir);
    return [$detalles, $items_ref, $avisos, $error, $descuento];
}

/**
 * Descuento general al pie que hay que aplicar a los ítems: ['pct', 'importe', 'lista', 'neto'] o null.
 * Solo con IVA discriminado (los ítems de A / M son netos de IVA) y cuando los ítems gravados, tal como vienen, suman
 * más que el neto gravado (están a precio de lista): si ya suman el neto, el descuento ya está en cada renglón.
 * El % leído manda; si solo vino el importe, % = importe / suma de los ítems (2 decimales, como la columna).
 */
function fplDescuentoGeneral(ResultadoExtraccion $r, array $grupos, $tol)
{
    if (!$grupos || !$r->items) return null;
    $pct = (float)($r->valor('descuento_general_pct') ?? 0);
    $importe = abs((float)($r->valor('descuento_general') ?? 0));
    if ($pct <= 0 && $importe <= 0) return null;
    $lista = 0.0;
    foreach ($r->items as $it) {
        if ($it['alicuota'] !== null && (float)$it['alicuota'] <= 0) continue;   // no gravados / exentos
        $lista += (float)($it['subtotal'] ?? 0) ?: (float)$it['cantidad'] * (float)$it['precio_unitario'];
    }
    $neto = round(array_sum(array_column($grupos, 'base')), 2);
    if ($lista <= $neto + $tol) {
        error_log("fplDescuentoGeneral: los ítems ($lista) ya suman el neto ($neto): no se aplica el descuento leído");
        return null;
    }
    if ($pct <= 0 || $pct >= 100) $pct = $importe / $lista * 100;
    $pct = round($pct, 2);
    if ($pct <= 0 || $pct >= 100) return null;
    return ['pct' => $pct, 'importe' => $importe > 0 ? round($importe, 2) : round($lista * $pct / 100, 2),
            'lista' => round($lista, 2), 'neto' => $neto];
}

/** producto_codigo de la empresa por producto_id. */
function fplCodigosProductos($conexion, $empresa_id, array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = mysqli_prepare($conexion, "SELECT producto_id, producto_codigo FROM gestion__productos WHERE empresa_id = ? AND producto_id IN ($in)");
    $params = array_merge([intval($empresa_id)], $ids);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) $out[intval($row['producto_id'])] = (string)$row['producto_codigo'];
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Último producto de las compras anteriores del proveedor: la última línea de su última factura no eliminada, con un
 * producto de compra activo. null si no tiene compras.
 */
function fplUltimoProductoProveedor($conexion, $empresa_id, $entidad_id)
{
    $e = intval($empresa_id);
    $ent = intval($entidad_id);
    $stmt = mysqli_prepare($conexion, "SELECT p.producto_id, p.producto_nombre, p.controla_stock
            FROM gestion__facturas_proveedores f
            JOIN gestion__facturas_proveedores_detalle d ON d.factura_proveedor_id = f.factura_proveedor_id
            JOIN gestion__productos p ON p.producto_id = d.producto_id AND p.empresa_id = f.empresa_id
           WHERE f.empresa_id = ? AND f.entidad_id = ? AND f.tabla_estado_registro_id <> 6
             AND p.aplica_compra = 1 AND p.tabla_estado_registro_id = 1
           ORDER BY f.f_emision DESC, f.factura_proveedor_id DESC, d.factura_proveedor_detalle_id DESC
           LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $e, $ent);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** Código de proveedor para comparar: mayúsculas y sin separadores ("16791/I" = "16791-I" = "16791 I"). */
function fplCodigoClave($codigo)
{
    return preg_replace('/[^0-9A-Z]/', '', mb_strtoupper(trim((string)$codigo)));
}

/**
 * Productos de compra de la empresa por código del proveedor, indexados por fplCodigoClave(): producto_id, nombre y
 * % de IVA. Si dos vínculos dan la misma clave, gana el que coincide tal cual con el código leído.
 */
function fplProductosPorCodigo($conexion, $empresa_id, $entidad_id, array $codigos)
{
    $exactos = array_flip(array_map(fn($c) => mb_strtoupper(trim((string)$c)), $codigos));
    $claves = array_values(array_unique(array_filter(array_map('fplCodigoClave', $codigos))));
    if (!$claves) return [];
    $claves = array_slice($claves, 0, 200);
    $in = implode(',', array_fill(0, count($claves), '?'));
    $clave_sql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(pp.codigo_proveedor)), '-', ''), '/', ''), '.', ''), ' ', ''), '_', '')";
    $stmt = mysqli_prepare($conexion, "SELECT UPPER(TRIM(pp.codigo_proveedor)) AS codigo, $clave_sql AS clave, p.producto_id,
                   p.producto_nombre, COALESCE(a.porcentaje, 21) AS porcentaje
            FROM gestion__productos_proveedores pp
            JOIN gestion__productos p ON p.producto_id = pp.producto_id AND p.empresa_id = pp.empresa_id
            LEFT JOIN gestion__impuestos__iva_alicuotas a ON a.iva_alicuota_id = p.iva_alicuota_id AND a.empresa_id = p.empresa_id
           WHERE pp.empresa_id = ? AND pp.entidad_id = ? AND pp.tabla_estado_registro_id = 1 AND p.tabla_estado_registro_id = 1
             AND p.aplica_compra = 1 AND $clave_sql IN ($in)
           ORDER BY pp.producto_id");
    $tipos = 'ii' . str_repeat('s', count($claves));
    $params = array_merge([intval($empresa_id), intval($entidad_id)], $claves);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    $exacto = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $k = fplCodigoClave($row['clave']);
        $es_exacto = isset($exactos[$row['codigo']]);
        if (!isset($out[$k]) || ($es_exacto && empty($exacto[$k]))) {
            $out[$k] = $row;
            $exacto[$k] = $es_exacto;
        }
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Percepciones sufridas: IVA (impuesto 2) a su configuración de la empresa; IIBB (impuesto 1) a la configuración de
 * la provincia leída. Lo que no tiene configuración o provincia queda pendiente con las opciones para elegir.
 */
function fplArmarPercepciones($conexion, $empresa_id, ResultadoExtraccion $r)
{
    $avisos = [];
    $impuestos = [];
    $pendientes = [];
    $configs = array_values(array_filter(obtenerImpuestosConfig($conexion, $empresa_id, 0),
                                         fn($c) => intval($c['es_percepcion'] ?? 0) === 1 && in_array(intval($c['impuesto_tipo_id']), [1, 2], true)));
    $base = round((float)($r->valor('neto_gravado') ?? 0), 2);
    $objeto = function ($cfg, $importe, $detalle) use ($base) {
        return [
            'empresa_impuesto_config_id' => intval($cfg['empresa_impuesto_config_id']),
            'impuesto_tipo_id' => intval($cfg['impuesto_tipo_id']),
            'impuesto_tipo' => $cfg['impuesto_tipo'],
            'impuesto_tipo_descripcion' => $cfg['impuesto_tipo'] . (!empty($cfg['jurisdiccion_nombre']) ? ' - ' . $cfg['jurisdiccion_nombre'] : ''),
            'impuesto_codigo_afip' => $cfg['codigo_afip'] ?? null,
            'jurisdiccion_id' => $cfg['jurisdiccion_id'] !== null ? intval($cfg['jurisdiccion_id']) : null,
            'jurisdiccion_nombre' => $cfg['jurisdiccion_nombre'] ?? '',
            'jurisdiccion_codigo' => $cfg['jurisdiccion_codigo'] ?? '',
            'condicion_fiscal_id' => $cfg['condicion_fiscal_id'] ?? null,
            'condicion_fiscal' => $cfg['condicion_fiscal'] ?? null,
            'condicion_fiscal_codigo' => $cfg['condicion_fiscal_codigo'] ?? null,
            'base_calculo' => $cfg['base_calculo'] ?? 'NETO_GRAVADO',
            'base_imponible' => $base,
            'alicuota' => $base > 0 ? round($importe / $base * 100, 4) : 0,
            'minimo_imponible' => 0, 'monto_fijo' => 0,
            'importe' => round($importe, 2),
            'aplica_siempre' => 0, 'prioridad' => 1, 'tipo_calculo' => 'manual',
            'detalle_origen' => mb_substr($detalle, 0, 255), 'tipo_origen' => 'manual',
        ];
    };
    $opciones = function ($tipo) use ($configs) {
        return array_values(array_map(fn($c) => ['empresa_impuesto_config_id' => intval($c['empresa_impuesto_config_id']),
                                                 'nombre' => $c['impuesto_tipo'] . (!empty($c['jurisdiccion_nombre']) ? ' - ' . $c['jurisdiccion_nombre'] : '')],
                                     array_filter($configs, fn($c) => intval($c['impuesto_tipo_id']) === $tipo)));
    };

    $perc_iva = (float)($r->valor('percepciones_iva') ?? 0);
    if ($perc_iva > 0) {
        $cfg_iva = array_values(array_filter($configs, fn($c) => intval($c['impuesto_tipo_id']) === 2));
        if (count($cfg_iva) === 1) {
            $impuestos[] = $objeto($cfg_iva[0], $perc_iva, 'Leída del comprobante');
        } else {
            $pendientes[] = ['tipo' => 'IVA', 'importe' => round($perc_iva, 2), 'jurisdiccion' => '', 'opciones' => $opciones(2),
                             'motivo' => $cfg_iva ? 'Elija la percepción de IVA' : 'La empresa no tiene configurada la percepción de IVA recibida'];
        }
    }

    $detalle = $r->percepcionesIibb;
    $total_iibb = (float)($r->valor('percepciones_iibb') ?? 0);
    if (!$detalle && $total_iibb > 0) $detalle = [['jurisdiccion' => '', 'importe' => $total_iibb]];
    foreach ($detalle as $p) {
        $importe = (float)($p['importe'] ?? 0);
        if ($importe <= 0) continue;
        $cod = (string)($p['jurisdiccion'] ?? '');
        $cfg = null;
        if ($cod !== '') {
            foreach ($configs as $c) {
                if (intval($c['impuesto_tipo_id']) === 1 && (string)($c['jurisdiccion_codigo'] ?? '') === $cod) { $cfg = $c; break; }
            }
        }
        if ($cfg) {
            $impuestos[] = $objeto($cfg, $importe, 'Leída del comprobante');
        } else {
            $pendientes[] = ['tipo' => 'IIBB', 'importe' => round($importe, 2), 'jurisdiccion' => $cod,
                             'texto' => (string)($p['texto'] ?? ''), 'opciones' => $opciones(1),
                             'motivo' => $cod === '' ? 'No se leyó la provincia' : 'La empresa no tiene configurada la percepción de IIBB de esa provincia'];
        }
    }
    if ($pendientes) $avisos[] = 'Hay percepciones sin asignar: elíjalas en el panel antes de guardar.';
    return [$impuestos, $pendientes, $avisos];
}
