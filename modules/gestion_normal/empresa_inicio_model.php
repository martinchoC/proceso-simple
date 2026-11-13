<?php
/**
 * Modelo: Asistente de Inicio de Empresa, Diagnóstico y Copia Autorizada
 * Módulo: Gestión (mod 2) / Gestión Normal (mod 5)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}

require_once __DIR__ . '/../../core/perfiles_predefinidos.php';

/**
 * Valor numérico para armar el SQL de copia, o NULL (las filas origen pueden traer NULL).
 */
function inicioSqlNum($v): string
{
    return ($v === null || $v === '') ? 'NULL' : (string) (0 + $v);
}

/** Ejecuta un SELECT con un único parámetro entero y devuelve todas las filas. */
function inicioLeerFilas(mysqli $conexion, string $sql, int $param): array
{
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $param);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $filas;
}

/** Clave de equivalencia entre empresas: el código si lo tiene, si no el nombre. */
function inicioClaveCatalogo(array $fila, string $columnaNombre): string
{
    $codigo = strtoupper(trim((string) ($fila['codigo'] ?? '')));
    return $codigo !== '' ? 'C:' . $codigo : 'N:' . mb_strtoupper(trim((string) ($fila[$columnaNombre] ?? '')));
}

/**
 * Grupos y subgrupos de comprobante son por empresa: los de la origen se buscan en la
 * destino por código (o nombre) y se crean los que falten. El tipo de asiento del subgrupo
 * se remapea por código; si la destino no lo tiene, el subgrupo queda sin tipo de asiento.
 * Devuelve [mapa subgrupo origen => destino, mapa grupo origen => destino, cantidad creada].
 */
function inicioMapearSubgruposComprobante(mysqli $conexion, int $origen, int $destino): array
{
    $creados = 0;

    $asientosDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT cont_tipo_asiento_id, codigo FROM gestion__cont_tipos_asientos WHERE empresa_id = ?", $destino) as $r) {
        $asientosDest[strtoupper((string) $r['codigo'])] = (int) $r['cont_tipo_asiento_id'];
    }
    $asientosOrig = [];
    foreach (inicioLeerFilas($conexion, "SELECT cont_tipo_asiento_id, codigo FROM gestion__cont_tipos_asientos WHERE empresa_id = ?", $origen) as $r) {
        $asientosOrig[(int) $r['cont_tipo_asiento_id']] = strtoupper((string) $r['codigo']);
    }

    // Grupos
    $gruposDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__comprobantes_grupos WHERE empresa_id = ?", $destino) as $r) {
        $gruposDest[inicioClaveCatalogo($r, 'comprobante_grupo')] = (int) $r['comprobante_grupo_id'];
    }
    $mapaGrupos = [];
    $insG = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_grupos
        (empresa_id, comprobante_grupo, codigo, comentario, orden, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, 1)");
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__comprobantes_grupos WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $g) {
        $k = inicioClaveCatalogo($g, 'comprobante_grupo');
        if (!isset($gruposDest[$k])) {
            $orden = (int) ($g['orden'] ?? 1);
            mysqli_stmt_bind_param($insG, "isssi", $destino, $g['comprobante_grupo'], $g['codigo'], $g['comentario'], $orden);
            mysqli_stmt_execute($insG);
            $gruposDest[$k] = (int) mysqli_insert_id($conexion);
            $creados++;
        }
        $mapaGrupos[(int) $g['comprobante_grupo_id']] = $gruposDest[$k];
    }
    mysqli_stmt_close($insG);

    // Subgrupos
    $subDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__comprobantes_subgrupos WHERE empresa_id = ?", $destino) as $r) {
        $subDest[inicioClaveCatalogo($r, 'comprobante_subgrupo')] = (int) $r['comprobante_subgrupo_id'];
    }
    $mapaSubgrupos = [];
    $insS = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_subgrupos
        (empresa_id, comprobante_grupo_id, tabla_id, comprobante_subgrupo, codigo, comentario, orden,
         tabla_estado_registro_id, tipo_asiento_id, origen_asiento)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__comprobantes_subgrupos WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $s) {
        $grupoDest = $mapaGrupos[(int) $s['comprobante_grupo_id']] ?? 0;
        if ($grupoDest <= 0) {
            continue;
        }
        $k = inicioClaveCatalogo($s, 'comprobante_subgrupo');
        if (!isset($subDest[$k])) {
            $tablaId = (int) $s['tabla_id'];
            $orden = (int) ($s['orden'] ?? 1);
            $codAsiento = $asientosOrig[(int) ($s['tipo_asiento_id'] ?? 0)] ?? null;
            $tipoAsiento = $codAsiento !== null ? ($asientosDest[$codAsiento] ?? null) : null;
            $origenAsiento = (string) ($s['origen_asiento'] ?? 'NINGUNO');
            mysqli_stmt_bind_param($insS, "iiisssiis", $destino, $grupoDest, $tablaId, $s['comprobante_subgrupo'],
                $s['codigo'], $s['comentario'], $orden, $tipoAsiento, $origenAsiento);
            mysqli_stmt_execute($insS);
            $subDest[$k] = (int) mysqli_insert_id($conexion);
            $creados++;
        }
        $mapaSubgrupos[(int) $s['comprobante_subgrupo_id']] = $subDest[$k];
    }
    mysqli_stmt_close($insS);

    return [$mapaSubgrupos, $mapaGrupos, $creados];
}

/**
 * Tipo de sucursal de la empresa (son por empresa): el de ese nombre, o sin nombre el
 * primero activo; si no hay, lo crea.
 */
function inicioSucursalTipoDestino(mysqli $conexion, int $empresa_id, ?string $nombre): int
{
    if ($nombre !== null && trim($nombre) !== '') {
        $stmt = mysqli_prepare($conexion, "SELECT sucursal_tipo_id FROM gestion__sucursales_tipos WHERE empresa_id = ? AND sucursal_tipo = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $empresa_id, $nombre);
    } else {
        $stmt = mysqli_prepare($conexion, "SELECT sucursal_tipo_id FROM gestion__sucursales_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_tipo_id LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    }
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($fila) {
        return (int) $fila['sucursal_tipo_id'];
    }

    $nombreNuevo = ($nombre !== null && trim($nombre) !== '') ? $nombre : 'Casa Central';
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__sucursales_tipos (empresa_id, sucursal_tipo, tabla_estado_registro_id) VALUES (?, ?, 1)");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $nombreNuevo);
    mysqli_stmt_execute($stmt);
    $id = (int) mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $id;
}

/** Moneda equivalente (mismo código) en la empresa destino, o null. */
function inicioMonedaDestino(mysqli $conexion, $moneda_origen_id, int $destino): ?int
{
    if (empty($moneda_origen_id)) {
        return null;
    }
    $origenId = (int) $moneda_origen_id;
    $stmt = mysqli_prepare($conexion, "SELECT d.moneda_id
        FROM gestion__monedas o
        JOIN gestion__monedas d ON d.codigo = o.codigo AND d.empresa_id = ?
        WHERE o.moneda_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $destino, $origenId);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ? (int) $fila['moneda_id'] : null;
}

/** Suma los COUNT(*) de un UNION ALL cuyas $partes llevan, cada una, la empresa como único parámetro. */
function inicioContarCatalogos(mysqli $conexion, string $sql, int $empresa_id, int $partes): int
{
    $stmt = mysqli_prepare($conexion, $sql);
    $valores = array_fill(0, $partes, $empresa_id);
    mysqli_stmt_bind_param($stmt, str_repeat('i', $partes), ...$valores);
    mysqli_stmt_execute($stmt);
    $total = 0;
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) {
        $total += (int) $r['c'];
    }
    mysqli_stmt_close($stmt);
    return $total;
}

/** Ejecuta un INSERT preparado y devuelve el id creado (lanza Exception si falla). */
function inicioInsertar(mysqli $conexion, string $sql, string $tipos, array $valores): int
{
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception($err);
    }
    $id = (int) mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    return $id;
}

/**
 * Datos básicos de la empresa origen: monedas, tipos de asiento, alícuotas de IVA, tipos y categorías de producto
 * y categorías de gasto. Solo agrega lo que falta en la destino (por código, o por nombre donde no hay código);
 * las cuentas contables se remapean con $mapaCuentas (cuentas origen => destino, por código del plan) y quedan
 * vacías si la destino no tiene esa cuenta. La moneda base solo se copia como base si la destino no tiene una.
 * Devuelve [texto del detalle, registros creados].
 */
function inicioCopiarDatosBasicos(mysqli $conexion, int $origen, int $destino, array $mapaCuentas): array
{
    $cuenta = function ($id) use ($mapaCuentas) {
        return (!empty($id) && isset($mapaCuentas[(int) $id])) ? $mapaCuentas[(int) $id] : null;
    };
    $cant = ['monedas' => 0, 'asientos' => 0, 'iva' => 0, 'tipos' => 0, 'categorias' => 0, 'gastos' => 0];

    // Monedas
    $existentes = [];
    $tieneBase = false;
    foreach (inicioLeerFilas($conexion, "SELECT codigo, es_moneda_base, tabla_estado_registro_id FROM gestion__monedas WHERE empresa_id = ?", $destino) as $r) {
        $existentes[strtoupper($r['codigo'])] = true;
        $tieneBase = $tieneBase || ((int) $r['es_moneda_base'] === 1 && (int) $r['tabla_estado_registro_id'] === 1);
    }
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__monedas WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY es_moneda_base DESC, orden", $origen) as $m) {
        if (isset($existentes[strtoupper($m['codigo'])])) {
            continue;
        }
        $esBase = ((int) $m['es_moneda_base'] === 1 && !$tieneBase) ? 1 : 0;
        $cotizacion = $esBase ? '1' : (string) $m['cotizacion_actual'];
        $decimales = (int) $m['cantidad_decimales'];
        $editar = $esBase ? 0 : (int) $m['permite_editar_cotizacion'];
        $orden = (int) $m['orden'];
        inicioInsertar($conexion, "INSERT INTO gestion__monedas
            (empresa_id, codigo, moneda, simbolo, es_moneda_base, cantidad_decimales, cotizacion_actual,
             permite_editar_cotizacion, orden, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", "isssiisii",
            [$destino, $m['codigo'], $m['moneda'], $m['simbolo'], $esBase, $decimales, $cotizacion, $editar, $orden]);
        $existentes[strtoupper($m['codigo'])] = true;
        $tieneBase = $tieneBase || $esBase === 1;
        $cant['monedas']++;
    }

    // Tipos de asiento (AUT lo usan todos los asientos automáticos)
    $existentes = [];
    foreach (inicioLeerFilas($conexion, "SELECT codigo FROM gestion__cont_tipos_asientos WHERE empresa_id = ?", $destino) as $r) {
        $existentes[strtoupper($r['codigo'])] = true;
    }
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__cont_tipos_asientos WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $t) {
        if (isset($existentes[strtoupper($t['codigo'])])) {
            continue;
        }
        inicioInsertar($conexion, "INSERT INTO gestion__cont_tipos_asientos
            (empresa_id, codigo, cont_tipo_asiento, descripcion, origen, modulo_origen, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, 1)", "isssss",
            [$destino, $t['codigo'], $t['cont_tipo_asiento'], $t['descripcion'], $t['origen'] ?? 'manual', $t['modulo_origen']]);
        $existentes[strtoupper($t['codigo'])] = true;
        $cant['asientos']++;
    }

    // Alícuotas de IVA
    $existentes = [];
    foreach (inicioLeerFilas($conexion, "SELECT codigo FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ?", $destino) as $r) {
        $existentes[strtoupper($r['codigo'])] = true;
    }
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $a) {
        if (isset($existentes[strtoupper($a['codigo'])])) {
            continue;
        }
        $ctaCompra = (int) ($cuenta($a['cont_cuenta_compra_id']) ?? 0);
        $ctaVenta = (int) ($cuenta($a['cont_cuenta_venta_id']) ?? 0);
        $gravado = (int) $a['es_gravado'];
        $exento = (int) $a['es_exento'];
        $noGravado = (int) $a['es_no_gravado'];
        inicioInsertar($conexion, "INSERT INTO gestion__impuestos__iva_alicuotas
            (empresa_id, codigo, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado,
             cont_cuenta_compra_id, cont_cuenta_venta_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", "isssiiiii",
            [$destino, $a['codigo'], $a['iva_alicuota'], (string) $a['porcentaje'], $gravado, $exento, $noGravado, $ctaCompra, $ctaVenta]);
        $existentes[strtoupper($a['codigo'])] = true;
        $cant['iva']++;
    }

    // Tipos de producto (por código, o por nombre si no tiene)
    $claveTipo = function (array $r): string {
        $codigo = strtoupper(trim((string) ($r['producto_tipo_codigo'] ?? '')));
        return $codigo !== '' ? 'C:' . $codigo : 'N:' . mb_strtoupper(trim((string) ($r['producto_tipo'] ?? '')));
    };
    $tiposDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_tipos WHERE empresa_id = ?", $destino) as $r) {
        $tiposDest[$claveTipo($r)] = (int) $r['producto_tipo_id'];
    }
    $mapaTipos = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $t) {
        $k = $claveTipo($t);
        if (!isset($tiposDest[$k])) {
            $stock = (int) $t['maneja_stock'];
            $compuesto = (int) $t['es_compuesto'];
            $factura = (int) $t['afecta_facturacion'];
            $tiposDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__productos_tipos
                (empresa_id, producto_tipo, producto_tipo_codigo, maneja_stock, es_compuesto, afecta_facturacion,
                 tabla_estado_registro_id, cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)", "issiiiiii",
                [$destino, $t['producto_tipo'], $t['producto_tipo_codigo'], $stock, $compuesto, $factura,
                 $cuenta($t['cont_cuenta_compra_default_id']), $cuenta($t['cont_cuenta_venta_default_id']), $cuenta($t['cont_cuenta_cmv_default_id'])]);
            $cant['tipos']++;
        }
        $mapaTipos[(int) $t['producto_tipo_id']] = $tiposDest[$k];
    }

    // Categorías de producto (árbol: primero las que tienen el padre ya resuelto; clave = nombre + padre en la destino)
    $catDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id
                                         FROM gestion__productos_categorias WHERE empresa_id = ?", $destino) as $r) {
        $catDest[mb_strtoupper(trim($r['producto_categoria_nombre'])) . '|' . (int) $r['producto_categoria_padre_id']] = (int) $r['producto_categoria_id'];
    }
    $pendientes = inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_categorias WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                              ORDER BY producto_categoria_id", $origen);
    $mapaCat = [];
    do {
        $avance = false;
        foreach ($pendientes as $i => $cat) {
            $padreOrigen = (int) $cat['producto_categoria_padre_id'];
            if ($padreOrigen > 0 && !isset($mapaCat[$padreOrigen])) {
                continue;
            }
            $padreDest = $padreOrigen > 0 ? $mapaCat[$padreOrigen] : null;
            $k = mb_strtoupper(trim($cat['producto_categoria_nombre'])) . '|' . (int) $padreDest;
            if (!isset($catDest[$k])) {
                $tipoDest = $mapaTipos[(int) $cat['producto_tipo_id']] ?? null;
                $catDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__productos_categorias
                    (empresa_id, producto_categoria_nombre, producto_categoria_padre_id, tabla_estado_registro_id,
                     producto_tipo_id, cont_cuenta_ingreso_id, cont_cuenta_cmv_id)
                    VALUES (?, ?, ?, 1, ?, ?, ?)", "isiiii",
                    [$destino, $cat['producto_categoria_nombre'], $padreDest, $tipoDest,
                     $cuenta($cat['cont_cuenta_ingreso_id']), $cuenta($cat['cont_cuenta_cmv_id'])]);
                $cant['categorias']++;
            }
            $mapaCat[(int) $cat['producto_categoria_id']] = $catDest[$k];
            unset($pendientes[$i]);
            $avance = true;
        }
    } while ($avance && $pendientes);
    // Las que quedan cuelgan de un padre inactivo: no se copian

    // Categorías de gasto (rendiciones)
    $existentes = [];
    foreach (inicioLeerFilas($conexion, "SELECT codigo FROM gestion__categorias_gasto WHERE empresa_id = ?", $destino) as $r) {
        $existentes[strtoupper($r['codigo'])] = true;
    }
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__categorias_gasto WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $g) {
        if (isset($existentes[strtoupper($g['codigo'])])) {
            continue;
        }
        $reqComp = (int) $g['requiere_comprobante'];
        $reqJust = (int) $g['requiere_justificacion'];
        $orden = (int) $g['orden'];
        inicioInsertar($conexion, "INSERT INTO gestion__categorias_gasto
            (empresa_id, codigo, categoria_gasto, cont_cuenta_id, requiere_comprobante, requiere_justificacion, orden, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)", "issiiii",
            [$destino, $g['codigo'], $g['categoria_gasto'], $cuenta($g['cont_cuenta_id']), $reqComp, $reqJust, $orden]);
        $existentes[strtoupper($g['codigo'])] = true;
        $cant['gastos']++;
    }

    $detalle = "{$cant['monedas']} monedas, {$cant['asientos']} tipos de asiento, {$cant['iva']} alícuotas de IVA, "
        . "{$cant['tipos']} tipos y {$cant['categorias']} categorías de producto, {$cant['gastos']} categorías de gasto";
    return [$detalle, array_sum($cant)];
}

/**
 * Tesorería de la empresa origen: cajas, cuentas de valores y de cupones (sin saldo: lo arma el libro) y medios de
 * cobro y pago. Las cuentas bancarias no se copian (banco, número y CBU son de cada empresa): los medios que las
 * tenían fijas quedan solo con el tipo de cuenta. Sucursal por nombre, moneda por código y cuentas contables por
 * $mapaCuentas; una cuenta cuya moneda no existe en la destino se omite. Devuelve [texto del detalle, registros creados].
 */
function inicioCopiarTesoreria(mysqli $conexion, int $origen, int $destino, array $mapaCuentas, int $usuario_id): array
{
    $cuenta = function ($id) use ($mapaCuentas) {
        return (!empty($id) && isset($mapaCuentas[(int) $id])) ? $mapaCuentas[(int) $id] : null;
    };
    $claveCuenta = function (array $r): string {
        $codigo = strtoupper(trim((string) ($r['codigo'] ?? '')));
        return $codigo !== '' ? 'C:' . $codigo : 'N:' . $r['cuenta_tipo'] . '|' . mb_strtoupper(trim((string) $r['tesoreria_cuenta']));
    };

    $sucursalesDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ?", $destino) as $r) {
        $sucursalesDest[mb_strtoupper(trim($r['sucursal_nombre']))] = (int) $r['sucursal_id'];
    }
    $cuentasDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__tesoreria_cuentas WHERE empresa_id = ?", $destino) as $r) {
        $cuentasDest[$claveCuenta($r)] = (int) $r['tesoreria_cuenta_id'];
    }

    $creadas = 0;
    $omitidas = 0;
    $mapaTes = [];
    foreach (inicioLeerFilas($conexion, "SELECT tc.*, s.sucursal_nombre
                                         FROM gestion__tesoreria_cuentas tc
                                         LEFT JOIN gestion__sucursales s ON s.sucursal_id = tc.sucursal_id
                                         WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1
                                         ORDER BY tc.orden, tc.tesoreria_cuenta_id", $origen) as $tc) {
        $k = $claveCuenta($tc);
        if (!isset($cuentasDest[$k])) {
            if ($tc['cuenta_tipo'] === 'BANCO') {
                continue;
            }
            $monedaDest = inicioMonedaDestino($conexion, $tc['moneda_id'], $destino);
            if ($monedaDest === null) {
                $omitidas++;
                continue;
            }
            $sucursalDest = !empty($tc['sucursal_nombre']) ? ($sucursalesDest[mb_strtoupper(trim($tc['sucursal_nombre']))] ?? null) : null;
            $negativo = (int) $tc['permite_saldo_negativo'];
            $orden = (int) $tc['orden'];
            $cuentasDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__tesoreria_cuentas
                (empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, moneda_id, cont_cuenta_id,
                 permite_saldo_negativo, orden, tabla_estado_registro_id, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)", "iisssiiiii",
                [$destino, $sucursalDest, $tc['cuenta_tipo'], $tc['codigo'], $tc['tesoreria_cuenta'], $monedaDest,
                 $cuenta($tc['cont_cuenta_id']), $negativo, $orden, $usuario_id]);
            $creadas++;
        }
        $mapaTes[(int) $tc['tesoreria_cuenta_id']] = $cuentasDest[$k];
    }

    // Medios de cobro y pago
    $existentes = [];
    foreach (inicioLeerFilas($conexion, "SELECT codigo FROM gestion__medios_pago WHERE empresa_id = ?", $destino) as $r) {
        $existentes[strtoupper($r['codigo'])] = true;
    }
    $medios = 0;
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__medios_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, medio_pago_id", $origen) as $mp) {
        if (isset($existentes[strtoupper($mp['codigo'])])) {
            continue;
        }
        $tesDest = !empty($mp['tesoreria_cuenta_id']) ? ($mapaTes[(int) $mp['tesoreria_cuenta_id']] ?? null) : null;
        $impuesto = !empty($mp['impuesto_tipo_id']) ? (int) $mp['impuesto_tipo_id'] : null;
        $detalle = (int) $mp['requiere_detalle'];
        $cobro = (int) $mp['aplica_cobro'];
        $pago = (int) $mp['aplica_pago'];
        $orden = (int) $mp['orden'];
        inicioInsertar($conexion, "INSERT INTO gestion__medios_pago
            (empresa_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo, tesoreria_cuenta_id, cont_cuenta_id,
             impuesto_tipo_id, requiere_detalle, aplica_cobro, aplica_pago, orden, tabla_estado_registro_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)", "issssiiiiiiii",
            [$destino, $mp['codigo'], $mp['medio_pago'], $mp['medio_pago_tipo'], $mp['tesoreria_cuenta_tipo'], $tesDest,
             $cuenta($mp['cont_cuenta_id']), $impuesto, $detalle, $cobro, $pago, $orden, $usuario_id]);
        $existentes[strtoupper($mp['codigo'])] = true;
        $medios++;
    }

    $detalle = "$creadas cuentas de tesorería (sin las bancarias), $medios medios de cobro y pago"
        . ($omitidas > 0 ? ", $omitidas cuentas omitidas por no tener su moneda en esta empresa" : '');
    return [$detalle, $creadas + $medios];
}

/**
 * Configuración de impuestos de la empresa origen (gestion__empresas_impuestos_config) y sus tablas hijas:
 * - gestion__empresas_impuestos_config_operaciones
 * - gestion__empresas_impuestos_config_situaciones
 * - gestion__empresas_impuestos_config_subgrupos
 *
 * Mapea:
 * - cont_cuenta_id mediante $mapaCuentas (por código de cuenta contable)
 * - producto_tipo_id en operaciones mediante correspondencia de código/nombre entre empresas
 * - comprobante_subgrupo_id en subgrupos mediante correspondencia de subgrupos entre empresas
 *
 * Devuelve [texto del detalle, cantidad de registros creados].
 */
function inicioCopiarImpuestosConfig(mysqli $conexion, int $origen, int $destino, array $mapaCuentas): array
{
    $cuenta = function ($id) use ($mapaCuentas) {
        return (!empty($id) && isset($mapaCuentas[(int) $id])) ? $mapaCuentas[(int) $id] : null;
    };

    // 1. Mapear tipos de producto de origen a destino (para operaciones)
    $tiposDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT producto_tipo_id, producto_tipo_codigo, producto_tipo FROM gestion__productos_tipos WHERE empresa_id = ?", $destino) as $r) {
        $c = strtoupper(trim((string) ($r['producto_tipo_codigo'] ?? '')));
        $k = $c !== '' ? 'C:' . $c : 'N:' . mb_strtoupper(trim((string) ($r['producto_tipo'] ?? '')));
        $tiposDest[$k] = (int) $r['producto_tipo_id'];
    }
    $mapaTipos = [];
    foreach (inicioLeerFilas($conexion, "SELECT producto_tipo_id, producto_tipo_codigo, producto_tipo FROM gestion__productos_tipos WHERE empresa_id = ?", $origen) as $r) {
        $c = strtoupper(trim((string) ($r['producto_tipo_codigo'] ?? '')));
        $k = $c !== '' ? 'C:' . $c : 'N:' . mb_strtoupper(trim((string) ($r['producto_tipo'] ?? '')));
        if (isset($tiposDest[$k])) {
            $mapaTipos[(int) $r['producto_tipo_id']] = $tiposDest[$k];
        }
    }

    // 2. Mapear subgrupos de comprobantes de origen a destino (para subgrupos)
    $subDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT comprobante_subgrupo_id, codigo, comprobante_subgrupo FROM gestion__comprobantes_subgrupos WHERE empresa_id = ?", $destino) as $r) {
        $c = strtoupper(trim((string) ($r['codigo'] ?? '')));
        $k = $c !== '' ? 'C:' . $c : 'N:' . mb_strtoupper(trim((string) ($r['comprobante_subgrupo'] ?? '')));
        $subDest[$k] = (int) $r['comprobante_subgrupo_id'];
    }
    $mapaSub = [];
    foreach (inicioLeerFilas($conexion, "SELECT comprobante_subgrupo_id, codigo, comprobante_subgrupo FROM gestion__comprobantes_subgrupos WHERE empresa_id = ?", $origen) as $r) {
        $c = strtoupper(trim((string) ($r['codigo'] ?? '')));
        $k = $c !== '' ? 'C:' . $c : 'N:' . mb_strtoupper(trim((string) ($r['comprobante_subgrupo'] ?? '')));
        if (isset($subDest[$k])) {
            $mapaSub[(int) $r['comprobante_subgrupo_id']] = $subDest[$k];
        }
    }

    // 3. Obtener configuraciones existentes en destino para no duplicar por (impuesto_tipo_id, jurisdiccion_id)
    $existentesDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT empresa_impuesto_config_id, impuesto_tipo_id, COALESCE(jurisdiccion_id, 0) as jur_id FROM gestion__empresas_impuestos_config WHERE empresa_id = ?", $destino) as $r) {
        $k = (int) $r['impuesto_tipo_id'] . '|' . (int) $r['jur_id'];
        $existentesDest[$k] = (int) $r['empresa_impuesto_config_id'];
    }

    $cantConfig = 0;
    $cantOperaciones = 0;
    $cantSituaciones = 0;
    $cantSubgrupos = 0;
    $mapaConfig = []; // old_id => new_id

    // Leer configuraciones activas de origen
    $configsOrigen = inicioLeerFilas($conexion, "SELECT * FROM gestion__empresas_impuestos_config WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY empresa_impuesto_config_id ASC", $origen);

    foreach ($configsOrigen as $cfg) {
        $oldId = (int) $cfg['empresa_impuesto_config_id'];
        $jurId = !empty($cfg['jurisdiccion_id']) ? (int) $cfg['jurisdiccion_id'] : 0;
        $k = (int) $cfg['impuesto_tipo_id'] . '|' . $jurId;

        if (isset($existentesDest[$k])) {
            $newConfigId = $existentesDest[$k];
            $mapaConfig[$oldId] = $newConfigId;
        } else {
            $newCtaId = $cuenta($cfg['cont_cuenta_id']);
            $sqlIns = "INSERT INTO gestion__empresas_impuestos_config (
                empresa_id, impuesto_tipo_id, jurisdiccion_id, condicion_fiscal_id, cont_cuenta_id,
                base_calculo, alicuota, minimo_imponible, monto_fijo, aplica_siempre,
                prioridad, f_desde, f_hasta, tipo_calculo, tabla_estado_registro_id,
                numero_agente, criterio_territorial, importe_minimo_percepcion, arca_tributo_id,
                formato_padron, formato_ddjj, codigo_jurisdiccion_cm, codigo_regimen_ddjj,
                fuente_alicuota, sin_dato_accion, integracion_empresa_id, ws_vigencia_dias, regimen_id
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, 1,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, NULL, ?, ?
            )";
            $newConfigId = inicioInsertar($conexion, $sqlIns, "iiiiisdddiiissssdissssssii", [
                $destino,
                (int) $cfg['impuesto_tipo_id'],
                !empty($cfg['jurisdiccion_id']) ? (int) $cfg['jurisdiccion_id'] : null,
                $cfg['condicion_fiscal_id'] !== null && $cfg['condicion_fiscal_id'] !== '' ? (int) $cfg['condicion_fiscal_id'] : null,
                $newCtaId,
                $cfg['base_calculo'] ?? 'NETO_GRAVADO',
                (float) ($cfg['alicuota'] ?? 0),
                (float) ($cfg['minimo_imponible'] ?? 0),
                (float) ($cfg['monto_fijo'] ?? 0),
                (int) ($cfg['aplica_siempre'] ?? 1),
                (int) ($cfg['prioridad'] ?? 1),
                !empty($cfg['f_desde']) ? $cfg['f_desde'] : date('Y-m-d'),
                !empty($cfg['f_hasta']) ? $cfg['f_hasta'] : null,
                $cfg['tipo_calculo'] ?? 'manual',
                $cfg['numero_agente'] ?? null,
                $cfg['criterio_territorial'] ?? null,
                (float) ($cfg['importe_minimo_percepcion'] ?? 0),
                !empty($cfg['arca_tributo_id']) ? (int) $cfg['arca_tributo_id'] : null,
                $cfg['formato_padron'] ?? null,
                $cfg['formato_ddjj'] ?? null,
                $cfg['codigo_jurisdiccion_cm'] ?? null,
                $cfg['codigo_regimen_ddjj'] ?? null,
                $cfg['fuente_alicuota'] ?? null,
                $cfg['sin_dato_accion'] ?? 'CONDICION',
                !empty($cfg['ws_vigencia_dias']) ? (int) $cfg['ws_vigencia_dias'] : null,
                !empty($cfg['regimen_id']) ? (int) $cfg['regimen_id'] : null
            ]);
            $existentesDest[$k] = $newConfigId;
            $mapaConfig[$oldId] = $newConfigId;
            $cantConfig++;
        }

        // 4. Copiar operaciones hijas
        $operacionesOrigen = inicioLeerFilas($conexion, "SELECT * FROM gestion__empresas_impuestos_config_operaciones WHERE empresa_impuesto_config_id = ? AND tabla_estado_registro_id = 1", $oldId);
        if (!empty($operacionesOrigen)) {
            $existOps = [];
            foreach (inicioLeerFilas($conexion, "SELECT producto_tipo_id FROM gestion__empresas_impuestos_config_operaciones WHERE empresa_impuesto_config_id = ?", $newConfigId) as $opD) {
                $existOps[(int) $opD['producto_tipo_id']] = true;
            }
            foreach ($operacionesOrigen as $op) {
                $oldPtId = (int) $op['producto_tipo_id'];
                $newPtId = $oldPtId > 0 ? ($mapaTipos[$oldPtId] ?? 0) : 0;
                if (!isset($existOps[$newPtId])) {
                    inicioInsertar($conexion, "INSERT INTO gestion__empresas_impuestos_config_operaciones (
                        empresa_impuesto_config_id, producto_tipo_id, condicion_fiscal_id, base_calculo,
                        alicuota, minimo_imponible, monto_fijo, f_desde, f_hasta, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", "iiisdddss", [
                        $newConfigId,
                        $newPtId,
                        (int) $op['condicion_fiscal_id'],
                        $op['base_calculo'],
                        (float) ($op['alicuota'] ?? 0),
                        (float) ($op['minimo_imponible'] ?? 0),
                        (float) ($op['monto_fijo'] ?? 0),
                        !empty($op['f_desde']) ? $op['f_desde'] : null,
                        !empty($op['f_hasta']) ? $op['f_hasta'] : null
                    ]);
                    $existOps[$newPtId] = true;
                    $cantOperaciones++;
                }
            }
        }

        // 5. Copiar situaciones hijas
        $situacionesOrigen = inicioLeerFilas($conexion, "SELECT * FROM gestion__empresas_impuestos_config_situaciones WHERE empresa_impuesto_config_id = ? AND tabla_estado_registro_id = 1", $oldId);
        if (!empty($situacionesOrigen)) {
            $existSit = [];
            foreach (inicioLeerFilas($conexion, "SELECT situacion FROM gestion__empresas_impuestos_config_situaciones WHERE empresa_impuesto_config_id = ?", $newConfigId) as $sitD) {
                $existSit[strtoupper(trim($sitD['situacion']))] = true;
            }
            foreach ($situacionesOrigen as $sit) {
                $sKey = strtoupper(trim($sit['situacion']));
                if (!isset($existSit[$sKey])) {
                    inicioInsertar($conexion, "INSERT INTO gestion__empresas_impuestos_config_situaciones (
                        empresa_impuesto_config_id, situacion, requiere_territorio, alicuota,
                        tipo_registro_ddjj, declarar_si_cero, leyenda, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)", "isidiis", [
                        $newConfigId,
                        $sit['situacion'],
                        (int) $sit['requiere_territorio'],
                        (float) ($sit['alicuota'] ?? 0),
                        !empty($sit['tipo_registro_ddjj']) ? (int) $sit['tipo_registro_ddjj'] : null,
                        (int) ($sit['declarar_si_cero'] ?? 0),
                        $sit['leyenda'] ?? null
                    ]);
                    $existSit[$sKey] = true;
                    $cantSituaciones++;
                }
            }
        }

        // 6. Copiar subgrupos asignados
        $subgruposOrigen = inicioLeerFilas($conexion, "SELECT * FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_id = ? AND tabla_estado_registro_id = 1", $oldId);
        if (!empty($subgruposOrigen)) {
            $existSub = [];
            foreach (inicioLeerFilas($conexion, "SELECT comprobante_subgrupo_id FROM gestion__empresas_impuestos_config_subgrupos WHERE empresa_impuesto_config_id = ?", $newConfigId) as $sgD) {
                $existSub[(int) $sgD['comprobante_subgrupo_id']] = true;
            }
            foreach ($subgruposOrigen as $sg) {
                $oldSubId = (int) $sg['comprobante_subgrupo_id'];
                $newSubId = $mapaSub[$oldSubId] ?? 0;
                if ($newSubId > 0 && !isset($existSub[$newSubId])) {
                    inicioInsertar($conexion, "INSERT INTO gestion__empresas_impuestos_config_subgrupos (
                        empresa_impuesto_config_id, comprobante_subgrupo_id, tabla_estado_registro_id
                    ) VALUES (?, ?, 1)", "ii", [
                        $newConfigId,
                        $newSubId
                    ]);
                    $existSub[$newSubId] = true;
                    $cantSubgrupos++;
                }
            }
        }
    }

    $detallePartes = [];
    $detallePartes[] = "$cantConfig configuraciones de impuestos";
    if ($cantOperaciones > 0) {
        $detallePartes[] = "$cantOperaciones reglas por operación";
    }
    if ($cantSituaciones > 0) {
        $detallePartes[] = "$cantSituaciones situaciones";
    }
    if ($cantSubgrupos > 0) {
        $detallePartes[] = "$cantSubgrupos comprobantes asignados";
    }
    $detalle = implode(', ', $detallePartes);

    return [$detalle, $cantConfig + $cantOperaciones + $cantSituaciones + $cantSubgrupos];
}

/**
 * Copia el catálogo de productos y servicios (gestion__productos) de la empresa origen a la destino.
 * Remapea (y crea si no existen en destino):
 * - Unidades de medida propias (por abreviatura; las empresa_id = 0 son globales)
 * - Alícuotas de IVA (por código)
 * - Tipos de producto (por código o nombre)
 * - Categorías de producto (árbol padre-hijo por nombre)
 * - Cuentas contables (compra, venta, CMV y cuenta legacy) mediante $mapaCuentas
 * No duplica códigos de producto existentes en la empresa destino.
 * Devuelve [texto del detalle, cantidad de productos/servicios creados].
 */
function inicioCopiarProductos(mysqli $conexion, int $origen, int $destino, array $mapaCuentas): array
{
    $cuenta = function ($id) use ($mapaCuentas) {
        return (!empty($id) && isset($mapaCuentas[(int) $id])) ? $mapaCuentas[(int) $id] : null;
    };

    // Cuenta legacy MERCADERIAS en destino (0 si no existe)
    $ctaMercaderiasDefault = 0;
    $resMerc = mysqli_query($conexion, "SELECT cont_cuenta_id FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = $destino AND concepto = 'MERCADERIAS' AND tabla_estado_registro_id = 1 LIMIT 1");
    if ($resMerc && ($rowMerc = mysqli_fetch_assoc($resMerc)) && !empty($rowMerc['cont_cuenta_id'])) {
        $ctaMercaderiasDefault = (int) $rowMerc['cont_cuenta_id'];
    }

    // 1. Unidades de medida (las empresa_id = 0 son globales; las propias se mapean por abreviatura)
    $umDest = [];
    foreach (inicioLeerFilas($conexion, "SELECT unidad_medida_id, unidad_abreviatura FROM gestion__unidades_medida WHERE empresa_id = 0 OR empresa_id = ?", $destino) as $u) {
        $umDest[mb_strtoupper(trim((string) $u['unidad_abreviatura']))] = (int) $u['unidad_medida_id'];
    }
    $mapaUm = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__unidades_medida WHERE empresa_id = 0 OR empresa_id = ?", $origen) as $u) {
        $uid = (int) $u['unidad_medida_id'];
        if ((int) $u['empresa_id'] === 0) {
            $mapaUm[$uid] = $uid;
            continue;
        }
        $k = mb_strtoupper(trim((string) $u['unidad_abreviatura']));
        if (!isset($umDest[$k])) {
            $umDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__unidades_medida (empresa_id, unidad_nombre, unidad_abreviatura, tabla_estado_registro_id) VALUES (?, ?, ?, 1)", "iss", [
                $destino, $u['unidad_nombre'], $u['unidad_abreviatura']
            ]);
        }
        $mapaUm[$uid] = $umDest[$k];
    }

    // 2. Alícuotas de IVA
    $ivaDest = [];
    $ivaDefaultId = 0;
    foreach (inicioLeerFilas($conexion, "SELECT iva_alicuota_id, codigo FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ?", $destino) as $r) {
        $ivaDest[strtoupper(trim((string) $r['codigo']))] = (int) $r['iva_alicuota_id'];
        if ($ivaDefaultId === 0) {
            $ivaDefaultId = (int) $r['iva_alicuota_id'];
        }
    }
    $mapaIva = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ?", $origen) as $a) {
        $k = strtoupper(trim((string) $a['codigo']));
        if (!isset($ivaDest[$k]) && (int) $a['tabla_estado_registro_id'] === 1) {
            $ctaCompra = (int) ($cuenta($a['cont_cuenta_compra_id']) ?? 0);
            $ctaVenta = (int) ($cuenta($a['cont_cuenta_venta_id']) ?? 0);
            $ivaDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__impuestos__iva_alicuotas
                (empresa_id, codigo, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado,
                 cont_cuenta_compra_id, cont_cuenta_venta_id, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", "isssiiiii", [
                $destino, $a['codigo'], $a['iva_alicuota'], (string) $a['porcentaje'],
                (int) $a['es_gravado'], (int) $a['es_exento'], (int) $a['es_no_gravado'], $ctaCompra, $ctaVenta
            ]);
        }
        if (isset($ivaDest[$k])) {
            $mapaIva[(int) $a['iva_alicuota_id']] = $ivaDest[$k];
            if ($ivaDefaultId === 0) {
                $ivaDefaultId = $ivaDest[$k];
            }
        }
    }

    // 3. Tipos de producto
    $claveTipo = function (array $r): string {
        $codigo = strtoupper(trim((string) ($r['producto_tipo_codigo'] ?? '')));
        return $codigo !== '' ? 'C:' . $codigo : 'N:' . mb_strtoupper(trim((string) ($r['producto_tipo'] ?? '')));
    };
    $tiposDest = [];
    $tipoDefaultId = 0;
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_tipos WHERE empresa_id = ?", $destino) as $r) {
        $tiposDest[$claveTipo($r)] = (int) $r['producto_tipo_id'];
        if ($tipoDefaultId === 0) {
            $tipoDefaultId = (int) $r['producto_tipo_id'];
        }
    }
    $mapaTipos = [];
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_tipos WHERE empresa_id = ?", $origen) as $t) {
        $k = $claveTipo($t);
        if (!isset($tiposDest[$k]) && (int) $t['tabla_estado_registro_id'] === 1) {
            $tiposDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__productos_tipos
                (empresa_id, producto_tipo, producto_tipo_codigo, maneja_stock, es_compuesto, afecta_facturacion,
                 tabla_estado_registro_id, cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)", "issiiiiii", [
                $destino, $t['producto_tipo'], $t['producto_tipo_codigo'],
                (int) $t['maneja_stock'], (int) $t['es_compuesto'], (int) $t['afecta_facturacion'],
                $cuenta($t['cont_cuenta_compra_default_id']), $cuenta($t['cont_cuenta_venta_default_id']), $cuenta($t['cont_cuenta_cmv_default_id'])
            ]);
        }
        if (isset($tiposDest[$k])) {
            $mapaTipos[(int) $t['producto_tipo_id']] = $tiposDest[$k];
            if ($tipoDefaultId === 0) {
                $tipoDefaultId = $tiposDest[$k];
            }
        }
    }

    // 4. Categorías de producto (árbol)
    $catDest = [];
    $catDefaultId = 0;
    foreach (inicioLeerFilas($conexion, "SELECT producto_categoria_id, producto_categoria_nombre, producto_categoria_padre_id
                                         FROM gestion__productos_categorias WHERE empresa_id = ?", $destino) as $r) {
        $catDest[mb_strtoupper(trim($r['producto_categoria_nombre'])) . '|' . (int) $r['producto_categoria_padre_id']] = (int) $r['producto_categoria_id'];
        if ($catDefaultId === 0) {
            $catDefaultId = (int) $r['producto_categoria_id'];
        }
    }
    $pendientes = inicioLeerFilas($conexion, "SELECT * FROM gestion__productos_categorias WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                              ORDER BY producto_categoria_id", $origen);
    $mapaCat = [];
    do {
        $avance = false;
        foreach ($pendientes as $i => $cat) {
            $padreOrigen = (int) $cat['producto_categoria_padre_id'];
            if ($padreOrigen > 0 && !isset($mapaCat[$padreOrigen])) {
                continue;
            }
            $padreDest = $padreOrigen > 0 ? $mapaCat[$padreOrigen] : null;
            $k = mb_strtoupper(trim($cat['producto_categoria_nombre'])) . '|' . (int) $padreDest;
            if (!isset($catDest[$k])) {
                $tipoDest = $mapaTipos[(int) $cat['producto_tipo_id']] ?? null;
                $catDest[$k] = inicioInsertar($conexion, "INSERT INTO gestion__productos_categorias
                    (empresa_id, producto_categoria_nombre, producto_categoria_padre_id, tabla_estado_registro_id,
                     producto_tipo_id, cont_cuenta_ingreso_id, cont_cuenta_cmv_id)
                    VALUES (?, ?, ?, 1, ?, ?, ?)", "isiiii", [
                    $destino, $cat['producto_categoria_nombre'], $padreDest, $tipoDest,
                    $cuenta($cat['cont_cuenta_ingreso_id']), $cuenta($cat['cont_cuenta_cmv_id'])
                ]);
            }
            $mapaCat[(int) $cat['producto_categoria_id']] = $catDest[$k];
            if ($catDefaultId === 0) {
                $catDefaultId = $catDest[$k];
            }
            unset($pendientes[$i]);
            $avance = true;
        }
    } while ($avance && $pendientes);

    // 5. Productos existentes en destino (por código)
    $existentes = [];
    foreach (inicioLeerFilas($conexion, "SELECT producto_codigo FROM gestion__productos WHERE empresa_id = ?", $destino) as $r) {
        $existentes[mb_strtoupper(trim((string) $r['producto_codigo']))] = true;
    }

    $sqlIns = "INSERT INTO gestion__productos (
        empresa_id, producto_codigo, producto_nombre, codigo_barras, producto_descripcion,
        producto_categoria_id, cont_cuenta_id, cont_cuenta_compra_id, cont_cuenta_venta_id, cont_cuenta_cmv_id,
        producto_tipo_id, lado, material, color, peso, dimensiones, garantia,
        unidad_medida_id, iva_alicuota_id, impuesto_interno_porcentaje, impuesto_interno_fijo,
        controla_stock, es_servicio, tabla_estado_registro_id, aplica_compra, aplica_venta
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, 1, ?, ?
    )";
    $stmtIns = mysqli_prepare($conexion, $sqlIns);
    if (!$stmtIns) {
        throw new Exception("Error preparando inserción de productos: " . mysqli_error($conexion));
    }

    $cantProductos = 0;
    $cantServicios = 0;
    $cantOmitidos = 0;

    $productosOrigen = inicioLeerFilas($conexion, "SELECT * FROM gestion__productos WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY producto_id ASC", $origen);
    foreach ($productosOrigen as $p) {
        $codigo = trim((string) $p['producto_codigo']);
        $codKey = mb_strtoupper($codigo);
        if ($codigo === '' || isset($existentes[$codKey])) {
            continue;
        }

        $catId = $mapaCat[(int) $p['producto_categoria_id']] ?? $catDefaultId;
        $tipoId = $mapaTipos[(int) $p['producto_tipo_id']] ?? $tipoDefaultId;
        $ivaId = $mapaIva[(int) $p['iva_alicuota_id']] ?? $ivaDefaultId;

        if ($catId <= 0 || $tipoId <= 0 || $ivaId <= 0) {
            $cantOmitidos++;
            continue;
        }

        $nombre = (string) $p['producto_nombre'];
        $barras = $p['codigo_barras'] !== null && $p['codigo_barras'] !== '' ? (string) $p['codigo_barras'] : null;
        $desc = $p['producto_descripcion'] !== null && $p['producto_descripcion'] !== '' ? (string) $p['producto_descripcion'] : null;

        $ctaLegacy = (int) ($cuenta($p['cont_cuenta_id']) ?? $ctaMercaderiasDefault);
        $ctaCompra = $cuenta($p['cont_cuenta_compra_id']);
        $ctaVenta = $cuenta($p['cont_cuenta_venta_id']);
        $ctaCmv = $cuenta($p['cont_cuenta_cmv_id']);

        $lado = $p['lado'] !== null && $p['lado'] !== '' ? (string) $p['lado'] : null;
        $material = $p['material'] !== null && $p['material'] !== '' ? (string) $p['material'] : null;
        $color = $p['color'] !== null && $p['color'] !== '' ? (string) $p['color'] : null;
        $peso = $p['peso'] !== null && $p['peso'] !== '' ? (float) $p['peso'] : null;
        $dimensiones = $p['dimensiones'] !== null && $p['dimensiones'] !== '' ? (string) $p['dimensiones'] : null;
        $garantia = $p['garantia'] !== null && $p['garantia'] !== '' ? (string) $p['garantia'] : null;

        $umId = !empty($p['unidad_medida_id']) ? ($mapaUm[(int) $p['unidad_medida_id']] ?? (int) $p['unidad_medida_id']) : null;
        $impPorc = $p['impuesto_interno_porcentaje'] !== null && $p['impuesto_interno_porcentaje'] !== '' ? (float) $p['impuesto_interno_porcentaje'] : null;
        $impFijo = $p['impuesto_interno_fijo'] !== null && $p['impuesto_interno_fijo'] !== '' ? (float) $p['impuesto_interno_fijo'] : null;

        $esServicio = (int) ($p['es_servicio'] ?? 0);
        $controlaStock = $esServicio ? 0 : (int) ($p['controla_stock'] ?? 1);
        $aplicaCompra = (int) ($p['aplica_compra'] ?? 1);
        $aplicaVenta = (int) ($p['aplica_venta'] ?? 1);

        mysqli_stmt_bind_param(
            $stmtIns,
            "issssiiiiiisssdssiiddiiii",
            $destino, $codigo, $nombre, $barras, $desc,
            $catId, $ctaLegacy, $ctaCompra, $ctaVenta, $ctaCmv,
            $tipoId, $lado, $material, $color, $peso, $dimensiones, $garantia,
            $umId, $ivaId, $impPorc, $impFijo,
            $controlaStock, $esServicio, $aplicaCompra, $aplicaVenta
        );
        if (!mysqli_stmt_execute($stmtIns)) {
            $err = mysqli_stmt_error($stmtIns);
            mysqli_stmt_close($stmtIns);
            throw new Exception("Error copiando producto '$codigo': " . $err);
        }

        $existentes[$codKey] = true;
        if ($esServicio) {
            $cantServicios++;
        } else {
            $cantProductos++;
        }
    }
    mysqli_stmt_close($stmtIns);

    $total = $cantProductos + $cantServicios;
    $detalle = "$total ítems copiados ($cantProductos productos, $cantServicios servicios)"
        . ($cantOmitidos > 0 ? ", $cantOmitidos omitidos por falta de categoría/tipo/IVA" : '');

    return [$detalle, $total];
}

/**
 * Obtiene el diagnóstico y checklist completo de inicio de una empresa
 */
function inicioObtenerDiagnostico($conexion, $empresa_id, $modulo_id = 5) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);

    // 1. Datos básicos de la empresa
    $sqlEmp = "SELECT e.*, cf.condicion_fiscal, l.localidad,
                      jr.jurisdiccion_nombre as jurisdiccion_sede_nombre
               FROM conf__empresas e
               LEFT JOIN gestion__condiciones_fiscales cf ON e.condicion_fiscal_id = cf.condicion_fiscal_id
               LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
               LEFT JOIN gestion__jurisdicciones jr ON e.jurisdiccion_sede_id = jr.jurisdiccion_id
               WHERE e.empresa_id = ?";
    $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
    mysqli_stmt_bind_param($stmtEmp, "i", $empresa_id);
    mysqli_stmt_execute($stmtEmp);
    $resEmp = mysqli_stmt_get_result($stmtEmp);
    $empresaData = mysqli_fetch_assoc($resEmp) ?: [];
    mysqli_stmt_close($stmtEmp);

    // 2. Sucursales
    $sqlSuc = "SELECT s.*, st.sucursal_tipo 
               FROM gestion__sucursales s
               LEFT JOIN gestion__sucursales_tipos st ON s.sucursal_tipo_id = st.sucursal_tipo_id
               WHERE s.empresa_id = ? AND s.tabla_estado_registro_id = 1
               ORDER BY s.sucursal_id ASC";
    $stmtSuc = mysqli_prepare($conexion, $sqlSuc);
    mysqli_stmt_bind_param($stmtSuc, "i", $empresa_id);
    mysqli_stmt_execute($stmtSuc);
    $resSuc = mysqli_stmt_get_result($stmtSuc);
    $sucursales = [];
    while ($row = mysqli_fetch_assoc($resSuc)) {
        $sucursales[] = $row;
    }
    mysqli_stmt_close($stmtSuc);

    // 3. Bocas de Depósito / Stock
    $sqlBoc = "SELECT b.*, s.sucursal_nombre
               FROM gestion__bocas b
               JOIN gestion__sucursales s ON b.sucursal_id = s.sucursal_id
               WHERE b.empresa_id = ? AND b.tabla_estado_registro_id = 1
               ORDER BY b.es_principal DESC, b.boca_id ASC";
    $stmtBoc = mysqli_prepare($conexion, $sqlBoc);
    mysqli_stmt_bind_param($stmtBoc, "i", $empresa_id);
    mysqli_stmt_execute($stmtBoc);
    $resBoc = mysqli_stmt_get_result($stmtBoc);
    $bocas = [];
    while ($row = mysqli_fetch_assoc($resBoc)) {
        $bocas[] = $row;
    }
    mysqli_stmt_close($stmtBoc);

    // 4. Puntos de Venta (Bocas de Emisión)
    $sqlPv = "SELECT pv.*, s.sucursal_nombre, b.boca_nombre
              FROM gestion__puntos_venta pv
              LEFT JOIN gestion__sucursales s ON pv.sucursal_id = s.sucursal_id
              LEFT JOIN gestion__bocas b ON pv.boca_id = b.boca_id
              WHERE pv.empresa_id = ? AND pv.tabla_estado_registro_id = 1
              ORDER BY pv.codigo_fiscal ASC";
    $stmtPv = mysqli_prepare($conexion, $sqlPv);
    mysqli_stmt_bind_param($stmtPv, "i", $empresa_id);
    mysqli_stmt_execute($stmtPv);
    $resPv = mysqli_stmt_get_result($stmtPv);
    $puntosVenta = [];
    while ($row = mysqli_fetch_assoc($resPv)) {
        $puntosVenta[] = $row;
    }
    mysqli_stmt_close($stmtPv);

    // 5. Tipos de Comprobantes
    $sqlComp = "SELECT comprobante_tipo_id, codigo, comprobante_tipo, letra, signo, impacta_stock, impacta_contabilidad, impacta_ctacte
                FROM gestion__comprobantes_tipos
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY orden ASC, comprobante_tipo ASC";
    $stmtComp = mysqli_prepare($conexion, $sqlComp);
    mysqli_stmt_bind_param($stmtComp, "i", $empresa_id);
    mysqli_stmt_execute($stmtComp);
    $resComp = mysqli_stmt_get_result($stmtComp);
    $comprobantes = [];
    while ($row = mysqli_fetch_assoc($resComp)) {
        $comprobantes[] = $row;
    }
    mysqli_stmt_close($stmtComp);

    // 6. Accesos a Sucursales por Perfil
    $sqlAcc = "SELECT ps.*, ep.empresa_perfil_nombre, s.sucursal_nombre
               FROM gestion__perfiles_sucursales ps
               JOIN conf__empresas_perfiles ep ON ps.empresa_perfil_id = ep.empresa_perfil_id
               LEFT JOIN gestion__sucursales s ON ps.sucursal_id = s.sucursal_id
               WHERE ps.empresa_id = ? AND ps.tabla_estado_registro_id = 1";
    $stmtAcc = mysqli_prepare($conexion, $sqlAcc);
    mysqli_stmt_bind_param($stmtAcc, "i", $empresa_id);
    mysqli_stmt_execute($stmtAcc);
    $resAcc = mysqli_stmt_get_result($stmtAcc);
    $accesos = [];
    while ($row = mysqli_fetch_assoc($resAcc)) {
        $accesos[] = $row;
    }
    mysqli_stmt_close($stmtAcc);

    // Perfiles activos de la empresa
    $sqlPerf = "SELECT empresa_perfil_id, empresa_perfil_nombre, modulo_id
                FROM conf__empresas_perfiles
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmtPerf = mysqli_prepare($conexion, $sqlPerf);
    mysqli_stmt_bind_param($stmtPerf, "i", $empresa_id);
    mysqli_stmt_execute($stmtPerf);
    $resPerf = mysqli_stmt_get_result($stmtPerf);
    $perfiles = [];
    while ($row = mysqli_fetch_assoc($resPerf)) {
        $perfiles[] = $row;
    }
    mysqli_stmt_close($stmtPerf);

    // 7. Plan de Cuentas Contables
    $sqlCuentas = "SELECT 
                      COUNT(*) as total_cuentas,
                      SUM(CASE WHEN es_imputable = 1 THEN 1 ELSE 0 END) as total_imputables,
                      SUM(CASE WHEN es_caja_banco = 1 THEN 1 ELSE 0 END) as total_caja_banco,
                      MAX(nivel) as max_nivel
                   FROM gestion__cont_cuentas
                   WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmtCuentas = mysqli_prepare($conexion, $sqlCuentas);
    mysqli_stmt_bind_param($stmtCuentas, "i", $empresa_id);
    mysqli_stmt_execute($stmtCuentas);
    $resCuentas = mysqli_stmt_get_result($stmtCuentas);
    $cuentasStats = mysqli_fetch_assoc($resCuentas) ?: ['total_cuentas' => 0, 'total_imputables' => 0, 'total_caja_banco' => 0, 'max_nivel' => 0];
    mysqli_stmt_close($stmtCuentas);

    // 8. Conceptos Contables de la Empresa
    $sqlConc = "SELECT ecc.*, cc.codigo as cuenta_codigo, cc.nombre as cuenta_nombre
                FROM gestion__empresas_cuentas_conceptos ecc
                LEFT JOIN gestion__cont_cuentas cc ON ecc.cont_cuenta_id = cc.cont_cuenta_id
                WHERE ecc.empresa_id = ? AND ecc.tabla_estado_registro_id = 1
                ORDER BY ecc.concepto ASC";
    $stmtConc = mysqli_prepare($conexion, $sqlConc);
    mysqli_stmt_bind_param($stmtConc, "i", $empresa_id);
    mysqli_stmt_execute($stmtConc);
    $resConc = mysqli_stmt_get_result($stmtConc);
    $conceptos = [];
    $conceptosConfigurados = 0;
    while ($row = mysqli_fetch_assoc($resConc)) {
        $conceptos[] = $row;
        if (!empty($row['cont_cuenta_id'])) {
            $conceptosConfigurados++;
        }
    }
    mysqli_stmt_close($stmtConc);

    // 9. Cuentas Contables de Roles de Entidades
    $sqlRoles = "SELECT erc.*, r.rol_entidad_codigo, r.rol_entidad_nombre,
                        cc.codigo as cuenta_codigo, cc.nombre as cuenta_nombre
                 FROM gestion__empresas_roles_cuentas erc
                 JOIN gestion__roles_entidades r ON erc.rol_entidad_id = r.rol_entidad_id
                 LEFT JOIN gestion__cont_cuentas cc ON erc.cont_cuenta_id = cc.cont_cuenta_id
                 WHERE erc.empresa_id = ? AND erc.tabla_estado_registro_id = 1
                 ORDER BY erc.rol_entidad_id ASC";
    $stmtRoles = mysqli_prepare($conexion, $sqlRoles);
    mysqli_stmt_bind_param($stmtRoles, "i", $empresa_id);
    mysqli_stmt_execute($stmtRoles);
    $resRoles = mysqli_stmt_get_result($stmtRoles);
    $rolesCuentas = [];
    while ($row = mysqli_fetch_assoc($resRoles)) {
        $rolesCuentas[] = $row;
    }
    mysqli_stmt_close($stmtRoles);

    // 10. Condiciones de Pago
    $sqlCond = "SELECT * FROM gestion__condiciones_pago
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY orden ASC, condicion_pago ASC";
    $stmtCond = mysqli_prepare($conexion, $sqlCond);
    mysqli_stmt_bind_param($stmtCond, "i", $empresa_id);
    mysqli_stmt_execute($stmtCond);
    $resCond = mysqli_stmt_get_result($stmtCond);
    $condicionesPago = [];
    while ($row = mysqli_fetch_assoc($resCond)) {
        $condicionesPago[] = $row;
    }
    mysqli_stmt_close($stmtCond);

    // 11. Listas de Precios
    $sqlListas = "SELECT * FROM gestion__listas_precios
                  WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                  ORDER BY lista_precio_nombre ASC";
    $stmtListas = mysqli_prepare($conexion, $sqlListas);
    mysqli_stmt_bind_param($stmtListas, "i", $empresa_id);
    mysqli_stmt_execute($stmtListas);
    $resListas = mysqli_stmt_get_result($stmtListas);
    $listasPrecios = [];
    while ($row = mysqli_fetch_assoc($resListas)) {
        $listasPrecios[] = $row;
    }
    mysqli_stmt_close($stmtListas);

    // 12. Parámetros de Facturación / Generales
    $sqlParams = "SELECT ep.*, cf.condicion_fiscal, pv.nombre as pv_nombre, lp.lista_precio_nombre, cp.condicion_pago
                  FROM gestion__empresas_parametros ep
                  LEFT JOIN gestion__condiciones_fiscales cf ON ep.condicion_fiscal_id = cf.condicion_fiscal_id
                  LEFT JOIN gestion__puntos_venta pv ON ep.punto_venta_defecto_id = pv.punto_venta_id
                  LEFT JOIN gestion__listas_precios lp ON ep.lista_precio_defecto_id = lp.lista_precio_id
                  LEFT JOIN gestion__condiciones_pago cp ON ep.condicion_pago_defecto_id = cp.condicion_pago_id
                  WHERE ep.empresa_id = ? AND ep.tabla_estado_registro_id = 1
                    AND (ep.f_hasta IS NULL OR ep.f_hasta >= CURDATE())
                  ORDER BY ep.f_desde DESC LIMIT 1";
    $stmtParams = mysqli_prepare($conexion, $sqlParams);
    mysqli_stmt_bind_param($stmtParams, "i", $empresa_id);
    mysqli_stmt_execute($stmtParams);
    $resParams = mysqli_stmt_get_result($stmtParams);
    $parametros = mysqli_fetch_assoc($resParams) ?: null;
    mysqli_stmt_close($stmtParams);

    // 13. Tipo de operación y perfiles predefinidos (core/perfiles_predefinidos.php)
    $tipoOperacion = empresa_tipo_operacion($conexion, $empresa_id);
    $perfilesPredefinidos = perfiles_base_para_empresa($conexion, $empresa_id, $modulo_id);
    $perfilesFaltantes = array_values(array_filter($perfilesPredefinidos, function ($p) {
        return $p['empresa_perfil_id'] === null;
    }));
    // En una empresa de servicios, perfiles que todavía muestran pedidos, remitos o stock.
    $perfilesBienes = $tipoOperacion === 'SERVICIOS' ? perfiles_funciones_bienes($conexion, $empresa_id, $modulo_id) : [];

    // 14. Maestros sin los que la empresa no factura ni cobra (la copia no los trae: cada uno tiene su página)
    $operacion = inicioMaestrosOperacion($conexion, $empresa_id);

    // 15. Configuración de Impuestos (IIBB, Percepciones, Retenciones)
    $sqlImp = "SELECT eic.*, it.impuesto_tipo, it.codigo_afip, j.jurisdiccion_nombre, cc.codigo as cuenta_codigo
               FROM gestion__empresas_impuestos_config eic
               LEFT JOIN gestion__impuestos_tipos it ON eic.impuesto_tipo_id = it.impuesto_tipo_id
               LEFT JOIN gestion__jurisdicciones j ON eic.jurisdiccion_id = j.jurisdiccion_id
               LEFT JOIN gestion__cont_cuentas cc ON eic.cont_cuenta_id = cc.cont_cuenta_id
               WHERE eic.empresa_id = ? AND eic.tabla_estado_registro_id = 1
               ORDER BY eic.prioridad ASC, it.impuesto_tipo ASC";
    $stmtImp = mysqli_prepare($conexion, $sqlImp);
    mysqli_stmt_bind_param($stmtImp, "i", $empresa_id);
    mysqli_stmt_execute($stmtImp);
    $resImp = mysqli_stmt_get_result($stmtImp);
    $impuestosConfig = [];
    while ($row = mysqli_fetch_assoc($resImp)) {
        $impuestosConfig[] = $row;
    }
    mysqli_stmt_close($stmtImp);

    // 16. Catálogo de Productos y Servicios
    $sqlProd = "SELECT 
                    COUNT(*) as total_productos,
                    SUM(CASE WHEN es_servicio = 0 THEN 1 ELSE 0 END) as total_bienes,
                    SUM(CASE WHEN es_servicio = 1 THEN 1 ELSE 0 END) as total_servicios
                FROM gestion__productos
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmtProd = mysqli_prepare($conexion, $sqlProd);
    mysqli_stmt_bind_param($stmtProd, "i", $empresa_id);
    mysqli_stmt_execute($stmtProd);
    $resProd = mysqli_stmt_get_result($stmtProd);
    $productosStats = mysqli_fetch_assoc($resProd) ?: ['total_productos' => 0, 'total_bienes' => 0, 'total_servicios' => 0];
    $productosStats['total_productos'] = intval($productosStats['total_productos'] ?? 0);
    $productosStats['total_bienes'] = intval($productosStats['total_bienes'] ?? 0);
    $productosStats['total_servicios'] = intval($productosStats['total_servicios'] ?? 0);
    mysqli_stmt_close($stmtProd);

    // Puntaje de inicio: cada control pesa lo suyo y el total se lleva a 100
    $checks = [
        'perfiles' => count($perfiles) > 0 && count($perfilesFaltantes) === 0 && count($perfilesBienes) === 0,
        'sucursales' => count($sucursales) > 0,
        'bocas' => count($bocas) > 0,
        'puntos_venta' => count($puntosVenta) > 0,
        'comprobantes_tipos' => count($comprobantes) >= 5, // al menos 5 tipos elementales
        'accesos_perfiles' => count($accesos) > 0,
        'plan_cuentas' => intval($cuentasStats['total_imputables']) >= 10,
        'conceptos_contables' => $conceptosConfigurados >= 3,
        'roles_cuentas' => count($rolesCuentas) >= 2, // al menos cliente y proveedor
        'condiciones_pago' => count($condicionesPago) > 0,
        'parametros' => !empty($parametros),
        'monedas' => $operacion['moneda_base'] !== null,
        'iva_alicuotas' => $operacion['iva_gravadas'] > 0,
        'tipos_asiento' => $operacion['tipo_asiento_aut'],
        'tesoreria_cuentas' => $operacion['cajas'] > 0 && $operacion['cajas_sin_cuenta'] === 0,
        'medios_pago' => $operacion['medios_cobro'] > 0 && $operacion['medios_pago'] > 0 && $operacion['medios_sin_cuenta'] === 0,
        'pv_comprobantes' => count($puntosVenta) > 0 && $operacion['pv_sin_comprobantes'] === 0,
        'impuestos_config' => count($impuestosConfig) > 0,
        'productos' => $productosStats['total_productos'] > 0,
    ];
    $pesos = ['perfiles' => 10, 'sucursales' => 10, 'bocas' => 10, 'puntos_venta' => 10, 'comprobantes_tipos' => 10, 'accesos_perfiles' => 10,
              'plan_cuentas' => 10, 'conceptos_contables' => 10, 'roles_cuentas' => 10, 'condiciones_pago' => 5, 'parametros' => 5,
              'monedas' => 10, 'iva_alicuotas' => 10, 'tipos_asiento' => 10, 'tesoreria_cuentas' => 10, 'medios_pago' => 10, 'pv_comprobantes' => 10,
              'impuestos_config' => 5, 'productos' => 5];
    $logrado = 0;
    foreach ($pesos as $clave => $peso) {
        if ($checks[$clave]) $logrado += $peso;
    }
    // 100 solo con todo cumplido (el redondeo no puede completar lo que falta)
    $score = $logrado === array_sum($pesos) ? 100 : min(99, intval(round($logrado * 100 / array_sum($pesos))));

    return [
        'empresa' => $empresaData,
        'score' => $score,
        'checks' => $checks,
        'sucursales' => $sucursales,
        'bocas' => $bocas,
        'puntos_venta' => $puntosVenta,
        'comprobantes_tipos' => $comprobantes,
        'accesos' => $accesos,
        'perfiles' => $perfiles,
        'tipo_operacion' => $tipoOperacion,
        'tipos_operacion' => TIPOS_OPERACION,
        'perfiles_predefinidos' => $perfilesPredefinidos,
        'perfiles_bienes' => $perfilesBienes,
        'cuentas_stats' => $cuentasStats,
        'conceptos' => $conceptos,
        'conceptos_configurados' => $conceptosConfigurados,
        'roles_cuentas' => $rolesCuentas,
        'condiciones_pago' => $condicionesPago,
        'listas_precios' => $listasPrecios,
        'parametros' => $parametros,
        'operacion' => $operacion,
        'impuestos_config' => $impuestosConfig,
        'productos_stats' => $productosStats
    ];
}

/**
 * Copia las relaciones activas entre tipos de comprobante de la empresa origen (gestion__comprobantes_tipos_cancelaciones:
 * qué tipo cancela a cuál; _dependencias: comprobante padre de cada tipo), con los tipos remapeados por código a los
 * de la destino. Solo agrega las que faltan (las claves únicas son por empresa + par de tipos). Devuelve cuántas creó.
 */
function inicioCopiarRelacionesTipos($conexion, $origen, $destino) {
    $origen = intval($origen);
    $destino = intval($destino);
    $total = 0;
    $tablas = [
        ['gestion__comprobantes_tipos_cancelaciones', 'comprobante_tipo_origen_id', 'comprobante_tipo_destino_id', ', exige_misma_entidad', ', x.exige_misma_entidad'],
        ['gestion__comprobantes_tipos_dependencias', 'comprobante_tipo_id', 'comprobante_tipo_padre_id', '', ''],
    ];
    foreach ($tablas as [$tabla, $c1, $c2, $extra, $extraSel]) {
        $sql = "INSERT INTO $tabla (empresa_id, $c1, $c2$extra, tabla_estado_registro_id)
                SELECT ?, d1.comprobante_tipo_id, d2.comprobante_tipo_id$extraSel, 1
                FROM $tabla x
                JOIN gestion__comprobantes_tipos o1 ON o1.comprobante_tipo_id = x.$c1
                JOIN gestion__comprobantes_tipos o2 ON o2.comprobante_tipo_id = x.$c2
                JOIN gestion__comprobantes_tipos d1 ON d1.empresa_id = ? AND d1.codigo = o1.codigo
                JOIN gestion__comprobantes_tipos d2 ON d2.empresa_id = ? AND d2.codigo = o2.codigo
                WHERE x.empresa_id = ? AND x.tabla_estado_registro_id = 1
                  AND NOT EXISTS (SELECT 1 FROM $tabla y WHERE y.empresa_id = ? AND y.$c1 = d1.comprobante_tipo_id
                                                          AND y.$c2 = d2.comprobante_tipo_id)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiiii", $destino, $destino, $destino, $origen, $destino);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("No se pudieron copiar las relaciones de tipos ($tabla): " . mysqli_stmt_error($stmt));
        }
        $total += mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    }
    return $total;
}

/** Clave de equivalencia de una sucursal entre empresas: su nombre. */
function inicioClaveSucursal(array $r): string
{
    return mb_strtoupper(trim((string) ($r['sucursal_nombre'] ?? '')));
}

/** Clave de equivalencia de un punto de venta entre empresas: su número fiscal o, sin número, su nombre. */
function inicioClavePuntoVenta(array $r): string
{
    $cod = $r['codigo_fiscal'] ?? null;
    return ($cod !== null && $cod !== '') ? 'F:' . (int) $cod : 'N:' . mb_strtoupper(trim((string) ($r['nombre'] ?? '')));
}

/** [sucursal origen => sucursal destino] de las que la destino ya tiene (por nombre). */
function inicioMapaSucursales(mysqli $conexion, int $origen, int $destino): array
{
    $dest = [];
    foreach (inicioLeerFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales
                                         WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id", $destino) as $r) {
        $dest[inicioClaveSucursal($r)] = $dest[inicioClaveSucursal($r)] ?? (int) $r['sucursal_id'];
    }
    $mapa = [];
    foreach (inicioLeerFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ?", $origen) as $r) {
        if (isset($dest[inicioClaveSucursal($r)])) {
            $mapa[(int) $r['sucursal_id']] = $dest[inicioClaveSucursal($r)];
        }
    }
    return $mapa;
}

/** [boca origen => boca destino] de las que la destino ya tiene (sucursal equivalente + código). */
function inicioMapaBocas(mysqli $conexion, int $origen, int $destino, array $mapaSuc): array
{
    $dest = [];
    foreach (inicioLeerFilas($conexion, "SELECT boca_id, sucursal_id, codigo FROM gestion__bocas WHERE empresa_id = ?", $destino) as $r) {
        $dest[(int) $r['sucursal_id'] . '|' . strtoupper(trim((string) $r['codigo']))] = (int) $r['boca_id'];
    }
    $mapa = [];
    foreach (inicioLeerFilas($conexion, "SELECT boca_id, sucursal_id, codigo FROM gestion__bocas WHERE empresa_id = ?", $origen) as $r) {
        $k = ($mapaSuc[(int) $r['sucursal_id']] ?? 0) . '|' . strtoupper(trim((string) $r['codigo']));
        if (isset($dest[$k])) {
            $mapa[(int) $r['boca_id']] = $dest[$k];
        }
    }
    return $mapa;
}

/** [punto de venta origen => destino] de los que la destino ya tiene (por número fiscal o nombre). */
function inicioMapaPuntosVenta(mysqli $conexion, int $origen, int $destino): array
{
    $dest = [];
    foreach (inicioLeerFilas($conexion, "SELECT punto_venta_id, codigo_fiscal, nombre FROM gestion__puntos_venta
                                         WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY punto_venta_id", $destino) as $r) {
        $dest[inicioClavePuntoVenta($r)] = $dest[inicioClavePuntoVenta($r)] ?? (int) $r['punto_venta_id'];
    }
    $mapa = [];
    foreach (inicioLeerFilas($conexion, "SELECT punto_venta_id, codigo_fiscal, nombre FROM gestion__puntos_venta WHERE empresa_id = ?", $origen) as $r) {
        if (isset($dest[inicioClavePuntoVenta($r)])) {
            $mapa[(int) $r['punto_venta_id']] = $dest[inicioClavePuntoVenta($r)];
        }
    }
    return $mapa;
}

/** [tipo de comprobante origen => destino] por código. */
function inicioMapaTiposComprobante(mysqli $conexion, int $origen, int $destino): array
{
    $dest = [];
    foreach (inicioLeerFilas($conexion, "SELECT comprobante_tipo_id, codigo FROM gestion__comprobantes_tipos WHERE empresa_id = ?", $destino) as $r) {
        $dest[strtoupper(trim((string) $r['codigo']))] = (int) $r['comprobante_tipo_id'];
    }
    $mapa = [];
    foreach (inicioLeerFilas($conexion, "SELECT comprobante_tipo_id, codigo FROM gestion__comprobantes_tipos WHERE empresa_id = ?", $origen) as $r) {
        $k = strtoupper(trim((string) $r['codigo']));
        if (isset($dest[$k])) {
            $mapa[(int) $r['comprobante_tipo_id']] = $dest[$k];
        }
    }
    return $mapa;
}

/** [perfil de empresa origen => destino]: por perfil base del mismo módulo y, si no tiene base, por nombre. */
function inicioMapaPerfiles(mysqli $conexion, int $origen, int $destino): array
{
    $sql = "SELECT empresa_perfil_id, modulo_id, perfil_id_base, empresa_perfil_nombre FROM conf__empresas_perfiles
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_id";
    $claves = function (array $r): array {
        $m = (int) $r['modulo_id'];
        $k = [];
        if (!empty($r['perfil_id_base'])) {
            $k[] = "B:$m:" . (int) $r['perfil_id_base'];
        }
        $k[] = "N:$m:" . mb_strtoupper(trim((string) $r['empresa_perfil_nombre']));
        return $k;
    };
    $dest = [];
    foreach (inicioLeerFilas($conexion, $sql, $destino) as $r) {
        foreach ($claves($r) as $k) {
            $dest[$k] = $dest[$k] ?? (int) $r['empresa_perfil_id'];
        }
    }
    $mapa = [];
    foreach (inicioLeerFilas($conexion, $sql, $origen) as $r) {
        foreach ($claves($r) as $k) {
            if (isset($dest[$k])) {
                $mapa[(int) $r['empresa_perfil_id']] = $dest[$k];
                break;
            }
        }
    }
    return $mapa;
}

/**
 * Sucursales, bocas de depósito y puntos de venta de la origen que la destino no tiene (sucursal por nombre, boca por
 * sucursal + código, PV por número fiscal o nombre). Lo que ya existe en la destino no se toca. Si la sucursal destino
 * ya tiene una boca principal, la copiada entra como no principal. Devuelve [detalle, registros creados].
 */
function inicioCopiarSucursalesBocasPv(mysqli $conexion, int $origen, int $destino, int $usuario_id): array
{
    $cantSuc = 0;
    $cantBoc = 0;
    $cantPv = 0;

    $mapaSuc = inicioMapaSucursales($conexion, $origen, $destino);
    $sucursales = inicioLeerFilas($conexion, "SELECT s.*, st.sucursal_tipo AS sucursal_tipo_nombre
        FROM gestion__sucursales s
        LEFT JOIN gestion__sucursales_tipos st ON st.sucursal_tipo_id = s.sucursal_tipo_id
        WHERE s.empresa_id = ? AND s.tabla_estado_registro_id = 1 ORDER BY s.sucursal_id", $origen);
    foreach ($sucursales as $s) {
        $id = (int) $s['sucursal_id'];
        if (isset($mapaSuc[$id])) {
            continue;
        }
        // Los tipos de sucursal son por empresa: se remapean por nombre
        $tipoDest = inicioSucursalTipoDestino($conexion, $destino, $s['sucursal_tipo_nombre'] ?? null);
        $locId = !empty($s['localidad_id']) ? (int) $s['localidad_id'] : null;
        $mapaSuc[$id] = inicioInsertar($conexion, "INSERT INTO gestion__sucursales
            (empresa_id, sucursal_tipo_id, sucursal_nombre, descripcion, localidad_id, direccion, telefono, email, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)", "iississs",
            [$destino, $tipoDest, $s['sucursal_nombre'], $s['descripcion'], $locId, $s['direccion'], $s['telefono'], $s['email']]);
        $cantSuc++;
    }

    $mapaBoc = inicioMapaBocas($conexion, $origen, $destino, $mapaSuc);
    $conPrincipal = [];
    foreach (inicioLeerFilas($conexion, "SELECT DISTINCT sucursal_id FROM gestion__bocas
                                         WHERE empresa_id = ? AND es_principal = 1 AND tabla_estado_registro_id = 1", $destino) as $r) {
        $conPrincipal[(int) $r['sucursal_id']] = true;
    }
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__bocas WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY boca_id", $origen) as $b) {
        $id = (int) $b['boca_id'];
        $sucDest = $mapaSuc[(int) $b['sucursal_id']] ?? 0;
        if (isset($mapaBoc[$id]) || $sucDest <= 0) {
            continue;
        }
        $principal = (int) $b['es_principal'] === 1 && empty($conPrincipal[$sucDest]) ? 1 : 0;
        if ($principal) {
            $conPrincipal[$sucDest] = true;
        }
        $locId = !empty($b['localidad_id']) ? (int) $b['localidad_id'] : null;
        $mapaBoc[$id] = inicioInsertar($conexion, "INSERT INTO gestion__bocas
            (empresa_id, sucursal_id, es_deposito, boca_nombre, codigo, descripcion, localidad_id, direccion,
             permite_ingresos, permite_egresos, permite_stock_negativo, es_principal, orden, tabla_estado_registro_id, f_alta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())", "iiisssisiiiii",
            [$destino, $sucDest, (int) $b['es_deposito'], $b['boca_nombre'], $b['codigo'], $b['descripcion'], $locId, $b['direccion'],
             (int) $b['permite_ingresos'], (int) $b['permite_egresos'], (int) $b['permite_stock_negativo'], $principal, (int) ($b['orden'] ?? 1)]);
        $cantBoc++;
    }

    $mapaPv = inicioMapaPuntosVenta($conexion, $origen, $destino);
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__puntos_venta WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY punto_venta_id", $origen) as $pv) {
        $id = (int) $pv['punto_venta_id'];
        $sucDest = $mapaSuc[(int) $pv['sucursal_id']] ?? 0;
        if (isset($mapaPv[$id]) || $sucDest <= 0) {
            continue;
        }
        $bocaDest = !empty($pv['boca_id']) ? ($mapaBoc[(int) $pv['boca_id']] ?? null) : null;
        $codFiscal = ($pv['codigo_fiscal'] !== null && $pv['codigo_fiscal'] !== '') ? (int) $pv['codigo_fiscal'] : null;
        $mapaPv[$id] = inicioInsertar($conexion, "INSERT INTO gestion__puntos_venta
            (empresa_id, sucursal_id, boca_id, nombre, descripcion, codigo_fiscal, sistema_emision, es_web, usuario_creacion_id, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)", "iiissisii",
            [$destino, $sucDest, $bocaDest, $pv['nombre'], $pv['descripcion'], $codFiscal, (string) ($pv['sistema_emision'] ?? '') !== '' ? $pv['sistema_emision'] : 'WSFE',(int) $pv['es_web'], $usuario_id]);
        $cantPv++;
    }

    return ["$cantSuc sucursales, $cantBoc bocas y $cantPv puntos de venta creados", $cantSuc + $cantBoc + $cantPv];
}

/**
 * Tipos de comprobante habilitados en cada punto de venta de la origen, en el PV y el tipo equivalentes de la destino
 * (los que no tienen equivalente se saltean). Solo agrega los que faltan. Devuelve cuántos creó.
 */
function inicioCopiarComprobantesPuntosVenta(mysqli $conexion, int $origen, int $destino): int
{
    $mapaPv = inicioMapaPuntosVenta($conexion, $origen, $destino);
    $mapaTipos = inicioMapaTiposComprobante($conexion, $origen, $destino);
    if (!$mapaPv || !$mapaTipos) {
        return 0;
    }
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__puntos_venta_comprobantes
        (empresa_id, punto_venta_id, comprobante_tipo_id, requiere_afip, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE punto_venta_comprobante_id = punto_venta_comprobante_id");
    $creados = 0;
    foreach (inicioLeerFilas($conexion, "SELECT punto_venta_id, comprobante_tipo_id, requiere_afip FROM gestion__puntos_venta_comprobantes
                                         WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $r) {
        $pv = $mapaPv[(int) $r['punto_venta_id']] ?? 0;
        $tipo = $mapaTipos[(int) $r['comprobante_tipo_id']] ?? 0;
        if ($pv <= 0 || $tipo <= 0) {
            continue;
        }
        $afip = (int) $r['requiere_afip'];
        mysqli_stmt_bind_param($stmt, "iiii", $destino, $pv, $tipo, $afip);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("No se pudieron copiar los comprobantes por punto de venta: " . mysqli_stmt_error($stmt));
        }
        if (mysqli_stmt_affected_rows($stmt) === 1) {
            $creados++;
        }
    }
    mysqli_stmt_close($stmt);
    return $creados;
}

/**
 * Accesos de los perfiles a sucursales / bocas / PV / tipos de comprobante (gestion__perfiles_sucursales) de la origen,
 * remapeados a la destino; una fila cuyo perfil o referencia no tiene equivalente se saltea. Después, todo perfil activo
 * de la destino que quedó sin ningún acceso recibe acceso a toda la empresa. Devuelve [detalle, registros creados].
 */
function inicioCopiarAccesosPerfiles(mysqli $conexion, int $origen, int $destino, int $usuario_id): array
{
    $mapaPerf = inicioMapaPerfiles($conexion, $origen, $destino);
    $mapaSuc = inicioMapaSucursales($conexion, $origen, $destino);
    $mapas = [
        'sucursal_id' => $mapaSuc,
        'boca_id' => inicioMapaBocas($conexion, $origen, $destino, $mapaSuc),
        'punto_venta_id' => inicioMapaPuntosVenta($conexion, $origen, $destino),
        'comprobante_tipo_id' => inicioMapaTiposComprobante($conexion, $origen, $destino),
    ];

    $copiados = 0;
    $omitidos = 0;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__perfiles_sucursales
        (empresa_id, empresa_perfil_id, sucursal_id, boca_id, punto_venta_id, comprobante_tipo_id, es_predeterminado,
         tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
        ON DUPLICATE KEY UPDATE perfil_sucursal_id = perfil_sucursal_id");
    foreach (inicioLeerFilas($conexion, "SELECT * FROM gestion__perfiles_sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1", $origen) as $r) {
        $perfil = $mapaPerf[(int) $r['empresa_perfil_id']] ?? 0;
        if ($perfil <= 0) {
            $omitidos++;
            continue;
        }
        $valores = [];
        foreach ($mapas as $col => $mapa) {
            if ($r[$col] === null) {
                $valores[$col] = null;
            } elseif (isset($mapa[(int) $r[$col]])) {
                $valores[$col] = $mapa[(int) $r[$col]];
            } else {
                $valores = null;
                break;
            }
        }
        if ($valores === null) {
            $omitidos++;
            continue;
        }
        $pred = (int) $r['es_predeterminado'];
        mysqli_stmt_bind_param($stmt, "iiiiiiii", $destino, $perfil, $valores['sucursal_id'], $valores['boca_id'],
            $valores['punto_venta_id'], $valores['comprobante_tipo_id'], $pred, $usuario_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("No se pudieron copiar los accesos por perfil: " . mysqli_stmt_error($stmt));
        }
        if (mysqli_stmt_affected_rows($stmt) === 1) {
            $copiados++;
        }
    }
    mysqli_stmt_close($stmt);

    // Perfiles sin ningún acceso: a toda la empresa (sin fila no ven ninguna sucursal)
    $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO gestion__perfiles_sucursales
        (empresa_id, empresa_perfil_id, sucursal_id, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
        SELECT ep.empresa_id, ep.empresa_perfil_id, NULL, 1, ?, NOW()
        FROM conf__empresas_perfiles ep
        WHERE ep.empresa_id = ? AND ep.tabla_estado_registro_id = 1
          AND NOT EXISTS (SELECT 1 FROM gestion__perfiles_sucursales x
                          WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.tabla_estado_registro_id = 1)");
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $destino);
    mysqli_stmt_execute($stmt);
    $globales = (int) mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    $detalle = "$copiados accesos copiados"
        . ($globales > 0 ? ", $globales perfiles sin accesos con acceso a toda la empresa" : '')
        . ($omitidos > 0 ? ", $omitidos omitidos por no tener perfil, sucursal, PV o tipo equivalente" : '');
    return [$detalle, $copiados + $globales];
}

/**
 * Tipo de operación y perfiles del módulo: la destino toma el tipo de operación de la origen; los perfiles activos del
 * módulo que la origen tiene y la destino no (por perfil base o nombre) se crean con las funciones que tienen asignadas
 * en la origen (sin las páginas que la destino no usa), y después se crean los perfiles predefinidos que todavía falten.
 * Los perfiles que la destino ya tiene no se tocan. Devuelve [detalle, registros creados].
 */
function inicioCopiarPerfiles(mysqli $conexion, int $origen, int $destino, int $modulo_id): array
{
    $partes = [];
    $total = 0;

    $tipoOrigen = empresa_tipo_operacion($conexion, $origen);
    if ($tipoOrigen !== empresa_tipo_operacion($conexion, $destino)) {
        empresa_guardar_tipo_operacion($conexion, $destino, $tipoOrigen);
        $partes[] = 'tipo de operación: ' . TIPOS_OPERACION[$tipoOrigen];
    }

    $mapa = inicioMapaPerfiles($conexion, $origen, $destino);
    $excluir = perfiles_sql_excluir_paginas($conexion, $destino, 'p');
    $stmtFun = mysqli_prepare($conexion, "INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
        SELECT ?, ?, epf.pagina_funcion_id, 1
        FROM conf__empresas_perfiles_funciones epf
        JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id AND pf.tabla_estado_registro_id = 1
        JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
        WHERE epf.empresa_perfil_id = ? AND epf.asignado = 1" . $excluir);
    $creados = 0;
    $funciones = 0;
    $sqlPerf = "SELECT * FROM conf__empresas_perfiles WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY empresa_perfil_id";
    foreach (inicioLeerFilas($conexion, $sqlPerf, $origen) as $p) {
        if ((int) $p['modulo_id'] !== $modulo_id || isset($mapa[(int) $p['empresa_perfil_id']])) {
            continue;
        }
        $base = !empty($p['perfil_id_base']) ? (int) $p['perfil_id_base'] : null;
        $nuevo = inicioInsertar($conexion, "INSERT INTO conf__empresas_perfiles
            (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id) VALUES (?, ?, ?, ?, 1)",
            "iiis", [$destino, $modulo_id, $base, $p['empresa_perfil_nombre']]);
        $origenPerfil = (int) $p['empresa_perfil_id'];
        mysqli_stmt_bind_param($stmtFun, "iii", $destino, $nuevo, $origenPerfil);
        mysqli_stmt_execute($stmtFun);
        $funciones += (int) mysqli_stmt_affected_rows($stmtFun);
        $creados++;
    }
    mysqli_stmt_close($stmtFun);
    if ($creados > 0) {
        $partes[] = "$creados perfiles copiados con $funciones funciones";
        $total += $creados + $funciones;
    }

    // Predefinidos del tipo de operación que la destino todavía no tiene
    $predef = 0;
    foreach (perfiles_base_para_empresa($conexion, $destino, $modulo_id) as $b) {
        if ($b['empresa_perfil_id'] === null) {
            $r = perfil_base_copiar_a_empresa($conexion, $destino, $modulo_id, $b['perfil_id'], false);
            $predef++;
            $total += 1 + $r['funciones'];
        }
    }
    if ($predef > 0) {
        $partes[] = "$predef perfiles predefinidos creados";
    }

    return [$partes ? implode(', ', $partes) : 'Sin cambios: la empresa ya tenía el tipo de operación y los perfiles', $total];
}

/**
 * Maestros de operación de la empresa: moneda base, alícuotas de IVA, tipo de asiento AUT (lo usan todos los
 * asientos automáticos), cajas con cuenta contable, medios de cobro y de pago (los de dinero, con cuenta de tesorería
 * o tipo de cuenta) y tipos de comprobante habilitados en cada punto de venta activo. Los primeros cinco los puede
 * traer la copia autorizada (componentes "Datos básicos" y "Tesorería"; los tipos por PV, "Sucursales" o "Tipos de comprobante"); las cuentas bancarias no.
 */
function inicioMaestrosOperacion($conexion, $empresa_id) {
    $e = intval($empresa_id);
    $uno = function ($sql) use ($conexion, $e) {
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $e);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: [];
    };
    $base = $uno("SELECT codigo, moneda FROM gestion__monedas WHERE empresa_id = ? AND es_moneda_base = 1 AND tabla_estado_registro_id = 1 LIMIT 1");
    $iva = $uno("SELECT COUNT(*) total, SUM(es_gravado = 1 AND porcentaje > 0) gravadas FROM gestion__impuestos__iva_alicuotas
                 WHERE empresa_id = ? AND tabla_estado_registro_id = 1");
    $aut = $uno("SELECT COUNT(*) n FROM gestion__cont_tipos_asientos WHERE empresa_id = ? AND codigo = 'AUT' AND tabla_estado_registro_id = 1");
    $tes = $uno("SELECT COUNT(*) total, SUM(cuenta_tipo = 'CAJA') cajas, SUM(cuenta_tipo = 'BANCO') bancos, SUM(cuenta_tipo = 'VALORES') valores,
                        SUM(cuenta_tipo = 'CAJA' AND IFNULL(cont_cuenta_id, 0) = 0) cajas_sin_cuenta
                 FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND tabla_estado_registro_id = 1");
    // Un medio de dinero necesita a dónde van los fondos (tipo de cuenta o cuenta fija); una retención, su cuenta contable
    $med = $uno("SELECT SUM(aplica_cobro = 1) cobro, SUM(aplica_pago = 1) pago,
                        SUM(medio_pago_tipo = 'RETENCION' AND aplica_cobro = 1) retenciones,
                        SUM(CASE WHEN medio_pago_tipo = 'RETENCION' THEN aplica_cobro = 1 AND IFNULL(cont_cuenta_id, 0) = 0
                                 ELSE IFNULL(tesoreria_cuenta_tipo, '') = '' AND IFNULL(tesoreria_cuenta_id, 0) = 0 END) sin_cuenta
                 FROM gestion__medios_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1");
    $pv = $uno("SELECT COUNT(*) n FROM gestion__puntos_venta pv WHERE pv.empresa_id = ? AND pv.tabla_estado_registro_id = 1
                AND NOT EXISTS (SELECT 1 FROM gestion__puntos_venta_comprobantes pvc WHERE pvc.punto_venta_id = pv.punto_venta_id)");
    return [
        'moneda_base' => $base ? $base['codigo'] . ' - ' . $base['moneda'] : null,
        'iva_alicuotas' => intval($iva['total'] ?? 0),
        'iva_gravadas' => intval($iva['gravadas'] ?? 0),
        'tipo_asiento_aut' => intval($aut['n'] ?? 0) > 0,
        'tesoreria_cuentas' => intval($tes['total'] ?? 0),
        'cajas' => intval($tes['cajas'] ?? 0),
        'bancos' => intval($tes['bancos'] ?? 0),
        'valores' => intval($tes['valores'] ?? 0),
        'cajas_sin_cuenta' => intval($tes['cajas_sin_cuenta'] ?? 0),
        'medios_cobro' => intval($med['cobro'] ?? 0),
        'medios_pago' => intval($med['pago'] ?? 0),
        'medios_retencion' => intval($med['retenciones'] ?? 0),
        'medios_sin_cuenta' => intval($med['sin_cuenta'] ?? 0),
        'pv_sin_comprobantes' => intval($pv['n'] ?? 0),
    ];
}

/**
 * Obtiene las empresas que han otorgado autorización activa a esta empresa destino
 */
function inicioObtenerEmpresasAutorizadas($conexion, $empresa_destino_id) {
    $empresa_destino_id = intval($empresa_destino_id);
    $sql = "SELECT a.*, 
                   e.empresa as empresa_origen_nombre,
                   e.documento_numero as empresa_origen_cuit,
                   e.domicilio as empresa_origen_domicilio,
                   e.email as empresa_origen_email
            FROM gestion__empresas_autorizaciones_copia a
            JOIN conf__empresas e ON a.empresa_origen_id = e.empresa_id
            WHERE a.empresa_destino_id = ?
              AND a.tabla_estado_registro_id = 1
              AND a.fecha_desde <= CURDATE()
              AND (a.fecha_hasta IS NULL OR a.fecha_hasta >= CURDATE())
            ORDER BY e.empresa ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_destino_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $empresas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $empresas[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $empresas;
}

/**
 * Previsualiza el contenido a copiar desde una empresa autorizada
 */
function inicioPrevisualizarCopia($conexion, $empresa_origen_id, $empresa_destino_id, $modulo_id = 5) {
    $empresa_origen_id = intval($empresa_origen_id);
    $empresa_destino_id = intval($empresa_destino_id);

    // Verificar autorización
    $sqlAut = "SELECT * FROM gestion__empresas_autorizaciones_copia
               WHERE empresa_origen_id = ? AND empresa_destino_id = ?
                 AND tabla_estado_registro_id = 1
                 AND fecha_desde <= CURDATE()
                 AND (fecha_hasta IS NULL OR fecha_hasta >= CURDATE())";
    $stmtAut = mysqli_prepare($conexion, $sqlAut);
    mysqli_stmt_bind_param($stmtAut, "ii", $empresa_origen_id, $empresa_destino_id);
    mysqli_stmt_execute($stmtAut);
    $resAut = mysqli_stmt_get_result($stmtAut);
    $aut = mysqli_fetch_assoc($resAut);
    mysqli_stmt_close($stmtAut);

    if (!$aut) {
        return ['success' => false, 'error' => 'No existe una autorización activa de la empresa seleccionada'];
    }

    $prev = [
        'autorizacion' => $aut,
        'detalles' => []
    ];

    // 1. Plan de cuentas
    if ($aut['permite_plan_cuentas']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__cont_cuentas WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__cont_cuentas WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['plan_cuentas'] = [
            'nombre' => 'Plan de Cuentas Contables',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-sitemap'
        ];
    }

    // 2. Conceptos contables
    if ($aut['permite_conceptos_contables']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['conceptos_contables'] = [
            'nombre' => 'Conceptos Contables Operativos',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-tags'
        ];
    }

    // 3. Roles de entidades
    if ($aut['permite_roles_cuentas']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_roles_cuentas WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_roles_cuentas WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['roles_cuentas'] = [
            'nombre' => 'Cuentas Contables por Rol de Entidad',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-users-cog'
        ];
    }

    // 4. Tipos de comprobantes
    if ($aut['permite_comprobantes_tipos']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__comprobantes_tipos WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__comprobantes_tipos WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['comprobantes_tipos'] = [
            'nombre' => 'Tipos de Comprobantes',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-file-invoice'
        ];
    }

    // 5. Condiciones de pago
    if ($aut['permite_condiciones_pago']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__condiciones_pago WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__condiciones_pago WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['condiciones_pago'] = [
            'nombre' => 'Condiciones de Pago',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-credit-card'
        ];
    }

    // 6. Sucursales, bocas y puntos de venta (+ comprobantes habilitados por PV)
    if ($aut['permite_sucursales_bocas']) {
        $sqlSuc = "SELECT COUNT(*) c FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                   UNION ALL SELECT COUNT(*) FROM gestion__bocas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                   UNION ALL SELECT COUNT(*) FROM gestion__puntos_venta WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
        $prev['detalles']['sucursales_bocas'] = [
            'nombre' => 'Sucursales, Bocas de Depósito y Puntos de Venta',
            'origen_total' => inicioContarCatalogos($conexion, $sqlSuc, $empresa_origen_id, 3),
            'destino_actual' => inicioContarCatalogos($conexion, $sqlSuc, $empresa_destino_id, 3),
            'icono' => 'fas fa-store'
        ];
    }
    if ($aut['permite_sucursales_bocas'] || $aut['permite_comprobantes_tipos']) {
        $sqlPvc = "SELECT COUNT(*) c FROM gestion__puntos_venta_comprobantes WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
        $prev['detalles']['pv_comprobantes'] = [
            'nombre' => 'Comprobantes por Punto de Venta',
            'origen_total' => inicioContarCatalogos($conexion, $sqlPvc, $empresa_origen_id, 1),
            'destino_actual' => inicioContarCatalogos($conexion, $sqlPvc, $empresa_destino_id, 1),
            'icono' => 'fas fa-receipt'
        ];
    }

    // 0. Tipo de operación y perfiles del módulo, y accesos por perfil (siempre se ofrecen)
    $sqlPerfiles = "SELECT COUNT(*) c FROM conf__empresas_perfiles WHERE empresa_id = ? AND modulo_id = " . intval($modulo_id) . " AND tabla_estado_registro_id = 1";
    $prev['detalles']['perfiles'] = [
        'nombre' => 'Tipo de Operación (' . TIPOS_OPERACION[empresa_tipo_operacion($conexion, $empresa_origen_id)] . ') y Perfiles de Usuario',
        'origen_total' => inicioContarCatalogos($conexion, $sqlPerfiles, $empresa_origen_id, 1),
        'destino_actual' => inicioContarCatalogos($conexion, $sqlPerfiles, $empresa_destino_id, 1),
        'icono' => 'fas fa-user-shield'
    ];
    $sqlAccesos = "SELECT COUNT(*) c FROM gestion__perfiles_sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $prev['detalles']['accesos_perfiles'] = [
        'nombre' => 'Accesos a Sucursales y Comprobantes por Perfil',
        'origen_total' => inicioContarCatalogos($conexion, $sqlAccesos, $empresa_origen_id, 1),
        'destino_actual' => inicioContarCatalogos($conexion, $sqlAccesos, $empresa_destino_id, 1),
        'icono' => 'fas fa-key'
    ];

    // 7. Listas de Precios
    if ($aut['permite_listas_precios']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__listas_precios WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__listas_precios WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['listas_precios'] = [
            'nombre' => 'Listas de Precios Base',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-list-ol'
        ];
    }

    // 9 y 10. Datos básicos y tesorería: varios catálogos, se cuentan juntos
    if (!empty($aut['permite_datos_basicos'])) {
        $sqlBasicos = "SELECT COUNT(*) c FROM gestion__monedas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       UNION ALL SELECT COUNT(*) FROM gestion__cont_tipos_asientos WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       UNION ALL SELECT COUNT(*) FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       UNION ALL SELECT COUNT(*) FROM gestion__productos_tipos WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       UNION ALL SELECT COUNT(*) FROM gestion__productos_categorias WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                       UNION ALL SELECT COUNT(*) FROM gestion__categorias_gasto WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
        $prev['detalles']['datos_basicos'] = [
            'nombre' => 'Datos Básicos (monedas, tipos de asiento, IVA, tipos y categorías de producto, categorías de gasto)',
            'origen_total' => inicioContarCatalogos($conexion, $sqlBasicos, $empresa_origen_id, 6),
            'destino_actual' => inicioContarCatalogos($conexion, $sqlBasicos, $empresa_destino_id, 6),
            'icono' => 'fas fa-database'
        ];
    }
    if (!empty($aut['permite_tesoreria'])) {
        $sqlTes = "SELECT COUNT(*) c FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND cuenta_tipo <> 'BANCO'
                   UNION ALL SELECT COUNT(*) FROM gestion__medios_pago WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
        $prev['detalles']['tesoreria'] = [
            'nombre' => 'Tesorería (cajas, valores y cupones sin saldo; medios de cobro y pago)',
            'origen_total' => inicioContarCatalogos($conexion, $sqlTes, $empresa_origen_id, 2),
            'destino_actual' => inicioContarCatalogos($conexion, $sqlTes, $empresa_destino_id, 2),
            'icono' => 'fas fa-cash-register'
        ];
    }

    // 8. Parámetros
    if ($aut['permite_parametros']) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_parametros WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_parametros WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['parametros'] = [
            'nombre' => 'Parámetros Generales de Empresa',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-sliders-h'
        ];
    }

    // 11. Configuración de Impuestos (IIBB, Percepciones, Retenciones)
    if (!empty($aut['permite_impuestos_config'])) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_impuestos_config WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__empresas_impuestos_config WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['impuestos_config'] = [
            'nombre' => 'Configuración de Impuestos (IIBB, Percepciones, Retenciones)',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-percent'
        ];
    }

    // 12. Catálogo de Productos y Servicios
    if (!empty($aut['permite_productos'])) {
        $rOrig = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__productos WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
        $rDest = mysqli_query($conexion, "SELECT count(*) as c FROM gestion__productos WHERE empresa_id = $empresa_destino_id AND tabla_estado_registro_id = 1");
        $prev['detalles']['productos'] = [
            'nombre' => 'Catálogo de Productos y Servicios',
            'origen_total' => (int)mysqli_fetch_assoc($rOrig)['c'],
            'destino_actual' => (int)mysqli_fetch_assoc($rDest)['c'],
            'icono' => 'fas fa-boxes'
        ];
    }

    return ['success' => true, 'data' => $prev];
}

/**
 * Ejecuta la copia transaccional de los datos iniciales autorizados
 */
function inicioEjecutarCopia($conexion, $empresa_origen_id, $empresa_destino_id, $componentesSolicitados, $usuario_id, $modulo_id = 5) {
    $empresa_origen_id = intval($empresa_origen_id);
    $empresa_destino_id = intval($empresa_destino_id);

    if ($empresa_origen_id <= 0 || $empresa_destino_id <= 0 || $empresa_origen_id === $empresa_destino_id) {
        return ['success' => false, 'error' => 'Empresas no válidas para la copia'];
    }

    // 1. Validar autorización vigente
    $sqlAut = "SELECT * FROM gestion__empresas_autorizaciones_copia
               WHERE empresa_origen_id = ? AND empresa_destino_id = ?
                 AND tabla_estado_registro_id = 1
                 AND fecha_desde <= CURDATE()
                 AND (fecha_hasta IS NULL OR fecha_hasta >= CURDATE())";
    $stmtAut = mysqli_prepare($conexion, $sqlAut);
    mysqli_stmt_bind_param($stmtAut, "ii", $empresa_origen_id, $empresa_destino_id);
    mysqli_stmt_execute($stmtAut);
    $resAut = mysqli_stmt_get_result($stmtAut);
    $aut = mysqli_fetch_assoc($resAut);
    mysqli_stmt_close($stmtAut);

    if (!$aut) {
        return ['success' => false, 'error' => 'La empresa origen no ha autorizado a esta empresa o la autorización está vencida'];
    }

    $autorizacion_id = intval($aut['autorizacion_id']);
    $resultadoDetalle = [];
    $totalRegistrosCreados = 0;

    mysqli_begin_transaction($conexion);

    try {
        // MAPEO DE CUENTAS: [old_cont_cuenta_id => new_cont_cuenta_id]
        $mapaCuentas = [];

        // -------------------------------------------------------------
        // 0. TIPO DE OPERACIÓN Y PERFILES DEL MÓDULO (no requiere autorización: no expone datos contables)
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['perfiles'])) {
            [$detallePerf, $cantPerf] = inicioCopiarPerfiles($conexion, $empresa_origen_id, $empresa_destino_id, (int) $modulo_id);
            $resultadoDetalle['Tipo de Operación y Perfiles'] = $detallePerf;
            $totalRegistrosCreados += $cantPerf;
        }

        // -------------------------------------------------------------
        // A. COPIAR PLAN DE CUENTAS CONTABLES
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['plan_cuentas']) && $aut['permite_plan_cuentas']) {
            $cantCuentas = 0;
            // 1) Leer todas las cuentas de la empresa destino actuales para mapear las que ya existan por código
            $destCuentasRes = mysqli_query($conexion, "SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = $empresa_destino_id");
            $destMapPorCodigo = [];
            while ($cr = mysqli_fetch_assoc($destCuentasRes)) {
                $destMapPorCodigo[$cr['codigo']] = (int)$cr['cont_cuenta_id'];
            }

            // 2) Leer cuentas de origen ordenadas por nivel, orden y ID
            $sqlSrc = "SELECT * FROM gestion__cont_cuentas WHERE empresa_id = $empresa_origen_id ORDER BY nivel ASC, orden ASC, cont_cuenta_id ASC";
            $resSrc = mysqli_query($conexion, $sqlSrc);

            while ($c = mysqli_fetch_assoc($resSrc)) {
                $oldId = (int)$c['cont_cuenta_id'];
                $codigo = $c['codigo'];

                if (isset($destMapPorCodigo[$codigo])) {
                    // Ya existe en destino
                    $mapaCuentas[$oldId] = $destMapPorCodigo[$codigo];
                } else {
                    // Mapear cuenta padre si tiene
                    $newPadreId = null;
                    if (!empty($c['cuenta_padre_id']) && isset($mapaCuentas[(int)$c['cuenta_padre_id']])) {
                        $newPadreId = $mapaCuentas[(int)$c['cuenta_padre_id']];
                    }

                    $padreSqlVal = $newPadreId !== null ? $newPadreId : "NULL";
                    $nomEsc = mysqli_real_escape_string($conexion, $c['nombre']);
                    $natEsc = mysqli_real_escape_string($conexion, $c['naturaleza']);
                    $codEsc = mysqli_real_escape_string($conexion, $c['codigo']);

                    $sqlIns = "INSERT INTO gestion__cont_cuentas (
                        empresa_id, comprobante_id, codigo, nombre, naturaleza,
                        cuenta_padre_id, nivel, orden, es_imputable, es_caja_banco,
                        tabla_estado_registro_id
                    ) VALUES (
                        $empresa_destino_id, 0, '$codEsc', '$nomEsc', '$natEsc',
                        $padreSqlVal, {$c['nivel']}, {$c['orden']}, {$c['es_imputable']}, {$c['es_caja_banco']},
                        1
                    )";
                    mysqli_query($conexion, $sqlIns);
                    $newId = mysqli_insert_id($conexion);
                    $mapaCuentas[$oldId] = $newId;
                    $destMapPorCodigo[$codigo] = $newId;
                    $cantCuentas++;
                }
            }
            $resultadoDetalle['Plan de Cuentas'] = "$cantCuentas cuentas creadas";
            $totalRegistrosCreados += $cantCuentas;
        }

        // Si no se copió el plan en este paso, cargar mapa de equivalencias por código para conceptos y roles
        if (empty($mapaCuentas)) {
            $destCuentasRes = mysqli_query($conexion, "SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = $empresa_destino_id");
            $destPorCod = [];
            while ($cr = mysqli_fetch_assoc($destCuentasRes)) {
                $destPorCod[$cr['codigo']] = (int)$cr['cont_cuenta_id'];
            }
            $srcCuentasRes = mysqli_query($conexion, "SELECT cont_cuenta_id, codigo FROM gestion__cont_cuentas WHERE empresa_id = $empresa_origen_id");
            while ($sr = mysqli_fetch_assoc($srcCuentasRes)) {
                if (isset($destPorCod[$sr['codigo']])) {
                    $mapaCuentas[(int)$sr['cont_cuenta_id']] = $destPorCod[$sr['codigo']];
                }
            }
        }

        // -------------------------------------------------------------
        // A2. DATOS BÁSICOS (antes de tipos de comprobante y listas: usan tipos de asiento y monedas)
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['datos_basicos']) && !empty($aut['permite_datos_basicos'])) {
            [$detalleBasicos, $cantBasicos] = inicioCopiarDatosBasicos($conexion, $empresa_origen_id, $empresa_destino_id, $mapaCuentas);
            $resultadoDetalle['Datos Básicos'] = $detalleBasicos;
            $totalRegistrosCreados += $cantBasicos;
        }

        // -------------------------------------------------------------
        // B. COPIAR CONCEPTOS CONTABLES DE LA EMPRESA
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['conceptos_contables']) && $aut['permite_conceptos_contables']) {
            $cantConceptos = 0;
            $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
            while ($row = mysqli_fetch_assoc($resSrc)) {
                $concepto = mysqli_real_escape_string($conexion, $row['concepto']);
                $newCuentaId = "NULL";
                if (!empty($row['cont_cuenta_id']) && isset($mapaCuentas[(int)$row['cont_cuenta_id']])) {
                    $newCuentaId = $mapaCuentas[(int)$row['cont_cuenta_id']];
                }

                $sqlIns = "INSERT INTO gestion__empresas_cuentas_conceptos (
                    empresa_id, concepto, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion, usuario_id
                ) VALUES (
                    $empresa_destino_id, '$concepto', $newCuentaId, 1, NOW(), $usuario_id
                ) ON DUPLICATE KEY UPDATE cont_cuenta_id = VALUES(cont_cuenta_id)";
                mysqli_query($conexion, $sqlIns);
                $cantConceptos++;
            }
            $resultadoDetalle['Conceptos Contables'] = "$cantConceptos conceptos mapeados";
            $totalRegistrosCreados += $cantConceptos;
        }

        // -------------------------------------------------------------
        // C. COPIAR CUENTAS CONTABLES POR ROL DE ENTIDAD
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['roles_cuentas']) && $aut['permite_roles_cuentas']) {
            $cantRoles = 0;
            $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__empresas_roles_cuentas WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
            while ($row = mysqli_fetch_assoc($resSrc)) {
                $rolId = (int)$row['rol_entidad_id'];
                $newCuentaId = null;
                if (!empty($row['cont_cuenta_id']) && isset($mapaCuentas[(int)$row['cont_cuenta_id']])) {
                    $newCuentaId = $mapaCuentas[(int)$row['cont_cuenta_id']];
                }

                if ($newCuentaId !== null) {
                    $sqlIns = "INSERT INTO gestion__empresas_roles_cuentas (
                        empresa_id, rol_entidad_id, cont_cuenta_id, tabla_estado_registro_id, fecha_creacion
                    ) VALUES (
                        $empresa_destino_id, $rolId, $newCuentaId, 1, NOW()
                    ) ON DUPLICATE KEY UPDATE cont_cuenta_id = VALUES(cont_cuenta_id)";
                    mysqli_query($conexion, $sqlIns);
                    $cantRoles++;
                }
            }
            $resultadoDetalle['Roles y Cuentas'] = "$cantRoles roles configurados";
            $totalRegistrosCreados += $cantRoles;
        }

        // -------------------------------------------------------------
        // D. COPIAR TIPOS DE COMPROBANTES
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['comprobantes_tipos']) && $aut['permite_comprobantes_tipos']) {
            $cantComprobantes = 0;
            $cantOmitidos = 0;
            // Grupos y subgrupos son por empresa: remapear (y crear los que falten) antes de los tipos
            [$mapaSubgrupos, $mapaGrupos, $cantEstructura] = inicioMapearSubgruposComprobante($conexion, $empresa_origen_id, $empresa_destino_id);
            // Tipos existentes en destino por código
            $chkDest = mysqli_query($conexion, "SELECT codigo FROM gestion__comprobantes_tipos WHERE empresa_id = $empresa_destino_id");
            $codigosExistentes = [];
            while ($cr = mysqli_fetch_assoc($chkDest)) {
                $codigosExistentes[$cr['codigo']] = true;
            }

            $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__comprobantes_tipos WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
            while ($c = mysqli_fetch_assoc($resSrc)) {
                if (!isset($codigosExistentes[$c['codigo']])) {
                    $grupoDest = $mapaGrupos[(int) $c['comprobante_grupo_id']] ?? 0;
                    $subgrupoDest = $mapaSubgrupos[(int) $c['comprobante_subgrupo_id']] ?? 0;
                    if ($grupoDest <= 0 || $subgrupoDest <= 0) {
                        $cantOmitidos++;
                        continue;
                    }
                    $cTipo = mysqli_real_escape_string($conexion, $c['comprobante_tipo']);
                    $cCod = mysqli_real_escape_string($conexion, $c['codigo']);
                    $cLetra = !empty($c['letra']) ? "'" . mysqli_real_escape_string($conexion, $c['letra']) . "'" : "NULL";
                    $cSigno = mysqli_real_escape_string($conexion, $c['signo']);
                    $stockSigno = !empty($c['stock_signo']) ? "'" . mysqli_real_escape_string($conexion, $c['stock_signo']) . "'" : "NULL";
                    $cComentario = !empty($c['comentario']) ? "'" . mysqli_real_escape_string($conexion, $c['comentario']) . "'" : "NULL";

                    $sqlIns = "INSERT INTO gestion__comprobantes_tipos (
                        empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id,
                        impacta_stock, stock_signo, impacta_contabilidad, impacta_ctacte,
                        requiere_debito_cuenta, comprobante_tipo, codigo, letra, signo,
                        comentario, orden, tabla_estado_registro_id
                    ) VALUES (
                        $empresa_destino_id, $grupoDest, $subgrupoDest, " . inicioSqlNum($c['comprobante_fiscal_id']) . ",
                        " . inicioSqlNum($c['impacta_stock']) . ", $stockSigno, " . inicioSqlNum($c['impacta_contabilidad']) . ", " . inicioSqlNum($c['impacta_ctacte']) . ",
                        " . inicioSqlNum($c['requiere_debito_cuenta']) . ", '$cTipo', '$cCod', $cLetra, '$cSigno',
                        $cComentario, " . inicioSqlNum($c['orden']) . ", 1
                    )";
                    mysqli_query($conexion, $sqlIns);
                    $codigosExistentes[$c['codigo']] = true;
                    $cantComprobantes++;
                }
            }
            // Relaciones entre tipos (qué cancela a qué y de qué comprobante padre depende cada uno), remapeadas por código
            $cantRelaciones = inicioCopiarRelacionesTipos($conexion, $empresa_origen_id, $empresa_destino_id);
            $resultadoDetalle['Tipos de Comprobantes'] = "$cantComprobantes tipos dados de alta"
                . ($cantEstructura > 0 ? " ($cantEstructura grupos/subgrupos creados)" : '')
                . ($cantOmitidos > 0 ? ", $cantOmitidos omitidos por no poder remapear su grupo/subgrupo" : '')
                . ($cantRelaciones > 0 ? ", $cantRelaciones relaciones de cancelación / dependencia" : '');
            $totalRegistrosCreados += $cantComprobantes + $cantEstructura + $cantRelaciones;
        }

        // -------------------------------------------------------------
        // E. COPIAR CONDICIONES DE PAGO
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['condiciones_pago']) && $aut['permite_condiciones_pago']) {
            $cantCond = 0;
            $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__condiciones_pago WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
            while ($cp = mysqli_fetch_assoc($resSrc)) {
                $codigo = mysqli_real_escape_string($conexion, $cp['codigo']);
                $nombre = mysqli_real_escape_string($conexion, $cp['condicion_pago']);
                $interes = !empty($cp['interes_mensual']) ? $cp['interes_mensual'] : "NULL";

                $sqlIns = "INSERT INTO gestion__condiciones_pago (
                    empresa_id, codigo, condicion_pago, tipo, cantidad_cuotas,
                    dias_primer_vencimiento, dias_entre_cuotas, porcentaje_anticipo,
                    aplica_interes, interes_mensual, permite_modificar, orden, tabla_estado_registro_id
                ) VALUES (
                    $empresa_destino_id, '$codigo', '$nombre', '{$cp['tipo']}', {$cp['cantidad_cuotas']},
                    {$cp['dias_primer_vencimiento']}, {$cp['dias_entre_cuotas']}, {$cp['porcentaje_anticipo']},
                    {$cp['aplica_interes']}, $interes, {$cp['permite_modificar']}, {$cp['orden']}, 1
                ) ON DUPLICATE KEY UPDATE condicion_pago = VALUES(condicion_pago)";
                mysqli_query($conexion, $sqlIns);
                $cantCond++;
            }
            $resultadoDetalle['Condiciones de Pago'] = "$cantCond condiciones copiadas";
            $totalRegistrosCreados += $cantCond;
        }

        // -------------------------------------------------------------
        // F. COPIAR SUCURSALES, BOCAS Y PUNTOS DE VENTA (los que falten, por nombre / código / número fiscal)
        // -------------------------------------------------------------
        $copiaSucursales = !empty($componentesSolicitados['sucursales_bocas']) && $aut['permite_sucursales_bocas'];
        if ($copiaSucursales) {
            [$detalleSuc, $cantSuc] = inicioCopiarSucursalesBocasPv($conexion, $empresa_origen_id, $empresa_destino_id, (int) $usuario_id);
            $resultadoDetalle['Sucursales, Bocas y Puntos de Venta'] = $detalleSuc;
            $totalRegistrosCreados += $cantSuc;
        }

        // -------------------------------------------------------------
        // F1. COMPROBANTES HABILITADOS POR PUNTO DE VENTA (después de tipos y de puntos de venta)
        // -------------------------------------------------------------
        $copiaTipos = !empty($componentesSolicitados['comprobantes_tipos']) && $aut['permite_comprobantes_tipos'];
        if ($copiaSucursales || $copiaTipos) {
            $cantPvc = inicioCopiarComprobantesPuntosVenta($conexion, $empresa_origen_id, $empresa_destino_id);
            $resultadoDetalle['Comprobantes por Punto de Venta'] = "$cantPvc tipos habilitados en puntos de venta";
            $totalRegistrosCreados += $cantPvc;
        }

        // -------------------------------------------------------------
        // F2. TESORERÍA (después de sucursales: las cajas se ubican por nombre de sucursal)
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['tesoreria']) && !empty($aut['permite_tesoreria'])) {
            [$detalleTes, $cantTes] = inicioCopiarTesoreria($conexion, $empresa_origen_id, $empresa_destino_id, $mapaCuentas, (int) $usuario_id);
            $resultadoDetalle['Tesorería'] = $detalleTes;
            $totalRegistrosCreados += $cantTes;
        }

        // -------------------------------------------------------------
        // G. COPIAR LISTAS DE PRECIOS BASE
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['listas_precios']) && $aut['permite_listas_precios']) {
            $cantListas = 0;
            $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__listas_precios WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1");
            while ($lp = mysqli_fetch_assoc($resSrc)) {
                $codigo = mysqli_real_escape_string($conexion, $lp['lista_precio_codigo']);
                $nombre = mysqli_real_escape_string($conexion, $lp['lista_precio_nombre']);
                $desc = mysqli_real_escape_string($conexion, $lp['descripcion'] ?? '');
                // Las monedas son por empresa: se busca la de igual código en la destino
                $monedaDest = inicioMonedaDestino($conexion, $lp['moneda_id'] ?? null, $empresa_destino_id);
                $monedaId = $monedaDest !== null ? $monedaDest : "NULL";

                $sqlIns = "INSERT INTO gestion__listas_precios (
                    empresa_id, lista_precio_codigo, lista_precio_nombre, descripcion,
                    lista_precio_origen_id, lista_base_id, moneda_id, requiere_recalculo,
                    tabla_estado_registro_id, creado_por, creado_en
                ) VALUES (
                    $empresa_destino_id, '$codigo', '$nombre', '$desc',
                    {$lp['lista_precio_origen_id']}, NULL, $monedaId, 0,
                    1, $usuario_id, NOW()
                ) ON DUPLICATE KEY UPDATE lista_precio_nombre = VALUES(lista_precio_nombre)";
                mysqli_query($conexion, $sqlIns);
                $cantListas++;
            }
            $resultadoDetalle['Listas de Precios'] = "$cantListas listas de precios clonadas";
            $totalRegistrosCreados += $cantListas;
        }

        // -------------------------------------------------------------
        // H. COPIAR PARÁMETROS GENERALES
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['parametros']) && $aut['permite_parametros']) {
            $chkParam = mysqli_query($conexion, "SELECT empresa_parametro_id FROM gestion__empresas_parametros WHERE empresa_id = $empresa_destino_id LIMIT 1");
            if (!mysqli_fetch_assoc($chkParam)) {
                $resSrc = mysqli_query($conexion, "SELECT * FROM gestion__empresas_parametros WHERE empresa_id = $empresa_origen_id AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1");
                // La condición fiscal es un dato de la empresa destino (conf__empresas), no se copia de la origen
                $resCf = mysqli_query($conexion, "SELECT condicion_fiscal_id FROM conf__empresas WHERE empresa_id = $empresa_destino_id");
                $condFiscalDest = (int) (mysqli_fetch_assoc($resCf)['condicion_fiscal_id'] ?? 0);
                $p = $condFiscalDest > 0 ? mysqli_fetch_assoc($resSrc) : null;
                if ($condFiscalDest <= 0) {
                    $resultadoDetalle['Parámetros'] = "No copiados: la empresa no tiene condición fiscal cargada en sus datos";
                }
                if ($p) {
                    $leyenda = mysqli_real_escape_string($conexion, $p['leyenda_fiscal'] ?? '');
                    $obs = mysqli_real_escape_string($conexion, $p['observaciones'] ?? '');

                    // Defaults comerciales remapeados: PV por número fiscal / nombre, lista y condición de pago por código
                    $pvDef = null;
                    if (!empty($p['punto_venta_defecto_id'])) {
                        $pvDef = inicioMapaPuntosVenta($conexion, $empresa_origen_id, $empresa_destino_id)[(int) $p['punto_venta_defecto_id']] ?? null;
                    }
                    $listaDef = null;
                    if (!empty($p['lista_precio_defecto_id'])) {
                        $stmtL = mysqli_prepare($conexion, "SELECT d.lista_precio_id FROM gestion__listas_precios o
                            JOIN gestion__listas_precios d ON d.empresa_id = ? AND d.lista_precio_codigo = o.lista_precio_codigo AND d.tabla_estado_registro_id = 1
                            WHERE o.lista_precio_id = ? LIMIT 1");
                        $lOrig = (int) $p['lista_precio_defecto_id'];
                        mysqli_stmt_bind_param($stmtL, "ii", $empresa_destino_id, $lOrig);
                        mysqli_stmt_execute($stmtL);
                        $fl = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtL));
                        $listaDef = $fl ? (int) $fl['lista_precio_id'] : null;
                        mysqli_stmt_close($stmtL);
                    }
                    $condDef = null;
                    if (!empty($p['condicion_pago_defecto_id'])) {
                        $stmtC = mysqli_prepare($conexion, "SELECT d.condicion_pago_id FROM gestion__condiciones_pago o
                            JOIN gestion__condiciones_pago d ON d.empresa_id = ? AND d.codigo = o.codigo AND d.tabla_estado_registro_id = 1
                            WHERE o.condicion_pago_id = ? LIMIT 1");
                        $cOrig = (int) $p['condicion_pago_defecto_id'];
                        mysqli_stmt_bind_param($stmtC, "ii", $empresa_destino_id, $cOrig);
                        mysqli_stmt_execute($stmtC);
                        $fc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
                        $condDef = $fc ? (int) $fc['condicion_pago_id'] : null;
                        mysqli_stmt_close($stmtC);
                    }

                    $sqlIns = "INSERT INTO gestion__empresas_parametros (
                        empresa_id, condicion_fiscal_id, punto_venta_defecto_id, lista_precio_defecto_id, condicion_pago_defecto_id,
                        arca_habilitado, compra_actualiza_lista_proveedor,
                        compra_actualiza_listas_venta, f_desde, leyenda_fiscal, observaciones,
                        usuario_id, tabla_estado_registro_id, created_at
                    ) VALUES (
                        $empresa_destino_id, $condFiscalDest, " . inicioSqlNum($pvDef) . ", " . inicioSqlNum($listaDef) . ", " . inicioSqlNum($condDef) . ", 0,
                        " . inicioSqlNum($p['compra_actualiza_lista_proveedor']) . ", " . inicioSqlNum($p['compra_actualiza_listas_venta']) . ",
                        CURDATE(), '$leyenda', '$obs', $usuario_id, 1, NOW()
                    )";
                    mysqli_query($conexion, $sqlIns);
                    $resultadoDetalle['Parámetros'] = "Parámetros iniciales configurados";
                    $totalRegistrosCreados++;
                }
            }
        }

        // -------------------------------------------------------------
        // H2. COPIAR CONFIGURACIÓN DE IMPUESTOS (IIBB, Percepciones, Retenciones)
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['impuestos_config']) && !empty($aut['permite_impuestos_config'])) {
            [$detalleImp, $cantImp] = inicioCopiarImpuestosConfig($conexion, $empresa_origen_id, $empresa_destino_id, $mapaCuentas);
            $resultadoDetalle['Configuración de Impuestos'] = $detalleImp;
            $totalRegistrosCreados += $cantImp;
        }

        // -------------------------------------------------------------
        // H3. COPIAR CATÁLOGO DE PRODUCTOS Y SERVICIOS
        // -------------------------------------------------------------
        if (!empty($componentesSolicitados['productos']) && !empty($aut['permite_productos'])) {
            [$detalleProd, $cantProd] = inicioCopiarProductos($conexion, $empresa_origen_id, $empresa_destino_id, $mapaCuentas);
            $resultadoDetalle['Productos y Servicios'] = $detalleProd;
            $totalRegistrosCreados += $cantProd;
        }

        // -------------------------------------------------------------
        // I. ACCESOS POR PERFIL: los de la origen remapeados; los perfiles que quedan sin ninguno, a toda la empresa
        // -------------------------------------------------------------
        [$detalleAcc, $cantAcc] = inicioCopiarAccesosPerfiles($conexion, $empresa_origen_id, $empresa_destino_id, (int) $usuario_id);
        $resultadoDetalle['Accesos por Perfil'] = $detalleAcc;
        $totalRegistrosCreados += $cantAcc;

        // -------------------------------------------------------------
        // J. REGISTRAR LOG DE AUDITORÍA
        // -------------------------------------------------------------
        $compJson = mysqli_real_escape_string($conexion, json_encode($componentesSolicitados));
        $detJson = mysqli_real_escape_string($conexion, json_encode($resultadoDetalle));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sqlLog = "INSERT INTO gestion__empresas_copia_log (
            empresa_origen_id, empresa_destino_id, autorizacion_id, componentes_solicitados,
            detalle_ejecucion, total_registros_creados, usuario_id, ip_origen, fecha_copia
        ) VALUES (
            $empresa_origen_id, $empresa_destino_id, $autorizacion_id, '$compJson',
            '$detJson', $totalRegistrosCreados, $usuario_id, '$ip', NOW()
        )";
        mysqli_query($conexion, $sqlLog);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Copia completada exitosamente. Total de registros creados/actualizados: $totalRegistrosCreados",
            'detalles' => $resultadoDetalle,
            'total' => $totalRegistrosCreados
        ];

    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'error' => "Error durante el proceso de copia: " . $e->getMessage()
        ];
    }
}

/**
 * Acciones rápidas para resolver puntos faltantes sin copia
 */
function inicioCrearSucursalRapida($conexion, $empresa_id, $usuario_id) {
    $empresa_id = intval($empresa_id);

    // Verificar si ya tiene
    $chk = mysqli_query($conexion, "SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = $empresa_id LIMIT 1");
    if (mysqli_fetch_assoc($chk)) {
        return ['success' => false, 'error' => 'La empresa ya cuenta con una sucursal registrada'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Sucursal Central (el tipo de sucursal es de la empresa, nunca un id fijo)
        $sucTipoId = inicioSucursalTipoDestino($conexion, $empresa_id, null);
        $sqlSuc = "INSERT INTO gestion__sucursales (
            empresa_id, sucursal_tipo_id, sucursal_nombre, descripcion, direccion, tabla_estado_registro_id
        ) VALUES (
            $empresa_id, $sucTipoId, 'Casa Central', 'Sede principal y administrativa', 'Sede Central', 1
        )";
        mysqli_query($conexion, $sqlSuc);
        $sucId = mysqli_insert_id($conexion);

        // Boca Principal
        $sqlBoc = "INSERT INTO gestion__bocas (
            empresa_id, sucursal_id, es_deposito, boca_nombre, codigo, descripcion,
            permite_ingresos, permite_egresos, permite_stock_negativo, es_principal, orden, tabla_estado_registro_id, f_alta
        ) VALUES (
            $empresa_id, $sucId, 1, 'Depósito Central', 'DEP01', 'Depósito principal de mercadería',
            1, 1, 1, 1, 1, 1, NOW()
        )";
        mysqli_query($conexion, $sqlBoc);
        $bocId = mysqli_insert_id($conexion);

        // Punto de Venta 1
        $sqlPv = "INSERT INTO gestion__puntos_venta (
            empresa_id, sucursal_id, boca_id, nombre, codigo_fiscal, sistema_emision, es_web, tabla_estado_registro_id
        ) VALUES (
            $empresa_id, $sucId, $bocId, 'Punto de Venta 0001 (WSFE)', 1, 'WSFE', 0, 1
        )";
        mysqli_query($conexion, $sqlPv);

        // Asignar accesos
        $sqlAcc = "INSERT IGNORE INTO gestion__perfiles_sucursales (
            empresa_id, empresa_perfil_id, sucursal_id, tabla_estado_registro_id, fecha_creacion
        ) SELECT ep.empresa_id, ep.empresa_perfil_id, NULL, 1, NOW()
          FROM conf__empresas_perfiles ep
          WHERE ep.empresa_id = $empresa_id AND ep.tabla_estado_registro_id = 1";
        mysqli_query($conexion, $sqlAcc);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Sucursal Central, Depósito Central y Punto de Venta 0001 creados con éxito'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Conceder acceso a todas las sucursales a todos los perfiles de la empresa
 */
function inicioAsignarAccesosGlobales($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sqlAcc = "INSERT IGNORE INTO gestion__perfiles_sucursales (
        empresa_id, empresa_perfil_id, sucursal_id, tabla_estado_registro_id, fecha_creacion
    ) SELECT ep.empresa_id, ep.empresa_perfil_id, NULL, 1, NOW()
      FROM conf__empresas_perfiles ep
      WHERE ep.empresa_id = $empresa_id AND ep.tabla_estado_registro_id = 1";
    $ok = mysqli_query($conexion, $sqlAcc);
    $cant = mysqli_affected_rows($conexion);

    if ($ok) {
        return ['success' => true, 'message' => "Accesos concedidos a perfiles de la empresa ($cant asignaciones registradas)"];
    } else {
        return ['success' => false, 'error' => mysqli_error($conexion)];
    }
}

/**
 * Tipo de operación de la empresa (BIENES / SERVICIOS / MIXTA). No toca perfiles existentes:
 * eso lo hacen "Crear perfiles predefinidos" y "Quitar funciones de bienes".
 */
function inicioGuardarTipoOperacion($conexion, $empresa_id, $tipo) {
    if (!empresa_guardar_tipo_operacion($conexion, intval($empresa_id), (string) $tipo)) {
        return ['success' => false, 'error' => 'Tipo de operación inválido.'];
    }
    return ['success' => true, 'message' => 'Tipo de operación actualizado: ' . TIPOS_OPERACION[strtoupper(trim((string) $tipo))]];
}

/**
 * Crea en la empresa los perfiles predefinidos indicados (o todos los que le faltan) para su tipo de
 * operación, con sus funciones y acceso a todas las sucursales.
 */
function inicioCrearPerfilesPredefinidos($conexion, $empresa_id, $modulo_id, array $perfil_ids = []) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    $pedidos = array_map('intval', $perfil_ids);

    $aCrear = [];
    foreach (perfiles_base_para_empresa($conexion, $empresa_id, $modulo_id) as $p) {
        if ($p['empresa_perfil_id'] === null && (!$pedidos || in_array($p['perfil_id'], $pedidos, true))) {
            $aCrear[] = $p;
        }
    }
    if (!$aCrear) {
        return ['success' => false, 'error' => 'No hay perfiles predefinidos pendientes de crear para esta empresa.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $nombres = [];
        $funciones = 0;
        foreach ($aCrear as $p) {
            $r = perfil_base_copiar_a_empresa($conexion, $empresa_id, $modulo_id, $p['perfil_id'], true);
            $nombres[] = $p['perfil_nombre'];
            $funciones += $r['funciones'];
        }
        mysqli_commit($conexion);
        return [
            'success' => true,
            'message' => count($nombres) . " perfiles creados (" . implode(', ', $nombres) . ") con $funciones funciones y acceso a todas las sucursales. Asigne los usuarios en Perfiles - Usuarios."
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("inicioCrearPerfilesPredefinidos empresa=$empresa_id: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Empresa de servicios: desasigna de los perfiles indicados las funciones de páginas de bienes
 * (pedidos, remitos, stock), que así dejan de verse en el menú. Se pueden volver a tildar.
 */
function inicioQuitarFuncionesBienes($conexion, $empresa_id, $modulo_id, array $empresa_perfil_ids) {
    $empresa_id = intval($empresa_id);
    if (empresa_tipo_operacion($conexion, $empresa_id) !== 'SERVICIOS') {
        return ['success' => false, 'error' => 'Solo se aplica a empresas de tipo Servicios.'];
    }
    mysqli_begin_transaction($conexion);
    try {
        $cant = perfiles_quitar_funciones_bienes($conexion, $empresa_id, intval($modulo_id), $empresa_perfil_ids);
        mysqli_commit($conexion);
        return ['success' => true, 'message' => "Se quitaron $cant funciones de pedidos, remitos y stock."];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Alta de una empresa nueva con los datos de la Constancia de Inscripción de ARCA, leídos con IA
 * (core/constancia_cuit_ia.php, acción leer_constancia) y revisados por el usuario en el modal del asistente.
 * Pasos: empresa (agregarempresa de conf/empresas_model.php, que también abre la vigencia de Parámetros de Empresa),
 * mes de cierre del ejercicio, módulo de esta página, perfiles predefinidos según el tipo de operación, el usuario
 * como Administrador y, si se pide, la autorización de copia desde la empresa activa (la copia se ejecuta después con
 * "Copiar Datos Iniciales" del asistente, con previsualización). No es una sola transacción (agregarempresa y los
 * perfiles abren la suya), igual que el alta con asistente del estudio: si un paso posterior falla, la empresa queda
 * creada y el asistente de inicio muestra lo que falta.
 * Requiere conf/empresas_model.php incluido a nivel de archivo (agregarempresa), como hace empresa_inicio_ajax.php.
 * $d: razon_social, cuit, condicion_fiscal_id, domicilio, localidad_id, iibb_regimen_id, jurisdiccion_sede_id,
 *     inicio_actividades (AAAA-MM-DD), mes_cierre_ejercicio (1-12), tipo_operacion, copiar_desde_actual (0/1).
 * Devuelve ['success' => true, 'data' => ['empresa_id', 'empresa', 'pasos', 'origen_copia_id'], 'message'] o
 * ['success' => false, 'message', 'code', 'data' => ['empresa_existente' => ...]].
 */
function inicioAltaEmpresaConstancia(mysqli $conexion, int $empresa_actual_id, int $usuario_id, int $modulo_id, array $d): array
{
    require_once __DIR__ . '/../../core/arca_padron_service.php';
    require_once __DIR__ . '/../../core/permisos.php';
    $mal = function (string $m, string $c = 'VALIDATION_ERROR', array $data = []): array {
        return ['success' => false, 'message' => $m, 'error' => $m, 'code' => $c, 'data' => $data];
    };
    if (!function_exists('agregarempresa')) {
        return $mal('Falta el modelo de empresas (conf/empresas_model.php).', 'CONFIG_ERROR');
    }

    // Validación: la constancia la leyó la IA y el usuario pudo corregirla, así que se revisa todo de nuevo
    $razon = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($d['razon_social'] ?? ''))), 0, 150);
    $cuit = preg_replace('/\D/', '', (string)($d['cuit'] ?? ''));
    $cond = intval($d['condicion_fiscal_id'] ?? 0);
    $loc = intval($d['localidad_id'] ?? 0);
    $regimen = intval($d['iibb_regimen_id'] ?? 1);
    $sede = intval($d['jurisdiccion_sede_id'] ?? 0);
    $mes = intval($d['mes_cierre_ejercicio'] ?? 12);
    $tipo = strtoupper(trim((string)($d['tipo_operacion'] ?? '')));
    $domicilio = mb_substr(trim((string)($d['domicilio'] ?? '')), 0, 150);
    $inicio = (string)($d['inicio_actividades'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !strtotime($inicio)) {
        $inicio = '';
    }

    if ($razon === '') {
        return $mal('Falta la razón social.');
    }
    if (!arca_padron_cuit_valido($cuit)) {
        return $mal('El CUIT no es válido: revisalo.');
    }
    if (!isset(TIPOS_OPERACION[$tipo])) {
        return $mal('Elegí el tipo de operación.');
    }
    if ($mes < 1 || $mes > 12) {
        $mes = 12;
    }

    $existe = function (string $sql, int $id) use ($conexion): bool {
        $st = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
        $ok = (bool) mysqli_fetch_row(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $ok;
    };
    if ($cond <= 0 || !$existe("SELECT 1 FROM gestion__condiciones_fiscales WHERE condicion_fiscal_id = ? AND tabla_estado_registro_id = 1", $cond)) {
        return $mal('Elegí la condición frente al IVA.');
    }
    if ($loc <= 0 || !$existe("SELECT 1 FROM conf__localidades WHERE localidad_id = ? AND tabla_estado_registro_id IN (1, 2)", $loc)) {
        return $mal('Elegí la provincia y la localidad del domicilio fiscal.');
    }
    if (!$existe("SELECT 1 FROM gestion__iibb_regimenes WHERE iibb_regimen_id = ? AND tabla_estado_registro_id = 1", $regimen)) {
        $regimen = 1;
    }
    if ($sede > 0 && !$existe("SELECT 1 FROM gestion__iibb_jurisdicciones_sedes WHERE jurisdiccion_sede_id = ? AND tabla_estado_registro_id = 1", $sede)) {
        $sede = 0;
    }

    // CUIT repetido: no se crea otra empresa; se avisa y, si el usuario tiene acceso, el JS ofrece abrirla
    $st = mysqli_prepare($conexion, "SELECT empresa_id, empresa FROM conf__empresas
                                     WHERE REPLACE(REPLACE(documento_numero, '-', ''), ' ', '') = ? ORDER BY empresa_id LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $cuit);
    mysqli_stmt_execute($st);
    $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if ($ex) {
        $ex_id = (int) $ex['empresa_id'];
        $accesible = permisos_es_superadmin() || (bool) usuario_obtener_perfiles_empresa($conexion, $usuario_id, $ex_id);
        return $mal("Ya existe la empresa \"{$ex['empresa']}\" (#$ex_id) con ese CUIT.", 'EMPRESA_EXISTENTE',
            ['empresa_existente' => ['empresa_id' => $ex_id, 'empresa' => $ex['empresa'], 'accesible' => $accesible]]);
    }

    // La autorización de copia la concede la empresa activa: hay que poder otorgarla allí
    $copiar = !empty($d['copiar_desde_actual']) && $empresa_actual_id > 0;
    if ($copiar && !permisos_es_superadmin()) {
        $pagina_aut = pagina_id_por_url($conexion, $modulo_id, 'empresas_autorizaciones_copia.php');
        if ($pagina_aut <= 0 || !usuario_puede_funcion($conexion, $usuario_id, $empresa_actual_id, $pagina_aut, 'agregar')) {
            return $mal('Tu perfil no puede otorgar autorizaciones de copia en la empresa activa: destildá "Copiar la configuración".', 'SIN_PERMISO');
        }
    }

    $pasos = [];
    $paso = function (string $titulo, bool $ok, string $detalle = '') use (&$pasos): void {
        $pasos[] = ['paso' => $titulo, 'ok' => $ok, 'detalle' => $detalle];
    };

    // 1. Empresa (+ vigencia inicial de Parámetros de Empresa con condición fiscal, inicio de actividades e IIBB)
    $nueva = 0;
    $ok = agregarempresa($conexion, [
        'empresa' => $razon,
        'documento_tipo_id' => 1,
        'documento_numero' => $cuit,
        'domicilio' => $domicilio,
        'localidad_id' => $loc,
        'condicion_fiscal_id' => $cond,
        'iibb_regimen_id' => $regimen,
        'jurisdiccion_sede_id' => $sede > 0 ? $sede : null,
        'inicio_actividades' => $inicio !== '' ? $inicio : null,
        'tipo_operacion' => $tipo,
        'nivel_gestion' => 'COMPLETA',
        'tabla_estado_registro_id' => 1,
    ], $usuario_id, $nueva);
    if (!$ok || $nueva <= 0) {
        error_log("=== inicioAltaEmpresaConstancia: agregarempresa falló (CUIT $cuit): " . mysqli_error($conexion) . " ===");
        return $mal('No se pudo crear la empresa.', 'EMPRESA_ERROR');
    }
    $empresa_id = (int) $nueva;
    error_log("=== inicioAltaEmpresaConstancia: empresa $empresa_id creada desde la constancia por el usuario $usuario_id ===");
    $paso('Empresa', true, "Empresa #$empresa_id creada");

    // 2. Mes de cierre del ejercicio (de la constancia) en la vigencia de Parámetros de Empresa que abrió el alta
    $st = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET mes_cierre_ejercicio = ?
                                     WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1");
    if ($st) {
        mysqli_stmt_bind_param($st, 'ii', $mes, $empresa_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }

    // 3. Módulo de esta página (sin él la empresa no aparece en el selector de módulos)
    $st = mysqli_prepare($conexion, "INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id)
                                     SELECT ?, ?, 1 FROM DUAL
                                     WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id = ?)");
    mysqli_stmt_bind_param($st, 'iiii', $empresa_id, $modulo_id, $empresa_id, $modulo_id);
    $ok_mod = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    $paso('Módulo de gestión', $ok_mod, $ok_mod ? 'Habilitado para la empresa' : 'No se pudo habilitar: hacelo en Empresas - Módulos');

    // 4. Perfiles predefinidos del tipo de operación (con acceso a todas las sucursales)
    $per = inicioCrearPerfilesPredefinidos($conexion, $empresa_id, $modulo_id);
    $paso('Perfiles predefinidos', !empty($per['success']), (string) ($per['message'] ?? ($per['error'] ?? '')));

    // 5. Quien hace el alta queda como Administrador (si no, solo el superadmin podría entrar)
    $st = mysqli_prepare($conexion, "SELECT ep.empresa_perfil_id FROM conf__empresas_perfiles ep
                                     JOIN conf__perfiles p ON p.perfil_id = ep.perfil_id_base
                                     WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND ep.tabla_estado_registro_id = 1
                                       AND p.perfil_nombre = 'Administrador'
                                     ORDER BY ep.empresa_perfil_id LIMIT 1");
    mysqli_stmt_bind_param($st, 'ii', $empresa_id, $modulo_id);
    mysqli_stmt_execute($st);
    $ep = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if ($ep) {
        $epid = (int) $ep['empresa_perfil_id'];
        $st = mysqli_prepare($conexion, "INSERT INTO conf__usuarios_perfiles
                                             (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion, tabla_estado_registro_id)
                                         SELECT ?, ?, CURDATE(), '2099-12-31', ?, 1 FROM DUAL
                                         WHERE NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles
                                                           WHERE usuario_id = ? AND empresa_perfil_id = ? AND tabla_estado_registro_id = 1)");
        mysqli_stmt_bind_param($st, 'iiiii', $usuario_id, $epid, $usuario_id, $usuario_id, $epid);
        $ok_adm = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $paso('Tu acceso', $ok_adm, $ok_adm ? 'Quedaste como Administrador de la empresa' : 'No se pudo asignar: hacelo en Perfiles - Usuarios');
    } else {
        $paso('Tu acceso', false, 'La empresa no tiene perfil Administrador: asigná los usuarios en Perfiles - Usuarios');
    }

    // 6. Autorización de copia desde la empresa activa (la copia se ejecuta después, con previsualización)
    $origen_copia = 0;
    if ($copiar) {
        require_once __DIR__ . '/empresas_autorizaciones_copia_model.php';
        $permisos = [];
        foreach (['plan_cuentas', 'conceptos_contables', 'roles_cuentas', 'comprobantes_tipos', 'condiciones_pago',
                  'sucursales_bocas', 'listas_precios', 'parametros', 'datos_basicos', 'tesoreria',
                  'impuestos_config', 'productos'] as $k) {
            $permisos['permite_' . $k] = 1;
        }
        $aut = autCopiaGuardar($conexion, $permisos + [
            'empresa_destino_id' => $empresa_id,
            'motivo' => 'Alta de empresa desde la constancia de CUIT',
        ], $empresa_actual_id, $usuario_id);
        $aut_ok = !empty($aut['success']);
        $paso('Autorización de copia', $aut_ok, $aut_ok ? 'La empresa activa autorizó copiar su configuración' : (string) ($aut['error'] ?? ''));
        if ($aut_ok) {
            $origen_copia = $empresa_actual_id;
        }
    }

    $fallas = count(array_filter($pasos, function ($p) { return !$p['ok']; }));
    return [
        'success' => true,
        'data' => ['empresa_id' => $empresa_id, 'empresa' => $razon, 'pasos' => $pasos, 'origen_copia_id' => $origen_copia],
        'message' => $fallas ? "La empresa se creó, pero $fallas paso(s) quedaron pendientes." : 'Empresa creada.',
    ];
}
