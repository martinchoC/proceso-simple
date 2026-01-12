<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de ubicaciones de sucursales
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

// ✅ Obtener todos los estados disponibles
function obtenerEstadosRegistro($conexion)
{
    $sql = "SELECT estado_registro_id, estado_registro, codigo_estandar 
            FROM conf__estados_registros 
            WHERE tabla_estado_registro_id = 1
            ORDER BY estado_registro";

    $result = mysqli_query($conexion, $sql);
    $estados = [];

    if ($result) {
        while ($fila = mysqli_fetch_assoc($result)) {
            $estados[] = $fila;
        }
    }

    return $estados;
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

    // Si no hay configuración, usar valores por defecto
    return [
        'nombre_funcion' => 'Agregar Ubicación',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevas ubicaciones
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
function ejecutarTransicionEstado($conexion, $sucursal_ubicacion_id, $accion_js, $empresa_idx, $pagina_id)
{
    $sucursal_ubicacion_id = intval($sucursal_ubicacion_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    // Verificar que la ubicación pertenezca a la empresa
    $sql_check = "SELECT gu.sucursal_ubicacion_id, gu.tabla_estado_registro_id 
                  FROM gestion__sucursales_ubicaciones gu
                  INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
                  WHERE gu.sucursal_ubicacion_id = ? AND gs.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $sucursal_ubicacion_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ubicacion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$ubicacion)
        return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];

    $estado_actual_id = $ubicacion['tabla_estado_registro_id'];

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
    $sql_update = "UPDATE gestion__sucursales_ubicaciones 
                   SET tabla_estado_registro_id = ? 
                   WHERE sucursal_ubicacion_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $sucursal_ubicacion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener sucursales activas para el select
function obtenerSucursalesActivas($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT s.sucursal_id, s.sucursal_nombre, l.localidad
            FROM gestion__sucursales s
            LEFT JOIN conf__localidades l ON s.localidad_id = l.localidad_id
            WHERE s.empresa_id = ? 
            AND s.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $sucursales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $sucursales[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $sucursales;
}


// ✅ Obtener todas las ubicaciones con filtros (ORDENADO)
function obtenerSucursalesUbicaciones($conexion, $empresa_idx, $pagina_id, $filters = [])
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT gu.*, 
                   gs.sucursal_nombre,
                   gs.localidad_id,
                   cl.localidad,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__sucursales_ubicaciones gu
            INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
            LEFT JOIN conf__localidades cl ON gs.localidad_id = cl.localidad_id
            LEFT JOIN conf__estados_registros er ON gu.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE gs.empresa_id = ?";

    $params = [$empresa_idx];
    $types = "i";

    // Aplicar filtros
    if (!empty($filters['sucursal'])) {
        $sql .= " AND gu.sucursal_id = ?";
        $params[] = intval($filters['sucursal']);
        $types .= "i";
    }

    if (!empty($filters['estado'])) {
        $sql .= " AND gu.tabla_estado_registro_id = ?";
        $params[] = intval($filters['estado']);
        $types .= "i";
    }

    if (!empty($filters['busqueda'])) {
        $sql .= " AND (gu.seccion LIKE ? OR gu.estanteria LIKE ? OR gu.estante LIKE ? OR gu.posicion LIKE ? OR gu.descripcion LIKE ?)";
        $search_term = '%' . $filters['busqueda'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sssss";
    }

    // ✅ ORDENAR POR: sucursal, sección, estantería, estante y posición
    $sql .= " ORDER BY gs.sucursal_nombre ASC, gu.seccion ASC, gu.estanteria ASC, gu.estante ASC, gu.posicion ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar black por defecto
        $color_clase = $fila['color_clase'] ?? '';
        $bg_clase = $fila['bg_clase'] ?? '';
        $text_clase = $fila['text_clase'] ?? '';

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'] ?? 'Sin estado',
            'codigo_estandar' => $fila['codigo_estandar'] ?? 'DESCONOCIDO',
            'color_clase' => $color_clase,
            'bg_clase' => $bg_clase,
            'text_clase' => $text_clase
        ];

        $fila['sucursal_info'] = [
            'sucursal_nombre' => $fila['sucursal_nombre'],
            'localidad' => $fila['localidad']
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Agregar nueva ubicación (con estado inicial) - ACTUALIZADA CON POSICIÓN
function agregarSucursalUbicacion($conexion, $data)
{
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $seccion = mysqli_real_escape_string($conexion, trim($data['seccion'] ?? ''));
    $estanteria = mysqli_real_escape_string($conexion, trim($data['estanteria'] ?? ''));
    $estante = mysqli_real_escape_string($conexion, trim($data['estante'] ?? ''));
    $posicion = mysqli_real_escape_string($conexion, trim($data['posicion'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $estado_registro_id = isset($data['estado_registro_id']) ? intval($data['estado_registro_id']) : obtenerEstadoInicial($conexion);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    // Validaciones básicas
    if ($sucursal_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una sucursal válida'];
    }

    if (empty($seccion)) {
        return ['resultado' => false, 'error' => 'La sección es obligatoria'];
    }

    if (empty($estanteria)) {
        return ['resultado' => false, 'error' => 'La estantería es obligatoria'];
    }

    if (empty($estante)) {
        return ['resultado' => false, 'error' => 'El estante es obligatorio'];
    }

    if (empty($posicion)) {
        return ['resultado' => false, 'error' => 'La posición es obligatoria'];
    }

    // Validar longitudes
    $campos = [
        'seccion' => ['value' => $seccion, 'max' => 50, 'label' => 'La sección'],
        'estanteria' => ['value' => $estanteria, 'max' => 50, 'label' => 'La estantería'],
        'estante' => ['value' => $estante, 'max' => 50, 'label' => 'El estante'],
        'posicion' => ['value' => $posicion, 'max' => 50, 'label' => 'La posición']
    ];

    foreach ($campos as $campo) {
        if (strlen($campo['value']) > $campo['max']) {
            return ['resultado' => false, 'error' => $campo['label'] . ' no puede exceder los ' . $campo['max'] . ' caracteres'];
        }
    }

    if (strlen($descripcion) > 255) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 255 caracteres'];
    }

    // Verificar que la sucursal pertenezca a la empresa
    $sql_check_sucursal = "SELECT sucursal_id FROM gestion__sucursales 
                           WHERE sucursal_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_sucursal);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'La sucursal seleccionada no pertenece a esta empresa'];
    }

    // Verificar duplicados (misma sucursal + misma sección + misma estantería + mismo estante + misma posición)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__sucursales_ubicaciones 
                  WHERE sucursal_id = ? AND seccion = ? AND estanteria = ? AND estante = ? AND posicion = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issss", $sucursal_id, $seccion, $estanteria, $estante, $posicion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una ubicación con esta combinación completa en la sucursal seleccionada'];
    }

    // Insertar nueva ubicación
    $sql = "INSERT INTO gestion__sucursales_ubicaciones 
            (empresa_id, sucursal_id, seccion, estanteria, estante, posicion, descripcion, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param(
        $stmt,
        "iisssssi",
        $empresa_idx,
        $sucursal_id,
        $seccion,
        $estanteria,
        $estante,
        $posicion,
        $descripcion,
        $estado_registro_id
    );
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $sucursal_ubicacion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'sucursal_ubicacion_id' => $sucursal_ubicacion_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la ubicación: ' . mysqli_error($conexion)];
    }
}

// ✅ Editar ubicación existente - ACTUALIZADA CON POSICIÓN
function editarSucursalUbicacion($conexion, $id, $data)
{
    $id = intval($id);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $seccion = mysqli_real_escape_string($conexion, trim($data['seccion'] ?? ''));
    $estanteria = mysqli_real_escape_string($conexion, trim($data['estanteria'] ?? ''));
    $estante = mysqli_real_escape_string($conexion, trim($data['estante'] ?? ''));
    $posicion = mysqli_real_escape_string($conexion, trim($data['posicion'] ?? ''));
    $descripcion = mysqli_real_escape_string($conexion, trim($data['descripcion'] ?? ''));
    $estado_registro_id = isset($data['estado_registro_id']) ? intval($data['estado_registro_id']) : null;
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    // Validaciones básicas
    if ($sucursal_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una sucursal válida'];
    }

    if (empty($seccion)) {
        return ['resultado' => false, 'error' => 'La sección es obligatoria'];
    }

    if (empty($estanteria)) {
        return ['resultado' => false, 'error' => 'La estantería es obligatoria'];
    }

    if (empty($estante)) {
        return ['resultado' => false, 'error' => 'El estante es obligatorio'];
    }

    if (empty($posicion)) {
        return ['resultado' => false, 'error' => 'La posición es obligatoria'];
    }

    // Validar longitudes
    $campos = [
        'seccion' => ['value' => $seccion, 'max' => 50, 'label' => 'La sección'],
        'estanteria' => ['value' => $estanteria, 'max' => 50, 'label' => 'La estantería'],
        'estante' => ['value' => $estante, 'max' => 50, 'label' => 'El estante'],
        'posicion' => ['value' => $posicion, 'max' => 50, 'label' => 'La posición']
    ];

    foreach ($campos as $campo) {
        if (strlen($campo['value']) > $campo['max']) {
            return ['resultado' => false, 'error' => $campo['label'] . ' no puede exceder los ' . $campo['max'] . ' caracteres'];
        }
    }

    if (strlen($descripcion) > 255) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 255 caracteres'];
    }

    // Verificar que la ubicación exista y pertenezca a la empresa
    $sql_check = "SELECT gu.sucursal_ubicacion_id 
                  FROM gestion__sucursales_ubicaciones gu
                  INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
                  WHERE gu.sucursal_ubicacion_id = ? AND gs.empresa_id = ?";
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

    // Verificar que la sucursal pertenezca a la empresa
    $sql_check_sucursal = "SELECT sucursal_id FROM gestion__sucursales 
                           WHERE sucursal_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_sucursal);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'La sucursal seleccionada no pertenece a esta empresa'];
    }

    // Verificar duplicados (excluyendo registro actual) - AHORA CON POSICIÓN
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__sucursales_ubicaciones 
                      WHERE sucursal_id = ? AND seccion = ? AND estanteria = ? AND estante = ? AND posicion = ?
                      AND sucursal_ubicacion_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issssi", $sucursal_id, $seccion, $estanteria, $estante, $posicion, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra ubicación con esta combinación completa en la sucursal seleccionada'];
    }

    // Construir consulta de actualización - AHORA CON POSICIÓN
    $sql = "UPDATE gestion__sucursales_ubicaciones 
            SET sucursal_id = ?, seccion = ?, estanteria = ?, estante = ?, posicion = ?, descripcion = ?";

    $params = [$sucursal_id, $seccion, $estanteria, $estante, $posicion, $descripcion];
    $types = "isssss";

    if ($estado_registro_id) {
        $sql .= ", tabla_estado_registro_id = ?";
        $params[] = $estado_registro_id;
        $types .= "i";
    }

    $sql .= " WHERE sucursal_ubicacion_id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la ubicación: ' . mysqli_error($conexion)];
    }
}

// ✅ Obtener ubicación específica
function obtenerSucursalUbicacionPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT gu.*, gs.sucursal_nombre, er.estado_registro, er.codigo_estandar
            FROM gestion__sucursales_ubicaciones gu
            INNER JOIN gestion__sucursales gs ON gu.sucursal_id = gs.sucursal_id
            LEFT JOIN conf__estados_registros er ON gu.tabla_estado_registro_id = er.estado_registro_id
            WHERE gu.sucursal_ubicacion_id = ? AND gs.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $sucursal_ubicacion = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $sucursal_ubicacion;
}

// ✅ Obtener valores por defecto según tipo de padre - REVISADO
function obtenerValoresPorDefecto($conexion, $parent_type, $parent_id, $empresa_idx)
{
    $parent_type = mysqli_real_escape_string($conexion, trim($parent_type));
    $parent_id = mysqli_real_escape_string($conexion, trim($parent_id));
    $empresa_idx = intval($empresa_idx);
    
    $valores = [
        'sucursal_id' => 0,
        'seccion' => '',
        'estanteria' => '',
        'estante' => '',
        'posicion' => '1A' // Valor por defecto para posición (primera posición)
    ];
    
    switch ($parent_type) {
        case 'sucursal':
            // El parent_id ES el ID de la sucursal
            $valores['sucursal_id'] = intval($parent_id);
            break;
            
        case 'seccion':
            // El parent_id tiene formato: sucursalId_seccion
            // Ejemplo: "2_A" (sucursal 2, sección A)
            $partes = explode('_', $parent_id);
            if (count($partes) >= 2) {
                $valores['sucursal_id'] = intval($partes[0]);
                $valores['seccion'] = $partes[1];
            }
            break;
            
        case 'estanteria':
            // El parent_id tiene formato: sucursalId_seccion_estanteria
            // Ejemplo: "2_A_01" (sucursal 2, sección A, estantería 01)
            $partes = explode('_', $parent_id);
            if (count($partes) >= 3) {
                $valores['sucursal_id'] = intval($partes[0]);
                $valores['seccion'] = $partes[1];
                $valores['estanteria'] = $partes[2];
            }
            break;
            
        case 'estante':
            // El parent_id tiene formato: sucursalId_seccion_estanteria_estante
            // Ejemplo: "2_A_01_01"
            $partes = explode('_', $parent_id);
            if (count($partes) >= 4) {
                $valores['sucursal_id'] = intval($partes[0]);
                $valores['seccion'] = $partes[1];
                $valores['estanteria'] = $partes[2];
                $valores['estante'] = $partes[3];
                
                // Buscar la próxima posición disponible en este estante
                $sql = "SELECT MAX(posicion) as max_posicion 
                        FROM gestion__sucursales_ubicaciones 
                        WHERE sucursal_id = ? 
                        AND seccion = ? 
                        AND estanteria = ? 
                        AND estante = ?";
                
                $stmt = mysqli_prepare($conexion, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "isss", 
                        $valores['sucursal_id'], 
                        $valores['seccion'], 
                        $valores['estanteria'], 
                        $valores['estante']
                    );
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    if ($fila = mysqli_fetch_assoc($result) && $fila['max_posicion']) {
                        // Determinar próxima posición
                        $valores['posicion'] = obtenerProximaPosicion($fila['max_posicion']);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            break;
    }
    
    return $valores;
}

// ✅ Función auxiliar para obtener próxima posición
function obtenerProximaPosicion($posicion_actual)
{
    // Ejemplo: Si posición actual es "1D", siguiente sería "2A"
    // Si posición actual es "4C", siguiente sería "4D"
    // Si posición actual es "4D", siguiente sería "5A"
    
    if (empty($posicion_actual)) return '1A';
    
    // Extraer número y letra
    preg_match('/(\d+)([A-D])/i', $posicion_actual, $matches);
    if (count($matches) < 3) return '1A';
    
    $numero = intval($matches[1]);
    $letra = strtoupper($matches[2]);
    
    // Determinar siguiente letra
    $letras = ['A', 'B', 'C', 'D'];
    $indice_letra = array_search($letra, $letras);
    
    if ($indice_letra < 3) {
        // Misma fila, siguiente columna
        $nueva_letra = $letras[$indice_letra + 1];
        return $numero . $nueva_letra;
    } else {
        // Siguiente fila, primera columna
        return ($numero + 1) . 'A';
    }
}
?>