<?php
/**
 * core/perfiles_predefinidos.php — perfiles base según el tipo de operación de la empresa.
 *
 * conf__empresas.tipo_operacion (BIENES / SERVICIOS / MIXTA) decide qué perfiles base de
 * conf__perfiles se le ofrecen (columna tipo_operacion: TODAS / BIENES / SERVICIOS) y, si es
 * SERVICIOS, que al copiarlos se omitan las funciones de páginas con conf__paginas.requiere_bienes = 1
 * (pedidos, remitos, stock, costos). Como el menú se arma con las páginas en las que el perfil tiene
 * funciones, eso las oculta; no bloquea el acceso (decisión 2026-10-15).
 *
 * Lo usan el asistente de inicio de empresa (gestion / gestion_normal) y la copia de perfiles al
 * asignar un módulo (conf/empresas_modulos_model.php). Scripts: _local/sql/mantenimiento/20261015_tipo_operacion_*.
 * Las funciones no abren transacción: el llamador decide.
 */

const TIPOS_OPERACION = [
    'BIENES' => 'Venta de bienes',
    'SERVICIOS' => 'Servicios',
    'MIXTA' => 'Bienes y servicios',
];

/**
 * Nivel de gestión (conf__empresas.nivel_gestion): COMPLETA = todo el ERP; SIMPLIFICADA = empresa que lleva solo lo
 * necesario (comprobantes, IVA, contabilidad, impuestos, activos fijos), típica de los clientes de un estudio contable.
 * Las páginas con conf__paginas.requiere_gestion_completa = 1 no se asignan a los perfiles de una empresa SIMPLIFICADA
 * (se oculta del menú; no bloquea el acceso, igual que requiere_bienes). Script: 20261106_nivel_gestion_empresa_01.sql.
 */
const NIVELES_GESTION = [
    'COMPLETA' => 'Gestión completa',
    'SIMPLIFICADA' => 'Gestión simplificada',
];

/** Nivel de gestión de la empresa; COMPLETA si no está cargado o la columna todavía no existe. */
function empresa_nivel_gestion(mysqli $conexion, int $empresa_id): string
{
    try {
        $stmt = mysqli_prepare($conexion, "SELECT nivel_gestion FROM conf__empresas WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        return 'COMPLETA';
    }
    $n = strtoupper((string) ($fila['nivel_gestion'] ?? ''));
    return isset(NIVELES_GESTION[$n]) ? $n : 'COMPLETA';
}

/** Graba el nivel de gestión. No toca perfiles ya creados (para eso: perfiles_quitar_funciones_simplificada). */
function empresa_guardar_nivel_gestion(mysqli $conexion, int $empresa_id, string $nivel): bool
{
    $nivel = strtoupper(trim($nivel));
    if (!isset(NIVELES_GESTION[$nivel])) {
        return false;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET nivel_gestion = ? WHERE empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $nivel, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/** Tipo de operación de la empresa; MIXTA si no está cargado o la columna todavía no existe. */
function empresa_tipo_operacion(mysqli $conexion, int $empresa_id): string
{
    try {
        $stmt = mysqli_prepare($conexion, "SELECT tipo_operacion FROM conf__empresas WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        error_log("empresa_tipo_operacion: " . $e->getMessage());
        return 'MIXTA';
    }
    $tipo = strtoupper((string) ($fila['tipo_operacion'] ?? ''));
    return isset(TIPOS_OPERACION[$tipo]) ? $tipo : 'MIXTA';
}

/** Graba el tipo de operación. Devuelve false si el valor no es válido. */
function empresa_guardar_tipo_operacion(mysqli $conexion, int $empresa_id, string $tipo): bool
{
    $tipo = strtoupper(trim($tipo));
    if (!isset(TIPOS_OPERACION[$tipo])) {
        return false;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET tipo_operacion = ? WHERE empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $tipo, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/** Valores de conf__perfiles.tipo_operacion que se ofrecen a una empresa de ese tipo. */
function perfiles_tipos_compatibles(string $tipo_empresa): array
{
    switch ($tipo_empresa) {
        case 'BIENES':
            return ['TODAS', 'BIENES'];
        case 'SERVICIOS':
            return ['TODAS', 'SERVICIOS'];
        default:
            return ['TODAS', 'BIENES', 'SERVICIOS'];
    }
}

/** Condición SQL (sin bind) que excluye páginas de bienes cuando la empresa es SERVICIOS. Alias de conf__paginas. */
function perfiles_sql_excluir_bienes(string $tipo_empresa, string $alias_pagina = 'p'): string
{
    return $tipo_empresa === 'SERVICIOS' ? " AND COALESCE({$alias_pagina}.requiere_bienes, 0) = 0" : '';
}

/**
 * Condición SQL (sin bind) con todas las páginas que la empresa no usa: bienes si es SERVICIOS y
 * "solo gestión completa" si es SIMPLIFICADA. Alias de conf__paginas.
 */
function perfiles_sql_excluir_paginas(mysqli $conexion, int $empresa_id, string $alias_pagina = 'p'): string
{
    $sql = perfiles_sql_excluir_bienes(empresa_tipo_operacion($conexion, $empresa_id), $alias_pagina);
    if (empresa_nivel_gestion($conexion, $empresa_id) === 'SIMPLIFICADA') {
        $sql .= " AND COALESCE({$alias_pagina}.requiere_gestion_completa, 0) = 0";
    }
    return $sql;
}

/**
 * Perfiles base activos del módulo que corresponden al tipo de la empresa, con lo que ya tiene copiado.
 * Cada fila: perfil_id, perfil_nombre, tipo_operacion, funciones (las que se copiarían),
 * empresa_perfil_id (si ya existe en la empresa, si no null).
 */
function perfiles_base_para_empresa(mysqli $conexion, int $empresa_id, int $modulo_id): array
{
    $tipo = empresa_tipo_operacion($conexion, $empresa_id);
    $tipos = perfiles_tipos_compatibles($tipo);
    $marcas = implode(',', array_fill(0, count($tipos), '?'));

    $sql = "SELECT p.perfil_id, p.perfil_nombre, p.tipo_operacion,
                   (SELECT COUNT(*)
                    FROM conf__perfiles_funciones prf
                    JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = prf.pagina_funcion_id
                         AND pf.tabla_estado_registro_id = 1
                    JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id
                    WHERE prf.perfil_id = p.perfil_id AND prf.asignado = 1"
                    . perfiles_sql_excluir_paginas($conexion, $empresa_id, 'pg') . ") AS funciones,
                   (SELECT MIN(ep.empresa_perfil_id) FROM conf__empresas_perfiles ep
                    WHERE ep.empresa_id = ? AND ep.modulo_id = p.modulo_id AND ep.perfil_id_base = p.perfil_id
                      AND ep.tabla_estado_registro_id = 1) AS empresa_perfil_id
            FROM conf__perfiles p
            WHERE p.modulo_id = ? AND p.tabla_estado_registro_id = 1 AND p.tipo_operacion IN ($marcas)
            ORDER BY p.perfil_id";
    $stmt = mysqli_prepare($conexion, $sql);
    $params = array_merge([$empresa_id, $modulo_id], $tipos);
    mysqli_stmt_bind_param($stmt, "ii" . str_repeat('s', count($tipos)), ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $fila['perfil_id'] = (int) $fila['perfil_id'];
        $fila['funciones'] = (int) $fila['funciones'];
        $fila['empresa_perfil_id'] = $fila['empresa_perfil_id'] !== null ? (int) $fila['empresa_perfil_id'] : null;
        $lista[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Copia un perfil base a la empresa: crea el conf__empresas_perfiles y sus funciones (sin las de
 * páginas de bienes si la empresa es SERVICIOS). Si la empresa ya tiene un perfil con esa base,
 * solo le agrega las funciones que le falten. Con $acceso_sucursales, le da acceso a todas las
 * sucursales (gestion__perfiles_sucursales con sucursal NULL), como el botón del asistente.
 *
 * @return array{empresa_perfil_id:int, creado:bool, funciones:int}
 */
function perfil_base_copiar_a_empresa(mysqli $conexion, int $empresa_id, int $modulo_id, int $perfil_id, bool $acceso_sucursales = false): array
{
    $tipo = empresa_tipo_operacion($conexion, $empresa_id);
    $tipos = perfiles_tipos_compatibles($tipo);

    $stmt = mysqli_prepare($conexion, "SELECT perfil_nombre, tipo_operacion FROM conf__perfiles
                                       WHERE perfil_id = ? AND modulo_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, "ii", $perfil_id, $modulo_id);
    mysqli_stmt_execute($stmt);
    $base = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$base) {
        throw new RuntimeException("El perfil base $perfil_id no existe o no es del módulo $modulo_id.");
    }
    if (!in_array($base['tipo_operacion'] ?? 'TODAS', $tipos, true)) {
        throw new RuntimeException("El perfil '{$base['perfil_nombre']}' no corresponde a una empresa de tipo " . TIPOS_OPERACION[$tipo] . ".");
    }

    $stmt = mysqli_prepare($conexion, "SELECT empresa_perfil_id FROM conf__empresas_perfiles
                                       WHERE empresa_id = ? AND modulo_id = ? AND perfil_id_base = ?
                                         AND tabla_estado_registro_id = 1
                                       ORDER BY empresa_perfil_id LIMIT 1");
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $modulo_id, $perfil_id);
    mysqli_stmt_execute($stmt);
    $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $creado = false;
    if ($existente) {
        $empresa_perfil_id = (int) $existente['empresa_perfil_id'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO conf__empresas_perfiles
                                           (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
                                           VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "iiis", $empresa_id, $modulo_id, $perfil_id, $base['perfil_nombre']);
        mysqli_stmt_execute($stmt);
        $empresa_perfil_id = (int) mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        $creado = true;
    }

    $sql = "INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado)
            SELECT ?, ?, prf.pagina_funcion_id, 1
            FROM conf__perfiles_funciones prf
            JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = prf.pagina_funcion_id
                 AND pf.tabla_estado_registro_id = 1
            JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
            WHERE prf.perfil_id = ? AND prf.asignado = 1" . perfiles_sql_excluir_paginas($conexion, $empresa_id, 'p') . "
              AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x
                              WHERE x.empresa_perfil_id = ? AND x.pagina_funcion_id = prf.pagina_funcion_id)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $empresa_perfil_id, $perfil_id, $empresa_perfil_id);
    mysqli_stmt_execute($stmt);
    $funciones = (int) mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($acceso_sucursales) {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__perfiles_sucursales
                                               (empresa_id, empresa_perfil_id, sucursal_id, tabla_estado_registro_id, fecha_creacion)
                                           SELECT ?, ?, NULL, 1, NOW() FROM DUAL
                                           WHERE NOT EXISTS (SELECT 1 FROM gestion__perfiles_sucursales
                                                             WHERE empresa_id = ? AND empresa_perfil_id = ?
                                                               AND sucursal_id IS NULL AND tabla_estado_registro_id = 1)");
        mysqli_stmt_bind_param($stmt, "iiii", $empresa_id, $empresa_perfil_id, $empresa_id, $empresa_perfil_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    error_log("=== perfil_base_copiar_a_empresa empresa=$empresa_id modulo=$modulo_id base=$perfil_id -> empresa_perfil=$empresa_perfil_id funciones=$funciones ===");
    return ['empresa_perfil_id' => $empresa_perfil_id, 'creado' => $creado, 'funciones' => $funciones];
}

/**
 * Perfiles activos de la empresa en el módulo con cuántas funciones asignadas de páginas de bienes
 * tienen (las que una empresa SERVICIOS no usa). Solo devuelve los que tienen alguna.
 */
function perfiles_funciones_bienes(mysqli $conexion, int $empresa_id, int $modulo_id): array
{
    $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre,
                   COUNT(*) AS funciones,
                   GROUP_CONCAT(DISTINCT p.pagina ORDER BY p.pagina SEPARATOR ', ') AS paginas
            FROM conf__empresas_perfiles ep
            JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
            JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
            JOIN conf__paginas p ON p.pagina_id = pf.pagina_id AND p.requiere_bienes = 1
            WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND ep.tabla_estado_registro_id = 1
            GROUP BY ep.empresa_perfil_id, ep.empresa_perfil_nombre
            ORDER BY ep.empresa_perfil_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $modulo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $fila['empresa_perfil_id'] = (int) $fila['empresa_perfil_id'];
        $fila['funciones'] = (int) $fila['funciones'];
        $lista[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Empresa SIMPLIFICADA con perfiles ya creados: desasigna las funciones de páginas "solo gestión completa".
 * Devuelve cuántas funciones cambió (reversible desde Perfiles - Funciones).
 */
function perfiles_quitar_funciones_simplificada(mysqli $conexion, int $empresa_id, int $modulo_id): int
{
    if (empresa_nivel_gestion($conexion, $empresa_id) !== 'SIMPLIFICADA') {
        return 0;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas_perfiles_funciones epf
            JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
            JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
            JOIN conf__paginas p ON p.pagina_id = pf.pagina_id AND p.requiere_gestion_completa = 1
            SET epf.asignado = 0
            WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND epf.asignado = 1");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $modulo_id);
    mysqli_stmt_execute($stmt);
    $n = (int) mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/**
 * Empresa que pasa a COMPLETA con perfiles ya creados: les da las funciones de páginas "solo gestión completa" que
 * tiene su perfil base (agrega las que falten y vuelve a asignar las desasignadas). Devuelve cuántas cambió.
 */
function perfiles_restaurar_funciones_completa(mysqli $conexion, int $empresa_id, int $modulo_id): int
{
    if (empresa_nivel_gestion($conexion, $empresa_id) !== 'COMPLETA') {
        return 0;
    }
    $cambiadas = 0;
    foreach (perfiles_base_para_empresa($conexion, $empresa_id, $modulo_id) as $p) {
        if ($p['empresa_perfil_id'] !== null) {
            $cambiadas += perfil_base_copiar_a_empresa($conexion, $empresa_id, $modulo_id, $p['perfil_id'])['funciones'];
        }
    }
    $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas_perfiles_funciones epf
            JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
            JOIN conf__perfiles_funciones prf ON prf.perfil_id = ep.perfil_id_base
                 AND prf.pagina_funcion_id = epf.pagina_funcion_id AND prf.asignado = 1
            JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
            JOIN conf__paginas p ON p.pagina_id = pf.pagina_id AND p.requiere_gestion_completa = 1
            SET epf.asignado = 1
            WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND epf.asignado = 0");
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $modulo_id);
    mysqli_stmt_execute($stmt);
    $cambiadas += (int) mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $cambiadas;
}

/**
 * Desasigna (asignado = 0, se puede volver a tildar en Perfiles - Funciones) las funciones de páginas
 * de bienes de los perfiles indicados de la empresa. Devuelve cuántas funciones cambió.
 */
function perfiles_quitar_funciones_bienes(mysqli $conexion, int $empresa_id, int $modulo_id, array $empresa_perfil_ids): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $empresa_perfil_ids), function ($v) {
        return $v > 0;
    })));
    if (!$ids) {
        return 0;
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE conf__empresas_perfiles_funciones epf
            JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id
            JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
            JOIN conf__paginas p ON p.pagina_id = pf.pagina_id AND p.requiere_bienes = 1
            SET epf.asignado = 0
            WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND epf.asignado = 1
              AND ep.empresa_perfil_id IN ($marcas)";
    $stmt = mysqli_prepare($conexion, $sql);
    $params = array_merge([$empresa_id, $modulo_id], $ids);
    mysqli_stmt_bind_param($stmt, "ii" . str_repeat('i', count($ids)), ...$params);
    mysqli_stmt_execute($stmt);
    $cambiadas = (int) mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    error_log("=== perfiles_quitar_funciones_bienes empresa=$empresa_id modulo=$modulo_id perfiles=" . implode(',', $ids) . " funciones=$cambiadas ===");
    return $cambiadas;
}
