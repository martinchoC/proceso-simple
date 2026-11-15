<?php
/**
 * Importación de precios por presentación desde planilla (CSV / XLSX / XLS), en dos pasos: simular (lee el
 * archivo, no graba nada y deja el resultado en la sesión con un token) y confirmar (graba lo simulado con
 * lpre_guardar_precios, todo o nada). Pantalla: Listas de precios de gestion / gestion_normal, solo con
 * venta por presentación. Diseño: _local/docs/gestion/19_precios_presentaciones.md.
 *
 * Formato (primera fila = encabezados; mayúsculas, acentos y espacios no importan):
 *  - Presentación: "codigo_barras" (de la presentación) o "codigo" (del producto) + "presentacion" (nombre).
 *  - Precios: una columna por lista con el código o el nombre de la lista como encabezado ("General", "Mostrador"),
 *    o el formato largo con las columnas "lista" y "precio".
 *  - Celda vacía = no cambia; 0 = quita el precio (la presentación deja de ofrecerse en esa lista).
 *  - Precio de la presentación entera (la caja), con punto o coma decimal.
 * La plantilla (lpre_imp_plantilla_csv) trae todas las presentaciones de venta con sus precios actuales.
 */

require_once __DIR__ . '/productos_presentaciones.php'; // y listas_precios_presentaciones.php
require_once __DIR__ . '/bancos/planilla_lector.php';

const LPRE_IMP_MAX_FILAS = 10000;
const LPRE_IMP_MUESTRA = 300; // filas que vuelven a la pantalla

const LPRE_IMP_COLUMNAS = [
    'producto' => ['codigo', 'codigo_producto', 'producto_codigo', 'cod', 'cod_producto', 'sku'],
    'presentacion' => ['presentacion', 'presentacion_nombre', 'nombre_presentacion'],
    'barras' => ['codigo_barras', 'codigo_de_barras', 'barras', 'ean', 'dun', 'dun14', 'dun_14'],
    'lista' => ['lista', 'lista_precio', 'lista_de_precios', 'lista_precios'],
    'precio' => ['precio', 'precio_final', 'precio_presentacion'],
];
// Encabezados informativos de la plantilla que no son listas
const LPRE_IMP_IGNORAR = ['producto', 'descripcion', 'nombre', 'producto_nombre', 'contiene', 'factor', 'unidades'];

if (!function_exists('lpre_imp_norm')) {
    /** Encabezado / clave normalizada: minúsculas, sin acentos, solo [a-z0-9_]. */
    function lpre_imp_norm(string $t): string
    {
        $t = mb_strtolower(trim($t), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $t), '_');
    }
}

if (!function_exists('lpre_imp_numero')) {
    /**
     * Precio de una celda: número de la planilla, o texto con punto o coma decimal ("1.234,50", "1234.5", "$ 980").
     * null si la celda está vacía; lanza si no es un número.
     */
    function lpre_imp_numero($v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $t = preg_replace('/[\s$]/u', '', (string) $v);
        if ($t === '') {
            return null;
        }
        $coma = strrpos($t, ',');
        $punto = strrpos($t, '.');
        if ($coma !== false && $punto !== false) {
            // El último separador es el decimal
            $t = $coma > $punto ? str_replace(',', '.', str_replace('.', '', $t)) : str_replace(',', '', $t);
        } elseif ($coma !== false) {
            $t = str_replace(',', '.', $t);
        }
        if (!is_numeric($t)) {
            throw new InvalidArgumentException("\"$v\" no es un precio.");
        }
        return (float) $t;
    }
}

if (!function_exists('lpre_imp_leer')) {
    /**
     * Primera hoja con datos: ['mapa' => [campo => columna], 'listas_col' => [columna => lista_precio_id],
     * 'filas' => [[n_fila, [col => valor]]]].
     */
    function lpre_imp_leer(string $ruta, string $nombre, array $listas): array
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tipo = ['csv' => 'CSV', 'txt' => 'CSV', 'xlsx' => 'XLSX', 'xls' => 'XLS'][$ext] ?? '';
        if ($tipo === '') {
            throw new RuntimeException('El archivo tiene que ser CSV, XLSX o XLS.');
        }
        $hojas = bco_planilla_leer($ruta, $tipo)['hojas'] ?? [];
        // Listas por código y por nombre normalizados
        $por_clave = [];
        foreach ($listas as $l) {
            $por_clave[lpre_imp_norm((string) $l['codigo'])] = $l['lista_precio_id'];
            $por_clave[lpre_imp_norm((string) $l['nombre'])] = $l['lista_precio_id'];
        }
        foreach ($hojas as $filas) {
            $filas = array_filter($filas, static fn($f) => !empty($f));
            if (!$filas) {
                continue;
            }
            $n_enc = array_key_first($filas);
            $mapa = [];
            $listas_col = [];
            $desconocidas = [];
            foreach ($filas[$n_enc] as $col => $titulo) {
                $t = lpre_imp_norm((string) $titulo);
                $campo = null;
                foreach (LPRE_IMP_COLUMNAS as $c => $alias) {
                    if (in_array($t, $alias, true)) {
                        $campo = $c;
                        break;
                    }
                }
                if ($campo !== null) {
                    if (!isset($mapa[$campo])) {
                        $mapa[$campo] = $col;
                    }
                } elseif (isset($por_clave[$t])) {
                    $listas_col[$col] = $por_clave[$t];
                } elseif ($t !== '' && !in_array($t, LPRE_IMP_IGNORAR, true)) {
                    $desconocidas[] = (string) $titulo;
                }
            }
            unset($filas[$n_enc]);
            if (count($filas) > LPRE_IMP_MAX_FILAS) {
                throw new RuntimeException('El archivo tiene más de ' . LPRE_IMP_MAX_FILAS . ' filas: dividilo en partes.');
            }
            $datos = [];
            foreach ($filas as $n => $f) {
                $datos[] = [$n + 1, $f];
            }
            return ['mapa' => $mapa, 'listas_col' => $listas_col, 'desconocidas' => $desconocidas, 'filas' => $datos];
        }
        throw new RuntimeException('El archivo no tiene datos.');
    }
}

if (!function_exists('lpre_imp_listas')) {
    /** Listas activas de la empresa con su código: [{lista_precio_id, codigo, nombre}]. */
    function lpre_imp_listas(mysqli $conexion, int $empresa_id): array
    {
        $st = mysqli_prepare($conexion, "SELECT lista_precio_id, lista_precio_codigo, lista_precio_nombre FROM gestion__listas_precios
                                         WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY lista_precio_id");
        mysqli_stmt_bind_param($st, "i", $empresa_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[] = ['lista_precio_id' => (int) $r['lista_precio_id'], 'codigo' => $r['lista_precio_codigo'], 'nombre' => $r['lista_precio_nombre']];
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('lpre_imp_presentaciones')) {
    /** Presentaciones activas de la empresa: por código de barras y por "código de producto|nombre". */
    function lpre_imp_presentaciones(mysqli $conexion, int $empresa_id): array
    {
        $st = mysqli_prepare($conexion, "SELECT pr.presentacion_id, pr.presentacion_nombre, pr.factor, pr.codigo_barras, pr.aplica_venta,
                                                p.producto_codigo, p.producto_nombre
                                         FROM gestion__productos_presentaciones pr
                                         JOIN gestion__productos p ON p.producto_id = pr.producto_id AND p.empresa_id = pr.empresa_id
                                         WHERE pr.empresa_id = ? AND pr.tabla_estado_registro_id = 1 AND p.tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($st, "i", $empresa_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $por_barras = [];
        $por_nombre = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['presentacion_id'] = (int) $r['presentacion_id'];
            if ((string) $r['codigo_barras'] !== '') {
                $por_barras[(string) $r['codigo_barras']] = $r;
            }
            $por_nombre[mb_strtolower(trim((string) $r['producto_codigo'])) . '|' . mb_strtolower(trim((string) $r['presentacion_nombre']))] = $r;
        }
        mysqli_stmt_close($st);
        return [$por_barras, $por_nombre];
    }
}

if (!function_exists('lpre_imp_simular')) {
    /**
     * Interpreta el archivo sin grabar nada. Devuelve ['resumen' => [...], 'filas' => muestra, 'aplicar' => filas
     * para lpre_guardar_precios]. Estados por precio: NUEVO, CAMBIA, IGUAL, QUITAR, ERROR (y REPETIDA: la misma
     * presentación y lista aparece más abajo, vale la última).
     */
    function lpre_imp_simular(mysqli $conexion, int $empresa_id, string $ruta, string $nombre): array
    {
        $listas = lpre_imp_listas($conexion, $empresa_id);
        if (!$listas) {
            throw new RuntimeException('La empresa no tiene listas de precios activas.');
        }
        $nombres_lista = array_column($listas, 'nombre', 'lista_precio_id');
        $leido = lpre_imp_leer($ruta, $nombre, $listas);
        $mapa = $leido['mapa'];
        if (!isset($mapa['barras']) && !(isset($mapa['producto']) && isset($mapa['presentacion']))) {
            throw new RuntimeException('Faltan las columnas para identificar la presentación: "codigo_barras", o "codigo" (del producto) y "presentacion".');
        }
        $largo = isset($mapa['lista']) && isset($mapa['precio']);
        if (!$largo && !$leido['listas_col']) {
            $extra = $leido['desconocidas'] ? ' Encabezados que no reconozco: ' . implode(', ', array_slice($leido['desconocidas'], 0, 8)) . '.' : '';
            throw new RuntimeException('No hay columnas de precio: poné una columna por lista con su código o nombre de encabezado, o las columnas "lista" y "precio".' . $extra);
        }
        $por_clave_lista = [];
        foreach ($listas as $l) {
            $por_clave_lista[lpre_imp_norm((string) $l['codigo'])] = $l['lista_precio_id'];
            $por_clave_lista[lpre_imp_norm((string) $l['nombre'])] = $l['lista_precio_id'];
        }
        [$por_barras, $por_nombre] = lpre_imp_presentaciones($conexion, $empresa_id);
        $vigentes = [];
        foreach ($listas as $l) {
            $vigentes[$l['lista_precio_id']] = lpre_precios_vigentes($conexion, $empresa_id, $l['lista_precio_id']);
        }

        $salida = [];   // clave "presentacion|lista" => índice en $items (la última gana)
        $items = [];
        $errores_fila = [];
        $vacias = 0;
        foreach ($leido['filas'] as [$n, $f]) {
            $val = static fn($campo) => isset($mapa[$campo]) ? trim((string) ($f[$mapa[$campo]] ?? '')) : '';
            $barras = $val('barras');
            $codigo = $val('producto');
            $pres_nombre = $val('presentacion');
            $ident = $barras !== '' ? $barras : trim("$codigo $pres_nombre");
            $pres = null;
            if ($barras !== '' && isset($por_barras[$barras])) {
                $pres = $por_barras[$barras];
            } elseif ($codigo !== '' && $pres_nombre !== '') {
                $pres = $por_nombre[mb_strtolower($codigo) . '|' . mb_strtolower($pres_nombre)] ?? null;
            }

            // Precios de la fila: [lista_id => valor de la celda]
            $celdas = [];
            if ($largo) {
                $lista_txt = $val('lista');
                $lista_id = $por_clave_lista[lpre_imp_norm($lista_txt)] ?? 0;
                if ($lista_txt !== '' || $val('precio') !== '') {
                    $celdas[$lista_id ?: -1] = $f[$mapa['precio']] ?? null;
                    if (!$lista_id) {
                        $errores_fila[] = ['fila' => $n, 'presentacion' => $ident, 'lista' => $lista_txt, 'estado' => 'ERROR',
                                           'mensaje' => 'La lista "' . $lista_txt . '" no existe o está inactiva.'];
                        continue;
                    }
                }
            }
            foreach ($leido['listas_col'] as $col => $lista_id) {
                $celdas[$lista_id] = $f[$col] ?? null;
            }
            $celdas = array_filter($celdas, static fn($v) => $v !== null && trim((string) $v) !== '');
            if (!$celdas) {
                $vacias++;
                continue;
            }
            if (!$pres) {
                $msg = $ident === '' ? 'Falta el código de barras o el código y la presentación.' : 'No encuentro la presentación (activa) "' . $ident . '".';
                $errores_fila[] = ['fila' => $n, 'presentacion' => $ident, 'lista' => '', 'estado' => 'ERROR', 'mensaje' => $msg];
                continue;
            }
            $etiqueta = $pres['producto_codigo'] . ' · ' . $pres['presentacion_nombre'];
            foreach ($celdas as $lista_id => $celda) {
                $item = ['fila' => $n, 'presentacion' => $etiqueta, 'lista' => $nombres_lista[$lista_id] ?? '', 'actual' => null, 'nuevo' => null,
                         'estado' => '', 'mensaje' => ''];
                $actual = $vigentes[$lista_id][$pres['presentacion_id']]['precio'] ?? null;
                $item['actual'] = $actual;
                try {
                    $precio = lpre_imp_numero($celda);
                    if ($precio < 0) {
                        throw new InvalidArgumentException('El precio no puede ser negativo.');
                    }
                    $precio = round($precio, 4);
                    $item['nuevo'] = $precio;
                    if ($precio == 0.0) {
                        $item['estado'] = $actual === null ? 'IGUAL' : 'QUITAR';
                        $item['mensaje'] = $actual === null ? 'Sin precio, como ya estaba.' : 'Deja de ofrecerse en esta lista.';
                    } elseif ($actual === null) {
                        $item['estado'] = 'NUEVO';
                    } elseif (abs($actual - $precio) < 0.00005) {
                        $item['estado'] = 'IGUAL';
                    } else {
                        $item['estado'] = 'CAMBIA';
                    }
                    if (!(int) $pres['aplica_venta'] && $item['estado'] !== 'IGUAL') {
                        $item['mensaje'] = 'La presentación no se usa en ventas: el precio se guarda pero no se ofrece.';
                    }
                } catch (InvalidArgumentException $e) {
                    $item['estado'] = 'ERROR';
                    $item['mensaje'] = $e->getMessage();
                }
                $item['_aplicar'] = ['lista_precio_id' => (int) $lista_id, 'presentacion_id' => $pres['presentacion_id'], 'precio' => $item['nuevo']];
                if ($item['estado'] === 'ERROR') {
                    $items[] = $item; // un error se muestra siempre como error (no entra en las repetidas)
                    continue;
                }
                $clave = $pres['presentacion_id'] . '|' . $lista_id;
                if (isset($salida[$clave])) {
                    $items[$salida[$clave]]['estado'] = 'REPETIDA';
                    $items[$salida[$clave]]['mensaje'] = 'Vuelve a aparecer en la fila ' . $n . ': vale esa.';
                }
                $items[] = $item;
                $salida[$clave] = count($items) - 1;
            }
        }

        $resumen = ['NUEVO' => 0, 'CAMBIA' => 0, 'QUITAR' => 0, 'IGUAL' => 0, 'REPETIDA' => 0, 'ERROR' => count($errores_fila), 'vacias' => $vacias];
        $aplicar = [];
        foreach ($items as $it) {
            $resumen[$it['estado']]++;
            if (in_array($it['estado'], ['NUEVO', 'CAMBIA', 'QUITAR'], true)) {
                $aplicar[] = $it['_aplicar'];
            }
        }
        // Muestra para la pantalla: errores primero, después cambios; sin los iguales si sobran
        $todos = array_merge($errores_fila, array_map(static function ($it) { unset($it['_aplicar']); return $it; }, $items));
        $peso = ['ERROR' => 0, 'REPETIDA' => 1, 'QUITAR' => 2, 'CAMBIA' => 3, 'NUEVO' => 4, 'IGUAL' => 5];
        usort($todos, static fn($a, $b) => [$peso[$a['estado']], $a['fila']] <=> [$peso[$b['estado']], $b['fila']]);
        return [
            'resumen' => $resumen,
            'filas' => array_slice($todos, 0, LPRE_IMP_MUESTRA),
            'total_filas' => count($todos),
            'listas_reconocidas' => array_values(array_unique(array_map(static fn($id) => $nombres_lista[$id] ?? '', $leido['listas_col']))),
            'aplicar' => $aplicar,
        ];
    }
}

if (!function_exists('lpre_imp_plantilla_csv')) {
    /** CSV (separador ;, UTF-8 con BOM) con las presentaciones de venta activas y sus precios vigentes por lista. */
    function lpre_imp_plantilla_csv(mysqli $conexion, int $empresa_id): string
    {
        $listas = lpre_imp_listas($conexion, $empresa_id);
        $vigentes = [];
        foreach ($listas as $l) {
            $vigentes[$l['lista_precio_id']] = lpre_precios_vigentes($conexion, $empresa_id, $l['lista_precio_id']);
        }
        $st = mysqli_prepare($conexion, "SELECT pr.presentacion_id, pr.presentacion_nombre, pr.factor, pr.codigo_barras,
                                                p.producto_codigo, p.producto_nombre
                                         FROM gestion__productos_presentaciones pr
                                         JOIN gestion__productos p ON p.producto_id = pr.producto_id AND p.empresa_id = pr.empresa_id
                                         WHERE pr.empresa_id = ? AND pr.tabla_estado_registro_id = 1 AND pr.aplica_venta = 1
                                           AND p.tabla_estado_registro_id = 1
                                         ORDER BY p.producto_codigo, pr.orden, pr.factor");
        mysqli_stmt_bind_param($st, "i", $empresa_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_merge(['codigo', 'producto', 'presentacion', 'contiene', 'codigo_barras'], array_column($listas, 'nombre')), ';');
        while ($r = mysqli_fetch_assoc($res)) {
            $fila = [$r['producto_codigo'], $r['producto_nombre'], $r['presentacion_nombre'], lpre_num((float) $r['factor']), (string) $r['codigo_barras']];
            foreach ($listas as $l) {
                $p = $vigentes[$l['lista_precio_id']][(int) $r['presentacion_id']]['precio'] ?? null;
                $fila[] = $p === null ? '' : lpre_num($p);
            }
            fputcsv($fh, $fila, ';');
        }
        mysqli_stmt_close($st);
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }
}

if (!function_exists('lpre_imp_despachar')) {
    /**
     * Acciones de la pantalla (las llama el listas_precios_ajax.php de cada módulo, que ya exigió la función
     * "editar" de la página). Devuelve la respuesta {success, data, message}; la plantilla la manda ella misma.
     *   importar_presentaciones_simular   POST archivo
     *   importar_presentaciones_confirmar POST token
     *   importar_presentaciones_plantilla GET (descarga CSV)
     */
    function lpre_imp_despachar(mysqli $conexion, int $empresa_id, int $usuario_id, string $accion): array
    {
        if (!lpre_empresa_usa($conexion, $empresa_id)) {
            return ['success' => false, 'message' => 'La empresa no vende por presentación (Configuración > Empresas).'];
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        try {
            if ($accion === 'importar_presentaciones_plantilla') {
                $csv = lpre_imp_plantilla_csv($conexion, $empresa_id);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="precios_presentaciones_' . date('Ymd') . '.csv"');
                echo $csv;
                exit;
            }
            if ($accion === 'importar_presentaciones_simular') {
                $f = $_FILES['archivo'] ?? null;
                if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                    return ['success' => false, 'message' => 'Elegí el archivo con los precios.'];
                }
                $r = lpre_imp_simular($conexion, $empresa_id, $f['tmp_name'], (string) $f['name']);
                $token = bin2hex(random_bytes(16));
                $guardadas = $_SESSION['lpre_imp'] ?? [];
                $guardadas = array_slice($guardadas, -2, null, true); // como mucho 3 simulaciones por sesión
                $guardadas[$token] = ['empresa_id' => $empresa_id, 'aplicar' => $r['aplicar'], 'creado' => time()];
                $_SESSION['lpre_imp'] = $guardadas;
                unset($r['aplicar']);
                $r['token'] = $token;
                $r['aplicables'] = count($guardadas[$token]['aplicar']);
                return ['success' => true, 'data' => $r, 'message' => ''];
            }
            if ($accion === 'importar_presentaciones_confirmar') {
                $token = (string) ($_POST['token'] ?? '');
                $sim = $_SESSION['lpre_imp'][$token] ?? null;
                if (!$sim || (int) $sim['empresa_id'] !== $empresa_id || time() - (int) $sim['creado'] > 3600) {
                    return ['success' => false, 'message' => 'La simulación venció o no es de esta empresa: volvé a simular.'];
                }
                unset($_SESSION['lpre_imp'][$token]);
                if (!$sim['aplicar']) {
                    return ['success' => true, 'data' => ['insertados' => 0, 'quitados' => 0, 'sin_cambio' => 0], 'message' => 'No había cambios para grabar.'];
                }
                $r = lpre_guardar_precios($conexion, $empresa_id, $usuario_id, $sim['aplicar']);
                if (!$r['success']) {
                    return ['success' => false, 'message' => 'No se grabó nada: ' . implode(' ', array_slice($r['errores'], 0, 5))];
                }
                return ['success' => true, 'data' => $r,
                        'message' => "Listo: {$r['insertados']} precios nuevos o cambiados, {$r['quitados']} quitados."];
            }
            return ['success' => false, 'message' => 'Acción no válida.'];
        } catch (RuntimeException | InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
