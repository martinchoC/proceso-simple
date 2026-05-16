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
        if ($funcion['accion_js'] == 'historial') {
            continue;
        }
        
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
        'nombre_funcion' => 'Nueva Lista',
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

function ejecutarTransicionEstado($conexion, $lista_precio_id, $accion_js, $empresa_idx, $pagina_id)
{
    $lista_precio_id = intval($lista_precio_id);
    $pagina_id = intval($pagina_id);
    
    error_log("=== ejecutarTransicionEstado INICIO ===");
    error_log("lista_precio_id: $lista_precio_id, accion_js: $accion_js");

    $sql_check = "SELECT lista_precio_id, tabla_estado_registro_id 
                  FROM gestion__listas_precios 
                  WHERE lista_precio_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $empresa_idx);
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
        $sql_update = "UPDATE gestion__listas_precios SET tabla_estado_registro_id = ? WHERE lista_precio_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $lista_precio_id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function ejecutarTransicionEstadoRegla($conexion, $regla_id, $accion_js, $empresa_idx, $pagina_id)
{
    $regla_id = intval($regla_id);
    $pagina_id = intval($pagina_id);
    
    $sql_check = "SELECT lista_precio_regla_id, tabla_estado_registro_id 
                  FROM gestion__listas_precios_reglas 
                  WHERE lista_precio_regla_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $regla_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $regla = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$regla)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $regla['tabla_estado_registro_id'];

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

    mysqli_begin_transaction($conexion);
    
    try {
        $sql_update = "UPDATE gestion__listas_precios_reglas SET tabla_estado_registro_id = ? WHERE lista_precio_regla_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $regla_id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function obtenerListasPrecios($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT lp.*, 
               er.$estado_column as estado_registro, 
               er.codigo_estandar,
               c.color_clase, c.bg_clase, c.text_clase,
               o.lista_precio_origen_nombre as origen_nombre,
               m.moneda as moneda_nombre,
               lb.lista_precio_nombre as lista_base_nombre,
               lb.lista_precio_codigo as lista_base_codigo
        FROM gestion__listas_precios lp
        LEFT JOIN conf__estados_registros er ON lp.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN conf__colores c ON er.color_id = c.color_id
        LEFT JOIN gestion__listas_precios_origenes o ON lp.lista_precio_origen_id = o.lista_precio_origen_id
        LEFT JOIN gestion__monedas m ON lp.moneda_id = m.moneda_id
        LEFT JOIN gestion__listas_precios lb ON lp.lista_base_id = lb.lista_precio_id
        WHERE lp.empresa_id = ?
        ORDER BY lp.lista_precio_nombre ASC";

    error_log("SQL Listas: " . $sql);

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query listas: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error ejecutando query listas: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    
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
function obtenerReglas($conexion, $empresa_idx, $pagina_id, $lista_precio_id = null)
{
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

    // Verificar columnas de estados registros
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

    // Verificar que las tablas existan antes de hacer JOIN
    $sql = "SELECT r.*, 
               er.$estado_column as estado_registro, 
               er.codigo_estandar,
               rvt.lista_precio_regla_valor_tipo_nombre as regla_valor_tipo_nombre,
               p.producto_codigo, p.producto_nombre,
               m.marca_nombre,
               md.modelo_nombre,
               sm.submodelo_nombre,
               pt.producto_tipo
        FROM gestion__listas_precios_reglas r
        LEFT JOIN conf__estados_registros er ON r.tabla_estado_registro_id = er.estado_registro_id
        LEFT JOIN gestion__listas_precios_reglas_valores_tipos rvt ON r.lista_precio_regla_valor_tipo_id = rvt.lista_precio_regla_valor_tipo_id
        LEFT JOIN gestion__productos p ON r.producto_id = p.producto_id
        LEFT JOIN gestion__marcas m ON r.marca_id = m.marca_id
        LEFT JOIN gestion__modelos md ON r.modelo_id = md.modelo_id
        LEFT JOIN gestion__submodelos sm ON r.submodelo_id = sm.submodelo_id
        LEFT JOIN gestion__productos_tipos pt ON r.producto_tipo_id = pt.producto_tipo_id
        WHERE r.empresa_id = ?";

    $params = [$empresa_idx];
    $types = "i";

    if ($lista_precio_id) {
        $sql .= " AND r.lista_precio_id = ?";
        $params[] = $lista_precio_id;
        $types .= "i";
    }

    $sql .= " ORDER BY r.prioridad ASC, r.regla_nombre ASC";

    error_log("SQL Reglas: " . $sql);
    error_log("Params: " . print_r($params, true));

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query reglas: " . mysqli_error($conexion));
        return [];
    }

    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error ejecutando query reglas: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => 'bg-secondary',
            'bg_clase' => 'bg-secondary',
            'text_clase' => 'text-white'
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}
function agregarListaPrecio($conexion, $data)
{
    error_log("=== INICIO agregarListaPrecio ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        error_log("Error: Conexión a BD no disponible");
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    if (empty($data['lista_precio_codigo'])) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }
    if (empty($data['lista_precio_nombre'])) {
        return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    }
    if (empty($data['lista_precio_origen_id'])) {
        return ['resultado' => false, 'error' => 'El origen es obligatorio'];
    }
    
    mysqli_begin_transaction($conexion);

    try {
        $sql_check = "SELECT COUNT(*) as total FROM gestion__listas_precios 
                      WHERE lista_precio_codigo = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt, "si", $data['lista_precio_codigo'], $data['empresa_idx']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe una lista de precios con este código');
        }

        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) {
            $estado_inicial = 1;
        }

        $sql = "INSERT INTO gestion__listas_precios 
                (empresa_id, lista_precio_codigo, lista_precio_nombre, descripcion, 
                 lista_precio_origen_id, lista_base_id, moneda_id, 
                 requiere_recalculo, f_ultimo_recalculo, observaciones, 
                 tabla_estado_registro_id, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        $empresa_id_val = intval($data['empresa_idx']);
        $codigo_val = trim($data['lista_precio_codigo']);
        $nombre_val = trim($data['lista_precio_nombre']);
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $origen_id_val = intval($data['lista_precio_origen_id']);
        $lista_base_id_val = !empty($data['lista_base_id']) ? intval($data['lista_base_id']) : null;
        $moneda_id_val = !empty($data['moneda_id']) ? intval($data['moneda_id']) : null;
        $requiere_recalculo_val = isset($data['requiere_recalculo']) ? 1 : 0;
        $f_ultimo_recalculo_val = !empty($data['f_ultimo_recalculo']) ? $data['f_ultimo_recalculo'] : null;
        $observaciones_val = !empty($data['observaciones']) ? trim($data['observaciones']) : null;
        $estado_inicial_val = $estado_inicial;
        $creado_por_val = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;

        mysqli_stmt_bind_param($stmt, "isssiiiiissii", 
            $empresa_id_val,
            $codigo_val,
            $nombre_val,
            $descripcion_val,
            $origen_id_val,
            $lista_base_id_val,
            $moneda_id_val,
            $requiere_recalculo_val,
            $f_ultimo_recalculo_val,
            $observaciones_val,
            $estado_inicial_val,
            $creado_por_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $lista_precio_id = mysqli_insert_id($conexion);
        error_log("Lista creada con ID: " . $lista_precio_id);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN agregarListaPrecio - ÉXITO ===");
        return ['resultado' => true, 'lista_precio_id' => $lista_precio_id, 'message' => 'Lista creada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarListaPrecio: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function editarListaPrecio($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarListaPrecio ID: $id ===");
    error_log("Datos recibidos: " . print_r($data, true));

    mysqli_begin_transaction($conexion);

    try {
        $sql_check = "SELECT COUNT(*) as total FROM gestion__listas_precios 
                      WHERE lista_precio_codigo = ? AND empresa_id = ? AND lista_precio_id != ?";
        $stmt = mysqli_prepare($conexion, $sql_check);
        mysqli_stmt_bind_param($stmt, "sii", $data['lista_precio_codigo'], $data['empresa_idx'], $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['total'] > 0) {
            throw new Exception('Ya existe otra lista con este código');
        }

        $sql = "UPDATE gestion__listas_precios 
                SET lista_precio_codigo = ?,
                    lista_precio_nombre = ?,
                    descripcion = ?,
                    lista_precio_origen_id = ?,
                    lista_base_id = ?,
                    moneda_id = ?,
                    requiere_recalculo = ?,
                    f_ultimo_recalculo = ?,
                    observaciones = ?
                WHERE lista_precio_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $codigo_val = trim($data['lista_precio_codigo']);
        $nombre_val = trim($data['lista_precio_nombre']);
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $origen_id_val = intval($data['lista_precio_origen_id']);
        $lista_base_id_val = !empty($data['lista_base_id']) ? intval($data['lista_base_id']) : null;
        $moneda_id_val = !empty($data['moneda_id']) ? intval($data['moneda_id']) : null;
        $requiere_recalculo_val = isset($data['requiere_recalculo']) ? 1 : 0;
        $f_ultimo_recalculo_val = !empty($data['f_ultimo_recalculo']) ? $data['f_ultimo_recalculo'] : null;
        $observaciones_val = !empty($data['observaciones']) ? trim($data['observaciones']) : null;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        mysqli_stmt_bind_param($stmt, "sssiiiissii", 
            $codigo_val,
            $nombre_val,
            $descripcion_val,
            $origen_id_val,
            $lista_base_id_val,
            $moneda_id_val,
            $requiere_recalculo_val,
            $f_ultimo_recalculo_val,
            $observaciones_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        error_log("=== FIN editarListaPrecio - ÉXITO ===");
        return ['resultado' => true, 'message' => 'Lista actualizada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarListaPrecio: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function agregarRegla($conexion, $data)
{
    error_log("=== INICIO agregarRegla ===");
    error_log("Datos recibidos: " . print_r($data, true));

    if (!$conexion) {
        return ['resultado' => false, 'error' => 'Error de conexión a la base de datos'];
    }

    if (empty($data['lista_precio_id'])) {
        return ['resultado' => false, 'error' => 'La lista de precio es obligatoria'];
    }
    if (empty($data['regla_nombre'])) {
        return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    }
    if (empty($data['lista_precio_regla_valor_tipo_id'])) {
        return ['resultado' => false, 'error' => 'El tipo de valor es obligatorio'];
    }
    if (empty($data['valor_ajuste']) && $data['valor_ajuste'] !== 0 && $data['valor_ajuste'] !== '0') {
        return ['resultado' => false, 'error' => 'El valor de ajuste es obligatorio'];
    }
    if (empty($data['f_desde'])) {
        return ['resultado' => false, 'error' => 'La fecha desde es obligatoria'];
    }
    
    mysqli_begin_transaction($conexion);

    try {
        $estado_inicial = obtenerEstadoInicial($conexion);
        if (!$estado_inicial) {
            $estado_inicial = 1;
        }

        $sql = "INSERT INTO gestion__listas_precios_reglas 
                (empresa_id, lista_precio_id, regla_nombre, descripcion,
                 lista_precio_regla_valor_tipo_id, valor_ajuste,
                 producto_id, marca_id, modelo_id, submodelo_id,
                 producto_categoria_id, producto_tipo_id, entidad_id, prioridad, 
                 f_desde, f_hasta, es_promocion, permite_acumulacion,
                 tabla_estado_registro_id, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando insert: " . mysqli_error($conexion));
        }

        // Preparar todas las variables
        $empresa_id_val = intval($data['empresa_idx']);
        $lista_precio_id_val = intval($data['lista_precio_id']);
        $regla_nombre_val = trim($data['regla_nombre']);
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $valor_tipo_id_val = intval($data['lista_precio_regla_valor_tipo_id']);
        $valor_ajuste_val = floatval($data['valor_ajuste']);
        $producto_id_val = !empty($data['producto_id']) ? intval($data['producto_id']) : null;
        $marca_id_val = !empty($data['marca_id']) ? intval($data['marca_id']) : null;
        $modelo_id_val = !empty($data['modelo_id']) ? intval($data['modelo_id']) : null;
        $submodelo_id_val = !empty($data['submodelo_id']) ? intval($data['submodelo_id']) : null;
        $categoria_id_val = !empty($data['producto_categoria_id']) ? intval($data['producto_categoria_id']) : null;
        $producto_tipo_id_val = !empty($data['producto_tipo_id']) ? intval($data['producto_tipo_id']) : null;
        $entidad_id_val = !empty($data['entidad_id']) ? intval($data['entidad_id']) : null;
        $prioridad_val = !empty($data['prioridad']) ? intval($data['prioridad']) : 100;
        $f_desde_val = $data['f_desde'];
        $f_hasta_val = !empty($data['f_hasta']) ? $data['f_hasta'] : null;
        $es_promocion_val = isset($data['es_promocion']) ? 1 : 0;
        $permite_acumulacion_val = isset($data['permite_acumulacion']) ? 1 : 0;
        $estado_inicial_val = $estado_inicial;
        $creado_por_val = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;

        // String de tipos: 20 parámetros
        // i = integer, s = string, d = double, i = integer
        $types = "iissidiiiiiiiissiiii";
        
        mysqli_stmt_bind_param($stmt, $types, 
            $empresa_id_val,
            $lista_precio_id_val,
            $regla_nombre_val,
            $descripcion_val,
            $valor_tipo_id_val,
            $valor_ajuste_val,
            $producto_id_val,
            $marca_id_val,
            $modelo_id_val,
            $submodelo_id_val,
            $categoria_id_val,
            $producto_tipo_id_val,
            $entidad_id_val,
            $prioridad_val,
            $f_desde_val,
            $f_hasta_val,
            $es_promocion_val,
            $permite_acumulacion_val,
            $estado_inicial_val,
            $creado_por_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando insert: " . mysqli_stmt_error($stmt));
        }

        $regla_id = mysqli_insert_id($conexion);
        error_log("Regla creada con ID: " . $regla_id);
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['resultado' => true, 'lista_precio_regla_id' => $regla_id, 'message' => 'Regla creada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en agregarRegla: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}
function editarRegla($conexion, $id, $data)
{
    $id = intval($id);
    
    error_log("=== INICIO editarRegla ID: $id ===");

    mysqli_begin_transaction($conexion);

    try {
        $sql = "UPDATE gestion__listas_precios_reglas 
                SET regla_nombre = ?,
                    descripcion = ?,
                    lista_precio_regla_valor_tipo_id = ?,
                    valor_ajuste = ?,
                    producto_id = ?,
                    marca_id = ?,
                    modelo_id = ?,
                    submodelo_id = ?,
                    producto_categoria_id = ?,
                    producto_tipo_id = ?,
                    entidad_id = ?,
                    prioridad = ?,
                    f_desde = ?,
                    f_hasta = ?,
                    es_promocion = ?,
                    permite_acumulacion = ?
                WHERE lista_precio_regla_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception("Error preparando update: " . mysqli_error($conexion));
        }

        $regla_nombre_val = trim($data['regla_nombre']);
        $descripcion_val = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
        $valor_tipo_id_val = intval($data['lista_precio_regla_valor_tipo_id']);
        $valor_ajuste_val = floatval($data['valor_ajuste']);
        $producto_id_val = !empty($data['producto_id']) ? intval($data['producto_id']) : null;
        $marca_id_val = !empty($data['marca_id']) ? intval($data['marca_id']) : null;
        $modelo_id_val = !empty($data['modelo_id']) ? intval($data['modelo_id']) : null;
        $submodelo_id_val = !empty($data['submodelo_id']) ? intval($data['submodelo_id']) : null;
        $categoria_id_val = !empty($data['producto_categoria_id']) ? intval($data['producto_categoria_id']) : null;
        $producto_tipo_id_val = !empty($data['producto_tipo_id']) ? intval($data['producto_tipo_id']) : null;
        $entidad_id_val = !empty($data['entidad_id']) ? intval($data['entidad_id']) : null;
        $prioridad_val = !empty($data['prioridad']) ? intval($data['prioridad']) : 100;
        $f_desde_val = $data['f_desde'];
        $f_hasta_val = !empty($data['f_hasta']) ? $data['f_hasta'] : null;
        $es_promocion_val = isset($data['es_promocion']) ? 1 : 0;
        $permite_acumulacion_val = isset($data['permite_acumulacion']) ? 1 : 0;
        $id_val = $id;
        $empresa_idx_val = intval($data['empresa_idx']);

        // CORREGIDO: Los tipos deben coincidir con los 17 parámetros + 2 del WHERE
        // s, s, i, d, i, i, i, i, i, i, i, i, s, s, i, i, i, i
        mysqli_stmt_bind_param($stmt, "ssidiiiiiiiissiiii", 
            $regla_nombre_val,
            $descripcion_val,
            $valor_tipo_id_val,
            $valor_ajuste_val,
            $producto_id_val,
            $marca_id_val,
            $modelo_id_val,
            $submodelo_id_val,
            $categoria_id_val,
            $producto_tipo_id_val,
            $entidad_id_val,
            $prioridad_val,
            $f_desde_val,
            $f_hasta_val,
            $es_promocion_val,
            $permite_acumulacion_val,
            $id_val,
            $empresa_idx_val
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error ejecutando update: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conexion);
        return ['resultado' => true, 'message' => 'Regla actualizada correctamente'];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en editarRegla: " . $e->getMessage());
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

function obtenerListaPrecioPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT * FROM gestion__listas_precios 
            WHERE lista_precio_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $lista = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($lista && $lista['f_ultimo_recalculo']) {
        $lista['f_ultimo_recalculo'] = date('Y-m-d\TH:i', strtotime($lista['f_ultimo_recalculo']));
    }

    return $lista;
}

function obtenerReglaPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT * FROM gestion__listas_precios_reglas 
            WHERE lista_precio_regla_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . mysqli_error($conexion));
        return null;
    }

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $regla = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $regla;
}

function obtenerOrigenesLista($conexion)
{
    $sql = "SELECT lista_precio_origen_id, lista_precio_origen_nombre 
            FROM gestion__listas_precios_origenes 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY orden, lista_precio_origen_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $origenes = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $origenes[] = $fila;
    }
    
    return $origenes;
}

function obtenerListasBase($conexion, $empresa_idx, $exclude_id = null)
{
    $sql = "SELECT lista_precio_id, lista_precio_codigo, lista_precio_nombre 
            FROM gestion__listas_precios 
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
    
    $params = [$empresa_idx];
    $types = "i";
    
    if ($exclude_id) {
        $sql .= " AND lista_precio_id != ?";
        $params[] = $exclude_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY lista_precio_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    array_unshift($params, $types);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $listas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $listas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $listas;
}



function obtenerTiposValorRegla($conexion)
{
    $sql = "SELECT lista_precio_regla_valor_tipo_id, lista_precio_regla_valor_tipo_nombre 
            FROM gestion__listas_precios_reglas_valores_tipos 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY orden, lista_precio_regla_valor_tipo_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    return $tipos;
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

function obtenerMarcas($conexion, $empresa_idx)
{
    $sql = "SELECT marca_id, marca_nombre 
            FROM gestion__marcas 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY marca_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $marcas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $marcas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $marcas;
}

function obtenerModelos($conexion, $empresa_idx)
{
    $sql = "SELECT m.modelo_id, m.modelo_nombre, ma.marca_nombre
            FROM gestion__modelos m
            LEFT JOIN gestion__marcas ma ON m.marca_id = ma.marca_id
            WHERE m.empresa_id = ? 
            AND m.tabla_estado_registro_id = 1 
            ORDER BY ma.marca_nombre, m.modelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $modelos;
}

function obtenerSubmodelos($conexion, $empresa_idx)
{
    $sql = "SELECT s.submodelo_id, s.submodelo_nombre, 
                   m.modelo_nombre, ma.marca_nombre
            FROM gestion__submodelos s
            LEFT JOIN gestion__modelos m ON s.modelo_id = m.modelo_id
            LEFT JOIN gestion__marcas ma ON m.marca_id = ma.marca_id
            WHERE s.empresa_id = ? 
            AND s.tabla_estado_registro_id = 1 
            ORDER BY ma.marca_nombre, m.modelo_nombre, s.submodelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $submodelos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $submodelos;
}

function obtenerCategorias($conexion, $empresa_idx)
{
    $sql = "SELECT producto_categoria_id, producto_categoria_nombre 
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

function obtenerTiposProducto($conexion, $empresa_idx)
{
    $sql = "SELECT producto_tipo_id, producto_tipo 
            FROM gestion__productos_tipos 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY producto_tipo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $tipos;
}

function obtenerEntidades($conexion, $empresa_idx)
{
    $sql = "SELECT entidad_id, entidad_nombre, cuit 
            FROM gestion__entidades 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1 
            ORDER BY entidad_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $entidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $entidades[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $entidades;
}

// ========== FUNCIONES PARA PRODUCTOS DE LISTA DE PRECIOS ==========

function obtenerProductosLista($conexion, $lista_precio_id, $empresa_idx)
{
    $lista_precio_id = intval($lista_precio_id);
    $empresa_idx = intval($empresa_idx);

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

    $sql = "SELECT lpp.*, 
               p.producto_codigo, p.producto_nombre,
               er.$estado_column as estado_registro, 
               er.codigo_estandar
        FROM gestion__listas_precios_productos lpp
        LEFT JOIN gestion__productos p ON lpp.producto_id = p.producto_id
        LEFT JOIN conf__estados_registros er ON lpp.tabla_estado_registro_id = er.estado_registro_id
        WHERE lpp.lista_precio_id = ? AND lpp.empresa_id = ?
        ORDER BY p.producto_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando query productos lista: " . mysqli_error($conexion));
        return [];
    }

    mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => 'bg-secondary',
            'bg_clase' => 'bg-secondary',
            'text_clase' => 'text-white'
        ];
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function recalcularPreciosLista($conexion, $lista_precio_id, $empresa_idx)
{
    $lista_precio_id = intval($lista_precio_id);
    $empresa_idx = intval($empresa_idx);
    
    error_log("=== INICIO recalcularPreciosLista - Lista ID: $lista_precio_id ===");
    
    mysqli_begin_transaction($conexion);
    
    try {
        // 1. Obtener datos de la lista de precios
        $query_lista = "SELECT * FROM gestion__listas_precios WHERE lista_precio_id = $lista_precio_id AND empresa_id = $empresa_idx";
        $result_lista = mysqli_query($conexion, $query_lista);
        $lista = mysqli_fetch_assoc($result_lista);
        
        if (!$lista) {
            throw new Exception('Lista de precios no encontrada');
        }
        
        // 2. Obtener reglas activas
        $query_reglas = "SELECT * FROM gestion__listas_precios_reglas 
                        WHERE lista_precio_id = $lista_precio_id 
                        AND empresa_id = $empresa_idx 
                        AND tabla_estado_registro_id = 1
                        AND (f_desde <= CURDATE() OR f_desde IS NULL)
                        AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                        ORDER BY prioridad ASC";
        $result_reglas = mysqli_query($conexion, $query_reglas);
        $reglas = [];
        while ($row = mysqli_fetch_assoc($result_reglas)) {
            $reglas[] = $row;
        }
        
        error_log("Reglas encontradas: " . count($reglas));
        
        // 3. Obtener productos con sus costos
        $query_productos = "SELECT DISTINCT 
                                p.producto_id, 
                                p.producto_codigo, 
                                p.producto_nombre,
                                p.producto_categoria_id, 
                                p.producto_tipo_id,
                                COALESCE(pc.costo_actual, 0) as precio_base,
                                pc.producto_costo_id
                            FROM gestion__productos p
                            LEFT JOIN gestion__productos_costos pc 
                                ON p.producto_id = pc.producto_id AND pc.empresa_id = p.empresa_id
                            WHERE p.empresa_id = $empresa_idx 
                            AND p.tabla_estado_registro_id = 1
                            AND (pc.costo_actual IS NOT NULL OR pc.costo_actual > 0)
                            ORDER BY p.producto_id";
        $result_productos = mysqli_query($conexion, $query_productos);
        $productos = [];
        while ($row = mysqli_fetch_assoc($result_productos)) {
            $row['precio_base'] = floatval($row['precio_base']);
            
            // Obtener compatibilidades
            $query_compat = "SELECT marca_id, modelo_id, submodelo_id
                            FROM gestion__productos_compatibilidad
                            WHERE producto_id = {$row['producto_id']} 
                            AND empresa_id = $empresa_idx
                            AND tabla_estado_registro_id = 1";
            $result_compat = mysqli_query($conexion, $query_compat);
            $compatibilidades = [];
            while ($comp = mysqli_fetch_assoc($result_compat)) {
                $compatibilidades[] = $comp;
            }
            $row['compatibilidades'] = $compatibilidades;
            $productos[] = $row;
        }
        
        error_log("Productos encontrados: " . count($productos));
        
        // 4. Guardar historial
        $query_historial = "INSERT INTO gestion__listas_precios_productos_historial 
                            (empresa_id, lista_precio_id, lista_precio_producto_id, producto_id,
                             precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                             lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                             es_manual, precio_manual, precio_final, f_desde, f_hasta,
                             observaciones, creado_por)
                            SELECT empresa_id, lista_precio_id, lista_precio_producto_id, producto_id,
                                   precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                                   lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                                   es_manual, precio_manual, precio_final, f_desde, f_hasta,
                                   observaciones, creado_por
                            FROM gestion__listas_precios_productos
                            WHERE lista_precio_id = $lista_precio_id AND empresa_id = $empresa_idx";
        mysqli_query($conexion, $query_historial);
        
        // 5. Eliminar productos existentes
        $query_delete = "DELETE FROM gestion__listas_precios_productos 
                        WHERE lista_precio_id = $lista_precio_id AND empresa_id = $empresa_idx";
        mysqli_query($conexion, $query_delete);
        
        // 6. Preparar valores fijos
        $estado_activo = obtenerEstadoInicial($conexion);
        $creado_por = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 1;
        $f_desde = date('Y-m-d');
        
        $insertados = 0;
        $errores = [];
        
        // 7. Procesar cada producto con INSERTs individuales
        foreach ($productos as $producto) {
            // Determinar precio base
            if ($lista['lista_base_id']) {
                $query_base = "SELECT precio_final 
                              FROM gestion__listas_precios_productos 
                              WHERE lista_precio_id = {$lista['lista_base_id']} 
                              AND producto_id = {$producto['producto_id']} 
                              AND empresa_id = $empresa_idx
                              LIMIT 1";
                $result_base = mysqli_query($conexion, $query_base);
                $base_row = mysqli_fetch_assoc($result_base);
                
                if ($base_row) {
                    $precio_actual = floatval($base_row['precio_final']);
                } else {
                    $precio_actual = $producto['precio_base'];
                }
            } else {
                $precio_actual = $producto['precio_base'];
            }
            
            $precio_origen = $precio_actual;
            $porcentaje_total = 0;
            $importe_total = 0;
            $ultima_regla_id = null;
            
            // Aplicar reglas
            foreach ($reglas as $regla) {
                $aplica = false;
                
                // Regla directa por producto
                if (!empty($regla['producto_id']) && $regla['producto_id'] == $producto['producto_id']) {
                    $aplica = true;
                }
                
                // Regla por categoría
                if (!$aplica && !empty($regla['producto_categoria_id']) && 
                    $regla['producto_categoria_id'] == $producto['producto_categoria_id']) {
                    $aplica = true;
                }
                
                // Regla por tipo
                if (!$aplica && !empty($regla['producto_tipo_id']) && 
                    $regla['producto_tipo_id'] == $producto['producto_tipo_id']) {
                    $aplica = true;
                }
                
                // Regla por compatibilidad
                if (!$aplica && (!empty($regla['marca_id']) || !empty($regla['modelo_id']) || !empty($regla['submodelo_id']))) {
                    foreach ($producto['compatibilidades'] as $compat) {
                        $compatibilidad_valida = true;
                        
                        if (!empty($regla['marca_id']) && $regla['marca_id'] != $compat['marca_id']) {
                            $compatibilidad_valida = false;
                        }
                        if ($compatibilidad_valida && !empty($regla['modelo_id']) && $regla['modelo_id'] != $compat['modelo_id']) {
                            $compatibilidad_valida = false;
                        }
                        if ($compatibilidad_valida && !empty($regla['submodelo_id']) && $regla['submodelo_id'] != $compat['submodelo_id']) {
                            $compatibilidad_valida = false;
                        }
                        
                        if ($compatibilidad_valida) {
                            $aplica = true;
                            break;
                        }
                    }
                }
                
                // Regla general
                if (!$aplica && empty($regla['producto_id']) && 
                    empty($regla['marca_id']) && empty($regla['modelo_id']) && 
                    empty($regla['submodelo_id']) && empty($regla['producto_categoria_id']) && 
                    empty($regla['producto_tipo_id']) && empty($regla['entidad_id'])) {
                    $aplica = true;
                }
                
                if ($aplica) {
                    $ultima_regla_id = $regla['lista_precio_regla_id'];
                    $tipo_valor = $regla['lista_precio_regla_valor_tipo_id'];
                    $valor_ajuste = floatval($regla['valor_ajuste']);
                    
                    if ($tipo_valor == 1) { // Porcentaje
                        $importe_regla = $precio_actual * $valor_ajuste / 100;
                        $precio_actual += $importe_regla;
                        $porcentaje_total += $valor_ajuste;
                        $importe_total += $importe_regla;
                    } else { // Monto fijo
                        $importe_regla = $valor_ajuste;
                        $precio_actual += $importe_regla;
                        $importe_total += $importe_regla;
                    }
                }
            }
            
            if ($precio_actual < 0) {
                $precio_actual = 0;
            }
            
            // Construir INSERT individual para evitar problemas con bind_param
            $producto_costo_id = $producto['producto_costo_id'] ? $producto['producto_costo_id'] : 'NULL';
            $regla_final_id = $ultima_regla_id ? $ultima_regla_id : 'NULL';
            $precio_manual_null = 'NULL';
            $f_hasta_null = 'NULL';
            $observaciones_null = 'NULL';
            
            $insert_sql = "INSERT INTO gestion__listas_precios_productos 
                           (empresa_id, lista_precio_id, producto_id, producto_costo_id,
                            precio_origen, porcentaje_general_aplicado, importe_general_aplicado,
                            lista_precio_regla_id, porcentaje_regla_aplicado, importe_regla_aplicado,
                            es_manual, precio_manual, precio_final, f_desde, f_hasta,
                            observaciones, tabla_estado_registro_id, creado_por)
                           VALUES (
                               $empresa_idx, 
                               $lista_precio_id, 
                               {$producto['producto_id']}, 
                               $producto_costo_id,
                               $precio_origen, 
                               $porcentaje_total, 
                               $importe_total,
                               $regla_final_id, 
                               $porcentaje_total, 
                               $importe_total,
                               0, 
                               $precio_manual_null, 
                               $precio_actual, 
                               '$f_desde', 
                               $f_hasta_null,
                               $observaciones_null, 
                               $estado_activo, 
                               $creado_por
                           )";
            
            if (mysqli_query($conexion, $insert_sql)) {
                $insertados++;
                error_log("✓ Producto {$producto['producto_codigo']} - Precio final: $precio_actual");
            } else {
                $error_msg = "Error: " . mysqli_error($conexion);
                $errores[] = $error_msg;
                error_log("✗ Producto {$producto['producto_codigo']} - $error_msg");
            }
        }
        
        // 8. Actualizar fecha de último recálculo
        $update_sql = "UPDATE gestion__listas_precios 
                       SET f_ultimo_recalculo = NOW(), 
                           requiere_recalculo = 0 
                       WHERE lista_precio_id = $lista_precio_id AND empresa_id = $empresa_idx";
        mysqli_query($conexion, $update_sql);
        
        mysqli_commit($conexion);
        
        $mensaje = "Precios recalculados correctamente. {$insertados} productos procesados.";
        if (!empty($errores)) {
            $mensaje .= " Errores: " . implode(", ", array_slice($errores, 0, 3));
        }
        
        error_log("=== FIN recalcularPreciosLista - ÉXITO: $mensaje ===");
        return ['success' => true, 'message' => $mensaje];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("ERROR en recalcularPreciosLista: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function actualizarPrecioManual($conexion, $lista_precio_producto_id, $precio_manual, $observaciones, $empresa_idx)
{
    $lista_precio_producto_id = intval($lista_precio_producto_id);
    $precio_manual = floatval($precio_manual);
    $empresa_idx = intval($empresa_idx);
    
    mysqli_begin_transaction($conexion);
    
    try {
        $sql_update = "UPDATE gestion__listas_precios_productos 
                       SET es_manual = 1,
                           precio_manual = ?,
                           precio_final = ?,
                           observaciones = ?,
                           actualizado_en = NOW()
                       WHERE lista_precio_producto_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql_update);
        mysqli_stmt_bind_param($stmt, "ddsii", $precio_manual, $precio_manual, $observaciones, $lista_precio_producto_id, $empresa_idx);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Precio manual actualizado correctamente'];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
// Agregar al final de listas_precios_model.php

function obtenerModelosPorMarca($conexion, $empresa_idx, $marca_id)
{
    $empresa_idx = intval($empresa_idx);
    $marca_id = intval($marca_id);
    
    $sql = "SELECT modelo_id, modelo_nombre 
            FROM gestion__modelos 
            WHERE empresa_id = ? 
            AND marca_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY modelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $marca_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $modelos;
}

function obtenerSubmodelosPorModelo($conexion, $empresa_idx, $modelo_id)
{
    $empresa_idx = intval($empresa_idx);
    $modelo_id = intval($modelo_id);
    
    $sql = "SELECT submodelo_id, submodelo_nombre 
            FROM gestion__submodelos 
            WHERE empresa_id = ? 
            AND modelo_id = ?
            AND tabla_estado_registro_id = 1 
            ORDER BY submodelo_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $modelo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $submodelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $submodelos[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $submodelos;
}
?>