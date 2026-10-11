<?php
/**
 * Modelo: Parámetros Contables de Enlace
 * Módulo 4: Estudio Contable (contabilidad_parametros_model.php)
 */

require_once __DIR__ . '/../../db.php';

class ContabilidadParametrosModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    /**
     * Obtiene los parámetros contables de la empresa activa
     */
    public function getParametros($empresa_id) {
        $sql = "SELECT p.*,
                       c_dv.nombre as cuenta_deudores_ventas_nombre,
                       c_vd.nombre as cuenta_ventas_defecto_nombre,
                       c_id.nombre as cuenta_iva_debito_nombre,
                       c_pr.nombre as cuenta_proveedores_nombre,
                       c_gd.nombre as cuenta_gastos_defecto_nombre,
                       c_ic.nombre as cuenta_iva_credito_nombre,
                       c_cj.nombre as cuenta_caja_defecto_nombre,
                       c_bc.nombre as cuenta_banco_defecto_nombre,
                       c_re.nombre as cuenta_resultado_ejercicio_nombre,
                       c_ra.nombre as cuenta_resultados_acumulados_nombre
                FROM gestion__cont_parametros p
                LEFT JOIN gestion__cont_cuentas c_dv ON p.cuenta_deudores_ventas_id = c_dv.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_vd ON p.cuenta_ventas_defecto_id = c_vd.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_id ON p.cuenta_iva_debito_id = c_id.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_pr ON p.cuenta_proveedores_id = c_pr.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_gd ON p.cuenta_gastos_defecto_id = c_gd.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_ic ON p.cuenta_iva_credito_id = c_ic.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_cj ON p.cuenta_caja_defecto_id = c_cj.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_bc ON p.cuenta_banco_defecto_id = c_bc.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_re ON p.cuenta_resultado_ejercicio_id = c_re.cont_cuenta_id
                LEFT JOIN gestion__cont_cuentas c_ra ON p.cuenta_resultados_acumulados_id = c_ra.cont_cuenta_id
                WHERE p.empresa_id = ?
                LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return $row;
    }

    /**
     * Guarda o actualiza los parámetros contables
     */
    public function guardarParametros($empresa_id, $data) {
        $dv = intval($data['cuenta_deudores_ventas_id'] ?? 0);
        $vd = intval($data['cuenta_ventas_defecto_id'] ?? 0);
        $id = intval($data['cuenta_iva_debito_id'] ?? 0);
        $pr = intval($data['cuenta_proveedores_id'] ?? 0);
        $gd = intval($data['cuenta_gastos_defecto_id'] ?? 0);
        $ic = intval($data['cuenta_iva_credito_id'] ?? 0);
        $cj = intval($data['cuenta_caja_defecto_id'] ?? 0);
        $bc = intval($data['cuenta_banco_defecto_id'] ?? 0);
        $re = intval($data['cuenta_resultado_ejercicio_id'] ?? 0);
        $ra = intval($data['cuenta_resultados_acumulados_id'] ?? 0);

        $sql = "INSERT INTO gestion__cont_parametros
                (empresa_id, cuenta_deudores_ventas_id, cuenta_ventas_defecto_id, cuenta_iva_debito_id,
                 cuenta_proveedores_id, cuenta_gastos_defecto_id, cuenta_iva_credito_id,
                 cuenta_caja_defecto_id, cuenta_banco_defecto_id, cuenta_resultado_ejercicio_id,
                 cuenta_resultados_acumulados_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    cuenta_deudores_ventas_id = VALUES(cuenta_deudores_ventas_id),
                    cuenta_ventas_defecto_id = VALUES(cuenta_ventas_defecto_id),
                    cuenta_iva_debito_id = VALUES(cuenta_iva_debito_id),
                    cuenta_proveedores_id = VALUES(cuenta_proveedores_id),
                    cuenta_gastos_defecto_id = VALUES(cuenta_gastos_defecto_id),
                    cuenta_iva_credito_id = VALUES(cuenta_iva_credito_id),
                    cuenta_caja_defecto_id = VALUES(cuenta_caja_defecto_id),
                    cuenta_banco_defecto_id = VALUES(cuenta_banco_defecto_id),
                    cuenta_resultado_ejercicio_id = VALUES(cuenta_resultado_ejercicio_id),
                    cuenta_resultados_acumulados_id = VALUES(cuenta_resultados_acumulados_id)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) throw new Exception("Error al preparar consulta: " . mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, "iiiiiiiiiii",
            $empresa_id, $dv, $vd, $id, $pr, $gd, $ic, $cj, $bc, $re, $ra
        );
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$res) throw new Exception("Error al guardar los parámetros contables.");
        return true;
    }

    /**
     * Lista cuentas contables imputables de la empresa para poblar selects
     */
    public function listarCuentasImputables($empresa_id) {
        $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza
                FROM gestion__cont_cuentas
                WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
                ORDER BY codigo ASC";
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
}
