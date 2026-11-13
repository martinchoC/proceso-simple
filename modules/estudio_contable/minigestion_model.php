<?php
/**
 * Modelo: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion_model.php)
 * 
 * Gestiona comprobantes de compra/venta para clientes externos (carga manual)
 * y visualización/auditoría para clientes vinculados al ERP (solo lectura).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';

class MiniGestionModel {
    // Modo ERP: los mismos comprobantes que toma la Posición de IVA de gestión (iva_posiciones_model.php):
    // solo fiscales, ventas en estados confirmados / derivados (IVA_ESTADOS_VENTAS), compras confirmadas y
    // las notas de crédito (signo '-' del tipo) restando.
    const ERP_ESTADOS_VENTAS = '5, 12, 13, 15, 39, 40';
    const ERP_ESTADO_COMPRAS = 5;

    // Carga manual: alícuotas de IVA admitidas y tipos de percepción (= enum de estudio__minigestion_comprobantes_percepciones.tipo)
    const ALICUOTAS_VALIDAS = [0.0, 2.5, 5.0, 10.5, 21.0, 27.0];
    const PERCEPCION_TIPOS = ['IVA', 'IIBB', 'GANANCIAS', 'SUSS', 'MUNICIPAL', 'OTRA'];

    private $conn;

    /** CUIT/CUIL de 11 dígitos con dígito verificador módulo 11 correcto. */
    private static function cuitValido(string $cuit): bool {
        if (!preg_match('/^\d{11}$/', $cuit)) return false;
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) $suma += intval($cuit[$i]) * $pesos[$i];
        $dv = 11 - ($suma % 11);
        if ($dv === 11) $dv = 0; elseif ($dv === 10) $dv = 9;
        return $dv === intval($cuit[10]);
    }

    /** Líneas de detalle recibidas como JSON (campo oculto del formulario) o como array; descarta lo que no es una fila. */
    private function lineas($valor): array {
        if (is_string($valor)) {
            $valor = trim($valor) === '' ? [] : json_decode($valor, true);
        }
        if (!is_array($valor)) return [];
        return array_values(array_filter($valor, 'is_array'));
    }

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    /**
     * Seguridad: el estudio solo lee la empresa ERP del cliente si ésta le otorgó una vinculación vigente
     * (conf__empresas_vinculaciones: origen = empresa cliente que concede, destino = estudio) con permiso
     * sobre el área pedida. Lanza excepción (la captura minigestion_ajax.php) si no la tiene.
     */
    private function exigirPermisoErp(array $cliente, string $area, bool $escritura = false): void {
        $erp = intval($cliente['empresa_vinculada_id'] ?? 0);
        $estudio = intval($cliente['empresa_id'] ?? 0);
        if ($erp <= 0 || $estudio <= 0 || !vinculacionesVerificarPermiso($this->conn, $erp, $estudio, $area, $escritura)) {
            throw new Exception("La empresa del cliente no otorgó al estudio permiso vigente de {$area}" . ($escritura ? ' (escritura)' : '') . ". Registre o actualice la vinculación en Vinculaciones entre empresas.");
        }
    }

    /**
     * Obtiene los datos del cliente y determina si opera en modo ERP o MANUAL
     */
    public function getClienteInfo($empresa_id, $cliente_id) {
        $sql = "SELECT c.cliente_id, c.empresa_id, c.razon_social, c.cuit, c.terminacion_cuit,
                       c.condicion_iva_id, c.empresa_vinculada_id,
                       e.empresa as empresa_vinculada_nombre
                FROM estudio__clientes c
                LEFT JOIN conf__empresas e ON c.empresa_vinculada_id = e.empresa_id
                WHERE c.empresa_id = ? AND c.cliente_id = ? AND c.tabla_estado_registro_id = 1
                LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cliente_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($row) {
            $row['modo'] = (!empty($row['empresa_vinculada_id']) && intval($row['empresa_vinculada_id']) > 0) ? 'ERP' : 'MANUAL';
        }
        return $row;
    }

    /**
     * Lista todos los clientes activos del estudio
     */
    public function listarClientes($empresa_id) {
        $sql = "SELECT c.cliente_id, c.razon_social, c.cuit, c.empresa_vinculada_id,
                       CASE WHEN c.empresa_vinculada_id > 0 THEN 'ERP' ELSE 'MANUAL' END as modo,
                       e.empresa as empresa_vinculada_nombre
                FROM estudio__clientes c
                LEFT JOIN conf__empresas e ON c.empresa_vinculada_id = e.empresa_id
                WHERE c.empresa_id = ? AND c.tabla_estado_registro_id = 1
                ORDER BY c.razon_social ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }

    /**
     * Lista comprobantes de ventas (desde ERP o MANUAL)
     */
    public function listarVentas($empresa_id, $cliente_id, $periodo_fiscal = '') {
        $cliente = $this->getClienteInfo($empresa_id, $cliente_id);
        if (!$cliente) return [];

        if ($cliente['modo'] === 'ERP') {
            $this->exigirPermisoErp($cliente, 'ventas');
            // Lectura directa desde gestión de la empresa vinculada
            $empresa_erp = intval($cliente['empresa_vinculada_id']);
            $pv = emp_sql_pv_mostrar('vf.punto_venta_id', 'vf.empresa_id');
            $sg = "IF(ct.signo = '-', -1, 1)";
            $sql = "SELECT vf.venta_factura_id as id,
                           vf.f_emision,
                           DATE_FORMAT(vf.f_emision, '%Y%m') as periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Factura') as tipo_nombre,
                           COALESCE(ct.letra, 'A') as letra,
                           $pv as punto_venta,
                           vf.comprobante_nro as numero,
                           COALESCE(e.cuit, '') as cuit_contraparte,
                           COALESCE(e.entidad_nombre, vf.consumidor_final_nombre, 'Consumidor Final') as contraparte,
                           $sg * vf.importe_neto as neto_gravado,
                           21.00 as alicuota_iva,
                           $sg * vf.importe_iva as importe_iva,
                           $sg * (vf.importe_no_gravado + vf.importe_exento) as no_gravado_exento,
                           $sg * vf.importe_otros_impuestos as percepciones,
                           $sg * vf.importe_total as importe_total,
                           COALESCE(vf.CAE, '') as cae,
                           'ERP' as origen,
                           vf.tabla_estado_registro_id
                    FROM gestion__ventas_facturas vf
                    INNER JOIN gestion__comprobantes_tipos ct ON vf.comprobante_tipo_id = ct.comprobante_tipo_id AND ct.comprobante_fiscal_id > 0
                    LEFT JOIN gestion__entidades e ON vf.entidad_id = e.entidad_id
                    WHERE vf.empresa_id = ? AND vf.tabla_estado_registro_id IN (" . self::ERP_ESTADOS_VENTAS . ")";
            
            if (!empty($periodo_fiscal)) {
                $sql .= " AND DATE_FORMAT(vf.f_emision, '%Y%m') = ?";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "is", $empresa_erp, $periodo_fiscal);
            } else {
                $sql .= " ORDER BY vf.f_emision DESC LIMIT 200";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $empresa_erp);
            }
        } else {
            // Lectura desde comprobantes cargados en mini-gestión
            $sql = "SELECT mc.comprobante_id as id,
                           mc.f_emision,
                           mc.periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Comprobante') as tipo_nombre,
                           mc.comprobante_letra as letra,
                           mc.punto_venta,
                           mc.comprobante_nro as numero,
                           mc.cuit_contraparte,
                           mc.razon_social_contraparte as contraparte,
                           IF(ct.signo = '-', -1, 1) * mc.neto_gravado as neto_gravado,
                           mc.alicuota_iva,
                           IF(ct.signo = '-', -1, 1) * mc.importe_iva as importe_iva,
                           IF(ct.signo = '-', -1, 1) * mc.no_gravado_exento as no_gravado_exento,
                           IF(ct.signo = '-', -1, 1) * (mc.percepcion_iibb + mc.percepcion_iva) as percepciones,
                           IF(ct.signo = '-', -1, 1) * mc.percepcion_iibb as percepcion_iibb,
                           IF(ct.signo = '-', -1, 1) * mc.percepcion_iva as percepcion_iva,
                           IF(ct.signo = '-', -1, 1) * mc.importe_total as importe_total,
                           '' as cae,
                           'MANUAL' as origen,
                           mc.tabla_estado_registro_id
                    FROM estudio__minigestion_comprobantes mc
                    LEFT JOIN gestion__comprobantes_tipos ct ON mc.comprobante_tipo_id = ct.comprobante_tipo_id
                    WHERE mc.empresa_id = ? AND mc.cliente_id = ? AND mc.tipo_operacion = 'VENTA' AND mc.tabla_estado_registro_id = 1";
            
            if (!empty($periodo_fiscal)) {
                $sql .= " AND mc.periodo_fiscal = ? ORDER BY mc.f_emision DESC";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $cliente_id, $periodo_fiscal);
            } else {
                $sql .= " ORDER BY mc.f_emision DESC LIMIT 200";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cliente_id);
            }
        }

        if (!$stmt) return [];
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }

    /**
     * Lista comprobantes de compras/gastos (desde ERP o MANUAL)
     */
    public function listarCompras($empresa_id, $cliente_id, $periodo_fiscal = '') {
        $cliente = $this->getClienteInfo($empresa_id, $cliente_id);
        if (!$cliente) return [];

        if ($cliente['modo'] === 'ERP') {
            $this->exigirPermisoErp($cliente, 'compras');
            $empresa_erp = intval($cliente['empresa_vinculada_id']);
            $sg = "IF(ct.signo = '-', -1, 1)";
            $sql = "SELECT fp.factura_proveedor_id as id,
                           fp.f_emision,
                           DATE_FORMAT(COALESCE(fp.f_contabilidad, fp.f_emision), '%Y%m') as periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Factura Compra') as tipo_nombre,
                           COALESCE(ct.letra, 'A') as letra,
                           fp.comprobante_pv as punto_venta,
                           fp.comprobante_nro as numero,
                           COALESCE(e.cuit, '') as cuit_contraparte,
                           COALESCE(e.entidad_nombre, 'Proveedor') as contraparte,
                           $sg * fp.subtotal as neto_gravado,
                           21.00 as alicuota_iva,
                           $sg * fp.impuestos as importe_iva,
                           $sg * (fp.no_gravado + fp.exento) as no_gravado_exento,
                           $sg * fp.otros_impuestos as percepciones,
                           $sg * fp.total as importe_total,
                           'ERP' as origen,
                           fp.tabla_estado_registro_id
                    FROM gestion__facturas_proveedores fp
                    INNER JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id AND ct.comprobante_fiscal_id > 0
                    LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
                    WHERE fp.empresa_id = ? AND fp.tabla_estado_registro_id = " . self::ERP_ESTADO_COMPRAS;

            if (!empty($periodo_fiscal)) {
                $sql .= " AND DATE_FORMAT(COALESCE(fp.f_contabilidad, fp.f_emision), '%Y%m') = ?";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "is", $empresa_erp, $periodo_fiscal);
            } else {
                $sql .= " ORDER BY fp.f_emision DESC LIMIT 200";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $empresa_erp);
            }
        } else {
            $sql = "SELECT mc.comprobante_id as id,
                           mc.f_emision,
                           mc.periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Comprobante') as tipo_nombre,
                           mc.comprobante_letra as letra,
                           mc.punto_venta,
                           mc.comprobante_nro as numero,
                           mc.cuit_contraparte,
                           mc.razon_social_contraparte as contraparte,
                           IF(ct.signo = '-', -1, 1) * mc.neto_gravado as neto_gravado,
                           mc.alicuota_iva,
                           IF(ct.signo = '-', -1, 1) * mc.importe_iva as importe_iva,
                           IF(ct.signo = '-', -1, 1) * mc.no_gravado_exento as no_gravado_exento,
                           IF(ct.signo = '-', -1, 1) * (mc.percepcion_iibb + mc.percepcion_iva) as percepciones,
                           IF(ct.signo = '-', -1, 1) * mc.percepcion_iibb as percepcion_iibb,
                           IF(ct.signo = '-', -1, 1) * mc.percepcion_iva as percepcion_iva,
                           IF(ct.signo = '-', -1, 1) * mc.importe_total as importe_total,
                           'MANUAL' as origen,
                           mc.tabla_estado_registro_id
                    FROM estudio__minigestion_comprobantes mc
                    LEFT JOIN gestion__comprobantes_tipos ct ON mc.comprobante_tipo_id = ct.comprobante_tipo_id
                    WHERE mc.empresa_id = ? AND mc.cliente_id = ? AND mc.tipo_operacion = 'COMPRA' AND mc.tabla_estado_registro_id = 1";
            
            if (!empty($periodo_fiscal)) {
                $sql .= " AND mc.periodo_fiscal = ? ORDER BY mc.f_emision DESC";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $cliente_id, $periodo_fiscal);
            } else {
                $sql .= " ORDER BY mc.f_emision DESC LIMIT 200";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $cliente_id);
            }
        }

        if (!$stmt) return [];
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }

    /**
     * Calcula los totales fiscales del período para el cliente.
     * Las percepciones de IVA sufridas en compras son crédito contra el IVA a pagar; las de IIBB NO tocan el IVA
     * (van a la liquidación de Ingresos Brutos). En modo ERP el importe de "otros impuestos" no discrimina tipo:
     * se informa aparte como percepciones_sin_discriminar y no se resta del IVA.
     */
    public function getTotalesFiscales($empresa_id, $cliente_id, $periodo_fiscal = '') {
        $cliente = $this->getClienteInfo($empresa_id, $cliente_id);
        $ventas = $this->listarVentas($empresa_id, $cliente_id, $periodo_fiscal);
        $compras = $this->listarCompras($empresa_id, $cliente_id, $periodo_fiscal);
        $esErp = $cliente && $cliente['modo'] === 'ERP';

        $tv = ['neto' => 0.0, 'iva' => 0.0, 'total' => 0.0];
        foreach ($ventas as $v) {
            $tv['neto'] += floatval($v['neto_gravado'] ?? 0);
            $tv['iva'] += floatval($v['importe_iva'] ?? 0);
            $tv['total'] += floatval($v['importe_total'] ?? 0);
        }
        $tc = ['neto' => 0.0, 'iva' => 0.0, 'total' => 0.0, 'perc_iva' => 0.0, 'perc_iibb' => 0.0, 'perc_otras' => 0.0];
        foreach ($compras as $c) {
            $tc['neto'] += floatval($c['neto_gravado'] ?? 0);
            // Crédito fiscal computable: solo letras A / M (en B / C / X el IVA no se discrimina), como en la Posición de IVA del ERP
            if (in_array(strtoupper(trim($c['letra'] ?? 'A')), ['A', 'M'], true)) {
                $tc['iva'] += floatval($c['importe_iva'] ?? 0);
            }
            $tc['total'] += floatval($c['importe_total'] ?? 0);
            if ($esErp) {
                $tc['perc_otras'] += floatval($c['percepciones'] ?? 0);
            } else {
                $tc['perc_iva'] += floatval($c['percepcion_iva'] ?? 0);
                $tc['perc_iibb'] += floatval($c['percepcion_iibb'] ?? 0);
            }
        }

        $saldo_tecnico = $tv['iva'] - $tc['iva'];
        $posicion_final = $saldo_tecnico - $tc['perc_iva'];

        return [
            'ventas_neto' => round($tv['neto'], 2),
            'iva_debito' => round($tv['iva'], 2),
            'ventas_total' => round($tv['total'], 2),
            'compras_neto' => round($tc['neto'], 2),
            'iva_credito' => round($tc['iva'], 2),
            'percepciones' => round($tc['perc_iva'], 2),            // percepciones de IVA (compat. con el resumen)
            'percepciones_iva' => round($tc['perc_iva'], 2),
            'percepciones_iibb' => round($tc['perc_iibb'], 2),
            'percepciones_sin_discriminar' => round($tc['perc_otras'], 2),
            'compras_total' => round($tc['total'], 2),
            'saldo_tecnico' => round($saldo_tecnico, 2),
            'posicion_final' => round($posicion_final, 2),
            'cant_ventas' => count($ventas),
            'cant_compras' => count($compras),
            'ventas_por_alicuota' => $this->ivaPorAlicuota($empresa_id, $cliente_id, 'VENTA', $periodo_fiscal, $esErp),
            'compras_por_alicuota' => $this->ivaPorAlicuota($empresa_id, $cliente_id, 'COMPRA', $periodo_fiscal, $esErp),
            'modo' => $esErp ? 'ERP' : 'MANUAL'
        ];
    }

    /**
     * Neto e IVA agrupados por alícuota. MANUAL: sale del detalle cargado (las notas de crédito restan).
     * ERP: las tablas de gestión no se discriminan por alícuota en esta lectura, así que devuelve [] y la
     * pantalla muestra solo los totales (antes se mostraba todo como 21%, que era incorrecto).
     */
    public function ivaPorAlicuota($empresa_id, $cliente_id, $tipo_operacion, $periodo_fiscal = '', $esErp = false) {
        if ($esErp) return [];
        $sql = "SELECT i.alicuota, SUM(IF(ct.signo = '-', -1, 1) * i.neto) AS neto, SUM(IF(ct.signo = '-', -1, 1) * i.iva) AS iva
                FROM estudio__minigestion_comprobantes mc
                INNER JOIN estudio__minigestion_comprobantes_iva i ON i.comprobante_id = mc.comprobante_id
                LEFT JOIN gestion__comprobantes_tipos ct ON mc.comprobante_tipo_id = ct.comprobante_tipo_id
                WHERE mc.empresa_id = ? AND mc.cliente_id = ? AND mc.tipo_operacion = ? AND mc.tabla_estado_registro_id = 1"
             . ($tipo_operacion === 'COMPRA' ? " AND mc.comprobante_letra IN ('A', 'M')" : "");
        $tipos = "iis"; $params = [$empresa_id, $cliente_id, $tipo_operacion];
        if (!empty($periodo_fiscal)) { $sql .= " AND mc.periodo_fiscal = ?"; $tipos .= "i"; $params[] = intval($periodo_fiscal); }
        $sql .= " GROUP BY i.alicuota ORDER BY i.alicuota DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        foreach ($rows as &$r) { $r['neto'] = round(floatval($r['neto']), 2); $r['iva'] = round(floatval($r['iva']), 2); $r['alicuota'] = floatval($r['alicuota']); }
        return $rows;
    }

    /**
     * Guarda un comprobante manual (modo carga mini-gestión).
     * El servidor NO confía en los importes que manda el navegador: el IVA de cada línea y el total se recalculan acá.
     * Entradas: iva_detalle = [{alicuota, neto}], percepciones = [{tipo, jurisdiccion_id, importe}].
     * Compatibilidad: si no llega iva_detalle se arma una sola línea con neto_gravado / alicuota_iva (formulario viejo).
     */
    public function guardarComprobanteManual($empresa_id, $data) {
        $comprobante_id = intval($data['comprobante_id'] ?? 0);
        $cliente_id = intval($data['cliente_id'] ?? 0);
        $tipo_operacion = (($data['tipo_operacion'] ?? '') === 'COMPRA') ? 'COMPRA' : 'VENTA';
        $f_emision = trim($data['f_emision'] ?? '');
        $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
        $punto_venta = intval($data['punto_venta'] ?? 0);
        $comprobante_nro = intval($data['comprobante_nro'] ?? 0);
        $cuit_contraparte = preg_replace('/\D/', '', trim($data['cuit_contraparte'] ?? ''));
        $razon_social_contraparte = trim($data['razon_social_contraparte'] ?? '');
        $no_gravado_exento = round(floatval($data['no_gravado_exento'] ?? 0), 2);
        $observaciones = trim($data['observaciones'] ?? '');

        // --- Validaciones de cabecera ---
        if ($cliente_id <= 0) throw new Exception("Seleccioná un cliente.");
        $d = DateTime::createFromFormat('Y-m-d', $f_emision);
        if (!$d || $d->format('Y-m-d') !== $f_emision) throw new Exception("Fecha de emisión inválida.");
        if ($f_emision > date('Y-m-d', strtotime('+1 day'))) throw new Exception("La fecha de emisión no puede ser futura.");
        if ($comprobante_tipo_id <= 0) throw new Exception("Elegí el tipo de comprobante.");
        if ($punto_venta <= 0 || $punto_venta > 99999) throw new Exception("Punto de venta inválido.");
        if ($comprobante_nro <= 0) throw new Exception("Número de comprobante inválido.");
        if ($razon_social_contraparte === '') throw new Exception("Falta la razón social de la contraparte.");
        if ($cuit_contraparte !== '' && !self::cuitValido($cuit_contraparte)) throw new Exception("El CUIT de la contraparte no es válido.");
        $periodo_fiscal = intval($d->format('Ym'));

        // El cliente tiene que ser del estudio y operar en modo MANUAL (el modo ERP es solo lectura)
        $cliente = $this->getClienteInfo($empresa_id, $cliente_id);
        if (!$cliente) throw new Exception("Cliente inexistente.");
        if ($cliente['modo'] !== 'MANUAL') throw new Exception("El cliente opera en el ERP: sus comprobantes no se cargan acá.");

        // Tipo de comprobante: de la empresa (o global) y con su letra real (no la que manda el navegador)
        $sqlT = "SELECT letra FROM gestion__comprobantes_tipos
                 WHERE comprobante_tipo_id = ? AND (empresa_id = ? OR empresa_id = 0) AND tabla_estado_registro_id = 1";
        $st = mysqli_prepare($this->conn, $sqlT);
        mysqli_stmt_bind_param($st, "ii", $comprobante_tipo_id, $empresa_id);
        mysqli_stmt_execute($st);
        $rowT = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$rowT) throw new Exception("Tipo de comprobante inexistente para esta empresa.");
        $comprobante_letra = strtoupper(substr(trim($rowT['letra'] ?? ''), 0, 2));
        if ($comprobante_letra === '') $comprobante_letra = strtoupper(substr(trim($data['comprobante_letra'] ?? 'A'), 0, 2));

        // --- Líneas de IVA (recalculadas) ---
        $lineasIva = $this->lineas($data['iva_detalle'] ?? null);
        if (!$lineasIva && isset($data['neto_gravado'])) {
            $lineasIva = [['alicuota' => $data['alicuota_iva'] ?? 21, 'neto' => $data['neto_gravado']]];
        }
        $iva = []; // alicuota(string) => [neto, iva]
        foreach ($lineasIva as $l) {
            $alic = round(floatval($l['alicuota'] ?? -1), 2);
            $neto = round(floatval($l['neto'] ?? 0), 2);
            if (!in_array($alic, self::ALICUOTAS_VALIDAS, true)) throw new Exception("Alícuota de IVA no admitida: " . $alic . "%.");
            if ($neto == 0) continue;
            $k = number_format($alic, 2, '.', '');
            if (!isset($iva[$k])) $iva[$k] = ['alicuota' => $alic, 'neto' => 0.0, 'iva' => 0.0];
            $iva[$k]['neto'] = round($iva[$k]['neto'] + $neto, 2);
        }
        $neto_total = 0.0; $iva_total = 0.0; $alicuota_principal = 21.00; $mayor = null;
        foreach ($iva as $k => &$l) {
            $l['iva'] = round($l['neto'] * $l['alicuota'] / 100, 2);
            $neto_total += $l['neto']; $iva_total += $l['iva'];
            if ($mayor === null || abs($l['neto']) > $mayor) { $mayor = abs($l['neto']); $alicuota_principal = $l['alicuota']; }
        }
        unset($l);
        $neto_total = round($neto_total, 2); $iva_total = round($iva_total, 2);

        // --- Percepciones desglosadas ---
        $percs = []; $percepcion_iibb = 0.0; $percepcion_iva = 0.0; $percepciones_otras = 0.0;
        foreach ($this->lineas($data['percepciones'] ?? null) as $p) {
            $tipo = strtoupper(trim($p['tipo'] ?? ''));
            $imp = round(floatval($p['importe'] ?? 0), 2);
            if ($imp == 0) continue;
            if (!in_array($tipo, self::PERCEPCION_TIPOS, true)) throw new Exception("Tipo de percepción inválido.");
            $jur = intval($p['jurisdiccion_id'] ?? 0);
            if (in_array($tipo, ['IIBB', 'MUNICIPAL'], true)) {
                if ($jur <= 0) throw new Exception("La percepción de $tipo necesita la jurisdicción.");
            } else {
                $jur = 0;
            }
            $percs[] = ['tipo' => $tipo, 'jurisdiccion_id' => $jur, 'importe' => $imp];
            if ($tipo === 'IIBB') $percepcion_iibb += $imp;
            elseif ($tipo === 'IVA') $percepcion_iva += $imp;
            else $percepciones_otras += $imp;
        }
        // Compatibilidad con el formulario viejo (campos planos)
        if (!$percs && !isset($data['percepciones'])) {
            $pi = round(floatval($data['percepcion_iibb'] ?? 0), 2);
            $pv = round(floatval($data['percepcion_iva'] ?? 0), 2);
            if ($pi != 0) { $percs[] = ['tipo' => 'IIBB', 'jurisdiccion_id' => 0, 'importe' => $pi]; $percepcion_iibb += $pi; }
            if ($pv != 0) { $percs[] = ['tipo' => 'IVA', 'jurisdiccion_id' => 0, 'importe' => $pv]; $percepcion_iva += $pv; }
        }
        $percepcion_iibb = round($percepcion_iibb, 2); $percepcion_iva = round($percepcion_iva, 2);

        $importe_total = round($neto_total + $iva_total + $no_gravado_exento + $percepcion_iibb + $percepcion_iva + $percepciones_otras, 2);
        if ($importe_total == 0) throw new Exception("El comprobante no tiene importes.");

        // --- Duplicados: mismo cliente + operación + tipo + PV + número + contraparte (ignorando anulados y a sí mismo) ---
        $sqlD = "SELECT comprobante_id FROM estudio__minigestion_comprobantes
                 WHERE empresa_id = ? AND cliente_id = ? AND tipo_operacion = ? AND comprobante_tipo_id = ?
                   AND punto_venta = ? AND comprobante_nro = ? AND REPLACE(cuit_contraparte, '-', '') = ?
                   AND tabla_estado_registro_id = 1 AND comprobante_id <> ? LIMIT 1";
        $sd = mysqli_prepare($this->conn, $sqlD);
        mysqli_stmt_bind_param($sd, "iisiiisi", $empresa_id, $cliente_id, $tipo_operacion, $comprobante_tipo_id,
            $punto_venta, $comprobante_nro, $cuit_contraparte, $comprobante_id);
        mysqli_stmt_execute($sd);
        $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($sd));
        mysqli_stmt_close($sd);
        if ($dup) throw new Exception("Ya existe ese comprobante cargado (ID " . intval($dup['comprobante_id']) . ").");

        // Período ya liquidado: ni el período nuevo ni el que tenía el comprobante (si se lo cambia de mes) se tocan
        $this->exigirPeriodoNoLiquidado($empresa_id, $cliente_id, $periodo_fiscal);
        if ($comprobante_id > 0) {
            $previo = $this->obtenerComprobanteManual($empresa_id, $comprobante_id);
            if (!$previo) throw new Exception("El comprobante no existe o está anulado.");
            $this->exigirPeriodoNoLiquidado($empresa_id, $cliente_id, intval($previo['periodo_fiscal']));
        }

        mysqli_begin_transaction($this->conn);
        try {
            if ($comprobante_id > 0) {
                $sql = "UPDATE estudio__minigestion_comprobantes
                        SET tipo_operacion = ?, f_emision = ?, periodo_fiscal = ?, comprobante_tipo_id = ?,
                            comprobante_letra = ?, punto_venta = ?, comprobante_nro = ?, cuit_contraparte = ?,
                            razon_social_contraparte = ?, neto_gravado = ?, alicuota_iva = ?, importe_iva = ?,
                            no_gravado_exento = ?, percepcion_iibb = ?, percepcion_iva = ?, importe_total = ?,
                            observaciones = ?
                        WHERE comprobante_id = ? AND empresa_id = ? AND cliente_id = ? AND tabla_estado_registro_id = 1";
                $stmt = mysqli_prepare($this->conn, $sql);
                if (!$stmt) throw new Exception("Error al preparar consulta: " . mysqli_error($this->conn));
                mysqli_stmt_bind_param($stmt, "ssiisiissdddddddsiii",
                    $tipo_operacion, $f_emision, $periodo_fiscal, $comprobante_tipo_id,
                    $comprobante_letra, $punto_venta, $comprobante_nro, $cuit_contraparte,
                    $razon_social_contraparte, $neto_total, $alicuota_principal, $iva_total,
                    $no_gravado_exento, $percepcion_iibb, $percepcion_iva, $importe_total,
                    $observaciones, $comprobante_id, $empresa_id, $cliente_id
                );
                $ok = mysqli_stmt_execute($stmt);
                $filas = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if (!$ok) throw new Exception("Error al actualizar comprobante.");
                // affected_rows = 0 puede ser "sin cambios": verificar que exista
                if ($filas === 0 && !$this->obtenerComprobanteManual($empresa_id, $comprobante_id)) {
                    throw new Exception("El comprobante no existe o está anulado.");
                }
                $id = $comprobante_id;
            } else {
                $sql = "INSERT INTO estudio__minigestion_comprobantes
                        (empresa_id, cliente_id, tipo_operacion, f_emision, periodo_fiscal,
                         comprobante_tipo_id, comprobante_letra, punto_venta, comprobante_nro,
                         cuit_contraparte, razon_social_contraparte, neto_gravado, alicuota_iva,
                         importe_iva, no_gravado_exento, percepcion_iibb, percepcion_iva,
                         importe_total, observaciones, tabla_estado_registro_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = mysqli_prepare($this->conn, $sql);
                if (!$stmt) throw new Exception("Error al preparar alta: " . mysqli_error($this->conn));
                mysqli_stmt_bind_param($stmt, "iissiisiissddddddds",
                    $empresa_id, $cliente_id, $tipo_operacion, $f_emision, $periodo_fiscal,
                    $comprobante_tipo_id, $comprobante_letra, $punto_venta, $comprobante_nro,
                    $cuit_contraparte, $razon_social_contraparte, $neto_total, $alicuota_principal,
                    $iva_total, $no_gravado_exento, $percepcion_iibb, $percepcion_iva,
                    $importe_total, $observaciones
                );
                if (!mysqli_stmt_execute($stmt)) { $e = mysqli_error($this->conn); mysqli_stmt_close($stmt); throw new Exception("Error al insertar comprobante: $e"); }
                $id = mysqli_insert_id($this->conn);
                mysqli_stmt_close($stmt);
            }

            // Detalle: se reemplaza completo
            foreach (['estudio__minigestion_comprobantes_iva', 'estudio__minigestion_comprobantes_percepciones'] as $tabla) {
                $sx = mysqli_prepare($this->conn, "DELETE FROM $tabla WHERE comprobante_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($sx, "ii", $id, $empresa_id);
                mysqli_stmt_execute($sx);
                mysqli_stmt_close($sx);
            }
            $si = mysqli_prepare($this->conn, "INSERT INTO estudio__minigestion_comprobantes_iva (empresa_id, comprobante_id, alicuota, neto, iva) VALUES (?, ?, ?, ?, ?)");
            foreach ($iva as $l) {
                mysqli_stmt_bind_param($si, "iiddd", $empresa_id, $id, $l['alicuota'], $l['neto'], $l['iva']);
                if (!mysqli_stmt_execute($si)) throw new Exception("Error al guardar el detalle de IVA.");
            }
            mysqli_stmt_close($si);
            $sp = mysqli_prepare($this->conn, "INSERT INTO estudio__minigestion_comprobantes_percepciones (empresa_id, comprobante_id, tipo, jurisdiccion_id, importe) VALUES (?, ?, ?, NULLIF(?, 0), ?)");
            foreach ($percs as $p) {
                mysqli_stmt_bind_param($sp, "iisid", $empresa_id, $id, $p['tipo'], $p['jurisdiccion_id'], $p['importe']);
                if (!mysqli_stmt_execute($sp)) throw new Exception("Error al guardar las percepciones.");
            }
            mysqli_stmt_close($sp);

            mysqli_commit($this->conn);
            return $id;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /** Líneas de IVA y percepciones de un comprobante manual (para el modal de edición). */
    public function obtenerDetalleManual($empresa_id, $comprobante_id) {
        $out = ['iva_detalle' => [], 'percepciones' => []];
        $s = mysqli_prepare($this->conn, "SELECT alicuota, neto, iva FROM estudio__minigestion_comprobantes_iva WHERE empresa_id = ? AND comprobante_id = ? ORDER BY alicuota DESC");
        mysqli_stmt_bind_param($s, "ii", $empresa_id, $comprobante_id);
        mysqli_stmt_execute($s);
        $out['iva_detalle'] = mysqli_fetch_all(mysqli_stmt_get_result($s), MYSQLI_ASSOC);
        mysqli_stmt_close($s);
        $s = mysqli_prepare($this->conn, "SELECT tipo, jurisdiccion_id, importe FROM estudio__minigestion_comprobantes_percepciones WHERE empresa_id = ? AND comprobante_id = ? ORDER BY percepcion_id");
        mysqli_stmt_bind_param($s, "ii", $empresa_id, $comprobante_id);
        mysqli_stmt_execute($s);
        $out['percepciones'] = mysqli_fetch_all(mysqli_stmt_get_result($s), MYSQLI_ASSOC);
        mysqli_stmt_close($s);
        return $out;
    }

    /** Jurisdicciones de IIBB del cliente (para elegir en percepciones). */
    public function jurisdiccionesCliente($empresa_id, $cliente_id) {
        $s = mysqli_prepare($this->conn, "SELECT cj.jurisdiccion_id, cj.tipo_inscripcion, j.jurisdiccion_nombre
                                           FROM estudio__clientes_jurisdicciones cj
                                           LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cj.jurisdiccion_id
                                           WHERE cj.empresa_id = ? AND cj.cliente_id = ? AND cj.tabla_estado_registro_id = 1
                                             AND (cj.f_baja IS NULL OR cj.f_baja >= CURDATE())
                                           ORDER BY j.jurisdiccion_nombre");
        mysqli_stmt_bind_param($s, "ii", $empresa_id, $cliente_id);
        mysqli_stmt_execute($s);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($s), MYSQLI_ASSOC);
        mysqli_stmt_close($s);
        return $rows;
    }

    /**
     * Obtiene un comprobante manual específico
     */
    public function obtenerComprobanteManual($empresa_id, $comprobante_id) {
        $sql = "SELECT * FROM estudio__minigestion_comprobantes 
                WHERE empresa_id = ? AND comprobante_id = ? AND tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $comprobante_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if ($row) {
            $row += $this->obtenerDetalleManual($empresa_id, $comprobante_id);
        }
        return $row;
    }

    /**
     * Anula un comprobante manual
     */
    public function anularComprobanteManual($empresa_id, $comprobante_id) {
        $previo = $this->obtenerComprobanteManual($empresa_id, $comprobante_id);
        if (!$previo) throw new Exception("El comprobante no existe o ya está anulado.");
        $this->exigirPeriodoNoLiquidado($empresa_id, intval($previo['cliente_id']), intval($previo['periodo_fiscal']));
        $sql = "UPDATE estudio__minigestion_comprobantes 
                SET tabla_estado_registro_id = 2 
                WHERE empresa_id = ? AND comprobante_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $comprobante_id);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Obtiene los tipos de comprobantes activos para el modal
     */
    public function listarTiposComprobantes($empresa_id) {
        // Catálogo por empresa: los comprobantes manuales se guardan con la empresa del estudio
        $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra, codigo
                FROM gestion__comprobantes_tipos
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY letra ASC, comprobante_tipo ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $list;
    }

    // ------------------------------------------------------------------ liquidación mensual de IVA (clientes MANUAL)
    const LIQ_VIGENTE = 5;
    const LIQ_ANULADA = 38;

    private function periodoValido($periodo) {
        $p = intval($periodo);
        return $p >= 200001 && $p <= 209912 && intval($p % 100) >= 1 && intval($p % 100) <= 12;
    }

    /** Liquidación vigente del cliente para el período (o null). */
    public function liquidacionVigente($empresa_id, $cliente_id, $periodo) {
        $s = mysqli_prepare($this->conn, "SELECT * FROM estudio__minigestion_liquidaciones_iva
                                           WHERE empresa_id = ? AND cliente_id = ? AND periodo = ? AND tabla_estado_registro_id = ?
                                           ORDER BY liquidacion_id DESC LIMIT 1");
        $est = self::LIQ_VIGENTE; $per = intval($periodo);
        mysqli_stmt_bind_param($s, "iiii", $empresa_id, $cliente_id, $per, $est);
        mysqli_stmt_execute($s);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        mysqli_stmt_close($s);
        return $row ?: null;
    }

    /** Saldo a favor que deja la última liquidación vigente anterior al período. */
    private function saldoFavorAnterior($empresa_id, $cliente_id, $periodo) {
        $s = mysqli_prepare($this->conn, "SELECT periodo, saldo_a_favor FROM estudio__minigestion_liquidaciones_iva
                                           WHERE empresa_id = ? AND cliente_id = ? AND periodo < ? AND tabla_estado_registro_id = ?
                                           ORDER BY periodo DESC, liquidacion_id DESC LIMIT 1");
        $est = self::LIQ_VIGENTE; $per = intval($periodo);
        mysqli_stmt_bind_param($s, "iiii", $empresa_id, $cliente_id, $per, $est);
        mysqli_stmt_execute($s);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        mysqli_stmt_close($s);
        return $row ? ['periodo' => intval($row['periodo']), 'importe' => round(floatval($row['saldo_a_favor']), 2)] : ['periodo' => null, 'importe' => 0.0];
    }

    /** ¿Hay una liquidación vigente que cubra el período del comprobante? Bloquea alta / edición / anulación. */
    public function exigirPeriodoNoLiquidado($empresa_id, $cliente_id, $periodo) {
        if ($this->liquidacionVigente($empresa_id, $cliente_id, $periodo)) {
            $p = intval($periodo);
            throw new Exception(sprintf("El IVA del período %02d/%04d ya está liquidado: anulá la liquidación para modificar sus comprobantes.", $p % 100, intdiv($p, 100)));
        }
    }

    /** Cálculo de la posición del período con los comprobantes actuales (no guarda nada). */
    public function calcularLiquidacion($empresa_id, $cliente_id, $periodo, $retenciones = 0.0) {
        if (!$this->periodoValido($periodo)) throw new Exception("Período inválido (AAAAMM).");
        $cliente = $this->getClienteInfo($empresa_id, $cliente_id);
        if (!$cliente) throw new Exception("Cliente inexistente.");
        $t = $this->getTotalesFiscales($empresa_id, $cliente_id, strval(intval($periodo)));
        $ant = $this->saldoFavorAnterior($empresa_id, $cliente_id, $periodo);
        $ret = round(max(0.0, floatval($retenciones)), 2);
        $resultado = round($t['iva_debito'] - $t['iva_credito'] - $t['percepciones_iva'] - $ret - $ant['importe'], 2);
        return [
            'periodo' => intval($periodo),
            'modo' => $cliente['modo'],
            'debito_fiscal' => $t['iva_debito'],
            'credito_fiscal' => $t['iva_credito'],
            'percepciones_iva' => $t['percepciones_iva'],
            'retenciones_iva' => $ret,
            'saldo_favor_anterior' => $ant['importe'],
            'saldo_favor_anterior_periodo' => $ant['periodo'],
            'saldo_a_pagar' => $resultado > 0 ? $resultado : 0.0,
            'saldo_a_favor' => $resultado < 0 ? round(-$resultado, 2) : 0.0,
            'cant_ventas' => $t['cant_ventas'],
            'cant_compras' => $t['cant_compras'],
            'vigente' => $this->liquidacionVigente($empresa_id, $cliente_id, $periodo),
        ];
    }

    /** Fija la liquidación del período. Los importes se recalculan acá; solo las retenciones vienen del usuario. */
    public function liquidarIva($empresa_id, $usuario_id, $cliente_id, $periodo, $retenciones, $observaciones = '') {
        $c = $this->calcularLiquidacion($empresa_id, $cliente_id, $periodo, $retenciones);
        if ($c['modo'] !== 'MANUAL') throw new Exception("Este cliente opera en el ERP: el IVA se liquida en la Posición de IVA de su empresa.");
        if ($c['vigente']) throw new Exception("El período ya está liquidado. Anulá la liquidación vigente para volver a liquidar.");
        if ($c['cant_ventas'] + $c['cant_compras'] === 0) throw new Exception("El período no tiene comprobantes cargados.");
        // Una liquidación posterior ya consumió el saldo a favor de esta: se liquida en orden
        $s = mysqli_prepare($this->conn, "SELECT periodo FROM estudio__minigestion_liquidaciones_iva
                                           WHERE empresa_id = ? AND cliente_id = ? AND periodo > ? AND tabla_estado_registro_id = ? LIMIT 1");
        $est = self::LIQ_VIGENTE; $per = intval($periodo);
        mysqli_stmt_bind_param($s, "iiii", $empresa_id, $cliente_id, $per, $est);
        mysqli_stmt_execute($s);
        $posterior = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        mysqli_stmt_close($s);
        if ($posterior) throw new Exception("Ya hay una liquidación vigente de un período posterior (" . intval($posterior['periodo']) . "): anulala primero.");
        $obs = mb_substr(trim($observaciones), 0, 255);

        $s = mysqli_prepare($this->conn, "INSERT INTO estudio__minigestion_liquidaciones_iva
            (empresa_id, cliente_id, periodo, debito_fiscal, credito_fiscal, percepciones_iva, retenciones_iva,
             saldo_favor_anterior, saldo_a_pagar, saldo_a_favor, observaciones, usuario_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $estado = self::LIQ_VIGENTE;
        mysqli_stmt_bind_param($s, "iiidddddddsii", $empresa_id, $cliente_id, $per, $c['debito_fiscal'], $c['credito_fiscal'],
            $c['percepciones_iva'], $c['retenciones_iva'], $c['saldo_favor_anterior'], $c['saldo_a_pagar'], $c['saldo_a_favor'],
            $obs, $usuario_id, $estado);
        if (!mysqli_stmt_execute($s)) { $e = mysqli_error($this->conn); mysqli_stmt_close($s); throw new Exception("No se pudo guardar la liquidación: $e"); }
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($s);
        return $id;
    }

    public function anularLiquidacionIva($empresa_id, $cliente_id, $periodo) {
        $v = $this->liquidacionVigente($empresa_id, $cliente_id, $periodo);
        if (!$v) throw new Exception("No hay liquidación vigente en ese período.");
        $s = mysqli_prepare($this->conn, "SELECT periodo FROM estudio__minigestion_liquidaciones_iva
                                           WHERE empresa_id = ? AND cliente_id = ? AND periodo > ? AND tabla_estado_registro_id = ? LIMIT 1");
        $est = self::LIQ_VIGENTE; $per = intval($periodo);
        mysqli_stmt_bind_param($s, "iiii", $empresa_id, $cliente_id, $per, $est);
        mysqli_stmt_execute($s);
        $posterior = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        mysqli_stmt_close($s);
        if ($posterior) throw new Exception("Hay una liquidación vigente posterior (" . intval($posterior['periodo']) . "): anulala primero.");
        $s = mysqli_prepare($this->conn, "UPDATE estudio__minigestion_liquidaciones_iva
                                           SET tabla_estado_registro_id = ?, anulada_at = NOW()
                                           WHERE liquidacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?");
        $nueva = self::LIQ_ANULADA; $id = intval($v['liquidacion_id']); $vig = self::LIQ_VIGENTE;
        mysqli_stmt_bind_param($s, "iiii", $nueva, $id, $empresa_id, $vig);
        $ok = mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
        if (!$ok) throw new Exception("No se pudo anular la liquidación.");
        return true;
    }
}
