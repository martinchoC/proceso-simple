<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de entidades y sucursales
 * Las sucursales usan las mismas acciones que las entidades
 */

// ✅ Obtener funciones configuradas para la página desde conf__paginas_funciones
function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1 -- Solo funciones activas
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

// ✅ Obtener información de un estado específico
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

// ✅ Obtener botones disponibles según el estado actual
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

// ✅ Obtener botón "Agregar" específico para la página
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
        'nombre_funcion' => 'Agregar',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos registros
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

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones (para entidades y sucursales)
function ejecutarTransicionEstado($conexion, $registro_id, $accion_js, $empresa_idx, $pagina_id, $tipo = 'entidad')
{
    $registro_id = intval($registro_id);
    $pagina_id = intval($pagina_id);

    // Determinar la tabla según el tipo
    $tabla = ($tipo === 'sucursal') ? 'gestion__entidades_sucursales' : 'gestion__entidades';
    $id_field = ($tipo === 'sucursal') ? 'sucursal_id' : 'entidad_id';
    
    $sql_check = "SELECT $id_field, tabla_estado_registro_id 
                  FROM $tabla 
                  WHERE $id_field = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $registro_id);
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

    $sql_update = "UPDATE $tabla 
                   SET tabla_estado_registro_id = ? 
                   WHERE $id_field = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $registro_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener todos los tipos de entidad
function obtenerTiposEntidad($conexion)
{
    $sql = "SELECT et.*, er.estado_registro
            FROM gestion__entidades_tipos et
            LEFT JOIN conf__estados_registros er ON et.tabla_estado_registro_id = er.estado_registro_id
            WHERE et.tabla_estado_registro_id IN (1, 2) -- Activos o similares
            ORDER BY et.entidad_tipo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $tipos;
}

// ✅ Obtener todas las localidades
function obtenerLocalidades($conexion)
{
    $sql = "SELECT l.*, er.estado_registro
            FROM conf__localidades l
            LEFT JOIN conf__estados_registros er ON l.tabla_estado_registro_id = er.estado_registro_id
            WHERE l.tabla_estado_registro_id IN (1, 2) -- Activos o similares
            ORDER BY l.localidad";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $localidades = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $localidades[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $localidades;
}

// ✅ Obtener todas las entidades
function obtenerEntidades($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT e.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   ec.color_clase, ec.bg_clase, ec.text_clase,
                   et.entidad_tipo,
                   l.localidad
            FROM gestion__entidades e
            LEFT JOIN conf__estados_registros er ON e.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            LEFT JOIN gestion__entidades_tipos et ON e.entidad_tipo_id = et.entidad_tipo_id
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            WHERE e.empresa_id = ?
            ORDER BY e.entidad_nombre";

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

        $fila['entidad_tipo_info'] = [
            'entidad_tipo' => $fila['entidad_tipo'] ?? null,
            'descripcion' => null,
            'bg_clase' => 'bg-secondary',
            'text_clase' => 'text-white'
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Obtener todas las sucursales de una entidad
function obtenerSucursalesEntidad($conexion, $empresa_idx, $entidad_id, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $entidad_id = intval($entidad_id);

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

    $sql = "SELECT es.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   ec.color_clase, ec.bg_clase, ec.text_clase,
                   l.localidad
            FROM gestion__entidades_sucursales es
            LEFT JOIN conf__estados_registros er ON es.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            LEFT JOIN conf__localidades l ON es.localidad_id = l.localidad_id
            WHERE es.empresa_id = ? AND es.entidad_id = ?
            ORDER BY es.sucursal_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $entidad_id);
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

        $fila['localidad_info'] = [
            'localidad' => $fila['localidad'] ?? null,
            'localidad_id' => $fila['localidad_id'] ?? null
        ];

        // Las sucursales usan las mismas acciones que las entidades
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar nueva entidad (con estado inicial) - SIN DESCUENTOS
function agregarEntidad($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $entidad_nombre = mysqli_real_escape_string($conexion, trim($data['entidad_nombre'] ?? ''));
    $entidad_fantasia = mysqli_real_escape_string($conexion, trim($data['entidad_fantasia'] ?? ''));
    $entidad_tipo_id = $data['entidad_tipo_id'] ? intval($data['entidad_tipo_id']) : null;
    $cont_cuenta_id_proveedor = $data['cont_cuenta_id_proveedor'] ? intval($data['cont_cuenta_id_proveedor']) : null;
    $cont_cuenta_id_cliente = $data['cont_cuenta_id_cliente'] ? intval($data['cont_cuenta_id_cliente']) : null;
    $cuit = $data['cuit'] ? intval($data['cuit']) : null;
    $sitio_web = mysqli_real_escape_string($conexion, trim($data['sitio_web'] ?? ''));
    $domicilio_legal = mysqli_real_escape_string($conexion, trim($data['domicilio_legal'] ?? ''));
    $localidad_id = $data['localidad_id'] ? intval($data['localidad_id']) : null;
    
    // CORRECCIÓN: Manejar correctamente los checkboxes
    $es_proveedor = isset($data['es_proveedor']) && $data['es_proveedor'] ? 1 : 0;
    $es_cliente = isset($data['es_cliente']) && $data['es_cliente'] ? 1 : 0;
    
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));

    if (empty($entidad_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la entidad es obligatorio'];
    }

    if (strlen($entidad_nombre) > 255) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 255 caracteres'];
    }

    if ($cuit && ($cuit < 0 || $cuit > 99999999999)) {
        return ['resultado' => false, 'error' => 'CUIT inválido'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo nombre en la misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__entidades 
                  WHERE empresa_id = ? AND LOWER(entidad_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $entidad_lower = strtolower($entidad_nombre);
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $entidad_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una entidad con este nombre'];
    }

    // Insertar nueva entidad - SIN CAMPOS DE DESCUENTO
    $sql = "INSERT INTO gestion__entidades 
            (empresa_id, entidad_nombre, entidad_fantasia, entidad_tipo_id, cuit, sitio_web, 
             domicilio_legal, localidad_id, cont_cuenta_id_proveedor, cont_cuenta_id_cliente, es_proveedor, es_cliente, observaciones, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issiissiiiissi", 
        $empresa_id, 
        $entidad_nombre, 
        $entidad_fantasia, 
        $entidad_tipo_id,
        $cuit,
        $sitio_web,
        $domicilio_legal,
        $localidad_id,
        $cont_cuenta_id_proveedor,
        $cont_cuenta_id_cliente,
        $es_proveedor,
        $es_cliente,
        $observaciones,
        $estado_inicial
    );
    
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $entidad_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'entidad_id' => $entidad_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la entidad'];
    }
}

// ✅ Editar entidad existente - SIN DESCUENTOS
function editarEntidad($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    $entidad_nombre = mysqli_real_escape_string($conexion, trim($data['entidad_nombre'] ?? ''));
    $entidad_fantasia = mysqli_real_escape_string($conexion, trim($data['entidad_fantasia'] ?? ''));
    $entidad_tipo_id = $data['entidad_tipo_id'] ? intval($data['entidad_tipo_id']) : null;
    $cont_cuenta_id_proveedor = $data['cont_cuenta_id_proveedor'] ? intval($data['cont_cuenta_id_proveedor']) : null;
    $cont_cuenta_id_cliente = $data['cont_cuenta_id_cliente'] ? intval($data['cont_cuenta_id_cliente']) : null;
    $cuit = $data['cuit'] ? intval($data['cuit']) : null;
    $sitio_web = mysqli_real_escape_string($conexion, trim($data['sitio_web'] ?? ''));
    $domicilio_legal = mysqli_real_escape_string($conexion, trim($data['domicilio_legal'] ?? ''));
    $localidad_id = $data['localidad_id'] ? intval($data['localidad_id']) : null;
    
    // CORRECCIÓN CRÍTICA: Manejar correctamente los checkboxes desde $_POST
    $es_proveedor = intval($data['es_proveedor'] ?? 0);
    $es_cliente   = intval($data['es_cliente'] ?? 0);
    
    $observaciones = mysqli_real_escape_string($conexion, trim($data['observaciones'] ?? ''));

    if (empty($entidad_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la entidad es obligatorio'];
    }

    if (strlen($entidad_nombre) > 255) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 255 caracteres'];
    }

    if ($cuit && ($cuit < 0 || $cuit > 99999999999)) {
        return ['resultado' => false, 'error' => 'CUIT inválido'];
    }

    // Verificar que la entidad exista
    $sql_check = "SELECT entidad_id FROM gestion__entidades 
                  WHERE entidad_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    // Verificar duplicados (mismo nombre en la misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__entidades 
                      WHERE empresa_id = ? AND LOWER(entidad_nombre) = LOWER(?) 
                      AND entidad_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $entidad_lower = strtolower($entidad_nombre);
    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $entidad_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra entidad con este nombre'];
    }

    // Actualizar entidad - SIN CAMPOS DE DESCUENTO
    $sql = "UPDATE gestion__entidades 
            SET entidad_nombre = ?, 
                entidad_fantasia = ?, 
                entidad_tipo_id = ?, 
                cuit = ?, 
                sitio_web = ?, 
                domicilio_legal = ?, 
                localidad_id = ?,
                cont_cuenta_id_proveedor = ?,
                cont_cuenta_id_cliente = ?, 
                es_proveedor = ?, 
                es_cliente = ?, 
                observaciones = ?
            WHERE entidad_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiissiiiissi", 
        $entidad_nombre, 
        $entidad_fantasia, 
        $entidad_tipo_id,
        $cuit,
        $sitio_web,
        $domicilio_legal,
        $localidad_id,
        $cont_cuenta_id_proveedor,
        $cont_cuenta_id_cliente,
        $es_proveedor,
        $es_cliente,
        $observaciones,
        $id
    );
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la entidad'];
    }
}



// ✅ Agregar nueva sucursal (con estado inicial)
function agregarSucursal($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $sucursal_nombre = mysqli_real_escape_string($conexion, trim($data['sucursal_nombre'] ?? ''));
    $sucursal_direccion = mysqli_real_escape_string($conexion, trim($data['sucursal_direccion'] ?? ''));
    $localidad_id = $data['localidad_id'] ? intval($data['localidad_id']) : null;
    $sucursal_telefono = mysqli_real_escape_string($conexion, trim($data['sucursal_telefono'] ?? ''));
    $sucursal_email = mysqli_real_escape_string($conexion, trim($data['sucursal_email'] ?? ''));
    $sucursal_contacto = mysqli_real_escape_string($conexion, trim($data['sucursal_contacto'] ?? ''));

    if (empty($sucursal_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la sucursal es obligatorio'];
    }

    if (empty($entidad_id)) {
        return ['resultado' => false, 'error' => 'ID de entidad no proporcionado'];
    }

    if (strlen($sucursal_nombre) > 150) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 150 caracteres'];
    }

    if ($sucursal_email && !filter_var($sucursal_email, FILTER_VALIDATE_EMAIL)) {
        return ['resultado' => false, 'error' => 'Email inválido'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo nombre para la misma entidad)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__entidades_sucursales 
                  WHERE empresa_id = ? AND entidad_id = ? AND LOWER(sucursal_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $sucursal_lower = strtolower($sucursal_nombre);
    mysqli_stmt_bind_param($stmt, "iis", $empresa_id, $entidad_id, $sucursal_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una sucursal con este nombre para esta entidad'];
    }

    // Insertar nueva sucursal
    $sql = "INSERT INTO gestion__entidades_sucursales 
            (empresa_id, entidad_id, sucursal_nombre, sucursal_direccion, localidad_id, 
             sucursal_telefono, sucursal_email, sucursal_contacto, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iississsi", 
        $empresa_id, 
        $entidad_id,
        $sucursal_nombre,
        $sucursal_direccion,
        $localidad_id,
        $sucursal_telefono,
        $sucursal_email,
        $sucursal_contacto,
        $estado_inicial
    );
    
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $sucursal_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'sucursal_id' => $sucursal_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la sucursal'];
    }
}

// ✅ Editar sucursal existente
function editarSucursal($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);
    $sucursal_nombre = mysqli_real_escape_string($conexion, trim($data['sucursal_nombre'] ?? ''));
    $sucursal_direccion = mysqli_real_escape_string($conexion, trim($data['sucursal_direccion'] ?? ''));
    $localidad_id = $data['localidad_id'] ? intval($data['localidad_id']) : null;
    $sucursal_telefono = mysqli_real_escape_string($conexion, trim($data['sucursal_telefono'] ?? ''));
    $sucursal_email = mysqli_real_escape_string($conexion, trim($data['sucursal_email'] ?? ''));
    $sucursal_contacto = mysqli_real_escape_string($conexion, trim($data['sucursal_contacto'] ?? ''));

    if (empty($sucursal_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de la sucursal es obligatorio'];
    }

    if (strlen($sucursal_nombre) > 150) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 150 caracteres'];
    }

    if ($sucursal_email && !filter_var($sucursal_email, FILTER_VALIDATE_EMAIL)) {
        return ['resultado' => false, 'error' => 'Email inválido'];
    }

    // Verificar que la sucursal exista
    $sql_check = "SELECT sucursal_id, entidad_id FROM gestion__entidades_sucursales 
                  WHERE sucursal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursal = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$sucursal) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    $entidad_id = $sucursal['entidad_id'];

    // Verificar duplicados (mismo nombre para la misma entidad, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__entidades_sucursales 
                      WHERE empresa_id = ? AND entidad_id = ? AND LOWER(sucursal_nombre) = LOWER(?) 
                      AND sucursal_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $sucursal_lower = strtolower($sucursal_nombre);
    mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $entidad_id, $sucursal_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra sucursal con este nombre para esta entidad'];
    }

    // Actualizar sucursal
    $sql = "UPDATE gestion__entidades_sucursales 
            SET sucursal_nombre = ?, 
                sucursal_direccion = ?, 
                localidad_id = ?, 
                sucursal_telefono = ?, 
                sucursal_email = ?, 
                sucursal_contacto = ?
            WHERE sucursal_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssisssi", 
        $sucursal_nombre,
        $sucursal_direccion,
        $localidad_id,
        $sucursal_telefono,
        $sucursal_email,
        $sucursal_contacto,
        $id
    );
    
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la sucursal'];
    }
}

// ✅ Obtener entidad específica
function obtenerEntidadPorId($conexion, $id, $empresa_idx)
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

    $sql = "SELECT e.*, er.$estado_column as estado_registro, er.codigo_estandar,
                   et.entidad_tipo, l.localidad
            FROM gestion__entidades e
            LEFT JOIN conf__estados_registros er ON e.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__entidades_tipos et ON e.entidad_tipo_id = et.entidad_tipo_id
            LEFT JOIN conf__localidades l ON e.localidad_id = l.localidad_id
            WHERE e.entidad_id = ? AND e.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $entidad = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $entidad;
}

// ✅ Obtener sucursal específica
function obtenerSucursalPorId($conexion, $id, $empresa_idx)
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

    $sql = "SELECT es.*, er.$estado_column as estado_registro, er.codigo_estandar,
                   l.localidad, e.entidad_nombre
            FROM gestion__entidades_sucursales es
            LEFT JOIN conf__estados_registros er ON es.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__localidades l ON es.localidad_id = l.localidad_id
            LEFT JOIN gestion__entidades e ON es.entidad_id = e.entidad_id
            WHERE es.sucursal_id = ? AND es.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursal = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $sucursal;
}
// ============================================
// FUNCIONES PARA CONDICIONES DE CLIENTES
// ============================================

// Obtener condiciones de pago
function obtenerCondicionesPago($conexion) {
    $sql = "SELECT cp.*, er.estado_registro
            FROM gestion__condiciones_pago cp
            LEFT JOIN conf__estados_registros er ON cp.tabla_estado_registro_id = er.estado_registro_id
            WHERE cp.tabla_estado_registro_id IN (1, 2)
            ORDER BY cp.condicion_pago";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $condiciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $condiciones[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $condiciones;
}

// Obtener listas de precios
function obtenerListasPrecios($conexion) {
    $sql = "SELECT lp.lista_precio_id, lp.lista_precio_nombre AS lista_precio, 
                   lp.descripcion, lp.empresa_id, lp.lista_precio_codigo,
                   lp.tabla_estado_registro_id, er.estado_registro
            FROM gestion__listas_precios lp
            LEFT JOIN conf__estados_registros er ON lp.tabla_estado_registro_id = er.estado_registro_id
            WHERE lp.tabla_estado_registro_id IN (1, 2)  -- Estados activos
            ORDER BY lp.lista_precio_nombre";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $listas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $listas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $listas;
}

// Obtener categorías de proveedores (CORREGIDO: usar proveedor_categoria_id como clave)
function obtenerCategoriasProveedores($conexion) {
    $sql = "SELECT cp.*, er.estado_registro
            FROM gestion__proveedores_categorias cp
            LEFT JOIN conf__estados_registros er ON cp.tabla_estado_registro_id = er.estado_registro_id
            WHERE cp.tabla_estado_registro_id IN (1, 2)
            ORDER BY cp.proveedor_categoria";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $categorias = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Asegurar que devolvemos el campo correcto
        $categorias[] = [
            'proveedor_categoria_id' => $fila['proveedor_categoria_id'],
            'proveedor_categoria' => $fila['proveedor_categoria'],
            'estado_registro' => $fila['estado_registro'] ?? null
        ];
    }
    
    mysqli_stmt_close($stmt);
    return $categorias;
}

// Obtener condiciones de cliente para una entidad (CORREGIDO)
function obtenerCondicionesCliente($conexion, $empresa_idx, $entidad_id, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT cc.*, 
                   cp.condicion_pago,
                   lp.lista_precio,
                   er.estado_registro,
                   ec.color_clase, ec.bg_clase, ec.text_clase
            FROM gestion__entidades_condiciones_clientes cc
            LEFT JOIN gestion__condiciones_pago cp ON cc.condicion_pago_id = cp.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON cc.lista_precio_id = lp.lista_precio_id
            LEFT JOIN conf__estados_registros er ON cc.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            WHERE cc.entidad_id = ?
            ORDER BY cc.f_desde DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';
        
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? ($fila['tabla_estado_registro_id'] == 1 ? 'Activo' : 'Inactivo'),
            'codigo_estandar' => $fila['codigo_estandar'] ?? ($fila['tabla_estado_registro_id'] == 1 ? 'ACTIVO' : 'INACTIVO'),
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];
        
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}
// Obtener condiciones de proveedor para una entidad (CORREGIDO: usar proveedor_categoria_id)
function obtenerCondicionesProveedor($conexion, $empresa_idx, $entidad_id, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT cp.*, 
                   cond.condicion_pago,
                   cat.proveedor_categoria,
                   er.estado_registro,
                   ec.color_clase, ec.bg_clase, ec.text_clase
            FROM gestion__entidades_condiciones_proveedores cp
            LEFT JOIN gestion__condiciones_pago cond ON cp.condicion_pago_id = cond.condicion_pago_id
            LEFT JOIN gestion__proveedores_categorias cat ON cp.proveedor_categoria_id = cat.proveedor_categoria_id
            LEFT JOIN conf__estados_registros er ON cp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            WHERE cp.entidad_id = ?
            ORDER BY cp.f_desde DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
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
        
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}

// Agregar condición de cliente (CORREGIDA - f_hasta = nueva f_desde)
function agregarCondicionCliente($conexion, $data) {
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $condicion_pago_id = $data['condicion_pago_id'] ? intval($data['condicion_pago_id']) : null;
    $lista_precio_id = $data['lista_precio_id'] ? intval($data['lista_precio_id']) : null;
    $limite_credito = isset($data['limite_credito']) && $data['limite_credito'] !== '' ? floatval($data['limite_credito']) : null;
    $cliente_descuento_general = isset($data['cliente_descuento_general']) && $data['cliente_descuento_general'] !== '' ? floatval($data['cliente_descuento_general']) : null;
    $f_desde = mysqli_real_escape_string($conexion, $data['f_desde'] ?? '');
    $f_hasta = null; // La nueva condición no tiene fecha hasta (vigente)
    
    if (empty($entidad_id)) {
        return ['resultado' => false, 'error' => 'ID de entidad no proporcionado'];
    }
    
    if (empty($condicion_pago_id)) {
        return ['resultado' => false, 'error' => 'La condición de pago es obligatoria'];
    }
    
    if (empty($f_desde)) {
        return ['resultado' => false, 'error' => 'La fecha desde es obligatoria'];
    }
    
    // VALIDACIÓN 1: La fecha no puede ser menor al día actual
    $hoy = date('Y-m-d');
    if ($f_desde < $hoy) {
        return ['resultado' => false, 'error' => 'La fecha desde no puede ser menor al día de hoy'];
    }
    
    // Obtener la última condición activa para cerrarla
    $sql_ultima = "SELECT entidad_condicion_cliente_id, f_desde 
                   FROM gestion__entidades_condiciones_clientes 
                   WHERE entidad_id = ? AND tabla_estado_registro_id = 1
                   ORDER BY f_desde DESC LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql_ultima);
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ultima = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($ultima) {
        // VALIDACIÓN 2: La fecha debe ser mayor o igual a la fecha de la condición anterior
        if ($f_desde < $ultima['f_desde']) {
            return ['resultado' => false, 'error' => 'La nueva fecha no puede ser menor a la fecha de la condición anterior (' . date('d/m/Y', strtotime($ultima['f_desde'])) . ')'];
        }
    }
    
    // Iniciar transacción
    mysqli_begin_transaction($conexion);
    
    try {
        // 1. Cerrar la condición anterior si existe y la nueva fecha es mayor
        if ($ultima && $f_desde >= $ultima['f_desde']) {
            // La condición anterior debe tener f_hasta = nueva f_desde (NO restar un día)
            $sql_update = "UPDATE gestion__entidades_condiciones_clientes 
                           SET f_hasta = ?, 
                               tabla_estado_registro_id = 2 
                           WHERE entidad_condicion_cliente_id = ?";
            
            $stmt = mysqli_prepare($conexion, $sql_update);
            if (!$stmt) throw new Exception('Error al preparar actualización de condición anterior');
            
            mysqli_stmt_bind_param($stmt, "si", $f_desde, $ultima['entidad_condicion_cliente_id']);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if (!$update_success) throw new Exception('Error al cerrar condición anterior');
        }
        // Si la fecha es igual, no se cierra la anterior (coexisten)
        
        // 2. Insertar nueva condición con estado activo
        $estado_activo = 1; // Estado activo
        
        $sql_insert = "INSERT INTO gestion__entidades_condiciones_clientes 
                       (entidad_id, condicion_pago_id, lista_precio_id, limite_credito, 
                        cliente_descuento_general, f_desde, f_hasta, tabla_estado_registro_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) throw new Exception('Error al preparar inserción');
        
        mysqli_stmt_bind_param($stmt, "iiiddssi", 
            $entidad_id,
            $condicion_pago_id,
            $lista_precio_id,
            $limite_credito,
            $cliente_descuento_general,
            $f_desde,
            $f_hasta,  // NULL para la nueva condición
            $estado_activo
        );
        
        $insert_success = mysqli_stmt_execute($stmt);
        if (!$insert_success) throw new Exception('Error al insertar condición: ' . mysqli_error($conexion));
        
        $condicion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        
        // Confirmar transacción
        mysqli_commit($conexion);
        
        $mensaje = ($ultima && $f_desde > $ultima['f_desde']) 
            ? 'Condición creada correctamente. La condición anterior ha sido cerrada con fecha hasta ' . date('d/m/Y', strtotime($f_desde)) . ' y desactivada.' 
            : 'Condición creada correctamente.';
        
        return ['resultado' => true, 'condicion_id' => $condicion_id, 'message' => $mensaje];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Agregar condición de proveedor (CORREGIDA - f_hasta = nueva f_desde)
function agregarCondicionProveedor($conexion, $data) {
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $condicion_pago_id = $data['condicion_pago_id'] ? intval($data['condicion_pago_id']) : null;
    $proveedor_categoria_id = $data['proveedor_categoria_id'] ? intval($data['proveedor_categoria_id']) : null;
    $proveedor_descuento_general = isset($data['proveedor_descuento_general']) && $data['proveedor_descuento_general'] !== '' ? floatval($data['proveedor_descuento_general']) : null;
    $f_desde = mysqli_real_escape_string($conexion, $data['f_desde'] ?? '');
    $f_hasta = null; // La nueva condición no tiene fecha hasta (vigente)
    
    if (empty($entidad_id)) {
        return ['resultado' => false, 'error' => 'ID de entidad no proporcionado'];
    }
    
    if (empty($condicion_pago_id)) {
        return ['resultado' => false, 'error' => 'La condición de pago es obligatoria'];
    }
    
    if (empty($f_desde)) {
        return ['resultado' => false, 'error' => 'La fecha desde es obligatoria'];
    }
    
    // VALIDACIÓN 1: La fecha no puede ser menor al día actual
    $hoy = date('Y-m-d');
    if ($f_desde < $hoy) {
        return ['resultado' => false, 'error' => 'La fecha desde no puede ser menor al día de hoy'];
    }
    
    // Obtener la última condición activa para cerrarla
    $sql_ultima = "SELECT entidad_condicion_proveedor_id, f_desde 
                   FROM gestion__entidades_condiciones_proveedores 
                   WHERE entidad_id = ? AND tabla_estado_registro_id = 1
                   ORDER BY f_desde DESC LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql_ultima);
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ultima = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($ultima) {
        // VALIDACIÓN 2: La fecha debe ser mayor o igual a la fecha de la condición anterior
        if ($f_desde < $ultima['f_desde']) {
            return ['resultado' => false, 'error' => 'La nueva fecha no puede ser menor a la fecha de la condición anterior (' . date('d/m/Y', strtotime($ultima['f_desde'])) . ')'];
        }
    }
    
    // Iniciar transacción
    mysqli_begin_transaction($conexion);
    
    try {
        // 1. Cerrar la condición anterior si existe y la nueva fecha es mayor
        if ($ultima && $f_desde >= $ultima['f_desde']) {
            // La condición anterior debe tener f_hasta = nueva f_desde (NO restar un día)
            $sql_update = "UPDATE gestion__entidades_condiciones_proveedores 
                           SET f_hasta = ?, 
                               tabla_estado_registro_id = 2 
                           WHERE entidad_condicion_proveedor_id = ?";
            
            $stmt = mysqli_prepare($conexion, $sql_update);
            if (!$stmt) throw new Exception('Error al preparar actualización de condición anterior');
            
            mysqli_stmt_bind_param($stmt, "si", $f_desde, $ultima['entidad_condicion_proveedor_id']);
            $update_success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if (!$update_success) throw new Exception('Error al cerrar condición anterior');
        }
        // Si la fecha es igual, no se cierra la anterior (coexisten)
        
        // 2. Insertar nueva condición con estado activo
        $estado_activo = 1; // Estado activo
        
        $sql_insert = "INSERT INTO gestion__entidades_condiciones_proveedores 
                       (entidad_id, condicion_pago_id, proveedor_categoria_id, proveedor_descuento_general, 
                        f_desde, f_hasta, tabla_estado_registro_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conexion, $sql_insert);
        if (!$stmt) throw new Exception('Error al preparar inserción');
        
        mysqli_stmt_bind_param($stmt, "iiidssi", 
            $entidad_id,
            $condicion_pago_id,
            $proveedor_categoria_id,
            $proveedor_descuento_general,
            $f_desde,
            $f_hasta,  // NULL para la nueva condición
            $estado_activo
        );
        
        $insert_success = mysqli_stmt_execute($stmt);
        if (!$insert_success) throw new Exception('Error al insertar condición: ' . mysqli_error($conexion));
        
        $condicion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        
        // Confirmar transacción
        mysqli_commit($conexion);
        
        $mensaje = ($ultima && $f_desde > $ultima['f_desde']) 
            ? 'Condición creada correctamente. La condición anterior ha sido cerrada con fecha hasta ' . date('d/m/Y', strtotime($f_desde)) . ' y desactivada.' 
            : 'Condición creada correctamente.';
        
        return ['resultado' => true, 'condicion_id' => $condicion_id, 'message' => $mensaje];
        
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => $e->getMessage()];
    }
}

// Obtener condición de cliente por ID
function obtenerCondicionClientePorId($conexion, $id) {
    $id = intval($id);
    
    $sql = "SELECT * FROM gestion__entidades_condiciones_clientes 
            WHERE entidad_condicion_cliente_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $condicion = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $condicion;
}

// Obtener condición de proveedor por ID (CORREGIDO)
function obtenerCondicionProveedorPorId($conexion, $id) {
    $id = intval($id);
    
    $sql = "SELECT * FROM gestion__entidades_condiciones_proveedores 
            WHERE entidad_condicion_proveedor_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $condicion = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $condicion;
}

// Obtener condición de cliente VIGENTE (actual) para una entidad
function obtenerCondicionClienteVigente($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    
    // Verificar columnas de conf__estados_registros
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
    
    $sql = "SELECT cc.*, 
                   cp.condicion_pago,
                   cp.condicion_pago_id,
                   lp.lista_precio_nombre AS lista_precio,
                   lp.lista_precio_id,
                   er.$estado_column as estado_registro,
                   er.codigo_estandar
            FROM gestion__entidades_condiciones_clientes cc
            LEFT JOIN gestion__condiciones_pago cp ON cc.condicion_pago_id = cp.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON cc.lista_precio_id = lp.lista_precio_id
            LEFT JOIN conf__estados_registros er ON cc.tabla_estado_registro_id = er.estado_registro_id
            WHERE cc.entidad_id = ? 
            AND cc.tabla_estado_registro_id = 1 -- Solo activas
            AND (cc.f_hasta IS NULL OR cc.f_hasta >= CURDATE())
            AND cc.f_desde <= CURDATE()
            ORDER BY cc.f_desde DESC
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $condicion = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    
    if ($condicion) {
        $condicion['estado_info'] = [
            'estado_registro' => $condicion['estado_registro'] ?? 'Activo',
            'codigo_estandar' => $condicion['codigo_estandar'] ?? 'ACTIVO',
            'bg_clase' => 'bg-success',
            'text_clase' => 'text-white'
        ];
    }
    
    return $condicion;
}

// Obtener condición de proveedor VIGENTE (actual) para una entidad
function obtenerCondicionProveedorVigente($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT cp.*, 
                   cond.condicion_pago,
                   cat.proveedor_categoria,
                   er.estado_registro,
                   ec.color_clase, ec.bg_clase, ec.text_clase
            FROM gestion__entidades_condiciones_proveedores cp
            LEFT JOIN gestion__condiciones_pago cond ON cp.condicion_pago_id = cond.condicion_pago_id
            LEFT JOIN gestion__proveedores_categorias cat ON cp.proveedor_categoria_id = cat.proveedor_categoria_id
            LEFT JOIN conf__estados_registros er ON cp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            WHERE cp.entidad_id = ? 
            AND cp.tabla_estado_registro_id = 1 -- Solo activas
            AND (cp.f_hasta IS NULL OR cp.f_hasta >= CURDATE())
            AND cp.f_desde <= CURDATE()
            ORDER BY cp.f_desde DESC
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $condicion = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    
    if ($condicion) {
        $color_clase = $condicion['color_clase'] ?? 'btn-dark';
        $bg_clase = $condicion['bg_clase'] ?? 'bg-dark';
        $text_clase = $condicion['text_clase'] ?? 'text-white';
        
        $condicion['estado_info'] = [
            'estado_registro' => $condicion['estado_registro'] ?? 'Activo',
            'codigo_estandar' => $condicion['codigo_estandar'] ?? 'ACTIVO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];
    }
    
    return $condicion;
}

// Obtener HISTÓRICO de condiciones de cliente
// Obtener HISTÓRICO de condiciones de cliente - CORREGIDO
function obtenerHistorialCondicionesCliente($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    
    // Verificar columnas de conf__estados_registros
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
    
    $sql = "SELECT cc.*, 
                   cp.condicion_pago,
                   lp.lista_precio_nombre AS lista_precio,  -- <-- CAMBIO IMPORTANTE
                   er.$estado_column as estado_registro,
                   er.codigo_estandar
            FROM gestion__entidades_condiciones_clientes cc
            LEFT JOIN gestion__condiciones_pago cp ON cc.condicion_pago_id = cp.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON cc.lista_precio_id = lp.lista_precio_id
            LEFT JOIN conf__estados_registros er ON cc.tabla_estado_registro_id = er.estado_registro_id
            WHERE cc.entidad_id = ? 
            ORDER BY cc.f_desde DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Determinar clases de badge según el estado
        $bg_clase = 'bg-secondary';
        $text_clase = 'text-white';
        
        if ($fila['tabla_estado_registro_id'] == 1) {
            $bg_clase = 'bg-success';
        } elseif ($fila['tabla_estado_registro_id'] == 2) {
            $bg_clase = 'bg-danger';
        }
        
        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];
        
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}
// Obtener HISTÓRICO de condiciones de proveedor
function obtenerHistorialCondicionesProveedor($conexion, $entidad_id) {
    $entidad_id = intval($entidad_id);
    
    $sql = "SELECT cp.*, 
                   cond.condicion_pago,
                   cat.proveedor_categoria,
                   er.estado_registro,
                   ec.color_clase, ec.bg_clase, ec.text_clase
            FROM gestion__entidades_condiciones_proveedores cp
            LEFT JOIN gestion__condiciones_pago cond ON cp.condicion_pago_id = cond.condicion_pago_id
            LEFT JOIN gestion__proveedores_categorias cat ON cp.proveedor_categoria_id = cat.proveedor_categoria_id
            LEFT JOIN conf__estados_registros er ON cp.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            WHERE cp.entidad_id = ? 
            ORDER BY cp.f_desde DESC";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    
    mysqli_stmt_bind_param($stmt, "i", $entidad_id);
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
        
        $data[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $data;
}
// ✅ Obtener cuentas contables para combo
function obtenerCuentasContables($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT cont_cuenta_id, codigo, nombre, nivel
            FROM gestion__cont_cuentas
            WHERE empresa_id = ?
            AND tabla_estado_registro_id = 1
            AND es_imputable = 1
            ORDER BY codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $cuentas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $cuentas[] = $fila;
    }
    
    mysqli_stmt_close($stmt);
    return $cuentas;
}
?>