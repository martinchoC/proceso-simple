<?php
require_once __DIR__ . '/../db.php';

$t0 = microtime(true);

// 1. Listas de precios
$listas = [];
$res_lp = mysqli_query($conn, "SELECT lista_precio_id, lista_precio_nombre FROM gestion__listas_precios WHERE tabla_estado_registro_id = 1 AND (empresa_id = 0 OR empresa_id = 2) ORDER BY lista_precio_id");
while ($r = mysqli_fetch_assoc($res_lp)) {
    $listas[$r['lista_precio_id']] = $r['lista_precio_nombre'];
}

// 2. Precios por producto
$precios_por_prod = [];
$res_prec = mysqli_query($conn, "SELECT producto_id, lista_precio_id, precio_final FROM gestion__listas_precios_productos WHERE tabla_estado_registro_id = 1");
while ($r = mysqli_fetch_assoc($res_prec)) {
    $precios_por_prod[$r['producto_id']][$r['lista_precio_id']] = (float)$r['precio_final'];
}

// 3. Compatibilidades por producto
$compat_por_prod = [];
$res_c = mysqli_query($conn, "SELECT pc.producto_id, ma.marca_nombre, mo.modelo_nombre, sm.submodelo_nombre, pc.anio_desde, pc.anio_hasta 
    FROM gestion__productos_compatibilidad pc
    JOIN gestion__marcas ma ON pc.marca_id = ma.marca_id
    JOIN gestion__modelos mo ON pc.modelo_id = mo.modelo_id
    LEFT JOIN gestion__submodelos sm ON pc.submodelo_id = sm.submodelo_id
    WHERE pc.empresa_id = 2 AND pc.tabla_estado_registro_id = 1
    ORDER BY pc.producto_id, ma.marca_nombre, mo.modelo_nombre");
while ($r = mysqli_fetch_assoc($res_c)) {
    $anios = '';
    if (!empty($r['anio_desde']) || !empty($r['anio_hasta'])) {
        $d = $r['anio_desde'] ?: '?';
        $h = $r['anio_hasta'] ?: '?';
        $anios = ($d === $h) ? $d : "$d-$h";
    }
    $compat_por_prod[$r['producto_id']][] = [
        'marca' => $r['marca_nombre'],
        'modelo' => $r['modelo_nombre'],
        'submodelo' => $r['submodelo_nombre'] ?: '',
        'anios' => $anios
    ];
}

// 4. Ubicaciones por producto
$ubic_por_prod = [];
$res_u = mysqli_query($conn, "SELECT pu.producto_id, s.sucursal_nombre, b.boca_nombre, su.seccion, su.estanteria, su.estante, su.posicion, su.descripcion
    FROM gestion__productos_ubicaciones pu
    JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
    LEFT JOIN gestion__sucursales s ON su.sucursal_id = s.sucursal_id
    LEFT JOIN gestion__bocas b ON su.boca_id = b.boca_id
    WHERE pu.tabla_estado_registro_id = 1");
while ($r = mysqli_fetch_assoc($res_u)) {
    $ubic_por_prod[$r['producto_id']][] = [
        'sucursal' => $r['sucursal_nombre'] ?: '',
        'boca' => $r['boca_nombre'] ?: '',
        'seccion' => $r['seccion'] ?: '',
        'estanteria' => $r['estanteria'] ?: '',
        'estante' => $r['estante'] ?: '',
        'posicion' => $r['posicion'] ?: '',
        'descripcion' => $r['descripcion'] ?: ''
    ];
}

// 5. Proveedores por producto
$prov_por_prod = [];
$res_p = mysqli_query($conn, "SELECT pp.producto_id, e.entidad_nombre, pp.codigo_proveedor
    FROM gestion__productos_proveedores pp
    LEFT JOIN gestion__entidades e ON pp.entidad_id = e.entidad_id
    WHERE pp.empresa_id = 2 AND pp.tabla_estado_registro_id = 1");
while ($r = mysqli_fetch_assoc($res_p)) {
    $prov_por_prod[$r['producto_id']][] = [
        'proveedor' => $r['entidad_nombre'] ?: '',
        'codigo' => $r['codigo_proveedor'] ?: ''
    ];
}

// 6. Stock por producto
$stock_por_prod = [];
$res_s = mysqli_query($conn, "SELECT producto_id, SUM(cantidad) as stock_total FROM gestion__stock WHERE empresa_id = 2 GROUP BY producto_id");
while ($r = mysqli_fetch_assoc($res_s)) {
    $stock_por_prod[$r['producto_id']] = (float)$r['stock_total'];
}

// 7. Productos base
$sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.producto_descripcion, p.codigo_barras,
               um.unidad_abreviatura, er.estado_registro, IFNULL(iva.porcentaje, 21.0) as iva_porcentaje
        FROM gestion__productos p
        LEFT JOIN conf__estados_registros er ON p.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN gestion__unidades_medida um ON p.unidad_medida_id = um.unidad_medida_id
        LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id AND iva.tabla_estado_registro_id = 1
        WHERE p.empresa_id = 2 AND p.controla_stock = 1
        ORDER BY p.producto_codigo ASC";
$res_prod = mysqli_query($conn, $sql);
$productos = [];
while ($row = mysqli_fetch_assoc($res_prod)) {
    $pid = $row['producto_id'];
    $iva = (float)$row['iva_porcentaje'];
    
    // Armar precios
    $precios = [];
    if (isset($precios_por_prod[$pid])) {
        foreach ($precios_por_prod[$pid] as $lid => $neto) {
            $con_iva = round($neto * (1 + $iva / 100), 2);
            $precios[$lid] = [
                'neto' => $neto,
                'con_iva' => $con_iva
            ];
        }
    }
    
    $productos[] = [
        'id' => (int)$pid,
        'codigo' => $row['producto_codigo'],
        'nombre' => $row['producto_nombre'],
        'descripcion' => $row['producto_descripcion'] ?: '',
        'barras' => $row['codigo_barras'] ?: '',
        'unidad' => $row['unidad_abreviatura'] ?: 'u',
        'estado' => $row['estado_registro'] ?: 'Activo',
        'iva' => $iva,
        'stock' => $stock_por_prod[$pid] ?? null,
        'precios' => $precios,
        'compat' => $compat_por_prod[$pid] ?? [],
        'ubicaciones' => $ubic_por_prod[$pid] ?? [],
        'proveedores' => $prov_por_prod[$pid] ?? []
    ];
}

$t1 = microtime(true);
$json = json_encode($productos, JSON_UNESCAPED_UNICODE);
$t2 = microtime(true);

echo "Total productos cargados: " . count($productos) . "\n";
echo "Tiempo consultas y procesamiento: " . round(($t1 - $t0) * 1000, 2) . " ms\n";
echo "Tiempo json_encode: " . round(($t2 - $t1) * 1000, 2) . " ms\n";
echo "Tamaño JSON: " . round(strlen($json) / 1024, 2) . " KB (" . round(strlen($json) / (1024*1024), 2) . " MB)\n";
echo "Muestra producto 0: " . substr($json, 0, 300) . "...\n";
