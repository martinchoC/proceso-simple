<?php
// ventas_remitos_print.php — impresión de un remito de venta (HTML para imprimir / PDF del navegador).
// Mismo esquema que ventas_facturas_print.php: emisor con logo | letra | datos del comprobante, destinatario,
// detalle (cantidades; con valorizado=1 también precios e importes) y recuadro de conformidad de la entrega.
// Mismo guard que ventas_remitos_ajax.php: sesión + empresa del usuario + acceso a alguna de las páginas de remitos.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['ventas_remitos.php', 'ventas_remitos_pendientes_facturacion.php']]);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ventas_remitos_model.php';
require_once __DIR__ . '/../../core/impresion.php';
require_once __DIR__ . '/../../core/formato.php';

$empresa_idx = $ctx['empresa_id'];
$venta_remito_id = intval($_GET['venta_remito_id'] ?? 0);
// copias=2: ORIGINAL + DUPLICADO; copias=3: + TRIPLICADO (sin el parámetro: copias_defecto de la empresa)
$copias = max(1, min(3, intval($_GET['copias'] ?? 1)));
$valorizado = intval($_GET['valorizado'] ?? 0) === 1;

if ($venta_remito_id <= 0) {
    http_response_code(400);
    die('Remito no indicado');
}

$remito = obtenerRemitoVentaPorId($conexion, $venta_remito_id, $empresa_idx);
if (!$remito) {
    http_response_code(404);
    die('Remito no encontrado');
}

if (!empty($remito['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($remito['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de este remito');
    }
}

function vrPrintFila($conexion, $sql, $tipos, ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("ventas_remitos_print: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: [];
}

function vrPrintCuit($cuit)
{
    $c = preg_replace('/\D/', '', (string) $cuit);
    return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c;
}

function vrPrintCantidad($v)
{
    $v = floatval($v);
    return fmt_numero($v, $v == floor($v) ? 0 : 2);
}

function h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$f_emision = substr((string) $remito['f_emision'], 0, 10);

// ---------- Emisor ----------
$empresa = vrPrintFila($conexion, "SELECT e.empresa, e.documento_numero, e.domicilio, e.telefono, e.email,
                                          l.localidad, l.cp, p.provincia, cf.condicion_fiscal
                                   FROM conf__empresas e
                                   LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                                   LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                                   LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = e.condicion_fiscal_id
                                   WHERE e.empresa_id = ?", "i", $empresa_idx);
$empresa_cuit = !empty($empresa['documento_numero']) ? number_format((float) $empresa['documento_numero'], 0, '', '') : '';

// Datos fiscales del emisor a la fecha de emisión (vigencia de Parámetros de Empresa), como en la factura
$parametros = vrPrintFila($conexion, "SELECT p.ingresos_brutos_nro, p.inicio_actividades, cf.condicion_fiscal
                                      FROM gestion__empresas_parametros p
                                      LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = p.condicion_fiscal_id
                                      WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
                                      ORDER BY (p.f_desde <= ? AND (p.f_hasta IS NULL OR p.f_hasta >= ?)) DESC,
                                               (p.f_hasta IS NULL) DESC, p.f_desde DESC, p.empresa_parametro_id DESC
                                      LIMIT 1", "iss", $empresa_idx, $f_emision, $f_emision);
if (!empty($parametros['condicion_fiscal'])) {
    $empresa['condicion_fiscal'] = $parametros['condicion_fiscal'];
}
$logo_src = impresion_logo_empresa($conexion, $empresa_idx);

$sucursal = !empty($remito['sucursal_id'])
    ? vrPrintFila($conexion, "SELECT sucursal_nombre, direccion, telefono FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $remito['sucursal_id'], $empresa_idx)
    : [];

// ---------- Destinatario ----------
$entidad_id = intval($remito['entidad_id'] ?? 0);
$cliente = $entidad_id > 0
    ? vrPrintFila($conexion, "SELECT e.entidad_nombre, e.cuit, e.domicilio_legal, l.localidad, p.provincia
                              FROM gestion__entidades e
                              LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                              LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                              WHERE e.entidad_id = ? AND e.empresa_id = ?", "ii", $entidad_id, $empresa_idx)
    : [];
$cliente_sucursal = !empty($remito['entidad_sucursal_id'])
    ? vrPrintFila($conexion, "SELECT s.sucursal_nombre, s.sucursal_direccion, l.localidad
                              FROM gestion__entidades_sucursales s
                              LEFT JOIN conf__localidades l ON l.localidad_id = s.localidad_id
                              WHERE s.sucursal_id = ? AND s.empresa_id = ?", "ii", $remito['entidad_sucursal_id'], $empresa_idx)
    : [];
$cliente_nombre = $entidad_id > 0
    ? ($cliente['entidad_nombre'] ?? $remito['entidad_nombre'])
    : (trim((string) ($remito['consumidor_final_nombre'] ?? '')) ?: 'Consumidor Final');
$cliente_cuit = preg_replace('/\D/', '', (string) ($cliente['cuit'] ?? ''));
$cliente_domicilio = trim(implode(' - ', array_filter([
    $cliente['domicilio_legal'] ?? '',
    $cliente['localidad'] ?? '',
    $cliente['provincia'] ?? ''
])));
$cliente_condicion = $remito['condicion_fiscal'] ?? 'Consumidor Final';

// ---------- Comprobante ----------
$tipo = vrPrintFila($conexion, "SELECT comprobante_subgrupo_id, comprobante_fiscal_id FROM gestion__comprobantes_tipos
                                WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?)", "ii", $remito['comprobante_tipo_id'], $empresa_idx);
$fiscal = intval($tipo['comprobante_fiscal_id'] ?? 0) > 0;
// El remito no lleva la letra de la factura: R si es un comprobante fiscal (cód. 091), X si no
$letra = $fiscal ? 'R' : 'X';
$titulo_tipo = 'REMITO';
$pto_vta = emp_pv_numero($conexion, $empresa_idx, $remito['comprobante_pv']);
$nro = intval($remito['comprobante_nro'] ?? 0);
$numero = emp_numero_comprobante($conexion, $empresa_idx, $remito['comprobante_pv'], $nro);
$estado = obtenerCodigoEstandarEstado($conexion, intval($remito['tabla_estado_registro_id'])) ?: [];
$remito['estado_registro'] = $estado['estado_registro'] ?? '';
// Sin validez: los estados previos a la confirmación y los dados de baja
$confirmado = $nro > 0 && !in_array(strtoupper((string) ($estado['codigo_estandar'] ?? '')), ['BORRADOR', 'PEND_APROB', 'ELIMINADO', 'ANULADO', 'INACTIVO'], true);

$detalles = $remito['detalles'] ?? [];
// Pedidos de origen (columna solo si alguna línea viene de un pedido)
$pedidos_origen = array_values(array_unique(array_filter(array_map(function ($d) {
    return intval($d['pedido_comprobante_nro'] ?? 0);
}, $detalles))));
$total_unidades = array_sum(array_map(function ($d) { return floatval($d['cantidad']); }, $detalles));
$neto = array_sum(array_map(function ($d) { return floatval($d['importe_linea']); }, $detalles));
$iva = array_sum(array_map(function ($d) { return floatval($d['iva_importe']); }, $detalles));
$total = $neto + $iva;

$etiquetas_copia = ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO'];

// ---------- Configuración de impresión de la empresa (core/impresion.php) ----------
$cfg_impresion = impresion_config($conexion, $empresa_idx, intval($tipo['comprobante_subgrupo_id'] ?? 0));
if (!isset($_GET['copias'])) $copias = $cfg_impresion['copias_defecto'];
if (!$cfg_impresion['mostrar_logo']) $logo_src = '';
$color_acento = $cfg_impresion['color_acento'] ?: '#d9d9d9';
$url_copias = function ($n, $val = null) use ($venta_remito_id, $empresa_idx, $valorizado) {
    return '?venta_remito_id=' . $venta_remito_id . '&empresa_idx=' . $empresa_idx . '&copias=' . $n
        . '&valorizado=' . (($val === null ? $valorizado : $val) ? 1 : 0);
};

// Venta por presentación: las líneas en cajas se muestran en cajas (core/ventas_presentaciones.php)
$detalles = vpre_lineas_impresion($conexion, $empresa_idx, 'remito', $detalles);

// Plantilla compartida (gestion / gestion_normal): usa las variables y helpers de este archivo
include impresion_plantilla('remito', $cfg_impresion['formato']);

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
