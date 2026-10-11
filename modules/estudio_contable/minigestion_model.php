<?php
/**
 * Modelo: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion_model.php)
 * 
 * Gestiona comprobantes de compra/venta para clientes externos (carga manual)
 * y visualización/auditoría para clientes vinculados al ERP (solo lectura).
 */

require_once __DIR__ . '/../../db.php';

class MiniGestionModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
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
            // Lectura directa desde gestión de la empresa vinculada
            $empresa_erp = intval($cliente['empresa_vinculada_id']);
            $sql = "SELECT vf.venta_factura_id as id,
                           vf.f_emision,
                           DATE_FORMAT(vf.f_emision, '%Y%m') as periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Factura') as tipo_nombre,
                           COALESCE(ct.letra, 'A') as letra,
                           1 as punto_venta,
                           vf.comprobante_nro as numero,
                           COALESCE(e.cuit, '') as cuit_contraparte,
                           COALESCE(e.entidad_nombre, vf.consumidor_final_nombre, 'Consumidor Final') as contraparte,
                           vf.importe_neto as neto_gravado,
                           21.00 as alicuota_iva,
                           vf.importe_iva,
                           (vf.importe_no_gravado + vf.importe_exento) as no_gravado_exento,
                           vf.importe_otros_impuestos as percepciones,
                           vf.importe_total,
                           COALESCE(vf.CAE, '') as cae,
                           'ERP' as origen,
                           vf.tabla_estado_registro_id
                    FROM gestion__ventas_facturas vf
                    LEFT JOIN gestion__comprobantes_tipos ct ON vf.comprobante_tipo_id = ct.comprobante_tipo_id
                    LEFT JOIN gestion__entidades e ON vf.entidad_id = e.entidad_id
                    WHERE vf.empresa_id = ?";
            
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
                           mc.neto_gravado,
                           mc.alicuota_iva,
                           mc.importe_iva,
                           mc.no_gravado_exento,
                           (mc.percepcion_iibb + mc.percepcion_iva) as percepciones,
                           mc.importe_total,
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
            $empresa_erp = intval($cliente['empresa_vinculada_id']);
            $sql = "SELECT fp.factura_proveedor_id as id,
                           fp.f_emision,
                           DATE_FORMAT(fp.f_emision, '%Y%m') as periodo_fiscal,
                           COALESCE(ct.comprobante_tipo, 'Factura Compra') as tipo_nombre,
                           COALESCE(ct.letra, 'A') as letra,
                           fp.comprobante_pv as punto_venta,
                           fp.comprobante_nro as numero,
                           COALESCE(e.cuit, '') as cuit_contraparte,
                           COALESCE(e.entidad_nombre, 'Proveedor') as contraparte,
                           fp.subtotal as neto_gravado,
                           21.00 as alicuota_iva,
                           fp.impuestos as importe_iva,
                           (fp.no_gravado + fp.exento) as no_gravado_exento,
                           fp.otros_impuestos as percepciones,
                           fp.total as importe_total,
                           'ERP' as origen,
                           fp.tabla_estado_registro_id
                    FROM gestion__facturas_proveedores fp
                    LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
                    LEFT JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
                    WHERE fp.empresa_id = ?";
            
            if (!empty($periodo_fiscal)) {
                $sql .= " AND DATE_FORMAT(fp.f_emision, '%Y%m') = ?";
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
                           mc.neto_gravado,
                           mc.alicuota_iva,
                           mc.importe_iva,
                           mc.no_gravado_exento,
                           (mc.percepcion_iibb + mc.percepcion_iva) as percepciones,
                           mc.importe_total,
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
     * Calcula los totales fiscales del período para el cliente
     */
    public function getTotalesFiscales($empresa_id, $cliente_id, $periodo_fiscal = '') {
        $ventas = $this->listarVentas($empresa_id, $cliente_id, $periodo_fiscal);
        $compras = $this->listarCompras($empresa_id, $cliente_id, $periodo_fiscal);

        $tot_ventas_neto = 0.0;
        $tot_iva_debito = 0.0;
        $tot_ventas_total = 0.0;

        foreach ($ventas as $v) {
            $tot_ventas_neto += floatval($v['neto_gravado'] ?? 0);
            $tot_iva_debito += floatval($v['importe_iva'] ?? 0);
            $tot_ventas_total += floatval($v['importe_total'] ?? 0);
        }

        $tot_compras_neto = 0.0;
        $tot_iva_credito = 0.0;
        $tot_percepciones = 0.0;
        $tot_compras_total = 0.0;

        foreach ($compras as $c) {
            $tot_compras_neto += floatval($c['neto_gravado'] ?? 0);
            $tot_iva_credito += floatval($c['importe_iva'] ?? 0);
            $tot_percepciones += floatval($c['percepciones'] ?? 0);
            $tot_compras_total += floatval($c['importe_total'] ?? 0);
        }

        $saldo_tecnico = $tot_iva_debito - $tot_iva_credito;
        $posicion_final = $saldo_tecnico - $tot_percepciones;

        return [
            'ventas_neto' => round($tot_ventas_neto, 2),
            'iva_debito' => round($tot_iva_debito, 2),
            'ventas_total' => round($tot_ventas_total, 2),
            'compras_neto' => round($tot_compras_neto, 2),
            'iva_credito' => round($tot_iva_credito, 2),
            'percepciones' => round($tot_percepciones, 2),
            'compras_total' => round($tot_compras_total, 2),
            'saldo_tecnico' => round($saldo_tecnico, 2),
            'posicion_final' => round($posicion_final, 2),
            'cant_ventas' => count($ventas),
            'cant_compras' => count($compras)
        ];
    }

    /**
     * Guarda un comprobante manual (modo carga mini-gestión)
     */
    public function guardarComprobanteManual($empresa_id, $data) {
        $comprobante_id = intval($data['comprobante_id'] ?? 0);
        $cliente_id = intval($data['cliente_id'] ?? 0);
        $tipo_operacion = ($data['tipo_operacion'] === 'COMPRA') ? 'COMPRA' : 'VENTA';
        $f_emision = trim($data['f_emision'] ?? date('Y-m-d'));
        $periodo_fiscal = intval(str_replace('-', '', substr($f_emision, 0, 7)));
        $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 4);
        $comprobante_letra = strtoupper(trim($data['comprobante_letra'] ?? 'A'));
        $punto_venta = intval($data['punto_venta'] ?? 1);
        $comprobante_nro = intval($data['comprobante_nro'] ?? 1);
        $cuit_contraparte = trim($data['cuit_contraparte'] ?? '');
        $razon_social_contraparte = trim($data['razon_social_contraparte'] ?? '');
        $neto_gravado = floatval($data['neto_gravado'] ?? 0);
        $alicuota_iva = floatval($data['alicuota_iva'] ?? 21.00);
        $importe_iva = floatval($data['importe_iva'] ?? 0);
        $no_gravado_exento = floatval($data['no_gravado_exento'] ?? 0);
        $percepcion_iibb = floatval($data['percepcion_iibb'] ?? 0);
        $percepcion_iva = floatval($data['percepcion_iva'] ?? 0);
        $importe_total = floatval($data['importe_total'] ?? 0);
        $observaciones = trim($data['observaciones'] ?? '');

        if ($cliente_id <= 0 || empty($f_emision)) {
            throw new Exception("Datos obligatorios incompletos (cliente y fecha de emisión).");
        }

        // Si el total no se envió, calcularlo
        if ($importe_total <= 0) {
            $importe_total = $neto_gravado + $importe_iva + $no_gravado_exento + $percepcion_iibb + $percepcion_iva;
        }

        if ($comprobante_id > 0) {
            // Edición
            $sql = "UPDATE estudio__minigestion_comprobantes 
                    SET tipo_operacion = ?, f_emision = ?, periodo_fiscal = ?, comprobante_tipo_id = ?,
                        comprobante_letra = ?, punto_venta = ?, comprobante_nro = ?, cuit_contraparte = ?,
                        razon_social_contraparte = ?, neto_gravado = ?, alicuota_iva = ?, importe_iva = ?,
                        no_gravado_exento = ?, percepcion_iibb = ?, percepcion_iva = ?, importe_total = ?,
                        observaciones = ?
                    WHERE comprobante_id = ? AND empresa_id = ? AND cliente_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            if (!$stmt) throw new Exception("Error al preparar consulta: " . mysqli_error($this->conn));
            mysqli_stmt_bind_param($stmt, "ssiisissdddddddssiii",
                $tipo_operacion, $f_emision, $periodo_fiscal, $comprobante_tipo_id,
                $comprobante_letra, $punto_venta, $comprobante_nro, $cuit_contraparte,
                $razon_social_contraparte, $neto_gravado, $alicuota_iva, $importe_iva,
                $no_gravado_exento, $percepcion_iibb, $percepcion_iva, $importe_total,
                $observaciones, $comprobante_id, $empresa_id, $cliente_id
            );
            $res = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al actualizar comprobante.");
            return $comprobante_id;
        } else {
            // Alta
            $sql = "INSERT INTO estudio__minigestion_comprobantes
                    (empresa_id, cliente_id, tipo_operacion, f_emision, periodo_fiscal,
                     comprobante_tipo_id, comprobante_letra, punto_venta, comprobante_nro,
                     cuit_contraparte, razon_social_contraparte, neto_gravado, alicuota_iva,
                     importe_iva, no_gravado_exento, percepcion_iibb, percepcion_iva,
                     importe_total, observaciones, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            if (!$stmt) throw new Exception("Error al preparar alta: " . mysqli_error($this->conn));
            mysqli_stmt_bind_param($stmt, "iissiisissdddddddds",
                $empresa_id, $cliente_id, $tipo_operacion, $f_emision, $periodo_fiscal,
                $comprobante_tipo_id, $comprobante_letra, $punto_venta, $comprobante_nro,
                $cuit_contraparte, $razon_social_contraparte, $neto_gravado, $alicuota_iva,
                $importe_iva, $no_gravado_exento, $percepcion_iibb, $percepcion_iva,
                $importe_total, $observaciones
            );
            $res = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al insertar comprobante.");
            return $new_id;
        }
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
        return $row;
    }

    /**
     * Anula un comprobante manual
     */
    public function anularComprobanteManual($empresa_id, $comprobante_id) {
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
    public function listarTiposComprobantes() {
        $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra, codigo 
                FROM gestion__comprobantes_tipos 
                WHERE tabla_estado_registro_id = 1 
                ORDER BY letra ASC, comprobante_tipo ASC";
        $res = mysqli_query($this->conn, $sql);
        $list = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
        return $list;
    }
}
