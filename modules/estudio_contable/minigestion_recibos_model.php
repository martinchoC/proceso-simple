<?php
/**
 * Modelo: Recibos y Cobranzas con Retenciones Sufridas
 * Módulo 4: Estudio Contable (minigestion_recibos_model.php)
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_parametros_model.php';

class MiniGestionRecibosModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function listarRecibos($empresa_id) {
        $sql = "SELECT c.cobranza_id, c.empresa_id, c.f_emision, c.comprobante_pv, c.comprobante_nro,
                       c.importe_total, e.entidad_nombre as cliente_nombre, e.cuit as cliente_cuit,
                       c.tabla_estado_registro_id
                FROM gestion__cobranzas c
                LEFT JOIN gestion__entidades e ON c.entidad_id = e.entidad_id
                WHERE c.empresa_id = ?
                ORDER BY c.f_emision DESC, c.cobranza_id DESC";
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

    public function emitirRecibo($empresa_id, $data, $usuario_id) {
        $entidad_id = intval($data['entidad_id'] ?? 0);
        $f_emision = trim($data['f_emision'] ?? date('Y-m-d'));
        $punto_venta = intval($data['comprobante_pv'] ?? 1);
        $importe_total = floatval($data['importe_total'] ?? 0);
        $medio_pago = trim($data['medio_pago'] ?? 'EFECTIVO'); // EFECTIVO, BANCO
        $observaciones = trim($data['observaciones'] ?? '');

        // Retenciones sufridas recibidas en el recibo
        $ret_ganancias = floatval($data['ret_ganancias_importe'] ?? 0);
        $ret_ganancias_nro = trim($data['ret_ganancias_nro'] ?? '');
        $ret_iva = floatval($data['ret_iva_importe'] ?? 0);
        $ret_iva_nro = trim($data['ret_iva_nro'] ?? '');
        $ret_iibb = floatval($data['ret_iibb_importe'] ?? 0);
        $ret_iibb_nro = trim($data['ret_iibb_nro'] ?? '');
        $ret_suss = floatval($data['ret_suss_importe'] ?? 0);
        $ret_suss_nro = trim($data['ret_suss_nro'] ?? '');

        $total_retenciones = $ret_ganancias + $ret_iva + $ret_iibb + $ret_suss;
        $importe_neto_cobrado = $importe_total - $total_retenciones;

        if ($entidad_id <= 0 || $importe_total <= 0) {
            throw new Exception("Cliente e importe total de cobranza son obligatorios.");
        }
        if ($importe_neto_cobrado < 0) {
            throw new Exception("El total de retenciones sufridas no puede superar el total del recibo.");
        }

        // Obtener último número de recibo
        $q_nro = mysqli_query($this->conn, "SELECT COALESCE(MAX(comprobante_nro), 0) + 1 FROM gestion__cobranzas WHERE empresa_id = {$empresa_id} AND comprobante_pv = {$punto_venta}");
        $r_nro = mysqli_fetch_row($q_nro);
        $comprobante_nro = intval($r_nro[0]);

        mysqli_begin_transaction($this->conn);

        try {
            // 1. Insertar en gestion__cobranzas
            $sql_c = "INSERT INTO gestion__cobranzas
                      (empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                       entidad_id, f_emision, f_contabilidad, moneda_id, tipo_cambio, importe_total,
                       observaciones, tabla_estado_registro_id, usuario_id)
                      VALUES (?, 1, 15, ?, ?, ?, ?, ?, 1, 1.000000, ?, ?, 1, ?)";
            $st_c = mysqli_prepare($this->conn, $sql_c);
            mysqli_stmt_bind_param($st_c, "iiiissdsi",
                $empresa_id, $punto_venta, $comprobante_nro, $entidad_id, $f_emision, $f_emision,
                $importe_total, $observaciones, $usuario_id
            );
            mysqli_stmt_execute($st_c);
            $cobranza_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($st_c);

            // 2. Registrar certificados de retención en gestion__cobranzas_retenciones
            $retenciones_items = [
                ['GANANCIAS', $ret_ganancias, $ret_ganancias_nro],
                ['IVA', $ret_iva, $ret_iva_nro],
                ['IIBB', $ret_iibb, $ret_iibb_nro],
                ['SUSS', $ret_suss, $ret_suss_nro]
            ];

            foreach ($retenciones_items as $item) {
                if ($item[1] > 0) {
                    $sql_ret = "INSERT INTO gestion__cobranzas_retenciones
                                (empresa_id, comprobante_id, tipo_retencion, nro_certificado, f_retencion, importe_base, alicuota, importe_retenido, cuenta_contable_id, tabla_estado_registro_id)
                                VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 1, 1)";
                    $st_ret = mysqli_prepare($this->conn, $sql_ret);
                    mysqli_stmt_bind_param($st_ret, "iisssdd",
                        $empresa_id, $cobranza_id, $item[0], $item[2], $f_emision, $importe_total, $item[1]
                    );
                    mysqli_stmt_execute($st_ret);
                    mysqli_stmt_close($st_ret);
                }
            }

            // 3. Generar Asiento Contable Automático en gestion__cont_asientos
            $paramModel = new ContabilidadParametrosModel($this->conn);
            $params = $paramModel->getParametros($empresa_id);

            if ($params && !empty($params['cuenta_deudores_ventas_id'])) {
                $cta_deudores = intval($params['cuenta_deudores_ventas_id']);
                $cta_caja = ($medio_pago === 'BANCO') ? intval($params['cuenta_banco_defecto_id'] ?? $params['cuenta_caja_defecto_id']) : intval($params['cuenta_caja_defecto_id']);
                if ($cta_caja <= 0) $cta_caja = $cta_deudores;

                $desc_asiento = "Cobranza Recibo {$punto_venta}-{$comprobante_nro}";
                $anio = intval(substr($f_emision, 0, 4));
                $mes = intval(substr($f_emision, 5, 2));

                $sql_as = "INSERT INTO gestion__cont_asientos
                           (empresa_id, sucursal_id, comprobante_id, f_asiento, anio, mes, descripcion, usuario_creacion_id, tabla_estado_registro_id)
                           VALUES (?, 1, ?, ?, ?, ?, ?, ?, 1)";
                $st_as = mysqli_prepare($this->conn, $sql_as);
                mysqli_stmt_bind_param($st_as, "iisisi", $empresa_id, $cobranza_id, $f_emision, $anio, $mes, $desc_asiento, $usuario_id);
                mysqli_stmt_execute($st_as);
                $asiento_id = mysqli_insert_id($this->conn);
                mysqli_stmt_close($st_as);

                // Debe: Fondos cobrados en efectivo/banco
                if ($importe_neto_cobrado > 0) {
                    $sql_d1 = "INSERT INTO gestion__cont_asientos_detalles (cont_asiento_id, cuenta_id, tipo, importe_local, descripcion) VALUES (?, ?, 'A', ?, 'Cobro disponible')";
                    $st_d1 = mysqli_prepare($this->conn, $sql_d1);
                    mysqli_stmt_bind_param($st_d1, "iid", $asiento_id, $cta_caja, $importe_neto_cobrado);
                    mysqli_stmt_execute($st_d1);
                    mysqli_stmt_close($st_d1);
                }

                // Debe: Retenciones sufridas (Créditos impositivos)
                if ($total_retenciones > 0) {
                    $cta_credito = intval($params['cuenta_iva_credito_id'] ?? $cta_caja);
                    $sql_d2 = "INSERT INTO gestion__cont_asientos_detalles (cont_asiento_id, cuenta_id, tipo, importe_local, descripcion) VALUES (?, ?, 'A', ?, 'Retenciones sufridas')";
                    $st_d2 = mysqli_prepare($this->conn, $sql_d2);
                    mysqli_stmt_bind_param($st_d2, "iid", $asiento_id, $cta_credito, $total_retenciones);
                    mysqli_stmt_execute($st_d2);
                    mysqli_stmt_close($st_d2);
                }

                // Haber: Cancelación de Deudores por Ventas
                $haber_deudores = -$importe_total;
                $sql_h = "INSERT INTO gestion__cont_asientos_detalles (cont_asiento_id, cuenta_id, tipo, importe_local, descripcion) VALUES (?, ?, 'A', ?, 'Cancelación cuenta cliente')";
                $st_h = mysqli_prepare($this->conn, $sql_h);
                mysqli_stmt_bind_param($st_h, "iid", $asiento_id, $cta_deudores, $haber_deudores);
                mysqli_stmt_execute($st_h);
                mysqli_stmt_close($st_h);
            }

            mysqli_commit($this->conn);
            return [
                'cobranza_id' => $cobranza_id,
                'comprobante_nro' => $comprobante_nro,
                'importe_total' => $importe_total,
                'total_retenciones' => $total_retenciones
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }
}
