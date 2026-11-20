<?php
/**
 * Asistente IA: ciclo de vida de los registros que el asistente dejó guardados (Comprobantes MC de tesorería,
 * Órdenes de Pago, Obligaciones de Pago y Cobranzas).
 *
 * La tarjeta de una propuesta confirmada muestra el estado actual del registro y un botón por cada acción posible:
 *  - Las acciones salen de la configuración de la página (conf__paginas_funciones): la función cuyo estado de origen
 *    es el estado actual del registro y que pasa a otro estado, asignada a alguno de los perfiles del usuario
 *    (asis_puede). Cambiar la configuración o el perfil cambia los botones.
 *  - Cada acción se ejecuta con la misma función del modelo que usa la pantalla (motor de aprobaciones, asientos y
 *    controles incluidos). El servidor revalida estado y permiso antes de ejecutar.
 */

/** Registros con ciclo: página (clave de $ctx['paginas']), nombre, acciones que sabe ejecutar el asistente. */
const ASIS_CICLOS = [
    'MC'  => ['pagina' => 'tesoreria_movimientos', 'nombre' => 'Comprobante',
              'acciones' => ['enviar_aprobacion', 'confirmar', 'aprobar', 'rechazar', 'eliminar', 'anular']],
    'OP'  => ['pagina' => 'pagos', 'nombre' => 'Orden de Pago',
              'acciones' => ['confirmar', 'aprobar', 'rechazar', 'eliminar', 'anular']],
    'OBL' => ['pagina' => 'obligaciones_pago', 'nombre' => 'Obligación',
              'acciones' => ['confirmar', 'aprobar', 'rechazar', 'eliminar', 'anular']],
    'RCX' => ['pagina' => 'cobranzas', 'nombre' => 'Cobranza',
              'acciones' => ['confirmar', 'eliminar', 'anular']],
];

/** Acciones que piden motivo: [obligatorio]. */
const ASIS_CICLO_MOTIVO = ['rechazar' => false, 'anular' => true];

const ASIS_CICLO_CLASES = [
    'confirmar' => 'btn-success', 'aprobar' => 'btn-success', 'enviar_aprobacion' => 'btn-outline-warning',
    'rechazar' => 'btn-outline-warning', 'eliminar' => 'btn-outline-danger', 'anular' => 'btn-outline-danger',
];

/** Registro con ciclo al que apunta una propuesta confirmada: ['clave' => 'OP', 'id' => 92] o null. */
function asis_ciclo_registro(array $p): ?array
{
    if (($p['estado'] ?? '') !== 'CONFIRMADA') return null;
    $d = (array)($p['datos'] ?? []);
    $reg = intval($p['registro_id'] ?? 0);
    switch ($p['tipo']) {
        case 'MOV_TESORERIA':
            return strtoupper((string)($d['tipo'] ?? '')) !== 'TRANSFERENCIA' && $reg > 0 ? ['clave' => 'MC', 'id' => $reg] : null;
        case 'ESTADO_TESO_MC':
            return intval($d['comprobante_id'] ?? 0) > 0 ? ['clave' => 'MC', 'id' => intval($d['comprobante_id'])] : null;
        case 'ORDEN_PAGO':
            return $reg > 0 ? ['clave' => 'OP', 'id' => $reg] : null;
        case 'COBRANZA':
            return $reg > 0 ? ['clave' => 'RCX', 'id' => $reg] : null;
        case 'OBLIGACION_PAGO':
            // Con varias obligaciones el registro es solo la primera: el ciclo de cada una se sigue en su pantalla
            return $reg > 0 && count((array)($d['obligaciones'] ?? [])) === 1 ? ['clave' => 'OBL', 'id' => $reg] : null;
    }
    return null;
}

/** Estado actual y número del registro; null si no existe en la empresa. */
function asis_ciclo_leer(array $ctx, string $clave, int $id): ?array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    if ($clave === 'MC') {
        require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
        require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
        $r = obtenerOtroMovimientoPorId($c, $id, $emp);
        return $r ? ['estado_id' => intval($r['tabla_estado_registro_id']), 'estado' => (string)$r['estado_registro'],
                     'numero' => (string)($r['numero_formateado'] ?? '')] : null;
    }
    $tablas = ['OP' => ['gestion__pagos', 'pago_id'], 'OBL' => ['gestion__obligaciones_pago', 'obligacion_id'],
               'RCX' => ['gestion__cobranzas', 'cobranza_id']];
    if (!isset($tablas[$clave])) return null;
    [$tabla, $pk] = $tablas[$clave];
    $r = asis_fila($c, "SELECT x.tabla_estado_registro_id AS est, er.estado_registro, ct.codigo, c.comprobante_pv, c.comprobante_nro
            FROM $tabla x
            LEFT JOIN gestion__comprobantes c ON c.comprobante_id = x.comprobante_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = x.tabla_estado_registro_id
            WHERE x.$pk = ? AND x.empresa_id = ?", 'ii', $id, $emp);
    if (!$r) return null;
    $nro = intval($r['comprobante_nro'] ?? 0);
    $numero = trim((string)($r['codigo'] ?? '') . ' ' . ($nro > 0 ? sprintf('%04d-%08d', intval($r['comprobante_pv']), $nro) : "(borrador #$id)"));
    return ['estado_id' => intval($r['est']), 'estado' => (string)($r['estado_registro'] ?? ''), 'numero' => $numero];
}

/** Acciones de la página para el estado, que el asistente sabe ejecutar y el perfil del usuario tiene asignadas. */
function asis_ciclo_acciones(array $ctx, string $clave, int $estado): array
{
    $cfg = ASIS_CICLOS[$clave] ?? null;
    $pagina = intval($ctx['paginas'][$cfg['pagina'] ?? ''] ?? 0);
    if (!$cfg || $pagina <= 0) return [];
    $filas = asis_filas($ctx['c'], "SELECT accion_js, nombre_funcion, descripcion FROM conf__paginas_funciones
            WHERE pagina_id = ? AND tabla_estado_registro_id = 1 AND tabla_estado_registro_origen_id = ?
              AND tabla_estado_registro_destino_id <> tabla_estado_registro_origen_id
            ORDER BY orden, pagina_funcion_id", 'ii', $pagina, $estado);
    $out = [];
    foreach ($filas as $f) {
        $a = (string)$f['accion_js'];
        if (isset($out[$a]) || !in_array($a, $cfg['acciones'], true) || !asis_puede($ctx, $cfg['pagina'], $a)) continue;
        $out[$a] = ['accion' => $a, 'texto' => (string)$f['nombre_funcion'], 'detalle' => (string)($f['descripcion'] ?: $f['nombre_funcion']),
                    'clase' => ASIS_CICLO_CLASES[$a] ?? 'btn-outline-secondary',
                    'motivo' => array_key_exists($a, ASIS_CICLO_MOTIVO) ? (ASIS_CICLO_MOTIVO[$a] ? 'obligatorio' : 'opcional') : null];
    }
    return array_values($out);
}

/** Lo que muestra la tarjeta: registro, estado y acciones; null si la propuesta no tiene registro con ciclo. */
function asis_ciclo_estado(array $ctx, array $p): ?array
{
    $reg = asis_ciclo_registro($p);
    if (!$reg || !asis_tiene_acceso_pagina($ctx, ASIS_CICLOS[$reg['clave']]['pagina'])) return null;
    $r = asis_ciclo_leer($ctx, $reg['clave'], $reg['id']);
    if (!$r) return null;
    return $reg + $r + ['nombre' => ASIS_CICLOS[$reg['clave']]['nombre'], 'acciones' => asis_ciclo_acciones($ctx, $reg['clave'], $r['estado_id'])];
}

/** Ejecuta la acción con la función del modelo de la pantalla. Devuelve el mensaje del resultado. */
function asis_ciclo_ejecutar_modelo(array $ctx, string $clave, int $id, string $accion, string $motivo): string
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];
    $dir = $ctx['modulo_dir'];
    $pagina = intval($ctx['paginas'][ASIS_CICLOS[$clave]['pagina']] ?? 0);
    try {
        switch ($clave) {
            case 'MC':
                return asis_grabar_estado_comprobante_tesoreria($ctx, ['comprobante_id' => $id, 'accion' => $accion, 'motivo' => $motivo])[1];
            case 'OP':
                require_once $dir . '/tesoreria_core_model.php';
                require_once $dir . '/cobranzas_model.php';
                require_once $dir . '/pagos_model.php';
                switch ($accion) {
                    // Si la empresa exige aprobación de OP queda pendiente; si no, se emite (igual que la pantalla)
                    case 'confirmar': $r = pagoSolicitarConfirmacion($c, $id, $emp, $uid); break;
                    case 'aprobar':   $r = pagoAprobar($c, $id, $emp, $uid, $pagina); break;
                    case 'rechazar':  $r = pagoRechazar($c, $id, $emp, $uid, $pagina, $motivo); break;
                    case 'anular':    $r = anularPago($c, $id, $emp, $uid, $motivo); break;
                    case 'eliminar':  $r = eliminarPagoBorrador($c, $id, $emp, $uid); break;
                }
                break;
            case 'RCX':
                require_once $dir . '/tesoreria_core_model.php';
                require_once $dir . '/cobranzas_model.php';
                switch ($accion) {
                    case 'confirmar': $r = confirmarCobranza($c, $id, $emp, $uid); break;
                    case 'anular':    $r = anularCobranza($c, $id, $emp, $uid, $motivo); break;
                    case 'eliminar':  $r = eliminarCobranzaBorrador($c, $id, $emp, $uid); break;
                }
                break;
            case 'OBL':
                require_once $dir . '/tesoreria_core_model.php';
                require_once __DIR__ . '/../obligaciones/obligaciones_service.php';
                switch ($accion) {
                    case 'confirmar': $r = obl_confirmar($c, $emp, $uid, $id); break;
                    case 'aprobar':   $r = obl_aprobar($c, $emp, $uid, $pagina, $id); break;
                    case 'rechazar':  obl_rechazar($c, $emp, $uid, $pagina, $id, $motivo); $r = ['mensaje' => 'Rechazada: volvió a Borrador.']; break;
                    case 'eliminar':  obl_eliminar($c, $emp, $uid, $id); $r = ['mensaje' => 'Obligación eliminada.']; break;
                    case 'anular':    obl_anular($c, $emp, $uid, $id, $motivo); $r = ['mensaje' => 'Obligación anulada.']; break;
                }
                break;
        }
    } catch (RuntimeException $ex) {
        throw $ex;
    } catch (Exception $ex) {
        // Los modelos de las pantallas tiran Exception con el mensaje para el usuario
        throw new RuntimeException($ex->getMessage());
    }
    if (!isset($r)) throw new RuntimeException('Acción no disponible.');
    if (is_array($r) && isset($r['success']) && !$r['success']) {
        throw new RuntimeException((string)($r['message'] ?? $r['error'] ?? 'No se pudo completar la acción.'));
    }
    return (string)(is_array($r) ? ($r['message'] ?? $r['mensaje'] ?? 'Listo.') : 'Listo.');
}

/**
 * Botón de la tarjeta: el usuario ejecuta una acción del ciclo de vida sobre el registro de una propuesta suya.
 * Revalida estado y permiso (asis_ciclo_acciones) bajo un bloqueo y deja el evento en la conversación.
 */
function asis_ciclo_ejecutar(array $ctx, int $propuesta_id, string $accion, string $motivo): array
{
    $p = asis_propuesta($ctx, $propuesta_id);
    if (!$p) throw new RuntimeException('La propuesta no existe.');
    $reg = asis_ciclo_registro($p);
    if (!$reg) throw new RuntimeException('Esta tarjeta no tiene un registro con ciclo de aprobación.');
    $ctx['conversacion_id'] = intval($p['conversacion_id']);
    [$texto, $est, $acc] = asis_ciclo_ejecutar_registro($ctx, $reg['clave'], $reg['id'], $accion, $motivo);
    asis_registrar_mensaje($ctx, 'EVENTO', "El usuario ejecutó '{$acc['texto']}' sobre {$est['nombre']} {$est['numero']} ({$reg['clave']} id {$reg['id']}): $texto",
                           ['propuesta_id' => $propuesta_id]);
    $pub = asis_propuesta_publica($ctx, asis_propuesta($ctx, $propuesta_id));
    $pub['mensaje_accion'] = $texto;
    return $pub;
}

/**
 * Ejecuta una acción del ciclo sobre un registro (de una propuesta o de la lista de pendientes de la bienvenida).
 * Revalida bajo bloqueo: el registro es de la empresa, el usuario accede a su pantalla, y la acción corresponde al
 * estado actual y a su perfil. Devuelve [mensaje, estado previo, acción].
 */
function asis_ciclo_ejecutar_registro(array $ctx, string $clave, int $id, string $accion, string $motivo): array
{
    if (!isset(ASIS_CICLOS[$clave]) || $id <= 0) throw new RuntimeException('Registro inválido.');
    if (!asis_tiene_acceso_pagina($ctx, ASIS_CICLOS[$clave]['pagina'])) throw new RuntimeException('No tenés acceso a la pantalla de ese registro.');
    $motivo = mb_substr(trim($motivo), 0, 250);
    if (!empty(ASIS_CICLO_MOTIVO[$accion]) && $motivo === '') throw new RuntimeException('Ingresá el motivo.');
    $lock = "asis_ciclo_{$clave}_$id";
    $l = asis_fila($ctx['c'], "SELECT GET_LOCK(?, 5) AS ok", 's', $lock);
    if (intval($l['ok'] ?? 0) !== 1) throw new RuntimeException('El registro se está actualizando.');
    try {
        $r = asis_ciclo_leer($ctx, $clave, $id);
        if (!$r) throw new RuntimeException('El registro no existe.');
        $est = $r + ['nombre' => ASIS_CICLOS[$clave]['nombre']];
        $acc = array_values(array_filter(asis_ciclo_acciones($ctx, $clave, $r['estado_id']), static fn($a) => $a['accion'] === $accion))[0] ?? null;
        if (!$acc) {
            throw new RuntimeException("{$est['nombre']} {$est['numero']} está en estado '{$est['estado']}': esa acción no está disponible para tu perfil.");
        }
        $texto = asis_ciclo_ejecutar_modelo($ctx, $clave, $id, $accion, $motivo);
        error_log("=== asis_ciclo $accion $clave $id empresa {$ctx['empresa_id']} usuario {$ctx['usuario_id']}: $texto ===");
    } finally {
        asis_fila($ctx['c'], "SELECT RELEASE_LOCK(?) AS ok", 's', $lock);
    }
    return [$texto, $est, $acc];
}
