<?php
/**
 * stock_niveles_model.php — Stock mínimo / máximo por producto y boca (página 112, tabla 429).
 *
 * Diccionario: 1 Activo / 2 Inactivo (= no controlar). Toda alta / edición / cambio de estado
 * reevalúa la alerta de reposición del par (stockReevaluarNivel, stock_niveles_core_model.php)
 * en la misma transacción.
 */
require_once __DIR__ . '/stock_niveles_core_model.php';
if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
}

const SNV_TABLA_ID = 429;

// ---------------------------------------------------------------------
// Motor de botones
// ---------------------------------------------------------------------
function snvFuncionesPagina(mysqli $conexion, int $pagina_id): array
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

function snvBotonesPorEstado(mysqli $conexion, int $pagina_id, int $estado_id): array
{
    $out = [];
    foreach (snvFuncionesPagina($conexion, $pagina_id) as $f) {
        if (intval($f['origen']) !== $estado_id || $estado_id === 0) continue;
        $out[] = [
            'nombre_funcion' => $f['nombre_funcion'],
            'accion_js' => $f['accion_js'],
            'descripcion' => $f['descripcion'] ?: $f['nombre_funcion'],
            'icono_clase' => $f['icono_clase'],
            'color_clase' => $f['color_clase'] ?: 'btn-outline-primary',
            'es_confirmable' => intval($f['origen']) !== intval($f['destino']) ? 1 : 0,
        ];
    }
    return $out;
}

/** Funciones de creación (origen 0): Agregar y Copiar de otra boca. */
function snvBotonesCreacion(mysqli $conexion, int $pagina_id): array
{
    $out = [];
    foreach (snvFuncionesPagina($conexion, $pagina_id) as $f) {
        if (intval($f['origen']) !== 0) continue;
        $out[] = [
            'nombre_funcion' => $f['nombre_funcion'],
            'accion_js' => $f['accion_js'],
            'icono_clase' => $f['icono_clase'],
            'color_clase' => $f['color_clase'] ?: 'btn-primary',
        ];
    }
    return $out;
}

function snvFuncionCreacionExiste(mysqli $conexion, int $pagina_id, string $accion_js): bool
{
    foreach (snvFuncionesPagina($conexion, $pagina_id) as $f) {
        if (intval($f['origen']) === 0 && $f['accion_js'] === $accion_js) return true;
    }
    return false;
}

// ---------------------------------------------------------------------
// Consultas
// ---------------------------------------------------------------------
/** Bocas que manejan stock (es_deposito = 1), activas, a las que el usuario tiene acceso. */
function snvBocas(mysqli $conexion, int $empresa_id, int $usuario_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT b.boca_id, b.sucursal_id, b.boca_nombre, b.codigo, s.sucursal_nombre
                                       FROM gestion__bocas b
                                       JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
                                       WHERE b.empresa_id = ? AND b.es_deposito = 1 AND b.tabla_estado_registro_id = 1
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

/** Boca válida para cargar niveles: de la empresa, activa, maneja stock y con acceso del usuario. */
function snvExigirBoca(mysqli $conexion, int $empresa_id, int $usuario_id, int $boca_id): array
{
    foreach (snvBocas($conexion, $empresa_id, $usuario_id) as $b) {
        if (intval($b['boca_id']) === $boca_id) return $b;
    }
    throw new RuntimeException('Boca inválida: no existe, no maneja stock o no tiene acceso.');
}

function snvBuscarProductos(mysqli $conexion, int $empresa_id, string $q): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) return [];
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare($conexion, "SELECT p.producto_id, p.producto_codigo, p.producto_nombre
                                       FROM gestion__productos p
                                       WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1 AND p.controla_stock = 1
                                         AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras = ?)
                                       ORDER BY p.producto_nombre
                                       LIMIT 30");
    mysqli_stmt_bind_param($stmt, 'isss', $empresa_id, $like, $like, $q);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function snvListar(mysqli $conexion, int $empresa_id, int $pagina_id, int $usuario_id, int $boca_id = 0): array
{
    $sql = "SELECT n.stock_nivel_id, n.boca_id, n.producto_id, n.stock_minimo, n.stock_maximo, n.observaciones,
                   n.tabla_estado_registro_id, n.f_modificacion,
                   p.producto_codigo, p.producto_nombre,
                   b.boca_nombre, b.sucursal_id, s.sucursal_nombre,
                   COALESCE(st.cantidad, 0) AS stock_actual,
                   a.stock_alerta_id,
                   COALESCE(ter.tabla_estado_registro, er.estado_registro) AS estado_nombre,
                   c.color_clase
            FROM gestion__stock_niveles n
            JOIN gestion__productos p ON p.producto_id = n.producto_id
            JOIN gestion__bocas b ON b.boca_id = n.boca_id
            JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
            LEFT JOIN gestion__stock st ON st.empresa_id = n.empresa_id AND st.boca_id = n.boca_id AND st.producto_id = n.producto_id
            LEFT JOIN gestion__stock_alertas a ON a.empresa_id = n.empresa_id AND a.boca_id = n.boca_id
                                              AND a.producto_id = n.producto_id AND a.abierta = 1
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = " . SNV_TABLA_ID . "
                                                       AND ter.estado_registro_id = n.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = n.tabla_estado_registro_id
            LEFT JOIN conf__colores c ON c.color_id = ter.color_id
            WHERE n.empresa_id = ?";
    $types = 'i';
    $params = [$empresa_id];
    if ($boca_id > 0) {
        $sql .= " AND n.boca_id = ?";
        $types .= 'i';
        $params[] = $boca_id;
    }
    if (class_exists('AccesosSucursalesHelper')) {
        $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $usuario_id, $empresa_id, 'b.sucursal_id');
    }
    $sql .= " ORDER BY s.sucursal_nombre, b.boca_nombre, p.producto_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log('snvListar: ' . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['bajo_minimo'] = intval($r['tabla_estado_registro_id']) === 1
            && floatval($r['stock_actual']) < floatval($r['stock_minimo']) ? 1 : 0;
        $r['botones'] = snvBotonesPorEstado($conexion, $pagina_id, intval($r['tabla_estado_registro_id']));
        $out[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

function snvObtener(mysqli $conexion, int $empresa_id, int $id, bool $bloquear = false): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT n.*, p.producto_codigo, p.producto_nombre, b.boca_nombre, b.sucursal_id
                                       FROM gestion__stock_niveles n
                                       JOIN gestion__productos p ON p.producto_id = n.producto_id
                                       JOIN gestion__bocas b ON b.boca_id = n.boca_id
                                       WHERE n.stock_nivel_id = ? AND n.empresa_id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: null;
}

// ---------------------------------------------------------------------
// Escrituras
// ---------------------------------------------------------------------
/** @return array [minimo, maximo|null, observaciones] validados */
function snvValidarValores(array $data): array
{
    $min_txt = trim((string)($data['stock_minimo'] ?? ''));
    $max_txt = trim((string)($data['stock_maximo'] ?? ''));
    if ($min_txt === '' || !is_numeric($min_txt) || floatval($min_txt) < 0) {
        throw new InvalidArgumentException('El stock mínimo debe ser un número mayor o igual a 0.');
    }
    $minimo = round(floatval($min_txt), 6);
    $maximo = null;
    if ($max_txt !== '') {
        if (!is_numeric($max_txt)) throw new InvalidArgumentException('El stock máximo debe ser un número.');
        $maximo = round(floatval($max_txt), 6);
        if ($maximo < $minimo) throw new InvalidArgumentException('El stock máximo no puede ser menor que el mínimo.');
    }
    $obs = trim((string)($data['observaciones'] ?? ''));
    return [$minimo, $maximo, $obs === '' ? null : mb_substr($obs, 0, 255)];
}

function snvAgregar(mysqli $conexion, int $empresa_id, int $usuario_id, array $data): array
{
    $boca_id = intval($data['boca_id'] ?? 0);
    $producto_id = intval($data['producto_id'] ?? 0);
    error_log("=== INICIO snvAgregar boca $boca_id producto $producto_id ===");
    [$minimo, $maximo, $obs] = snvValidarValores($data);
    snvExigirBoca($conexion, $empresa_id, $usuario_id, $boca_id);
    snvExigirProducto($conexion, $empresa_id, $producto_id);

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT stock_nivel_id FROM gestion__stock_niveles
                                           WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($existe) {
            throw new InvalidArgumentException('Ese producto ya tiene mínimo cargado en esta boca: edítelo desde la grilla.');
        }

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock_niveles
                                           (empresa_id, boca_id, producto_id, stock_minimo, stock_maximo, observaciones,
                                            tabla_estado_registro_id, usuario_id, f_alta, f_modificacion)
                                           VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())");
        mysqli_stmt_bind_param($stmt, 'iiiddsi', $empresa_id, $boca_id, $producto_id, $minimo, $maximo, $obs, $usuario_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);

        stockReevaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $usuario_id, true);
        mysqli_commit($conexion);
        return ['stock_nivel_id' => $id];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

function snvEditar(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id, int $id, array $data): void
{
    error_log("=== INICIO snvEditar ID: $id ===");
    [$minimo, $maximo, $obs] = snvValidarValores($data);

    mysqli_begin_transaction($conexion);
    try {
        $nivel = snvObtener($conexion, $empresa_id, $id);
        if (!$nivel) throw new InvalidArgumentException('Nivel no encontrado.');
        snvExigirBoca($conexion, $empresa_id, $usuario_id, intval($nivel['boca_id']));
        if (!snvFuncionPermitida($conexion, $pagina_id, intval($nivel['tabla_estado_registro_id']), 'editar')) {
            throw new InvalidArgumentException('El nivel no se puede editar en su estado actual.');
        }
        $boca_id = intval($nivel['boca_id']);
        $producto_id = intval($nivel['producto_id']);

        // Mismo orden de locks que un movimiento: saldo -> nivel -> alerta.
        stockNivelBloquearSaldo($conexion, $empresa_id, $boca_id, $producto_id);
        $nivel = snvObtener($conexion, $empresa_id, $id, true);
        $cambio_minimo = abs(floatval($nivel['stock_minimo']) - $minimo) > 0.0000005;

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_niveles
                                           SET stock_minimo = ?, stock_maximo = ?, observaciones = ?, usuario_id = ?,
                                               f_modificacion = NOW()
                                           WHERE stock_nivel_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ddsiii', $minimo, $maximo, $obs, $usuario_id, $id, $empresa_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        stockReevaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $usuario_id, $cambio_minimo);
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

function snvFuncionPermitida(mysqli $conexion, int $pagina_id, int $estado_id, string $accion_js): ?array
{
    foreach (snvFuncionesPagina($conexion, $pagina_id) as $f) {
        if (intval($f['origen']) === $estado_id && $f['accion_js'] === $accion_js) return $f;
    }
    return null;
}

/** Transición genérica (Inhabilitar 1->2 / Habilitar 2->1) + reevaluación de la alerta. */
function snvEjecutarAccion(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id, int $id, string $accion_js): string
{
    mysqli_begin_transaction($conexion);
    try {
        $nivel = snvObtener($conexion, $empresa_id, $id);
        if (!$nivel) throw new InvalidArgumentException('Nivel no encontrado.');
        snvExigirBoca($conexion, $empresa_id, $usuario_id, intval($nivel['boca_id']));
        $boca_id = intval($nivel['boca_id']);
        $producto_id = intval($nivel['producto_id']);

        stockNivelBloquearSaldo($conexion, $empresa_id, $boca_id, $producto_id);
        $nivel = snvObtener($conexion, $empresa_id, $id, true);
        $funcion = snvFuncionPermitida($conexion, $pagina_id, intval($nivel['tabla_estado_registro_id']), $accion_js);
        if (!$funcion) throw new InvalidArgumentException('Acción no permitida para el estado actual.');
        $destino = intval($funcion['destino']);

        if ($destino !== intval($nivel['tabla_estado_registro_id'])) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_niveles
                                               SET tabla_estado_registro_id = ?, usuario_id = ?, f_modificacion = NOW()
                                               WHERE stock_nivel_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'iiii', $destino, $usuario_id, $id, $empresa_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            stockReevaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $usuario_id, true);
        }
        mysqli_commit($conexion);
        return $funcion['nombre_funcion'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Copia los niveles ACTIVOS de una boca a otra. Sin $sobrescribir, los productos que ya tienen
 * nivel en el destino se saltean. Una sola transacción; reevalúa cada par en producto_id ASC.
 * @return array ['insertados' => int, 'actualizados' => int, 'salteados' => int]
 */
function snvCopiarBoca(mysqli $conexion, int $empresa_id, int $usuario_id, int $origen_id, int $destino_id,
                       bool $sobrescribir): array
{
    error_log("=== INICIO snvCopiarBoca $origen_id -> $destino_id ===");
    if ($origen_id === $destino_id) throw new InvalidArgumentException('Elija dos bocas distintas.');
    snvExigirBoca($conexion, $empresa_id, $usuario_id, $origen_id);
    snvExigirBoca($conexion, $empresa_id, $usuario_id, $destino_id);

    $stmt = mysqli_prepare($conexion, "SELECT o.producto_id, o.stock_minimo, o.stock_maximo, o.observaciones,
                                              d.stock_nivel_id AS destino_nivel_id
                                       FROM gestion__stock_niveles o
                                       LEFT JOIN gestion__stock_niveles d ON d.empresa_id = o.empresa_id
                                                                         AND d.boca_id = ? AND d.producto_id = o.producto_id
                                       WHERE o.empresa_id = ? AND o.boca_id = ? AND o.tabla_estado_registro_id = 1
                                       ORDER BY o.producto_id");
    mysqli_stmt_bind_param($stmt, 'iii', $destino_id, $empresa_id, $origen_id);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    if (!$filas) throw new InvalidArgumentException('La boca de origen no tiene niveles activos.');

    $res = ['insertados' => 0, 'actualizados' => 0, 'salteados' => 0];
    mysqli_begin_transaction($conexion);
    try {
        $ins = mysqli_prepare($conexion, "INSERT INTO gestion__stock_niveles
                                          (empresa_id, boca_id, producto_id, stock_minimo, stock_maximo, observaciones,
                                           tabla_estado_registro_id, usuario_id, f_alta, f_modificacion)
                                          VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())");
        $upd = mysqli_prepare($conexion, "UPDATE gestion__stock_niveles
                                          SET stock_minimo = ?, stock_maximo = ?, observaciones = ?,
                                              tabla_estado_registro_id = 1, usuario_id = ?, f_modificacion = NOW()
                                          WHERE stock_nivel_id = ? AND empresa_id = ?");
        foreach ($filas as $f) {
            $producto_id = intval($f['producto_id']);
            $minimo = floatval($f['stock_minimo']);
            $maximo = $f['stock_maximo'] !== null ? floatval($f['stock_maximo']) : null;
            $obs = $f['observaciones'];
            $destino_nivel_id = intval($f['destino_nivel_id'] ?? 0);

            if ($destino_nivel_id && !$sobrescribir) {
                $res['salteados']++;
                continue;
            }
            stockNivelBloquearSaldo($conexion, $empresa_id, $destino_id, $producto_id);
            if ($destino_nivel_id) {
                mysqli_stmt_bind_param($upd, 'ddsiii', $minimo, $maximo, $obs, $usuario_id, $destino_nivel_id, $empresa_id);
                if (!mysqli_stmt_execute($upd)) throw new Exception(mysqli_stmt_error($upd));
                $res['actualizados']++;
            } else {
                mysqli_stmt_bind_param($ins, 'iiiddsi', $empresa_id, $destino_id, $producto_id, $minimo, $maximo, $obs, $usuario_id);
                if (!mysqli_stmt_execute($ins)) throw new Exception(mysqli_stmt_error($ins));
                $res['insertados']++;
            }
            stockReevaluarNivel($conexion, $empresa_id, $destino_id, $producto_id, $usuario_id, true);
        }
        mysqli_stmt_close($ins);
        mysqli_stmt_close($upd);
        mysqli_commit($conexion);
        return $res;
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

function snvExigirProducto(mysqli $conexion, int $empresa_id, int $producto_id): void
{
    $stmt = mysqli_prepare($conexion, "SELECT controla_stock FROM gestion__productos
                                       WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, 'ii', $producto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$p) throw new InvalidArgumentException('Producto inválido.');
    if (intval($p['controla_stock']) !== 1) throw new InvalidArgumentException('El producto no controla stock.');
}

// ---------------------------------------------------------------------
// Carga desde la ficha del producto (productos.php, solapa Información)
// ---------------------------------------------------------------------
/**
 * Una fila por boca a la que el usuario tiene acceso, con el nivel cargado (si hay) y el stock actual.
 * producto_id 0 = producto nuevo (sin niveles ni stock).
 */
function snvNivelesProducto(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id): array
{
    $niveles = [];
    if ($producto_id > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT b.boca_id, n.stock_minimo, n.stock_maximo, n.tabla_estado_registro_id,
                                                  COALESCE(st.cantidad, 0) AS stock_actual,
                                                  (a.stock_alerta_id IS NOT NULL) AS alerta_abierta
                                           FROM gestion__bocas b
                                           LEFT JOIN gestion__stock_niveles n ON n.empresa_id = b.empresa_id AND n.boca_id = b.boca_id
                                                                             AND n.producto_id = ?
                                           LEFT JOIN gestion__stock st ON st.empresa_id = b.empresa_id AND st.boca_id = b.boca_id
                                                                      AND st.producto_id = ?
                                           LEFT JOIN gestion__stock_alertas a ON a.empresa_id = b.empresa_id AND a.boca_id = b.boca_id
                                                                             AND a.producto_id = ? AND a.abierta = 1
                                           WHERE b.empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'iiii', $producto_id, $producto_id, $producto_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            $niveles[intval($r['boca_id'])] = $r;
        }
        mysqli_stmt_close($stmt);
    }

    $out = [];
    foreach (snvBocas($conexion, $empresa_id, $usuario_id) as $b) {
        $n = $niveles[intval($b['boca_id'])] ?? [];
        $activo = isset($n['tabla_estado_registro_id']) && intval($n['tabla_estado_registro_id']) === 1;
        $out[] = [
            'boca_id' => intval($b['boca_id']),
            'boca_nombre' => $b['boca_nombre'],
            'sucursal_nombre' => $b['sucursal_nombre'],
            'stock_actual' => isset($n['stock_actual']) ? floatval($n['stock_actual']) : 0.0,
            // Un nivel inactivo se muestra vacío: "vacío = no controla".
            'stock_minimo' => $activo ? floatval($n['stock_minimo']) : null,
            'stock_maximo' => $activo && $n['stock_maximo'] !== null ? floatval($n['stock_maximo']) : null,
            'alerta_abierta' => !empty($n['alerta_abierta']) ? 1 : 0,
        ];
    }
    return $out;
}

/**
 * Graba los niveles de un producto en varias bocas (una transacción, bocas en orden ASC).
 * Cada ítem: boca_id, stock_minimo ('' = no controlar), stock_maximo ('' = sin tope).
 *   - mínimo vacío y nivel activo  -> lo inhabilita (cierra la alerta abierta)
 *   - mínimo cargado               -> alta, o actualización (y rehabilitación si estaba inactivo)
 * Toda modificación reevalúa la alerta (stockReevaluarNivel), igual que la página 112.
 * @return array ['guardados' => int, 'inhabilitados' => int]
 */
function snvGuardarNivelesProducto(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, array $niveles): array
{
    error_log("=== INICIO snvGuardarNivelesProducto producto $producto_id ===");
    $stmt = mysqli_prepare($conexion, "SELECT controla_stock FROM gestion__productos WHERE producto_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $producto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$p) throw new InvalidArgumentException('Producto inválido.');

    $bocas_ok = array_flip(array_map('intval', array_column(snvBocas($conexion, $empresa_id, $usuario_id), 'boca_id')));
    $items = [];
    foreach ($niveles as $n) {
        $boca_id = intval($n['boca_id'] ?? 0);
        if (!isset($bocas_ok[$boca_id])) throw new InvalidArgumentException('Boca inválida en los niveles de stock.');
        $vacio = trim((string)($n['stock_minimo'] ?? '')) === '';
        if ($vacio && trim((string)($n['stock_maximo'] ?? '')) !== '') {
            throw new InvalidArgumentException('Para cargar un stock máximo hay que cargar también el mínimo.');
        }
        $items[$boca_id] = $vacio ? null : snvValidarValores([
            'stock_minimo' => $n['stock_minimo'],
            'stock_maximo' => $n['stock_maximo'] ?? '',
        ]);
    }
    ksort($items);
    if (array_filter($items) && intval($p['controla_stock']) !== 1) {
        throw new InvalidArgumentException('El producto no controla stock: no admite mínimo ni máximo.');
    }

    $res = ['guardados' => 0, 'inhabilitados' => 0];
    mysqli_begin_transaction($conexion);
    try {
        foreach ($items as $boca_id => $valores) {
            // Mismo orden de locks que un movimiento: saldo -> nivel -> alerta.
            stockNivelBloquearSaldo($conexion, $empresa_id, $boca_id, $producto_id);
            $stmt = mysqli_prepare($conexion, "SELECT stock_nivel_id, stock_minimo, stock_maximo, tabla_estado_registro_id
                                               FROM gestion__stock_niveles
                                               WHERE empresa_id = ? AND boca_id = ? AND producto_id = ?
                                               FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $boca_id, $producto_id);
            mysqli_stmt_execute($stmt);
            $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            $activo = $actual && intval($actual['tabla_estado_registro_id']) === 1;
            $nivel_id = $actual ? intval($actual['stock_nivel_id']) : 0;

            if ($valores === null) {
                if (!$activo) continue;
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_niveles
                                                   SET tabla_estado_registro_id = 2, usuario_id = ?, f_modificacion = NOW()
                                                   WHERE stock_nivel_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $usuario_id, $nivel_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                stockReevaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $usuario_id, true);
                $res['inhabilitados']++;
                continue;
            }

            [$minimo, $maximo] = $valores;
            if (!$actual) {
                $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__stock_niveles
                                                   (empresa_id, boca_id, producto_id, stock_minimo, stock_maximo,
                                                    tabla_estado_registro_id, usuario_id, f_alta, f_modificacion)
                                                   VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())");
                mysqli_stmt_bind_param($stmt, 'iiiddi', $empresa_id, $boca_id, $producto_id, $minimo, $maximo, $usuario_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $cambio_minimo = true;
            } else {
                $max_actual = $actual['stock_maximo'] !== null ? floatval($actual['stock_maximo']) : null;
                $cambio_minimo = !$activo || abs(floatval($actual['stock_minimo']) - $minimo) > 0.0000005;
                $cambio_maximo = ($max_actual === null) !== ($maximo === null)
                    || ($maximo !== null && abs($max_actual - $maximo) > 0.0000005);
                if (!$cambio_minimo && !$cambio_maximo) continue;
                $stmt = mysqli_prepare($conexion, "UPDATE gestion__stock_niveles
                                                   SET stock_minimo = ?, stock_maximo = ?, tabla_estado_registro_id = 1,
                                                       usuario_id = ?, f_modificacion = NOW()
                                                   WHERE stock_nivel_id = ?");
                mysqli_stmt_bind_param($stmt, 'ddii', $minimo, $maximo, $usuario_id, $nivel_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            stockReevaluarNivel($conexion, $empresa_id, $boca_id, $producto_id, $usuario_id, $cambio_minimo);
            $res['guardados']++;
        }
        mysqli_commit($conexion);
        return $res;
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}
