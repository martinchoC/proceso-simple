<?php
/**
 * stock_niveles_core_model.php — Alertas de reposición por stock mínimo (por boca).
 *
 * Esquema y configuración: sql/mantenimiento/20261008_stock_niveles_0{1,2}_*.sql
 * - gestion__stock_niveles: mínimo / máximo por (empresa, boca, producto), carga manual.
 * - gestion__stock_alertas: alerta MATERIALIZADA. Única escritora: stockEvaluarNivel().
 *
 * Se engancha en el servicio central de stock (stock_core_model.php): cada vez que actualiza
 * gestion__stock.cantidad con la fila bloqueada, llama a stockEvaluarNivel() con la cantidad
 * anterior y la nueva. La carga / edición de niveles llama a stockReevaluarNivel().
 *
 * No abre transacción: corre dentro de la del llamador y lanza Exception ante error.
 * Orden de locks: gestion__stock -> gestion__stock_niveles -> gestion__stock_alertas
 * (stockReevaluarNivel bloquea primero la fila de gestion__stock, igual que un movimiento).
 *
 * Mejora futura (documentada, no implementada): mínimo / máximo sugeridos por consumo histórico.
 * Ver docs/gestion/stock_niveles.md.
 */

if (!defined('STOCK_ALERTA_PENDIENTE')) {
    // Ids GENÉRICOS de conf__estados_registros (catálogo de la tabla 430).
    define('STOCK_ALERTA_PENDIENTE', 17);
    define('STOCK_ALERTA_EN_CURSO', 18);
    define('STOCK_ALERTA_REPUESTA', 21);
    define('STOCK_ALERTA_DESCARTADA', 26);
}

/**
 * @return array<int,true> producto_id => true para los que tienen nivel activo en la boca.
 * Sirve para no evaluar (ni bloquear niveles) en los productos sin mínimo cargado.
 */
function stockNivelesActivos(mysqli $conexion, int $empresa_id, int $boca_id, array $producto_ids): array
{
    $producto_ids = array_values(array_unique(array_map('intval', $producto_ids)));
    if (!$producto_ids) return [];
    $marcas = implode(',', array_fill(0, count($producto_ids), '?'));
    $stmt = mysqli_prepare($conexion, "SELECT producto_id FROM gestion__stock_niveles
                                       WHERE empresa_id = ? AND boca_id = ? AND tabla_estado_registro_id = 1
                                         AND producto_id IN ($marcas)");
    $params = array_merge([$empresa_id, $boca_id], $producto_ids);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $out[intval($r['producto_id'])] = true;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

function stockNivelSugerida(float $minimo, ?float $maximo, float $stock): float
{
    return max(($maximo ?? $minimo) - $stock, 0.0);
}

/**
 * ÚNICA fuente de las alertas de reposición. Llamar con la fila de gestion__stock ya bloqueada.
 *   - nivel inexistente o inactivo y alerta abierta   -> cierra (26, NIVEL_INACTIVO)
 *   - stock < mínimo y alerta abierta                 -> actualiza stock_actual / copias / sugerida
 *   - stock < mínimo, sin alerta, y cruzó el mínimo hacia abajo (o cambió el nivel) -> abre (17)
 *   - stock >= mínimo y alerta abierta                -> 21 REPOSICION, o 26 NIVEL_MODIFICADO si
 *                                                       lo que cambió fue el mínimo
 * Disparo por CRUCE: una alerta descartada con el stock todavía bajo no se reabre con el próximo
 * egreso; vuelve a abrirse cuando reponen y cae de nuevo, o cuando cambian el mínimo.
 *
 * @param bool $por_cambio_nivel true si la llamada viene de cargar / habilitar / cambiar el mínimo.
 */
function stockEvaluarNivel(mysqli $conexion, int $empresa_id, int $boca_id, int $producto_id, float $anterior,
                           float $nueva, ?int $stock_movimiento_id = null, bool $por_cambio_nivel = false,
                           ?int $usuario_id = null): void
{
    $stmt = mysqli_prepare($conexion, "SELECT stock_nivel_id, stock_minimo, stock_maximo, tabla_estado_registro_id
                                       FROM gestion__stock_niveles
                                       WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?
                                       FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $nivel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "SELECT stock_alerta_id FROM gestion__stock_alertas
                                       WHERE empresa_id = ? AND boca_id = ? AND producto_id = ? AND abierta = 1
                                       FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $alerta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $alerta_id = $alerta ? intval($alerta['stock_alerta_id']) : 0;

    if (!$nivel || intval($nivel['tabla_estado_registro_id']) !== 1) {
        if ($alerta_id) {
            stockNivelCerrarAlerta($conexion, $alerta_id, STOCK_ALERTA_DESCARTADA, 'NIVEL_INACTIVO', $usuario_id, $nueva);
        }
        return;
    }

    $minimo = floatval($nivel['stock_minimo']);
    $maximo = $nivel['stock_maximo'] !== null ? floatval($nivel['stock_maximo']) : null;
    $eps = 0.0000005;
    $bajo_minimo = ($minimo - $nueva) > $eps;
    $sugerida = stockNivelSugerida($minimo, $maximo, $nueva);
    $nivel_id = intval($nivel['stock_nivel_id']);

    if ($bajo_minimo) {
        if ($alerta_id) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_alertas
                                               SET stock_actual = ?, stock_minimo = ?, stock_maximo = ?, cantidad_sugerida = ?
                                               WHERE stock_alerta_id = ?");
            mysqli_stmt_bind_param($stmt, 'ddddi', $nueva, $minimo, $maximo, $sugerida, $alerta_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error actualizando alerta de stock: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            return;
        }
        $cruzo = ($anterior - $minimo) > -$eps; // anterior >= mínimo
        if (!$cruzo && !$por_cambio_nivel) {
            return;
        }
        $estado = STOCK_ALERTA_PENDIENTE;
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock_alertas
                                           (empresa_id, boca_id, producto_id, stock_nivel_id, abierta, f_disparo,
                                            stock_al_disparo, stock_minimo, stock_maximo, stock_actual, cantidad_sugerida,
                                            stock_movimiento_id, tabla_estado_registro_id)
                                           VALUES (?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiidddddii', $empresa_id, $boca_id, $producto_id, $nivel_id, $nueva, $minimo,
            $maximo, $nueva, $sugerida, $stock_movimiento_id, $estado);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error creando alerta de stock: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        error_log("Alerta de reposición abierta: producto $producto_id boca $boca_id stock $nueva < mínimo $minimo");
        return;
    }

    if ($alerta_id) {
        if ($por_cambio_nivel) {
            stockNivelCerrarAlerta($conexion, $alerta_id, STOCK_ALERTA_DESCARTADA, 'NIVEL_MODIFICADO', $usuario_id, $nueva);
        } else {
            stockNivelCerrarAlerta($conexion, $alerta_id, STOCK_ALERTA_REPUESTA, 'REPOSICION', null, $nueva);
        }
    }
}

/** Cierra una alerta abierta (abierta = NULL libera el UNIQUE para la próxima). */
function stockNivelCerrarAlerta(mysqli $conexion, int $stock_alerta_id, int $estado_id, string $motivo, ?int $usuario_id,
                                ?float $stock_actual = null): void
{
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_alertas
                                       SET abierta = NULL, tabla_estado_registro_id = ?, motivo_cierre = ?,
                                           f_cierre = NOW(), usuario_cierre_id = ?,
                                           stock_actual = COALESCE(?, stock_actual)
                                       WHERE stock_alerta_id = ? AND abierta = 1");
    mysqli_stmt_bind_param($stmt, 'isidi', $estado_id, $motivo, $usuario_id, $stock_actual, $stock_alerta_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error cerrando alerta de stock: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    error_log("Alerta de reposición $stock_alerta_id cerrada: $motivo");
}

/** Saldo actual de la boca con la fila bloqueada (0 si el producto nunca se movió en la boca). */
function stockNivelBloquearSaldo(mysqli $conexion, int $empresa_id, int $boca_id, int $producto_id): float
{
    $stmt = mysqli_prepare($conexion, "SELECT cantidad FROM gestion__stock
                                       WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?
                                       FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ? floatval($fila['cantidad']) : 0.0;
}

/**
 * Reevalúa la alerta de un par boca+producto después de cargar, habilitar, inhabilitar o editar
 * su nivel.
 * @param bool $cambio_minimo false si no cambió nada que afecte la alerta (p. ej. solo observaciones
 *                            o el máximo): así no reabre una alerta descartada.
 */
function stockReevaluarNivel(mysqli $conexion, int $empresa_id, int $boca_id, int $producto_id, ?int $usuario_id,
                             bool $cambio_minimo = true): void
{
    $cantidad = stockNivelBloquearSaldo($conexion, $empresa_id, $boca_id, $producto_id);
    stockEvaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $cantidad, $cantidad, null, $cambio_minimo, $usuario_id);
}
