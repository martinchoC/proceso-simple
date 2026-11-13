<?php
require_once __DIR__ . '/../../core/productos_proveedores.php';
/* =========================================================================
 * Motor de precios de compra/venta compartido.
 *
 * Extraído de proveedor_precios_model.php para poder usarlo también desde
 * facturas_proveedores_model.php (actualización de la lista del proveedor y
 * de las listas de venta al confirmar una compra, según Parámetros de
 * Empresa) sin incluir un _model.php de otra página: los dos definen
 * obtenerFuncionesPagina / obtenerProveedores / obtenerMonedas, etc.
 *
 * Contiene: descuento vigente del proveedor, costos actuales, listas de
 * precios activas y sus reglas, cálculo del "debería ser" por lista, alta
 * del vínculo producto-proveedor, actualización de la lista del proveedor y
 * escritura de los precios de venta. Cada escritura abre su propia
 * transacción: NO llamarlas dentro de otra transacción abierta (un
 * START TRANSACTION anidado commitea la de afuera).
 * ========================================================================= */

/* =========================================================================
 * Condición de compra vigente del proveedor (descuento general).
 * Confirmado por Pablo: un proveedor tiene una sola condición activa, así
 * que no hace falta resolver prioridad por categoría — se trae la vigente
 * por fecha y listo. Se llama UNA sola vez por listado (no por fila).
 * ========================================================================= */
function obtenerDescuentoVigenteProveedor($conexion, $entidad_id)
{
    $entidad_id = intval($entidad_id);

    $sql = "SELECT proveedor_descuento_general
            FROM gestion__entidades_condiciones_proveedores
            WHERE entidad_id = ?
              AND tabla_estado_registro_id = 1
              AND f_desde <= CURDATE()
              AND (f_hasta IS NULL OR f_hasta >= CURDATE())
            ORDER BY f_desde DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerDescuentoVigenteProveedor: " . mysqli_error($conexion));
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $fila ? (float)$fila['proveedor_descuento_general'] : 0.0;
}

/* =========================================================================
 * Descuento vigente de VARIOS proveedores en una sola consulta — necesario
 * para la vista sin proveedor elegido (varias filas, cada una con su propio
 * entidad_id). Reusa la misma tabla y el mismo criterio de
 * obtenerDescuentoVigenteProveedor (una condición activa por proveedor).
 * ========================================================================= */
function obtenerDescuentosVigentesPorProveedores($conexion, $entidadIds)
{
    if (empty($entidadIds)) return [];
    $placeholders = implode(',', array_fill(0, count($entidadIds), '?'));
    $tipos = str_repeat('i', count($entidadIds));

    $sql = "SELECT entidad_id, proveedor_descuento_general
            FROM gestion__entidades_condiciones_proveedores
            WHERE tabla_estado_registro_id = 1
              AND f_desde <= CURDATE()
              AND (f_hasta IS NULL OR f_hasta >= CURDATE())
              AND entidad_id IN ($placeholders)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerDescuentosVigentesPorProveedores: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $tipos, ...$entidadIds);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $descuentos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $descuentos[(int)$row['entidad_id']] = (float)$row['proveedor_descuento_general'];
    }
    mysqli_stmt_close($stmt);
    return $descuentos;
}

/* =========================================================================
 * Último costo registrado por producto (gestion__productos_costos.costo_actual),
 * para comparar contra el costo_neto_compra que propone la lista del
 * proveedor ANTES de tocar el botón "Actualizar costo". Precarga en una
 * sola consulta para todos los producto_id del listado.
 * ========================================================================= */
function obtenerCostosActualesPorProductos($conexion, $empresa_id, $productoIds)
{
    if (empty($productoIds)) return [];
    $empresa_id = intval($empresa_id);

    $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
    $tipos = str_repeat('i', count($productoIds));
    $sql = "SELECT producto_id, costo_actual
            FROM gestion__productos_costos
            WHERE empresa_id = ? AND producto_id IN ($placeholders)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerCostosActualesPorProductos: " . mysqli_error($conexion));
        return [];
    }

    $params = array_merge([$empresa_id], $productoIds);
    mysqli_stmt_bind_param($stmt, "i" . $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $costos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $costos[(int)$row['producto_id']] = (float)$row['costo_actual'];
    }
    mysqli_stmt_close($stmt);
    return $costos;
}

/* =========================================================================
 * Listas de precios activas de la empresa (gestion__listas_precios) — misma
 * consulta que ya usa listas_precios_productos_model.php::obtenerListasPrecios,
 * agregando el filtro por empresa_id.
 * ========================================================================= */
function obtenerListasPreciosActivas($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    // lista_precio_origen_id / lista_base_id: hacen falta para la cadena de
    // cálculo (ver calcularPreciosDeberianSerPorLista) — una lista con
    // origen OTRA_LISTA (2) no parte del costo, parte del precio YA
    // calculado de lista_base_id.
    $sql = "SELECT lista_precio_id, lista_precio_nombre, lista_precio_origen_id, lista_base_id
            FROM gestion__listas_precios
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY lista_precio_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerListasPreciosActivas: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $listas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $listas[] = [
            'lista_precio_id' => (int)$row['lista_precio_id'],
            'lista_precio_nombre' => $row['lista_precio_nombre'],
            'lista_precio_origen_id' => (int)$row['lista_precio_origen_id'],
            'lista_base_id' => $row['lista_base_id'] !== null ? (int)$row['lista_base_id'] : null
        ];
    }
    mysqli_stmt_close($stmt);
    return $listas;
}

/* =========================================================================
 * Precio de venta VIGENTE de cada producto, por cada lista de precios
 * (gestion__listas_precios_productos). Tal cual está cargado hoy — esto es
 * el "precio actual", no el recalculado.
 * ========================================================================= */
function obtenerPreciosVentaActualesPorProductoYLista($conexion, $empresa_id, $productoIds, $listaIds)
{
    if (empty($productoIds) || empty($listaIds)) return [];
    $empresa_id = intval($empresa_id);

    $placeholdersProd = implode(',', array_fill(0, count($productoIds), '?'));
    $placeholdersLista = implode(',', array_fill(0, count($listaIds), '?'));
    $tiposProd = str_repeat('i', count($productoIds));
    $tiposLista = str_repeat('i', count($listaIds));

    $sql = "SELECT producto_id, lista_precio_id, precio_final
            FROM gestion__listas_precios_productos
            WHERE empresa_id = ?
              AND tabla_estado_registro_id = 1
              AND producto_id IN ($placeholdersProd)
              AND lista_precio_id IN ($placeholdersLista)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerPreciosVentaActualesPorProductoYLista: " . mysqli_error($conexion));
        return [];
    }

    $params = array_merge([$empresa_id], $productoIds, $listaIds);
    mysqli_stmt_bind_param($stmt, "i" . $tiposProd . $tiposLista, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $precios = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $pid = (int)$row['producto_id'];
        $lid = (int)$row['lista_precio_id'];
        if (!isset($precios[$pid])) $precios[$pid] = [];
        $precios[$pid][$lid] = (float)$row['precio_final'];
    }
    mysqli_stmt_close($stmt);
    return $precios;
}

/* =========================================================================
 * Reglas VIGENTES de gestion__listas_precios_reglas para las listas dadas,
 * agrupadas por lista_precio_id. Alcance confirmado por Pablo para este
 * cálculo:
 *   - NO se filtra por es_promocion ni por permite_acumulacion — confirmado
 *     que no se consideren, ni siquiera como criterio de exclusión. (Ojo:
 *     en los datos reales las reglas "generales" están cargadas con
 *     es_promocion=1, así que filtrar por ese campo las tapaba a todas.)
 *   - NO se resuelve por `prioridad` — hoy no hay reglas por producto_tipo_id
 *     que se superpongan, así que no hace falta desambiguar.
 *   - Solo se matchea por producto_id, producto_categoria_id o entidad_id
 *     (proveedor): son los únicos alcances que gestion__productos expone
 *     directamente. marca_id/modelo_id/submodelo_id de la tabla de reglas
 *     quedan SIN USAR en este cálculo — gestion__productos no tiene esas
 *     columnas, así que una regla que solo matchee por esas dimensiones no
 *     se va a aplicar acá. Avisar si eso llega a importar en la práctica.
 * ========================================================================= */
function obtenerReglasVigentesPorListas($conexion, $empresa_id, $entidad_id, $listaIds)
{
    if (empty($listaIds)) return [];
    $empresa_id = intval($empresa_id);

    $placeholders = implode(',', array_fill(0, count($listaIds), '?'));
    $tipos = str_repeat('i', count($listaIds));

    $sql = "SELECT lista_precio_regla_id, lista_precio_id, valor_ajuste, producto_id, producto_categoria_id, entidad_id
            FROM gestion__listas_precios_reglas
            WHERE empresa_id = ?
              AND tabla_estado_registro_id = 1
              AND f_desde <= CURDATE()
              AND (f_hasta IS NULL OR f_hasta >= CURDATE())
              AND lista_precio_id IN ($placeholders)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando obtenerReglasVigentesPorListas: " . mysqli_error($conexion));
        return [];
    }

    $params = array_merge([$empresa_id], $listaIds);
    mysqli_stmt_bind_param($stmt, "i" . $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $reglas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $lid = (int)$row['lista_precio_id'];
        if (!isset($reglas[$lid])) $reglas[$lid] = [];
        $reglas[$lid][] = $row;
    }
    mysqli_stmt_close($stmt);
    return $reglas;
}

/* =========================================================================
 * De las reglas vigentes de UNA lista, elige la que aplica a este producto.
 * Especificidad manual (sin usar `prioridad`, por lo dicho arriba):
 * match por producto_id puntual > match por categoría > match por
 * proveedor (entidad_id) > regla general (los 3 campos de alcance en NULL).
 * ========================================================================= */
function resolverReglaAplicable($reglasDeLista, $producto_id, $categoria_id, $entidad_id)
{
    $porProducto = null; $porCategoria = null; $porEntidad = null; $general = null;

    foreach ($reglasDeLista as $r) {
        if ($r['producto_id'] !== null && (int)$r['producto_id'] === $producto_id) {
            $porProducto = $r;
        } elseif ($r['producto_categoria_id'] !== null && (int)$r['producto_categoria_id'] === $categoria_id) {
            $porCategoria = $r;
        } elseif ($r['entidad_id'] !== null && (int)$r['entidad_id'] === $entidad_id) {
            $porEntidad = $r;
        } elseif ($r['producto_id'] === null && $r['producto_categoria_id'] === null && $r['entidad_id'] === null) {
            $general = $r;
        }
    }

    return $porProducto ?? $porCategoria ?? $porEntidad ?? $general;
}

/* =========================================================================
 * "Debería ser" para TODAS las listas activas de un producto, respetando la
 * cadena de origen de gestion__listas_precios.lista_precio_origen_id:
 *   1 = COSTO        -> parte de costo_neto_compra (el propuesto por esta lista de proveedor)
 *   2 = OTRA_LISTA    -> parte del "debería ser" YA calculado de lista_base_id (encadenado)
 *   3 = MANUAL        -> no se recalcula (el valor es materializado a mano); resultado null
 * Se resuelve en varias pasadas porque una lista OTRA_LISTA puede depender
 * de otra que todavía no se calculó en esta misma vuelta (el orden de
 * $listasPrecios no está garantizado). Con 2 niveles de cadena alcanza para
 * los datos reales, pero esto soporta cualquier profundidad razonable.
 * ========================================================================= */
/* =========================================================================
 * "Debería ser" para TODAS las listas activas de un producto. A diferencia
 * de la versión anterior, cada entrada del resultado es un detalle completo
 * {precio, regla_id, porcentaje, base} en vez de solo el precio — hace
 * falta para poder PERSISTIR el cálculo (qué regla se usó, sobre qué base)
 * cuando el botón "Actualizar costo y lista de precios" escribe en
 * gestion__listas_precios_productos, no solo para mostrarlo en pantalla.
 * Respeta la cadena de gestion__listas_precios.lista_precio_origen_id:
 *   1 = COSTO        -> parte de costo_neto_compra (el propuesto por esta lista de proveedor)
 *   2 = OTRA_LISTA    -> parte del "debería ser" YA calculado de lista_base_id (encadenado)
 *   3 = MANUAL        -> no se recalcula (el valor es materializado a mano); resultado null
 * Se resuelve en varias pasadas porque una lista OTRA_LISTA puede depender
 * de otra que todavía no se calculó en esta misma vuelta.
 * ========================================================================= */
function calcularPreciosDeberianSerPorLista($listasPrecios, $reglasPorLista, $producto_id, $categoria_id, $entidad_id, $costo_neto_compra)
{
    $vacio = ['precio' => null, 'regla_id' => null, 'porcentaje' => null, 'base' => null];
    $resultado = [];
    $pendientes = $listasPrecios;
    $intentosRestantes = count($listasPrecios) + 1;

    while (!empty($pendientes) && $intentosRestantes-- > 0) {
        $siguientesPendientes = [];

        foreach ($pendientes as $lp) {
            $lid = $lp['lista_precio_id'];
            $origen = $lp['lista_precio_origen_id'];

            if ($origen === 3) { // MANUAL: no hay fórmula que aplicar
                $resultado[$lid] = $vacio;
                continue;
            }

            if ($origen === 1) { // COSTO
                $base = $costo_neto_compra;
            } else { // OTRA_LISTA: depende de que lista_base_id ya esté resuelta
                $baseListaId = $lp['lista_base_id'];
                if ($baseListaId === null || !array_key_exists($baseListaId, $resultado)) {
                    $siguientesPendientes[] = $lp; // todavía no le tocó a la lista base; reintentar
                    continue;
                }
                if ($resultado[$baseListaId]['precio'] === null) {
                    $resultado[$lid] = $vacio; // la base es manual o sin regla: no se puede encadenar
                    continue;
                }
                $base = $resultado[$baseListaId]['precio'];
            }

            $regla = resolverReglaAplicable($reglasPorLista[$lid] ?? [], $producto_id, $categoria_id, $entidad_id);
            $resultado[$lid] = $regla
                ? [
                    'precio' => round($base * (1 + ($regla['valor_ajuste'] / 100)), 2),
                    'regla_id' => (int)$regla['lista_precio_regla_id'],
                    'porcentaje' => (float)$regla['valor_ajuste'],
                    'base' => $base
                ]
                : $vacio;
        }

        $pendientes = $siguientesPendientes;
    }

    // Lo que quedó sin resolver (dependencia circular, o lista_base_id que
    // no está entre las listas activas) se marca vacío en vez de quedar sin
    // dato — evita colgar el cálculo por un caso raro de configuración.
    foreach ($pendientes as $lp) {
        $resultado[$lp['lista_precio_id']] = $vacio;
    }

    return $resultado;
}

/* =========================================================================
 * Devuelve el producto_proveedor_id activo de este producto+proveedor, y
 * si no existe lo CREA (codigo_proveedor en NULL — se completa después a
 * mano en el ABM de Productos-Proveedores). Separado en su propia función
 * porque tanto el alta manual como, en el futuro, cualquier otro punto de
 * entrada que necesite "vincular sobre la marcha" lo van a necesitar igual.
 * ========================================================================= */
function obtenerOCrearProductoProveedor($conexion, $empresa_id, $entidad_id, $producto_id, $usuario_id)
{
    $sql = "SELECT producto_proveedor_id FROM gestion__productos_proveedores
            WHERE empresa_id = ? AND entidad_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $entidad_id, $producto_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($fila) {
        return (int)$fila['producto_proveedor_id'];
    }

    $sql_ins = "INSERT INTO gestion__productos_proveedores
                (producto_id, entidad_id, empresa_id, codigo_proveedor, tabla_estado_registro_id)
                VALUES (?, ?, ?, NULL, 1)";
    $stmt = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    // Si es el primer proveedor del producto, queda como principal.
    pp_asegurar_principal($conexion, $empresa_id, $producto_id);
    return $nuevo_id;
}

/* =========================================================================
 * Actualiza SOLO el precio_lista del proveedor (gestion__productos_proveedores_precios) —
 * mismo patrón historial de siempre (cerrar la fila vigente, insertar en
 * _historico, actualizar). NO toca gestion__productos_costos ni las listas
 * de precio de venta: costo_neto_compra se deriva de precio_lista al leer,
 * así que alcanza con esto para que el resto de la grilla se actualice solo.
 * ========================================================================= */
// $origen_carga: MANUAL (default, botones de la página), COMPRA (confirmación de
// una factura de proveedor, ver fpActualizarPreciosPorCompra).
function actualizarPrecioListaProveedor($conexion, $producto_proveedor_id, $nuevo_precio_lista, $moneda_id, $usuario_id, $origen_carga = 'MANUAL')
{
    $producto_proveedor_id = intval($producto_proveedor_id);
    $moneda_id = intval($moneda_id) ?: 1;

    if ($nuevo_precio_lista < 0) {
        return ['success' => false, 'message' => 'El precio de lista calculado es inválido.'];
    }

    $sql_actual = "SELECT precio_lista, moneda_id, f_vigencia_desde, origen_carga, archivo_importacion, usuario_carga_id
                   FROM gestion__productos_proveedores_precios WHERE producto_proveedor_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($stmt, "i", $producto_proveedor_id);
    mysqli_stmt_execute($stmt);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$actual) {
        return ['success' => false, 'message' => 'No se encontró el precio actual de este producto/proveedor.'];
    }

    if (abs((float)$actual['precio_lista'] - $nuevo_precio_lista) < 0.99) {
        return ['success' => true, 'actualizado' => false];
    }

    $fecha_hoy = date('Y-m-d');

    mysqli_begin_transaction($conexion);
    try {
        $sql_hist = "INSERT INTO gestion__productos_proveedores_precios_historico
                    (producto_proveedor_id, precio_lista, moneda_id, f_vigencia_desde, f_vigencia_hasta,
                     origen_carga, archivo_importacion, usuario_carga_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql_hist);
        $f_hasta = date('Y-m-d', strtotime($fecha_hoy . ' -1 day'));
        if ($f_hasta < $actual['f_vigencia_desde']) {
            $f_hasta = $actual['f_vigencia_desde'];
        }
        mysqli_stmt_bind_param(
            $stmt,
            "idsssssi",
            $producto_proveedor_id,
            $actual['precio_lista'],
            $actual['moneda_id'],
            $actual['f_vigencia_desde'],
            $f_hasta,
            $actual['origen_carga'],
            $actual['archivo_importacion'],
            $actual['usuario_carga_id']
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
        mysqli_stmt_bind_param($stmt, "dissii", $nuevo_precio_lista, $moneda_id, $fecha_hoy, $origen_carga, $usuario_id, $producto_proveedor_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error actualizando precio de lista: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'actualizado' => true];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Contexto reusable de cálculo (listas activas + reglas vigentes), para no
 * repetir esas 2 consultas por cada producto cuando se actualiza en lote.
 * ========================================================================= */
function obtenerContextoCalculoPrecios($conexion, $empresa_id, $entidad_id)
{
    $listas = obtenerListasPreciosActivas($conexion, $empresa_id);
    $listaIds = array_column($listas, 'lista_precio_id');
    $reglas = obtenerReglasVigentesPorListas($conexion, $empresa_id, $entidad_id, $listaIds);
    return ['listas' => $listas, 'reglas' => $reglas];
}

/* =========================================================================
 * producto_categoria_id de varios productos en una sola consulta (para no
 * repetirla producto por producto en el loop del botón masivo).
 * ========================================================================= */
function obtenerCategoriasPorProductos($conexion, $productoIds)
{
    if (empty($productoIds)) return [];
    $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
    $tipos = str_repeat('i', count($productoIds));
    $sql = "SELECT producto_id, producto_categoria_id FROM gestion__productos WHERE producto_id IN ($placeholders)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $tipos, ...$productoIds);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $categorias = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categorias[(int)$row['producto_id']] = (int)$row['producto_categoria_id'];
    }
    mysqli_stmt_close($stmt);
    return $categorias;
}

/* =========================================================================
 * Escribe el "debería ser" calculado en gestion__listas_precios_productos
 * para UNA lista puntual — mismo patrón de gestion__listas_precios_productos_historico
 * que ya usa listas_precios_productos_model.php::importarPreciosDesdeExcel:
 * desactivar (tabla_estado_registro_id=2) la fila vigente, copiarla al
 * historial, e insertar la fila nueva (es_manual=0, con la regla y el
 * costo que la originaron). Si no hay fila vigente todavía, se inserta
 * directo sin paso de historial.
 * ========================================================================= */
function actualizarPrecioVentaDesdeRegla($conexion, $empresa_id, $lista_precio_id, $producto_id, $producto_costo_id, $detalleCalculo, $usuario_id)
{
    $fecha_hoy = date('Y-m-d');

    $sql_actual = "SELECT lista_precio_producto_id, precio_final, precio_origen, porcentaje_general_aplicado,
                          importe_general_aplicado, lista_precio_regla_id, porcentaje_regla_aplicado,
                          importe_regla_aplicado, es_manual, precio_manual, f_desde, f_hasta, observaciones
                   FROM gestion__listas_precios_productos
                   WHERE lista_precio_id = ? AND producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql_actual);
    mysqli_stmt_bind_param($stmt, "iii", $lista_precio_id, $producto_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Si ya está en el mismo precio (redondeado), no se toca — evita
    // ensuciar el historial con "cambios" que en los hechos no cambian nada.
    if ($actual && abs((float)$actual['precio_final'] - $detalleCalculo['precio']) < 0.99) {
        return ['success' => true, 'actualizado' => false];
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($actual) {
            $sql_hist = "INSERT INTO gestion__listas_precios_productos_historial
                        (empresa_id, lista_precio_id, lista_precio_producto_id, producto_id, producto_costo_id,
                         precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                         lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                         es_manual, precio_manual, precio_final, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($conexion, $sql_hist);
            $lpp_id = (int)$actual['lista_precio_producto_id'];
            mysqli_stmt_bind_param(
                $stmt,
                "iiiiidddiddiddsss",
                $empresa_id, $lista_precio_id, $lpp_id, $producto_id, $producto_costo_id,
                $actual['precio_origen'], $actual['porcentaje_general_aplicado'], $actual['importe_general_aplicado'],
                $actual['lista_precio_regla_id'], $actual['porcentaje_regla_aplicado'], $actual['importe_regla_aplicado'],
                $actual['es_manual'], $actual['precio_manual'], $actual['precio_final'],
                $actual['f_desde'], $actual['f_hasta'], $actual['observaciones']
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error guardando historial de precio de venta: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            $sql_deact = "UPDATE gestion__listas_precios_productos SET tabla_estado_registro_id = 2 WHERE lista_precio_producto_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_deact);
            mysqli_stmt_bind_param($stmt, "i", $lpp_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error desactivando precio de venta anterior: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        }

        $importe_regla = round($detalleCalculo['base'] * ($detalleCalculo['porcentaje'] / 100), 2);

        $sql_ins = "INSERT INTO gestion__listas_precios_productos
                   (empresa_id, lista_precio_id, producto_id, producto_costo_id, precio_origen,
                    lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                    es_manual, precio_final, f_desde, tabla_estado_registro_id, creado_por)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1, ?)";
        $stmt = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param(
            $stmt,
            "iiiididddsi",
            $empresa_id, $lista_precio_id, $producto_id, $producto_costo_id, $detalleCalculo['base'],
            $detalleCalculo['regla_id'], $detalleCalculo['porcentaje'], $importe_regla,
            $detalleCalculo['precio'], $fecha_hoy, $usuario_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error insertando precio de venta: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'actualizado' => true];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Orquestador del botón "Actualizar costo y lista de precios": actualiza
 * gestion__productos_costos (+ su historial) y, con ese costo nuevo, recorre
 * las listas activas escribiendo el "debería ser" en
 * gestion__listas_precios_productos allí donde haya una regla aplicable
 * (las listas MANUAL, o sin regla que matchee, se dejan como están — no se
 * inventa un valor). $contexto = obtenerContextoCalculoPrecios(), pasado
 * desde afuera para no repetirlo producto por producto en el botón masivo.
 * ========================================================================= */
/* =========================================================================
 * Recorre las listas activas y escribe el "debería ser" donde haya regla —
 * el paso común entre "actualizar precio de venta" (solo) y "actualizar
 * costo y precio de venta" (ambos). Requiere producto_costo_id para
 * vincular el precio al costo que lo originó (puede ser null si el
 * producto todavía no tiene costo registrado).
 * ========================================================================= */
// AJUSTE HARDCODEADO — lista_precio_id=3 ("Mostrador"): pedido puntual de
// Pablo, no es un criterio general del sistema. Si en algún momento se
// necesita para otra lista o se define una regla real que lo reemplace,
// esta constante y el bloque de abajo son lo primero que hay que tocar.
if (!defined('AJUSTE_HARDCODEADO_LISTA_PRECIO_ID')) {
    define('AJUSTE_HARDCODEADO_LISTA_PRECIO_ID', 3);
}

// $costo_anterior_registrado / $costo_neto_compra_propuesto: para calcular
// el % de incremento de costo que mueve la lista 3 (ver bloque de abajo).
// Se piden como 2 valores separados (en vez de un solo % ya calculado) para
// que el % salga SIEMPRE de "cuánto cambiaría el costo si se aplicara esta
// lista de proveedor", sin importar si este llamado en particular escribe
// o no un costo nuevo — antes solo se calculaba en el flujo "Actualizar
// costo y precio" (que sí escribe costo), y "Actualizar precio de venta"
// (que no lo toca) nunca pasaba nada acá, dejando la lista 3 sin actualizar.
function actualizarListasPreciosPorRegla($conexion, $producto_id, $categoria_id, $entidad_id, $costo_base, $producto_costo_id, $empresa_id, $usuario_id, $contexto, $costo_anterior_registrado = null, $costo_neto_compra_propuesto = null)
{
    $deberianSer = calcularPreciosDeberianSerPorLista($contexto['listas'], $contexto['reglas'], $producto_id, $categoria_id, $entidad_id, $costo_base);

    // AJUSTE HARDCODEADO (ver constante arriba): trato asimétrico según de
    // qué lado esté el desvío de la lista 3 contra la fórmula normal
    // (regla sobre la lista base):
    // - Precio actual MAYOR a la fórmula (desvío hacia arriba, p. ej. un
    //   precio de mostrador puesto a propósito más alto por demanda): NUNCA
    //   se resetea de golpe al valor de fórmula. Si se puede calcular el %
    //   de incremento de costo (hay un costo anterior > 0 registrado, y un
    //   costo propuesto para compararlo), se ajusta el precio actual
    //   proporcionalmente a ese %, preservando el desvío. Si NO se puede
    //   calcular (no hay costo anterior, primera carga de costo del
    //   producto), no se toca la lista 3 en absoluto.
    // - Precio actual MENOR o igual a la fórmula (o sin desvío real): se
    //   deja $deberianSer[$lid3] tal cual lo calculó calcularPreciosDeberianSerPorLista
    //   más arriba — sube al valor de fórmula como cualquier otra lista, no
    //   hay motivo para preservar un precio de mostrador más bajo que el
    //   que le correspondería.
    $lid3 = AJUSTE_HARDCODEADO_LISTA_PRECIO_ID;
    if (isset($deberianSer[$lid3]) && $deberianSer[$lid3]['precio'] !== null) {
        $preciosActuales = obtenerPreciosVentaActualesPorProductoYLista($conexion, $empresa_id, [$producto_id], [$lid3]);
        $precioActualLista3 = $preciosActuales[$producto_id][$lid3] ?? null;

        if ($precioActualLista3 !== null && ($precioActualLista3 - $deberianSer[$lid3]['precio']) > 0.99) {
            // Guarda simétrica en los dos lados de la comparación: sin esto,
            // un costo propuesto en 0 (dato mal cargado, descuento de compra
            // al 100%, etc.) daría un % de -100% y llevaría el precio de la
            // lista 3 a $0 sin ningún piso.
            $porcentajeCambioCosto = null;
            if ($costo_anterior_registrado !== null && (float)$costo_anterior_registrado > 0
                && $costo_neto_compra_propuesto !== null && (float)$costo_neto_compra_propuesto > 0) {
                $porcentajeCambioCosto = (($costo_neto_compra_propuesto - (float)$costo_anterior_registrado) / (float)$costo_anterior_registrado) * 100;
            }

            if ($porcentajeCambioCosto !== null) {
                $deberianSer[$lid3]['precio'] = round($precioActualLista3 * (1 + ($porcentajeCambioCosto / 100)), 2);
            } else {
                unset($deberianSer[$lid3]);
            }
        }
    }

    $precios_actualizados = 0;
    $precios_sin_regla = 0;
    foreach ($deberianSer as $lid => $detalle) {
        if ($detalle['precio'] === null) {
            $precios_sin_regla++;
            continue;
        }
        $resPrecio = actualizarPrecioVentaDesdeRegla($conexion, $empresa_id, $lid, $producto_id, $producto_costo_id, $detalle, $usuario_id);
        if ($resPrecio['success'] && !empty($resPrecio['actualizado'])) {
            $precios_actualizados++;
        } elseif (!$resPrecio['success']) {
            error_log("actualizarListasPreciosPorRegla: falló producto_id=$producto_id lista_precio_id=$lid: " . ($resPrecio['message'] ?? ''));
        }
    }

    return ['precios_actualizados' => $precios_actualizados, 'precios_sin_regla' => $precios_sin_regla];
}

/* =========================================================================
 * Inversa de calcularPreciosDeberianSerPorLista: dado el precio de venta de
 * un producto en una lista, devuelve el costo del que saldría con la regla
 * vigente (precio / (1 + ajuste%)), siguiendo la cadena OTRA_LISTA hasta la
 * lista de origen COSTO. null si no se puede: lista MANUAL, sin regla
 * aplicable, ajuste <= -100% o cadena rota / circular.
 * $listasPorId = obtenerListasPreciosActivas() indexado por lista_precio_id.
 * ========================================================================= */
function calcularCostoDesdePrecioVenta($listasPorId, $reglasPorLista, $lista_precio_id, $precio, $producto_id, $categoria_id, $entidad_id, $profundidad = 0)
{
    if ($precio === null || $precio <= 0 || $profundidad > 10) return null;
    $lp = $listasPorId[$lista_precio_id] ?? null;
    if (!$lp || $lp['lista_precio_origen_id'] === 3) return null;

    $regla = resolverReglaAplicable($reglasPorLista[$lista_precio_id] ?? [], $producto_id, $categoria_id, $entidad_id);
    if (!$regla) return null;
    $factor = 1 + ((float)$regla['valor_ajuste'] / 100);
    if ($factor <= 0) return null;
    $base = $precio / $factor;

    if ($lp['lista_precio_origen_id'] === 1) return $base;
    if ($lp['lista_base_id'] === null) return null;
    return calcularCostoDesdePrecioVenta($listasPorId, $reglasPorLista, $lp['lista_base_id'], $base, $producto_id, $categoria_id, $entidad_id, $profundidad + 1);
}

/* =========================================================================
 * Alta del precio de lista de un vínculo producto-proveedor que todavía no
 * tiene fila en gestion__productos_proveedores_precios (una por vínculo).
 * NO abre transacción (la maneja el llamador) y no pisa: si la fila ya
 * existe devuelve false sin tocarla (INSERT ... WHERE NOT EXISTS, así dos
 * procesos en paralelo no duplican). $origen_carga: MANUAL / IMPORTACION /
 * ESTIMADO (armado desde precios de venta o costo) / COMPRA (factura).
 * ========================================================================= */
function insertarPrecioListaProveedor($conexion, $producto_proveedor_id, $empresa_id, $entidad_id, $precio_lista, $moneda_id, $origen_carga, $usuario_id, $estado_id = 1)
{
    $f_hoy = date('Y-m-d');
    $sql = "INSERT INTO gestion__productos_proveedores_precios
                (producto_proveedor_id, empresa_id, entidad_id, precio_lista, moneda_id,
                 f_vigencia_desde, origen_carga, usuario_carga_id, tabla_estado_registro_id)
            SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM gestion__productos_proveedores_precios
                              WHERE producto_proveedor_id = ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new Exception('Error preparando alta de precio de proveedor: ' . mysqli_error($conexion));
    }
    $usuario_id = intval($usuario_id);
    mysqli_stmt_bind_param($stmt, "iiidissiii", $producto_proveedor_id, $empresa_id, $entidad_id, $precio_lista,
        $moneda_id, $f_hoy, $origen_carga, $usuario_id, $estado_id, $producto_proveedor_id);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Error insertando precio de proveedor: ' . $error);
    }
    $insertado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $insertado;
}

/* =========================================================================
 * origen_carga admite ESTIMADO / COMPRA recién después de
 * _local/sql/mantenimiento/20261010_precios_compra_01_estructura.sql. Si el script
 * no corrió, un valor fuera del ENUM se grabaría como '' (sin modo estricto)
 * o fallaría (con modo estricto): en ese caso se usa MANUAL.
 * ========================================================================= */
function preciosOrigenCargaValido($conexion, $origen_carga)
{
    static $tipo = null;
    if ($tipo === null) {
        $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__productos_proveedores_precios LIKE 'origen_carga'");
        $fila = $res ? mysqli_fetch_assoc($res) : null;
        $tipo = $fila ? (string)$fila['Type'] : '';
    }
    if (strpos($tipo, "'" . $origen_carga . "'") !== false) {
        return $origen_carga;
    }
    error_log("preciosOrigenCargaValido: origen_carga '$origen_carga' no existe en el ENUM, se usa MANUAL");
    return 'MANUAL';
}
