<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$accion     = $_REQUEST['accion'] ?? '';
$empresa_id = 2;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Construye las condiciones WHERE y los parámetros comunes para búsqueda de productos.
 * Soporta: q (texto), categorias[] (IDs), marca_id, modelo_id, precio_min, precio_max.
 * El parámetro $excluir permite omitir uno de los grupos para el cálculo de facets.
 */
function buildWhere($conexion, $empresa_id, $excluir = null) {
    $where = [
        "p.tabla_estado_registro_id = 1",
        "p.empresa_id = $empresa_id",
        "EXISTS (
            SELECT 1
            FROM gestion__listas_precios_productos_historial h
            INNER JOIN gestion__listas_precios lp
                    ON lp.lista_precio_id = h.lista_precio_id
                   AND lp.empresa_id = $empresa_id
                   AND lp.tabla_estado_registro_id = 1
            WHERE h.producto_id = p.producto_id
              AND h.empresa_id = $empresa_id
              AND h.tabla_estado_registro_id = 1
              AND h.precio_final > 0
              AND h.f_desde <= CURDATE()
              AND (h.f_hasta IS NULL OR h.f_hasta >= CURDATE())
        )"
    ];

    // Búsqueda multi-término: cada palabra del input debe aparecer en al menos un campo visible.
    // Ejemplo: "acce amort" → busca productos que contengan "acce" en algún campo Y "amort" en algún campo.
    // COLLATE utf8mb4_general_ci: sin distinción de mayúsculas ni tildes.
    if (!empty($_GET['q']) && $excluir !== 'q') {
        $terminos = array_filter(array_map('trim', explode(' ', trim($_GET['q']))));
        foreach ($terminos as $termino) {
            $t = mysqli_real_escape_string($conexion, $termino);
            $where[] = "(
                CONVERT(p.producto_nombre         USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                OR CONVERT(p.producto_codigo      USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                OR CONVERT(p.producto_descripcion USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                OR EXISTS (
                    SELECT 1 FROM gestion__productos_categorias cat_q
                    WHERE cat_q.producto_categoria_id = p.producto_categoria_id
                      AND CONVERT(cat_q.producto_categoria_nombre USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                )
                OR EXISTS (
                    SELECT 1 FROM gestion__productos_compatibilidad pc2
                    INNER JOIN gestion__marcas m2 ON m2.marca_id = pc2.marca_id
                    WHERE pc2.producto_id = p.producto_id
                      AND CONVERT(m2.marca_nombre USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                )
                OR EXISTS (
                    SELECT 1 FROM gestion__productos_compatibilidad pc3
                    INNER JOIN gestion__modelos mo2 ON mo2.modelo_id = pc3.modelo_id
                    WHERE pc3.producto_id = p.producto_id
                      AND CONVERT(mo2.modelo_nombre USING utf8mb4) COLLATE utf8mb4_general_ci LIKE '%$t%'
                )
            )";
        }
    }

    // Categorías (múltiples)
    $cat_ids = [];
    if (!empty($_GET['categorias'])) {
        foreach ((array)$_GET['categorias'] as $c) {
            $v = intval($c);
            if ($v > 0) $cat_ids[] = $v;
        }
    }
    if ($cat_ids && $excluir !== 'categorias') {
        $where[] = "p.producto_categoria_id IN (" . implode(',', $cat_ids) . ")";
    }

    // Marca (vía tabla de compatibilidad)
    $marca_id = intval($_GET['marca_id'] ?? 0);
    if ($marca_id > 0 && $excluir !== 'marca') {
        $where[] = "EXISTS (
            SELECT 1 FROM gestion__productos_compatibilidad pc3
            WHERE pc3.producto_id = p.producto_id
              AND pc3.marca_id = $marca_id
        )";
    }

    // Modelo (vía tabla de compatibilidad)
    $modelo_id = intval($_GET['modelo_id'] ?? 0);
    if ($modelo_id > 0 && $excluir !== 'modelo') {
        $where[] = "EXISTS (
            SELECT 1 FROM gestion__productos_compatibilidad pc4
            WHERE pc4.producto_id = p.producto_id
              AND pc4.modelo_id = $modelo_id
        )";
    }

    return implode(' AND ', $where);
}

$subquery_precio = "(SELECT h.precio_final
                     FROM gestion__listas_precios_productos_historial h
                     INNER JOIN gestion__listas_precios lp
                             ON lp.lista_precio_id = h.lista_precio_id
                            AND lp.empresa_id = $empresa_id
                            AND lp.tabla_estado_registro_id = 1
                     WHERE h.producto_id = p.producto_id
                       AND h.empresa_id  = $empresa_id
                       AND h.tabla_estado_registro_id = 1
                       AND h.precio_final > 0
                       AND h.f_desde <= CURDATE()
                       AND (h.f_hasta IS NULL OR h.f_hasta >= CURDATE())
                     ORDER BY lp.lista_precio_id ASC, h.fecha_historial DESC
                     LIMIT 1)";

// ── Switch de acciones ────────────────────────────────────────────────────────

switch ($accion) {

    // ── Catálogo + facets ─────────────────────────────────────────────────────
    case 'obtener_catalogo':

        $where_all = buildWhere($conexion, $empresa_id);

        // Aplicar precio sobre la subquery (requiere envolver)
        $having = ["precio_vigente > 0"];
        if (isset($_GET['precio_min']) && $_GET['precio_min'] !== '') {
            $having[] = "precio_vigente >= " . floatval($_GET['precio_min']);
        }
        if (isset($_GET['precio_max']) && $_GET['precio_max'] !== '') {
            $having[] = "precio_vigente <= " . floatval($_GET['precio_max']);
        }
        $having_sql = 'HAVING ' . implode(' AND ', $having);

        // ── Productos ──
        $sql_prod = "SELECT p.producto_id              AS id,
                            p.producto_codigo           AS codigo,
                            p.producto_nombre           AS nombre,
                            p.producto_descripcion      AS descripcion,
                            p.producto_categoria_id     AS categoria_id,
                            c.producto_categoria_nombre AS categoria_nombre,
                            p.iva_alicuota_id,
                            COALESCE(iva.porcentaje, 0) AS iva_porcentaje,
                            COALESCE($subquery_precio, 0) AS precio_vigente,
                            (SELECT pi.imagen_id
                             FROM gestion__productos_imagenes pi
                             WHERE pi.producto_id = p.producto_id
                               AND pi.empresa_id  = p.empresa_id
                               AND pi.tabla_estado_registro_id = 1
                             ORDER BY pi.es_principal DESC, pi.orden ASC, pi.producto_imagen_id ASC
                             LIMIT 1) AS imagen_id
                     FROM gestion__productos p
                     LEFT JOIN gestion__productos_categorias c
                            ON c.producto_categoria_id = p.producto_categoria_id
                            AND p.producto_categoria_id > 0
                     LEFT JOIN gestion__impuestos__iva_alicuotas iva
                            ON iva.iva_alicuota_id = p.iva_alicuota_id
                           AND iva.empresa_id = p.empresa_id
                     WHERE $where_all
                     $having_sql
                     ORDER BY p.producto_nombre
                     LIMIT 500";

        $res = mysqli_query($conexion, $sql_prod);
        if (!$res) {
            echo json_encode(['error_bd' => mysqli_error($conexion), 'sql_debug' => $sql_prod]);
            exit;
        }

        $productos = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $precio_neto    = floatval($row['precio_vigente']);
            $iva_porcentaje = floatval($row['iva_porcentaje']);
            $productos[] = [
                'id'             => intval($row['id']),
                'codigo'         => $row['codigo'],
                'nombre'         => $row['nombre'],
                'descripcion'    => $row['descripcion'] ?? '',
                'categoria_id'   => intval($row['categoria_id']),
                'categoria'      => $row['categoria_nombre'] ?? '',
                'iva_alicuota_id'=> intval($row['iva_alicuota_id']),
                'iva_porcentaje' => $iva_porcentaje,
                'precio_neto'    => $precio_neto,
                'precio'         => round($precio_neto * (1 + $iva_porcentaje / 100), 2),
                'imagen_id'      => $row['imagen_id'] ? intval($row['imagen_id']) : 0,
            ];
        }

        // ── Facet: Categorías — incluye "Sin categoría" para productos sin categoría asignada ──
        $where_sin_cat = buildWhere($conexion, $empresa_id, 'categorias');
        $sql_cats = "SELECT COALESCE(c.producto_categoria_id, 0) AS id,
                            COALESCE(c.producto_categoria_nombre, 'Sin categoría') AS nombre,
                            COUNT(DISTINCT p.producto_id) AS total
                     FROM gestion__productos p
                     LEFT JOIN gestion__productos_categorias c
                             ON c.producto_categoria_id = p.producto_categoria_id
                             AND p.producto_categoria_id > 0
                     WHERE $where_sin_cat
                     GROUP BY COALESCE(c.producto_categoria_id, 0),
                              COALESCE(c.producto_categoria_nombre, 'Sin categoría')
                     ORDER BY nombre ASC";

        $facet_cats = [];
        $res = mysqli_query($conexion, $sql_cats);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $facet_cats[] = ['id' => intval($row['id']), 'nombre' => $row['nombre'], 'count' => intval($row['total'])];
            }
        }

        // ── Facet: Marcas (excluye filtro de marca para mostrar alternativas) ──
        $where_sin_marca = buildWhere($conexion, $empresa_id, 'marca');
        $sql_marcas = "SELECT m.marca_id AS id, m.marca_nombre AS nombre,
                              COUNT(DISTINCT p.producto_id) AS total
                       FROM gestion__marcas m
                       INNER JOIN gestion__productos_compatibilidad pc
                               ON pc.marca_id = m.marca_id
                       INNER JOIN gestion__productos p
                               ON p.producto_id = pc.producto_id
                       WHERE $where_sin_marca
                         AND m.empresa_id = $empresa_id
                         AND m.tabla_estado_registro_id = 1
                       GROUP BY m.marca_id, m.marca_nombre
                       ORDER BY m.marca_nombre";

        $facet_marcas = [];
        $res = mysqli_query($conexion, $sql_marcas);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $facet_marcas[] = ['id' => intval($row['id']), 'nombre' => $row['nombre'], 'count' => intval($row['total'])];
            }
        }

        // ── Facet: Modelos (solo si hay una marca seleccionada) ──
        $facet_modelos = [];
        $marca_id_sel = intval($_GET['marca_id'] ?? 0);
        if ($marca_id_sel > 0) {
            $where_sin_modelo = buildWhere($conexion, $empresa_id, 'modelo');
            $sql_modelos = "SELECT mo.modelo_id AS id, mo.modelo_nombre AS nombre,
                                   COUNT(DISTINCT p.producto_id) AS total
                            FROM gestion__modelos mo
                            INNER JOIN gestion__productos_compatibilidad pc
                                    ON pc.modelo_id = mo.modelo_id AND pc.marca_id = $marca_id_sel
                            INNER JOIN gestion__productos p
                                    ON p.producto_id = pc.producto_id
                            WHERE $where_sin_modelo
                              AND mo.empresa_id = $empresa_id
                              AND mo.tabla_estado_registro_id = 1
                              AND mo.marca_id = $marca_id_sel
                            GROUP BY mo.modelo_id, mo.modelo_nombre
                            ORDER BY mo.modelo_nombre";

            $res = mysqli_query($conexion, $sql_modelos);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $facet_modelos[] = ['id' => intval($row['id']), 'nombre' => $row['nombre'], 'count' => intval($row['total'])];
                }
            }
        }

        echo json_encode([
            'productos' => $productos,
            'facets'    => [
                'categorias' => $facet_cats,
                'marcas'     => $facet_marcas,
                'modelos'    => $facet_modelos,
            ],
        ]);
        break;

    // ── Clientes (entidades) ──────────────────────────────────────────────────
    case 'obtener_clientes':
        $sql = "SELECT entidad_id AS id, entidad_nombre AS nombre
                FROM gestion__entidades
                WHERE empresa_id = $empresa_id
                  AND es_cliente = 1
                  AND tabla_estado_registro_id = 1
                ORDER BY entidad_nombre";

        $clientes = [];
        $res = mysqli_query($conexion, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $clientes[] = ['id' => intval($row['id']), 'nombre' => $row['nombre']];
            }
        }
        echo json_encode($clientes);
        break;

    // ── Sucursales de la empresa ──────────────────────────────────────────────
    case 'obtener_sucursales_empresa':
        $sql = "SELECT sucursal_id AS id, sucursal_nombre AS nombre
                FROM gestion__sucursales
                WHERE empresa_id = $empresa_id
                  AND tabla_estado_registro_id = 1
                ORDER BY sucursal_nombre";

        $sucursales = [];
        $res = mysqli_query($conexion, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $sucursales[] = ['id' => intval($row['id']), 'nombre' => $row['nombre']];
            }
        }
        echo json_encode($sucursales);
        break;

    // ── Guardar pedido → gestion__ventas_pedidos ─────────────────────────────
    case 'guardar_pedido_carrito':
        $detalles_json = $_POST['detalles']   ?? '[]';
        $entidad_id    = intval($_POST['entidad_id']  ?? 0);
        $sucursal_id   = intval($_POST['sucursal_id'] ?? 1);
        $usuario_id    = intval($_SESSION['usuario_id'] ?? 0) ?: 1;

        $detalles = json_decode($detalles_json, true);

        if (empty($detalles)) {
            echo json_encode(['resultado' => false, 'error' => 'El carrito está vacío.']);
            break;
        }
        if ($entidad_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Debe seleccionar un cliente.']);
            break;
        }

        $res = mysqli_query($conexion, "SELECT entidad_id FROM gestion__entidades
                                        WHERE entidad_id = $entidad_id
                                          AND empresa_id = $empresa_id
                                          AND es_cliente = 1
                                          AND tabla_estado_registro_id = 1");
        if (!$res || mysqli_num_rows($res) === 0) {
            echo json_encode(['resultado' => false, 'error' => 'Cliente no válido.']);
            break;
        }

        $comprobante_tipo_id = 1;   // Pedido de Cliente
        $tabla_origen_id     = 82;  // conf__tablas: gestion__ventas_pedidos
        $moneda_id           = 1;
        $tipo_cambio         = 1.000000;
        $f_emision           = date('Y-m-d');

        mysqli_begin_transaction($conexion);

        try {
            // Punto de venta de la sucursal
            $res_pv = mysqli_query($conexion,
                "SELECT punto_venta_id FROM gestion__puntos_venta
                 WHERE empresa_id = $empresa_id
                   AND sucursal_id = $sucursal_id
                   AND tabla_estado_registro_id = 1
                 ORDER BY punto_venta_id ASC LIMIT 1");

            if (!$res_pv || mysqli_num_rows($res_pv) === 0) {
                throw new Exception("No se encontró punto de venta para la sucursal seleccionada.");
            }
            $punto_venta_id = intval(mysqli_fetch_assoc($res_pv)['punto_venta_id']);

            // Numerador del comprobante
            $res_num = mysqli_query($conexion,
                "SELECT numerador_id, ultimo_numero
                 FROM gestion__comprobantes_numeradores
                 WHERE empresa_id = $empresa_id
                   AND punto_venta_id = $punto_venta_id
                   AND comprobante_tipo_id = $comprobante_tipo_id
                 FOR UPDATE");

            if ($res_num && mysqli_num_rows($res_num) > 0) {
                $row_num      = mysqli_fetch_assoc($res_num);
                $nuevo_numero = intval($row_num['ultimo_numero']) + 1;
                $numerador_id = intval($row_num['numerador_id']);
                mysqli_query($conexion,
                    "UPDATE gestion__comprobantes_numeradores
                     SET ultimo_numero = $nuevo_numero, updated_at = NOW()
                     WHERE numerador_id = $numerador_id");
            } else {
                $nuevo_numero = 1;
                mysqli_query($conexion,
                    "INSERT INTO gestion__comprobantes_numeradores
                     (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero, created_at, updated_at)
                     VALUES ($empresa_id, $punto_venta_id, $comprobante_tipo_id, 1, NOW(), NOW())");
            }
            $comprobante_nro = intval($nuevo_numero);

            // Calcular totales y enriquecer ítems con IVA verificado desde BD
            $subtotal_neto = 0;
            $total_iva     = 0;
            $items_enriquecidos = [];

            foreach ($detalles as $item) {
                $prod_id  = intval($item['id']);
                $res_prod = mysqli_query($conexion,
                    "SELECT p.iva_alicuota_id, COALESCE(iva.porcentaje, 0) AS iva_porcentaje
                     FROM gestion__productos p
                     LEFT JOIN gestion__impuestos__iva_alicuotas iva
                            ON iva.iva_alicuota_id = p.iva_alicuota_id
                           AND iva.empresa_id = p.empresa_id
                     WHERE p.producto_id = $prod_id AND p.empresa_id = $empresa_id");

                if (!$res_prod || mysqli_num_rows($res_prod) === 0) {
                    throw new Exception("Producto ID $prod_id no encontrado.");
                }
                $prod_data       = mysqli_fetch_assoc($res_prod);
                $iva_alicuota_id = intval($prod_data['iva_alicuota_id']);
                $iva_porcentaje  = floatval($prod_data['iva_porcentaje']);

                $cantidad        = floatval($item['cantidad']);
                $precio_neto     = floatval($item['precio_neto']);
                $importe_neto    = round($cantidad * $precio_neto, 2);
                $importe_iva     = round($importe_neto * $iva_porcentaje / 100, 2);
                $importe_total   = round($importe_neto + $importe_iva, 2);

                $subtotal_neto += $importe_neto;
                $total_iva     += $importe_iva;

                $items_enriquecidos[] = [
                    'prod_id'         => $prod_id,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precio_neto,
                    'iva_alicuota_id' => $iva_alicuota_id,
                    'iva_porcentaje'  => $iva_porcentaje,
                    'importe_neto'    => $importe_neto,
                    'importe_iva'     => $importe_iva,
                    'importe_total'   => $importe_total,
                ];
            }

            $subtotal_neto = round($subtotal_neto, 2);
            $total_iva     = round($total_iva, 2);
            $total_pedido  = round($subtotal_neto + $total_iva, 2);

            // 1. Insertar comprobante (registro_origen_id se actualiza tras conocer el pedido_id)
            $sql_comp = "INSERT INTO gestion__comprobantes
                         (tabla_origen_id, registro_origen_id, modulo,
                          empresa_id, sucursal_id, comprobante_tipo_id,
                          comprobante_pv, comprobante_nro,
                          entidad_id, f_emision,
                          moneda_id, tipo_cambio,
                          importe_bruto, descuento_general,
                          importe_neto, importe_exento, importe_no_gravado,
                          importe_iva, importe_otros_impuestos,
                          importe_total, importe_pendiente,
                          signo, tabla_estado_registro_id, usuario_id)
                         VALUES
                         ($tabla_origen_id, 0, 'ecommerce',
                          $empresa_id, $sucursal_id, $comprobante_tipo_id,
                          $punto_venta_id, $comprobante_nro,
                          $entidad_id, '$f_emision',
                          $moneda_id, $tipo_cambio,
                          $subtotal_neto, 0,
                          $subtotal_neto, 0, 0,
                          $total_iva, 0,
                          $total_pedido, $total_pedido,
                          1, 1, $usuario_id)";

            if (!mysqli_query($conexion, $sql_comp)) {
                throw new Exception("Error al crear comprobante: " . mysqli_error($conexion));
            }
            $comprobante_id = mysqli_insert_id($conexion);

            // 2. Insertar pedido de venta
            $sql_ped = "INSERT INTO gestion__ventas_pedidos
                        (empresa_id, sucursal_id, comprobante_tipo_id,
                         comprobante_id, comprobante_pv, comprobante_nro,
                         entidad_id, f_emision,
                         moneda_id, tipo_cambio,
                         importe_bruto, descuento_general_pct, descuento_general,
                         importe_neto, importe_exento, importe_no_gravado,
                         importe_iva, importe_otros_impuestos, importe_total,
                         usuario_id, tabla_estado_registro_id)
                        VALUES
                        ($empresa_id, $sucursal_id, $comprobante_tipo_id,
                         $comprobante_id, $punto_venta_id, $comprobante_nro,
                         $entidad_id, '$f_emision',
                         $moneda_id, $tipo_cambio,
                         $subtotal_neto, 0, 0,
                         $subtotal_neto, 0, 0,
                         $total_iva, 0, $total_pedido,
                         $usuario_id, 1)";

            if (!mysqli_query($conexion, $sql_ped)) {
                throw new Exception("Error al crear el pedido: " . mysqli_error($conexion));
            }
            $venta_pedido_id = mysqli_insert_id($conexion);

            // 3. Actualizar comprobante con el ID real del pedido
            mysqli_query($conexion,
                "UPDATE gestion__comprobantes
                 SET registro_origen_id = $venta_pedido_id
                 WHERE comprobante_id = $comprobante_id");

            // 4. Insertar detalles
            foreach ($items_enriquecidos as $it) {
                $sql_det = "INSERT INTO gestion__ventas_pedidos_detalles
                            (venta_pedido_id, producto_id, cantidad,
                             precio_unitario, descuento_general_pct, descuento_general,
                             precio_unitario_neto, importe_neto,
                             iva_alicuota_id, porcentaje_iva, importe_iva,
                             importe_no_gravado, importe_exento, importe_linea,
                             tabla_estado_registro_id)
                            VALUES
                            ($venta_pedido_id, {$it['prod_id']}, {$it['cantidad']},
                             {$it['precio_unitario']}, 0, 0,
                             {$it['precio_unitario']}, {$it['importe_neto']},
                             {$it['iva_alicuota_id']}, {$it['iva_porcentaje']}, {$it['importe_iva']},
                             0, 0, {$it['importe_total']},
                             1)";

                if (!mysqli_query($conexion, $sql_det)) {
                    throw new Exception("Error en detalle producto {$it['prod_id']}: " . mysqli_error($conexion));
                }
            }

            mysqli_commit($conexion);
            echo json_encode([
                'resultado'       => true,
                'mensaje'         => 'Pedido registrado con éxito.',
                'venta_pedido_id' => $venta_pedido_id,
                'comprobante_nro' => $comprobante_nro,
                'total'           => $total_pedido,
            ]);

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            echo json_encode(['resultado' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['resultado' => false, 'error' => 'Acción no válida']);
        break;
}
?>
