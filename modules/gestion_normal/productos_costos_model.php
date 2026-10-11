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
        // Excluir el botón de historial (se maneja aparte en el JS)
        if ($funcion['accion_js'] == 'historial') {
            continue;
        }
        
        // Incluir botones donde el origen coincide con el estado actual
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
        'nombre_funcion' => 'Nuevo Costo',
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

function ejecutarTransicionEstado($conexion, $producto_costo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_costo_id = intval($producto_costo_id);
    $pagina_id = intval($pagina_id);
    
    error_log("=== ejecutarTransicionEstado INICIO ===");
    error_log("producto_costo_id: $producto_costo_id, accion_js: $accion_js");

    $sql_check = "SELECT producto_costo_id, tabla_estado_registro_id 
                  FROM gestion__productos_costos 
                  WHERE producto_costo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $producto_costo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $costo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$costo)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $costo['tabla_estado_registro_id'];
    error_log("Estado actual ID: $estado_actual_id");

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
    error_log("Estado destino ID: $estado_destino_id");

    if ($estado_destino_id == $estado_actual_id) {
        return ['success' => true, 'message' => 'Acción ejecutada correctamente'];
    }

    mysqli_begin_transaction($conexion);
    
    try {
        $sql_update = "UPDATE gestion__productos_costos SET tabla_estado_registro_id = ? WHERE producto_costo_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $producto_costo_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function obtenerProductosCostos($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT pc.*, 
               er.$estado_column as estado_registro, 
               er.codigo_estandar,
               c.color_clase, c.bg_clase, c.text_clase,
               p.producto_codigo, p.producto_nombre,
               m.moneda, m.simbolo,
               o.producto_costo_origen_nombre as origen_nombre
        FROM gestion__productos_costos pc
        LEFT JOIN conf__estados_registros er ON pc.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN conf__colores c ON er.color_id = c.color_id
        LEFT JOIN gestion__productos p ON pc.producto_id = p.producto_id
        LEFT JOIN gestion__monedas m ON pc.moneda_id = m.moneda_id
        LEFT JOIN gestion__productos_costos_origenes o ON pc.producto_costo_origen_id = o.producto_costo_origen_id
        WHERE pc.empresa_id = ?
        ORDER BY p.producto_nombre ASC";

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

function agregarProductoCosto($conexion, $data)
{
    error_log("=== INICIO agregarProductoCosto ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        error_log("Error: Conexión a BD no disponible");
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    if (empty($data['producto_id'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un producto'];
    }
    if (empty($data['costo_actual']) && $data['costo_actual'] !== 0) {
        return ['resultado' => false, 'error' => 'El costo actual es obligatorio'];
    }
    if (empty($data['f_actualizacion'])) {
        return ['resultado' => false, 'error' => 'La fecha de actualización es obligatoria'];
    }
    
    mysqli_begin_transaction($conexion);

    try {
        // Verificar si ya existe un costo para este producto
        $sql_check = "SELECT COUNT(*) as total FROM gestion__productos_costos 
                      WHERE producto_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param($stmt, "ii", $data['producto_id'], $data['empresa_idx']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe un costo configurado para este producto');
        }

        // Obtener estado inicial
        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) {
            $estado_inicial = 1;
        }

        $sql = "INSERT INTO gestion__productos_costos 
                (empresa_id, producto_id, costo_actual, moneda_id, 
                 producto_costo_origen_id, comprobante_id, f_actualizacion, 
                 observaciones, tabla_estado_registro_id, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        $empresa_id_val = intval($data['empresa_idx']);
        $producto_id_val = intval($data['producto_id']);
        $costo_actual_val = floatval($data['costo_actual']);
        $moneda_id_val = !empty($data['moneda_id']) ? intval($data['moneda_id']) : null;
        $origen_id_val = !empty($data['producto_costo_origen_id']) ? intval($data['producto_costo_origen_id']) : null;
        $comprobante_id_val = !empty($data['comprobante_id']) ? intval($data['comprobante_id']) : null;
        $f_actualizacion_val = $data['f_actualizacion'];
        $observaciones_val = trim($data['observaciones'] ?? '');
        $estado_inicial_val = $estado_inicial;
        $creado_por_val = $_SESSION['usuario_id'] ?? 0;

        mysqli_stmt_bind_param($stmt, "iidiiiisii", 
            $empresa_id_val,
            $producto_id_val,
            $costo_actual_val,
            $moneda_id_val,
            $origen_id_val,
            $comprobante_id_val,
            $f_actualizacion_val,
            $observaciones_val,
            $estado_inicial_val,
            $creado_por_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $producto_costo_id = mysqli_insert_id($conexion);
        error_log("Costo creado con ID: " . $producto_costo_id);
        mysqli_stmt_close($stmt);

        // Insertar en el historial
        $sql_historial = "INSERT INTO gestion__productos_costos_historial 
                          (empresa_id, producto_id, costo_anterior, costo_nuevo, 
                           moneda_id, producto_costo_origen_id, comprobante_id, f_desde, 
                           observaciones, tabla_estado_registro_id, creado_por) 
                          VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_hist = mysqli_prepare($conexion, $sql_historial);
        if ($stmt_hist) {
            mysqli_stmt_bind_param($stmt_hist, "iidiiisiii", 
                $empresa_id_val,
                $producto_id_val,
                $costo_actual_val,
                $moneda_id_val,
                $origen_id_val,
                $comprobante_id_val,
                $f_actualizacion_val,
                $observaciones_val,
                $estado_inicial_val,
                $creado_por_val
            );
            mysqli_stmt_execute($stmt_hist);
            mysqli_stmt_close($stmt_hist);
            error_log("Historial insertado para producto $producto_id_val");
        }

        mysqli_commit($conexion);
        error_log("=== FIN agregarProductoCosto - ÉXITO ===");
        return ['resultado' => true, 'producto_costo_id' => $producto_costo_id];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarProductoCosto: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarProductoCosto($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarProductoCosto ID: $id ===");
    error_log("Datos recibidos: " . print_r($data, true));

    mysqli_begin_transaction($conexion);

    try {
        // Obtener el costo anterior para el historial
        $sql_anterior = "SELECT costo_actual, producto_id, moneda_id, 
                                 producto_costo_origen_id, comprobante_id,
                                 observaciones
                          FROM gestion__productos_costos 
                          WHERE producto_costo_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_anterior);
        mysqli_stmt_bind_param($stmt, "ii", $id, $data['empresa_idx']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $anterior = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$anterior) {
            throw new Exception('Registro no encontrado');
        }

        $costo_anterior = floatval($anterior['costo_actual']);
        $producto_id = intval($anterior['producto_id']);
        $costo_nuevo = floatval($data['costo_actual']);

        // Actualizar el costo actual
        $sql = "UPDATE gestion__productos_costos 
                SET producto_id = ?,
                    costo_actual = ?,
                    moneda_id = ?,
                    producto_costo_origen_id = ?,
                    comprobante_id = ?,
                    f_actualizacion = ?,
                    observaciones = ?
                WHERE producto_costo_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $producto_id_val = intval($data['producto_id']);
        $costo_actual_val = floatval($data['costo_actual']);
        $moneda_id_val = !empty($data['moneda_id']) ? intval($data['moneda_id']) : null;
        $origen_id_val = !empty($data['producto_costo_origen_id']) ? intval($data['producto_costo_origen_id']) : null;
        $comprobante_id_val = !empty($data['comprobante_id']) ? intval($data['comprobante_id']) : null;
        $f_actualizacion_val = $data['f_actualizacion'];
        $observaciones_val = trim($data['observaciones'] ?? '');
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "idiidisii", 
            $producto_id_val,
            $costo_actual_val,
            $moneda_id_val,
            $origen_id_val,
            $comprobante_id_val,
            $f_actualizacion_val,
            $observaciones_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // Insertar en el historial si el costo cambió
        if ($costo_anterior != $costo_nuevo || true) {
            $sql_historial = "INSERT INTO gestion__productos_costos_historial 
                              (empresa_id, producto_id, costo_anterior, costo_nuevo, 
                               moneda_id, producto_costo_origen_id, comprobante_id, f_desde, 
                               observaciones, tabla_estado_registro_id, creado_por) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_hist = mysqli_prepare($conexion, $sql_historial);
            if ($stmt_hist) {
                $creado_por_val = $_SESSION['usuario_id'] ?? 0;
                $estado_registro_id = 1;
                
                mysqli_stmt_bind_param($stmt_hist, "iiddiiisiii", 
                    $empresa_idx_val,
                    $producto_id_val,
                    $costo_anterior,
                    $costo_actual_val,
                    $moneda_id_val,
                    $origen_id_val,
                    $comprobante_id_val,
                    $f_actualizacion_val,
                    $observaciones_val,
                    $estado_registro_id,
                    $creado_por_val
                );
                mysqli_stmt_execute($stmt_hist);
                mysqli_stmt_close($stmt_hist);
                error_log("Historial insertado para producto $producto_id_val");
            }
        }

        mysqli_commit($conexion);
        error_log("=== FIN editarProductoCosto - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Costo actualizado correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarProductoCosto: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerProductoCostoPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT pc.*, p.producto_codigo, p.producto_nombre
            FROM gestion__productos_costos pc
            LEFT JOIN gestion__productos p ON pc.producto_id = p.producto_id
            WHERE pc.producto_costo_id = ? AND pc.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $costo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $costo;
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

function obtenerMonedas($conexion, $empresa_idx)
{
    $sql = "SELECT moneda_id, codigo, moneda, simbolo, es_moneda_base, cotizacion_actual 
            FROM gestion__monedas 
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY es_moneda_base DESC, orden";
    
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

function obtenerOrigenesCosto($conexion)
{
    $sql = "SELECT producto_costo_origen_id, producto_costo_origen_nombre 
            FROM gestion__productos_costos_origenes 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY orden, producto_costo_origen_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $origenes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $origenes[] = $fila;
    }
    
    return $origenes;
}

function obtenerHistorialCostos($conexion, $producto_costo_id, $empresa_idx)
{
    $producto_costo_id = intval($producto_costo_id);
    $empresa_idx = intval($empresa_idx);
    
    // Primero obtener el producto_id desde la tabla principal
    $sql_costo = "SELECT producto_id FROM gestion__productos_costos WHERE producto_costo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_costo);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al consultar costo'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_costo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $costo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$costo) {
        return ['success' => false, 'error' => 'Costo no encontrado'];
    }
    
    $producto_id = $costo['producto_id'];
    
    // Obtener nombre del producto para mostrar en el modal
    $sql_producto = "SELECT producto_codigo, producto_nombre FROM gestion__productos WHERE producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_producto);
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $producto_nombre = ($producto ? $producto['producto_codigo'] . ' - ' . $producto['producto_nombre'] : 'Producto ID: ' . $producto_id);
    
    // Obtener historial de costos
    $sql_historial = "SELECT h.*, 
                             o.producto_costo_origen_nombre as origen_nombre,
                             m.moneda as moneda_nombre
                      FROM gestion__productos_costos_historial h
                      LEFT JOIN gestion__productos_costos_origenes o ON h.producto_costo_origen_id = o.producto_costo_origen_id
                      LEFT JOIN gestion__monedas m ON h.moneda_id = m.moneda_id
                      WHERE h.producto_id = ? AND h.empresa_id = ?
                      ORDER BY h.f_desde DESC, h.producto_costo_historial_id DESC";
    
    $stmt = mysqli_prepare($conexion, $sql_historial);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al consultar historial'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $historial = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Formatear números
        $fila['costo_anterior'] = $fila['costo_anterior'] !== null ? floatval($fila['costo_anterior']) : null;
        $fila['costo_nuevo'] = floatval($fila['costo_nuevo']);
        
        $historial[] = $fila;
    }
    mysqli_stmt_close($stmt);
    
    return [
        'success' => true,
        'producto_nombre' => $producto_nombre,
        'historial' => $historial
    ];
}
function obtenerHistorialCostosPaginado($conexion, $producto_costo_id, $empresa_idx, $page = 1, $fecha_desde = '', $fecha_hasta = '')
{
    $producto_costo_id = intval($producto_costo_id);
    $empresa_idx = intval($empresa_idx);
    $page = max(1, intval($page));
    $por_pagina = 20;
    $offset = ($page - 1) * $por_pagina;
    
    // Primero obtener el producto_id desde la tabla principal
    $sql_costo = "SELECT producto_id FROM gestion__productos_costos WHERE producto_costo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_costo);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al consultar costo'];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $producto_costo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $costo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$costo) {
        return ['success' => false, 'error' => 'Costo no encontrado'];
    }
    
    $producto_id = $costo['producto_id'];
    
    // Obtener nombre del producto para mostrar en el modal
    $sql_producto = "SELECT producto_codigo, producto_nombre FROM gestion__productos WHERE producto_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_producto);
    mysqli_stmt_bind_param($stmt, "i", $producto_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $producto_nombre = ($producto ? $producto['producto_codigo'] . ' - ' . $producto['producto_nombre'] : 'Producto ID: ' . $producto_id);
    
    // Construir consulta COUNT
    $sql_count = "SELECT COUNT(*) as total
                  FROM gestion__productos_costos_historial h
                  WHERE h.producto_id = ? AND h.empresa_id = ?";
    
    $params_count = [$producto_id, $empresa_idx];
    $types_count = "ii";
    
    if (!empty($fecha_desde)) {
        $sql_count .= " AND h.f_desde >= ?";
        $params_count[] = $fecha_desde;
        $types_count .= "s";
    }
    if (!empty($fecha_hasta)) {
        $sql_count .= " AND h.f_desde <= ?";
        $params_count[] = $fecha_hasta;
        $types_count .= "s";
    }
    
    $stmt = mysqli_prepare($conexion, $sql_count);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al contar historial'];
    }
    
    array_unshift($params_count, $types_count);
    $refs = [];
    foreach ($params_count as $key => $value) {
        $refs[$key] = &$params_count[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $total = intval($row['total']);
    mysqli_stmt_close($stmt);
    
    // Construir consulta SELECT
    $sql_historial = "SELECT h.*, 
                             o.producto_costo_origen_nombre as origen_nombre,
                             m.moneda as moneda_nombre
                      FROM gestion__productos_costos_historial h
                      LEFT JOIN gestion__productos_costos_origenes o ON h.producto_costo_origen_id = o.producto_costo_origen_id
                      LEFT JOIN gestion__monedas m ON h.moneda_id = m.moneda_id
                      WHERE h.producto_id = ? AND h.empresa_id = ?";
    
    $params_select = [$producto_id, $empresa_idx];
    $types_select = "ii";
    
    if (!empty($fecha_desde)) {
        $sql_historial .= " AND h.f_desde >= ?";
        $params_select[] = $fecha_desde;
        $types_select .= "s";
    }
    if (!empty($fecha_hasta)) {
        $sql_historial .= " AND h.f_desde <= ?";
        $params_select[] = $fecha_hasta;
        $types_select .= "s";
    }
    
    $sql_historial .= " ORDER BY h.f_desde DESC, h.producto_costo_historial_id DESC
                        LIMIT ? OFFSET ?";
    
    $params_select[] = $por_pagina;
    $params_select[] = $offset;
    $types_select .= "ii";
    
    $stmt = mysqli_prepare($conexion, $sql_historial);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al consultar historial'];
    }
    
    array_unshift($params_select, $types_select);
    $refs = [];
    foreach ($params_select as $key => $value) {
        $refs[$key] = &$params_select[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $historial = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['costo_anterior'] = $fila['costo_anterior'] !== null ? floatval($fila['costo_anterior']) : null;
        $fila['costo_nuevo'] = floatval($fila['costo_nuevo']);
        $historial[] = $fila;
    }
    mysqli_stmt_close($stmt);
    
    $total_paginas = ceil($total / $por_pagina);
    
    return [
        'success' => true,
        'producto_nombre' => $producto_nombre,
        'historial' => $historial,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $por_pagina,
            'total' => $total,
            'total_pages' => $total_paginas,
            'from' => $offset + 1,
            'to' => min($offset + $por_pagina, $total)
        ]
    ];
}
?>