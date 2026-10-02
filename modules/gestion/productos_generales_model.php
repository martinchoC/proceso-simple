<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// ABM "Productos - Generales": alta/edición de productos y servicios sobre
// gestion__productos, con todos sus campos editables y SIN compatibilidad
// (marca/modelo/submodelo). Es independiente de productos.php/productos_model.php.
//
// Campos que NO se editan acá:
//   - compatibilidad_texto / compatibilidad_busqueda: materializados por la
//     compatibilidad, que esta página no maneja.
//   - proveedores_busqueda: materializado por recalcularProveedoresBusqueda()
//     (productos_model.php) al tocar los proveedores del producto.
//   - cont_cuenta_id: se sacó de los ABM de productos; la columna sigue siendo
//     NOT NULL, así que en el alta se asigna 131 (mismo criterio que productos_model.php).
//   - tabla_estado_registro_id: lo maneja el motor de estados (ejecutar_accion).
//
// Igual que en productos.php, tabla_estado_registro_id guarda el estado_registro_id
// genérico (1 = Activo, 2 = Inactivo), que es contra lo que comparan
// tabla_estado_registro_origen_id / _destino_id de conf__paginas_funciones.

define('PRODUCTOS_GENERALES_CUENTA_LEGACY', 131);

// ✅ Resolver el tabla_id de gestion__productos en conf__tablas (sin hardcodear)
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
        error_log("productos_generales: gestion__productos no está registrada en conf__tablas");
    }
    return $tabla_id;
}

// ✅ Listado paginado server-side (DataTables) de productos y servicios de la empresa
function obtenerProductosGeneralesPaginados($conexion, $empresa_idx, $pagina_id, $params = [])
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    $tabla_id = obtenerTablaIdProductos($conexion);

    $start = max(0, intval($params['start'] ?? 0));
    $length = intval($params['length'] ?? 25);
    $search = trim($params['search'] ?? '');
    $order_column = intval($params['order_column'] ?? 1);
    $order_dir = strtoupper($params['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    // Índice de columna del DataTable → columna SQL (whitelist, nunca el valor crudo)
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

    // Filtro por código (campo "Código..." del listado, igual que productos.php)
    $filtro_codigo = trim($params['filtro_codigo'] ?? '');
    if ($filtro_codigo !== '') {
        $where[] = "p.producto_codigo LIKE ?";
        $types .= "s";
        $values[] = '%' . $filtro_codigo . '%';
    }

    // Búsqueda por etiquetas, mismo criterio que productos.php: cada término tiene
    // que aparecer (AND) en código, nombre, código de barras, descripción o código
    // de proveedor. Las etiquetas compuestas llegan entre comillas ("disco rigido")
    // y se buscan como UNA frase; lo que va sin comillas se parte por espacios.
    if ($search !== '' && preg_match_all('/"([^"]*)"|(\S+)/', $search, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $termino = trim(isset($match[2]) ? $match[2] : $match[1]);
            if ($termino === '') {
                continue;
            }
            $like = '%' . $termino . '%';
            $where[] = "(p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras LIKE ?
                         OR p.producto_descripcion LIKE ? OR p.proveedores_busqueda LIKE ?)";
            $types .= "sssss";
            array_push($values, $like, $like, $like, $like, $like);
        }
    }
    $where_filtrado = implode(" AND ", $where);

    $total = contarProductosGenerales($conexion, $where_base, $types_base, $values_base);
    $filtrados = $where_filtrado !== $where_base
        ? contarProductosGenerales($conexion, $where_filtrado, $types, $values)
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

    // length = -1 es "Todos" en DataTables
    if ($length > 0) {
        $sql .= " LIMIT ?, ?";
        $types_sql .= "ii";
        array_push($values_sql, $start, $length);
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando listado de productos_generales: " . mysqli_error($conexion));
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

function contarProductosGenerales($conexion, $where, $types, $values)
{
    $sql = "SELECT COUNT(*) AS total FROM gestion__productos p WHERE $where";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando conteo de productos_generales: " . mysqli_error($conexion));
        return 0;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['total'] ?? 0);
}

// ✅ Estados posibles de la tabla (dropdown de filtro rápido).
// El value es estado_registro_id (1/2), que es lo que guarda la columna.
function obtenerEstadosProductosGenerales($conexion)
{
    $tabla_id = obtenerTablaIdProductos($conexion);

    $sql = "SELECT estado_registro_id, tabla_estado_registro AS estado_registro, es_inicial
            FROM conf__tablas_estados_registros
            WHERE tabla_id = ?
            ORDER BY orden ASC, tabla_estado_registro_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
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

// ✅ Obtener funciones configuradas para la página desde conf__paginas_funciones
function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ?
            AND pf.tabla_estado_registro_id = 1 -- Solo funciones activas
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

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

// ✅ Obtener botones disponibles según el estado actual
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    static $cache = [];
    if (!isset($cache[$pagina_id])) {
        $cache[$pagina_id] = obtenerFuncionesPagina($conexion, $pagina_id);
    }

    $botones = [];
    foreach ($cache[$pagina_id] as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] != $estado_actual_id) {
            continue;
        }

        // Confirmable solo si la función cambia el estado
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

// ✅ Obtener botón "Agregar" específico para la página (función con origen 0)
function obtenerBotonAgregar($conexion, $pagina_id)
{
    foreach (obtenerFuncionesPagina($conexion, $pagina_id) as $funcion) {
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
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Estado destino de la función "Agregar" (origen 0) de la página; default 1 (Activo)
function obtenerEstadoInicialProductosGenerales($conexion, $pagina_id)
{
    foreach (obtenerFuncionesPagina($conexion, $pagina_id) as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0 && $funcion['accion_js'] === 'agregar') {
            return intval($funcion['tabla_estado_registro_destino_id']);
        }
    }
    return 1;
}

// ========== COMBOS ==========

// ✅ Tipos de producto activos, con los flags y cuentas default que usa el alta.
// Sin filtro de empresa, igual que obtenerTiposProducto de productos_model.php
// (los tipos existentes son compartidos por el combo de productos.php).
function obtenerTiposProductoGenerales($conexion)
{
    $sql = "SELECT producto_tipo_id, producto_tipo, producto_tipo_codigo, maneja_stock,
                   cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id
            FROM gestion__productos_tipos
            WHERE tabla_estado_registro_id = 1
            ORDER BY producto_tipo";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        error_log("Error obteniendo tipos de producto: " . mysqli_error($conexion));
        return [];
    }

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    return $tipos;
}

// ✅ Categorías activas (propias de la empresa o globales)
function obtenerCategoriasProductoGenerales($conexion, $empresa_idx)
{
    return obtenerCombo($conexion,
        "SELECT producto_categoria_id, producto_categoria_nombre
         FROM gestion__productos_categorias
         WHERE (empresa_id = 0 OR empresa_id = ?) AND tabla_estado_registro_id = 1
         ORDER BY producto_categoria_nombre",
        $empresa_idx);
}

// ✅ Unidades de medida activas (propias de la empresa o globales)
function obtenerUnidadesMedidaGenerales($conexion, $empresa_idx)
{
    return obtenerCombo($conexion,
        "SELECT unidad_medida_id, unidad_nombre, unidad_abreviatura
         FROM gestion__unidades_medida
         WHERE (empresa_id = 0 OR empresa_id = ?) AND tabla_estado_registro_id = 1
         ORDER BY unidad_nombre",
        $empresa_idx);
}

// ✅ Cuentas contables imputables y activas de la empresa
function obtenerCuentasContablesGenerales($conexion, $empresa_idx)
{
    return obtenerCombo($conexion,
        "SELECT cont_cuenta_id, codigo, nombre
         FROM gestion__cont_cuentas
         WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1
         ORDER BY codigo",
        $empresa_idx);
}

// ✅ Alícuotas de IVA activas (tabla global, sin empresa)
function obtenerIvaAlicuotasGenerales($conexion)
{
    $sql = "SELECT iva_alicuota_id, iva_alicuota, porcentaje
            FROM gestion__impuestos__iva_alicuotas
            WHERE tabla_estado_registro_id = 1
            ORDER BY porcentaje DESC, iva_alicuota";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    $alicuotas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $alicuotas[] = $fila;
    }
    return $alicuotas;
}

function obtenerCombo($conexion, $sql, $empresa_idx)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando combo de productos_generales: " . mysqli_error($conexion));
        return [];
    }
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

// ========== VALIDACIÓN ==========

// Devuelve la fila del tipo de producto (activo) o null
function obtenerTipoProductoGeneral($conexion, $producto_tipo_id)
{
    $sql = "SELECT producto_tipo_id, maneja_stock,
                   cont_cuenta_compra_default_id, cont_cuenta_venta_default_id, cont_cuenta_cmv_default_id
            FROM gestion__productos_tipos
            WHERE producto_tipo_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $producto_tipo_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: null;
}

// Verifica que un id exista en una tabla de referencia. $sql lleva un "?" para el id
// y, si se pasa $empresa_idx, un segundo "?" para la empresa.
function existeReferencia($conexion, $sql, $id, $empresa_idx = null)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return false;
    }
    if ($empresa_idx === null) {
        mysqli_stmt_bind_param($stmt, "i", $id);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['total'] ?? 0) > 0;
}

function decimalONull($valor)
{
    $valor = trim((string) ($valor ?? ''));
    if ($valor === '') {
        return null;
    }
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? floatval($valor) : false;
}

function idONull($valor)
{
    return !empty($valor) && intval($valor) > 0 ? intval($valor) : null;
}

// Normaliza y valida los datos del formulario. Devuelve [datos, error].
// En el alta ($es_alta), las cuentas que vengan vacías se completan con los
// defaults del tipo de producto (la de CMV solo si el producto controla stock).
function normalizarDatosProductoGeneral($conexion, $data, $empresa_idx, $es_alta)
{
    $es_servicio = !empty($data['es_servicio']) ? 1 : 0;

    $datos = [
        'producto_codigo' => trim($data['producto_codigo'] ?? ''),
        'producto_nombre' => trim($data['producto_nombre'] ?? ''),
        'codigo_barras' => trim($data['codigo_barras'] ?? ''),
        'producto_descripcion' => trim($data['producto_descripcion'] ?? ''),
        'producto_categoria_id' => idONull($data['producto_categoria_id'] ?? null),
        'producto_tipo_id' => idONull($data['producto_tipo_id'] ?? null),
        'unidad_medida_id' => idONull($data['unidad_medida_id'] ?? null),
        'iva_alicuota_id' => idONull($data['iva_alicuota_id'] ?? null),
        'lado' => trim($data['lado'] ?? ''),
        'material' => trim($data['material'] ?? ''),
        'color' => trim($data['color'] ?? ''),
        'peso' => decimalONull($data['peso'] ?? null),
        'dimensiones' => trim($data['dimensiones'] ?? ''),
        'garantia' => trim($data['garantia'] ?? ''),
        'impuesto_interno_porcentaje' => decimalONull($data['impuesto_interno_porcentaje'] ?? null),
        'impuesto_interno_fijo' => decimalONull($data['impuesto_interno_fijo'] ?? null),
        'es_servicio' => $es_servicio,
        // Un servicio nunca controla stock
        'controla_stock' => $es_servicio ? 0 : (!empty($data['controla_stock']) ? 1 : 0),
        'aplica_compra' => !empty($data['aplica_compra']) ? 1 : 0,
        'aplica_venta' => !empty($data['aplica_venta']) ? 1 : 0,
        'cont_cuenta_compra_id' => idONull($data['cont_cuenta_compra_id'] ?? null),
        'cont_cuenta_venta_id' => idONull($data['cont_cuenta_venta_id'] ?? null),
        'cont_cuenta_cmv_id' => idONull($data['cont_cuenta_cmv_id'] ?? null),
    ];

    // Obligatorios y largos (según el DDL de gestion__productos)
    if ($datos['producto_codigo'] === '') return [null, 'El código es obligatorio'];
    if ($datos['producto_nombre'] === '') return [null, 'El nombre es obligatorio'];
    if ($datos['producto_tipo_id'] === null) return [null, 'El tipo de producto es obligatorio'];
    if ($datos['producto_categoria_id'] === null) return [null, 'La categoría es obligatoria'];
    if ($datos['iva_alicuota_id'] === null) return [null, 'La alícuota de IVA es obligatoria'];

    $largos = [
        'producto_codigo' => [50, 'El código'],
        'producto_nombre' => [150, 'El nombre'],
        'codigo_barras' => [150, 'El código de barras'],
        'lado' => [10, 'El lado'],
        'material' => [50, 'El material'],
        'color' => [50, 'El color'],
        'dimensiones' => [50, 'Las dimensiones'],
        'garantia' => [50, 'La garantía'],
    ];
    foreach ($largos as $campo => [$max, $etiqueta]) {
        if (mb_strlen($datos[$campo]) > $max) {
            return [null, "$etiqueta no puede exceder los $max caracteres"];
        }
    }

    // Decimales: peso DECIMAL(6,2), impuesto interno % DECIMAL(5,2), fijo DECIMAL(12,2)
    $decimales = [
        'peso' => [9999.99, 'El peso'],
        'impuesto_interno_porcentaje' => [100, 'El impuesto interno (%)'],
        'impuesto_interno_fijo' => [9999999999.99, 'El impuesto interno fijo'],
    ];
    foreach ($decimales as $campo => [$max, $etiqueta]) {
        if ($datos[$campo] === false) return [null, "$etiqueta debe ser un número"];
        if ($datos[$campo] !== null && ($datos[$campo] < 0 || $datos[$campo] > $max)) {
            return [null, "$etiqueta debe estar entre 0 y $max"];
        }
    }

    if (!$datos['aplica_compra'] && !$datos['aplica_venta']) {
        return [null, 'El producto tiene que poder comprarse, venderse o ambas cosas'];
    }

    // Referencias
    $tipo = obtenerTipoProductoGeneral($conexion, $datos['producto_tipo_id']);
    if (!$tipo) return [null, 'El tipo de producto no es válido'];

    if (!existeReferencia($conexion, "SELECT COUNT(*) AS total FROM gestion__productos_categorias
            WHERE producto_categoria_id = ? AND (empresa_id = 0 OR empresa_id = ?)", $datos['producto_categoria_id'], $empresa_idx)) {
        return [null, 'La categoría no es válida para esta empresa'];
    }
    if ($datos['unidad_medida_id'] !== null && !existeReferencia($conexion, "SELECT COUNT(*) AS total FROM gestion__unidades_medida
            WHERE unidad_medida_id = ? AND (empresa_id = 0 OR empresa_id = ?)", $datos['unidad_medida_id'], $empresa_idx)) {
        return [null, 'La unidad de medida no es válida para esta empresa'];
    }
    if (!existeReferencia($conexion, "SELECT COUNT(*) AS total FROM gestion__impuestos__iva_alicuotas
            WHERE iva_alicuota_id = ?", $datos['iva_alicuota_id'])) {
        return [null, 'La alícuota de IVA no es válida'];
    }

    // Cuentas: en el alta, las vacías heredan el default del tipo
    if ($es_alta) {
        $defaults = [
            'cont_cuenta_compra_id' => 'cont_cuenta_compra_default_id',
            'cont_cuenta_venta_id' => 'cont_cuenta_venta_default_id',
            'cont_cuenta_cmv_id' => 'cont_cuenta_cmv_default_id',
        ];
        foreach ($defaults as $campo => $campo_tipo) {
            if ($datos[$campo] === null) {
                $datos[$campo] = idONull($tipo[$campo_tipo]);
            }
        }
    }
    // La cuenta de CMV solo aplica a productos que controlan stock
    if (!$datos['controla_stock']) {
        $datos['cont_cuenta_cmv_id'] = null;
    }

    $cuentas = ['cont_cuenta_compra_id' => 'compra', 'cont_cuenta_venta_id' => 'venta', 'cont_cuenta_cmv_id' => 'CMV'];
    foreach ($cuentas as $campo => $etiqueta) {
        if ($datos[$campo] !== null && !existeReferencia($conexion, "SELECT COUNT(*) AS total FROM gestion__cont_cuentas
                WHERE cont_cuenta_id = ? AND empresa_id = ? AND es_imputable = 1", $datos[$campo], $empresa_idx)) {
            return [null, "La cuenta de $etiqueta no es válida para esta empresa"];
        }
    }

    // Columnas de texto opcionales: vacío → NULL
    foreach (['codigo_barras', 'producto_descripcion', 'lado', 'material', 'color', 'dimensiones', 'garantia'] as $campo) {
        if ($datos[$campo] === '') {
            $datos[$campo] = null;
        }
    }

    return [$datos, null];
}

// El índice UNIQUE de producto_codigo es GLOBAL (no por empresa), así que el
// código se valida contra todas las empresas.
function validarDuplicadoProductoGeneral($conexion, $producto_codigo, $excluir_id = 0)
{
    $sql = "SELECT COUNT(*) AS total FROM gestion__productos
            WHERE producto_codigo = ? AND producto_id <> ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return 'Error en la consulta';
    }
    mysqli_stmt_bind_param($stmt, "si", $producto_codigo, $excluir_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return intval($row['total']) > 0 ? 'Ya existe un producto con el código "' . $producto_codigo . '"' : null;
}

// ========== ALTA / EDICIÓN ==========

// ✅ Agregar nuevo producto o servicio
function agregarProductoGeneral($conexion, $data, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO agregarProductoGeneral empresa: $empresa_idx ===");

    [$d, $error] = normalizarDatosProductoGeneral($conexion, $data, $empresa_idx, true);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoProductoGeneral($conexion, $d['producto_codigo']);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $estado_inicial = obtenerEstadoInicialProductosGenerales($conexion, $pagina_id);
    $cont_cuenta_id = PRODUCTOS_GENERALES_CUENTA_LEGACY;

    mysqli_begin_transaction($conexion);
    try {
        $sql = "INSERT INTO gestion__productos
                    (empresa_id, producto_codigo, producto_nombre, codigo_barras, producto_descripcion,
                     producto_categoria_id, cont_cuenta_id, cont_cuenta_compra_id, cont_cuenta_venta_id,
                     cont_cuenta_cmv_id, producto_tipo_id, lado, material, color, peso, dimensiones,
                     garantia, unidad_medida_id, iva_alicuota_id, impuesto_interno_porcentaje,
                     impuesto_interno_fijo, controla_stock, es_servicio, aplica_compra, aplica_venta,
                     tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando el alta: ' . mysqli_error($conexion));
        }

        // 26 tipos ↔ 26 parámetros
        mysqli_stmt_bind_param(
            $stmt,
            "issssiiiiiisssdssiiddiiiii",
            $empresa_idx,
            $d['producto_codigo'],
            $d['producto_nombre'],
            $d['codigo_barras'],
            $d['producto_descripcion'],
            $d['producto_categoria_id'],
            $cont_cuenta_id,
            $d['cont_cuenta_compra_id'],
            $d['cont_cuenta_venta_id'],
            $d['cont_cuenta_cmv_id'],
            $d['producto_tipo_id'],
            $d['lado'],
            $d['material'],
            $d['color'],
            $d['peso'],
            $d['dimensiones'],
            $d['garantia'],
            $d['unidad_medida_id'],
            $d['iva_alicuota_id'],
            $d['impuesto_interno_porcentaje'],
            $d['impuesto_interno_fijo'],
            $d['controla_stock'],
            $d['es_servicio'],
            $d['aplica_compra'],
            $d['aplica_venta'],
            $estado_inicial
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al insertar: ' . mysqli_stmt_error($stmt));
        }
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN agregarProductoGeneral ID: $nuevo_id ===");
        return ['success' => true, 'data' => ['producto_id' => $nuevo_id], 'message' => 'Producto creado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("agregarProductoGeneral: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar el producto'];
    }
}

// ✅ Editar producto o servicio existente (solo de la empresa activa)
function editarProductoGeneral($conexion, $id, $data, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    error_log("=== INICIO editarProductoGeneral ID: $id ===");

    if (!obtenerProductoGeneralPorId($conexion, $id, $empresa_idx)) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }

    [$d, $error] = normalizarDatosProductoGeneral($conexion, $data, $empresa_idx, false);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $error = validarDuplicadoProductoGeneral($conexion, $d['producto_codigo'], $id);
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql = "UPDATE gestion__productos
                SET producto_codigo = ?, producto_nombre = ?, codigo_barras = ?, producto_descripcion = ?,
                    producto_categoria_id = ?, cont_cuenta_compra_id = ?, cont_cuenta_venta_id = ?,
                    cont_cuenta_cmv_id = ?, producto_tipo_id = ?, lado = ?, material = ?, color = ?,
                    peso = ?, dimensiones = ?, garantia = ?, unidad_medida_id = ?, iva_alicuota_id = ?,
                    impuesto_interno_porcentaje = ?, impuesto_interno_fijo = ?, controla_stock = ?,
                    es_servicio = ?, aplica_compra = ?, aplica_venta = ?
                WHERE producto_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando la edición: ' . mysqli_error($conexion));
        }

        // 23 SET + 2 WHERE = 25 tipos ↔ 25 parámetros
        mysqli_stmt_bind_param(
            $stmt,
            "ssssiiiiisssdssiiddiiiiii",
            $d['producto_codigo'],
            $d['producto_nombre'],
            $d['codigo_barras'],
            $d['producto_descripcion'],
            $d['producto_categoria_id'],
            $d['cont_cuenta_compra_id'],
            $d['cont_cuenta_venta_id'],
            $d['cont_cuenta_cmv_id'],
            $d['producto_tipo_id'],
            $d['lado'],
            $d['material'],
            $d['color'],
            $d['peso'],
            $d['dimensiones'],
            $d['garantia'],
            $d['unidad_medida_id'],
            $d['iva_alicuota_id'],
            $d['impuesto_interno_porcentaje'],
            $d['impuesto_interno_fijo'],
            $d['controla_stock'],
            $d['es_servicio'],
            $d['aplica_compra'],
            $d['aplica_venta'],
            $id,
            $empresa_idx
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al actualizar: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN editarProductoGeneral ID: $id ===");
        return ['success' => true, 'data' => ['producto_id' => $id], 'message' => 'Producto actualizado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("editarProductoGeneral: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el producto'];
    }
}

// ✅ Obtener un producto por ID (filtrado por empresa), con los campos que edita esta página
function obtenerProductoGeneralPorId($conexion, $id, $empresa_idx)
{
    $sql = "SELECT producto_id, empresa_id, producto_codigo, producto_nombre, codigo_barras,
                   producto_descripcion, producto_categoria_id, cont_cuenta_compra_id,
                   cont_cuenta_venta_id, cont_cuenta_cmv_id, producto_tipo_id, lado, material, color,
                   peso, dimensiones, garantia, unidad_medida_id, iva_alicuota_id,
                   impuesto_interno_porcentaje, impuesto_interno_fijo, controla_stock, es_servicio,
                   aplica_compra, aplica_venta, tabla_estado_registro_id
            FROM gestion__productos
            WHERE producto_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    $id = intval($id);
    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $producto_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_id = intval($producto_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    error_log("=== INICIO ejecutarTransicionEstado productos_generales ID: $producto_id acción: $accion_js ===");

    $producto = obtenerProductoGeneralPorId($conexion, $producto_id, $empresa_idx);
    if (!$producto) {
        return ['success' => false, 'message' => 'Registro no encontrado'];
    }

    $estado_actual_id = intval($producto['tabla_estado_registro_id']);

    $sql_funcion = "SELECT pf.tabla_estado_registro_destino_id
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ?
                    AND pf.tabla_estado_registro_origen_id = ?
                    AND pf.accion_js = ?
                    AND pf.tabla_estado_registro_id = 1
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'message' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$funcion) {
        return ['success' => false, 'message' => 'Acción no permitida para este estado'];
    }

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);
    if ($estado_destino_id === $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql_update = "UPDATE gestion__productos
                       SET tabla_estado_registro_id = ?
                       WHERE producto_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            throw new Exception('Error preparando el cambio de estado: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $producto_id, $empresa_idx);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al cambiar estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("ejecutarTransicionEstado productos_generales: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al actualizar el estado'];
    }
}
