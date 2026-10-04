<?php
/**
 * Modelo de Datos: Cargas Masivas (Página 362 - Módulo 3 Matriz Legal)
 * Archivo: modules/ml/cargas_masivas_model.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cargas_masivas_service.php';

/**
 * Listar lotes de cargas masivas
 */
function cargas_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array {
    $where = ["c.empresa_id = {$empresa_id}"];

    if (!empty($filtros['carga_tipo_id'])) {
        $tipo_id = (int)$filtros['carga_tipo_id'];
        $where[] = "c.carga_tipo_id = {$tipo_id}";
    }

    if (!empty($filtros['estado_id'])) {
        $estado_id = (int)$filtros['estado_id'];
        $where[] = "c.tabla_estado_registro_id = {$estado_id}";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                c.carga_id,
                c.carga_tipo_id,
                ct.codigo as tipo_codigo,
                ct.nombre as tipo_nombre,
                c.archivo_ref,
                c.tabla_estado_registro_id as estado_id,
                CASE c.tabla_estado_registro_id
                    WHEN 1 THEN 'Cargada'
                    WHEN 2 THEN 'Validada OK'
                    WHEN 3 THEN 'Con Errores'
                    WHEN 4 THEN 'Confirmada'
                    ELSE 'Inactiva'
                END as estado_nombre,
                DATE_FORMAT(c.creado_en, '%d/%m/%Y %H:%i') as creado_en_fmt,
                DATE_FORMAT(c.validada_en, '%d/%m/%Y %H:%i') as validada_en_fmt,
                DATE_FORMAT(c.confirmada_en, '%d/%m/%Y %H:%i') as confirmada_en_fmt,
                u.usuario_nombre as cargado_por_nombre,
                uc.usuario_nombre as confirmado_por_nombre,
                COUNT(cf.carga_fila_id) as total_filas,
                SUM(CASE WHEN cf.errores IS NULL OR cf.errores = '' THEN 1 ELSE 0 END) as filas_validas,
                SUM(CASE WHEN cf.errores IS NOT NULL AND cf.errores != '' THEN 1 ELSE 0 END) as filas_con_error
            FROM ml__cargas c
            INNER JOIN ml__cargas_tipos ct ON ct.carga_tipo_id = c.carga_tipo_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = c.creado_por
            LEFT JOIN conf__usuarios uc ON uc.usuario_id = c.confirmada_por
            LEFT JOIN ml__cargas_filas cf ON cf.carga_id = c.carga_id
            WHERE {$where_sql}
            GROUP BY c.carga_id
            ORDER BY c.carga_id DESC";

    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        throw new RuntimeException("Error al listar cargas: " . mysqli_error($conexion));
    }

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Obtener detalle de filas de una carga para vista previa
 */
function cargas_obtener_filas(mysqli $conexion, int $carga_id, int $empresa_id): array {
    // Validar pertenencia
    $res_c = mysqli_query($conexion, "SELECT carga_id FROM ml__cargas WHERE carga_id = {$carga_id} AND empresa_id = {$empresa_id} LIMIT 1");
    if (!mysqli_fetch_assoc($res_c)) {
        return [];
    }

    $sql = "SELECT carga_fila_id, nro_fila, contenido, errores
            FROM ml__cargas_filas
            WHERE carga_id = {$carga_id}
            ORDER BY nro_fila ASC";
    $res = mysqli_query($conexion, $sql);
    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['datos'] = json_decode($r['contenido'], true);
        $filas[] = $r;
    }
    return $filas;
}

/**
 * Eliminar / descartar un lote de carga masiva y sus filas asociadas
 */
function cargas_eliminar(mysqli $conexion, int $carga_id, int $empresa_id): array {
    $res_c = mysqli_query($conexion, "SELECT carga_id, archivo_ref FROM ml__cargas WHERE carga_id = {$carga_id} AND empresa_id = {$empresa_id} LIMIT 1");
    $carga = mysqli_fetch_assoc($res_c);
    if (!$carga) {
        return ['success' => false, 'message' => 'Lote de carga no encontrado o no pertenece a la empresa.'];
    }

    // Borrar archivo físico si existe
    if (!empty($carga['archivo_ref'])) {
        $ruta_fisica = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $carga['archivo_ref']);
        if (file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }
    }

    // Borrar filas
    mysqli_query($conexion, "DELETE FROM ml__cargas_filas WHERE carga_id = {$carga_id}");

    // Borrar lote de carga
    $ok = mysqli_query($conexion, "DELETE FROM ml__cargas WHERE carga_id = {$carga_id} AND empresa_id = {$empresa_id}");

    if ($ok) {
        return ['success' => true, 'message' => "Lote de carga #{$carga_id} eliminado exitosamente."];
    }
    return ['success' => false, 'message' => 'Error al eliminar el lote de carga.'];
}

