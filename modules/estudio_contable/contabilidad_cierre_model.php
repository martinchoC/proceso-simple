<?php
/**
 * Modelo: Cierre de Ejercicio y Refundición Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre_model.php)
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_parametros_model.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/../gestion/tesoreria_core_model.php';

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
     * Ejercicio contable N de la empresa (mes de cierre de Parámetros de Empresa, core/cont_periodos.php) y la
     * lista de ejercicios para elegir.
     */
    public function rangoEjercicio($empresa_id, $anio) {
        $anio = intval($anio) ?: cont_ejercicio_de_fecha($this->conn, intval($empresa_id), date('Y-m-d'));
        $rango = cont_ejercicio_rango($this->conn, intval($empresa_id), $anio);
        $rango['ejercicios'] = cont_ejercicios_lista($this->conn, intval($empresa_id));
        return $rango;
    }

    /**
     * Calcula el balance de sumas y saldos para el período seleccionado: solo asientos registrados (estado 1),
     * igual que Contabilidad > Balances sin borradores. Cuentas de resultado = rubros 4 (ingresos), 5 (costos) y
     * 6 (gastos) del plan de cuentas, el mismo criterio de cont__balances_model.php.
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
                JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id AND c.empresa_id = a.empresa_id
                WHERE a.empresa_id = ?
                  AND a.f_asiento BETWEEN ? AND ?
                  AND a.tabla_estado_registro_id = 1
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
            $saldo = round(floatval($r['saldo_neto']), 2); // >0 deudor, <0 acreedor
            $r['saldo_neto'] = $saldo;

            $tot_debe += $debe;
            $tot_haber += $haber;

            // Cuenta de resultado según el rubro (primer dígito del código), como Balances. El saldo va con signo:
            // un ingreso con saldo deudor resta, un gasto con saldo acreedor también.
            $rubro = substr(trim($r['codigo']), 0, 1);
            $es_resultado = in_array($rubro, ['4', '5', '6'], true);
            $tipo_res = '';

            if ($rubro === '4') {
                $tipo_res = 'INGRESO';
                $tot_ingresos += -$saldo;
            } elseif ($es_resultado) {
                $tipo_res = 'GASTO';
                $tot_gastos += $saldo;
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
     * Ejecuta el cierre contable del ejercicio $anio con refundición de resultados y cierre patrimonial.
     * Las fechas salen del ejercicio de la empresa (mes de cierre de Parámetros de Empresa), no del navegador.
     * Los dos asientos van por el generador común de gestión (tesoreriaGenerarAsiento, sin comprobante): tipo AUT,
     * moneda base, estado 1 (Registrado, el que leen Balances y Mayores), cuentas del plan de la empresa y período
     * contable abierto.
     */
    public function ejecutarCierre($empresa_id, $anio, $usuario_id) {
        $empresa_id = intval($empresa_id);
        $anio = intval($anio);
        if ($anio <= 1900) {
            throw new Exception("Parámetros de cierre de ejercicio inválidos.");
        }
        $rango = cont_ejercicio_rango($this->conn, $empresa_id, $anio);
        $f_inicio = $rango['desde'];
        $f_cierre = $rango['hasta'];
        if ($f_cierre >= date('Y-m-d')) {
            throw new Exception("El {$rango['texto']} todavía no terminó.");
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

        // Los borradores no entran en el balance: hay que confirmarlos o eliminarlos antes de cerrar
        $chk = mysqli_prepare($this->conn, "SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id = ? AND f_asiento BETWEEN ? AND ? AND tabla_estado_registro_id = 3");
        mysqli_stmt_bind_param($chk, "iss", $empresa_id, $f_inicio, $f_cierre);
        mysqli_stmt_execute($chk);
        $borradores = intval(mysqli_fetch_row(mysqli_stmt_get_result($chk))[0] ?? 0);
        mysqli_stmt_close($chk);
        if ($borradores > 0) {
            throw new Exception("Hay {$borradores} asientos en borrador en el ejercicio: confirmalos o eliminalos en Contabilidad > Asientos antes de cerrar.");
        }

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
            throw new Exception("El balance de sumas y saldos está descuadrado (diferencia: " . fmt_moneda($balance['totales']['diferencia_cuadre']) . "). No se puede proceder con el cierre.");
        }

        $cuentas = $balance['cuentas'];
        $resultado_neto = $balance['totales']['resultado_neto'];

        mysqli_begin_transaction($this->conn);

        try {
            // =========================================================================
            // A) ASIENTO DE REFUNDICION DE CUENTAS DE RESULTADO
            // =========================================================================
            // Cada cuenta de resultado se lleva a cero (saldo deudor se acredita, acreedor se debita)
            $lineas_ref = [];
            $sum_debe_ref = 0.0;
            $sum_haber_ref = 0.0;
            foreach ($cuentas as $c) {
                if (!$c['es_resultado'] || abs($c['saldo_neto']) < 0.005) continue;
                $contra_saldo = -$c['saldo_neto'];
                $lineas_ref[] = $this->linea($c['cuenta_id'], $contra_saldo, "Refundición saldo {$c['cuenta_nombre']}");
                if ($contra_saldo > 0) $sum_debe_ref += $contra_saldo;
                else $sum_haber_ref += -$contra_saldo;
            }

            // Contrapartida a la Cuenta Resultado del Ejercicio
            $contra_resultado = round($sum_haber_ref - $sum_debe_ref, 2);
            if (abs($contra_resultado) >= 0.005) {
                $lineas_ref[] = $this->linea($cta_resultado_id, $contra_resultado, "Resultado neto del ejercicio {$anio}");
            }
            $asiento_refundicion_id = $lineas_ref ? $this->generarAsiento($empresa_id, $f_cierre, "Cierre de Ejercicio {$anio} - Refundición de Cuentas de Resultado", $usuario_id, $lineas_ref) : 0;

            // =========================================================================
            // B) ASIENTO DE CIERRE PATRIMONIAL
            // =========================================================================
            // Cuentas patrimoniales (no resultados) + la cuenta de resultado del ejercicio, invertidas para dejarlas en cero
            $lineas_pat = [];
            foreach ($cuentas as $c) {
                if ($c['es_resultado'] || abs($c['saldo_neto']) < 0.005) continue;
                $lineas_pat[] = $this->linea($c['cuenta_id'], -$c['saldo_neto'], "Cierre patrimonial {$c['cuenta_nombre']}");
            }
            if (abs($contra_resultado) >= 0.005) {
                $lineas_pat[] = $this->linea($cta_resultado_id, -$contra_resultado, "Cierre patrimonial Resultado del Ejercicio");
            }
            $asiento_patrimonial_id = $lineas_pat ? $this->generarAsiento($empresa_id, $f_cierre, "Cierre de Ejercicio {$anio} - Cierre Patrimonial de Cuentas", $usuario_id, $lineas_pat) : 0;

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
            mysqli_stmt_bind_param($stmt_cr, "iissiidi",
                $empresa_id, $anio, $f_inicio, $f_cierre, $asiento_refundicion_id,
                $asiento_patrimonial_id, $resultado_neto, $usuario_id
            );
            mysqli_stmt_execute($stmt_cr);
            $cierre_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt_cr);

            mysqli_commit($this->conn);
            return [
                'cierre_id' => $cierre_id,
                'f_inicio' => $f_inicio,
                'f_cierre' => $f_cierre,
                'asiento_refundicion_id' => $asiento_refundicion_id,
                'asiento_patrimonial_id' => $asiento_patrimonial_id,
                'resultado_neto' => $resultado_neto
            ];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /** Renglón para tesoreriaGenerarAsiento: importe con signo (> 0 Debe, < 0 Haber). */
    private function linea($cuenta_id, $importe_local, $descripcion) {
        $importe_local = round(floatval($importe_local), 2);
        return [
            'cuenta_id' => intval($cuenta_id),
            'debe' => $importe_local > 0 ? $importe_local : 0,
            'haber' => $importe_local < 0 ? -$importe_local : 0,
            'descripcion' => $descripcion
        ];
    }

    private function generarAsiento($empresa_id, $f_asiento, $descripcion, $usuario_id, array $lineas) {
        return tesoreriaGenerarAsiento($this->conn, [
            'empresa_id' => $empresa_id,
            'sin_comprobante' => true,
            'f_asiento' => $f_asiento,
            'descripcion' => $descripcion,
            'usuario_id' => $usuario_id
        ], $lineas);
    }
}
