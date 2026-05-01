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

        // Subquery de precio: solo registros activos y con fechas vigentes
        $subquery_precio = "(
            SELECT lpp.precio
            FROM gestion__listas_precios_productos lpp
            WHERE lpp.producto_id = p.producto_id
              AND lpp.tabla_estado_registro_id = 1
              AND lpp.f_desde <= CURDATE()
              AND (lpp.f_hasta IS NULL OR lpp.f_hasta >= CURDATE())
            ORDER BY lpp.lista_precio_producto_id DESC
            LIMIT 1
        )";

        $sql = "SELECT p.producto_id AS id,
                       p.producto_codigo AS codigo,
                       p.producto_nombre AS nombre,
                       p.producto_categoria_id AS categoria_id,
                       c.producto_categoria_nombre AS categoria_nombre,
                       p.color,
                       p.material,
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
                'id'           => intval($row['id']),
                'codigo'       => $row['codigo'],
                'nombre'       => $row['nombre'],
                'categoria_id' => intval($row['categoria_id']),
                'categoria'    => $row['categoria_nombre'] ?? '',
                'color'        => $row['color'] ?? '',
                'material'     => $row['material'] ?? '',
                'precio'       => floatval($row['precio']),
                'imagenes'     => $imagenes,
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

    case 'guardar_pedido_carrito':
        $detalles_json = $_POST['detalles'] ?? '[]';
        $detalles      = json_decode($detalles_json, true);
        $usuario_id    = intval($_SESSION['usuario_id'] ?? 1);

        if (empty($detalles)) {
            echo json_encode(['resultado' => false, 'error' => 'El carrito está vacío.']);
            break;
        }

        mysqli_begin_transaction($conexion);
        try {
            $fecha        = date('Y-m-d H:i:s');
            $sql_cabecera = "INSERT INTO gestion__comprobantes
                             (comprobante_tipo_id, sucursal_id, f_comprobante, estado, creado_por, f_creacion)
                             VALUES (1, 1, '$fecha', 1, $usuario_id, '$fecha')";

            if (!mysqli_query($conexion, $sql_cabecera)) {
                throw new Exception('Error al crear la cabecera: ' . mysqli_error($conexion));
            }
            $comprobante_id = mysqli_insert_id($conexion);

            foreach ($detalles as $item) {
                $prod_id  = intval($item['id']);
                $cant     = floatval($item['cantidad']);
                $precio   = floatval($item['precio']);
                $subtotal = $cant * $precio;

                $sql_det = "INSERT INTO gestion__comprobantes_detalles
                            (comprobante_id, producto_id, cantidad, precio_unitario, subtotal)
                            VALUES ($comprobante_id, $prod_id, $cant, $precio, $subtotal)";

                if (!mysqli_query($conexion, $sql_det)) {
                    throw new Exception("Error en detalle ID $prod_id: " . mysqli_error($conexion));
                }
            }

            mysqli_commit($conexion);
            echo json_encode([
                'resultado'      => true,
                'mensaje'        => 'Pedido generado con éxito.',
                'comprobante_id' => $comprobante_id,
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

        echo json_encode([
            'categorias' => $categorias,
            'colores'    => $colores,
            'materiales' => $materiales,
        ]);
        break;

    default:
        echo json_encode(['resultado' => false, 'error' => 'Acción no válida']);
        break;
}