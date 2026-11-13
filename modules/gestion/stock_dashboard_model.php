<?php
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * stock_dashboard_model.php — Dashboard de stock (página 114). Solo lectura.
 *
 * Fuentes:
 * - gestion__stock: saldo materializado por (empresa, boca, producto) + costo promedio de la boca.
 * - gestion__stock_movimientos: libro inmutable (cantidad > 0, dirección en tipo_movimiento).
 * Ambas las escribe solo stock_core_model.php; acá no se modifica nada.
 *
 * Toda consulta filtra por empresa y por las sucursales que el usuario puede ver
 * (AccesosSucursalesHelper::filtroSqlSucursal sobre sucursal_id, desnormalizado en ambas tablas).
 */
if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
}

function sdbFiltroSucursal(mysqli $conexion, int $usuario_id, int $empresa_id, string $columna): string
{
    if (!class_exists('AccesosSucursalesHelper')) return '';
    return AccesosSucursalesHelper::filtroSqlSucursal($conexion, $usuario_id, $empresa_id, $columna);
}

/**
 * Filtros de producto (alias p = gestion__productos) que comparten la grilla de stock, el blanqueo de negativos y la
 * planilla de conteo: categoria_id, producto_id, texto (search[value] de DataTables o buscar) y compatibilidad
 * (marca_id / modelo_id / submodelo_id en la MISMA fila de gestion__productos_compatibilidad, como productos.php;
 * solo la manda Gestión, módulo 2). Devuelve el SQL a agregar al WHERE y suma tipos y parámetros.
 */
function sdbFiltroProductos(array $req, string &$types, array &$params): string
{
    $sql = '';
    $categoria_id = intval($req['categoria_id'] ?? 0);
    $producto_id = intval($req['producto_id'] ?? 0);
    if ($categoria_id > 0) {
        $sql .= " AND p.producto_categoria_id = ?";
        $types .= 'i';
        $params[] = $categoria_id;
    }
    if ($producto_id > 0) {
        $sql .= " AND p.producto_id = ?";
        $types .= 'i';
        $params[] = $producto_id;
    }
    $buscar = trim((string)($req['search']['value'] ?? ($req['buscar'] ?? '')));
    // Cada palabra tiene que estar en el código, el nombre o el código de barras
    foreach (array_slice(preg_split('/\s+/', $buscar, -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $palabra) {
        $like = '%' . $palabra . '%';
        $sql .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras LIKE ?)";
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    $compat = [];
    foreach (['marca_id', 'modelo_id', 'submodelo_id'] as $campo) {
        $id = intval($req[$campo] ?? 0);
        if ($id > 0) {
            $compat[] = "pcx.$campo = ?";
            $types .= 'i';
            $params[] = $id;
        }
    }
    if ($compat) {
        $sql .= " AND EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pcx
                              WHERE pcx.producto_id = p.producto_id AND pcx.empresa_id = p.empresa_id
                                AND pcx.tabla_estado_registro_id = 1 AND " . implode(' AND ', $compat) . ")";
    }
    return $sql;
}

function sdbConsultar(mysqli $conexion, string $sql, string $types, array $params): array
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta: ' . mysqli_error($conexion));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $filas;
}

/** Fecha 'YYYY-MM-DD' válida o el default. */
function sdbFecha(?string $valor, string $default): string
{
    $valor = trim((string)$valor);
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    return ($d && $d->format('Y-m-d') === $valor) ? $valor : $default;
}

/** Rango de fechas normalizado (desde <= hasta, máximo 3 años). */
function sdbRango(?string $desde, ?string $hasta): array
{
    $hasta = sdbFecha($hasta, date('Y-m-d'));
    $desde = sdbFecha($desde, date('Y-m-d', strtotime($hasta . ' -29 days')));
    if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
    $minimo = date('Y-m-d', strtotime($hasta . ' -3 years'));
    if ($desde < $minimo) $desde = $minimo;
    return [$desde, $hasta];
}

function sdbBocas(mysqli $conexion, int $empresa_id, int $usuario_id): array
{
    $bocas = sdbConsultar($conexion, "SELECT b.boca_id, b.sucursal_id, b.boca_nombre, b.codigo, s.sucursal_nombre
                                      FROM gestion__bocas b
                                      JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
                                      WHERE b.empresa_id = ? AND b.es_deposito = 1
                                      ORDER BY s.sucursal_nombre, b.orden, b.boca_nombre", 'i', [$empresa_id]);
    if (class_exists('AccesosSucursalesHelper')) {
        $bocas = AccesosSucursalesHelper::filtrarBocas($conexion, $empresa_id, $bocas, $usuario_id);
    }
    return array_values($bocas);
}

function sdbCategorias(mysqli $conexion, int $empresa_id): array
{
    return sdbConsultar($conexion, "SELECT producto_categoria_id, producto_categoria_nombre
                                    FROM gestion__productos_categorias
                                    WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                    ORDER BY producto_categoria_nombre", 'i', [$empresa_id]);
}

/** Tipos de comprobante que movieron stock (combo de filtro del libro). */
function sdbTiposMovimiento(mysqli $conexion, int $empresa_id): array
{
    return sdbConsultar($conexion, "SELECT DISTINCT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo
                                    FROM gestion__stock_movimientos m
                                    JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = m.comprobante_tipo_id
                                    WHERE m.empresa_id = ?
                                    ORDER BY ct.comprobante_tipo", 'i', [$empresa_id]);
}

/**
 * KPIs, serie diaria, movimientos por tipo de comprobante y productos con más egresos.
 */
function sdbResumen(mysqli $conexion, int $empresa_id, int $usuario_id, int $boca_id, string $desde, string $hasta): array
{
    // --- Saldos actuales (gestion__stock), agrupados por producto ---
    $sql = "SELECT COUNT(*) AS productos,
                   SUM(x.cant > 0) AS con_stock,
                   SUM(x.cant < 0) AS negativos,
                   SUM(CASE WHEN x.cant > 0 THEN x.cant ELSE 0 END) AS unidades,
                   SUM(x.valorizado) AS valorizado
            FROM (SELECT s.producto_id, SUM(s.cantidad) AS cant,
                         SUM(CASE WHEN s.cantidad > 0 THEN s.cantidad * s.costo_promedio ELSE 0 END) AS valorizado
                  FROM gestion__stock s
                  WHERE s.empresa_id = ?";
    $types = 'i';
    $params = [$empresa_id];
    if ($boca_id > 0) {
        $sql .= " AND s.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    $sql .= sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 's.sucursal_id');
    $sql .= " GROUP BY s.producto_id) x";
    $saldos = sdbConsultar($conexion, $sql, $types, $params)[0] ?? [];

    // --- Alertas de reposición abiertas ---
    $sql = "SELECT COUNT(*) AS n
            FROM gestion__stock_alertas a
            JOIN gestion__bocas b ON b.boca_id = a.boca_id
            WHERE a.empresa_id = ? AND a.abierta = 1";
    $types = 'i';
    $params = [$empresa_id];
    if ($boca_id > 0) {
        $sql .= " AND a.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    $sql .= sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 'b.sucursal_id');
    $alertas = intval(sdbConsultar($conexion, $sql, $types, $params)[0]['n'] ?? 0);

    // --- Filtro común del libro en el período ---
    $where = " WHERE m.empresa_id = ? AND m.f_movimiento >= ? AND m.f_movimiento < DATE_ADD(?, INTERVAL 1 DAY)";
    $wTypes = 'iss';
    $wParams = [$empresa_id, $desde, $hasta];
    if ($boca_id > 0) {
        $where .= " AND m.boca_id = ?";
        $wTypes .= 'i';
        $wParams[] = $boca_id;
    }
    $where .= sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 'm.sucursal_id');

    $totales = sdbConsultar($conexion, "SELECT COUNT(*) AS movimientos,
                                               COUNT(DISTINCT m.producto_id) AS productos,
                                               SUM(CASE WHEN m.tipo_movimiento = 'INGRESO' THEN m.cantidad ELSE 0 END) AS ingresos,
                                               SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN m.cantidad ELSE 0 END) AS egresos
                                        FROM gestion__stock_movimientos m" . $where, $wTypes, $wParams)[0] ?? [];

    $serie = sdbConsultar($conexion, "SELECT DATE(m.f_movimiento) AS fecha,
                                             SUM(CASE WHEN m.tipo_movimiento = 'INGRESO' THEN m.cantidad ELSE 0 END) AS ingresos,
                                             SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN m.cantidad ELSE 0 END) AS egresos
                                      FROM gestion__stock_movimientos m" . $where . "
                                      GROUP BY DATE(m.f_movimiento)
                                      ORDER BY fecha", $wTypes, $wParams);

    // Por tipo (no por nombre): "Factura A" de compra y de venta son tipos distintos con el mismo nombre.
    $porTipo = sdbConsultar($conexion, "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo, m.tipo_movimiento,
                                               COUNT(*) AS movimientos, SUM(m.cantidad) AS cantidad
                                        FROM gestion__stock_movimientos m
                                        JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = m.comprobante_tipo_id" . $where . "
                                        GROUP BY ct.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo, m.tipo_movimiento
                                        ORDER BY cantidad DESC", $wTypes, $wParams);

    $topEgresos = sdbConsultar($conexion, "SELECT m.producto_id, p.producto_codigo, p.producto_nombre,
                                                  SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN m.cantidad ELSE -m.cantidad END) AS egresos_netos
                                           FROM gestion__stock_movimientos m
                                           JOIN gestion__productos p ON p.producto_id = m.producto_id" . $where . "
                                           GROUP BY m.producto_id, p.producto_codigo, p.producto_nombre
                                           HAVING egresos_netos > 0
                                           ORDER BY egresos_netos DESC
                                           LIMIT 10", $wTypes, $wParams);

    return [
        'desde' => $desde,
        'hasta' => $hasta,
        'kpis' => [
            'productos_con_stock' => intval($saldos['con_stock'] ?? 0),
            'productos_negativos' => intval($saldos['negativos'] ?? 0),
            'unidades' => (float)($saldos['unidades'] ?? 0),
            'valorizado' => (float)($saldos['valorizado'] ?? 0),
            'alertas_abiertas' => $alertas,
            'movimientos' => intval($totales['movimientos'] ?? 0),
            'productos_movidos' => intval($totales['productos'] ?? 0),
            'ingresos' => (float)($totales['ingresos'] ?? 0),
            'egresos' => (float)($totales['egresos'] ?? 0),
        ],
        'serie' => $serie,
        'por_tipo' => $porTipo,
        'top_egresos' => $topEgresos,
    ];
}

/**
 * Stock por producto, server-side para DataTables.
 *
 * $req['filtro']: 'movidos' (con fila en gestion__stock), 'con_stock', 'negativos',
 *                 'bajo_minimo' (alerta abierta) o 'todos' (todo producto activo que controla stock)
 * @return array {draw, recordsTotal, recordsFiltered, data}
 */
function sdbListarStock(mysqli $conexion, int $empresa_id, int $usuario_id, array $req): array
{
    $boca_id = intval($req['boca_id'] ?? 0);
    $sucursal_id = intval($req['sucursal_id'] ?? 0);   // opcional (Stock y Movimientos, página 138)
    $filtro = (string)($req['filtro'] ?? 'movidos');
    $start = max(0, intval($req['start'] ?? 0));
    $length = intval($req['length'] ?? 25);
    // La planilla de conteo de Stock y Movimientos pide todo lo filtrado (tope 5000); la grilla, de a una página
    $tope = !empty($req['reporte']) ? 5000 : 500;
    $length = ($length < 1 || $length > $tope) ? 25 : $length;

    $filtroSuc = sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 's.sucursal_id');

    // Saldo agregado por producto (respetando boca y sucursales visibles).
    $sub = "SELECT s.producto_id, SUM(s.cantidad) AS cantidad,
                   SUM(CASE WHEN s.cantidad > 0 THEN s.cantidad * s.costo_promedio ELSE 0 END) AS valorizado,
                   MAX(s.f_ultimo_movimiento) AS f_ultimo_movimiento,
                   GROUP_CONCAT(CONCAT(s.boca_id, ':', s.cantidad) SEPARATOR ',') AS por_boca
            FROM gestion__stock s
            WHERE s.empresa_id = ?" . ($boca_id > 0 ? " AND s.boca_id = ?" : "") . ($sucursal_id > 0 ? " AND s.sucursal_id = ?" : "") . $filtroSuc . "
            GROUP BY s.producto_id";
    $subTypes = 'i' . ($boca_id > 0 ? 'i' : '') . ($sucursal_id > 0 ? 'i' : '');
    $subParams = [$empresa_id];
    if ($boca_id > 0) $subParams[] = $boca_id;
    if ($sucursal_id > 0) $subParams[] = $sucursal_id;

    $from = " FROM gestion__productos p
              LEFT JOIN ($sub) st ON st.producto_id = p.producto_id
              LEFT JOIN gestion__productos_categorias pc ON pc.producto_categoria_id = p.producto_categoria_id
              LEFT JOIN gestion__unidades_medida um ON um.unidad_medida_id = p.unidad_medida_id";
    $base = " WHERE p.empresa_id = ? AND p.controla_stock = 1";
    $baseTypes = $subTypes . 'i';
    $baseParams = array_merge($subParams, [$empresa_id]);

    switch ($filtro) {
        case 'todos':
            $base .= " AND p.tabla_estado_registro_id = 1";
            break;
        case 'con_stock':
            $base .= " AND st.cantidad > 0";
            break;
        case 'negativos':
            $base .= " AND st.cantidad < 0";
            break;
        case 'bajo_minimo':
            $base .= " AND EXISTS (SELECT 1 FROM gestion__stock_alertas a
                                   JOIN gestion__bocas b ON b.boca_id = a.boca_id
                                   WHERE a.empresa_id = p.empresa_id AND a.producto_id = p.producto_id AND a.abierta = 1"
                . ($boca_id > 0 ? " AND a.boca_id = ?" : "")
                . ($sucursal_id > 0 ? " AND b.sucursal_id = ?" : "")
                . sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 'b.sucursal_id') . ")";
            if ($boca_id > 0) {
                $baseTypes .= 'i';
                $baseParams[] = $boca_id;
            }
            if ($sucursal_id > 0) {
                $baseTypes .= 'i';
                $baseParams[] = $sucursal_id;
            }
            break;
        default:
            $base .= " AND st.producto_id IS NOT NULL";
    }

    $where = $base;
    $types = $baseTypes;
    $params = $baseParams;
    $where .= sdbFiltroProductos($req, $types, $params);

    $total = intval(sdbConsultar($conexion, "SELECT COUNT(*) AS n" . $from . $base, $baseTypes, $baseParams)[0]['n'] ?? 0);
    $filtrados = ($where === $base) ? $total
        : intval(sdbConsultar($conexion, "SELECT COUNT(*) AS n" . $from . $where, $types, $params)[0]['n'] ?? 0);

    $columnas = [
        'codigo' => 'p.producto_codigo',
        'nombre' => 'p.producto_nombre',
        'categoria' => 'pc.producto_categoria_nombre',
        'cantidad' => 'COALESCE(st.cantidad, 0)',
        'valorizado' => 'COALESCE(st.valorizado, 0)',
        'ultimo' => 'st.f_ultimo_movimiento',
    ];
    $orden = 'p.producto_nombre ASC';
    $ord = $req['order'][0] ?? null;
    if (is_array($ord)) {
        $colData = (string)($req['columns'][intval($ord['column'] ?? -1)]['name'] ?? '');
        if (isset($columnas[$colData])) {
            $orden = $columnas[$colData] . (strtolower((string)($ord['dir'] ?? '')) === 'desc' ? ' DESC' : ' ASC')
                . ', p.producto_id';
        }
    }

    $filas = sdbConsultar($conexion, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.codigo_barras,
                                             pc.producto_categoria_nombre, um.unidad_abreviatura,
                                             COALESCE(st.cantidad, 0) AS cantidad,
                                             COALESCE(st.valorizado, 0) AS valorizado,
                                             st.f_ultimo_movimiento, st.por_boca,
                                             (SELECT COUNT(*) FROM gestion__stock_alertas a
                                              WHERE a.empresa_id = p.empresa_id AND a.producto_id = p.producto_id
                                                AND a.abierta = 1) AS alertas"
        . $from . $where . " ORDER BY $orden LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$length, $start]));

    foreach ($filas as &$f) {
        $mapa = [];
        foreach (array_filter(explode(',', (string)$f['por_boca'])) as $par) {
            [$b, $c] = array_pad(explode(':', $par, 2), 2, 0);
            $mapa[intval($b)] = (float)$c;
        }
        $f['por_boca'] = (object)$mapa;
    }
    unset($f);

    $resultado = [
        'draw' => intval($req['draw'] ?? 0),
        'recordsTotal' => $total,
        'recordsFiltered' => $filtrados,
        'data' => $filas,
    ];
    // Totales de todo lo filtrado (no solo la página): los pide Stock y Movimientos
    if (!empty($req['con_totales'])) {
        $t = sdbConsultar($conexion, "SELECT COUNT(*) AS productos, COALESCE(SUM(COALESCE(st.cantidad, 0)), 0) AS unidades,
                                             COALESCE(SUM(COALESCE(st.valorizado, 0)), 0) AS valorizado,
                                             COALESCE(SUM(st.cantidad < 0), 0) AS negativos"
            . $from . $where, $types, $params)[0] ?? [];
        $resultado['totales'] = ['productos' => intval($t['productos'] ?? 0), 'unidades' => (float)($t['unidades'] ?? 0),
                                 'valorizado' => (float)($t['valorizado'] ?? 0), 'negativos' => intval($t['negativos'] ?? 0)];
    }
    return $resultado;
}

/**
 * Libro de movimientos, server-side para DataTables.
 */
function sdbListarMovimientos(mysqli $conexion, int $empresa_id, int $usuario_id, array $req): array
{
    [$desde, $hasta] = sdbRango($req['desde'] ?? null, $req['hasta'] ?? null);
    $boca_id = intval($req['boca_id'] ?? 0);
    $sucursal_id = intval($req['sucursal_id'] ?? 0);   // opcional (Stock y Movimientos, página 138)
    $producto_id = intval($req['producto_id'] ?? 0);   // opcional (ídem)
    $categoria_id = intval($req['categoria_id'] ?? 0); // opcional (ídem)
    $tipo_id = intval($req['comprobante_tipo_id'] ?? 0);
    $direccion = (string)($req['direccion'] ?? '');
    $buscar = trim((string)($req['search']['value'] ?? ''));
    $start = max(0, intval($req['start'] ?? 0));
    $length = intval($req['length'] ?? 25);
    $length = ($length < 1 || $length > 500) ? 25 : $length;

    $from = " FROM gestion__stock_movimientos m
              JOIN gestion__productos p ON p.producto_id = m.producto_id
              JOIN gestion__bocas b ON b.boca_id = m.boca_id
              JOIN gestion__sucursales su ON su.sucursal_id = b.sucursal_id
              JOIN gestion__comprobantes c ON c.comprobante_id = m.comprobante_id
              JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = m.comprobante_tipo_id
              LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
              LEFT JOIN conf__usuarios u ON u.usuario_id = m.usuario_id";
    $base = " WHERE m.empresa_id = ? AND m.f_movimiento >= ? AND m.f_movimiento < DATE_ADD(?, INTERVAL 1 DAY)"
        . sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 'm.sucursal_id');
    $baseTypes = 'iss';
    $baseParams = [$empresa_id, $desde, $hasta];

    $where = $base;
    $types = $baseTypes;
    $params = $baseParams;
    if ($boca_id > 0) {
        $where .= " AND m.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    if ($sucursal_id > 0) {
        $where .= " AND m.sucursal_id = ?";
        $types .= 'i';
        $params[] = $sucursal_id;
    }
    if ($producto_id > 0) {
        $where .= " AND m.producto_id = ?";
        $types .= 'i';
        $params[] = $producto_id;
    }
    if ($categoria_id > 0) {
        $where .= " AND p.producto_categoria_id = ?";
        $types .= 'i';
        $params[] = $categoria_id;
    }
    if ($tipo_id > 0) {
        $where .= " AND m.comprobante_tipo_id = ?";
        $types .= 'i';
        $params[] = $tipo_id;
    }
    if ($direccion === 'INGRESO' || $direccion === 'EGRESO') {
        $where .= " AND m.tipo_movimiento = ?";
        $types .= 's';
        $params[] = $direccion;
    }
    if ($buscar !== '') {
        $like = '%' . $buscar . '%';
        $where .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR e.entidad_nombre LIKE ? OR e.entidad_fantasia LIKE ?
                         OR ct.comprobante_tipo LIKE ? OR CAST(c.comprobante_nro AS CHAR) = ?)";
        $types .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $buscar);
    }

    $total = intval(sdbConsultar($conexion, "SELECT COUNT(*) AS n FROM gestion__stock_movimientos m" . $base,
        $baseTypes, $baseParams)[0]['n'] ?? 0);
    $filtrados = ($where === $base) ? $total
        : intval(sdbConsultar($conexion, "SELECT COUNT(*) AS n" . $from . $where, $types, $params)[0]['n'] ?? 0);

    $columnas = [
        'fecha' => 'm.f_movimiento',
        'boca' => 'b.boca_nombre',
        'codigo' => 'p.producto_codigo',
        'producto' => 'p.producto_nombre',
        'comprobante' => 'ct.comprobante_tipo',
        'entidad' => "COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre)",
        'cantidad' => "(CASE WHEN m.tipo_movimiento = 'INGRESO' THEN m.cantidad ELSE -m.cantidad END)",
    ];
    $orden = 'm.f_movimiento DESC, m.stock_movimiento_id DESC';
    $ord = $req['order'][0] ?? null;
    if (is_array($ord)) {
        $colData = (string)($req['columns'][intval($ord['column'] ?? -1)]['name'] ?? '');
        if (isset($columnas[$colData])) {
            $orden = $columnas[$colData] . (strtolower((string)($ord['dir'] ?? '')) === 'desc' ? ' DESC' : ' ASC')
                . ', m.stock_movimiento_id DESC';
        }
    }

    $filas = sdbConsultar($conexion, "SELECT m.stock_movimiento_id, m.f_movimiento, m.tipo_movimiento, m.cantidad,
                                             m.costo_unitario, m.observaciones, m.producto_id,
                                             p.producto_codigo, p.producto_nombre,
                                             b.boca_nombre, su.sucursal_nombre,
                                             ct.comprobante_tipo, ct.codigo AS comprobante_tipo_codigo,
                                             " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro, c.f_emision,
                                             COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, u.usuario_nombre"
        . $from . $where . " ORDER BY $orden LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$length, $start]));

    $resultado = [
        'draw' => intval($req['draw'] ?? 0),
        'recordsTotal' => $total,
        'recordsFiltered' => $filtrados,
        'data' => $filas,
    ];
    // Totales de todo lo filtrado: unidades que entraron y salieron (Stock y Movimientos)
    if (!empty($req['con_totales'])) {
        $t = sdbConsultar($conexion, "SELECT COUNT(*) AS movimientos,
                                             COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'INGRESO' THEN m.cantidad ELSE 0 END), 0) AS ingresos,
                                             COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'EGRESO' THEN m.cantidad ELSE 0 END), 0) AS egresos"
            . $from . $where, $types, $params)[0] ?? [];
        $resultado['totales'] = ['movimientos' => intval($t['movimientos'] ?? 0), 'ingresos' => (float)($t['ingresos'] ?? 0),
                                 'egresos' => (float)($t['egresos'] ?? 0)];
    }
    return $resultado;
}

/**
 * Ficha de un producto: saldo por boca (con mínimo/máximo) y kardex del período con saldo corrido.
 * El saldo inicial sale del libro (suma de movimientos anteriores a $desde), no de gestion__stock.
 */
function sdbKardex(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, int $boca_id,
                   string $desde, string $hasta): array
{
    $producto = sdbConsultar($conexion, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.codigo_barras,
                                                p.controla_stock, pc.producto_categoria_nombre, um.unidad_abreviatura
                                         FROM gestion__productos p
                                         LEFT JOIN gestion__productos_categorias pc ON pc.producto_categoria_id = p.producto_categoria_id
                                         LEFT JOIN gestion__unidades_medida um ON um.unidad_medida_id = p.unidad_medida_id
                                         WHERE p.producto_id = ? AND p.empresa_id = ?", 'ii', [$producto_id, $empresa_id])[0] ?? null;
    if (!$producto) {
        throw new InvalidArgumentException('Producto no encontrado.');
    }

    $bocas = sdbConsultar($conexion, "SELECT s.boca_id, b.boca_nombre, su.sucursal_nombre, s.cantidad,
                                             s.costo_promedio, s.costo_ultima_compra, s.f_ultimo_movimiento,
                                             n.stock_minimo, n.stock_maximo
                                      FROM gestion__stock s
                                      JOIN gestion__bocas b ON b.boca_id = s.boca_id
                                      JOIN gestion__sucursales su ON su.sucursal_id = b.sucursal_id
                                      LEFT JOIN gestion__stock_niveles n ON n.empresa_id = s.empresa_id AND n.boca_id = s.boca_id
                                                                      AND n.producto_id = s.producto_id
                                                                      AND n.tabla_estado_registro_id = 1
                                      WHERE s.empresa_id = ? AND s.producto_id = ?"
        . sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 's.sucursal_id') . "
                                      ORDER BY su.sucursal_nombre, b.boca_nombre", 'ii', [$empresa_id, $producto_id]);

    $filtro = " WHERE m.empresa_id = ? AND m.producto_id = ?"
        . sdbFiltroSucursal($conexion, $usuario_id, $empresa_id, 'm.sucursal_id');
    $fTypes = 'ii';
    $fParams = [$empresa_id, $producto_id];
    if ($boca_id > 0) {
        $filtro .= " AND m.boca_id = ?";
        $fTypes .= 'i';
        $fParams[] = $boca_id;
    }

    $saldoInicial = (float)(sdbConsultar($conexion, "SELECT COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'INGRESO'
                                                                   THEN m.cantidad ELSE -m.cantidad END), 0) AS saldo
                                                     FROM gestion__stock_movimientos m" . $filtro . " AND m.f_movimiento < ?",
        $fTypes . 's', array_merge($fParams, [$desde]))[0]['saldo'] ?? 0);

    $movs = sdbConsultar($conexion, "SELECT m.stock_movimiento_id, m.f_movimiento, m.tipo_movimiento, m.cantidad,
                                            m.costo_unitario, m.observaciones, b.boca_nombre,
                                            ct.comprobante_tipo, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro,
                                            COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, u.usuario_nombre
                                     FROM gestion__stock_movimientos m
                                     JOIN gestion__bocas b ON b.boca_id = m.boca_id
                                     JOIN gestion__comprobantes c ON c.comprobante_id = m.comprobante_id
                                     JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = m.comprobante_tipo_id
                                     LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
                                     LEFT JOIN conf__usuarios u ON u.usuario_id = m.usuario_id" . $filtro . "
                                       AND m.f_movimiento >= ? AND m.f_movimiento < DATE_ADD(?, INTERVAL 1 DAY)
                                     ORDER BY m.f_movimiento, m.stock_movimiento_id
                                     LIMIT 2000", $fTypes . 'ss', array_merge($fParams, [$desde, $hasta]));

    $saldo = $saldoInicial;
    foreach ($movs as &$m) {
        $saldo += ($m['tipo_movimiento'] === 'INGRESO' ? 1 : -1) * (float)$m['cantidad'];
        $m['saldo'] = round($saldo, 4);
    }
    unset($m);

    return [
        'producto' => $producto,
        'bocas' => $bocas,
        'desde' => $desde,
        'hasta' => $hasta,
        'saldo_inicial' => round($saldoInicial, 4),
        'saldo_final' => round($saldo, 4),
        'movimientos' => $movs,
        'truncado' => count($movs) >= 2000,
    ];
}
