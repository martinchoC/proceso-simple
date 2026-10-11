<?php
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * comprobantes_consulta_model.php
 *
 * Consulta universal de comprobantes (gestion__comprobantes): todos los documentos con número
 * del ERP (pedidos, remitos, facturas, NC/ND, recibos, OP, tesorería...) con filtros.
 *
 * Es SOLO LECTURA: gestion__comprobantes es la fila espejo que mantiene syncComprobante() desde
 * cada tabla de origen; altas, ediciones y cambios de estado se hacen en la pantalla de origen.
 *
 * Saldo: se calcula con las imputaciones confirmadas (mismo criterio que
 * tesoreriaObtenerSaldoComprobante), no con la caché importe_pendiente, que está en 0 en los
 * comprobantes anteriores a tesorería. Solo se informa para tipos que participan de la matriz
 * gestion__comprobantes_tipos_cancelaciones (los demás no tienen saldo).
 */

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
}

const CCONS_LIMITE_FILAS = 5000;

/**
 * Columna con el nombre del estado en conf__estados_registros (según versión de la tabla puede
 * llamarse estado_registro, nombre_estado o descripcion). Se resuelve una vez por request.
 */
function cconsColumnaNombreEstado($conexion)
{
    static $columna = null;
    if ($columna !== null) {
        return $columna;
    }

    $columnas = [];
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM conf__estados_registros");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $columnas[] = $row['Field'];
    }

    $columna = 'estado_registro';
    if (!in_array('estado_registro', $columnas, true)) {
        if (in_array('nombre_estado', $columnas, true)) {
            $columna = 'nombre_estado';
        } elseif (in_array('descripcion', $columnas, true)) {
            $columna = 'descripcion';
        }
    }
    return $columna;
}

/**
 * SELECT base de comprobantes con estado, color, origen e imputaciones confirmadas.
 * Devuelve [sql, types, params] con el WHERE de empresa ya puesto; el llamador agrega
 * condiciones con " AND ...".
 */
function cconsSqlBase($conexion, $empresa_id)
{
    $col_estado = cconsColumnaNombreEstado($conexion);

    $sql = "SELECT c.comprobante_id, c.tabla_origen_id, c.registro_origen_id,
                   c.sucursal_id, s.sucursal_nombre,
                   c.comprobante_tipo_id, ct.codigo AS tipo_codigo, ct.comprobante_tipo, ct.letra,
                   ct.comprobante_grupo_id, cg.comprobante_grupo,
                   " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro,
                   c.f_emision, c.f_vto,
                   c.entidad_id, e.entidad_nombre, e.cuit,
                   c.moneda_id, m.simbolo AS moneda_simbolo, c.tipo_cambio,
                   c.importe_neto, c.importe_iva, c.importe_otros_impuestos, c.importe_total,
                   c.tabla_estado_registro_id AS estado_id, er.codigo_estandar,
                   COALESCE(
                       (SELECT ter.tabla_estado_registro FROM conf__tablas_estados_registros ter
                        WHERE ter.tabla_id = c.tabla_origen_id AND ter.estado_registro_id = c.tabla_estado_registro_id
                        ORDER BY ter.tabla_estado_registro_id LIMIT 1),
                       er.$col_estado
                   ) AS estado,
                   c.observaciones,
                   pag.pagina_id AS origen_pagina_id, pag.pagina AS origen_pagina, pag.url AS origen_url,
                   COALESCE(imd.imputado, 0) AS imputado_destino,
                   COALESCE(imo.imputado, 0) AS imputado_origen,
                   CASE WHEN EXISTS (SELECT 1 FROM gestion__comprobantes_tipos_cancelaciones tc
                                     WHERE tc.empresa_id = c.empresa_id AND tc.tabla_estado_registro_id = 1
                                       AND (tc.comprobante_tipo_destino_id = c.comprobante_tipo_id
                                            OR tc.comprobante_tipo_origen_id = c.comprobante_tipo_id))
                        THEN 1 ELSE 0 END AS maneja_saldo
            FROM gestion__comprobantes c
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN gestion__comprobantes_grupos cg ON cg.comprobante_grupo_id = ct.comprobante_grupo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
            LEFT JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN conf__paginas pag ON pag.pagina_id = (
                SELECT MIN(p2.pagina_id) FROM conf__paginas p2
                WHERE p2.tabla_id = c.tabla_origen_id AND c.tabla_origen_id > 0
                  AND p2.modulo_id = 2 AND p2.tabla_estado_registro_id = 1 AND p2.url NOT IN ('', '#', 'index.php')
            )
            LEFT JOIN (
                SELECT ci.comp_destino_id AS comprobante_id, SUM(ci.importe_imputado) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__estados_registros eri ON eri.estado_registro_id = ci.tabla_estado_registro_id
                WHERE ci.empresa_id = ? AND eri.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_destino_id
            ) imd ON imd.comprobante_id = c.comprobante_id
            LEFT JOIN (
                SELECT ci.comp_origen_id AS comprobante_id, SUM(ci.importe_origen) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__estados_registros eri ON eri.estado_registro_id = ci.tabla_estado_registro_id
                WHERE ci.empresa_id = ? AND eri.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_origen_id
            ) imo ON imo.comprobante_id = c.comprobante_id
            WHERE c.empresa_id = ?";

    return [$sql, "iii", [$empresa_id, $empresa_id, $empresa_id]];
}

/** Normaliza una fila del SELECT base: tipos numéricos y saldo calculado. */
function cconsNormalizarFila($r)
{
    foreach (['comprobante_id', 'tabla_origen_id', 'registro_origen_id', 'sucursal_id', 'comprobante_tipo_id',
              'comprobante_grupo_id', 'comprobante_pv', 'comprobante_nro', 'entidad_id', 'moneda_id',
              'estado_id', 'origen_pagina_id', 'maneja_saldo'] as $k) {
        $r[$k] = isset($r[$k]) ? intval($r[$k]) : null;
    }
    foreach (['tipo_cambio', 'importe_neto', 'importe_iva', 'importe_otros_impuestos', 'importe_total',
              'imputado_destino', 'imputado_origen'] as $k) {
        $r[$k] = floatval($r[$k] ?? 0);
    }
    $r['saldo'] = $r['maneja_saldo']
        ? max(0.0, round($r['importe_total'] - $r['imputado_destino'] - $r['imputado_origen'], 2))
        : null;
    return $r;
}

/**
 * Listado con filtros. Filtros soportados (todos opcionales):
 * desde, hasta (f_emision), grupo_id, tipo_id, sucursal_id, entidad_id, estado_id, moneda_id,
 * pv, numero, texto (nombre/CUIT de la entidad, código u observaciones), solo_con_saldo.
 *
 * @return array ['filas' => array, 'truncado' => bool, 'limite' => int]
 */
function cconsListar($conexion, $empresa_id, $usuario_id, array $filtros = [])
{
    $empresa_id = intval($empresa_id);
    [$sql, $types, $params] = cconsSqlBase($conexion, $empresa_id);

    if (!empty($filtros['desde'])) {
        $sql .= " AND c.f_emision >= ?";
        $types .= "s";
        $params[] = $filtros['desde'];
    }
    if (!empty($filtros['hasta'])) {
        $sql .= " AND c.f_emision <= ?";
        $types .= "s";
        $params[] = $filtros['hasta'];
    }
    $filtros_int = [
        'grupo_id'    => 'ct.comprobante_grupo_id',
        'tipo_id'     => 'c.comprobante_tipo_id',
        'sucursal_id' => 'c.sucursal_id',
        'entidad_id'  => 'c.entidad_id',
        'estado_id'   => 'c.tabla_estado_registro_id',
        'moneda_id'   => 'c.moneda_id',
        'pv'          => 'c.comprobante_pv',
        'numero'      => 'c.comprobante_nro',
    ];
    foreach ($filtros_int as $clave => $columna) {
        if (isset($filtros[$clave]) && $filtros[$clave] !== '' && $filtros[$clave] !== null && intval($filtros[$clave]) > 0) {
            $sql .= " AND $columna = ?";
            $types .= "i";
            $params[] = intval($filtros[$clave]);
        }
    }
    if (!empty($filtros['texto'])) {
        $texto = '%' . trim($filtros['texto']) . '%';
        $sql .= " AND (e.entidad_nombre LIKE ? OR CAST(e.cuit AS CHAR) LIKE ? OR ct.codigo LIKE ? OR c.observaciones LIKE ?)";
        $types .= "ssss";
        array_push($params, $texto, $texto, $texto, $texto);
    }

    // Accesos por sucursal (core/accesos_sucursales.php)
    if ($usuario_id > 0 && class_exists('AccesosSucursalesHelper')) {
        $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, intval($usuario_id), $empresa_id, 'c.sucursal_id');
    }

    if (!empty($filtros['solo_con_saldo'])) {
        $sql .= " HAVING maneja_saldo = 1 AND (importe_total - imputado_destino - imputado_origen) > 0.009";
    }

    $limite = CCONS_LIMITE_FILAS + 1;
    $sql .= " ORDER BY c.f_emision DESC, c.comprobante_id DESC LIMIT $limite";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("=== ERROR cconsListar: " . mysqli_error($conexion));
        throw new Exception('No se pudo consultar los comprobantes.');
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $filas[] = cconsNormalizarFila($r);
    }
    mysqli_stmt_close($stmt);

    $truncado = count($filas) > CCONS_LIMITE_FILAS;
    if ($truncado) {
        array_pop($filas);
    }

    return ['filas' => $filas, 'truncado' => $truncado, 'limite' => CCONS_LIMITE_FILAS];
}

/**
 * Detalle de un comprobante: cabecera + imputaciones en las que participa (como cancelado y
 * como cancelador), con el comprobante de la contraparte.
 *
 * @param int|null $entidad_id si viene, exige que el comprobante sea de esa entidad.
 */
function cconsObtener($conexion, $empresa_id, $usuario_id, $comprobante_id, $entidad_id = null)
{
    $empresa_id = intval($empresa_id);
    $comprobante_id = intval($comprobante_id);

    [$sql, $types, $params] = cconsSqlBase($conexion, $empresa_id);
    $sql .= " AND c.comprobante_id = ?";
    $types .= "i";
    $params[] = $comprobante_id;
    if (!empty($entidad_id)) {
        $sql .= " AND c.entidad_id = ?";
        $types .= "i";
        $params[] = intval($entidad_id);
    }
    if ($usuario_id > 0 && class_exists('AccesosSucursalesHelper')) {
        $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, intval($usuario_id), $empresa_id, 'c.sucursal_id');
    }

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $cab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$cab) {
        return null;
    }
    $cab = cconsNormalizarFila($cab);

    // CAE de ARCA: hoy solo lo guardan las facturas de venta (gestion__ventas_facturas.CAE)
    $cab['cae'] = null;
    $cab['f_vto_cae'] = null;
    $stmt = mysqli_prepare($conexion, "SELECT vf.CAE, vf.f_vto_CAE
                                       FROM gestion__ventas_facturas vf
                                       JOIN conf__tablas t ON t.tabla_nombre = 'gestion__ventas_facturas' AND t.tabla_id = ?
                                       WHERE vf.venta_factura_id = ? AND vf.empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $cab['tabla_origen_id'], $cab['registro_origen_id'], $empresa_id);
    mysqli_stmt_execute($stmt);
    $vf = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($vf && trim((string)$vf['CAE']) !== '') {
        $cab['cae'] = $vf['CAE'];
        $cab['f_vto_cae'] = $vf['f_vto_CAE'];
    }

    $col_estado = cconsColumnaNombreEstado($conexion);
    $sql_imp = "SELECT ci.imputacion_id, ci.f_imputacion, ci.importe_imputado, ci.importe_origen,
                       ci.f_anulacion, ci.motivo_anulacion,
                       eri.codigo_estandar AS imputacion_codigo, eri.$col_estado AS imputacion_estado,
                       CASE WHEN ci.comp_destino_id = ? THEN 'CANCELADO_POR' ELSE 'CANCELA_A' END AS rol,
                       o.comprobante_id AS otro_comprobante_id, ot.codigo AS otro_tipo_codigo,
                       ot.comprobante_tipo AS otro_tipo, ot.letra AS otro_letra,
                       " . emp_sql_pv_mostrar('o.comprobante_pv', 'o.empresa_id', 'o.tabla_origen_id') . " AS otro_pv, o.comprobante_nro AS otro_nro,
                       o.f_emision AS otro_f_emision, o.importe_total AS otro_importe_total
                FROM gestion__comprobantes_imputaciones ci
                LEFT JOIN conf__estados_registros eri ON eri.estado_registro_id = ci.tabla_estado_registro_id
                JOIN gestion__comprobantes o
                     ON o.comprobante_id = CASE WHEN ci.comp_destino_id = ? THEN ci.comp_origen_id ELSE ci.comp_destino_id END
                JOIN gestion__comprobantes_tipos ot ON ot.comprobante_tipo_id = o.comprobante_tipo_id
                WHERE ci.empresa_id = ? AND (ci.comp_destino_id = ? OR ci.comp_origen_id = ?)
                ORDER BY ci.f_imputacion, ci.imputacion_id";
    $stmt = mysqli_prepare($conexion, $sql_imp);
    mysqli_stmt_bind_param($stmt, "iiiii", $comprobante_id, $comprobante_id, $empresa_id, $comprobante_id, $comprobante_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $imputaciones = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['importe_imputado'] = floatval($r['importe_imputado']);
        $r['importe_origen'] = floatval($r['importe_origen']);
        $r['otro_importe_total'] = floatval($r['otro_importe_total']);
        $imputaciones[] = $r;
    }
    mysqli_stmt_close($stmt);

    return ['comprobante' => $cab, 'imputaciones' => $imputaciones];
}

/** Combos de filtros: grupos, tipos, sucursales (según accesos), estados usados y monedas. */
function cconsMaestros($conexion, $empresa_id, $usuario_id)
{
    $empresa_id = intval($empresa_id);
    $col_estado = cconsColumnaNombreEstado($conexion);

    $grupos = [];
    $stmt = mysqli_prepare($conexion, "SELECT comprobante_grupo_id, comprobante_grupo
                                       FROM gestion__comprobantes_grupos
                                       WHERE empresa_id IN (0, ?) AND tabla_estado_registro_id = 1
                                       ORDER BY orden, comprobante_grupo");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $grupos[] = $r;
    }
    mysqli_stmt_close($stmt);

    // Solo tipos que tienen comprobantes en la empresa (evita un combo con tipos sin uso)
    $tipos = [];
    $stmt = mysqli_prepare($conexion, "SELECT ct.comprobante_tipo_id, ct.comprobante_grupo_id, ct.codigo, ct.comprobante_tipo, ct.letra
                                       FROM gestion__comprobantes_tipos ct
                                       WHERE EXISTS (SELECT 1 FROM gestion__comprobantes c
                                                     WHERE c.empresa_id = ? AND c.comprobante_tipo_id = ct.comprobante_tipo_id)
                                       ORDER BY ct.comprobante_grupo_id, ct.orden, ct.comprobante_tipo");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $tipos[] = $r;
    }
    mysqli_stmt_close($stmt);

    $sucursales = [];
    $stmt = mysqli_prepare($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales
                                       WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                       ORDER BY sucursal_nombre");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $sucursales[] = $r;
    }
    mysqli_stmt_close($stmt);
    if (class_exists('AccesosSucursalesHelper')) {
        $sucursales = AccesosSucursalesHelper::filtrarSucursales($conexion, $empresa_id, $sucursales, $usuario_id > 0 ? intval($usuario_id) : null);
    }

    $estados = [];
    // orden_estandar va en el SELECT: MySQL 8 no acepta ORDER BY fuera de la lista con DISTINCT
    $stmt = mysqli_prepare($conexion, "SELECT DISTINCT er.estado_registro_id, er.$col_estado AS estado_registro, er.orden_estandar
                                       FROM gestion__comprobantes c
                                       JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
                                       WHERE c.empresa_id = ?
                                       ORDER BY er.orden_estandar, er.estado_registro_id");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $estados[] = $r;
    }
    mysqli_stmt_close($stmt);

    $monedas = [];
    $stmt = mysqli_prepare($conexion, "SELECT moneda_id, codigo, simbolo FROM gestion__monedas
                                       WHERE empresa_id IN (0, ?) AND tabla_estado_registro_id = 1
                                       ORDER BY orden, codigo");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $monedas[] = $r;
    }
    mysqli_stmt_close($stmt);

    return [
        'grupos'     => $grupos,
        'tipos'      => $tipos,
        'sucursales' => $sucursales,
        'estados'    => $estados,
        'monedas'    => $monedas,
    ];
}

/** Búsqueda de entidades para el combo de filtro (por nombre, fantasía o CUIT). */
function cconsBuscarEntidades($conexion, $texto, $solo_clientes = false, $limite = 30)
{
    $texto = trim((string) $texto);
    $like = '%' . $texto . '%';
    $limite = max(1, min(100, intval($limite)));

    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia, cuit
            FROM gestion__entidades
            WHERE tabla_estado_registro_id IN (1, 2)
              AND (entidad_nombre LIKE ? OR entidad_fantasia LIKE ? OR CAST(cuit AS CHAR) LIKE ?)";
    if ($solo_clientes) {
        $sql .= " AND es_cliente = 1";
    }
    $sql .= " ORDER BY entidad_nombre LIMIT $limite";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['entidad_id'] = intval($r['entidad_id']);
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
