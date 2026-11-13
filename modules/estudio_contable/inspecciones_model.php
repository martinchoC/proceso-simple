<?php
/**
 * Modelo: Seguimiento de inspecciones / fiscalizaciones de los clientes del estudio (F3).
 * Tablas: estudio__inspecciones, estudio__inspecciones_hitos (20261106_estudio_inspecciones_01.sql).
 * Todo se acota por empresa_id (tenant del estudio); el cliente se valida contra estudio__clientes.
 */

declare(strict_types=1);

require_once __DIR__ . '/tareas_raci_model.php';

const INSP_ORGANISMOS = ['ARCA', 'COMARB', 'PROVINCIAL', 'MUNICIPAL', 'SINDICATO', 'TRABAJO', 'OTRO'];
const INSP_TIPOS = ['FISCALIZACION', 'VERIFICACION', 'REQUERIMIENTO', 'INTIMACION', 'OTRO'];
const INSP_ESTADOS = ['ABIERTA', 'EN_DESCARGO', 'EN_APELACION', 'PLAN_PAGOS', 'CERRADA_SIN_AJUSTE', 'CERRADA_CON_AJUSTE', 'PRESCRIPTA'];
const INSP_ESTADOS_CERRADOS = ['CERRADA_SIN_AJUSTE', 'CERRADA_CON_AJUSTE', 'PRESCRIPTA'];
const INSP_HITOS = ['NOTIFICACION', 'REQUERIMIENTO', 'PRESENTACION', 'AUDIENCIA', 'RESOLUCION', 'PAGO', 'OTRO'];

function insp_resp(bool $ok, string $msg, $data = null, ?string $err = null): array
{
    return ['success' => $ok, 'message' => $msg, 'data' => $data, 'error' => $err];
}

function insp_fecha_valida(?string $f): bool
{
    if ($f === null || $f === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}

function insp_periodo(?string $p): ?int
{
    $p = preg_replace('/\D/', '', (string) $p);
    if ($p === '') {
        return null;
    }
    $n = (int) $p;
    $mes = $n % 100;
    return ($n >= 190001 && $n <= 209912 && $mes >= 1 && $mes <= 12) ? $n : null;
}

function insp_cliente_del_estudio(mysqli $conexion, int $empresa_id, int $cliente_id): bool
{
    $st = mysqli_prepare($conexion, "SELECT 1 FROM estudio__clientes WHERE empresa_id = ? AND cliente_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $cliente_id);
    mysqli_stmt_execute($st);
    $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $ok;
}

function inspecciones_combos(mysqli $conexion, int $empresa_id): array
{
    $c = ['clientes' => [], 'usuarios' => [], 'obligaciones' => [],
          'organismos' => INSP_ORGANISMOS, 'tipos' => INSP_TIPOS, 'estados' => INSP_ESTADOS, 'tipos_hito' => INSP_HITOS];

    $st = mysqli_prepare($conexion, "SELECT cliente_id, razon_social, cuit FROM estudio__clientes
                                     WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    while ($f = mysqli_fetch_assoc($r)) {
        $c['clientes'][] = $f;
    }
    mysqli_stmt_close($st);

    $r = mysqli_query($conexion, "SELECT usuario_id, usuario_nombre FROM conf__usuarios WHERE tabla_estado_registro_id = 1 ORDER BY usuario_nombre");
    while ($r && $f = mysqli_fetch_assoc($r)) {
        $c['usuarios'][] = $f;
    }
    $r = mysqli_query($conexion, "SELECT impuesto_obligacion_id, codigo_oficial, nombre FROM estudio__impuestos_obligaciones WHERE activo = 1 ORDER BY organismo, nombre");
    while ($r && $f = mysqli_fetch_assoc($r)) {
        $c['obligaciones'][] = $f;
    }
    return $c;
}

/** Listado con el próximo plazo pendiente y cuántos plazos están vencidos. Filtros: cliente_id, estado_proceso, solo_abiertas. */
function inspecciones_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $where = "i.empresa_id = ? AND i.tabla_estado_registro_id = 1";
    $tipos = "i";
    $params = [$empresa_id];
    $cliente_id = intval($filtros['cliente_id'] ?? 0);
    if ($cliente_id > 0) {
        $where .= " AND i.cliente_id = ?";
        $tipos .= "i";
        $params[] = $cliente_id;
    }
    $estado = (string) ($filtros['estado_proceso'] ?? '');
    if (in_array($estado, INSP_ESTADOS, true)) {
        $where .= " AND i.estado_proceso = ?";
        $tipos .= "s";
        $params[] = $estado;
    } elseif (!empty($filtros['solo_abiertas'])) {
        $where .= " AND i.estado_proceso NOT IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')";
    }

    $sql = "SELECT i.inspeccion_id, i.cliente_id, c.razon_social, i.organismo, i.tipo, i.numero_acta, i.f_inicio, i.f_cierre,
                   i.estado_proceso, i.monto_reclamado, i.monto_aceptado, i.monto_pagado, i.periodo_desde, i.periodo_hasta,
                   u.usuario_nombre AS responsable,
                   (SELECT MIN(h.f_limite) FROM estudio__inspecciones_hitos h
                     WHERE h.empresa_id = i.empresa_id AND h.inspeccion_id = i.inspeccion_id
                       AND h.cumplido = 0 AND h.tabla_estado_registro_id = 1 AND h.f_limite >= CURDATE()) AS proximo_plazo,
                   (SELECT COUNT(*) FROM estudio__inspecciones_hitos h
                     WHERE h.empresa_id = i.empresa_id AND h.inspeccion_id = i.inspeccion_id
                       AND h.cumplido = 0 AND h.tabla_estado_registro_id = 1 AND h.f_limite < CURDATE()) AS plazos_vencidos
            FROM estudio__inspecciones i
            JOIN estudio__clientes c ON c.cliente_id = i.cliente_id AND c.empresa_id = i.empresa_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = i.usuario_r_id
            WHERE $where
            ORDER BY (i.estado_proceso IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')), i.f_inicio DESC";
    $st = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($st, $tipos, ...$params);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $out = [];
    while ($f = mysqli_fetch_assoc($r)) {
        $f['plazos_vencidos'] = (int) $f['plazos_vencidos'];
        $out[] = $f;
    }
    mysqli_stmt_close($st);
    return $out;
}

function inspecciones_kpis(mysqli $conexion, int $empresa_id): array
{
    $st = mysqli_prepare($conexion, "SELECT
            SUM(estado_proceso NOT IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')) AS abiertas,
            COALESCE(SUM(CASE WHEN estado_proceso NOT IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')
                              THEN monto_reclamado - monto_pagado ELSE 0 END), 0) AS monto_en_juego
        FROM estudio__inspecciones WHERE empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $k = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    mysqli_stmt_close($st);

    $st = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM estudio__inspecciones_hitos h
        JOIN estudio__inspecciones i ON i.inspeccion_id = h.inspeccion_id AND i.empresa_id = h.empresa_id
        WHERE h.empresa_id = ? AND h.cumplido = 0 AND h.tabla_estado_registro_id = 1 AND h.f_limite < CURDATE()
          AND i.tabla_estado_registro_id = 1 AND i.estado_proceso NOT IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $v = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: [];
    mysqli_stmt_close($st);

    return ['abiertas' => (int) ($k['abiertas'] ?? 0), 'monto_en_juego' => (float) ($k['monto_en_juego'] ?? 0),
            'plazos_vencidos' => (int) ($v['n'] ?? 0)];
}

function inspeccion_obtener(mysqli $conexion, int $empresa_id, int $inspeccion_id): ?array
{
    $st = mysqli_prepare($conexion, "SELECT * FROM estudio__inspecciones WHERE empresa_id = ? AND inspeccion_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $inspeccion_id);
    mysqli_stmt_execute($st);
    $i = mysqli_fetch_assoc(mysqli_stmt_get_result($st)) ?: null;
    mysqli_stmt_close($st);
    if (!$i) {
        return null;
    }
    $st = mysqli_prepare($conexion, "SELECT hito_id, tipo_hito, f_hito, f_limite, descripcion, importe, cumplido, tarea_id
                                     FROM estudio__inspecciones_hitos
                                     WHERE empresa_id = ? AND inspeccion_id = ? AND tabla_estado_registro_id = 1
                                     ORDER BY f_hito DESC, hito_id DESC");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $inspeccion_id);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $i['hitos'] = [];
    while ($h = mysqli_fetch_assoc($r)) {
        $i['hitos'][] = $h;
    }
    mysqli_stmt_close($st);
    return $i;
}

function inspeccion_guardar(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    $id = intval($d['inspeccion_id'] ?? 0);
    $cliente_id = intval($d['cliente_id'] ?? 0);
    $organismo = (string) ($d['organismo'] ?? '');
    $tipo = (string) ($d['tipo'] ?? '');
    $estado = (string) ($d['estado_proceso'] ?? 'ABIERTA');
    $numero = trim((string) ($d['numero_acta'] ?? ''));
    $f_inicio = (string) ($d['f_inicio'] ?? '');
    $f_cierre = (string) ($d['f_cierre'] ?? '');
    $imp_id = intval($d['impuesto_obligacion_id'] ?? 0);
    $p_desde = insp_periodo($d['periodo_desde'] ?? null);
    $p_hasta = insp_periodo($d['periodo_hasta'] ?? null);
    $reclamado = round((float) str_replace(',', '.', (string) ($d['monto_reclamado'] ?? 0)), 2);
    $aceptado = round((float) str_replace(',', '.', (string) ($d['monto_aceptado'] ?? 0)), 2);
    $pagado = round((float) str_replace(',', '.', (string) ($d['monto_pagado'] ?? 0)), 2);
    $resp = intval($d['usuario_r_id'] ?? 0);
    $obs = trim((string) ($d['observaciones'] ?? ''));

    if ($cliente_id <= 0 || !insp_cliente_del_estudio($conexion, $empresa_id, $cliente_id)) {
        return insp_resp(false, 'Seleccione un cliente del estudio.', null, 'VALIDATION');
    }
    if (!in_array($organismo, INSP_ORGANISMOS, true) || !in_array($tipo, INSP_TIPOS, true) || !in_array($estado, INSP_ESTADOS, true)) {
        return insp_resp(false, 'Organismo, tipo o estado inválido.', null, 'VALIDATION');
    }
    if (!insp_fecha_valida($f_inicio)) {
        return insp_resp(false, 'La fecha de inicio es obligatoria y debe ser válida.', null, 'VALIDATION');
    }
    if ($f_cierre !== '' && (!insp_fecha_valida($f_cierre) || $f_cierre < $f_inicio)) {
        return insp_resp(false, 'La fecha de cierre es inválida o anterior al inicio.', null, 'VALIDATION');
    }
    if ($p_desde !== null && $p_hasta !== null && $p_hasta < $p_desde) {
        return insp_resp(false, 'El período hasta no puede ser anterior al período desde.', null, 'VALIDATION');
    }
    if ($reclamado < 0 || $aceptado < 0 || $pagado < 0) {
        return insp_resp(false, 'Los montos no pueden ser negativos.', null, 'VALIDATION');
    }
    // Estado cerrado => fecha de cierre (hoy si no la indicó)
    if (in_array($estado, INSP_ESTADOS_CERRADOS, true) && $f_cierre === '') {
        $f_cierre = date('Y-m-d');
    }
    $f_cierre_db = $f_cierre === '' ? null : $f_cierre;
    $imp_db = $imp_id > 0 ? $imp_id : null;
    $resp_db = $resp > 0 ? $resp : null;
    $num_db = $numero === '' ? null : $numero;

    if ($id > 0) {
        if (!inspeccion_obtener($conexion, $empresa_id, $id)) {
            return insp_resp(false, 'La inspección no existe.', null, 'NOT_FOUND');
        }
        $st = mysqli_prepare($conexion, "UPDATE estudio__inspecciones SET cliente_id = ?, organismo = ?, tipo = ?, numero_acta = ?,
                impuesto_obligacion_id = ?, periodo_desde = ?, periodo_hasta = ?, f_inicio = ?, f_cierre = ?, estado_proceso = ?,
                monto_reclamado = ?, monto_aceptado = ?, monto_pagado = ?, usuario_r_id = ?, observaciones = ?
            WHERE empresa_id = ? AND inspeccion_id = ?");
        mysqli_stmt_bind_param($st, "isssiiisssdddisii", $cliente_id, $organismo, $tipo, $num_db, $imp_db, $p_desde, $p_hasta,
            $f_inicio, $f_cierre_db, $estado, $reclamado, $aceptado, $pagado, $resp_db, $obs, $empresa_id, $id);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        return $ok ? insp_resp(true, 'Inspección actualizada.', ['inspeccion_id' => $id]) : insp_resp(false, 'No se pudo actualizar.', null, 'DB_ERROR');
    }

    $st = mysqli_prepare($conexion, "INSERT INTO estudio__inspecciones (empresa_id, cliente_id, organismo, tipo, numero_acta,
            impuesto_obligacion_id, periodo_desde, periodo_hasta, f_inicio, f_cierre, estado_proceso,
            monto_reclamado, monto_aceptado, monto_pagado, usuario_r_id, observaciones, usuario_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, "iisssiiisssdddisi", $empresa_id, $cliente_id, $organismo, $tipo, $num_db, $imp_db, $p_desde, $p_hasta,
        $f_inicio, $f_cierre_db, $estado, $reclamado, $aceptado, $pagado, $resp_db, $obs, $usuario_id);
    $ok = mysqli_stmt_execute($st);
    $nuevo = (int) mysqli_insert_id($conexion);
    mysqli_stmt_close($st);
    return $ok ? insp_resp(true, 'Inspección registrada.', ['inspeccion_id' => $nuevo]) : insp_resp(false, 'No se pudo registrar.', null, 'DB_ERROR');
}

function inspeccion_anular(mysqli $conexion, int $empresa_id, int $inspeccion_id): array
{
    $st = mysqli_prepare($conexion, "UPDATE estudio__inspecciones SET tabla_estado_registro_id = 38 WHERE empresa_id = ? AND inspeccion_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "ii", $empresa_id, $inspeccion_id);
    mysqli_stmt_execute($st);
    $n = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);
    return $n > 0 ? insp_resp(true, 'Inspección anulada.') : insp_resp(false, 'La inspección no existe o ya estaba anulada.', null, 'NOT_FOUND');
}

/**
 * Alta de un hito. Con crear_tarea=1 y un plazo, genera la tarea RACI (responsable = el de la inspección o el indicado;
 * supervisor = quien carga). La tarea se crea fuera de cualquier transacción propia: tarea_raci_guardar abre la suya.
 */
function inspeccion_hito_guardar(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): array
{
    $inspeccion_id = intval($d['inspeccion_id'] ?? 0);
    $insp = inspeccion_obtener($conexion, $empresa_id, $inspeccion_id);
    if (!$insp) {
        return insp_resp(false, 'La inspección no existe.', null, 'NOT_FOUND');
    }
    $tipo = (string) ($d['tipo_hito'] ?? 'OTRO');
    $f_hito = (string) ($d['f_hito'] ?? date('Y-m-d'));
    $f_limite = (string) ($d['f_limite'] ?? '');
    $desc = trim((string) ($d['descripcion'] ?? ''));
    $importe = ($d['importe'] ?? '') === '' ? null : round((float) str_replace(',', '.', (string) $d['importe']), 2);

    if (!in_array($tipo, INSP_HITOS, true) || $desc === '' || !insp_fecha_valida($f_hito)) {
        return insp_resp(false, 'Complete tipo, fecha y descripción del hito.', null, 'VALIDATION');
    }
    if ($f_limite !== '' && (!insp_fecha_valida($f_limite) || $f_limite < $f_hito)) {
        return insp_resp(false, 'El plazo es inválido o anterior a la fecha del hito.', null, 'VALIDATION');
    }
    if ($importe !== null && $importe < 0) {
        return insp_resp(false, 'El importe no puede ser negativo.', null, 'VALIDATION');
    }
    $f_limite_db = $f_limite === '' ? null : $f_limite;
    $desc = mb_substr($desc, 0, 255);

    $st = mysqli_prepare($conexion, "INSERT INTO estudio__inspecciones_hitos (empresa_id, inspeccion_id, tipo_hito, f_hito, f_limite, descripcion, importe, usuario_id)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, "iissssdi", $empresa_id, $inspeccion_id, $tipo, $f_hito, $f_limite_db, $desc, $importe, $usuario_id);
    $ok = mysqli_stmt_execute($st);
    $hito_id = (int) mysqli_insert_id($conexion);
    mysqli_stmt_close($st);
    if (!$ok) {
        return insp_resp(false, 'No se pudo registrar el hito.', null, 'DB_ERROR');
    }

    $tarea_id = null;
    if (!empty($d['crear_tarea']) && $f_limite_db !== null) {
        $r = intval($d['usuario_r_id'] ?? 0) ?: intval($insp['usuario_r_id'] ?? 0) ?: $usuario_id;
        $t = tarea_raci_guardar($conexion, $empresa_id, [
            'cliente_id' => (int) $insp['cliente_id'],
            'titulo' => mb_substr('Inspección ' . ($insp['numero_acta'] ?: '#' . $inspeccion_id) . ': ' . $desc, 0, 150),
            'descripcion' => 'Generada desde el seguimiento de inspecciones (hito #' . $hito_id . ').',
            'f_inicio' => date('Y-m-d'),
            'f_limite' => $f_limite_db,
            'usuario_r_id' => $r,
            'usuario_a_id' => $usuario_id,
        ], $usuario_id);
        if (!empty($t['success'])) {
            $tarea_id = (int) $t['data']['tarea_id'];
            $up = mysqli_prepare($conexion, "UPDATE estudio__inspecciones_hitos SET tarea_id = ? WHERE empresa_id = ? AND hito_id = ?");
            mysqli_stmt_bind_param($up, "iii", $tarea_id, $empresa_id, $hito_id);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
        } else {
            return insp_resp(true, 'Hito registrado, pero no se pudo crear la tarea: ' . ($t['message'] ?? ''), ['hito_id' => $hito_id, 'tarea_id' => null]);
        }
    }
    return insp_resp(true, $tarea_id ? 'Hito registrado y tarea creada.' : 'Hito registrado.', ['hito_id' => $hito_id, 'tarea_id' => $tarea_id]);
}

function inspeccion_hito_cumplir(mysqli $conexion, int $empresa_id, int $hito_id, bool $cumplido): array
{
    $v = $cumplido ? 1 : 0;
    $st = mysqli_prepare($conexion, "UPDATE estudio__inspecciones_hitos SET cumplido = ? WHERE empresa_id = ? AND hito_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, "iii", $v, $empresa_id, $hito_id);
    mysqli_stmt_execute($st);
    $ok = mysqli_stmt_errno($st) === 0;
    mysqli_stmt_close($st);
    return $ok ? insp_resp(true, 'Hito actualizado.') : insp_resp(false, 'No se pudo actualizar el hito.', null, 'DB_ERROR');
}
