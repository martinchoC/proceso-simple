<?php
/**
 * Modelo de Datos: Facturación Recurrente y Emisión en Lote de Abonos
 * Módulo 4: Estudio Contable (honorarios_facturacion_model.php)
 * Tablas: estudio__clientes_abonos, estudio__honorarios_novedades, gestion__ventas_facturas, gestion__comprobantes
 */

declare(strict_types=1);

require_once __DIR__ . '/../gestion/ventas_facturas_model.php';

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
                   pv.codigo_fiscal AS punto_venta_codigo
            FROM estudio__clientes_abonos a
            INNER JOIN estudio__clientes c ON c.cliente_id = a.cliente_id
            INNER JOIN estudio__honorarios_conceptos hc ON hc.concepto_id = a.concepto_id
            INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = a.punto_venta_id
            WHERE a.empresa_id = ? AND a.tabla_estado_registro_id = 1
            ORDER BY c.razon_social ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $items = [];

    while ($ab = mysqli_fetch_assoc($res)) {
        $importe_base = floatval($ab['importe_base']);
        $adicional_comprobantes = 0.0;
        $adicional_legajos = 0.0;
        $novedades_monto = 0.0;
        $detalle_novedades = [];

        // 1. Novedades eventuales del período para este cliente
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

        $importe_neto = $importe_base + $adicional_comprobantes + $adicional_legajos + $novedades_monto;
        $importe_iva = round($importe_neto * 0.21, 2);
        $importe_total = $importe_neto + $importe_iva;
        $ya_facturado = ($ab['ultimo_periodo_facturado'] !== null && intval($ab['ultimo_periodo_facturado']) >= $periodo_fiscal);

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
            'importe_base' => $importe_base,
            'adicional_legajos' => $adicional_legajos,
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
 * Emisión masiva en lote con generación transaccional en ventas_facturas y syncComprobante
 */
function honorarios_facturacion_emitir_lote(mysqli $conexion, int $empresa_id, int $periodo_fiscal, array $abono_ids_seleccionados, int $usuario_id): array
{
    $items = honorarios_facturacion_consolidar($conexion, $empresa_id, $periodo_fiscal);
    $seleccionados = array_flip($abono_ids_seleccionados);

    mysqli_begin_transaction($conexion);

    $facturados = 0;
    $omitidos = 0;
    $total_facturado_monto = 0.0;

    try {
        foreach ($items as $item) {
            // Verificar si fue tildado
            if (!isset($seleccionados[$item['abono_id']])) {
                continue;
            }

            // Omitir si ya fue facturado
            if ($item['ya_facturado']) {
                $omitidos++;
                continue;
            }

            // 1. Obtener o crear entidad en gestion__entidades a partir del cliente del estudio
            $cuit_limpio = (int) preg_replace('/[^0-9]/', '', $item['cliente_cuit']);
            $sql_ent = "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1";
            $stmt_ent = mysqli_prepare($conexion, $sql_ent);
            mysqli_stmt_bind_param($stmt_ent, "ii", $empresa_id, $cuit_limpio);
            mysqli_stmt_execute($stmt_ent);
            $res_ent = mysqli_stmt_get_result($stmt_ent);
            $ent = mysqli_fetch_assoc($res_ent);
            mysqli_stmt_close($stmt_ent);

            if ($ent) {
                $entidad_id = (int) $ent['entidad_id'];
            } else {
                // Crear entidad cliente rápida
                $sql_ins_ent = "INSERT INTO gestion__entidades (empresa_id, entidad_nombre, cuit, es_cliente, tabla_estado_registro_id)
                                VALUES (?, ?, ?, 1, 1)";
                $stmt_ie = mysqli_prepare($conexion, $sql_ins_ent);
                mysqli_stmt_bind_param($stmt_ie, "isi", $empresa_id, $item['cliente_nombre'], $cuit_limpio);
                mysqli_stmt_execute($stmt_ie);
                $entidad_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt_ie);
            }

            // 2. Correlativo de factura
            $pv_id = intval($item['punto_venta_id'] ?? 1);
            $tipo_doc = ($item['condicion_fiscal_codigo'] === 'RI') ? 1 : 6; // Factura A (1) o Factura B (6)
            
            $sql_num = "SELECT COALESCE(MAX(comprobante_nro), 0) + 1 AS proximo 
                        FROM gestion__ventas_facturas 
                        WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?";
            $stmt_num = mysqli_prepare($conexion, $sql_num);
            mysqli_stmt_bind_param($stmt_num, "iii", $empresa_id, $pv_id, $tipo_doc);
            mysqli_stmt_execute($stmt_num);
            $res_num = mysqli_stmt_get_result($stmt_num);
            $row_num = mysqli_fetch_assoc($res_num);
            $proximo_nro = intval($row_num['proximo'] ?? 1);
            mysqli_stmt_close($stmt_num);

            // 3. Insertar factura en gestion__ventas_facturas
            $obs = "Honorarios Profesionales - Período " . $periodo_fiscal;
            $sql_vf = "INSERT INTO gestion__ventas_facturas (
                            empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id,
                            comprobante_nro, comprobante_id, entidad_id, condicion_pago_id,
                            f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                            importe_bruto, importe_neto, importe_iva, importe_total,
                            observaciones, tabla_estado_registro_id
                        ) VALUES (
                            ?, 1, ?, ?,
                            ?, 0, ?, ?,
                            CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 1, 1.000000,
                            ?, ?, ?, ?,
                            ?, 5
                        )";
            $stmt_vf = mysqli_prepare($conexion, $sql_vf);
            mysqli_stmt_bind_param(
                $stmt_vf,
                "iiiiiiidddds",
                $empresa_id,
                $tipo_doc,
                $pv_id,
                $proximo_nro,
                $entidad_id,
                $item['condicion_venta_id'],
                $item['importe_neto'],
                $item['importe_neto'],
                $item['importe_iva'],
                $item['importe_total'],
                $obs
            );
            mysqli_stmt_execute($stmt_vf);
            $venta_factura_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_vf);

            // 4. Invocar syncComprobante() universal para gestion__comprobantes (tabla_origen_id = 80)
            $comprobante_data = [
                'empresa_id' => $empresa_id,
                'sucursal_id' => 1,
                'comprobante_pv' => $pv_id,
                'comprobante_tipo_id' => $tipo_doc,
                'comprobante_nro' => $proximo_nro,
                'entidad_id' => $entidad_id,
                'f_emision' => date('Y-m-d'),
                'f_contabilidad' => date('Y-m-d'),
                'f_vto' => date('Y-m-d', strtotime('+15 days')),
                'moneda_id' => 1,
                'tipo_cambio' => 1.0,
                'registro_origen_id' => $venta_factura_id,
                'tabla_estado_registro_id' => 5, // Confirmado
                'usuario_id' => $usuario_id,
                'observaciones' => $obs,
                'importe_neto' => $item['importe_neto'],
                'descuento_general' => 0.0,
                'importe_no_gravado' => 0.0,
                'importe_exento' => 0.0,
                'importe_iva' => $item['importe_iva'],
                'importe_otros_impuestos' => 0.0,
                'importe_total' => $item['importe_total']
            ];

            $comprobante_id = syncComprobante($conexion, $comprobante_data, 80);

            // Actualizar comprobante_id en la factura
            if ($comprobante_id) {
                $sql_up_vf = "UPDATE gestion__ventas_facturas SET comprobante_id = ? WHERE venta_factura_id = ?";
                $stmt_up_vf = mysqli_prepare($conexion, $sql_up_vf);
                mysqli_stmt_bind_param($stmt_up_vf, "ii", $comprobante_id, $venta_factura_id);
                mysqli_stmt_execute($stmt_up_vf);
                mysqli_stmt_close($stmt_up_vf);
            }

            // 5. Actualizar abono y novedades asociadas
            $sql_up_ab = "UPDATE estudio__clientes_abonos SET ultimo_periodo_facturado = ? WHERE abono_id = ?";
            $stmt_ab = mysqli_prepare($conexion, $sql_up_ab);
            mysqli_stmt_bind_param($stmt_ab, "ii", $periodo_fiscal, $item['abono_id']);
            mysqli_stmt_execute($stmt_ab);
            mysqli_stmt_close($stmt_ab);

            // Marcar novedades facturadas
            $sql_up_nov = "UPDATE estudio__honorarios_novedades 
                           SET facturado = 1, venta_factura_id = ? 
                           WHERE empresa_id = ? AND cliente_id = ? AND periodo = ? AND facturado = 0";
            $stmt_nov = mysqli_prepare($conexion, $sql_up_nov);
            mysqli_stmt_bind_param($stmt_nov, "iiii", $venta_factura_id, $empresa_id, $item['cliente_id'], $periodo_fiscal);
            mysqli_stmt_execute($stmt_nov);
            mysqli_stmt_close($stmt_nov);

            $facturados++;
            $total_facturado_monto += $item['importe_total'];
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Facturación masiva completada: {$facturados} facturas emitidas y sincronizadas al comprobante universal. Importe total: $ " . number_format($total_facturado_monto, 2, ',', '.'),
            'data' => [
                'facturados' => $facturados,
                'omitidos' => $omitidos,
                'total_monto' => $total_facturado_monto
            ],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error durante la facturación en lote: ' . $e->getMessage(),
            'data' => null,
            'error' => $e->getMessage()
        ];
    }
}
