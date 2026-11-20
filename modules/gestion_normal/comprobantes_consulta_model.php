<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/vendedores.php';
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
                   c.entidad_id, COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, e.entidad_fantasia, e.cuit,
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

    $asientos = cconsObtenerAsientos($conexion, $empresa_id, $comprobante_id);
    $adjuntos = cconsObtenerAdjuntos($conexion, $empresa_id, $comprobante_id, intval($cab['tabla_origen_id']), intval($cab['registro_origen_id']));
    $comentarios = cconsObtenerComentarios($conexion, $empresa_id, $comprobante_id, intval($usuario_id));
    $historial = cconsObtenerHistorial($conexion, $empresa_id, $comprobante_id, $asientos, $adjuntos, $imputaciones);

    return [
        'comprobante'  => $cab,
        'imputaciones' => $imputaciones,
        'asientos'     => $asientos,
        'adjuntos'     => $adjuntos,
        'comentarios'  => $comentarios,
        'historial'    => $historial,
    ];
}

/**
 * Asientos contables vinculados al comprobante (gestion__cont_asientos + detalles).
 */
function cconsObtenerAsientos($conexion, int $empresa_id, int $comprobante_id): array
{
    $col_estado = cconsColumnaNombreEstado($conexion);
    $sql = "SELECT a.cont_asiento_id, a.f_asiento, a.descripcion, a.tabla_estado_registro_id AS estado_id,
                   COALESCE(er.$col_estado, CASE WHEN a.tabla_estado_registro_id IN (1, 5) THEN 'Confirmado' WHEN a.tabla_estado_registro_id = 2 THEN 'Anulado' ELSE 'Borrador' END) AS estado
            FROM gestion__cont_asientos a
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE a.empresa_id = ? AND a.comprobante_id = ?
            ORDER BY a.cont_asiento_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $comprobante_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $asientos = [];
    while ($a = mysqli_fetch_assoc($res)) {
        $aid = intval($a['cont_asiento_id']);
        $lineas = [];
        $tot_debe = 0.0;
        $tot_haber = 0.0;
        $st_d = mysqli_prepare($conexion, "SELECT d.cuenta_id, cc.codigo, cc.nombre, d.importe_local, d.descripcion
                                           FROM gestion__cont_asientos_detalles d
                                           LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id
                                           WHERE d.cont_asiento_id = ? AND (d.estado = 'activo' OR d.estado IS NULL)
                                           ORDER BY d.importe_local DESC, cc.codigo ASC");
        mysqli_stmt_bind_param($st_d, 'i', $aid);
        mysqli_stmt_execute($st_d);
        $rd = mysqli_stmt_get_result($st_d);
        while ($d = mysqli_fetch_assoc($rd)) {
            $imp = floatval($d['importe_local'] ?? 0);
            $debe = $imp > 0 ? round($imp, 2) : 0.0;
            $haber = $imp < 0 ? round(-$imp, 2) : 0.0;
            $tot_debe = round($tot_debe + $debe, 2);
            $tot_haber = round($tot_haber + $haber, 2);
            $lineas[] = [
                'codigo'      => $d['codigo'] ?? '',
                'nombre'      => $d['nombre'] ?? '',
                'descripcion' => $d['descripcion'] ?? '',
                'debe'        => $debe,
                'haber'       => $haber,
            ];
        }
        mysqli_stmt_close($st_d);
        $asientos[] = [
            'cont_asiento_id' => $aid,
            'f_asiento'       => $a['f_asiento'],
            'descripcion'     => $a['descripcion'] ?? '',
            'estado_id'       => intval($a['estado_id']),
            'estado'          => $a['estado'] ?? '',
            'anulado'         => in_array(intval($a['estado_id']), [2, 38], true),
            'total_debe'      => $tot_debe,
            'total_haber'     => $tot_haber,
            'lineas'          => $lineas,
        ];
    }
    mysqli_stmt_close($stmt);
    return $asientos;
}

/**
 * Documentos escaneados / adjuntos vinculados al comprobante desde las 3 fuentes:
 * 1) gestion__comprobantes_adjuntos (universal para cualquier comprobante)
 * 2) gestion__facturas_proveedores_adjuntos (facturas de compra escaneadas)
 * 3) gestion__bandeja_documentos (documentos de la bandeja asociados a la tabla/registro origen)
 */
function cconsObtenerAdjuntos($conexion, int $empresa_id, int $comprobante_id, int $tabla_origen_id, int $registro_origen_id): array
{
    $adjuntos = [];
    $vistos_ruta = [];

    // 1. Adjuntos universales del comprobante
    $chk = @mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__comprobantes_adjuntos'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $st = mysqli_prepare($conexion, "SELECT a.comprobante_adjunto_id, a.archivo_ruta, a.nombre_original, a.mime,
                                                a.tamanio_bytes, a.descripcion, a.fecha_alta, u.usuario_nombre
                                         FROM gestion__comprobantes_adjuntos a
                                         LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
                                         WHERE a.empresa_id = ? AND a.comprobante_id = ? AND a.tabla_estado_registro_id = 1
                                         ORDER BY a.fecha_alta DESC, a.comprobante_adjunto_id DESC");
        mysqli_stmt_bind_param($st, 'ii', $empresa_id, $comprobante_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        while ($r = mysqli_fetch_assoc($res)) {
            $vistos_ruta[$r['archivo_ruta']] = true;
            $adjuntos[] = [
                'origen'          => 'COMPROBANTE',
                'origen_label'    => 'Adjunto',
                'id'              => intval($r['comprobante_adjunto_id']),
                'nombre_original' => $r['nombre_original'],
                'mime'            => $r['mime'],
                'tamanio_bytes'   => intval($r['tamanio_bytes']),
                'descripcion'     => $r['descripcion'] ?? '',
                'usuario_nombre'  => $r['usuario_nombre'] ?? '',
                'fecha_alta'      => $r['fecha_alta'],
                'puede_eliminar'  => true,
            ];
        }
        mysqli_stmt_close($st);
    }

    // 2. Factura de proveedor escaneada (gestion__facturas_proveedores_adjuntos)
    if ($tabla_origen_id > 0 && $registro_origen_id > 0) {
        $st_fp = mysqli_prepare($conexion, "SELECT a.adjunto_id, a.archivo_ruta, a.nombre_original, a.mime,
                                                   a.tamanio_bytes, a.fecha_alta, u.usuario_nombre
                                            FROM gestion__facturas_proveedores_adjuntos a
                                            JOIN conf__tablas t ON t.tabla_nombre = 'gestion__facturas_proveedores' AND t.tabla_id = ?
                                            LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
                                            WHERE a.empresa_id = ? AND a.factura_proveedor_id = ? AND a.tabla_estado_registro_id = 1
                                            ORDER BY a.fecha_alta DESC");
        if ($st_fp) {
            mysqli_stmt_bind_param($st_fp, 'iii', $tabla_origen_id, $empresa_id, $registro_origen_id);
            mysqli_stmt_execute($st_fp);
            $res = mysqli_stmt_get_result($st_fp);
            while ($r = mysqli_fetch_assoc($res)) {
                if (isset($vistos_ruta[$r['archivo_ruta']])) continue;
                $vistos_ruta[$r['archivo_ruta']] = true;
                $adjuntos[] = [
                    'origen'          => 'FACTURA_PROVEEDOR',
                    'origen_label'    => 'Factura escaneada',
                    'id'              => intval($r['adjunto_id']),
                    'nombre_original' => $r['nombre_original'],
                    'mime'            => $r['mime'],
                    'tamanio_bytes'   => intval($r['tamanio_bytes']),
                    'descripcion'     => 'Documento asociado en Compras',
                    'usuario_nombre'  => $r['usuario_nombre'] ?? '',
                    'fecha_alta'      => $r['fecha_alta'],
                    'puede_eliminar'  => false,
                ];
            }
            mysqli_stmt_close($st_fp);
        }

        // 3. Documento desde Bandeja de Documentos
        $st_b = mysqli_prepare($conexion, "SELECT b.bandeja_documento_id, b.archivo_ruta, b.nombre_original, b.mime,
                                                  b.tamanio_bytes, b.tipo_documento, b.fecha_alta, u.usuario_nombre
                                           FROM gestion__bandeja_documentos b
                                           LEFT JOIN conf__usuarios u ON u.usuario_id = b.usuario_id
                                           WHERE b.empresa_id = ? AND b.destino_tabla_id = ? AND b.destino_registro_id = ?
                                           ORDER BY b.fecha_alta DESC");
        if ($st_b) {
            mysqli_stmt_bind_param($st_b, 'iii', $empresa_id, $tabla_origen_id, $registro_origen_id);
            mysqli_stmt_execute($st_b);
            $res = mysqli_stmt_get_result($st_b);
            while ($r = mysqli_fetch_assoc($res)) {
                if (isset($vistos_ruta[$r['archivo_ruta']])) continue;
                $vistos_ruta[$r['archivo_ruta']] = true;
                $adjuntos[] = [
                    'origen'          => 'BANDEJA',
                    'origen_label'    => 'Bandeja (' . ($r['tipo_documento'] ?: 'Doc') . ')',
                    'id'              => intval($r['bandeja_documento_id']),
                    'nombre_original' => $r['nombre_original'],
                    'mime'            => $r['mime'],
                    'tamanio_bytes'   => intval($r['tamanio_bytes']),
                    'descripcion'     => 'Ingresado por Bandeja de Documentos',
                    'usuario_nombre'  => $r['usuario_nombre'] ?? '',
                    'fecha_alta'      => $r['fecha_alta'],
                    'puede_eliminar'  => false,
                ];
            }
            mysqli_stmt_close($st_b);
        }
    }

    return $adjuntos;
}

/**
 * Sube y asocia un documento escaneado (PDF, JPG, PNG, WebP hasta 10 MB) a cualquier comprobante.
 */
function cconsSubirAdjunto($conexion, int $empresa_id, int $usuario_id, int $comprobante_id, array $archivo, string $descripcion = ''): array
{
    $st_c = mysqli_prepare($conexion, "SELECT comprobante_id FROM gestion__comprobantes WHERE comprobante_id = ? AND empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st_c, 'ii', $comprobante_id, $empresa_id);
    mysqli_stmt_execute($st_c);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($st_c));
    mysqli_stmt_close($st_c);
    if (!$existe) throw new Exception('Comprobante no encontrado.');

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw new Exception('No se recibió el archivo o superó el límite permitido.');
    }
    $tam = intval(filesize($archivo['tmp_name']));
    if ($tam <= 0 || $tam > 10 * 1024 * 1024) {
        throw new Exception('El archivo debe pesar entre 1 byte y 10 MB.');
    }
    $mimes = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $ext = $mimes[$mime] ?? null;
    if (!$ext) throw new Exception('Formato no permitido. Subí un archivo PDF, JPG, PNG o WebP.');

    $dir_rel = 'uploads/comprobantes/' . $empresa_id;
    $base_dir = __DIR__ . '/../../uploads/comprobantes';
    $dir_abs = $base_dir . '/' . $empresa_id;
    if (!is_dir($dir_abs) && !mkdir($dir_abs, 0755, true)) {
        throw new Exception('No se pudo crear la carpeta de adjuntos.');
    }
    if (!file_exists($base_dir . '/.htaccess')) {
        @file_put_contents($base_dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }

    $nombre_arch = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $ruta_abs = $dir_abs . '/' . $nombre_arch;
    $ruta_rel = $dir_rel . '/' . $nombre_arch;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_abs)) {
        throw new Exception('No se pudo guardar el archivo en el servidor.');
    }
    $sha = hash_file('sha256', $ruta_abs);
    $orig = mb_substr(basename(str_replace('\\', '/', (string)($archivo['name'] ?? "documento.$ext"))), 0, 255);
    $desc = mb_substr(trim($descripcion), 0, 255);

    $st = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_adjuntos
        (empresa_id, comprobante_id, archivo_ruta, nombre_original, mime, tamanio_bytes, sha256, descripcion, usuario_id, fecha_alta, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    mysqli_stmt_bind_param($st, 'iisssissi', $empresa_id, $comprobante_id, $ruta_rel, $orig, $mime, $tam, $sha, $desc, $usuario_id);
    if (!mysqli_stmt_execute($st)) {
        @unlink($ruta_abs);
        throw new Exception('Error registrando adjunto: ' . mysqli_stmt_error($st));
    }
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($st);
    cconsRegistrarLog($conexion, $empresa_id, $comprobante_id, $usuario_id, 'ADJUNTO_ALTA', "Adjuntó '$orig'" . ($desc !== '' ? " ($desc)" : ''));
    return ['comprobante_adjunto_id' => intval($id), 'nombre_original' => $orig];
}

/**
 * Quita (baja lógica) un adjunto universal del comprobante.
 */
function cconsEliminarAdjunto($conexion, int $empresa_id, int $adjunto_id, int $usuario_id = 0): bool
{
    $st_q = mysqli_prepare($conexion, "SELECT comprobante_id, nombre_original FROM gestion__comprobantes_adjuntos WHERE comprobante_adjunto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    mysqli_stmt_bind_param($st_q, 'ii', $adjunto_id, $empresa_id);
    mysqli_stmt_execute($st_q);
    $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($st_q));
    mysqli_stmt_close($st_q);
    if (!$prev) return false;

    $st = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_adjuntos SET tabla_estado_registro_id = 6 WHERE comprobante_adjunto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, 'ii', $adjunto_id, $empresa_id);
    mysqli_stmt_execute($st);
    $af = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);
    if ($af > 0) {
        cconsRegistrarLog($conexion, $empresa_id, intval($prev['comprobante_id']), $usuario_id, 'ADJUNTO_BAJA', "Quitó adjunto '{$prev['nombre_original']}'");
    }
    return $af > 0;
}

/**
 * Registra un evento en el log de historial del comprobante (defensivo si la tabla aún no existe).
 */
function cconsRegistrarLog($conexion, int $empresa_id, int $comprobante_id, int $usuario_id, string $accion, string $detalle = ''): void
{
    $chk = @mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__comprobantes_log'");
    if (!$chk || mysqli_num_rows($chk) === 0) return;
    $uid = $usuario_id > 0 ? $usuario_id : null;
    $det = mb_substr(trim($detalle), 0, 500);
    $st = @mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_log (empresa_id, comprobante_id, usuario_id, accion, detalle, fecha_evento) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$st) return;
    mysqli_stmt_bind_param($st, 'iiiss', $empresa_id, $comprobante_id, $uid, $accion, $det);
    @mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
}

/**
 * Comentarios multiusuario de un comprobante.
 */
function cconsObtenerComentarios($conexion, int $empresa_id, int $comprobante_id, int $usuario_actual_id = 0): array
{
    $chk = @mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__comprobantes_comentarios'");
    if (!$chk || mysqli_num_rows($chk) === 0) return [];
    $st = mysqli_prepare($conexion, "SELECT cm.comentario_id, cm.usuario_id, cm.comentario, cm.fecha_alta,
                                            COALESCE(u.usuario_nombre, 'Usuario') AS usuario_nombre
                                     FROM gestion__comprobantes_comentarios cm
                                     LEFT JOIN conf__usuarios u ON u.usuario_id = cm.usuario_id
                                     WHERE cm.empresa_id = ? AND cm.comprobante_id = ? AND cm.tabla_estado_registro_id = 1
                                     ORDER BY cm.fecha_alta ASC, cm.comentario_id ASC");
    mysqli_stmt_bind_param($st, 'ii', $empresa_id, $comprobante_id);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = [
            'comentario_id'  => intval($r['comentario_id']),
            'usuario_id'     => intval($r['usuario_id']),
            'usuario_nombre' => $r['usuario_nombre'],
            'comentario'     => $r['comentario'],
            'fecha_alta'     => $r['fecha_alta'],
            'es_propio'      => $usuario_actual_id > 0 && intval($r['usuario_id']) === $usuario_actual_id,
        ];
    }
    mysqli_stmt_close($st);
    return $out;
}

function cconsAgregarComentario($conexion, int $empresa_id, int $usuario_id, int $comprobante_id, string $texto): array
{
    $texto = trim($texto);
    if ($texto === '') throw new Exception('Escribí un comentario.');
    $texto = mb_substr($texto, 0, 2000);

    $st_c = mysqli_prepare($conexion, "SELECT comprobante_id FROM gestion__comprobantes WHERE comprobante_id = ? AND empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st_c, 'ii', $comprobante_id, $empresa_id);
    mysqli_stmt_execute($st_c);
    $ok = mysqli_fetch_assoc(mysqli_stmt_get_result($st_c));
    mysqli_stmt_close($st_c);
    if (!$ok) throw new Exception('Comprobante no encontrado.');

    $st = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_comentarios (empresa_id, comprobante_id, usuario_id, comentario, fecha_alta, tabla_estado_registro_id) VALUES (?, ?, ?, ?, NOW(), 1)");
    mysqli_stmt_bind_param($st, 'iiis', $empresa_id, $comprobante_id, $usuario_id, $texto);
    if (!mysqli_stmt_execute($st)) throw new Exception('Error guardando comentario.');
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($st);
    cconsRegistrarLog($conexion, $empresa_id, $comprobante_id, $usuario_id, 'COMENTARIO', mb_substr($texto, 0, 140));
    return ['comentario_id' => intval($id)];
}

function cconsEliminarComentario($conexion, int $empresa_id, int $usuario_id, int $comentario_id): bool
{
    $st = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_comentarios SET tabla_estado_registro_id = 6
                                     WHERE comentario_id = ? AND empresa_id = ? AND usuario_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($st, 'iii', $comentario_id, $empresa_id, $usuario_id);
    mysqli_stmt_execute($st);
    $af = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);
    return $af > 0;
}

/**
 * Línea de tiempo unificada del comprobante (creación, modificación, aprobación MC, asientos, imputaciones, adjuntos y log).
 */
function cconsObtenerHistorial($conexion, int $empresa_id, int $comprobante_id, array $asientos, array $adjuntos, array $imputaciones): array
{
    $eventos = [];

    // 1. Creación y última modificación del comprobante + aprobación soporte MC (si existe)
    $st = mysqli_prepare($conexion, "SELECT c.fecha_creacion, c.fecha_modificacion,
                                            u_c.usuario_nombre AS u_crea, u_m.usuario_nombre AS u_mod
                                     FROM gestion__comprobantes c
                                     LEFT JOIN conf__usuarios u_c ON u_c.usuario_id = c.usuario_id
                                     LEFT JOIN conf__usuarios u_m ON u_m.usuario_id = c.usuario_modificacion_id
                                     WHERE c.comprobante_id = ? AND c.empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, 'ii', $comprobante_id, $empresa_id);
    mysqli_stmt_execute($st);
    $meta = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if ($meta) {
        if (!empty($meta['fecha_creacion'])) {
            $eventos[] = [
                'fecha'   => $meta['fecha_creacion'],
                'tipo'    => 'ALTA',
                'titulo'  => 'Comprobante creado',
                'detalle' => 'Registrado en el sistema',
                'usuario' => $meta['u_crea'] ?: 'Sistema',
            ];
        }
        if (!empty($meta['fecha_modificacion']) && $meta['fecha_modificacion'] !== $meta['fecha_creacion']) {
            $eventos[] = [
                'fecha'   => $meta['fecha_modificacion'],
                'tipo'    => 'MODIF',
                'titulo'  => 'Última actualización',
                'detalle' => 'Modificación o cambio de estado del comprobante',
                'usuario' => $meta['u_mod'] ?: 'Sistema',
            ];
        }
    }

    // 2. Aprobación de comprobante soporte MC (si aplica)
    $chk_sp = @mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__tesoreria_comprobantes_soporte'");
    if ($chk_sp && mysqli_num_rows($chk_sp) > 0) {
        $st_sp = mysqli_prepare($conexion, "SELECT sp.fecha_aprobacion, sp.observacion_aprobacion, u.usuario_nombre
                                            FROM gestion__tesoreria_comprobantes_soporte sp
                                            LEFT JOIN conf__usuarios u ON u.usuario_id = sp.usuario_aprobacion_id
                                            WHERE sp.comprobante_id = ? AND sp.empresa_id = ? LIMIT 1");
        mysqli_stmt_bind_param($st_sp, 'ii', $comprobante_id, $empresa_id);
        mysqli_stmt_execute($st_sp);
        $sp = mysqli_fetch_assoc(mysqli_stmt_get_result($st_sp));
        mysqli_stmt_close($st_sp);
        if ($sp && !empty($sp['fecha_aprobacion'])) {
            $eventos[] = [
                'fecha'   => $sp['fecha_aprobacion'],
                'tipo'    => 'APROBACION',
                'titulo'  => 'Comprobante confirmado / aprobado',
                'detalle' => $sp['observacion_aprobacion'] ?: 'Impactó caja/banco y contabilidad',
                'usuario' => $sp['usuario_nombre'] ?: 'Sistema',
            ];
        }
    }

    // 3. Asientos contables
    foreach ($asientos as $a) {
        $eventos[] = [
            'fecha'   => ($a['f_asiento'] ?? '') . ' 00:00:00',
            'tipo'    => $a['anulado'] ? 'ANULACION' : 'ASIENTO',
            'titulo'  => 'Asiento contable #' . $a['cont_asiento_id'] . ($a['anulado'] ? ' (Anulado)' : ''),
            'detalle' => trim((string)($a['descripcion'] ?? '')),
            'usuario' => 'Contabilidad',
        ];
    }

    // 4. Imputaciones
    foreach ($imputaciones as $imp) {
        $rel = ($imp['rol'] ?? '') === 'CANCELADO_POR' ? 'Cancelado por ' : 'Aplicado a ';
        $nro = trim(($imp['otro_tipo_codigo'] ?? '') . ' ' . str_pad((string)($imp['otro_pv'] ?? 0), 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)($imp['otro_nro'] ?? 0), 8, '0', STR_PAD_LEFT));
        $eventos[] = [
            'fecha'   => ($imp['f_imputacion'] ?? '') . ' 00:00:00',
            'tipo'    => 'IMPUTACION',
            'titulo'  => 'Imputación registrada',
            'detalle' => $rel . $nro,
            'usuario' => 'Tesorería',
        ];
    }

    // 5. Eventos explícitos en gestion__comprobantes_log (adjuntos, comentarios, etc.)
    $chk_lg = @mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__comprobantes_log'");
    if ($chk_lg && mysqli_num_rows($chk_lg) > 0) {
        $st_lg = mysqli_prepare($conexion, "SELECT l.accion, l.detalle, l.fecha_evento, COALESCE(u.usuario_nombre, 'Sistema') AS usuario_nombre
                                            FROM gestion__comprobantes_log l
                                            LEFT JOIN conf__usuarios u ON u.usuario_id = l.usuario_id
                                            WHERE l.empresa_id = ? AND l.comprobante_id = ?
                                            ORDER BY l.fecha_evento DESC, l.log_id DESC LIMIT 100");
        mysqli_stmt_bind_param($st_lg, 'ii', $empresa_id, $comprobante_id);
        mysqli_stmt_execute($st_lg);
        $rl = mysqli_stmt_get_result($st_lg);
        $titulos = [
            'ADJUNTO_ALTA' => 'Documento adjuntado',
            'ADJUNTO_BAJA' => 'Adjunto quitado',
            'COMENTARIO'   => 'Nuevo comentario',
        ];
        while ($l = mysqli_fetch_assoc($rl)) {
            $eventos[] = [
                'fecha'   => $l['fecha_evento'],
                'tipo'    => $l['accion'],
                'titulo'  => $titulos[$l['accion']] ?? $l['accion'],
                'detalle' => $l['detalle'] ?? '',
                'usuario' => $l['usuario_nombre'],
            ];
        }
        mysqli_stmt_close($st_lg);
    }

    usort($eventos, static fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
    return $eventos;
}

/**
 * Resuelve la ruta absoluta segura de un adjunto (COMPROBANTE, FACTURA_PROVEEDOR o BANDEJA) validando empresa_id.
 */
function cconsResolverArchivoAdjunto($conexion, int $empresa_id, string $origen, int $id): ?array
{
    $origen = strtoupper(trim($origen));
    $row = null;
    if ($origen === 'COMPROBANTE') {
        $st = mysqli_prepare($conexion, "SELECT archivo_ruta, nombre_original, mime FROM gestion__comprobantes_adjuntos WHERE comprobante_adjunto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    } elseif ($origen === 'FACTURA_PROVEEDOR') {
        $st = mysqli_prepare($conexion, "SELECT archivo_ruta, nombre_original, mime FROM gestion__facturas_proveedores_adjuntos WHERE adjunto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    } elseif ($origen === 'BANDEJA') {
        $st = mysqli_prepare($conexion, "SELECT archivo_ruta, nombre_original, mime FROM gestion__bandeja_documentos WHERE bandeja_documento_id = ? AND empresa_id = ? LIMIT 1");
    } else {
        return null;
    }
    if (!$st) return null;
    mysqli_stmt_bind_param($st, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$row) return null;

    $base_uploads = realpath(__DIR__ . '/../../uploads');
    $abs = realpath(__DIR__ . '/../../' . ltrim(str_replace('\\', '/', (string)$row['archivo_ruta']), '/'));
    if (!$base_uploads || !$abs || strpos($abs, $base_uploads) !== 0 || !is_file($abs)) {
        return null;
    }
    return ['abs' => $abs, 'nombre' => $row['nombre_original'], 'mime' => $row['mime'] ?: 'application/octet-stream'];
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
function cconsBuscarEntidades($conexion, $texto, $solo_clientes = false, $limite = 30, $empresa_id = 0, $usuario_id = 0)
{
    $texto = trim((string) $texto);
    $like = '%' . $texto . '%';
    $limite = max(1, min(100, intval($limite)));
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);

    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia, cuit
            FROM gestion__entidades
            WHERE tabla_estado_registro_id IN (1, 2)
              AND (entidad_nombre LIKE ? OR entidad_fantasia LIKE ? OR CAST(cuit AS CHAR) LIKE ?)";
    $types = "sss";
    $params = [$like, $like, $like];
    if ($empresa_id > 0) {
        $sql .= " AND empresa_id = ?";
        $types .= "i";
        $params[] = $empresa_id;
    }
    if ($solo_clientes) {
        $sql .= " AND " . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'CLI');
        if ($empresa_id > 0 && $usuario_id > 0) {
            $sql .= vcar_sql_filtro_cliente($conexion, $empresa_id, $usuario_id, 'gestion__entidades.entidad_id');
        }
    }
    $sql .= " ORDER BY entidad_nombre LIMIT $limite";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
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
