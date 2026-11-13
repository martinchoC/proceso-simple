<?php
/**
 * Modelo: Contabilidad del Cliente (página 423), sobre la empresa del cliente en el ERP. Consulta y, con el período
 * contable abierto, edición de un asiento (ccon_asiento_guardar: fecha, descripción y renglones).
 * Balance y sumas y saldos: obtenerBalanceCompleto() de gestion/cont__balances_model.php.
 * Mayores: obtenerSaldosCuentas() / obtenerDetalleCuenta() de gestion/cont__mayores_model.php (incluyen borradores,
 * como Gestión > Mayores Contables). Asientos: consultas propias de solo lectura (cont_asientos_model.php trae el ABM y
 * comparte nombres de función con el de mayores, no se pueden incluir juntos).
 * El acceso (cliente del estudio + vinculación de contabilidad o el propio estudio) lo resuelve estudio_cliente_empresa().
 */

declare(strict_types=1);

require_once __DIR__ . '/estudio_cliente_empresa.php';
require_once __DIR__ . '/../../core/cont_periodos.php';
require_once __DIR__ . '/../../core/cont_asientos_edicion.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/formato.php';

function ccon_filas(mysqli $c, string $sql, string $tipos, array $params): array
{
    $st = mysqli_prepare($c, $sql);
    if (!$st) {
        throw new LogicException('ccon_filas: ' . mysqli_error($c));
    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($st, $tipos, ...$params);
    }
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);
    return $rows;
}

/** Fecha AAAA-MM-DD válida o el valor por defecto. */
function ccon_fecha(?string $f, string $defecto): string
{
    $f = (string) $f;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) ? $f : $defecto;
}

/** Ejercicios de la empresa del cliente y el rango del pedido (o del actual). */
function ccon_periodo(mysqli $c, int $empresa_id, int $ejercicio): array
{
    $ejercicio = $ejercicio ?: cont_ejercicio_de_fecha($c, $empresa_id, date('Y-m-d'));
    $r = cont_ejercicio_rango($c, $empresa_id, $ejercicio);
    return ['ejercicio' => $ejercicio, 'desde' => $r['desde'], 'hasta' => $r['hasta'], 'texto' => $r['texto'],
            'ejercicios' => cont_ejercicios_lista($c, $empresa_id)];
}

/**
 * Balance general + sumas y saldos del período: lo mismo que Gestión > Balance (cont__balances.php), con gráficos,
 * desglose por rubros y auditoría. Los dos módulos de gestión tienen el mismo cont__balances_model.php.
 */
function ccon_balance(mysqli $c, int $empresa_id, string $desde, string $hasta, bool $borradores): array
{
    require_once __DIR__ . '/../gestion/cont__balances_model.php';
    $b = obtenerBalanceCompleto($c, $empresa_id, $desde, $hasta, $borradores);
    if (empty($b['success'])) {
        throw new RuntimeException($b['error'] ?? 'No se pudo calcular el balance.');
    }
    return ['periodo' => $b['periodo'] ?? null, 'cuentas' => $b['cuentas'] ?? [], 'totales' => $b['totales_sumas_saldos'] ?? [],
            'kpis' => $b['kpis'] ?? [], 'auditoria' => $b['auditoria'] ?? [], 'desglose' => $b['desglose'] ?? [],
            'graficos' => $b['graficos'] ?? []];
}

/** Plan de cuentas completo (activas) de la empresa del cliente, con la cuenta madre. */
function ccon_plan_cuentas(mysqli $c, int $empresa_id): array
{
    return ccon_filas($c, "SELECT cc.cont_cuenta_id, cc.codigo, cc.nombre, cc.naturaleza, cc.nivel, cc.es_imputable, cc.es_caja_banco,
                                  p.codigo AS padre_codigo, p.nombre AS padre_nombre
                           FROM gestion__cont_cuentas cc
                           LEFT JOIN gestion__cont_cuentas p ON p.cont_cuenta_id = cc.cuenta_padre_id AND p.empresa_id = cc.empresa_id
                           WHERE cc.empresa_id = ? AND cc.tabla_estado_registro_id = 1
                           ORDER BY cc.codigo", 'i', [$empresa_id]);
}

/** Cuentas con saldo o movimientos en el período (resumen de mayores). */
function ccon_mayor_cuentas(mysqli $c, int $empresa_id, string $desde, string $hasta): array
{
    require_once __DIR__ . '/../gestion/cont__mayores_model.php';
    return obtenerSaldosCuentas($c, $empresa_id, $desde, $hasta, null);
}

/** Mayor de una cuenta: saldo inicial, movimientos con saldo acumulado y totales. */
function ccon_mayor_detalle(mysqli $c, int $empresa_id, int $cuenta_id, string $desde, string $hasta): array
{
    $ok = ccon_filas($c, "SELECT 1 FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ?", 'ii', [$cuenta_id, $empresa_id]);
    if (!$ok) {
        throw new RuntimeException('La cuenta no es de la empresa del cliente.');
    }
    require_once __DIR__ . '/../gestion/cont__mayores_model.php';
    return obtenerDetalleCuenta($c, $empresa_id, $cuenta_id, $desde, $hasta);
}

/** Asientos del período (sin anulados), con sus totales. $estado: 0 = registrados y borradores, 1 o 3. */
function ccon_asientos(mysqli $c, int $empresa_id, string $desde, string $hasta, int $estado, string $texto): array
{
    $sql = "SELECT a.cont_asiento_id, a.f_asiento, a.anio, a.descripcion, a.tabla_estado_registro_id AS estado_id,
                   ta.cont_tipo_asiento AS tipo, a.comprobante_id,
                   COALESCE(SUM(CASE WHEN d.importe_local > 0 THEN d.importe_local ELSE 0 END), 0) AS debe,
                   COALESCE(SUM(CASE WHEN d.importe_local < 0 THEN -d.importe_local ELSE 0 END), 0) AS haber,
                   COUNT(d.cuenta_id) AS lineas
            FROM gestion__cont_asientos a
            LEFT JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id AND IFNULL(d.estado, 'activo') = 'activo'
            LEFT JOIN gestion__cont_tipos_asientos ta ON ta.cont_tipo_asiento_id = a.cont_tipo_asiento_id
            WHERE a.empresa_id = ? AND a.f_asiento BETWEEN ? AND ? AND a.tabla_estado_registro_id IN (1, 3)";
    $tipos = 'iss';
    $params = [$empresa_id, $desde, $hasta];
    if ($estado === 1 || $estado === 3) {
        $sql .= " AND a.tabla_estado_registro_id = ?";
        $tipos .= 'i';
        $params[] = $estado;
    }
    $texto = trim($texto);
    if ($texto !== '') {
        $sql .= " AND (a.descripcion LIKE ? OR a.cont_asiento_id = ?)";
        $tipos .= 'si';
        $params[] = '%' . $texto . '%';
        $params[] = ctype_digit($texto) ? (int) $texto : 0;
    }
    $sql .= " GROUP BY a.cont_asiento_id ORDER BY a.f_asiento DESC, a.cont_asiento_id DESC LIMIT 2000";
    $rows = ccon_filas($c, $sql, $tipos, $params);
    foreach ($rows as &$r) {
        $r['debe'] = round((float) $r['debe'], 2);
        $r['haber'] = round((float) $r['haber'], 2);
        $r['cuadrado'] = abs($r['debe'] - $r['haber']) < 0.01;
    }
    unset($r);
    return $rows;
}

/** Renglones de un asiento de la empresa y si se puede editar (edicion.puede / edicion.motivo). */
function ccon_asiento_detalle(mysqli $c, int $empresa_id, int $asiento_id): array
{
    $cab = cae_asiento($c, $empresa_id, $asiento_id);
    if (!$cab) {
        throw new RuntimeException('El asiento no es de la empresa del cliente.');
    }
    $lineas = ccon_filas($c, "SELECT d.cuenta_id, cc.codigo, cc.nombre AS cuenta, d.descripcion, d.producto_id, d.importe_local,
                                     CASE WHEN d.importe_local > 0 THEN d.importe_local ELSE 0 END AS debe,
                                     CASE WHEN d.importe_local < 0 THEN -d.importe_local ELSE 0 END AS haber
                              FROM gestion__cont_asientos_detalles d
                              JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id AND cc.empresa_id = ?
                              WHERE d.cont_asiento_id = ? AND IFNULL(d.estado, 'activo') = 'activo'
                              ORDER BY d.importe_local < 0, cc.codigo", 'ii', [$empresa_id, $asiento_id]);
    // Clave del renglón y si se le puede cambiar la cuenta (en un automático, solo los de productos)
    foreach ($lineas as &$l) {
        $l['clave'] = cae_linea_clave($l);
        $l['cuenta_editable'] = cae_linea_cuenta_editable($cab, $l);
    }
    unset($l);
    $motivo = ccon_asiento_no_editable($c, $empresa_id, $cab);
    if ($motivo === null && $cab['es_automatico'] && !array_filter(array_column($lineas, 'cuenta_editable'))) {
        $motivo = 'Asiento automático sin renglones de productos: no tiene cuentas para cambiar.';
    }
    return ['cabecera' => $cab, 'lineas' => $lineas, 'edicion' => ['puede' => $motivo === null, 'motivo' => $motivo]];
}

/** Motivo por el que el asiento no se puede editar (anulado / eliminado o período contable cerrado); null si se puede. */
function ccon_asiento_no_editable(mysqli $c, int $empresa_id, array $cab): ?string
{
    return cae_motivo_no_editable($c, $empresa_id, $cab);
}

/**
 * Asiento automático: cambia solo la cuenta de renglones de productos (core/cont_asientos_edicion.php).
 * $cambios: [['clave' => 'cuenta:producto:importe', 'cuenta_id' => int], ...]
 */
function ccon_asiento_cambiar_cuentas(mysqli $c, int $empresa_id, int $usuario_id, int $asiento_id, array $cambios): array
{
    return cae_cambiar_cuentas($c, $empresa_id, $usuario_id, $asiento_id, $cambios);
}

/** Cuentas imputables y activas del plan de la empresa del cliente (para los renglones). */
function ccon_cuentas_imputables(mysqli $c, int $empresa_id): array
{
    return ccon_filas($c, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas
                           WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
                           ORDER BY codigo", 'i', [$empresa_id]);
}

/**
 * Edita un asiento de la empresa del cliente: fecha, descripción y renglones (los reemplaza todos). Solo con el período
 * contable abierto en la fecha actual del asiento y en la nueva, cuentas imputables de la empresa y Debe = Haber.
 * El asiento conserva su estado (registrado o borrador). Cada renglón conserva entidad, comprobante y dimensiones del
 * renglón anterior de la misma cuenta; quedan como manuales (tipo M).
 * $lineas: [['cuenta_id' => int, 'descripcion' => string, 'debe' => float, 'haber' => float], ...]
 */
function ccon_asiento_guardar(mysqli $c, int $empresa_id, int $usuario_id, int $asiento_id, string $fecha, string $descripcion, array $lineas): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
        throw new RuntimeException('La fecha no es válida.');
    }
    $descripcion = trim($descripcion);
    if ($descripcion === '') {
        throw new RuntimeException('Escribí la descripción del asiento.');
    }
    $renglones = [];
    $debe = 0.0;
    $haber = 0.0;
    foreach (array_values($lineas) as $i => $l) {
        $cuenta = intval($l['cuenta_id'] ?? 0);
        $d = round((float) ($l['debe'] ?? 0), 2);
        $h = round((float) ($l['haber'] ?? 0), 2);
        if ($cuenta <= 0 && abs($d) < 0.005 && abs($h) < 0.005) {
            continue; // renglón vacío
        }
        $n = $i + 1;
        if ($cuenta <= 0) {
            throw new RuntimeException("Elegí la cuenta del renglón $n.");
        }
        if ($d < 0 || $h < 0) {
            throw new RuntimeException("El renglón $n tiene un importe negativo.");
        }
        if (($d > 0) === ($h > 0)) {
            throw new RuntimeException("El renglón $n tiene que llevar importe en el Debe o en el Haber (uno solo).");
        }
        $renglones[] = ['cuenta_id' => $cuenta, 'descripcion' => trim((string) ($l['descripcion'] ?? '')), 'importe' => $d > 0 ? $d : -$h];
        $debe += $d;
        $haber += $h;
    }
    if (count($renglones) < 2) {
        throw new RuntimeException('El asiento necesita al menos dos renglones.');
    }
    if (abs(round($debe - $haber, 2)) >= 0.01) {
        throw new RuntimeException('El asiento no cierra: Debe ' . fmt_numero($debe, 2) . ' y Haber ' . fmt_numero($haber, 2) . '.');
    }
    emp_validar_cuentas_asiento($c, $empresa_id, array_column($renglones, 'cuenta_id'));

    mysqli_begin_transaction($c);
    try {
        $cab = ccon_filas($c, "SELECT cont_asiento_id, f_asiento, tabla_estado_registro_id AS estado_id, moneda_id, tipo_cambio
                               FROM gestion__cont_asientos WHERE cont_asiento_id = ? AND empresa_id = ? FOR UPDATE",
                          'ii', [$asiento_id, $empresa_id]);
        if (!$cab) {
            throw new RuntimeException('El asiento no es de la empresa del cliente.');
        }
        $cab = $cab[0];
        $motivo = ccon_asiento_no_editable($c, $empresa_id, $cab);
        if ($motivo !== null) {
            throw new RuntimeException($motivo);
        }
        $tipo = cae_asiento($c, $empresa_id, $asiento_id);
        if ($tipo && $tipo['es_automatico']) {
            throw new RuntimeException(cae_msg_automatico());
        }
        cont_periodo_exigir_abierto($c, $empresa_id, $fecha, 'pasar el asiento a esa fecha');

        // Datos de los renglones anteriores, por cuenta (el primero de cada una)
        $previos = [];
        foreach (ccon_filas($c, "SELECT * FROM gestion__cont_asientos_detalles
                                 WHERE cont_asiento_id = ? AND IFNULL(estado, 'activo') = 'activo'", 'i', [$asiento_id]) as $p) {
            $previos[(int) $p['cuenta_id']] = $previos[(int) $p['cuenta_id']] ?? $p;
        }

        $anio = (int) substr($fecha, 0, 4);
        $mes = (int) substr($fecha, 5, 2);
        $st = mysqli_prepare($c, "UPDATE gestion__cont_asientos
                                  SET f_asiento = ?, anio = ?, mes = ?, descripcion = ?, usuario_modificacion_id = ?
                                  WHERE cont_asiento_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "siisiii", $fecha, $anio, $mes, $descripcion, $usuario_id, $asiento_id, $empresa_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        $st = mysqli_prepare($c, "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = ?");
        mysqli_stmt_bind_param($st, "i", $asiento_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        $st = mysqli_prepare($c, "INSERT INTO gestion__cont_asientos_detalles
                                  (cont_asiento_id, cuenta_id, area_id, departamento_id, centro_costo_id, proyecto_id, entidad_id,
                                   producto_id, comprobante_id, comprobante_id_destino, tipo, importe_local, importe_ifrs,
                                   importe_impositivo, moneda_id, tipo_cambio, descripcion, estado)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'M', ?, 0.00, 0.00, ?, ?, ?, 'activo')");
        $moneda = (int) $cab['moneda_id'];
        $tc = (float) ($cab['tipo_cambio'] ?: 1);
        foreach ($renglones as $r) {
            $p = $previos[$r['cuenta_id']] ?? [];
            $v = static function (string $k) use ($p): ?int {
                return isset($p[$k]) ? (int) $p[$k] : null;
            };
            $area = $v('area_id');
            $depto = $v('departamento_id');
            $ccosto = $v('centro_costo_id');
            $proy = $v('proyecto_id');
            $ent = $v('entidad_id');
            $prod = $v('producto_id');
            $comp = $v('comprobante_id');
            $comp_dest = $v('comprobante_id_destino');
            $cuenta = $r['cuenta_id'];
            $importe = $r['importe'];
            $desc = $r['descripcion'] !== '' ? $r['descripcion'] : null;
            mysqli_stmt_bind_param($st, "iiiiiiiiiidids", $asiento_id, $cuenta, $area, $depto, $ccosto, $proy, $ent, $prod,
                $comp, $comp_dest, $importe, $moneda, $tc, $desc);
            mysqli_stmt_execute($st);
        }
        mysqli_stmt_close($st);

        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return ['cont_asiento_id' => $asiento_id];
}
