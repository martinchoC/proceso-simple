<?php
ob_start();

require_once __DIR__ . '/../../config.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {

    case 'obtener_catalogo':
        $empresa_id    = intval($_REQUEST['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
        $where_empresa = $empresa_id > 0 ? "AND p.empresa_id = $empresa_id" : '';

        $sql = "SELECT p.producto_id AS id,
                       p.producto_codigo AS codigo,
                       p.producto_nombre AS nombre,
                       p.empresa_id,
                       COALESCE((
                           SELECT lpp.precio_unitario
                           FROM gestion__listas_precios_productos lpp
                           WHERE lpp.producto_id = p.producto_id
                           ORDER BY lpp.lista_precio_producto_id DESC
                           LIMIT 1
                       ), 0) AS precio
                FROM gestion__productos p
                WHERE p.tabla_estado_registro_id = 1
                $where_empresa
                ORDER BY p.producto_nombre
                LIMIT 200";

        $res = mysqli_query($conexion, $sql);

        if (!$res) {
            ob_clean();
            echo json_encode(['error_bd' => mysqli_error($conexion)]);
            exit;
        }

        $productos = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $productos[] = [
                'id'     => intval($row['id']),
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'precio' => floatval($row['precio']),
            ];
        }

        ob_clean();
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
        ob_clean();
        echo json_encode([
            'distribucion'        => $rows,
            'empresa_id_recibido' => intval($_REQUEST['empresa_id'] ?? 0),
        ]);
        break;

    case 'guardar_pedido_carrito':
        $detalles_json = $_POST['detalles'] ?? '[]';
        $detalles      = json_decode($detalles_json, true);
        $usuario_id    = $_SESSION['usuario_id'] ?? 1;

        if (empty($detalles)) {
            ob_clean();
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

                $sql_detalle = "INSERT INTO gestion__comprobantes_detalles
                                (comprobante_id, producto_id, cantidad, precio_unitario, subtotal)
                                VALUES ($comprobante_id, $prod_id, $cant, $precio, $subtotal)";

                if (!mysqli_query($conexion, $sql_detalle)) {
                    throw new Exception("Error en detalle ID $prod_id: " . mysqli_error($conexion));
                }
            }

            mysqli_commit($conexion);
            ob_clean();
            echo json_encode([
                'resultado'      => true,
                'mensaje'        => 'Pedido generado con éxito.',
                'comprobante_id' => $comprobante_id,
            ]);

        } catch (Exception $e) {
            mysqli_rollback($conexion);
            ob_clean();
            echo json_encode(['resultado' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        ob_clean();
        echo json_encode(['resultado' => false, 'error' => 'Acción no válida']);
        break;
}
