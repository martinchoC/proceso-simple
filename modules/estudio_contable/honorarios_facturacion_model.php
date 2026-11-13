<?php
/**
 * Modelo de Datos: Facturación Recurrente y Emisión en Lote de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion_model.php)
 * Tablas: estudio__clientes_abonos, estudio__honorarios_novedades; las facturas se emiten con el circuito de
 * gestion/ventas_facturas_model.php (gestion__ventas_facturas + _detalles, gestion__comprobantes, asiento)
 */

declare(strict_types=1);

require_once __DIR__ . '/../gestion/ventas_facturas_model.php';
require_once __DIR__ . '/../../core/entidades_roles.php';

/**
 * Consolidación de liquidación de honorarios del período
 */
function honorarios_facturacion_consolidar(mysqli $conexion, int $empresa_id, int $periodo_fiscal): array
{
    // Obtener abonos activos
    $sql = "SELECT a.abono_id, a.empresa_id, a.cliente_id, a.concepto_id, a.punto_venta_id,
                   a.condicion_venta_id, a.importe_base, a.dia_facturacion, a.aplica_adicionales_automaticos,
                   a.tope_comprobantes_incluidos, a.precio_excedente_comprobante,
                   a.tope_legajos_incluidos, a.precio_excedente_legajo, a.ultimo_periodo_facturado,
                   c.razon_social AS cliente_nombre, c.cuit AS cliente_cuit, c.condicion_iva_id,
                   c.cantidad_empleados, c.empresa_vinculada_id,
                   hc.nombre AS concepto_nombre, hc.producto_id,
                   cf.condicion_fiscal_codigo,
                   pv.codigo_fiscal AS punto_venta_codigo,
                   ia.iva_alicuota_id, ia.porcentaje AS iva_porcentaje
            FROM estudio__clientes_abonos a
            INNER JOIN estudio__clientes c ON c.cliente_id = a.cliente_id
            INNER JOIN estudio__honorarios_conceptos hc ON hc.concepto_id = a.concepto_id
            INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = a.punto_venta_id AND pv.empresa_id = a.empresa_id
            LEFT JOIN gestion__productos p ON p.producto_id = hc.producto_id AND p.empresa_id = a.empresa_id
            LEFT JOIN gestion__impuestos__iva_alicuotas ia ON ia.iva_alicuota_id = p.iva_alicuota_id AND ia.empresa_id = a.empresa_id
            WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 1
            ORDER BY c.razon_social ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $items = [];
    // Las novedades son del cliente, no del abono: se cargan una sola vez, en el primer abono
    // pendiente del cliente. Si el cliente tiene varios abonos, los demás no las repiten.
    $clientes_con_novedades = [];

    while ($ab = mysqli_fetch_assoc($res)) {
        $importe_base = floatval($ab['importe_base']);
        $adicional_comprobantes = 0.0;
        $adicional_legajos = 0.0;
        $excedente_leg = 0;
        $novedades_monto = 0.0;
        $detalle_novedades = [];
        $ya_facturado = ($ab['ultimo_periodo_facturado'] !== null && intval($ab['ultimo_periodo_facturado']) >= $periodo_fiscal);

        // 1. Novedades eventuales del período para este cliente (solo en su primer abono pendiente)
        if (!$ya_facturado && !isset($clientes_con_novedades[(int) $ab['cliente_id']])) {
            $clientes_con_novedades[(int) $ab['cliente_id']] = true;
            $sql_nov = "SELECT novedad_id, descripcion, cantidad, importe_unitario, importe_total
                        FROM estudio__honorarios_novedades
                        WHERE empresa_id = ? AND cliente_id = ? AND periodo = ? AND facturado = 0 AND tabla_estado_registro_id = 1";
            $stmt_n = mysqli_prepare($conexion, $sql_nov);
            mysqli_stmt_bind_param($stmt_n, "iii", $empresa_id, $ab['cliente_id'], $periodo_fiscal);
            mysqli_stmt_execute($stmt_n);
            $res_n = mysqli_stmt_get_result($stmt_n);
            while ($rn = mysqli_fetch_assoc($res_n)) {
                $novedades_monto += floatval($rn['importe_total']);
                $detalle_novedades[] = $rn;
            }
            mysqli_stmt_close($stmt_n);
        }

        // 2. Cálculo de adicionales si aplica
        if ($ab['aplica_adicionales_automaticos'] == 1) {
            // Excedente de legajos
            $legajos = intval($ab['cantidad_empleados']);
            $tope_leg = intval($ab['tope_legajos_incluidos']);
            if ($legajos > $tope_leg && $ab['precio_excedente_legajo'] > 0) {
                $excedente_leg = $legajos - $tope_leg;
                $adicional_legajos = $excedente_leg * floatval($ab['precio_excedente_legajo']);
            }
        }

        // IVA de la alícuota del producto del concepto (la misma que se usa al emitir); sin producto, 21%
        $iva_porcentaje = $ab['iva_porcentaje'] !== null ? floatval($ab['iva_porcentaje']) : 21.0;
        $importe_neto = $importe_base + $adicional_comprobantes + $adicional_legajos + $novedades_monto;
        $importe_iva = round($importe_neto * $iva_porcentaje / 100, 2);
        $importe_total = $importe_neto + $importe_iva;

        $items[] = [
            'abono_id' => $ab['abono_id'],
            'cliente_id' => $ab['cliente_id'],
            'cliente_nombre' => $ab['cliente_nombre'],
            'cliente_cuit' => $ab['cliente_cuit'],
            'condicion_fiscal_codigo' => $ab['condicion_fiscal_codigo'],
            'concepto_nombre' => $ab['concepto_nombre'],
            'producto_id' => $ab['producto_id'],
            'punto_venta_id' => $ab['punto_venta_id'],
            'punto_venta_codigo' => $ab['punto_venta_codigo'] ?? 1,
            'condicion_venta_id' => $ab['condicion_venta_id'],
            'condicion_iva_id' => $ab['condicion_iva_id'],
            'iva_alicuota_id' => $ab['iva_alicuota_id'],
            'iva_porcentaje' => $iva_porcentaje,
            'importe_base' => $importe_base,
            'adicional_legajos' => $adicional_legajos,
            'excedente_legajos' => $excedente_leg,
            'precio_excedente_legajo' => floatval($ab['precio_excedente_legajo']),
            'novedades_monto' => $novedades_monto,
            'novedades_detalle' => $detalle_novedades,
            'importe_neto' => $importe_neto,
            'importe_iva' => $importe_iva,
            'importe_total' => $importe_total,
            'ya_facturado' => $ya_facturado,
            'ultimo_periodo_facturado' => $ab['ultimo_periodo_facturado']
        ];
    }
    mysqli_stmt_close($stmt);

    return $items;
}


/**
 * Página de Facturación (ventas_facturas.php) del módulo de gestión activo de la empresa: 56 en Gestión (2),
 * 556 en Gestión Normal (5). Las facturas de honorarios se emiten con la configuración de esa página
 * (estado inicial, tabla de origen, funciones de la transición).
 */
function honorarios_pagina_ventas(mysqli $conexion, int $empresa_id): int
{
    $sql = "SELECT p.pagina_id
            FROM conf__paginas p
            INNER JOIN conf__empresas_modulos em ON em.modulo_id = p.modulo_id AND em.empresa_id = ? AND em.tabla_estado_registro_id = 1
            WHERE p.url = 'ventas_facturas.php' AND p.modulo_id IN (2, 5) AND p.tabla_estado_registro_id = 1
            ORDER BY p.modulo_id
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['pagina_id'] ?? 0);
}

/**
 * Entidad cliente de gestion__entidades para el cliente del estudio (por CUIT), con rol CLI y con la
 * condición fiscal del padrón del estudio si la entidad no tiene una vigente (la usa la relación fiscal
 * emisor × receptor para elegir la letra).
 */
function honorarios_entidad_cliente(mysqli $conexion, int $empresa_id, array $item): int
{
    $cuit_limpio = (int) preg_replace('/[^0-9]/', '', (string) $item['cliente_cuit']);
    $stmt = mysqli_prepare($conexion, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cuit_limpio);
    mysqli_stmt_execute($stmt);
    $ent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    mysqli_begin_transaction($conexion);
    try {
        if ($ent) {
            $entidad_id = (int) $ent['entidad_id'];
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades (empresa_id, entidad_nombre, cuit, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "isi", $empresa_id, $item['cliente_nombre'], $cuit_limpio);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('No se pudo dar de alta el cliente: ' . mysqli_stmt_error($stmt));
            }
            $entidad_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }
        if (!ent_tiene_rol($conexion, $entidad_id, 'CLI')) {
            actualizarRolIndividualEntidad($conexion, $entidad_id, 'CLI', 1, null, $empresa_id);
        }

        $condicion_fiscal_id = intval($item['condicion_iva_id'] ?? 0);
        if ($condicion_fiscal_id > 0) {
            $stmt = mysqli_prepare($conexion, "SELECT 1 FROM gestion__entidades_condiciones_fiscales
                                               WHERE entidad_id = ? AND tabla_estado_registro_id = 1
                                                 AND (f_desde IS NULL OR f_desde <= CURDATE())
                                                 AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                                               LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $entidad_id);
            mysqli_stmt_execute($stmt);
            $tiene = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$tiene) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_condiciones_fiscales
                                                   (entidad_id, condicion_fiscal_id, f_desde, tabla_estado_registro_id)
                                                   VALUES (?, ?, CURDATE(), 1)");
                mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $condicion_fiscal_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_commit($conexion);
        return $entidad_id;
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Factura de la empresa que corresponde al cliente según la relación fiscal emisor × receptor (página 110):
 * el tipo de la tabla de la página de ventas con ese comprobante fiscal (Factura A, B o C), nunca un id fijo.
 */
function honorarios_tipo_factura(mysqli $conexion, int $empresa_id, int $entidad_id, int $pagina_ventas): array
{
    $rel = vfObtenerRelacionFiscal($conexion, $empresa_id, $entidad_id);
    $comprobante_fiscal_id = intval($rel['relacion']['comprobante_fiscal_id'] ?? 0);
    if ($comprobante_fiscal_id <= 0) {
        throw new RuntimeException('No hay relación fiscal configurada para un cliente ' . ($rel['receptor_condicion_fiscal_nombre'] ?? '') . '.');
    }
    $tabla_id = intval(obtenerTablaOrigenPorPagina($conexion, $pagina_ventas));
    $sql = "SELECT t.comprobante_tipo_id, t.comprobante_tipo, t.letra
            FROM gestion__comprobantes_tipos t
            INNER JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id
            WHERE t.empresa_id = ? AND t.comprobante_fiscal_id = ? AND s.tabla_id = ? AND t.tabla_estado_registro_id = 1
            ORDER BY t.comprobante_tipo_id
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $comprobante_fiscal_id, $tabla_id);
    mysqli_stmt_execute($stmt);
    $tipo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$tipo) {
        throw new RuntimeException('La empresa no tiene el tipo de comprobante ' . ($rel['relacion']['comprobante_fiscal'] ?? 'fiscal') . ' para ventas.');
    }
    return $tipo;
}

/**
 * Emisión en lote: cada abono tildado genera una factura por el mismo circuito que Ventas > Facturación
 * (guardarVentaFactura + vfEjecutarTransicionEstado), así lleva líneas de detalle, espejo, asiento,
 * percepciones, control de períodos cerrados y, con ARCA habilitado, queda "Pendiente de ARCA" para pedir
 * el CAE desde Facturación. Cada factura es independiente: un abono que falla no frena a los demás.
 */
function honorarios_facturacion_emitir_lote(mysqli $conexion, int $empresa_id, int $periodo_fiscal, array $abono_ids_seleccionados, int $usuario_id): array
{
    $pagina_ventas = honorarios_pagina_ventas($conexion, $empresa_id);
    if ($pagina_ventas <= 0) {
        return ['success' => false, 'message' => 'La empresa no tiene activo el módulo de gestión con la página de Facturación.',
                'data' => null, 'error' => 'SIN_PAGINA_VENTAS'];
    }

    $items = honorarios_facturacion_consolidar($conexion, $empresa_id, $periodo_fiscal);
    $seleccionados = array_flip($abono_ids_seleccionados);
    $con_arca = vfArcaHabilitado($conexion, $empresa_id);
    $leyenda_periodo = sprintf('Período %02d/%04d', $periodo_fiscal % 100, intdiv($periodo_fiscal, 100));

    $facturados = 0;
    $pendientes_arca = 0;
    $omitidos = 0;
    $total_facturado_monto = 0.0;
    $errores = [];

    foreach ($items as $item) {
        if (!isset($seleccionados[$item['abono_id']])) {
            continue;
        }
        if ($item['ya_facturado']) {
            $omitidos++;
            continue;
        }

        $venta_factura_id = 0;
        try {
            if (intval($item['producto_id'] ?? 0) <= 0) {
                throw new RuntimeException("el concepto {$item['concepto_nombre']} no tiene producto asociado.");
            }
            $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__puntos_venta
                                               WHERE punto_venta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
            $pv_id = intval($item['punto_venta_id'] ?? 0);
            mysqli_stmt_bind_param($stmt, "ii", $pv_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            $pv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$pv) {
                throw new RuntimeException('el abono no tiene un punto de venta activo de la empresa.');
            }

            $entidad_id = honorarios_entidad_cliente($conexion, $empresa_id, $item);
            $tipo = honorarios_tipo_factura($conexion, $empresa_id, $entidad_id, $pagina_ventas);

            // Sucursal principal del cliente, si tiene (la percepción IIBB toma de ahí el lugar de la operación)
            $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__entidades_sucursales
                                               WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                               ORDER BY sucursal_numero, sucursal_id LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_id);
            mysqli_stmt_execute($stmt);
            $ent_suc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            // Líneas: abono, excedente de legajos y una por novedad, todas con el producto del concepto
            $iva_alicuota_id = intval($item['iva_alicuota_id'] ?? 0) ?: emp_iva_alicuota_id($conexion, $empresa_id, 21.0);
            $iva_porcentaje = strtoupper((string) $tipo['letra']) === 'C' ? 0.0 : floatval($item['iva_porcentaje']);
            $linea = function (float $cantidad, float $precio, string $leyenda) use ($item, $iva_alicuota_id, $iva_porcentaje): array {
                return ['producto_id' => intval($item['producto_id']), 'cantidad' => $cantidad, 'precio_unitario' => $precio,
                        'descuento_general_pct' => 0, 'iva_alicuota_id' => $iva_alicuota_id, 'iva_porcentaje' => $iva_porcentaje,
                        'leyenda' => $leyenda];
            };
            $detalles = [$linea(1.0, floatval($item['importe_base']), $item['concepto_nombre'] . ' - ' . $leyenda_periodo)];
            if (intval($item['excedente_legajos']) > 0) {
                $detalles[] = $linea((float) $item['excedente_legajos'], floatval($item['precio_excedente_legajo']),
                                     'Legajos excedentes - ' . $leyenda_periodo);
            }
            foreach ($item['novedades_detalle'] as $nov) {
                $detalles[] = $linea(floatval($nov['cantidad']), floatval($nov['importe_unitario']), (string) $nov['descripcion']);
            }

            $alta = guardarVentaFactura($conexion, $empresa_id, $pagina_ventas, [
                'entidad_id' => $entidad_id,
                'entidad_sucursal_id' => intval($ent_suc['sucursal_id'] ?? 0),
                'sucursal_id' => intval($pv['sucursal_id']),
                'punto_venta_id' => $pv_id,
                'comprobante_tipo_id' => intval($tipo['comprobante_tipo_id']),
                'condicion_pago_id' => intval($item['condicion_venta_id'] ?? 0),
                'f_emision' => date('Y-m-d'),
                'f_vto' => date('Y-m-d', strtotime('+15 days')),
                'moneda_id' => emp_moneda_base_id($conexion, $empresa_id),
                'tipo_cambio' => 1,
                'observaciones' => 'Honorarios Profesionales - ' . $leyenda_periodo,
                'detalles' => $detalles,
            ]);
            if (empty($alta['success'])) {
                throw new RuntimeException($alta['error'] ?? 'no se pudo guardar la factura.');
            }
            $venta_factura_id = intval($alta['venta_factura_id']);

            // Marca del abono y de sus novedades. Las condiciones sobre el período y sobre facturado = 0
            // evitan facturar dos veces si otra emisión del mismo lote corrió en paralelo.
            mysqli_begin_transaction($conexion);
            try {
                $stmt_ab = mysqli_prepare($conexion, "UPDATE estudio__clientes_abonos SET ultimo_periodo_facturado = ?
                                                      WHERE abono_id = ? AND empresa_id = ?
                                                        AND (ultimo_periodo_facturado IS NULL OR ultimo_periodo_facturado < ?)");
                mysqli_stmt_bind_param($stmt_ab, "iiii", $periodo_fiscal, $item['abono_id'], $empresa_id, $periodo_fiscal);
                mysqli_stmt_execute($stmt_ab);
                $abono_actualizado = mysqli_stmt_affected_rows($stmt_ab);
                mysqli_stmt_close($stmt_ab);
                if ($abono_actualizado !== 1) {
                    throw new RuntimeException("ya fue facturado para el período {$periodo_fiscal} por otra emisión.");
                }

                $stmt_nov = mysqli_prepare($conexion, "UPDATE estudio__honorarios_novedades
                                                       SET facturado = 1, venta_factura_id = ?
                                                       WHERE novedad_id = ? AND empresa_id = ? AND facturado = 0");
                foreach ($item['novedades_detalle'] as $nov) {
                    $novedad_id = (int) $nov['novedad_id'];
                    mysqli_stmt_bind_param($stmt_nov, "iii", $venta_factura_id, $novedad_id, $empresa_id);
                    mysqli_stmt_execute($stmt_nov);
                    if (mysqli_stmt_affected_rows($stmt_nov) !== 1) {
                        throw new RuntimeException("la novedad {$novedad_id} ya fue facturada por otra emisión.");
                    }
                }
                mysqli_stmt_close($stmt_nov);
                mysqli_commit($conexion);
            } catch (Throwable $e) {
                mysqli_rollback($conexion);
                // El borrador recién creado no tiene que quedar: se descarta por la transición de Eliminar
                vfEjecutarTransicionEstado($conexion, $venta_factura_id, 'eliminar', $empresa_id, $pagina_ventas);
                $venta_factura_id = 0;
                throw $e;
            }

            // Confirmar (3 → 4) y, sin ARCA, aprobar (4 → 5). Con ARCA queda Pendiente de ARCA.
            $conf = vfEjecutarTransicionEstado($conexion, $venta_factura_id, 'confirma', $empresa_id, $pagina_ventas);
            if (empty($conf['success'])) {
                throw new RuntimeException('quedó en Borrador en Facturación: ' . ($conf['error'] ?? 'no se pudo confirmar.'));
            }
            if ($con_arca) {
                $pendientes_arca++;
            } else {
                $apr = vfEjecutarTransicionEstado($conexion, $venta_factura_id, 'aprobada', $empresa_id, $pagina_ventas);
                if (empty($apr['success'])) {
                    throw new RuntimeException('quedó confirmada sin aprobar en Facturación: ' . ($apr['error'] ?? 'no se pudo aprobar.'));
                }
            }

            $facturados++;
            $total_facturado_monto += floatval($item['importe_total']);
        } catch (Throwable $e) {
            error_log("=== honorarios_facturacion_emitir_lote abono {$item['abono_id']}: " . $e->getMessage());
            $errores[] = "{$item['cliente_nombre']}: " . $e->getMessage();
        }
    }

    $mensaje = "{$facturados} facturas emitidas por " . fmt_moneda($total_facturado_monto) . '.';
    if ($pendientes_arca > 0) {
        $mensaje .= " {$pendientes_arca} quedaron Pendientes de ARCA: aprobalas desde Ventas > Facturación para pedir el CAE.";
    }
    if ($omitidos > 0) {
        $mensaje .= " {$omitidos} ya estaban facturadas.";
    }
    if ($errores) {
        $mensaje .= ' Con problemas: ' . implode(' | ', $errores);
    }

    return [
        'success' => $facturados > 0 || !$errores,
        'message' => $mensaje,
        'data' => [
            'facturados' => $facturados,
            'pendientes_arca' => $pendientes_arca,
            'omitidos' => $omitidos,
            'total_monto' => $total_facturado_monto,
            'errores' => $errores
        ],
        'error' => $errores ? implode(' | ', $errores) : null
    ];
}
