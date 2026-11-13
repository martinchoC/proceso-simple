<?php
/**
 * Conciliación bancaria: configuración por empresa. Formatos de extracto por banco (gestion__bancos_formatos +
 * _formatos_columnas, tabla 442), conceptos (gestion__bancos_conceptos, 443) y reglas de clasificación
 * (gestion__bancos_reglas, 444). Diccionarios Activo (1) / Inactivo (2).
 */

require_once __DIR__ . '/bancos_db.php';

const BCO_CAMPOS_COLUMNA = ['FECHA', 'FECHA_VALOR', 'DESCRIPCION', 'REFERENCIA', 'IMPORTE', 'DEBITO', 'CREDITO', 'SALDO'];

// ---------------------------------------------------------------------------------------------------------
// Formatos
// ---------------------------------------------------------------------------------------------------------

function bco_formatos_listar(mysqli $c, int $empresa_id, int $pagina_id): array
{
    $col = bco_columna_estado($c);
    $rows = bco_filas($c,
        "SELECT f.formato_id, f.formato, f.banco_id, b.banco, f.tipo_archivo, f.hoja, f.fila_inicio, f.formato_fecha, f.orden_archivo,
                f.modo_importe, f.f_desde, f.f_hasta, f.tabla_estado_registro_id, er.$col AS estado_registro, er.codigo_estandar,
                (SELECT GROUP_CONCAT(CONCAT(fc.campo, '=', CHAR(65 + fc.columna_indice)) ORDER BY fc.columna_indice SEPARATOR ', ')
                   FROM gestion__bancos_formatos_columnas fc WHERE fc.formato_id = f.formato_id) AS columnas,
                (SELECT COUNT(*) FROM gestion__bancos_importaciones i WHERE i.formato_id = f.formato_id AND i.empresa_id = f.empresa_id) AS importaciones
         FROM gestion__bancos_formatos f
         JOIN conf__bancos b ON b.banco_id = f.banco_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
         WHERE f.empresa_id = ?
         ORDER BY f.tabla_estado_registro_id, b.banco, f.f_desde DESC", 'i', $empresa_id);
    return bco_con_botones($c, $pagina_id, $rows);
}

/** Formato de la empresa con su mapa de columnas ('columnas' => ['FECHA' => 1, ...]), o null. */
function bco_formato_obtener(mysqli $c, int $empresa_id, int $formato_id): ?array
{
    $f = bco_fila($c, "SELECT f.*, b.banco FROM gestion__bancos_formatos f JOIN conf__bancos b ON b.banco_id = f.banco_id
                       WHERE f.formato_id = ? AND f.empresa_id = ?", 'ii', $formato_id, $empresa_id);
    if (!$f) return null;
    $f['columnas'] = [];
    foreach (bco_filas($c, "SELECT campo, columna_indice FROM gestion__bancos_formatos_columnas WHERE formato_id = ?", 'i', $formato_id) as $col) {
        $f['columnas'][$col['campo']] = intval($col['columna_indice']);
    }
    return $f;
}

/** Normaliza y valida los datos del formulario. Devuelve [campos, columnas]. */
function bco_formato_validar(mysqli $c, array $d): array
{
    $tipo = strtoupper(trim((string)($d['tipo_archivo'] ?? '')));
    if (!in_array($tipo, ['XLS', 'XLSX', 'CSV'], true)) throw new RuntimeException('Elegí el tipo de archivo.');
    $banco_id = intval($d['banco_id'] ?? 0);
    if (!bco_fila($c, "SELECT 1 FROM conf__bancos WHERE banco_id = ? AND tabla_estado_registro_id = 1", 'i', $banco_id)) {
        throw new RuntimeException('Elegí el banco.');
    }
    $nombre = trim((string)($d['formato'] ?? ''));
    if ($nombre === '' || mb_strlen($nombre) > 100) throw new RuntimeException('Poné un nombre al formato (hasta 100 caracteres).');
    // Se guarda en formato PHP: "dd-mmm-aaaa" (estilo planilla) queda "d-M-Y"
    $fecha_fmt = bco_formato_fecha_normalizar((string)($d['formato_fecha'] ?? 'd/m/Y'));
    if (!preg_match('~^[dmMYy/\-. ]{3,20}$~', $fecha_fmt) || !preg_match('/d/', $fecha_fmt) || !preg_match('/[mM]/', $fecha_fmt) || !preg_match('/[Yy]/', $fecha_fmt)) {
        throw new RuntimeException('Formato de fecha no válido: indicá día, mes y año, p. ej. d/m/Y (21/10/2026) o d-M-Y (21-oct-2026).');
    }
    $fila_inicio = intval($d['fila_inicio'] ?? 0);
    if ($fila_inicio < 1 || $fila_inicio > 1000) throw new RuntimeException('La primera fila con datos va de 1 a 1000.');
    $sep_dec = (string)($d['sep_decimal'] ?? ',');
    if (!in_array($sep_dec, [',', '.'], true)) throw new RuntimeException('El separador decimal es coma o punto.');
    $sep_miles = (string)($d['sep_miles'] ?? '');
    if (!in_array($sep_miles, ['', '.', ',', ' '], true) || $sep_miles === $sep_dec) throw new RuntimeException('El separador de miles no puede ser igual al decimal.');
    $delim = (string)($d['delimitador'] ?? '');
    if ($tipo === 'CSV' && !in_array($delim, [';', ',', "\t", '|', ''], true)) throw new RuntimeException('Delimitador de CSV no válido.');
    $modo = (string)($d['modo_importe'] ?? 'SIGNO');
    if (!in_array($modo, ['SIGNO', 'DEBITO_CREDITO'], true)) throw new RuntimeException('Elegí cómo vienen los importes.');
    foreach (['cuenta_regex' => 100, 'referencia_regex' => 200] as $campo => $max) {
        $v = trim((string)($d[$campo] ?? ''));
        if ($v !== '' && ($err = bco_regex_validar($v))) throw new RuntimeException(($campo === 'cuenta_regex' ? 'N° de cuenta: ' : 'Referencia: ') . $err);
        if (mb_strlen($v) > $max) throw new RuntimeException('La expresión es demasiado larga.');
    }
    $f_desde = bco_fecha_valida((string)($d['f_desde'] ?? '')) ?? throw new RuntimeException('Indicá desde cuándo vale el formato.');
    $f_hasta = bco_fecha_valida((string)($d['f_hasta'] ?? ''));
    if ($f_hasta !== null && $f_hasta < $f_desde) throw new RuntimeException('La vigencia termina antes de empezar.');
    $codif = strtoupper(trim((string)($d['codificacion'] ?? 'UTF-8')));
    if (!in_array($codif, ['UTF-8', 'WINDOWS-1252', 'ISO-8859-1'], true)) throw new RuntimeException('Codificación no válida.');

    $columnas = [];
    foreach (BCO_CAMPOS_COLUMNA as $campo) {
        $v = $d['col_' . strtolower($campo)] ?? '';
        if ($v === '' || $v === null) continue;
        $v = is_numeric($v) ? intval($v) : bco_planilla_columna_indice((string)$v);
        if ($v < 0 || $v > 200) throw new RuntimeException("Columna de $campo no válida.");
        $columnas[$campo] = $v;
    }
    if (!isset($columnas['FECHA'], $columnas['DESCRIPCION'])) throw new RuntimeException('Indicá las columnas de Fecha y Descripción.');
    if ($modo === 'SIGNO' && !isset($columnas['IMPORTE'])) throw new RuntimeException('Indicá la columna del importe.');
    if ($modo === 'DEBITO_CREDITO' && !isset($columnas['DEBITO'], $columnas['CREDITO'])) throw new RuntimeException('Indicá las columnas de débito y crédito.');

    $cuenta_fila = intval($d['cuenta_fila'] ?? 0) ?: null;
    $cuenta_col = ($d['cuenta_columna'] ?? '') === '' ? null : (is_numeric($d['cuenta_columna']) ? intval($d['cuenta_columna']) : bco_planilla_columna_indice((string)$d['cuenta_columna']));
    $hoja = trim((string)($d['hoja'] ?? ''));

    return [[
        'banco_id' => $banco_id, 'formato' => $nombre, 'tipo_archivo' => $tipo, 'hoja' => $hoja === '' ? null : mb_substr($hoja, 0, 60),
        'fila_inicio' => $fila_inicio, 'delimitador' => $tipo === 'CSV' && $delim !== '' ? $delim : null, 'codificacion' => $codif,
        'formato_fecha' => $fecha_fmt, 'idioma_fecha' => ($d['idioma_fecha'] ?? 'ES') === 'EN' ? 'EN' : 'ES',
        'orden_archivo' => ($d['orden_archivo'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC', 'saldo_posterior' => empty($d['saldo_anterior']) ? 1 : 0,
        'cuenta_fila' => $cuenta_fila, 'cuenta_columna' => $cuenta_col,
        'cuenta_regex' => trim((string)($d['cuenta_regex'] ?? '')) ?: null, 'referencia_regex' => trim((string)($d['referencia_regex'] ?? '')) ?: null,
        'sep_decimal' => $sep_dec, 'sep_miles' => $sep_miles === '' ? null : $sep_miles, 'modo_importe' => $modo,
        'f_desde' => $f_desde, 'f_hasta' => $f_hasta,
    ], $columnas];
}

function bco_formato_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    $id = intval($d['formato_id'] ?? 0);
    if ($id > 0 && !bco_formato_obtener($c, $empresa_id, $id)) throw new RuntimeException('Formato no encontrado.');
    [$f, $columnas] = bco_formato_validar($c, $d);

    mysqli_begin_transaction($c);
    try {
        if ($id > 0) {
            bco_exec($c, "UPDATE gestion__bancos_formatos SET banco_id = ?, formato = ?, tipo_archivo = ?, hoja = ?, fila_inicio = ?, delimitador = ?,
                                 codificacion = ?, formato_fecha = ?, idioma_fecha = ?, orden_archivo = ?, saldo_posterior = ?, cuenta_fila = ?,
                                 cuenta_columna = ?, cuenta_regex = ?, referencia_regex = ?, sep_decimal = ?, sep_miles = ?, modo_importe = ?,
                                 f_desde = ?, f_hasta = ?, usuario_modificacion_id = ?
                          WHERE formato_id = ? AND empresa_id = ?",
                'isssisssssiiisssssssiii',
                $f['banco_id'], $f['formato'], $f['tipo_archivo'], $f['hoja'], $f['fila_inicio'], $f['delimitador'], $f['codificacion'],
                $f['formato_fecha'], $f['idioma_fecha'], $f['orden_archivo'], $f['saldo_posterior'], $f['cuenta_fila'], $f['cuenta_columna'],
                $f['cuenta_regex'], $f['referencia_regex'], $f['sep_decimal'], $f['sep_miles'], $f['modo_importe'], $f['f_desde'], $f['f_hasta'],
                $usuario_id, $id, $empresa_id);
            bco_exec($c, "DELETE FROM gestion__bancos_formatos_columnas WHERE formato_id = ?", 'i', $id);
        } else {
            bco_exec($c, "INSERT INTO gestion__bancos_formatos (empresa_id, banco_id, formato, tipo_archivo, hoja, fila_inicio, delimitador, codificacion,
                                 formato_fecha, idioma_fecha, orden_archivo, saldo_posterior, cuenta_fila, cuenta_columna, cuenta_regex, referencia_regex,
                                 sep_decimal, sep_miles, modo_importe, f_desde, f_hasta, tabla_estado_registro_id, usuario_id)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                'iisssisssssiiisssssssi',
                $empresa_id, $f['banco_id'], $f['formato'], $f['tipo_archivo'], $f['hoja'], $f['fila_inicio'], $f['delimitador'], $f['codificacion'],
                $f['formato_fecha'], $f['idioma_fecha'], $f['orden_archivo'], $f['saldo_posterior'], $f['cuenta_fila'], $f['cuenta_columna'],
                $f['cuenta_regex'], $f['referencia_regex'], $f['sep_decimal'], $f['sep_miles'], $f['modo_importe'], $f['f_desde'], $f['f_hasta'],
                $usuario_id);
            $id = intval(mysqli_insert_id($c));
        }
        foreach ($columnas as $campo => $indice) {
            bco_exec($c, "INSERT INTO gestion__bancos_formatos_columnas (formato_id, campo, columna_indice) VALUES (?, ?, ?)", 'isi', $id, $campo, $indice);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    error_log("=== bco_formato_guardar empresa $empresa_id formato $id ===");
    return $id;
}

/** Probar un formato (guardado o los datos del formulario sin guardar) contra un archivo: no persiste nada. */
function bco_formato_probar(mysqli $c, int $empresa_id, array $d, string $ruta): array
{
    $id = intval($d['formato_id'] ?? 0);
    if ($id > 0 && empty($d['banco_id'])) {
        $formato = bco_formato_obtener($c, $empresa_id, $id) ?? throw new RuntimeException('Formato no encontrado.');
    } else {
        [$formato, $columnas] = bco_formato_validar($c, $d);
        $formato['columnas'] = $columnas;
    }
    $x = bco_extracto_desde_archivo($ruta, $formato);
    $x['muestra'] = array_slice($x['movimientos'], 0, 15);
    $x['cantidad'] = count($x['movimientos']);
    unset($x['movimientos']);
    return $x;
}

function bco_formato_accion(mysqli $c, int $empresa_id, int $pagina_id, int $id, string $accion_js): int
{
    $f = bco_fila($c, "SELECT tabla_estado_registro_id e FROM gestion__bancos_formatos WHERE formato_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id)
        ?? throw new RuntimeException('Formato no encontrado.');
    $destino = bco_destino_accion($c, $pagina_id, $accion_js, intval($f['e']));
    bco_exec($c, "UPDATE gestion__bancos_formatos SET tabla_estado_registro_id = ? WHERE formato_id = ? AND empresa_id = ?", 'iii', $destino, $id, $empresa_id);
    return $destino;
}

/** Formatos activos y vigentes del banco de la cuenta (para importar). */
function bco_formatos_para_cuenta(mysqli $c, int $empresa_id, int $cuenta_id): array
{
    $cta = bco_cuenta($c, $empresa_id, $cuenta_id);
    $hoy = date('Y-m-d');
    return bco_filas($c, "SELECT f.formato_id, f.formato, f.tipo_archivo, b.banco FROM gestion__bancos_formatos f
                          JOIN conf__bancos b ON b.banco_id = f.banco_id
                          WHERE f.empresa_id = ? AND f.tabla_estado_registro_id = 1 AND f.f_desde <= ? AND (f.f_hasta IS NULL OR f.f_hasta >= ?)
                            AND (? IS NULL OR f.banco_id = ?)
                          ORDER BY f.f_desde DESC, f.formato", 'issii', $empresa_id, $hoy, $hoy, $cta['banco_id'], $cta['banco_id']);
}

// ---------------------------------------------------------------------------------------------------------
// Conceptos
// ---------------------------------------------------------------------------------------------------------

const BCO_ROLES = ['ITEM', 'IVA', 'PERCEPCION_IVA', 'PERCEPCION_IIBB', 'PERCEPCION_GANANCIAS', 'RETENCION_IIBB'];
/** Impuesto de compras (gestion__impuestos_tipos, catálogo global) de cada rol de percepción. SIRCREB se registra como
 *  percepción IIBB recibida de la provincia del concepto. */
const BCO_ROL_IMPUESTO = ['PERCEPCION_IIBB' => 1, 'RETENCION_IIBB' => 1, 'PERCEPCION_IVA' => 2, 'PERCEPCION_GANANCIAS' => 3];
/** Roles que llevan provincia (la percepción IIBB es por jurisdicción). */
const BCO_ROLES_JURISDICCION = ['PERCEPCION_IIBB', 'RETENCION_IIBB'];

function bco_conceptos_listar(mysqli $c, int $empresa_id, int $pagina_id = 0, bool $solo_activos = false): array
{
    $col = bco_columna_estado($c);
    $rows = bco_filas($c,
        "SELECT k.concepto_id, k.codigo, k.concepto, k.naturaleza, k.rol, k.iva_incluido, k.producto_id, p.producto_codigo, p.producto_nombre,
                k.impuesto_tipo_id, it.impuesto_tipo, k.jurisdiccion_id, j.jurisdiccion_nombre, k.es_cobranza, k.orden, k.tabla_estado_registro_id, er.$col AS estado_registro, er.codigo_estandar,
                (SELECT COUNT(*) FROM gestion__bancos_reglas r WHERE r.concepto_id = k.concepto_id AND r.tabla_estado_registro_id = 1) AS reglas
         FROM gestion__bancos_conceptos k
         LEFT JOIN gestion__productos p ON p.producto_id = k.producto_id AND p.empresa_id = k.empresa_id
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = k.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = k.jurisdiccion_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = k.tabla_estado_registro_id
         WHERE k.empresa_id = ?" . ($solo_activos ? ' AND k.tabla_estado_registro_id = 1' : '') . "
         ORDER BY k.tabla_estado_registro_id, k.orden, k.concepto", 'i', $empresa_id);
    return $pagina_id ? bco_con_botones($c, $pagina_id, $rows) : $rows;
}

function bco_concepto_obtener(mysqli $c, int $empresa_id, int $id): ?array
{
    return bco_fila($c, "SELECT * FROM gestion__bancos_conceptos WHERE concepto_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id);
}

/** Productos de servicio (sin stock) de la empresa que se compran: candidatos de los conceptos con rol ITEM. */
function bco_productos_servicio(mysqli $c, int $empresa_id): array
{
    return bco_filas($c, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, i.porcentaje AS iva_porcentaje
                          FROM gestion__productos p
                          LEFT JOIN gestion__impuestos__iva_alicuotas i ON i.iva_alicuota_id = p.iva_alicuota_id
                          WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1 AND p.controla_stock = 0 AND p.aplica_compra = 1
                          ORDER BY (p.producto_codigo LIKE 'BCO\\_%') DESC, p.producto_nombre", 'i', $empresa_id);
}

/** Provincias en las que la empresa tiene configurada la percepción IIBB recibida (Impuestos de la empresa). */
function bco_jurisdicciones_iibb(mysqli $c, int $empresa_id): array
{
    return bco_filas($c, "SELECT DISTINCT j.jurisdiccion_id, j.jurisdiccion_nombre
                          FROM gestion__empresas_impuestos_config cfg
                          JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
                          WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.tabla_estado_registro_id = 1
                          ORDER BY j.jurisdiccion_nombre", 'ii', $empresa_id, BCO_ROL_IMPUESTO['PERCEPCION_IIBB']);
}

function bco_concepto_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    $id = intval($d['concepto_id'] ?? 0);
    $actual = $id > 0 ? bco_concepto_obtener($c, $empresa_id, $id) : null;
    if ($id > 0 && !$actual) throw new RuntimeException('Concepto no encontrado.');
    $codigo = strtoupper(trim((string)($d['codigo'] ?? '')));
    if (!preg_match('/^[A-Z0-9_]{1,30}$/', $codigo)) throw new RuntimeException('El código va en mayúsculas, números y guión bajo (hasta 30).');
    $nombre = trim((string)($d['concepto'] ?? ''));
    if ($nombre === '' || mb_strlen($nombre) > 120) throw new RuntimeException('Poné el nombre del concepto (hasta 120 caracteres).');
    $nat = (string)($d['naturaleza'] ?? '');
    if (!in_array($nat, ['GASTO_BANCARIO', 'MOVIMIENTO_PROPIO'], true)) throw new RuntimeException('Elegí si es un gasto del banco o un movimiento propio.');
    $rol = $nat === 'GASTO_BANCARIO' ? (string)($d['rol'] ?? '') : null;
    if ($nat === 'GASTO_BANCARIO' && !in_array($rol, BCO_ROLES, true)) throw new RuntimeException('Elegí cómo va el gasto en el comprobante.');
    $producto_id = $rol === 'ITEM' ? intval($d['producto_id'] ?? 0) : null;
    if ($rol === 'ITEM' && !in_array($producto_id, array_map('intval', array_column(bco_productos_servicio($c, $empresa_id), 'producto_id')), true)) {
        throw new RuntimeException('Elegí el producto (servicio sin stock) del gasto: de él sale la cuenta contable y el IVA.');
    }
    $impuesto = BCO_ROL_IMPUESTO[$rol] ?? null;
    $jurisdiccion = in_array($rol, BCO_ROLES_JURISDICCION, true) ? intval($d['jurisdiccion_id'] ?? 0) : null;
    if ($jurisdiccion !== null && !in_array($jurisdiccion, array_map('intval', array_column(bco_jurisdicciones_iibb($c, $empresa_id), 'jurisdiccion_id')), true)) {
        throw new RuntimeException('Elegí la provincia de la percepción de IIBB (tiene que estar configurada en Impuestos de la empresa).');
    }
    $iva_incluido = 0;   // sin uso desde 2026-10-30: el IVA es solo el que declara el extracto
    // Concepto de cobranzas (Generar recibo de Conciliación Bancaria): solo un movimiento propio, uno por empresa.
    // Si no viene en el pedido (alta rápida desde Conciliación) se conserva el valor actual.
    $es_cobranza = array_key_exists('es_cobranza', $d) ? (empty($d['es_cobranza']) ? 0 : 1) : intval($actual['es_cobranza'] ?? 0);
    if ($es_cobranza && $nat !== 'MOVIMIENTO_PROPIO') throw new RuntimeException('El concepto de cobranzas tiene que ser un movimiento propio.');
    $orden = max(0, min(9999, intval($d['orden'] ?? 1)));
    if (bco_fila($c, "SELECT 1 FROM gestion__bancos_conceptos WHERE empresa_id = ? AND codigo = ? AND concepto_id <> ?", 'isi', $empresa_id, $codigo, $id)) {
        throw new RuntimeException("Ya existe el concepto $codigo.");
    }
    if ($actual && $actual['naturaleza'] !== $nat && bco_fila($c, "SELECT 1 FROM gestion__bancos_extracto WHERE concepto_id = ? AND estado = 'CONCILIADA' LIMIT 1", 'i', $id)) {
        throw new RuntimeException('El concepto tiene líneas conciliadas: no se cambia entre gasto y movimiento propio.');
    }

    mysqli_begin_transaction($c);
    try {
        if ($actual) {
            bco_exec($c, "UPDATE gestion__bancos_conceptos SET codigo = ?, concepto = ?, naturaleza = ?, rol = ?, iva_incluido = ?, producto_id = ?,
                                 impuesto_tipo_id = ?, jurisdiccion_id = ?, es_cobranza = ?, orden = ?, usuario_modificacion_id = ? WHERE concepto_id = ? AND empresa_id = ?",
                'ssssiiiiiiiii', $codigo, $nombre, $nat, $rol, $iva_incluido, $producto_id, $impuesto, $jurisdiccion, $es_cobranza, $orden, $usuario_id, $id, $empresa_id);
        } else {
            bco_exec($c, "INSERT INTO gestion__bancos_conceptos (empresa_id, codigo, concepto, naturaleza, rol, iva_incluido, producto_id, impuesto_tipo_id,
                                 jurisdiccion_id, es_cobranza, orden, tabla_estado_registro_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                'issssiiiiiii', $empresa_id, $codigo, $nombre, $nat, $rol, $iva_incluido, $producto_id, $impuesto, $jurisdiccion, $es_cobranza, $orden, $usuario_id);
            $id = intval(mysqli_insert_id($c));
        }
        if ($es_cobranza) {
            bco_exec($c, "UPDATE gestion__bancos_conceptos SET es_cobranza = 0, usuario_modificacion_id = ? WHERE empresa_id = ? AND concepto_id <> ? AND es_cobranza = 1",
                'iii', $usuario_id, $empresa_id, $id);
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return $id;
}

function bco_concepto_accion(mysqli $c, int $empresa_id, int $pagina_id, int $id, string $accion_js): int
{
    $k = bco_concepto_obtener($c, $empresa_id, $id) ?? throw new RuntimeException('Concepto no encontrado.');
    $destino = bco_destino_accion($c, $pagina_id, $accion_js, intval($k['tabla_estado_registro_id']));
    if ($destino === 2) {
        $n = intval(bco_fila($c, "SELECT COUNT(*) n FROM gestion__bancos_reglas WHERE concepto_id = ? AND tabla_estado_registro_id = 1", 'i', $id)['n']);
        if ($n > 0) throw new RuntimeException("Lo usan $n reglas activas: inactivalas o cambiales el concepto antes.");
    }
    bco_exec($c, "UPDATE gestion__bancos_conceptos SET tabla_estado_registro_id = ? WHERE concepto_id = ? AND empresa_id = ?", 'iii', $destino, $id, $empresa_id);
    return $destino;
}

// ---------------------------------------------------------------------------------------------------------
// Reglas
// ---------------------------------------------------------------------------------------------------------

function bco_reglas_listar(mysqli $c, int $empresa_id, int $pagina_id = 0, int $banco_id = 0): array
{
    $col = bco_columna_estado($c);
    $rows = bco_filas($c,
        "SELECT r.regla_id, r.banco_id, b.banco, r.concepto_id, k.codigo AS concepto_codigo, k.concepto, k.naturaleza, r.prioridad,
                r.tipo_coincidencia, r.patron, r.aplica_a, r.origen, r.tabla_estado_registro_id, er.$col AS estado_registro, er.codigo_estandar,
                (SELECT COUNT(*) FROM gestion__bancos_extracto x WHERE x.regla_id = r.regla_id AND x.empresa_id = r.empresa_id) AS lineas
         FROM gestion__bancos_reglas r
         JOIN conf__bancos b ON b.banco_id = r.banco_id
         JOIN gestion__bancos_conceptos k ON k.concepto_id = r.concepto_id AND k.empresa_id = r.empresa_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = r.tabla_estado_registro_id
         WHERE r.empresa_id = ? AND (? = 0 OR r.banco_id = ?)
         ORDER BY b.banco, r.tabla_estado_registro_id, r.prioridad, r.regla_id", 'iii', $empresa_id, $banco_id, $banco_id);
    return $pagina_id ? bco_con_botones($c, $pagina_id, $rows) : $rows;
}

function bco_regla_obtener(mysqli $c, int $empresa_id, int $id): ?array
{
    return bco_fila($c, "SELECT * FROM gestion__bancos_reglas WHERE regla_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id);
}

function bco_regla_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    $id = intval($d['regla_id'] ?? 0);
    if ($id > 0 && !bco_regla_obtener($c, $empresa_id, $id)) throw new RuntimeException('Regla no encontrada.');
    $banco_id = intval($d['banco_id'] ?? 0);
    if (!bco_fila($c, "SELECT 1 FROM conf__bancos WHERE banco_id = ? AND tabla_estado_registro_id = 1", 'i', $banco_id)) throw new RuntimeException('Elegí el banco.');
    $concepto_id = intval($d['concepto_id'] ?? 0);
    if (!bco_fila($c, "SELECT 1 FROM gestion__bancos_conceptos WHERE concepto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $concepto_id, $empresa_id)) {
        throw new RuntimeException('Elegí un concepto activo.');
    }
    $tipo = (string)($d['tipo_coincidencia'] ?? 'CONTIENE');
    $patron = trim((string)($d['patron'] ?? ''));
    $aplica = (string)($d['aplica_a'] ?? 'AMBOS');
    if ($err = bco_regla_validar($tipo, $patron, $aplica)) throw new RuntimeException($err);
    $prioridad = max(0, min(9999, intval($d['prioridad'] ?? 100)));

    if ($id > 0) {
        bco_exec($c, "UPDATE gestion__bancos_reglas SET banco_id = ?, concepto_id = ?, prioridad = ?, tipo_coincidencia = ?, patron = ?, aplica_a = ?,
                             usuario_modificacion_id = ? WHERE regla_id = ? AND empresa_id = ?",
            'iiisssiii', $banco_id, $concepto_id, $prioridad, $tipo, $patron, $aplica, $usuario_id, $id, $empresa_id);
    } else {
        bco_exec($c, "INSERT INTO gestion__bancos_reglas (empresa_id, banco_id, concepto_id, prioridad, tipo_coincidencia, patron, aplica_a,
                             tabla_estado_registro_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)",
            'iiiisssi', $empresa_id, $banco_id, $concepto_id, $prioridad, $tipo, $patron, $aplica, $usuario_id);
        $id = intval(mysqli_insert_id($c));
    }
    return $id;
}

function bco_regla_accion(mysqli $c, int $empresa_id, int $pagina_id, int $id, string $accion_js): int
{
    $r = bco_regla_obtener($c, $empresa_id, $id) ?? throw new RuntimeException('Regla no encontrada.');
    $destino = bco_destino_accion($c, $pagina_id, $accion_js, intval($r['tabla_estado_registro_id']));
    bco_exec($c, "UPDATE gestion__bancos_reglas SET tabla_estado_registro_id = ? WHERE regla_id = ? AND empresa_id = ?", 'iii', $destino, $id, $empresa_id);
    return $destino;
}

/**
 * Regla aprendida de una asignación manual (script 20261029_conciliacion_bancaria_04): "descripción igual a" -> concepto,
 * por banco y sentido de la línea, prioridad 1. Si ya existe (activa o no) se actualiza el concepto y se reactiva.
 * Devuelve el regla_id. Va dentro de la transacción del llamador.
 */
function bco_regla_aprender(mysqli $c, int $empresa_id, int $usuario_id, int $banco_id, string $descripcion, float $importe, int $concepto_id): int
{
    $patron = mb_substr(bco_texto_normalizado($descripcion), 0, 300);
    $aplica = $importe < 0 ? 'DEBITO' : 'CREDITO';
    $r = bco_fila($c, "SELECT regla_id FROM gestion__bancos_reglas WHERE empresa_id = ? AND banco_id = ? AND tipo_coincidencia = 'IGUAL'
                       AND aplica_a = ? AND patron = ? ORDER BY tabla_estado_registro_id, regla_id LIMIT 1 FOR UPDATE",
                  'iiss', $empresa_id, $banco_id, $aplica, $patron);
    if ($r) {
        $id = intval($r['regla_id']);
        bco_exec($c, "UPDATE gestion__bancos_reglas SET concepto_id = ?, tabla_estado_registro_id = 1, usuario_modificacion_id = ?
                      WHERE regla_id = ? AND empresa_id = ?", 'iiii', $concepto_id, $usuario_id, $id, $empresa_id);
        return $id;
    }
    bco_exec($c, "INSERT INTO gestion__bancos_reglas (empresa_id, banco_id, concepto_id, prioridad, tipo_coincidencia, patron, aplica_a, origen,
                         tabla_estado_registro_id, usuario_id) VALUES (?, ?, ?, 1, 'IGUAL', ?, ?, 'APRENDIDA', 1, ?)",
        'iiissi', $empresa_id, $banco_id, $concepto_id, $patron, $aplica, $usuario_id);
    return intval(mysqli_insert_id($c));
}

/** Reglas activas del banco, ordenadas para clasificar. */
function bco_reglas_activas(mysqli $c, int $empresa_id, ?int $banco_id): array
{
    if (!$banco_id) return [];
    return bco_reglas_ordenar(bco_filas($c,
        "SELECT r.regla_id, r.concepto_id, r.prioridad, r.tipo_coincidencia, r.patron, r.aplica_a
         FROM gestion__bancos_reglas r
         JOIN gestion__bancos_conceptos k ON k.concepto_id = r.concepto_id AND k.empresa_id = r.empresa_id AND k.tabla_estado_registro_id = 1
         WHERE r.empresa_id = ? AND r.banco_id = ? AND r.tabla_estado_registro_id = 1", 'ii', $empresa_id, $banco_id));
}

/**
 * Vuelve a aplicar las reglas a las líneas no conciliadas cuyo concepto salió de una regla o que no tienen concepto
 * (las asignadas a mano no se tocan). Devuelve cuántas cambiaron.
 */
function bco_reglas_aplicar(mysqli $c, int $empresa_id, int $usuario_id, int $banco_id = 0): array
{
    $lineas = bco_filas($c,
        "SELECT x.extracto_id, x.descripcion, x.importe, x.concepto_id, x.regla_id, x.concepto_origen, x.estado, t.banco_id
         FROM gestion__bancos_extracto x
         JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = x.tesoreria_cuenta_id AND t.empresa_id = x.empresa_id
         WHERE x.empresa_id = ? AND x.estado IN ('PENDIENTE', 'CLASIFICADA') AND (x.concepto_origen IS NULL OR x.concepto_origen = 'REGLA')
           AND (? = 0 OR t.banco_id = ?)", 'iii', $empresa_id, $banco_id, $banco_id);
    $reglas = [];
    $cambios = 0;
    $pendientes = 0;
    mysqli_begin_transaction($c);
    try {
        foreach ($lineas as $l) {
            $b = intval($l['banco_id']);
            $reglas[$b] = $reglas[$b] ?? bco_reglas_activas($c, $empresa_id, $b);
            $r = bco_regla_para($l, $reglas[$b]);
            $concepto = $r ? intval($r['concepto_id']) : null;
            $regla = $r ? intval($r['regla_id']) : null;
            $estado = $r ? 'CLASIFICADA' : 'PENDIENTE';
            if (!$r) $pendientes++;
            if ($concepto === ($l['concepto_id'] === null ? null : intval($l['concepto_id'])) && $regla === ($l['regla_id'] === null ? null : intval($l['regla_id']))) continue;
            $origen = $r ? 'REGLA' : null;
            $xid = intval($l['extracto_id']);
            bco_exec($c, "UPDATE gestion__bancos_extracto SET concepto_id = ?, regla_id = ?, concepto_origen = ?, estado = ?, usuario_modificacion_id = ?
                          WHERE extracto_id = ? AND empresa_id = ? AND estado IN ('PENDIENTE', 'CLASIFICADA')
                            AND (concepto_origen IS NULL OR concepto_origen = 'REGLA')",
                'iissiii', $concepto, $regla, $origen, $estado, $usuario_id, $xid, $empresa_id);
            $cambios++;
        }
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    return ['revisadas' => count($lineas), 'cambiadas' => $cambios, 'sin_concepto' => $pendientes];
}
