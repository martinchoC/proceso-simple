<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
function obtenerPedidosClientes($conexion)
{
    $sql = "SELECT c.*, e.nombre as nombre_entidad 
            FROM `gestion__comprobantes` c
            LEFT JOIN `gestion__entidades` e ON c.entidad_id = e.entidad_id
            WHERE c.comprobante_tipo_id = 1  -- Asumiendo que 1 es el tipo para Pedidos de Clientes
            ORDER BY c.f_emision DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPedidoCliente($conexion, $data)
{
    mysqli_begin_transaction($conexion);

    try {
        // Insertar cabecera del comprobante
        $sql = "INSERT INTO `gestion__comprobantes` 
                (empresa_id, sucursal_id, comprobante_tipo_id, numero_comprobante, 
                 entidad_id, f_emision, f_contabilizacion, f_vto, observaciones, 
                 importe_neto, total, estado_registro_id, creado_por) 
                VALUES 
                (1, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)";

        $stmt = mysqli_prepare($conexion, $sql);
        $total = calcularTotalDetalles($data['detalles']);

        mysqli_stmt_bind_param(
            $stmt,
            "iissssdd",
            $data['numero_comprobante'],
            $data['entidad_id'],
            $data['f_emision'],
            $data['f_emision'], // f_contabilizacion misma que emisión
            $data['f_vto'],
            $data['observaciones'],
            $total,
            $total
        );

        mysqli_stmt_execute($stmt);
        $comprobante_id = mysqli_insert_id($conexion);

        // Insertar detalles
        foreach ($data['detalles'] as $detalle) {
            $sql_detalle = "INSERT INTO `gestion__comprobantes_detalles` 
                           (comprobante_id, producto_id, cantidad, precio_unitario, descuento) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
            mysqli_stmt_bind_param(
                $stmt_detalle,
                "iiddd",
                $comprobante_id,
                $detalle['producto_id'],
                $detalle['cantidad'],
                $detalle['precio_unitario'],
                $detalle['descuento']
            );
            mysqli_stmt_execute($stmt_detalle);
        }

        mysqli_commit($conexion);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function editarPedidoCliente($conexion, $id, $data)
{
    mysqli_begin_transaction($conexion);

    try {
        // Actualizar cabecera
        $sql = "UPDATE `gestion__comprobantes` SET
                numero_comprobante = ?,
                entidad_id = ?,
                f_emision = ?,
                f_vto = ?,
                observaciones = ?,
                importe_neto = ?,
                total = ?,
                actualizado_por = 1,
                actualizado_en = NOW()
                WHERE comprobante_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        $total = calcularTotalDetalles($data['detalles']);

        mysqli_stmt_bind_param(
            $stmt,
            "issssddi",
            $data['numero_comprobante'],
            $data['entidad_id'],
            $data['f_emision'],
            $data['f_vto'],
            $data['observaciones'],
            $total,
            $total,
            $id
        );

        mysqli_stmt_execute($stmt);

        // Eliminar detalles existentes
        $sql_delete = "DELETE FROM `gestion__comprobantes_detalles` WHERE comprobante_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        mysqli_stmt_execute($stmt_delete);

        // Insertar nuevos detalles
        foreach ($data['detalles'] as $detalle) {
            $sql_detalle = "INSERT INTO `gestion__comprobantes_detalles` 
                           (comprobante_id, producto_id, cantidad, precio_unitario, descuento) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
            mysqli_stmt_bind_param(
                $stmt_detalle,
                "iiddd",
                $id,
                $detalle['producto_id'],
                $detalle['cantidad'],
                $detalle['precio_unitario'],
                $detalle['descuento']
            );
            mysqli_stmt_execute($stmt_detalle);
        }

        mysqli_commit($conexion);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function calcularTotalDetalles($detalles)
{
    $total = 0;
    foreach ($detalles as $detalle) {
        $subtotal = ($detalle['cantidad'] * $detalle['precio_unitario']) - $detalle['descuento'];
        $total += $subtotal;
    }
    return $total;
}

function cambiarEstadoPedidoCliente($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $sql = "UPDATE `gestion__comprobantes` 
            SET estado_registro_id = ? 
            WHERE comprobante_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id);
    return mysqli_stmt_execute($stmt);
}

function eliminarPedidoCliente($conexion, $id)
{
    $id = intval($id);

    mysqli_begin_transaction($conexion);
    try {
        // Eliminar detalles primero
        $sql_detalles = "DELETE FROM `gestion__comprobantes_detalles` WHERE comprobante_id = ?";
        $stmt_detalles = mysqli_prepare($conexion, $sql_detalles);
        mysqli_stmt_bind_param($stmt_detalles, "i", $id);
        mysqli_stmt_execute($stmt_detalles);

        // Eliminar cabecera
        $sql = "DELETE FROM `gestion__comprobantes` WHERE comprobante_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $result = mysqli_stmt_execute($stmt);

        mysqli_commit($conexion);
        return $result;
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function obtenerPedidoClientePorId($conexion, $id)
{
    $id = intval($id);

    // Obtener cabecera
    $sql = "SELECT * FROM `gestion__comprobantes` WHERE comprobante_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cabecera = mysqli_fetch_assoc($result);

    // Obtener detalles
    $sql_detalles = "SELECT * FROM `gestion__comprobantes_detalles` WHERE comprobante_id = ?";
    $stmt_detalles = mysqli_prepare($conexion, $sql_detalles);
    mysqli_stmt_bind_param($stmt_detalles, "i", $id);
    mysqli_stmt_execute($stmt_detalles);
    $result_detalles = mysqli_stmt_get_result($stmt_detalles);

    $detalles = [];
    while ($fila = mysqli_fetch_assoc($result_detalles)) {
        $detalles[] = $fila;
    }

    return [
        'cabecera' => $cabecera,
        'detalles' => $detalles
    ];
}

function obtenerClientes($conexion)
{
    $sql = "SELECT entidad_id, nombre FROM `gestion__entidades` WHERE tipo_entidad_id = 1 AND estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion)
{
    $sql = "SELECT producto_id, nombre FROM `gestion__productos` WHERE estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>