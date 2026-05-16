<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de Orígenes de Costos
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// Obtener funciones configuradas para la página
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

// Obtener información de un estado específico
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

// Obtener botones disponibles según el estado actual
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

// Obtener botón "Agregar" específico para la página
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
        'nombre_funcion' => 'Agregar Origen de Costo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// Obtener estado inicial para nuevos registros
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

// Ejecutar transición de estado
function ejecutarTransicionEstado($conexion, $producto_costo_origen_id, $accion_js, $empresa_idx, $pagina_id)
{
    $producto_costo_origen_id = intval($producto_costo_origen_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT producto_costo_origen_id, tabla_estado_registro_id 
                  FROM gestion__productos_costos_origenes 
                  WHERE producto_costo_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $producto_costo_origen_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $registro = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$registro)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $registro['tabla_estado_registro_id'];

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

    $sql_update = "UPDATE gestion__productos_costos_origenes 
                   SET tabla_estado_registro_id = ? 
                   WHERE producto_costo_origen_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $producto_costo_origen_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener todos los orígenes de costos
function obtenerOrigenesCostos($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT o.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__productos_costos_origenes o
            LEFT JOIN conf__estados_registros er ON o.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            ORDER BY o.orden, o.producto_costo_origen_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

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

// Agregar nuevo origen de costo
function agregarOrigenCosto($conexion, $data)
{
    $producto_costo_origen_codigo = mysqli_real_escape_string($conexion, trim($data['producto_costo_origen_codigo'] ?? ''));
    $producto_costo_origen_nombre = mysqli_real_escape_string($conexion, trim($data['producto_costo_origen_nombre'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $orden = intval($data['orden'] ?? 1);

    if (empty($producto_costo_origen_codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (strlen($producto_costo_origen_codigo) > 50) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 50 caracteres'];
    }

    if (empty($producto_costo_origen_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    }

    if (strlen($producto_costo_origen_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    // Verificar código único
    $sql_check = "SELECT producto_costo_origen_id FROM gestion__productos_costos_origenes WHERE producto_costo_origen_codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $producto_costo_origen_codigo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'El código ya existe'];
        }
        mysqli_stmt_close($stmt);
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    $sql = "INSERT INTO gestion__productos_costos_origenes 
            (producto_costo_origen_codigo, producto_costo_origen_nombre, descripcion, orden, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssii", $producto_costo_origen_codigo, $producto_costo_origen_nombre, 
                          $descripcion, $orden, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'producto_costo_origen_id' => $id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el registro: ' . mysqli_error($conexion)];
    }
}

// Editar origen de costo existente
function editarOrigenCosto($conexion, $id, $data)
{
    $id = intval($id);
    $producto_costo_origen_codigo = mysqli_real_escape_string($conexion, trim($data['producto_costo_origen_codigo'] ?? ''));
    $producto_costo_origen_nombre = mysqli_real_escape_string($conexion, trim($data['producto_costo_origen_nombre'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $orden = intval($data['orden'] ?? 1);

    if (empty($producto_costo_origen_codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (strlen($producto_costo_origen_codigo) > 50) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 50 caracteres'];
    }

    if (empty($producto_costo_origen_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre es obligatorio'];
    }

    if (strlen($producto_costo_origen_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    // Verificar código único (excluyendo el registro actual)
    $sql_check = "SELECT producto_costo_origen_id FROM gestion__productos_costos_origenes 
                  WHERE producto_costo_origen_codigo = ? AND producto_costo_origen_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $producto_costo_origen_codigo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'El código ya existe'];
        }
        mysqli_stmt_close($stmt);
    }

    $sql = "UPDATE gestion__productos_costos_origenes 
            SET producto_costo_origen_codigo = ?, producto_costo_origen_nombre = ?, 
                descripcion = ?, orden = ?
            WHERE producto_costo_origen_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssii", $producto_costo_origen_codigo, $producto_costo_origen_nombre, 
                          $descripcion, $orden, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el registro: ' . mysqli_error($conexion)];
    }
}

// Obtener origen de costo específico
function obtenerOrigenCostoPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT * FROM gestion__productos_costos_origenes WHERE producto_costo_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $registro = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $registro;
}
?>