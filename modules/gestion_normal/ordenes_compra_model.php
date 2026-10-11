<?php
require_once __DIR__ . '/../../core/empresa_config.php';
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
            
            $esConfirmable = 0;
            if ($funcion['tabla_estado_registro_destino_id'] != $funcion['tabla_estado_registro_origen_id']) {
                $esConfirmable = 1;
            }
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']),
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion'],
                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => $esConfirmable
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

function obtenerEstadoInicial($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT ter.estado_registro_id
            FROM conf__paginas p
            JOIN conf__tablas_estados_registros ter ON ter.tabla_id = p.tabla_id
            WHERE p.pagina_id = ?
            AND ter.es_inicial = 1
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta estado inicial: " . mysqli_error($conexion));
        return 1; // Valor por defecto si hay error
    }
    
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($fila) {
        error_log("Estado inicial encontrado: " . $fila['estado_registro_id']);
        return $fila['estado_registro_id'];
    }
    
    // Si no encuentra, buscar un estado por defecto
    error_log("No se encontró estado inicial para página $pagina_id, usando ID 1 por defecto");
    return 1;
}

/**
 * Obtiene el próximo número de comprobante y actualiza el numerador
 */
function obtenerProximoNumeroComprobante($conexion, $empresa_id, $punto_venta_id, $comprobante_tipo_id) {
    error_log("=== obtenerProximoNumeroComprobante ===");
    error_log("empresa_id: $empresa_id, punto_venta_id: $punto_venta_id, comprobante_tipo_id: $comprobante_tipo_id");
    
    // Verificar si existe el numerador
    $sql_check = "SELECT numerador_id, ultimo_numero 
                  FROM gestion__comprobantes_numeradores 
                  WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                  FOR UPDATE";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando consulta numerador: " . mysqli_error($conexion));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $numerador = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($numerador) {
        // Actualizar el número
        $nuevo_numero = $numerador['ultimo_numero'] + 1;
        error_log("Numerador encontrado - ID: {$numerador['numerador_id']}, último: {$numerador['ultimo_numero']}, nuevo: $nuevo_numero");
        
        $sql_update = "UPDATE gestion__comprobantes_numeradores 
                       SET ultimo_numero = ? 
                       WHERE numerador_id = ?";
        
        $stmt_update = mysqli_prepare($conexion, $sql_update);
        if (!$stmt_update) {
            error_log("Error preparando update numerador: " . mysqli_error($conexion));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt_update, "ii", $nuevo_numero, $numerador['numerador_id']);
        $success = mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        if ($success) {
            error_log("Numerador actualizado correctamente. Nuevo número: $nuevo_numero");
            return $nuevo_numero;
        } else {
            error_log("Error actualizando numerador: " . mysqli_error($conexion));
            return false;
        }
    } else {
        // No existe el numerador, crear uno nuevo empezando en 1
        error_log("No existe numerador para empresa $empresa_id, punto_venta $punto_venta_id, tipo $comprobante_tipo_id. Creando nuevo con número 1");
        
        $sql_insert = "INSERT INTO gestion__comprobantes_numeradores 
                       (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) 
                       VALUES (?, ?, ?, 1)";
        
        $stmt_insert = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt_insert) {
            error_log("Error preparando insert numerador: " . mysqli_error($conexion));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt_insert, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
        $success = mysqli_stmt_execute($stmt_insert);
        $insert_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_insert);
        
        if ($success) {
            error_log("Numerador creado con ID: $insert_id, número inicial: 1");
            return 1;
        } else {
            error_log("Error insertando numerador: " . mysqli_error($conexion));
            return false;
        }
    }
}
/**
 * Asigna número de comprobante a una orden al ser confirmada
 */
function asignarNumeroComprobante($conexion, $orden_compra_id) {
    // Obtener datos de la orden necesarios para la numeración
    $sql = "SELECT oc.empresa_id, oc.punto_venta_id, oc.comprobante_tipo_id
            FROM gestion__ordenes_compra oc
            WHERE oc.orden_compra_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error preparando consulta: ' . mysqli_error($conexion)];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$orden) {
        return ['success' => false, 'error' => 'Orden no encontrada'];
    }
    
    if (empty($orden['punto_venta_id'])) {
        return ['success' => false, 'error' => 'La orden no tiene punto de venta asignado'];
    }
    
    // Obtener próximo número
    $proximo_numero = obtenerProximoNumeroComprobante(
        $conexion, 
        $orden['empresa_id'], 
        $orden['punto_venta_id'], 
        $orden['comprobante_tipo_id']
    );
    
    if ($proximo_numero === false) {
        return ['success' => false, 'error' => 'Error al obtener próximo número de comprobante'];
    }
    
    // Actualizar la orden con el nuevo número
    $sql_update = "UPDATE gestion__ordenes_compra 
                   SET comprobante_nro = ? 
                   WHERE orden_compra_id = ?";
    
    $stmt_update = mysqli_prepare($conexion, $sql_update);
    if (!$stmt_update) {
        return ['success' => false, 'error' => 'Error preparando update: ' . mysqli_error($conexion)];
    }
    
    mysqli_stmt_bind_param($stmt_update, "ii", $proximo_numero, $orden_compra_id);
    $success = mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);
    
    if ($success) {
        return [
            'success' => true, 
            'message' => 'Número de comprobante asignado correctamente',
            'comprobante_nro' => $proximo_numero
        ];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar la orden: ' . mysqli_error($conexion)];
    }
}
function ejecutarTransicionEstado($conexion, $orden_compra_id, $accion_js, $empresa_idx, $pagina_id)
{
    $orden_compra_id = intval($orden_compra_id);
    $pagina_id = intval($pagina_id);

    // Obtener datos completos de la orden
    $sql_check = "SELECT oc.orden_compra_id, oc.tabla_estado_registro_id, oc.comprobante_nro, 
                         oc.comprobante_tipo_id, oc.punto_venta_id, oc.empresa_id,
                         pv.punto_venta_id as pv_id
                  FROM gestion__ordenes_compra oc
                  LEFT JOIN gestion__puntos_venta pv ON oc.punto_venta_id = pv.punto_venta_id
                  WHERE oc.orden_compra_id = ? AND oc.empresa_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return ['success' => false, 'error' => 'Error en la consulta'];
    }

    $empresa_idx = intval($empresa_idx);
    mysqli_stmt_bind_param($stmt, "ii", $orden_compra_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orden = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$orden) {
        return ['success' => false, 'error' => 'Registro no encontrado'];
    }

    $estado_actual_id = $orden['tabla_estado_registro_id'];

    // Buscar la función que corresponde a la acción
    $sql_funcion = "SELECT pf.* 
                    FROM conf__paginas_funciones pf
                    WHERE pf.pagina_id = ? 
                    AND pf.tabla_estado_registro_origen_id = ? 
                    AND pf.accion_js = ?
                    LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt) {
        error_log("Error preparando consulta función: " . mysqli_error($conexion));
        return ['success' => false, 'error' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual_id, $accion_js);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$funcion) {
        error_log("Acción no permitida - página: $pagina_id, estado origen: $estado_actual_id, acción: $accion_js");
        return ['success' => false, 'error' => 'Acción no permitida para este estado'];
    }

    $estado_destino_id = $funcion['tabla_estado_registro_destino_id'];

    // Si el estado no cambia, retornar éxito
    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    mysqli_begin_transaction($conexion);
    
    try {
        // Variable para almacenar el número asignado
        $numero_asignado = null;
        
        // Si la acción es "confirmar", asignar número ANTES de cambiar el estado
        if ($accion_js === 'confirmar') {
            
            // Verificar que la orden tenga punto de venta
            if (empty($orden['punto_venta_id'])) {
                throw new Exception('La orden no tiene punto de venta asignado. No se puede generar número de comprobante.');
            }
            
            // Verificar que no tenga ya un número asignado
            if (!empty($orden['comprobante_nro']) && $orden['comprobante_nro'] > 0) {
                // Ya tiene número, lo usamos
                $numero_asignado = $orden['comprobante_nro'];
                error_log("La orden ya tiene número: $numero_asignado");
            } else {
                // Obtener el próximo número del numerador
                $proximo_numero = obtenerProximoNumeroComprobante(
                    $conexion, 
                    $orden['empresa_id'], 
                    $orden['punto_venta_id'], 
                    $orden['comprobante_tipo_id']
                );
                
                if ($proximo_numero === false) {
                    throw new Exception('Error al obtener próximo número de comprobante');
                }
                
                // Actualizar la orden con el nuevo número
                $sql_update_numero = "UPDATE gestion__ordenes_compra 
                                      SET comprobante_nro = ? 
                                      WHERE orden_compra_id = ?";
                
                $stmt_numero = mysqli_prepare($conexion, $sql_update_numero);
                if (!$stmt_numero) {
                    throw new Exception('Error preparando update de número: ' . mysqli_error($conexion));
                }
                
                mysqli_stmt_bind_param($stmt_numero, "ii", $proximo_numero, $orden_compra_id);
                if (!mysqli_stmt_execute($stmt_numero)) {
                    throw new Exception('Error actualizando número: ' . mysqli_stmt_error($stmt_numero));
                }
                mysqli_stmt_close($stmt_numero);
                
                $numero_asignado = $proximo_numero;
                error_log("Nuevo número asignado: $numero_asignado");
            }
        }
        
        // Actualizar estado
        $sql_update = "UPDATE gestion__ordenes_compra 
                       SET tabla_estado_registro_id = ? 
                       WHERE orden_compra_id = ?";
        
        $stmt = mysqli_prepare($conexion, $sql_update);
        if (!$stmt) {
            throw new Exception('Error preparando update de estado: ' . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $orden_compra_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error actualizando estado: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conexion);
        
        $mensaje = 'Estado actualizado correctamente';
        if ($numero_asignado) {
            $mensaje .= ' - Número asignado: ' . $numero_asignado;
        }
        
        return ['success' => true, 'message' => $mensaje, 'comprobante_nro' => $numero_asignado];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en ejecutarTransicionEstado: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
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
                   e.entidad_nombre, e.entidad_fantasia,
                   m.moneda, m.simbolo,
                   s.sucursal_nombre,
                   pv.nombre as punto_venta_nombre, pv.codigo_fiscal as punto_venta_codigo
            FROM gestion__ordenes_compra oc
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__comprobantes_tipos ct ON oc.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON oc.moneda_id = m.moneda_id
            LEFT JOIN gestion__sucursales s ON oc.sucursal_id = s.sucursal_id AND s.empresa_id = oc.empresa_id
            LEFT JOIN gestion__puntos_venta pv ON oc.punto_venta_id = pv.punto_venta_id AND pv.empresa_id = oc.empresa_id
            WHERE oc.empresa_id = ?";

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $uid = intval($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_idx, 'oc.sucursal_id');
        }
    }

    $sql .= " ORDER BY oc.f_emision DESC, oc.orden_compra_id DESC";

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
    error_log("=== INICIO agregarOrdenCompra ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        error_log("Error: Conexión a BD no disponible");
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    // Validaciones básicas (sin validar precio)
    if (empty($data['f_emision'])) {
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
    if (empty($data['sucursal_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la sucursal'];
    }
    if (empty($data['punto_venta_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el punto de venta'];
    }
    if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
        return ['resultado' => false, 'error' => 'Debe agregar al menos un producto al detalle'];
    }

    mysqli_begin_transaction($conexion);

    try {
        // Obtener estado inicial usando la página
        $estado_inicial = obtenerEstadoInicial($conexion, $data['pagina_idx']);
        if (!$estado_inicial) {
            $estado_inicial = 1; // Fallback
        }

        // Manejar valores NULL
        $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? $data['f_entrega_estimada'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = (!empty($data['tipo_cambio'])) ? floatval($data['tipo_cambio']) : 1.000000;
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        $punto_venta_id = (!empty($data['punto_venta_id']) && $data['punto_venta_id'] > 0) ? intval($data['punto_venta_id']) : null;
        
        $direccion_entrega = isset($data['direccion_entrega']) ? trim($data['direccion_entrega']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';

        // Insertar orden
        $sql = "INSERT INTO gestion__ordenes_compra 
                (empresa_id, sucursal_id, punto_venta_id, comprobante_tipo_id, comprobante_nro, 
                 entidad_id, entidad_sucursal_id, f_emision, f_entrega_estimada, condicion_pago_id, 
                 moneda_id, tipo_cambio, direccion_entrega, subtotal, descuentos, impuestos, total, 
                 observaciones, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        $empresa_id_val = intval($data['empresa_idx']);
        $sucursal_id_val = $sucursal_id;
        $punto_venta_id_val = $punto_venta_id;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_nro_val = 0;
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_entrega_estimada_val = $f_entrega_estimada;
        $condicion_pago_id_val = $condicion_pago_id;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = $tipo_cambio;
        $direccion_entrega_val = $direccion_entrega;
        $subtotal_val = floatval($data['subtotal'] ?? 0);
        $descuentos_val = floatval($data['descuentos'] ?? 0);
        $impuestos_val = floatval($data['impuestos'] ?? 0);
        $total_val = floatval($data['total'] ?? 0);
        $observaciones_val = $observaciones;
        $estado_inicial_val = intval($estado_inicial);

        mysqli_stmt_bind_param($stmt, "iiiiiiissiiddddddsi",
            $empresa_id_val,
            $sucursal_id_val,
            $punto_venta_id_val,
            $comprobante_tipo_id_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_entrega_estimada_val,
            $condicion_pago_id_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $direccion_entrega_val,
            $subtotal_val,
            $descuentos_val,
            $impuestos_val,
            $total_val,
            $observaciones_val,
            $estado_inicial_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $orden_compra_id = mysqli_insert_id($conexion);
        error_log("Orden creada con ID: " . $orden_compra_id);
        mysqli_stmt_close($stmt);

        // Insertar detalles (ahora permite precio cero)
        $detalles_success = insertarDetallesOrden($conexion, $orden_compra_id, $data['empresa_idx'], $data['detalles']);
        
        if (!$detalles_success) {
            throw new Exception("Error al insertar los detalles");
        }

        mysqli_commit($conexion);
        error_log("=== FIN agregarOrdenCompra - ÉXITO ===");
        return ['resultado' => true, 'orden_compra_id' => $orden_compra_id];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarOrdenCompra: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}


function editarOrdenCompra($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarOrdenCompra ID: $id ===");
    error_log("Datos recibidos en función: " . print_r($data, true));

    mysqli_begin_transaction($conexion);

    try {
        // Solo se edita en Borrador (función Editar 3→3); el número lo asigna Confirmar, no el cliente.
        $empresa_idx_chk = intval($data['empresa_idx']);
        $stmt_chk = mysqli_prepare($conexion, "SELECT tabla_estado_registro_id, comprobante_nro FROM gestion__ordenes_compra
                                               WHERE orden_compra_id = ? AND empresa_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt_chk, "ii", $id, $empresa_idx_chk);
        mysqli_stmt_execute($stmt_chk);
        $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));
        mysqli_stmt_close($stmt_chk);
        if (!$actual) {
            throw new Exception('Orden de compra no encontrada');
        }
        if (intval($actual['tabla_estado_registro_id']) !== 3) {
            throw new Exception('Solo se puede editar una orden de compra en Borrador');
        }

        $f_entrega_estimada = (!empty($data['f_entrega_estimada'])) ? $data['f_entrega_estimada'] : null;
        $condicion_pago_id = (!empty($data['condicion_pago_id']) && $data['condicion_pago_id'] > 0) ? intval($data['condicion_pago_id']) : null;
        $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.000000);
        $entidad_sucursal_id = (!empty($data['entidad_sucursal_id']) && $data['entidad_sucursal_id'] > 0) ? intval($data['entidad_sucursal_id']) : null;
        $sucursal_id = (!empty($data['sucursal_id']) && $data['sucursal_id'] > 0) ? intval($data['sucursal_id']) : null;
        $punto_venta_id = (!empty($data['punto_venta_id']) && $data['punto_venta_id'] > 0) ? intval($data['punto_venta_id']) : null;
        $direccion_entrega = isset($data['direccion_entrega']) ? trim($data['direccion_entrega']) : '';
        $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : '';
        
        $comprobante_nro = intval($actual['comprobante_nro'] ?? 0);

        $sql = "UPDATE gestion__ordenes_compra 
                SET sucursal_id = ?,
                    punto_venta_id = ?,
                    comprobante_tipo_id = ?, 
                    comprobante_nro = ?, 
                    entidad_id = ?, 
                    entidad_sucursal_id = ?, 
                    f_emision = ?, 
                    f_entrega_estimada = ?, 
                    condicion_pago_id = ?, 
                    moneda_id = ?, 
                    tipo_cambio = ?, 
                    direccion_entrega = ?, 
                    subtotal = ?, 
                    descuentos = ?, 
                    impuestos = ?, 
                    total = ?, 
                    observaciones = ?
                WHERE orden_compra_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $sucursal_id_val = $sucursal_id;
        $punto_venta_id_val = $punto_venta_id;
        $comprobante_tipo_id_val = intval($data['comprobante_tipo_id']);
        $comprobante_nro_val = $comprobante_nro;
        $entidad_id_val = intval($data['entidad_id']);
        $entidad_sucursal_id_val = $entidad_sucursal_id;
        $f_emision_val = $data['f_emision'];
        $f_entrega_estimada_val = $f_entrega_estimada;
        $condicion_pago_id_val = $condicion_pago_id;
        $moneda_id_val = intval($data['moneda_id']);
        $tipo_cambio_val = $tipo_cambio;
        $direccion_entrega_val = $direccion_entrega;
        $subtotal_val = floatval($data['subtotal'] ?? 0);
        $descuentos_val = floatval($data['descuentos'] ?? 0);
        $impuestos_val = floatval($data['impuestos'] ?? 0);
        $total_val = floatval($data['total'] ?? 0);
        $observaciones_val = $observaciones;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, 
            "iiiiiissiiddddddsii",
            $sucursal_id_val,
            $punto_venta_id_val,
            $comprobante_tipo_id_val,
            $comprobante_nro_val,
            $entidad_id_val,
            $entidad_sucursal_id_val,
            $f_emision_val,
            $f_entrega_estimada_val,
            $condicion_pago_id_val,
            $moneda_id_val,
            $tipo_cambio_val,
            $direccion_entrega_val,
            $subtotal_val,
            $descuentos_val,
            $impuestos_val,
            $total_val,
            $observaciones_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        error_log("Filas afectadas en update: " . $affected_rows);
        mysqli_stmt_close($stmt);

        // Eliminar detalles existentes
        $sql_delete = "DELETE FROM gestion__ordenes_compra_detalle WHERE orden_compra_id = ?";
        $stmt_delete = mysqli_prepare($conexion, $sql_delete);
        if (!$stmt_delete) {
            throw new Exception("Error preparando delete de detalles: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("Error eliminando detalles existentes: " . mysqli_stmt_error($stmt_delete));
        }
        mysqli_stmt_close($stmt_delete);
        
        // Insertar nuevos detalles (permite precio cero)
        if (isset($data['detalles']) && is_array($data['detalles']) && count($data['detalles']) > 0) {
            $detalles_success = insertarDetallesOrden($conexion, $id, $empresa_idx_val, $data['detalles']);
            
            if (!$detalles_success) {
                throw new Exception("Error al insertar los nuevos detalles");
            }
        } else {
            throw new Exception("Debe haber al menos un detalle en la orden");
        }

        mysqli_commit($conexion);
        error_log("=== FIN editarOrdenCompra - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Orden actualizada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarOrdenCompra: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
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

    $sql = "SELECT oc.*, oc.sucursal_id, oc.punto_venta_id,
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   ct.comprobante_tipo,
                   e.entidad_nombre, e.entidad_fantasia,
                   m.moneda, m.simbolo
            FROM gestion__ordenes_compra oc
            LEFT JOIN conf__estados_registros er ON oc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON oc.comprobante_tipo_id = ct.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON oc.moneda_id = m.moneda_id
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

    error_log("Orden cargada - sucursal_id: " . ($orden['sucursal_id'] ?? 'null') . " - punto_venta_id: " . ($orden['punto_venta_id'] ?? 'null'));
    
    $sql_detalles = "SELECT d.*, p.producto_codigo, p.producto_nombre,
                            p.iva_alicuota_id as producto_iva_id,
                            pp.codigo_proveedor,
                            iva.porcentaje as iva_porcentaje
                     FROM gestion__ordenes_compra_detalle d
                     LEFT JOIN gestion__productos p ON d.producto_id = p.producto_id
                     LEFT JOIN (
                         SELECT producto_id, MAX(codigo_proveedor) AS codigo_proveedor
                         FROM gestion__productos_proveedores
                         WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                         GROUP BY producto_id
                     ) pp ON d.producto_id = pp.producto_id
                     LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
                     WHERE d.orden_compra_id = ?
                     ORDER BY d.ordenes_compra_detalle_id";

    $stmt = mysqli_prepare($conexion, $sql_detalles);
    if (!$stmt)
        return $orden;

    mysqli_stmt_bind_param($stmt, "iii", $orden['entidad_id'], $orden['empresa_id'], $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $detalles = [];
    while ($detalle = mysqli_fetch_assoc($result)) {
        $detalles[] = [
            'ordenes_compra_detalle_id' => $detalle['ordenes_compra_detalle_id'],
            'producto_id' => $detalle['producto_id'],
            'producto_nombre' => $detalle['producto_nombre'] . ' (' . $detalle['producto_codigo'] . ')',
            'cantidad' => floatval($detalle['cantidad']),
            'cantidad_recibida' => floatval($detalle['cantidad_recibida'] ?? 0),
            'precio_unitario' => floatval($detalle['precio_unitario']),
            'no_gravado' => floatval($detalle['no_gravado'] ?? 0),
            'exento' => floatval($detalle['exento'] ?? 0),
            'iva_alicuota_id' => $detalle['producto_iva_id'] ?? $detalle['iva_alicuota_id'] ?? emp_iva_alicuota_id($conexion, $empresa_idx),
            'iva_porcentaje' => floatval($detalle['iva_porcentaje'] ?? 21),
            'neto_gravado' => floatval($detalle['neto_gravado']),
            'iva_importe' => floatval($detalle['iva_importe']),
            'total_linea' => floatval($detalle['total_linea']),
            'codigo_proveedor' => $detalle['codigo_proveedor'] ?? ''
        ];
    }
    mysqli_stmt_close($stmt);

    $orden['detalles'] = $detalles;
    return $orden;
}

function obtenerComprobantesTipos($conexion, $empresa_idx = null)
{
    $empresa_idx = intval($empresa_idx ?? ($_SESSION['empresa_id'] ?? 0));
    $subgrupo_id = emp_subgrupo_id($conexion, $empresa_idx, 'OC');   // Pedido de Compra de la empresa
    if ($subgrupo_id <= 0) return [];   // la empresa no tiene ese subgrupo activo
    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra
            FROM gestion__comprobantes_tipos
            WHERE empresa_id = $empresa_idx AND comprobante_subgrupo_id = $subgrupo_id
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

function obtenerMonedas($conexion, $empresa_idx)
{
    $sql = "SELECT moneda_id, codigo, moneda, simbolo, es_moneda_base, cotizacion_actual 
            FROM gestion__monedas 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY es_moneda_base DESC, orden, moneda";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta monedas: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $monedas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $monedas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $monedas;
}

function obtenerProductosPorProveedor($conexion, $empresa_idx, $entidad_id)
{
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, 
                   pp.codigo_proveedor, p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje
            FROM gestion__productos p
            INNER JOIN gestion__productos_proveedores pp ON p.producto_id = pp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
            WHERE p.empresa_id = ? 
            AND pp.entidad_id = ?
            AND p.tabla_estado_registro_id = 1
            AND pp.tabla_estado_registro_id = 1
            ORDER BY p.producto_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos proveedor: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $entidad_id);
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

/* =========================================================================
 * Alta rápida de producto desde la carga del comprobante, ya vinculado al
 * proveedor con su código. Producto + vínculo en una sola transacción y
 * recálculo de gestion__productos.proveedores_busqueda (si no, el producto
 * nuevo no aparece al buscar por código de proveedor en otras pantallas).
 * ========================================================================= */
function agregarProductoRapido($conexion, $data)
{
    error_log("=== INICIO agregarProductoRapido codigo: " . ($data['producto_codigo'] ?? '') . " ===");

    if (empty($data['producto_codigo']) || empty($data['producto_nombre'])) {
        return ['success' => false, 'error' => 'Código y nombre son obligatorios'];
    }
    
    if (empty($data['producto_categoria_id'])) {
        return ['success' => false, 'error' => 'La categoría es obligatoria'];
    }
    
    if (empty($data['codigo_proveedor'])) {
        return ['success' => false, 'error' => 'El código del proveedor es obligatorio'];
    }
    
    if (empty($data['entidad_id'])) {
        return ['success' => false, 'error' => 'Debe seleccionar un proveedor'];
    }

    $empresa_id = intval($data['empresa_idx']);
    $entidad_id = intval($data['entidad_id']);
    $categoria_id = intval($data['producto_categoria_id']);
    $iva_alicuota_id = intval($data['iva_alicuota_id']);
    $unidad_medida_id = intval($data['unidad_medida_id']);

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) as total FROM gestion__productos 
                                           WHERE producto_codigo = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $data['producto_codigo'], $empresa_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row['total'] > 0) {
            throw new RuntimeException('Ya existe un producto con este código');
        }

        // Solo cuentan los vínculos activos (los inactivos no se usan al buscar)
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) as total FROM gestion__productos_proveedores
                                           WHERE empresa_id = ? AND entidad_id = ? AND codigo_proveedor = ?
                                           AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt, "iis", $empresa_id, $entidad_id, $data['codigo_proveedor']);
        mysqli_stmt_execute($stmt);
        $row_proveedor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row_proveedor && $row_proveedor['total'] > 0) {
            throw new RuntimeException('Ya existe un producto con este código de proveedor para el proveedor seleccionado');
        }

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos 
                (empresa_id, producto_codigo, producto_nombre, codigo_barras, 
                 producto_descripcion, producto_categoria_id, producto_tipo_id, 
                 iva_alicuota_id, unidad_medida_id, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $producto_tipo_id = emp_producto_tipo_id($conexion, $empresa_id, true);
        mysqli_stmt_bind_param($stmt, "issssiiii", 
            $empresa_id,
            $data['producto_codigo'],
            $data['producto_nombre'],
            $data['codigo_barras'],
            $data['producto_descripcion'],
            $categoria_id,
            $producto_tipo_id,
            $iva_alicuota_id,
            $unidad_medida_id
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Error al crear el producto: ' . mysqli_stmt_error($stmt));
        }
        $producto_id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos_proveedores 
                (empresa_id, producto_id, entidad_id, codigo_proveedor, tabla_estado_registro_id) 
                VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "iiis", $empresa_id, $producto_id, $entidad_id, $data['codigo_proveedor']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Error al asociar el producto con el proveedor: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        recalcularProveedoresBusqueda($conexion, $producto_id);
        mysqli_commit($conexion);

        return ['success' => true, 'producto_id' => $producto_id];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("Error agregarProductoRapido: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/* =========================================================================
 * Busca entre TODOS los productos activos de la empresa, no solo los
 * vinculados al proveedor. Los vinculados (vínculo activo en
 * gestion__productos_proveedores) vienen primero con vinculado = 1 y su
 * código de proveedor; el resto con vinculado = 0, para que el front ofrezca
 * vincularlos (vincularProductoProveedor) al seleccionarlos.
 * ========================================================================= */
function buscarProductosPorProveedor($conexion, $empresa_idx, $entidad_id, $q)
{
    $entidad_id = intval($entidad_id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   pp.codigo_proveedor,
                   IF(pp.producto_id IS NULL, 0, 1) AS vinculado,
                   p.iva_alicuota_id,
                   iva.porcentaje as iva_porcentaje,
                   iva.iva_alicuota as iva_nombre
            FROM gestion__productos p
            LEFT JOIN (
                SELECT producto_id, MAX(codigo_proveedor) AS codigo_proveedor
                FROM gestion__productos_proveedores
                WHERE empresa_id = ?
                AND entidad_id = ?
                AND tabla_estado_registro_id = 1
                GROUP BY producto_id
            ) pp ON pp.producto_id = p.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id AND iva.empresa_id = p.empresa_id
            WHERE p.empresa_id = ?
            AND p.tabla_estado_registro_id = 1
            AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ? OR pp.codigo_proveedor LIKE ?)
            ORDER BY vinculado DESC, p.producto_nombre
            LIMIT 30";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta productos: " . mysqli_error($conexion));
        return [];
    }

    $search = "%$q%";
    mysqli_stmt_bind_param($stmt, "iiisss", $empresa_idx, $entidad_id, $empresa_idx, $search, $search, $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = [
            'producto_id' => $fila['producto_id'],
            'producto_codigo' => $fila['producto_codigo'],
            'producto_nombre' => $fila['producto_nombre'],
            'codigo_proveedor' => $fila['codigo_proveedor'],
            'vinculado' => intval($fila['vinculado']),
            'iva_alicuota_id' => $fila['iva_alicuota_id'],
            'iva_porcentaje' => floatval($fila['iva_porcentaje'] ?? 21),
            'iva_nombre' => $fila['iva_nombre'] ?? 'IVA ' . ($fila['iva_porcentaje'] ?? 21) . '%'
        ];
    }

    mysqli_stmt_close($stmt);
    return $productos;
}

/* =========================================================================
 * Vincula un producto existente al proveedor con su código, desde la carga
 * de la factura. Si ya hay una fila INACTIVA para producto + proveedor la
 * reactiva en vez de duplicarla (mismo criterio que
 * productos_proveedores_model.php::agregarVinculoProveedor). El código de
 * proveedor no puede repetirse en otro producto del mismo proveedor.
 * ========================================================================= */
function vincularProductoProveedor($conexion, $data)
{
    $producto_id = intval($data['producto_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $codigo_proveedor = trim($data['codigo_proveedor'] ?? '');

    error_log("=== INICIO vincularProductoProveedor producto: $producto_id proveedor: $entidad_id ===");

    if ($producto_id <= 0) {
        return ['success' => false, 'message' => 'Producto no válido'];
    }
    if ($entidad_id <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar un proveedor'];
    }
    if ($codigo_proveedor === '') {
        return ['success' => false, 'message' => 'El código del proveedor es obligatorio'];
    }
    if (mb_strlen($codigo_proveedor) > 50) {
        return ['success' => false, 'message' => 'El código del proveedor no puede superar los 50 caracteres'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // El producto tiene que ser de la empresa y estar activo
        $stmt = mysqli_prepare($conexion, "SELECT producto_id FROM gestion__productos
                                           WHERE producto_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                           FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$producto) {
            throw new RuntimeException('El producto no existe o está inactivo');
        }

        // Código repetido en otro producto del mismo proveedor
        $stmt = mysqli_prepare($conexion, "SELECT p.producto_codigo, p.producto_nombre
                                           FROM gestion__productos_proveedores pp
                                           INNER JOIN gestion__productos p ON p.producto_id = pp.producto_id
                                           WHERE pp.empresa_id = ? AND pp.entidad_id = ? AND pp.codigo_proveedor = ?
                                           AND pp.producto_id <> ? AND pp.tabla_estado_registro_id = 1
                                           LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iisi", $empresa_id, $entidad_id, $codigo_proveedor, $producto_id);
        mysqli_stmt_execute($stmt);
        $repetido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($repetido) {
            throw new RuntimeException('El código ' . $codigo_proveedor . ' ya está asignado para este proveedor al producto '
                . $repetido['producto_codigo'] . ' - ' . $repetido['producto_nombre']);
        }

        $stmt = mysqli_prepare($conexion, "SELECT producto_proveedor_id, tabla_estado_registro_id
                                           FROM gestion__productos_proveedores
                                           WHERE producto_id = ? AND entidad_id = ? AND empresa_id = ?
                                           ORDER BY (tabla_estado_registro_id = 1) DESC, producto_proveedor_id
                                           LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existente) {
            // Activo (vinculado mientras se cargaba la factura) o inactivo: se deja activo con el código informado
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores
                                               SET tabla_estado_registro_id = 1, codigo_proveedor = ?
                                               WHERE producto_proveedor_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $codigo_proveedor, $existente['producto_proveedor_id']);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Error al reactivar el vínculo: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            $producto_proveedor_id = intval($existente['producto_proveedor_id']);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__productos_proveedores
                                               (producto_id, entidad_id, empresa_id, codigo_proveedor, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "iiis", $producto_id, $entidad_id, $empresa_id, $codigo_proveedor);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Error al vincular el producto: ' . mysqli_stmt_error($stmt));
            }
            $producto_proveedor_id = intval(mysqli_insert_id($conexion));
            mysqli_stmt_close($stmt);
        }

        recalcularProveedoresBusqueda($conexion, $producto_id);
        mysqli_commit($conexion);

        return [
            'success' => true,
            'data' => ['producto_proveedor_id' => $producto_proveedor_id, 'codigo_proveedor' => $codigo_proveedor],
            'message' => 'Producto vinculado al proveedor'
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        error_log("Error vincularProductoProveedor: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/* =========================================================================
 * Mantiene materializada gestion__productos.proveedores_busqueda — MISMA
 * función que productos_model.php / productos_proveedores_model.php, duplicada
 * acá por la convención de no cruzar includes entre archivos de página.
 * ========================================================================= */
function recalcularProveedoresBusqueda($conexion, $producto_id)
{
    $producto_id = intval($producto_id);
    if ($producto_id <= 0) return false;

    $sql = "UPDATE gestion__productos p
            SET p.proveedores_busqueda = (
                SELECT GROUP_CONCAT(DISTINCT pp.codigo_proveedor SEPARATOR ' ')
                FROM gestion__productos_proveedores pp
                WHERE pp.producto_id = p.producto_id
                AND pp.tabla_estado_registro_id = 1
                AND pp.codigo_proveedor IS NOT NULL
                AND pp.codigo_proveedor <> ''
            )
            WHERE p.producto_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando UPDATE proveedores_busqueda: " . mysqli_error($conexion));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        error_log("Error ejecutando UPDATE proveedores_busqueda($producto_id): " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $ok;
}

function obtenerUltimoPrecioProducto($conexion, $producto_id, $entidad_id, $empresa_id)
{
    $sql = "SELECT precio_unitario 
            FROM gestion__ordenes_compra_detalle d
            INNER JOIN gestion__ordenes_compra o ON d.orden_compra_id = o.orden_compra_id
            WHERE d.producto_id = ? 
            AND o.entidad_id = ? 
            AND o.empresa_id = ?
            ORDER BY o.orden_compra_id DESC 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false];
    
    mysqli_stmt_bind_param($stmt, "iii", $producto_id, $entidad_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        return ['success' => true, 'precio' => $row['precio_unitario']];
    }
    
    return ['success' => false];
}

function insertarDetallesOrden($conexion, $orden_compra_id, $empresa_id, $detalles)
{
    error_log("Insertando " . count($detalles) . " detalles para orden $orden_compra_id");
    
    if (!is_array($detalles) || count($detalles) === 0) {
        error_log("Error: No hay detalles para insertar");
        return false;
    }
    
    $insertados = 0;
    
    foreach ($detalles as $index => $detalle) {
        // Validar campos requeridos
        if (empty($detalle['producto_id'])) {
            error_log("Error: producto_id vacío en detalle $index");
            return false;
        }
        
        $cantidad = floatval($detalle['cantidad'] ?? 0);
        $precio_unitario = floatval($detalle['precio_unitario'] ?? 0);
        
        if ($cantidad <= 0) {
            error_log("Error: cantidad inválida en detalle $index: $cantidad");
            return false;
        }
        
        // ELIMINAMOS LA VALIDACIÓN DE PRECIO UNITARIO > 0
        // Ahora permitimos precio = 0 (productos sin precio definido)
        // if ($precio_unitario <= 0) {
        //     error_log("Error: precio_unitario inválido en detalle $index: $precio_unitario");
        //     return false;
        // }
        
        // Asegurar que iva_alicuota_id tenga un valor válido
        $iva_alicuota_id = !empty($detalle['iva_alicuota_id']) ? intval($detalle['iva_alicuota_id']) : 1; // Default a 21%
        $iva_porcentaje = floatval($detalle['iva_porcentaje'] ?? 21);
        
        // Calcular valores
        $neto_gravado = floatval($detalle['neto_gravado'] ?? ($cantidad * $precio_unitario));
        $no_gravado = floatval($detalle['no_gravado'] ?? 0);
        $exento = floatval($detalle['exento'] ?? 0);
        $iva_importe = floatval($detalle['iva_importe'] ?? ($neto_gravado * $iva_porcentaje / 100));
        $total_linea = floatval($detalle['total_linea'] ?? ($neto_gravado + $iva_importe + $no_gravado + $exento));
        
        // Insertar detalle
        $sql = "INSERT INTO gestion__ordenes_compra_detalle 
                (orden_compra_id, empresa_id, producto_id, cantidad, cantidad_recibida, 
                 precio_unitario, no_gravado, exento, neto_gravado, iva_alicuota_id, iva_porcentaje, iva_importe, total_linea) 
                VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            error_log("Error preparando insert detalle: " . mysqli_error($conexion));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "iiidddddidid",
            $orden_compra_id,
            $empresa_id,
            $detalle['producto_id'],
            $cantidad,
            $precio_unitario,
            $no_gravado,
            $exento,
            $neto_gravado,
            $iva_alicuota_id,
            $iva_porcentaje,
            $iva_importe,
            $total_linea
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            error_log("Error ejecutando insert detalle $index: " . $error);
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $detalle_id = mysqli_insert_id($conexion);
        error_log("Detalle $index insertado con ID: " . $detalle_id . " - Precio: $precio_unitario, Cantidad: $cantidad");
        $insertados++;
        mysqli_stmt_close($stmt);
    }
    
    error_log("Se insertaron $insertados detalles correctamente");
    return true; // No necesitamos verificar count exacto si todos se insertaron
}

function obtenerSucursalesEmpresa($conexion, $empresa_idx) {
    $sql = "SELECT sucursal_id, sucursal_nombre 
            FROM gestion__sucursales 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY sucursal_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta sucursales: " . mysqli_error($conexion));
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }
    
    mysqli_stmt_close($stmt);

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $sucursales = AccesosSucursalesHelper::filtrarSucursales($conexion, $empresa_idx, $sucursales);
    }

    return $sucursales;
}

?>