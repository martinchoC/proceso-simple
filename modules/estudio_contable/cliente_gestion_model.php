<?php
/**
 * Modelo: Gestión del Cliente (ficha 360 del cliente del estudio). Solo lectura.
 * Módulo 4: Estudio Contable (cliente_gestion_model.php) - Página ID: 422
 *
 * Qué se muestra depende del servicio que el estudio le presta al cliente (estudio__clientes.nivel_servicio):
 *   PADRON   -> resumen, vencimientos, tareas, honorarios, inspecciones
 *   FISCAL   -> + IVA de los últimos meses (Mini-Gestión manual o empresa vinculada del ERP)
 *   CONTABLE -> + contabilidad del ejercicio de la empresa vinculada
 *   GESTION  -> + operación de la empresa vinculada (ventas, compras, saldos, tesorería)
 * y de dónde salen los datos (origen_datos): LOCAL = este ERP; REMOTO / EXTERNO = fuera de este servidor
 * (la sincronización está pendiente: F4), así que las solapas del ERP muestran un aviso.
 *
 * Toda lectura de la empresa vinculada exige la vinculación vigente que la empresa del cliente otorgó al estudio
 * (conf__empresas_vinculaciones, origen = cliente, destino = estudio) con permiso del área: vinculacionesVerificarPermiso().
 * Estados: vencimientos (tabla 414) 28 / 35 / 38 cerrados; tareas (tabla 419) 21 / 26 cerradas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../core/empresa_config.php';

const CG_NIVELES = ['PADRON' => 1, 'FISCAL' => 2, 'CONTABLE' => 3, 'GESTION' => 4];
const CG_AREAS = ['ventas', 'compras', 'impuestos', 'contabilidad', 'tesoreria'];

function cg_resp(bool $ok, string $msg, $data = null, ?string $err = null): array
{
    return ['success' => $ok, 'message' => $msg, 'data' => $data, 'error' => $err];
}

function cg_filas(mysqli $c, string $sql, string $tipos, array $params): array
{
    $st = mysqli_prepare($c, $sql);
    if (!$st) {
        throw new LogicException('cg_filas: ' . mysqli_error($c)); // no es RuntimeException: el AJAX no lo muestra

    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($st, $tipos, ...$params);
    }
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    $out = [];
    while ($f = mysqli_fetch_assoc($r)) {
        $out[] = $f;
    }
    mysqli_stmt_close($st);
    return $out;
}

function cg_nivel_alcanza(string $nivel, string $minimo): bool
{
    return (CG_NIVELES[$nivel] ?? 0) >= (CG_NIVELES[$minimo] ?? 99);
}

/** Clientes activos del estudio para el selector. */
function cg_clientes(mysqli $c, int $estudio_id): array
{
    return cg_filas($c, "SELECT cliente_id, razon_social, cuit, nivel_servicio, origen_datos, COALESCE(empresa_vinculada_id, 0) AS empresa_vinculada_id
                         FROM estudio__clientes WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY razon_social", 'i', [$estudio_id]);
}

/** Cliente del estudio (solo del tenant activo) o null. */
function cg_cliente(mysqli $c, int $estudio_id, int $cliente_id): ?array
{
    $f = cg_filas($c, "SELECT c.cliente_id, c.empresa_id, c.razon_social, c.cuit, c.terminacion_cuit, c.nivel_servicio, c.origen_datos,
                              c.remoto_url, COALESCE(c.empresa_vinculada_id, 0) AS empresa_vinculada_id, c.mes_cierre_ejercicio,
                              c.ingresos_brutos_tipo, c.es_empleador, c.cantidad_empleados, c.observaciones,
                              COALESCE(ct.nombre, 'General') AS tipo_cliente, cf.condicion_fiscal,
                              e.empresa AS empresa_vinculada, ur.usuario_nombre AS responsable_r, ua.usuario_nombre AS responsable_a
                       FROM estudio__clientes c
                       LEFT JOIN estudio__clientes_tipos ct ON ct.cliente_tipo_id = c.cliente_tipo_id AND ct.empresa_id = c.empresa_id
                       LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
                       LEFT JOIN conf__empresas e ON e.empresa_id = c.empresa_vinculada_id
                       LEFT JOIN conf__usuarios ur ON ur.usuario_id = c.usuario_r_defecto_id
                       LEFT JOIN conf__usuarios ua ON ua.usuario_id = c.usuario_a_defecto_id
                       WHERE c.empresa_id = ? AND c.cliente_id = ? AND c.tabla_estado_registro_id = 1 LIMIT 1", 'ii', [$estudio_id, $cliente_id]);
    if (!$f) {
        return null;
    }
    $cli = $f[0];
    $cli['empresa_vinculada_id'] = (int) $cli['empresa_vinculada_id'];
    if (!isset(CG_NIVELES[$cli['nivel_servicio']])) {
        $cli['nivel_servicio'] = 'FISCAL';
    }
    // Modo de los datos: ERP (empresa vinculada en este servidor), MANUAL (Mini-Gestión), REMOTO / EXTERNO (fuera)
    if ($cli['origen_datos'] === 'REMOTO' || $cli['origen_datos'] === 'EXTERNO') {
        $cli['modo'] = $cli['empresa_vinculada_id'] > 0 ? 'ERP' : $cli['origen_datos'];
    } else {
        $cli['modo'] = $cli['empresa_vinculada_id'] > 0 ? 'ERP' : 'MANUAL';
    }
    return $cli;
}

/** Permisos que la empresa del cliente le dio al estudio, por área (lectura / escritura). */
function cg_vinculacion(mysqli $c, array $cli): array
{
    $erp = $cli['empresa_vinculada_id'];
    $out = [];
    foreach (CG_AREAS as $a) {
        // El propio estudio como cliente: su empresa de Gestión, sin vinculación (vinculacionesVerificarPermiso da true)
        $lee = $erp > 0 && vinculacionesVerificarPermiso($c, $erp, (int) $cli['empresa_id'], $a, false);
        $escribe = $lee && vinculacionesVerificarPermiso($c, $erp, (int) $cli['empresa_id'], $a, true);
        $out[$a] = $lee ? ($escribe ? 'ESCRITURA' : 'LECTURA') : 'NO';
    }
    return $out;
}

/** Exige vinculación de lectura del área; si no, RuntimeException con un mensaje para mostrar. */
function cg_exigir_area(mysqli $c, array $cli, string $area): int
{
    $erp = $cli['empresa_vinculada_id'];
    if ($erp <= 0) {
        throw new RuntimeException('El cliente no tiene empresa en el ERP.');
    }
    if (!vinculacionesVerificarPermiso($c, $erp, (int) $cli['empresa_id'], $area, false)) {
        throw new RuntimeException("La empresa del cliente no le otorgó al estudio permiso vigente de {$area}. Se registra en Vinculaciones entre empresas.");
    }
    return $erp;
}

/** Solapas que corresponden al cliente por su servicio. */
function cg_solapas(array $cli): array
{
    $n = $cli['nivel_servicio'];
    $s = ['resumen', 'vencimientos', 'tareas', 'honorarios', 'inspecciones'];
    if (cg_nivel_alcanza($n, 'FISCAL')) {
        $s[] = 'fiscal';
    }
    if (cg_nivel_alcanza($n, 'CONTABLE')) {
        $s[] = 'contable';
    }
    if (cg_nivel_alcanza($n, 'GESTION')) {
        $s[] = 'gestion';
    }
    return $s;
}

/** Páginas del estudio a las que la ficha ofrece ir, solo si el usuario puede abrirlas. */
function cg_accesos(mysqli $c, int $usuario_id, int $estudio_id, array $cli): array
{
    $paginas = [
        'padron' => [402, 'estudio_clientes.php'],
        'vencimientos' => [403, 'vencimientos_tablero.php'],
        'tareas' => [408, 'tareas_raci.php'],
        'honorarios' => [406, 'honorarios_facturacion.php'],
        'inspecciones' => [418, 'inspecciones.php'],
        'minigestion' => [417, 'minigestion.php'],
        'cierres' => [413, 'contabilidad_cierre.php'],
        'contabilidad' => [423, 'cliente_contabilidad.php'],
    ];
    $out = [];
    foreach ($paginas as $k => [$pid, $url]) {
        if (usuario_tiene_acceso_pagina($c, $usuario_id, $estudio_id, $pid)) {
            $out[$k] = $url . '?cliente_id=' . (int) $cli['cliente_id'];
        }
    }
    // Los recibos del estudio (cobro de honorarios, con retenciones) se hacen en Cobranzas del módulo de Gestión de la
    // empresa del estudio (2 = Gestión, 5 = Gestión Normal: la primera a la que el usuario tenga acceso)
    foreach ([[105, 2, 'gestion'], [605, 5, 'gestion_normal']] as [$pid, $mod, $carpeta]) {
        if (usuario_tiene_acceso_pagina($c, $usuario_id, $estudio_id, $pid)) {
            $out['cobranzas'] = '../' . $carpeta . '/cobranzas.php?' . http_build_query(['pagina_id' => $pid, 'empresa_id' => $estudio_id, 'modulo_id' => $mod]);
            break;
        }
    }
    // Mini-Gestión (con su botón Importar de ARCA): solo clientes Fiscal / Contable (empresa simplificada).
    // Gestión completa trabaja en el módulo de Gestión de su propia empresa.
    $simplificado = in_array($cli['nivel_servicio'], ['FISCAL', 'CONTABLE'], true);
    if (!$simplificado) {
        unset($out['minigestion']);
    }
    if ($cli['empresa_vinculada_id'] <= 0 || $cli['origen_datos'] !== 'LOCAL') {
        unset($out['cierres'], $out['contabilidad']); // sin contabilidad en este ERP
    }
    $erp = $cli['empresa_vinculada_id'];
    if ($cli['nivel_servicio'] === 'GESTION' && $erp > 0
        && ajax_guard_usuario_tiene_empresa($c, $usuario_id, $erp)) {
        $mods = array_column(cg_filas($c, "SELECT modulo_id FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id IN (2, 5)", 'i', [$erp]), 'modulo_id');
        $mod = in_array(5, array_map('intval', $mods), true) ? 5 : (in_array(2, array_map('intval', $mods), true) ? 2 : 0);
        if ($mod > 0) {
            $out['empresa'] = '../' . ($mod === 5 ? 'gestion_normal' : 'gestion') . '/index.php?' . http_build_query(['empresa_id' => $erp, 'modulo_id' => $mod]);
        }
    }
    return $out;
}

/** Encabezado + indicadores generales. */
function cg_resumen(mysqli $c, int $estudio_id, int $usuario_id, array $cli): array
{
    $cid = (int) $cli['cliente_id'];
    $k = cg_filas($c, "SELECT
            (SELECT COUNT(*) FROM estudio__vencimientos_generados v WHERE v.empresa_id = ? AND v.cliente_id = ?
               AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite < CURDATE()) AS venc_vencidos,
            (SELECT COUNT(*) FROM estudio__vencimientos_generados v WHERE v.empresa_id = ? AND v.cliente_id = ?
               AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)) AS venc_proximos,
            (SELECT MIN(v.f_limite) FROM estudio__vencimientos_generados v WHERE v.empresa_id = ? AND v.cliente_id = ?
               AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite >= CURDATE()) AS proximo_venc,
            (SELECT COUNT(*) FROM estudio__tareas t WHERE t.empresa_id = ? AND t.cliente_id = ? AND t.tabla_estado_registro_id NOT IN (21, 26)) AS tareas_abiertas,
            (SELECT COUNT(*) FROM estudio__tareas t WHERE t.empresa_id = ? AND t.cliente_id = ? AND t.tabla_estado_registro_id NOT IN (21, 26)
               AND t.f_limite < CURDATE()) AS tareas_atrasadas,
            (SELECT COUNT(*) FROM estudio__inspecciones i WHERE i.empresa_id = ? AND i.cliente_id = ? AND i.tabla_estado_registro_id = 1
               AND i.estado_proceso NOT IN ('CERRADA_SIN_AJUSTE','CERRADA_CON_AJUSTE','PRESCRIPTA')) AS inspecciones_abiertas,
            (SELECT COALESCE(SUM(a.importe_base), 0) FROM estudio__clientes_abonos a WHERE a.empresa_id = ? AND a.cliente_id = ?
               AND a.tabla_estado_registro_id = 1) AS abono_mensual",
        'iiiiiiiiiiiiii', [$estudio_id, $cid, $estudio_id, $cid, $estudio_id, $cid, $estudio_id, $cid, $estudio_id, $cid, $estudio_id, $cid, $estudio_id, $cid])[0];

    $jur = cg_filas($c, "SELECT j.jurisdiccion_nombre, cj.tipo_inscripcion
                         FROM estudio__clientes_jurisdicciones cj
                         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cj.jurisdiccion_id
                         WHERE cj.empresa_id = ? AND cj.cliente_id = ? AND cj.tabla_estado_registro_id = 1
                         ORDER BY cj.tipo_inscripcion, j.jurisdiccion_nombre", 'ii', [$estudio_id, $cid]);

    $cli_pub = $cli;
    unset($cli_pub['empresa_id']);
    return [
        'cliente' => $cli_pub,
        'jurisdicciones' => $jur,
        'vinculacion' => cg_vinculacion($c, $cli),
        'solapas' => cg_solapas($cli),
        'accesos' => cg_accesos($c, $usuario_id, $estudio_id, $cli),
        'kpis' => [
            'venc_vencidos' => (int) $k['venc_vencidos'],
            'venc_proximos' => (int) $k['venc_proximos'],
            'proximo_venc' => $k['proximo_venc'],
            'tareas_abiertas' => (int) $k['tareas_abiertas'],
            'tareas_atrasadas' => (int) $k['tareas_atrasadas'],
            'inspecciones_abiertas' => (int) $k['inspecciones_abiertas'],
            'abono_mensual' => round((float) $k['abono_mensual'], 2),
        ],
    ];
}

/** Vencimientos abiertos (vencidos y de los próximos 90 días) y los últimos cerrados. */
function cg_vencimientos(mysqli $c, int $estudio_id, array $cli): array
{
    $cid = (int) $cli['cliente_id'];
    $sel = "SELECT v.vencimiento_id, io.codigo_oficial, io.nombre AS obligacion, io.organismo, j.jurisdiccion_nombre, v.periodo_fiscal,
                   v.f_limite, v.importe_a_pagar, v.f_presentacion, v.f_pago, v.tabla_estado_registro_id,
                   COALESCE(ter.tabla_estado_registro, '') AS estado
            FROM estudio__vencimientos_generados v
            JOIN estudio__impuestos_obligaciones io ON io.impuesto_obligacion_id = v.impuesto_obligacion_id
            LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = v.jurisdiccion_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 414 AND ter.estado_registro_id = v.tabla_estado_registro_id
            WHERE v.empresa_id = ? AND v.cliente_id = ?";
    $abiertos = cg_filas($c, $sel . " AND v.tabla_estado_registro_id NOT IN (28, 35, 38)
                                      AND v.f_limite <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) ORDER BY v.f_limite LIMIT 200", 'ii', [$estudio_id, $cid]);
    $cerrados = cg_filas($c, $sel . " AND v.tabla_estado_registro_id IN (28, 35) ORDER BY v.f_limite DESC LIMIT 15", 'ii', [$estudio_id, $cid]);
    return ['abiertos' => $abiertos, 'cerrados' => $cerrados];
}

/** Tareas abiertas del cliente. */
function cg_tareas(mysqli $c, int $estudio_id, array $cli): array
{
    return cg_filas($c, "SELECT t.tarea_id, t.titulo, t.f_inicio, t.f_limite, t.horas_estimadas, t.horas_reales,
                                ur.usuario_nombre AS responsable, ua.usuario_nombre AS aprobador,
                                COALESCE(ter.tabla_estado_registro, '') AS estado, t.tabla_estado_registro_id
                         FROM estudio__tareas t
                         LEFT JOIN conf__usuarios ur ON ur.usuario_id = t.usuario_r_id
                         LEFT JOIN conf__usuarios ua ON ua.usuario_id = t.usuario_a_id
                         LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 419 AND ter.estado_registro_id = t.tabla_estado_registro_id
                         WHERE t.empresa_id = ? AND t.cliente_id = ? AND t.tabla_estado_registro_id NOT IN (21, 26)
                         ORDER BY t.f_limite IS NULL, t.f_limite LIMIT 200", 'ii', [$estudio_id, (int) $cli['cliente_id']]);
}

/**
 * Honorarios: abonos vigentes, novedades sin facturar y las facturas que el estudio le emitió al cliente
 * (la entidad del estudio con el CUIT del cliente, como hace honorarios_entidad_cliente()), con su saldo.
 */
function cg_honorarios(mysqli $c, int $estudio_id, array $cli): array
{
    $cid = (int) $cli['cliente_id'];
    $abonos = cg_filas($c, "SELECT a.abono_id, hc.nombre AS concepto, a.importe_base, a.dia_facturacion, a.ultimo_periodo_facturado
                            FROM estudio__clientes_abonos a
                            LEFT JOIN estudio__honorarios_conceptos hc ON hc.concepto_id = a.concepto_id
                            WHERE a.empresa_id = ? AND a.cliente_id = ? AND a.tabla_estado_registro_id = 1
                            ORDER BY a.abono_id", 'ii', [$estudio_id, $cid]);
    $novedades = cg_filas($c, "SELECT n.novedad_id, n.periodo, hc.nombre AS concepto, n.cantidad, n.importe_total, n.descripcion
                               FROM estudio__honorarios_novedades n
                               LEFT JOIN estudio__honorarios_conceptos hc ON hc.concepto_id = n.concepto_id
                               WHERE n.empresa_id = ? AND n.cliente_id = ? AND n.facturado = 0 AND n.tabla_estado_registro_id = 1
                               ORDER BY n.periodo DESC", 'ii', [$estudio_id, $cid]);

    $facturas = [];
    $saldo = 0.0;
    $cuit = (int) preg_replace('/[^0-9]/', '', (string) $cli['cuit']);
    $tabla = cg_filas($c, "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__ventas_facturas' LIMIT 1", '', []);
    if ($cuit > 0 && $tabla) {
        $facturas = cg_filas($c, "SELECT cp.comprobante_id, cp.f_emision, ct.comprobante_tipo AS tipo, cp.comprobante_pv, cp.comprobante_nro,
                                         IF(ct.signo = '-', -1, 1) * cp.importe_total AS importe_total,
                                         IF(ct.signo = '-', -1, 1) * cp.importe_pendiente AS importe_pendiente,
                                         er.estado_registro AS estado
                                  FROM gestion__comprobantes cp
                                  JOIN gestion__entidades en ON en.entidad_id = cp.entidad_id AND en.empresa_id = cp.empresa_id
                                  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cp.comprobante_tipo_id
                                  JOIN conf__estados_registros er ON er.estado_registro_id = cp.tabla_estado_registro_id
                                  WHERE cp.empresa_id = ? AND cp.tabla_origen_id = ? AND en.cuit = ?
                                    AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')
                                  ORDER BY cp.f_emision DESC, cp.comprobante_id DESC LIMIT 24",
            'iii', [$estudio_id, (int) $tabla[0]['tabla_id'], $cuit]);
        $s = cg_filas($c, "SELECT COALESCE(SUM(IF(ct.signo = '-', -1, 1) * cp.importe_pendiente), 0) AS saldo
                           FROM gestion__comprobantes cp
                           JOIN gestion__entidades en ON en.entidad_id = cp.entidad_id AND en.empresa_id = cp.empresa_id
                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cp.comprobante_tipo_id
                           JOIN conf__estados_registros er ON er.estado_registro_id = cp.tabla_estado_registro_id
                           WHERE cp.empresa_id = ? AND cp.tabla_origen_id = ? AND en.cuit = ?
                             AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL')",
            'iii', [$estudio_id, (int) $tabla[0]['tabla_id'], $cuit]);
        $saldo = round((float) ($s[0]['saldo'] ?? 0), 2);
        foreach ($facturas as &$f) {
            $f['numero'] = emp_numero_comprobante($c, $estudio_id, (int) $f['comprobante_pv'], (int) $f['comprobante_nro']);
            unset($f['comprobante_pv'], $f['comprobante_nro']);
        }
        unset($f);
    }
    return ['abonos' => $abonos, 'novedades' => $novedades, 'facturas' => $facturas, 'saldo' => $saldo];
}

/** Inspecciones del cliente (mismo listado que la página 418). */
function cg_inspecciones(mysqli $c, int $estudio_id, array $cli): array
{
    require_once __DIR__ . '/inspecciones_model.php';
    return inspecciones_listar($c, $estudio_id, ['cliente_id' => (int) $cli['cliente_id']]);
}

/**
 * IVA de los últimos $meses meses: con la misma cuenta que Mini-Gestión (calcularLiquidacion: débito, crédito A / M,
 * percepciones de IVA y saldo a favor anterior). En modo ERP agrega el estado de la Posición de IVA de la empresa.
 */
function cg_fiscal(mysqli $c, int $estudio_id, array $cli, int $meses = 6): array
{
    if (!cg_nivel_alcanza($cli['nivel_servicio'], 'FISCAL')) {
        throw new RuntimeException('El estudio no le presta servicio fiscal a este cliente.');
    }
    if ($cli['modo'] === 'REMOTO' || $cli['modo'] === 'EXTERNO') {
        return ['modo' => $cli['modo'], 'periodos' => [], 'aviso' => 'Los comprobantes del cliente están fuera de este servidor; la sincronización todavía no está disponible.'];
    }
    $erp = 0;
    if ($cli['modo'] === 'ERP') {
        $erp = cg_exigir_area($c, $cli, 'impuestos');
        cg_exigir_area($c, $cli, 'ventas');
        cg_exigir_area($c, $cli, 'compras');
    }
    require_once __DIR__ . '/minigestion_model.php';
    $m = new MiniGestionModel($c);
    $out = [];
    for ($i = $meses; $i >= 1; $i--) {
        $t = strtotime("first day of -$i month");
        $per = (int) date('Ym', $t);
        $fila = ['periodo' => date('Y-m', $t)];
        try {
            $l = $m->calcularLiquidacion($estudio_id, (int) $cli['cliente_id'], $per, 0.0);
            $fila += [
                'debito' => $l['debito_fiscal'], 'credito' => $l['credito_fiscal'], 'percepciones' => $l['percepciones_iva'],
                'saldo_favor_anterior' => $l['saldo_favor_anterior'], 'a_pagar' => $l['saldo_a_pagar'], 'a_favor' => $l['saldo_a_favor'],
                'cant_ventas' => $l['cant_ventas'], 'cant_compras' => $l['cant_compras'],
                'liquidado' => !empty($l['vigente']),
            ];
            if ($l['vigente']) {
                $fila['a_pagar'] = round((float) $l['vigente']['saldo_a_pagar'], 2);
                $fila['a_favor'] = round((float) $l['vigente']['saldo_a_favor'], 2);
            }
        } catch (Throwable $e) {
            $fila['error'] = $e->getMessage();
        }
        if ($erp > 0) {
            $p = cg_filas($c, "SELECT saldo_a_pagar, saldo_a_favor FROM gestion__iva_posiciones
                               WHERE empresa_id = ? AND periodo = ? AND tabla_estado_registro_id = 5 LIMIT 1", 'is', [$erp, $fila['periodo']]);
            $fila['liquidado'] = (bool) $p;
            if ($p) {
                $fila['a_pagar'] = round((float) $p[0]['saldo_a_pagar'], 2);
                $fila['a_favor'] = round((float) $p[0]['saldo_a_favor'], 2);
            }
        }
        $out[] = $fila;
    }
    $borradores = 0;
    if ($erp > 0) {
        $b = cg_filas($c, "SELECT COUNT(*) AS n FROM gestion__facturas_proveedores WHERE empresa_id = ? AND tabla_estado_registro_id = 3", 'i', [$erp]);
        $borradores = (int) ($b[0]['n'] ?? 0);
    }
    return ['modo' => $cli['modo'], 'periodos' => $out, 'compras_borrador' => $borradores];
}

/**
 * Contabilidad del ejercicio en curso de la empresa vinculada: totales por rubro, resultado y meses cerrados.
 * Los rubros suman asientos vigentes (1) y en borrador (3): los de venta nacen en borrador (vfGenerarAsientoContableFactura)
 * y sin ellos el resultado no tendría ingresos. Balances y el cierre de ejercicio siguen tomando solo los vigentes.
 */
function cg_contable(mysqli $c, int $estudio_id, array $cli): array
{
    if (!cg_nivel_alcanza($cli['nivel_servicio'], 'CONTABLE')) {
        throw new RuntimeException('El estudio no le lleva la contabilidad a este cliente.');
    }
    if ($cli['modo'] !== 'ERP') {
        return ['aviso' => $cli['modo'] === 'MANUAL'
            ? 'El cliente no tiene empresa en el ERP: la contabilidad se lleva en su empresa (se crea desde el Padrón de Clientes).'
            : 'La contabilidad del cliente está fuera de este servidor; la sincronización todavía no está disponible.'];
    }
    $erp = cg_exigir_area($c, $cli, 'contabilidad');
    require_once __DIR__ . '/../../core/cont_periodos.php';
    $ej = cont_ejercicio_de_fecha($c, $erp, date('Y-m-d'));
    $r = cont_ejercicio_rango($c, $erp, $ej);

    $rubros = cg_filas($c, "SELECT LEFT(TRIM(cc.codigo), 1) AS rubro, SUM(d.importe_local) AS saldo
                            FROM gestion__cont_asientos a
                            JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id
                            JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id AND cc.empresa_id = a.empresa_id
                            WHERE a.empresa_id = ? AND a.f_asiento BETWEEN ? AND ? AND a.tabla_estado_registro_id IN (1, 3)
                            GROUP BY LEFT(TRIM(cc.codigo), 1) ORDER BY rubro", 'iss', [$erp, $r['desde'], $r['hasta']]);
    $nombres = ['1' => 'Activo', '2' => 'Pasivo', '3' => 'Patrimonio neto', '4' => 'Ingresos', '5' => 'Costos', '6' => 'Gastos'];
    $resultado = 0.0;
    foreach ($rubros as &$x) {
        $x['saldo'] = round((float) $x['saldo'], 2);
        $x['nombre'] = $nombres[$x['rubro']] ?? ('Rubro ' . $x['rubro']);
        if (in_array($x['rubro'], ['4', '5', '6'], true)) {
            $resultado += -$x['saldo']; // Haber (negativo) = ganancia
        }
    }
    unset($x);

    $cant = cg_filas($c, "SELECT SUM(tabla_estado_registro_id = 1) AS vigentes, SUM(tabla_estado_registro_id = 3) AS borradores, MAX(f_asiento) AS ultimo
                          FROM gestion__cont_asientos WHERE empresa_id = ? AND f_asiento BETWEEN ? AND ?", 'iss', [$erp, $r['desde'], $r['hasta']])[0];
    $cerrados = cg_filas($c, "SELECT anio, mes FROM gestion__cont_periodos_cierres
                              WHERE empresa_id = ? AND tabla_estado_registro_id = 5
                                AND CONCAT(anio, '-', LPAD(mes, 2, '0'), '-01') BETWEEN ? AND ?
                              ORDER BY anio, mes", 'iss', [$erp, substr($r['desde'], 0, 7) . '-01', $r['hasta']]);
    return [
        'ejercicio' => $r['texto'],
        'desde' => $r['desde'],
        'hasta' => $r['hasta'],
        'rubros' => $rubros,
        'resultado' => round($resultado, 2),
        'asientos' => (int) $cant['vigentes'],
        'asientos_borrador' => (int) $cant['borradores'],
        'ultimo_asiento' => $cant['ultimo'],
        'meses_cerrados' => array_map(function ($m) { return sprintf('%04d-%02d', $m['anio'], $m['mes']); }, $cerrados),
        'meses_ejercicio' => count($r['meses']),
    ];
}

/**
 * Operación de la empresa vinculada (servicio de gestión completa): ventas y compras netas del mes y del anterior,
 * saldos pendientes de facturas de venta y de compra, y saldos de tesorería. Cada bloque exige su área.
 */
function cg_gestion(mysqli $c, int $estudio_id, array $cli): array
{
    if (!cg_nivel_alcanza($cli['nivel_servicio'], 'GESTION')) {
        throw new RuntimeException('El estudio no le presta gestión completa a este cliente.');
    }
    if ($cli['modo'] !== 'ERP') {
        return ['aviso' => 'La gestión del cliente no está en este servidor.'];
    }
    $vinc = cg_vinculacion($c, $cli);
    $erp = $cli['empresa_vinculada_id'];
    $mes = [date('Y-m-01'), date('Y-m-d')];
    $ant = [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))];
    $out = ['vinculacion' => $vinc];

    $bloque = function (string $tabla_nombre) use ($c, $erp, $mes, $ant): array {
        $t = cg_filas($c, "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = ? LIMIT 1", 's', [$tabla_nombre]);
        if (!$t) {
            return [];
        }
        $tid = (int) $t[0]['tabla_id'];
        $sql = "SELECT COALESCE(SUM(IF(ct.signo = '-', -1, 1) * (cp.importe_total - cp.importe_iva - cp.importe_otros_impuestos) * cp.tipo_cambio), 0) AS neto,
                       COUNT(*) AS cantidad
                FROM gestion__comprobantes cp
                JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cp.comprobante_tipo_id
                JOIN conf__estados_registros er ON er.estado_registro_id = cp.tabla_estado_registro_id
                WHERE cp.empresa_id = ? AND cp.tabla_origen_id = ? AND cp.f_emision BETWEEN ? AND ?
                  AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')";
        $a = cg_filas($c, $sql, 'iiss', [$erp, $tid, $mes[0], $mes[1]])[0];
        $b = cg_filas($c, $sql, 'iiss', [$erp, $tid, $ant[0], $ant[1]])[0];
        $p = cg_filas($c, "SELECT COALESCE(SUM(IF(ct.signo = '-', -1, 1) * cp.importe_pendiente * cp.tipo_cambio), 0) AS pendiente,
                                  SUM(cp.f_vto IS NOT NULL AND cp.f_vto < CURDATE() AND cp.importe_pendiente > 0) AS vencidas
                           FROM gestion__comprobantes cp
                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cp.comprobante_tipo_id
                           JOIN conf__estados_registros er ON er.estado_registro_id = cp.tabla_estado_registro_id
                           WHERE cp.empresa_id = ? AND cp.tabla_origen_id = ? AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL')",
            'ii', [$erp, $tid])[0];
        $bor = cg_filas($c, "SELECT COUNT(*) AS n FROM gestion__comprobantes WHERE empresa_id = ? AND tabla_origen_id = ? AND tabla_estado_registro_id = 3",
            'ii', [$erp, $tid])[0];
        return [
            'mes_neto' => round((float) $a['neto'], 2), 'mes_cantidad' => (int) $a['cantidad'],
            'anterior_neto' => round((float) $b['neto'], 2), 'anterior_cantidad' => (int) $b['cantidad'],
            'pendiente' => round((float) $p['pendiente'], 2), 'vencidas' => (int) $p['vencidas'],
            'borradores' => (int) $bor['n'],
        ];
    };

    $out['ventas'] = $vinc['ventas'] !== 'NO' ? $bloque('gestion__ventas_facturas') : null;
    $out['compras'] = $vinc['compras'] !== 'NO' ? $bloque('gestion__facturas_proveedores') : null;
    $out['tesoreria'] = $vinc['tesoreria'] !== 'NO'
        ? cg_filas($c, "SELECT tc.cuenta_tipo, tc.tesoreria_cuenta, m.codigo AS moneda, tc.saldo_actual
                        FROM gestion__tesoreria_cuentas tc
                        LEFT JOIN gestion__monedas m ON m.moneda_id = tc.moneda_id
                        WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1
                        ORDER BY FIELD(tc.cuenta_tipo, 'CAJA', 'BANCO', 'VALORES', 'CUPONES'), tc.orden, tc.tesoreria_cuenta", 'i', [$erp])
        : null;
    $out['mes'] = date('Y-m');
    $out['mes_anterior'] = date('Y-m', strtotime('first day of last month'));
    return $out;
}
