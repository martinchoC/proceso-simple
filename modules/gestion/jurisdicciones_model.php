<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de Jurisdicciones
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// Obtener tipos de jurisdicción
function obtenerTiposJurisdiccion($conexion) {
    $sql = "SELECT jurisdiccion_tipo_id, jurisdiccion_tipo 
            FROM gestion__jurisdicciones_tipos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY jurisdiccion_tipo";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    
    $tipos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tipos[] = $row;
    }
    
    return $tipos;
}

// Obtener países
function obtenerPaises($conexion) {
    $sql = "SELECT pais_id, pais 
            FROM conf__paises 
            WHERE tabla_estado_registro_id = 1
            ORDER BY pais";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    
    $paises = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $paises[] = $row;
    }
    
    return $paises;
}

// Obtener provincias por país
function obtenerProvincias($conexion, $pais_id) {
    $pais_id = intval($pais_id);
    
    $sql = "SELECT provincia_id, provincia 
            FROM conf__provincias 
            WHERE pais_id = ? AND tabla_estado_registro_id = 1
            ORDER BY provincia";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $pais_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $provincias = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $provincias[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $provincias;
}

// Obtener localidades por provincia
function obtenerLocalidades($conexion, $provincia_id) {
    $provincia_id = intval($provincia_id);
    
    $sql = "SELECT localidad_id, localidad 
            FROM conf__localidades 
            WHERE provincia_id = ? AND tabla_estado_registro_id = 1
            ORDER BY localidad";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $provincia_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $localidades = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $localidades[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $localidades;
}

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
        'nombre_funcion' => 'Agregar Jurisdicción',
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
function ejecutarTransicionEstado($conexion, $jurisdiccion_id, $accion_js, $empresa_idx, $pagina_id)
{
    $jurisdiccion_id = intval($jurisdiccion_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT jurisdiccion_id, tabla_estado_registro_id 
                  FROM gestion__jurisdicciones 
                  WHERE jurisdiccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $jurisdiccion_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $jurisdiccion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$jurisdiccion)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $jurisdiccion['tabla_estado_registro_id'];

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

    $sql_update = "UPDATE gestion__jurisdicciones 
                   SET tabla_estado_registro_id = ? 
                   WHERE jurisdiccion_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $jurisdiccion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener todas las jurisdicciones
function obtenerJurisdicciones($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT j.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   jt.jurisdiccion_tipo,
                   p.pais,
                   pr.provincia,
                   l.localidad
            FROM gestion__jurisdicciones j
            LEFT JOIN conf__estados_registros er ON j.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__jurisdicciones_tipos jt ON j.jurisdiccion_tipo_id = jt.jurisdiccion_tipo_id
            LEFT JOIN conf__paises p ON j.pais_id = p.pais_id
            LEFT JOIN conf__provincias pr ON j.provincia_id = pr.provincia_id
            LEFT JOIN conf__localidades l ON j.localidad_id = l.localidad_id
            ORDER BY j.orden, j.jurisdiccion_nombre";

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
        
        // CORREGIDO: usar los nombres correctos de columnas
        $fila['pais_nombre'] = $fila['pais'] ?? null;
        $fila['provincia_nombre'] = $fila['provincia'] ?? null;
        $fila['localidad_nombre'] = $fila['localidad'] ?? null;

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// Agregar nueva jurisdicción
function agregarJurisdiccion($conexion, $data)
{
    $jurisdiccion_codigo = mysqli_real_escape_string($conexion, trim($data['jurisdiccion_codigo'] ?? ''));
    $jurisdiccion_nombre = mysqli_real_escape_string($conexion, trim($data['jurisdiccion_nombre'] ?? ''));
    $pais_id = !empty($data['pais_id']) ? intval($data['pais_id']) : null;
    $provincia_id = !empty($data['provincia_id']) ? intval($data['provincia_id']) : null;
    $localidad_id = !empty($data['localidad_id']) ? intval($data['localidad_id']) : null;
    $jurisdiccion_tipo_id = intval($data['jurisdiccion_tipo_id'] ?? 0);
    $organismo_recaudador = mysqli_real_escape_string($conexion, trim($data['organismo_recaudador'] ?? ''));
    $requiere_padron = intval($data['requiere_padron'] ?? 0);
    $codigo_externo = mysqli_real_escape_string($conexion, trim($data['codigo_externo'] ?? ''));
    $orden = intval($data['orden'] ?? 1);

    if (empty($jurisdiccion_codigo)) {
        return ['resultado' => false, 'error' => 'El código de jurisdicción es obligatorio'];
    }

    if (strlen($jurisdiccion_codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if (empty($jurisdiccion_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de jurisdicción es obligatorio'];
    }

    if (strlen($jurisdiccion_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    if ($jurisdiccion_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de jurisdicción'];
    }

    // Verificar código único
    $sql_check = "SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $jurisdiccion_codigo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'El código de jurisdicción ya existe'];
        }
        mysqli_stmt_close($stmt);
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    $sql = "INSERT INTO gestion__jurisdicciones 
            (jurisdiccion_codigo, jurisdiccion_nombre, pais_id, provincia_id, localidad_id, 
             jurisdiccion_tipo_id, organismo_recaudador, requiere_padron, codigo_externo, 
             orden, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiiisssiii", $jurisdiccion_codigo, $jurisdiccion_nombre, 
                          $pais_id, $provincia_id, $localidad_id, $jurisdiccion_tipo_id,
                          $organismo_recaudador, $requiere_padron, $codigo_externo, 
                          $orden, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $jurisdiccion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'jurisdiccion_id' => $jurisdiccion_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la jurisdicción: ' . mysqli_error($conexion)];
    }
}

// Editar jurisdicción existente
function editarJurisdiccion($conexion, $id, $data)
{
    $id = intval($id);
    $jurisdiccion_codigo = mysqli_real_escape_string($conexion, trim($data['jurisdiccion_codigo'] ?? ''));
    $jurisdiccion_nombre = mysqli_real_escape_string($conexion, trim($data['jurisdiccion_nombre'] ?? ''));
    $pais_id = !empty($data['pais_id']) ? intval($data['pais_id']) : null;
    $provincia_id = !empty($data['provincia_id']) ? intval($data['provincia_id']) : null;
    $localidad_id = !empty($data['localidad_id']) ? intval($data['localidad_id']) : null;
    $jurisdiccion_tipo_id = intval($data['jurisdiccion_tipo_id'] ?? 0);
    $organismo_recaudador = mysqli_real_escape_string($conexion, trim($data['organismo_recaudador'] ?? ''));
    $requiere_padron = intval($data['requiere_padron'] ?? 0);
    $codigo_externo = mysqli_real_escape_string($conexion, trim($data['codigo_externo'] ?? ''));
    $orden = intval($data['orden'] ?? 1);

    if (empty($jurisdiccion_codigo)) {
        return ['resultado' => false, 'error' => 'El código de jurisdicción es obligatorio'];
    }

    if (strlen($jurisdiccion_codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if (empty($jurisdiccion_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre de jurisdicción es obligatorio'];
    }

    if (strlen($jurisdiccion_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    if ($jurisdiccion_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de jurisdicción'];
    }

    // Verificar código único (excluyendo el registro actual)
    $sql_check = "SELECT jurisdiccion_id FROM gestion__jurisdicciones 
                  WHERE jurisdiccion_codigo = ? AND jurisdiccion_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $jurisdiccion_codigo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'El código de jurisdicción ya existe'];
        }
        mysqli_stmt_close($stmt);
    }

    $sql = "UPDATE gestion__jurisdicciones 
            SET jurisdiccion_codigo = ?, jurisdiccion_nombre = ?, pais_id = ?, provincia_id = ?, 
                localidad_id = ?, jurisdiccion_tipo_id = ?, organismo_recaudador = ?, 
                requiere_padron = ?, codigo_externo = ?, orden = ?
            WHERE jurisdiccion_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiiissssii", $jurisdiccion_codigo, $jurisdiccion_nombre, 
                          $pais_id, $provincia_id, $localidad_id, $jurisdiccion_tipo_id,
                          $organismo_recaudador, $requiere_padron, $codigo_externo, 
                          $orden, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la jurisdicción: ' . mysqli_error($conexion)];
    }
}

// Obtener jurisdicción específica
function obtenerJurisdiccionPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT * FROM gestion__jurisdicciones WHERE jurisdiccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $jurisdiccion = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $jurisdiccion;
}
?>