<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/precios_core_model.php';

/* =========================================================================
 * Motor de estados genérico — COPIA FUNCIONAL de las mismas funciones que
 * ya están en ventas_pedidos_model.php (obtenerFuncionesPagina,
 * obtenerBotonesPorEstado, obtenerBotonAgregar, obtenerEstadoInicial,
 * obtenerTablaOrigenPorPagina). Se duplican a propósito acá, siguiendo la
 * misma decisión ya documentada en ese archivo: no introducir un include
 * cruzado entre módulos. Si en algún momento se extrae un helper común
 * (conf_paginas_helper.php o similar), estas 5 funciones deberían borrarse
 * de acá y de cualquier otro _model.php que las duplique.
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

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $esConfirmable = ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0;

            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $esConfirmable
            ];
        }
    }
    return $botones;
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
        'nombre_funcion' => 'Nuevo Precio',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicialPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT ter.estado_registro_id
            FROM conf__paginas p
            JOIN conf__tablas_estados_registros ter ON ter.tabla_id = p.tabla_id
            WHERE p.pagina_id = ?
            AND ter.es_inicial = 1
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta estado inicial: " . mysqli_error($conexion));
        return 1;
    }

    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $fila ? $fila['estado_registro_id'] : 1;
}

function obtenerTablaOrigenPorPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta tabla_id: " . mysqli_error($conexion));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return ($row && !empty($row['tabla_id'])) ? (int)$row['tabla_id'] : null;
}

/* =========================================================================
 * Combo de proveedores — confirmado por Pablo: gestion__entidades.es_proveedor = 1
 * ========================================================================= */
function obtenerProveedores($conexion, $empresa_id)
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
        error_log("Error preparando obtenerProveedores: " . mysqli_error($conexion));
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
 * 3 consultas que ya usa productos_model.php (obtenerMarcas/obtenerModelos/
 * obtenerSubmodelos), duplicadas acá siguiendo la misma convención de no
 * cruzar includes entre módulos que ya se aplica al motor de estados.
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
 * Monedas — misma consulta que ya usa ventas_pedidos_model.php::obtenerMonedas.
 * ========================================================================= */
function obtenerMonedas($conexion, $empresa_idx)
{
    $sql = "SELECT moneda_id, codigo, moneda, simbolo, es_moneda_base, cotizacion_actual
            FROM gestion__monedas
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1
            ORDER BY es_moneda_base DESC, orden, moneda";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta monedas: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $monedas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $monedas[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $monedas;
}

/* =========================================================================
 * Listado principal. entidad_id es OPCIONAL: si viene vacío/0, busca en
 * TODOS los proveedores (para "¿quién me vende este producto?" sin elegir
 * proveedor primero) — en ese caso hace falta al menos un filtro (código,
 * marca, modelo o submodelo), si no se corta antes de tirar el catálogo
 * completo × cada proveedor.
 * ========================================================================= */
function obtenerPreciosProveedor($conexion, $empresa_id, $entidad_id, $pagina_id, $filtros = [])
{
    return obtenerFilasPreciosProveedorCalculadas($conexion, $empresa_id, $entidad_id, $pagina_id, $filtros, true);
}

/*
 * Núcleo compartido por el listado (obtenerPreciosProveedor, con botones por
 * fila) y el dashboard (obtenerDashboardPreciosProveedor, sin botones): trae
 * las mismas filas con el mismo costo_neto_compra / precio_deberia_ser que
 * ve el usuario en la grilla, para que los números no difieran entre
 * pestañas. $incluirBotones=false se salta obtenerBotonesPorEstado() por
 * fila (una consulta cada una) cuando no hace falta pintar acciones, ya que
 * la vista "todos los proveedores" del dashboard puede traer el catálogo
 * completo.
 */
function obtenerFilasPreciosProveedorCalculadas($conexion, $empresa_id, $entidad_id, $pagina_id, $filtros, $incluirBotones)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);

    $filtro_codigo = trim($filtros['filtro_codigo'] ?? '');
    $filtro_marca = $filtros['filtro_marca'] ?? '';
    $filtro_modelo = $filtros['filtro_modelo'] ?? '';
    $filtro_submodelo = $filtros['filtro_submodelo'] ?? '';

    $hay_algun_filtro = $filtro_codigo !== '' || $filtro_marca !== '' || $filtro_modelo !== '' || $filtro_submodelo !== '';

    // Sin proveedor Y sin ningún filtro: no se ejecuta la consulta — sería
    // todo el catálogo multiplicado por cada proveedor que lo vende.
    if (empty($entidad_id) && !$hay_algun_filtro) {
        return ['listas_precios' => obtenerListasPreciosActivas($conexion, $empresa_id), 'filas' => [], 'requiere_filtro' => true];
    }

    $where_conditions = ["ppp.empresa_id = ?"];
    $where_params = [$empresa_id];
    $where_types = "i";

    if (!empty($entidad_id)) {
        $where_conditions[] = "ppp.entidad_id = ?";
        $where_params[] = $entidad_id;
        $where_types .= "i";
    }

    if ($filtro_codigo !== '') {
        // Ampliado a producto_nombre — el cuadro de búsqueda ahora es
        // general (código propio, código del proveedor, o nombre), mismo
        // criterio que el buscador de productos.php.
        $where_conditions[] = "(p.producto_codigo LIKE ? OR pp.codigo_proveedor LIKE ? OR p.producto_nombre LIKE ?)";
        $where_params[] = '%' . $filtro_codigo . '%';
        $where_params[] = '%' . $filtro_codigo . '%';
        $where_params[] = '%' . $filtro_codigo . '%';
        $where_types .= "sss";
    }

    // Mismo criterio que productos_model.php::obtenerProductosPaginados: marca +
    // modelo + submodelo tienen que cumplirse en la MISMA fila de
    // gestion__productos_compatibilidad (no 3 EXISTS sueltos, que podrían
    // dar falso positivo con combinaciones que no existen realmente).
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

    // Sin JOIN a gestion__monedas ni a conf__tablas_estados_registros/conf__colores:
    // la columna Moneda y la columna Estado se sacaron de la grilla (quedan solo
    // como datos internos del payload para el modal de edición y el motor de
    // botones, que sigue necesitando tabla_estado_registro_id). Se agrega
    // p.producto_categoria_id para el matching de reglas, ppp.entidad_id +
    // e.entidad_nombre (proveedor de ESTA fila — necesario en la vista sin
    // proveedor elegido, donde puede haber varios por producto), y
    // compatibilidades_detalle: mismo criterio que productos_model.php (una
    // entrada por fila REAL de marca+modelo+submodelo+años, vía JSON_OBJECT),
    // no 3 GROUP_CONCAT DISTINCT sueltos por campo — eso mezclaría combos que
    // no existen realmente si el producto tiene más de una compatibilidad.
    $sql = "SELECT ppp.producto_proveedor_precio_id, ppp.producto_proveedor_id, ppp.entidad_id,
                   e.entidad_nombre,
                   ppp.precio_lista, ppp.moneda_id, ppp.f_vigencia_desde,
                   ppp.origen_carga, ppp.archivo_importacion, ppp.tabla_estado_registro_id,
                   pp.codigo_proveedor, pp.producto_id,
                   p.producto_codigo, p.producto_nombre, p.producto_categoria_id,
                   COALESCE(
                       (SELECT CONCAT('[', GROUP_CONCAT(
                           JSON_OBJECT(
                               'marca', ma.marca_nombre,
                               'modelo', mo.modelo_nombre,
                               'submodelo', sm.submodelo_nombre,
                               'anio_desde', pcm.anio_desde,
                               'anio_hasta', pcm.anio_hasta
                           )
                           ORDER BY ma.marca_nombre, mo.modelo_nombre, sm.submodelo_nombre, pcm.anio_desde
                           SEPARATOR ','
                       ), ']')
                        FROM gestion__productos_compatibilidad pcm
                        INNER JOIN gestion__marcas ma ON pcm.marca_id = ma.marca_id
                        INNER JOIN gestion__modelos mo ON pcm.modelo_id = mo.modelo_id
                        LEFT JOIN gestion__submodelos sm ON pcm.submodelo_id = sm.submodelo_id
                        WHERE pcm.producto_id = p.producto_id AND pcm.empresa_id = p.empresa_id AND pcm.tabla_estado_registro_id = 1
                       ), '[]'
                   ) AS compatibilidades_detalle
            FROM gestion__productos_proveedores_precios ppp
            INNER JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = ppp.producto_proveedor_id
            INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = ppp.entidad_id
            $where_clause
            ORDER BY p.producto_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerPreciosProveedor: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $where_types, ...$where_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    $productoIds = [];
    $entidadIds = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productoIds[] = (int)$fila['producto_id'];
        $entidadIds[] = (int)$fila['entidad_id'];
        $filas[] = $fila;
    }
    mysqli_stmt_close($stmt);

    if (empty($filas)) {
        return ['listas_precios' => obtenerListasPreciosActivas($conexion, $empresa_id), 'filas' => []];
    }

    $productoIds = array_values(array_unique($productoIds));
    $entidadIds = array_values(array_unique($entidadIds));

    // Precargas para TODO el listado a la vez (nada dentro del foreach de
    // abajo dispara una consulta nueva): mismo criterio anti-N+1 de siempre.
    // El descuento ahora se precarga POR PROVEEDOR (antes era un solo valor
    // fijo, válido cuando la grilla ya venía filtrada a un único proveedor).
    $costosPorProducto = obtenerCostosActualesPorProductos($conexion, $empresa_id, $productoIds);
    $descuentosPorProveedor = obtenerDescuentosVigentesPorProveedores($conexion, $entidadIds);
    $listasPrecios = obtenerListasPreciosActivas($conexion, $empresa_id);
    $listaIds = array_column($listasPrecios, 'lista_precio_id');
    $preciosActualesPorProductoYLista = obtenerPreciosVentaActualesPorProductoYLista($conexion, $empresa_id, $productoIds, $listaIds);
    $reglasPorLista = obtenerReglasVigentesPorListas($conexion, $empresa_id, $entidad_id, $listaIds);

    $data = [];
    foreach ($filas as $fila) {
        $fila_entidad_id = (int)$fila['entidad_id'];
        $descuento_general = $descuentosPorProveedor[$fila_entidad_id] ?? 0.0;

        $precio_lista = (float)$fila['precio_lista'];
        $costo_neto = round($precio_lista * (1 - ($descuento_general / 100)), 4);
        $producto_id = (int)$fila['producto_id'];
        $categoria_id = (int)$fila['producto_categoria_id'];

        $fila['descuento_general_pct'] = $descuento_general;
        $fila['costo_neto_compra'] = $costo_neto;
        $fila['costo_actual_registrado'] = $costosPorProducto[$producto_id] ?? null;
        $fila['compatibilidades_detalle'] = decodificarCompatibilidadesDetalle($fila['compatibilidades_detalle'] ?? '[]');

        $deberianSerPorLista = calcularPreciosDeberianSerPorLista($listasPrecios, $reglasPorLista, $producto_id, $categoria_id, $fila_entidad_id, $costo_neto);

        $porLista = [];
        foreach ($listasPrecios as $lp) {
            $lid = $lp['lista_precio_id'];
            $porLista[$lid] = [
                'precio_actual' => $preciosActualesPorProductoYLista[$producto_id][$lid] ?? null,
                'precio_deberia_ser' => $deberianSerPorLista[$lid]['precio'] ?? null
            ];
        }
        $fila['precios_por_lista'] = $porLista;

        if ($incluirBotones) {
            $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        }
        $data[] = $fila;
    }

    return ['listas_precios' => $listasPrecios, 'filas' => $data];
}

/*
 * Decodifica el JSON de compatibilidades_detalle (una entrada por fila real
 * de gestion__productos_compatibilidad, no por campo suelto) al mismo
 * formato que productos_model.php: marca, modelo, submodelo y un texto de
 * años ya armado ("1998-2000" o "1998-Actual" si no tiene tope o usa el
 * sentinel >=2100 de "sin tope superior").
 */
function decodificarCompatibilidadesDetalle($json)
{
    $compatibilidades = [];
    if (empty($json) || $json === '[]') {
        return $compatibilidades;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $compatibilidades;
    }

    foreach ($decoded as $item) {
        $anio_desde_raw = $item['anio_desde'] ?? null;
        $anio_desde = $anio_desde_raw !== null ? (int)$anio_desde_raw : null;
        $anio_hasta_raw = $item['anio_hasta'] ?? null;
        if ($anio_hasta_raw === null || (int)$anio_hasta_raw >= 2100) {
            $anio_texto = $anio_desde . '-Actual';
        } else {
            $anio_texto = $anio_desde . '-' . (int)$anio_hasta_raw;
        }
        $compatibilidades[] = [
            'marca' => $item['marca'] ?? '',
            'modelo' => $item['modelo'] ?? '',
            'submodelo' => $item['submodelo'] ?? null,
            'anios' => $anio_texto
        ];
    }

    return $compatibilidades;
}

/*
 * Dashboard de precios de proveedores (solapa "Dashboard" de
 * proveedor_precios.php): sobre las mismas filas calculadas para el
 * listado, arma el top 10 de mayores variaciones de costo (Costo Neto de
 * Compra vs Último Costo Registrado) y el top 10 de mayores variaciones en
 * la lista de precios "General" (Precio Actual vs Debería ser).
 *
 * La lista "general" se identifica por nombre ('General', case-insensitive);
 * si la empresa la renombró o no existe, usa la primera lista activa como
 * fallback (nunca deja la sección vacía por un nombre distinto) — el nombre
 * efectivamente usado viaja en 'lista_general_nombre' para que el frontend
 * lo aclare.
 */
function obtenerDashboardPreciosProveedor($conexion, $empresa_id, $entidad_id, $filtros = [])
{
    $resultado = obtenerFilasPreciosProveedorCalculadas($conexion, $empresa_id, $entidad_id, 0, $filtros, false);
    $filas = $resultado['filas'];
    $listasPrecios = $resultado['listas_precios'];

    $dashboard = [
        'top_variaciones_costo' => [],
        'top_variaciones_lista_general' => [],
        'lista_general_nombre' => null,
        // Defensa en profundidad: el frontend ya evita llamar a este endpoint
        // sin proveedor ni filtro (mostraría "0 resultados" indistinguible de
        // "no hay variaciones"), pero si algún consumidor lo llama igual, que
        // pueda distinguir ambos casos mirando esta bandera.
        'requiere_filtro' => $resultado['requiere_filtro'] ?? false
    ];

    if (empty($filas)) {
        return $dashboard;
    }

    $listaGeneral = null;
    foreach ($listasPrecios as $lp) {
        if (strcasecmp($lp['lista_precio_nombre'], 'General') === 0) {
            $listaGeneral = $lp;
            break;
        }
    }
    if (!$listaGeneral && !empty($listasPrecios)) {
        $listaGeneral = $listasPrecios[0];
    }
    $listaGeneralId = $listaGeneral['lista_precio_id'] ?? null;
    $dashboard['lista_general_nombre'] = $listaGeneral['lista_precio_nombre'] ?? null;

    $variacionesCosto = [];
    $variacionesListaGeneral = [];

    foreach ($filas as $fila) {
        $costoNeto = (float)$fila['costo_neto_compra'];
        $costoActual = $fila['costo_actual_registrado'];
        $entidadNombre = $fila['entidad_nombre'] ?? '';
        // Misma compatibilidad que ya viaja en el listado (decodificada en
        // obtenerFilasPreciosProveedorCalculadas) — para que el frontend la
        // muestre debajo del producto igual que en la solapa Listado.
        $compatibilidad = $fila['compatibilidades_detalle'] ?? [];

        if ($costoActual !== null) {
            $costoActual = (float)$costoActual;
            $pct = $costoActual != 0 ? (($costoNeto - $costoActual) / $costoActual) * 100 : 0;
            $variacionesCosto[] = [
                'producto_nombre' => $fila['producto_nombre'],
                'compatibilidades_detalle' => $compatibilidad,
                'entidad_nombre' => $entidadNombre,
                'costo_actual' => $costoActual,
                'costo_neto_compra' => $costoNeto,
                'variacion_pct' => round($pct, 2)
            ];
        }

        if ($listaGeneralId !== null) {
            $porLista = $fila['precios_por_lista'][$listaGeneralId] ?? [];
            $actual = $porLista['precio_actual'] ?? null;
            $deberiaSer = $porLista['precio_deberia_ser'] ?? null;
            if ($actual !== null && $deberiaSer !== null) {
                $actual = (float)$actual;
                $deberiaSer = (float)$deberiaSer;
                $pct = $actual != 0 ? (($deberiaSer - $actual) / $actual) * 100 : 0;
                $variacionesListaGeneral[] = [
                    'producto_nombre' => $fila['producto_nombre'],
                    'compatibilidades_detalle' => $compatibilidad,
                    'entidad_nombre' => $entidadNombre,
                    'precio_actual' => $actual,
                    'precio_deberia_ser' => $deberiaSer,
                    'variacion_pct' => round($pct, 2)
                ];
            }
        }
    }

    usort($variacionesCosto, function ($a, $b) { return abs($b['variacion_pct']) <=> abs($a['variacion_pct']); });
    $dashboard['top_variaciones_costo'] = array_slice($variacionesCosto, 0, 10);

    usort($variacionesListaGeneral, function ($a, $b) { return abs($b['variacion_pct']) <=> abs($a['variacion_pct']); });
    $dashboard['top_variaciones_lista_general'] = array_slice($variacionesListaGeneral, 0, 10);

    return $dashboard;
}

/* =========================================================================
 * Buscador para el alta manual — busca en TODO el catálogo de
 * gestion__productos (antes solo buscaba entre los ya vinculados a este
 * proveedor vía gestion__productos_proveedores, así que un producto que
 * el proveedor todavía no tenía cargado no aparecía nunca: "no se ve ese
 * cambio"). Cada resultado indica, para ESTE proveedor:
 *   - vinculo_existente: 0/1 — si ya hay fila en gestion__productos_proveedores
 *   - ya_tiene_precio: 0/1 — si esa vinculación ya tiene un precio cargado
 * Si el producto no está vinculado todavía, guardarPrecioProveedor() crea
 * el vínculo al guardar (ver obtenerOCrearProductoProveedor).
 * ========================================================================= */
function buscarProductosCatalogo($conexion, $empresa_id, $entidad_id, $q)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $q = '%' . trim($q) . '%';

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   pp.producto_proveedor_id, pp.codigo_proveedor,
                   (ppp.producto_proveedor_precio_id IS NOT NULL) AS ya_tiene_precio
            FROM gestion__productos p
            LEFT JOIN gestion__productos_proveedores pp
                   ON pp.producto_id = p.producto_id AND pp.entidad_id = ? AND pp.empresa_id = p.empresa_id AND pp.tabla_estado_registro_id = 1
            LEFT JOIN gestion__productos_proveedores_precios ppp
                   ON ppp.producto_proveedor_id = pp.producto_proveedor_id
            WHERE p.empresa_id = ?
              AND p.tabla_estado_registro_id = 1
              AND (p.producto_nombre LIKE ? OR p.producto_codigo LIKE ? OR pp.codigo_proveedor LIKE ?)
            ORDER BY p.producto_nombre
            LIMIT 50";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando buscarProductosCatalogo: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "iisss", $entidad_id, $empresa_id, $q, $q, $q);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['vinculo_existente'] = $fila['producto_proveedor_id'] !== null ? 1 : 0;
        $fila['ya_tiene_precio'] = (int)$fila['ya_tiene_precio'];
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $productos;
}

/* =========================================================================
 * Alta / edición manual de un precio — misma función para ambos casos,
 * la diferencia la da si ya existe fila en la tabla "actual" para ese
 * producto_proveedor_id. Transacción: cierra el historial anterior (si
 * hay) y pisa/inserta la fila actual. Sin trigger de BD, resuelto desde
 * PHP (misma decisión ya tomada para el estado combinado de pedidos).
 * ========================================================================= */
function guardarPrecioProveedor($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $precio_lista = floatval($data['precio_lista'] ?? -1);
    $moneda_id = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
    $usuario_id = intval($data['usuario_id'] ?? 0);
    $pagina_id = intval($data['pagina_idx'] ?? 0);
    $f_vigencia_desde = !empty($data['f_vigencia_desde']) ? $data['f_vigencia_desde'] : date('Y-m-d');
    $origen_carga = 'MANUAL';

    if (empty($producto_id) || empty($entidad_id)) {
        return ['success' => false, 'message' => 'Debe seleccionar un producto y un proveedor.'];
    }
    if ($precio_lista < 0) {
        return ['success' => false, 'message' => 'El precio de lista debe ser mayor o igual a 0.'];
    }

    // Resuelve el vínculo producto↔proveedor (gestion__productos_proveedores);
    // si el producto se buscó desde el catálogo completo y todavía no
    // estaba vinculado a este proveedor, se crea acá (codigo_proveedor en
    // NULL, a completar después). Si ya existe, devuelve el mismo id de
    // siempre — el alta y la edición quedan unificadas bajo el mismo flujo.
    $producto_proveedor_id = obtenerOCrearProductoProveedor($conexion, $empresa_id, $entidad_id, $producto_id, $usuario_id);
    if (empty($producto_proveedor_id)) {
        return ['success' => false, 'message' => 'No se pudo vincular el producto con el proveedor.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        $sql_actual = "SELECT producto_proveedor_precio_id, precio_lista, moneda_id, f_vigencia_desde,
                              origen_carga, archivo_importacion, usuario_carga_id
                       FROM gestion__productos_proveedores_precios
                       WHERE producto_proveedor_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_actual);
        mysqli_stmt_bind_param($stmt, "i", $producto_proveedor_id);
        mysqli_stmt_execute($stmt);
        $registro_actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($registro_actual) {
            // Cerrar historial con el valor que se está reemplazando
            $sql_hist = "INSERT INTO gestion__productos_proveedores_precios_historico
                        (producto_proveedor_id, precio_lista, moneda_id, f_vigencia_desde, f_vigencia_hasta,
                         origen_carga, archivo_importacion, usuario_carga_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $sql_hist);
            $f_hasta = date('Y-m-d', strtotime($f_vigencia_desde . ' -1 day'));
            mysqli_stmt_bind_param(
                $stmt,
                "idsssssi",
                $producto_proveedor_id,
                $registro_actual['precio_lista'],
                $registro_actual['moneda_id'],
                $registro_actual['f_vigencia_desde'],
                $f_hasta,
                $registro_actual['origen_carga'],
                $registro_actual['archivo_importacion'],
                $registro_actual['usuario_carga_id']
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error guardando historial: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            $sql_upd = "UPDATE gestion__productos_proveedores_precios
                       SET precio_lista = ?, moneda_id = ?, f_vigencia_desde = ?, origen_carga = ?,
                           archivo_importacion = NULL, usuario_modificacion_id = ?
                       WHERE producto_proveedor_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_upd);
            mysqli_stmt_bind_param($stmt, "dissii", $precio_lista, $moneda_id, $f_vigencia_desde, $origen_carga, $usuario_id, $producto_proveedor_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error actualizando precio: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            $mensaje = 'Precio actualizado correctamente.';
        } else {
            $estado_inicial = obtenerEstadoInicialPagina($conexion, $pagina_id);

            $sql_ins = "INSERT INTO gestion__productos_proveedores_precios
                        (producto_proveedor_id, empresa_id, entidad_id, precio_lista, moneda_id,
                         f_vigencia_desde, origen_carga, usuario_carga_id, tabla_estado_registro_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $sql_ins);
            mysqli_stmt_bind_param(
                $stmt,
                "iiidsssii",
                $producto_proveedor_id,
                $empresa_id,
                $entidad_id,
                $precio_lista,
                $moneda_id,
                $f_vigencia_desde,
                $origen_carga,
                $usuario_id,
                $estado_inicial
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error insertando precio: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            $mensaje = 'Precio cargado correctamente.';
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => $mensaje];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en guardarPrecioProveedor: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Transición de estado (habilitar / inhabilitar). Simplificado respecto al
 * de ventas_pedidos: no hay sincronización con gestion__comprobantes,
 * porque esta tabla no es un comprobante.
 * ========================================================================= */
function ejecutarTransicionEstadoPrecioProveedor($conexion, $id, $accion_js, $pagina_id)
{
    $id = intval($id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT producto_proveedor_precio_id, tabla_estado_registro_id
                  FROM gestion__productos_proveedores_precios
                  WHERE producto_proveedor_precio_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$registro) {
        return ['success' => false, 'message' => 'Registro no encontrado'];
    }

    $estado_actual_id = $registro['tabla_estado_registro_id'];

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

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    $sql_upd = "UPDATE gestion__productos_proveedores_precios SET tabla_estado_registro_id = ? WHERE producto_proveedor_precio_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_upd);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error ejecutando transición de estado: " . mysqli_stmt_error($stmt));
        return ['success' => false, 'message' => 'Error actualizando el estado'];
    }
    mysqli_stmt_close($stmt);

    return ['success' => true, 'message' => 'Estado actualizado correctamente'];
}

/* =========================================================================
 * Historial de precios de un producto_proveedor_id puntual.
 * ========================================================================= */
function obtenerHistorialPrecioProveedor($conexion, $producto_proveedor_id)
{
    $producto_proveedor_id = intval($producto_proveedor_id);

    $sql = "SELECT h.precio_lista, h.moneda_id, h.f_vigencia_desde, h.f_vigencia_hasta,
                   h.origen_carga, h.archivo_importacion, h.usuario_carga_id, h.f_carga,
                   m.moneda, m.simbolo
            FROM gestion__productos_proveedores_precios_historico h
            LEFT JOIN gestion__monedas m ON m.moneda_id = h.moneda_id
            WHERE h.producto_proveedor_id = ?
            ORDER BY h.f_vigencia_desde DESC, h.producto_proveedor_precio_historico_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "i", $producto_proveedor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $historial = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $historial[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $historial;
}

/* =========================================================================
 * Importación de lista de proveedor desde Excel (codigo_proveedor,
 * descripcion, precio). Mismo patrón anti-N+1 que
 * listas_precios_productos_model.php::importarPreciosDesdeExcel: precarga
 * en 2 SELECTs, statements preparados una sola vez y reusados por
 * bind-por-referencia, todo el lote en una transacción. El front debe
 * mandar los lotes de a 300 filas (ver proveedor_precios.js), no las 5000
 * juntas.
 *
 * "descripcion" del Excel se usa SOLO para identificar al usuario los
 * códigos no vinculados en la respuesta — no se persiste en ninguna tabla.
 * ========================================================================= */
function importarListaProveedorDesdeExcel($conexion, $empresa_id, $entidad_id, $items, $usuario_id, $archivo_nombre, $pagina_id)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);

    if (empty($items)) {
        return ['success' => true, 'message' => 'Lote vacío.', 'procesados' => 0, 'sin_cambios' => 0, 'no_vinculados' => [], 'errores_count' => 0];
    }

    // Normalizar y deduplicar el lote (gana la última fila de ese código).
    // f_vigencia_desde viene del Excel (columna nueva); si falta o es
    // inválida, cae a hoy — así el usuario puede cargar una lista que
    // entra en vigencia a futuro sin tener que esperar esa fecha para
    // importarla.
    $fecha_hoy = date('Y-m-d');
    $filas = [];
    foreach ($items as $item) {
        $codigo = trim((string)($item['codigo_proveedor'] ?? ''));
        if ($codigo === '') continue;

        $f_vigencia_desde = trim((string)($item['f_vigencia_desde'] ?? ''));
        if ($f_vigencia_desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_vigencia_desde) || !strtotime($f_vigencia_desde)) {
            $f_vigencia_desde = $fecha_hoy;
        }

        $filas[$codigo] = [
            'precio' => floatval($item['precio'] ?? 0),
            'descripcion' => trim((string)($item['descripcion'] ?? '')),
            'f_vigencia_desde' => $f_vigencia_desde
        ];
    }
    $codigos = array_keys($filas);
    if (empty($codigos)) {
        return ['success' => true, 'message' => 'Lote sin códigos válidos.', 'procesados' => 0, 'sin_cambios' => 0, 'no_vinculados' => [], 'errores_count' => 0];
    }

    // 1) Precargar TODOS los producto_proveedor_id de ese proveedor que matchean los códigos del lote
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
    $tipos = str_repeat('s', count($codigos));
    $sql_pp = "SELECT producto_proveedor_id, codigo_proveedor
               FROM gestion__productos_proveedores
               WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                 AND codigo_proveedor IN ($placeholders)";
    $stmt = mysqli_prepare($conexion, $sql_pp);
    $params = array_merge([$entidad_id, $empresa_id], $codigos);
    mysqli_stmt_bind_param($stmt, "ii" . $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    // Un mismo código del proveedor puede estar vinculado a VARIOS productos
    // (el proveedor vende un artículo que aplica a más de un producto
    // propio): código → lista de producto_proveedor_id, y el precio del
    // Excel se aplica a todos. Clave normalizada igual que la comparación
    // del IN de MySQL (case-insensitive, sin espacios de más), para no
    // reportar como "no vinculado" un código que sí matcheó.
    $ppPorCodigo = [];
    $ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ppPorCodigo[normalizarCodigoProveedor($row['codigo_proveedor'])][] = (int)$row['producto_proveedor_id'];
        $ids[] = (int)$row['producto_proveedor_id'];
    }
    mysqli_stmt_close($stmt);

    // 2) Precargar los registros "actuales" existentes para esos producto_proveedor_id
    $actualPorId = [];
    if (!empty($ids)) {
        $placeholdersIds = implode(',', array_fill(0, count($ids), '?'));
        $tiposIds = str_repeat('i', count($ids));
        $sql_actual = "SELECT producto_proveedor_id, precio_lista, moneda_id, f_vigencia_desde,
                              origen_carga, archivo_importacion, usuario_carga_id
                       FROM gestion__productos_proveedores_precios
                       WHERE producto_proveedor_id IN ($placeholdersIds)";
        $stmt = mysqli_prepare($conexion, $sql_actual);
        mysqli_stmt_bind_param($stmt, $tiposIds, ...$ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $actualPorId[$row['producto_proveedor_id']] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    // 3) Preparar UNA VEZ los statements reutilizados en el loop
    $estado_inicial = obtenerEstadoInicialPagina($conexion, $pagina_id);

    $sql_hist = "INSERT INTO gestion__productos_proveedores_precios_historico
                (producto_proveedor_id, precio_lista, moneda_id, f_vigencia_desde, f_vigencia_hasta,
                 origen_carga, archivo_importacion, usuario_carga_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_hist = mysqli_prepare($conexion, $sql_hist);
    $h_pp_id = 0; $h_precio = 0.0; $h_moneda = 0; $h_desde = ''; $h_hasta = '';
    $h_origen = ''; $h_archivo = null; $h_usuario = 0;
    mysqli_stmt_bind_param($stmt_hist, "idsssssi", $h_pp_id, $h_precio, $h_moneda, $h_desde, $h_hasta, $h_origen, $h_archivo, $h_usuario);

    $sql_upd = "UPDATE gestion__productos_proveedores_precios
               SET precio_lista = ?, moneda_id = " . emp_moneda_base_id($conexion, $empresa_id) . ", f_vigencia_desde = ?, origen_carga = 'IMPORTACION',
                   archivo_importacion = ?, usuario_modificacion_id = ?
               WHERE producto_proveedor_id = ?";
    $stmt_upd = mysqli_prepare($conexion, $sql_upd);
    $u_precio = 0.0; $u_desde = ''; $u_archivo = ''; $u_usuario = 0; $u_pp_id = 0;
    mysqli_stmt_bind_param($stmt_upd, "dssii", $u_precio, $u_desde, $u_archivo, $u_usuario, $u_pp_id);

    $sql_ins = "INSERT INTO gestion__productos_proveedores_precios
               (producto_proveedor_id, empresa_id, entidad_id, precio_lista, moneda_id,
                f_vigencia_desde, origen_carga, archivo_importacion, usuario_carga_id, tabla_estado_registro_id)
               VALUES (?, ?, ?, ?, " . emp_moneda_base_id($conexion, $empresa_id) . ", ?, 'IMPORTACION', ?, ?, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    $i_pp_id = 0; $i_precio = 0.0; $i_desde = ''; $i_archivo = ''; $i_usuario = 0; $i_estado = $estado_inicial;
    mysqli_stmt_bind_param($stmt_ins, "iiidssii", $i_pp_id, $empresa_id, $entidad_id, $i_precio, $i_desde, $i_archivo, $i_usuario, $i_estado);

    $procesados = 0; $sin_cambios = 0; $errores_count = 0; $no_vinculados = []; $codigos_multiples = 0;

    mysqli_begin_transaction($conexion);
    try {
        foreach ($filas as $codigo => $info) {
            $productoProveedorIds = $ppPorCodigo[normalizarCodigoProveedor($codigo)] ?? [];

            if (empty($productoProveedorIds)) {
                $no_vinculados[] = ['codigo_proveedor' => $codigo, 'descripcion' => $info['descripcion'], 'precio' => $info['precio']];
                continue;
            }
            if (count($productoProveedorIds) > 1) {
                $codigos_multiples++;
            }

            // procesados / sin_cambios cuentan por PRODUCTO vinculado, no por
            // fila del Excel: un código con 2 productos suma 2.
            foreach ($productoProveedorIds as $producto_proveedor_id) {
                $registro_actual = $actualPorId[$producto_proveedor_id] ?? null;

                if ($registro_actual && abs((float)$registro_actual['precio_lista'] - $info['precio']) < 0.99) {
                    $sin_cambios++;
                    continue;
                }

                try {
                    if ($registro_actual) {
                        $h_pp_id = $producto_proveedor_id;
                        $h_precio = $registro_actual['precio_lista'];
                        $h_moneda = $registro_actual['moneda_id'];
                        $h_desde = $registro_actual['f_vigencia_desde'];
                        // f_vigencia_hasta = el día anterior a la nueva vigencia. Si la
                        // nueva fecha es igual o anterior a la vigencia que se está
                        // cerrando (ej. dos cargas el mismo día), evitamos un rango
                        // invertido: en ese caso, hasta = desde (mismo día).
                        $h_hasta = date('Y-m-d', strtotime($info['f_vigencia_desde'] . ' -1 day'));
                        if ($h_hasta < $h_desde) {
                            $h_hasta = $h_desde;
                        }
                        $h_origen = $registro_actual['origen_carga'];
                        $h_archivo = $registro_actual['archivo_importacion'];
                        $h_usuario = $registro_actual['usuario_carga_id'];
                        if (!mysqli_stmt_execute($stmt_hist)) {
                            throw new Exception('Error guardando historial: ' . mysqli_stmt_error($stmt_hist));
                        }

                        $u_precio = $info['precio']; $u_desde = $info['f_vigencia_desde']; $u_archivo = $archivo_nombre;
                        $u_usuario = $usuario_id; $u_pp_id = $producto_proveedor_id;
                        if (!mysqli_stmt_execute($stmt_upd)) {
                            throw new Exception('Error actualizando precio: ' . mysqli_stmt_error($stmt_upd));
                        }
                    } else {
                        $i_pp_id = $producto_proveedor_id; $i_precio = $info['precio'];
                        $i_desde = $info['f_vigencia_desde']; $i_archivo = $archivo_nombre; $i_usuario = $usuario_id;
                        if (!mysqli_stmt_execute($stmt_ins)) {
                            throw new Exception('Error insertando precio: ' . mysqli_stmt_error($stmt_ins));
                        }
                    }
                    $procesados++;
                } catch (Throwable $e) {
                    $errores_count++;
                    error_log("Error importando código $codigo (producto_proveedor_id $producto_proveedor_id): " . $e->getMessage());
                    continue;
                }
            }
        }

        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("ERROR GENERAL en importarListaProveedorDesdeExcel: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }

    mysqli_stmt_close($stmt_hist);
    mysqli_stmt_close($stmt_upd);
    mysqli_stmt_close($stmt_ins);

    return [
        'success' => true,
        'procesados' => $procesados,
        'sin_cambios' => $sin_cambios,
        'errores_count' => $errores_count,
        'codigos_multiples' => $codigos_multiples,
        'no_vinculados' => $no_vinculados
    ];
}

/* =========================================================================
 * Control de vínculos producto↔proveedor contra la lista de precios.
 *
 * Trae TODOS los vínculos activos de gestion__productos_proveedores de este
 * proveedor (productos activos), con el precio cargado si lo hay. Lo usan:
 *   - la solapa "Control" (obtenerControlVinculadosSinPrecio): vinculados
 *     que no tienen fila en gestion__productos_proveedores_precios.
 *   - el control post-importación (controlarVinculadosFueraDeImportacion):
 *     vinculados cuyo codigo_proveedor no vino en el Excel — el caso típico
 *     es que el proveedor le cambió el código al producto.
 * ========================================================================= */
function obtenerVinculosActivosProveedor($conexion, $empresa_id, $entidad_id)
{
    $sql = "SELECT pp.producto_proveedor_id, pp.producto_id, pp.codigo_proveedor,
                   p.producto_codigo, p.producto_nombre,
                   ppp.producto_proveedor_precio_id, ppp.precio_lista, ppp.f_vigencia_desde,
                   ppp.archivo_importacion
            FROM gestion__productos_proveedores pp
            INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id AND p.empresa_id = pp.empresa_id
            LEFT JOIN gestion__productos_proveedores_precios ppp ON ppp.producto_proveedor_id = pp.producto_proveedor_id
            WHERE pp.empresa_id = ? AND pp.entidad_id = ?
              AND pp.tabla_estado_registro_id = 1
              AND p.tabla_estado_registro_id = 1
            ORDER BY p.producto_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerVinculosActivosProveedor: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $vinculos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $vinculos[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $vinculos;
}

// Misma comparación que hace MySQL en el IN del import (collation
// case-insensitive, sin espacios de más), pero del lado de PHP.
function normalizarCodigoProveedor($codigo)
{
    return mb_strtoupper(trim((string)$codigo));
}

function obtenerControlVinculadosSinPrecio($conexion, $empresa_id, $entidad_id)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    if (empty($entidad_id)) {
        return ['success' => false, 'message' => 'Debe indicar el proveedor', 'data' => []];
    }

    $sinPrecio = array_values(array_filter(
        obtenerVinculosActivosProveedor($conexion, $empresa_id, $entidad_id),
        function ($v) { return $v['producto_proveedor_precio_id'] === null; }
    ));

    return ['success' => true, 'data' => $sinPrecio, 'message' => ''];
}

/*
 * $codigos = TODOS los codigo_proveedor del Excel (no un lote): el front lo
 * llama una sola vez al terminar de procesar todos los lotes, porque un lote
 * suelto no alcanza para saber qué códigos faltan en el archivo completo.
 */
function controlarVinculadosFueraDeImportacion($conexion, $empresa_id, $entidad_id, $codigos)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    if (empty($entidad_id)) {
        return ['success' => false, 'message' => 'Debe indicar el proveedor', 'data' => []];
    }

    $enArchivo = [];
    foreach ((array)$codigos as $codigo) {
        $enArchivo[normalizarCodigoProveedor($codigo)] = true;
    }

    $faltantes = [];
    foreach (obtenerVinculosActivosProveedor($conexion, $empresa_id, $entidad_id) as $v) {
        $codigo = normalizarCodigoProveedor($v['codigo_proveedor']);
        if ($codigo === '' || !isset($enArchivo[$codigo])) {
            $faltantes[] = $v;
        }
    }

    error_log("=== controlarVinculadosFueraDeImportacion entidad $entidad_id: " . count($enArchivo) . " códigos en archivo, " . count($faltantes) . " vinculados fuera del archivo ===");

    return ['success' => true, 'data' => $faltantes, 'message' => ''];
}

/* =========================================================================
 * Botón "Actualizar costo del producto" (alcance confirmado: SOLO toca
 * gestion__productos_costos, NO recalcula gestion__listas_precios_productos
 * — ese recálculo queda pendiente de definir el motor de reglas, ver
 * gestion__listas_precios_reglas / especificacion_costos_v1.1.docx).
 *
 * producto_costo_origen_id = 3 → LISTA_PROVEEDOR, confirmado contra
 * gestion__productos_costos_origenes.
 * ========================================================================= */
/* =========================================================================
 * Botón "Actualizar costo del producto" — UPSERT en gestion__productos_costos
 * MÁS el registro correspondiente en gestion__productos_costos_historial,
 * en la misma transacción. El historial es una tabla temporal como la de
 * precios de proveedor: cada fila guarda costo_anterior -> costo_nuevo con
 * su propia vigencia (f_desde/f_hasta), así que antes de insertar la nueva
 * fila hay que cerrar la anterior (la que tenga f_hasta IS NULL para este
 * producto+empresa).
 * ========================================================================= */

/* =========================================================================
 * Botón "% de cambio" — ajusta por un porcentaje manual (positivo o
 * negativo) el precio_lista DEL PROVEEDOR (no el costo del producto) de
 * cada fila filtrada en pantalla ahora mismo. Reusa obtenerPreciosProveedor()
 * para traer exactamente lo que ve el usuario. El costo del producto
 * (gestion__productos_costos) NO se toca acá — eso lo deciden los otros
 * botones (individuales o masivos) después, mirando el costo_neto_compra
 * ya recalculado con el precio_lista nuevo.
 * ========================================================================= */
function aplicarPorcentajeListaFiltrados($conexion, $empresa_id, $entidad_id, $pagina_id, $filtros, $porcentaje, $usuario_id)
{
    $porcentaje = floatval($porcentaje);
    $entidad_id = intval($entidad_id);

    if (empty($entidad_id)) {
        return ['success' => false, 'message' => 'Debe elegir un proveedor.'];
    }

    $resultado = obtenerPreciosProveedor($conexion, $empresa_id, $entidad_id, $pagina_id, $filtros);
    $filas = $resultado['filas'] ?? [];

    if (empty($filas)) {
        return ['success' => true, 'actualizados' => 0, 'sin_cambios' => 0, 'errores' => 0, 'message' => 'No hay productos filtrados para ajustar.'];
    }

    $actualizados = 0;
    $sin_cambios = 0;
    $errores = 0;
    foreach ($filas as $fila) {
        $nuevo_precio_lista = round((float)$fila['precio_lista'] * (1 + ($porcentaje / 100)), 4);
        $res = actualizarPrecioListaProveedor($conexion, $fila['producto_proveedor_id'], $nuevo_precio_lista, $fila['moneda_id'], $usuario_id);
        if ($res['success']) {
            if (!empty($res['actualizado'])) { $actualizados++; } else { $sin_cambios++; }
        } else {
            $errores++;
            error_log("aplicarPorcentajeListaFiltrados: falló producto_proveedor_id={$fila['producto_proveedor_id']}: " . ($res['message'] ?? ''));
        }
    }

    return [
        'success' => true,
        'actualizados' => $actualizados,
        'sin_cambios' => $sin_cambios,
        'errores' => $errores,
        'message' => "Se ajustó el precio de lista de $actualizados producto(s) en " . ($porcentaje >= 0 ? '+' : '') . "$porcentaje%, $sin_cambios sin cambios" . ($errores ? ", $errores con error (ver log)" : '') . '.'
    ];
}

/* =========================================================================
 * Botón masivo "Actualizar Costos" (solo) — igual que actualizarCostosMasivoProveedor
 * pero sin tocar listas de precio, para cuando solo hace falta sincronizar
 * el costo (por ejemplo, después de usar "% de cambio" sobre el precio de
 * lista, y decidir que el costo debe seguirlo pero no recalcular ventas todavía).
 * ========================================================================= */
function actualizarSoloCostosMasivoProveedor($conexion, $empresa_id, $entidad_id, $usuario_id)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $descuento_general = obtenerDescuentoVigenteProveedor($conexion, $entidad_id);

    $sql = "SELECT ppp.precio_lista, ppp.moneda_id, pp.producto_id
            FROM gestion__productos_proveedores_precios ppp
            INNER JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = ppp.producto_proveedor_id
            WHERE ppp.empresa_id = ? AND ppp.entidad_id = ? AND ppp.tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando actualizarSoloCostosMasivoProveedor: " . mysqli_error($conexion));
        return ['success' => false, 'message' => 'Error de base de datos'];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($row = mysqli_fetch_assoc($result)) { $filas[] = $row; }
    mysqli_stmt_close($stmt);

    if (empty($filas)) {
        return ['success' => true, 'actualizados' => 0, 'sin_cambios' => 0, 'errores' => 0, 'message' => 'Este proveedor no tiene precios cargados.'];
    }

    $productoIds = array_values(array_unique(array_map(function ($f) { return (int)$f['producto_id']; }, $filas)));
    $costosActuales = obtenerCostosActualesPorProductos($conexion, $empresa_id, $productoIds);

    $actualizados = 0;
    $sin_cambios = 0;
    $errores = 0;
    foreach ($filas as $fila) {
        $producto_id = (int)$fila['producto_id'];
        $costo_neto = round((float)$fila['precio_lista'] * (1 - ($descuento_general / 100)), 4);
        $costo_actual = $costosActuales[$producto_id] ?? null;

        if ($costo_actual !== null && abs($costo_actual - $costo_neto) < 0.99) {
            $sin_cambios++;
            continue;
        }

        $res = actualizarCostoDesdeListaProveedor($conexion, $producto_id, $empresa_id, $entidad_id, $costo_neto, $fila['moneda_id'], $usuario_id);
        if ($res['success']) {
            $actualizados++;
        } else {
            $errores++;
            error_log("actualizarSoloCostosMasivoProveedor: falló producto_id={$producto_id}: " . ($res['message'] ?? ''));
        }
    }

    return [
        'success' => true,
        'actualizados' => $actualizados,
        'sin_cambios' => $sin_cambios,
        'errores' => $errores,
        'message' => "Se actualizó el costo de $actualizados producto(s), $sin_cambios sin cambios" . ($errores ? ", $errores con error (ver log)" : '') . '.'
    ];
}

/* =========================================================================
 * Botón masivo "Actualizar Precios de Venta" (solo) — recorre TODOS los
 * productos activos del proveedor y recalcula sus listas de precio con el
 * costo YA REGISTRADO (mismo criterio que actualizarSoloPreciosVenta por
 * fila), sin tocar gestion__productos_costos.
 * ========================================================================= */
function actualizarSoloPreciosVentaMasivoProveedor($conexion, $empresa_id, $entidad_id, $pagina_id, $usuario_id)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $descuento_general = obtenerDescuentoVigenteProveedor($conexion, $entidad_id);

    $sql = "SELECT ppp.precio_lista, pp.producto_id
            FROM gestion__productos_proveedores_precios ppp
            INNER JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = ppp.producto_proveedor_id
            WHERE ppp.empresa_id = ? AND ppp.entidad_id = ? AND ppp.tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando actualizarSoloPreciosVentaMasivoProveedor: " . mysqli_error($conexion));
        return ['success' => false, 'message' => 'Error de base de datos'];
    }
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($row = mysqli_fetch_assoc($result)) { $filas[] = $row; }
    mysqli_stmt_close($stmt);

    if (empty($filas)) {
        return ['success' => true, 'actualizados' => 0, 'precios_sin_regla' => 0, 'errores' => 0, 'message' => 'Este proveedor no tiene precios cargados.'];
    }

    $productoIds = array_values(array_unique(array_map(function ($f) { return (int)$f['producto_id']; }, $filas)));
    $categoriasPorProducto = obtenerCategoriasPorProductos($conexion, $productoIds);
    $contexto = obtenerContextoCalculoPrecios($conexion, $empresa_id, $entidad_id);

    $actualizados = 0;
    $precios_sin_regla_total = 0;
    $errores = 0;
    foreach ($filas as $fila) {
        $producto_id = (int)$fila['producto_id'];
        $categoria_id = $categoriasPorProducto[$producto_id] ?? 0;
        // costo_neto_compra propuesto por esta lista de proveedor — solo se
        // usa como fallback si el producto todavía no tiene costo registrado
        // (ver actualizarSoloPreciosVenta).
        $costo_neto_fallback = round((float)$fila['precio_lista'] * (1 - ($descuento_general / 100)), 4);

        $res = actualizarSoloPreciosVenta($conexion, $producto_id, $categoria_id, $empresa_id, $entidad_id, $costo_neto_fallback, $usuario_id, $contexto);
        if ($res['success']) {
            $actualizados += $res['precios_actualizados'];
            $precios_sin_regla_total += $res['precios_sin_regla'];
        } else {
            $errores++;
            error_log("actualizarSoloPreciosVentaMasivoProveedor: falló producto_id={$producto_id}: " . ($res['message'] ?? ''));
        }
    }

    return [
        'success' => true,
        'actualizados' => $actualizados,
        'precios_sin_regla' => $precios_sin_regla_total,
        'errores' => $errores,
        'message' => "Se actualizaron $actualizados precio(s) de venta" . ($precios_sin_regla_total ? ", $precios_sin_regla_total sin regla aplicable" : '') . ($errores ? ", $errores producto(s) con error (ver log)" : '') . '.'
    ];
}

/* =========================================================================
 * Botón "Actualizar costos de todos los productos de este proveedor" —
 * recorre TODOS los productos_proveedores_precios activos del proveedor,
 * recalcula costo_neto_compra con el descuento vigente y aplica
 * actualizarCostoDesdeListaProveedor() producto por producto (cada uno ya
 * es transaccional con su propio historial). No se hace una única
 * transacción gigante para todo el lote: un producto con un problema
 * puntual no debe frenar ni revertir el resto — se cuenta como error y se
 * sigue con los demás.
 * ========================================================================= */
function actualizarCostosMasivoProveedor($conexion, $empresa_id, $entidad_id, $usuario_id)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);

    $descuento_general = obtenerDescuentoVigenteProveedor($conexion, $entidad_id);

    $sql = "SELECT ppp.precio_lista, ppp.moneda_id, pp.producto_id
            FROM gestion__productos_proveedores_precios ppp
            INNER JOIN gestion__productos_proveedores pp ON pp.producto_proveedor_id = ppp.producto_proveedor_id
            WHERE ppp.empresa_id = ?
              AND ppp.entidad_id = ?
              AND ppp.tabla_estado_registro_id = 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando actualizarCostosMasivoProveedor: " . mysqli_error($conexion));
        return ['success' => false, 'message' => 'Error de base de datos'];
    }

    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $filas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $filas[] = $row;
    }
    mysqli_stmt_close($stmt);

    if (empty($filas)) {
        return ['success' => true, 'actualizados' => 0, 'sin_cambios' => 0, 'errores' => 0, 'message' => 'Este proveedor no tiene precios cargados.'];
    }

    // Precarga de categorías (para el matching de reglas) y el contexto de
    // cálculo (listas activas + reglas vigentes), UNA sola vez para todo el
    // lote — evita repetir esas consultas producto por producto. Antes acá
    // también se precargaban los costos actuales para SALTEAR por completo
    // (sin llamar a aplicarActualizacionCostoYPrecios) los productos cuyo
    // costo no cambiaba — pero eso también se saltaba la revisión de listas
    // de precio (el ajuste de la lista 3 "Mostrador"), que tiene que correr
    // SIEMPRE, cambie o no el costo (mismo criterio que ya aplica el botón
    // individual "Ambos", ver el comentario en aplicarActualizacionCostoYPrecios).
    // El filtro de "no reescribir si no cambió" ya lo hace por su cuenta
    // actualizarCostoDesdeListaProveedor, así que este filtro acá era
    // redundante Y estaba bloqueando Mostrador de más.
    $productoIds = array_values(array_unique(array_map(function ($f) { return (int)$f['producto_id']; }, $filas)));
    $categoriasPorProducto = obtenerCategoriasPorProductos($conexion, $productoIds);
    $contexto = obtenerContextoCalculoPrecios($conexion, $empresa_id, $entidad_id);

    $actualizados = 0;
    $sin_cambios = 0;
    $errores = 0;
    $precios_actualizados_total = 0;
    foreach ($filas as $fila) {
        $producto_id = (int)$fila['producto_id'];
        $costo_neto = round((float)$fila['precio_lista'] * (1 - ($descuento_general / 100)), 4);
        $categoria_id = $categoriasPorProducto[$producto_id] ?? 0;

        $res = aplicarActualizacionCostoYPrecios($conexion, $producto_id, $categoria_id, $empresa_id, $entidad_id, $costo_neto, $fila['moneda_id'], $usuario_id, $contexto);
        if ($res['success']) {
            if (!empty($res['costo_actualizado'])) {
                $actualizados++;
            } else {
                $sin_cambios++;
            }
            $precios_actualizados_total += $res['precios_actualizados'];
        } else {
            $errores++;
            error_log("actualizarCostosMasivoProveedor: falló producto_id={$producto_id}: " . ($res['message'] ?? ''));
        }
    }

    return [
        'success' => true,
        'actualizados' => $actualizados,
        'sin_cambios' => $sin_cambios,
        'errores' => $errores,
        'precios_actualizados' => $precios_actualizados_total,
        'message' => "Se actualizaron $actualizados costo(s) y $precios_actualizados_total precio(s) de venta, $sin_cambios sin cambios" . ($errores ? ", $errores con error (ver log)" : '') . '.'
    ];
}

function actualizarCostoDesdeListaProveedor($conexion, $producto_id, $empresa_id, $entidad_id, $costo_neto_compra, $moneda_id, $usuario_id)
{
    $producto_id = intval($producto_id);
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $moneda_id = intval($moneda_id) ?: 1;
    $usuario_id = intval($usuario_id) ?: null;

    if ($costo_neto_compra < 0) {
        return ['success' => false, 'message' => 'El costo calculado es inválido.'];
    }

    $fecha_hoy = date('Y-m-d');

    // Costo actual (si existe): se consulta ANTES de abrir transacción para
    // poder saltear temprano si no cambió nada — ni togar gestion__productos_costos
    // ni su historial. "Solo aplica a los que tienen cambios": esto es lo que
    // lo garantiza tanto para el botón individual como para el masivo (que
    // además pre-filtra antes de llegar acá, pero este chequeo es el que
    // manda en los dos casos).
    $sql_actual = "SELECT producto_costo_id, costo_actual FROM gestion__productos_costos WHERE empresa_id = ? AND producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $costo_anterior = $actual ? (float)$actual['costo_actual'] : null;

    if ($costo_anterior !== null && abs($costo_anterior - $costo_neto_compra) < 0.99) {
        return ['success' => true, 'actualizado' => false, 'message' => 'El costo ya estaba actualizado.', 'producto_costo_id' => (int)$actual['producto_costo_id'], 'costo_anterior' => $costo_anterior];
    }

    mysqli_begin_transaction($conexion);
    try {
        // Cerrar la fila de historial abierta (f_hasta IS NULL) de este
        // producto, si hay una. Mismo resguardo que en precios de proveedor:
        // si dos cambios caen el mismo día, f_hasta = f_desde en vez de un
        // rango invertido.
        $sql_ultima = "SELECT producto_costo_historial_id, f_desde
                       FROM gestion__productos_costos_historial
                       WHERE empresa_id = ? AND producto_id = ? AND f_hasta IS NULL
                       ORDER BY f_desde DESC, producto_costo_historial_id DESC
                       LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql_ultima);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $ultima = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($ultima) {
            $f_hasta_cierre = date('Y-m-d', strtotime($fecha_hoy . ' -1 day'));
            if ($f_hasta_cierre < $ultima['f_desde']) {
                $f_hasta_cierre = $ultima['f_desde'];
            }
            $sql_cerrar = "UPDATE gestion__productos_costos_historial SET f_hasta = ? WHERE producto_costo_historial_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_cerrar);
            $id_ultima = (int)$ultima['producto_costo_historial_id'];
            mysqli_stmt_bind_param($stmt, "si", $f_hasta_cierre, $id_ultima);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error cerrando historial de costo anterior: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }

        // Nueva fila de historial: costo_anterior -> costo_nuevo, abierta
        // (f_hasta NULL) desde hoy.
        $sql_hist = "INSERT INTO gestion__productos_costos_historial
                    (empresa_id, producto_id, entidad_id, costo_anterior, costo_nuevo, moneda_id,
                     producto_costo_origen_id, comprobante_id, f_desde, f_hasta, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, 3, NULL, ?, NULL, ?)";
        $stmt = mysqli_prepare($conexion, $sql_hist);
        mysqli_stmt_bind_param($stmt, "iiiddisi", $empresa_id, $producto_id, $entidad_id, $costo_anterior, $costo_neto_compra, $moneda_id, $fecha_hoy, $usuario_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error guardando historial de costo: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // UPSERT del costo actual (igual que antes).
        $sql = "INSERT INTO gestion__productos_costos
                    (empresa_id, producto_id, entidad_id, costo_actual, moneda_id,
                     producto_costo_origen_id, comprobante_id, f_actualizacion)
                VALUES (?, ?, ?, ?, ?, 3, NULL, CURDATE())
                ON DUPLICATE KEY UPDATE
                    entidad_id = VALUES(entidad_id),
                    costo_actual = VALUES(costo_actual),
                    moneda_id = VALUES(moneda_id),
                    producto_costo_origen_id = 3,
                    comprobante_id = NULL,
                    f_actualizacion = CURDATE()";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando actualización de costo: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, "iiidi", $empresa_id, $producto_id, $entidad_id, $costo_neto_compra, $moneda_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error actualizando el costo: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // producto_costo_id resultante, para vincularlo desde
        // gestion__listas_precios_productos.producto_costo_id cuando el
        // orquestador también actualiza la lista de precios.
        $sql_id = "SELECT producto_costo_id FROM gestion__productos_costos WHERE empresa_id = ? AND producto_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_id);
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $fila_id = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $producto_costo_id = $fila_id ? (int)$fila_id['producto_costo_id'] : null;

        mysqli_commit($conexion);
        return ['success' => true, 'actualizado' => true, 'message' => 'Costo del producto actualizado.', 'producto_costo_id' => $producto_costo_id, 'costo_anterior' => $costo_anterior];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en actualizarCostoDesdeListaProveedor: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Botón "Actualizar precio de venta" (solo) — para cuando lo que cambió es
 * la REGLA de la lista de precios, no el costo del proveedor. Recalcula
 * usando el costo YA registrado (gestion__productos_costos.costo_actual);
 * si el producto todavía no tiene costo registrado, usa costo_neto_compra
 * (el propuesto por esta lista de proveedor) como mejor base disponible.
 * NO toca gestion__productos_costos ni su historial.
 * ========================================================================= */
function actualizarSoloPreciosVenta($conexion, $producto_id, $categoria_id, $empresa_id, $entidad_id, $costo_neto_compra, $usuario_id, $contexto)
{
    $sql = "SELECT producto_costo_id, costo_actual FROM gestion__productos_costos WHERE empresa_id = ? AND producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $producto_costo_id = $actual ? (int)$actual['producto_costo_id'] : null;
    $costo_actual_registrado = $actual ? (float)$actual['costo_actual'] : null;
    $costo_base = $costo_actual_registrado ?? $costo_neto_compra;

    // Este botón no toca el costo, pero igual pasa costo_actual_registrado
    // vs. costo_neto_compra (el propuesto por la lista de este proveedor)
    // para que la lista 3 ("Mostrador") pueda ajustarse proporcionalmente
    // si ya tenía un desvío manual — ver el comentario en
    // actualizarListasPreciosPorRegla.
    $res = actualizarListasPreciosPorRegla($conexion, $producto_id, $categoria_id, $entidad_id, $costo_base, $producto_costo_id, $empresa_id, $usuario_id, $contexto, $costo_actual_registrado, $costo_neto_compra);

    return [
        'success' => true,
        'message' => 'Lista(s) de precios actualizadas.',
        'precios_actualizados' => $res['precios_actualizados'],
        'precios_sin_regla' => $res['precios_sin_regla']
    ];
}

function aplicarActualizacionCostoYPrecios($conexion, $producto_id, $categoria_id, $empresa_id, $entidad_id, $costo_neto_compra, $moneda_id, $usuario_id, $contexto)
{
    $resCosto = actualizarCostoDesdeListaProveedor($conexion, $producto_id, $empresa_id, $entidad_id, $costo_neto_compra, $moneda_id, $usuario_id);
    if (!$resCosto['success']) {
        return ['success' => false, 'message' => $resCosto['message'], 'precios_actualizados' => 0, 'precios_sin_regla' => 0];
    }

    // "Ambos" SIEMPRE revisa las listas de precio, aunque el costo no haya
    // cambiado (actualizarCostoDesdeListaProveedor ya se encarga de no
    // reescribir el costo si es igual — eso está bien). La regla de la
    // lista puede haber cambiado sola: si "Ambos" se cortara acá cuando el
    // costo no cambia, el botón "Ambos" terminaría dependiendo de probar
    // primero el botón de costo por separado para "activarse", que es
    // justo el bug que estaba pasando.

    // costo_anterior (registrado ANTES de este write, ver
    // actualizarCostoDesdeListaProveedor) vs. costo_neto_compra: el % de
    // cambio se calcula dentro de actualizarListasPreciosPorRegla, para el
    // ajuste hardcodeado de lista_precio_id=3 ("Mostrador").
    $res = actualizarListasPreciosPorRegla($conexion, $producto_id, $categoria_id, $entidad_id, $costo_neto_compra, $resCosto['producto_costo_id'], $empresa_id, $usuario_id, $contexto, $resCosto['costo_anterior'] ?? null, $costo_neto_compra);

    $mensaje = !empty($resCosto['actualizado'])
        ? 'Costo y lista(s) de precios actualizados.'
        : 'El costo ya estaba al día; lista(s) de precios revisadas igual.';

    return [
        'success' => true,
        'message' => $mensaje,
        'costo_actualizado' => !empty($resCosto['actualizado']),
        'precios_actualizados' => $res['precios_actualizados'],
        'precios_sin_regla' => $res['precios_sin_regla']
    ];
}

/* =========================================================================
 * Botón "Armar lista desde precios": para los productos vinculados a este
 * proveedor que NO tienen precio en su lista (los mismos de la solapa
 * Control), propone un precio de lista a partir de lo que ya está cargado:
 *   - $base = id de una lista de venta: costo = precio de venta deshecho con
 *     la regla vigente de esa lista (calcularCostoDesdePrecioVenta, sigue la
 *     cadena OTRA_LISTA hasta la de origen COSTO).
 *   - $base = 'COSTO': el costo registrado en gestion__productos_costos.
 * Precio de lista = costo / (1 - descuento vigente del proveedor): la
 * inversa de costo_neto_compra, así la grilla vuelve a mostrar ese costo.
 * Solo calcula (vista previa); armarListaProveedorDesdePrecios() graba.
 * ========================================================================= */
function calcularArmadoListaProveedor($conexion, $empresa_id, $entidad_id, $base)
{
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    if (empty($entidad_id)) {
        return ['success' => false, 'message' => 'Debe elegir un proveedor.', 'data' => null];
    }

    $descuento = obtenerDescuentoVigenteProveedor($conexion, $entidad_id);
    if ($descuento >= 100) {
        return ['success' => false, 'message' => 'El descuento de compra vigente del proveedor es del 100% o más: no se puede deducir un precio de lista.', 'data' => null];
    }

    $listasPorId = [];
    foreach (obtenerListasPreciosActivas($conexion, $empresa_id) as $lp) {
        $listasPorId[$lp['lista_precio_id']] = $lp;
    }
    $usarCosto = ($base === 'COSTO');
    $lista_id = $usarCosto ? 0 : intval($base);
    if (!$usarCosto && !isset($listasPorId[$lista_id])) {
        return ['success' => false, 'message' => 'Elegí una lista de precios activa o el costo registrado.', 'data' => null];
    }
    if (!$usarCosto && $listasPorId[$lista_id]['lista_precio_origen_id'] === 3) {
        return ['success' => false, 'message' => 'La lista "' . $listasPorId[$lista_id]['lista_precio_nombre'] . '" es de precio manual: no tiene regla para deducir el costo.', 'data' => null];
    }

    $vinculos = array_values(array_filter(
        obtenerVinculosActivosProveedor($conexion, $empresa_id, $entidad_id),
        function ($v) { return $v['producto_proveedor_precio_id'] === null; }
    ));

    $resumen = ['filas' => [], 'descuento_pct' => $descuento, 'total' => count($vinculos), 'calculables' => 0];
    if (empty($vinculos)) {
        return ['success' => true, 'message' => 'Todos los productos vinculados a este proveedor ya tienen precio en su lista.', 'data' => $resumen];
    }

    // Precargas para todo el lote (nada dentro del foreach consulta la base).
    $productoIds = array_values(array_unique(array_map(function ($v) { return (int)$v['producto_id']; }, $vinculos)));
    if ($usarCosto) {
        $costos = obtenerCostosActualesPorProductos($conexion, $empresa_id, $productoIds);
    } else {
        $preciosVenta = obtenerPreciosVentaActualesPorProductoYLista($conexion, $empresa_id, $productoIds, [$lista_id]);
        $reglasPorLista = obtenerReglasVigentesPorListas($conexion, $empresa_id, $entidad_id, array_keys($listasPorId));
        $categorias = obtenerCategoriasPorProductos($conexion, $productoIds);
    }

    foreach ($vinculos as $v) {
        $producto_id = (int)$v['producto_id'];
        $precio_base = null;
        $costo = null;
        $motivo = '';

        if ($usarCosto) {
            $precio_base = $costos[$producto_id] ?? null;
            $costo = $precio_base;
            if ($precio_base === null) $motivo = 'Sin costo registrado';
        } else {
            $precio_base = $preciosVenta[$producto_id][$lista_id] ?? null;
            if ($precio_base === null) {
                $motivo = 'Sin precio en la lista';
            } else {
                $costo = calcularCostoDesdePrecioVenta($listasPorId, $reglasPorLista, $lista_id, $precio_base, $producto_id, $categorias[$producto_id] ?? 0, $entidad_id);
                if ($costo === null) $motivo = 'La lista no tiene regla aplicable';
            }
        }
        if ($costo !== null && $costo <= 0) {
            $costo = null;
            $motivo = 'Precio o costo en cero';
        }

        $precio_lista = $costo !== null ? round($costo / (1 - ($descuento / 100)), 2) : null;
        if ($precio_lista !== null) $resumen['calculables']++;

        $resumen['filas'][] = [
            'producto_proveedor_id' => (int)$v['producto_proveedor_id'],
            'producto_id' => $producto_id,
            'codigo_proveedor' => $v['codigo_proveedor'],
            'producto_codigo' => $v['producto_codigo'],
            'producto_nombre' => $v['producto_nombre'],
            'precio_base' => $precio_base,
            'costo_estimado' => $costo !== null ? round($costo, 4) : null,
            'precio_lista_propuesto' => $precio_lista,
            'motivo' => $motivo
        ];
    }

    return ['success' => true, 'message' => '', 'data' => $resumen];
}

/* =========================================================================
 * Graba lo que calcula calcularArmadoListaProveedor (se recalcula acá, no se
 * confía en los importes que muestra el front): una fila nueva en
 * gestion__productos_proveedores_precios por cada vínculo sin precio que
 * tenga base, con origen_carga = ESTIMADO para distinguirlas de una lista
 * real del proveedor (una importación posterior las pisa como cualquier
 * otro precio). No toca costos ni listas de venta. $producto_proveedor_ids
 * opcional: limita a esos vínculos (los tildados en la vista previa).
 * Una sola transacción: son solo INSERT.
 * ========================================================================= */
function armarListaProveedorDesdePrecios($conexion, $empresa_id, $entidad_id, $base, $usuario_id, $pagina_id, $producto_proveedor_ids = null)
{
    error_log("=== INICIO armarListaProveedorDesdePrecios entidad: $entidad_id base: $base ===");
    $calculo = calcularArmadoListaProveedor($conexion, $empresa_id, $entidad_id, $base);
    if (!$calculo['success']) {
        return $calculo;
    }

    $seleccion = null;
    if (is_array($producto_proveedor_ids)) {
        $seleccion = array_flip(array_map('intval', $producto_proveedor_ids));
    }

    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $estado_inicial = intval(obtenerEstadoInicialPagina($conexion, $pagina_id)) ?: 1;
    $origen = preciosOrigenCargaValido($conexion, 'ESTIMADO');

    $insertados = 0;
    $ya_tenian = 0;
    $sin_base = 0;
    mysqli_begin_transaction($conexion);
    try {
        foreach ($calculo['data']['filas'] as $fila) {
            if ($seleccion !== null && !isset($seleccion[$fila['producto_proveedor_id']])) continue;
            if ($fila['precio_lista_propuesto'] === null) {
                $sin_base++;
                continue;
            }
            $ok = insertarPrecioListaProveedor($conexion, $fila['producto_proveedor_id'], $empresa_id, $entidad_id,
                $fila['precio_lista_propuesto'], 1, $origen, $usuario_id, $estado_inicial);
            if ($ok) { $insertados++; } else { $ya_tenian++; }
        }
        mysqli_commit($conexion);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en armarListaProveedorDesdePrecios: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
    }

    $mensaje = "Se cargaron $insertados precio(s) en la lista del proveedor"
        . ($sin_base ? ", $sin_base sin base para calcular" : '')
        . ($ya_tenian ? ", $ya_tenian ya tenían precio" : '') . '.';
    return ['success' => true, 'message' => $mensaje,
            'data' => ['insertados' => $insertados, 'sin_base' => $sin_base, 'ya_tenian' => $ya_tenian]];
}
