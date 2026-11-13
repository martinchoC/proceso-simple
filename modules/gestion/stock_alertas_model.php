<?php
require_once __DIR__ . '/../../core/permisos.php';
/**
 * stock_alertas_model.php — Alertas de reposición (página 113, tabla 430).
 *
 * Las alertas las ABRE y las CIERRA por reposición solo stockEvaluarNivel()
 * (stock_niveles_core_model.php). Desde esta pantalla el usuario únicamente:
 *   Tomar     17 -> 18 (queda en gestión, con quién y cuándo)
 *   Descartar 17/18 -> 26 (motivo DESCARTADA; no se reabre hasta que reponen y vuelve a caer)
 */
require_once __DIR__ . '/stock_niveles_core_model.php';
if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
}

const SAL_TABLA_ID = 430;

function salFuncionesPagina(mysqli $conexion, int $pagina_id): array
{
    static $cache = [];
    if (isset($cache[$pagina_id])) return $cache[$pagina_id];
    $stmt = mysqli_prepare($conexion, "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.accion_js, pf.descripcion,
                                              pf.tabla_estado_registro_origen_id AS origen,
                                              pf.tabla_estado_registro_destino_id AS destino,
                                              i.icono_clase, c.color_clase
                                       FROM conf__paginas_funciones pf
                                       LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
                                       LEFT JOIN conf__colores c ON c.color_id = pf.color_id
                                       WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
                                       ORDER BY pf.tabla_estado_registro_origen_id, pf.orden, pf.pagina_funcion_id");
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $cache[$pagina_id] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $cache[$pagina_id];
}

function salBotonesPorEstado(mysqli $conexion, int $pagina_id, int $estado_id): array
{
    $out = [];
    foreach (permisos_filtrar_funciones($conexion, salFuncionesPagina($conexion, $pagina_id), $pagina_id) as $f) {
        if (intval($f['origen']) !== $estado_id) continue;
        $out[] = [
            'nombre_funcion' => $f['nombre_funcion'],
            'accion_js' => $f['accion_js'],
            'descripcion' => $f['descripcion'] ?: $f['nombre_funcion'],
            'icono_clase' => $f['icono_clase'],
            'color_clase' => $f['color_clase'] ?: 'btn-outline-primary',
        ];
    }
    return $out;
}

function salBocas(mysqli $conexion, int $empresa_id, int $usuario_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT b.boca_id, b.sucursal_id, b.boca_nombre, s.sucursal_nombre
                                       FROM gestion__bocas b
                                       JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
                                       WHERE b.empresa_id = ? AND b.es_deposito = 1
                                       ORDER BY s.sucursal_nombre, b.orden, b.boca_nombre");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $bocas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    if (class_exists('AccesosSucursalesHelper')) {
        $bocas = AccesosSucursalesHelper::filtrarBocas($conexion, $empresa_id, $bocas, $usuario_id);
    }
    return $bocas;
}

/**
 * @param string $vista 'abiertas' (17/18) o 'historial' (cerradas de los últimos $dias días)
 */
function salListar(mysqli $conexion, int $empresa_id, int $pagina_id, int $usuario_id, string $vista,
                   int $boca_id = 0, int $dias = 30): array
{
    $sql = "SELECT a.stock_alerta_id, a.boca_id, a.producto_id, a.f_disparo, a.stock_al_disparo,
                   a.stock_minimo, a.stock_maximo, a.stock_actual, a.cantidad_sugerida,
                   a.f_toma, a.f_cierre, a.motivo_cierre, a.tabla_estado_registro_id,
                   p.producto_codigo, p.producto_nombre,
                   b.boca_nombre, s.sucursal_nombre,
                   ut.usuario_nombre AS usuario_toma, uc.usuario_nombre AS usuario_cierre,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre,
                   c.color_clase
            FROM gestion__stock_alertas a
            JOIN gestion__productos p ON p.producto_id = a.producto_id
            JOIN gestion__bocas b ON b.boca_id = a.boca_id
            JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
            LEFT JOIN conf__usuarios ut ON ut.usuario_id = a.usuario_toma_id
            LEFT JOIN conf__usuarios uc ON uc.usuario_id = a.usuario_cierre_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . SAL_TABLA_ID . "
                                                       AND ter.estado_registro_id = a.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            LEFT JOIN conf__colores c ON c.color_id = ter.color_id
            WHERE a.empresa_id = ?";
    $types = 'i';
    $params = [$empresa_id];
    if ($vista === 'historial') {
        $sql .= " AND a.abierta IS NULL AND a.f_cierre >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $types .= 'i';
        $params[] = max(1, min($dias, 366));
    } else {
        $sql .= " AND a.abierta = 1";
    }
    if ($boca_id > 0) {
        $sql .= " AND a.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    if (class_exists('AccesosSucursalesHelper')) {
        $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $usuario_id, $empresa_id, 'b.sucursal_id');
    }
    $sql .= " ORDER BY a.f_disparo DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log('salListar: ' . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['botones'] = salBotonesPorEstado($conexion, $pagina_id, intval($r['tabla_estado_registro_id']));
        $out[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/** Cantidad de alertas abiertas visibles para el usuario (contador del header). */
function salContarAbiertas(mysqli $conexion, int $empresa_id, int $usuario_id): int
{
    $sql = "SELECT COUNT(*) AS n
            FROM gestion__stock_alertas a
            JOIN gestion__bocas b ON b.boca_id = a.boca_id
            WHERE a.empresa_id = ? AND a.abierta = 1";
    if (class_exists('AccesosSucursalesHelper')) {
        $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $usuario_id, $empresa_id, 'b.sucursal_id');
    }
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return 0;
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($fila['n'] ?? 0);
}

/** Tomar (17 -> 18) / Descartar (17|18 -> 26) según conf__paginas_funciones. */
function salEjecutarAccion(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id, int $id, string $accion_js): string
{
    mysqli_begin_transaction($conexion);
    try {
        // Solo se bloquea la alerta (sin tocar saldo ni nivel): no hay ciclo con el orden
        // saldo -> nivel -> alerta de los movimientos.
        $stmt = mysqli_prepare($conexion, "SELECT a.stock_alerta_id, a.abierta, a.tabla_estado_registro_id, b.sucursal_id
                                           FROM gestion__stock_alertas a
                                           JOIN gestion__bocas b ON b.boca_id = a.boca_id
                                           WHERE a.stock_alerta_id = ? AND a.empresa_id = ?
                                           FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $alerta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$alerta) throw new InvalidArgumentException('Alerta no encontrada.');
        if (class_exists('AccesosSucursalesHelper')) {
            AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_id, intval($alerta['sucursal_id']), $usuario_id);
        }
        if (intval($alerta['abierta'] ?? 0) !== 1) {
            throw new InvalidArgumentException('La alerta ya está cerrada (se repuso el stock o la descartaron).');
        }

        $funcion = null;
        foreach (salFuncionesPagina($conexion, $pagina_id) as $f) {
            if (intval($f['origen']) === intval($alerta['tabla_estado_registro_id']) && $f['accion_js'] === $accion_js) {
                $funcion = $f;
                break;
            }
        }
        if (!$funcion) throw new InvalidArgumentException('Acción no permitida para el estado actual.');
        $destino = intval($funcion['destino']);

        if ($destino === STOCK_ALERTA_DESCARTADA) {
            stockNivelCerrarAlerta($conexion, $id, STOCK_ALERTA_DESCARTADA, 'DESCARTADA', $usuario_id);
        } elseif ($destino === STOCK_ALERTA_EN_CURSO) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_alertas
                                               SET tabla_estado_registro_id = ?, usuario_toma_id = ?, f_toma = NOW()
                                               WHERE stock_alerta_id = ? AND abierta = 1");
            mysqli_stmt_bind_param($stmt, 'iii', $destino, $usuario_id, $id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        } else {
            // 21 Repuesta solo la asigna stockEvaluarNivel().
            throw new InvalidArgumentException('Transición no soportada desde esta pantalla.');
        }
        mysqli_commit($conexion);
        return $funcion['nombre_funcion'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}
