<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de modelos - Versión simplificada
 * Toda la configuración se obtiene de conf__paginas_funciones
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
    $sql = "SELECT estado_registro, codigo_estandar 
            FROM conf__estados_registros 
            WHERE estado_registro_id = ?";

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
        'nombre_funcion' => 'Agregar Modelo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos modelos
function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            WHERE valor_estandar IS NOT NULL
            ORDER BY valor_estandar ASC 
            LIMIT 1";

    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return 1;

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $modelo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $modelo_id = intval($modelo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    // Verificar que el modelo pertenezca a la empresa
    $sql_check = "SELECT modelo_id, tabla_estado_registro_id 
                  FROM gestion__modelos 
                  WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $modelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $modelo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$modelo)
        return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];

    $estado_actual_id = $modelo['tabla_estado_registro_id'];

    // Buscar la función correspondiente en conf__paginas_funciones
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

    // Actualizar el estado
    $sql_update = "UPDATE gestion__modelos 
                   SET tabla_estado_registro_id = ? 
                   WHERE modelo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $modelo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener marcas activas para el select
function obtenerMarcasActivas($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    // Estados considerados como "activos" (codigo_estandar IN ('ACTIVO', 'DISPONIBLE', etc.)
    // O podrías tener una columna "es_activo" en conf__estados_registros
    // Por ahora, asumimos que estado_registro_id = 1 es el estado activo por defecto
    $sql = "SELECT marca_id, marca_nombre
            FROM gestion__marcas 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1  -- Solo marcas activas
            ORDER BY marca_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

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

// ✅ Obtener todos los modelos (con filtro multiempresa)
function obtenerModelos($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT m.*, 
                   ma.marca_nombre,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__modelos m
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id AND ma.empresa_id = ?
            LEFT JOIN conf__estados_registros er ON m.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE m.empresa_id = ?
            ORDER BY m.modelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar black por defecto
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

// ✅ Agregar nuevo modelo (con estado inicial)
function agregarModelo($conexion, $data)
{
    $modelo_nombre = mysqli_real_escape_string($conexion, trim($data['modelo_nombre'] ?? ''));
    $marca_id = intval($data['marca_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($modelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del modelo es obligatorio'];
    }

    if ($marca_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una marca válida'];
    }

    if (strlen($modelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar que la marca pertenezca a la empresa
    $sql_check_marca = "SELECT marca_id FROM gestion__marcas 
                        WHERE marca_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_marca);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $marca_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'La marca seleccionada no pertenece a esta empresa'];
    }

    // Verificar duplicados (mismo nombre + misma marca + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__modelos 
                  WHERE empresa_id = ? AND marca_id = ? AND LOWER(modelo_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $modelo_nombre_lower = strtolower($modelo_nombre);
    mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $marca_id, $modelo_nombre_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un modelo con este nombre en la marca seleccionada'];
    }

    // Insertar nuevo modelo
    $sql = "INSERT INTO gestion__modelos (modelo_nombre, empresa_id, marca_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $modelo_nombre, $empresa_idx, $marca_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $modelo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'modelo_id' => $modelo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el modelo'];
    }
}

// ✅ Editar modelo existente
function editarModelo($conexion, $id, $data)
{
    $id = intval($id);
    $modelo_nombre = mysqli_real_escape_string($conexion, trim($data['modelo_nombre'] ?? ''));
    $marca_id = intval($data['marca_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($modelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del modelo es obligatorio'];
    }

    if ($marca_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una marca válida'];
    }

    if (strlen($modelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    // Verificar que el modelo pertenezca a la empresa
    $sql_check = "SELECT modelo_id FROM gestion__modelos 
                  WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Acceso denegado o registro no encontrado'];
    }

    // Verificar que la marca pertenezca a la empresa
    $sql_check_marca = "SELECT marca_id FROM gestion__marcas 
                        WHERE marca_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_marca);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $marca_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'La marca seleccionada no pertenece a esta empresa'];
    }

    // Verificar duplicados (mismo nombre + misma marca + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__modelos 
                      WHERE empresa_id = ? AND marca_id = ? 
                      AND LOWER(modelo_nombre) = LOWER(?) AND modelo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $modelo_nombre_lower = strtolower($modelo_nombre);
    mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $marca_id, $modelo_nombre_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro modelo con este nombre en la marca seleccionada'];
    }

    // Actualizar modelo
    $sql = "UPDATE gestion__modelos 
            SET modelo_nombre = ?, marca_id = ? 
            WHERE modelo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $modelo_nombre, $marca_id, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el modelo'];
    }
}

// ✅ Obtener modelo específico
function obtenerModeloPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT m.*, er.estado_registro, er.codigo_estandar
            FROM gestion__modelos m
            LEFT JOIN conf__estados_registros er ON m.tabla_estado_registro_id = er.estado_registro_id
            WHERE m.modelo_id = ? AND m.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $modelo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $modelo;
}
?>