<?php
/**
 * core/empresa_config.php — configuración propia de cada empresa, resuelta por empresa_id.
 *
 * Reemplaza los ids fijos de Casa Lucho (empresa 2) que tenían los modelos de modules/gestion y
 * modules/gestion_normal (tipo de asiento 2, moneda 1, cuentas 111/600/331/851/131, subgrupos 5/8/15,
 * boca 1, producto tipo 1, IVA 1...). Todo se busca por empresa_id + código, así que vale igual para
 * cualquier empresa que tenga su configuración cargada (ver la siembra de tesorería y el alta de empresa).
 *
 * Si la empresa no tiene el dato, las funciones lanzan RuntimeException con un mensaje para el usuario:
 * nunca se cae en un id de otra empresa. Los resultados se cachean por request.
 */

function empresa_cfg_cache(string $clave, callable $resolver)
{
    static $cache = [];
    if (!array_key_exists($clave, $cache)) {
        $cache[$clave] = $resolver();
    }
    return $cache[$clave];
}

/** Primera columna de la primera fila, o null. */
function empresa_cfg_valor($conexion, string $sql, string $tipos, array $params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new RuntimeException('Error consultando la configuración de la empresa: ' . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ? $fila[0] : null;
}

/** Tipo de asiento por código ('AUT' automático, D27; 'MAN' manual). */
function emp_tipo_asiento_id($conexion, $empresa_id, string $codigo = 'AUT'): int
{
    return empresa_cfg_cache("tipo_asiento:$empresa_id:$codigo", function () use ($conexion, $empresa_id, $codigo) {
        $id = intval(empresa_cfg_valor($conexion,
            "SELECT cont_tipo_asiento_id FROM gestion__cont_tipos_asientos
              WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1
              ORDER BY cont_tipo_asiento_id LIMIT 1", "is", [$empresa_id, $codigo]));
        if ($id <= 0) {
            throw new RuntimeException("La empresa no tiene configurado el tipo de asiento '$codigo' (Contabilidad > Tipos de Asientos).");
        }
        return $id;
    });
}

/** Moneda base de la empresa (es_moneda_base = 1). */
function emp_moneda_base_id($conexion, $empresa_id): int
{
    return empresa_cfg_cache("moneda_base:$empresa_id", function () use ($conexion, $empresa_id) {
        $id = intval(empresa_cfg_valor($conexion,
            "SELECT moneda_id FROM gestion__monedas
              WHERE empresa_id = ? AND tabla_estado_registro_id = 1
              ORDER BY es_moneda_base DESC, orden, moneda_id LIMIT 1", "i", [$empresa_id]));
        if ($id <= 0) {
            throw new RuntimeException('La empresa no tiene monedas configuradas.');
        }
        return $id;
    });
}

/** La moneda pedida si es de la empresa; si no viene o es de otra empresa, la moneda base. */
function emp_moneda_id($conexion, $empresa_id, $moneda_id = 0): int
{
    $moneda_id = intval($moneda_id);
    if ($moneda_id > 0) {
        $propia = empresa_cfg_cache("moneda:$empresa_id:$moneda_id", function () use ($conexion, $empresa_id, $moneda_id) {
            return intval(empresa_cfg_valor($conexion,
                "SELECT moneda_id FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ?", "ii", [$moneda_id, $empresa_id]));
        });
        if ($propia > 0) {
            return $propia;
        }
    }
    return emp_moneda_base_id($conexion, $empresa_id);
}

/** Moneda por código ISO ('USD'); 0 si la empresa no la tiene. */
function emp_moneda_por_codigo($conexion, $empresa_id, string $codigo): int
{
    return empresa_cfg_cache("moneda_cod:$empresa_id:$codigo", function () use ($conexion, $empresa_id, $codigo) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT moneda_id FROM gestion__monedas WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1",
            "is", [$empresa_id, $codigo]));
    });
}

/**
 * Cuentas contables por defecto: cada empresa tiene su propio plan de cuentas, así que nunca se buscan por
 * código ni por id. Salen de los Conceptos Contables de la empresa (gestion__empresas_cuentas_conceptos,
 * página Tesorería > Conceptos Contables, D35) y, en los conceptos que tienen columna, de los parámetros
 * contables (gestion__cont_parametros). La cuenta tiene que ser de la empresa, imputable y activa.
 */
const EMP_CONCEPTOS_CUENTAS = [
    'DEUDORES_VENTAS'  => 'Deudores por Ventas',
    'VENTAS'           => 'Ventas (por defecto)',
    'IVA_DEBITO'       => 'IVA Débito Fiscal',
    'PROVEEDORES'      => 'Proveedores',
    'VALORES_CARTERA'  => 'Valores en Cartera',
    'IVA_A_PAGAR'      => 'IVA a Pagar',
    'GASTOS_GENERALES' => 'Otros Gastos Generales',
    'MERCADERIAS'      => 'Mercaderías',
    'EMPLEADOS'        => 'Empleados - Anticipos y Rendiciones',
    'VALORES_A_PAGAR'  => 'Valores a Pagar',
    'IVA_SALDO_FAVOR'  => 'IVA Saldo a Favor',
    'IIBB_GASTO'       => 'Impuesto sobre los Ingresos Brutos',
    'IIBB_A_PAGAR'     => 'Ingresos Brutos a Pagar',
    'IIBB_SALDO_FAVOR' => 'Ingresos Brutos Saldo a Favor',
];

/** Cuenta del concepto de la empresa; 0 si no está configurada. */
function emp_cuenta_concepto_id($conexion, $empresa_id, string $concepto): int
{
    return empresa_cfg_cache("cuenta_concepto:$empresa_id:$concepto", function () use ($conexion, $empresa_id, $concepto) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT c.cont_cuenta_id
               FROM gestion__empresas_cuentas_conceptos ecc
               JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = ecc.cont_cuenta_id AND c.empresa_id = ecc.empresa_id
              WHERE ecc.empresa_id = ? AND ecc.concepto = ? AND ecc.tabla_estado_registro_id = 1
                AND c.es_imputable = 1 AND c.tabla_estado_registro_id = 1
              LIMIT 1", "is", [$empresa_id, $concepto]));
    });
}

/** Cuenta del concepto o, si no está, de la columna de gestion__cont_parametros; si no, error para el usuario. */
function emp_cuenta_id($conexion, $empresa_id, string $concepto, ?string $columna_parametro = null): int
{
    return empresa_cfg_cache("cuenta:$empresa_id:$concepto:$columna_parametro", function () use ($conexion, $empresa_id, $concepto, $columna_parametro) {
        $id = emp_cuenta_concepto_id($conexion, $empresa_id, $concepto);
        if ($id <= 0 && $columna_parametro !== null && preg_match('/^cuenta_[a-z_]+_id$/', $columna_parametro)) {
            $id = intval(empresa_cfg_valor($conexion,
                "SELECT c.cont_cuenta_id FROM gestion__cont_parametros p
                   JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = p.$columna_parametro AND c.empresa_id = p.empresa_id
                  WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
                    AND c.es_imputable = 1 AND c.tabla_estado_registro_id = 1 LIMIT 1", "i", [$empresa_id]));
        }
        if ($id <= 0) {
            $nombre = EMP_CONCEPTOS_CUENTAS[$concepto] ?? $concepto;
            throw new RuntimeException("La empresa no tiene configurada la cuenta contable de $nombre (concepto $concepto). "
                . 'Asígnela en Tesorería > Conceptos Contables.');
        }
        return $id;
    });
}

/**
 * Valida que todas las cuentas de un asiento sean del plan de la empresa, imputables y activas.
 * Ningún asiento puede imputar a una cuenta de otra empresa.
 */
function emp_validar_cuentas_asiento($conexion, $empresa_id, array $cuenta_ids): void
{
    foreach ($cuenta_ids as $cid) {
        if (intval($cid) <= 0) {
            throw new RuntimeException('El asiento tiene un renglón sin cuenta contable.');
        }
    }
    $ids = array_values(array_unique(array_map('intval', $cuenta_ids)));
    if (!$ids) {
        return;
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = mysqli_prepare($conexion,
        "SELECT cont_cuenta_id FROM gestion__cont_cuentas
          WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1 AND cont_cuenta_id IN ($marcas)");
    $params = array_merge([intval($empresa_id)], $ids);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
    mysqli_stmt_execute($stmt);
    $validas = array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'cont_cuenta_id'));
    mysqli_stmt_close($stmt);
    $invalidas = array_diff($ids, $validas);
    if ($invalidas) {
        throw new RuntimeException('El asiento usa cuentas que no son imputables y activas del plan de cuentas de la empresa (id '
            . implode(', ', $invalidas) . '). Revise la configuración contable.');
    }
}

/** Subgrupo de comprobantes por código ('FC' facturas de compra, 'OC', 'CEF' ajustes de stock...); 0 si no existe. */
function emp_subgrupo_id($conexion, $empresa_id, string $codigo): int
{
    return empresa_cfg_cache("subgrupo:$empresa_id:$codigo", function () use ($conexion, $empresa_id, $codigo) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos
              WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1
              ORDER BY comprobante_subgrupo_id LIMIT 1", "is", [$empresa_id, $codigo]));
    });
}

/** El subgrupo pedido si es de la empresa; si no, el del código indicado. */
function emp_subgrupo_valido_id($conexion, $empresa_id, $subgrupo_id, string $codigo_defecto): int
{
    $subgrupo_id = intval($subgrupo_id);
    if ($subgrupo_id > 0 && intval(empresa_cfg_valor($conexion,
            "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE comprobante_subgrupo_id = ? AND empresa_id = ?",
            "ii", [$subgrupo_id, $empresa_id])) > 0) {
        return $subgrupo_id;
    }
    return emp_subgrupo_id($conexion, $empresa_id, $codigo_defecto);
}

/** Tipo de comprobante por código ('PC' pedido de cliente, 'RCX'...); 0 si no existe. */
function emp_comprobante_tipo_id($conexion, $empresa_id, string $codigo): int
{
    return empresa_cfg_cache("ctipo:$empresa_id:$codigo", function () use ($conexion, $empresa_id, $codigo) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos
              WHERE empresa_id = ? AND codigo = ? AND tabla_estado_registro_id = 1
              ORDER BY comprobante_tipo_id LIMIT 1", "is", [$empresa_id, $codigo]));
    });
}

/** Alícuota de IVA gravada por porcentaje (21 por defecto). */
function emp_iva_alicuota_id($conexion, $empresa_id, float $porcentaje = 21.0): int
{
    return empresa_cfg_cache("iva:$empresa_id:$porcentaje", function () use ($conexion, $empresa_id, $porcentaje) {
        $id = intval(empresa_cfg_valor($conexion,
            "SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas
              WHERE empresa_id = ? AND es_gravado = 1 AND ABS(porcentaje - ?) < 0.001 AND tabla_estado_registro_id = 1
              ORDER BY iva_alicuota_id LIMIT 1", "id", [$empresa_id, $porcentaje]));
        if ($id <= 0) {
            throw new RuntimeException("La empresa no tiene configurada la alícuota de IVA del $porcentaje%.");
        }
        return $id;
    });
}

/** Tipo de producto por defecto: el primero activo que maneja stock (o el primero que no, para servicios). */
function emp_producto_tipo_id($conexion, $empresa_id, bool $maneja_stock = true): int
{
    return empresa_cfg_cache("prod_tipo:$empresa_id:" . intval($maneja_stock), function () use ($conexion, $empresa_id, $maneja_stock) {
        $id = intval(empresa_cfg_valor($conexion,
            "SELECT producto_tipo_id FROM gestion__productos_tipos
              WHERE empresa_id = ? AND tabla_estado_registro_id = 1
              ORDER BY (maneja_stock = ?) DESC, producto_tipo_id LIMIT 1", "ii", [$empresa_id, $maneja_stock ? 1 : 0]));
        if ($id <= 0) {
            throw new RuntimeException('La empresa no tiene tipos de producto configurados.');
        }
        return $id;
    });
}

/** Boca de la sucursal (la principal) o, sin sucursal, la principal de la empresa; 0 si no hay. */
function emp_boca_id($conexion, $empresa_id, $sucursal_id = 0): int
{
    return empresa_cfg_cache("boca:$empresa_id:$sucursal_id", function () use ($conexion, $empresa_id, $sucursal_id) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT boca_id FROM gestion__bocas
              WHERE empresa_id = ? AND tabla_estado_registro_id = 1
              ORDER BY (sucursal_id = ?) DESC, es_principal DESC, orden, boca_id LIMIT 1", "ii", [$empresa_id, $sucursal_id]));
    });
}

/** Medio de pago activo de la empresa por tipo ('EFECTIVO', 'TRANSFERENCIA'...); 0 si no hay. */
function emp_medio_pago_id($conexion, $empresa_id, string $medio_pago_tipo): int
{
    return empresa_cfg_cache("medio:$empresa_id:$medio_pago_tipo", function () use ($conexion, $empresa_id, $medio_pago_tipo) {
        return intval(empresa_cfg_valor($conexion,
            "SELECT medio_pago_id FROM gestion__medios_pago
              WHERE empresa_id = ? AND medio_pago_tipo = ? AND tabla_estado_registro_id = 1
              ORDER BY orden, medio_pago_id LIMIT 1", "is", [$empresa_id, $medio_pago_tipo]));
    });
}

/** Punto de venta por defecto (Parámetros de Empresa) o el primero activo; 0 si no hay. */
function emp_punto_venta_id($conexion, $empresa_id): int
{
    return empresa_cfg_cache("pv:$empresa_id", function () use ($conexion, $empresa_id) {
        $id = intval(empresa_cfg_valor($conexion,
            "SELECT pv.punto_venta_id FROM gestion__empresas_parametros p
               JOIN gestion__puntos_venta pv ON pv.punto_venta_id = p.punto_venta_defecto_id AND pv.empresa_id = p.empresa_id
              WHERE p.empresa_id = ? AND p.f_hasta IS NULL AND p.tabla_estado_registro_id = 1 LIMIT 1", "i", [$empresa_id]));
        if ($id <= 0) {
            $id = intval(empresa_cfg_valor($conexion,
                "SELECT punto_venta_id FROM gestion__puntos_venta WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                  ORDER BY punto_venta_id LIMIT 1", "i", [$empresa_id]));
        }
        return $id;
    });
}

/** CUIT de la empresa (conf__empresas.documento_numero, 11 dígitos); '' si no tiene. */
function emp_cuit($conexion, $empresa_id): string
{
    return empresa_cfg_cache("cuit:$empresa_id", function () use ($conexion, $empresa_id) {
        $cuit = (string)empresa_cfg_valor($conexion,
            "SELECT CAST(documento_numero AS UNSIGNED) FROM conf__empresas WHERE empresa_id = ?", "i", [$empresa_id]);
        return preg_match('/^\d{11}$/', $cuit) ? $cuit : '';
    });
}

/*
 * Numeración que se muestra. Cada empresa tiene sus puntos de venta: comprobante_pv de los comprobantes
 * propios guarda el id interno del PV (gestion__puntos_venta.punto_venta_id), pero lo que se muestra es su
 * código fiscal (el mismo que va a ARCA y a la impresión). En los comprobantes de proveedores (tabla 84)
 * comprobante_pv ya es el punto de venta del proveedor y se muestra tal cual.
 */
const EMP_TABLA_FACTURAS_PROVEEDORES = 84;

/** Código fiscal del punto de venta de la empresa; si no tiene, el id. */
function emp_pv_numero($conexion, $empresa_id, $punto_venta_id): int
{
    $punto_venta_id = intval($punto_venta_id);
    if ($punto_venta_id <= 0) {
        return 0;
    }
    return empresa_cfg_cache("pv_num:$empresa_id:$punto_venta_id", function () use ($conexion, $empresa_id, $punto_venta_id) {
        $cod = intval(empresa_cfg_valor($conexion,
            "SELECT codigo_fiscal FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?", "ii", [$punto_venta_id, $empresa_id]));
        return $cod > 0 ? $cod : $punto_venta_id;
    });
}

/** "0001-00000012" con el código fiscal del punto de venta de la empresa. */
function emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $nro): string
{
    return sprintf('%04d-%08d', emp_pv_numero($conexion, $empresa_id, $punto_venta_id), intval($nro));
}

/**
 * Expresión SQL del punto de venta a mostrar para una fila (columnas calificadas, p. ej. 'c.comprobante_pv').
 * Con $col_tabla_origen, las filas de facturas de proveedores muestran el PV del proveedor.
 */
function emp_sql_pv_mostrar(string $col_pv, string $col_empresa, ?string $col_tabla_origen = null): string
{
    $propio = "COALESCE((SELECT NULLIF(pvn.codigo_fiscal, 0) FROM gestion__puntos_venta pvn
                          WHERE pvn.punto_venta_id = $col_pv AND pvn.empresa_id = $col_empresa), $col_pv)";
    return $col_tabla_origen === null
        ? "($propio)"
        : "(CASE WHEN $col_tabla_origen = " . EMP_TABLA_FACTURAS_PROVEEDORES . " THEN $col_pv ELSE $propio END)";
}
