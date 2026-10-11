<?php
/**
 * Modelo: Cierre de Ejercicio y Refundición Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre_model.php)
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_parametros_model.php';

class ContabilidadCierreModel {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    /**
     * Lista los cierres anuales registrados para la empresa
     */
    public function listarCierres($empresa_id) {
        $sql = "SELECT c.*,
                       u.usuario as usuario_cierre_nombre
                FROM gestion__cont_cierres_ejercicios c
                LEFT JOIN conf__usuarios u ON c.usuario_cierre_id = u.usuario_id
                WHERE c.empresa_id = ?
                ORDER BY c.anio_ejercicio DESC";
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
     * Calcula el balance de sumas y saldos para el período seleccionado
     */
    public function calcularBalanceComprobacion($empresa_id, $f_inicio, $f_cierre) {
        // Consultar suma de débitos y créditos agrupados por cuenta
        $sql = "SELECT d.cuenta_id,
                       c.codigo,
                       c.nombre as cuenta_nombre,
                       c.naturaleza,
                       SUM(CASE WHEN d.importe_local > 0 THEN d.importe_local ELSE 0 END) as total_debe,
                       SUM(CASE WHEN d.importe_local < 0 THEN ABS(d.importe_local) ELSE 0 END) as total_haber,
                       SUM(d.importe_local) as saldo_neto
                FROM gestion__cont_asientos_detalles d
                JOIN gestion__cont_asientos a ON d.cont_asiento_id = a.cont_asiento_id
                JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id
                WHERE a.empresa_id = ? 
                  AND a.f_asiento BETWEEN ? AND ?
                  AND a.tabla_estado_registro_id != 2
                GROUP BY d.cuenta_id, c.codigo, c.nombre, c.naturaleza
                ORDER BY c.codigo ASC";

        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) return ['cuentas' => [], 'totales' => []];
        mysqli_stmt_bind_param($stmt, "iss", $empresa_id, $f_inicio, $f_cierre);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $cuentas = [];
        $tot_debe = 0.0;
        $tot_haber = 0.0;
        $tot_ingresos = 0.0;
        $tot_gastos = 0.0;

        while ($r = mysqli_fetch_assoc($res)) {
            $debe = floatval($r['total_debe']);
            $haber = floatval($r['total_haber']);
            $saldo = floatval($r['saldo_neto']); // >0 deudor, <0 acreedor

            $tot_debe += $debe;
            $tot_haber += $haber;

            // Determinar si es cuenta de resultado
            $cod = trim($r['codigo']);
            $nat = strtoupper(trim($r['naturaleza']));
            $es_resultado = false;
            $tipo_res = '';

            if (strpos($nat, 'INGR') !== false || strpos($nat, 'GAN') !== false || substr($cod, 0, 1) === '4') {
                $es_resultado = true;
                $tipo_res = 'INGRESO';
                $tot_ingresos += abs($saldo);
            } elseif (strpos($nat, 'EGRE') !== false || strpos($nat, 'GAS') !== false || strpos($nat, 'PERD') !== false || substr($cod, 0, 1) === '5') {
                $es_resultado = true;
                $tipo_res = 'GASTO';
                $tot_gastos += abs($saldo);
            }

            $r['es_resultado'] = $es_resultado;
            $r['tipo_res'] = $tipo_res;
            $cuentas[] = $r;
        }
        mysqli_stmt_close($stmt);

        $cuadre = round($tot_debe - $tot_haber, 2);
        $resultado_ejercicio = round($tot_ingresos - $tot_gastos, 2);

        return [
            'cuentas' => $cuentas,
            'totales' => [
                'total_debe' => round($tot_debe, 2),
                'total_haber' => round($tot_haber, 2),
                'diferencia_cuadre' => $cuadre,
                'esta_cuadrado' => (abs($cuadre) < 0.01),
                'total_ingresos' => round($tot_ingresos, 2),
                'total_gastos' => round($tot_gastos, 2),
                'resultado_neto' => $resultado_ejercicio
            ]
        ];
    }

    /**
     * Ejecuta el cierre contable con refundición de resultados y cierre patrimonial
     */
    public function ejecutarCierre($empresa_id, $anio, $f_inicio, $f_cierre, $usuario_id) {
        $anio = intval($anio);
        if ($anio <= 1900 || empty($f_inicio) || empty($f_cierre)) {
            throw new Exception("Parámetros de cierre de ejercicio inválidos.");
        }

        // 1. Verificar si ya existe cierre confirmado para este año
        $chk = mysqli_prepare($this->conn, "SELECT cierre_id FROM gestion__cont_cierres_ejercicios WHERE empresa_id = ? AND anio_ejercicio = ? AND tabla_estado_registro_id = 5");
        mysqli_stmt_bind_param($chk, "ii", $empresa_id, $anio);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            mysqli_stmt_close($chk);
            throw new Exception("El ejercicio {$anio} ya se encuentra cerrado.");
        }
        mysqli_stmt_close($chk);

        // 2. Obtener parámetros contables para la cuenta de resultado del ejercicio
        $paramModel = new ContabilidadParametrosModel($this->conn);
        $params = $paramModel->getParametros($empresa_id);
        if (!$params || empty($params['cuenta_resultado_ejercicio_id'])) {
            throw new Exception("Debe configurar la 'Cuenta Resultado del Ejercicio' en los Parámetros Contables antes de cerrar.");
        }
        $cta_resultado_id = intval($params['cuenta_resultado_ejercicio_id']);

        // 3. Calcular balance de comprobación
        $balance = $this->calcularBalanceComprobacion($empresa_id, $f_inicio, $f_cierre);
        if (!$balance['totales']['esta_cuadrado']) {
            throw new Exception("El balance de sumas y saldos está descuadrado (Diferencia: \${$balance['totales']['diferencia_cuadre']}). No se puede proceder con el cierre.");
        }

        $cuentas = $balance['cuentas'];
        $resultado_neto = $balance['totales']['resultado_neto'];

        mysqli_begin_transaction($this->conn);

        try {
            // =========================================================================
            // A) ASIENTO DE REFUNDICION DE CUENTAS DE RESULTADO
            // =========================================================================
            $desc_ref = "Cierre de Ejercicio {$anio} - Refundición de Cuentas de Resultado";
            $sql_as = "INSERT INTO gestion__cont_asientos 
                       (empresa_id, sucursal_id, f_asiento, anio, mes, descripcion, usuario_creacion_id, tabla_estado_registro_id)
                       VALUES (?, 1, ?, ?, 12, ?, ?, 5)";
            $stmt_as = mysqli_prepare($this->conn, $sql_as);
            mysqli_stmt_bind_param($stmt_as, "isisi", $empresa_id, $f_cierre, $anio, $desc_ref, $usuario_id);
            mysqli_stmt_execute($stmt_as);
            $asiento_refundicion_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt_as);

            $sum_debe_ref = 0.0;
            $sum_haber_ref = 0.0;

            // Refundir cada cuenta de resultado a cero
            foreach ($cuentas as $c) {
                if ($c['es_resultado']) {
                    $saldo = floatval($c['saldo_neto']);
                    if (abs($saldo) < 0.001) continue;

                    // Si tenía saldo deudor (>0), se acredita para cancelar (<0)
                    // Si tenía saldo acreedor (<0), se debita para cancelar (>0)
                    $contra_saldo = -$saldo;
                    $this->insertarDetalleAsiento($asiento_refundicion_id, $c['cuenta_id'], $contra_saldo, "Refundición saldo {$c['cuenta_nombre']}");

                    if ($contra_saldo > 0) $sum_debe_ref += $contra_saldo;
                    else $sum_haber_ref += abs($contra_saldo);
                }
            }

            // Contrapartida a la Cuenta Resultado del Ejercicio
            $contra_resultado = round($sum_haber_ref - $sum_debe_ref, 2);
            if (abs($contra_resultado) > 0.001) {
                $this->insertarDetalleAsiento($asiento_refundicion_id, $cta_resultado_id, $contra_resultado, "Resultado neto del ejercicio {$anio}");
            }

            // =========================================================================
            // B) ASIENTO DE CIERRE PATRIMONIAL
            // =========================================================================
            $desc_pat = "Cierre de Ejercicio {$anio} - Cierre Patrimonial de Cuentas";
            $stmt_as2 = mysqli_prepare($this->conn, $sql_as);
            mysqli_stmt_bind_param($stmt_as2, "isisi", $empresa_id, $f_cierre, $anio, $desc_pat, $usuario_id);
            mysqli_stmt_execute($stmt_as2);
            $asiento_patrimonial_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt_as2);

            // Cierre de cuentas patrimoniales (no resultados) + la cuenta de resultado del ejercicio
            foreach ($cuentas as $c) {
                if (!$c['es_resultado']) {
                    $saldo = floatval($c['saldo_neto']);
                    if (abs($saldo) < 0.001) continue;
                    // Invertir saldo para dejar en cero
                    $this->insertarDetalleAsiento($asiento_patrimonial_id, $c['cuenta_id'], -$saldo, "Cierre patrimonial {$c['cuenta_nombre']}");
                }
            }
            // Incluir el resultado del ejercicio en el cierre patrimonial
            if (abs($contra_resultado) > 0.001) {
                $this->insertarDetalleAsiento($asiento_patrimonial_id, $cta_resultado_id, -$contra_resultado, "Cierre patrimonial Resultado del Ejercicio");
            }

            // =========================================================================
            // C) REGISTRO EN gestion__cont_cierres_ejercicios
            // =========================================================================
            $sql_cierre = "INSERT INTO gestion__cont_cierres_ejercicios
                           (empresa_id, anio_ejercicio, f_inicio, f_cierre, asiento_refundicion_id,
                            asiento_cierre_patrimonial_id, resultado_neto_ejercicio, usuario_cierre_id, tabla_estado_registro_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 5)
                           ON DUPLICATE KEY UPDATE
                               f_inicio = VALUES(f_inicio),
                               f_cierre = VALUES(f_cierre),
                               asiento_refundicion_id = VALUES(asiento_refundicion_id),
                               asiento_cierre_patrimonial_id = VALUES(asiento_cierre_patrimonial_id),
                               resultado_neto_ejercicio = VALUES(resultado_neto_ejercicio),
                               usuario_cierre_id = VALUES(usuario_cierre_id),
                               tabla_estado_registro_id = 5";

            $stmt_cr = mysqli_prepare($this->conn, $sql_cierre);
            mysqli_stmt_bind_param($stmt_cr, "iissiddi",
                $empresa_id, $anio, $f_inicio, $f_cierre, $asiento_refundicion_id,
                $asiento_patrimonial_id, $resultado_neto, $usuario_id
            );
            mysqli_stmt_execute($stmt_cr);
            $cierre_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt_cr);

            mysqli_commit($this->conn);
            return [
                'cierre_id' => $cierre_id,
                'asiento_refundicion_id' => $asiento_refundicion_id,
                'asiento_patrimonial_id' => $asiento_patrimonial_id,
                'resultado_neto' => $resultado_neto
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    private function insertarDetalleAsiento($asiento_id, $cuenta_id, $importe_local, $descripcion) {
        $tipo = 'A'; // Automático
        $sql = "INSERT INTO gestion__cont_asientos_detalles
                (cont_asiento_id, cuenta_id, tipo, importe_local, descripcion)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iisds", $asiento_id, $cuenta_id, $tipo, $importe_local, $descripcion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
