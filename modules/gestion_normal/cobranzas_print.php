<?php
// cobranzas_print.php — impresión de un recibo de cobranza a clientes (HTML para imprimir / PDF del navegador).
// Formato de comprobante ARCA análogo a la factura a clientes: emisor con logo | letra | datos del comprobante,
// receptor (cliente), medios de cobro recibidos, comprobantes imputados y totales.

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['cobranzas.php']]);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php';
require_once __DIR__ . '/../../core/impresion.php';

$empresa_idx = $ctx['empresa_id'];
$cobranza_id = intval($_GET['cobranza_id'] ?? $_GET['id'] ?? 0);
$copias = max(1, min(3, intval($_GET['copias'] ?? 1)));

if ($cobranza_id <= 0) {
    http_response_code(400);
    die('Cobranza no indicada');
}

$datos = obtenerCobranzaDetalle($conexion, $cobranza_id, $empresa_idx);
if (!$datos) {
    http_response_code(404);
    die('Recibo de cobranza no encontrado');
}

$cabecera = $datos['cabecera'];
$medios = $datos['medios'];
$imputaciones = $datos['imputaciones'];

if (!empty($cabecera['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($cabecera['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de esta cobranza');
    }
}

function cobPrintFila($conexion, $sql, $tipos, ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: [];
}

function cobPrintCuit($cuit)
{
    $c = preg_replace('/\D/', '', (string) $cuit);
    return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c;
}

function cobPrintFecha($fecha)
{
    return !empty($fecha) && $fecha !== '0000-00-00' ? date('d/m/Y', strtotime($fecha)) : '';
}

function cobPrintImporte($v)
{
    return number_format(floatval($v), 2, ',', '.');
}

function h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if (!function_exists('vfPrintFecha')) {
    function vfPrintFecha($fecha) { return cobPrintFecha($fecha); }
}
if (!function_exists('vfPrintImporte')) {
    function vfPrintImporte($v) { return cobPrintImporte($v); }
}
if (!function_exists('vfPrintCuit')) {
    function vfPrintCuit($cuit) { return cobPrintCuit($cuit); }
}

function cobPrintLogo($ruta_relativa)
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
$empresa = cobPrintFila($conexion, "SELECT e.empresa, e.documento_numero, e.domicilio, e.telefono, e.email,
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
$parametros = cobPrintFila($conexion, "SELECT ingresos_brutos_nro, inicio_actividades, leyenda_fiscal" . ($tiene_logo ? ", logo_archivo" : "") . "
                                      FROM gestion__empresas_parametros
                                      WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                      ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1", "i", $empresa_idx);
$logo_archivo = $parametros['logo_archivo'] ?? '';
if ($tiene_logo && empty($logo_archivo)) {
    $ultimo = cobPrintFila($conexion, "SELECT logo_archivo FROM gestion__empresas_parametros
                                      WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND logo_archivo IS NOT NULL AND logo_archivo <> ''
                                      ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1", "i", $empresa_idx);
    $logo_archivo = $ultimo['logo_archivo'] ?? '';
}
$logo_src = cobPrintLogo($logo_archivo);

$sucursal = !empty($cabecera['sucursal_id'])
    ? cobPrintFila($conexion, "SELECT sucursal_nombre, direccion, telefono FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $cabecera['sucursal_id'], $empresa_idx)
    : [];

$pto_vta = intval($cabecera['pv_nro'] ?? $cabecera['comprobante_pv'] ?? 0);
$nro = intval($cabecera['comprobante_nro'] ?? 0);
$numero = str_pad((string) $pto_vta, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string) $nro, 8, '0', STR_PAD_LEFT);

// ---------- Receptor (Cliente) ----------
$entidad_id = intval($cabecera['entidad_id'] ?? 0);
$cliente = $entidad_id > 0
    ? cobPrintFila($conexion, "SELECT e.entidad_nombre, e.cuit, e.domicilio_legal, l.localidad, p.provincia
                              FROM gestion__entidades e
                              LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                              LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                              WHERE e.entidad_id = ? AND e.empresa_id = ?", "ii", $entidad_id, $empresa_idx)
    : [];
$cliente_nombre = $entidad_id > 0
    ? ($cliente['entidad_nombre'] ?? $cabecera['entidad_nombre'])
    : 'Consumidor Final';
$cliente_cuit = preg_replace('/\D/', '', (string) ($cliente['cuit'] ?? $cabecera['cuit'] ?? ''));
$cliente_domicilio = trim(implode(' - ', array_filter([
    $cliente['domicilio_legal'] ?? $cabecera['domicilio_legal'] ?? '',
    $cliente['localidad'] ?? '',
    $cliente['provincia'] ?? ''
])));

$cliente_cond_row = cobPrintFila($conexion, "
    SELECT cf.condicion_fiscal
    FROM gestion__entidades_condiciones_fiscales ecf
    INNER JOIN gestion__condiciones_fiscales cf ON ecf.condicion_fiscal_id = cf.condicion_fiscal_id
    WHERE ecf.entidad_id = ? AND ecf.tabla_estado_registro_id = 1
      AND (ecf.f_desde IS NULL OR ecf.f_desde <= CURDATE())
      AND (ecf.f_hasta IS NULL OR ecf.f_hasta >= CURDATE())
    ORDER BY ecf.f_desde DESC LIMIT 1
", "i", $entidad_id);
$cliente_condicion_fiscal = $cliente_cond_row['condicion_fiscal'] ?? 'Consumidor Final';

$simbolo = $cabecera['moneda_simbolo'] ?? '$';
$letra = strtoupper(trim((string) ($cabecera['comprobante_letra'] ?? ''))) ?: 'X';
$titulo_tipo = trim((string) ($cabecera['comprobante_tipo'] ?? 'RECIBO DE COBRO'));

$estado_id = intval($cabecera['tabla_estado_registro_id']);
$confirmada = ($estado_id === 5);
$anulada = ($estado_id === 38);

$total_recibido = floatval($cabecera['importe_total'] ?? 0);
$total_imputado = 0.0;
foreach ($imputaciones as $imp) {
    $total_imputado += floatval($imp['importe_imputado'] ?? 0);
}
$saldo_a_cuenta = max(0.0, round($total_recibido - $total_imputado, 2));

$etiquetas_copia = ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO'];

// Configuración de impresión de la empresa (core/impresion.php)
$subgrupo = cobPrintFila($conexion, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ?", "i", $cabecera['comprobante_tipo_id']);
$cfg_impresion = impresion_config($conexion, $empresa_idx, intval($subgrupo['comprobante_subgrupo_id'] ?? 0));
if (!isset($_GET['copias'])) $copias = $cfg_impresion['copias_defecto'];
if (!$cfg_impresion['mostrar_logo']) $logo_src = '';
$color_acento = $cfg_impresion['color_acento'] ?: '#d9d9d9';
$url_copias = function ($n) use ($cobranza_id, $empresa_idx) {
    return '?cobranza_id=' . $cobranza_id . '&empresa_idx=' . $empresa_idx . '&copias=' . $n;
};

// Plantilla compartida
include impresion_plantilla('recibo', $cfg_impresion['formato']);

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
