<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_comprobantes_core.php
 * Única puerta del módulo 6 hacia gestion__comprobantes (espejo universal, §7.1 de la especificación).
 *
 * - Sucursal y punto de venta de emisión resueltos por empresa (core/empresa_config.php), nunca fijos.
 * - Numeración por empresa + punto de venta + tipo con gestion__comprobantes_numeradores FOR UPDATE
 *   (mismo patrón que cobranzas), protegida contra desincronización con los comprobantes existentes.
 * - Un comprobante por (tabla_origen_id, registro_origen_id): si ya existe se devuelve el existente,
 *   así una confirmación repetida no duplica deuda.
 * - Se emite Confirmado (5) con importe_pendiente = importe: deudas y créditos (recibos, reversiones)
 *   quedan cancelables por tesoreriaRegistrarImputacion(), que mantiene saldos y estados 39/40.
 *
 * Llamar siempre dentro de la transacción del proceso que emite.
 */

require_once __DIR__ . '/../../core/empresa_config.php';

const SINDI_COMP_CONFIRMADO = 5;
const SINDI_COMP_ANULADO = 38;

// Tablas de origen (conf__tablas, bloque 501–599 del módulo 6)
const SINDI_TABLA_DDJJ_CONCEPTOS = 524;
const SINDI_TABLA_VOLANTES = 528;
const SINDI_TABLA_RENDICIONES_LINEAS = 533;
const SINDI_TABLA_ACTAS = 534;
const SINDI_TABLA_PLANES = 538;
const SINDI_TABLA_PLANES_CUOTAS = 539;

/** Sucursal y punto de venta con los que el sindicato emite sus comprobantes. */
function sindi_contexto_emision(mysqli $conexion, int $empresa_id): array
{
    $pv = emp_punto_venta_id($conexion, $empresa_id);
    if ($pv <= 0) {
        throw new RuntimeException('La empresa no tiene un punto de venta activo. Configúrelo en Gestión > Puntos de Venta (o el punto de venta por defecto en Parámetros de Empresa).');
    }
    $stmt = mysqli_prepare($conexion, "SELECT sucursal_id FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $pv, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $sucursal = (int)($fila['sucursal_id'] ?? 0);
    if ($sucursal <= 0) {
        throw new RuntimeException("El punto de venta {$pv} no tiene sucursal asignada.");
    }
    return ['punto_venta_id' => $pv, 'sucursal_id' => $sucursal];
}

/** Próximo número para empresa + PV + tipo, con el numerador bloqueado hasta el commit. */
function sindi_proximo_numero(mysqli $conexion, int $empresa_id, int $punto_venta_id, int $comprobante_tipo_id): int
{
    $stmt = mysqli_prepare($conexion, "SELECT numerador_id, ultimo_numero FROM gestion__comprobantes_numeradores
                                       WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                                       FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $num = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$num) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero)
                                           VALUES (?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
        mysqli_stmt_execute($stmt);
        $num = ['numerador_id' => mysqli_insert_id($conexion), 'ultimo_numero' => 0];
        mysqli_stmt_close($stmt);
    }

    // Protección ante numeradores atrasados respecto de lo ya emitido
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(MAX(comprobante_nro), 0) FROM gestion__comprobantes
                                       WHERE empresa_id = ? AND comprobante_pv = ? AND comprobante_tipo_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $maxEmitido = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);

    $proximo = max((int)$num['ultimo_numero'], $maxEmitido) + 1;
    $numerador_id = (int)$num['numerador_id'];
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $proximo, $numerador_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $proximo;
}

/** Código del tipo (DFS, REC...) para armar comprobante_codigo. */
function sindi_codigo_tipo(mysqli $conexion, int $empresa_id, int $comprobante_tipo_id): string
{
    $stmt = mysqli_prepare($conexion, "SELECT codigo FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$fila) {
        throw new RuntimeException("El tipo de comprobante {$comprobante_tipo_id} no pertenece a la empresa {$empresa_id}.");
    }
    return (string)$fila['codigo'];
}

/**
 * Emite un comprobante del módulo. Claves de $d:
 *   empresa_id, tabla_origen_id, registro_origen_id, comprobante_tipo_id, entidad_id, importe (> 0),
 *   signo (1 deuda / -1 crédito), usuario_id; opcionales: entidad_sucursal_id, f_emision, f_vto, observaciones.
 * Devuelve ['comprobante_id' => int, 'numero' => string, 'nuevo' => bool].
 */
function sindi_emitir_comprobante(mysqli $conexion, array $d): array
{
    $empresa_id = (int)$d['empresa_id'];
    $tabla_origen_id = (int)$d['tabla_origen_id'];
    $registro_origen_id = (int)$d['registro_origen_id'];
    $tipo_id = (int)$d['comprobante_tipo_id'];
    $entidad_id = (int)$d['entidad_id'];
    $importe = round((float)$d['importe'], 2);
    $signo = ((int)$d['signo']) < 0 ? -1 : 1;
    $usuario_id = (int)($d['usuario_id'] ?? 0);
    $entidad_sucursal_id = !empty($d['entidad_sucursal_id']) ? (int)$d['entidad_sucursal_id'] : null;
    $f_emision = (string)($d['f_emision'] ?? date('Y-m-d'));
    $f_vto = (string)($d['f_vto'] ?? $f_emision);
    $observaciones = mb_substr((string)($d['observaciones'] ?? ''), 0, 255);

    if ($tipo_id <= 0) {
        throw new RuntimeException('Falta el tipo de comprobante (revise la configuración de comprobantes del sindicato).');
    }
    if ($importe <= 0) {
        throw new RuntimeException('El importe del comprobante debe ser mayor a cero.');
    }

    // Idempotencia por origen (UNIQUE idx_origen)
    $stmt = mysqli_prepare($conexion, "SELECT comprobante_id, comprobante_pv, comprobante_nro FROM gestion__comprobantes
                                       WHERE tabla_origen_id = ? AND registro_origen_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $tabla_origen_id, $registro_origen_id);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($existe) {
        return [
            'comprobante_id' => (int)$existe['comprobante_id'],
            'numero' => emp_numero_comprobante($conexion, $empresa_id, $existe['comprobante_pv'], $existe['comprobante_nro']),
            'nuevo' => false,
        ];
    }

    $ctx = sindi_contexto_emision($conexion, $empresa_id);
    $pv = $ctx['punto_venta_id'];
    $sucursal_id = $ctx['sucursal_id'];
    $nro = sindi_proximo_numero($conexion, $empresa_id, $pv, $tipo_id);
    $numero = emp_numero_comprobante($conexion, $empresa_id, $pv, $nro);
    $codigo = sindi_codigo_tipo($conexion, $empresa_id, $tipo_id) . ' ' . $numero;
    $moneda_id = emp_moneda_base_id($conexion, $empresa_id);
    if ($moneda_id <= 0) {
        throw new RuntimeException('La empresa no tiene moneda base configurada.');
    }
    $estado = SINDI_COMP_CONFIRMADO;
    $modulo = 'sindicatos';

    $sql = "INSERT INTO gestion__comprobantes (
                tabla_origen_id, registro_origen_id, modulo, empresa_id, sucursal_id, comprobante_tipo_id,
                comprobante_pv, comprobante_nro, comprobante_codigo, entidad_id, entidad_sucursal_id,
                f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                importe_bruto, importe_neto, importe_total, importe_pendiente, signo,
                tabla_estado_registro_id, observaciones, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iisiiiiisiisssiddddiisi",
        $tabla_origen_id, $registro_origen_id, $modulo, $empresa_id, $sucursal_id, $tipo_id,
        $pv, $nro, $codigo, $entidad_id, $entidad_sucursal_id,
        $f_emision, $f_emision, $f_vto, $moneda_id,
        $importe, $importe, $importe, $importe, $signo,
        $estado, $observaciones, $usuario_id
    );
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('No se pudo emitir el comprobante: ' . mysqli_stmt_error($stmt));
    }
    $comprobante_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    error_log("=== sindi_emitir_comprobante: {$codigo} id {$comprobante_id} origen {$tabla_origen_id}/{$registro_origen_id} importe {$importe} ===");

    return ['comprobante_id' => $comprobante_id, 'numero' => $numero, 'nuevo' => true];
}

/**
 * Tipo de comprobante de un concepto de aporte: el de la deuda (sindi__conceptos.comprobante_tipo_id)
 * o el de su reversión por rectificativa (comprobante_tipo_reversion_id). Error explícito si falta.
 */
function sindi_tipo_comprobante_concepto(mysqli $conexion, int $empresa_id, int $concepto_id, bool $reversion = false): int
{
    $col = $reversion ? 'comprobante_tipo_reversion_id' : 'comprobante_tipo_id';
    $stmt = mysqli_prepare($conexion, "SELECT codigo, {$col} AS tipo FROM sindi__conceptos WHERE concepto_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $concepto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tipo = (int)($fila['tipo'] ?? 0);
    if ($tipo <= 0) {
        $codigo = $fila['codigo'] ?? $concepto_id;
        throw new RuntimeException("El concepto {$codigo} no tiene tipo de comprobante " . ($reversion ? 'de reversión ' : '') . 'configurado (Conceptos de Aporte).');
    }
    return $tipo;
}

/** Cuenta contable de recurso de un concepto (sindi__conceptos.cuenta_concepto_codigo). Error explícito si falta. */
function sindi_cuenta_recurso_concepto(mysqli $conexion, int $empresa_id, int $concepto_id): int
{
    $stmt = mysqli_prepare($conexion, "SELECT codigo, cuenta_concepto_codigo FROM sindi__conceptos WHERE concepto_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $concepto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $concepto = (string)($fila['cuenta_concepto_codigo'] ?? '');
    $cuenta = $concepto !== '' ? emp_cuenta_concepto_id($conexion, $empresa_id, $concepto) : 0;
    if ($cuenta <= 0) {
        throw new RuntimeException("Falta la cuenta contable del concepto " . ($fila['codigo'] ?? $concepto_id) . " ({$concepto}) en Tesorería > Conceptos Contables.");
    }
    return $cuenta;
}

/** Saldo pendiente de un comprobante de la empresa (caché que mantiene tesorería), con la fila bloqueada. */
function sindi_saldo_comprobante(mysqli $conexion, int $comprobante_id, int $empresa_id): float
{
    $stmt = mysqli_prepare($conexion, "SELECT importe_pendiente FROM gestion__comprobantes
                                       WHERE comprobante_id = ? AND empresa_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return round((float)($fila[0] ?? 0), 2);
}

/** Cuenta contable por concepto de empresa, con error explícito si no está configurada. */
function sindi_cuenta_obligatoria(mysqli $conexion, int $empresa_id, string $concepto): int
{
    $cuenta = emp_cuenta_concepto_id($conexion, $empresa_id, $concepto);
    if ($cuenta <= 0) {
        throw new RuntimeException("Falta la cuenta contable del concepto {$concepto} en Tesorería > Conceptos Contables.");
    }
    return $cuenta;
}
