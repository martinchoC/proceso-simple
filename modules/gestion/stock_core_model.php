<?php
require_once __DIR__ . '/../../core/formato.php';

/**
 * stock_core_model.php
 *
 * Movimientos de stock por boca - Servicio central
 * Sistema gestion_multipyme
 *
 * Esquema y configuración: _local/sql/mantenimiento/20261008_stock_0{1,2,3}_*.sql
 * - gestion__stock_movimientos: libro inmutable (una fila por producto/boca/movimiento,
 *   cantidad > 0, dirección en tipo_movimiento). La cabecera es gestion__comprobantes.
 * - gestion__stock: saldo materializado por (empresa, boca, producto), mismo patrón que
 *   gestion__tesoreria_cuentas.saldo_actual. Este archivo es su único escritor.
 * - QUÉ mueve stock: gestion__comprobantes_tipos.impacta_stock + stock_signo ('+', '-', '+/-').
 *   CUÁNDO: conf__tablas_estados_registros.aplica_stock del estado actual del documento.
 *   Nada está hardcodeado por tipo de documento.
 *
 * Las funciones NO abren transacción: corren dentro de la del modelo que las llama.
 * Locks: filas de gestion__stock en orden (boca_id, producto_id) ASC.
 */

if (!defined('STOCK_EPSILON')) define('STOCK_EPSILON', 0.00005);

// Stock mínimo/máximo y alertas de reposición: stockEvaluarNivel() se llama con la fila de
// gestion__stock ya bloqueada (orden de locks: gestion__stock -> _niveles -> _alertas).
if (file_exists(__DIR__ . '/stock_niveles_core_model.php')) {
    require_once __DIR__ . '/stock_niveles_core_model.php';
}

/**
 * Lleva el libro al estado que corresponde al documento AHORA y devuelve cuántos movimientos generó.
 *
 * Por cada línea calcula cuánto debería haber movido (0 si el tipo no mueve stock, si el estado no
 * tiene aplica_stock o si el producto no controla stock), le resta lo que ya movió según el libro y
 * registra solo la diferencia. Por eso es idempotente: llamarla dos veces no duplica, y confirmar,
 * anular, volver a borrador o re-confirmar quedan resueltos sin código propio para cada caso.
 * Conviene llamarla en TODA transición de estado del documento, después de actualizar su estado.
 *
 * Regla de antecesor (evita contar dos veces): si la línea viene de otra línea que ya movió stock
 * en el mismo sentido (factura de venta desde remito), solo mueve lo que el antecesor no movió.
 *
 * @param mysqli $conexion
 * @param array  $doc    empresa_id, comprobante_id, comprobante_tipo_id, tabla_origen_id, registro_origen_id,
 *                       estado_registro_id (estado genérico actual del documento), boca_id (default de las líneas),
 *                       usuario_id, observaciones
 * @param array  $lineas cada una: registro_detalle_id, producto_id, cantidad (con signo si el tipo es '+/-'),
 *                       boca_id (opcional), costo_unitario (opcional),
 *                       antecesor_tabla_id + antecesor_detalle_id (opcional)
 * @return array ['success' => true, 'movimientos' => int]
 * @throws RuntimeException
 */
function stockSincronizarComprobante($conexion, array $doc, array $lineas)
{
    $empresa_id          = intval($doc['empresa_id'] ?? 0);
    $comprobante_id      = intval($doc['comprobante_id'] ?? 0);
    $comprobante_tipo_id = intval($doc['comprobante_tipo_id'] ?? 0);
    $tabla_origen_id     = intval($doc['tabla_origen_id'] ?? 0);
    $registro_origen_id  = intval($doc['registro_origen_id'] ?? 0);
    $estado_registro_id  = intval($doc['estado_registro_id'] ?? 0);
    $boca_default        = intval($doc['boca_id'] ?? 0);
    $usuario_id          = !empty($doc['usuario_id']) ? intval($doc['usuario_id']) : null;
    $observaciones       = mb_substr(trim((string)($doc['observaciones'] ?? '')), 0, 255);

    error_log("=== INICIO stockSincronizarComprobante tabla $tabla_origen_id registro $registro_origen_id estado $estado_registro_id ===");

    if ($empresa_id <= 0 || $tabla_origen_id <= 0 || $registro_origen_id <= 0) {
        throw new RuntimeException('Stock: faltan empresa, tabla o registro de origen.');
    }

    // 1) ¿El documento, en este estado, tiene que tener el stock aplicado?
    $sentido = stockSentidoTipo($conexion, $comprobante_tipo_id, $empresa_id);
    $aplica  = $sentido !== null && stockEstadoAplica($conexion, $tabla_origen_id, $estado_registro_id);

    // 2) Lo que ya movió cada línea según el libro
    $claves = [];
    $sql = "SELECT registro_detalle_id, producto_id, boca_id,
                   SUM(CASE tipo_movimiento WHEN 'INGRESO' THEN cantidad ELSE -cantidad END) AS neto
            FROM gestion__stock_movimientos
            WHERE empresa_id = ? AND tabla_origen_id = ? AND registro_origen_id = ?
            GROUP BY registro_detalle_id, producto_id, boca_id";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new RuntimeException('Stock: error leyendo el libro: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $tabla_origen_id, $registro_origen_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $k = intval($r['boca_id']) . '|' . intval($r['producto_id']) . '|' . intval($r['registro_detalle_id']);
        $claves[$k] = [
            'boca_id' => intval($r['boca_id']), 'producto_id' => intval($r['producto_id']),
            'registro_detalle_id' => intval($r['registro_detalle_id']),
            'actual' => floatval($r['neto']), 'objetivo' => 0.0, 'costo' => null,
        ];
    }
    mysqli_stmt_close($stmt);

    // 3) Lo que cada línea debería haber movido
    if ($aplica && count($lineas) > 0) {
        $controlados = stockProductosControlados($conexion, $empresa_id, array_column($lineas, 'producto_id'));
        foreach ($lineas as $l) {
            $producto_id = intval($l['producto_id'] ?? 0);
            if (!isset($controlados[$producto_id])) continue;   // servicio / concepto sin stock

            $cantidad = round(floatval($l['cantidad'] ?? 0), 4);
            $signo = ($sentido === '+/-') ? ($cantidad < 0 ? -1 : 1) : ($sentido === '+' ? 1 : -1);
            $qty = abs($cantidad);

            $ant_tabla = intval($l['antecesor_tabla_id'] ?? 0);
            $ant_det   = intval($l['antecesor_detalle_id'] ?? 0);
            if ($ant_tabla > 0 && $ant_det > 0) {
                $previo = stockNetoLinea($conexion, $empresa_id, $ant_tabla, $ant_det, $producto_id);
                if ($previo * $signo > 0) $qty = max(0.0, $qty - abs($previo));
            }
            if ($qty < STOCK_EPSILON) continue;

            $boca_id = intval($l['boca_id'] ?? 0) ?: $boca_default;
            if ($boca_id <= 0) throw new RuntimeException('Stock: el comprobante no tiene boca asignada.');

            $det = intval($l['registro_detalle_id'] ?? 0);
            $k = "$boca_id|$producto_id|$det";
            if (!isset($claves[$k])) {
                $claves[$k] = ['boca_id' => $boca_id, 'producto_id' => $producto_id, 'registro_detalle_id' => $det,
                               'actual' => 0.0, 'objetivo' => 0.0, 'costo' => null];
            }
            $claves[$k]['objetivo'] += $signo * $qty;
            if (isset($l['costo_unitario']) && floatval($l['costo_unitario']) > 0) {
                $claves[$k]['costo'] = floatval($l['costo_unitario']);
            }
        }
    }

    // 4) Diferencias, en orden de lock
    $deltas = [];
    foreach ($claves as $c) {
        $c['delta'] = round($c['objetivo'] - $c['actual'], 4);
        if (abs($c['delta']) >= STOCK_EPSILON) $deltas[] = $c;
    }
    if (count($deltas) === 0) {
        return ['success' => true, 'movimientos' => 0];
    }
    if ($comprobante_id <= 0) {
        throw new RuntimeException('Stock: el documento no tiene comprobante sincronizado.');
    }
    usort($deltas, function ($a, $b) {
        return [$a['boca_id'], $a['producto_id'], $a['registro_detalle_id']]
           <=> [$b['boca_id'], $b['producto_id'], $b['registro_detalle_id']];
    });

    $bocas = [];
    $ahora = date('Y-m-d H:i:s');
    $n = 0;
    foreach ($deltas as $d) {
        $boca_id = $d['boca_id'];
        if (!isset($bocas[$boca_id])) $bocas[$boca_id] = stockObtenerBoca($conexion, $empresa_id, $boca_id);
        $boca = $bocas[$boca_id];

        $tipo_mov = $d['delta'] > 0 ? 'INGRESO' : 'EGRESO';
        $qty = abs($d['delta']);
        // Las validaciones de la boca valen al aplicar; deshacer (anular, volver a borrador) siempre se permite.
        if ($aplica) {
            if ($tipo_mov === 'INGRESO' && intval($boca['permite_ingresos']) !== 1) {
                throw new RuntimeException("La boca '{$boca['boca_nombre']}' no admite ingresos de mercadería.");
            }
            if ($tipo_mov === 'EGRESO' && intval($boca['permite_egresos']) !== 1) {
                throw new RuntimeException("La boca '{$boca['boca_nombre']}' no admite egresos de mercadería.");
            }
        }

        $saldo = stockBloquearSaldo($conexion, $empresa_id, $boca_id, intval($boca['sucursal_id']), $d['producto_id']);
        $cantidad_ant = floatval($saldo['cantidad']);
        $nueva = round($cantidad_ant + $d['delta'], 4);

        if ($aplica && $tipo_mov === 'EGRESO' && intval($boca['permite_stock_negativo']) !== 1 && $nueva < -STOCK_EPSILON) {
            $prod = stockCodigoProducto($conexion, $d['producto_id']);
            throw new RuntimeException("Stock insuficiente de $prod en '{$boca['boca_nombre']}'. Disponible: "
                . fmt_numero($cantidad_ant, 2) . ', requerido: ' . fmt_numero($qty, 2));
        }

        $costo_promedio = floatval($saldo['costo_promedio']);
        $costo_ultima   = floatval($saldo['costo_ultima_compra']);
        if ($aplica && $tipo_mov === 'INGRESO' && $d['costo'] !== null) {
            $base = max($cantidad_ant, 0.0);
            $costo_promedio = round(($base * $costo_promedio + $qty * $d['costo']) / ($base + $qty), 6);
            $costo_ultima = $d['costo'];
        }

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock
                                           SET cantidad = ?, costo_promedio = ?, costo_ultima_compra = ?, f_ultimo_movimiento = ?
                                           WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?");
        if (!$stmt) throw new RuntimeException('Stock: error preparando actualización de saldo: ' . mysqli_error($conexion));
        mysqli_stmt_bind_param($stmt, 'dddsiii', $nueva, $costo_promedio, $costo_ultima, $ahora, $empresa_id, $boca_id, $d['producto_id']);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Stock: error actualizando saldo: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $sucursal_id = intval($boca['sucursal_id']);
        $costo = $d['costo'];
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock_movimientos
            (empresa_id, sucursal_id, boca_id, producto_id, tipo_movimiento, cantidad, costo_unitario,
             comprobante_id, comprobante_tipo_id, tabla_origen_id, registro_origen_id, registro_detalle_id,
             f_movimiento, observaciones, tabla_estado_registro_id, usuario_id, f_alta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        if (!$stmt) throw new RuntimeException('Stock: error preparando movimiento: ' . mysqli_error($conexion));
        mysqli_stmt_bind_param($stmt, 'iiiisddiiiiissis',
            $empresa_id, $sucursal_id, $boca_id, $d['producto_id'], $tipo_mov, $qty, $costo,
            $comprobante_id, $comprobante_tipo_id, $tabla_origen_id, $registro_origen_id, $d['registro_detalle_id'],
            $ahora, $observaciones, $usuario_id, $ahora);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Stock: error registrando movimiento: ' . mysqli_stmt_error($stmt));
        $stock_movimiento_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        $n++;

        if (function_exists('stockEvaluarNivel')) {
            stockEvaluarNivel($conexion, $empresa_id, $boca_id, $d['producto_id'], (float)$cantidad_ant, (float)$nueva, $stock_movimiento_id);
        }
    }

    error_log("=== FIN stockSincronizarComprobante: $n movimientos ===");
    return ['success' => true, 'movimientos' => $n];
}

/** Sentido en stock del tipo ('+', '-', '+/-') o null si no mueve. */
function stockSentidoTipo($conexion, $comprobante_tipo_id, $empresa_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT impacta_stock, stock_signo FROM gestion__comprobantes_tipos
                                       WHERE comprobante_tipo_id = ? AND empresa_id IN (0, ?) LIMIT 1");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo el tipo de comprobante: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'ii', $comprobante_tipo_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$r || intval($r['impacta_stock']) !== 1) return null;
    if ($r['stock_signo'] === null || $r['stock_signo'] === '') {
        error_log("stockSentidoTipo: el tipo $comprobante_tipo_id impacta stock pero no tiene stock_signo; se ignora");
        return null;
    }
    return $r['stock_signo'];
}

/** ¿El estado genérico tiene aplica_stock en el catálogo de esa tabla? */
function stockEstadoAplica($conexion, $tabla_id, $estado_registro_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT aplica_stock FROM conf__tablas_estados_registros
                                       WHERE tabla_id = ? AND estado_registro_id = ? LIMIT 1");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo estados de la tabla: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'ii', $tabla_id, $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $r && intval($r['aplica_stock']) === 1;
}

/** producto_id => true para los productos de la empresa que controlan stock. */
function stockProductosControlados($conexion, $empresa_id, array $producto_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
    if (count($ids) === 0) return [];
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = mysqli_prepare($conexion, "SELECT producto_id FROM gestion__productos
                                       WHERE empresa_id = ? AND controla_stock = 1 AND es_servicio = 0
                                       AND producto_id IN ($marcas)");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo productos: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids) + 1), $empresa_id, ...$ids);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[intval($r['producto_id'])] = true;
    mysqli_stmt_close($stmt);
    return $out;
}

/** Neto (con signo) que movió una línea de otro documento. */
function stockNetoLinea($conexion, $empresa_id, $tabla_origen_id, $registro_detalle_id, $producto_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(SUM(CASE tipo_movimiento WHEN 'INGRESO' THEN cantidad ELSE -cantidad END), 0) AS neto
                                       FROM gestion__stock_movimientos
                                       WHERE empresa_id = ? AND tabla_origen_id = ? AND registro_detalle_id = ? AND producto_id = ?");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo línea antecesora: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iiii', $empresa_id, $tabla_origen_id, $registro_detalle_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return floatval($r['neto'] ?? 0);
}

function stockObtenerBoca($conexion, $empresa_id, $boca_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT boca_id, sucursal_id, boca_nombre, permite_ingresos, permite_egresos, permite_stock_negativo
                                       FROM gestion__bocas WHERE boca_id = ? AND empresa_id = ?");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo la boca: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'ii', $boca_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$r) throw new RuntimeException("Stock: la boca $boca_id no existe en la empresa.");
    return $r;
}

/** Crea la fila de saldo si falta y la devuelve bloqueada (FOR UPDATE). */
function stockBloquearSaldo($conexion, $empresa_id, $boca_id, $sucursal_id, $producto_id)
{
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock (empresa_id, boca_id, producto_id, sucursal_id, cantidad)
                                       VALUES (?, ?, ?, ?, 0)
                                       ON DUPLICATE KEY UPDATE cantidad = cantidad");
    if (!$stmt) throw new RuntimeException('Stock: error preparando saldo: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iiii', $empresa_id, $boca_id, $producto_id, $sucursal_id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Stock: error creando saldo: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "SELECT cantidad, costo_promedio, costo_ultima_compra FROM gestion__stock
                                       WHERE empresa_id = ? AND boca_id = ? AND producto_id = ? FOR UPDATE");
    if (!$stmt) throw new RuntimeException('Stock: error bloqueando saldo: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $r;
}

function stockCodigoProducto($conexion, $producto_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT producto_codigo, producto_nombre FROM gestion__productos WHERE producto_id = ?");
    if (!$stmt) return "producto $producto_id";
    mysqli_stmt_bind_param($stmt, 'i', $producto_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $r ? trim($r['producto_codigo'] . ' ' . $r['producto_nombre']) : "producto $producto_id";
}

/** conf__tablas.tabla_id por nombre físico (para el antecesor: 'gestion__ventas_remitos', ...). */
function stockTablaIdPorNombre($conexion, $tabla_nombre)
{
    $stmt = mysqli_prepare($conexion, "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = ? LIMIT 1");
    if (!$stmt) throw new RuntimeException('Stock: error leyendo conf__tablas: ' . mysqli_error($conexion));
    mysqli_stmt_bind_param($stmt, 's', $tabla_nombre);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($r['tabla_id'] ?? 0);
}

/** Stock actual de un producto en una boca (lectura del materializado, sin lock). */
function stockObtener($conexion, $empresa_id, $boca_id, $producto_id)
{
    $stmt = mysqli_prepare($conexion, "SELECT cantidad FROM gestion__stock WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?");
    if (!$stmt) return 0.0;
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return floatval($r['cantidad'] ?? 0);
}

/**
 * Rearma gestion__stock.cantidad desde el libro (boca/producto 0 = todos). No toca el costo.
 * Correr dentro de una transacción; ver también _local/sql/mantenimiento/20261008_stock_03_verificacion.sql.
 */
function stockRecalcular($conexion, $empresa_id, $boca_id = 0, $producto_id = 0)
{
    $empresa_id = intval($empresa_id); $boca_id = intval($boca_id); $producto_id = intval($producto_id);

    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock (empresa_id, boca_id, producto_id, sucursal_id, cantidad)
                                       SELECT empresa_id, boca_id, producto_id, MAX(sucursal_id), 0
                                       FROM gestion__stock_movimientos
                                       WHERE empresa_id = ? AND (? = 0 OR boca_id = ?) AND (? = 0 OR producto_id = ?)
                                       GROUP BY empresa_id, boca_id, producto_id
                                       ON DUPLICATE KEY UPDATE cantidad = gestion__stock.cantidad");
    mysqli_stmt_bind_param($stmt, 'iiiii', $empresa_id, $boca_id, $boca_id, $producto_id, $producto_id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Stock: error recalculando: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock s
                                       LEFT JOIN (SELECT boca_id, producto_id,
                                                         SUM(CASE tipo_movimiento WHEN 'INGRESO' THEN cantidad ELSE -cantidad END) AS libro,
                                                         MAX(f_movimiento) AS f_ultimo
                                                  FROM gestion__stock_movimientos
                                                  WHERE empresa_id = ?
                                                  GROUP BY boca_id, producto_id) x
                                              ON x.boca_id = s.boca_id AND x.producto_id = s.producto_id
                                       SET s.cantidad = COALESCE(x.libro, 0), s.f_ultimo_movimiento = x.f_ultimo
                                       WHERE s.empresa_id = ? AND (? = 0 OR s.boca_id = ?) AND (? = 0 OR s.producto_id = ?)");
    mysqli_stmt_bind_param($stmt, 'iiiiii', $empresa_id, $empresa_id, $boca_id, $boca_id, $producto_id, $producto_id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Stock: error recalculando: ' . mysqli_stmt_error($stmt));
    $afectadas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    // El rearmado no pasa por stockEvaluarNivel(): reevaluar las alertas de los pares con nivel cargado.
    if (function_exists('stockReevaluarNivel')) {
        $stmt = mysqli_prepare($conexion, "SELECT boca_id, producto_id FROM gestion__stock_niveles
                                           WHERE empresa_id = ? AND (? = 0 OR boca_id = ?) AND (? = 0 OR producto_id = ?)
                                           ORDER BY boca_id, producto_id");
        mysqli_stmt_bind_param($stmt, 'iiiii', $empresa_id, $boca_id, $boca_id, $producto_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $pares = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        foreach ($pares as $p) {
            stockReevaluarNivel($conexion, $empresa_id, intval($p['boca_id']), intval($p['producto_id']), null, false);
        }
    }
    return $afectadas;
}
