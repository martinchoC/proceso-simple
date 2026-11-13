<?php
/**
 * Modelo: Recibos y Cobranzas con Retenciones Sufridas
 * Módulo 4: Estudio Contable (minigestion_recibos_model.php)
 *
 * El recibo es una cobranza común del módulo de gestión (gestion__cobranzas, tabla 275): se guarda con
 * guardarCobranzaBorrador() y se confirma con confirmarCobranza() en la misma transacción, igual que los recibos
 * que genera Conciliación Bancaria (core/bancos/bancos_cobranzas_service.php). Así lleva punto de venta y tipo de
 * la empresa, numeración, espejo en gestion__comprobantes, movimiento de tesorería, imputación a las facturas,
 * asiento, control de períodos contables y fiscales, y las retenciones sufridas como medios de cobro de tipo
 * RETENCION (gestion__cobranzas_medios: certificado, provincia en IIBB, Control Fiscal y Posición de IVA).
 * Se consulta y se anula desde Tesorería > Cobranzas.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../gestion/cobranzas_model.php';

class MiniGestionRecibosModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    private function filas($sql, $tipos, ...$params) {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) throw new RuntimeException('Error consultando: ' . mysqli_error($this->conn));
        if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    /**
     * Página de Cobranzas (cobranzas.php) del módulo de gestión activo de la empresa: 105 en Gestión (2), 605 en
     * Gestión Normal (5). De ella salen los puntos de venta y los tipos de recibo. 0 si la empresa no tiene gestión.
     */
    public function paginaCobranzas($empresa_id) {
        $r = $this->filas("SELECT p.pagina_id
                           FROM conf__paginas p
                           INNER JOIN conf__empresas_modulos em ON em.modulo_id = p.modulo_id AND em.empresa_id = ? AND em.tabla_estado_registro_id = 1
                           WHERE p.url = 'cobranzas.php' AND p.modulo_id IN (2, 5) AND p.tabla_estado_registro_id = 1
                           ORDER BY p.modulo_id LIMIT 1", 'i', intval($empresa_id));
        return intval($r[0]['pagina_id'] ?? 0);
    }

    private function exigirPagina($empresa_id) {
        $pagina = $this->paginaCobranzas($empresa_id);
        if ($pagina <= 0) {
            throw new RuntimeException('La empresa no tiene activo el módulo de gestión con la página de Cobranzas.');
        }
        return $pagina;
    }

    public function listarRecibos($empresa_id) {
        $empresa_id = intval($empresa_id);
        $rows = $this->filas("SELECT c.cobranza_id, c.f_emision, c.comprobante_nro, c.importe_total,
                                     " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS punto_venta,
                                     ct.comprobante_tipo, e.entidad_nombre AS cliente_nombre, e.cuit AS cliente_cuit,
                                     c.tabla_estado_registro_id, er.estado_registro, er.codigo_estandar,
                                     COALESCE((SELECT SUM(cm.importe_comprobante) FROM gestion__cobranzas_medios cm
                                               JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id
                                               WHERE cm.cobranza_id = c.cobranza_id AND mp.medio_pago_tipo = 'RETENCION'), 0) AS total_retenciones
                              FROM gestion__cobranzas c
                              LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
                              LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                              LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
                              WHERE c.empresa_id = ? AND c.tabla_estado_registro_id <> 6
                              ORDER BY c.f_emision DESC, c.cobranza_id DESC", 'i', $empresa_id);
        foreach ($rows as &$r) {
            $r['numero'] = intval($r['comprobante_nro']) > 0
                ? sprintf('%04d-%08d', intval($r['punto_venta']), intval($r['comprobante_nro']))
                : 'Borrador';
        }
        unset($r);
        return $rows;
    }

    /** Combos del formulario: clientes, puntos de venta, medios de fondos, cuentas de tesorería, retenciones y provincias. */
    public function maestros($empresa_id) {
        $empresa_id = intval($empresa_id);
        $pagina = $this->exigirPagina($empresa_id);
        $clientes = $this->filas("SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades
                                  WHERE empresa_id = ? AND " . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'CLI') . " AND tabla_estado_registro_id = 1
                                  ORDER BY entidad_nombre ASC", 'i', $empresa_id);
        $medios = $this->filas("SELECT mp.medio_pago_id, mp.codigo, mp.medio_pago, mp.medio_pago_tipo, mp.tesoreria_cuenta_tipo,
                                       mp.tesoreria_cuenta_id, mp.impuesto_tipo_id, it.impuesto_tipo
                                FROM gestion__medios_pago mp
                                LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = mp.impuesto_tipo_id
                                WHERE mp.empresa_id = ? AND mp.tabla_estado_registro_id = 1 AND mp.aplica_cobro = 1
                                  AND mp.medio_pago_tipo NOT IN ('CHEQUE_PROPIO', 'CHEQUE_TERCERO')
                                ORDER BY mp.orden, mp.medio_pago", 'i', $empresa_id);
        $fondos = [];
        $retenciones = [];
        foreach ($medios as $m) {
            if ($m['medio_pago_tipo'] === 'RETENCION') {
                if (intval($m['impuesto_tipo_id']) <= 0) continue;
                $m['es_iibb'] = cobImpuestoEsIibb($m['impuesto_tipo']) ? 1 : 0;
                $retenciones[] = $m;
            } else {
                $fondos[] = $m;
            }
        }
        return [
            'clientes' => $clientes,
            'puntos_venta' => obtenerPuntosVentaCobranza($this->conn, $empresa_id, $pagina),
            'medios' => $fondos,
            'cuentas' => obtenerCuentasTesoreriaEmpresa($this->conn, $empresa_id),
            'retenciones' => $retenciones,
            'jurisdicciones' => obtenerJurisdicciones($this->conn),
        ];
    }

    public function tiposRecibo($empresa_id, $punto_venta_id) {
        return obtenerTiposComprobanteCobranza($this->conn, intval($empresa_id), intval($punto_venta_id), $this->exigirPagina($empresa_id));
    }

    /**
     * Emite y confirma el recibo. $data: entidad_id, f_emision, punto_venta_id, comprobante_tipo_id, importe_total,
     * medio_pago_id + tesoreria_cuenta_id (fondos = total - retenciones), retenciones [{medio_pago_id, importe,
     * certificado, jurisdiccion_id}], imputar (1 = a las facturas pendientes más viejas; 0 = a cuenta), observaciones.
     */
    public function emitirRecibo($empresa_id, $data, $usuario_id) {
        $empresa_id = intval($empresa_id);
        $usuario_id = intval($usuario_id);
        $pagina = $this->exigirPagina($empresa_id);

        $entidad_id = intval($data['entidad_id'] ?? 0);
        $f_emision = trim((string)($data['f_emision'] ?? '')) ?: date('Y-m-d');
        $pv = intval($data['punto_venta_id'] ?? 0);
        $tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
        $importe_total = round(floatval($data['importe_total'] ?? 0), 2);
        $medio_id = intval($data['medio_pago_id'] ?? 0);
        $cuenta_id = intval($data['tesoreria_cuenta_id'] ?? 0);
        $imputar = !empty($data['imputar']);
        $observaciones = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 500);

        if ($entidad_id <= 0 || $importe_total <= 0) {
            throw new RuntimeException('Elegí el cliente y cargá el importe total del recibo.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_emision)) {
            throw new RuntimeException('La fecha del recibo no es válida.');
        }
        $maestros = $this->maestros($empresa_id);
        if (!in_array($entidad_id, array_map('intval', array_column($maestros['clientes'], 'entidad_id')), true)) {
            throw new RuntimeException('El cliente no es un cliente activo de la empresa.');
        }
        $pv_fila = null;
        foreach ($maestros['puntos_venta'] as $p) {
            if (intval($p['punto_venta_id']) === $pv) $pv_fila = $p;
        }
        if (!$pv_fila) throw new RuntimeException('Elegí el punto de venta del recibo.');
        $tipo = null;
        foreach (obtenerTiposComprobanteCobranza($this->conn, $empresa_id, $pv, $pagina) as $t) {
            if (intval($t['comprobante_tipo_id']) === $tipo_id) $tipo = $t;
        }
        if (!$tipo) throw new RuntimeException('Elegí el tipo de recibo (habilitado en ese punto de venta).');

        // Retenciones sufridas: un medio RETENCION por impuesto (cobValidarRetenciones valida certificado y provincia)
        $por_id = [];
        foreach ($maestros['retenciones'] as $r) $por_id[intval($r['medio_pago_id'])] = $r;
        $medios = [];
        $total_retenciones = 0.0;
        foreach ((array)($data['retenciones'] ?? []) as $r) {
            $imp = round(floatval($r['importe'] ?? 0), 2);
            if ($imp <= 0) continue;
            $mid = intval($r['medio_pago_id'] ?? 0);
            if (!isset($por_id[$mid])) throw new RuntimeException('Alguna retención usa un medio de cobro que no es de la empresa.');
            $medios[] = [
                'medio_pago_id' => $mid,
                'importe' => $imp,
                'importe_comprobante' => $imp,
                'impuesto_tipo_id' => intval($por_id[$mid]['impuesto_tipo_id']),
                'jurisdiccion_id' => intval($r['jurisdiccion_id'] ?? 0),
                'retencion_certificado_nro' => trim((string)($r['certificado'] ?? '')),
            ];
            $total_retenciones += $imp;
        }
        $total_retenciones = round($total_retenciones, 2);
        $fondos = round($importe_total - $total_retenciones, 2);
        if ($fondos < 0) {
            throw new RuntimeException('El total de retenciones sufridas no puede superar el total del recibo.');
        }

        // Fondos: medio de cobro y cuenta de tesorería (del tipo que pide el medio; en efectivo, la caja de la sucursal)
        if ($fondos > 0) {
            $medio = null;
            foreach ($maestros['medios'] as $m) {
                if (intval($m['medio_pago_id']) === $medio_id) $medio = $m;
            }
            if (!$medio) throw new RuntimeException('Elegí el medio de cobro de los fondos.');
            if ($cuenta_id <= 0) $cuenta_id = intval($medio['tesoreria_cuenta_id'] ?? 0);
            if ($cuenta_id > 0) {
                $cuenta = null;
                foreach ($maestros['cuentas'] as $c) {
                    if (intval($c['tesoreria_cuenta_id']) === $cuenta_id) $cuenta = $c;
                }
                if (!$cuenta) throw new RuntimeException('La cuenta de tesorería no es de la empresa.');
                if (!empty($medio['tesoreria_cuenta_tipo']) && $cuenta['cuenta_tipo'] !== $medio['tesoreria_cuenta_tipo']) {
                    throw new RuntimeException("El medio \"{$medio['medio_pago']}\" va a una cuenta de tipo {$medio['tesoreria_cuenta_tipo']}.");
                }
            } elseif ($medio['medio_pago_tipo'] !== 'EFECTIVO' && $medio['tesoreria_cuenta_tipo'] !== 'CAJA') {
                throw new RuntimeException('Elegí la cuenta de tesorería donde entran los fondos.');
            }
            array_unshift($medios, [
                'medio_pago_id' => $medio_id,
                'tesoreria_cuenta_id' => $cuenta_id ?: null,
                'importe' => $fondos,
                'importe_comprobante' => $fondos,
                'f_valor' => $f_emision,
            ]);
        }

        mysqli_begin_transaction($this->conn);
        try {
            // Imputación: las facturas pendientes más viejas del cliente (emitidas hasta la fecha), hasta el importe
            $imputaciones = [];
            if ($imputar) {
                $resto = $importe_total;
                foreach (tesoreriaObtenerComprobantesCancelables($this->conn, $empresa_id, (string)$tipo['codigo'], $entidad_id) as $p) {
                    if ($resto < 0.005) break;
                    if (intval($p['signo'] ?? 1) < 0 || substr((string)$p['f_emision'], 0, 10) > $f_emision) continue;
                    $monto = round(min($resto, floatval($p['importe_pendiente'])), 2);
                    if ($monto <= 0) continue;
                    $imputaciones[] = ['comprobante_destino_id' => intval($p['comprobante_id']), 'importe_imputado' => $monto];
                    $resto = round($resto - $monto, 2);
                }
            }

            $cobranza_id = $this->llamar(fn() => guardarCobranzaBorrador($this->conn, [
                'empresa_id' => $empresa_id,
                'sucursal_id' => intval($pv_fila['sucursal_id']),
                'entidad_id' => $entidad_id,
                'f_emision' => $f_emision,
                'comprobante_pv' => $pv,
                'comprobante_tipo_id' => $tipo_id,
                'observaciones' => $observaciones,
                'usuario_id' => $usuario_id,
                'medios' => $medios,
                'imputaciones' => $imputaciones,
            ]));
            $conf = $this->llamar(fn() => confirmarCobranza($this->conn, $cobranza_id, $empresa_id, $usuario_id, true));
            mysqli_commit($this->conn);
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }

        $imputado = round(array_sum(array_column($imputaciones, 'importe_imputado')), 2);
        error_log("=== emitirRecibo estudio empresa $empresa_id: cobranza $cobranza_id confirmada, imputado $imputado ===");
        return [
            'cobranza_id' => intval($cobranza_id),
            'numero' => emp_numero_comprobante($this->conn, $empresa_id, $pv, intval($conf['comprobante_nro'])),
            'importe_total' => $importe_total,
            'total_retenciones' => $total_retenciones,
            'importe_imputado' => $imputado,
            'importe_a_cuenta' => round($importe_total - $imputado, 2),
        ];
    }

    /** Las funciones de cobranzas lanzan Exception con mensajes para el usuario: se pasan como RuntimeException. */
    private function llamar(callable $fn) {
        try {
            return $fn();
        } catch (mysqli_sql_exception $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
