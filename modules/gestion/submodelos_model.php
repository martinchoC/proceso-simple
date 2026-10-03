<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de submodelos - Versión simplificada
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
        'nombre_funcion' => 'Agregar Submodelo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos submodelos
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
function ejecutarTransicionEstado($conexion, $submodelo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $submodelo_id = intval($submodelo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    // Verificar que el submodelo pertenezca a la empresa
    $sql_check = "SELECT submodelo_id, tabla_estado_registro_id 
                  FROM gestion__submodelos 
                  WHERE submodelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $submodelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $submodelo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$submodelo)
        return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];

    $estado_actual_id = $submodelo['tabla_estado_registro_id'];

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
    $sql_update = "UPDATE gestion__submodelos 
                   SET tabla_estado_registro_id = ? 
                   WHERE submodelo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $submodelo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener modelos activos para el select (con sus marcas)
function obtenerModelosActivos($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT m.modelo_id, m.modelo_nombre, ma.marca_nombre
            FROM gestion__modelos m
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id AND ma.empresa_id = ?
            WHERE m.empresa_id = ? 
            AND m.tabla_estado_registro_id = 1  -- Solo modelos activos
            AND ma.tabla_estado_registro_id = 1 -- Solo marcas activas
            ORDER BY ma.marca_nombre, m.modelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $modelos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $modelos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $modelos;
}

// ✅ Obtener todos los submodelos (con filtro multiempresa y joins para modelo y marca)
function obtenerSubmodelos($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT sm.*, 
                   m.modelo_nombre,
                   ma.marca_nombre,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__submodelos sm
            INNER JOIN gestion__modelos m ON sm.modelo_id = m.modelo_id AND m.empresa_id = ?
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id AND ma.empresa_id = ?
            LEFT JOIN conf__estados_registros er ON sm.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE sm.empresa_id = ?
            ORDER BY ma.marca_nombre, m.modelo_nombre, sm.submodelo_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "iii", $empresa_idx, $empresa_idx, $empresa_idx);
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

// ✅ Agregar nuevo submodelo (con estado inicial)
function agregarSubmodelo($conexion, $data)
{
    $submodelo_nombre = mysqli_real_escape_string($conexion, trim($data['submodelo_nombre'] ?? ''));
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($submodelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del submodelo es obligatorio'];
    }

    if ($modelo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un modelo válido'];
    }

    if (strlen($submodelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar que el modelo pertenezca a la empresa
    $sql_check_modelo = "SELECT modelo_id FROM gestion__modelos 
                         WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_modelo);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $modelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El modelo seleccionado no pertenece a esta empresa'];
    }

    // Verificar duplicados (mismo nombre + mismo modelo + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__submodelos 
                  WHERE empresa_id = ? AND modelo_id = ? AND LOWER(submodelo_nombre) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $submodelo_nombre_lower = strtolower($submodelo_nombre);
    mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $modelo_id, $submodelo_nombre_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un submodelo con este nombre en el modelo seleccionado'];
    }

    // Insertar nuevo submodelo
    $sql = "INSERT INTO gestion__submodelos (submodelo_nombre, empresa_id, modelo_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $submodelo_nombre, $empresa_idx, $modelo_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $submodelo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'submodelo_id' => $submodelo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el submodelo'];
    }
}

// ✅ Editar submodelo existente
function editarSubmodelo($conexion, $id, $data)
{
    $id = intval($id);
    $submodelo_nombre = mysqli_real_escape_string($conexion, trim($data['submodelo_nombre'] ?? ''));
    $modelo_id = intval($data['modelo_id'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($submodelo_nombre)) {
        return ['resultado' => false, 'error' => 'El nombre del submodelo es obligatorio'];
    }

    if ($modelo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un modelo válido'];
    }

    if (strlen($submodelo_nombre) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    // Verificar que el submodelo pertenezca a la empresa
    $sql_check = "SELECT submodelo_id FROM gestion__submodelos 
                  WHERE submodelo_id = ? AND empresa_id = ?";
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

    // Verificar que el modelo pertenezca a la empresa
    $sql_check_modelo = "SELECT modelo_id FROM gestion__modelos 
                         WHERE modelo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check_modelo);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $modelo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'El modelo seleccionado no pertenece a esta empresa'];
    }

    // Verificar duplicados (mismo nombre + mismo modelo + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__submodelos 
                      WHERE empresa_id = ? AND modelo_id = ? 
                      AND LOWER(submodelo_nombre) = LOWER(?) AND submodelo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $submodelo_nombre_lower = strtolower($submodelo_nombre);
    mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $modelo_id, $submodelo_nombre_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro submodelo con este nombre en el modelo seleccionado'];
    }

    // Actualizar submodelo
    $sql = "UPDATE gestion__submodelos 
            SET submodelo_nombre = ?, modelo_id = ? 
            WHERE submodelo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $submodelo_nombre, $modelo_id, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el submodelo'];
    }
}

// ✅ Obtener submodelo específico
function obtenerSubmodeloPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT sm.*, er.estado_registro, er.codigo_estandar
            FROM gestion__submodelos sm
            LEFT JOIN conf__estados_registros er ON sm.tabla_estado_registro_id = er.estado_registro_id
            WHERE sm.submodelo_id = ? AND sm.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $submodelo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $submodelo;
}

// ✅ Resumen de productos por marca → modelo → submodelo (pestaña dashboard)
// Se cuentan productos DISTINTOS activos con compatibilidad activa en
// gestion__productos_compatibilidad. Los conteos de cada nivel se calculan
// por separado (no se suman los hijos) porque un mismo producto puede ser
// compatible con varios submodelos/modelos y se contaría dos veces.
// Compatibilidades con submodelo_id NULL/0 aplican a todo el modelo y se
// informan aparte como "sin submodelo".
function obtenerResumenProductosJerarquia($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);
    error_log("=== INICIO obtenerResumenProductosJerarquia empresa: $empresa_idx ===");

    // submodelo_id va "crudo" (sin NULLIF): con una función sobre la columna
    // el JOIN no puede usar idx_compat_submodelo. NULL y 0 = sin submodelo.
    $sql_base = "SELECT pc.producto_id, pc.marca_id, pc.modelo_id, pc.submodelo_id
                 FROM gestion__productos_compatibilidad pc
                 INNER JOIN gestion__productos p ON p.producto_id = pc.producto_id
                     AND p.empresa_id = pc.empresa_id
                     AND p.tabla_estado_registro_id = 1
                 WHERE pc.empresa_id = ? AND pc.tabla_estado_registro_id = 1";

    $ejecutar = function ($sql, $tipos, ...$params) use ($conexion) {
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error al preparar consulta: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $filas = [];
        while ($fila = mysqli_fetch_assoc($result)) {
            $filas[] = $fila;
        }
        mysqli_stmt_close($stmt);
        return $filas;
    };

    try {
        // KPIs generales
        $kpi_productos = $ejecutar(
            "SELECT COUNT(*) AS total FROM gestion__productos
             WHERE empresa_id = ? AND tabla_estado_registro_id = 1",
            "i", $empresa_idx
        );
        $kpi_compat = $ejecutar(
            "SELECT COUNT(DISTINCT x.producto_id) AS total FROM ($sql_base) x",
            "i", $empresa_idx
        );

        // Nivel marca
        $marcas = $ejecutar(
            "SELECT ma.marca_id, ma.marca_nombre, ma.tabla_estado_registro_id,
                    COUNT(DISTINCT x.producto_id) AS productos
             FROM gestion__marcas ma
             LEFT JOIN ($sql_base) x ON x.marca_id = ma.marca_id
             WHERE ma.empresa_id = ?
             GROUP BY ma.marca_id, ma.marca_nombre, ma.tabla_estado_registro_id
             ORDER BY ma.marca_nombre",
            "ii", $empresa_idx, $empresa_idx
        );

        // Nivel modelo
        $modelos = $ejecutar(
            "SELECT m.modelo_id, m.marca_id, m.modelo_nombre, m.tabla_estado_registro_id,
                    COUNT(DISTINCT x.producto_id) AS productos,
                    COUNT(DISTINCT CASE WHEN x.submodelo_id IS NULL OR x.submodelo_id = 0 THEN x.producto_id END) AS sin_submodelo
             FROM gestion__modelos m
             LEFT JOIN ($sql_base) x ON x.modelo_id = m.modelo_id
             WHERE m.empresa_id = ?
             GROUP BY m.modelo_id, m.marca_id, m.modelo_nombre, m.tabla_estado_registro_id
             ORDER BY m.modelo_nombre",
            "ii", $empresa_idx, $empresa_idx
        );

        // Nivel submodelo
        $submodelos = $ejecutar(
            "SELECT s.submodelo_id, s.modelo_id, s.submodelo_nombre, s.tabla_estado_registro_id,
                    COUNT(DISTINCT x.producto_id) AS productos
             FROM gestion__submodelos s
             LEFT JOIN ($sql_base) x ON x.submodelo_id = s.submodelo_id
             WHERE s.empresa_id = ?
             GROUP BY s.submodelo_id, s.modelo_id, s.submodelo_nombre, s.tabla_estado_registro_id
             ORDER BY s.submodelo_nombre",
            "ii", $empresa_idx, $empresa_idx
        );
    } catch (Exception $e) {
        error_log("obtenerResumenProductosJerarquia: " . $e->getMessage());
        return ['success' => false, 'data' => null, 'message' => 'Error al obtener el resumen de productos'];
    }

    // Botones del submodelo según su estado (motor de estados, igual que en
    // el listado). Se resuelven una vez por estado, no por fila.
    $botones_por_estado = [];
    $botones = function ($estado_id) use ($conexion, $pagina_id, &$botones_por_estado) {
        if (!array_key_exists($estado_id, $botones_por_estado)) {
            $botones_por_estado[$estado_id] = obtenerBotonesPorEstado($conexion, $pagina_id, $estado_id);
        }
        return $botones_por_estado[$estado_id];
    };

    // Estado legible de cada registro (para la lista "sin productos")
    $estados = [];
    $nombre_estado = function ($estado_id) use ($conexion, &$estados) {
        if (!array_key_exists($estado_id, $estados)) {
            $info = obtenerInfoEstado($conexion, $estado_id);
            $estados[$estado_id] = $info['estado_registro'] ?? 'Sin estado';
        }
        return $estados[$estado_id];
    };

    // Armar el árbol
    $subs_por_modelo = [];
    foreach ($submodelos as $s) {
        $estado_id = (int) $s['tabla_estado_registro_id'];
        $botones_sub = $botones($estado_id);
        $boton_editar = null;
        foreach ($botones_sub as $b) {
            if ($b['accion_js'] === 'editar') {
                $boton_editar = $b;
                break;
            }
        }
        $subs_por_modelo[$s['modelo_id']][] = [
            'id' => (int) $s['submodelo_id'],
            'nombre' => $s['submodelo_nombre'],
            'activo' => $estado_id === 1,
            'estado' => $nombre_estado($estado_id),
            'productos' => (int) $s['productos'],
            'boton_editar' => $boton_editar
        ];
    }

    // Listas planas de registros sin productos (dashboard, para depurarlos).
    // Los submodelos "huérfanos" (modelo_id inexistente en la empresa) no
    // aparecen en el árbol ni en el listado (INNER JOIN), así que se
    // incluyen acá aunque tengan productos.
    $marca_nombre = array_column($marcas, 'marca_nombre', 'marca_id');
    $modelo_info = [];
    foreach ($modelos as $m) {
        $modelo_info[$m['modelo_id']] = $m;
    }
    $hijos = function ($filas, $campo) {
        $c = [];
        foreach ($filas as $f) {
            $c[$f[$campo]] = ($c[$f[$campo]] ?? 0) + 1;
        }
        return $c;
    };
    $modelos_por_marca_cnt = $hijos($modelos, 'marca_id');
    $subs_por_modelo_cnt = $hijos($submodelos, 'modelo_id');

    $sin_productos = ['marcas' => [], 'modelos' => [], 'submodelos' => []];
    foreach ($marcas as $ma) {
        if ((int) $ma['productos'] > 0) continue;
        $sin_productos['marcas'][] = [
            'id' => (int) $ma['marca_id'],
            'nombre' => $ma['marca_nombre'],
            'estado' => $nombre_estado((int) $ma['tabla_estado_registro_id']),
            'hijos' => $modelos_por_marca_cnt[$ma['marca_id']] ?? 0
        ];
    }
    foreach ($modelos as $m) {
        if ((int) $m['productos'] > 0) continue;
        $sin_productos['modelos'][] = [
            'id' => (int) $m['modelo_id'],
            'nombre' => $m['modelo_nombre'],
            'marca' => $marca_nombre[$m['marca_id']] ?? null,
            'estado' => $nombre_estado((int) $m['tabla_estado_registro_id']),
            'hijos' => $subs_por_modelo_cnt[$m['modelo_id']] ?? 0
        ];
    }
    foreach ($submodelos as $s) {
        $mo = $modelo_info[$s['modelo_id']] ?? null;
        if ((int) $s['productos'] > 0 && $mo) continue;
        $sin_productos['submodelos'][] = [
            'id' => (int) $s['submodelo_id'],
            'nombre' => $s['submodelo_nombre'],
            'modelo' => $mo['modelo_nombre'] ?? null,
            'marca' => $mo ? ($marca_nombre[$mo['marca_id']] ?? null) : null,
            'huerfano' => $mo === null,
            'productos' => (int) $s['productos'],
            'estado' => $nombre_estado((int) $s['tabla_estado_registro_id']),
            'botones' => $botones((int) $s['tabla_estado_registro_id'])
        ];
    }

    $modelos_por_marca = [];
    foreach ($modelos as $m) {
        $modelos_por_marca[$m['marca_id']][] = [
            'id' => (int) $m['modelo_id'],
            'nombre' => $m['modelo_nombre'],
            'activo' => (int) $m['tabla_estado_registro_id'] === 1,
            'estado' => $nombre_estado((int) $m['tabla_estado_registro_id']),
            'productos' => (int) $m['productos'],
            'sin_submodelo' => (int) $m['sin_submodelo'],
            'submodelos' => $subs_por_modelo[$m['modelo_id']] ?? []
        ];
    }

    $arbol = [];
    foreach ($marcas as $ma) {
        $arbol[] = [
            'id' => (int) $ma['marca_id'],
            'nombre' => $ma['marca_nombre'],
            'activo' => (int) $ma['tabla_estado_registro_id'] === 1,
            'estado' => $nombre_estado((int) $ma['tabla_estado_registro_id']),
            'productos' => (int) $ma['productos'],
            'modelos' => $modelos_por_marca[$ma['marca_id']] ?? []
        ];
    }

    $con_productos = function ($filas) {
        return count(array_filter($filas, function ($f) {
            return (int) $f['productos'] > 0;
        }));
    };

    return [
        'success' => true,
        'data' => [
            'kpis' => [
                'productos_activos' => (int) ($kpi_productos[0]['total'] ?? 0),
                'productos_con_compatibilidad' => (int) ($kpi_compat[0]['total'] ?? 0),
                'marcas_total' => count($marcas),
                'marcas_con_productos' => $con_productos($marcas),
                'modelos_total' => count($modelos),
                'modelos_con_productos' => $con_productos($modelos),
                'submodelos_total' => count($submodelos),
                'submodelos_con_productos' => $con_productos($submodelos)
            ],
            'marcas' => $arbol,
            'sin_productos' => $sin_productos
        ],
        'message' => ''
    ];
}

// ✅ Productos de un nodo hoja del resumen (carga diferida al expandir).
// $tipo = 'submodelo' → $id es submodelo_id
// $tipo = 'sin_submodelo' → $id es modelo_id (compatibilidades sin submodelo)
function obtenerProductosNodoResumen($conexion, $empresa_idx, $tipo, $id)
{
    $empresa_idx = intval($empresa_idx);
    $id = intval($id);

    if ($id <= 0 || !in_array($tipo, ['submodelo', 'sin_submodelo'], true)) {
        return ['success' => false, 'data' => [], 'message' => 'Parámetros inválidos'];
    }

    $filtro = $tipo === 'submodelo'
        ? "pc.submodelo_id = ?"
        : "pc.modelo_id = ? AND (pc.submodelo_id IS NULL OR pc.submodelo_id = 0)";

    $sql = "SELECT DISTINCT p.producto_id, p.producto_codigo, p.producto_nombre, p.producto_descripcion
            FROM gestion__productos_compatibilidad pc
            INNER JOIN gestion__productos p ON p.producto_id = pc.producto_id
                AND p.empresa_id = pc.empresa_id
                AND p.tabla_estado_registro_id = 1
            WHERE pc.empresa_id = ? AND pc.tabla_estado_registro_id = 1 AND $filtro
            ORDER BY p.producto_codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("obtenerProductosNodoResumen: " . mysqli_error($conexion));
        return ['success' => false, 'data' => [], 'message' => 'Error en la consulta'];
    }

    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $productos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $productos[] = $fila;
    }
    mysqli_stmt_close($stmt);

    return ['success' => true, 'data' => $productos, 'message' => ''];
}
?>