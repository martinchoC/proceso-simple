<?php
/**
 * Reglas de edición de asientos contables (Gestión > Asientos Contables 84 / 584 y Estudio > Contabilidad del Cliente 423).
 *
 * - Un asiento no anulado ni eliminado (estados 1 Activo, 3 Borrador, 5 Confirmado) se puede editar mientras su período
 *   contable esté abierto (core/cont_periodos.php). Cerrado el período, nada.
 * - Asiento manual (tipo de asiento MAN): fecha, descripción y renglones.
 * - Asiento automático (tipo de asiento con origen 'automatico', p. ej. AUT: lo generó un comprobante o un proceso):
 *   solo se cambia la cuenta de los renglones de productos (producto_id > 0). La cuenta del cliente / proveedor, IVA,
 *   IIBB, percepciones, retenciones, caja, valores, etc., los importes, la fecha y la descripción quedan como los
 *   generó el sistema. Si el comprobante se vuelve a contabilizar, el asiento se rehace y el cambio se pierde.
 *
 * Los renglones no tienen PK: un renglón se identifica por su clave "cuenta:producto:importe" (cae_linea_clave).
 */

require_once __DIR__ . '/cont_periodos.php';
require_once __DIR__ . '/empresa_config.php';

/** Estados genéricos en los que un asiento se puede editar (los demás: anulado 2 / 4 / 38, eliminado 6). */
const CAE_ESTADOS_EDITABLES = [1, 3, 5];

function cae_filas($c, string $sql, string $tipos, array $params): array
{
    $st = mysqli_prepare($c, $sql);
    if (!$st) {
        throw new RuntimeException('Error preparando la consulta del asiento: ' . mysqli_error($c));
    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($st, $tipos, ...$params);
    }
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($st);
    return $rows;
}

/** Expresión SQL (sobre el alias del tipo de asiento) que vale 1 si el asiento es automático. */
function cae_sql_es_automatico(string $alias_tipo = 't'): string
{
    return "(CASE WHEN $alias_tipo.origen = 'automatico' OR ($alias_tipo.codigo IS NOT NULL AND $alias_tipo.codigo <> 'MAN') THEN 1 ELSE 0 END)";
}

/** Cabecera del asiento de la empresa con es_automatico; null si no existe. */
function cae_asiento($c, int $empresa_id, int $asiento_id, bool $bloquear = false): ?array
{
    $rows = cae_filas($c, "SELECT a.cont_asiento_id, a.empresa_id, a.f_asiento, a.descripcion, a.comprobante_id,
                                  a.tabla_estado_registro_id AS estado_id, a.moneda_id, a.tipo_cambio,
                                  " . cae_sql_es_automatico('t') . " AS es_automatico
                           FROM gestion__cont_asientos a
                           LEFT JOIN gestion__cont_tipos_asientos t ON t.cont_tipo_asiento_id = a.cont_tipo_asiento_id
                           WHERE a.cont_asiento_id = ? AND a.empresa_id = ?" . ($bloquear ? ' FOR UPDATE' : ''),
        'ii', [$asiento_id, $empresa_id]);
    if (!$rows) {
        return null;
    }
    $rows[0]['es_automatico'] = (int) $rows[0]['es_automatico'] === 1;
    return $rows[0];
}

/** Motivo por el que el asiento no se puede editar (anulado / eliminado o período contable cerrado); null si se puede. */
function cae_motivo_no_editable($c, int $empresa_id, array $cab): ?string
{
    if (!in_array((int) $cab['estado_id'], CAE_ESTADOS_EDITABLES, true)) {
        return 'El asiento está anulado o eliminado: no se puede editar.';
    }
    try {
        cont_periodo_exigir_abierto($c, $empresa_id, $cab['f_asiento'], 'editar el asiento');
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
    return null;
}

/** Mensaje para un cambio que un asiento automático no admite. */
function cae_msg_automatico(): string
{
    return 'Es un asiento automático: solo se puede cambiar la cuenta de los renglones de productos. '
        . 'Las cuentas del cliente / proveedor, IVA, IIBB y demás, los importes, la fecha y la descripción no se modifican.';
}

/**
 * Exige que el asiento exista, se pueda editar y sea manual (para cambiar fecha, descripción, importes o renglones).
 * Lanza RuntimeException. Devuelve la cabecera.
 */
function cae_exigir_edicion_manual($c, int $empresa_id, int $asiento_id): array
{
    $cab = cae_asiento($c, $empresa_id, $asiento_id);
    if (!$cab) {
        throw new RuntimeException('Asiento no encontrado.');
    }
    $motivo = cae_motivo_no_editable($c, $empresa_id, $cab);
    if ($motivo !== null) {
        throw new RuntimeException($motivo);
    }
    if ($cab['es_automatico']) {
        throw new RuntimeException(cae_msg_automatico());
    }
    return $cab;
}

/** Clave de un renglón (cuenta:producto:importe), con la fila de gestion__cont_asientos_detalles. */
function cae_linea_clave(array $d): string
{
    $prod = (int) ($d['producto_id'] ?? 0);
    return (int) $d['cuenta_id'] . ':' . ($prod > 0 ? $prod : '') . ':' . number_format((float) $d['importe_local'], 2, '.', '');
}

/** Si la cuenta del renglón se puede cambiar (en un automático, solo los de productos). */
function cae_linea_cuenta_editable(array $cab, array $d): bool
{
    return !$cab['es_automatico'] || (int) ($d['producto_id'] ?? 0) > 0;
}

/**
 * Cambia la cuenta de renglones del asiento. $cambios: [['clave' => 'cuenta:producto:importe', 'cuenta_id' => int], ...].
 * En un asiento automático solo los renglones de productos. Cuentas imputables y activas de la empresa. No cambia
 * importes ni el estado del asiento. Lanza RuntimeException.
 */
function cae_cambiar_cuentas($c, int $empresa_id, int $usuario_id, int $asiento_id, array $cambios): array
{
    $pedidos = [];
    foreach ($cambios as $cb) {
        $clave = trim((string) ($cb['clave'] ?? ''));
        $cuenta = intval($cb['cuenta_id'] ?? 0);
        if ($clave === '' || $cuenta <= 0) {
            continue;
        }
        if (!preg_match('/^(\d+):(\d*):(-?\d+\.\d{2})$/', $clave, $m)) {
            throw new RuntimeException('Renglón no válido. Volvé a abrir el asiento.');
        }
        $pedidos[] = ['cuenta_actual' => (int) $m[1], 'producto_id' => $m[2] === '' ? null : (int) $m[2],
                      'importe' => $m[3], 'cuenta_nueva' => $cuenta];
    }
    if (!$pedidos) {
        throw new RuntimeException('Elegí la cuenta nueva.');
    }

    $nuevas = array_values(array_unique(array_column($pedidos, 'cuenta_nueva')));
    emp_validar_cuentas_asiento($c, $empresa_id, $nuevas);
    $marcas = implode(',', array_fill(0, count($nuevas), '?'));
    $validas = cae_filas($c, "SELECT cont_cuenta_id FROM gestion__cont_cuentas
                              WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
                                AND cont_cuenta_id IN ($marcas)",
        'i' . str_repeat('i', count($nuevas)), array_merge([$empresa_id], $nuevas));
    if (count($validas) !== count($nuevas)) {
        throw new RuntimeException('La cuenta elegida no es una cuenta imputable y activa del plan de la empresa.');
    }

    mysqli_begin_transaction($c);
    try {
        $cab = cae_asiento($c, $empresa_id, $asiento_id, true);
        if (!$cab) {
            throw new RuntimeException('Asiento no encontrado.');
        }
        $motivo = cae_motivo_no_editable($c, $empresa_id, $cab);
        if ($motivo !== null) {
            throw new RuntimeException($motivo);
        }

        $st = mysqli_prepare($c, "UPDATE gestion__cont_asientos_detalles SET cuenta_id = ?
                                  WHERE cont_asiento_id = ? AND cuenta_id = ? AND producto_id <=> ?
                                    AND importe_local = CAST(? AS DECIMAL(18,2)) AND IFNULL(estado, 'activo') = 'activo'
                                  LIMIT 1");
        $cambiados = 0;
        foreach ($pedidos as $p) {
            if ($cab['es_automatico'] && (int) $p['producto_id'] <= 0) {
                throw new RuntimeException(cae_msg_automatico());
            }
            if ($p['cuenta_actual'] === $p['cuenta_nueva']) {
                continue;
            }
            $nueva = $p['cuenta_nueva'];
            $actual = $p['cuenta_actual'];
            $prod = $p['producto_id'];
            $imp = $p['importe'];
            mysqli_stmt_bind_param($st, 'iiiis', $nueva, $asiento_id, $actual, $prod, $imp);
            mysqli_stmt_execute($st);
            if (mysqli_stmt_affected_rows($st) !== 1) {
                throw new RuntimeException('El renglón ya no está en el asiento (¿lo cambió otro usuario?). Volvé a abrirlo.');
            }
            $cambiados++;
        }
        mysqli_stmt_close($st);

        if ($cambiados > 0 && $usuario_id > 0) {
            $st = mysqli_prepare($c, "UPDATE gestion__cont_asientos SET usuario_modificacion_id = ? WHERE cont_asiento_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($st, 'iii', $usuario_id, $asiento_id, $empresa_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return ['cont_asiento_id' => $asiento_id, 'cambiados' => $cambiados];
}
