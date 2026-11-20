<?php
/**
 * Asistente IA: sugerencias de la pantalla de bienvenida, calculadas con los datos de la empresa y el perfil del usuario.
 *
 *  - pendientes: registros en Borrador / Pendiente de aprobación que el perfil puede hacer avanzar (con los botones del
 *    ciclo de vida, asistente_ciclo.php) y obligaciones confirmadas que vencen pronto sin pagar (como pedido a la IA).
 *  - repetir: operaciones de los últimos ASIS_SUG_DIAS días agrupadas (obligaciones manuales, comprobantes MC y la copia
 *    de las facturas del mes anterior) para repetir las mensuales; las ya cargadas este mes van al final.
 *  - frecuentes: pedidos que el usuario repitió en el asistente en ese período.
 *  - ejemplos: los del catálogo que el perfil puede ejecutar.
 * Todo filtra por empresa y por los permisos del perfil (asis_puede / asis_tiene_acceso_pagina).
 */

const ASIS_SUG_DIAS = 60;
const ASIS_SUG_MAX_PENDIENTES = 12;
const ASIS_SUG_MAX_REPETIR = 8;

/** Catálogo de ejemplos: texto, página y función que exige (null = cualquiera con acceso al asistente). */
const ASIS_SUG_EJEMPLOS = [
    ['texto' => '¿Cómo cargo el pago de un sueldo o un FCI?', 'pagina' => null, 'funcion' => null],
    ['texto' => 'Registrá el sueldo de un empleado y armá la orden de pago', 'pagina' => 'obligaciones_pago', 'funcion' => 'agregar'],
    ['texto' => 'Consultá la cuenta corriente de un cliente y sus saldos a favor', 'pagina' => 'clientes_ctacte', 'funcion' => 'consultar'],
    ['texto' => 'Factura a Ferretería López con 10 tornillos 8 mm y 2 cajas de clavos', 'pagina' => 'facturas', 'funcion' => 'agregar'],
    ['texto' => 'Cobrale a un cliente sus facturas pendientes por transferencia', 'pagina' => 'cobranzas', 'funcion' => 'agregar'],
    ['texto' => 'Conciliá los movimientos del extracto bancario de este mes', 'pagina' => 'bancos_conciliacion', 'funcion' => 'agregar'],
    ['texto' => 'Mostrame el flujo de caja y la posición de IVA del mes', 'pagina' => 'cont_asientos', 'funcion' => 'consultar'],
    ['texto' => 'Crear cuenta Cuenta Particular Socios y dos subcuentas particulares', 'pagina' => 'plan_cuentas', 'funcion' => 'agregar'],
    ['texto' => 'Dar de alta este proveedor (adjunto la constancia de ARCA)', 'pagina' => 'proveedores', 'funcion' => 'agregar', 'adjuntar' => true],
];

function asis_sugerencias(array $ctx): array
{
    return [
        'pendientes' => asis_sug_pendientes($ctx),
        'repetir'    => asis_sug_repetir($ctx),
        'frecuentes' => asis_sug_frecuentes($ctx),
        'ejemplos'   => asis_sug_ejemplos($ctx),
    ];
}

function asis_sug_puede(array $ctx, ?string $pagina, ?string $funcion): bool
{
    if ($pagina === null) return true;
    return $funcion === 'consultar' ? asis_tiene_acceso_pagina($ctx, $pagina) : asis_puede($ctx, $pagina, (string)$funcion);
}

function asis_sug_importe($v): string
{
    return fmt_moneda(floatval($v));
}

function asis_sug_fecha(?string $f): string
{
    return $f ? date('d/m/Y', strtotime($f)) : '';
}

// ---------------------------------------------------------------------------------------------------
// Pendientes
// ---------------------------------------------------------------------------------------------------

/** Item de un registro con ciclo: datos para la tarjeta y acciones del perfil; null si el perfil no puede avanzarlo. */
function asis_sug_item_ciclo(array $ctx, string $clave, array $f, array &$cache_acciones): ?array
{
    $est = intval($f['est']);
    $k = "$clave-$est";
    if (!isset($cache_acciones[$k])) $cache_acciones[$k] = asis_ciclo_acciones($ctx, $clave, $est);
    if (!$cache_acciones[$k]) return null;
    $r = asis_ciclo_leer($ctx, $clave, intval($f['id']));
    if (!$r) return null;
    $detalle = array_values(array_filter([trim((string)($f['entidad'] ?? '')), asis_sug_importe($f['importe']), asis_sug_fecha($f['fecha'] ?? null)]));
    return ['tipo' => 'registro', 'clave' => $clave, 'id' => intval($f['id']), 'nombre' => ASIS_CICLOS[$clave]['nombre'],
            'numero' => $r['numero'], 'estado_id' => $r['estado_id'], 'estado' => $r['estado'],
            'detalle' => implode(' · ', $detalle), 'concepto' => mb_substr(trim((string)($f['concepto'] ?? '')), 0, 120),
            'acciones' => $cache_acciones[$k]];
}

function asis_sug_pendientes(array $ctx): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $consultas = [
        'OBL' => "SELECT o.obligacion_id AS id, o.tabla_estado_registro_id AS est, e.entidad_nombre AS entidad, o.importe_total AS importe,
                         o.f_emision AS fecha, o.descripcion AS concepto
                  FROM gestion__obligaciones_pago o LEFT JOIN gestion__entidades e ON e.entidad_id = o.entidad_id
                  WHERE o.empresa_id = ? AND o.tabla_estado_registro_id IN (3, 4) ORDER BY o.f_emision DESC, o.obligacion_id DESC LIMIT 10",
        'OP'  => "SELECT p.pago_id AS id, p.tabla_estado_registro_id AS est, e.entidad_nombre AS entidad, p.importe_total AS importe,
                         p.f_emision AS fecha, p.observaciones AS concepto
                  FROM gestion__pagos p LEFT JOIN gestion__entidades e ON e.entidad_id = p.entidad_id
                  WHERE p.empresa_id = ? AND p.tabla_estado_registro_id IN (3, 4) ORDER BY p.f_emision DESC, p.pago_id DESC LIMIT 10",
        'RCX' => "SELECT r.cobranza_id AS id, r.tabla_estado_registro_id AS est, e.entidad_nombre AS entidad, r.importe_total AS importe,
                         r.f_emision AS fecha, r.observaciones AS concepto
                  FROM gestion__cobranzas r LEFT JOIN gestion__entidades e ON e.entidad_id = r.entidad_id
                  WHERE r.empresa_id = ? AND r.tabla_estado_registro_id IN (3, 4) ORDER BY r.f_emision DESC, r.cobranza_id DESC LIMIT 10",
        'MC'  => "SELECT c.comprobante_id AS id, c.tabla_estado_registro_id AS est, e.entidad_nombre AS entidad, c.importe_total AS importe,
                         c.f_emision AS fecha, REPLACE(c.observaciones, '[OTRO_MOV] ', '') AS concepto
                  FROM gestion__tesoreria_comprobantes_soporte s
                  JOIN gestion__comprobantes c ON c.comprobante_id = s.comprobante_id
                  LEFT JOIN gestion__entidades e ON e.entidad_id = s.entidad_id
                  WHERE s.empresa_id = ? AND c.tabla_estado_registro_id IN (3, 4) ORDER BY c.f_emision DESC, c.comprobante_id DESC LIMIT 10",
    ];
    $items = [];
    $cache = [];
    foreach ($consultas as $clave => $sql) {
        if (!asis_tiene_acceso_pagina($ctx, ASIS_CICLOS[$clave]['pagina'])) continue;
        foreach (asis_filas($c, $sql, 'i', $emp) as $f) {
            $it = asis_sug_item_ciclo($ctx, $clave, $f, $cache);
            if ($it) $items[] = $it;
        }
    }
    // Primero lo que espera aprobación, después los borradores; dentro, lo más reciente
    usort($items, static fn($a, $b) => [$b['estado_id'], $b['id']] <=> [$a['estado_id'], $a['id']]);
    $items = array_slice($items, 0, ASIS_SUG_MAX_PENDIENTES);

    // Obligaciones confirmadas (o canceladas en parte) que vencen en la semana y todavía no tienen OP: pedido a la IA
    if (asis_puede($ctx, 'pagos', 'agregar')) {
        $vencen = asis_filas($c, "SELECT o.obligacion_id, o.descripcion, o.importe_total, o.f_vto, e.entidad_nombre, t.nombre AS tipo
                FROM gestion__obligaciones_pago o
                LEFT JOIN gestion__entidades e ON e.entidad_id = o.entidad_id
                LEFT JOIN gestion__obligaciones_tipos t ON t.obligacion_tipo_id = o.obligacion_tipo_id
                WHERE o.empresa_id = ? AND o.tabla_estado_registro_id IN (5, 39) AND o.f_vto <= CURDATE() + INTERVAL 7 DAY
                  AND NOT EXISTS (SELECT 1 FROM gestion__pagos p WHERE p.empresa_id = o.empresa_id AND p.entidad_id = o.entidad_id
                                  AND p.tabla_estado_registro_id IN (3, 4))
                ORDER BY o.f_vto, o.obligacion_id LIMIT 5", 'i', $ctx['empresa_id']);
        foreach ($vencen as $o) {
            $vencida = $o['f_vto'] < date('Y-m-d');
            $items[] = ['tipo' => 'pedido',
                        'titulo' => "Pagar {$o['tipo']} de {$o['entidad_nombre']}",
                        'detalle' => asis_sug_importe($o['importe_total']) . ' · ' . ($vencida ? 'venció el ' : 'vence el ') . asis_sug_fecha($o['f_vto']),
                        'urgente' => $vencida,
                        'pedido' => "Armá la orden de pago de la obligación \"{$o['descripcion']}\" de {$o['entidad_nombre']} por "
                                  . asis_sug_importe($o['importe_total']) . ', imputándola.'];
        }
    }
    return $items;
}

// ---------------------------------------------------------------------------------------------------
// Repetir operaciones recientes
// ---------------------------------------------------------------------------------------------------

function asis_sug_repetir(array $ctx): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $dias = ASIS_SUG_DIAS;
    $mes = date('Y-m-01');
    $out = [];

    // Obligaciones cargadas a mano (sueldos, anticipos, servicios...): la última de cada tipo y beneficiario
    if (asis_puede($ctx, 'obligaciones_pago', 'agregar')) {
        $filas = asis_filas($c, "SELECT o.obligacion_tipo_id, o.entidad_id, t.nombre AS tipo, e.entidad_nombre,
                       COUNT(*) AS veces, MAX(o.f_emision) AS ultima,
                       SUBSTRING_INDEX(GROUP_CONCAT(o.importe_total ORDER BY o.f_emision DESC, o.obligacion_id DESC), ',', 1) AS importe,
                       SUBSTRING_INDEX(GROUP_CONCAT(o.descripcion ORDER BY o.f_emision DESC, o.obligacion_id DESC SEPARATOR '\n'), '\n', 1) AS descripcion
                FROM gestion__obligaciones_pago o
                JOIN gestion__obligaciones_tipos t ON t.obligacion_tipo_id = o.obligacion_tipo_id
                LEFT JOIN gestion__entidades e ON e.entidad_id = o.entidad_id
                WHERE o.empresa_id = ? AND o.origen = 'MANUAL' AND o.tabla_estado_registro_id NOT IN (6, 38)
                  AND o.f_emision >= CURDATE() - INTERVAL $dias DAY
                GROUP BY o.obligacion_tipo_id, o.entidad_id, t.nombre, e.entidad_nombre", 'i', $emp);
        foreach ($filas as $f) {
            $out[] = ['titulo' => "{$f['tipo']} · {$f['entidad_nombre']}",
                      'detalle' => asis_sug_importe($f['importe']) . ' · última ' . asis_sug_fecha($f['ultima']) . (intval($f['veces']) > 1 ? " · {$f['veces']} veces" : ''),
                      'ultima' => $f['ultima'], 'este_mes' => $f['ultima'] >= $mes, 'veces' => intval($f['veces']),
                      'pedido' => "Cargá la obligación de {$f['tipo']} de {$f['entidad_nombre']} por " . asis_sug_importe($f['importe'])
                                . " con fecha de hoy, igual a la del " . asis_sug_fecha($f['ultima']) . " (\"{$f['descripcion']}\")."];
        }
    }

    // Comprobantes MC (aportes, gastos, FCI...): el último de cada combinación de cuenta, contrapartida y entidad
    if (asis_puede($ctx, 'tesoreria_movimientos', 'agregar')) {
        $filas = asis_filas($c, "SELECT s.tipo_movimiento, tc.tesoreria_cuenta AS cuenta, cc.codigo AS cont_codigo, cc.nombre AS cont_nombre,
                       e.entidad_nombre, COUNT(*) AS veces, MAX(c.f_emision) AS ultima,
                       SUBSTRING_INDEX(GROUP_CONCAT(c.importe_total ORDER BY c.f_emision DESC, c.comprobante_id DESC), ',', 1) AS importe,
                       SUBSTRING_INDEX(GROUP_CONCAT(REPLACE(c.observaciones, '[OTRO_MOV] ', '') ORDER BY c.f_emision DESC, c.comprobante_id DESC SEPARATOR '\n'), '\n', 1) AS concepto
                FROM gestion__tesoreria_comprobantes_soporte s
                JOIN gestion__comprobantes c ON c.comprobante_id = s.comprobante_id
                LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = s.tesoreria_cuenta_id
                LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = s.cont_cuenta_contrapartida_id
                LEFT JOIN gestion__entidades e ON e.entidad_id = s.entidad_id
                WHERE s.empresa_id = ? AND c.tabla_estado_registro_id NOT IN (6, 38) AND c.f_emision >= CURDATE() - INTERVAL $dias DAY
                GROUP BY s.tipo_movimiento, s.tesoreria_cuenta_id, s.cont_cuenta_contrapartida_id, s.entidad_id,
                         tc.tesoreria_cuenta, cc.codigo, cc.nombre, e.entidad_nombre", 'i', $emp);
        foreach ($filas as $f) {
            $mov = strtolower((string)$f['tipo_movimiento']) === 'egreso' ? 'egreso' : 'ingreso';
            $contra = trim("{$f['cont_codigo']} {$f['cont_nombre']}");
            $out[] = ['titulo' => ucfirst($mov) . ' · ' . ($f['concepto'] ?: $contra),
                      'detalle' => asis_sug_importe($f['importe']) . " · {$f['cuenta']}" . ($f['entidad_nombre'] ? " · {$f['entidad_nombre']}" : '')
                                 . ' · último ' . asis_sug_fecha($f['ultima']),
                      'ultima' => $f['ultima'], 'este_mes' => $f['ultima'] >= $mes, 'veces' => intval($f['veces']),
                      'pedido' => "Registrá un $mov de " . asis_sug_importe($f['importe']) . " en {$f['cuenta']} contra la cuenta $contra"
                                . ($f['entidad_nombre'] ? " a nombre de {$f['entidad_nombre']}" : '') . " por \"{$f['concepto']}\" con fecha de hoy."];
        }
    }

    // Facturación mensual: copiar las facturas emitidas el mes anterior
    if (asis_puede($ctx, 'facturas', 'agregar')) {
        $desde = date('Y-m-01', strtotime('first day of last month'));
        $hasta = date('Y-m-t', strtotime('first day of last month'));
        $f = asis_fila($c, "SELECT COUNT(*) AS n, COALESCE(SUM(importe_total), 0) AS total FROM gestion__ventas_facturas
                WHERE empresa_id = ? AND f_emision BETWEEN ? AND ? AND tabla_estado_registro_id NOT IN (3, 6, 38)", 'iss', $emp, $desde, $hasta);
        if (intval($f['n'] ?? 0) > 0) {
            $nombre_mes = asis_sug_mes($desde);
            $ya = asis_fila($c, "SELECT COUNT(*) AS n FROM gestion__ventas_facturas WHERE empresa_id = ? AND f_emision >= ? AND tabla_estado_registro_id NOT IN (6, 38)",
                            'is', $emp, $mes);
            $out[] = ['titulo' => "Facturación de $nombre_mes",
                      'detalle' => "{$f['n']} factura(s) por " . asis_sug_importe($f['total']) . (intval($ya['n'] ?? 0) > 0 ? " · este mes ya hay {$ya['n']}" : ''),
                      'ultima' => $hasta, 'este_mes' => intval($ya['n'] ?? 0) >= intval($f['n']), 'veces' => intval($f['n']),
                      'pedido' => "Copiar las facturas de $nombre_mes con un 0 % de aumento"];
        }
    }

    // Primero lo que todavía no se cargó este mes; después lo más repetido y lo más reciente
    usort($out, static fn($a, $b) => [$a['este_mes'], -$a['veces'], $b['ultima']] <=> [$b['este_mes'], -$b['veces'], $a['ultima']]);
    return array_slice($out, 0, ASIS_SUG_MAX_REPETIR);
}

function asis_sug_mes(string $fecha): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $meses[intval(date('n', strtotime($fecha))) - 1] . ' ' . date('Y', strtotime($fecha));
}

// ---------------------------------------------------------------------------------------------------
// Frecuentes y ejemplos
// ---------------------------------------------------------------------------------------------------

/** Pedidos del propio usuario (en este módulo) repetidos en el período; las respuestas cortas a opciones no cuentan. */
function asis_sug_frecuentes(array $ctx): array
{
    $dias = ASIS_SUG_DIAS;
    $filas = asis_filas($ctx['c'], "SELECT m.texto, COUNT(*) AS veces, MAX(m.fecha) AS ultima
            FROM gestion__ia_asistente_mensajes m
            JOIN gestion__ia_asistente_conversaciones cv ON cv.conversacion_id = m.conversacion_id
            WHERE m.empresa_id = ? AND cv.usuario_id = ? AND cv.modulo_id = ? AND m.rol = 'USUARIO'
              AND m.fecha >= NOW() - INTERVAL $dias DAY AND CHAR_LENGTH(m.texto) >= 25
            GROUP BY m.texto HAVING veces >= 2 ORDER BY veces DESC, ultima DESC LIMIT 5",
            'iii', $ctx['empresa_id'], $ctx['usuario_id'], $ctx['modulo_id']);
    return array_map(static fn($f) => ['texto' => $f['texto'], 'veces' => intval($f['veces'])], $filas);
}

function asis_sug_ejemplos(array $ctx): array
{
    $out = [];
    foreach (ASIS_SUG_EJEMPLOS as $e) {
        if (asis_sug_puede($ctx, $e['pagina'], $e['funcion'])) $out[] = ['texto' => $e['texto'], 'adjuntar' => !empty($e['adjuntar'])];
    }
    return $out;
}
