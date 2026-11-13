<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../core/productos_presentaciones.php';
$conexion = $conn;

// ABM "Productos y Servicios" para Módulo Gestión Normal:
// alta/edición de productos y servicios sobre gestion__productos SIN compatibilidad de vehículos.


function obtenerTablaIdProductos($conexion)
{
    static $tabla_id = null;
    if ($tabla_id !== null) {
        return $tabla_id;
    }

    $sql = "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = 'gestion__productos' LIMIT 1";
    $result = mysqli_query($conexion, $sql);
    $fila = $result ? mysqli_fetch_assoc($result) : null;
    $tabla_id = $fila ? intval($fila['tabla_id']) : 0;

    if ($tabla_id === 0) {
        error_log("productos_model (gestion_normal): gestion__productos no está registrada en conf__tablas");
    }
    return $tabla_id;
}

function obtenerProductosPaginados($conexion, $empresa_idx, $pagina_id, $params = [])
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    $tabla_id = obtenerTablaIdProductos($conexion);

    $start = max(0, intval($params['start'] ?? 0));
    $length = intval($params['length'] ?? 25);
    $search = trim($params['search'] ?? '');
    $order_column = intval($params['order_column'] ?? 1);
    $order_dir = strtoupper($params['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    $column_mapping = [
        0 => 'p.producto_codigo',
        1 => 'p.producto_nombre',
        2 => 'pt.producto_tipo',
        3 => 'pc.producto_categoria_nombre',
        4 => 'um.unidad_abreviatura',
        5 => 'ia.porcentaje',
        6 => 'p.controla_stock',
        7 => 'p.aplica_compra',
        8 => 'p.aplica_venta',
        9 => 'p.tabla_estado_registro_id'
    ];
    $order_by = $column_mapping[$order_column] ?? 'p.producto_nombre';

    $where = ["p.empresa_id = ?"];
    $types = "i";
    $values = [$empresa_idx];

    if (($params['filtro_estado'] ?? '') !== '') {
        $where[] = "p.tabla_estado_registro_id = ?";
        $types .= "i";
        $values[] = intval($params['filtro_estado']);
    }
    if (($params['filtro_tipo'] ?? '') !== '') {
        $where[] = "p.producto_tipo_id = ?";
        $types .= "i";
        $values[] = intval($params['filtro_tipo']);
    }
    if (($params['filtro_servicio'] ?? '') !== '') {
        $where[] = "p.es_servicio = ?";
        $types .= "i";
        $values[] = intval($params['filtro_servicio']) === 1 ? 1 : 0;
    }

    $where_base = implode(" AND ", $where);
    $types_base = $types;
    $values_base = $values;

    $filtro_codigo = trim($params['filtro_codigo'] ?? '');
    if ($filtro_codigo !== '') {
        $where[] = "p.producto_codigo LIKE ?";
        $types .= "s";
        $values[] = '%' . $filtro_codigo . '%';
    }

    if ($search !== '' && preg_match_all('/"([^"]*)"|(\S+)/', $search, $matches, PREG_SET_ORDER)) {
        // También por el código de barras de sus presentaciones (core/productos_presentaciones.php)
        $sql_pres = prpr_sql_busqueda_barras($conexion, 'p');
        foreach ($matches as $match) {
            $termino = trim(isset($match[2]) ? $match[2] : $match[1]);
            if ($termino === '') {
                continue;
            }
            $like = '%' . $termino . '%';
            $where[] = "(p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras LIKE ? OR p.producto_descripcion LIKE ? OR p.proveedores_busqueda LIKE ?"
                . ($sql_pres !== '' ? " OR $sql_pres" : '') . ")";
            $types .= "sssss";
            array_push($values, $like, $like, $like, $like, $like);
            if ($sql_pres !== '') {
                $types .= "s";
                $values[] = $like;
            }
        }
    }
    $where_filtrado = implode(" AND ", $where);

    $total = contarProductos($conexion, $where_base, $types_base, $values_base);
    $filtrados = $where_filtrado !== $where_base
        ? contarProductos($conexion, $where_filtrado, $types, $values)
        : $total;

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.codigo_barras,
                   p.producto_tipo_id, p.producto_categoria_id, p.unidad_medida_id, p.iva_alicuota_id,
                   p.controla_stock, p.es_servicio, p.aplica_compra, p.aplica_venta,
                   p.tabla_estado_registro_id,
                   pt.producto_tipo, pc.producto_categoria_nombre,
                   um.unidad_abreviatura, ia.iva_alicuota,
                   ter.tabla_estado_registro AS estado_registro
            FROM gestion__productos p
            LEFT JOIN gestion__productos_tipos pt ON pt.producto_tipo_id = p.producto_tipo_id
            LEFT JOIN gestion__productos_categorias pc ON pc.producto_categoria_id = p.producto_categoria_id
            LEFT JOIN gestion__unidades_medida um ON um.unidad_medida_id = p.unidad_medida_id
            LEFT JOIN gestion__impuestos__iva_alicuotas ia ON ia.iva_alicuota_id = p.iva_alicuota_id
            LEFT JOIN conf__tablas_estados_registros ter
                ON ter.tabla_id = ? AND ter.estado_registro_id = p.tabla_estado_registro_id
            WHERE $where_filtrado
            ORDER BY $order_by $order_dir, p.producto_id ASC";

    $types_sql = "i" . $types;
    $values_sql = array_merge([$tabla_id], $values);

    if ($length > 0) {
        $sql .= " LIMIT ?, ?";
        $types_sql .= "ii";
        array_push($values_sql, $start, $length);
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando listado de productos: " . mysqli_error($conexion));
        return ['total' => 0, 'filtered' => 0, 'productos' => []];
    }

    mysqli_stmt_bind_param($stmt, $types_sql, ...$values_sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        if (empty($fila['estado_registro'])) {
            $fila['estado_registro'] = $fila['tabla_estado_registro_id'] == 1 ? 'Activo' : 'Inactivo';
        }
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return ['total' => $total, 'filtered' => $filtrados, 'productos' => $productos];
}

function contarProductos($conexion, $where, $types, $values)
{
    $sql = "SELECT COUNT(*) AS total FROM gestion__productos p WHERE $where";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando conteo de productos: " . mysqli_error($conexion));
        return 0;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['total'] ?? 0);
}

function obtenerEstadosProductos($conexion)
{
    $tabla_id = obtenerTablaIdProductos($conexion);

    $sql = "SELECT estado_registro_id, tabla_estado_registro AS estado_registro, es_inicial
            FROM conf__tablas_estados_registros
            WHERE tabla_id = ?
            ORDER BY orden ASC, tabla_estado_registro_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $tabla_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $estados = [];
    while ($f = mysqli_fetch_assoc($result)) {
        $estados[] = $f;
    }
    mysqli_stmt_close($stmt);
    return $estados;
}

function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ?
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $funciones[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $funciones;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    static $cache = [];
    if (!isset($cache[$pagina_id])) {
        $cache[$pagina_id] = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    }

    $botones = [];
    foreach ($cache[$pagina_id] as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] != $estado_actual_id) {
            continue;
        }

        $es_confirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;

        $botones[] = [
            'nombre_funcion' => $funcion['nombre_funcion'],
            'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
            'icono_clase' => $funcion['icono_clase'],
            'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
            'bg_clase' => $funcion['bg_clase'] ?? '',
            'text_clase' => $funcion['text_clase'] ?? '',
            'descripcion' => $funcion['descripcion'],
            'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
            'es_confirmable' => $es_confirmable
        ];
    }

    return $botones;
}

function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    if (permisos_sin_funcion_creacion($funciones)) {
        return null;
    }
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }

    return [
        'nombre_funcion' => 'Agregar Producto',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => '',
        'text_clase' => '',
        'descripcion' => 'Agregar nuevo producto'
    ];
}

function obtenerTiposProducto($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT producto_tipo_id, producto_tipo FROM gestion__productos_tipos
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY producto_tipo";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function obtenerCategoriasProducto($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre
            FROM gestion__productos_categorias
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY producto_categoria_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function obtenerUnidadesMedida($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT unidad_medida_id, unidad_nombre, unidad_abreviatura
            FROM gestion__unidades_medida
            WHERE (empresa_id = 0 OR empresa_id = ?) AND tabla_estado_registro_id = 1
            ORDER BY unidad_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function obtenerIvaAlicuotas($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT iva_alicuota_id, iva_alicuota, porcentaje FROM gestion__impuestos__iva_alicuotas
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY porcentaje ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function obtenerCuentasContables($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    $sql = "SELECT cont_cuenta_id, codigo, nombre
            FROM gestion__cont_cuentas
            WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
            ORDER BY codigo ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function obtenerProductoPorId($conexion, $producto_id, $empresa_idx)
{
    $producto_id = intval($producto_id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT * FROM gestion__productos WHERE producto_id = ? AND empresa_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function sanitizarFloat($valor)
{
    if ($valor === null || $valor === '') return null;
    $v = floatval(str_replace(',', '.', (string)$valor));
    return $v >= 0 ? $v : null;
}

function sanitizarIntNull($valor)
{
    if ($valor === null || $valor === '') return null;
    $v = intval($valor);
    return $v > 0 ? $v : null;
}

function sanitizarStringNull($valor)
{
    if ($valor === null) return null;
    $s = trim((string)$valor);
    return $s !== '' ? $s : null;
}

function agregarProducto($conexion, $datos, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);

    $codigo = trim($datos['producto_codigo'] ?? '');
    $nombre = trim($datos['producto_nombre'] ?? '');
    $tipo_id = intval($datos['producto_tipo_id'] ?? 0);
    $cat_id = intval($datos['producto_categoria_id'] ?? 0);
    $iva_id = intval($datos['iva_alicuota_id'] ?? 0);

    if ($codigo === '' || $nombre === '' || $tipo_id <= 0 || $cat_id <= 0 || $iva_id <= 0) {
        return ['success' => false, 'message' => 'Faltan campos obligatorios (Código, Nombre, Tipo, Categoría, Alícuota IVA).'];
    }

    $chk_sql = "SELECT producto_id FROM gestion__productos WHERE empresa_id = ? AND producto_codigo = ? LIMIT 1";
    $chk_stmt = mysqli_prepare($conexion, $chk_sql);
    mysqli_stmt_bind_param($chk_stmt, "is", $empresa_idx, $codigo);
    mysqli_stmt_execute($chk_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt))) {
        mysqli_stmt_close($chk_stmt);
        return ['success' => false, 'message' => "Ya existe un producto con el código '$codigo' en la empresa."];
    }
    mysqli_stmt_close($chk_stmt);

    $barras = sanitizarStringNull($datos['codigo_barras'] ?? null);
    $desc = sanitizarStringNull($datos['producto_descripcion'] ?? null);
    $um_id = sanitizarIntNull($datos['unidad_medida_id'] ?? null);

    $imp_porc = sanitizarFloat($datos['impuesto_interno_porcentaje'] ?? null);
    $imp_fijo = sanitizarFloat($datos['impuesto_interno_fijo'] ?? null);

    $es_servicio = !empty($datos['es_servicio']) ? 1 : 0;
    $controla_stock = $es_servicio ? 0 : (!empty($datos['controla_stock']) ? 1 : 0);
    $aplica_compra = isset($datos['aplica_compra']) ? (!empty($datos['aplica_compra']) ? 1 : 0) : 1;
    $aplica_venta = isset($datos['aplica_venta']) ? (!empty($datos['aplica_venta']) ? 1 : 0) : 1;

    $lado = sanitizarStringNull($datos['lado'] ?? null);
    $material = sanitizarStringNull($datos['material'] ?? null);
    $color = sanitizarStringNull($datos['color'] ?? null);
    $peso = sanitizarFloat($datos['peso'] ?? null);
    $dimensiones = sanitizarStringNull($datos['dimensiones'] ?? null);
    $garantia = sanitizarStringNull($datos['garantia'] ?? null);

    $cc_compra = sanitizarIntNull($datos['cont_cuenta_compra_id'] ?? null);
    $cc_venta = sanitizarIntNull($datos['cont_cuenta_venta_id'] ?? null);
    $cc_cmv = sanitizarIntNull($datos['cont_cuenta_cmv_id'] ?? null);

    $legacy_cuenta = emp_cuenta_concepto_id($conexion, $empresa_idx, 'MERCADERIAS'); // columna legacy NOT NULL: Mercaderías de la empresa (0 si no la tiene)
    $estado_inicial = 1; // Activo

    $sql = "INSERT INTO gestion__productos (
                empresa_id, producto_codigo, producto_nombre, codigo_barras, producto_descripcion,
                producto_categoria_id, cont_cuenta_id, cont_cuenta_compra_id, cont_cuenta_venta_id, cont_cuenta_cmv_id,
                producto_tipo_id, lado, material, color, peso, dimensiones, garantia,
                unidad_medida_id, iva_alicuota_id, impuesto_interno_porcentaje, impuesto_interno_fijo,
                controla_stock, es_servicio, tabla_estado_registro_id, aplica_compra, aplica_venta
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparando el alta: ' . mysqli_error($conexion)];
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issssiiiiiisssdssiiddiiiii",
        $empresa_idx, $codigo, $nombre, $barras, $desc,
        $cat_id, $legacy_cuenta, $cc_compra, $cc_venta, $cc_cmv,
        $tipo_id, $lado, $material, $color, $peso, $dimensiones, $garantia,
        $um_id, $iva_id, $imp_porc, $imp_fijo,
        $controla_stock, $es_servicio, $estado_inicial, $aplica_compra, $aplica_venta
    );

    if (mysqli_stmt_execute($stmt)) {
        $id_nuevo = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Producto agregado correctamente.', 'producto_id' => $id_nuevo];
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Error al guardar el producto: ' . $err];
    }
}

function editarProducto($conexion, $datos, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $producto_id = intval($datos['producto_id'] ?? 0);

    if ($producto_id <= 0) {
        return ['success' => false, 'message' => 'ID de producto no válido.'];
    }

    $prod_actual = obtenerProductoPorId($conexion, $producto_id, $empresa_idx);
    if (!$prod_actual) {
        return ['success' => false, 'message' => 'El producto a editar no existe o pertenece a otra empresa.'];
    }

    $codigo = trim($datos['producto_codigo'] ?? '');
    $nombre = trim($datos['producto_nombre'] ?? '');
    $tipo_id = intval($datos['producto_tipo_id'] ?? 0);
    $cat_id = intval($datos['producto_categoria_id'] ?? 0);
    $iva_id = intval($datos['iva_alicuota_id'] ?? 0);

    if ($codigo === '' || $nombre === '' || $tipo_id <= 0 || $cat_id <= 0 || $iva_id <= 0) {
        return ['success' => false, 'message' => 'Faltan campos obligatorios.'];
    }

    $chk_sql = "SELECT producto_id FROM gestion__productos WHERE empresa_id = ? AND producto_codigo = ? AND producto_id != ? LIMIT 1";
    $chk_stmt = mysqli_prepare($conexion, $chk_sql);
    mysqli_stmt_bind_param($chk_stmt, "isi", $empresa_idx, $codigo, $producto_id);
    mysqli_stmt_execute($chk_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt))) {
        mysqli_stmt_close($chk_stmt);
        return ['success' => false, 'message' => "Ya existe otro producto con el código '$codigo'."];
    }
    mysqli_stmt_close($chk_stmt);

    $barras = sanitizarStringNull($datos['codigo_barras'] ?? null);
    $desc = sanitizarStringNull($datos['producto_descripcion'] ?? null);
    $um_id = sanitizarIntNull($datos['unidad_medida_id'] ?? null);

    $imp_porc = sanitizarFloat($datos['impuesto_interno_porcentaje'] ?? null);
    $imp_fijo = sanitizarFloat($datos['impuesto_interno_fijo'] ?? null);

    $es_servicio = !empty($datos['es_servicio']) ? 1 : 0;
    $controla_stock = $es_servicio ? 0 : (!empty($datos['controla_stock']) ? 1 : 0);
    $aplica_compra = isset($datos['aplica_compra']) ? (!empty($datos['aplica_compra']) ? 1 : 0) : 1;
    $aplica_venta = isset($datos['aplica_venta']) ? (!empty($datos['aplica_venta']) ? 1 : 0) : 1;

    $lado = sanitizarStringNull($datos['lado'] ?? null);
    $material = sanitizarStringNull($datos['material'] ?? null);
    $color = sanitizarStringNull($datos['color'] ?? null);
    $peso = sanitizarFloat($datos['peso'] ?? null);
    $dimensiones = sanitizarStringNull($datos['dimensiones'] ?? null);
    $garantia = sanitizarStringNull($datos['garantia'] ?? null);

    $cc_compra = sanitizarIntNull($datos['cont_cuenta_compra_id'] ?? null);
    $cc_venta = sanitizarIntNull($datos['cont_cuenta_venta_id'] ?? null);
    $cc_cmv = sanitizarIntNull($datos['cont_cuenta_cmv_id'] ?? null);

    $sql = "UPDATE gestion__productos SET
                producto_codigo = ?,
                producto_nombre = ?,
                codigo_barras = ?,
                producto_descripcion = ?,
                producto_categoria_id = ?,
                cont_cuenta_compra_id = ?,
                cont_cuenta_venta_id = ?,
                cont_cuenta_cmv_id = ?,
                producto_tipo_id = ?,
                lado = ?,
                material = ?,
                color = ?,
                peso = ?,
                dimensiones = ?,
                garantia = ?,
                unidad_medida_id = ?,
                iva_alicuota_id = ?,
                impuesto_interno_porcentaje = ?,
                impuesto_interno_fijo = ?,
                controla_stock = ?,
                es_servicio = ?,
                aplica_compra = ?,
                aplica_venta = ?
            WHERE producto_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparando la edición: ' . mysqli_error($conexion)];
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssssiiiiisssdssiiddiiiiii",
        $codigo, $nombre, $barras, $desc,
        $cat_id, $cc_compra, $cc_venta, $cc_cmv,
        $tipo_id, $lado, $material, $color, $peso, $dimensiones, $garantia,
        $um_id, $iva_id, $imp_porc, $imp_fijo,
        $controla_stock, $es_servicio, $aplica_compra, $aplica_venta,
        $producto_id, $empresa_idx
    );

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Producto actualizado correctamente.'];
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Error al actualizar el producto: ' . $err];
    }
}

function ejecutarAccionProducto($conexion, $producto_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_id = intval($producto_id);
    $empresa_idx = intval($empresa_idx);

    $prod = obtenerProductoPorId($conexion, $producto_id, $empresa_idx);
    if (!$prod) {
        return ['success' => false, 'message' => 'Producto no encontrado.'];
    }

    $estado_actual = intval($prod['tabla_estado_registro_id']);

    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    $funcion_encontrada = null;
    foreach ($funciones as $fn) {
        $accion_fn = $fn['accion_js'] ?? strtolower($fn['nombre_funcion']);
        if ($accion_fn === $accion_js && intval($fn['tabla_estado_registro_origen_id']) === $estado_actual) {
            $funcion_encontrada = $fn;
            break;
        }
    }

    if (!$funcion_encontrada) {
        return ['success' => false, 'message' => "La acción '$accion_js' no está permitida desde el estado actual del producto."];
    }

    $nuevo_estado = intval($funcion_encontrada['tabla_estado_registro_destino_id']);

    $sql = "UPDATE gestion__productos SET tabla_estado_registro_id = ? WHERE producto_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparando el cambio de estado.'];
    }

    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $producto_id, $empresa_idx);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Estado del producto actualizado correctamente.'];
    } else {
        return ['success' => false, 'message' => 'Error al cambiar el estado del producto.'];
    }
}