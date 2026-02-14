<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

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
    if (!$stmt)
        return [];

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

function obtenerInfoEstado($conexion, $estado_registro_id)
{
    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    if (in_array('estado_registro', $columns)) {
        $sql = "SELECT estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } elseif (in_array('nombre_estado', $columns)) {
        $sql = "SELECT nombre_estado as estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } elseif (in_array('descripcion', $columns)) {
        $sql = "SELECT descripcion as estado_registro, codigo_estandar 
                FROM conf__estados_registros 
                WHERE estado_registro_id = ?";
    } else {
        return [
            'estado_registro' => 'Estado ' . $estado_registro_id,
            'codigo_estandar' => 'ESTADO_' . $estado_registro_id
        ];
    }

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $estado_registro_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $info = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $info;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) ? 1 : 0
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
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }

    return [
        'nombre_funcion' => 'Nueva Orden',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return 1;
    }

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

function ejecutarTransicionEstado($conexion, $orden_compra_id, $accion_js, $empresa_idx, $pagina_id)
{
    $orden_compra_id = intval($orden_compra_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT orden_compra_id, tabla_estado_registro_id 
                  FROM gestion__ordenes_compra 
                  WHERE orden_compra_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$orden)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $orden['tabla_estado_registro_id'];

    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion)
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    $sql_update = "UPDATE gestion__ordenes_compra 
                   SET tabla_estado_registro_id = ? 
                   WHERE orden_compra_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $orden_compra_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

function obtenerOrdenesCompra($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }

    $sql = "SELECT oc.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia
            FROM gestion__ordenes_compra oc
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON oc.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            WHERE oc.empresa_id = ?
            ORDER BY oc.f_emision DESC, oc.orden_compra_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function agregarOrdenCompra($conexion, $data)
{
    $comprobante_letra = mysqli_real_escape_string($conexion, trim($data['comprobante_letra'] ?? ''));
    $comprobante_suc = mysqli_real_escape_string($conexion, trim($data['comprobante_suc'] ?? ''));
    $comprobante_nro = mysqli_real_escape_string($conexion, trim($data['comprobante_nro'] ?? ''));
    $f_emision = mysqli_real_escape_string($conexion, $data['f_emision'] ?? '');
    $direccion_entrega = mysqli_real_escape_string($conexion, trim($data['direccion_entrega'] ?? ''));
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));

    if (empty($comprobante_nro)) {
        return ['resultado' => false, 'error' => 'El número de comprobante es obligatorio'];
    }

    if (empty($f_emision)) {
        return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
    }

    if (empty($data['entidad_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un proveedor'];
    }

    if (empty($data['comprobante_tipo_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
    }

    if (empty($data['moneda_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la moneda'];
    }

    if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    $sql_check = "SELECT COUNT(*) as total FROM gestion__ordenes_compra 
                  WHERE comprobante_letra = ? AND comprobante_suc = ? AND comprobante_nro = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssi", $comprobante_letra, $comprobante_suc, $comprobante_nro, $data['empresa_idx']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una orden con este número de comprobante'];
    }

    $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? "'" . mysqli_real_escape_string($conexion, $data['f_entrega_estimada']) . "'" : "NULL";
    $condicion_pago_id = (!empty($data['condicion_pago_id'])) ? intval($data['condicion_pago_id']) : "NULL";
    $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : "NULL";

    $sql = "INSERT INTO gestion__ordenes_compra 
            (empresa_id, comprobante_tipo_id, comprobante_letra, comprobante_suc, comprobante_nro, entidad_id, entidad_sucursal_id, 
             f_emision, f_entrega_estimada, condicion_pago_id, moneda_id, tipo_cambio, 
             direccion_entrega, subtotal, descuentos, impuestos, total, observaciones, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, $f_entrega_estimada, $condicion_pago_id, ?, $tipo_cambio, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iissiisdsdddddsi",
        $data['empresa_idx'],
        $data['comprobante_tipo_id'],
        $comprobante_letra,
        $comprobante_suc,
        $comprobante_nro,
        $data['entidad_id'],
        $data['entidad_sucursal_id'],
        $f_emision,
        $data['moneda_id'],
        $direccion_entrega,
        $data['subtotal'],
        $data['descuentos'],
        $data['impuestos'],
        $data['total'],
        $observaciones,
        $estado_inicial
    );

    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $orden_compra_id = mysqli_insert_id($conexion);
        
        $detalles_success = insertarDetallesOrden($conexion, $orden_compra_id, $data['empresa_idx'], $data['detalles']);
        
        if ($detalles_success) {
            mysqli_stmt_close($stmt);
            return ['resultado' => true, 'orden_compra_id' => $orden_compra_id];
        } else {
            mysqli_stmt_close($stmt);
            $sql_delete = "DELETE FROM gestion__ordenes_compra WHERE orden_compra_id = ?";
            $stmt_delete = mysqli_prepare($conexion, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $orden_compra_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);
            
            return ['resultado' => false, 'error' => 'Error al insertar los detalles'];
        }
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la orden de compra'];
    }
}

function insertarDetallesOrden($conexion, $orden_compra_id, $empresa_id, $detalles)
{
    foreach ($detalles as $detalle) {
        $sql = "INSERT INTO gestion__ordenes_compra_detalle 
                (orden_compra_id, empresa_id, producto_id, cantidad, cantidad_recibida, 
                 precio_unitario, neto_gravado, iva_alicuota_id, iva_porcentaje, iva_importe, total_linea) 
                VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt)
            return false;
        
        mysqli_stmt_bind_param($stmt, "iiidddddii",
            $orden_compra_id,
            $empresa_id,
            $detalle['producto_id'],
            $detalle['cantidad'],
            $detalle['precio_unitario'],
            $detalle['neto_gravado'],
            $detalle['iva_alicuota_id'],
            $detalle['iva_porcentaje'],
            $detalle['iva_importe'],
            $detalle['total_linea']
        );
        
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$success)
            return false;
    }
    
    return true;
}

function editarOrdenCompra($conexion, $id, $data)
{
    $id = intval($id);
    $comprobante_letra = mysqli_real_escape_string($conexion, trim($data['comprobante_letra'] ?? ''));
    $comprobante_suc = mysqli_real_escape_string($conexion, trim($data['comprobante_suc'] ?? ''));
    $comprobante_nro = mysqli_real_escape_string($conexion, trim($data['comprobante_nro'] ?? ''));
    $f_emision = mysqli_real_escape_string($conexion, $data['f_emision'] ?? '');
    $direccion_entrega = mysqli_real_escape_string($conexion, trim($data['direccion_entrega'] ?? ''));
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));

    if (empty($comprobante_nro)) {
        return ['resultado' => false, 'error' => 'El número de comprobante es obligatorio'];
    }

    if (empty($f_emision)) {
        return ['resultado' => false, 'error' => 'La fecha de emisión es obligatoria'];
    }

    if (empty($data['entidad_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un proveedor'];
    }

    if (empty($data['comprobante_tipo_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el tipo de comprobante'];
    }

    if (empty($data['moneda_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la moneda'];
    }

    if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
    }

    $sql_check = "SELECT orden_compra_id, tabla_estado_registro_id 
                  FROM gestion__ordenes_compra 
                  WHERE orden_compra_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$orden) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    $sql_estado = "SELECT codigo_estandar FROM conf__estados_registros 
                   WHERE estado_registro_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_estado);
    mysqli_stmt_bind_param($stmt, "i", $orden['tabla_estado_registro_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $estado_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($estado_info && in_array($estado_info['codigo_estandar'], ['CERRADO', 'CANCELADO'])) {
        return ['resultado' => false, 'error' => 'No se puede editar una orden ' . strtolower($estado_info['codigo_estandar'])];
    }

    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__ordenes_compra 
                      WHERE comprobante_letra = ? AND comprobante_suc = ? AND comprobante_nro = ? 
                      AND empresa_id = ?
                      AND orden_compra_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssii", $comprobante_letra, $comprobante_suc, $comprobante_nro, $data['empresa_idx'], $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra orden con este número de comprobante'];
    }

    $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? "'" . mysqli_real_escape_string($conexion, $data['f_entrega_estimada']) . "'" : "NULL";
    $condicion_pago_id = (!empty($data['condicion_pago_id'])) ? intval($data['condicion_pago_id']) : "NULL";
    $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : "NULL";

    $sql = "UPDATE gestion__ordenes_compra 
            SET comprobante_tipo_id = ?, 
                comprobante_letra = ?, 
                comprobante_suc = ?, 
                comprobante_nro = ?, 
                entidad_id = ?, 
                entidad_sucursal_id = ?, 
                f_emision = ?, 
                f_entrega_estimada = $f_entrega_estimada, 
                condicion_pago_id = $condicion_pago_id, 
                moneda_id = ?, 
                tipo_cambio = $tipo_cambio, 
                direccion_entrega = ?, 
                subtotal = ?, 
                descuentos = ?, 
                impuestos = ?, 
                total = ?, 
                observaciones = ?
            WHERE orden_compra_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "isssiisdddddddsi",
        $data['comprobante_tipo_id'],
        $comprobante_letra,
        $comprobante_suc,
        $comprobante_nro,
        $data['entidad_id'],
        $data['entidad_sucursal_id'],
        $f_emision,
        $data['moneda_id'],
        $direccion_entrega,
        $data['subtotal'],
        $data['descuentos'],
        $data['impuestos'],
        $data['total'],
        $observaciones,
        $id
    );

    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $sql_delete = "DELETE FROM gestion__ordenes_compra_detalle WHERE orden_compra_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        $detalles_success = insertarDetallesOrden($conexion, $id, $data['empresa_idx'], $data['detalles']);

        if ($detalles_success) {
            mysqli_stmt_close($stmt);
            return ['resultado' => true];
        } else {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'Error al actualizar los detalles'];
        }
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al actualizar la orden de compra'];
    }
}

function obtenerOrdenCompraPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);

    $sql_check = "SHOW COLUMNS FROM conf__estados_registros";
    $result = mysqli_query($conexion, $sql_check);
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $estado_column = 'estado_registro';
    if (!in_array('estado_registro', $columns)) {
        if (in_array('nombre_estado', $columns)) {
            $estado_column = 'nombre_estado';
        } elseif (in_array('descripcion', $columns)) {
            $estado_column = 'descripcion';
        }
    }

    $sql = "SELECT oc.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia
            FROM gestion__ordenes_compra oc
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON oc.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            WHERE oc.orden_compra_id = ? AND oc.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$orden) {
        return null;
    }

    $sql_detalles = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                            p.iva_alicuota_id as producto_iva_id
                     FROM gestion__ordenes_compra_detalle d
                     LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                     WHERE d.orden_compra_id = ?
                     ORDER BY d.ordenes_compra_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt)
        return $orden;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        // Formatear los detalles para que coincidan con lo que espera el frontend
        $detalles[] = [
            'ordenes_compra_detalle_id' => $detalle['ordenes_compra_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_nombre' => $detalle['producto_nombre'] . ' (' . $detalle['producto_codigo'] . ')',
            'cantidad' => floatval($detalle['cantidad']),
            'precio_unitario' => floatval($detalle['precio_unitario']),
            'iva_alicuota_id' => $detalle['producto_iva_id'] ?? $detalle['iva_alicuota_id'] ?? 1,
            'iva_porcentaje' => floatval($detalle['iva_porcentaje']),
            'neto_gravado' => floatval($detalle['neto_gravado']),
            'iva_importe' => floatval($detalle['iva_importe']),
            'total_linea' => floatval($detalle['total_linea']),
            'codigo_proveedor' => '' // Este campo se cargará dinámicamente
        ];
    }
    mysqli_stmt_close($stmt);

    $orden['detalles'] = $detalles;
    return $orden;
}

function obtenerComprobantesTipos($conexion)
{
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra 
            FROM gestion__comprobantes_tipos 
            WHERE comprobante_grupo_id = 2 
            AND comprobante_subgrupo_id = 3
            AND tabla_estado_registro_id = 1 
            ORDER BY comprobante_tipo";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    return $tipos;
}

function obtenerProveedores($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia 
            FROM gestion__entidades 
            WHERE empresa_id = ? 
            AND es_proveedor = 1
            AND tabla_estado_registro_id = 1 
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $proveedores = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $proveedores[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $proveedores;
}

function obtenerSucursales($conexion, $entidad_id, $empresa_idx)
{
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__entidades_sucursales 
            WHERE entidad_id = ? 
            AND empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $sucursales;
}

function obtenerCondicionesPago($conexion, $empresa_idx)
{
    $sql = "SELECT condicion_pago_id, codigo, condicion_pago, tipo 
            FROM gestion__condiciones_pago 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY orden, condicion_pago";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $condiciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $condiciones[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $condiciones;
}

function obtenerProductos($conexion, $empresa_idx)
{
    $sql = "SELECT producto_id, producto_codigo, producto_nombre 
            FROM gestion__productos 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY producto_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

function obtenerCodigoProveedor($conexion, $producto_id, $entidad_id, $empresa_id)
{
    $sql = "SELECT codigo_proveedor 
            FROM gestion__productos_proveedores 
            WHERE producto_id = ? 
            AND entidad_id = ? 
            AND empresa_id = ? 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return '';
    
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row ? $row['codigo_proveedor'] : '';
}

function obtenerCategoriasProductos($conexion, $empresa_idx)
{
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre as categoria_nombre
            FROM gestion__productos_categorias 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY producto_categoria_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $categorias = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $categorias[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $categorias;
}

function obtenerUnidadesMedida($conexion)
{
    $sql = "SELECT unidad_medida_id, unidad_nombre 
            FROM conf__unidades_medida 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY unidad_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $unidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $unidades[] = $fila;
    }
    
    return $unidades;
}

function agregarProductoRapido($conexion, $data)
{
    if (empty($data['producto_codigo']) || empty($data['producto_nombre'])) {
        return ['success' => false, 'error' => 'Código y nombre son obligatorios'];
    }
    
    if (empty($data['producto_categoria_id'])) {
        return ['success' => false, 'error' => 'La categoría es obligatoria'];
    }
    
    $sql_check = "SELECT COUNT(*) as total FROM gestion__productos 
                  WHERE producto_codigo = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "si", $data['producto_codigo'], $data['empresa_idx']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['success' => false, 'error' => 'Ya existe un producto con este código'];
    }
    
    $sql = "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, codigo_barras, 
             producto_descripcion, producto_categoria_id, producto_tipo_id, 
             iva_alicuota_id, unidad_medida_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 1)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "issssiii", 
        $data['empresa_idx'],
        $data['producto_codigo'],
        $data['producto_nombre'],
        $data['codigo_barras'],
        $data['producto_descripcion'],
        $data['producto_categoria_id'],
        $data['iva_alicuota_id'],
        $data['unidad_medida_id']
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $producto_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        
        if (!empty($data['codigo_proveedor']) && !empty($data['entidad_id'])) {
            $sql_proveedor = "INSERT INTO gestion__productos_proveedores 
                              (empresa_id, producto_id, entidad_id, codigo_proveedor, tabla_estado_registro_id) 
                              VALUES (?, ?, ?, ?, 1)";
            
            $stmt_proveedor = mysqli_prepare($conexion, $sql_proveedor);
            if ($stmt_proveedor) {
                mysqli_stmt_bind_param($stmt_proveedor, "iiis", 
                    $data['empresa_idx'],
                    $producto_id,
                    $data['entidad_id'],
                    $data['codigo_proveedor']
                );
                mysqli_stmt_execute($stmt_proveedor);
                mysqli_stmt_close($stmt_proveedor);
            }
        }
        
        return ['success' => true, 'producto_id' => $producto_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Error al crear el producto'];
    }
}
?>