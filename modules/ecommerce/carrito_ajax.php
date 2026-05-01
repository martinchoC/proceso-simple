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
        $where_empresa = $empresa_id > 0 ? "AND p.empresa_id = $empresa_id" : '';

        $sql = "SELECT p.producto_id AS id,
                       p.producto_codigo AS codigo,
                       p.producto_nombre AS nombre,
                       COALESCE((
                           SELECT lpp.precio
                           FROM gestion__listas_precios_productos lpp
                           WHERE lpp.producto_id = p.producto_id
                           LIMIT 1
                       ), 0) AS precio,
                       (
                           SELECT ci.imagen_id
                           FROM gestion__productos_imagenes pi
                           INNER JOIN conf__imagenes ci ON pi.imagen_id = ci.imagen_id
                           WHERE pi.producto_id = p.producto_id
                             AND pi.empresa_id = p.empresa_id
                             AND pi.es_principal = 1
                             AND pi.tabla_estado_registro_id = 1
                           LIMIT 1
                       ) AS imagen_id
                FROM gestion__productos p
                WHERE p.tabla_estado_registro_id = 1
                $where_empresa
                ORDER BY p.producto_nombre
                LIMIT 200";

        $res = mysqli_query($conexion, $sql);

        if (!$res) {
            echo json_encode(['error_bd' => mysqli_error($conexion), 'sql' => $sql]);
            exit;
        }

        $productos = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $imagen_url = !empty($row['imagen_id'])
                ? BASE_URL . '/modules/gestion/get_imagen.php?id=' . intval($row['imagen_id'])
                : null;

            $productos[] = [
                'id'         => intval($row['id']),
                'codigo'     => $row['codigo'],
                'nombre'     => $row['nombre'],
                'precio'     => floatval($row['precio']),
                'imagen_url' => $imagen_url,
            ];
        }

        echo json_encode([
            'data'       => $productos,
            'empresa_id' => $empresa_id,
            'total'      => count($productos),
        ]);
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

    default:
        echo json_encode(['resultado' => false, 'error' => 'Acción no válida']);
        break;
}