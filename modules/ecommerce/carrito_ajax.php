<?php
ini_set('display_errors', 0);
ob_start();

function json_fatal($msg) {
    ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['fatal' => $msg]);
    exit;
}

set_exception_handler(function ($e) {
    json_fatal($e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . ']');
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        json_fatal($err['message'] . ' [' . $err['file'] . ':' . $err['line'] . ']');
    }
});

require_once __DIR__ . '/../../config.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    case 'obtener_catalogo':
        $empresa_id    = intval($_REQUEST['empresa_id'] ?? 0);
        $where_parts   = ['p.tabla_estado_registro_id = 1'];

        if ($empresa_id > 0) {
            $where_parts[] = "p.empresa_id = $empresa_id";
        }

        // Filtros opcionales (para búsqueda server-side si se necesita)
        if (!empty($_REQUEST['categoria_id'])) {
            $where_parts[] = "p.producto_categoria_id = " . intval($_REQUEST['categoria_id']);
        }
        if (!empty($_REQUEST['q'])) {
            $q = mysqli_real_escape_string($conexion, $_REQUEST['q']);
            $where_parts[] = "(p.producto_nombre LIKE '%$q%' OR p.producto_codigo LIKE '%$q%')";
        }

        $where_sql = implode(' AND ', $where_parts);

        /*
         * Cascada de precio (3 fuentes en orden de prioridad):
         * 1. gestion__listas_precios_productos   → tabla nueva, actualmente vacía, se irá poblando
         * 2. gestion__listas_precios_productos_historial → registros activos (f_baja IS NULL)
         * 3. xxx_gestion__listas_precios_productos       → tabla legacy con precios vigentes
         */
        $subquery_precio = "COALESCE(
            (
                SELECT lpp.precio
                FROM gestion__listas_precios_productos lpp
                WHERE lpp.producto_id = p.producto_id
                  AND lpp.tabla_estado_registro_id = 1
                  AND lpp.f_desde <= CURDATE()
                  AND (lpp.f_hasta IS NULL OR lpp.f_hasta >= CURDATE())
                ORDER BY lpp.lista_precio_producto_id DESC
                LIMIT 1
            ),
            (
                SELECT h.precio_unitario
                FROM gestion__listas_precios_productos_historial h
                WHERE h.producto_id = p.producto_id
                  AND h.f_baja IS NULL
                ORDER BY h.lista_precio_producto_historial_id DESC
                LIMIT 1
            ),
            (
                SELECT x.precio_unitario
                FROM xxx_gestion__listas_precios_productos x
                WHERE x.producto_id = p.producto_id
                ORDER BY x.lista_precio_producto_id DESC
                LIMIT 1
            )
        )";

        $sql = "SELECT p.producto_id AS id,
                       p.producto_codigo AS codigo,
                       p.producto_nombre AS nombre,
                       p.producto_categoria_id AS categoria_id,
                       c.producto_categoria_nombre AS categoria_nombre,
                       p.producto_tipo_id AS tipo_id,
                       t.producto_tipo AS tipo_nombre,
                       p.color,
                       p.material,
                       p.lado,
                       p.garantia,
                       p.es_servicio,
                       p.controla_stock,
                       COALESCE($subquery_precio, 0) AS precio,
                       (
                           SELECT GROUP_CONCAT(ci.imagen_id ORDER BY pi.es_principal DESC, pi.orden ASC SEPARATOR ',')
                           FROM gestion__productos_imagenes pi
                           INNER JOIN conf__imagenes ci ON pi.imagen_id = ci.imagen_id
                           WHERE pi.producto_id = p.producto_id
                             AND pi.empresa_id = p.empresa_id
                             AND pi.tabla_estado_registro_id = 1
                       ) AS imagen_ids
                FROM gestion__productos p
                LEFT JOIN gestion__productos_categorias c ON c.producto_categoria_id = p.producto_categoria_id
                LEFT JOIN gestion__productos_tipos t ON t.producto_tipo_id = p.producto_tipo_id
                WHERE $where_sql
                ORDER BY p.producto_nombre
                LIMIT 300";

        $res = mysqli_query($conexion, $sql);

        if (!$res) {
            echo json_encode(['error_bd' => mysqli_error($conexion)]);
            exit;
        }

        $productos = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $imagenes = [];
            if (!empty($row['imagen_ids'])) {
                foreach (explode(',', $row['imagen_ids']) as $img_id) {
                    $imagenes[] = BASE_URL . '/modules/gestion/get_imagen.php?id=' . intval($img_id);
                }
            }

            $productos[] = [
                'id'             => intval($row['id']),
                'codigo'         => $row['codigo'],
                'nombre'         => $row['nombre'],
                'categoria_id'   => intval($row['categoria_id']),
                'categoria'      => $row['categoria_nombre'] ?? '',
                'tipo_id'        => intval($row['tipo_id']),
                'tipo'           => $row['tipo_nombre'] ?? '',
                'color'          => $row['color'] ?? '',
                'material'       => $row['material'] ?? '',
                'lado'           => $row['lado'] ?? '',
                'garantia'       => $row['garantia'] ?? '',
                'es_servicio'    => intval($row['es_servicio']),
                'controla_stock' => intval($row['controla_stock']),
                'precio'         => floatval($row['precio']),
                'imagenes'       => $imagenes,
            ];
        }

        echo json_encode([
            'data'       => $productos,
            'empresa_id' => $empresa_id,
            'total'      => count($productos),
        ]);
        break;

    case 'debug_precios':
        $cols   = [];
        $r      = mysqli_query($conexion, 'SHOW COLUMNS FROM gestion__listas_precios_productos');
        while ($row = mysqli_fetch_assoc($r)) {
            $cols[] = $row['Field'];
        }
        $sample = [];
        $r2     = mysqli_query($conexion, 'SELECT * FROM gestion__listas_precios_productos LIMIT 3');
        if ($r2) {
            while ($row = mysqli_fetch_assoc($r2)) {
                $sample[] = $row;
            }
        }
        echo json_encode(['columnas' => $cols, 'muestra' => $sample]);
        break;

    case 'debug_productos':
        $rows = [];
        $r    = mysqli_query($conexion,
            'SELECT empresa_id, tabla_estado_registro_id, COUNT(*) AS total
             FROM gestion__productos
             GROUP BY empresa_id, tabla_estado_registro_id
             ORDER BY empresa_id');
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = $row;
        }
        echo json_encode([
            'distribucion'        => $rows,
            'empresa_id_recibido' => intval($_REQUEST['empresa_id'] ?? 0),
        ]);
        break;

    case 'obtener_destinatarios':
        $empresa_id = intval($_REQUEST['empresa_id'] ?? 0);
        if ($empresa_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'empresa_id inválido.']);
            break;
        }

        $sucursales = [];
        $sql_suc = "SELECT sucursal_id, sucursal_nombre
                    FROM gestion__sucursales
                    WHERE empresa_id = $empresa_id
                      AND tabla_estado_registro_id = 1
                    ORDER BY sucursal_nombre";
        $res_suc = mysqli_query($conexion, $sql_suc);
        while ($row = mysqli_fetch_assoc($res_suc)) {
            $sucursales[] = [
                'id'     => intval($row['sucursal_id']),
                'nombre' => $row['sucursal_nombre'],
            ];
        }

        $clientes = [];
        $sql_cli = "SELECT entidad_id, entidad_nombre
                    FROM gestion__entidades
                    WHERE empresa_id = $empresa_id
                      AND es_cliente = 1
                      AND tabla_estado_registro_id = 1
                    ORDER BY entidad_nombre";
        $res_cli = mysqli_query($conexion, $sql_cli);
        while ($row = mysqli_fetch_assoc($res_cli)) {
            $clientes[] = [
                'id'     => intval($row['entidad_id']),
                'nombre' => $row['entidad_nombre'],
            ];
        }

        echo json_encode([
            'resultado'  => true,
            'sucursales' => $sucursales,
            'clientes'   => $clientes,
        ]);
        break;

    case 'guardar_pedido_carrito':
        $detalles_json = $_POST['detalles'] ?? '[]';
        $detalles      = json_decode($detalles_json, true);
        $usuario_id    = intval($_SESSION['usuario_id'] ?? 0);
        $empresa_id    = intval($_POST['empresa_id'] ?? 0);
        $sucursal_id   = intval($_POST['sucursal_id'] ?? 0);
        $entidad_id    = intval($_POST['entidad_id'] ?? 0);
        $moneda_id     = 1;

        if ($usuario_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Sesión expirada. Iniciá sesión nuevamente.']);
            break;
        }
        if ($empresa_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Empresa inválida.']);
            break;
        }
        if (empty($detalles)) {
            echo json_encode(['resultado' => false, 'error' => 'El carrito está vacío.']);
            break;
        }
        if ($sucursal_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Seleccioná una sucursal.']);
            break;
        }
        if ($entidad_id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Seleccioná un cliente.']);
            break;
        }

        // Validar que sucursal y cliente pertenezcan a la empresa
        $chk_suc = mysqli_query($conexion,
            "SELECT 1 FROM gestion__sucursales
             WHERE sucursal_id = $sucursal_id AND empresa_id = $empresa_id
               AND tabla_estado_registro_id = 1 LIMIT 1");
        if (!$chk_suc || !mysqli_num_rows($chk_suc)) {
            echo json_encode(['resultado' => false, 'error' => 'La sucursal no pertenece a la empresa.']);
            break;
        }
        $chk_cli = mysqli_query($conexion,
            "SELECT 1 FROM gestion__entidades
             WHERE entidad_id = $entidad_id AND empresa_id = $empresa_id
               AND es_cliente = 1 AND tabla_estado_registro_id = 1 LIMIT 1");
        if (!$chk_cli || !mysqli_num_rows($chk_cli)) {
            echo json_encode(['resultado' => false, 'error' => 'El cliente no pertenece a la empresa.']);
            break;
        }

        mysqli_begin_transaction($conexion);
        try {
            $fecha_pedido    = date('Y-m-d');
            $subtotal_total  = 0.0;
            $total_impuestos = 0.0;
            $total_general   = 0.0;
            $items_calc      = [];

            // ── Calcular importes por ítem (con IVA) ──────────────────────────
            foreach ($detalles as $item) {
                $prod_id = intval($item['id']);
                $cant    = floatval($item['cantidad']);
                $precio  = floatval($item['precio']);

                // Obtener alícuota IVA del producto
                $sql_iva = "SELECT p.iva_alicuota_id,
                                   COALESCE(iva.porcentaje, 0) AS iva_pct
                            FROM gestion__productos p
                            LEFT JOIN gestion__impuestos__iva_alicuotas iva
                                   ON iva.iva_alicuota_id = p.iva_alicuota_id
                                  AND iva.empresa_id      = p.empresa_id
                            WHERE p.producto_id = $prod_id
                            LIMIT 1";
                $res_iva        = mysqli_query($conexion, $sql_iva);
                $iva_row        = $res_iva ? mysqli_fetch_assoc($res_iva) : null;
                $iva_alicuota   = $iva_row ? intval($iva_row['iva_alicuota_id']) : 1;
                $iva_pct        = $iva_row ? floatval($iva_row['iva_pct'])       : 0.0;

                $importe_neto   = round($cant * $precio, 2);
                $importe_iva    = round($importe_neto * ($iva_pct / 100), 2);
                $importe_total  = round($importe_neto + $importe_iva, 2);

                $subtotal_total  += $importe_neto;
                $total_impuestos += $importe_iva;
                $total_general   += $importe_total;

                $items_calc[] = compact(
                    'prod_id', 'cant', 'precio',
                    'iva_alicuota', 'iva_pct',
                    'importe_neto', 'importe_iva', 'importe_total'
                );
            }

            $subtotal_total  = round($subtotal_total,  2);
            $total_impuestos = round($total_impuestos, 2);
            $total_general   = round($total_general,   2);

            // ── Insertar cabecera del pedido ──────────────────────────────────
            $sql_pedido = "INSERT INTO gestion__ventas_pedidos
                               (empresa_id, sucursal_id, comprobante_id, entidad_id,
                                fecha_pedido, moneda_id, tipo_cambio,
                                subtotal, total_impuestos, total,
                                usuario_id, tabla_estado_registro_id)
                           VALUES
                               ($empresa_id, $sucursal_id, 0, $entidad_id,
                                '$fecha_pedido', $moneda_id, 1.000000,
                                $subtotal_total, $total_impuestos, $total_general,
                                $usuario_id, 1)";

            if (!mysqli_query($conexion, $sql_pedido)) {
                throw new Exception('Error al crear el pedido: ' . mysqli_error($conexion));
            }
            $pedido_id = mysqli_insert_id($conexion);

            // ── Insertar detalles ─────────────────────────────────────────────
            foreach ($items_calc as $it) {
                $sql_det = "INSERT INTO gestion__ventas_pedidos_detalles
                                (venta_pedido_id, producto_id, cantidad, precio_unitario,
                                 iva_alicuota_id, porcentaje_iva,
                                 importe_iva, importe_neto, importe_total,
                                 tabla_estado_registro_id)
                            VALUES
                                ($pedido_id, {$it['prod_id']}, {$it['cant']}, {$it['precio']},
                                 {$it['iva_alicuota']}, {$it['iva_pct']},
                                 {$it['importe_iva']}, {$it['importe_neto']}, {$it['importe_total']},
                                 1)";

                if (!mysqli_query($conexion, $sql_det)) {
                    throw new Exception("Error en detalle producto {$it['prod_id']}: " . mysqli_error($conexion));
                }
            }

            mysqli_commit($conexion);
            echo json_encode([
                'resultado' => true,
                'mensaje'   => 'Pedido generado con éxito.',
                'pedido_id' => $pedido_id,
            ]);

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            echo json_encode(['resultado' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'obtener_filtros':
        $empresa_id    = intval($_REQUEST['empresa_id'] ?? 0);
        $where_empresa = $empresa_id > 0 ? "AND p.empresa_id = $empresa_id" : '';

        $sql_cats = "SELECT DISTINCT c.producto_categoria_id AS id, c.producto_categoria_nombre AS nombre
                     FROM gestion__productos_categorias c
                     INNER JOIN gestion__productos p ON p.producto_categoria_id = c.producto_categoria_id
                     WHERE p.tabla_estado_registro_id = 1 $where_empresa
                     ORDER BY c.producto_categoria_nombre";
        $res_cats  = mysqli_query($conexion, $sql_cats);
        $categorias = [];
        while ($row = mysqli_fetch_assoc($res_cats)) {
            $categorias[] = ['id' => intval($row['id']), 'nombre' => $row['nombre']];
        }

        $sql_colors = "SELECT DISTINCT color FROM gestion__productos
                       WHERE color IS NOT NULL AND color <> '' AND tabla_estado_registro_id = 1 $where_empresa
                       ORDER BY color";
        $res_colors = mysqli_query($conexion, $sql_colors);
        $colores = [];
        while ($row = mysqli_fetch_assoc($res_colors)) {
            $colores[] = $row['color'];
        }

        $sql_mats = "SELECT DISTINCT material FROM gestion__productos
                     WHERE material IS NOT NULL AND material <> '' AND tabla_estado_registro_id = 1 $where_empresa
                     ORDER BY material";
        $res_mats = mysqli_query($conexion, $sql_mats);
        $materiales = [];
        while ($row = mysqli_fetch_assoc($res_mats)) {
            $materiales[] = $row['material'];
        }

        $sql_lados = "SELECT DISTINCT lado FROM gestion__productos
                      WHERE lado IS NOT NULL AND lado <> '' AND tabla_estado_registro_id = 1 $where_empresa
                      ORDER BY lado";
        $res_lados = mysqli_query($conexion, $sql_lados);
        $lados = [];
        while ($row = mysqli_fetch_assoc($res_lados)) {
            $lados[] = $row['lado'];
        }

        $sql_gars = "SELECT DISTINCT garantia FROM gestion__productos
                     WHERE garantia IS NOT NULL AND garantia <> '' AND tabla_estado_registro_id = 1 $where_empresa
                     ORDER BY garantia";
        $res_gars = mysqli_query($conexion, $sql_gars);
        $garantias = [];
        while ($row = mysqli_fetch_assoc($res_gars)) {
            $garantias[] = $row['garantia'];
        }

        echo json_encode([
            'categorias' => $categorias,
            'colores'    => $colores,
            'materiales' => $materiales,
            'lados'      => $lados,
            'garantias'  => $garantias,
        ]);
        break;

    default:
        echo json_encode(['resultado' => false, 'error' => 'Acción no válida']);
        break;
}