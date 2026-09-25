<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/* =========================================================================
 * Motor de estados genérico — COPIA FUNCIONAL de las mismas funciones que
 * ya están en proveedor_precios_model.php / ventas_pedidos_model.php. Se
 * duplican a propósito acá siguiendo la misma decisión ya documentada en
 * esos archivos: no introducir un include cruzado entre páginas.
 * ========================================================================= */

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

function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0) {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? ''
            ];
        }
    }

    error_log("No se encontró configuración de botón agregar para pagina_id=$pagina_id, usando valores por defecto");
    return [
        'nombre_funcion' => 'Agregar',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

/* =========================================================================
 * Combo de proveedores — confirmado por Pablo: gestion__entidades.es_proveedor = 1
 * (misma consulta que proveedor_precios_model.php::obtenerProveedores; la
 * versión de productos_model.php::obtenerEntidadesProveedores NO filtra por
 * es_proveedor y quedó desactualizada, no replicar ese criterio acá).
 * ========================================================================= */
function obtenerProveedoresCombo($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia
            FROM gestion__entidades e
            WHERE e.empresa_id = ?
              AND e.es_proveedor = 1
              AND e.tabla_estado_registro_id = 1
            ORDER BY e.entidad_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerProveedoresCombo: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $proveedores = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $proveedores[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $proveedores;
}

/* =========================================================================
 * Marca / modelo / submodelo para los filtros de compatibilidad — mismas
 * 3 consultas que ya usa proveedor_precios_model.php, duplicadas acá
 * siguiendo la misma convención de no cruzar includes entre archivos de
 * página.
 * ========================================================================= */
function obtenerMarcas($conexion)
{
    $sql = "SELECT marca_id, marca_nombre FROM gestion__marcas WHERE tabla_estado_registro_id = 1 ORDER BY marca_nombre";
    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];
    $marcas = [];
    while ($fila = mysqli_fetch_assoc($result)) $marcas[] = $fila;
    return $marcas;
}

function obtenerModelosPorMarca($conexion, $marca_id)
{
    $marca_id = intval($marca_id);
    $sql = "SELECT modelo_id, modelo_nombre FROM gestion__modelos WHERE marca_id = ? AND tabla_estado_registro_id = 1 ORDER BY modelo_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $marca_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) $modelos[] = $fila;
    mysqli_stmt_close($stmt);
    return $modelos;
}

function obtenerSubmodelosPorModelo($conexion, $modelo_id)
{
    $modelo_id = intval($modelo_id);
    $sql = "SELECT submodelo_id, submodelo_nombre FROM gestion__submodelos WHERE modelo_id = ? AND tabla_estado_registro_id = 1 ORDER BY submodelo_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $modelo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($result)) $submodelos[] = $fila;
    mysqli_stmt_close($stmt);
    return $submodelos;
}

/* =========================================================================
 * Resumen de correlación — cuántos productos activos tienen al menos un
 * código de proveedor vinculado (gestion__productos_proveedores activo),
 * para el cartel de encabezado de la página.
 * ========================================================================= */
function obtenerResumenCorrelacion($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT
                COUNT(*) AS total_productos,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM gestion__productos_proveedores pp
                    WHERE pp.producto_id = p.producto_id
                    AND pp.tabla_estado_registro_id = 1
                ) THEN 1 ELSE 0 END) AS con_vinculo
            FROM gestion__productos p
            WHERE p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerResumenCorrelacion: " . mysqli_error($conexion));
        return ['total_productos' => 0, 'con_vinculo' => 0, 'sin_vinculo' => 0, 'porcentaje' => 0];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $total = intval($fila['total_productos'] ?? 0);
    $con_vinculo = intval($fila['con_vinculo'] ?? 0);

    return [
        'total_productos' => $total,
        'con_vinculo' => $con_vinculo,
        'sin_vinculo' => $total - $con_vinculo,
        'porcentaje' => $total > 0 ? round(($con_vinculo / $total) * 100, 1) : 0
    ];
}

/* =========================================================================
 * Dashboard — top proveedores por cantidad de productos vinculados,
 * correlación por marca y proveedores activos sin ningún producto vinculado.
 * Se resuelve todo en una sola llamada (obtenerDatosDashboard) para que la
 * pestaña Dashboard haga un solo request.
 * ========================================================================= */
function obtenerTopProveedoresPorVinculos($conexion, $empresa_id, $limite = 10)
{
    $empresa_id = intval($empresa_id);
    $limite = intval($limite);

    $sql = "SELECT e.entidad_id, e.entidad_nombre, COUNT(*) AS cantidad
            FROM gestion__productos_proveedores pp
            INNER JOIN gestion__entidades e ON pp.entidad_id = e.entidad_id
            WHERE pp.empresa_id = ? AND pp.tabla_estado_registro_id = 1
            GROUP BY e.entidad_id, e.entidad_nombre
            ORDER BY cantidad DESC, e.entidad_nombre ASC
            LIMIT ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerTopProveedoresPorVinculos: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $limite);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['cantidad'] = intval($fila['cantidad']);
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function obtenerCorrelacionPorMarca($conexion, $empresa_id, $limite = 10)
{
    $empresa_id = intval($empresa_id);
    $limite = intval($limite);

    $sql = "SELECT ma.marca_id, ma.marca_nombre,
                COUNT(DISTINCT pc.producto_id) AS total_productos,
                COUNT(DISTINCT CASE WHEN EXISTS (
                    SELECT 1 FROM gestion__productos_proveedores pp2
                    WHERE pp2.producto_id = pc.producto_id
                    AND pp2.tabla_estado_registro_id = 1
                ) THEN pc.producto_id END) AS con_vinculo
            FROM gestion__productos_compatibilidad pc
            INNER JOIN gestion__marcas ma ON pc.marca_id = ma.marca_id
            INNER JOIN gestion__productos p ON p.producto_id = pc.producto_id
                AND p.tabla_estado_registro_id = 1
            WHERE pc.empresa_id = ? AND pc.tabla_estado_registro_id = 1
            GROUP BY ma.marca_id, ma.marca_nombre
            ORDER BY total_productos DESC, ma.marca_nombre ASC
            LIMIT ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerCorrelacionPorMarca: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $limite);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $total = intval($fila['total_productos']);
        $con_vinculo = intval($fila['con_vinculo']);
        $filas[] = [
            'marca_id' => $fila['marca_id'],
            'marca_nombre' => $fila['marca_nombre'],
            'total_productos' => $total,
            'con_vinculo' => $con_vinculo,
            'porcentaje' => $total > 0 ? round(($con_vinculo / $total) * 100, 1) : 0
        ];
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function obtenerProveedoresSinVinculos($conexion, $empresa_id, $limite = 50)
{
    $empresa_id = intval($empresa_id);
    $limite = intval($limite);

    $sql = "SELECT e.entidad_id, e.entidad_nombre, e.cuit
            FROM gestion__entidades e
            WHERE e.empresa_id = ?
            AND e.es_proveedor = 1
            AND e.tabla_estado_registro_id = 1
            AND NOT EXISTS (
                SELECT 1 FROM gestion__productos_proveedores pp
                WHERE pp.entidad_id = e.entidad_id
                AND pp.tabla_estado_registro_id = 1
            )
            ORDER BY e.entidad_nombre
            LIMIT ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerProveedoresSinVinculos: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $limite);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function obtenerDatosDashboard($conexion, $empresa_id)
{
    $proveedores_sin_vinculos = obtenerProveedoresSinVinculos($conexion, $empresa_id, 50);

    return [
        'resumen' => obtenerResumenCorrelacion($conexion, $empresa_id),
        'top_proveedores' => obtenerTopProveedoresPorVinculos($conexion, $empresa_id, 10),
        'correlacion_marca' => obtenerCorrelacionPorMarca($conexion, $empresa_id, 10),
        'proveedores_sin_vinculos' => $proveedores_sin_vinculos,
        'total_proveedores_sin_vinculos' => count($proveedores_sin_vinculos)
    ];
}

/* =========================================================================
 * Listado paginado (server-side DataTables) — un renglón por PRODUCTO, con
 * sus vínculos activos a proveedores embebidos como JSON (vinculos_json).
 * Misma convención de paginación liviana que productos_model.php: acá no
 * hace falta el paso en 2 etapas porque no hay joins uno-a-muchos que
 * generen fan-out (el detalle de vínculos es una subconsulta escalar).
 * ========================================================================= */
function obtenerProductosProveedoresPaginados($conexion, $empresa_idx, $opciones)
{
    $start = intval($opciones['start'] ?? 0);
    $length = intval($opciones['length'] ?? 10);
    if ($length <= 0) $length = 10;
    $order_column_index = intval($opciones['order_column'] ?? 0);
    $order_dir = (strtolower($opciones['order_dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    $filtro_texto = trim($opciones['filtro_texto'] ?? '');
    $filtro_proveedor_id = intval($opciones['filtro_proveedor_id'] ?? 0);
    $filtro_vinculo = $opciones['filtro_vinculo'] ?? 'todos';
    $filtro_marca = trim($opciones['filtro_marca'] ?? '');
    $filtro_modelo = trim($opciones['filtro_modelo'] ?? '');
    $filtro_submodelo = trim($opciones['filtro_submodelo'] ?? '');

    $columnas_ordenables = [0 => 'p.producto_codigo', 1 => 'p.producto_nombre'];
    $order_by = $columnas_ordenables[$order_column_index] ?? 'p.producto_nombre';

    $where_conditions = ["p.empresa_id = ?", "p.tabla_estado_registro_id = 1"];
    $where_params = [$empresa_idx];
    $where_types = "i";

    if ($filtro_texto !== '') {
        $like = '%' . $filtro_texto . '%';
        $where_conditions[] = "(p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.proveedores_busqueda LIKE ?)";
        $where_params[] = $like;
        $where_params[] = $like;
        $where_params[] = $like;
        $where_types .= "sss";
    }

    if ($filtro_proveedor_id > 0) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM gestion__productos_proveedores ppf
            WHERE ppf.producto_id = p.producto_id
            AND ppf.entidad_id = ?
            AND ppf.tabla_estado_registro_id = 1
        )";
        $where_params[] = $filtro_proveedor_id;
        $where_types .= "i";
    }

    if ($filtro_vinculo === 'con') {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM gestion__productos_proveedores ppc
            WHERE ppc.producto_id = p.producto_id
            AND ppc.tabla_estado_registro_id = 1
        )";
    } elseif ($filtro_vinculo === 'sin') {
        $where_conditions[] = "NOT EXISTS (
            SELECT 1 FROM gestion__productos_proveedores ppc
            WHERE ppc.producto_id = p.producto_id
            AND ppc.tabla_estado_registro_id = 1
        )";
    }

    // Marca + modelo + submodelo tienen que cumplirse en la MISMA fila de
    // gestion__productos_compatibilidad (no 3 EXISTS sueltos) — mismo
    // criterio que proveedor_precios_model.php::obtenerPreciosProveedor.
    if ($filtro_marca !== '' || $filtro_modelo !== '' || $filtro_submodelo !== '') {
        $compat_conditions = ["pc.producto_id = p.producto_id", "pc.empresa_id = p.empresa_id", "pc.tabla_estado_registro_id = 1"];
        if ($filtro_marca !== '') {
            $compat_conditions[] = "pc.marca_id = ?";
            $where_params[] = intval($filtro_marca);
            $where_types .= "i";
        }
        if ($filtro_modelo !== '') {
            $compat_conditions[] = "pc.modelo_id = ?";
            $where_params[] = intval($filtro_modelo);
            $where_types .= "i";
        }
        if ($filtro_submodelo !== '') {
            $compat_conditions[] = "pc.submodelo_id = ?";
            $where_params[] = intval($filtro_submodelo);
            $where_types .= "i";
        }
        $where_conditions[] = "EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc WHERE " . implode(" AND ", $compat_conditions) . ")";
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Total sin filtros (solo empresa + activo)
    $sql_total = "SELECT COUNT(*) as total FROM gestion__productos p WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1";
    $stmt_total = mysqli_prepare($conexion, $sql_total);
    mysqli_stmt_bind_param($stmt_total, "i", $empresa_idx);
    mysqli_stmt_execute($stmt_total);
    $total_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total));
    $total_records = intval($total_row['total'] ?? 0);
    mysqli_stmt_close($stmt_total);

    // Total filtrado
    $sql_count = "SELECT COUNT(*) as total FROM gestion__productos p $where_clause";
    $stmt_count = mysqli_prepare($conexion, $sql_count);
    if (!$stmt_count) {
        return ['total' => $total_records, 'filtered' => 0, 'productos' => []];
    }
    mysqli_stmt_bind_param($stmt_count, $where_types, ...$where_params);
    mysqli_stmt_execute($stmt_count);
    $filtered_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
    $filtered_records = intval($filtered_row['total'] ?? 0);
    mysqli_stmt_close($stmt_count);

    $sql = "SELECT
                p.producto_id,
                p.producto_codigo,
                p.producto_nombre,
                p.compatibilidad_texto,
                (SELECT COUNT(*) FROM gestion__productos_proveedores c
                 WHERE c.producto_id = p.producto_id AND c.tabla_estado_registro_id = 1) AS cantidad_vinculos,
                COALESCE(
                    (SELECT CONCAT('[', GROUP_CONCAT(
                        JSON_OBJECT(
                            'producto_proveedor_id', pp.producto_proveedor_id,
                            'entidad_id', pp.entidad_id,
                            'entidad_nombre', COALESCE(e.entidad_nombre, ''),
                            'codigo_proveedor', COALESCE(pp.codigo_proveedor, '')
                        )
                        ORDER BY e.entidad_nombre
                        SEPARATOR ','
                    ), ']')
                    FROM gestion__productos_proveedores pp
                    LEFT JOIN gestion__entidades e ON pp.entidad_id = e.entidad_id
                    WHERE pp.producto_id = p.producto_id
                    AND pp.empresa_id = p.empresa_id
                    AND pp.tabla_estado_registro_id = 1
                    ), '[]'
                ) AS vinculos_json
            FROM gestion__productos p
            $where_clause
            ORDER BY $order_by $order_dir, p.producto_id ASC
            LIMIT ? OFFSET ?";

    $params = $where_params;
    $types = $where_types . "ii";
    $params[] = $length;
    $params[] = $start;

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerProductosProveedoresPaginados: " . mysqli_error($conexion));
        return ['total' => $total_records, 'filtered' => $filtered_records, 'productos' => []];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['cantidad_vinculos'] = intval($fila['cantidad_vinculos']);
        $vinculos = json_decode($fila['vinculos_json'], true);
        $fila['vinculos'] = is_array($vinculos) ? $vinculos : [];
        unset($fila['vinculos_json']);
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return ['total' => $total_records, 'filtered' => $filtered_records, 'productos' => $productos];
}

/* =========================================================================
 * Mantiene materializada gestion__productos.proveedores_busqueda — MISMA
 * función que productos_model.php::recalcularProveedoresBusqueda, duplicada
 * acá por la misma convención de no cruzar includes entre archivos de
 * página. Debe llamarse siempre que se agregue/edite/inhabilite/habilite
 * un vínculo.
 * ========================================================================= */
function recalcularProveedoresBusqueda($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    if ($producto_id <= 0) return false;

    $sql = "UPDATE gestion__productos p
            SET p.proveedores_busqueda = (
                SELECT GROUP_CONCAT(DISTINCT pp.codigo_proveedor SEPARATOR ' ')
                FROM gestion__productos_proveedores pp
                WHERE pp.producto_id = p.producto_id
                AND pp.tabla_estado_registro_id = 1
                AND pp.codigo_proveedor IS NOT NULL
                AND pp.codigo_proveedor <> ''
            )
            WHERE p.producto_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando UPDATE proveedores_busqueda: " . mysqli_error($conexion));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log("Error ejecutando UPDATE proveedores_busqueda($producto_id): " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $ok;
}

/* =========================================================================
 * Alta rápida de vínculo. Si ya existe una fila INACTIVA para el mismo
 * producto_id + entidad_id, la reactiva (transición Habilitar) en vez de
 * insertar un duplicado — mismo criterio que
 * proveedor_precios_model.php::obtenerOCrearProductoProveedor.
 * ========================================================================= */
function agregarVinculoProveedor($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $codigo_proveedor = trim($data['codigo_proveedor'] ?? '');

    if ($producto_id <= 0) {
        return ['success' => false, 'message' => 'Producto no válido'];
    }
    if ($entidad_id <= 0) {
        return ['success' => false, 'message' => 'Proveedor no válido'];
    }

    $sql_check = "SELECT producto_proveedor_id, tabla_estado_registro_id
                  FROM gestion__productos_proveedores
                  WHERE producto_id = ? AND entidad_id = ? AND empresa_id = ?
                  LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($existente) {
        if (intval($existente['tabla_estado_registro_id']) === 1) {
            return ['success' => false, 'message' => 'Este proveedor ya está vinculado (activo) a este producto'];
        }

        // Reactivar el vínculo inactivo existente
        $sql_upd = "UPDATE gestion__productos_proveedores
                    SET tabla_estado_registro_id = 1, codigo_proveedor = ?
                    WHERE producto_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param($stmt, "si", $codigo_proveedor, $existente['producto_proveedor_id']);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al reactivar el vínculo: ' . mysqli_error($conexion)];
        }
        recalcularProveedoresBusqueda($conexion, $producto_id);
        return ['success' => true, 'message' => 'Vínculo reactivado correctamente', 'producto_proveedor_id' => intval($existente['producto_proveedor_id'])];
    }

    $sql = "INSERT INTO gestion__productos_proveedores
            (producto_id, entidad_id, empresa_id, codigo_proveedor, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error en la consulta'];
    }
    mysqli_stmt_bind_param($stmt, "iiis", $producto_id, $entidad_id, $empresa_id, $codigo_proveedor);
    $ok = mysqli_stmt_execute($stmt);

    if (!$ok) {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Error al vincular proveedor: ' . mysqli_error($conexion)];
    }
    $producto_proveedor_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    recalcularProveedoresBusqueda($conexion, $producto_id);

    return ['success' => true, 'message' => 'Proveedor vinculado correctamente', 'producto_proveedor_id' => $producto_proveedor_id];
}

/* =========================================================================
 * Edición rápida del código de proveedor de un vínculo ya existente.
 * ========================================================================= */
function editarVinculoProveedor($conexion, $producto_proveedor_id, $codigo_proveedor, $empresa_idx)
{
    $producto_proveedor_id = intval($producto_proveedor_id);
    $empresa_idx = intval($empresa_idx);
    $codigo_proveedor = trim($codigo_proveedor);

    $sql_check = "SELECT producto_id FROM gestion__productos_proveedores
                  WHERE producto_proveedor_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $producto_proveedor_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$fila) {
        return ['success' => false, 'message' => 'Vínculo no encontrado'];
    }

    $sql = "UPDATE gestion__productos_proveedores
            SET codigo_proveedor = ?
            WHERE producto_proveedor_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $codigo_proveedor, $producto_proveedor_id, $empresa_idx);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        return ['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($conexion)];
    }
    recalcularProveedoresBusqueda($conexion, $fila['producto_id']);
    return ['success' => true, 'message' => 'Código actualizado correctamente'];
}

/* =========================================================================
 * Baja rápida (inhabilitar) / reactivación (habilitar) de un vínculo puntual
 * vía el motor de estados genérico — misma lógica que
 * proveedor_precios_model.php::ejecutarTransicionEstadoPrecioProveedor.
 * ========================================================================= */
function ejecutarTransicionEstadoVinculo($conexion, $producto_proveedor_id, $accion_js, $pagina_id, $empresa_idx)
{
    $producto_proveedor_id = intval($producto_proveedor_id);
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

    $sql_check = "SELECT producto_proveedor_id, producto_id, tabla_estado_registro_id
                  FROM gestion__productos_proveedores
                  WHERE producto_proveedor_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "ii", $producto_proveedor_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$registro) {
        return ['success' => false, 'message' => 'Vínculo no encontrado'];
    }

    $estado_actual_id = intval($registro['tabla_estado_registro_id']);

    $sql_funcion = "SELECT * FROM conf__paginas_funciones
                    WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ?
                    LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $funcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$funcion) {
        return ['success' => false, 'message' => 'Acción no permitida para este estado'];
    }

    $estado_destino_id = intval($funcion['tabla_estado_registro_destino_id']);

    $sql_upd = "UPDATE gestion__productos_proveedores SET tabla_estado_registro_id = ? WHERE producto_proveedor_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_upd);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $producto_proveedor_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error ejecutando transición de estado vínculo: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Error actualizando el estado'];
    }
    mysqli_stmt_close($stmt);

    recalcularProveedoresBusqueda($conexion, $registro['producto_id']);

    return ['success' => true, 'message' => 'Estado actualizado correctamente'];
}
