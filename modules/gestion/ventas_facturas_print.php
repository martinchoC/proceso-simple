<?php
// ventas_facturas_print.php — impresión de una factura de venta (HTML para imprimir / PDF del navegador).
// Formato de comprobante ARCA: emisor con logo (Parámetros de Empresa) | letra | datos del comprobante,
// receptor, detalle, totales y, en los comprobantes fiscales autorizados, QR + CAE (RG 4291).
// Mismo guard que ventas_facturas_ajax.php: sesión + empresa del usuario + acceso a la página.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['ventas_facturas.php', 'ventas_remitos.php', 'ventas_remitos_pendientes_facturacion.php']]);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ventas_facturas_model.php';
require_once __DIR__ . '/../../core/impresion.php';

$empresa_idx = $ctx['empresa_id'];
$venta_factura_id = intval($_GET['venta_factura_id'] ?? 0);
// copias=2: ORIGINAL + DUPLICADO; copias=3: + TRIPLICADO (sin el parámetro: copias_defecto de la empresa)
$copias = max(1, min(3, intval($_GET['copias'] ?? 1)));

if ($venta_factura_id <= 0) {
    http_response_code(400);
    die('Factura no indicada');
}

$factura = vfObtenerVentaFacturaPorId($conexion, $venta_factura_id, $empresa_idx);
if (!$factura) {
    http_response_code(404);
    die('Factura no encontrada');
}

if (!empty($factura['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($factura['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de esta factura');
    }
}

function vfPrintFila($conexion, $sql, $tipos, ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("ventas_facturas_print: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: [];
}

function vfPrintCuit($cuit)
{
    $c = preg_replace('/\D/', '', (string) $cuit);
    return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c;
}

function vfPrintFecha($fecha)
{
    return !empty($fecha) && $fecha !== '0000-00-00' ? date('d/m/Y', strtotime($fecha)) : '';
}

function vfPrintImporte($v)
{
    return number_format(floatval($v), 2, ',', '.');
}

function h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// Logo de Parámetros de Empresa embebido como data URI (no depende de la URL base del sitio).
function vfPrintLogo($ruta_relativa)
{
    if (empty($ruta_relativa)) return '';
    $base = realpath(__DIR__ . '/../../uploads/empresas');
    $archivo = realpath(__DIR__ . '/../../' . $ruta_relativa);
    if (!$base || !$archivo || strpos($archivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)) return '';
    $info = @getimagesize($archivo);
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) return '';
    return 'data:' . $info['mime'] . ';base64,' . base64_encode(file_get_contents($archivo));
}

// ---------- Emisor ----------
$empresa = vfPrintFila($conexion, "SELECT e.empresa, e.documento_numero, e.domicilio, e.telefono, e.email,
                                          l.localidad, l.cp, p.provincia, cf.condicion_fiscal
                                   FROM conf__empresas e
                                   LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                                   LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                                   LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = e.condicion_fiscal_id
                                   WHERE e.empresa_id = ?", "i", $empresa_idx);
$empresa_cuit = !empty($empresa['documento_numero']) ? number_format((float) $empresa['documento_numero'], 0, '', '') : '';

$tiene_logo = false;
$res_col = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'logo_archivo'");
if ($res_col && mysqli_num_rows($res_col) > 0) $tiene_logo = true;
$parametros = vfPrintFila($conexion, "SELECT ingresos_brutos_nro, inicio_actividades, leyenda_fiscal" . ($tiene_logo ? ", logo_archivo" : "") . "
                                      FROM gestion__empresas_parametros
                                      WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                      ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1", "i", $empresa_idx);
$logo_archivo = $parametros['logo_archivo'] ?? '';
if ($tiene_logo && empty($logo_archivo)) {
    // La vigencia abierta no tiene logo: el último que se haya cargado
    $ultimo = vfPrintFila($conexion, "SELECT logo_archivo FROM gestion__empresas_parametros
                                      WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND logo_archivo IS NOT NULL AND logo_archivo <> ''
                                      ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1", "i", $empresa_idx);
    $logo_archivo = $ultimo['logo_archivo'] ?? '';
}
$logo_src = vfPrintLogo($logo_archivo);

$sucursal = !empty($factura['sucursal_id'])
    ? vfPrintFila($conexion, "SELECT sucursal_nombre, direccion, telefono FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $factura['sucursal_id'], $empresa_idx)
    : [];
$pv = vfPrintFila($conexion, "SELECT nombre, codigo_fiscal FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?", "ii", $factura['punto_venta_id'], $empresa_idx);

// ---------- Receptor ----------
$entidad_id = intval($factura['entidad_id'] ?? 0);
$cliente = $entidad_id > 0
    ? vfPrintFila($conexion, "SELECT e.entidad_nombre, e.cuit, e.domicilio_legal, l.localidad, p.provincia
                              FROM gestion__entidades e
                              LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                              LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                              WHERE e.entidad_id = ? AND e.empresa_id = ?", "ii", $entidad_id, $empresa_idx)
    : [];
$cliente_sucursal = !empty($factura['entidad_sucursal_id'])
    ? vfPrintFila($conexion, "SELECT s.sucursal_nombre, s.sucursal_direccion, l.localidad
                              FROM gestion__entidades_sucursales s
                              LEFT JOIN conf__localidades l ON l.localidad_id = s.localidad_id
                              WHERE s.sucursal_id = ? AND s.empresa_id = ?", "ii", $factura['entidad_sucursal_id'], $empresa_idx)
    : [];
$relacion = vfObtenerRelacionFiscal($conexion, $empresa_idx, $entidad_id ?: 'CF');
$cliente_nombre = $entidad_id > 0
    ? ($cliente['entidad_nombre'] ?? $factura['entidad_nombre'])
    : (trim((string) ($factura['consumidor_final_nombre'] ?? '')) ?: 'Consumidor Final');
$cliente_cuit = preg_replace('/\D/', '', (string) ($cliente['cuit'] ?? ''));
$cliente_domicilio = trim(implode(' - ', array_filter([
    $cliente['domicilio_legal'] ?? '',
    $cliente['localidad'] ?? '',
    $cliente['provincia'] ?? ''
])));

$moneda = vfPrintFila($conexion, "SELECT codigo, moneda, simbolo, es_moneda_base FROM gestion__monedas WHERE moneda_id = ?", "i", $factura['moneda_id']);
$simbolo = $moneda['simbolo'] ?? '$';

// ---------- Comprobante ----------
$fiscal = intval($factura['comprobante_fiscal_id'] ?? 0) > 0
    ? vfComprobanteFiscalArca($conexion, intval($factura['comprobante_tipo_id']), $empresa_idx)
    : null;
$arca_tipo = $fiscal ? intval($fiscal['arca_tipo']) : 0;
$letra = strtoupper(trim((string) ($factura['letra'] ?? ''))) ?: ($fiscal ? '' : 'X');
$discrimina_iva = $letra === 'A' || $letra === 'M';
$es_c = $letra === 'C';
$pto_vta = intval($pv['codigo_fiscal'] ?? 0) ?: intval($factura['punto_venta_id']);
$nro = intval($factura['comprobante_nro'] ?? 0);
$numero = str_pad((string) $pto_vta, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
$estado_id = intval($factura['tabla_estado_registro_id']);
$confirmada = in_array($estado_id, [5, 12, 13, 15, 39, 40], true);
$cae = trim((string) ($factura['CAE'] ?? ''));
$con_cae = $fiscal && $cae !== '' && $nro > 0;

// Detalle activo + IVA por alícuota (exento / no gravado aparte, como en vfArcaDatosFactura)
$detalles = array_values(array_filter($factura['detalles'], function ($d) {
    return intval($d['tabla_estado_registro_id'] ?? 1) !== 6;
}));
$alicuotas_cat = [];
$res_al = mysqli_query($conexion, "SELECT iva_alicuota_id, es_exento, es_no_gravado FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = " . intval($empresa_idx));
while ($res_al && $a = mysqli_fetch_assoc($res_al)) $alicuotas_cat[intval($a['iva_alicuota_id'])] = $a;
$iva_por_alicuota = [];
foreach ($detalles as $d) {
    $al = $alicuotas_cat[intval($d['iva_alicuota_id'] ?? 0)] ?? [];
    if (!empty($al['es_exento']) || !empty($al['es_no_gravado'])) continue;
    $clave = number_format(floatval($d['porcentaje_iva']), 2, '.', '');
    $iva_por_alicuota[$clave] = ($iva_por_alicuota[$clave] ?? 0) + floatval($d['importe_iva']);
}
ksort($iva_por_alicuota, SORT_NUMERIC);

$exento = floatval($factura['importe_exento'] ?? 0);
$no_gravado = floatval($factura['importe_no_gravado'] ?? 0);
$iva_total = floatval($factura['importe_iva'] ?? 0);
$otros = floatval($factura['importe_otros_impuestos'] ?? 0);
// Percepciones IIBB practicadas (se detallan aparte; no son "otros impuestos nacionales indirectos")
$percepciones_iibb = array_values(array_filter($factura['percepciones'] ?? [], function ($p) {
    return floatval($p['importe']) > 0;
}));
$total_percepciones = array_sum(array_map(function ($p) { return floatval($p['importe']); }, $percepciones_iibb));
$otros_nacionales = round($otros - $total_percepciones, 2);
$leyendas_fiscales = [];
foreach ($factura['percepciones'] ?? [] as $p) {
    if (!empty($p['numero_agente'])) {
        $leyendas_fiscales['ag' . $p['jurisdiccion_id']] = 'Agente de Percepción IIBB ' . $p['jurisdiccion_nombre'] . ' N° ' . $p['numero_agente'];
    }
}
if (intval($factura['destino_bien_uso'] ?? 0) === 1) {
    $leyendas_fiscales['bu'] = 'El adquirente declara que los bienes tienen destino de bien de uso / insumo para su fabricación (sin percepción de IIBB).';
}
$fmt_pct_perc = function ($v) { return str_replace('.', ',', rtrim(rtrim(number_format(floatval($v), 2, '.', ''), '0'), '.')); };
$total = floatval($factura['importe_total'] ?? 0);
$neto_gravado = floatval($factura['importe_neto'] ?? 0) - $exento - $no_gravado;

// ---------- QR de ARCA (RG 4291, especificación en https://www.afip.gob.ar/fe/qr/) ----------
$qr_url = '';
if ($con_cae) {
    $codigo_moneda = strtoupper((string) ($moneda['codigo'] ?? 'ARS'));
    $mapa_monedas = ['ARS' => 'PES', 'USD' => 'DOL', 'EUR' => '060'];
    $qr = [
        'ver' => 1,
        'fecha' => substr((string) $factura['f_emision'], 0, 10),
        'cuit' => intval(preg_replace('/\D/', '', $empresa_cuit)),
        'ptoVta' => $pto_vta,
        'tipoCmp' => $arca_tipo,
        'nroCmp' => $nro,
        'importe' => round($total, 2),
        'moneda' => $mapa_monedas[$codigo_moneda] ?? $codigo_moneda,
        'ctz' => round(floatval($factura['tipo_cambio'] ?: 1), 6),
    ];
    if (strlen($cliente_cuit) === 11) {
        $qr['tipoDocRec'] = 80;
        $qr['nroDocRec'] = intval($cliente_cuit);
    } else {
        $qr['tipoDocRec'] = 99;
        $qr['nroDocRec'] = 0;
    }
    $qr['tipoCodAut'] = 'E';
    $qr['codAut'] = intval(preg_replace('/\D/', '', $cae));
    $qr_url = 'https://www.afip.gob.ar/fe/qr/?p=' . base64_encode(json_encode($qr));
}

$etiquetas_copia = ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO'];
$titulo_tipo = $fiscal
    ? preg_replace('/\s+[ABCMEX]$/i', '', trim((string) $factura['comprobante_tipo']))
    : trim((string) $factura['comprobante_tipo']);

// ---------- Configuración de impresión de la empresa (core/impresion.php) ----------
$subgrupo = vfPrintFila($conexion, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ?", "i", $factura['comprobante_tipo_id']);
$cfg_impresion = impresion_config($conexion, $empresa_idx, intval($subgrupo['comprobante_subgrupo_id'] ?? 0));
if (!isset($_GET['copias'])) $copias = $cfg_impresion['copias_defecto'];
if (!$cfg_impresion['mostrar_logo']) $logo_src = '';
$color_acento = $cfg_impresion['color_acento'] ?: '#d9d9d9';
$url_copias = function ($n) use ($venta_factura_id, $empresa_idx) {
    return '?venta_factura_id=' . $venta_factura_id . '&empresa_idx=' . $empresa_idx . '&copias=' . $n;
};

// Plantilla compartida (gestion / gestion_normal): usa las variables y helpers de este archivo
include impresion_plantilla('factura', $cfg_impresion['formato']);

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
