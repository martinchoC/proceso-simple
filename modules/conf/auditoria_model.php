<?php
/**
 * modules/conf/auditoria_model.php
 * Modelo de datos para la visualización y análisis de logs de auditoría particionados anualmente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/auditoria.php';

/**
 * Devuelve la lista de años disponibles con tablas de auditoría.
 */
function audModelObtenerAnios(mysqli $c): array
{
    return auditoria_obtener_anios_disponibles($c);
}

/**
 * Devuelve los usuarios y empresas que efectivamente han tenido interacciones en el año seleccionado,
 * ordenados alfabéticamente para los selectores de filtros.
 */
function audModelObtenerCatalogosFiltros(mysqli $c, int $anio = 0): array
{
    if ($anio <= 0) {
        $anio = (int)date('Y');
    }
    $tabla = auditoria_obtener_nombre_tabla($anio);
    auditoria_asegurar_tabla($c, $anio);

    $usuarios = [];
    $sqlU = "SELECT DISTINCT 
                a.usuario_id,
                COALESCE(NULLIF(TRIM(a.usuario_nombre), ''), NULLIF(TRIM(u.usuario_nombre), ''), u.usuario, CONCAT('Usuario #', a.usuario_id)) AS nombre_mostrar,
                u.usuario AS usuario_login
             FROM `{$tabla}` a
             LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
             WHERE a.usuario_id IS NOT NULL AND a.usuario_id > 0
             ORDER BY nombre_mostrar ASC";

    $resU = mysqli_query($c, $sqlU);
    if ($resU) {
        while ($r = mysqli_fetch_assoc($resU)) {
            $nombre = trim((string)($r['nombre_mostrar'] ?? ''));
            $login = trim((string)($r['usuario_login'] ?? ''));
            $etiqueta = ($login !== '' && strpos($nombre, $login) === false) ? "{$nombre} ({$login})" : $nombre;
            $usuarios[] = [
                'id' => (int)$r['usuario_id'],
                'usuario_id' => (int)$r['usuario_id'],
                'nombre' => $etiqueta,
                'nombre_completo' => $nombre,
                'usuario' => $login,
            ];
        }
    }

    $empresas = [];
    $sqlE = "SELECT DISTINCT
                a.empresa_id,
                COALESCE(e.empresa, CONCAT('Empresa #', a.empresa_id)) AS empresa_nombre
             FROM `{$tabla}` a
             LEFT JOIN conf__empresas e ON e.empresa_id = a.empresa_id
             WHERE a.empresa_id IS NOT NULL AND a.empresa_id > 0
             ORDER BY empresa_nombre ASC";

    $resE = mysqli_query($c, $sqlE);
    if ($resE) {
        while ($r = mysqli_fetch_assoc($resE)) {
            $empresas[] = [
                'id' => (int)$r['empresa_id'],
                'empresa_id' => (int)$r['empresa_id'],
                'nombre' => $r['empresa_nombre']
            ];
        }
    }

    return [
        'usuarios' => $usuarios,
        'empresas' => $empresas
    ];
}

/**
 * Obtiene métricas y KPIs rápidos para el año seleccionado.
 */
function audModelObtenerKPIs(mysqli $c, int $anio): array
{
    $tabla = auditoria_obtener_nombre_tabla($anio);
    auditoria_asegurar_tabla($c, $anio);

    $sql = "SELECT 
                COUNT(*) AS total_eventos,
                SUM(CASE WHEN accion = 'LOGIN_EXITOSO' THEN 1 ELSE 0 END) AS logins_ok,
                SUM(CASE WHEN accion = 'LOGIN_FALLIDO' THEN 1 ELSE 0 END) AS logins_fail,
                SUM(CASE WHEN accion LIKE 'SEGURIDAD_%' THEN 1 ELSE 0 END) AS alertas_seguridad,
                SUM(CASE WHEN accion IN ('ALTA', 'MODIFICACION', 'BAJA') OR accion LIKE '%ALTA%' OR accion LIKE '%MODIF%' THEN 1 ELSE 0 END) AS cambios_datos
            FROM `{$tabla}`";

    $res = mysqli_query($c, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return [
            'total' => (int)($row['total_eventos'] ?? 0),
            'total_eventos' => (int)($row['total_eventos'] ?? 0),
            'logins_ok' => (int)($row['logins_ok'] ?? 0),
            'logins_fail' => (int)($row['logins_fail'] ?? 0),
            'seguridad' => (int)($row['alertas_seguridad'] ?? 0),
            'alertas_seguridad' => (int)($row['alertas_seguridad'] ?? 0),
            'cambios_datos' => (int)($row['cambios_datos'] ?? 0),
        ];
    }

    return [
        'total' => 0,
        'total_eventos' => 0,
        'logins_ok' => 0,
        'logins_fail' => 0,
        'seguridad' => 0,
        'alertas_seguridad' => 0,
        'cambios_datos' => 0,
    ];
}

/**
 * Lista los registros de auditoría aplicando filtros avanzados.
 */
function audModelListar(mysqli $c, int $anio, array $filtros): array
{
    $tabla = auditoria_obtener_nombre_tabla($anio);
    auditoria_asegurar_tabla($c, $anio);

    $where = ["1=1"];
    $params = [];
    $types = "";

    if (!empty($filtros['usuario_id'])) {
        $where[] = "a.usuario_id = ?";
        $params[] = (int)$filtros['usuario_id'];
        $types .= "i";
    }

    if (!empty($filtros['empresa_id'])) {
        $where[] = "a.empresa_id = ?";
        $params[] = (int)$filtros['empresa_id'];
        $types .= "i";
    }

    if (!empty($filtros['accion'])) {
        $where[] = "a.accion = ?";
        $params[] = (string)$filtros['accion'];
        $types .= "s";
    }

    if (!empty($filtros['fecha_desde'])) {
        $where[] = "a.fecha_hora >= ?";
        $params[] = $filtros['fecha_desde'] . " 00:00:00";
        $types .= "s";
    }

    if (!empty($filtros['fecha_hasta'])) {
        $where[] = "a.fecha_hora <= ?";
        $params[] = $filtros['fecha_hasta'] . " 23:59:59";
        $types .= "s";
    }

    if (!empty($filtros['busqueda'])) {
        $where[] = "(a.descripcion LIKE ? OR a.usuario_nombre LIKE ? OR a.tabla_afectada LIKE ? OR a.ip_origen LIKE ? OR a.accion LIKE ?)";
        $term = "%" . $filtros['busqueda'] . "%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types .= "sssss";
    }

    $whereSql = implode(" AND ", $where);

    $sql = "SELECT 
                a.auditoria_id AS id,
                a.auditoria_id,
                a.fecha_hora,
                a.usuario_id,
                a.usuario_nombre,
                a.empresa_id,
                e.empresa AS empresa_nombre,
                a.modulo_id,
                m.modulo AS modulo_nombre,
                a.pagina_id,
                p.pagina AS pagina_nombre,
                a.accion,
                COALESCE(a.tabla_id, t.tabla_id) AS tabla_id,
                a.tabla_afectada,
                t.tabla_descripcion,
                a.registro_id,
                a.tablas_involucradas,
                a.descripcion,
                a.ip_origen,
                a.metodo_http,
                (CASE WHEN a.datos_antes IS NOT NULL OR a.datos_despues IS NOT NULL THEN 1 ELSE 0 END) AS tiene_payload
            FROM `{$tabla}` a
            LEFT JOIN conf__empresas e ON e.empresa_id = a.empresa_id
            LEFT JOIN conf__modulos m ON m.modulo_id = a.modulo_id
            LEFT JOIN conf__paginas p ON p.pagina_id = a.pagina_id
            LEFT JOIN conf__tablas t ON (a.tabla_id IS NOT NULL AND t.tabla_id = a.tabla_id) OR (a.tabla_afectada IS NOT NULL AND t.tabla_nombre = a.tabla_afectada)
            WHERE {$whereSql}
            ORDER BY a.fecha_hora DESC, a.auditoria_id DESC
            LIMIT 500";

    $stmt = mysqli_prepare($c, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $filas[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $filas;
}

/**
 * Obtiene el detalle completo de un registro individual (incluyendo JSON diff y User Agent).
 */
function audModelObtenerDetalle(mysqli $c, int $anio, int $id): ?array
{
    $tabla = auditoria_obtener_nombre_tabla($anio);
    auditoria_asegurar_tabla($c, $anio);

    $sql = "SELECT 
                a.auditoria_id AS id,
                a.*,
                COALESCE(a.tabla_id, t.tabla_id) AS tabla_id,
                t.tabla_descripcion,
                e.empresa AS empresa_nombre,
                m.modulo AS modulo_nombre,
                p.pagina AS pagina_nombre
            FROM `{$tabla}` a
            LEFT JOIN conf__empresas e ON e.empresa_id = a.empresa_id
            LEFT JOIN conf__modulos m ON m.modulo_id = a.modulo_id
            LEFT JOIN conf__paginas p ON p.pagina_id = a.pagina_id
            LEFT JOIN conf__tablas t ON (a.tabla_id IS NOT NULL AND t.tabla_id = a.tabla_id) OR (a.tabla_afectada IS NOT NULL AND t.tabla_nombre = a.tabla_afectada)
            WHERE a.auditoria_id = ? LIMIT 1";

    $stmt = mysqli_prepare($c, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $detalle = mysqli_fetch_assoc($res) ?: null;
    mysqli_stmt_close($stmt);

    return $detalle;
}

/**
 * Obtiene el historial completo de eventos para una tabla y registro_id específicos.
 * Puede buscar en el año seleccionado o recorrer todos los años disponibles.
 */
function audModelObtenerHistoriaRegistro(mysqli $c, string $tablaAfectada, string $registroId, ?int $anio = null): array
{
    if (empty($tablaAfectada) || empty($registroId)) {
        return [];
    }

    $anios = $anio ? [$anio] : audModelObtenerAnios($c);
    $historia = [];

    foreach ($anios as $a) {
        $nombreTabla = auditoria_obtener_nombre_tabla($a);
        auditoria_asegurar_tabla($c, $a);

        $sql = "SELECT 
                    a.auditoria_id AS id,
                    a.auditoria_id,
                    a.fecha_hora,
                    a.usuario_id,
                    a.usuario_nombre,
                    a.empresa_id,
                    e.empresa AS empresa_nombre,
                    a.modulo_id,
                    m.modulo AS modulo_nombre,
                    a.pagina_id,
                    p.pagina AS pagina_nombre,
                    a.accion,
                    COALESCE(a.tabla_id, t.tabla_id) AS tabla_id,
                    a.tabla_afectada,
                    t.tabla_descripcion,
                    a.registro_id,
                    a.tablas_involucradas,
                    a.descripcion,
                    a.datos_antes,
                    a.datos_despues,
                    a.ip_origen,
                    a.metodo_http,
                    {$a} AS anio
                FROM `{$nombreTabla}` a
                LEFT JOIN conf__empresas e ON e.empresa_id = a.empresa_id
                LEFT JOIN conf__modulos m ON m.modulo_id = a.modulo_id
                LEFT JOIN conf__paginas p ON p.pagina_id = a.pagina_id
                LEFT JOIN conf__tablas t ON (a.tabla_id IS NOT NULL AND t.tabla_id = a.tabla_id) OR (a.tabla_afectada IS NOT NULL AND t.tabla_nombre = a.tabla_afectada)
                WHERE ((a.tabla_afectada = ? OR (a.tabla_id IS NOT NULL AND a.tabla_id = ?)) AND a.registro_id = ?)
                   OR (a.tablas_involucradas LIKE ? AND a.tablas_involucradas LIKE ?)
                ORDER BY a.fecha_hora DESC, a.auditoria_id DESC";

        $stmt = mysqli_prepare($c, $sql);
        if ($stmt) {
            $tablaIdNum = auditoria_obtener_tabla_id($c, $tablaAfectada) ?? (is_numeric($tablaAfectada) ? (int)$tablaAfectada : 0);
            $likeTabla = "%\"tabla\":\"" . $tablaAfectada . "\"%";
            $likeReg = "%\"registro_id\":\"" . $registroId . "\"%";
            mysqli_stmt_bind_param($stmt, "sisss", $tablaAfectada, $tablaIdNum, $registroId, $likeTabla, $likeReg);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                $historia[] = $r;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Ordenar cronológicamente (más reciente primero)
    usort($historia, function ($x, $y) {
        return strcmp($y['fecha_hora'], $x['fecha_hora']);
    });

    return $historia;
}

