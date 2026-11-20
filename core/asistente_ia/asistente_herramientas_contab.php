<?php
/**
 * Asistente IA — Módulo de Contabilidad y Tesorería.
 * Herramientas de consulta, propuestas (proponer_*) y grabadores al confirmar (asis_grabar_*).
 * Todas las herramientas se exponen a la IA SOLO si el perfil del usuario tiene acceso/permiso
 * en la página correspondiente, y se revalidan al confirmar en asis_confirmar_propuesta().
 */

require_once __DIR__ . '/asistente_guia.php';

/** Definiciones JSON Schema de herramientas de guía, contabilidad y tesorería, filtradas por permisos del usuario. */
function asis_herramientas_contab(array $ctx): array
{
    $out = [];

    // Guía funcional del sistema: siempre disponible para cualquier usuario con acceso al asistente
    $out[] = [
        'name' => 'consultar_guia_sistema',
        'description' => 'Consulta la guía paso a paso de cómo funciona un circuito del sistema (sueldos, impuestos, socios, fci, cobranzas, bancos, ventas, contabilidad). Usala cuando el usuario pregunte cómo hacer algo en el ERP.',
        'input_schema' => ['type' => 'object', 'properties' => [
            'tema' => ['type' => 'string', 'enum' => ['general', 'sueldos', 'impuestos', 'socios', 'fci', 'cobranzas', 'bancos', 'ventas', 'contabilidad']],
        ], 'required' => ['tema']],
    ];

    $ver_plan = asis_tiene_acceso_pagina($ctx, 'plan_cuentas')
             || asis_tiene_acceso_pagina($ctx, 'cont_asientos')
             || asis_tiene_acceso_pagina($ctx, 'obligaciones_tipos')
             || asis_tiene_acceso_pagina($ctx, 'obligaciones_pago')
             || asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos');
    if ($ver_plan) {
        $out[] = [
            'name' => 'buscar_cuentas_contables',
            'description' => 'Busca cuentas del Plan de Cuentas de la empresa por código (prefijo) o texto del nombre. Devuelve hasta 40 cuentas con cont_cuenta_id, codigo, nombre, naturaleza, nivel, es_imputable y cuenta_padre_id.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Parte del nombre o del código (ej: "Otros Créditos", "1.1.03", "Socio", "FCI")'],
                'solo_imputables' => ['type' => 'boolean', 'description' => 'true = solo cuentas imputables (es_imputable = 1)'],
            ], 'required' => ['texto']],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'obligaciones_tipos') || asis_tiene_acceso_pagina($ctx, 'obligaciones_pago')) {
        $out[] = [
            'name' => 'listar_tipos_obligacion',
            'description' => 'Lista los tipos de obligación de pago configurados en la empresa (SUELDO, SAC, ANT_SUELDO, IMP_VARIOS, ANT_SOCIO, etc.) con sus cuentas y rol.',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos') || asis_tiene_acceso_pagina($ctx, 'tesoreria_cuentas')
        || asis_tiene_acceso_pagina($ctx, 'bancos_conciliacion') || asis_tiene_acceso_pagina($ctx, 'pagos')) {
        $out[] = [
            'name' => 'listar_cuentas_tesoreria',
            'description' => 'Lista las cajas y cuentas bancarias activas de la empresa (tesoreria_cuenta_id, nombre, tipo CAJA/BANCO, cont_cuenta_id y saldo_actual).',
            'input_schema' => ['type' => 'object', 'properties' => (object)[]],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'bancos_conciliacion') || asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos')) {
        $out[] = [
            'name' => 'buscar_extracto_bancario',
            'description' => 'Busca movimientos importados en el extracto bancario por texto/CUIT, rango de fechas o estado (PENDIENTE, CLASIFICADA, CONCILIADA). Devuelve hasta 30 líneas.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Texto en descripción o referencia/CUIT'],
                'fecha_desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
                'fecha_hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
                'estado' => ['type' => 'string', 'enum' => ['PENDIENTE', 'CLASIFICADA', 'CONCILIADA', 'TODOS']],
            ]],
        ];
    }

    if (asis_puede($ctx, 'plan_cuentas', 'agregar')) {
        $out[] = [
            'name' => 'proponer_cuentas_contables',
            'description' => 'Propone crear una o varias cuentas en el Plan de Cuentas (en orden jerárquico: primero la cuenta suma/padre y luego sus subcuentas imputables). No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'cuentas' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'codigo' => ['type' => 'string', 'description' => 'Código jerárquico único (ej: 1.1.03.11 o 1.1.03.11.01)'],
                    'nombre' => ['type' => 'string', 'description' => 'Nombre de la cuenta'],
                    'cuenta_padre_id' => ['type' => 'integer', 'description' => 'cont_cuenta_id de una cuenta padre existente en el plan'],
                    'codigo_padre' => ['type' => 'string', 'description' => 'Código de una cuenta padre existente o incluida antes en este mismo lote'],
                    'es_imputable' => ['type' => 'boolean', 'description' => 'false = cuenta de suma/agrupadora; true = cuenta imputable (recibe asientos)'],
                    'naturaleza' => ['type' => 'string', 'enum' => ['D', 'A'], 'description' => 'Opcional: se hereda del padre si se omite'],
                ], 'required' => ['codigo', 'nombre', 'es_imputable']]],
            ], 'required' => ['cuentas']],
        ];
    }

    if (asis_puede($ctx, 'obligaciones_tipos', 'agregar')) {
        $out[] = [
            'name' => 'proponer_tipo_obligacion',
            'description' => 'Propone crear un nuevo Tipo de Obligación de Pago (ej. ANT_SOCIO). No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'codigo' => ['type' => 'string', 'description' => 'Código corto en mayúsculas sin espacios (ej: ANT_SOCIO)'],
                'nombre' => ['type' => 'string', 'description' => 'Nombre descriptivo (ej: Anticipo / Retiro a Socio)'],
                'clase' => ['type' => 'string', 'enum' => ['OBLIGACION', 'ANTICIPO'], 'description' => 'ANTICIPO = no devenga al confirmar, la OP debita la cuenta elegida; OBLIGACION = devenga gasto a pasivo'],
                'rol_entidad_codigo' => ['type' => 'string', 'enum' => ['EMP', 'PRO', 'ORG'], 'description' => 'Rol requerido del beneficiario (opcional: omitir para permitir cualquiera)'],
                'cont_cuenta_debe_id' => ['type' => 'integer', 'description' => 'Cuenta contable imputable por defecto (opcional si se elige en cada obligación)'],
                'cont_cuenta_pasivo_id' => ['type' => 'integer', 'description' => 'Solo para clase OBLIGACION: cuenta de pasivo a pagar (opcional)'],
                'orden' => ['type' => 'integer'],
            ], 'required' => ['codigo', 'nombre', 'clase']],
        ];
    }

    if (asis_puede($ctx, 'obligaciones_pago', 'agregar')) {
        $out[] = [
            'name' => 'proponer_obligacion_pago',
            'description' => 'Propone cargar una o varias Obligaciones de Pago (sueldos, impuestos, anticipos/retiros de socios) en Borrador. No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'obligaciones' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'obligacion_tipo_id' => ['type' => 'integer', 'description' => 'Id de listar_tipos_obligacion'],
                    'entidad_id' => ['type' => 'integer', 'description' => 'entidad_id del beneficiario (buscar_entidades)'],
                    'f_emision' => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                    'f_vto' => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = f_emision)'],
                    'importe' => ['type' => 'number', 'description' => 'Importe positivo'],
                    'descripcion' => ['type' => 'string', 'description' => 'Detalle o concepto'],
                    'cont_cuenta_debe_id' => ['type' => 'integer', 'description' => 'Cuenta contable imputable (ej: Cuenta Particular del socio)'],
                    'cont_cuenta_pasivo_id' => ['type' => 'integer', 'description' => 'Solo para obligaciones con pasivo'],
                ], 'required' => ['obligacion_tipo_id', 'entidad_id', 'importe']]],
            ], 'required' => ['obligaciones']],
        ];
    }

    if (asis_puede($ctx, 'cont_asientos', 'agregar')) {
        $out[] = [
            'name' => 'proponer_asiento_contable',
            'description' => 'Propone un asiento contable manual balanceado (Debe = Haber). Útil para rendimientos de FCI, reclasificaciones, distribución de utilidades, etc. No genera movimiento de tesorería. No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'f_asiento' => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'descripcion' => ['type' => 'string', 'description' => 'Leyenda general del asiento'],
                'lineas' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'cont_cuenta_id' => ['type' => 'integer', 'description' => 'Id de cuenta imputable (buscar_cuentas_contables)'],
                    'debe' => ['type' => 'number'],
                    'haber' => ['type' => 'number'],
                    'descripcion' => ['type' => 'string'],
                ], 'required' => ['cont_cuenta_id']]],
            ], 'required' => ['descripcion', 'lineas']],
        ];
    }

    if (asis_puede($ctx, 'tesoreria_movimientos', 'agregar')) {
        $out[] = [
            'name' => 'proponer_movimiento_tesoreria',
            'description' => 'Propone un comprobante soporte de tesorería (MC en Borrador para revisar/aprobar y confirmar): '
                . 'INGRESO (ej. aporte de socio en banco contra Cuenta Particular o Capital Social), '
                . 'EGRESO (salida directa de banco/caja contra una cuenta contable; NO usar para retiros de socios — para retiros/anticipos usar proponer_obligacion_pago + proponer_orden_pago) o '
                . 'TRANSFERENCIA (entre dos cuentas propias de tesorería, ej. suscripción/rescate de FCI). '
                . 'No graba hasta que el usuario confirme.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'tipo' => ['type' => 'string', 'enum' => ['INGRESO', 'EGRESO', 'TRANSFERENCIA']],
                'f_movimiento' => ['type' => 'string', 'description' => 'AAAA-MM-DD (omitir = hoy)'],
                'importe' => ['type' => 'number'],
                'concepto' => ['type' => 'string'],
                'entidad_id' => ['type' => 'integer', 'description' => 'Opcional para INGRESO/EGRESO: entidad_id del socio/aportante (buscar_entidades)'],
                'tesoreria_cuenta_id' => ['type' => 'integer', 'description' => 'Para INGRESO o EGRESO: cuenta bancaria/caja afectada'],
                'cont_cuenta_contrapartida_id' => ['type' => 'integer', 'description' => 'Para INGRESO o EGRESO: cuenta contable imputable contrapartida (ej: Cuenta Particular Socio o Capital Social)'],
                'cuenta_origen_id' => ['type' => 'integer', 'description' => 'Solo TRANSFERENCIA: tesoreria_cuenta_id de origen'],
                'cuenta_destino_id' => ['type' => 'integer', 'description' => 'Solo TRANSFERENCIA: tesoreria_cuenta_id de destino'],
            ], 'required' => ['tipo', 'importe', 'concepto']],
        ];
    }

    if (asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos')) {
        $out[] = [
            'name' => 'consultar_comprobantes_tesoreria',
            'description' => 'Lista los comprobantes soporte de tesorería (MC - Otros ingresos/egresos como aportes de socios) con su estado actual (Borrador, Pendiente Aprobación, Confirmado, Anulado) y las acciones habilitadas para el perfil.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'estado_id' => ['type' => 'integer', 'description' => 'Opcional: 3=Borrador, 4=Pendiente Aprobación, 5=Confirmado, 38=Anulado'],
            ]],
        ];

        $acciones_mc = [];
        foreach (['enviar_aprobacion', 'confirmar', 'aprobar', 'rechazar', 'eliminar', 'anular'] as $ac) {
            if (asis_puede($ctx, 'tesoreria_movimientos', $ac)) $acciones_mc[] = $ac;
        }
        if ($acciones_mc) {
            $out[] = [
                'name' => 'proponer_estado_comprobante_tesoreria',
                'description' => 'Propone avanzar o cambiar el estado del ciclo de vida de un comprobante soporte MC de tesorería según los permisos del perfil en conf__: enviar_aprobacion (3->4), confirmar (3->5), aprobar (4->5), rechazar (4->3), eliminar (3/4->6) o anular (5->38). No ejecuta hasta que el usuario confirme.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'comprobante_id' => ['type' => 'integer', 'description' => 'comprobante_id del MC (de consultar_comprobantes_tesoreria o del creado recién)'],
                    'accion' => ['type' => 'string', 'enum' => $acciones_mc, 'description' => 'Transición del ciclo de vida habilitada para el perfil'],
                    'motivo' => ['type' => 'string', 'description' => 'Opcional: motivo de rechazo'],
                ], 'required' => ['comprobante_id', 'accion']],
            ];
        }
    }

    return $out;
}

/** Despacha herramientas de guía, contabilidad y tesorería. Devuelve null si el nombre no corresponde a este módulo. */
function asis_ejecutar_herramienta_contab(array $ctx, string $nombre, array $e): ?array
{
    switch ($nombre) {
        case 'consultar_guia_sistema':       return asis_h_consultar_guia($e);
        case 'buscar_cuentas_contables':     return asis_h_buscar_cuentas_contables($ctx, $e);
        case 'listar_tipos_obligacion':      return asis_h_listar_tipos_obligacion($ctx);
        case 'listar_cuentas_tesoreria':     return asis_h_listar_cuentas_tesoreria($ctx);
        case 'buscar_extracto_bancario':     return asis_h_buscar_extracto_bancario($ctx, $e);
        case 'proponer_cuentas_contables':   return asis_h_proponer_cuentas_contables($ctx, $e);
        case 'proponer_tipo_obligacion':     return asis_h_proponer_tipo_obligacion($ctx, $e);
        case 'proponer_obligacion_pago':     return asis_h_proponer_obligacion_pago($ctx, $e);
        case 'proponer_asiento_contable':    return asis_h_proponer_asiento_contable($ctx, $e);
        case 'proponer_movimiento_tesoreria': return asis_h_proponer_movimiento_tesoreria($ctx, $e);
        case 'consultar_comprobantes_tesoreria':       return asis_h_consultar_comprobantes_tesoreria($ctx, $e);
        case 'proponer_estado_comprobante_tesoreria':  return asis_h_proponer_estado_comprobante_tesoreria($ctx, $e);
        default: return null;
    }
}

// ===================================================================================================
// HERRAMIENTAS DE CONSULTA
// ===================================================================================================

function asis_h_buscar_cuentas_contables(array $ctx, array $e): array
{
    $txt = trim((string)($e['texto'] ?? ''));
    $solo_imp = !empty($e['solo_imputables']);
    $like = '%' . $txt . '%';
    $pref = $txt . '%';
    $sql = "SELECT cont_cuenta_id, codigo, nombre, naturaleza, nivel, cuenta_padre_id, es_imputable
            FROM gestion__cont_cuentas
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1"
         . ($solo_imp ? " AND es_imputable = 1" : "")
         . ($txt !== '' ? " AND (codigo LIKE ? OR nombre LIKE ?)" : "")
         . " ORDER BY codigo ASC LIMIT 40";
    $filas = $txt !== ''
        ? asis_filas($ctx['c'], $sql, 'iss', $ctx['empresa_id'], $pref, $like)
        : asis_filas($ctx['c'], $sql, 'i', $ctx['empresa_id']);
    foreach ($filas as &$f) {
        $f['cont_cuenta_id'] = intval($f['cont_cuenta_id']);
        $f['cuenta_padre_id'] = $f['cuenta_padre_id'] !== null ? intval($f['cuenta_padre_id']) : null;
        $f['nivel'] = intval($f['nivel']);
        $f['es_imputable'] = intval($f['es_imputable']) === 1;
    }
    return ['cuentas' => $filas];
}

function asis_h_listar_tipos_obligacion(array $ctx): array
{
    $filas = asis_filas($ctx['c'], "SELECT ot.obligacion_tipo_id, ot.codigo, ot.nombre, ot.clase, ot.rol_entidad_codigo,
                ot.comprobante_tipo_codigo, ot.cont_cuenta_debe_id, cd.codigo AS cuenta_debe_codigo, cd.nombre AS cuenta_debe,
                ot.cont_cuenta_pasivo_id, cp.codigo AS cuenta_pasivo_codigo, cp.nombre AS cuenta_pasivo, ot.permite_manual
            FROM gestion__obligaciones_tipos ot
            LEFT JOIN gestion__cont_cuentas cd ON cd.cont_cuenta_id = ot.cont_cuenta_debe_id
            LEFT JOIN gestion__cont_cuentas cp ON cp.cont_cuenta_id = ot.cont_cuenta_pasivo_id
            WHERE ot.empresa_id = ? AND ot.tabla_estado_registro_id = 1
            ORDER BY ot.orden, ot.nombre", 'i', $ctx['empresa_id']);
    return ['tipos' => $filas];
}

function asis_h_listar_cuentas_tesoreria(array $ctx): array
{
    $filas = asis_filas($ctx['c'], "SELECT tc.tesoreria_cuenta_id, tc.codigo, tc.tesoreria_cuenta AS nombre, tc.cuenta_tipo,
                tc.sucursal_id, tc.moneda_id, tc.cont_cuenta_id, cc.codigo AS cont_codigo, cc.nombre AS cont_nombre,
                tc.saldo_actual
            FROM gestion__tesoreria_cuentas tc
            LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id
            WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1
            ORDER BY tc.cuenta_tipo, tc.orden, tc.tesoreria_cuenta", 'i', $ctx['empresa_id']);
    return ['cuentas' => $filas];
}

function asis_h_buscar_extracto_bancario(array $ctx, array $e): array
{
    $where = ["x.empresa_id = ?"];
    $types = 'i';
    $params = [$ctx['empresa_id']];
    $txt = trim((string)($e['texto'] ?? ''));
    if ($txt !== '') {
        $where[] = "(x.descripcion LIKE ? OR x.referencia LIKE ?)";
        $types .= 'ss';
        $like = '%' . $txt . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $desde = trim((string)($e['fecha_desde'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $where[] = "x.f_movimiento >= ?";
        $types .= 's';
        $params[] = $desde;
    }
    $hasta = trim((string)($e['fecha_hasta'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $where[] = "x.f_movimiento <= ?";
        $types .= 's';
        $params[] = $hasta;
    }
    $est = strtoupper(trim((string)($e['estado'] ?? '')));
    if (in_array($est, ['PENDIENTE', 'CLASIFICADA', 'CONCILIADA'], true)) {
        $where[] = "x.estado = ?";
        $types .= 's';
        $params[] = $est;
    }
    $sql = "SELECT x.extracto_id, x.tesoreria_cuenta_id, tc.tesoreria_cuenta AS cuenta_nombre,
                   x.f_movimiento, x.descripcion, x.referencia, x.importe, x.estado
            FROM gestion__bancos_extracto x
            LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = x.tesoreria_cuenta_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY x.f_movimiento DESC, x.extracto_id DESC LIMIT 30";
    return ['movimientos' => asis_filas($ctx['c'], $sql, $types, ...$params)];
}

// ===================================================================================================
// PROPUESTAS (proponer_*) — NO GRABAN EN TABLAS DE NEGOCIO
// ===================================================================================================

function asis_validar_cuentas_contables(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $lista = (array)($e['cuentas'] ?? []);
    if (!$lista) throw new RuntimeException('Indicá al menos una cuenta contable para crear.');
    if (count($lista) > 20) throw new RuntimeException('Se pueden proponer hasta 20 cuentas por vez.');

    $validadas = [];
    $en_lote = []; // codigo => ['nivel' => int, 'naturaleza' => string]
    foreach ($lista as $item) {
        $codigo = trim((string)($item['codigo'] ?? ''));
        $nombre = trim((string)($item['nombre'] ?? ''));
        if ($codigo === '' || !preg_match('/^[0-9]+(\.[0-9]+)*$/', $codigo)) {
            throw new RuntimeException("Código de cuenta inválido: '$codigo' (usá números separados por puntos, ej. 1.1.03.11).");
        }
        if ($nombre === '') throw new RuntimeException("Falta el nombre de la cuenta $codigo.");
        if (isset($en_lote[$codigo])) throw new RuntimeException("El código $codigo está repetido en el pedido.");
        $dup = asis_fila($c, "SELECT cont_cuenta_id, nombre FROM gestion__cont_cuentas WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1", 'is', $emp, $codigo);
        if ($dup) throw new RuntimeException("Ya existe una cuenta con el código $codigo ({$dup['nombre']}).");

        $padre_id = intval($item['cuenta_padre_id'] ?? 0);
        $cod_padre = trim((string)($item['codigo_padre'] ?? ''));
        $padre_nombre = '';
        $padre_nivel = 0;
        $padre_nat = '';

        if ($padre_id > 0) {
            $p = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, nivel, naturaleza, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $padre_id, $emp);
            if (!$p) throw new RuntimeException("La cuenta padre ID $padre_id no existe en la empresa.");
            if (intval($p['es_imputable']) === 1) throw new RuntimeException("La cuenta padre {$p['codigo']} ({$p['nombre']}) es imputable y no puede tener subcuentas.");
            $cod_padre = $p['codigo'];
            $padre_nombre = $p['nombre'];
            $padre_nivel = intval($p['nivel']);
            $padre_nat = (string)$p['naturaleza'];
        } elseif ($cod_padre !== '') {
            if (isset($en_lote[$cod_padre])) {
                if ($en_lote[$cod_padre]['es_imputable']) {
                    throw new RuntimeException("La cuenta padre $cod_padre en el lote es imputable y no puede tener subcuentas.");
                }
                $padre_nombre = $en_lote[$cod_padre]['nombre'];
                $padre_nivel = $en_lote[$cod_padre]['nivel'];
                $padre_nat = $en_lote[$cod_padre]['naturaleza'];
            } else {
                $p = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, nivel, naturaleza, es_imputable FROM gestion__cont_cuentas WHERE codigo = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 LIMIT 1", 'si', $cod_padre, $emp);
                if (!$p) throw new RuntimeException("No se encontró la cuenta padre con código $cod_padre.");
                if (intval($p['es_imputable']) === 1) throw new RuntimeException("La cuenta padre {$p['codigo']} ({$p['nombre']}) es imputable.");
                $padre_id = intval($p['cont_cuenta_id']);
                $padre_nombre = $p['nombre'];
                $padre_nivel = intval($p['nivel']);
                $padre_nat = (string)$p['naturaleza'];
            }
        } else {
            throw new RuntimeException("Indicá cuenta_padre_id o codigo_padre para la cuenta $codigo.");
        }

        $es_imp = !empty($item['es_imputable']);
        $nat = strtoupper(trim((string)($item['naturaleza'] ?? '')));
        if (!in_array($nat, ['D', 'A'], true)) $nat = $padre_nat ?: 'D';
        $nivel = $padre_nivel + 2;
        if ($nivel > 9) $nivel = $padre_nivel + 1;

        $row = [
            'codigo'          => $codigo,
            'nombre'          => mb_substr($nombre, 0, 255),
            'cuenta_padre_id' => $padre_id,
            'codigo_padre'    => $cod_padre,
            'padre_nombre'    => $padre_nombre,
            'es_imputable'    => $es_imp,
            'naturaleza'      => $nat,
            'nivel'           => $nivel,
        ];
        $validadas[] = $row;
        $en_lote[$codigo] = $row;
    }

    return ['cuentas' => $validadas];
}

function asis_h_proponer_cuentas_contables(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'plan_cuentas', 'crear cuentas en el Plan de Cuentas');
    $v = asis_validar_cuentas_contables($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'CUENTAS_CONTABLES', $v);
    return ['propuesta_id' => $pid, 'cantidad' => count($v['cuentas']), 'cuentas' => $v['cuentas']];
}

function asis_validar_tipo_obligacion(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', trim((string)($e['codigo'] ?? ''))));
    $nombre = trim((string)($e['nombre'] ?? ''));
    $clase = strtoupper(trim((string)($e['clase'] ?? 'OBLIGACION'))) === 'ANTICIPO' ? 'ANTICIPO' : 'OBLIGACION';
    $rol = strtoupper(trim((string)($e['rol_entidad_codigo'] ?? '')));
    if (!in_array($rol, ['EMP', 'PRO', 'ORG'], true)) $rol = '';
    if ($codigo === '') throw new RuntimeException('Indicá un código para el tipo de obligación (ej. ANT_SOCIO).');
    if ($nombre === '') throw new RuntimeException('Indicá un nombre para el tipo de obligación.');

    $dup = asis_fila($c, "SELECT obligacion_tipo_id, nombre FROM gestion__obligaciones_tipos WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1", 'is', $emp, $codigo);
    if ($dup) throw new RuntimeException("Ya existe un tipo de obligación con código $codigo ({$dup['nombre']}).");

    $debe_id = intval($e['cont_cuenta_debe_id'] ?? 0);
    $debe_txt = '';
    if ($debe_id > 0) {
        $cd = asis_fila($c, "SELECT codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $debe_id, $emp);
        if (!$cd || intval($cd['es_imputable']) !== 1) throw new RuntimeException("La cuenta Debe ID $debe_id no existe o no es imputable.");
        $debe_txt = $cd['codigo'] . ' - ' . $cd['nombre'];
    }
    $pasivo_id = $clase === 'OBLIGACION' ? intval($e['cont_cuenta_pasivo_id'] ?? 0) : 0;
    $pasivo_txt = '';
    if ($pasivo_id > 0) {
        $cp = asis_fila($c, "SELECT codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $pasivo_id, $emp);
        if (!$cp || intval($cp['es_imputable']) !== 1) throw new RuntimeException("La cuenta Pasivo ID $pasivo_id no existe o no es imputable.");
        $pasivo_txt = $cp['codigo'] . ' - ' . $cp['nombre'];
    }

    return [
        'codigo'                 => mb_substr($codigo, 0, 30),
        'nombre'                 => mb_substr($nombre, 0, 120),
        'clase'                  => $clase,
        'comprobante_tipo_codigo'=> $clase === 'ANTICIPO' ? 'SOL_ANT' : 'OBL',
        'rol_entidad_codigo'     => $rol,
        'cont_cuenta_debe_id'    => $debe_id ?: null,
        'cuenta_debe_texto'      => $debe_txt,
        'cont_cuenta_pasivo_id'  => $pasivo_id ?: null,
        'cuenta_pasivo_texto'    => $pasivo_txt,
        'orden'                  => intval($e['orden'] ?? 20),
    ];
}

function asis_h_proponer_tipo_obligacion(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'obligaciones_tipos', 'crear tipos de obligación');
    $v = asis_validar_tipo_obligacion($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'TIPO_OBLIGACION', $v);
    return ['propuesta_id' => $pid, 'tipo' => $v];
}

function asis_validar_obligaciones_pago(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $lista = (array)($e['obligaciones'] ?? []);
    if (!$lista) throw new RuntimeException('Indicá al menos una obligación de pago.');
    if (count($lista) > 30) throw new RuntimeException('Se pueden proponer hasta 30 obligaciones por vez.');

    $validadas = [];
    $total = 0.0;
    foreach ($lista as $item) {
        $tipo_id = intval($item['obligacion_tipo_id'] ?? 0);
        $ent_id = intval($item['entidad_id'] ?? 0);
        $imp = round(floatval($item['importe'] ?? 0), 2);
        if ($imp <= 0) throw new RuntimeException('El importe de cada obligación debe ser mayor a 0.');

        $t = asis_fila($c, "SELECT obligacion_tipo_id, codigo, nombre, clase, permite_manual FROM gestion__obligaciones_tipos WHERE obligacion_tipo_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $tipo_id, $emp);
        if (!$t) throw new RuntimeException("Tipo de obligación ID $tipo_id no encontrado.");
        if (intval($t['permite_manual']) !== 1) throw new RuntimeException("El tipo {$t['nombre']} no admite carga manual.");

        $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
        if (!$ent) throw new RuntimeException("Beneficiario ID $ent_id no encontrado.");

        $f_em = trim((string)($item['f_emision'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_em)) $f_em = date('Y-m-d');
        $f_vto = trim((string)($item['f_vto'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_vto)) $f_vto = $f_em;

        $debe_id = intval($item['cont_cuenta_debe_id'] ?? 0);
        $debe_txt = '';
        if ($debe_id > 0) {
            $cd = asis_fila($c, "SELECT codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $debe_id, $emp);
            if (!$cd || intval($cd['es_imputable']) !== 1) throw new RuntimeException("La cuenta Debe ID $debe_id no es válida o no es imputable.");
            $debe_txt = $cd['codigo'] . ' - ' . $cd['nombre'];
        }
        $pasivo_id = intval($item['cont_cuenta_pasivo_id'] ?? 0);
        $pasivo_txt = '';
        if ($pasivo_id > 0) {
            $cp = asis_fila($c, "SELECT codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $pasivo_id, $emp);
            if (!$cp || intval($cp['es_imputable']) !== 1) throw new RuntimeException("La cuenta Pasivo ID $pasivo_id no es válida o no es imputable.");
            $pasivo_txt = $cp['codigo'] . ' - ' . $cp['nombre'];
        }

        $validadas[] = [
            'obligacion_tipo_id'    => $tipo_id,
            'tipo_codigo'           => $t['codigo'],
            'tipo_nombre'           => $t['nombre'],
            'clase'                 => $t['clase'],
            'entidad_id'            => $ent_id,
            'entidad_nombre'        => $ent['entidad_nombre'],
            'f_emision'             => $f_em,
            'f_vto'                 => $f_vto,
            'importe'               => $imp,
            'descripcion'           => mb_substr(trim((string)($item['descripcion'] ?? $t['nombre'])), 0, 255),
            'cont_cuenta_debe_id'   => $debe_id ?: null,
            'cuenta_debe_texto'     => $debe_txt,
            'cont_cuenta_pasivo_id' => $pasivo_id ?: null,
            'cuenta_pasivo_texto'   => $pasivo_txt,
        ];
        $total = round($total + $imp, 2);
    }

    return ['obligaciones' => $validadas, 'total' => $total];
}

function asis_h_proponer_obligacion_pago(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'obligaciones_pago', 'cargar obligaciones de pago');
    $v = asis_validar_obligaciones_pago($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'OBLIGACION_PAGO', $v);
    return ['propuesta_id' => $pid, 'cantidad' => count($v['obligaciones']), 'total' => $v['total']];
}

function asis_validar_asiento_contable(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $fecha = trim((string)($e['f_asiento'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
    $desc = trim((string)($e['descripcion'] ?? ''));
    if ($desc === '') throw new RuntimeException('Indicá una descripción para el asiento contable.');
    $lineas_in = (array)($e['lineas'] ?? []);
    if (count($lineas_in) < 2) throw new RuntimeException('Un asiento contable necesita al menos 2 líneas (Debe y Haber).');

    $lineas = [];
    $tot_debe = 0.0;
    $tot_haber = 0.0;
    foreach ($lineas_in as $l) {
        $cid = intval($l['cont_cuenta_id'] ?? 0);
        $debe = round(max(0.0, floatval($l['debe'] ?? 0)), 2);
        $haber = round(max(0.0, floatval($l['haber'] ?? 0)), 2);
        if ($debe <= 0 && $haber <= 0) continue;
        if ($debe > 0 && $haber > 0) throw new RuntimeException('Una línea del asiento no puede tener Debe y Haber a la vez.');
        $cta = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $cid, $emp);
        if (!$cta || intval($cta['es_imputable']) !== 1) {
            throw new RuntimeException("La cuenta ID $cid no existe o no es imputable.");
        }
        $lineas[] = [
            'cont_cuenta_id' => $cid,
            'codigo'         => $cta['codigo'],
            'nombre'         => $cta['nombre'],
            'debe'           => $debe,
            'haber'          => $haber,
            'descripcion'    => mb_substr(trim((string)($l['descripcion'] ?? $desc)), 0, 255),
        ];
        $tot_debe = round($tot_debe + $debe, 2);
        $tot_haber = round($tot_haber + $haber, 2);
    }
    if (count($lineas) < 2 || $tot_debe <= 0) throw new RuntimeException('El asiento debe tener importes positivos en Debe y Haber.');
    if (abs($tot_debe - $tot_haber) > 0.009) {
        throw new RuntimeException("El asiento no balancea: Debe ($tot_debe) ≠ Haber ($tot_haber).");
    }

    return [
        'f_asiento'   => $fecha,
        'descripcion' => mb_substr($desc, 0, 255),
        'lineas'      => $lineas,
        'total'       => $tot_debe,
    ];
}

function asis_h_proponer_asiento_contable(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'cont_asientos', 'cargar asientos contables');
    $v = asis_validar_asiento_contable($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'ASIENTO_CONTABLE', $v);
    return ['propuesta_id' => $pid, 'total' => $v['total'], 'lineas' => count($v['lineas'])];
}

function asis_validar_movimiento_tesoreria(array $ctx, array $e): array
{
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $tipo = strtoupper(trim((string)($e['tipo'] ?? '')));
    if (!in_array($tipo, ['INGRESO', 'EGRESO', 'TRANSFERENCIA'], true)) {
        throw new RuntimeException('El tipo debe ser INGRESO, EGRESO o TRANSFERENCIA.');
    }
    $fecha = trim((string)($e['f_movimiento'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
    $imp = round(floatval($e['importe'] ?? 0), 2);
    if ($imp <= 0) throw new RuntimeException('El importe debe ser mayor a 0.');
    $concepto = mb_substr(trim((string)($e['concepto'] ?? '')), 0, 255);
    if ($concepto === '') throw new RuntimeException('Indicá un concepto para el movimiento.');

    if ($tipo === 'TRANSFERENCIA') {
        $ori_id = intval($e['cuenta_origen_id'] ?? 0);
        $dst_id = intval($e['cuenta_destino_id'] ?? 0);
        if ($ori_id <= 0 || $dst_id <= 0 || $ori_id === $dst_id) {
            throw new RuntimeException('Indicá dos cuentas de tesorería distintas (origen y destino).');
        }
        $co = asis_fila($c, "SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.sucursal_id, tc.moneda_id, tc.cont_cuenta_id, cc.codigo AS cont_codigo, cc.nombre AS cont_nombre FROM gestion__tesoreria_cuentas tc LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id WHERE tc.tesoreria_cuenta_id = ? AND tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1", 'ii', $ori_id, $emp);
        $cd = asis_fila($c, "SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.sucursal_id, tc.moneda_id, tc.cont_cuenta_id, cc.codigo AS cont_codigo, cc.nombre AS cont_nombre FROM gestion__tesoreria_cuentas tc LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id WHERE tc.tesoreria_cuenta_id = ? AND tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1", 'ii', $dst_id, $emp);
        if (!$co || !$cd) throw new RuntimeException('Cuenta origen o destino no encontrada.');
        if (intval($co['cont_cuenta_id']) <= 0 || intval($cd['cont_cuenta_id']) <= 0) {
            throw new RuntimeException('Ambas cuentas de tesorería deben tener una cuenta contable asociada.');
        }
        return [
            'tipo'                  => 'TRANSFERENCIA',
            'f_movimiento'          => $fecha,
            'importe'               => $imp,
            'concepto'              => $concepto,
            'cuenta_origen_id'      => $ori_id,
            'cuenta_origen_nombre'  => $co['tesoreria_cuenta'],
            'cuenta_origen_cont_id' => intval($co['cont_cuenta_id']),
            'cuenta_destino_id'     => $dst_id,
            'cuenta_destino_nombre' => $cd['tesoreria_cuenta'],
            'cuenta_destino_cont_id'=> intval($cd['cont_cuenta_id']),
            'sucursal_id'           => intval($co['sucursal_id'] ?: $cd['sucursal_id'] ?: 0),
            'moneda_id'             => intval($co['moneda_id'] ?: 3),
        ];
    }

    $tc_id = intval($e['tesoreria_cuenta_id'] ?? 0);
    $cp_id = intval($e['cont_cuenta_contrapartida_id'] ?? 0);
    $tc = asis_fila($c, "SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.sucursal_id, tc.moneda_id, tc.cont_cuenta_id, cc.codigo AS cont_codigo, cc.nombre AS cont_nombre FROM gestion__tesoreria_cuentas tc LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = tc.cont_cuenta_id WHERE tc.tesoreria_cuenta_id = ? AND tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1", 'ii', $tc_id, $emp);
    if (!$tc) throw new RuntimeException("Cuenta de tesorería ID $tc_id no encontrada.");
    if (intval($tc['cont_cuenta_id']) <= 0) throw new RuntimeException("La cuenta {$tc['tesoreria_cuenta']} no tiene cuenta contable vinculada.");
    $cp = asis_fila($c, "SELECT cont_cuenta_id, codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $cp_id, $emp);
    if (!$cp || intval($cp['es_imputable']) !== 1) throw new RuntimeException("La cuenta contable contrapartida ID $cp_id no existe o no es imputable.");

    $ent_id = intval($e['entidad_id'] ?? 0);
    $ent_nom = '';
    if ($ent_id > 0) {
        $ent = asis_fila($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $ent_id, $emp);
        if (!$ent) throw new RuntimeException("Entidad ID $ent_id no encontrada.");
        $ent_nom = (string)$ent['entidad_nombre'];
    }

    return [
        'tipo'                         => $tipo,
        'f_movimiento'                 => $fecha,
        'importe'                      => $imp,
        'concepto'                     => $concepto,
        'entidad_id'                   => $ent_id,
        'entidad_nombre'               => $ent_nom,
        'tesoreria_cuenta_id'          => $tc_id,
        'tesoreria_cuenta_nombre'      => $tc['tesoreria_cuenta'],
        'tesoreria_cont_id'            => intval($tc['cont_cuenta_id']),
        'cont_cuenta_contrapartida_id' => $cp_id,
        'contrapartida_texto'          => $cp['codigo'] . ' - ' . $cp['nombre'],
        'sucursal_id'                  => intval($tc['sucursal_id'] ?: 0),
        'moneda_id'                    => intval($tc['moneda_id'] ?: 3),
    ];
}

function asis_h_proponer_movimiento_tesoreria(array $ctx, array $e): array
{
    asis_exigir_permiso($ctx, 'tesoreria_movimientos', 'registrar movimientos de tesorería');
    $v = asis_validar_movimiento_tesoreria($ctx, $e);
    $pid = asis_guardar_propuesta($ctx, 'MOV_TESORERIA', $v);
    return ['propuesta_id' => $pid, 'tipo' => $v['tipo'], 'importe' => $v['importe']];
}

// ===================================================================================================
// EJECUTORES AL CONFIRMAR (asis_grabar_*) — SOLO LLAMADOS POR asis_confirmar_propuesta()
// ===================================================================================================

function asis_grabar_cuentas_contables(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'plan_cuentas', 'crear cuentas en el Plan de Cuentas');
    $v = asis_validar_cuentas_contables($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];

    mysqli_begin_transaction($c);
    try {
        $creadas = []; // codigo => id
        $primero = 0;
        $stmt = mysqli_prepare($c, "INSERT INTO gestion__cont_cuentas
                (empresa_id, comprobante_id, codigo, nombre, naturaleza, cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco, tabla_estado_registro_id)
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, 0, 1)");
        foreach ($v['cuentas'] as $i => $cta) {
            $pid = intval($cta['cuenta_padre_id']);
            if ($pid <= 0 && isset($creadas[$cta['codigo_padre']])) {
                $pid = $creadas[$cta['codigo_padre']];
            }
            $es_imp = $cta['es_imputable'] ? 1 : 0;
            $ord = $i + 1;
            mysqli_stmt_bind_param($stmt, 'isssiiii', $emp, $cta['codigo'], $cta['nombre'], $cta['naturaleza'], $pid, $cta['nivel'], $ord, $es_imp);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException("Error creando cuenta {$cta['codigo']}: " . mysqli_stmt_error($stmt));
            }
            $nid = mysqli_insert_id($c);
            $creadas[$cta['codigo']] = $nid;
            if (!$primero) $primero = $nid;
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($c);
        return [$primero, count($creadas) . ' cuenta(s) contable(s) creada(s) en el Plan de Cuentas.'];
    } catch (Throwable $ex) {
        mysqli_rollback($c);
        throw $ex;
    }
}

function asis_grabar_tipo_obligacion(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'obligaciones_tipos', 'crear tipos de obligación');
    $v = asis_validar_tipo_obligacion($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];
    $rol = $v['rol_entidad_codigo'] !== '' ? $v['rol_entidad_codigo'] : null;

    $stmt = mysqli_prepare($c, "INSERT INTO gestion__obligaciones_tipos
            (empresa_id, codigo, nombre, clase, rol_entidad_codigo, comprobante_tipo_codigo,
             cont_cuenta_debe_id, cont_cuenta_pasivo_id, permite_manual, orden, tabla_estado_registro_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?)");
    mysqli_stmt_bind_param($stmt, 'isssssiiii',
        $emp, $v['codigo'], $v['nombre'], $v['clase'], $rol, $v['comprobante_tipo_codigo'],
        $v['cont_cuenta_debe_id'], $v['cont_cuenta_pasivo_id'], $v['orden'], $uid
    );
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('No se pudo crear el tipo de obligación: ' . mysqli_stmt_error($stmt));
    }
    $id = mysqli_insert_id($c);
    mysqli_stmt_close($stmt);
    return [$id, "Tipo de obligación {$v['codigo']} ({$v['nombre']}) creado."];
}

function asis_grabar_obligacion_pago(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'obligaciones_pago', 'cargar obligaciones de pago');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once __DIR__ . '/../obligaciones/obligaciones_service.php';

    $v = asis_validar_obligaciones_pago($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];

    mysqli_begin_transaction($c);
    try {
        $ids = [];
        foreach ($v['obligaciones'] as $o) {
            $id = obl_guardar($c, $emp, $uid, [
                'obligacion_tipo_id'    => $o['obligacion_tipo_id'],
                'entidad_id'            => $o['entidad_id'],
                'f_emision'             => $o['f_emision'],
                'f_vto'                 => $o['f_vto'],
                'importe_total'         => $o['importe'],
                'descripcion'           => $o['descripcion'],
                'cont_cuenta_debe_id'   => $o['cont_cuenta_debe_id'],
                'cont_cuenta_pasivo_id' => $o['cont_cuenta_pasivo_id'],
            ], true);
            $ids[] = $id;
        }
        mysqli_commit($c);
        return [$ids[0], count($ids) . ' obligación(es) de pago guardada(s) en Borrador por ' . fmt_moneda($v['total']) . '.'];
    } catch (Throwable $ex) {
        mysqli_rollback($c);
        throw $ex;
    }
}

function asis_grabar_asiento_contable(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'cont_asientos', 'cargar asientos contables');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once __DIR__ . '/../cont_periodos.php';

    $v = asis_validar_asiento_contable($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];

    cont_periodo_exigir_abierto($c, $emp, $v['f_asiento'], 'registrar el asiento contable');

    $suc = asis_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
    $suc_id = intval($suc['sucursal_id'] ?? 1);

    mysqli_begin_transaction($c);
    try {
        $detalles = array_map(fn($l) => [
            'cuenta_id'   => $l['cont_cuenta_id'],
            'debe'        => $l['debe'],
            'haber'       => $l['haber'],
            'descripcion' => $l['descripcion'],
        ], $v['lineas']);
        $asiento_id = tesoreriaGenerarAsiento($c, [
            'empresa_id'  => $emp,
            'sucursal_id' => $suc_id,
            'f_asiento'   => $v['f_asiento'],
            'descripcion' => $v['descripcion'],
            'usuario_id'  => $uid,
        ], $detalles);
        mysqli_commit($c);
        return [intval($asiento_id), "Asiento contable #$asiento_id registrado por " . fmt_moneda($v['total']) . '.'];
    } catch (Throwable $ex) {
        mysqli_rollback($c);
        throw $ex;
    }
}

function asis_grabar_movimiento_tesoreria(array $ctx, array $d): array
{
    asis_exigir_permiso($ctx, 'tesoreria_movimientos', 'registrar movimientos de tesorería');
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once __DIR__ . '/../cont_periodos.php';

    $v = asis_validar_movimiento_tesoreria($ctx, $d);
    $c = $ctx['c'];
    $emp = $ctx['empresa_id'];
    $uid = $ctx['usuario_id'];

    cont_periodo_exigir_abierto($c, $emp, $v['f_movimiento'], 'registrar el movimiento de tesorería');

    $suc_id = $v['sucursal_id'];
    if ($suc_id <= 0) {
        $suc = asis_fila($c, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1", 'i', $emp);
        $suc_id = intval($suc['sucursal_id'] ?? 1);
    }

    if ($v['tipo'] === 'TRANSFERENCIA') {
        require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
        try {
            $res_tr = registrarTransferenciaInterna($c, [
                'sucursal_id'       => $suc_id,
                'f_movimiento'      => $v['f_movimiento'],
                'cuenta_origen_id'  => $v['cuenta_origen_id'],
                'cuenta_destino_id' => $v['cuenta_destino_id'],
                'importe'           => $v['importe'],
                'moneda_id'         => $v['moneda_id'],
                'observaciones'     => $v['concepto'],
            ], $emp, $uid);
        } catch (Exception $ex) {
            throw new RuntimeException($ex->getMessage());
        }
        $comp_id = intval($res_tr['comprobante_id'] ?? 0);
        $asiento_id = intval($res_tr['asiento_id'] ?? 0);
        return [$comp_id ?: 1, "Transferencia por " . fmt_moneda($v['importe']) . " registrada" . ($asiento_id ? " (Asiento #$asiento_id)" : '') . '.'];
    }

    // INGRESO o EGRESO contra cuenta contable (ej. Aporte de socio): genera comprobante soporte MC en Borrador (3)
    // para que pueda revisarse, imprimirse, aprobarse y confirmarse en tesoreria_movimientos.php.
    require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
    try {
        $res_om = registrarOtroMovimientoTesoreria($c, [
            'tipo_movimiento'              => $v['tipo'],
            'tesoreria_cuenta_id'          => $v['tesoreria_cuenta_id'],
            'cont_cuenta_contrapartida_id' => $v['cont_cuenta_contrapartida_id'],
            'entidad_id'                   => intval($v['entidad_id'] ?? 0),
            'sucursal_id'                  => $suc_id,
            'f_movimiento'                 => $v['f_movimiento'],
            'importe'                      => $v['importe'],
            'concepto'                     => $v['concepto'],
            'estado_inicial'               => 3,
        ], $emp, $uid);
    } catch (Exception $ex) {
        throw new RuntimeException($ex->getMessage());
    }
    return [intval($res_om['comprobante_id'] ?? 1), (string)($res_om['message'] ?? "Comprobante {$v['tipo']} creado en Borrador.")];
}

function asis_h_consultar_comprobantes_tesoreria(array $ctx, array $e): array
{
    if (!asis_tiene_acceso_pagina($ctx, 'tesoreria_movimientos')) {
        throw new RuntimeException('No tenés acceso a Movimientos de Tesorería.');
    }
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
    $filtros = [];
    if (!empty($e['estado_id'])) $filtros['estado_id'] = intval($e['estado_id']);
    $res = obtenerOtrosMovimientosListado($ctx['c'], $ctx['empresa_id'], $filtros, intval($ctx['paginas']['tesoreria_movimientos'] ?? 0));
    return ['comprobantes' => array_slice((array)($res['data'] ?? []), 0, 30)];
}

function asis_h_proponer_estado_comprobante_tesoreria(array $ctx, array $e): array
{
    $cid = intval($e['comprobante_id'] ?? 0);
    $acc = trim((string)($e['accion'] ?? ''));
    $motivo = mb_substr(trim((string)($e['motivo'] ?? '')), 0, 250);
    $validas = ['enviar_aprobacion', 'confirmar', 'aprobar', 'rechazar', 'eliminar', 'anular'];
    if ($cid <= 0) throw new RuntimeException('Indicá un comprobante_id válido.');
    if (!in_array($acc, $validas, true)) throw new RuntimeException('Acción de ciclo de vida inválida.');
    if (!asis_puede($ctx, 'tesoreria_movimientos', $acc)) {
        throw new RuntimeException("Tu perfil no tiene habilitada la función '$acc' en Movimientos de Tesorería.");
    }

    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
    $comp = obtenerOtroMovimientoPorId($ctx['c'], $cid, $ctx['empresa_id']);
    if (!$comp) throw new RuntimeException("Comprobante MC #$cid no encontrado.");

    $est = intval($comp['tabla_estado_registro_id']);
    $reglas = [
        'enviar_aprobacion' => [3],
        'confirmar'         => [3],
        'aprobar'           => [4],
        'rechazar'          => [4],
        'eliminar'          => [3, 4],
        'anular'            => [5],
    ];
    if (!in_array($est, $reglas[$acc], true)) {
        throw new RuntimeException("El comprobante {$comp['numero_formateado']} está en estado '{$comp['estado_registro']}' y no admite la acción '$acc'.");
    }

    $etiquetas = [
        'enviar_aprobacion' => 'Enviar a Aprobación (Borrador → Pendiente)',
        'confirmar'         => 'Confirmar e impactar caja y contabilidad (Borrador → Confirmado)',
        'aprobar'           => 'Aprobar e impactar caja y contabilidad (Pendiente → Confirmado)',
        'rechazar'          => 'Rechazar y devolver a Borrador (Pendiente → Borrador)',
        'eliminar'          => 'Eliminar Borrador',
        'anular'            => 'Anular Comprobante y revertir caja/asiento (Confirmado → Anulado)',
    ];
    $datos = [
        'comprobante_id'  => $cid,
        'numero'          => $comp['numero_formateado'],
        'tipo_movimiento' => $comp['tipo_movimiento'],
        'f_emision'       => $comp['f_emision'],
        'entidad_nombre'  => $comp['entidad_nombre'] ?? '',
        'cuenta_nombre'   => $comp['cuenta_nombre'] ?? '',
        'importe_total'   => floatval($comp['importe_total']),
        'concepto'        => $comp['observaciones'],
        'estado_actual'   => $comp['estado_registro'],
        'accion'          => $acc,
        'accion_texto'    => $etiquetas[$acc],
        'motivo'          => $motivo,
    ];
    $pid = asis_guardar_propuesta($ctx, 'ESTADO_TESO_MC', $datos);
    return ['propuesta_id' => $pid, 'numero' => $comp['numero_formateado'], 'accion' => $etiquetas[$acc]];
}

function asis_grabar_estado_comprobante_tesoreria(array $ctx, array $d): array
{
    $cid = intval($d['comprobante_id'] ?? 0);
    $acc = trim((string)($d['accion'] ?? ''));
    $motivo = trim((string)($d['motivo'] ?? ''));
    if (!asis_puede($ctx, 'tesoreria_movimientos', $acc)) {
        throw new RuntimeException("Tu perfil no tiene permiso para '$acc' en Movimientos de Tesorería.");
    }
    require_once $ctx['modulo_dir'] . '/tesoreria_core_model.php';
    require_once $ctx['modulo_dir'] . '/tesoreria_movimientos_model.php';
    try {
        switch ($acc) {
            case 'enviar_aprobacion': $r = enviarAprobacionOtroMovimiento($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id']); break;
            case 'confirmar':         $r = confirmarOtroMovimientoTesoreria($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id'], false); break;
            case 'aprobar':           $r = confirmarOtroMovimientoTesoreria($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id'], true); break;
            case 'rechazar':          $r = rechazarOtroMovimiento($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id'], $motivo); break;
            case 'eliminar':          $r = eliminarOtroMovimientoBorrador($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id']); break;
            case 'anular':            $r = anularOtroMovimientoTesoreria($ctx['c'], $cid, $ctx['empresa_id'], $ctx['usuario_id']); break;
            default: throw new RuntimeException('Acción desconocida.');
        }
    } catch (Exception $ex) {
        throw new RuntimeException($ex->getMessage());
    }
    return [$cid, (string)($r['message'] ?? 'Estado del comprobante actualizado.')];
}
