<?php
/**
 * core/cont_periodos.php — cierre de períodos contables (meses del ejercicio) y su bloqueo.
 * Scripts: _local/sql/mantenimiento/20261028_cont_periodos_cierres_01_estructura.sql y 20261030_..._02_mes_cierre_ejercicio.sql
 *
 * Ejercicio (año contable): sale del mes de cierre de Parámetros de Empresa (gestion__empresas_parametros.mes_cierre_ejercicio,
 * con vigencia; 12 por defecto). El ejercicio N termina el último día del mes de cierre del año N y empieza el día
 * siguiente al cierre del N-1 (cont_ejercicio_rango). Los cierres son por mes calendario (anio + mes): el ejercicio
 * solo agrupa los meses en la pantalla y en "Cerrar todo el ejercicio".
 *
 * Un cierre (gestion__cont_periodos_cierres, estado 5) bloquea un mes de la contabilidad de la empresa: mientras
 * esté cerrado no se crea, modifica, confirma, anula ni elimina ningún asiento con f_asiento en ese mes. Se reabre
 * con motivo (38: la fila queda como historial) y volver a cerrar crea otra fila. Independiente de los cierres
 * fiscales (Control Fiscal, periodos_fiscales_core_model.php).
 *
 * Todo lo que escribe gestion__cont_asientos llama antes a uno de estos controles (lanzan RuntimeException):
 *   cont_periodo_exigir_abierto($conexion, $empresa_id, $fecha, $que)                 asiento nuevo o fecha nueva
 *   cont_periodo_exigir_asiento($conexion, $empresa_id, $cont_asiento_id, $que)       un asiento existente
 *   cont_periodo_exigir_comprobante($conexion, $empresa_id, $comprobante_id, $que)    los asientos de un comprobante
 * $que completa el mensaje: "No se puede {$que}: ...". Si la tabla no existe (script sin correr) no bloquean.
 * Lo usan gestion y gestion_normal (cont_asientos, ventas_facturas, facturas_proveedores, tesorería, cobranzas,
 * pagos, IVA, retenciones) y estudio_contable (cierre de ejercicio, amortizaciones, recibos de mini-gestión).
 */

const CONT_PERIODO_TABLA = 448;
const CONT_PERIODO_CERRADO = 5;
const CONT_PERIODO_REABIERTO = 38;
const CONT_PERIODO_MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
                            'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function cont_periodo_filas($conexion, string $sql, string $tipos = '', ...$params): array
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new RuntimeException('Períodos contables: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** La tabla existe (los controles no fallan si el script no corrió). */
function cont_periodo_disponible($conexion): bool
{
    static $ok = null;
    if ($ok === null) {
        $r = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__cont_periodos_cierres'");
        $ok = $r && mysqli_num_rows($r) > 0;
    }
    return $ok;
}

function cont_periodo_texto(int $anio, int $mes): string
{
    return (CONT_PERIODO_MESES[$mes] ?? $mes) . ' ' . $anio;
}

/** Cierre activo del mes, o null. */
function cont_periodo_cierre_activo($conexion, int $empresa_id, int $anio, int $mes, bool $bloquear = false): ?array
{
    if (!cont_periodo_disponible($conexion)) return null;
    $rows = cont_periodo_filas($conexion,
        "SELECT * FROM gestion__cont_periodos_cierres
         WHERE empresa_id = ? AND anio = ? AND mes = ? AND tabla_estado_registro_id = ?
         ORDER BY cierre_id DESC LIMIT 1" . ($bloquear ? ' FOR UPDATE' : ''),
        'iiii', $empresa_id, $anio, $mes, CONT_PERIODO_CERRADO);
    return $rows[0] ?? null;
}

/** Lanza RuntimeException si la fecha cae en un mes cerrado de la empresa. */
function cont_periodo_exigir_abierto($conexion, $empresa_id, $fecha, string $que = 'registrar el asiento'): void
{
    $fecha = trim((string)$fecha);
    if ($fecha === '' || !preg_match('/^(\d{4})-(\d{2})/', $fecha, $m) || !cont_periodo_disponible($conexion)) return;
    $anio = intval($m[1]);
    $mes = intval($m[2]);
    if (cont_periodo_cierre_activo($conexion, intval($empresa_id), $anio, $mes)) {
        $ejercicio = cont_ejercicio_de_fecha($conexion, intval($empresa_id), sprintf('%04d-%02d-01', $anio, $mes));
        throw new RuntimeException("No se puede $que: el período contable " . cont_periodo_texto($anio, $mes)
            . " (ejercicio $ejercicio) está cerrado. Para modificarlo, reábralo en Contabilidad > Cierre de Períodos Contables.");
    }
}

/** El asiento existente no está en un mes cerrado (si no existe o es de otra empresa, no hace nada). */
function cont_periodo_exigir_asiento($conexion, $empresa_id, $cont_asiento_id, string $que = 'modificar el asiento'): void
{
    if (intval($cont_asiento_id) <= 0 || !cont_periodo_disponible($conexion)) return;
    foreach (cont_periodo_filas($conexion,
                 "SELECT f_asiento FROM gestion__cont_asientos WHERE cont_asiento_id = ? AND empresa_id = ?",
                 'ii', intval($cont_asiento_id), intval($empresa_id)) as $a) {
        cont_periodo_exigir_abierto($conexion, $empresa_id, $a['f_asiento'], $que);
    }
}

/** Ningún asiento vigente del comprobante está en un mes cerrado. */
function cont_periodo_exigir_comprobante($conexion, $empresa_id, $comprobante_id, string $que = 'modificar el asiento del comprobante'): void
{
    if (intval($comprobante_id) <= 0 || !cont_periodo_disponible($conexion)) return;
    foreach (cont_periodo_filas($conexion,
                 "SELECT DISTINCT f_asiento FROM gestion__cont_asientos
                  WHERE comprobante_id = ? AND empresa_id = ? AND IFNULL(tabla_estado_registro_id, 3) <> 2",
                 'ii', intval($comprobante_id), intval($empresa_id)) as $a) {
        cont_periodo_exigir_abierto($conexion, $empresa_id, $a['f_asiento'], $que);
    }
}

// ------------------------------------------------------------------ ejercicio contable
/**
 * Vigencias de gestion__empresas_parametros con su mes de cierre (cacheadas por request). Sin la columna
 * (script 02 sin correr) o sin parámetros, el ejercicio cierra en diciembre.
 */
function cont_ejercicio_vigencias($conexion, int $empresa_id): array
{
    static $cache = [];
    if (!isset($cache[$empresa_id])) {
        $r = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'mes_cierre_ejercicio'");
        $cache[$empresa_id] = ($r && mysqli_num_rows($r) > 0)
            ? cont_periodo_filas($conexion,
                "SELECT f_desde, f_hasta, mes_cierre_ejercicio FROM gestion__empresas_parametros
                 WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY f_desde", 'i', $empresa_id)
            : [];
    }
    return $cache[$empresa_id];
}

/** Mes de cierre del ejercicio vigente a la fecha (antes de la primera vigencia, el de la primera; sin datos, 12). */
function cont_mes_cierre($conexion, int $empresa_id, string $fecha): int
{
    $vig = cont_ejercicio_vigencias($conexion, $empresa_id);
    $mes = $vig ? intval($vig[0]['mes_cierre_ejercicio']) : 12;
    foreach ($vig as $v) {
        if ($v['f_desde'] <= $fecha && ($v['f_hasta'] === null || $v['f_hasta'] >= $fecha)) {
            $mes = intval($v['mes_cierre_ejercicio']);
        }
    }
    return ($mes >= 1 && $mes <= 12) ? $mes : 12;
}

/**
 * Último día del ejercicio N: el fin del primer mes del año N que es el mes de cierre vigente a ese día. Si
 * ninguno coincide (cambio de mes de cierre en el año), el mes de cierre vigente al 31/12 de N.
 */
function cont_ejercicio_fin($conexion, int $empresa_id, int $anio): string
{
    for ($m = 1; $m <= 12; $m++) {
        $fin = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $m)));
        if (cont_mes_cierre($conexion, $empresa_id, $fin) === $m) return $fin;
    }
    $m = cont_mes_cierre($conexion, $empresa_id, "$anio-12-31");
    return date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $m)));
}

/**
 * Ejercicio N (se nombra por el año en que termina): desde el día siguiente al cierre del N-1 hasta el cierre del N,
 * con sus meses en orden. Irregular si no tiene 12 meses (la empresa cambió el mes de cierre).
 */
function cont_ejercicio_rango($conexion, int $empresa_id, int $ejercicio): array
{
    $hasta = cont_ejercicio_fin($conexion, $empresa_id, $ejercicio);
    $desde = date('Y-m-d', strtotime(cont_ejercicio_fin($conexion, $empresa_id, $ejercicio - 1) . ' +1 day'));
    $meses = [];
    for ($t = strtotime(substr($desde, 0, 7) . '-01'); $t <= strtotime($hasta); $t = strtotime('+1 month', $t)) {
        $meses[] = ['anio' => intval(date('Y', $t)), 'mes' => intval(date('n', $t))];
    }
    return [
        'ejercicio' => $ejercicio,
        'desde' => $desde,
        'hasta' => $hasta,
        'mes_cierre' => intval(substr($hasta, 5, 2)),
        'meses' => $meses,
        'irregular' => count($meses) !== 12,
        'texto' => "Ejercicio $ejercicio (" . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . ')',
    ];
}

/** Ejercicio al que pertenece la fecha. */
function cont_ejercicio_de_fecha($conexion, int $empresa_id, string $fecha): int
{
    $anio = intval(substr($fecha, 0, 4));
    return $fecha <= cont_ejercicio_fin($conexion, $empresa_id, $anio) ? $anio : $anio + 1;
}

/** Ejercicios para elegir: del primero con asientos o cierres (como mucho 10 atrás) al actual. */
function cont_ejercicios_lista($conexion, int $empresa_id): array
{
    $actual = cont_ejercicio_de_fecha($conexion, $empresa_id, date('Y-m-d'));
    $r = cont_periodo_filas($conexion,
        "SELECT LEAST(COALESCE((SELECT MIN(f_asiento) FROM gestion__cont_asientos WHERE empresa_id = ? AND f_asiento > '1900-01-01'), CURDATE()),
                      COALESCE((SELECT MIN(CONCAT(anio, '-', LPAD(mes, 2, '0'), '-01')) FROM gestion__cont_periodos_cierres WHERE empresa_id = ?), CURDATE())) AS primera",
        'ii', $empresa_id, $empresa_id);
    $primero = max($actual - 10, cont_ejercicio_de_fecha($conexion, $empresa_id, substr((string)($r[0]['primera'] ?? date('Y-m-d')), 0, 10)));
    $lista = [];
    for ($n = $actual; $n >= $primero; $n--) {
        $rango = cont_ejercicio_rango($conexion, $empresa_id, $n);
        $lista[] = ['ejercicio' => $n, 'texto' => $rango['texto'], 'irregular' => $rango['irregular']];
    }
    return $lista;
}

// ------------------------------------------------------------------ ABM
/** Asientos del mes: total y en borrador (estado 3). */
function cont_periodo_asientos_mes($conexion, int $empresa_id, int $anio, int $mes): array
{
    $r = cont_periodo_filas($conexion,
        "SELECT COUNT(*) AS total, COALESCE(SUM(IFNULL(tabla_estado_registro_id, 3) = 3), 0) AS borradores
         FROM gestion__cont_asientos
         WHERE empresa_id = ? AND f_asiento BETWEEN ? AND ? AND IFNULL(tabla_estado_registro_id, 3) <> 2",
        'iss', $empresa_id, sprintf('%04d-%02d-01', $anio, $mes), date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mes))));
    return ['total' => intval($r[0]['total'] ?? 0), 'borradores' => intval($r[0]['borradores'] ?? 0)];
}

/** Cierra un mes. Ver cont_periodo_cerrar_meses. */
function cont_periodo_cerrar($conexion, int $empresa_id, int $usuario_id, int $anio, int $mes, string $observaciones = '', bool $forzar = false): array
{
    if ($anio < 2000 || $anio > 2100) throw new RuntimeException('Elija el año.');
    if ($mes < 1 || $mes > 12) throw new RuntimeException('Elija el mes.');
    if (sprintf('%04d-%02d', $anio, $mes) > date('Y-m')) throw new RuntimeException('No se puede cerrar un período futuro.');
    return cont_periodo_cerrar_meses($conexion, $empresa_id, $usuario_id, [['anio' => $anio, 'mes' => $mes]], $observaciones, $forzar,
                                     'El período ' . cont_periodo_texto($anio, $mes) . ' ya está cerrado.');
}

/** Cierra los meses todavía abiertos del ejercicio, salvo los futuros. Ver cont_periodo_cerrar_meses. */
function cont_periodo_cerrar_ejercicio($conexion, int $empresa_id, int $usuario_id, int $ejercicio, string $observaciones = '', bool $forzar = false): array
{
    if ($ejercicio < 2000 || $ejercicio > 2100) throw new RuntimeException('Elija el ejercicio.');
    $rango = cont_ejercicio_rango($conexion, $empresa_id, $ejercicio);
    return cont_periodo_cerrar_meses($conexion, $empresa_id, $usuario_id, $rango['meses'], $observaciones, $forzar,
                                     "No quedan meses abiertos para cerrar en el ejercicio $ejercicio.");
}

/**
 * Cierra los meses [['anio' =>, 'mes' =>], ...] que no estén cerrados ni sean futuros.
 * Sin $forzar, si hay asientos en borrador en esos meses devuelve requiere_confirmacion (no cierra nada):
 * cerrados, esos borradores ya no se pueden completar ni confirmar.
 */
function cont_periodo_cerrar_meses($conexion, int $empresa_id, int $usuario_id, array $meses, string $observaciones, bool $forzar, string $msg_nada): array
{
    if (!cont_periodo_disponible($conexion)) throw new RuntimeException('Falta correr el script de cierre de períodos contables.');
    $actual = date('Y-m');
    $obs = mb_substr(trim($observaciones), 0, 255);

    mysqli_begin_transaction($conexion);
    try {
        $a_cerrar = [];
        $ya_cerrados = [];
        foreach ($meses as $p) {
            if (sprintf('%04d-%02d', $p['anio'], $p['mes']) > $actual) continue;
            if (cont_periodo_cierre_activo($conexion, $empresa_id, $p['anio'], $p['mes'], true)) {
                $ya_cerrados[] = $p;
            } else {
                $a_cerrar[] = $p;
            }
        }
        if (!$a_cerrar) throw new RuntimeException($msg_nada);
        $borradores = [];
        foreach ($a_cerrar as $p) {
            $n = cont_periodo_asientos_mes($conexion, $empresa_id, $p['anio'], $p['mes'])['borradores'];
            if ($n > 0) $borradores[] = $p + ['periodo' => cont_periodo_texto($p['anio'], $p['mes']), 'borradores' => $n];
        }
        if ($borradores && !$forzar) {
            mysqli_rollback($conexion);
            return ['requiere_confirmacion' => true, 'borradores' => $borradores, 'cerrados' => [], 'ya_cerrados' => $ya_cerrados];
        }
        $estado = CONT_PERIODO_CERRADO;
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__cont_periodos_cierres
            (empresa_id, anio, mes, observaciones, usuario_id, tabla_estado_registro_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($a_cerrar as $p) {
            $a = $p['anio'];
            $m = $p['mes'];
            mysqli_stmt_bind_param($stmt, 'iiisii', $empresa_id, $a, $m, $obs, $usuario_id, $estado);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
    error_log("=== cont_periodo_cerrar_meses empresa $empresa_id: "
        . implode(',', array_map(function ($p) { return sprintf('%04d-%02d', $p['anio'], $p['mes']); }, $a_cerrar)) . " usuario $usuario_id ===");
    return ['requiere_confirmacion' => false, 'cerrados' => $a_cerrar, 'ya_cerrados' => $ya_cerrados, 'borradores' => $borradores];
}

/** Reabre un cierre (queda en 38 con usuario, fecha y motivo). */
function cont_periodo_reabrir($conexion, int $empresa_id, int $usuario_id, int $cierre_id, string $motivo): array
{
    $motivo = mb_substr(trim($motivo), 0, 255);
    if ($motivo === '') throw new RuntimeException('Ingrese el motivo de la reapertura.');
    mysqli_begin_transaction($conexion);
    try {
        $rows = cont_periodo_filas($conexion,
            "SELECT * FROM gestion__cont_periodos_cierres WHERE cierre_id = ? AND empresa_id = ? FOR UPDATE",
            'ii', $cierre_id, $empresa_id);
        $c = $rows[0] ?? null;
        if (!$c) throw new RuntimeException('Cierre no encontrado.');
        if (intval($c['tabla_estado_registro_id']) !== CONT_PERIODO_CERRADO) throw new RuntimeException('El período ya está reabierto.');
        $estado = CONT_PERIODO_REABIERTO;
        $cerrado = CONT_PERIODO_CERRADO;
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__cont_periodos_cierres
            SET tabla_estado_registro_id = ?, motivo_reapertura = ?, usuario_reapertura_id = ?, fecha_reapertura = NOW()
            WHERE cierre_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?");
        mysqli_stmt_bind_param($stmt, 'isiiii', $estado, $motivo, $usuario_id, $cierre_id, $empresa_id, $cerrado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
    error_log("=== cont_periodo_reabrir cierre $cierre_id empresa $empresa_id: $motivo ===");
    return ['periodo' => cont_periodo_texto(intval($c['anio']), intval($c['mes']))];
}

/** Cierres de la empresa (de un ejercicio, o todos con $ejercicio = 0), con los reabiertos si se piden. */
function cont_periodo_listar($conexion, int $empresa_id, int $ejercicio = 0, bool $incluir_reabiertos = false): array
{
    if (!cont_periodo_disponible($conexion)) return [];
    $sql = "SELECT c.*, u.usuario_nombre AS usuario, ur.usuario_nombre AS usuario_reapertura,
                   ter.tabla_estado_registro AS estado
            FROM gestion__cont_periodos_cierres c
            LEFT JOIN conf__usuarios u ON u.usuario_id = c.usuario_id
            LEFT JOIN conf__usuarios ur ON ur.usuario_id = c.usuario_reapertura_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . CONT_PERIODO_TABLA . "
                  AND ter.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ?";
    $tipos = 'i';
    $params = [$empresa_id];
    if ($ejercicio > 0) {
        $rango = cont_ejercicio_rango($conexion, $empresa_id, $ejercicio);
        $sql .= ' AND c.anio * 100 + c.mes BETWEEN ? AND ?';
        $tipos .= 'ii';
        $params[] = intval(str_replace('-', '', substr($rango['desde'], 0, 7)));
        $params[] = intval(str_replace('-', '', substr($rango['hasta'], 0, 7)));
    }
    if (!$incluir_reabiertos) {
        $sql .= ' AND c.tabla_estado_registro_id = ' . CONT_PERIODO_CERRADO;
    }
    $sql .= ' ORDER BY c.anio DESC, c.mes DESC, c.cierre_id DESC';
    $rows = cont_periodo_filas($conexion, $sql, $tipos, ...$params);
    foreach ($rows as &$r) {
        $r['periodo'] = sprintf('%04d-%02d', $r['anio'], $r['mes']);
        $r['periodo_texto'] = cont_periodo_texto(intval($r['anio']), intval($r['mes']));
        $r['ejercicio'] = cont_ejercicio_de_fecha($conexion, $empresa_id, $r['periodo'] . '-01');
    }
    return $rows;
}

/** Los meses del ejercicio: cerrado o no (con su cierre_id), futuro, y cantidad de asientos y borradores. */
function cont_periodo_resumen_ejercicio($conexion, int $empresa_id, int $ejercicio): array
{
    $rango = cont_ejercicio_rango($conexion, $empresa_id, $ejercicio);
    $cierres = [];
    foreach (cont_periodo_listar($conexion, $empresa_id, $ejercicio) as $c) {
        $cierres[$c['periodo']] = $cierres[$c['periodo']] ?? $c;
    }
    $asientos = [];
    foreach (cont_periodo_filas($conexion,
                 "SELECT per, COUNT(*) AS total, SUM(est = 3) AS borradores
                  FROM (SELECT DATE_FORMAT(f_asiento, '%Y-%m') AS per, IFNULL(tabla_estado_registro_id, 3) AS est
                        FROM gestion__cont_asientos
                        WHERE empresa_id = ? AND f_asiento BETWEEN ? AND ? AND IFNULL(tabla_estado_registro_id, 3) <> 2) x
                  GROUP BY per", 'iss', $empresa_id, substr($rango['desde'], 0, 7) . '-01', $rango['hasta']) as $a) {
        $asientos[$a['per']] = $a;
    }
    $actual = date('Y-m');
    $meses = [];
    foreach ($rango['meses'] as $p) {
        $per = sprintf('%04d-%02d', $p['anio'], $p['mes']);
        $c = $cierres[$per] ?? null;
        $meses[] = [
            'anio' => $p['anio'],
            'mes' => $p['mes'],
            'nombre' => cont_periodo_texto($p['anio'], $p['mes']),
            'periodo' => $per,
            'cerrado' => $c !== null,
            'cierre_id' => $c ? intval($c['cierre_id']) : null,
            'cerrado_por' => $c['usuario'] ?? null,
            'fecha_cierre' => $c['fecha_creacion'] ?? null,
            'futuro' => $per > $actual,
            'asientos' => intval($asientos[$per]['total'] ?? 0),
            'borradores' => intval($asientos[$per]['borradores'] ?? 0),
        ];
    }
    unset($rango['meses']);
    return ['ejercicio' => $rango, 'meses' => $meses];
}
