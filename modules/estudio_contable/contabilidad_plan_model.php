<?php
/**
 * Modelo: Plan de Cuentas Jerárquico
 * Módulo 4: Estudio Contable (contabilidad_plan_model.php)
 */

require_once __DIR__ . '/../../db.php';

class ContabilidadPlanModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function listarCuentas($empresa_id) {
        $sql = "SELECT c.*,
                       p.nombre as cuenta_padre_nombre,
                       p.codigo as cuenta_padre_codigo
                FROM gestion__cont_cuentas c
                LEFT JOIN gestion__cont_cuentas p ON c.cuenta_padre_id = p.cont_cuenta_id
                WHERE c.empresa_id = ? AND c.tabla_estado_registro_id = 1
                ORDER BY c.codigo ASC";
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

    public function guardarCuenta($empresa_id, $data) {
        $id = intval($data['cont_cuenta_id'] ?? 0);
        $codigo = trim($data['codigo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $naturaleza = trim($data['naturaleza'] ?? 'ACTIVO');
        $padre_id = intval($data['cuenta_padre_id'] ?? 0);
        $es_imputable = intval($data['es_imputable'] ?? 1);

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("El código y nombre de la cuenta son obligatorios.");
        }

        // Calcular nivel según la cantidad de puntos en el código (ej. 1 -> nivel 1, 1.1 -> nivel 2, 1.1.01 -> nivel 3, etc.)
        $nivel = substr_count($codigo, '.') + 1;

        if ($id > 0) {
            $sql = "UPDATE gestion__cont_cuentas
                    SET codigo = ?, nombre = ?, naturaleza = ?, cuenta_padre_id = ?, nivel = ?, es_imputable = ?
                    WHERE cont_cuenta_id = ? AND empresa_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssiiiii", $codigo, $nombre, $naturaleza, $padre_id, $nivel, $es_imputable, $id, $empresa_id);
            $res = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al actualizar la cuenta contable.");
            return $id;
        } else {
            $sql = "INSERT INTO gestion__cont_cuentas
                    (empresa_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, es_imputable, tabla_estado_registro_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssiii", $empresa_id, $codigo, $nombre, $naturaleza, $padre_id, $nivel, $es_imputable);
            $res = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            if (!$res) throw new Exception("Error al insertar la cuenta contable.");
            return $new_id;
        }
    }

    public function anularCuenta($empresa_id, $id) {
        // Verificar que no tenga movimientos en asientos contables
        $chk = mysqli_query($this->conn, "SELECT COUNT(*) FROM gestion__cont_asientos_detalles WHERE cuenta_id = {$id}");
        $r = mysqli_fetch_row($chk);
        if ($r && $r[0] > 0) {
            throw new Exception("No se puede eliminar la cuenta porque posee {$r[0]} movimientos en asientos contables.");
        }

        $sql = "UPDATE gestion__cont_cuentas SET tabla_estado_registro_id = 2 WHERE cont_cuenta_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }
}
