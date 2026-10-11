<?php
// VERSION: 2026-08-30-fix-bindparam-v2 (null por referencia + conteo de tipos corregido)
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerListasPreciosProductos($conexion, $params = [])
{
    // VERSION 2026-09-05: pasa a servidor (serverSide), con el mismo filtro
    // de texto ya probado en productos_model.php. Se había vuelto a carga
    // completa porque paginar server-side daba MÁS lento que un solo request
    // grande — pero esa lentitud venía de otra pantalla (productos.php) por
    // un fan-out al unir dos relaciones 1-a-muchos en la misma consulta. Acá
    // el SELECT principal solo hace joins N-a-1 respecto de lpp (lista,
    // producto, iva): no hay fan-out que armar, así que LIMIT/OFFSET sí
    // recorta trabajo real en vez de recortar sobre un resultado ya inflado.
    //
    // Búsqueda de texto: pega contra p.compatibilidad_busqueda (materializada
    // por trigger sobre gestion__productos_compatibilidad), no contra un
    // JOIN+EXISTS — evita repetir ese join por cada palabra buscada.
    //
    // Imagen y ubicación (1 a muchos de verdad) se siguen resolviendo aparte,
    // en bloque, pero ahora SOLO para los producto_id de la página ya
    // recortada, no para todo el resultado filtrado.
    //
    // ASUNCIÓN A CONFIRMAR (arrastrada de versiones anteriores): nombres de
    // tabla gestion__marcas/modelos/submodelos, iva_alicuota_id en
    // gestion__productos, y gestion__sucursales(sucursal_id, sucursal_nombre).
    $filtro_lista     = $params['filtro_lista'] ?? '';
    $filtro_marca     = $params['filtro_marca'] ?? '';
    $filtro_modelo    = $params['filtro_modelo'] ?? '';
    $filtro_submodelo = $params['filtro_submodelo'] ?? '';
    $busqueda         = trim((string) ($params['busqueda'] ?? ''));
    $start            = max(0, intval($params['start'] ?? 0));
    $length           = intval($params['length'] ?? 10);
    if ($length === -1) {
        // VERSION 2026-10-01: "Todos" del selector de cantidad (lengthMenu).
        // Se devuelve el listado filtrado completo para que los botones
        // Excel/PDF exporten todo; LIMIT con PHP_INT_MAX = sin tope efectivo.
        $start  = 0;
        $length = PHP_INT_MAX;
    } elseif ($length <= 0) {
        $length = 10;
    } elseif ($length > 5000) {
        // Tope de cordura para valores fuera del menú.
        $length = 5000;
    }
    $order_dir = (strtoupper((string) ($params['order_dir'] ?? 'ASC')) === 'DESC') ? 'DESC' : 'ASC';

    // Whitelist columna->SQL. Sólo se ordena por lo que YA está en el SELECT
    // principal (nada que dependa del segundo paso de imagen/ubicación).
    $columnas_permitidas = [
        'lp.lista_precio_nombre', 'p.producto_codigo', 'p.producto_nombre',
        'p.compatibilidad_texto', 'lpp.precio_final', 'lpp.actualizado_en'
    ];
    $order_column = $params['order_column'] ?? 'p.producto_codigo';
    if (!in_array($order_column, $columnas_permitidas, true)) {
        $order_column = 'p.producto_codigo';
    }

    $where = "lpp.tabla_estado_registro_id = 1";
    $where_params = [];
    $types = '';

    if (!empty($filtro_lista)) {
        $where .= " AND lpp.lista_precio_id = ?";
        $where_params[] = intval($filtro_lista);
        $types .= 'i';
    }

    if (!empty($filtro_marca) || !empty($filtro_modelo) || !empty($filtro_submodelo)) {
        $where .= " AND EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pcf
                                 WHERE pcf.producto_id = p.producto_id
                                 AND pcf.tabla_estado_registro_id = 1";
        if (!empty($filtro_marca)) {
            $where .= " AND pcf.marca_id = ?";
            $where_params[] = intval($filtro_marca);
            $types .= 'i';
        }
        if (!empty($filtro_modelo)) {
            $where .= " AND pcf.modelo_id = ?";
            $where_params[] = intval($filtro_modelo);
            $types .= 'i';
        }
        if (!empty($filtro_submodelo)) {
            $where .= " AND pcf.submodelo_id = ?";
            $where_params[] = intval($filtro_submodelo);
            $types .= 'i';
        }
        $where .= ")";
    }

    if ($busqueda !== '') {
        $palabras = preg_split('/\s+/', $busqueda, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($palabras as $palabra) {
            $like = '%' . $palabra . '%';
            $where .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.compatibilidad_busqueda LIKE ? OR lp.lista_precio_nombre LIKE ?)";
            for ($i = 0; $i < 4; $i++) {
                $where_params[] = $like;
                $types .= 's';
            }
        }
    }

    // --- recordsTotal: total activo, sin ningún filtro (para el "X de Y" de DataTables) ---
    $sql_total = "SELECT COUNT(*) AS total FROM gestion__listas_precios_productos lpp WHERE lpp.tabla_estado_registro_id = 1";
    $res_total = mysqli_query($conexion, $sql_total);
    $total_records = $res_total ? (int) mysqli_fetch_assoc($res_total)['total'] : 0;

    // --- recordsFiltered: con lista/marca/modelo/submodelo/búsqueda aplicados ---
    $sql_filtered = "SELECT COUNT(*) AS total
                      FROM gestion__listas_precios_productos lpp
                      INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
                      INNER JOIN gestion__productos p ON lpp.producto_id = p.producto_id
                      WHERE $where";
    $stmt_filtered = mysqli_prepare($conexion, $sql_filtered);
    if (!$stmt_filtered) {
        return ['total' => $total_records, 'filtered' => 0, 'data' => []];
    }
    if (!empty($where_params)) {
        mysqli_stmt_bind_param($stmt_filtered, $types, ...$where_params);
    }
    mysqli_stmt_execute($stmt_filtered);
    $res_filtered = mysqli_stmt_get_result($stmt_filtered);
    $filtered_records = (int) mysqli_fetch_assoc($res_filtered)['total'];
    mysqli_stmt_close($stmt_filtered);

    if ($filtered_records === 0) {
        return ['total' => $total_records, 'filtered' => 0, 'data' => []];
    }

    // MATERIALIZADO: compatibilidad_texto / compatibilidad_busqueda ya NO se
    // calculan acá — se leen directo de gestion__productos, mantenidas por
    // triggers en gestion__productos_compatibilidad (ver
    // materializar_compatibilidad.sql).
    $sql = "SELECT lpp.lista_precio_producto_id, lpp.lista_precio_id, lpp.producto_id, lpp.precio_final,
                   lp.lista_precio_nombre AS lista_nombre,
                   p.producto_codigo,
                   p.producto_nombre,
                   p.compatibilidad_texto AS compatibilidad,
                   p.compatibilidad_busqueda,
                   lpp.precio_final AS precio_unitario,
                   IFNULL(iva.porcentaje, 0) AS iva_porcentaje,
                   ROUND(lpp.precio_final * (1 + IFNULL(iva.porcentaje, 0) / 100), 2) AS precio_con_iva,
                   lpp.actualizado_en AS f_actualizacion
            FROM gestion__listas_precios_productos lpp
            INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
            INNER JOIN gestion__productos p ON lpp.producto_id = p.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva
                   ON p.iva_alicuota_id = iva.iva_alicuota_id
                  AND iva.tabla_estado_registro_id = 1
            WHERE $where
            ORDER BY $order_column $order_dir, lpp.lista_precio_producto_id ASC
            LIMIT ? OFFSET ?";

    $page_params = $where_params;
    $page_types = $types;
    $page_params[] = $length;
    $page_params[] = $start;
    $page_types .= 'ii';

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['total' => $total_records, 'filtered' => $filtered_records, 'data' => []];
    }
    mysqli_stmt_bind_param($stmt, $page_types, ...$page_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filas = [];
    $productoIds = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $filas[] = $fila;
        $productoIds[] = (int) $fila['producto_id'];
    }
    mysqli_stmt_close($stmt);

    // Imagen y ubicación SOLO para los producto_id de ESTA página (antes era
    // para todo el resultado filtrado, porque se cargaba todo de una).
    if (!empty($filas)) {
        $productoIds = array_values(array_unique($productoIds));
        $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
        $tiposIds = str_repeat('i', count($productoIds));

        $imagenes = [];
        $sqlImg = "SELECT pi.producto_id, pi.imagen_id
                   FROM gestion__productos_imagenes pi
                   WHERE pi.producto_id IN ($placeholders)
                     AND pi.tabla_estado_registro_id = 1
                   ORDER BY pi.producto_id, pi.es_principal DESC, pi.orden ASC, pi.producto_imagen_id ASC";
        $stmtImg = mysqli_prepare($conexion, $sqlImg);
        mysqli_stmt_bind_param($stmtImg, $tiposIds, ...$productoIds);
        mysqli_stmt_execute($stmtImg);
        $resImg = mysqli_stmt_get_result($stmtImg);
        while ($row = mysqli_fetch_assoc($resImg)) {
            if (!isset($imagenes[$row['producto_id']])) {
                $imagenes[$row['producto_id']] = $row['imagen_id'];
            }
        }
        mysqli_stmt_close($stmtImg);

        $ubicaciones = [];
        $sqlUbi = "SELECT pu.producto_id,
                          GROUP_CONCAT(DISTINCT CONCAT_WS('|', IFNULL(suc.sucursal_nombre, ''), su.seccion, su.estanteria, su.estante, su.posicion) SEPARATOR ';;') AS ubicaciones_info
                   FROM gestion__productos_ubicaciones pu
                   INNER JOIN gestion__sucursales_ubicaciones su ON pu.sucursal_ubicacion_id = su.sucursal_ubicacion_id
                   LEFT JOIN gestion__sucursales suc ON su.sucursal_id = suc.sucursal_id
                   WHERE pu.producto_id IN ($placeholders)
                     AND pu.tabla_estado_registro_id = 1
                     AND su.tabla_estado_registro_id = 1
                   GROUP BY pu.producto_id";
        $stmtUbi = mysqli_prepare($conexion, $sqlUbi);
        mysqli_stmt_bind_param($stmtUbi, $tiposIds, ...$productoIds);
        mysqli_stmt_execute($stmtUbi);
        $resUbi = mysqli_stmt_get_result($stmtUbi);
        while ($row = mysqli_fetch_assoc($resUbi)) {
            $ubicaciones[$row['producto_id']] = $row['ubicaciones_info'];
        }
        mysqli_stmt_close($stmtUbi);

        foreach ($filas as &$fila) {
            $pid = $fila['producto_id'];
            $fila['imagen_id_principal'] = $imagenes[$pid] ?? null;
            $fila['ubicaciones_info'] = $ubicaciones[$pid] ?? null;
        }
        unset($fila);
    }

    return ['total' => $total_records, 'filtered' => $filtered_records, 'data' => $filas];
}

function obtenerPreciosPorProducto($conexion, $producto_id)
{
    // VERSION 2026-09-05: usada desde la pestaña "Precios" del ABM de
    // productos (productos.php), para editar el precio de UN producto en
    // cada lista sin salir del modal. A diferencia de
    // obtenerListasPreciosProductos(), acá no hace falta paginar ni buscar:
    // el resultado está acotado a la cantidad de listas de precios activas
    // (normalmente unas pocas decenas como mucho), así que es un SELECT
    // simple, sin LIMIT.
    $producto_id = intval($producto_id);
    if ($producto_id <= 0) {
        return [];
    }

    $sql = "SELECT lpp.lista_precio_producto_id, lpp.lista_precio_id, lpp.precio_final AS precio_unitario,
                   lp.lista_precio_nombre AS lista_nombre,
                   IFNULL(iva.porcentaje, 0) AS iva_porcentaje,
                   ROUND(lpp.precio_final * (1 + IFNULL(iva.porcentaje, 0) / 100), 2) AS precio_con_iva,
                   lpp.actualizado_en AS f_actualizacion
            FROM gestion__listas_precios_productos lpp
            INNER JOIN gestion__listas_precios lp ON lpp.lista_precio_id = lp.lista_precio_id
            INNER JOIN gestion__productos p ON lpp.producto_id = p.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva
                   ON p.iva_alicuota_id = iva.iva_alicuota_id
                  AND iva.tabla_estado_registro_id = 1
            WHERE lpp.producto_id = ? AND lpp.tabla_estado_registro_id = 1
            ORDER BY lp.lista_precio_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function agregarListaPrecioProducto($conexion, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;
    $empresa_id = intval($data['empresa_id'] ?? 0);
    if ($empresa_id <= 0) {
        return false;
    }

    // Verificar duplicado activo
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $producto_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    // Insertar registro manual (es_manual = 1)
    $sql = "INSERT INTO gestion__listas_precios_productos 
            (empresa_id, lista_precio_id, producto_id, precio_final, es_manual, precio_manual, f_desde, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, 1, ?, CURDATE(), 1)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiidd", $empresa_id, $lista_precio_id, $producto_id, $precio_final, $precio_final);
    return mysqli_stmt_execute($stmt);
}

function editarListaPrecioProducto($conexion, $id, $data)
{
    if (empty($data['lista_precio_id']) || empty($data['producto_id']) || empty($data['precio_unitario'])) {
        return false;
    }

    $id = intval($id);
    $lista_precio_id = intval($data['lista_precio_id']);
    $producto_id = intval($data['producto_id']);
    $precio_final = floatval($data['precio_unitario']);
    $ajuste_id = !empty($data['ajuste_id']) ? intval($data['ajuste_id']) : null;

    // Verificar duplicado excluyendo el actual
    $sql_check = "SELECT COUNT(*) as existe FROM gestion__listas_precios_productos 
                  WHERE lista_precio_id = ? AND producto_id = ? AND lista_precio_producto_id != ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $producto_id, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $existe);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($existe > 0) {
        return false;
    }

    $sql = "UPDATE gestion__listas_precios_productos 
            SET lista_precio_id = ?, producto_id = ?, precio_final = ?, es_manual = 1, precio_manual = ?
            WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiddi", $lista_precio_id, $producto_id, $precio_final, $precio_final, $id);
    return mysqli_stmt_execute($stmt);
}

function eliminarListaPrecioProducto($conexion, $id)
{
    $id = intval($id);
    // Desactivación lógica
    $sql = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    return mysqli_stmt_execute($stmt);
}

function obtenerListaPrecioProductoPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT *, precio_final as precio_unitario FROM gestion__listas_precios_productos WHERE lista_precio_producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function obtenerListasPrecios($conexion, $empresa_idx = null)
{
    $empresa_idx = intval($empresa_idx ?? ($_SESSION['empresa_id'] ?? 0));
    $sql = "SELECT lista_precio_id, lista_precio_nombre as nombre
            FROM gestion__listas_precios
            WHERE empresa_id = $empresa_idx AND tabla_estado_registro_id = 1
            ORDER BY lista_precio_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion)
{
    $sql = "SELECT producto_id, producto_codigo, producto_nombre 
            FROM gestion__productos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY producto_codigo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
// listas_precios_productos_model.php
// ... (código existente) ...

function importarPreciosDesdeExcel($conexion, $lista_precio_id, $productos_precios, int $empresa_id)
{
    // VERSION 2026-09-05: reescrita para procesar por LOTES desde el front
    // (ver listas_precios_productos.php) y para eliminar el problema N+1:
    // antes, por cada fila del Excel se hacían hasta 5 consultas individuales
    // (producto, precio activo, historial, desactivar, insertar), lo que con
    // 5.000 filas significaba hasta ~25.000 round-trips a la base. Ahora:
    //   1) se precargan en 2 consultas TODOS los productos y precios activos
    //      del lote recibido (el front ya no manda los 5.000 juntos, ver nota
    //      en el frontend), y
    //   2) los INSERT/UPDATE se preparan UNA sola vez fuera del loop y se
    //      reutilizan (bind por referencia + execute), en vez de
    //      prepare/bind/close en cada vuelta.
    //   3) todo el lote corre en una única transacción: evita el costo de un
    //      commit (fsync) por fila, que junto con el N+1 era el otro gran
    //      cuello de botella.
    //
    // NOTA DE COMPORTAMIENTO: si el lote recibido trae el mismo código de
    // producto repetido más de una vez, sólo se aplica el último precio (se
    // deduplica en memoria antes de tocar la base). Antes cada repetición se
    // procesaba por separado. Si esto no es lo esperado, avisame y lo
    // ajustamos.

    $lista_precio_id = intval($lista_precio_id);

    // Validar que la lista de precios exista y esté activa
    $sql_check_lista = "SELECT lista_precio_id FROM gestion__listas_precios WHERE lista_precio_id = ? AND tabla_estado_registro_id = 1";
    $stmt_check = mysqli_prepare($conexion, $sql_check_lista);
    mysqli_stmt_bind_param($stmt_check, "i", $lista_precio_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    if (mysqli_num_rows($result_check) == 0) {
        mysqli_stmt_close($stmt_check);
        return ['success' => false, 'message' => 'La lista de precios seleccionada no existe o está inactiva.'];
    }
    mysqli_stmt_close($stmt_check);

    $detalle = [];
    $procesados = 0;
    $sin_cambios = 0;
    $no_encontrados_count = 0;
    $errores_count = 0;

    if (empty($productos_precios)) {
        return [
            'success' => true, 'message' => 'Lote vacío.',
            'procesados' => 0, 'sin_cambios' => 0, 'no_encontrados_count' => 0, 'errores_count' => 0,
            'detalle' => []
        ];
    }

    // --- Normalizar el lote: descartar códigos vacíos y deduplicar (gana el último precio) ---
    $items = [];
    foreach ($productos_precios as $item) {
        $codigo = trim((string)($item['codigo'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $items[$codigo] = floatval($item['precio'] ?? 0);
    }
    $codigos = array_keys($items);
    if (empty($codigos)) {
        return [
            'success' => true, 'message' => 'Lote sin códigos válidos.',
            'procesados' => 0, 'sin_cambios' => 0, 'no_encontrados_count' => 0, 'errores_count' => 0,
            'detalle' => []
        ];
    }

    // --- 1) Traer TODOS los productos del lote en una sola consulta (antes: 1 SELECT por fila) ---
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
    $tipos = str_repeat('s', count($codigos));
    $productosPorCodigo = [];
    $sql_prod = "SELECT producto_id, producto_codigo, producto_nombre
                 FROM gestion__productos
                 WHERE producto_codigo IN ($placeholders) AND tabla_estado_registro_id = 1";
    $stmt_prod = mysqli_prepare($conexion, $sql_prod);
    mysqli_stmt_bind_param($stmt_prod, $tipos, ...$codigos);
    mysqli_stmt_execute($stmt_prod);
    $res_prod = mysqli_stmt_get_result($stmt_prod);
    while ($row = mysqli_fetch_assoc($res_prod)) {
        $productosPorCodigo[$row['producto_codigo']] = $row;
    }
    mysqli_stmt_close($stmt_prod);

    // --- 2) Traer los precios ACTIVOS actuales para esos producto_id, también en una sola consulta ---
    $productoIds = array_values(array_unique(array_column($productosPorCodigo, 'producto_id')));
    $registroPorProductoId = [];
    if (!empty($productoIds)) {
        $placeholdersIds = implode(',', array_fill(0, count($productoIds), '?'));
        $tiposIds = str_repeat('i', count($productoIds));
        $sql_actual = "SELECT lista_precio_producto_id, producto_id, precio_final, f_desde, f_hasta,
                              precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                              lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                              es_manual, precio_manual, observaciones
                       FROM gestion__listas_precios_productos
                       WHERE lista_precio_id = ? AND producto_id IN ($placeholdersIds) AND tabla_estado_registro_id = 1";
        $stmt_actual = mysqli_prepare($conexion, $sql_actual);
        $paramsActual = array_merge([$lista_precio_id], $productoIds);
        $tiposActual = 'i' . $tiposIds;
        mysqli_stmt_bind_param($stmt_actual, $tiposActual, ...$paramsActual);
        mysqli_stmt_execute($stmt_actual);
        $res_actual = mysqli_stmt_get_result($stmt_actual);
        while ($row = mysqli_fetch_assoc($res_actual)) {
            $registroPorProductoId[$row['producto_id']] = $row;
        }
        mysqli_stmt_close($stmt_actual);
    }

    // --- 3) Preparar UNA SOLA VEZ los statements que se reutilizan en el loop ---
    $fecha_hoy = date('Y-m-d');

    $sql_historial = "INSERT INTO gestion__listas_precios_productos_historial
                      (empresa_id, lista_precio_id, lista_precio_producto_id, producto_id, producto_costo_id,
                       precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                       lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                       es_manual, precio_manual, precio_final, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt_hist = mysqli_prepare($conexion, $sql_historial);
    if (!$stmt_hist) {
        return ['success' => false, 'message' => 'Error al preparar insert de historial: ' . mysqli_error($conexion)];
    }

    $sql_deactivate = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
    $stmt_deact = mysqli_prepare($conexion, $sql_deactivate);

    $sql_insert = "INSERT INTO gestion__listas_precios_productos
                   (empresa_id, lista_precio_id, producto_id, precio_final, es_manual, precio_manual, f_desde, tabla_estado_registro_id)
                   VALUES (?, ?, ?, ?, 1, ?, ?, 1)";
    $stmt_insert = mysqli_prepare($conexion, $sql_insert);

    // Variables bindeadas por referencia: se reasignan en cada vuelta del
    // loop y el valor nuevo queda "adentro" del statement ya preparado.
    $h_lpp_id = 0; $h_producto_id = 0; $h_costo_id = null;
    $h_precio_origen = null; $h_pct_gral = null; $h_imp_gral = null;
    $h_regla_id = null; $h_pct_regla = null; $h_imp_regla = null;
    $h_manual = null; $h_precio_manual = null; $h_precio_final = null;
    $h_desde = null; $h_hasta = null; $h_obs = null;
    mysqli_stmt_bind_param($stmt_hist, "iiiiidddiddiddsss",
        $empresa_id, $lista_precio_id, $h_lpp_id, $h_producto_id, $h_costo_id,
        $h_precio_origen, $h_pct_gral, $h_imp_gral,
        $h_regla_id, $h_pct_regla, $h_imp_regla,
        $h_manual, $h_precio_manual, $h_precio_final,
        $h_desde, $h_hasta, $h_obs
    );

    $d_lpp_id = 0;
    mysqli_stmt_bind_param($stmt_deact, "i", $d_lpp_id);

    $i_producto_id = 0; $i_precio = 0.0;
    mysqli_stmt_bind_param($stmt_insert, "iiidds", $empresa_id, $lista_precio_id, $i_producto_id, $i_precio, $i_precio, $fecha_hoy);

    // --- 4) Recorrer el lote usando SOLO datos ya cargados en memoria (sin más SELECTs) ---
    mysqli_begin_transaction($conexion);
    try {
        foreach ($items as $codigo => $precio_nuevo) {
            $producto = $productosPorCodigo[$codigo] ?? null;

            if (!$producto) {
                $no_encontrados_count++;
                $detalle[] = [
                    'codigo' => $codigo, 'producto_nombre' => '', 'estado' => 'No encontrado',
                    'precio_anterior' => null, 'precio_nuevo' => $precio_nuevo,
                    'detalle' => 'El código no existe en la base de datos. Falta dar de alta el producto.'
                ];
                continue;
            }

            $producto_id = (int)$producto['producto_id'];
            $registro_actual = $registroPorProductoId[$producto_id] ?? null;

            if ($registro_actual && abs((float)$registro_actual['precio_final'] - $precio_nuevo) < 0.005) {
                $sin_cambios++;
                $detalle[] = [
                    'codigo' => $codigo, 'producto_nombre' => $producto['producto_nombre'], 'estado' => 'Sin cambios',
                    'precio_anterior' => (float)$registro_actual['precio_final'], 'precio_nuevo' => $precio_nuevo,
                    'detalle' => 'El precio ya estaba actualizado.'
                ];
                continue;
            }

            // Error real de fila (constraint, tipo de dato, etc.): se registra
            // como error de ESA fila y se sigue con las demás del lote, sin
            // tirar abajo la transacción completa.
            try {
                if ($registro_actual) {
                    $h_lpp_id = (int)$registro_actual['lista_precio_producto_id'];
                    $h_producto_id = $producto_id;
                    $h_costo_id = null;
                    $h_precio_origen = $registro_actual['precio_origen'];
                    $h_pct_gral = $registro_actual['porcentaje_general_aplicado'];
                    $h_imp_gral = $registro_actual['importe_general_aplicado'];
                    $h_regla_id = $registro_actual['lista_precio_regla_id'];
                    $h_pct_regla = $registro_actual['porcentaje_regla_aplicado'];
                    $h_imp_regla = $registro_actual['importe_regla_aplicado'];
                    $h_manual = $registro_actual['es_manual'];
                    $h_precio_manual = $registro_actual['precio_manual'];
                    $h_precio_final = $registro_actual['precio_final'];
                    $h_desde = $registro_actual['f_desde'];
                    $h_hasta = $registro_actual['f_hasta'];
                    $h_obs = $registro_actual['observaciones'];

                    if (!mysqli_stmt_execute($stmt_hist)) {
                        throw new Exception('Error al guardar historial: ' . mysqli_error($conexion));
                    }

                    $d_lpp_id = $h_lpp_id;
                    if (!mysqli_stmt_execute($stmt_deact)) {
                        throw new Exception('Error al desactivar el registro anterior: ' . mysqli_error($conexion));
                    }
                }

                $i_producto_id = $producto_id;
                $i_precio = $precio_nuevo;
                if (!mysqli_stmt_execute($stmt_insert)) {
                    throw new Exception('Error al insertar nuevo precio: ' . mysqli_error($conexion));
                }

                $procesados++;
                $detalle[] = [
                    'codigo' => $codigo, 'producto_nombre' => $producto['producto_nombre'],
                    'estado' => $registro_actual ? 'Actualizado' : 'Cargado (nuevo)',
                    'precio_anterior' => $registro_actual ? (float)$registro_actual['precio_final'] : null,
                    'precio_nuevo' => $precio_nuevo,
                    'detalle' => $registro_actual ? 'Precio anterior pasado a historial.' : 'No tenía precio previo en esta lista.'
                ];

            } catch (Throwable $e) {
                $errores_count++;
                $detalle[] = [
                    'codigo' => $codigo, 'producto_nombre' => $producto['producto_nombre'], 'estado' => 'Error',
                    'precio_anterior' => $registro_actual ? (float)$registro_actual['precio_final'] : null,
                    'precio_nuevo' => $precio_nuevo, 'detalle' => $e->getMessage()
                ];
                continue;
            }
        }

        mysqli_commit($conexion);
    } catch (Throwable $eGlobal) {
        // Esto sólo debería dispararse ante un bug/error no previsto (no los
        // errores normales de fila, que ya se capturan arriba): ante eso,
        // se hace rollback de TODO el lote en curso para no dejar la
        // transacción a mitad de camino.
        mysqli_rollback($conexion);
        mysqli_stmt_close($stmt_hist);
        mysqli_stmt_close($stmt_deact);
        mysqli_stmt_close($stmt_insert);
        return ['success' => false, 'message' => 'Error al procesar el lote: ' . $eGlobal->getMessage()];
    }

    mysqli_stmt_close($stmt_hist);
    mysqli_stmt_close($stmt_deact);
    mysqli_stmt_close($stmt_insert);

    $mensaje = "Lote completado: $procesados actualizados, $sin_cambios sin cambios, $no_encontrados_count no encontrados, $errores_count con error.";

    return [
        'success' => true,
        'message' => $mensaje,
        'procesados' => $procesados,
        'sin_cambios' => $sin_cambios,
        'no_encontrados_count' => $no_encontrados_count,
        'errores_count' => $errores_count,
        'detalle' => $detalle
    ];
}

// ===================== DASHBOARD =====================
// VERSION 2026-09-30: pestaña "Dashboard" de listas_precios_productos.php.
// Todo se calcula sobre precios ACTIVOS (lpp.tabla_estado_registro_id = 1)
// de listas activas, filtrado por empresa. "Universo" de productos para
// cobertura = productos activos de la empresa con aplica_venta = 1.
// La fecha de referencia es lpp.actualizado_en (la misma que muestra la
// columna "Última Actualización" de la grilla).

define('DASH_LIMITE_DETALLE', 10000); // el modal es DataTable client-side; con ~10k filas sigue fluido

function dashboardBucketsAntiguedad()
{
    return [
        '0-30'    => ['label' => 'Hasta 30 días',  'min' => 0,   'max' => 30],
        '31-90'   => ['label' => '31 a 90 días',   'min' => 31,  'max' => 90],
        '91-180'  => ['label' => '91 a 180 días',  'min' => 91,  'max' => 180],
        '181-365' => ['label' => '181 a 365 días', 'min' => 181, 'max' => 365],
        '365+'    => ['label' => 'Más de 1 año',   'min' => 366, 'max' => 9999999],
    ];
}

// FROM/WHERE común para métricas sobre precios (una fila por producto x lista).
function dashboardBasePrecios($empresa_idx, $lista_id)
{
    $sql = "FROM gestion__listas_precios_productos lpp
            INNER JOIN gestion__listas_precios lp
                    ON lp.lista_precio_id = lpp.lista_precio_id
                   AND lp.empresa_id = lpp.empresa_id
                   AND lp.tabla_estado_registro_id = 1
            INNER JOIN gestion__productos p
                    ON p.producto_id = lpp.producto_id
                   AND p.tabla_estado_registro_id = 1
            LEFT JOIN gestion__productos_costos pc
                   ON pc.empresa_id = lpp.empresa_id
                  AND pc.producto_id = lpp.producto_id
                  AND pc.tabla_estado_registro_id = 1
            WHERE lpp.tabla_estado_registro_id = 1
              AND lpp.empresa_id = ?";
    $types = 'i';
    $params = [$empresa_idx];
    if ($lista_id > 0) {
        $sql .= " AND lpp.lista_precio_id = ?";
        $types .= 'i';
        $params[] = $lista_id;
    }
    return [$sql, $types, $params];
}

// FROM/WHERE común para cobertura (una fila por producto del universo, con
// la cantidad de listas en las que tiene precio activo).
function dashboardBaseCobertura($empresa_idx, $lista_id)
{
    $filtro_lista = $lista_id > 0 ? " AND lpp.lista_precio_id = ?" : "";
    $sql = "FROM gestion__productos p
            LEFT JOIN (
                SELECT lpp.producto_id, COUNT(DISTINCT lpp.lista_precio_id) AS listas_con_precio
                FROM gestion__listas_precios_productos lpp
                INNER JOIN gestion__listas_precios lp
                        ON lp.lista_precio_id = lpp.lista_precio_id
                       AND lp.empresa_id = lpp.empresa_id
                       AND lp.tabla_estado_registro_id = 1
                WHERE lpp.tabla_estado_registro_id = 1
                  AND lpp.empresa_id = ? $filtro_lista
                GROUP BY lpp.producto_id
            ) cob ON cob.producto_id = p.producto_id
            LEFT JOIN gestion__productos_costos pc
                   ON pc.empresa_id = p.empresa_id
                  AND pc.producto_id = p.producto_id
                  AND pc.tabla_estado_registro_id = 1
            WHERE p.empresa_id = ?
              AND p.tabla_estado_registro_id = 1
              AND p.aplica_venta = 1";
    $types = 'i';
    $params = [$empresa_idx];
    if ($lista_id > 0) {
        $types .= 'i';
        $params[] = $lista_id;
    }
    $types .= 'i';
    $params[] = $empresa_idx;
    return [$sql, $types, $params];
}

function dashboardEjecutar($conexion, $sql, $types, $params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("=== dashboard ERROR prepare: " . mysqli_error($conexion) . " ===");
        throw new Exception('Error al preparar consulta del dashboard');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function dashboardContarListas($conexion, $empresa_idx, $lista_id)
{
    $sql = "SELECT COUNT(*) AS total FROM gestion__listas_precios
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    $types = 'i';
    $params = [$empresa_idx];
    if ($lista_id > 0) {
        $sql .= " AND lista_precio_id = ?";
        $types .= 'i';
        $params[] = $lista_id;
    }
    $filas = dashboardEjecutar($conexion, $sql, $types, $params);
    return (int) ($filas[0]['total'] ?? 0);
}

function obtenerDashboardPrecios($conexion, $empresa_idx, $lista_id = 0)
{
    $empresa_idx = intval($empresa_idx);
    $lista_id = intval($lista_id);
    error_log("=== INICIO obtenerDashboardPrecios empresa: $empresa_idx lista: $lista_id ===");

    $total_listas = dashboardContarListas($conexion, $empresa_idx, $lista_id);

    // --- 1) Cobertura de productos ---
    // Productos sin fila en "cob" no tienen precio (listas_con_precio NULL):
    // SUM() ignora esos NULL en parcial/completa.
    [$fromCob, $typesCob, $paramsCob] = dashboardBaseCobertura($empresa_idx, $lista_id);
    $sql = "SELECT COUNT(*) AS productos_activos,
                   SUM(cob.listas_con_precio IS NULL) AS sin_precio,
                   SUM(cob.listas_con_precio < ?) AS cobertura_parcial,
                   SUM(cob.listas_con_precio >= ?) AS cobertura_completa
            $fromCob";
    $cob = dashboardEjecutar($conexion, $sql, 'ii' . $typesCob, array_merge([$total_listas, $total_listas], $paramsCob))[0] ?? [];

    // --- 2) Métricas sobre precios ---
    [$from, $types, $params] = dashboardBasePrecios($empresa_idx, $lista_id);
    $sql = "SELECT COUNT(*) AS precios_activos,
                   SUM(DATEDIFF(CURDATE(), DATE(lpp.actualizado_en)) <= 30) AS actualizados_30,
                   SUM(DATEDIFF(CURDATE(), DATE(lpp.actualizado_en)) > 90) AS desactualizados,
                   ROUND(AVG(DATEDIFF(CURDATE(), DATE(lpp.actualizado_en)))) AS antiguedad_promedio,
                   SUM(lpp.precio_final <= 0) AS precio_cero,
                   SUM(pc.costo_actual > 0 AND lpp.precio_final > 0 AND lpp.precio_final < pc.costo_actual) AS bajo_costo,
                   ROUND(AVG(CASE WHEN pc.costo_actual > 0 AND lpp.precio_final > 0
                                  THEN (lpp.precio_final - pc.costo_actual) / pc.costo_actual * 100 END), 2) AS margen_promedio,
                   MAX(lpp.actualizado_en) AS ultima_actualizacion,
                   CURDATE() AS hoy
            $from";
    $met = dashboardEjecutar($conexion, $sql, $types, $params)[0] ?? [];

    // --- 3) Distribución por día -> se pliega en PHP a meses y antigüedad ---
    $sql = "SELECT DATE(lpp.actualizado_en) AS dia, COUNT(*) AS cantidad
            $from
            GROUP BY DATE(lpp.actualizado_en)";
    $dias = dashboardEjecutar($conexion, $sql, $types, $params);

    $hoy = new DateTime($met['hoy'] ?? 'today');
    $inicio_ventana = (clone $hoy)->modify('first day of this month')->modify('-11 months');

    $meses = [];
    $cursor = clone $inicio_ventana;
    for ($i = 0; $i < 12; $i++) {
        $meses[$cursor->format('Y-m')] = 0;
        $cursor->modify('+1 month');
    }
    $anteriores = 0;

    $buckets = dashboardBucketsAntiguedad();
    $antiguedad = array_fill_keys(array_keys($buckets), 0);

    foreach ($dias as $d) {
        if (empty($d['dia'])) {
            continue;
        }
        $cantidad = (int) $d['cantidad'];
        $fecha = new DateTime($d['dia']);
        $mes = $fecha->format('Y-m');
        if (isset($meses[$mes])) {
            $meses[$mes] += $cantidad;
        } elseif ($fecha < $inicio_ventana) {
            $anteriores += $cantidad;
        }

        $dias_antiguedad = max(0, (int) $hoy->diff($fecha)->days);
        foreach ($buckets as $clave => $b) {
            if ($dias_antiguedad >= $b['min'] && $dias_antiguedad <= $b['max']) {
                $antiguedad[$clave] += $cantidad;
                break;
            }
        }
    }

    $serie_meses = [];
    if ($anteriores > 0) {
        $serie_meses[] = ['mes' => 'anteriores', 'cantidad' => $anteriores];
    }
    foreach ($meses as $mes => $cantidad) {
        $serie_meses[] = ['mes' => $mes, 'cantidad' => $cantidad];
    }

    $serie_antiguedad = [];
    foreach ($buckets as $clave => $b) {
        $serie_antiguedad[] = ['bucket' => $clave, 'label' => $b['label'], 'cantidad' => $antiguedad[$clave]];
    }

    // --- 4) Cobertura por lista (siempre todas, no depende del filtro) ---
    $sql = "SELECT lp.lista_precio_id, lp.lista_precio_nombre AS lista_nombre,
                   COUNT(DISTINCT p.producto_id) AS con_precio,
                   MAX(lpp.actualizado_en) AS ultima_actualizacion
            FROM gestion__listas_precios lp
            LEFT JOIN gestion__listas_precios_productos lpp
                   ON lpp.lista_precio_id = lp.lista_precio_id
                  AND lpp.empresa_id = lp.empresa_id
                  AND lpp.tabla_estado_registro_id = 1
            LEFT JOIN gestion__productos p
                   ON p.producto_id = lpp.producto_id
                  AND p.empresa_id = ?
                  AND p.tabla_estado_registro_id = 1
                  AND p.aplica_venta = 1
            WHERE lp.empresa_id = ? AND lp.tabla_estado_registro_id = 1
            GROUP BY lp.lista_precio_id, lp.lista_precio_nombre
            ORDER BY lp.lista_precio_nombre";
    $por_lista = dashboardEjecutar($conexion, $sql, 'ii', [$empresa_idx, $empresa_idx]);

    return [
        'kpis' => [
            'productos_activos'    => (int) ($cob['productos_activos'] ?? 0),
            'sin_precio'           => (int) ($cob['sin_precio'] ?? 0),
            'cobertura_parcial'    => (int) ($cob['cobertura_parcial'] ?? 0),
            'cobertura_completa'   => (int) ($cob['cobertura_completa'] ?? 0),
            'total_listas'         => $total_listas,
            'precios_activos'      => (int) ($met['precios_activos'] ?? 0),
            'actualizados_30'      => (int) ($met['actualizados_30'] ?? 0),
            'desactualizados'      => (int) ($met['desactualizados'] ?? 0),
            'antiguedad_promedio'  => isset($met['antiguedad_promedio']) ? (int) $met['antiguedad_promedio'] : null,
            'precio_cero'          => (int) ($met['precio_cero'] ?? 0),
            'bajo_costo'           => (int) ($met['bajo_costo'] ?? 0),
            'margen_promedio'      => isset($met['margen_promedio']) ? (float) $met['margen_promedio'] : null,
            'ultima_actualizacion' => $met['ultima_actualizacion'] ?? null,
        ],
        'meses' => $serie_meses,
        'antiguedad' => $serie_antiguedad,
        'por_lista' => $por_lista,
    ];
}

// Listado para el modal de detalle del dashboard.
// $tipo: mes | antiguedad | actualizados_30 | desactualizados | bajo_costo |
//        precio_cero (filas de precio) — sin_precio | parcial (filas de producto)
function obtenerDashboardDetalle($conexion, $empresa_idx, $tipo, $valor, $lista_id = 0)
{
    $empresa_idx = intval($empresa_idx);
    $lista_id = intval($lista_id);
    $limite = DASH_LIMITE_DETALLE + 1; // +1 para detectar si se truncó
    error_log("=== INICIO obtenerDashboardDetalle tipo: $tipo valor: $valor lista: $lista_id ===");

    if ($tipo === 'sin_precio' || $tipo === 'parcial') {
        [$from, $types, $params] = dashboardBaseCobertura($empresa_idx, $lista_id);
        if ($tipo === 'sin_precio') {
            $from .= " AND cob.listas_con_precio IS NULL";
        } else {
            $from .= " AND cob.listas_con_precio < ?";
            $types .= 'i';
            $params[] = dashboardContarListas($conexion, $empresa_idx, $lista_id);
        }
        $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                       pc.costo_actual,
                       IFNULL(cob.listas_con_precio, 0) AS listas_con_precio
                $from
                ORDER BY p.producto_codigo
                LIMIT ?";
        $types .= 'i';
        $params[] = $limite;
        $filas = dashboardEjecutar($conexion, $sql, $types, $params);
        $tipo_fila = 'producto';
    } else {
        [$from, $types, $params] = dashboardBasePrecios($empresa_idx, $lista_id);
        $dias_expr = "DATEDIFF(CURDATE(), DATE(lpp.actualizado_en))";

        switch ($tipo) {
            case 'mes':
                if ($valor === 'anteriores') {
                    $from .= " AND lpp.actualizado_en < DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)";
                } elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $valor)) {
                    $desde = $valor . '-01';
                    $hasta = (new DateTime($desde))->modify('+1 month')->format('Y-m-d');
                    $from .= " AND lpp.actualizado_en >= ? AND lpp.actualizado_en < ?";
                    $types .= 'ss';
                    $params[] = $desde;
                    $params[] = $hasta;
                } else {
                    throw new Exception('Mes inválido');
                }
                break;
            case 'antiguedad':
                $buckets = dashboardBucketsAntiguedad();
                if (!isset($buckets[$valor])) {
                    throw new Exception('Rango de antigüedad inválido');
                }
                $from .= " AND $dias_expr BETWEEN ? AND ?";
                $types .= 'ii';
                $params[] = $buckets[$valor]['min'];
                $params[] = $buckets[$valor]['max'];
                break;
            case 'actualizados_30':
                $from .= " AND $dias_expr <= 30";
                break;
            case 'desactualizados':
                $from .= " AND $dias_expr > 90";
                break;
            case 'bajo_costo':
                $from .= " AND pc.costo_actual > 0 AND lpp.precio_final > 0 AND lpp.precio_final < pc.costo_actual";
                break;
            case 'precio_cero':
                $from .= " AND lpp.precio_final <= 0";
                break;
            default:
                throw new Exception('Tipo de detalle inválido');
        }

        $sql = "SELECT lpp.lista_precio_producto_id, lpp.lista_precio_id, lpp.producto_id,
                       lp.lista_precio_nombre AS lista_nombre,
                       p.producto_codigo, p.producto_nombre,
                       lpp.precio_final AS precio_unitario,
                       pc.costo_actual,
                       CASE WHEN pc.costo_actual > 0
                            THEN ROUND((lpp.precio_final - pc.costo_actual) / pc.costo_actual * 100, 2) END AS margen_pct,
                       lpp.actualizado_en AS f_actualizacion,
                       $dias_expr AS dias
                $from
                ORDER BY lpp.actualizado_en ASC, p.producto_codigo
                LIMIT ?";
        $types .= 'i';
        $params[] = $limite;
        $filas = dashboardEjecutar($conexion, $sql, $types, $params);
        $tipo_fila = 'precio';
    }

    $truncado = count($filas) > DASH_LIMITE_DETALLE;
    if ($truncado) {
        array_pop($filas);
    }

    return ['tipo_fila' => $tipo_fila, 'filas' => $filas, 'truncado' => $truncado, 'limite' => DASH_LIMITE_DETALLE];
}
?>