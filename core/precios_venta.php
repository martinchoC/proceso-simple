<?php
/**
 * Reglas de precio de venta (paso 4 de la distribuidora): escalas por cantidad, promociones, tope del descuento
 * automático por lista y tope del descuento manual por perfil. Diseño: _local/docs/gestion/22_escalas_promociones.md
 * (aprobado el 2026-11-15).
 *
 * Precedencia (sin acumular): precio de lista o de la presentación → descuento AUTOMÁTICO = el mayor entre el
 * descuento general del cliente, la escala que alcanza la cantidad y la promoción vigente (empate: promoción >
 * escala > cliente), limitado por el tope de la lista → descuento MANUAL del vendedor encima, hasta el tope de su
 * perfil. Total = 1 − (1 − automático) × (1 − manual): es el descuento_general_pct de siempre, así que neto, IVA,
 * asientos y ARCA no cambian.
 *
 * El servidor no reescribe el % ni los importes que manda la pantalla (como el precio, doc 20 decisión 6): con
 * precio_resolver() calcula el automático que corresponde y toma como manual lo que la línea trae por encima. Ese
 * manual es lo que se controla contra el tope.
 *
 * Indicador: conf__empresas.usa_reglas_precio_venta (script 20261117_reglas_precio_venta_01.sql). Sin el script o
 * apagado, prv_empresa_usa() da false y los modelos no llaman a nada más de acá.
 */

require_once __DIR__ . '/canales_venta.php';

const PRV_TABLAS_LINEAS = [
    'pedido' => ['gestion__ventas_pedidos_detalles', 'venta_pedido_detalle_id'],
    'remito' => ['gestion__ventas_remitos_detalles', 'venta_remito_detalle_id'],
    'factura' => ['gestion__ventas_facturas_detalles', 'venta_factura_detalle_id'],
];
const PRV_COLUMNAS_LINEA = ['precio_regla_tipo', 'precio_regla_id', 'descuento_auto_pct', 'descuento_manual_pct', 'descuento_aprobado_por', 'descuento_aprobado_en'];
const PRV_ORDEN_TIPO = ['PROMOCION' => 3, 'ESCALA' => 2, 'CLIENTE' => 1, 'NINGUNA' => 0];

if (!function_exists('prv_existe')) {
    /** ¿Existe la tabla o, con $columna, la columna? (cacheado por request) */
    function prv_existe(mysqli $conexion, string $tabla, string $columna = ''): bool
    {
        static $cache = [];
        $k = "$tabla.$columna";
        if (!isset($cache[$k])) {
            $t = preg_replace('/[^a-z0-9_]/i', '', $tabla);
            if ($columna === '') {
                $res = mysqli_query($conexion, "SHOW TABLES LIKE '$t'");
            } else {
                $c = preg_replace('/[^a-z0-9_]/i', '', $columna);
                $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$t` LIKE '$c'");
            }
            $cache[$k] = $res && mysqli_num_rows($res) > 0;
        }
        return $cache[$k];
    }
}

if (!function_exists('prv_estructura')) {
    /** Tablas de reglas, indicador y tope de lista. */
    function prv_estructura(mysqli $conexion): bool
    {
        return prv_existe($conexion, 'gestion__precios_reglas_venta') && prv_existe($conexion, 'gestion__precios_reglas_venta_tramos')
            && prv_existe($conexion, 'gestion__ventas_descuentos_topes') && prv_existe($conexion, 'conf__empresas', 'usa_reglas_precio_venta')
            && prv_existe($conexion, 'gestion__listas_precios', 'descuento_tope_pct');
    }
}

if (!function_exists('prv_columnas_lineas')) {
    /** ¿Tiene la tabla de líneas del tipo las seis columnas de la regla aplicada? */
    function prv_columnas_lineas(mysqli $conexion, string $tipo): bool
    {
        if (!isset(PRV_TABLAS_LINEAS[$tipo])) {
            return false;
        }
        foreach (PRV_COLUMNAS_LINEA as $c) {
            if (!prv_existe($conexion, PRV_TABLAS_LINEAS[$tipo][0], $c)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('prv_empresa_usa')) {
    /** Indicador usa_reglas_precio_venta. Sin la estructura: false (lectura tolerante). */
    function prv_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && prv_estructura($conexion)) {
            $st = mysqli_prepare($conexion, "SELECT usa_reglas_precio_venta FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($st, "i", $empresa_id);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $usa = intval($row['usa_reglas_precio_venta'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('prv_activo')) {
    /** Indicador prendido y columnas en la tabla de líneas del tipo. */
    function prv_activo(mysqli $conexion, int $empresa_id, string $tipo): bool
    {
        return prv_empresa_usa($conexion, $empresa_id) && prv_columnas_lineas($conexion, $tipo);
    }
}

if (!function_exists('prv_filas')) {
    function prv_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
    {
        $st = mysqli_prepare($conexion, $sql);
        if (!$st) {
            return [];
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($st, $tipos, ...$params);
        }
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('prv_reglas_vigentes')) {
    /**
     * Reglas activas y vigentes a $fecha que pueden tocar a esos productos (por producto, categoría o "todos"), con
     * sus tramos (ESCALA, ordenados de menor a mayor).
     */
    function prv_reglas_vigentes(mysqli $conexion, int $empresa_id, string $fecha, array $producto_ids, array $categoria_ids): array
    {
        $producto_ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids)))) ?: [0];
        $categoria_ids = array_values(array_unique(array_filter(array_map('intval', $categoria_ids)))) ?: [0];
        $ph_p = implode(',', array_fill(0, count($producto_ids), '?'));
        $ph_c = implode(',', array_fill(0, count($categoria_ids), '?'));
        $reglas = prv_filas($conexion,
            "SELECT * FROM gestion__precios_reglas_venta
              WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                AND (producto_id IN ($ph_p) OR producto_categoria_id IN ($ph_c) OR (producto_id IS NULL AND producto_categoria_id IS NULL))",
            'iss' . str_repeat('i', count($producto_ids) + count($categoria_ids)),
            array_merge([$empresa_id, $fecha, $fecha], $producto_ids, $categoria_ids));
        if (!$reglas) {
            return [];
        }
        $ids = array_map('intval', array_column($reglas, 'regla_venta_id'));
        $tramos = prv_filas($conexion,
            "SELECT regla_venta_id, cantidad_desde, descuento_pct FROM gestion__precios_reglas_venta_tramos
              WHERE regla_venta_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY regla_venta_id, cantidad_desde",
            str_repeat('i', count($ids)), $ids);
        $por_regla = [];
        foreach ($tramos as $t) {
            $por_regla[(int) $t['regla_venta_id']][] = ['desde' => (float) $t['cantidad_desde'], 'pct' => (float) $t['descuento_pct']];
        }
        foreach ($reglas as &$r) {
            $r['tramos'] = $por_regla[(int) $r['regla_venta_id']] ?? [];
        }
        unset($r);
        return $reglas;
    }
}

if (!function_exists('prv_tope_lista')) {
    /** Tope del descuento automático de la lista (null = sin tope). */
    function prv_tope_lista(mysqli $conexion, int $empresa_id, int $lista_precio_id): ?float
    {
        if ($lista_precio_id <= 0) {
            return null;
        }
        $r = prv_filas($conexion, "SELECT descuento_tope_pct FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ?",
            'ii', [$lista_precio_id, $empresa_id]);
        return ($r && $r[0]['descuento_tope_pct'] !== null) ? (float) $r[0]['descuento_tope_pct'] : null;
    }
}

if (!function_exists('prv_tope_usuario')) {
    /**
     * % máximo de descuento manual que puede dar o aprobar el usuario en la empresa: el mayor de los topes vigentes de
     * sus perfiles. Sin filas = 0. Superadmin (USUARIOS_ACCESO_TOTAL) = 100.
     */
    function prv_tope_usuario(mysqli $conexion, int $empresa_id, int $usuario_id, ?string $fecha = null): float
    {
        if (defined('USUARIOS_ACCESO_TOTAL') && in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
            return 100.0;
        }
        $fecha = $fecha ?: date('Y-m-d');
        $r = prv_filas($conexion,
            "SELECT MAX(t.tope_pct) AS tope
               FROM gestion__ventas_descuentos_topes t
               INNER JOIN conf__usuarios_perfiles up ON up.empresa_perfil_id = t.empresa_perfil_id AND up.usuario_id = ?
                      AND up.tabla_estado_registro_id = 1
               INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = t.empresa_perfil_id AND ep.empresa_id = t.empresa_id
              WHERE t.empresa_id = ? AND t.tabla_estado_registro_id = 1 AND t.f_desde <= ? AND (t.f_hasta IS NULL OR t.f_hasta >= ?)",
            'iiss', [$usuario_id, $empresa_id, $fecha, $fecha]);
        return (float) ($r[0]['tope'] ?? 0);
    }
}

if (!function_exists('prv_r2')) {
    function prv_r2(float $v): float
    {
        return round($v + 0.0, 2);
    }
}

if (!function_exists('precio_resolver')) {
    /**
     * Descuento que corresponde a cada línea de una venta.
     *
     * $ctx: entidad_id (0 / null = consumidor final), lista_precio_id (0 = la del cliente con cvta_lista_cliente),
     *       sucursal_id, fecha (Y-m-d; null = hoy), usuario_id (para el tope manual),
     *       cliente_descuento_pct (null = el de la condición vigente del cliente).
     * $lineas: cada una con producto_id, presentacion_id (opcional), cantidad (unidad base) y, opcional,
     *       descuento_total_pct (el % que trae la pantalla) o descuento_manual_pct. Las claves se conservan.
     *
     * Devuelve [clave => [cliente_pct, candidatos[], regla_tipo, regla_id, regla_nombre, auto_pct, tope_lista,
     *   manual_pct, total_pct, tope_usuario, requiere_aprobacion, cantidad_producto]]. La cantidad de una escala es
     *   la del producto en TODO el comprobante (doc 22, decisión 2).
     * Sin el indicador: auto = descuento del cliente, sin reglas ni topes (como hoy).
     */
    function precio_resolver(mysqli $conexion, int $empresa_id, array $ctx, array $lineas): array
    {
        $fecha = (!empty($ctx['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $ctx['fecha'])) ? substr((string) $ctx['fecha'], 0, 10) : date('Y-m-d');
        $entidad_id = intval($ctx['entidad_id'] ?? 0);
        $sucursal_id = intval($ctx['sucursal_id'] ?? 0);
        $usuario_id = intval($ctx['usuario_id'] ?? 0);
        $lista_id = intval($ctx['lista_precio_id'] ?? 0);
        if ($lista_id <= 0) {
            $lista_id = cvta_lista_cliente($conexion, $empresa_id, $entidad_id ?: null, $fecha)['lista_precio_id'];
        }
        $cliente_pct = $ctx['cliente_descuento_pct'] ?? null;
        if ($cliente_pct === null) {
            $cliente_pct = 0.0;
            if ($entidad_id > 0) {
                $r = prv_filas($conexion,
                    "SELECT ecc.cliente_descuento_general FROM gestion__entidades_condiciones_clientes ecc
                     INNER JOIN gestion__entidades e ON e.entidad_id = ecc.entidad_id AND e.empresa_id = ?
                     WHERE ecc.entidad_id = ? AND ecc.tabla_estado_registro_id = 1 AND ecc.f_desde <= ? AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= ?)
                     ORDER BY ecc.f_desde DESC, ecc.entidad_condicion_cliente_id DESC LIMIT 1",
                    'iiss', [$empresa_id, $entidad_id, $fecha, $fecha]);
                $cliente_pct = (float) ($r[0]['cliente_descuento_general'] ?? 0);
            }
        }
        $cliente_pct = max(0.0, min(100.0, (float) $cliente_pct));
        $usa = prv_empresa_usa($conexion, $empresa_id);

        // Cantidad por producto en todo el comprobante y categoría de cada producto
        $cant_producto = [];
        foreach ($lineas as $l) {
            $pid = intval($l['producto_id'] ?? 0);
            $cant_producto[$pid] = ($cant_producto[$pid] ?? 0) + (float) ($l['cantidad'] ?? 0);
        }
        $categorias = [];
        $reglas = [];
        $tope_lista = null;
        $canal_id = null;
        if ($usa) {
            $pids = array_keys(array_filter($cant_producto, fn($v, $k) => $k > 0, ARRAY_FILTER_USE_BOTH));
            if ($pids) {
                foreach (prv_filas($conexion, "SELECT producto_id, producto_categoria_id FROM gestion__productos WHERE empresa_id = ? AND producto_id IN ("
                        . implode(',', array_fill(0, count($pids), '?')) . ")", 'i' . str_repeat('i', count($pids)), array_merge([$empresa_id], $pids)) as $p) {
                    $categorias[(int) $p['producto_id']] = (int) ($p['producto_categoria_id'] ?? 0);
                }
            }
            $reglas = prv_reglas_vigentes($conexion, $empresa_id, $fecha, $pids, array_values($categorias));
            $tope_lista = prv_tope_lista($conexion, $empresa_id, $lista_id);
            $canal = cvta_canal_lista($conexion, $empresa_id, $lista_id);
            $canal_id = $canal ? $canal['canal_venta_id'] : null;
        }
        $tope_usuario = $usa ? prv_tope_usuario($conexion, $empresa_id, $usuario_id, $fecha) : 100.0;

        $out = [];
        foreach ($lineas as $clave => $l) {
            $pid = intval($l['producto_id'] ?? 0);
            $pres = intval($l['presentacion_id'] ?? 0);
            $cant = $cant_producto[$pid] ?? 0;
            $cand = [['tipo' => 'CLIENTE', 'id' => null, 'nombre' => 'Descuento del cliente', 'pct' => $cliente_pct, 'prioridad' => 0]];
            foreach ($reglas as $r) {
                if ($r['producto_id'] !== null && (int) $r['producto_id'] !== $pid) continue;
                if ($r['producto_id'] === null && $r['producto_categoria_id'] !== null && (int) $r['producto_categoria_id'] !== ($categorias[$pid] ?? -1)) continue;
                if ($r['presentacion_id'] !== null && (int) $r['presentacion_id'] !== $pres) continue;
                if ($r['lista_precio_id'] !== null && (int) $r['lista_precio_id'] !== $lista_id) continue;
                if ($r['canal_venta_id'] !== null && (int) $r['canal_venta_id'] !== $canal_id) continue;
                if ($r['sucursal_id'] !== null && (int) $r['sucursal_id'] !== $sucursal_id) continue;
                $pct = null;
                if ($r['tipo'] === 'PROMOCION') {
                    $pct = (float) $r['descuento_pct'];
                } elseif ($r['tipo'] === 'ESCALA') {
                    foreach ($r['tramos'] as $t) {
                        if ($cant + 0.00001 >= $t['desde']) $pct = $t['pct'];
                    }
                }
                if ($pct !== null && $pct > 0) {
                    $cand[] = ['tipo' => $r['tipo'], 'id' => (int) $r['regla_venta_id'], 'nombre' => $r['nombre'], 'pct' => min(100.0, $pct), 'prioridad' => (int) $r['prioridad']];
                }
            }
            usort($cand, function ($a, $b) {
                if (abs($a['pct'] - $b['pct']) > 0.00001) return $b['pct'] <=> $a['pct'];
                if ($a['tipo'] !== $b['tipo']) return PRV_ORDEN_TIPO[$b['tipo']] <=> PRV_ORDEN_TIPO[$a['tipo']];
                if ($a['prioridad'] !== $b['prioridad']) return $b['prioridad'] <=> $a['prioridad'];
                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            });
            $gana = $cand[0];
            $auto = $gana['pct'];
            if ($tope_lista !== null && $auto > $tope_lista) {
                $auto = $tope_lista;
            }
            $auto = prv_r2($auto);
            $tipo = $auto > 0 ? $gana['tipo'] : 'NINGUNA';

            // Manual: el que viene explícito o lo que el total de la pantalla trae por encima del automático
            if (isset($l['descuento_manual_pct']) && $l['descuento_manual_pct'] !== '' && $l['descuento_manual_pct'] !== null) {
                $manual = max(0.0, min(100.0, (float) $l['descuento_manual_pct']));
            } elseif (isset($l['descuento_total_pct']) && $l['descuento_total_pct'] !== '' && $l['descuento_total_pct'] !== null) {
                $total_pantalla = max(0.0, min(100.0, (float) $l['descuento_total_pct']));
                if ($total_pantalla <= $auto + 0.005) {
                    // La pantalla dio menos (o lo mismo) que lo que corresponde: se registra lo que dio, sin manual
                    $auto = prv_r2($total_pantalla);
                    $manual = 0.0;
                } else {
                    $manual = $auto >= 100 ? 0.0 : (1 - (1 - $total_pantalla / 100) / (1 - $auto / 100)) * 100;
                }
            } else {
                $manual = 0.0;
            }
            $manual = prv_r2($manual);
            $total = prv_r2((1 - (1 - $auto / 100) * (1 - $manual / 100)) * 100);
            $out[$clave] = [
                'cliente_pct' => $cliente_pct,
                'candidatos' => $cand,
                'regla_tipo' => $usa ? ($auto > 0 ? $tipo : 'NINGUNA') : null,
                'regla_id' => ($usa && $auto > 0) ? $gana['id'] : null,
                'regla_nombre' => ($usa && $auto > 0) ? $gana['nombre'] : null,
                'auto_pct' => $auto,
                'tope_lista' => $tope_lista,
                'manual_pct' => $manual,
                'total_pct' => $total,
                'tope_usuario' => $tope_usuario,
                'requiere_aprobacion' => $usa && $manual > $tope_usuario + 0.005,
                'cantidad_producto' => $cant,
            ];
        }
        return $out;
    }
}

if (!function_exists('prv_guardar_linea')) {
    /**
     * Graba la regla aplicada en una línea (UPDATE aparte de los INSERT / UPDATE existentes, regla 8). $res es la
     * fila de precio_resolver; $aprobado_por = usuario cuyo tope cubre el manual (null = pendiente o sin manual).
     */
    function prv_guardar_linea(mysqli $conexion, string $tipo, int $detalle_id, array $res, ?int $aprobado_por): void
    {
        if ($detalle_id <= 0 || !prv_columnas_lineas($conexion, $tipo)) {
            return;
        }
        [$tabla, $pk] = PRV_TABLAS_LINEAS[$tipo];
        $regla_tipo = $res['regla_tipo'] ?? null;
        $regla_id = $res['regla_id'] ?? null;
        $auto = isset($res['auto_pct']) ? (float) $res['auto_pct'] : null;
        $manual = isset($res['manual_pct']) ? (float) $res['manual_pct'] : null;
        $aprob = ($manual !== null && $manual > 0 && $aprobado_por) ? $aprobado_por : null;
        $st = mysqli_prepare($conexion, "UPDATE `$tabla` SET precio_regla_tipo = ?, precio_regla_id = ?, descuento_auto_pct = ?, descuento_manual_pct = ?,
                                         descuento_aprobado_por = ?, descuento_aprobado_en = IF(? IS NULL, NULL, NOW()) WHERE `$pk` = ?");
        mysqli_stmt_bind_param($st, "siddiii", $regla_tipo, $regla_id, $auto, $manual, $aprob, $aprob, $detalle_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
}

if (!function_exists('prv_heredar')) {
    /** Copia la regla aplicada de la línea de origen (remito desde pedido, factura desde remito o pedido). */
    function prv_heredar(mysqli $conexion, string $tipo_destino, int $detalle_id, string $tipo_origen, int $origen_detalle_id): void
    {
        if ($detalle_id <= 0 || $origen_detalle_id <= 0 || !prv_columnas_lineas($conexion, $tipo_destino) || !prv_columnas_lineas($conexion, $tipo_origen)) {
            return;
        }
        [$to, $po] = PRV_TABLAS_LINEAS[$tipo_origen];
        [$td, $pd] = PRV_TABLAS_LINEAS[$tipo_destino];
        $set = implode(', ', array_map(fn($c) => "d.`$c` = o.`$c`", PRV_COLUMNAS_LINEA));
        $st = mysqli_prepare($conexion, "UPDATE `$td` d INNER JOIN `$to` o ON o.`$po` = ? SET $set WHERE d.`$pd` = ? AND o.precio_regla_tipo IS NOT NULL");
        mysqli_stmt_bind_param($st, "ii", $origen_detalle_id, $detalle_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
}

if (!function_exists('prv_lineas_comprobante')) {
    /** Reglas aplicadas en las líneas de un comprobante [detalle_id => columnas] (para mostrarlas y para confirmar). */
    function prv_lineas_comprobante(mysqli $conexion, string $tipo, array $detalle_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $detalle_ids))));
        if (!$ids || !prv_columnas_lineas($conexion, $tipo)) {
            return [];
        }
        [$tabla, $pk] = PRV_TABLAS_LINEAS[$tipo];
        $out = [];
        foreach (prv_filas($conexion, "SELECT `$pk` AS id, " . implode(', ', PRV_COLUMNAS_LINEA) . " FROM `$tabla` WHERE `$pk` IN ("
                . implode(',', array_fill(0, count($ids), '?')) . ")", str_repeat('i', count($ids)), $ids) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }
}

if (!function_exists('prv_aprobar_pendientes')) {
    /**
     * Al confirmar: las líneas con descuento manual sin aprobar necesitan que el tope de quien confirma lo cubra.
     * Si alcanza, quedan aprobadas por él; si no, devuelve el mensaje (y no graba nada). Sin el indicador: null.
     */
    function prv_aprobar_pendientes(mysqli $conexion, int $empresa_id, string $tipo, array $detalle_ids, int $usuario_id): ?string
    {
        if (!prv_activo($conexion, $empresa_id, $tipo)) {
            return null;
        }
        $lineas = prv_lineas_comprobante($conexion, $tipo, $detalle_ids);
        $pend = array_filter($lineas, fn($l) => (float) ($l['descuento_manual_pct'] ?? 0) > 0 && empty($l['descuento_aprobado_por']));
        if (!$pend) {
            return null;
        }
        $max = max(array_map(fn($l) => (float) $l['descuento_manual_pct'], $pend));
        $tope = prv_tope_usuario($conexion, $empresa_id, $usuario_id);
        if ($max > $tope + 0.005) {
            return 'Hay un descuento manual de ' . rtrim(rtrim(number_format($max, 2, ',', ''), '0'), ',') . ' % que supera tu tope ('
                . rtrim(rtrim(number_format($tope, 2, ',', ''), '0'), ',') . ' %). Lo tiene que confirmar alguien con un tope mayor.';
        }
        [$tabla, $pk] = PRV_TABLAS_LINEAS[$tipo];
        $st = mysqli_prepare($conexion, "UPDATE `$tabla` SET descuento_aprobado_por = ?, descuento_aprobado_en = NOW() WHERE `$pk` = ?");
        foreach (array_keys($pend) as $id) {
            mysqli_stmt_bind_param($st, "ii", $usuario_id, $id);
            mysqli_stmt_execute($st);
        }
        mysqli_stmt_close($st);
        return null;
    }
}
