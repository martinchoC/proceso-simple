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
    if ($length <= 0) {
        $length = 10;
    }
    if ($length > 5000) {
        // Tope de cordura: si el front manda "-1" (Todos) o algo fuera de
        // rango, no volvemos a pedirle a MySQL 5.000 filas hidratadas de una.
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
    $empresa_id = 2; // o tomarlo de sesión

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

function obtenerListasPrecios($conexion)
{
    $sql = "SELECT lista_precio_id, lista_precio_nombre as nombre
            FROM gestion__listas_precios 
            WHERE tabla_estado_registro_id = 1
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

function importarPreciosDesdeExcel($conexion, $lista_precio_id, $productos_precios, $empresa_id = 2)
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
?>