<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_cobros_core_model.php
 * Motor de conciliación de rendiciones, emisión de ND de intereses resarcitorios,
 * generación de cobranzas e imputación de deudas en cuenta corriente (§7.3.2).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/sindi_base_model.php';
require_once __DIR__ . '/sindi_comprobantes_core.php';
require_once __DIR__ . '/sindi_pagos_drivers.php';

/**
 * Registra un nuevo archivo o lote de rendición en estado 8 (Recibido).
 */
function sindi_registrar_rendicion(
    mysqli $conexion,
    int $empresa_id,
    int $usuario_id,
    int $medio_pago_id,
    string $f_rendicion,
    string $archivoNombre,
    string $contenidoTexto
): array {
    $sqlMP = "SELECT * FROM sindi__medios_pago WHERE empresa_id = ? AND medio_pago_id = ? LIMIT 1";
    $stmtM = mysqli_prepare($conexion, $sqlMP);
    if (!$stmtM) return ['success' => false, 'message' => 'Medio de pago inválido.'];
    mysqli_stmt_bind_param($stmtM, "ii", $empresa_id, $medio_pago_id);
    mysqli_stmt_execute($stmtM);
    $medioPago = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtM));
    mysqli_stmt_close($stmtM);

    if (!$medioPago) {
        return ['success' => false, 'message' => 'Medio de pago no encontrado para la empresa.'];
    }

    $driver = SindiPagosFactory::obtenerDriver($medioPago['driver']);
    $resParse = $driver->parsearArchivo($contenidoTexto, $medioPago);

    if (!($resParse['success'] ?? false) || empty($resParse['lineas'])) {
        return ['success' => false, 'message' => 'No se pudieron extraer líneas válidas del archivo de rendición.'];
    }

    $totalReg = count($resParse['lineas']);
    $totalImp = 0.00;
    foreach ($resParse['lineas'] as $lin) {
        $totalImp = round($totalImp + (float)($lin['importe_rendido'] ?? 0.0), 2);
    }

    $hash = hash('sha256', $contenidoTexto);

    // El mismo archivo del mismo medio no se registra dos veces
    $stmtH = mysqli_prepare($conexion, "SELECT rendicion_id FROM sindi__rendiciones
                                        WHERE empresa_id = ? AND medio_pago_id = ? AND archivo_hash = ? AND tabla_estado_registro_id <> 38 LIMIT 1");
    mysqli_stmt_bind_param($stmtH, "iis", $empresa_id, $medio_pago_id, $hash);
    mysqli_stmt_execute($stmtH);
    $previa = mysqli_fetch_row(mysqli_stmt_get_result($stmtH));
    mysqli_stmt_close($stmtH);
    if ($previa) {
        return ['success' => false, 'message' => "Este archivo ya fue registrado (rendición {$previa[0]})."];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sqlR = "INSERT INTO sindi__rendiciones (
            empresa_id, medio_pago_id, f_rendicion, archivo_nombre, archivo_hash,
            total_registros, total_importe, tabla_estado_registro_id, observaciones, usuario_id, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 8, 'Rendición ingresada para conciliación', ?, NOW())";
        $stmtR = mysqli_prepare($conexion, $sqlR);
        mysqli_stmt_bind_param($stmtR, "iisssidi", $empresa_id, $medio_pago_id, $f_rendicion, $archivoNombre, $hash, $totalReg, $totalImp, $usuario_id);
        mysqli_stmt_execute($stmtR);
        $rendicion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmtR);

        // Insertar líneas normalizadas
        $sqlLin = "INSERT INTO sindi__rendiciones_lineas (
            rendicion_id, empresa_id, numero_linea, identificador_externo, f_pago, f_acreditacion,
            importe_rendido, sucursal_banco, canal_origen, resultado_conciliacion, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'OK', NOW())";
        $stmtL = mysqli_prepare($conexion, $sqlLin);

        foreach ($resParse['lineas'] as $l) {
            $nro = (int)($l['numero_linea'] ?? 1);
            $idExt = (string)($l['identificador_externo'] ?? '');
            $fP = (string)($l['f_pago'] ?? $f_rendicion);
            $fA = (string)($l['f_acreditacion'] ?? $fP);
            $imp = (float)($l['importe_rendido'] ?? 0.0);
            $suc = (string)($l['sucursal_banco'] ?? 'BNA');
            $can = (string)($l['canal_origen'] ?? $medioPago['driver']);

            mysqli_stmt_bind_param($stmtL, "iiisssdss", $rendicion_id, $empresa_id, $nro, $idExt, $fP, $fA, $imp, $suc, $can);
            mysqli_stmt_execute($stmtL);
        }
        mysqli_stmt_close($stmtL);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Rendición registrada exitosamente con {$totalReg} líneas.",
            'rendicion_id' => $rendicion_id,
            'total_registros' => $totalReg,
            'total_importe' => $totalImp
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_COBROS] Error al registrar rendición: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar rendición: ' . $e->getMessage()];
    }
}

/**
 * Cuenta de tesorería y medio de pago del ERP donde se acredita un medio de cobro del sindicato
 * (sindi__medios_pago.tesoreria_cuenta_codigo / medio_pago_codigo, §7.3.1). Error explícito si falta.
 */
function sindi_tesoreria_de_medio(mysqli $conexion, int $empresa_id, array $medio): array
{
    $codigo = trim((string)($medio['tesoreria_cuenta_codigo'] ?? ''));
    if ($codigo === '') {
        throw new RuntimeException("El medio de cobro {$medio['nombre']} no tiene cuenta de tesorería asignada (Medios de Cobro y Canales).");
    }
    $stmt = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_id, cont_cuenta_id, sucursal_id FROM gestion__tesoreria_cuentas
                                       WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $cta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$cta || (int)$cta['cont_cuenta_id'] <= 0) {
        throw new RuntimeException("La cuenta de tesorería '{$codigo}' del medio {$medio['nombre']} no existe en la empresa o no tiene cuenta contable.");
    }

    $medio_pago_erp_id = null;
    $codigoErp = trim((string)($medio['medio_pago_codigo'] ?? ''));
    if ($codigoErp !== '') {
        $stmt = mysqli_prepare($conexion, "SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigoErp);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $medio_pago_erp_id = $fila ? (int)$fila[0] : null;
    }

    return [
        'tesoreria_cuenta_id' => (int)$cta['tesoreria_cuenta_id'],
        'cont_cuenta_id' => (int)$cta['cont_cuenta_id'],
        'sucursal_id' => (int)$cta['sucursal_id'],
        'medio_pago_id' => $medio_pago_erp_id,
    ];
}

/** Marca el resultado de una línea de rendición. */
function sindi_marcar_linea_rendicion(mysqli $conexion, int $linea_id, string $resultado, ?int $volante_id, ?int $cobro_id, ?int $ndi_id, string $obs = ''): void
{
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__rendiciones_lineas
        SET resultado_conciliacion = ?, volante_id = ?, comprobante_cobro_id = ?, nota_debito_interes_id = ?,
            observaciones = NULLIF(?, '')
        WHERE rendicion_linea_id = ?");
    mysqli_stmt_bind_param($stmt, "siiisi", $resultado, $volante_id, $cobro_id, $ndi_id, $obs, $linea_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Procesa una rendición registrada (§7.3.2). Por cada línea, en orden:
 *  1. Identifica el volante (código de barras, número o id) y bloquea la fila.
 *  2. Controla duplicados (misma línea ya cobrada), estado del volante y el vencimiento en que se pagó:
 *     fecha de pago <= f_pago_1 -> importe 1; <= f_pago_2 -> importe 2; después, DIFERENCIA (fuera de término).
 *     Si el importe no coincide con el del vencimiento, DIFERENCIA para revisión manual.
 *  3. Emite la ND de intereses por el interés FIJADO en el volante para ese vencimiento (no se recalcula).
 *  4. Emite el recibo (REC, signo -1), registra el ingreso en la cuenta de tesorería del medio y el asiento.
 *  5. Imputa el recibo contra las deudas del volante (por vencimiento) y la ND con tesoreriaRegistrarImputacion,
 *     que mantiene saldos y estados 39/40. Lo que sobre queda como saldo a favor en el recibo.
 * Las líneas con resultado distinto de OK no mueven dinero ni deuda: quedan para revisión.
 */
function sindi_procesar_cobranza_rendicion(mysqli $conexion, int $empresa_id, int $usuario_id, int $rendicion_id): array
{
    $sqlR = "SELECT r.*, mp.driver, mp.nombre, mp.tesoreria_cuenta_codigo, mp.medio_pago_codigo
             FROM sindi__rendiciones r
             INNER JOIN sindi__medios_pago mp ON mp.medio_pago_id = r.medio_pago_id AND mp.empresa_id = r.empresa_id
             WHERE r.rendicion_id = ? AND r.empresa_id = ? LIMIT 1";
    $stmtR = mysqli_prepare($conexion, $sqlR);
    mysqli_stmt_bind_param($stmtR, "ii", $rendicion_id, $empresa_id);
    mysqli_stmt_execute($stmtR);
    $rendicion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtR));
    mysqli_stmt_close($stmtR);

    if (!$rendicion) {
        return ['success' => false, 'message' => 'Rendición no encontrada.'];
    }
    if ((int)$rendicion['tabla_estado_registro_id'] === 35) {
        return ['success' => false, 'message' => 'La rendición ya fue procesada anteriormente.'];
    }

    // Configuración obligatoria: cuentas, tipos y tesorería del medio (errores explícitos)
    try {
        $cuenta_deudores_id = sindi_cuenta_obligatoria($conexion, $empresa_id, 'SINDI_DEUDORES_APORTES');
        $tes = sindi_tesoreria_de_medio($conexion, $empresa_id, $rendicion);
        $tipo_rec_id = emp_comprobante_tipo_id($conexion, $empresa_id, 'REC');
        $tipo_ndi_id = emp_comprobante_tipo_id($conexion, $empresa_id, 'NDI');
        if ($tipo_rec_id <= 0 || $tipo_ndi_id <= 0) {
            throw new RuntimeException('Faltan los tipos de comprobante REC (cobranza) y/o NDI (intereses) de la empresa.');
        }
    } catch (RuntimeException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    $cuenta_intereses_id = emp_cuenta_concepto_id($conexion, $empresa_id, 'SINDI_INTERESES_GANADOS');

    $stmtL = mysqli_prepare($conexion, "SELECT * FROM sindi__rendiciones_lineas
                                        WHERE rendicion_id = ? AND empresa_id = ? AND comprobante_cobro_id IS NULL
                                        ORDER BY numero_linea ASC");
    mysqli_stmt_bind_param($stmtL, "ii", $rendicion_id, $empresa_id);
    mysqli_stmt_execute($stmtL);
    $lineas = mysqli_fetch_all(mysqli_stmt_get_result($stmtL), MYSQLI_ASSOC);
    mysqli_stmt_close($stmtL);

    $cont = ['OK' => 0, 'DIFERENCIA' => 0, 'SIN_VOLANTE' => 0, 'DUPLICADA' => 0];

    mysqli_begin_transaction($conexion);
    try {
        // La rendición bloqueada: dos procesamientos simultáneos no cobran dos veces
        $stmtLk = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id FROM sindi__rendiciones WHERE rendicion_id = ? AND empresa_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmtLk, "ii", $rendicion_id, $empresa_id);
        mysqli_stmt_execute($stmtLk);
        $estadoRend = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmtLk))[0] ?? 0);
        mysqli_stmt_close($stmtLk);
        if ($estadoRend === 35) {
            throw new RuntimeException('La rendición ya fue procesada.');
        }

        foreach ($lineas as $lin) {
            $lineaId = (int)$lin['rendicion_linea_id'];
            $idExt = trim((string)$lin['identificador_externo']);
            $fPago = (string)$lin['f_pago'];
            $fAcred = (string)($lin['f_acreditacion'] ?: $fPago);
            $impRendido = round((float)$lin['importe_rendido'], 2);

            // Duplicado: la misma operación ya cobrada en otra línea del mismo medio
            $stmtDup = mysqli_prepare($conexion, "SELECT l.rendicion_linea_id FROM sindi__rendiciones_lineas l
                INNER JOIN sindi__rendiciones r ON r.rendicion_id = l.rendicion_id
                WHERE r.empresa_id = ? AND r.medio_pago_id = ? AND l.identificador_externo = ?
                  AND l.f_pago = ? AND l.importe_rendido = ? AND l.comprobante_cobro_id IS NOT NULL
                  AND l.rendicion_linea_id <> ? LIMIT 1");
            $medioId = (int)$rendicion['medio_pago_id'];
            mysqli_stmt_bind_param($stmtDup, "iissdi", $empresa_id, $medioId, $idExt, $fPago, $impRendido, $lineaId);
            mysqli_stmt_execute($stmtDup);
            $dup = mysqli_fetch_row(mysqli_stmt_get_result($stmtDup));
            mysqli_stmt_close($stmtDup);
            if ($dup) {
                $cont['DUPLICADA']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'DUPLICADA', null, null, null, "Ya cobrada en la línea {$dup[0]}");
                continue;
            }

            $posibleId = (int)preg_replace('/\D/', '', $idExt);
            $stmtV = mysqli_prepare($conexion, "SELECT v.*, emp.entidad_id
                FROM sindi__volantes v
                INNER JOIN sindi__empleadores emp ON emp.empleador_id = v.empleador_id AND emp.empresa_id = v.empresa_id
                WHERE v.empresa_id = ? AND (v.codigo_barras = ? OR v.numero = ? OR v.volante_id = ?)
                LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($stmtV, "issi", $empresa_id, $idExt, $idExt, $posibleId);
            mysqli_stmt_execute($stmtV);
            $volante = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtV));
            mysqli_stmt_close($stmtV);

            if (!$volante) {
                $cont['SIN_VOLANTE']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'SIN_VOLANTE', null, null, null);
                continue;
            }
            $volante_id = (int)$volante['volante_id'];
            $estadoVol = (int)$volante['tabla_estado_registro_id'];

            if ($estadoVol === 40) {
                $cont['DUPLICADA']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'DUPLICADA', $volante_id, null, null, 'El volante ya estaba cancelado');
                continue;
            }
            if ($estadoVol !== 31) {
                $cont['DIFERENCIA']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'DIFERENCIA', $volante_id, null, null, "Pago de un volante en estado {$estadoVol} (anulado, vencido o borrador): revisar");
                continue;
            }

            // Vencimiento en que se pagó
            if ($fPago <= (string)$volante['f_pago_1']) {
                $impEsperado = round((float)$volante['importe_1'], 2);
                $intAplicar = round((float)$volante['importe_interes_1'], 2);
            } elseif ($fPago <= (string)$volante['f_pago_2']) {
                $impEsperado = round((float)$volante['importe_2'], 2);
                $intAplicar = round((float)$volante['importe_interes_2'], 2);
            } else {
                $cont['DIFERENCIA']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'DIFERENCIA', $volante_id, null, null, 'Pagado después del segundo vencimiento');
                continue;
            }
            if (abs($impRendido - $impEsperado) > 0.009) {
                $cont['DIFERENCIA']++;
                sindi_marcar_linea_rendicion($conexion, $lineaId, 'DIFERENCIA', $volante_id, null, null,
                    "Importe rendido {$impRendido} distinto del esperado {$impEsperado}");
                continue;
            }

            $entidad_id = (int)$volante['entidad_id'];

            // ND de intereses por el interés fijado en el volante para ese vencimiento
            $ndi_id = null;
            if ($intAplicar > 0) {
                if ($cuenta_intereses_id <= 0) {
                    throw new RuntimeException('Falta la cuenta contable del concepto SINDI_INTERESES_GANADOS en Tesorería > Conceptos Contables.');
                }
                $ndi = sindi_emitir_comprobante($conexion, [
                    'empresa_id' => $empresa_id,
                    'tabla_origen_id' => SINDI_TABLA_VOLANTES,
                    'registro_origen_id' => $volante_id,
                    'comprobante_tipo_id' => $tipo_ndi_id,
                    'entidad_id' => $entidad_id,
                    'importe' => $intAplicar,
                    'signo' => 1,
                    'f_emision' => $fPago,
                    'f_vto' => $fPago,
                    'usuario_id' => $usuario_id,
                    'observaciones' => "Intereses resarcitorios volante {$volante['numero']}",
                ]);
                $ndi_id = $ndi['comprobante_id'];
                if ($ndi['nuevo']) {
                    tesoreriaGenerarAsiento($conexion, [
                        'empresa_id' => $empresa_id,
                        'comprobante_id' => $ndi_id,
                        'entidad_id' => $entidad_id,
                        'f_asiento' => $fPago,
                        'descripcion' => "Intereses resarcitorios volante {$volante['numero']}",
                        'usuario_id' => $usuario_id,
                        'cont_tipo_asiento_codigo' => 'AUT'
                    ], [
                        ['cuenta_id' => $cuenta_deudores_id, 'debe' => $intAplicar, 'haber' => 0.00, 'descripcion' => 'Intereses a cobrar'],
                        ['cuenta_id' => $cuenta_intereses_id, 'debe' => 0.00, 'haber' => $intAplicar, 'descripcion' => 'Intereses ganados resarcitorios'],
                    ]);
                }
            }

            // Recibo, ingreso en tesorería y asiento
            $rec = sindi_emitir_comprobante($conexion, [
                'empresa_id' => $empresa_id,
                'tabla_origen_id' => SINDI_TABLA_RENDICIONES_LINEAS,
                'registro_origen_id' => $lineaId,
                'comprobante_tipo_id' => $tipo_rec_id,
                'entidad_id' => $entidad_id,
                'importe' => $impRendido,
                'signo' => -1,
                'f_emision' => $fPago,
                'usuario_id' => $usuario_id,
                'observaciones' => "Cobro volante {$volante['numero']} por {$rendicion['nombre']}",
            ]);
            $recibo_id = $rec['comprobante_id'];

            tesoreriaRegistrarMovimiento($conexion, [
                'empresa_id' => $empresa_id,
                'sucursal_id' => $tes['sucursal_id'],
                'tesoreria_cuenta_id' => $tes['tesoreria_cuenta_id'],
                'comprobante_id' => $recibo_id,
                'tipo_movimiento' => 'INGRESO',
                'importe' => $impRendido,
                'f_movimiento' => $fPago,
                'f_valor' => $fAcred,
                'medio_pago_id' => $tes['medio_pago_id'],
                'concepto' => "Cobro volante {$volante['numero']} ({$rendicion['nombre']})",
                'tabla_origen_id' => SINDI_TABLA_RENDICIONES_LINEAS,
                'registro_origen_id' => $lineaId,
                'usuario_id' => $usuario_id,
            ]);

            tesoreriaGenerarAsiento($conexion, [
                'empresa_id' => $empresa_id,
                'comprobante_id' => $recibo_id,
                'entidad_id' => $entidad_id,
                'f_asiento' => $fPago,
                'descripcion' => "Cobro volante {$volante['numero']} ({$rendicion['nombre']})",
                'usuario_id' => $usuario_id,
                'cont_tipo_asiento_codigo' => 'AUT'
            ], [
                ['cuenta_id' => $tes['cont_cuenta_id'], 'debe' => $impRendido, 'haber' => 0.00, 'descripcion' => 'Ingreso por rendición'],
                ['cuenta_id' => $cuenta_deudores_id, 'debe' => 0.00, 'haber' => $impRendido, 'descripcion' => 'Cancelación deuda sindical'],
            ]);

            // Imputar: deudas del volante por vencimiento, después la ND de intereses
            $stmtD = mysqli_prepare($conexion, "SELECT vd.comprobante_id
                FROM sindi__volantes_detalles vd
                INNER JOIN gestion__comprobantes c ON c.comprobante_id = vd.comprobante_id AND c.empresa_id = ?
                WHERE vd.volante_id = ?
                ORDER BY c.f_vto, c.comprobante_id");
            mysqli_stmt_bind_param($stmtD, "ii", $empresa_id, $volante_id);
            mysqli_stmt_execute($stmtD);
            $destinos = array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmtD), MYSQLI_ASSOC), 'comprobante_id'));
            mysqli_stmt_close($stmtD);
            if ($ndi_id !== null) {
                $destinos[] = $ndi_id;
            }

            $restante = $impRendido;
            $quedaDeuda = false;
            foreach ($destinos as $destino) {
                $saldo = sindi_saldo_comprobante($conexion, $destino, $empresa_id);
                $aplicar = round(min($saldo, $restante), 2);
                if ($aplicar > 0) {
                    tesoreriaRegistrarImputacion($conexion, [
                        'empresa_id' => $empresa_id,
                        'comp_origen_id' => $recibo_id,
                        'comp_destino_id' => $destino,
                        'importe_imputado' => $aplicar,
                        'f_imputacion' => $fPago,
                        'usuario_id' => $usuario_id,
                    ]);
                    $restante = round($restante - $aplicar, 2);
                }
                if ($saldo - $aplicar > 0.004) {
                    $quedaDeuda = true;
                }
            }

            $estadoNuevo = $quedaDeuda ? 39 : 40;
            $stmtVu = mysqli_prepare($conexion, "UPDATE sindi__volantes SET tabla_estado_registro_id = ?, usuario_actualizacion_id = ? WHERE volante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmtVu, "iiii", $estadoNuevo, $usuario_id, $volante_id, $empresa_id);
            mysqli_stmt_execute($stmtVu);
            mysqli_stmt_close($stmtVu);

            $obs = $restante > 0 ? "Saldo a favor del empleador: {$restante}" : '';
            sindi_marcar_linea_rendicion($conexion, $lineaId, 'OK', $volante_id, $recibo_id, $ndi_id, $obs);
            $cont['OK']++;
        }

        $stmtRp = mysqli_prepare($conexion, "UPDATE sindi__rendiciones SET tabla_estado_registro_id = 35 WHERE rendicion_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmtRp, "ii", $rendicion_id, $empresa_id);
        mysqli_stmt_execute($stmtRp);
        mysqli_stmt_close($stmtRp);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Rendición procesada: {$cont['OK']} cobros, {$cont['DIFERENCIA']} con diferencia, {$cont['SIN_VOLANTE']} sin volante, {$cont['DUPLICADA']} duplicados.",
            'procesadas_ok' => $cont['OK'],
            'diferencias' => $cont['DIFERENCIA'],
            'sin_volante' => $cont['SIN_VOLANTE'],
            'duplicadas' => $cont['DUPLICADA'],
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("[SINDI_COBROS] Error al procesar rendición {$rendicion_id}: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al procesar la rendición: ' . $e->getMessage()];
    }
}
