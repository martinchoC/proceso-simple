<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de tipos de comprobantes
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
        'nombre_funcion' => 'Agregar Tipo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevos tipos de comprobantes
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

// ✅ Ejecutar transición de estado basada en conf__paginas_funciones
function ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $comprobante_tipo_id = intval($comprobante_tipo_id);
    $pagina_id = intval($pagina_id);

    // Verificar que el tipo exista
    $sql_check = "SELECT comprobante_tipo_id, tabla_estado_registro_id 
                  FROM gestion__comprobantes_tipos 
                  WHERE comprobante_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $comprobante_tipo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$tipo)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $tipo['tabla_estado_registro_id'];

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
    $sql_update = "UPDATE gestion__comprobantes_tipos 
                   SET tabla_estado_registro_id = ? 
                   WHERE comprobante_tipo_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $comprobante_tipo_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}


// ✅ Obtener grupos de comprobantes
function obtenerGruposComprobantes($conexion, $empresa_idx)
{
    $sql = "SELECT comprobante_grupo_id, comprobante_grupo, orden
            FROM gestion__comprobantes_grupos
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1
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

// ✅ Obtener subgrupos de comprobantes
function obtenerSubgruposComprobantes($conexion, $empresa_idx, $grupo_id = 0)
{
    if ($grupo_id > 0) {
        $sql = "SELECT csg.comprobante_subgrupo_id, csg.comprobante_subgrupo, csg.orden
                FROM gestion__comprobantes_subgrupos csg
                WHERE csg.empresa_id = ? 
                AND csg.comprobante_grupo_id = ?
                AND csg.tabla_estado_registro_id = 1
                ORDER BY csg.orden, csg.comprobante_subgrupo";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt)
            return [];
        
        mysqli_stmt_bind_param($stmt, "ii", $empresa_idx, $grupo_id);
    } else {
        $sql = "SELECT csg.comprobante_subgrupo_id, csg.comprobante_subgrupo, csg.orden
                FROM gestion__comprobantes_subgrupos csg
                WHERE csg.empresa_id = ? 
                AND csg.tabla_estado_registro_id = 1
                ORDER BY csg.orden, csg.comprobante_subgrupo";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt)
            return [];
        
        mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $subgrupos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $subgrupos[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $subgrupos;
}

// ✅ Obtener comprobantes fiscales
function obtenerComprobantesFiscales($conexion, $empresa_idx)
{
    $sql = "SELECT cf.comprobante_fiscal_id, 
                   LPAD(cf.codigo, 3, '0') as codigo_pad,
                   cf.comprobante_fiscal
            FROM gestion__comprobantes_fiscales cf
            WHERE cf.tabla_estado_registro_id = 1
            ORDER BY cf.codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $fiscales = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fiscales[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $fiscales;
}

// ✅ Agregar nuevo tipo de comprobante
function agregarComprobanteTipo($conexion, $data)
{
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $comprobante_tipo = mysqli_real_escape_string($conexion, trim($data['comprobante_tipo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $comprobante_subgrupo_id = intval($data['comprobante_subgrupo_id'] ?? 0);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $letra = mysqli_real_escape_string($conexion, trim($data['letra'] ?? ''));
    $signo = mysqli_real_escape_string($conexion, $data['signo'] ?? '+');
    $orden = intval($data['orden'] ?? 1);
    $comentario = mysqli_real_escape_string($conexion, trim($data['comentario'] ?? ''));
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 2);

    // Validaciones
    if (empty($comprobante_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante es obligatorio'];
    }

    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (strlen($comprobante_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un grupo'];
    }

    if ($comprobante_subgrupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un subgrupo'];
    }

    if (!empty($letra) && strlen($letra) > 1) {
        return ['resultado' => false, 'error' => 'La letra debe ser un solo carácter'];
    }

    // Validar signo
    if (!in_array($signo, ['+', '-', '+/-'])) {
        $signo = '+';
    }

    // Verificar duplicados (mismo código)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__comprobantes_tipos 
                  WHERE empresa_id = ? AND codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un tipo de comprobante con este código'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Insertar nuevo tipo de comprobante
    $sql = "INSERT INTO gestion__comprobantes_tipos (
                empresa_id,
                comprobante_tipo,
                codigo,
                comprobante_grupo_id,
                comprobante_subgrupo_id,
                comprobante_fiscal_id,
                letra,
                signo,
                orden,
                comentario,
                impacta_stock,
                impacta_contabilidad,
                impacta_ctacte,
                tabla_estado_registro_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "issiiissisiiii", 
        $empresa_idx,
        $comprobante_tipo,
        $codigo,
        $comprobante_grupo_id,
        $comprobante_subgrupo_id,
        $comprobante_fiscal_id,
        $letra,
        $signo,
        $orden,
        $comentario,
        $impacta_stock,
        $impacta_contabilidad,
        $impacta_ctacte,
        $estado_inicial
    );

    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $comprobante_tipo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_tipo_id' => $comprobante_tipo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el tipo de comprobante'];
    }
}

// ✅ Editar tipo de comprobante existente
function editarComprobanteTipo($conexion, $id, $data)
{
    $id = intval($id);
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $comprobante_tipo = mysqli_real_escape_string($conexion, trim($data['comprobante_tipo'] ?? ''));
    $comprobante_grupo_id = intval($data['comprobante_grupo_id'] ?? 0);
    $comprobante_subgrupo_id = intval($data['comprobante_subgrupo_id'] ?? 0);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $letra = mysqli_real_escape_string($conexion, trim($data['letra'] ?? ''));
    $signo = mysqli_real_escape_string($conexion, $data['signo'] ?? '+');
    $orden = intval($data['orden'] ?? 1);
    $comentario = mysqli_real_escape_string($conexion, trim($data['comentario'] ?? ''));
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $empresa_idx = intval($data['empresa_idx'] ?? 2);

    // Validaciones
    if (empty($comprobante_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante es obligatorio'];
    }

    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (strlen($comprobante_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }

    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if ($comprobante_grupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un grupo'];
    }

    if ($comprobante_subgrupo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un subgrupo'];
    }

    if (!empty($letra) && strlen($letra) > 1) {
        return ['resultado' => false, 'error' => 'La letra debe ser un solo carácter'];
    }

    // Validar signo
    if (!in_array($signo, ['+', '-', '+/-'])) {
        $signo = '+';
    }

    // Verificar que el tipo exista
    $sql_check = "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos 
                  WHERE comprobante_tipo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($result) == 0) {
        return ['resultado' => false, 'error' => 'Registro no encontrado'];
    }

    // Verificar duplicados (mismo código, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_tipos 
                      WHERE empresa_id = ? AND codigo = ? AND comprobante_tipo_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "isi", $empresa_idx, $codigo, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otro tipo de comprobante con este código'];
    }

    // Actualizar tipo de comprobante
    $sql = "UPDATE gestion__comprobantes_tipos 
            SET comprobante_tipo = ?,
                codigo = ?,
                comprobante_grupo_id = ?,
                comprobante_subgrupo_id = ?,
                comprobante_fiscal_id = ?,
                letra = ?,
                signo = ?,
                orden = ?,
                comentario = ?,
                impacta_stock = ?,
                impacta_contabilidad = ?,
                impacta_ctacte = ?
            WHERE comprobante_tipo_id = ? AND empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiiissisiiiii", 
        $comprobante_tipo,
        $codigo,
        $comprobante_grupo_id,
        $comprobante_subgrupo_id,
        $comprobante_fiscal_id,
        $letra,
        $signo,
        $orden,
        $comentario,
        $impacta_stock,
        $impacta_contabilidad,
        $impacta_ctacte,
        $id,
        $empresa_idx
    );

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el tipo de comprobante'];
    }
}

// ✅ Obtener tipo de comprobante específico
function obtenerComprobanteTipoPorId($conexion, $id, $empresa_idx)
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

    $sql = "SELECT ct.*, er.$estado_column as estado_registro, er.codigo_estandar
            FROM gestion__comprobantes_tipos ct
            LEFT JOIN conf__estados_registros er ON ct.tabla_estado_registro_id = er.estado_registro_id
            WHERE ct.comprobante_tipo_id = ? AND ct.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $tipo;
}
// ✅ Obtener todos los tipos de comprobantes CON FILTROS
function obtenerComprobantesTipos($conexion, $empresa_idx, $pagina_id, $filtros = [])
{
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

    // Verificar estructura de la tabla conf__estados_registros
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

    // Consulta principal con joins a todas las tablas relacionadas
    $sql = "SELECT 
                ct.*,
                er.$estado_column as estado_registro,
                er.codigo_estandar,
                cg.comprobante_grupo,
                csg.comprobante_subgrupo,
                cf.comprobante_fiscal,
                LPAD(cf.codigo, 3, '0') as codigo_fiscal,
                ec.color_clase, ec.bg_clase, ec.text_clase
            FROM gestion__comprobantes_tipos ct
            LEFT JOIN conf__estados_registros er ON ct.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN gestion__comprobantes_grupos cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            LEFT JOIN gestion__comprobantes_subgrupos csg ON ct.comprobante_subgrupo_id = csg.comprobante_subgrupo_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON ct.comprobante_fiscal_id = cf.comprobante_fiscal_id
            LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
            WHERE ct.empresa_id = ?";
    
    // Aplicar filtros
    $params = [$empresa_idx];
    $param_types = "i";
    
    if (!empty($filtros['grupo_id'])) {
        $sql .= " AND ct.comprobante_grupo_id = ?";
        $params[] = $filtros['grupo_id'];
        $param_types .= "i";
    }
    
    if (!empty($filtros['subgrupo_id'])) {
        $sql .= " AND ct.comprobante_subgrupo_id = ?";
        $params[] = $filtros['subgrupo_id'];
        $param_types .= "i";
    }
    
    if (!empty($filtros['signo'])) {
        $sql .= " AND ct.signo = ?";
        $params[] = $filtros['signo'];
        $param_types .= "s";
    }
    
    if (!empty($filtros['estado_id'])) {
        $sql .= " AND ct.tabla_estado_registro_id = ?";
        $params[] = $filtros['estado_id'];
        $param_types .= "i";
    }
    
    $sql .= " ORDER BY ct.orden, ct.codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    // Vincular parámetros dinámicamente
    if (count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Si no hay color configurado, usar dark por defecto
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

        $fila['grupo_info'] = [
            'comprobante_grupo' => $fila['comprobante_grupo'] ?? 'Sin grupo',
            'comprobante_grupo_id' => $fila['comprobante_grupo_id']
        ];

        $fila['subgrupo_info'] = [
            'comprobante_subgrupo' => $fila['comprobante_subgrupo'] ?? 'Sin subgrupo',
            'comprobante_subgrupo_id' => $fila['comprobante_subgrupo_id']
        ];

        $fila['fiscal_info'] = [
            'comprobante_fiscal' => $fila['comprobante_fiscal'] ?? 'Sin fiscal',
            'codigo_fiscal' => $fila['codigo_fiscal'] ?? ''
        ];

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// ✅ Obtener estados disponibles para filtros
function obtenerEstadosDisponibles($conexion)
{
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

    $sql = "SELECT estado_registro_id, $estado_column as estado_registro
            FROM conf__estados_registros
            WHERE tabla_estado_registro_id = 1
            ORDER BY $estado_column";

    $result = mysqli_query($conexion, $sql);
    if (!$result)
        return [];

    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = $fila;
    }

    return $estados;
}
// ✅ Copiar tipo de comprobante existente
function copiarComprobanteTipo($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_idx = intval($data['empresa_idx'] ?? 2);
    
    // Verificar que el tipo original exista
    $sql_check = "SELECT * FROM gestion__comprobantes_tipos 
                  WHERE comprobante_tipo_id = ? AND empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $original = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$original) {
        return ['resultado' => false, 'error' => 'Tipo de comprobante original no encontrado'];
    }
    
    // Validar datos del nuevo tipo
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $comprobante_tipo = mysqli_real_escape_string($conexion, trim($data['comprobante_tipo'] ?? ''));
    
    if (empty($comprobante_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de comprobante es obligatorio'];
    }
    
    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }
    
    if (strlen($comprobante_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El nombre no puede exceder los 100 caracteres'];
    }
    
    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }
    
    // Verificar que el nuevo código no exista
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__comprobantes_tipos 
                      WHERE empresa_id = ? AND codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "is", $empresa_idx, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe un tipo de comprobante con este código'];
    }
    
    $estado_inicial = obtenerEstadoInicial($conexion);
    
    // Insertar la copia del tipo de comprobante
    $sql = "INSERT INTO gestion__comprobantes_tipos (
                empresa_id,
                comprobante_tipo,
                codigo,
                comprobante_grupo_id,
                comprobante_subgrupo_id,
                comprobante_fiscal_id,
                letra,
                signo,
                orden,
                comentario,
                impacta_stock,
                impacta_contabilidad,
                impacta_ctacte,
                tabla_estado_registro_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];
    
    mysqli_stmt_bind_param($stmt, "issiiissisiiii", 
        $empresa_idx,
        $comprobante_tipo,
        $codigo,
        $original['comprobante_grupo_id'],
        $original['comprobante_subgrupo_id'],
        $original['comprobante_fiscal_id'],
        $original['letra'],
        $original['signo'],
        $original['orden'],
        $original['comentario'],
        $original['impacta_stock'],
        $original['impacta_contabilidad'],
        $original['impacta_ctacte'],
        $estado_inicial
    );
    
    $success = mysqli_stmt_execute($stmt);
    
    if ($success) {
        $comprobante_tipo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'comprobante_tipo_id' => $comprobante_tipo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al copiar el tipo de comprobante'];
    }
}
?>