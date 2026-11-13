<?php
/**
 * Relaciones entre tipos de comprobante de la empresa (función "Relaciones" de Tipos de Comprobante, páginas 45 / 545):
 *  - gestion__comprobantes_tipos_cancelaciones: el tipo ORIGEN cancela al DESTINO (recibo -> factura, NC -> factura...).
 *    La leen las cuentas corrientes, la consulta de comprobantes y tesorería para saber qué tiene saldo y qué lo cancela.
 *  - gestion__comprobantes_tipos_dependencias: el tipo se asocia a un comprobante PADRE (NC / ND -> factura). La usan
 *    facturación (tipos raíz), compras y percepciones.
 * Quitar una relación la deja en estado 2 (todas las lecturas filtran estado 1); volver a marcarla la reactiva.
 * Alta de la función: _local/sql/mantenimiento/20261019_comprobantes_tipos_relaciones_01.sql
 */

function ctrFilas($conexion, $sql, $tipos = '', ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Relaciones de tipos: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Tipos activos de la empresa (los elegibles), indexados por id. */
function ctrTiposEmpresa($conexion, $empresa_id)
{
    $out = [];
    foreach (ctrFilas($conexion,
        "SELECT ct.comprobante_tipo_id, ct.codigo, ct.comprobante_tipo, ct.letra, ct.signo, sg.comprobante_subgrupo
         FROM gestion__comprobantes_tipos ct
         LEFT JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
         WHERE ct.empresa_id = ? AND ct.tabla_estado_registro_id = 1
         ORDER BY sg.comprobante_subgrupo, ct.orden, ct.comprobante_tipo", 'i', intval($empresa_id)) as $t) {
        $out[intval($t['comprobante_tipo_id'])] = $t;
    }
    return $out;
}

/** Relaciones activas del tipo, en los dos sentidos, y los tipos elegibles. */
function ctrObtener($conexion, $empresa_id, $tipo_id)
{
    $e = intval($empresa_id);
    $t = intval($tipo_id);
    $tipos = ctrTiposEmpresa($conexion, $e);
    if (!isset($tipos[$t])) throw new Exception('Tipo de comprobante no encontrado o inactivo.');
    return [
        'tipo' => $tipos[$t],
        'tipos' => array_values($tipos),
        'cancela' => ctrFilas($conexion, "SELECT comprobante_tipo_destino_id AS comprobante_tipo_id, exige_misma_entidad FROM gestion__comprobantes_tipos_cancelaciones
                                         WHERE empresa_id = ? AND comprobante_tipo_origen_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t),
        'cancelado_por' => array_map('intval', array_column(ctrFilas($conexion,
            "SELECT comprobante_tipo_origen_id FROM gestion__comprobantes_tipos_cancelaciones
             WHERE empresa_id = ? AND comprobante_tipo_destino_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t), 'comprobante_tipo_origen_id')),
        'padres' => array_map('intval', array_column(ctrFilas($conexion,
            "SELECT comprobante_tipo_padre_id FROM gestion__comprobantes_tipos_dependencias
             WHERE empresa_id = ? AND comprobante_tipo_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t), 'comprobante_tipo_padre_id')),
        'hijos' => array_map('intval', array_column(ctrFilas($conexion,
            "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos_dependencias
             WHERE empresa_id = ? AND comprobante_tipo_padre_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t), 'comprobante_tipo_id')),
    ];
}

/**
 * Reemplaza las relaciones del tipo: $cancela = [[comprobante_tipo_id, exige_misma_entidad], ...] (tipos que cancela)
 * y $padres = [comprobante_tipo_id, ...] (comprobantes a los que se asocia). Devuelve [altas, bajas].
 */
function ctrGuardar($conexion, $empresa_id, $usuario_id, $tipo_id, array $cancela, array $padres)
{
    $e = intval($empresa_id);
    $t = intval($tipo_id);
    $uid = intval($usuario_id);
    $tipos = ctrTiposEmpresa($conexion, $e);
    if (!isset($tipos[$t])) throw new Exception('Tipo de comprobante no encontrado o inactivo.');

    $destinos = [];
    foreach ($cancela as $c) {
        $id = intval(is_array($c) ? ($c['comprobante_tipo_id'] ?? 0) : $c);
        if ($id === $t) throw new Exception('Un tipo no se cancela a sí mismo.');
        if (!isset($tipos[$id])) throw new Exception("El tipo $id no es un tipo activo de la empresa.");
        $destinos[$id] = (is_array($c) && isset($c['exige_misma_entidad']) && !$c['exige_misma_entidad']) ? 0 : 1;
    }
    $padres_ok = [];
    foreach ($padres as $p) {
        $id = intval($p);
        if ($id === $t) throw new Exception('Un tipo no es padre de sí mismo.');
        if (!isset($tipos[$id])) throw new Exception("El tipo $id no es un tipo activo de la empresa.");
        // Sin ciclos directos: el padre no puede depender a su vez de este tipo
        if (ctrFilas($conexion, "SELECT 1 FROM gestion__comprobantes_tipos_dependencias WHERE empresa_id = ? AND comprobante_tipo_id = ?
                                 AND comprobante_tipo_padre_id = ? AND tabla_estado_registro_id = 1", 'iii', $e, $id, $t)) {
            throw new Exception("{$tipos[$id]['comprobante_tipo']} ya depende de {$tipos[$t]['comprobante_tipo']}: no puede ser su padre.");
        }
        $padres_ok[$id] = true;
    }

    $altas = $bajas = 0;
    mysqli_begin_transaction($conexion);
    try {
        // Cancelaciones de este tipo como origen
        foreach (ctrFilas($conexion, "SELECT comprobante_tipo_destino_id d FROM gestion__comprobantes_tipos_cancelaciones
                                     WHERE empresa_id = ? AND comprobante_tipo_origen_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t) as $r) {
            if (!isset($destinos[intval($r['d'])])) {
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_tipos_cancelaciones SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                                   WHERE empresa_id = ? AND comprobante_tipo_origen_id = ? AND comprobante_tipo_destino_id = ?");
                $d = intval($r['d']);
                mysqli_stmt_bind_param($stmt, 'iiii', $uid, $e, $t, $d);
                mysqli_stmt_execute($stmt);
                $bajas += mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        foreach ($destinos as $d => $misma) {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_tipos_cancelaciones
                    (empresa_id, comprobante_tipo_origen_id, comprobante_tipo_destino_id, exige_misma_entidad, tabla_estado_registro_id, usuario_id)
                    VALUES (?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE exige_misma_entidad = VALUES(exige_misma_entidad), tabla_estado_registro_id = 1, usuario_modificacion_id = VALUES(usuario_id)");
            mysqli_stmt_bind_param($stmt, 'iiiii', $e, $t, $d, $misma, $uid);
            mysqli_stmt_execute($stmt);
            $altas += mysqli_stmt_affected_rows($stmt) > 0 ? 1 : 0;
            mysqli_stmt_close($stmt);
        }
        // Dependencias de este tipo como hijo
        foreach (ctrFilas($conexion, "SELECT comprobante_tipo_padre_id p FROM gestion__comprobantes_tipos_dependencias
                                     WHERE empresa_id = ? AND comprobante_tipo_id = ? AND tabla_estado_registro_id = 1", 'ii', $e, $t) as $r) {
            if (!isset($padres_ok[intval($r['p'])])) {
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_tipos_dependencias SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                                   WHERE empresa_id = ? AND comprobante_tipo_id = ? AND comprobante_tipo_padre_id = ?");
                $p = intval($r['p']);
                mysqli_stmt_bind_param($stmt, 'iiii', $uid, $e, $t, $p);
                mysqli_stmt_execute($stmt);
                $bajas += mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        foreach (array_keys($padres_ok) as $p) {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes_tipos_dependencias
                    (empresa_id, comprobante_tipo_id, comprobante_tipo_padre_id, tabla_estado_registro_id, usuario_id)
                    VALUES (?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1, usuario_modificacion_id = VALUES(usuario_id)");
            mysqli_stmt_bind_param($stmt, 'iiii', $e, $t, $p, $uid);
            mysqli_stmt_execute($stmt);
            $altas += mysqli_stmt_affected_rows($stmt) > 0 ? 1 : 0;
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
    } catch (Exception $ex) {
        mysqli_rollback($conexion);
        throw $ex;
    }
    error_log("=== ctrGuardar empresa $e tipo $t: cancela " . implode(',', array_keys($destinos)) . ' / padres ' . implode(',', array_keys($padres_ok)) . " ===");
    return [$altas, $bajas];
}
