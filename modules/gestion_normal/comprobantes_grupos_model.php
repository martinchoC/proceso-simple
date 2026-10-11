<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de grupos y subgrupos de comprobantes
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// ===========================================
// FUNCIONES GENERALES
// ===========================================

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
        'nombre_funcion' => 'Agregar Grupo',
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
    if (!$result)
        return 1;

    $fila = mysqli_fetch_assoc($result);
    return $fila ? $fila['estado_registro_id'] : 1;
}

// ===========================================
// FUNCIONES PARA GRUPOS DE COMPROBANTES
// ===========================================

// ✅ Obtener grupos para select
function obtenerGruposParaSelect($conexion, $empresa_idx)
{
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT comprobante_grupo_id, comprobante_grupo 
            FROM gestion__comprobantes_grupos 
            WHERE empresa_id = ? AND tabla_estado_registro_id IN (1, 2) 
            ORDER BY orden, comprobante_grupo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $grupos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $grupos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $grupos;
}

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones para grupos
function ejecutarTransicionEstadoGrupo($conexion, $comprobante_grupo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $comprobante_grupo_id = intval($comprobante_grupo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    // Verificar que el grupo pertenezca a la empresa
    $sql_check = "SELECT comprobante_grupo_id, tabla_estado_registro_id 
                  FROM gestion__comprobantes_grupos 
                  WHERE comprobante_grupo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $comprobante_grupo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $grupo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$grupo)
        return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];

    $estado_actual_id = $grupo['tabla_estado_registro_id'];

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
    $sql_update = "UPDATE gestion__comprobantes_grupos 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_grupo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $comprobante_grupo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener todos los grupos de comprobantes (con filtro multiempresa) - CORREGIDO
function obtenerComprobantesGrupos($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT cg.comprobante_grupo_id, cg.comprobante_grupo, cg.orden, 
                   cg.tabla_estado_registro_id,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__comprobantes_grupos cg
            LEFT JOIN conf__estados_registros er ON cg.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE cg.empresa_id = ?
            ORDER BY cg.orden, cg.comprobante_grupo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
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

// ✅ Agregar nuevo grupo de comprobantes (con estado inicial) - CORREGIDO
function agregarComprobanteGrupo($conexion, $data)
{
    $comprobante_grupo = mysqli_real_escape_string($conexion, trim($data['comprobante_grupo'] ?? ''));
    $orden = intval($data['orden'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($comprobante_grupo)) {
        return ['resultado' => false, 'error' => 'El nombre del grupo es obligatorio'];
    }

    if (strlen($comprobante_grupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo nombre + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_grupos 
                  WHERE empresa_id = ? AND LOWER(comprobante_grupo) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $comprobante_grupo_lower = strtolower($comprobante_grupo);
    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $comprobante_grupo_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un grupo de comprobante con este nombre'];
    }

    // Insertar nuevo grupo - CORREGIDO: Incluir campo orden
    $sql = "INSERT INTO gestion__comprobantes_grupos (comprobante_grupo, empresa_id, orden, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $comprobante_grupo, $empresa_idx, $orden, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $comprobante_grupo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_grupo_id' => $comprobante_grupo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el grupo de comprobante'];
    }
}

// ✅ Editar grupo de comprobantes existente - CORREGIDO
function editarComprobanteGrupo($conexion, $id, $data)
{
    $id = intval($id);
    $comprobante_grupo = mysqli_real_escape_string($conexion, trim($data['comprobante_grupo'] ?? ''));
    $orden = intval($data['orden'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($comprobante_grupo)) {
        return ['resultado' => false, 'error' => 'El nombre del grupo es obligatorio'];
    }

    if (strlen($comprobante_grupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }

    // Verificar que el grupo pertenezca a la empresa
    $sql_check = "SELECT comprobante_grupo_id FROM gestion__comprobantes_grupos 
                  WHERE comprobante_grupo_id = ? AND empresa_id = ?";
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

    // Verificar duplicados (mismo nombre + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_grupos 
                      WHERE empresa_id = ? 
                      AND LOWER(comprobante_grupo) = LOWER(?) 
                      AND comprobante_grupo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $comprobante_grupo_lower = strtolower($comprobante_grupo);
    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $comprobante_grupo_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro grupo de comprobante con este nombre'];
    }

    // Actualizar grupo - CORREGIDO: Incluir campo orden
    $sql = "UPDATE gestion__comprobantes_grupos 
            SET comprobante_grupo = ?, orden = ? 
            WHERE comprobante_grupo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siii", $comprobante_grupo, $orden, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el grupo de comprobante'];
    }
}

// ✅ Obtener grupo específico - CORREGIDO
function obtenerComprobanteGrupoPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT cg.comprobante_grupo_id, cg.comprobante_grupo, cg.orden, 
                   cg.tabla_estado_registro_id,
                   er.estado_registro, er.codigo_estandar
            FROM gestion__comprobantes_grupos cg
            LEFT JOIN conf__estados_registros er ON cg.tabla_estado_registro_id = er.estado_registro_id
            WHERE cg.comprobante_grupo_id = ? AND cg.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $grupo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $grupo;
}

// ===========================================
// FUNCIONES PARA ASOCIACIÓN TABLA <-> SUBGRUPO DE COMPROBANTES
// (tabla_id ahora es una columna de gestion__comprobantes_subgrupos;
//  ya no existe/usa la tabla puente conf__tablas_comprobantes_subgrupos)
// ===========================================

// ✅ Tablas candidatas para el desplegable: las que ningún OTRO subgrupo activo
// tiene ya tomadas (se excluye el propio subgrupo que se está editando, para que
// su tabla actual no desaparezca del combo).
function obtenerTablasSinSubgrupoAsociado($conexion, $empresa_idx, $comprobante_subgrupo_id = 0)
{
    $empresa_idx = intval($empresa_idx);
    $comprobante_subgrupo_id = intval($comprobante_subgrupo_id);

    $sql = "SELECT t.tabla_id, t.tabla_nombre, t.tabla_descripcion
            FROM conf__tablas t
            WHERE t.tabla_estado_registro_id = 1
              AND NOT EXISTS (
                    SELECT 1 FROM gestion__comprobantes_subgrupos cs
                    WHERE cs.tabla_id = t.tabla_id
                      AND cs.empresa_id = ?
                      AND cs.tabla_estado_registro_id = 1
                      AND cs.comprobante_subgrupo_id != ?
              )
            ORDER BY t.tabla_nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $comprobante_subgrupo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tablas = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tablas[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $tablas;
}

// ✅ Verifica que una tabla no esté ya tomada por otro subgrupo (distinto del actual)
// antes de guardar. Devuelve un mensaje de error, o null si está disponible.
function validarTablaDisponible($conexion, $empresa_idx, $tabla_id, $comprobante_subgrupo_id_actual = 0)
{
    $tabla_id = intval($tabla_id);
    $empresa_idx = intval($empresa_idx);
    $comprobante_subgrupo_id_actual = intval($comprobante_subgrupo_id_actual);

    $sql = "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos
            WHERE tabla_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
            AND comprobante_subgrupo_id != ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return 'Error en la consulta';

    mysqli_stmt_bind_param($stmt, "iii", $tabla_id, $empresa_idx, $comprobante_subgrupo_id_actual);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existente = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $existente ? 'Esa tabla ya está asociada a otro subgrupo de comprobantes' : null;
}

// ===========================================
// FUNCIONES PARA SUBGRUPOS DE COMPROBANTES
// ===========================================

// ✅ Ejecutar transición de estado para subgrupos
function ejecutarTransicionEstadoSubgrupo($conexion, $comprobante_subgrupo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $comprobante_subgrupo_id = intval($comprobante_subgrupo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    // Verificar que el subgrupo pertenezca a la empresa
    $sql_check = "SELECT cs.comprobante_subgrupo_id, cs.tabla_estado_registro_id 
                  FROM gestion__comprobantes_subgrupos cs
                  WHERE cs.comprobante_subgrupo_id = ? AND cs.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $comprobante_subgrupo_id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subgrupo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$subgrupo)
        return ['success' => false, 'error' => 'Acceso denegado o registro no encontrado'];

    $estado_actual_id = $subgrupo['tabla_estado_registro_id'];

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
    $sql_update = "UPDATE gestion__comprobantes_subgrupos 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_subgrupo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iii", $estado_destino_id, $comprobante_subgrupo_id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener subgrupos agrupados por grupo para estructura jerárquica - CORREGIDO
function obtenerSubgruposAgrupadosPorGrupo($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT cs.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.orden,
                   cs.comprobante_grupo_id, cs.tabla_estado_registro_id,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   t.tabla_id, t.tabla_nombre
            FROM gestion__comprobantes_subgrupos cs
            LEFT JOIN conf__estados_registros er ON cs.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN conf__tablas t ON t.tabla_id = cs.tabla_id AND cs.tabla_id > 0
            WHERE cs.empresa_id = ?
            ORDER BY cs.comprobante_grupo_id, cs.orden, cs.comprobante_subgrupo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $subgruposAgrupados = [];
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

        $fila['tabla_asociada'] = $fila['tabla_id'] ? [
            'tabla_id' => $fila['tabla_id'],
            'tabla_nombre' => $fila['tabla_nombre']
        ] : null;

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        
        // Agrupar por grupo_id
        $grupo_id = $fila['comprobante_grupo_id'];
        if (!isset($subgruposAgrupados[$grupo_id])) {
            $subgruposAgrupados[$grupo_id] = [];
        }
        
        $subgruposAgrupados[$grupo_id][] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $subgruposAgrupados;
}

// ✅ Obtener TODOS los subgrupos - NUEVA FUNCIÓN
function obtenerComprobantesSubgruposTodos($conexion, $empresa_idx, $pagina_id)
{
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT cs.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.orden,
                   cs.comprobante_grupo_id, cs.tabla_estado_registro_id,
                   cg.comprobante_grupo,
                   er.estado_registro, er.codigo_estandar,
                   cc.color_clase, cc.bg_clase, cc.text_clase
            FROM gestion__comprobantes_subgrupos cs
            INNER JOIN gestion__comprobantes_grupos cg ON cs.comprobante_grupo_id = cg.comprobante_grupo_id
            LEFT JOIN conf__estados_registros er ON cs.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores cc ON er.color_id = cc.color_id
            WHERE cs.empresa_id = ?
            ORDER BY cs.orden, cs.comprobante_subgrupo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
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

        $fila['grupo_padre'] = [
            'comprobante_grupo_id' => $fila['comprobante_grupo_id'],
            'comprobante_grupo' => $fila['comprobante_grupo']
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        
        // Limpiar campos duplicados
        unset($fila['color_clase'], $fila['bg_clase'], $fila['text_clase']);
        
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Obtener subgrupos de un grupo específico - CORREGIDO
function obtenerComprobantesSubgrupos($conexion, $comprobante_grupo_id, $empresa_idx, $pagina_id)
{
    $comprobante_grupo_id = intval($comprobante_grupo_id);
    $empresa_idx = intval($empresa_idx);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT cs.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.orden,
                   cs.tabla_estado_registro_id,
                   er.estado_registro, er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__comprobantes_subgrupos cs
            LEFT JOIN conf__estados_registros er ON cs.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE cs.comprobante_grupo_id = ? AND cs.empresa_id = ?
            ORDER BY cs.orden, cs.comprobante_subgrupo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "ii", $comprobante_grupo_id, $empresa_idx);
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

// ✅ Agregar nuevo subgrupo de comprobantes - CORREGIDO
function agregarComprobanteSubgrupo($conexion, $data)
{
    $comprobante_subgrupo = mysqli_real_escape_string($conexion, trim($data['comprobante_subgrupo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $orden = intval($data['orden'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($comprobante_subgrupo)) {
        return ['resultado' => false, 'error' => 'El nombre del subgrupo es obligatorio'];
    }

    if (strlen($comprobante_subgrupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }

    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'ID de grupo inválido'];
    }

    // Tabla a asociar (opcional) — 0/vacío = sin tabla asociada
    $tabla_id = intval($data['tabla_id'] ?? 0);
    if ($tabla_id > 0) {
        $error_tabla = validarTablaDisponible($conexion, $empresa_idx, $tabla_id, 0);
        if ($error_tabla) {
            return ['resultado' => false, 'error' => $error_tabla];
        }
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo nombre + mismo grupo + misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_subgrupos 
                  WHERE empresa_id = ? AND comprobante_grupo_id = ? AND LOWER(comprobante_subgrupo) = LOWER(?)";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $comprobante_subgrupo_lower = strtolower($comprobante_subgrupo);
    mysqli_stmt_bind_param($stmt, "iis", $empresa_idx, $comprobante_grupo_id, $comprobante_subgrupo_lower);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un subgrupo con este nombre en el grupo seleccionado'];
    }

    // Insertar nuevo subgrupo - tabla_id ahora es columna propia (0 = sin tabla asociada)
    $sql = "INSERT INTO gestion__comprobantes_subgrupos (comprobante_subgrupo, comprobante_grupo_id, empresa_id, orden, tabla_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siiiii", $comprobante_subgrupo, $comprobante_grupo_id, $empresa_idx, $orden, $tabla_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $comprobante_subgrupo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_subgrupo_id' => $comprobante_subgrupo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el subgrupo de comprobante'];
    }
}

// ✅ Editar subgrupo de comprobantes existente - CORREGIDO
function editarComprobanteSubgrupo($conexion, $id, $data)
{
    $id = intval($id);
    $comprobante_subgrupo = mysqli_real_escape_string($conexion, trim($data['comprobante_subgrupo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $orden = intval($data['orden'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 0);

    if (empty($comprobante_subgrupo)) {
        return ['resultado' => false, 'error' => 'El nombre del subgrupo es obligatorio'];
    }

    if (strlen($comprobante_subgrupo) > 50) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 50 caracteres'];
    }

    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'ID de grupo inválido'];
    }

    // Tabla a asociar (opcional) — 0/vacío = sin tabla asociada
    $tabla_id = intval($data['tabla_id'] ?? 0);
    if ($tabla_id > 0) {
        $error_tabla = validarTablaDisponible($conexion, $empresa_idx, $tabla_id, $id);
        if ($error_tabla) {
            return ['resultado' => false, 'error' => $error_tabla];
        }
    }

    // Verificar que el subgrupo pertenezca a la empresa
    $sql_check = "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos 
                  WHERE comprobante_subgrupo_id = ? AND empresa_id = ?";
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

    // Verificar duplicados (mismo nombre + mismo grupo + misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_subgrupos 
                      WHERE empresa_id = ? 
                      AND comprobante_grupo_id = ? 
                      AND LOWER(comprobante_subgrupo) = LOWER(?) 
                      AND comprobante_subgrupo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    $comprobante_subgrupo_lower = strtolower($comprobante_subgrupo);
    mysqli_stmt_bind_param($stmt, "iisi", $empresa_idx, $comprobante_grupo_id, $comprobante_subgrupo_lower, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro subgrupo con este nombre en el mismo grupo'];
    }

    // Actualizar subgrupo - tabla_id ahora es columna propia (0 = sin tabla asociada)
    $sql = "UPDATE gestion__comprobantes_subgrupos 
            SET comprobante_subgrupo = ?, comprobante_grupo_id = ?, orden = ?, tabla_id = ? 
            WHERE comprobante_subgrupo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "siiiii", $comprobante_subgrupo, $comprobante_grupo_id, $orden, $tabla_id, $id, $empresa_idx);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el subgrupo de comprobante'];
    }
}

// ✅ Obtener subgrupo específico - CORREGIDO
function obtenerComprobanteSubgrupoPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = intval($empresa_idx);

    $sql = "SELECT cs.comprobante_subgrupo_id, cs.comprobante_subgrupo, cs.orden,
                   cs.comprobante_grupo_id, cs.tabla_estado_registro_id, cs.tabla_id,
                   er.estado_registro, er.codigo_estandar
            FROM gestion__comprobantes_subgrupos cs
            LEFT JOIN conf__estados_registros er ON cs.tabla_estado_registro_id = er.estado_registro_id
            WHERE cs.comprobante_subgrupo_id = ? AND cs.empresa_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subgrupo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $subgrupo;
}
?>