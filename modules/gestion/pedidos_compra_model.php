<?php
require_once "conexion.php";

function obtenerPedidosCompra($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT 
                c.*,
                ct.comprobante_tipo,
                e.razon_social as proveedor_nombre,
                s.sucursal,
                u1.nombre as creado_por_nombre,
                u2.nombre as actualizado_por_nombre
            FROM `gestion__comprobantes` c
            INNER JOIN `gestion__comprobantes_tipos` ct ON c.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN `gestion__entidades` e ON c.entidad_id = e.entidad_id
            LEFT JOIN `gestion__sucursales` s ON c.sucursal_id = s.sucursal_id
            LEFT JOIN `gestion__usuarios` u1 ON c.creado_por = u1.usuario_id
            LEFT JOIN `gestion__usuarios` u2 ON c.actualizado_por = u2.usuario_id
            WHERE c.empresa_id = $empresa_id 
            AND ct.comprobante_tipo LIKE '%pedido%compra%'
            ORDER BY c.f_emision DESC, c.comprobante_id DESC";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTiposComprobantePedido($conexion) {
    $sql = "SELECT * FROM `gestion__comprobantes_tipos` 
            WHERE comprobante_tipo LIKE '%pedido%compra%'
            AND tabla_estado_registro_id = 1
            ORDER BY comprobante_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProveedores($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT entidad_id, razon_social, documento 
            FROM `gestion__entidades` 
            WHERE empresa_id = $empresa_id 
            AND es_proveedor = 1
            AND tabla_estado_registro_id = 1
            ORDER BY razon_social";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerSucursales($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT sucursal_id, sucursal 
            FROM `gestion__sucursales` 
            WHERE empresa_id = $empresa_id 
            AND tabla_estado_registro_id = 1
            ORDER BY sucursal";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProductos($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT producto_id, producto, codigo, precio_compra 
            FROM `gestion__productos` 
            WHERE empresa_id = $empresa_id 
            AND tabla_estado_registro_id = 1
            ORDER BY producto";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPedidoCompra($conexion, $data, $detalles, $usuario_id) {
    // Iniciar transacción
    mysqli_begin_transaction($conexion);
    
    try {
        // Insertar cabecera del comprobante
        $sql = "INSERT INTO `gestion__comprobantes` 
                (empresa_id, sucursal_id, punto_venta_id, comprobante_tipo_id, 
                 numero_comprobante, entidad_id, f_emision, f_contabilizacion, f_vto,
                 observaciones, importe_neto, importe_no_gravado, total,
                 tabla_estado_registro_id, creado_por, creado_en) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiisssssddii", 
            $data['empresa_id'],
            $data['sucursal_id'],
            $data['punto_venta_id'],
            $data['comprobante_tipo_id'],
            $data['numero_comprobante'],
            $data['entidad_id'],
            $data['f_emision'],
            $data['f_contabilizacion'],
            $data['f_vto'],
            $data['observaciones'],
            $data['importe_neto'],
            $data['importe_no_gravado'],
            $data['total'],
            $data['tabla_estado_registro_id'],
            $usuario_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error al insertar cabecera del pedido");
        }
        
        $comprobante_id = mysqli_insert_id($conexion);
        
        // Insertar detalles
        foreach ($detalles as $detalle) {
            $sql_detalle = "INSERT INTO `gestion__comprobantes_detalles` 
                           (comprobante_id, producto_id, cantidad, precio_unitario, descuento) 
                           VALUES (?, ?, ?, ?, ?)";
            
            $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
            mysqli_stmt_bind_param($stmt_detalle, "iiddd", 
                $comprobante_id,
                $detalle['producto_id'],
                $detalle['cantidad'],
                $detalle['precio_unitario'],
                $detalle['descuento']
            );
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception("Error al insertar detalle del pedido");
            }
        }
        
        // Confirmar transacción
        mysqli_commit($conexion);
        return $comprobante_id;
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function editarPedidoCompra($conexion, $comprobante_id, $data, $detalles, $usuario_id) {
    mysqli_begin_transaction($conexion);
    
    try {
        // Actualizar cabecera
        $sql = "UPDATE `gestion__comprobantes` SET
                sucursal_id = ?,
                punto_venta_id = ?,
                comprobante_tipo_id = ?,
                numero_comprobante = ?,
                entidad_id = ?,
                f_emision = ?,
                f_contabilizacion = ?,
                f_vto = ?,
                observaciones = ?,
                importe_neto = ?,
                importe_no_gravado = ?,
                total = ?,
                tabla_estado_registro_id = ?,
                actualizado_por = ?,
                actualizado_en = NOW()
                WHERE comprobante_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiissssssdddiii", 
            $data['sucursal_id'],
            $data['punto_venta_id'],
            $data['comprobante_tipo_id'],
            $data['numero_comprobante'],
            $data['entidad_id'],
            $data['f_emision'],
            $data['f_contabilizacion'],
            $data['f_vto'],
            $data['observaciones'],
            $data['importe_neto'],
            $data['importe_no_gravado'],
            $data['total'],
            $data['tabla_estado_registro_id'],
            $usuario_id,
            $comprobante_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error al actualizar cabecera del pedido");
        }
        
        // Eliminar detalles existentes
        $sql_delete = "DELETE FROM `gestion__comprobantes_detalles` WHERE comprobante_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $comprobante_id);
        
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Error al eliminar detalles anteriores");
        }
        
        // Insertar nuevos detalles
        foreach ($detalles as $detalle) {
            $sql_detalle = "INSERT INTO `gestion__comprobantes_detalles` 
                           (comprobante_id, producto_id, cantidad, precio_unitario, descuento) 
                           VALUES (?, ?, ?, ?, ?)";
            
            $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
            mysqli_stmt_bind_param($stmt_detalle, "iiddd", 
                $comprobante_id,
                $detalle['producto_id'],
                $detalle['cantidad'],
                $detalle['precio_unitario'],
                $detalle['descuento']
            );
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception("Error al insertar detalle del pedido");
            }
        }
        
        mysqli_commit($conexion);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function cambiarEstadoPedidoCompra($conexion, $comprobante_id, $nuevo_estado, $usuario_id) {
    $sql = "UPDATE `gestion__comprobantes` 
            SET tabla_estado_registro_id = ?, actualizado_por = ?, actualizado_en = NOW()
            WHERE comprobante_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $comprobante_id);
    return mysqli_stmt_execute($stmt);
}

function eliminarPedidoCompra($conexion, $comprobante_id) {
    mysqli_begin_transaction($conexion);
    
    try {
        // Eliminar detalles primero
        $sql_detalles = "DELETE FROM `gestion__comprobantes_detalles` WHERE comprobante_id = ?";
        $stmt_detalles = mysqli_prepare($conexion, $sql_detalles);
        mysqli_stmt_bind_param($stmt_detalles, "i", $comprobante_id);
        
        if (!mysqli_stmt_execute($stmt_detalles)) {
            throw new Exception("Error al eliminar detalles");
        }
        
        // Eliminar cabecera
        $sql_cabecera = "DELETE FROM `gestion__comprobantes` WHERE comprobante_id = ?";
        $stmt_cabecera = mysqli_prepare($conexion, $sql_cabecera);
        mysqli_stmt_bind_param($stmt_cabecera, "i", $comprobante_id);
        
        if (!mysqli_stmt_execute($stmt_cabecera)) {
            throw new Exception("Error al eliminar cabecera");
        }
        
        mysqli_commit($conexion);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return false;
    }
}

function obtenerPedidoCompraPorId($conexion, $comprobante_id) {
    $sql = "SELECT * FROM `gestion__comprobantes` WHERE comprobante_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function obtenerDetallesPedidoCompra($conexion, $comprobante_id) {
    $sql = "SELECT cd.*, p.producto, p.codigo 
            FROM `gestion__comprobantes_detalles` cd
            LEFT JOIN `gestion__productos` p ON cd.producto_id = p.producto_id
            WHERE cd.comprobante_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $comprobante_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerProximoNumeroComprobante($conexion, $comprobante_tipo_id, $punto_venta_id) {
    $sql = "SELECT COALESCE(MAX(numero_comprobante), 0) + 1 as proximo_numero
            FROM `gestion__comprobantes` 
            WHERE comprobante_tipo_id = ? AND punto_venta_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $comprobante_tipo_id, $punto_venta_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    return $fila['proximo_numero'];
}
?>