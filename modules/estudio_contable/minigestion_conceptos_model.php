<?php
/**
 * Modelo: Catálogo de Conceptos sin Stock (Bienes vs Servicios)
 * Módulo 4: Estudio Contable (minigestion_conceptos_model.php)
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';

class MiniGestionConceptosModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function listarConceptos($empresa_id) {
        $sql = "SELECT p.producto_id, p.empresa_id, p.producto_codigo, p.producto_nombre,
                       p.es_servicio, p.controla_stock, p.iva_alicuota_id,
                       p.cont_cuenta_venta_id, c_v.nombre as cuenta_venta_nombre, c_v.codigo as cuenta_venta_codigo,
                       p.cont_cuenta_compra_id, c_c.nombre as cuenta_compra_nombre, c_c.codigo as cuenta_compra_codigo,
                       p.tabla_estado_registro_id
                FROM gestion__productos p
                LEFT JOIN gestion__cont_cuentas c_v ON p.cont_cuenta_venta_id = c_v.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_c ON p.cont_cuenta_compra_id = c_c.cont_cuenta_id
                WHERE p.empresa_id = ? AND p.controla_stock = 0 AND p.tabla_estado_registro_id = 1
                ORDER BY p.producto_codigo ASC";
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

    public function guardarConcepto($empresa_id, $data) {
        $id = intval($data['producto_id'] ?? 0);
        $codigo = trim($data['producto_codigo'] ?? '');
        $nombre = trim($data['producto_nombre'] ?? '');
        $es_servicio = intval($data['es_servicio'] ?? 1);
        $cuenta_venta = intval($data['cont_cuenta_venta_id'] ?? 0);
        $cuenta_compra = intval($data['cont_cuenta_compra_id'] ?? 0);
        $alicuota = intval($data['iva_alicuota_id'] ?? 0);

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("Código y nombre del concepto son obligatorios.");
        }
        // Las alícuotas son por empresa: sin elegir, la del 21% de la empresa; elegida, tiene que ser suya
        if ($alicuota <= 0) {
            $alicuota = emp_iva_alicuota_id($this->conn, $empresa_id, 21.0);
        } elseif (!$this->alicuotaDeEmpresa($empresa_id, $alicuota)) {
            throw new Exception("La alícuota de IVA elegida no es de esta empresa.");
        }

        if ($id > 0) {
            $sql = "UPDATE gestion__productos 
                    SET producto_codigo = ?, producto_nombre = ?, es_servicio = ?,
                        cont_cuenta_venta_id = ?, cont_cuenta_compra_id = ?, iva_alicuota_id = ?, controla_stock = 0
                    WHERE producto_id = ? AND empresa_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssiiiiii", $codigo, $nombre, $es_servicio, $cuenta_venta, $cuenta_compra, $alicuota, $id, $empresa_id);
            $res = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al actualizar concepto.");
            return $id;
        } else {
            // Tipo de producto de la empresa que no maneja stock (la columna tiene default 1, de otra empresa)
            $producto_tipo_id = emp_producto_tipo_id($this->conn, $empresa_id, false);
            $sql = "INSERT INTO gestion__productos
                    (empresa_id, producto_codigo, producto_nombre, producto_tipo_id, es_servicio, controla_stock,
                     cont_cuenta_venta_id, cont_cuenta_compra_id, iva_alicuota_id, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "issiiiii", $empresa_id, $codigo, $nombre, $producto_tipo_id, $es_servicio, $cuenta_venta, $cuenta_compra, $alicuota);
            $res = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al insertar concepto.");
            return $new_id;
        }
    }

    /** Alícuotas de IVA activas de la empresa (catálogo por empresa: nunca ids fijos). */
    public function listarAlicuotas($empresa_id) {
        $sql = "SELECT iva_alicuota_id, iva_alicuota, porcentaje, es_gravado
                FROM gestion__impuestos__iva_alicuotas
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY es_gravado DESC, porcentaje DESC, iva_alicuota ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $list;
    }

    private function alicuotaDeEmpresa($empresa_id, $iva_alicuota_id) {
        foreach ($this->listarAlicuotas($empresa_id) as $a) {
            if (intval($a['iva_alicuota_id']) === intval($iva_alicuota_id)) return true;
        }
        return false;
    }

    public function anularConcepto($empresa_id, $id) {
        $sql = "UPDATE gestion__productos SET tabla_estado_registro_id = 2 WHERE producto_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }
}
