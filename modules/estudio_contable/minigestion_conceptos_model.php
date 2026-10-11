<?php
/**
 * Modelo: Catálogo de Conceptos sin Stock (Bienes vs Servicios)
 * Módulo 4: Estudio Contable (minigestion_conceptos_model.php)
 */

require_once __DIR__ . '/../../db.php';

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
        $alicuota = intval($data['iva_alicuota_id'] ?? 1); // 1 = 21%

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("Código y nombre del concepto son obligatorios.");
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
            $sql = "INSERT INTO gestion__productos
                    (empresa_id, producto_codigo, producto_nombre, es_servicio, controla_stock,
                     cont_cuenta_venta_id, cont_cuenta_compra_id, iva_alicuota_id, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, 0, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "issiiii", $empresa_id, $codigo, $nombre, $es_servicio, $cuenta_venta, $cuenta_compra, $alicuota);
            $res = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al insertar concepto.");
            return $new_id;
        }
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
