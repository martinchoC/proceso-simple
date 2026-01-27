<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de órdenes de compra
 */

// Función para obtener funciones de página (reutilizada del modelo anterior)
function obtenerFuncionesPagina($conexion, $pagina_id) {
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

// Obtener botón "Agregar"
function obtenerBotonAgregar($conexion, $pagina_id) {
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

// Obtener botones por estado
function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id) {
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

// Obtener todas las órdenes de compra (actualizada)
function obtenerOrdenesCompra($conexion, $empresa_idx, $pagina_id) {
    $sql = "SELECT oc.*, 
                   cf.comprobante_fiscal,
                   e.entidad_nombre,
                   e.cuit,
                   es.sucursal_nombre,
                   es.sucursal_direccion,
                   er.estado_registro,
                   er.codigo_estandar,
                   m.moneda_nombre,
                   m.moneda_simbolo,
                   CONCAT('OC-', LPAD(oc.orden_compra_id, 6, '0')) as numero_orden
            FROM gestion__ordenes_compra oc
            LEFT JOIN gestion__comprobantes_fiscales cf ON oc.comprobante_id = cf.comprobante_fiscal_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            LEFT JOIN gestion__entidades_sucursales es ON oc.entidad_sucursal_id = es.sucursal_id
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__monedas m ON oc.moneda_id = m.moneda_id
            WHERE oc.tabla_estado_registro_id > 0
            ORDER BY oc.orden_compra_id DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Construir nombre completo del proveedor
        $proveedorNombre = $fila['entidad_nombre'];
        if ($fila['sucursal_nombre']) {
            $proveedorNombre .= ' - ' . $fila['sucursal_nombre'];
            if ($fila['sucursal_direccion']) {
                $proveedorNombre .= ' (' . $fila['sucursal_direccion'] . ')';
            }
        }
        
        $fila['proveedor_nombre'] = $proveedorNombre;
        $fila['proveedor_ruc'] = $fila['cuit'];
        
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO'
        ];
        
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// Obtener orden de compra por ID con detalles
function obtenerOrdenCompraPorId($conexion, $orden_compra_id, $empresa_idx) {
    $orden_compra_id = intval($orden_compra_id);
    
    // Obtener datos principales
    $sql = "SELECT oc.*, 
                   cf.comprobante_fiscal,
                   e.entidad_id,
                   e.entidad_nombre,
                   e.entidad_fantasia,
                   e.cuit,
                   es.sucursal_id as entidad_sucursal_id,
                   es.sucursal_nombre,
                   es.sucursal_direccion,
                   er.estado_registro,
                   cp.descripcion as condicion_pago,
                   m.moneda_nombre,
                   d.deposito_nombre
            FROM gestion__ordenes_compra oc
            LEFT JOIN gestion__comprobantes_fiscales cf ON oc.comprobante_id = cf.comprobante_fiscal_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            LEFT JOIN gestion__entidades_sucursales es ON oc.entidad_sucursal_id = es.sucursal_id
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__condiciones_pago cp ON oc.condicion_pago_id = cp.condicion_pago_id
            LEFT JOIN conf__monedas m ON oc.moneda_id = m.moneda_id
            LEFT JOIN gestion__depositos d ON oc.deposito_destino_id = d.deposito_id
            WHERE oc.orden_compra_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$orden) return null;
    
    // Usar nombre fantasia si existe
    if ($orden['entidad_fantasia']) {
        $orden['entidad_nombre'] = $orden['entidad_fantasia'];
    }
    
    // Obtener detalles
    $sql_detalles = "SELECT ocd.*, 
                            p.producto_codigo,
                            p.producto_nombre,
                            i.descripcion as impuesto_descripcion
                     FROM gestion__ordenes_compra_detalles ocd
                     LEFT JOIN gestion__productos p ON ocd.producto_id = p.producto_id
                     LEFT JOIN conf__impuestos i ON ocd.impuesto_id = i.impuesto_id
                     WHERE ocd.orden_compra_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt) return ['orden' => $orden, 'detalles' => []];
    
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = $detalle;
    }
    mysqli_stmt_close($stmt);
    
    return ['orden' => $orden, 'detalles' => $detalles];
}

// Obtener detalle para visualización
function obtenerDetalleOrdenCompra($conexion, $orden_compra_id, $empresa_idx) {
    return obtenerOrdenCompraPorId($conexion, $orden_compra_id, $empresa_idx);
}

// Agregar nueva orden de compra
function agregarOrdenCompra($conexion, $data, $empresa_idx, $pagina_id) {
    // Validaciones básicas
    if (empty($data['comprobante_id']) || empty($data['entidad_id']) || empty($data['fecha_emision'])) {
        return ['resultado' => false, 'error' => 'Datos incompletos'];
    }
    
    // Validar que la entidad sea proveedor
    $sql_check_proveedor = "SELECT entidad_id, entidad_nombre
                            FROM gestion__entidades 
                            WHERE entidad_id = ? 
                            AND es_proveedor = 1
                            AND tabla_estado_registro_id = 1";
    
    $stmt_check = mysqli_prepare($conexion, $sql_check_proveedor);
    if (!$stmt_check) return ['resultado' => false, 'error' => 'Error validando proveedor'];
    
    mysqli_stmt_bind_param($stmt_check, "i", $data['entidad_id']);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    $proveedor = mysqli_fetch_assoc($stmt_check);
    mysqli_stmt_close($stmt_check);
    
    if (!$proveedor) {
        return ['resultado' => false, 'error' => 'La entidad seleccionada no es un proveedor válido'];
    }
    
    // Validar sucursal si fue seleccionada
    if (!empty($data['entidad_sucursal_id'])) {
        $sql_check_sucursal = "SELECT sucursal_id 
                               FROM gestion__entidades_sucursales 
                               WHERE sucursal_id = ? 
                               AND entidad_id = ?
                               AND tabla_estado_registro_id = 1";
        
        $stmt_suc = mysqli_prepare($conexion, $sql_check_sucursal);
        if (!$stmt_suc) return ['resultado' => false, 'error' => 'Error validando sucursal'];
        
        mysqli_stmt_bind_param($stmt_suc, "ii", $data['entidad_sucursal_id'], $data['entidad_id']);
        mysqli_stmt_execute($stmt_suc);
        $result = mysqli_stmt_get_result($stmt_suc);
        $sucursal = mysqli_fetch_assoc($stmt_suc);
        mysqli_stmt_close($stmt_suc);
        
        if (!$sucursal) {
            return ['resultado' => false, 'error' => 'La sucursal seleccionada no pertenece al proveedor'];
        }
    }
    
    // Obtener estado inicial
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    mysqli_begin_transaction($conexion);
    
    try {
        // Insertar orden principal
        $sql_orden = "INSERT INTO gestion__ordenes_compra (
                        comprobante_id, entidad_id, entidad_sucursal_id, 
                        fecha_emision, fecha_entrega_estimada,
                        condicion_pago_id, moneda_id, tipo_cambio, deposito_destino_id,
                        direccion_entrega, subtotal, descuentos, impuestos, total,
                        observaciones, tabla_estado_registro_id, usuario_creacion_id
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_orden = mysqli_prepare($conexion, $sql_orden);
        if (!$stmt_orden) throw new Exception('Error preparando consulta de orden');
        
        mysqli_stmt_bind_param($stmt_orden, "iiissiisdssddddsii",
            $data['comprobante_id'],
            $data['entidad_id'],
            $data['entidad_sucursal_id'] ?: null,
            $data['fecha_emision'],
            $data['fecha_entrega_estimada'] ?: null,
            $data['condicion_pago_id'] ?: null,
            $data['moneda_id'],
            $data['tipo_cambio'] ?: 1.0,
            $data['deposito_destino_id'] ?: null,
            $data['direccion_entrega'] ?: '',
            $data['subtotal'],
            $data['descuentos'],
            $data['impuestos'],
            $data['total'],
            $data['observaciones'] ?: '',
            $estado_inicial,
            $data['usuario_creacion_id']
        );
        
        if (!mysqli_stmt_execute($stmt_orden)) {
            throw new Exception('Error insertando orden: ' . mysqli_stmt_error($stmt_orden));
        }
        
        $orden_compra_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_orden);
        
        // Insertar detalles
        $detalles = json_decode($data['detalles'], true);
        if (empty($detalles) || !is_array($detalles)) {
            throw new Exception('No hay detalles de productos');
        }
        
        $sql_detalle = "INSERT INTO gestion__ordenes_compra_detalles (
                          orden_compra_id, producto_id, descripcion, cantidad_pedida,
                          precio_unitario, descuento, impuesto_id, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
        if (!$stmt_detalle) throw new Exception('Error preparando consulta de detalle');
        
        foreach ($detalles as $detalle) {
            $subtotal = $detalle['cantidad_pedida'] * $detalle['precio_unitario'] * (1 - ($detalle['descuento'] / 100));
            
            mysqli_stmt_bind_param($stmt_detalle, "iisdiddd",
                $orden_compra_id,
                $detalle['producto_id'],
                $detalle['descripcion'] ?: null,
                $detalle['cantidad_pedida'],
                $detalle['precio_unitario'],
                $detalle['descuento'] ?: 0,
                $detalle['impuesto_id'] ?: null,
                $subtotal
            );
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception('Error insertando detalle: ' . mysqli_stmt_error($stmt_detalle));
            }
        }
        
        mysqli_stmt_close($stmt_detalle);
        
        mysqli_commit($conexion);
        
        return [
            'resultado' => true,
            'orden_compra_id' => $orden_compra_id,
            'numero_orden' => 'OC-' . str_pad($orden_compra_id, 6, '0', STR_PAD_LEFT)
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Editar orden de compra existente
function editarOrdenCompra($conexion, $data, $empresa_idx, $pagina_id) {
    $orden_compra_id = intval($data['orden_compra_id']);
    
    if (empty($orden_compra_id)) {
        return ['resultado' => false, 'error' => 'ID de orden no válido'];
    }
    
    // Verificar que la orden existe
    $sql_check = "SELECT orden_compra_id, tabla_estado_registro_id 
                  FROM gestion__ordenes_compra 
                  WHERE orden_compra_id = ?";
    
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if (!$stmt_check) return ['resultado' => false, 'error' => 'Error en verificación'];
    
    mysqli_stmt_bind_param($stmt_check, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_check);
    
    if (!$orden) {
        return ['resultado' => false, 'error' => 'Orden no encontrada'];
    }
    
    // Verificar que la orden esté en estado editable (BORRADOR o similar)
    if ($orden['tabla_estado_registro_id'] != 1) { // Asumiendo 1 = BORRADOR
        return ['resultado' => false, 'error' => 'La orden no está en estado editable'];
    }
    
    // Validar sucursal si fue seleccionada
    if (!empty($data['entidad_sucursal_id'])) {
        $sql_check_sucursal = "SELECT sucursal_id 
                               FROM gestion__entidades_sucursales 
                               WHERE sucursal_id = ? 
                               AND entidad_id = ?
                               AND tabla_estado_registro_id = 1";
        
        $stmt_suc = mysqli_prepare($conexion, $sql_check_sucursal);
        if (!$stmt_suc) return ['resultado' => false, 'error' => 'Error validando sucursal'];
        
        mysqli_stmt_bind_param($stmt_suc, "ii", $data['entidad_sucursal_id'], $data['entidad_id']);
        mysqli_stmt_execute($stmt_suc);
        $result = mysqli_stmt_get_result($stmt_suc);
        $sucursal = mysqli_fetch_assoc($stmt_suc);
        mysqli_stmt_close($stmt_suc);
        
        if (!$sucursal) {
            return ['resultado' => false, 'error' => 'La sucursal seleccionada no pertenece al proveedor'];
        }
    }
    
    mysqli_begin_transaction($conexion);
    
    try {
        // Actualizar orden principal
        $sql_orden = "UPDATE gestion__ordenes_compra SET
                        comprobante_id = ?,
                        entidad_id = ?,
                        entidad_sucursal_id = ?,
                        fecha_emision = ?,
                        fecha_entrega_estimada = ?,
                        condicion_pago_id = ?,
                        moneda_id = ?,
                        tipo_cambio = ?,
                        deposito_destino_id = ?,
                        direccion_entrega = ?,
                        subtotal = ?,
                        descuentos = ?,
                        impuestos = ?,
                        total = ?,
                        observaciones = ?
                      WHERE orden_compra_id = ?";
        
        $stmt_orden = mysqli_prepare($conexion, $sql_orden);
        if (!$stmt_orden) throw new Exception('Error preparando actualización de orden');
        
        mysqli_stmt_bind_param($stmt_orden, "iiissiisdssddddsi",
            $data['comprobante_id'],
            $data['entidad_id'],
            $data['entidad_sucursal_id'] ?: null,
            $data['fecha_emision'],
            $data['fecha_entrega_estimada'] ?: null,
            $data['condicion_pago_id'] ?: null,
            $data['moneda_id'],
            $data['tipo_cambio'] ?: 1.0,
            $data['deposito_destino_id'] ?: null,
            $data['direccion_entrega'] ?: '',
            $data['subtotal'],
            $data['descuentos'],
            $data['impuestos'],
            $data['total'],
            $data['observaciones'] ?: '',
            $orden_compra_id
        );
        
        if (!mysqli_stmt_execute($stmt_orden)) {
            throw new Exception('Error actualizando orden: ' . mysqli_stmt_error($stmt_orden));
        }
        
        mysqli_stmt_close($stmt_orden);
        
        // Eliminar detalles existentes
        $sql_delete = "DELETE FROM gestion__ordenes_compra_detalles WHERE orden_compra_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if (!$stmt_delete) throw new Exception('Error preparando eliminación de detalles');
        
        mysqli_stmt_bind_param($stmt_delete, "i", $orden_compra_id);
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception('Error eliminando detalles: ' . mysqli_stmt_error($stmt_delete));
        }
        mysqli_stmt_close($stmt_delete);
        
        // Insertar nuevos detalles
        $detalles = json_decode($data['detalles'], true);
        if (empty($detalles) || !is_array($detalles)) {
            throw new Exception('No hay detalles de productos');
        }
        
        $sql_detalle = "INSERT INTO gestion__ordenes_compra_detalles (
                          orden_compra_id, producto_id, descripcion, cantidad_pedida,
                          precio_unitario, descuento, impuesto_id, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
        if (!$stmt_detalle) throw new Exception('Error preparando inserción de detalles');
        
        foreach ($detalles as $detalle) {
            $subtotal = $detalle['cantidad_pedida'] * $detalle['precio_unitario'] * (1 - ($detalle['descuento'] / 100));
            
            mysqli_stmt_bind_param($stmt_detalle, "iisdiddd",
                $orden_compra_id,
                $detalle['producto_id'],
                $detalle['descripcion'] ?: null,
                $detalle['cantidad_pedida'],
                $detalle['precio_unitario'],
                $detalle['descuento'] ?: 0,
                $detalle['impuesto_id'] ?: null,
                $subtotal
            );
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception('Error insertando detalle: ' . mysqli_stmt_error($stmt_detalle));
            }
        }
        
        mysqli_stmt_close($stmt_detalle);
        
        mysqli_commit($conexion);
        
        return ['resultado' => true];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $orden_compra_id, $accion_js, $empresa_idx, $pagina_id) {
    $orden_compra_id = intval($orden_compra_id);
    $pagina_id = intval($pagina_id);
    
    // Obtener estado actual
    $sql_check = "SELECT tabla_estado_registro_id 
                  FROM gestion__ordenes_compra 
                  WHERE orden_compra_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$orden) return ['success' => false, 'error' => 'Orden no encontrada'];
    
    $estado_actual_id = $orden['tabla_estado_registro_id'];
    
    // Buscar la función correspondiente
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$funcion) return ['success' => false, 'error' => 'Acción no permitida para este estado'];
    
    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];
    
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }
    
    // Actualizar el estado
    $sql_update = "UPDATE gestion__ordenes_compra 
                   SET tabla_estado_registro_id = ? 
                   WHERE orden_compra_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt) return ['success' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $orden_compra_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener estado inicial
function obtenerEstadoInicial($conexion) {
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE codigo_estandar = 'BORRADOR' 
            OR valor_estandar = 1
            LIMIT 1";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) return 1;
    
    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// Funciones para cargar combos

// Cargar proveedores con sus sucursales (un solo array combinado)
function cargarProveedoresConSucursales($conexion, $empresa_idx) {
    $resultados = [];
    
    // Primero obtener las entidades que son proveedores
    $sql_entidades = "SELECT entidad_id, entidad_nombre, entidad_fantasia, cuit
                      FROM gestion__entidades
                      WHERE empresa_id = ? 
                      AND es_proveedor = 1
                      AND tabla_estado_registro_id = 1
                      ORDER BY entidad_nombre";
    
    $stmt_entidades = mysqli_prepare($conexion, $sql_entidades);
    if (!$stmt_entidades) return [];
    
    mysqli_stmt_bind_param($stmt_entidades, "i", $empresa_idx);
    mysqli_stmt_execute($stmt_entidades);
    $result_entidades = mysqli_stmt_get_result($stmt_entidades);
    
    while ($entidad = mysqli_fetch_assoc($result_entidades)) {
        // Usar nombre fantasia si existe
        $nombreEntidad = $entidad['entidad_fantasia'] ?: $entidad['entidad_nombre'];
        $cuitInfo = $entidad['cuit'] ? " ({$entidad['cuit']})" : "";
        
        // Agregar la entidad como opción principal
        $resultados[] = [
            'tipo' => 'entidad',
            'entidad_id' => $entidad['entidad_id'],
            'entidad_nombre' => $nombreEntidad . $cuitInfo,
            'cuit' => $entidad['cuit'],
            'sucursal_id' => null,
            'sucursal_nombre' => null,
            'sucursal_direccion' => null
        ];
        
        // Obtener sucursales de esta entidad
        $sql_sucursales = "SELECT sucursal_id, sucursal_nombre, sucursal_direccion
                          FROM gestion__entidades_sucursales
                          WHERE entidad_id = ?
                          AND empresa_id = ?
                          AND tabla_estado_registro_id = 1
                          ORDER BY sucursal_nombre";
        
        $stmt_sucursales = mysqli_prepare($conexion, $sql_sucursales);
        if ($stmt_sucursales) {
            mysqli_stmt_bind_param($stmt_sucursales, "ii", $entidad['entidad_id'], $empresa_idx);
            mysqli_stmt_execute($stmt_sucursales);
            $result_sucursales = mysqli_stmt_get_result($stmt_sucursales);
            
            while ($sucursal = mysqli_fetch_assoc($result_sucursales)) {
                $resultados[] = [
                    'tipo' => 'sucursal',
                    'entidad_id' => $entidad['entidad_id'],
                    'entidad_nombre' => $nombreEntidad,
                    'cuit' => $entidad['cuit'],
                    'sucursal_id' => $sucursal['sucursal_id'],
                    'sucursal_nombre' => $sucursal['sucursal_nombre'],
                    'sucursal_direccion' => $sucursal['sucursal_direccion']
                ];
            }
            
            mysqli_stmt_close($stmt_sucursales);
        }
    }
    
    mysqli_stmt_close($stmt_entidades);
    return $resultados;
}

function cargarComprobantes($conexion) {
    $sql = "SELECT comprobante_fiscal_id, codigo, comprobante_fiscal
            FROM gestion__comprobantes_fiscales
            WHERE tabla_estado_registro_id = 1
            ORDER BY codigo";
    
    $result = mysqli_query($conexion, $sql);
    $comprobantes = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $comprobantes[] = $row;
    }
    
    return $comprobantes;
}

function cargarProductos($conexion, $empresa_idx) {
    $sql = "SELECT producto_id, producto_codigo, producto_nombre, 
                   producto_descripcion, producto_categoria_id,
                   (SELECT precio FROM gestion__productos_precios WHERE producto_id = p.producto_id ORDER BY fecha_actualizacion DESC LIMIT 1) as precio_referencia
            FROM gestion__productos p
            WHERE p.empresa_id = ? 
            AND p.tabla_estado_registro_id = 1
            ORDER BY producto_codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

function cargarCondicionesPago($conexion) {
    $sql = "SELECT condicion_pago_id, descripcion, dias_plazo
            FROM conf__condiciones_pago
            WHERE estado = 1
            ORDER BY descripcion";
    
    $result = mysqli_query($conexion, $sql);
    $condiciones = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $condiciones[] = $row;
    }
    
    return $condiciones;
}

function cargarDepositos($conexion) {
    $sql = "SELECT deposito_id, deposito_nombre, direccion
            FROM gestion__depositos
            WHERE tabla_estado_registro_id = 1
            ORDER BY deposito_nombre";
    
    $result = mysqli_query($conexion, $sql);
    $depositos = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $depositos[] = $row;
    }
    
    return $depositos;
}

function cargarImpuestos($conexion) {
    $sql = "SELECT impuesto_id, descripcion, porcentaje, codigo
            FROM conf__impuestos
            WHERE estado = 1
            ORDER BY descripcion";
    
    $result = mysqli_query($conexion, $sql);
    $impuestos = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $impuestos[] = $row;
    }
    
    return $impuestos;
}
// Buscar productos del proveedor específico
function buscarProductosProveedor($conexion, $search, $entidad_id, $empresa_idx) {
    $search = mysqli_real_escape_string($conexion, $search);
    $entidad_id = intval($entidad_id);
    
    if (empty($entidad_id)) {
        return [];
    }
    
    $sql = "SELECT 
                p.producto_id,
                p.producto_codigo,
                p.producto_nombre,
                p.producto_descripcion,
                p.codigo_barras,
                pc.categoria_nombre,
                pp.codigo_proveedor,
                pp.costo,
                pp.moneda_id
            FROM gestion__productos_proveedores pp
            INNER JOIN gestion__productos p ON pp.producto_id = p.producto_id
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.categoria_id
            WHERE pp.entidad_id = ?
            AND p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND pp.tabla_estado_registro_id = 1";
    
    if (!empty($search)) {
        $sql .= " AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR pp.codigo_proveedor LIKE ?)";
        $searchTerm = "%{$search}%";
    }
    
    $sql .= " ORDER BY p.producto_codigo LIMIT 50";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    if (!empty($search)) {
        mysqli_stmt_bind_param($stmt, "iisss", $entidad_id, $empresa_idx, $searchTerm, $searchTerm, $searchTerm);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $entidad_id, $empresa_idx);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

// Buscar en todos los productos
function buscarTodosProductos($conexion, $search, $empresa_idx) {
    $search = mysqli_real_escape_string($conexion, $search);
    
    if (empty($search) || strlen($search) < 2) {
        return [];
    }
    
    $searchTerm = "%{$search}%";
    
    $sql = "SELECT 
                p.producto_id,
                p.producto_codigo,
                p.producto_nombre,
                p.producto_descripcion,
                p.codigo_barras,
                pc.categoria_nombre
            FROM gestion__productos p
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.categoria_id
            WHERE p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR p.codigo_barras LIKE ?)
            ORDER BY p.producto_codigo
            LIMIT 100";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "isss", $empresa_idx, $searchTerm, $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $productos;
}

// Obtener producto por ID
function obtenerProductoPorId($conexion, $producto_id, $empresa_idx) {
    $producto_id = intval($producto_id);
    
    $sql = "SELECT 
                p.producto_id,
                p.producto_codigo,
                p.producto_nombre,
                p.producto_descripcion,
                p.codigo_barras,
                pc.categoria_nombre
            FROM gestion__productos p
            LEFT JOIN gestion__productos_categorias pc ON p.producto_categoria_id = pc.categoria_id
            WHERE p.producto_id = ?
            AND p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $producto;
}
?>