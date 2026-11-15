<?php
// ventas_pedidos_print.php — impresión de un pedido de cliente (HTML para imprimir / PDF del navegador).
// Mismo formato que el remito: usa las plantillas templates/impresion/remito_<formato>.php con las variables
// que ellas esperan ($remito = el pedido, $titulo_tipo = 'PEDIDO') más los datos propios del pedido en $datos_extra,
// sin recuadro de conformidad. Por defecto sale valorizado.
// Guard: sesión + empresa del usuario + acceso a Pedidos Realizados o a Nuevos Pedidos de Clientes.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['ventas_pedidos.php', 'ventas_pedidos_gestion.php']]);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ventas_pedidos_model.php';
require_once __DIR__ . '/../../core/impresion.php';
require_once __DIR__ . '/../../core/formato.php';

$pagina_idx = 55;
$empresa_idx = $ctx['empresa_id'];
$venta_pedido_id = intval($_GET['venta_pedido_id'] ?? 0);
// copias=2: ORIGINAL + DUPLICADO; copias=3: + TRIPLICADO (sin el parámetro: copias_defecto de la empresa)
$copias = max(1, min(3, intval($_GET['copias'] ?? 1)));
$valorizado = intval($_GET['valorizado'] ?? 1) === 1;

if ($venta_pedido_id <= 0) {
    http_response_code(400);
    die('Pedido no indicado');
}

$pedido = obtenerPedidoVentaPorId($conexion, $venta_pedido_id, $empresa_idx, $pagina_idx);
if (!$pedido) {
    http_response_code(404);
    die('Pedido no encontrado');
}

if (!empty($pedido['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($pedido['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de este pedido');
    }
}

function vpPrintFila($conexion, $sql, $tipos, ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("ventas_pedidos_print: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: [];
}

// Nombres que usan las plantillas del remito
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

$f_emision = substr((string) $pedido['f_emision'], 0, 10);

// ---------- Emisor ----------
$empresa = vpPrintFila($conexion, "SELECT e.empresa, e.documento_numero, e.domicilio, e.telefono, e.email,
                                          l.localidad, l.cp, p.provincia, cf.condicion_fiscal
                                   FROM conf__empresas e
                                   LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                                   LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                                   LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = e.condicion_fiscal_id
                                   WHERE e.empresa_id = ?", "i", $empresa_idx);
$empresa_cuit = !empty($empresa['documento_numero']) ? number_format((float) $empresa['documento_numero'], 0, '', '') : '';

// Datos fiscales del emisor a la fecha de emisión (vigencia de Parámetros de Empresa), como en el remito
$parametros = vpPrintFila($conexion, "SELECT p.ingresos_brutos_nro, p.inicio_actividades, cf.condicion_fiscal
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

$sucursal = !empty($pedido['sucursal_id'])
    ? vpPrintFila($conexion, "SELECT sucursal_nombre, direccion, telefono FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $pedido['sucursal_id'], $empresa_idx)
    : [];

// ---------- Cliente ----------
$entidad_id = intval($pedido['entidad_id'] ?? 0);
$cliente = $entidad_id > 0
    ? vpPrintFila($conexion, "SELECT e.entidad_nombre, e.cuit, e.domicilio_legal, l.localidad, p.provincia, cf.condicion_fiscal
                              FROM gestion__entidades e
                              LEFT JOIN conf__localidades l ON l.localidad_id = e.localidad_id
                              LEFT JOIN conf__provincias p ON p.provincia_id = l.provincia_id
                              LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                                     ON ecf.entidad_condicion_fiscal_id = (
                                          SELECT ecf2.entidad_condicion_fiscal_id
                                          FROM gestion__entidades_condiciones_fiscales ecf2
                                          WHERE ecf2.entidad_id = e.entidad_id
                                            AND ecf2.tabla_estado_registro_id = 1
                                            AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= ?)
                                            AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= ?)
                                          ORDER BY ecf2.f_desde DESC, ecf2.entidad_condicion_fiscal_id DESC
                                          LIMIT 1)
                              LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = ecf.condicion_fiscal_id
                              WHERE e.entidad_id = ? AND e.empresa_id = ?", "ssii", $f_emision, $f_emision, $entidad_id, $empresa_idx)
    : [];
$cliente_sucursal = !empty($pedido['entidad_sucursal_id'])
    ? vpPrintFila($conexion, "SELECT sucursal_nombre FROM gestion__entidades_sucursales WHERE sucursal_id = ? AND empresa_id = ?",
                  "ii", $pedido['entidad_sucursal_id'], $empresa_idx)
    : [];
$cliente_nombre = $entidad_id > 0 ? ($cliente['entidad_nombre'] ?? $pedido['entidad_nombre']) : 'Consumidor Final';
$cliente_cuit = preg_replace('/\D/', '', (string) ($cliente['cuit'] ?? ''));
$cliente_domicilio = trim(implode(' - ', array_filter([
    $cliente['domicilio_legal'] ?? '',
    $cliente['localidad'] ?? '',
    $cliente['provincia'] ?? ''
])));
$cliente_condicion = $cliente['condicion_fiscal'] ?? 'Consumidor Final';

// ---------- Datos propios del pedido (la plantilla los muestra debajo del cliente) ----------
$condicion_pago = !empty($pedido['condicion_pago_id'])
    ? vpPrintFila($conexion, "SELECT condicion_pago FROM gestion__condiciones_pago WHERE condicion_pago_id = ? AND empresa_id = ?",
                  "ii", $pedido['condicion_pago_id'], $empresa_idx)
    : [];
$lugar_entrega = trim(implode(' - ', array_filter([
    $cliente_sucursal['sucursal_nombre'] ?? '',
    trim((string) ($pedido['direccion_entrega'] ?? ''))
])));
$datos_extra = array_filter([
    'Lugar de entrega' => $lugar_entrega,
    'Entrega estimada' => !empty($pedido['f_entrega_estimada']) ? fmt_fecha($pedido['f_entrega_estimada']) : '',
    'Condición de pago' => $condicion_pago['condicion_pago'] ?? '',
    'Moneda' => $pedido['moneda'] ?? '',
], 'strlen');
$cliente_sucursal = [];   // ya va en "Lugar de entrega"
$mostrar_conformidad = false;

// ---------- Comprobante ----------
$tipo = vpPrintFila($conexion, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_tipos
                                WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?)", "ii", $pedido['comprobante_tipo_id'], $empresa_idx);
$fiscal = false;   // el pedido no es un comprobante fiscal
$letra = 'X';
$titulo_tipo = 'PEDIDO';
$pto_vta = emp_pv_numero($conexion, $empresa_idx, $pedido['punto_venta_id']);
$nro = intval($pedido['comprobante_nro'] ?? 0);
$numero = emp_numero_comprobante($conexion, $empresa_idx, $pedido['punto_venta_id'], $nro);
// Sin validez: los estados previos a la confirmación y los dados de baja
$confirmado = $nro > 0 && !in_array(strtoupper((string) ($pedido['codigo_estandar'] ?? '')), ['BORRADOR', 'PEND_APROB', 'ELIMINADO', 'ANULADO', 'INACTIVO'], true);

// Las plantillas leen el documento de $remito y las líneas con los nombres del remito
$remito = $pedido;
$detalles = array_map(function ($d) {
    $d['precio_unitario_bruto'] = $d['precio_unitario'];
    $d['importe_linea'] = $d['neto_gravado'] + $d['no_gravado'] + $d['exento'];
    return $d;
}, $pedido['detalles'] ?? []);
$pedidos_origen = [];
$total_unidades = array_sum(array_map(function ($d) { return floatval($d['cantidad']); }, $detalles));
// Totales del encabezado del pedido (los que muestra la pantalla; la suma de las líneas puede diferir en redondeo)
$neto = floatval($pedido['subtotal'] ?? 0);
$iva = floatval($pedido['impuestos'] ?? 0);
$total = floatval($pedido['total'] ?? ($neto + $iva));

$etiquetas_copia = ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO'];

// ---------- Configuración de impresión de la empresa (core/impresion.php) ----------
$cfg_impresion = impresion_config($conexion, $empresa_idx, intval($tipo['comprobante_subgrupo_id'] ?? 0));
if (!isset($_GET['copias'])) $copias = $cfg_impresion['copias_defecto'];
if (!$cfg_impresion['mostrar_logo']) $logo_src = '';
$color_acento = $cfg_impresion['color_acento'] ?: '#d9d9d9';
$url_copias = function ($n, $val = null) use ($venta_pedido_id, $empresa_idx, $valorizado) {
    return '?venta_pedido_id=' . $venta_pedido_id . '&empresa_idx=' . $empresa_idx . '&copias=' . $n
        . '&valorizado=' . (($val === null ? $valorizado : $val) ? 1 : 0);
};

// Venta por presentación: las líneas en cajas se muestran en cajas (core/ventas_presentaciones.php)
$detalles = vpre_lineas_impresion($conexion, $empresa_idx, 'pedido', $detalles);

// Plantilla compartida con el remito (gestion / gestion_normal)
include impresion_plantilla('remito', $cfg_impresion['formato']);

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
