<?php
/**
 * Importación de "Mis Comprobantes Emitidos" de ARCA a Facturas de Clientes (gestion_normal, página 556).
 *
 * El navegador lee el .xlsx / .csv que baja la web de ARCA y manda una fila por comprobante (ver
 * ventas_facturas.js, vfiLeerArchivoArca). Acá se valida todo de nuevo: el navegador solo arma las filas.
 *
 * Cada comprobante entra Confirmado (5) con el número y el CAE de ARCA — ya está autorizado, no pasa por
 * Borrador ni se vuelve a pedir CAE — y con su comprobante espejo y su asiento, igual que una factura
 * aprobada (vfArcaGuardarAutorizacion). Duplicado = mismo tipo + punto de venta + número ya cargado
 * (cualquier estado salvo Eliminado). Una línea de detalle por alícuota, con el producto / concepto que se
 * elige al importar (sin stock), con los importes de ARCA tal cual (no se recalcula el IVA).
 *
 * No se importan: comprobantes con Otros Tributos (percepciones: no se sabe la jurisdicción), en moneda
 * extranjera, rangos (Número Desde ≠ Hasta), ni tipos que el punto de venta no tenga habilitados.
 * No se hace la post-confirmación (cobro automático a consumidor final, aplicación de NC a su factura).
 */

require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/periodos_fiscales_core_model.php';

const VFI_ESTADO_CONFIRMADO = 5;
const VFI_ESTADO_ELIMINADO = 6;
// Alícuotas gravadas de "Mis Comprobantes Emitidos" (columnas "Neto Grav. IVA x%" / "IVA x%")
const VFI_ALICUOTAS = ['0', '2.5', '5', '10.5', '21', '27'];
const VFI_TOLERANCIA = 0.02;
// En las B, ARCA informa neto e IVA derivados del total y no siempre suman exacto: el total autorizado
// manda y una diferencia de hasta este importe se absorbe en el IVA de la línea gravada más grande.
const VFI_AJUSTE_REDONDEO_MAX = 0.10;

function vfiError(array $fila, string $mensaje): array
{
    $fila['estado'] = 'error';
    $fila['mensaje'] = $mensaje;
    return $fila;
}

function vfiFecha($valor): ?string
{
    $v = trim((string)$valor);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
    return $v;
}

// Puntos de venta de la empresa por código fiscal de ARCA (solo los que el usuario puede usar)
function vfiPuntosVentaPorCodigo($conexion, $empresa_id): array
{
    $pvs = vfPuntosVenta($conexion, $empresa_id);
    $por_codigo = [];
    foreach ($pvs as $pv) {
        $cod = intval($pv['codigo_fiscal']);
        if ($cod > 0) $por_codigo[$cod][] = $pv;
    }
    return $por_codigo;
}

// Tipo de venta habilitado en el punto de venta con ese código ARCA (comprobante_fiscal_id)
function vfiTipoPorCodigoArca($conexion, $empresa_id, $punto_venta_id, $pagina_id, $codigo_arca, array &$cache): ?array
{
    if (!isset($cache[$punto_venta_id])) $cache[$punto_venta_id] = vfTiposPorPuntoVenta($conexion, $empresa_id, $punto_venta_id, $pagina_id);
    $encontrados = array_values(array_filter($cache[$punto_venta_id], function ($t) use ($codigo_arca) {
        return intval($t['comprobante_fiscal_id']) === $codigo_arca;
    }));
    return count($encontrados) === 1 ? $encontrados[0] : null;
}

function vfiFacturaExistente($conexion, $empresa_id, $tipo_id, $punto_venta_id, $nro): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT venta_factura_id, tabla_estado_registro_id FROM gestion__ventas_facturas
                                       WHERE empresa_id = ? AND comprobante_tipo_id = ? AND punto_venta_id = ? AND comprobante_nro = ?
                                         AND tabla_estado_registro_id <> ? LIMIT 1");
    $eliminado = VFI_ESTADO_ELIMINADO;
    mysqli_stmt_bind_param($stmt, 'iiiii', $empresa_id, $tipo_id, $punto_venta_id, $nro, $eliminado);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function vfiEntidadPorCuit($conexion, $empresa_id, $cuit): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT e.entidad_id, e.entidad_nombre,
                                              (SELECT MIN(es.sucursal_id) FROM gestion__entidades_sucursales es
                                                WHERE es.entidad_id = e.entidad_id AND es.empresa_id = e.empresa_id AND es.tabla_estado_registro_id = 1) AS entidad_sucursal_id
                                       FROM gestion__entidades e
                                       WHERE e.empresa_id = ? AND e.cuit = ? AND e.tabla_estado_registro_id = 1
                                       ORDER BY e.entidad_id LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'is', $empresa_id, $cuit);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Alta mínima de un cliente que ARCA trae y la empresa no tiene: razón social, CUIT y rol CLI.
function vfiCrearCliente($conexion, $empresa_id, $cuit, $denominacion): int
{
    $nombre = mb_substr(trim($denominacion) !== '' ? trim($denominacion) : "CUIT $cuit", 0, 200);
    // 30/33/34: persona jurídica; el resto, física (gestion__entidades_tipos)
    $tipo = in_array(substr($cuit, 0, 2), ['30', '33', '34'], true) ? 2 : 1;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades (empresa_id, entidad_nombre, entidad_tipo_id, cuit, domicilio_legal, fecha_alta, tabla_estado_registro_id)
                                       VALUES (?, ?, ?, ?, '', NOW(), 1)");
    mysqli_stmt_bind_param($stmt, 'isis', $empresa_id, $nombre, $tipo, $cuit);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo crear el cliente: ' . mysqli_stmt_error($stmt));
    $entidad_id = intval(mysqli_insert_id($conexion));
    mysqli_stmt_close($stmt);
    actualizarRolIndividualEntidad($conexion, $entidad_id, 'CLI', 1, null, $empresa_id);
    return $entidad_id;
}

// Producto / concepto para las líneas: de la empresa, activo, de venta y sin stock.
function vfiProductosConcepto($conexion, $empresa_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT producto_id, producto_codigo, producto_nombre FROM gestion__productos
                                       WHERE empresa_id = ? AND aplica_venta = 1 AND controla_stock = 0 AND tabla_estado_registro_id = 1
                                       ORDER BY producto_nombre");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfiProductoValido($conexion, $empresa_id, $producto_id): bool
{
    foreach (vfiProductosConcepto($conexion, $empresa_id) as $p) {
        if (intval($p['producto_id']) === intval($producto_id)) return true;
    }
    return false;
}

// Alícuota de la empresa marcada como exenta / no gravada
function vfiAlicuotaEspecial($conexion, $empresa_id, string $columna): int
{
    $col = $columna === 'es_exento' ? 'es_exento' : 'es_no_gravado';
    $stmt = mysqli_prepare($conexion, "SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas
                                       WHERE empresa_id = ? AND $col = 1 AND tabla_estado_registro_id = 1 ORDER BY iva_alicuota_id LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) throw new RuntimeException('La empresa no tiene configurada la alícuota ' . ($col === 'es_exento' ? 'Exento' : 'No Gravado') . '.');
    return intval($row['iva_alicuota_id']);
}

/**
 * Valida una fila del archivo y resuelve todo lo necesario para grabarla.
 * Devuelve la fila con estado 'listo' | 'duplicado' | 'error', mensaje y los datos resueltos.
 */
function vfiAnalizarFila($conexion, $empresa_id, $pagina_id, array $f, array $pvs_por_codigo, array &$cache_tipos): array
{
    $codigo_tipo = intval($f['tipo_codigo'] ?? 0);
    $pv_codigo = intval($f['pv'] ?? 0);
    $nro = intval($f['nro_desde'] ?? 0);
    $nro_hasta = intval($f['nro_hasta'] ?? 0) ?: $nro;
    $doc_nro = preg_replace('/\D/', '', (string)($f['doc_nro'] ?? ''));
    $doc_tipo = strtoupper(trim((string)($f['doc_tipo'] ?? '')));
    $fila = [
        'indice' => intval($f['indice'] ?? 0),
        'fecha' => vfiFecha($f['fecha'] ?? ''),
        'tipo_texto' => trim((string)($f['tipo'] ?? '')),
        'pv' => $pv_codigo, 'nro' => $nro,
        'cae' => preg_replace('/\D/', '', (string)($f['cae'] ?? '')),
        'doc_tipo' => $doc_tipo, 'doc_nro' => $doc_nro,
        'denominacion' => trim((string)($f['denominacion'] ?? '')),
        'total' => round(floatval($f['total'] ?? 0), 2),
        'cliente_nuevo' => false, 'entidad_id' => 0, 'entidad_sucursal_id' => 0, 'cliente_nombre' => '',
    ];

    if ($codigo_tipo <= 0 || $pv_codigo <= 0 || $nro <= 0) return vfiError($fila, 'Faltan el tipo, el punto de venta o el número.');
    if (!$fila['fecha']) return vfiError($fila, 'La fecha no es válida.');
    if ($nro_hasta !== $nro) return vfiError($fila, "Rango de números ($nro a $nro_hasta): no se importa, cárguelo a mano.");
    $moneda = strtoupper(trim((string)($f['moneda'] ?? '$')));
    if (!in_array($moneda, ['$', 'PES', 'ARS', ''], true)) return vfiError($fila, "Moneda $moneda: solo se importan comprobantes en pesos.");
    if (abs(floatval($f['otros_tributos'] ?? 0)) >= VFI_TOLERANCIA) return vfiError($fila, 'Tiene Otros Tributos (percepciones): cárguelo a mano.');

    $pvs = $pvs_por_codigo[$pv_codigo] ?? [];
    if (count($pvs) === 0) return vfiError($fila, "La empresa no tiene el punto de venta $pv_codigo (o no tiene acceso a él).");
    if (count($pvs) > 1) return vfiError($fila, "Hay más de un punto de venta con el código $pv_codigo.");
    $pv = $pvs[0];
    $fila['punto_venta_id'] = intval($pv['punto_venta_id']);
    $fila['sucursal_id'] = intval($pv['sucursal_id']);

    $tipo = vfiTipoPorCodigoArca($conexion, $empresa_id, $fila['punto_venta_id'], $pagina_id, $codigo_tipo, $cache_tipos);
    if (!$tipo) return vfiError($fila, "El punto de venta $pv_codigo no tiene habilitado un único tipo con código ARCA $codigo_tipo ({$fila['tipo_texto']}).");
    $fila['comprobante_tipo_id'] = intval($tipo['comprobante_tipo_id']);
    $fila['comprobante_tipo'] = $tipo['comprobante_tipo'];

    $existe = vfiFacturaExistente($conexion, $empresa_id, $fila['comprobante_tipo_id'], $fila['punto_venta_id'], $nro);
    if ($existe) {
        $fila['estado'] = 'duplicado';
        $fila['mensaje'] = 'Ya está cargado (factura #' . intval($existe['venta_factura_id']) . ').';
        return $fila;
    }

    // Líneas por alícuota: importes de ARCA tal cual
    $lineas = [];
    $suma = 0.0;
    $iva_total = 0.0;
    foreach (VFI_ALICUOTAS as $pct) {
        $neto = round(floatval($f['neto'][$pct] ?? 0), 2);
        $iva = round(floatval($f['iva'][$pct] ?? 0), 2);
        if (abs($neto) < 0.005 && abs($iva) < 0.005) continue;
        $lineas[] = ['tipo' => 'gravado', 'porcentaje' => floatval($pct), 'neto' => $neto, 'iva' => $iva];
        $suma += $neto + $iva;
        $iva_total += $iva;
    }
    foreach (['no_gravado' => 'es_no_gravado', 'exento' => 'es_exento'] as $campo => $marca) {
        $imp = round(floatval($f[$campo] ?? 0), 2);
        if (abs($imp) < 0.005) continue;
        $lineas[] = ['tipo' => $marca, 'porcentaje' => 0.0, 'neto' => $imp, 'iva' => 0.0];
        $suma += $imp;
    }
    if (!$lineas) return vfiError($fila, 'No tiene importes.');
    $diferencia = round($fila['total'] - $suma, 2);
    $aviso_redondeo = '';
    if (abs($diferencia) >= 0.005) {
        $mayor = null;
        foreach ($lineas as $k => $l) {
            if ($l['tipo'] === 'gravado' && $l['porcentaje'] > 0 && ($mayor === null || $l['neto'] > $lineas[$mayor]['neto'])) $mayor = $k;
        }
        if ($mayor === null || abs($diferencia) > VFI_AJUSTE_REDONDEO_MAX) {
            return vfiError($fila, 'Los importes no suman el total (' . number_format($suma, 2, ',', '.') . ' contra ' . number_format($fila['total'], 2, ',', '.') . ').');
        }
        $lineas[$mayor]['iva'] = round($lineas[$mayor]['iva'] + $diferencia, 2);
        $iva_total += $diferencia;
        $aviso_redondeo = ' Redondeo de ARCA: ' . number_format($diferencia, 2, ',', '.') . ' ajustado en el IVA.';
    }
    try {
        foreach ($lineas as &$l) {
            $l['iva_alicuota_id'] = $l['tipo'] === 'gravado' ? emp_iva_alicuota_id($conexion, $empresa_id, $l['porcentaje'])
                                                             : vfiAlicuotaEspecial($conexion, $empresa_id, $l['tipo']);
        }
        unset($l);
    } catch (RuntimeException $e) {
        return vfiError($fila, $e->getMessage());
    }
    $fila['lineas'] = $lineas;
    $fila['importe_iva'] = round($iva_total, 2);

    // Cliente: CUIT / CUIL por número; sin documento o con otro documento, consumidor final
    if (in_array($doc_tipo, ['CUIT', 'CUIL', 'CDI'], true) && strlen($doc_nro) === 11) {
        $ent = vfiEntidadPorCuit($conexion, $empresa_id, $doc_nro);
        if ($ent) {
            $fila['entidad_id'] = intval($ent['entidad_id']);
            $fila['entidad_sucursal_id'] = intval($ent['entidad_sucursal_id'] ?? 0);
            $fila['cliente_nombre'] = $ent['entidad_nombre'];
        } else {
            $fila['cliente_nuevo'] = true;
            $fila['cliente_nombre'] = $fila['denominacion'];
        }
    } else {
        $fila['cliente_nombre'] = 'Consumidor Final' . ($fila['denominacion'] !== '' ? ' - ' . $fila['denominacion'] : '');
        $fila['consumidor_final_nombre'] = mb_substr(trim($fila['denominacion'] . ($doc_nro !== '' && $doc_nro !== '0' ? " ($doc_tipo $doc_nro)" : '')), 0, 150);
    }

    $fila['estado'] = 'listo';
    $fila['mensaje'] = ($fila['cliente_nuevo'] ? 'Listo (cliente nuevo: se da de alta).' : 'Listo para importar.') . $aviso_redondeo;
    return $fila;
}

function vfiAnalizarArchivo($conexion, $empresa_id, $pagina_id, array $filas): array
{
    $pvs = vfiPuntosVentaPorCodigo($conexion, $empresa_id);
    $cache = [];
    $res = ['items' => [], 'listos' => 0, 'duplicados' => 0, 'errores' => 0, 'clientes_nuevos' => 0];
    foreach ($filas as $f) {
        $fila = vfiAnalizarFila($conexion, $empresa_id, $pagina_id, is_array($f) ? $f : [], $pvs, $cache);
        if ($fila['estado'] === 'listo') { $res['listos']++; if ($fila['cliente_nuevo']) $res['clientes_nuevos']++; }
        elseif ($fila['estado'] === 'duplicado') $res['duplicados']++;
        else $res['errores']++;
        unset($fila['lineas']);
        $res['items'][] = $fila;
    }
    return $res;
}

// Graba una fila ya analizada como factura confirmada, en su propia transacción.
function vfiGrabarFila($conexion, $empresa_id, $pagina_id, $usuario_id, array $fila, int $producto_id, bool $crear_clientes): array
{
    if ($fila['cliente_nuevo'] && !$crear_clientes) {
        return ['estado' => 'error', 'mensaje' => 'El cliente no existe y no se eligió darlo de alta.'];
    }
    mysqli_begin_transaction($conexion);
    try {
        if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            AccesosSucursalesHelper::exigirAccesoPuntoVenta($conexion, $empresa_id, $fila['punto_venta_id'], $usuario_id);
        }
        // Se vuelve a mirar dentro de la transacción: dos importaciones a la vez no duplican
        if (vfiFacturaExistente($conexion, $empresa_id, $fila['comprobante_tipo_id'], $fila['punto_venta_id'], $fila['nro'])) {
            mysqli_rollback($conexion);
            return ['estado' => 'duplicado', 'mensaje' => 'Ya está cargado.'];
        }
        $entidad_id = intval($fila['entidad_id']) ?: null;
        $entidad_sucursal_id = intval($fila['entidad_sucursal_id']) ?: null;
        $consumidor_final = null;
        if ($fila['cliente_nuevo']) {
            // otra fila del mismo archivo pudo haberlo creado
            $ent = vfiEntidadPorCuit($conexion, $empresa_id, $fila['doc_nro']);
            $entidad_id = $ent ? intval($ent['entidad_id']) : vfiCrearCliente($conexion, $empresa_id, $fila['doc_nro'], $fila['denominacion']);
            $entidad_sucursal_id = $ent ? (intval($ent['entidad_sucursal_id'] ?? 0) ?: null) : null;
        } elseif (!$entidad_id) {
            $consumidor_final = $fila['consumidor_final_nombre'] !== '' ? $fila['consumidor_final_nombre'] : null;
        }
        $cond = vfObtenerCondicionDestinatario($conexion, intval($entidad_id), $empresa_id);
        $condicion_pago_id = intval($cond['condicion_pago_id'] ?? 0);
        $moneda_id = emp_moneda_base_id($conexion, $empresa_id);

        $detalles = [];
        $neto = 0.0;
        foreach ($fila['lineas'] as $l) {
            $detalles[] = ['producto_id' => $producto_id, 'cantidad' => 1, 'precio_unitario' => $l['neto'], 'descuento_general_pct' => 0,
                           'iva_alicuota_id' => $l['iva_alicuota_id'], 'iva_porcentaje' => $l['porcentaje'],
                           '_bruto' => $l['neto'], '_desc' => 0.0, '_neto' => $l['neto'], '_iva' => $l['iva'], '_linea' => $l['neto'] + $l['iva']];
            $neto += $l['neto'];
        }
        $neto = round($neto, 2);
        $iva = $fila['importe_iva'];
        $total = $fila['total'];
        $cae = $fila['cae'] !== '' ? $fila['cae'] : null;
        $obs = 'Importado de ARCA (Mis Comprobantes Emitidos)';
        $estado = VFI_ESTADO_CONFIRMADO;
        $f = $fila['fecha'];
        $tc = 1.0;
        $cero = 0.0;

        // Mismas columnas que guardarVentaFactura(); exento / no gravado van por la alícuota de cada línea
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__ventas_facturas
                (empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id, comprobante_nro,
                 comprobante_id, entidad_id, entidad_sucursal_id, consumidor_final_nombre, condicion_pago_id, f_emision,
                 f_contabilidad, f_vto, moneda_id, tipo_cambio, importe_bruto, descuento_general_pct,
                 descuento_general, importe_neto, importe_exento, importe_no_gravado, importe_iva,
                 importe_otros_impuestos, importe_total, observaciones, CAE, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, ?, 0, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiiiiisisssiddddddssi',
            $empresa_id, $fila['sucursal_id'], $fila['comprobante_tipo_id'], $fila['punto_venta_id'], $fila['nro'],
            $entidad_id, $entidad_sucursal_id, $consumidor_final, $condicion_pago_id, $f, $f, $f, $moneda_id, $tc,
            $neto, $cero, $neto, $iva, $total, $obs, $cae, $estado);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $factura_id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);

        vfInsertarDetallesFactura($conexion, $factura_id, $detalles);

        $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__ventas_facturas WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $factura_id);
        mysqli_stmt_execute($stmt);
        $factura = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $tabla_origen_id = obtenerTablaOrigenPorPagina($conexion, $pagina_id);
        if (!$tabla_origen_id) throw new Exception('No se pudo determinar la tabla de origen.');
        $comprobante_id = syncComprobante($conexion, vfDatosComprobanteFactura($factura, $empresa_id, $estado), $tabla_origen_id);
        if (!$comprobante_id) throw new Exception('No se pudo sincronizar el comprobante.');
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $factura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        pfExigirDocumentoAbierto($conexion, $empresa_id, 'VENTA', $factura_id, 'importar el comprobante');

        $res_asiento = vfGenerarAsientoContableFactura($conexion, $factura_id, $empresa_id, true);
        if (empty($res_asiento['success'])) {
            throw new Exception('No se pudo generar el asiento: ' . ($res_asiento['error'] ?? $res_asiento['message'] ?? 'error desconocido'));
        }
        mysqli_commit($conexion);
        return ['estado' => 'importado', 'mensaje' => 'Importado (factura #' . $factura_id . ').', 'venta_factura_id' => $factura_id,
                'cliente_creado' => $fila['cliente_nuevo'] && !$ent];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log('ERROR vfiGrabarFila (' . $fila['tipo_texto'] . ' ' . $fila['pv'] . '-' . $fila['nro'] . '): ' . $e->getMessage());
        return ['estado' => 'error', 'mensaje' => $e->getMessage()];
    }
}

function vfiImportar($conexion, $empresa_id, $pagina_id, $usuario_id, array $filas, int $producto_id, bool $crear_clientes): array
{
    if (!vfiProductoValido($conexion, $empresa_id, $producto_id)) {
        return ['success' => false, 'message' => 'Elija el producto / concepto (de venta y sin stock) para las líneas.'];
    }
    $pvs = vfiPuntosVentaPorCodigo($conexion, $empresa_id);
    $cache = [];
    $res = ['importados' => 0, 'duplicados' => 0, 'errores' => 0, 'clientes_creados' => 0, 'items' => []];
    foreach ($filas as $f) {
        $fila = vfiAnalizarFila($conexion, $empresa_id, $pagina_id, is_array($f) ? $f : [], $pvs, $cache);
        $r = $fila['estado'] === 'listo'
            ? vfiGrabarFila($conexion, $empresa_id, $pagina_id, $usuario_id, $fila, $producto_id, $crear_clientes)
            : ['estado' => $fila['estado'], 'mensaje' => $fila['mensaje']];
        if ($r['estado'] === 'importado') { $res['importados']++; if (!empty($r['cliente_creado'])) $res['clientes_creados']++; }
        elseif ($r['estado'] === 'duplicado') $res['duplicados']++;
        else $res['errores']++;
        $res['items'][] = ['indice' => $fila['indice'], 'tipo_texto' => $fila['tipo_texto'], 'pv' => $fila['pv'], 'nro' => $fila['nro'],
                           'cliente_nombre' => $fila['cliente_nombre'], 'total' => $fila['total']] + $r;
    }
    return ['success' => true, 'data' => $res,
            'message' => "Importados: {$res['importados']}. Ya cargados: {$res['duplicados']}. Con error: {$res['errores']}."];
}
