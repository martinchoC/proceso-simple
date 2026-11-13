<?php
/**
 * Obligaciones de pago (gestion y gestion_normal): todo lo que la empresa tiene que pagar y no es una factura de
 * compra (sueldos, impuestos, anticipos, varias). Se cancelan con Órdenes de Pago (pagos.php).
 * Diseño: _local/docs/gestion/13_obligaciones_de_pago.md
 *
 * Tabla gestion__obligaciones_pago (conf__tablas 286), espejo en gestion__comprobantes (I_LIQ / OBL / SOL_ANT,
 * tabla_origen_id 286). Estados genéricos: 3 Borrador, 4 Pend. aprobación, 5 Pendiente de pago, 39 Pagada en
 * parte, 40 Pagada (39 / 40 los pone recalcularEstadoDestino() al imputar la OP), 6 Eliminada, 38 Anulada.
 *
 * Requiere que el módulo haya cargado antes su tesoreria_core_model.php (tesoreriaGenerarAsiento,
 * tesoreriaRegistrarImputacion, recalcularEstadoDestino). Errores para el usuario: RuntimeException.
 * Las funciones de escritura abren su transacción salvo que reciban $en_transaccion = true.
 */

require_once __DIR__ . '/../aprobaciones.php';
require_once __DIR__ . '/../empresa_config.php';
require_once __DIR__ . '/../entidades_roles.php';
require_once __DIR__ . '/../cont_periodos.php';

const OBL_TABLA_ID = 286;
const OBL_BORRADOR = 3;
const OBL_PEND_APROBACION = 4;
const OBL_CONFIRMADA = 5;
const OBL_ELIMINADA = 6;
const OBL_ANULADA = 38;
const OBL_PAGADA_PARCIAL = 39;
const OBL_PAGADA = 40;
const OBL_ROLES = ['EMP' => 'Empleado', 'PRO' => 'Proveedor', 'ORG' => 'Organismo'];
const OBL_COMPROBANTES = ['OBL' => 'Obligación de Pago', 'I_LIQ' => 'Liquidación Impositiva', 'SOL_ANT' => 'Solicitud de Anticipo'];
const OBL_ORIGENES_MANUALES = ['MANUAL', 'IMPORTACION'];
const OBL_ORIGENES_TEXTO = [
    'MANUAL' => 'Carga manual', 'IMPORTACION' => 'Importación', 'IVA_POSICION' => 'IVA - Posición Mensual',
    'PERCEPCIONES_IIBB' => 'Percepciones IIBB', 'IIBB_POSICION' => 'Ingresos Brutos - DDJJ mensual','RETENCIONES_LIQ' => 'Control Fiscal (retenciones)', 'SUELDOS' => 'Liquidación de sueldos',
];

function obl_filas(mysqli $c, string $sql, string $tipos = '', ...$params): array { return aprob_filas($c, $sql, $tipos, ...$params); }
function obl_fila(mysqli $c, string $sql, string $tipos = '', ...$params): ?array { return aprob_filas($c, $sql, $tipos, ...$params)[0] ?? null; }
function obl_exec(mysqli $c, string $sql, string $tipos = '', ...$params): int { return aprob_exec($c, $sql, $tipos, ...$params); }

function obl_fecha($f, ?string $defecto = null): ?string
{
    $f = trim((string)$f);
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $f, $m)) $f = "$m[3]-$m[2]-$m[1]";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && checkdate((int)substr($f, 5, 2), (int)substr($f, 8, 2), (int)substr($f, 0, 4))) return $f;
    return $defecto;
}

function obl_importe($v): float
{
    if (is_string($v)) {
        $v = trim(str_replace(['$', ' '], '', $v));
        // 1.234,56 (es-AR) o 1234.56
        if (preg_match('/^-?[\d.]*,\d+$/', $v)) $v = str_replace(['.', ','], ['', '.'], $v);
        else $v = str_replace(',', '', $v);
    }
    return round(floatval($v), 2);
}

// ---------------------------------------------------------------------------------------------------------
// Tipos de obligación
// ---------------------------------------------------------------------------------------------------------

function obl_tipos(mysqli $c, int $empresa_id, bool $solo_activos = true, ?bool $manual = null): array
{
    $sql = "SELECT ot.*, cd.codigo AS cuenta_debe_codigo, cd.nombre AS cuenta_debe, cp.codigo AS cuenta_pasivo_codigo, cp.nombre AS cuenta_pasivo,
                   er.estado_registro, er.codigo_estandar
              FROM gestion__obligaciones_tipos ot
              LEFT JOIN gestion__cont_cuentas cd ON cd.cont_cuenta_id = ot.cont_cuenta_debe_id
              LEFT JOIN gestion__cont_cuentas cp ON cp.cont_cuenta_id = ot.cont_cuenta_pasivo_id
              LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ot.tabla_estado_registro_id
             WHERE ot.empresa_id = ?" . ($solo_activos ? ' AND ot.tabla_estado_registro_id = 1' : '')
          . ($manual === null ? '' : ' AND ot.permite_manual = ' . ($manual ? 1 : 0)) . "
             ORDER BY ot.orden, ot.nombre";
    return obl_filas($c, $sql, 'i', $empresa_id);
}

function obl_tipo(mysqli $c, int $empresa_id, int $tipo_id): array
{
    $t = obl_fila($c, "SELECT * FROM gestion__obligaciones_tipos WHERE obligacion_tipo_id = ? AND empresa_id = ?", 'ii', $tipo_id, $empresa_id);
    if (!$t) throw new RuntimeException('Elegí un tipo de obligación de la empresa.');
    return $t;
}

function obl_tipo_por_codigo(mysqli $c, int $empresa_id, string $codigo): ?array
{
    return obl_fila($c, "SELECT * FROM gestion__obligaciones_tipos WHERE empresa_id = ? AND codigo = ?", 'is', $empresa_id, strtoupper(trim($codigo)));
}

function obl_cuenta_valida(mysqli $c, int $empresa_id, int $cuenta_id): bool
{
    return $cuenta_id > 0 && (bool)obl_fila($c,
        "SELECT 1 FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1",
        'ii', $cuenta_id, $empresa_id);
}

/** Cuentas imputables activas de la empresa (combos). */
function obl_cuentas(mysqli $c, int $empresa_id): array
{
    return obl_filas($c, "SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas
                          WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1 ORDER BY codigo", 'i', $empresa_id);
}

function obl_tipo_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    $id = intval($d['obligacion_tipo_id'] ?? 0);
    $actual = $id > 0 ? obl_tipo($c, $empresa_id, $id) : null;
    $sistema = $actual && !empty($actual['origen_sistema']);

    $nombre = trim((string)($d['nombre'] ?? ''));
    if ($nombre === '') throw new RuntimeException('Escribí el nombre del tipo.');
    $debe = intval($d['cont_cuenta_debe_id'] ?? 0);
    $pasivo = intval($d['cont_cuenta_pasivo_id'] ?? 0);
    foreach ([$debe, $pasivo] as $cta) {
        if ($cta > 0 && !obl_cuenta_valida($c, $empresa_id, $cta)) throw new RuntimeException('Elegí cuentas imputables y activas del plan de la empresa.');
    }
    $orden = max(0, min(9999, intval($d['orden'] ?? 100)));
    $debe = $debe ?: null;
    $pasivo = $pasivo ?: null;

    if ($sistema) {
        // Los tipos que genera el sistema solo cambian nombre, cuentas y orden
        obl_exec($c, "UPDATE gestion__obligaciones_tipos SET nombre = ?, cont_cuenta_debe_id = ?, cont_cuenta_pasivo_id = ?, orden = ?, usuario_modificacion_id = ?
                      WHERE obligacion_tipo_id = ? AND empresa_id = ?", 'siiiiii', $nombre, $debe, $pasivo, $orden, $usuario_id, $id, $empresa_id);
        return $id;
    }

    $codigo = strtoupper(trim((string)($d['codigo'] ?? '')));
    if (!preg_match('/^[A-Z][A-Z0-9_]{1,19}$/', $codigo)) throw new RuntimeException('El código va en mayúsculas, sin espacios (letras, números y _), hasta 20 caracteres.');
    $otro = obl_tipo_por_codigo($c, $empresa_id, $codigo);
    if ($otro && intval($otro['obligacion_tipo_id']) !== $id) throw new RuntimeException("Ya existe un tipo con el código $codigo.");
    $clase = ($d['clase'] ?? '') === 'ANTICIPO' ? 'ANTICIPO' : 'OBLIGACION';
    $rol = strtoupper(trim((string)($d['rol_entidad_codigo'] ?? '')));
    $rol = isset(OBL_ROLES[$rol]) ? $rol : null;
    $comp = strtoupper(trim((string)($d['comprobante_tipo_codigo'] ?? 'OBL')));
    if ($clase === 'ANTICIPO') $comp = 'SOL_ANT';
    elseif (!in_array($comp, ['OBL', 'I_LIQ'], true)) $comp = 'OBL';
    if (emp_comprobante_tipo_id($c, $empresa_id, $comp) <= 0) throw new RuntimeException("La empresa no tiene el tipo de comprobante $comp.");

    if ($id > 0) {
        obl_exec($c, "UPDATE gestion__obligaciones_tipos SET codigo = ?, nombre = ?, clase = ?, rol_entidad_codigo = ?, comprobante_tipo_codigo = ?,
                             cont_cuenta_debe_id = ?, cont_cuenta_pasivo_id = ?, orden = ?, usuario_modificacion_id = ?
                      WHERE obligacion_tipo_id = ? AND empresa_id = ?",
                 'sssssiiiiii', $codigo, $nombre, $clase, $rol, $comp, $debe, $pasivo, $orden, $usuario_id, $id, $empresa_id);
        return $id;
    }
    obl_exec($c, "INSERT INTO gestion__obligaciones_tipos (empresa_id, codigo, nombre, clase, rol_entidad_codigo, comprobante_tipo_codigo,
                         cont_cuenta_debe_id, cont_cuenta_pasivo_id, permite_manual, orden, usuario_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)",
             'isssssiiii', $empresa_id, $codigo, $nombre, $clase, $rol, $comp, $debe, $pasivo, $orden, $usuario_id);
    return intval(mysqli_insert_id($c));
}

function obl_tipo_estado(mysqli $c, int $empresa_id, int $usuario_id, int $id, int $estado): void
{
    $t = obl_tipo($c, $empresa_id, $id);
    if ($estado === 2 && !empty($t['origen_sistema'])) throw new RuntimeException('Este tipo lo usa el sistema al liquidar impuestos: no se puede inactivar.');
    obl_exec($c, "UPDATE gestion__obligaciones_tipos SET tabla_estado_registro_id = ?, usuario_modificacion_id = ? WHERE obligacion_tipo_id = ? AND empresa_id = ?",
             'iiii', $estado, $usuario_id, $id, $empresa_id);
}

// ---------------------------------------------------------------------------------------------------------
// Entidades y cuentas
// ---------------------------------------------------------------------------------------------------------

/** Beneficiarios posibles: entidades activas con rol de empleado, proveedor u organismo (o solo $rol). */
function obl_entidades(mysqli $c, int $empresa_id, ?string $rol = null): array
{
    $cond = $rol !== null && isset(OBL_ROLES[$rol])
        ? ent_sql_tiene_rol('e.entidad_id', $rol)
        : '(' . implode(' OR ', array_map(static fn($r) => ent_sql_tiene_rol('e.entidad_id', $r), array_keys(OBL_ROLES))) . ')';
    return obl_filas($c,
        "SELECT e.entidad_id, e.entidad_nombre, e.cuit,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'EMP') . ", 1, 0) AS es_emp,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'PRO') . ", 1, 0) AS es_pro,
                IF(" . ent_sql_tiene_rol('e.entidad_id', 'ORG') . ", 1, 0) AS es_org
           FROM gestion__entidades e
          WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND $cond
          ORDER BY e.entidad_nombre", 'i', $empresa_id);
}

function obl_validar_entidad(mysqli $c, int $empresa_id, int $entidad_id, array $tipo): array
{
    $e = obl_fila($c, "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
                  'ii', $entidad_id, $empresa_id);
    if (!$e) throw new RuntimeException('Elegí el beneficiario (una entidad activa de la empresa).');
    $rol = (string)($tipo['rol_entidad_codigo'] ?? '');
    if ($rol !== '' && !ent_tiene_rol($c, $entidad_id, $rol)) {
        throw new RuntimeException("{$e['entidad_nombre']} no tiene el rol " . (OBL_ROLES[$rol] ?? $rol) . ': asignáselo en Entidades.');
    }
    return $e;
}

/** Concepto contable por defecto de la cuenta de un rol de entidad. */
function obl_concepto_rol(string $rol): ?string
{
    return ['PRO' => 'PROVEEDORES', 'EMP' => 'EMPLEADOS', 'CLI' => 'DEUDORES_VENTAS'][$rol] ?? null;
}

/** Cuenta de la entidad para un rol: la del rol o la del concepto por defecto; 0 si no hay. */
function obl_cuenta_rol_entidad(mysqli $c, int $empresa_id, int $entidad_id, string $rol): int
{
    $cta = $entidad_id > 0 ? intval(ent_cuenta_rol($c, $entidad_id, $rol) ?? 0) : 0;
    if ($cta > 0 && obl_cuenta_valida($c, $empresa_id, $cta)) return $cta;
    $concepto = obl_concepto_rol($rol);
    return $concepto ? emp_cuenta_concepto_id($c, $empresa_id, $concepto) : 0;
}

/**
 * Cuentas de la obligación: [debe (gasto o anticipo), pasivo (lo que cancela la OP)].
 * OBLIGACION: debe = elegida > tipo > GASTOS_GENERALES; pasivo = elegido > tipo > cuenta del rol de la entidad.
 * ANTICIPO: no se contabiliza al confirmar; la OP debita la cuenta del anticipo (elegida > tipo > cuenta del rol),
 *           que conviene que sea la misma que la deuda que va a cancelar (proveedor, Sueldos a pagar).
 */
function obl_resolver_cuentas(mysqli $c, int $empresa_id, array $tipo, int $entidad_id, int $debe, int $pasivo): array
{
    $rol = (string)($tipo['rol_entidad_codigo'] ?? '');
    if (($tipo['clase'] ?? '') === 'ANTICIPO') {
        $cta = $debe ?: intval($tipo['cont_cuenta_debe_id'] ?? 0);
        if ($cta <= 0 && $rol !== '') $cta = obl_cuenta_rol_entidad($c, $empresa_id, $entidad_id, $rol);
        if (!obl_cuenta_valida($c, $empresa_id, $cta)) {
            throw new RuntimeException("Falta la cuenta contable del anticipo: elegila en Tipos de Obligación ({$tipo['nombre']}).");
        }
        return [$cta, null];
    }
    $debe = $debe ?: intval($tipo['cont_cuenta_debe_id'] ?? 0);
    if ($debe <= 0) $debe = emp_cuenta_concepto_id($c, $empresa_id, 'GASTOS_GENERALES');
    $pasivo = $pasivo ?: intval($tipo['cont_cuenta_pasivo_id'] ?? 0);
    if ($pasivo <= 0 && $rol !== '') $pasivo = obl_cuenta_rol_entidad($c, $empresa_id, $entidad_id, $rol);
    if (!obl_cuenta_valida($c, $empresa_id, $debe)) {
        throw new RuntimeException("Falta la cuenta del gasto: elegila en la obligación o en Tipos de Obligación ({$tipo['nombre']}).");
    }
    if (!obl_cuenta_valida($c, $empresa_id, $pasivo)) {
        throw new RuntimeException("Falta la cuenta del pasivo a pagar: elegila en la obligación o en Tipos de Obligación ({$tipo['nombre']}).");
    }
    return [$debe, $pasivo];
}

// ---------------------------------------------------------------------------------------------------------
// Alta, edición y transiciones
// ---------------------------------------------------------------------------------------------------------

/** Próximo número del tipo de comprobante en el PV (gestion__comprobantes_numeradores, con bloqueo). */
function obl_numerar(mysqli $c, int $empresa_id, int $comprobante_tipo_id, int $pv): int
{
    $n = obl_fila($c, "SELECT numerador_id, ultimo_numero FROM gestion__comprobantes_numeradores
                       WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ? FOR UPDATE", 'iii', $empresa_id, $pv, $comprobante_tipo_id);
    if (!$n) {
        // Primer número: arranca después del mayor ya usado (las I_LIQ viejas se numeraban por MAX + 1)
        $max = obl_fila($c, "SELECT COALESCE(MAX(comprobante_nro), 0) m FROM gestion__comprobantes WHERE empresa_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ?",
                        'iii', $empresa_id, $comprobante_tipo_id, $pv);
        obl_exec($c, "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) VALUES (?, ?, ?, ?)",
                 'iiii', $empresa_id, $pv, $comprobante_tipo_id, intval($max['m'] ?? 0));
        $n = ['numerador_id' => intval(mysqli_insert_id($c)), 'ultimo_numero' => intval($max['m'] ?? 0)];
    }
    $nro = intval($n['ultimo_numero']) + 1;
    obl_exec($c, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?", 'ii', $nro, intval($n['numerador_id']));
    return $nro;
}

/** Normaliza y valida los datos editables de una obligación. */
function obl_datos(mysqli $c, int $empresa_id, array $d, array $tipo): array
{
    $entidad = obl_validar_entidad($c, $empresa_id, intval($d['entidad_id'] ?? 0), $tipo);
    $importe = obl_importe($d['importe_total'] ?? 0);
    if ($importe <= 0) throw new RuntimeException('El importe tiene que ser mayor a cero.');
    $f_emision = obl_fecha($d['f_emision'] ?? '', date('Y-m-d'));
    $f_vto = obl_fecha($d['f_vto'] ?? '', $f_emision);
    if ($f_vto < $f_emision) {
        // Carga manual: error. Generadores (p. ej. la posición de IVA, que emite al fin de mes): vence ese día.
        if (in_array(strtoupper((string)($d['origen'] ?? 'MANUAL')), OBL_ORIGENES_MANUALES, true)) {
            throw new RuntimeException('El vencimiento no puede ser anterior a la fecha.');
        }
        $f_vto = $f_emision;
    }
    $periodo = trim((string)($d['periodo_fiscal'] ?? $d['periodo'] ?? ''));
    if ($periodo !== '' && !preg_match('/^\d{4}(-\d{2})?$/', $periodo)) throw new RuntimeException('El período va como AAAA-MM.');
    $sucursal = intval($d['sucursal_id'] ?? 0);
    if ($sucursal <= 0) {
        $s = obl_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id LIMIT 1", 'i', $empresa_id);
        $sucursal = intval($s['sucursal_id'] ?? 0);
    } elseif (!obl_fila($c, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal, $empresa_id)) {
        throw new RuntimeException('Elegí una sucursal de la empresa.');
    }
    if ($sucursal <= 0) throw new RuntimeException('La empresa no tiene sucursales.');
    $descripcion = trim((string)($d['descripcion'] ?? ''));
    if ($descripcion === '') $descripcion = $tipo['nombre'] . ($periodo !== '' ? " $periodo" : '') . ' - ' . $entidad['entidad_nombre'];
    $debe = intval($d['cont_cuenta_debe_id'] ?? 0);
    $pasivo = intval($d['cont_cuenta_pasivo_id'] ?? 0);
    foreach ([$debe, $pasivo] as $cta) {
        if ($cta > 0 && !obl_cuenta_valida($c, $empresa_id, $cta)) throw new RuntimeException('Elegí cuentas imputables y activas del plan de la empresa.');
    }
    return [
        'entidad_id' => intval($entidad['entidad_id']), 'entidad_nombre' => $entidad['entidad_nombre'], 'sucursal_id' => $sucursal,
        'f_emision' => $f_emision, 'f_vto' => $f_vto, 'periodo_fiscal' => $periodo !== '' ? $periodo : null,
        'descripcion' => mb_substr($descripcion, 0, 200), 'referencia' => mb_substr(trim((string)($d['referencia'] ?? '')), 0, 100) ?: null,
        'nro_vep' => mb_substr(trim((string)($d['nro_vep'] ?? '')), 0, 50) ?: null,
        'nro_ddjj' => mb_substr(trim((string)($d['nro_ddjj'] ?? '')), 0, 50) ?: null,
        'observaciones' => trim((string)($d['observaciones'] ?? '')) ?: null,
        'importe_total' => $importe, 'cont_cuenta_debe_id' => $debe ?: null, 'cont_cuenta_pasivo_id' => $pasivo ?: null,
    ];
}

/**
 * Alta de una obligación (manual, importación o generadores del sistema).
 * $d: obligacion_tipo_id o tipo_codigo, entidad_id, importe_total, f_emision, f_vto, periodo_fiscal, descripcion,
 *     referencia, nro_vep, nro_ddjj, sucursal_id, cont_cuenta_debe_id, cont_cuenta_pasivo_id, impuesto_tipo_id,
 *     jurisdiccion_id, origen, origen_registro_id, importacion_id.
 * $d['confirmar']: 'NO' (queda en Borrador), 'SI' (pasa por el motor de aprobaciones) o 'FORZAR' (generadores del
 *     sistema: confirmada sin aprobación). $d['sin_asiento']: el llamador hace su propio asiento.
 */
function obl_registrar(mysqli $c, array $d, int $empresa_id, int $usuario_id, bool $en_transaccion = false): array
{
    $tipo = !empty($d['obligacion_tipo_id']) ? obl_tipo($c, $empresa_id, intval($d['obligacion_tipo_id']))
                                             : obl_tipo_por_codigo($c, $empresa_id, (string)($d['tipo_codigo'] ?? ''));
    if (!$tipo) throw new RuntimeException('La empresa no tiene el tipo de obligación ' . ($d['tipo_codigo'] ?? '') . ': revisá Tipos de Obligación.');
    if (intval($tipo['tabla_estado_registro_id']) !== 1) throw new RuntimeException("El tipo {$tipo['nombre']} está inactivo.");
    $origen = strtoupper(trim((string)($d['origen'] ?? 'MANUAL')));
    if (in_array($origen, OBL_ORIGENES_MANUALES, true) && intval($tipo['permite_manual']) !== 1) {
        throw new RuntimeException("Las obligaciones de tipo {$tipo['nombre']} las genera el sistema.");
    }
    $x = obl_datos($c, $empresa_id, $d, $tipo);
    $comp_tipo = emp_comprobante_tipo_id($c, $empresa_id, $tipo['comprobante_tipo_codigo']);
    if ($comp_tipo <= 0) throw new RuntimeException("La empresa no tiene el tipo de comprobante {$tipo['comprobante_tipo_codigo']}.");
    $pv = emp_punto_venta_id($c, $empresa_id);
    if ($pv <= 0) throw new RuntimeException('La empresa no tiene puntos de venta: no se puede numerar el comprobante.');
    $moneda = emp_moneda_id($c, $empresa_id, $d['moneda_id'] ?? 0);
    $impuesto = intval($d['impuesto_tipo_id'] ?? $tipo['impuesto_tipo_id'] ?? 0) ?: null;
    $jurisdiccion = intval($d['jurisdiccion_id'] ?? 0) ?: null;
    $origen_reg = intval($d['origen_registro_id'] ?? 0) ?: null;
    $importacion = intval($d['importacion_id'] ?? 0) ?: null;
    $tipo_id = intval($tipo['obligacion_tipo_id']);
    $borrador = OBL_BORRADOR;

    if (!$en_transaccion) mysqli_begin_transaction($c);
    try {
        obl_exec($c,
            "INSERT INTO gestion__obligaciones_pago (empresa_id, obligacion_tipo_id, origen, origen_registro_id, importacion_id, sucursal_id,
                    comprobante_tipo_id, comprobante_pv, comprobante_nro, comprobante_id, entidad_id, f_emision, f_contabilidad, f_vto,
                    impuesto_tipo_id, jurisdiccion_id, periodo_fiscal, descripcion, nro_ddjj, nro_vep, referencia,
                    cont_cuenta_id, cont_cuenta_debe_id, moneda_id, tipo_cambio, importe_total, observaciones, tabla_estado_registro_id, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
            'iisiiiiiisssiisssssiiidsii',
            $empresa_id, $tipo_id, $origen, $origen_reg, $importacion, $x['sucursal_id'],
            $comp_tipo, $pv, $x['entidad_id'], $x['f_emision'], $x['f_emision'], $x['f_vto'],
            $impuesto, $jurisdiccion, $x['periodo_fiscal'], $x['descripcion'], $x['nro_ddjj'], $x['nro_vep'], $x['referencia'],
            $x['cont_cuenta_pasivo_id'], $x['cont_cuenta_debe_id'], $moneda, $x['importe_total'], $x['observaciones'], $borrador, $usuario_id);
        $id = intval(mysqli_insert_id($c));

        $tabla = OBL_TABLA_ID;
        $modulo = 'TESORERIA';
        $cero = 0.0;
        obl_exec($c,
            "INSERT INTO gestion__comprobantes (tabla_origen_id, registro_origen_id, modulo, entidad_id, empresa_id, sucursal_id, comprobante_tipo_id,
                    comprobante_pv, comprobante_nro, f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                    importe_bruto, importe_neto, importe_total, importe_pendiente, observaciones, tabla_estado_registro_id, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1.0, ?, ?, ?, ?, ?, ?, ?)",
            'iisiiiiisssiddddsii',
            $tabla, $id, $modulo, $x['entidad_id'], $empresa_id, $x['sucursal_id'], $comp_tipo, $pv,
            $x['f_emision'], $x['f_emision'], $x['f_vto'], $moneda,
            $x['importe_total'], $x['importe_total'], $x['importe_total'], $cero, $x['descripcion'], $borrador, $usuario_id);
        $comp_id = intval(mysqli_insert_id($c));
        obl_exec($c, "UPDATE gestion__obligaciones_pago SET comprobante_id = ? WHERE obligacion_id = ?", 'ii', $comp_id, $id);

        $res = ['obligacion_id' => $id, 'comprobante_id' => $comp_id, 'estado' => OBL_BORRADOR, 'numero' => '', 'asiento_id' => null,
                'mensaje' => 'Obligación guardada en borrador.'];
        $confirmar = strtoupper((string)($d['confirmar'] ?? 'NO'));
        if ($confirmar === 'SI' || $confirmar === 'FORZAR') {
            $res = array_merge($res, obl_confirmar($c, $empresa_id, $usuario_id, $id, $confirmar === 'FORZAR', true, !empty($d['sin_asiento'])));
        }
        if (!$en_transaccion) mysqli_commit($c);
        return $res;
    } catch (Throwable $e) {
        if (!$en_transaccion) mysqli_rollback($c);
        throw $e;
    }
}

function obl_bloquear(mysqli $c, int $empresa_id, int $id): array
{
    $o = obl_fila($c, "SELECT o.*, ot.clase, ot.nombre AS tipo_nombre, ot.codigo AS tipo_codigo, ot.rol_entidad_codigo,
                              ot.cont_cuenta_debe_id AS tipo_cuenta_debe_id, ot.cont_cuenta_pasivo_id AS tipo_cuenta_pasivo_id
                         FROM gestion__obligaciones_pago o
                         LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id
                        WHERE o.obligacion_id = ? AND o.empresa_id = ? FOR UPDATE", 'ii', $id, $empresa_id);
    if (!$o) throw new RuntimeException('Obligación no encontrada.');
    return $o;
}

function obl_set_estado(mysqli $c, array $o, int $estado, int $usuario_id): void
{
    obl_exec($c, "UPDATE gestion__obligaciones_pago SET tabla_estado_registro_id = ?, usuario_modificacion_id = ? WHERE obligacion_id = ?",
             'iii', $estado, $usuario_id, intval($o['obligacion_id']));
    obl_exec($c, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = ?, usuario_modificacion_id = ? WHERE comprobante_id = ?",
             'iii', $estado, $usuario_id, intval($o['comprobante_id']));
}

/** Edita una obligación en Borrador (manuales e importadas). */
function obl_editar(mysqli $c, int $empresa_id, int $usuario_id, int $id, array $d): void
{
    mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (intval($o['tabla_estado_registro_id']) !== OBL_BORRADOR) throw new RuntimeException('Solo se editan obligaciones en Borrador.');
        $tipo = obl_tipo($c, $empresa_id, intval($d['obligacion_tipo_id'] ?? $o['obligacion_tipo_id']));
        if (intval($tipo['permite_manual']) !== 1) throw new RuntimeException("Las obligaciones de tipo {$tipo['nombre']} las genera el sistema.");
        $x = obl_datos($c, $empresa_id, $d, $tipo);
        $comp_tipo = emp_comprobante_tipo_id($c, $empresa_id, $tipo['comprobante_tipo_codigo']);
        $tipo_id = intval($tipo['obligacion_tipo_id']);
        obl_exec($c,
            "UPDATE gestion__obligaciones_pago SET obligacion_tipo_id = ?, comprobante_tipo_id = ?, sucursal_id = ?, entidad_id = ?, f_emision = ?,
                    f_contabilidad = ?, f_vto = ?, periodo_fiscal = ?, descripcion = ?, nro_ddjj = ?, nro_vep = ?, referencia = ?,
                    cont_cuenta_id = ?, cont_cuenta_debe_id = ?, importe_total = ?, observaciones = ?, usuario_modificacion_id = ?
              WHERE obligacion_id = ?",
            'iiiissssssssiidsii', $tipo_id, $comp_tipo, $x['sucursal_id'], $x['entidad_id'], $x['f_emision'], $x['f_emision'], $x['f_vto'],
            $x['periodo_fiscal'], $x['descripcion'], $x['nro_ddjj'], $x['nro_vep'], $x['referencia'],
            $x['cont_cuenta_pasivo_id'], $x['cont_cuenta_debe_id'], $x['importe_total'], $x['observaciones'], $usuario_id, $id);
        obl_exec($c,
            "UPDATE gestion__comprobantes SET comprobante_tipo_id = ?, sucursal_id = ?, entidad_id = ?, f_emision = ?, f_contabilidad = ?, f_vto = ?,
                    importe_bruto = ?, importe_neto = ?, importe_total = ?, observaciones = ?, usuario_modificacion_id = ?
              WHERE comprobante_id = ?",
            'iiisssdddsii', $comp_tipo, $x['sucursal_id'], $x['entidad_id'], $x['f_emision'], $x['f_emision'], $x['f_vto'],
            $x['importe_total'], $x['importe_total'], $x['importe_total'], $x['descripcion'], $usuario_id, intval($o['comprobante_id']));
        aprob_anular_vigentes($c, $empresa_id, 'OBLIGACION', $id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

/**
 * Confirmar: si una regla de aprobación aplica pasa a Pend. aprobación (4); si no, queda Pendiente de pago (5).
 * $forzar: sin motor de aprobaciones (generadores del sistema).
 */
function obl_confirmar(mysqli $c, int $empresa_id, int $usuario_id, int $id, bool $forzar = false, bool $en_transaccion = false, bool $sin_asiento = false): array
{
    if (!$en_transaccion) mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (intval($o['tabla_estado_registro_id']) !== OBL_BORRADOR) throw new RuntimeException('La obligación ya no está en Borrador.');
        // Valida datos y cuentas antes de pedir la aprobación (que no se apruebe algo que después no se puede confirmar)
        obl_validar_entidad($c, $empresa_id, intval($o['entidad_id']), $o + ['rol_entidad_codigo' => $o['rol_entidad_codigo'], 'nombre' => $o['tipo_nombre']]);
        if (!$sin_asiento || $o['clase'] === 'ANTICIPO') {
            obl_resolver_cuentas($c, $empresa_id, obl_tipo($c, $empresa_id, intval($o['obligacion_tipo_id'])), intval($o['entidad_id']),
                                 intval($o['cont_cuenta_debe_id'] ?? 0), intval($o['cont_cuenta_id'] ?? 0));
        }
        $ev = $forzar ? ['requiere' => false, 'aprobaciones' => 0]
                      : aprob_evaluar($c, $empresa_id, 'OBLIGACION', intval($o['obligacion_tipo_id']), floatval($o['importe_total']), $o['f_emision']);
        if ($ev['requiere']) {
            obl_set_estado($c, $o, OBL_PEND_APROBACION, $usuario_id);
            obl_exec($c, "UPDATE gestion__obligaciones_pago SET aprobaciones_requeridas = ? WHERE obligacion_id = ?", 'ii', $ev['aprobaciones'], $id);
            aprob_anular_vigentes($c, $empresa_id, 'OBLIGACION', $id);
            $res = ['estado' => OBL_PEND_APROBACION, 'mensaje' => 'Quedó pendiente de aprobación' . ($ev['aprobaciones'] > 1 ? ' (dos aprobaciones)' : '') . '.'];
        } else {
            $res = obl_efectivizar($c, $empresa_id, $usuario_id, $o, $sin_asiento);
        }
        if (!$en_transaccion) mysqli_commit($c);
        return $res;
    } catch (Throwable $e) {
        if (!$en_transaccion) mysqli_rollback($c);
        throw $e;
    }
}

/** Número, saldo, asiento de devengamiento y estado 5. Corre dentro de la transacción del llamador. */
function obl_efectivizar(mysqli $c, int $empresa_id, int $usuario_id, array $o, bool $sin_asiento = false): array
{
    $id = intval($o['obligacion_id']);
    $tipo = obl_tipo($c, $empresa_id, intval($o['obligacion_tipo_id']));
    $total = floatval($o['importe_total']);
    $debe = intval($o['cont_cuenta_debe_id'] ?? 0);
    $pasivo = intval($o['cont_cuenta_id'] ?? 0);
    if (!$sin_asiento || $tipo['clase'] === 'ANTICIPO') {
        [$debe, $pasivo] = obl_resolver_cuentas($c, $empresa_id, $tipo, intval($o['entidad_id']), $debe, $pasivo);
    }
    $nro = obl_numerar($c, $empresa_id, intval($o['comprobante_tipo_id']), intval($o['comprobante_pv']));
    $estado = OBL_CONFIRMADA;
    obl_exec($c, "UPDATE gestion__obligaciones_pago SET comprobante_nro = ?, cont_cuenta_id = ?, cont_cuenta_debe_id = ?, tabla_estado_registro_id = ?,
                         usuario_modificacion_id = ? WHERE obligacion_id = ?",
             'iiiiii', $nro, $pasivo ?: null, $debe ?: null, $estado, $usuario_id, $id);
    obl_exec($c, "UPDATE gestion__comprobantes SET comprobante_nro = ?, importe_pendiente = importe_total, tabla_estado_registro_id = ?,
                         usuario_modificacion_id = ? WHERE comprobante_id = ?",
             'iiii', $nro, $estado, $usuario_id, intval($o['comprobante_id']));

    $asiento_id = null;
    if (!$sin_asiento && $tipo['clase'] !== 'ANTICIPO') {
        $asiento_id = tesoreriaGenerarAsiento($c, [
            'empresa_id' => $empresa_id, 'sucursal_id' => intval($o['sucursal_id']), 'comprobante_id' => intval($o['comprobante_id']),
            'entidad_id' => intval($o['entidad_id']), 'f_asiento' => $o['f_emision'],
            'descripcion' => mb_substr("{$tipo['nombre']}: {$o['descripcion']}", 0, 250), 'usuario_id' => $usuario_id,
        ], [
            ['cuenta_id' => $debe, 'debe' => $total, 'haber' => 0.0, 'descripcion' => $o['descripcion']],
            ['cuenta_id' => $pasivo, 'debe' => 0.0, 'haber' => $total, 'descripcion' => "{$tipo['nombre']} a pagar"],
        ]);
    }
    $numero = emp_numero_comprobante($c, $empresa_id, intval($o['comprobante_pv']), $nro);
    return ['estado' => $estado, 'numero' => $numero, 'asiento_id' => $asiento_id,
            'mensaje' => "Obligación $numero confirmada: ya se puede pagar desde Órdenes de Pago."];
}

function obl_aprobar(mysqli $c, int $empresa_id, int $usuario_id, int $pagina_id, int $id): array
{
    mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (intval($o['tabla_estado_registro_id']) !== OBL_PEND_APROBACION) throw new RuntimeException('La obligación no está pendiente de aprobación.');
        $r = aprob_aprobar($c, $usuario_id, $empresa_id, $pagina_id, 'OBLIGACION', $id, intval($o['obligacion_tipo_id']),
                           floatval($o['importe_total']), intval($o['usuario_id']), intval($o['aprobaciones_requeridas']));
        if ($r['completa']) {
            $res = obl_efectivizar($c, $empresa_id, $usuario_id, $o);
            $res['mensaje'] = 'Aprobada. ' . $res['mensaje'];
        } else {
            $res = ['estado' => OBL_PEND_APROBACION, 'mensaje' => 'Aprobación registrada: falta la de otro usuario.'];
        }
        mysqli_commit($c);
        return $res;
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

function obl_rechazar(mysqli $c, int $empresa_id, int $usuario_id, int $pagina_id, int $id, string $motivo): void
{
    mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (intval($o['tabla_estado_registro_id']) !== OBL_PEND_APROBACION) throw new RuntimeException('La obligación no está pendiente de aprobación.');
        aprob_rechazar($c, $usuario_id, $empresa_id, $pagina_id, 'OBLIGACION', $id, floatval($o['importe_total']), $motivo);
        obl_set_estado($c, $o, OBL_BORRADOR, $usuario_id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

function obl_eliminar(mysqli $c, int $empresa_id, int $usuario_id, int $id): void
{
    mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (!in_array(intval($o['tabla_estado_registro_id']), [OBL_BORRADOR, OBL_PEND_APROBACION], true)) {
            throw new RuntimeException('Solo se eliminan obligaciones en Borrador o pendientes de aprobación.');
        }
        obl_set_estado($c, $o, OBL_ELIMINADA, $usuario_id);
        aprob_anular_vigentes($c, $empresa_id, 'OBLIGACION', $id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

/** Anula una obligación confirmada sin pagos (las del sistema se anulan desde su pantalla de origen). */
function obl_anular(mysqli $c, int $empresa_id, int $usuario_id, int $id, string $motivo): void
{
    $motivo = trim($motivo);
    if ($motivo === '') throw new RuntimeException('Indicá el motivo de la anulación.');
    mysqli_begin_transaction($c);
    try {
        $o = obl_bloquear($c, $empresa_id, $id);
        if (intval($o['tabla_estado_registro_id']) !== OBL_CONFIRMADA) throw new RuntimeException('Solo se anulan obligaciones pendientes de pago y sin pagos.');
        if (!in_array($o['origen'], OBL_ORIGENES_MANUALES, true)) {
            throw new RuntimeException('Esta obligación la generó ' . (OBL_ORIGENES_TEXTO[$o['origen']] ?? $o['origen']) . ': se anula desde esa pantalla.');
        }
        $comp_id = intval($o['comprobante_id']);
        $pagos = obl_fila($c, "SELECT COUNT(*) n FROM gestion__comprobantes_imputaciones WHERE comp_destino_id = ? AND tabla_estado_registro_id = 5", 'i', $comp_id);
        if (intval($pagos['n'] ?? 0) > 0) throw new RuntimeException('La obligación tiene pagos: anulá antes la orden de pago.');
        cont_periodo_exigir_comprobante($c, $empresa_id, $comp_id, 'anular la obligación');
        obl_exec($c, "UPDATE gestion__obligaciones_pago SET tabla_estado_registro_id = ?, motivo_anulacion = ?, usuario_modificacion_id = ? WHERE obligacion_id = ?",
                 'isii', OBL_ANULADA, mb_substr($motivo, 0, 255), $usuario_id, $id);
        obl_exec($c, "UPDATE gestion__comprobantes SET tabla_estado_registro_id = ?, importe_pendiente = 0, usuario_modificacion_id = ? WHERE comprobante_id = ?",
                 'iii', OBL_ANULADA, $usuario_id, $comp_id);
        obl_exec($c, "UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = ? WHERE comprobante_id = ? AND empresa_id = ?",
                 'iii', $usuario_id, $comp_id, $empresa_id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

// ---------------------------------------------------------------------------------------------------------
// Consultas
// ---------------------------------------------------------------------------------------------------------

/** Etiquetas de estado de la tabla 286 (catálogo propio). */
function obl_estados(mysqli $c): array
{
    return obl_filas($c, "SELECT ter.estado_registro_id, ter.tabla_estado_registro AS estado, ter.orden, co.bg_clase, co.text_clase
                            FROM conf__tablas_estados_registros ter
                            LEFT JOIN conf__colores co ON co.color_id = ter.color_id
                           WHERE ter.tabla_id = ? ORDER BY ter.orden", 'i', OBL_TABLA_ID);
}

function obl_botones(mysqli $c, int $pagina_id, int $estado): array
{
    static $cache = [];
    $k = "$pagina_id|$estado";
    if (!isset($cache[$k])) {
        // Por estado (origen) y, además, solo las funciones asignadas al perfil del usuario (core/permisos.php)
        $cache[$k] = permisos_filtrar_funciones($c, obl_filas($c,
            "SELECT pf.pagina_funcion_id, pf.pagina_id, pf.nombre_funcion, pf.accion_js, IF(pf.tabla_estado_registro_destino_id <> pf.tabla_estado_registro_origen_id, 1, 0) AS es_confirmable,
                    i.icono_clase, co.color_clase, co.bg_clase, co.text_clase
               FROM conf__paginas_funciones pf
               LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
               LEFT JOIN conf__colores co ON co.color_id = pf.color_id
              WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.tabla_estado_registro_id = 1
              ORDER BY pf.orden", 'ii', $pagina_id, $estado));
    }
    return $cache[$k];
}

/** Botones de página (origen 0) que el usuario puede usar. */
function obl_funciones_pagina(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id): array
{
    return array_values(array_filter(obl_botones($c, $pagina_id, 0),
        static fn($b) => usuario_puede_funcion($c, $usuario_id, $empresa_id, $pagina_id, $b['accion_js'])));
}

function obl_listar(mysqli $c, int $empresa_id, int $usuario_id, int $pagina_id, array $f = []): array
{
    $sql = "SELECT o.obligacion_id, o.comprobante_id, o.obligacion_tipo_id, o.origen, o.f_emision, o.f_vto, o.periodo_fiscal, o.descripcion,
                   o.referencia, o.nro_vep, o.importe_total, o.tabla_estado_registro_id, o.usuario_id, o.importacion_id, o.aprobaciones_requeridas,
                   ot.nombre AS tipo_nombre, ot.codigo AS tipo_codigo, ot.clase, COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, e.entidad_fantasia, e.cuit,
                   c.importe_pendiente, c.comprobante_nro, ct.codigo AS comprobante_codigo,
                   " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS pv_mostrar,
                   ter.tabla_estado_registro AS estado, co.bg_clase, co.text_clase
              FROM gestion__obligaciones_pago o
              JOIN gestion__comprobantes c ON c.comprobante_id = o.comprobante_id
              JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
              LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id
              LEFT JOIN gestion__entidades e ON e.entidad_id = o.entidad_id
              LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . OBL_TABLA_ID . " AND ter.estado_registro_id = o.tabla_estado_registro_id
              LEFT JOIN conf__colores co ON co.color_id = ter.color_id
             WHERE o.empresa_id = ?";
    $tipos = 'i';
    $p = [$empresa_id];
    if (!empty($f['obligacion_tipo_id'])) { $sql .= ' AND o.obligacion_tipo_id = ?'; $tipos .= 'i'; $p[] = intval($f['obligacion_tipo_id']); }
    if (!empty($f['entidad_id'])) { $sql .= ' AND o.entidad_id = ?'; $tipos .= 'i'; $p[] = intval($f['entidad_id']); }
    if (!empty($f['importacion_id'])) { $sql .= ' AND o.importacion_id = ?'; $tipos .= 'i'; $p[] = intval($f['importacion_id']); }
    $vista = (string)($f['vista'] ?? 'pendientes');
    if ($vista === 'pendientes') $sql .= ' AND o.tabla_estado_registro_id IN (3, 4, 5, 39)';
    elseif ($vista === 'aprobacion') $sql .= ' AND o.tabla_estado_registro_id = 4';
    elseif ($vista === 'a_pagar') $sql .= ' AND o.tabla_estado_registro_id IN (5, 39)';
    elseif ($vista === 'pagadas') $sql .= ' AND o.tabla_estado_registro_id = 40';
    elseif ($vista === 'anuladas') $sql .= ' AND o.tabla_estado_registro_id IN (6, 38)';
    else $sql .= ' AND o.tabla_estado_registro_id <> 6';
    if ($d = obl_fecha($f['vto_desde'] ?? '')) { $sql .= ' AND o.f_vto >= ?'; $tipos .= 's'; $p[] = $d; }
    if ($h = obl_fecha($f['vto_hasta'] ?? '')) { $sql .= ' AND o.f_vto <= ?'; $tipos .= 's'; $p[] = $h; }
    $sql .= ' ORDER BY (o.tabla_estado_registro_id IN (5, 39)) DESC, o.f_vto, o.obligacion_id DESC LIMIT 2000';
    $rows = obl_filas($c, $sql, $tipos, ...$p);

    $hoy = date('Y-m-d');
    foreach ($rows as &$r) {
        $estado = intval($r['tabla_estado_registro_id']);
        $r['numero'] = intval($r['comprobante_nro']) > 0 ? $r['comprobante_codigo'] . ' ' . str_pad((string)$r['pv_mostrar'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$r['comprobante_nro'], 8, '0', STR_PAD_LEFT) : '';
        $r['origen_texto'] = OBL_ORIGENES_TEXTO[$r['origen']] ?? $r['origen'];
        $r['vencida'] = in_array($estado, [OBL_CONFIRMADA, OBL_PAGADA_PARCIAL], true) && $r['f_vto'] < $hoy ? 1 : 0;
        $manual = in_array($r['origen'], OBL_ORIGENES_MANUALES, true);
        $botones = [];
        foreach (obl_botones($c, $pagina_id, $estado) as $b) {
            if (!$manual && in_array($b['accion_js'], ['editar', 'anular', 'eliminar'], true)) continue;
            if ($b['accion_js'] === 'aprobar' && !aprob_puede($c, $usuario_id, $empresa_id, $pagina_id, 'OBLIGACION', intval($r['obligacion_id']),
                                                               intval($r['obligacion_tipo_id']), floatval($r['importe_total']), intval($r['usuario_id']))) continue;
            $botones[] = $b;
        }
        $r['botones'] = $botones;
    }
    unset($r);
    return $rows;
}

function obl_resumen(mysqli $c, int $empresa_id): array
{
    $hoy = date('Y-m-d');
    $semana = date('Y-m-d', strtotime('+7 days'));
    $r = obl_fila($c,
        "SELECT SUM(o.tabla_estado_registro_id IN (5, 39) AND o.f_vto < ?) AS vencidas_n,
                SUM(IF(o.tabla_estado_registro_id IN (5, 39) AND o.f_vto < ?, c.importe_pendiente, 0)) AS vencidas_importe,
                SUM(o.tabla_estado_registro_id IN (5, 39) AND o.f_vto BETWEEN ? AND ?) AS semana_n,
                SUM(IF(o.tabla_estado_registro_id IN (5, 39) AND o.f_vto BETWEEN ? AND ?, c.importe_pendiente, 0)) AS semana_importe,
                SUM(IF(o.tabla_estado_registro_id IN (5, 39), c.importe_pendiente, 0)) AS a_pagar_importe,
                SUM(o.tabla_estado_registro_id = 4) AS aprobacion_n
           FROM gestion__obligaciones_pago o
           JOIN gestion__comprobantes c ON c.comprobante_id = o.comprobante_id
          WHERE o.empresa_id = ?", 'ssssssi', $hoy, $hoy, $hoy, $semana, $hoy, $semana, $empresa_id) ?? [];
    return array_map(static fn($v) => $v === null ? 0 : $v, $r);
}

function obl_obtener(mysqli $c, int $empresa_id, int $id): array
{
    $o = obl_fila($c,
        "SELECT o.*, ot.nombre AS tipo_nombre, ot.clase, ot.rol_entidad_codigo, e.entidad_nombre, e.cuit, c.importe_pendiente,
                cd.codigo AS cuenta_debe_codigo, cd.nombre AS cuenta_debe, cp.codigo AS cuenta_pasivo_codigo, cp.nombre AS cuenta_pasivo,
                ter.tabla_estado_registro AS estado, " . emp_sql_pv_mostrar('o.comprobante_pv', 'o.empresa_id') . " AS pv_mostrar, ct.codigo AS comprobante_codigo
           FROM gestion__obligaciones_pago o
           JOIN gestion__comprobantes c ON c.comprobante_id = o.comprobante_id
           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = o.comprobante_tipo_id
           LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id
           LEFT JOIN gestion__entidades e ON e.entidad_id = o.entidad_id
           LEFT JOIN gestion__cont_cuentas cd ON cd.cont_cuenta_id = o.cont_cuenta_debe_id
           LEFT JOIN gestion__cont_cuentas cp ON cp.cont_cuenta_id = o.cont_cuenta_id
           LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . OBL_TABLA_ID . " AND ter.estado_registro_id = o.tabla_estado_registro_id
          WHERE o.obligacion_id = ? AND o.empresa_id = ?", 'ii', $id, $empresa_id);
    if (!$o) throw new RuntimeException('Obligación no encontrada.');
    $o['origen_texto'] = OBL_ORIGENES_TEXTO[$o['origen']] ?? $o['origen'];
    $o['pagos'] = obl_filas($c,
        "SELECT ci.f_imputacion, ci.importe_imputado, ci.tabla_estado_registro_id, ct.codigo, co.comprobante_nro,
                " . emp_sql_pv_mostrar('co.comprobante_pv', 'co.empresa_id') . " AS pv_mostrar
           FROM gestion__comprobantes_imputaciones ci
           JOIN gestion__comprobantes co ON co.comprobante_id = ci.comp_origen_id
           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = co.comprobante_tipo_id
          WHERE ci.comp_destino_id = ? AND ci.tabla_estado_registro_id IN (5, 38)
          ORDER BY ci.imputacion_id", 'i', intval($o['comprobante_id']));
    $o['aprobaciones'] = aprob_historial($c, $empresa_id, 'OBLIGACION', $id);
    $o['asiento'] = obl_filas($c,
        "SELECT cc.codigo, cc.nombre, d.importe_local, d.descripcion
           FROM gestion__cont_asientos a
           JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id
           JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id
          WHERE a.comprobante_id = ? AND a.empresa_id = ? AND a.tabla_estado_registro_id = 1
          ORDER BY d.importe_local DESC", 'ii', intval($o['comprobante_id']), $empresa_id);
    return $o;
}

// ---------------------------------------------------------------------------------------------------------
// Órdenes de pago: cuentas de cancelación y anticipos
// ---------------------------------------------------------------------------------------------------------

/**
 * Para un comprobante destino de una OP: si es una obligación, su cuenta de cancelación y su clase.
 * null si no es una obligación (factura, ND...: la OP usa la cuenta de la entidad, como siempre).
 */
function obl_cancelacion_destino(mysqli $c, int $comprobante_id): ?array
{
    $o = obl_fila($c, "SELECT o.cont_cuenta_id, o.cont_cuenta_debe_id, ot.clase
                         FROM gestion__comprobantes cd
                         JOIN gestion__obligaciones_pago o ON o.obligacion_id = cd.registro_origen_id AND o.comprobante_id = cd.comprobante_id
                         LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id
                        WHERE cd.comprobante_id = ? AND cd.tabla_origen_id = ?", 'ii', $comprobante_id, OBL_TABLA_ID);
    if (!$o) return null;
    $anticipo = ($o['clase'] ?? '') === 'ANTICIPO';
    $cuenta = intval($anticipo ? $o['cont_cuenta_debe_id'] : $o['cont_cuenta_id']);
    if ($cuenta <= 0) throw new RuntimeException('Una obligación imputada no tiene su cuenta contable: confirmala de nuevo o revisá Tipos de Obligación.');
    return ['cuenta_id' => $cuenta, 'anticipo' => $anticipo];
}

/**
 * OP con saldo a favor (anticipos o pagos a cuenta) de la entidad, que se pueden aplicar a sus deudas.
 */
function obl_anticipos_disponibles(mysqli $c, int $empresa_id, int $entidad_id): array
{
    return obl_filas($c,
        "SELECT c.comprobante_id, c.comprobante_nro, c.f_emision, c.importe_total, c.importe_pendiente,
                " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS pv_mostrar
           FROM gestion__comprobantes c
           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id AND ct.codigo = 'OP'
          WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.tabla_estado_registro_id IN (5, 39) AND c.importe_pendiente > 0.001
          ORDER BY c.f_emision, c.comprobante_id", 'ii', $empresa_id, $entidad_id);
}

/**
 * Aplica el saldo de una OP (anticipo) a una deuda de la misma entidad: imputación directa OP -> destino, sin
 * movimiento de fondos ni asiento (el anticipo se debitó en la misma cuenta que cancela la deuda: la del
 * proveedor, o Sueldos a pagar en el anticipo de sueldo). Devuelve el importe aplicado.
 */
function obl_aplicar_anticipo(mysqli $c, int $empresa_id, int $usuario_id, int $op_comprobante_id, int $destino_id, float $importe): float
{
    mysqli_begin_transaction($c);
    try {
        $op = obl_fila($c, "SELECT c.comprobante_id, c.entidad_id, c.importe_pendiente, c.tabla_estado_registro_id, c.moneda_id, c.comprobante_tipo_id
                              FROM gestion__comprobantes c JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id AND ct.codigo = 'OP'
                             WHERE c.comprobante_id = ? AND c.empresa_id = ? FOR UPDATE", 'ii', $op_comprobante_id, $empresa_id);
        if (!$op || !in_array(intval($op['tabla_estado_registro_id']), [5, 39], true) || floatval($op['importe_pendiente']) <= 0.001) {
            throw new RuntimeException('La orden de pago no tiene saldo a favor para aplicar.');
        }
        $dest = obl_fila($c, "SELECT c.comprobante_id, c.entidad_id, c.importe_pendiente, c.tabla_estado_registro_id, c.moneda_id, ct.codigo
                                FROM gestion__comprobantes c
                                JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
                                JOIN gestion__comprobantes_tipos_cancelaciones tc ON tc.comprobante_tipo_destino_id = c.comprobante_tipo_id
                                     AND tc.comprobante_tipo_origen_id = ? AND tc.empresa_id = c.empresa_id AND tc.tabla_estado_registro_id = 1
                               WHERE c.comprobante_id = ? AND c.empresa_id = ? FOR UPDATE", 'iii', intval($op['comprobante_tipo_id']), $destino_id, $empresa_id);
        if (!$dest || $dest['codigo'] === 'SOL_ANT') throw new RuntimeException('Ese comprobante no se puede cancelar con un anticipo.');
        if (intval($dest['entidad_id']) !== intval($op['entidad_id'])) throw new RuntimeException('El anticipo y la deuda tienen que ser del mismo beneficiario.');
        if (!in_array(intval($dest['tabla_estado_registro_id']), [5, 39], true) || floatval($dest['importe_pendiente']) <= 0.001) {
            throw new RuntimeException('La deuda no tiene saldo pendiente.');
        }
        if (intval($dest['moneda_id']) !== intval($op['moneda_id'])) throw new RuntimeException('El anticipo y la deuda están en monedas distintas.');
        $maximo = round(min(floatval($dest['importe_pendiente']), floatval($op['importe_pendiente'])), 2);
        $importe = round($importe, 2) > 0 ? round($importe, 2) : $maximo;
        if ($importe > $maximo + 0.001) throw new RuntimeException('El importe supera el saldo disponible ($ ' . number_format($maximo, 2, ',', '.') . ').');
        cont_periodo_exigir_abierto($c, $empresa_id, date('Y-m-d'), 'aplicar el anticipo');
        tesoreriaRegistrarImputacion($c, [
            'empresa_id' => $empresa_id, 'comp_origen_id' => $op_comprobante_id, 'comp_destino_id' => $destino_id,
            'importe_imputado' => $importe, 'moneda_origen_id' => intval($op['moneda_id']), 'usuario_id' => $usuario_id,
        ]);
        mysqli_commit($c);
        return $importe;
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}
