<?php
/**
 * Modelo: Importar resúmenes bancarios en la empresa del cliente del estudio (Mini-Gestión, página 417, botón
 * "Importar resumen bancario", minigestion_banco.js + importar_banco_ajax.php). Script 20261108_estudio_importar_banco_01.sql.
 *
 * Los gastos del banco (comisiones, ley 25.413, intereses) con su IVA y sus percepciones (IVA, IIBB por provincia,
 * Ganancias, SIRCREB) se cargan como en Gastos Bancarios de Gestión: un comprobante de compra "Resumen bancario"
 * (C_RB, código ARCA 039) por banco y mes al proveedor del banco, que entra al Libro IVA al confirmarlo. A diferencia
 * de Gestión no hay tesorería: no se guarda el extracto ni se concilia, el C_RB no lleva cuenta debitada ni OP
 * automática (queda en la cuenta corriente del proveedor, como las compras importadas de ARCA).
 *
 * Reutiliza:
 *   - lectura del extracto: formato del banco de la empresa del ESTUDIO (Gestión > Formatos Bancarios) o lectura con IA
 *     con la clave del estudio (core/bancos/extracto_parser.php / extracto_ia.php);
 *   - clasificación: conceptos y reglas bancarias de la empresa del CLIENTE (core/bancos/extracto_reglas.php); las
 *     asignaciones a mano se aprenden como en Conciliación Bancaria (bco_regla_aprender);
 *   - armado del comprobante: bco_gastos_componer() (core/bancos/bancos_gastos_service.php), el mismo de Gestión;
 *   - grabación y confirmación: agregarFacturaProveedor + ia_confirmar_compras (motor de Gestión Normal, página 557).
 * Preparar la empresa del cliente copia de la plantilla del estudio (o, si la plantilla no tiene conceptos bancarios,
 * de la empresa del estudio) lo que falte: tipos / alícuotas / plan (ia_preparar), conceptos bancarios con sus
 * productos y reglas, y la configuración de compras de cada percepción con la cuenta remapeada por código.
 *
 * Requiere gestion_normal/facturas_proveedores_model.php (lo carga el ajax) para fpArcaBuscarProveedor,
 * fpAsegurarProveedorArca, agregarFacturaProveedor y ejecutarTransicionEstado.
 */

declare(strict_types=1);

require_once __DIR__ . '/importar_arca_model.php';
require_once __DIR__ . '/../../core/bancos/bancos_gastos_service.php';
require_once __DIR__ . '/../../core/bancos/extracto_ia.php';

const IB_ROLES_IMPUESTO = ['PERCEPCION_IVA', 'PERCEPCION_IIBB', 'PERCEPCION_GANANCIAS', 'RETENCION_IIBB'];

// ------------------------------------------------------------------------------------------- combos

/** Formatos bancarios activos de la empresa del estudio (para leer sin IA) y si hay lectura con IA. */
function ib_lectores(mysqli $c, int $estudio_id): array
{
    $formatos = ia_filas($c, "SELECT f.formato_id, f.formato, f.banco_id, b.banco FROM gestion__bancos_formatos f
                              JOIN conf__bancos b ON b.banco_id = f.banco_id
                              WHERE f.empresa_id = ? AND f.tabla_estado_registro_id = 1 ORDER BY b.banco, f.formato", "i", [$estudio_id]);
    return ['formatos' => $formatos, 'ia' => bco_ia_clave_empresa($c, $estudio_id) !== ''];
}

/** Proveedores del cliente que ya tienen resúmenes bancarios (C_RB) o el rol BAN: sugerencias para el banco. */
function ib_proveedores_banco(mysqli $c, int $empresa_id): array
{
    return ia_filas($c, "SELECT e.entidad_id, e.entidad_nombre, e.cuit,
                                (SELECT MAX(fp.comprobante_pv) FROM gestion__facturas_proveedores fp
                                 JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id AND ct.codigo = 'C_RB'
                                 WHERE fp.empresa_id = e.empresa_id AND fp.entidad_id = e.entidad_id) AS pv_banco
                         FROM gestion__entidades e
                         WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1
                           AND (" . ent_sql_tiene_rol('e.entidad_id', 'BAN') . "
                                OR EXISTS (SELECT 1 FROM gestion__facturas_proveedores fp
                                           JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = fp.comprobante_tipo_id AND ct.codigo = 'C_RB'
                                           WHERE fp.empresa_id = e.empresa_id AND fp.entidad_id = e.entidad_id))
                         ORDER BY e.entidad_nombre", "i", [$empresa_id]);
}

// ------------------------------------------------------------------------------------- lectura

/**
 * Lee el extracto: con un formato de la empresa del estudio ($formato_id > 0, tiene que ser del banco elegido) o con
 * IA (clave del estudio; el resultado se cachea por archivo, así importar no vuelve a llamar a la IA).
 */
function ib_leer_extracto(mysqli $c, int $estudio_id, int $banco_id, int $formato_id, string $ruta): array
{
    if ($formato_id > 0) {
        $f = bco_formato_obtener($c, $estudio_id, $formato_id);
        if (!$f || (int)$f['tabla_estado_registro_id'] !== 1) {
            throw new RuntimeException('Elegí un formato activo.');
        }
        if ((int)$f['banco_id'] !== $banco_id) {
            throw new RuntimeException('El formato es de ' . $f['banco'] . ': elegí el banco del formato o leé el archivo con IA.');
        }
        return bco_extracto_desde_archivo($ruta, $f);
    }
    return bco_extracto_desde_archivo_ia($c, $estudio_id, $ruta);
}

/** Banco del catálogo (conf__bancos) activo. */
function ib_banco(mysqli $c, int $banco_id): array
{
    $r = ia_filas($c, "SELECT banco_id, banco, codigo_bcra FROM conf__bancos WHERE banco_id = ? AND tabla_estado_registro_id = 1", "i", [$banco_id]);
    if (!$r) {
        throw new RuntimeException('Elegí el banco del resumen.');
    }
    return $r[0];
}

/** Punto de venta del C_RB: el código BCRA del banco (estable para el banco; si no es numérico, el id). */
function ib_pv_banco(array $banco): int
{
    $cod = (int)preg_replace('/\D/', '', (string)$banco['codigo_bcra']);
    return $cod > 0 ? $cod : (int)$banco['banco_id'];
}

// ------------------------------------------------------------------------------ prerrequisitos

/** Tipo C_RB activo de la empresa del cliente (o null). */
function ib_tipo_crb(mysqli $c, int $empresa_id): ?array
{
    $r = ia_filas($c, "SELECT comprobante_tipo_id, comprobante_tipo, requiere_debito_cuenta FROM gestion__comprobantes_tipos
                       WHERE empresa_id = ? AND codigo = 'C_RB' AND tabla_estado_registro_id = 1 ORDER BY comprobante_tipo_id LIMIT 1", "i", [$empresa_id]);
    return $r ? $r[0] : null;
}

/** Qué le falta a la empresa del cliente para cargar resúmenes bancarios (sin mirar el archivo). */
function ib_diagnostico(mysqli $c, int $empresa_id): array
{
    $tipo = ib_tipo_crb($c, $empresa_id);
    $ng = ia_filas($c, "SELECT 1 FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND es_no_gravado = 1 AND tabla_estado_registro_id = 1 LIMIT 1",
                   "i", [$empresa_id]);
    $suc = ia_filas($c, "SELECT COUNT(*) n FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1", "i", [$empresa_id]);
    $conceptos = ia_filas($c, "SELECT COUNT(*) n FROM gestion__bancos_conceptos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND naturaleza = 'GASTO_BANCARIO'",
                          "i", [$empresa_id]);
    // Conceptos de gasto sin producto con cuenta, o percepciones sin su configuración de compras vigente
    $incompletos = [];
    foreach (bco_gastos_conceptos_info($c, $empresa_id) as $k) {
        if ($k['naturaleza'] !== 'GASTO_BANCARIO') {
            continue;
        }
        if ($k['rol'] === 'ITEM' && (!$k['producto_nombre'] || !$k['cont_cuenta_compra_id'])) {
            $incompletos[] = $k['concepto'] . ' (sin producto con cuenta de compra)';
        }
        if (in_array($k['rol'], IB_ROLES_IMPUESTO, true) && $k['impuesto_tipo_id']
            && !ib_config_impuesto($c, $empresa_id, (int)$k['impuesto_tipo_id'], ib_jurisdiccion_concepto($k), date('Y-m-d'))) {
            $incompletos[] = $k['concepto'] . ' (sin configuración de ' . $k['impuesto_tipo'] . ($k['jurisdiccion_nombre'] ? ' ' . $k['jurisdiccion_nombre'] : '') . ')';
        }
    }
    $d = [
        'tipo_crb' => $tipo === null,
        'crb_debito' => $tipo !== null && (int)$tipo['requiere_debito_cuenta'] === 1 && !ib_tiene_cuentas_banco($c, $empresa_id),
        'no_gravado' => !$ng,
        'sucursal' => (int)$suc[0]['n'] === 0,
        'conceptos' => (int)$conceptos[0]['n'] === 0,
        'incompletos' => $incompletos,
    ];
    $d['ok'] = !$d['tipo_crb'] && !$d['crb_debito'] && !$d['no_gravado'] && !$d['sucursal'] && !$d['conceptos'] && !$incompletos;
    return $d;
}

function ib_tiene_cuentas_banco(mysqli $c, int $empresa_id): bool
{
    return (bool)ia_filas($c, "SELECT 1 FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND cuenta_tipo = 'BANCO' AND tabla_estado_registro_id = 1 LIMIT 1",
                          "i", [$empresa_id]);
}

function ib_jurisdiccion_concepto(array $k): int
{
    return in_array($k['rol'], BCO_ROLES_JURISDICCION, true) ? (int)($k['jurisdiccion_id'] ?? 0) : 0;
}

/** Configuración de compras vigente a $fecha del impuesto (y provincia, si $jur > 0). Igual criterio que bco_gastos_componer. */
function ib_config_impuesto(mysqli $c, int $empresa_id, int $impuesto_tipo_id, int $jur, string $fecha): ?int
{
    $r = ia_filas($c, "SELECT cfg.empresa_impuesto_config_id FROM gestion__empresas_impuestos_config cfg
                       JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id AND it.aplica_compra = 1
                       WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND (? = 0 OR cfg.jurisdiccion_id = ?) AND cfg.tabla_estado_registro_id = 1
                         AND (cfg.f_desde IS NULL OR cfg.f_desde <= ?) AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
                       ORDER BY cfg.empresa_impuesto_config_id LIMIT 1", "iiiiss", [$empresa_id, $impuesto_tipo_id, $jur, $jur, $fecha, $fecha]);
    return $r ? (int)$r[0]['empresa_impuesto_config_id'] : null;
}

/** Empresa de donde se copian conceptos, reglas y configuraciones: la plantilla si tiene conceptos bancarios, si no el estudio. */
function ib_empresa_fuente(mysqli $c, int $estudio_id, int $plantilla_id): int
{
    $origen = 0;
    if ($plantilla_id > 0) {
        foreach (estudio_plantillas_listar($c, $estudio_id) as $p) {
            if ((int)$p['plantilla_id'] === $plantilla_id) {
                $origen = (int)$p['empresa_origen_id'];
            }
        }
    }
    foreach ([$origen, $estudio_id] as $e) {
        if ($e > 0 && ia_filas($c, "SELECT 1 FROM gestion__bancos_conceptos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND naturaleza = 'GASTO_BANCARIO' LIMIT 1",
                                "i", [$e])) {
            return $e;
        }
    }
    return 0;
}

/** Cuenta de la empresa destino con el mismo código que la cuenta origen (0 si no hay). */
function ib_cuenta_por_codigo(mysqli $c, int $cuenta_origen_id, int $empresa_destino): int
{
    if ($cuenta_origen_id <= 0) {
        return 0;
    }
    $r = ia_filas($c, "SELECT d.cont_cuenta_id FROM gestion__cont_cuentas o
                       JOIN gestion__cont_cuentas d ON d.empresa_id = ? AND d.codigo = o.codigo AND d.tabla_estado_registro_id = 1
                       WHERE o.cont_cuenta_id = ? ORDER BY d.es_imputable DESC, d.cont_cuenta_id LIMIT 1", "ii", [$empresa_destino, $cuenta_origen_id]);
    return $r ? (int)$r[0]['cont_cuenta_id'] : 0;
}

/**
 * Deja la empresa del cliente lista para cargar resúmenes bancarios. Pasos (idempotentes, por código):
 *   1. tipos de comprobante, alícuota No gravado, plan de cuentas y sucursal: ia_preparar (copia autorizada desde la plantilla);
 *   2. el C_RB sin cuenta debitada si la empresa no tiene cuentas bancarias de tesorería (no hay OP automática);
 *   3. conceptos bancarios que falten (con sus productos de compra, cuenta remapeada por código) y sus reglas;
 *   4. configuración de compras de cada percepción de los conceptos, con la cuenta de la fuente remapeada.
 * Requiere los modelos de copia (los carga el ajax, como en la importación de ARCA).
 */
function ib_preparar(mysqli $c, int $estudio_id, int $usuario_id, int $empresa_id, int $plantilla_id): array
{
    $pasos = [];
    $paso = function (string $n, bool $ok, string $d = '') use (&$pasos) { $pasos[] = ['paso' => $n, 'ok' => $ok, 'detalle' => $d]; };

    // 1. Tipos (039 = Resumen bancario en el subgrupo FC), No gravado y sucursal con el mismo asistente que ARCA
    $diag = ib_diagnostico($c, $empresa_id);
    if ($diag['tipo_crb'] || $diag['no_gravado'] || $diag['sucursal']) {
        $req = ['tipos' => $diag['tipo_crb'] ? [39 => true] : [], 'pvs' => [], 'tipos_por_pv' => [], 'alicuotas' => ['21.00' => true],
                'no_gravado' => true, 'exento' => false];
        $r = ia_preparar($c, $estudio_id, $usuario_id, $empresa_id, 'RECIBIDOS', $req, $plantilla_id);
        foreach ($r['pasos'] as $p) {
            $pasos[] = $p;
        }
        if (!ib_tipo_crb($c, $empresa_id)) {
            $paso('Tipo "Resumen bancario" (C_RB)', false, 'La plantilla no tiene el tipo C_RB: créelo en la plantilla (Tipos de Comprobante, código ARCA 039) y vuelva a preparar.');
            return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => ib_diagnostico($c, $empresa_id)];
        }
    }

    // 2. Sin tesorería: el C_RB se registra sin cuenta debitada (en Gestión la exige para la OP automática)
    $tipo = ib_tipo_crb($c, $empresa_id);
    if ($tipo && (int)$tipo['requiere_debito_cuenta'] === 1 && !ib_tiene_cuentas_banco($c, $empresa_id)) {
        $st = mysqli_prepare($c, "UPDATE gestion__comprobantes_tipos SET requiere_debito_cuenta = 0 WHERE comprobante_tipo_id = ? AND empresa_id = ?");
        $tid = (int)$tipo['comprobante_tipo_id'];
        mysqli_stmt_bind_param($st, "ii", $tid, $empresa_id);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $paso('Resumen bancario sin débito en cuenta', $ok, 'La empresa no lleva tesorería: el C_RB queda en la cuenta corriente del banco.');
    }

    // 3 y 4. Conceptos, productos, reglas y configuraciones desde la fuente
    $fuente = ib_empresa_fuente($c, $estudio_id, $plantilla_id);
    if ($fuente <= 0) {
        $paso('Conceptos bancarios', false, 'Ni la plantilla ni la empresa del estudio tienen Conceptos Bancarios de gasto: cárguelos en Gestión (Conceptos Bancarios) y vuelva a preparar.');
        return ['ok' => false, 'pasos' => $pasos, 'diagnostico' => ib_diagnostico($c, $empresa_id)];
    }
    if ($fuente !== $empresa_id) {
        mysqli_begin_transaction($c);
        try {
            $mapa = ib_copiar_conceptos($c, $fuente, $empresa_id, $usuario_id, $paso);
            ib_copiar_reglas($c, $fuente, $empresa_id, $usuario_id, $mapa, $paso);
            ib_copiar_configuraciones($c, $fuente, $empresa_id, $paso);
            mysqli_commit($c);
        } catch (Throwable $e) {
            mysqli_rollback($c);
            $paso('Conceptos bancarios', false, $e->getMessage());
        }
    }

    $diag = ib_diagnostico($c, $empresa_id);
    if (!$pasos) {
        $paso('Empresa del cliente', true, 'No le falta nada para cargar resúmenes bancarios');
    }
    return ['ok' => $diag['ok'], 'pasos' => $pasos, 'diagnostico' => $diag];
}

/**
 * Copia los conceptos bancarios activos de la fuente que el cliente no tiene (por código) y, para los de rol ITEM,
 * su producto (por código; si falta se crea con la cuenta remapeada por código, o la de GASTOS_GENERALES del cliente).
 * Devuelve [concepto_id fuente => concepto_id cliente] de todos los conceptos con el mismo código.
 */
function ib_copiar_conceptos(mysqli $c, int $fuente, int $empresa_id, int $usuario_id, callable $paso): array
{
    $mapa = [];
    $creados = [];
    $existentes = [];
    foreach (ia_filas($c, "SELECT concepto_id, codigo FROM gestion__bancos_conceptos WHERE empresa_id = ?", "i", [$empresa_id]) as $k) {
        $existentes[$k['codigo']] = (int)$k['concepto_id'];
    }
    $src = ia_filas($c, "SELECT k.*, p.producto_codigo, p.producto_nombre, p.cont_cuenta_compra_id, p.cont_cuenta_id AS p_cuenta,
                                i.porcentaje AS iva_porcentaje, i.es_gravado, i.es_no_gravado, i.es_exento
                         FROM gestion__bancos_conceptos k
                         LEFT JOIN gestion__productos p ON p.producto_id = k.producto_id AND p.empresa_id = k.empresa_id
                         LEFT JOIN gestion__impuestos__iva_alicuotas i ON i.iva_alicuota_id = p.iva_alicuota_id
                         WHERE k.empresa_id = ? AND k.tabla_estado_registro_id = 1 ORDER BY k.orden, k.concepto_id", "i", [$fuente]);
    foreach ($src as $k) {
        if (isset($existentes[$k['codigo']])) {
            $mapa[(int)$k['concepto_id']] = $existentes[$k['codigo']];
            continue;
        }
        $producto_id = null;
        if ($k['rol'] === 'ITEM' && $k['producto_codigo']) {
            $producto_id = ib_producto_destino($c, $k, $empresa_id);
            if (!$producto_id) {
                $paso('Concepto ' . $k['concepto'], false, 'No se pudo crear su producto: falta la cuenta (mismo código del plan o concepto contable GASTOS_GENERALES) o la alícuota de IVA.');
                continue;
            }
        }
        $st = mysqli_prepare($c, "INSERT INTO gestion__bancos_conceptos (empresa_id, codigo, concepto, naturaleza, rol, iva_incluido, producto_id,
                                         impuesto_tipo_id, jurisdiccion_id, es_cobranza, orden, tabla_estado_registro_id, usuario_id)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1, ?)");
        $iva_inc = (int)$k['iva_incluido'];
        $imp = $k['impuesto_tipo_id'] !== null ? (int)$k['impuesto_tipo_id'] : null;
        $jur = $k['jurisdiccion_id'] !== null ? (int)$k['jurisdiccion_id'] : null;
        $orden = (int)$k['orden'];
        mysqli_stmt_bind_param($st, "issssiiiiii", $empresa_id, $k['codigo'], $k['concepto'], $k['naturaleza'], $k['rol'], $iva_inc,
                               $producto_id, $imp, $jur, $orden, $usuario_id);
        mysqli_stmt_execute($st);
        $nuevo = (int)mysqli_insert_id($c);
        mysqli_stmt_close($st);
        $mapa[(int)$k['concepto_id']] = $nuevo;
        $existentes[$k['codigo']] = $nuevo;
        $creados[] = $k['concepto'];
    }
    if ($creados) {
        $paso('Conceptos bancarios', true, 'Copiados: ' . implode(', ', $creados));
    }
    return $mapa;
}

/** Producto del cliente para un concepto ITEM de la fuente: el de mismo código, o uno nuevo con la cuenta remapeada. */
function ib_producto_destino(mysqli $c, array $k, int $empresa_id): ?int
{
    $r = ia_filas($c, "SELECT producto_id FROM gestion__productos WHERE empresa_id = ? AND producto_codigo = ? ORDER BY tabla_estado_registro_id, producto_id LIMIT 1",
                  "is", [$empresa_id, $k['producto_codigo']]);
    if ($r) {
        return (int)$r[0]['producto_id'];
    }
    $cuenta = ib_cuenta_por_codigo($c, (int)($k['cont_cuenta_compra_id'] ?: $k['p_cuenta']), $empresa_id)
              ?: emp_cuenta_concepto_id($c, $empresa_id, 'GASTOS_GENERALES');
    if ((int)$k['es_no_gravado'] === 1 || (int)$k['es_exento'] === 1) {
        $col = (int)$k['es_no_gravado'] === 1 ? 'es_no_gravado' : 'es_exento';
        $a = ia_filas($c, "SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND $col = 1 AND tabla_estado_registro_id = 1
                           ORDER BY iva_alicuota_id LIMIT 1", "i", [$empresa_id]);
        $iva = $a ? (int)$a[0]['iva_alicuota_id'] : 0;
    } else {
        $iva = emp_iva_alicuota_id($c, $empresa_id, (float)($k['iva_porcentaje'] ?? 21));
    }
    if ($cuenta <= 0 || $iva <= 0) {
        return null;
    }
    $tipo = ia_filas($c, "SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                          ORDER BY (producto_tipo LIKE 'Servicio%') DESC, producto_tipo_id LIMIT 1", "i", [$empresa_id]);
    $tipo_id = $tipo ? (int)$tipo[0]['producto_tipo_id'] : 1;
    $st = mysqli_prepare($c, "INSERT INTO gestion__productos (empresa_id, producto_codigo, producto_nombre, producto_tipo_id, cont_cuenta_id, cont_cuenta_compra_id,
                                      iva_alicuota_id, controla_stock, es_servicio, aplica_compra, aplica_venta, tabla_estado_registro_id)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 1, 0, 1)");
    mysqli_stmt_bind_param($st, "issiiii", $empresa_id, $k['producto_codigo'], $k['producto_nombre'], $tipo_id, $cuenta, $cuenta, $iva);
    mysqli_stmt_execute($st);
    $id = (int)mysqli_insert_id($c);
    mysqli_stmt_close($st);
    return $id ?: null;
}

/** Copia las reglas activas de la fuente (por banco, tipo, texto y sentido) que el cliente no tiene, con el concepto remapeado. */
function ib_copiar_reglas(mysqli $c, int $fuente, int $empresa_id, int $usuario_id, array $mapa, callable $paso): void
{
    $ya = [];
    foreach (ia_filas($c, "SELECT banco_id, tipo_coincidencia, patron, aplica_a FROM gestion__bancos_reglas WHERE empresa_id = ?", "i", [$empresa_id]) as $r) {
        $ya[$r['banco_id'] . '|' . $r['tipo_coincidencia'] . '|' . $r['aplica_a'] . '|' . $r['patron']] = true;
    }
    $n = 0;
    foreach (ia_filas($c, "SELECT * FROM gestion__bancos_reglas WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY regla_id", "i", [$fuente]) as $r) {
        $clave = $r['banco_id'] . '|' . $r['tipo_coincidencia'] . '|' . $r['aplica_a'] . '|' . $r['patron'];
        $concepto = $mapa[(int)$r['concepto_id']] ?? 0;
        if (isset($ya[$clave]) || $concepto <= 0) {
            continue;
        }
        $st = mysqli_prepare($c, "INSERT INTO gestion__bancos_reglas (empresa_id, banco_id, concepto_id, prioridad, tipo_coincidencia, patron, aplica_a, origen,
                                         tabla_estado_registro_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $banco = (int)$r['banco_id'];
        $prio = (int)$r['prioridad'];
        mysqli_stmt_bind_param($st, "iiiissssi", $empresa_id, $banco, $concepto, $prio, $r['tipo_coincidencia'], $r['patron'], $r['aplica_a'], $r['origen'], $usuario_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $ya[$clave] = true;
        $n++;
    }
    if ($n) {
        $paso('Reglas bancarias', true, "Copiadas: $n");
    }
}

/**
 * Configuración de compras de cada percepción de los conceptos del cliente (impuesto + provincia en IIBB) que no
 * tenga una vigente: copia la de la fuente del mismo impuesto (de esa provincia o, si no, de otra) con la cuenta
 * remapeada por código, vigente desde 2000-01-01 (sirve para extractos viejos).
 */
function ib_copiar_configuraciones(mysqli $c, int $fuente, int $empresa_id, callable $paso): void
{
    $hoy = date('Y-m-d');
    foreach (bco_gastos_conceptos_info($c, $empresa_id) as $k) {
        if (!in_array($k['rol'], IB_ROLES_IMPUESTO, true) || !$k['impuesto_tipo_id']) {
            continue;
        }
        $imp = (int)$k['impuesto_tipo_id'];
        $jur = ib_jurisdiccion_concepto($k);
        if (ib_config_impuesto($c, $empresa_id, $imp, $jur, $hoy)) {
            continue;
        }
        $nombre = $k['impuesto_tipo'] . ($k['jurisdiccion_nombre'] ? ' ' . $k['jurisdiccion_nombre'] : '');
        $src = ia_filas($c, "SELECT cfg.cont_cuenta_id, cfg.jurisdiccion_id, cfg.base_calculo FROM gestion__empresas_impuestos_config cfg
                             WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.tabla_estado_registro_id = 1 AND cfg.cont_cuenta_id IS NOT NULL
                             ORDER BY (cfg.jurisdiccion_id <=> ?) DESC, (cfg.f_hasta IS NULL) DESC, cfg.empresa_impuesto_config_id DESC LIMIT 1",
                        "iii", [$fuente, $imp, $jur]);
        $cuenta = $src ? ib_cuenta_por_codigo($c, (int)$src[0]['cont_cuenta_id'], $empresa_id) : 0;
        if ($cuenta <= 0) {
            $paso($nombre, false, $src ? 'La cuenta de la plantilla no existe en el plan del cliente (mismo código).'
                                      : 'La plantilla no tiene configurado ese impuesto de compras: configúrelo allí (Impuestos de la empresa).');
            continue;
        }
        $jur_db = $jur > 0 ? $jur : ($src[0]['jurisdiccion_id'] !== null ? (int)$src[0]['jurisdiccion_id'] : null);
        $base = (string)($src[0]['base_calculo'] ?: 'NETO_GRAVADO');
        $st = mysqli_prepare($c, "INSERT INTO gestion__empresas_impuestos_config (empresa_id, impuesto_tipo_id, jurisdiccion_id, cont_cuenta_id, base_calculo,
                                         alicuota, aplica_siempre, prioridad, f_desde, tipo_calculo, tabla_estado_registro_id)
                                  VALUES (?, ?, ?, ?, ?, 0, 0, 1, '2000-01-01', 'manual', 1)");
        mysqli_stmt_bind_param($st, "iiiis", $empresa_id, $imp, $jur_db, $cuenta, $base);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $paso($nombre, $ok, $ok ? 'Configurada con la cuenta de la plantilla' : 'No se pudo configurar');
    }
}

// ------------------------------------------------------------------------------------- análisis

/**
 * Lee, clasifica y arma un comprobante por mes (sin escribir). $asignaciones: [nro_linea => concepto_id] elegidos a mano
 * (concepto del cliente). $proveedor: ['cuit', 'nombre'] del banco en la empresa del cliente.
 */
function ib_analizar(mysqli $c, int $estudio_id, int $empresa_id, array $banco, int $formato_id, string $ruta, array $asignaciones, array $proveedor): array
{
    $x = ib_leer_extracto($c, $estudio_id, (int)$banco['banco_id'], $formato_id, $ruta);
    $diag = ib_diagnostico($c, $empresa_id);
    $info = bco_gastos_conceptos_info($c, $empresa_id);

    // Clasificación: reglas del banco en la empresa del cliente, después lo elegido a mano
    $movs = bco_reglas_clasificar($x['movimientos'], bco_reglas_activas($c, $empresa_id, (int)$banco['banco_id']));
    foreach ($movs as &$m) {
        $m['extracto_id'] = (int)$m['nro_linea'];
        $manual = (int)($asignaciones[$m['nro_linea']] ?? 0);
        if ($manual > 0 && isset($info[$manual])) {
            $m['concepto_id'] = $manual;
            $m['concepto_origen'] = 'MANUAL';
        }
        $k = $m['concepto_id'] ? ($info[(int)$m['concepto_id']] ?? null) : null;
        foreach (['concepto', 'naturaleza', 'rol', 'producto_id', 'producto_nombre', 'iva_alicuota_id', 'iva_porcentaje', 'es_gravado',
                  'cont_cuenta_compra_id', 'impuesto_tipo_id', 'impuesto_tipo', 'jurisdiccion_id', 'jurisdiccion_nombre'] as $campo) {
            $m[$campo] = $k[$campo] ?? null;
        }
        if (!$k) {
            $m['concepto_id'] = null;
            $m['concepto_origen'] = null;
        }
    }
    unset($m);

    // Proveedor del banco y tipo C_RB
    $tipo = ib_tipo_crb($c, $empresa_id);
    $cuit = preg_replace('/\D/', '', (string)($proveedor['cuit'] ?? ''));
    $prov = strlen($cuit) === 11 ? fpArcaBuscarProveedor($c, $empresa_id, $cuit) : null;
    $pv = ib_pv_banco($banco);

    // Un comprobante por mes con las líneas de gasto del mes
    $por_mes = [];
    foreach ($movs as $m) {
        $por_mes[substr((string)$m['f_movimiento'], 0, 7)][] = $m;
    }
    ksort($por_mes);
    $meses = [];
    foreach ($por_mes as $mes => $lineas) {
        $desde = $mes . '-01';
        $hasta = date('Y-m-t', strtotime($desde));
        $gastos = array_values(array_filter($lineas, static fn($l) => $l['naturaleza'] === 'GASTO_BANCARIO'));
        $sin_concepto = count(array_filter($lineas, static fn($l) => !$l['concepto_id'] && (float)$l['importe'] < 0));
        $comp = bco_gastos_componer($c, $empresa_id, $gastos, $hasta);
        $incl = $comp['incluidas'];
        $bloqueos = $comp['bloqueos'];
        $avisos = [];
        if ($sin_concepto) {
            $bloqueos[] = "Hay $sin_concepto débitos del mes sin concepto: elegí el concepto de cada uno (un gasto o un movimiento propio).";
        }
        if (!$incl) {
            $bloqueos[] = 'No hay gastos del banco en el mes.';
        } elseif ($comp['impuestos'] && !$comp['detalles']) {
            $bloqueos[] = 'Las percepciones / SIRCREB no pueden ir solas: el resumen necesita al menos un gasto con producto.';
        }
        if (!$tipo) {
            $bloqueos[] = 'La empresa del cliente no tiene el tipo "Resumen bancario" (C_RB): prepárela.';
        }
        if (strlen($cuit) !== 11) {
            $bloqueos[] = 'Indicá el CUIT del banco (proveedor del resumen).';
        }
        $total = round(-array_sum(array_column($incl, 'importe')), 2);
        $existentes = [];
        if ($tipo && $prov) {
            $base = (int)str_replace('-', '', $mes) * 100;
            $existentes = ia_filas($c, "SELECT factura_proveedor_id, comprobante_nro, total, tabla_estado_registro_id FROM gestion__facturas_proveedores
                                        WHERE empresa_id = ? AND entidad_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ?
                                          AND comprobante_nro BETWEEN ? AND ? AND tabla_estado_registro_id NOT IN (6, 38) ORDER BY comprobante_nro",
                                   "iiiiii", [$empresa_id, (int)$prov['entidad_id'], (int)$tipo['comprobante_tipo_id'], $pv, $base + 1, $base + 99]);
            foreach ($existentes as $e) {
                if (abs((float)$e['total'] - $total) < 0.01) {
                    $bloqueos[] = 'Ya está cargado: resumen n° ' . $e['comprobante_nro'] . ' por el mismo total.';
                }
            }
            if ($existentes && count($bloqueos) === 0) {
                $avisos[] = 'Ya hay ' . count($existentes) . ' resumen(es) del banco en el mes por otro total (otra cuenta o parte del mes): este será otro comprobante.';
            }
        }
        $incompleto = $x['f_desde'] > $desde || $x['f_hasta'] < $hasta;
        if ($incompleto) {
            $avisos[] = 'El extracto no cubre el mes completo (' . fmt_fecha(max($x['f_desde'], $desde)) . ' a ' . fmt_fecha(min($x['f_hasta'], $hasta))
                . '): si lo cargás, el resto del mes irá en otro comprobante.';
        }
        $meses[] = [
            'mes' => $mes, 'f_emision' => $incl ? max(array_column($incl, 'f_movimiento')) : null, 'total' => $total,
            'lineas_gasto' => count($incl), 'detalles' => $comp['detalles'], 'impuestos' => array_values($comp['impuestos']),
            'iva_extracto' => $comp['iva_extracto'], 'bloqueos' => array_values(array_unique($bloqueos)), 'avisos' => $avisos,
            'existentes' => $existentes, 'incompleto' => $incompleto, 'generable' => !$bloqueos,
            '_incluidas' => $incl,
        ];
    }

    $conceptos = array_values(array_map(static fn($k) => ['concepto_id' => (int)$k['concepto_id'], 'concepto' => $k['concepto'],
                                                          'naturaleza' => $k['naturaleza'], 'rol' => $k['rol']], $info));
    return [
        'extracto' => ['cuenta_archivo' => $x['cuenta_archivo'], 'f_desde' => $x['f_desde'], 'f_hasta' => $x['f_hasta'],
                       'saldo_inicial' => $x['saldo_inicial'], 'saldo_final' => $x['saldo_final'], 'debitos' => $x['debitos'], 'creditos' => $x['creditos'],
                       'avisos' => array_values(array_merge($x['avisos'] ?? [], $x['control']['errores'] ?? []))],
        'diagnostico' => $diag,
        'proveedor' => $prov ? ['entidad_id' => (int)$prov['entidad_id'], 'nombre' => $prov['entidad_nombre'], 'existe' => true]
                             : ['entidad_id' => 0, 'nombre' => (string)($proveedor['nombre'] ?? ''), 'existe' => false],
        'pv' => $pv,
        'conceptos' => $conceptos,
        'movimientos' => array_map(static fn($m) => ['nro_linea' => (int)$m['nro_linea'], 'f_movimiento' => $m['f_movimiento'],
                                                     'descripcion' => $m['descripcion'], 'referencia' => $m['referencia'] ?? null,
                                                     'importe' => (float)$m['importe'], 'concepto_id' => $m['concepto_id'] ? (int)$m['concepto_id'] : null,
                                                     'concepto' => $m['concepto'], 'naturaleza' => $m['naturaleza'], 'origen' => $m['concepto_origen']], $movs),
        'meses' => $meses,
        '_movs' => $movs,
    ];
}

/** Quita del análisis lo interno antes de mandarlo al navegador. */
function ib_publico(array $a): array
{
    unset($a['_movs']);
    foreach ($a['meses'] as &$m) {
        unset($m['_incluidas']);
    }
    unset($m);
    return $a;
}

// -------------------------------------------------------------------------------------- importar

/**
 * Graba los meses elegidos: aprende las asignaciones manuales (reglas IGUAL del banco en la empresa del cliente),
 * asegura el proveedor del banco y crea un C_RB por mes en Borrador con agregarFacturaProveedor (número AAAAMMNN, PV =
 * código BCRA del banco). Si $confirmar, aprueba y confirma cada uno (Libro IVA y asiento). Serializa por empresa + banco.
 */
function ib_importar(mysqli $c, int $estudio_id, int $usuario_id, int $empresa_id, array $banco, int $formato_id, string $ruta,
                     array $asignaciones, array $proveedor, array $meses_elegidos, bool $confirmar, int $fp_pagina_id): array
{
    $lock = 'ib_banco_' . $empresa_id . '_' . (int)$banco['banco_id'];
    $l = ia_filas($c, "SELECT GET_LOCK(?, 5) l", "s", [$lock]);
    if ((int)($l[0]['l'] ?? 0) !== 1) {
        throw new RuntimeException('Otro usuario está cargando resúmenes de este banco para el cliente: probá en unos segundos.');
    }
    try {
        $a = ib_analizar($c, $estudio_id, $empresa_id, $banco, $formato_id, $ruta, $asignaciones, $proveedor);
        if (!$a['diagnostico']['ok']) {
            throw new RuntimeException('Primero prepare la empresa del cliente (faltan prerrequisitos).');
        }
        $elegidos = array_flip(array_map('strval', $meses_elegidos));
        $meses = array_values(array_filter($a['meses'], static fn($m) => isset($elegidos[$m['mes']])));
        if (!$meses) {
            throw new RuntimeException('Elegí los meses a cargar.');
        }
        foreach ($meses as $m) {
            if (!$m['generable']) {
                throw new RuntimeException($m['mes'] . ': ' . implode(' ', $m['bloqueos']));
            }
        }

        // Reglas aprendidas y proveedor del banco
        $cuit = preg_replace('/\D/', '', (string)$proveedor['cuit']);
        $condicion = ia_condicion_pago_compras($c, $empresa_id);
        mysqli_begin_transaction($c);
        try {
            $aprendidas = 0;
            foreach ($a['_movs'] as $mv) {
                if ($mv['concepto_origen'] === 'MANUAL' && $mv['concepto_id']) {
                    bco_regla_aprender($c, $empresa_id, $usuario_id, (int)$banco['banco_id'], (string)$mv['descripcion'], (float)$mv['importe'], (int)$mv['concepto_id']);
                    $aprendidas++;
                }
            }
            $nombre = trim((string)($proveedor['nombre'] ?? '')) ?: (string)$banco['banco'];
            $prov = fpAsegurarProveedorArca($c, $empresa_id, $cuit, $nombre, $condicion, 'A', $usuario_id);
            mysqli_commit($c);
        } catch (Throwable $e) {
            mysqli_rollback($c);
            throw $e;
        }

        $tipo = ib_tipo_crb($c, $empresa_id);
        $tipo_id = (int)$tipo['comprobante_tipo_id'];
        $pv = ib_pv_banco($banco);
        $sucursal = ia_sucursal_compras($c, $empresa_id);
        $moneda = emp_moneda_base_id($c, $empresa_id);
        $res = ['creados' => [], 'errores' => [], 'aprendidas' => $aprendidas, 'confirmacion' => null];
        foreach ($meses as $m) {
            $base = (int)str_replace('-', '', $m['mes']) * 100;
            $ult = ia_filas($c, "SELECT MAX(comprobante_nro) n FROM gestion__facturas_proveedores
                                 WHERE empresa_id = ? AND entidad_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ? AND comprobante_nro BETWEEN ? AND ?",
                            "iiiiii", [$empresa_id, (int)$prov['entidad_id'], $tipo_id, $pv, $base + 1, $base + 99]);
            $nro = $ult && $ult[0]['n'] ? (int)$ult[0]['n'] + 1 : $base + 1;
            if ($nro > $base + 99) {
                $res['errores'][] = $m['mes'] . ': se agotó la numeración del mes.';
                continue;
            }
            $detalles = [];
            $subtotal = $no_gravado = $iva = 0.0;
            foreach ($m['detalles'] as $d) {
                $detalles[] = ['producto_id' => $d['producto_id'], 'cantidad' => 1, 'precio_unitario' => $d['neto'], 'neto_gravado' => $d['neto'],
                               'iva_alicuota_id' => $d['iva_alicuota_id'], 'iva_porcentaje' => $d['iva_porcentaje'],
                               'no_gravado' => $d['no_gravado'], 'exento' => 0];
                $subtotal += $d['neto'];
                $no_gravado += $d['no_gravado'];
                $iva += $d['iva'];
            }
            $impuestos = array_map(static fn($i) => ['empresa_impuesto_config_id' => $i['empresa_impuesto_config_id'], 'importe' => $i['importe'],
                                                     'tipo_origen' => 'manual', 'detalle_origen' => 'Resumen bancario'], $m['impuestos']);
            try {
                $r = agregarFacturaProveedor($c, [
                    'empresa_idx' => $empresa_id, 'pagina_idx' => $fp_pagina_id, 'comprobante_tipo_id' => $tipo_id,
                    'comprobante_pv' => $pv, 'comprobante_nro' => $nro, 'entidad_id' => (int)$prov['entidad_id'],
                    'entidad_sucursal_id' => (int)$prov['entidad_sucursal_id'] ?: null, 'sucursal_id' => $sucursal ?: null,
                    'f_emision' => $m['f_emision'], 'f_contabilidad' => $m['f_emision'], 'f_vencimiento' => $m['f_emision'],
                    'condicion_pago_id' => $condicion ?: null, 'moneda_id' => $moneda, 'tipo_cambio' => 1,
                    'subtotal' => round($subtotal, 2), 'no_gravado' => round($no_gravado, 2), 'exento' => 0, 'descuentos' => 0, 'impuestos' => round($iva, 2),
                    'otros_impuestos' => 0, 'detalles' => $detalles, 'impuestos_adicionales' => $impuestos,
                    'observaciones' => 'Resumen bancario ' . $banco['banco'] . ($a['extracto']['cuenta_archivo'] ? ' cta. ' . $a['extracto']['cuenta_archivo'] : '')
                                       . ' ' . $m['mes'] . ' (importado desde Mini-Gestión del estudio)',
                ]);
            } catch (Throwable $e) {
                $r = ['resultado' => false, 'error' => $e->getMessage()];
            }
            if (empty($r['resultado'])) {
                $res['errores'][] = $m['mes'] . ': ' . ($r['error'] ?? 'no se pudo crear el comprobante');
                continue;
            }
            $id = (int)$r['factura_proveedor_id'];
            $tot = ia_filas($c, "SELECT total FROM gestion__facturas_proveedores WHERE factura_proveedor_id = ? AND empresa_id = ?", "ii", [$id, $empresa_id]);
            if (abs((float)($tot[0]['total'] ?? 0) - $m['total']) >= 0.01) {
                $res['errores'][] = $m['mes'] . ': el total del comprobante (' . fmt_moneda((float)($tot[0]['total'] ?? 0)) . ') no coincide con los gastos del extracto ('
                    . fmt_moneda($m['total']) . '); quedó en Borrador (id ' . $id . ') para revisarlo.';
                continue;
            }
            $res['creados'][] = ['factura_proveedor_id' => $id, 'mes' => $m['mes'], 'numero' => sprintf('%05d-%08d', $pv, $nro), 'total' => $m['total']];
        }
        if ($confirmar && $res['creados']) {
            $borrador = (int)obtenerEstadoInicialPagina($c, $fp_pagina_id) ?: 3;
            $res['confirmacion'] = ia_confirmar_compras($c, $empresa_id, $fp_pagina_id, $borrador, array_column($res['creados'], 'factura_proveedor_id'));
        }
        error_log('=== ib_importar empresa ' . $empresa_id . ' banco ' . $banco['banco_id'] . ': ' . count($res['creados']) . ' C_RB, ' . count($res['errores']) . ' errores ===');
        return $res;
    } finally {
        ia_filas($c, "SELECT RELEASE_LOCK(?) l", "s", [$lock]);
    }
}
