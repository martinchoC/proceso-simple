<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de IVA alícuotas
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
        'nombre_funcion' => 'Agregar Alícuota',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Obtener estado inicial para nuevas alícuotas
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
function ejecutarTransicionEstado($conexion, $iva_alicuota_id, $accion_js, $empresa_idx, $pagina_id)
{
    $iva_alicuota_id = intval($iva_alicuota_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT iva_alicuota_id, tabla_estado_registro_id 
                  FROM gestion__impuestos__iva_alicuotas 
                  WHERE iva_alicuota_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $iva_alicuota_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $alicuota = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$alicuota)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $alicuota['tabla_estado_registro_id'];

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

    $sql_update = "UPDATE gestion__impuestos__iva_alicuotas 
                   SET tabla_estado_registro_id = ? 
                   WHERE iva_alicuota_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $iva_alicuota_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// ✅ Obtener todas las alícuotas IVA
function obtenerIvaAlicuotas($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $empresa_idx = mysqli_real_escape_string($conexion, $empresa_idx);

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

    $sql = "SELECT ia.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__impuestos__iva_alicuotas ia
            LEFT JOIN conf__estados_registros er ON ia.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            WHERE ia.empresa_id = ?
            ORDER BY ia.codigo";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return [];

    mysqli_stmt_bind_param($stmt, "s", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        // Determinar tipo de IVA para mostrar
        $tipo_iva = 'Desconocido';
        if ($fila['es_gravado'] == 1) {
            $tipo_iva = 'Gravado';
        } elseif ($fila['es_exento'] == 1) {
            $tipo_iva = 'Exento';
        } elseif ($fila['es_no_gravado'] == 1) {
            $tipo_iva = 'No Gravado';
        }

        $color_clase = $fila['color_clase'] ?? 'btn-dark';
        $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
        $text_clase = $fila['text_clase'] ?? 'text-white';

        $fila['tipo_iva'] = $tipo_iva;
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

// ✅ Agregar nueva alícuota IVA (con estado inicial)
function agregarIvaAlicuota($conexion, $data)
{
    $empresa_id = mysqli_real_escape_string($conexion, $data['empresa_id'] ?? '');
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $iva_alicuota = mysqli_real_escape_string($conexion, trim($data['iva_alicuota'] ?? ''));
    $porcentaje = floatval($data['porcentaje'] ?? 0);
    $es_gravado = intval($data['es_gravado'] ?? 1);
    $es_exento = intval($data['es_exento'] ?? 0);
    $es_no_gravado = intval($data['es_no_gravado'] ?? 0);

    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (empty($iva_alicuota)) {
        return ['resultado' => false, 'error' => 'La descripción es obligatoria'];
    }

    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if (strlen($iva_alicuota) > 100) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 100 caracteres'];
    }

    if ($porcentaje < 0 || $porcentaje > 100) {
        return ['resultado' => false, 'error' => 'El porcentaje debe estar entre 0 y 100'];
    }

    // Verificar que solo un tipo esté activo
    $tipos_activos = ($es_gravado ? 1 : 0) + ($es_exento ? 1 : 0) + ($es_no_gravado ? 1 : 0);
    if ($tipos_activos != 1) {
        return ['resultado' => false, 'error' => 'Debe seleccionar exactamente un tipo de IVA'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    // Verificar duplicados (mismo código en la misma empresa)
    $sql_check = "SELECT COUNT(*) as total FROM gestion__impuestos__iva_alicuotas 
                  WHERE empresa_id = ? AND codigo = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ss", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe una alícuota con este código en esta empresa'];
    }

    // Insertar nueva alícuota IVA
    $sql = "INSERT INTO gestion__impuestos__iva_alicuotas 
            (empresa_id, codigo, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "sssddiii", $empresa_id, $codigo, $iva_alicuota, $porcentaje, 
                          $es_gravado, $es_exento, $es_no_gravado, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $iva_alicuota_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'iva_alicuota_id' => $iva_alicuota_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear la alícuota IVA'];
    }
}

// ✅ Editar alícuota IVA existente
function editarIvaAlicuota($conexion, $id, $data)
{
    $id = intval($id);
    $empresa_id = mysqli_real_escape_string($conexion, $data['empresa_id'] ?? '');
    $codigo = mysqli_real_escape_string($conexion, trim($data['codigo'] ?? ''));
    $iva_alicuota = mysqli_real_escape_string($conexion, trim($data['iva_alicuota'] ?? ''));
    $porcentaje = floatval($data['porcentaje'] ?? 0);
    $es_gravado = intval($data['es_gravado'] ?? 1);
    $es_exento = intval($data['es_exento'] ?? 0);
    $es_no_gravado = intval($data['es_no_gravado'] ?? 0);

    if (empty($codigo)) {
        return ['resultado' => false, 'error' => 'El código es obligatorio'];
    }

    if (empty($iva_alicuota)) {
        return ['resultado' => false, 'error' => 'La descripción es obligatoria'];
    }

    if (strlen($codigo) > 10) {
        return ['resultado' => false, 'error' => 'El código no puede exceder los 10 caracteres'];
    }

    if (strlen($iva_alicuota) > 100) {
        return ['resultado' => false, 'error' => 'La descripción no puede exceder los 100 caracteres'];
    }

    if ($porcentaje < 0 || $porcentaje > 100) {
        return ['resultado' => false, 'error' => 'El porcentaje debe estar entre 0 y 100'];
    }

    // Verificar que solo un tipo esté activo
    $tipos_activos = ($es_gravado ? 1 : 0) + ($es_exento ? 1 : 0) + ($es_no_gravado ? 1 : 0);
    if ($tipos_activos != 1) {
        return ['resultado' => false, 'error' => 'Debe seleccionar exactamente un tipo de IVA'];
    }

    // Verificar que la alícuota exista
    $sql_check = "SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas 
                  WHERE iva_alicuota_id = ?";
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

    // Verificar duplicados (mismo código en la misma empresa, excluyendo registro actual)
    $sql_duplicate = "SELECT COUNT(*) as total FROM gestion__impuestos__iva_alicuotas 
                      WHERE empresa_id = ? AND codigo = ? AND iva_alicuota_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_duplicate);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssi", $empresa_id, $codigo, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row['total'] > 0) {
        return ['resultado' => false, 'error' => 'Ya existe otra alícuota con este código en esta empresa'];
    }

    // Actualizar alícuota IVA
    $sql = "UPDATE gestion__impuestos__iva_alicuotas 
            SET codigo = ?, iva_alicuota = ?, porcentaje = ?, 
                es_gravado = ?, es_exento = ?, es_no_gravado = ? 
            WHERE iva_alicuota_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssddiii", $codigo, $iva_alicuota, $porcentaje, 
                          $es_gravado, $es_exento, $es_no_gravado, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar la alícuota IVA'];
    }
}

// ✅ Obtener alícuota IVA específica
function obtenerIvaAlicuotaPorId($conexion, $id, $empresa_idx)
{
    $id = intval($id);
    $empresa_idx = mysqli_real_escape_string($conexion, $empresa_idx);

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

    $sql = "SELECT ia.*, er.$estado_column as estado_registro, er.codigo_estandar
            FROM gestion__impuestos__iva_alicuotas ia
            LEFT JOIN conf__estados_registros er ON ia.tabla_estado_registro_id = er.estado_registro_id
            WHERE ia.iva_alicuota_id = ? AND ia.empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "is", $id, $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $alicuota = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $alicuota;
}
?>