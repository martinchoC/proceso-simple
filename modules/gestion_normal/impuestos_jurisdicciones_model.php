<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de Impuestos por Jurisdicción
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// Obtener tipos de impuesto activos
function obtenerTiposImpuesto($conexion) {
    $sql = "SELECT impuesto_tipo_id, impuesto_tipo, codigo_afip, aplica_compra, aplica_venta, es_retencion, es_percepcion
            FROM gestion__impuestos_tipos 
            WHERE tabla_estado_registro_id = 1
            ORDER BY impuesto_tipo";
    
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

// Obtener jurisdicciones activas
function obtenerJurisdiccionesParaSelect($conexion) {
    $sql = "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre 
            FROM gestion__jurisdicciones 
            WHERE tabla_estado_registro_id = 1
            ORDER BY jurisdiccion_nombre";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    
    $jurisdicciones = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $jurisdicciones[] = $row;
    }
    
    return $jurisdicciones;
}

// Obtener cuentas contables
function obtenerCuentasContables($conexion) {
    $sql = "SELECT cuenta_contable_id, codigo, nombre 
            FROM conf__cuentas_contables 
            WHERE tabla_estado_registro_id = 1
            ORDER BY codigo";
    
    $result = mysqli_query($conexion, $sql);
    if (!$result) {
        return [];
    }
    
    $cuentas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cuentas[] = $row;
    }
    
    return $cuentas;
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
        'nombre_funcion' => 'Agregar Impuesto por Jurisdicción',
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
function ejecutarTransicionEstado($conexion, $impuesto_jurisdiccion_id, $accion_js, $empresa_idx, $pagina_id)
{
    $impuesto_jurisdiccion_id = intval($impuesto_jurisdiccion_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT impuesto_jurisdiccion_id, tabla_estado_registro_id 
                  FROM gestion__impuestos_jurisdicciones 
                  WHERE impuesto_jurisdiccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $impuesto_jurisdiccion_id);
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

    $sql_update = "UPDATE gestion__impuestos_jurisdicciones 
                   SET tabla_estado_registro_id = ? 
                   WHERE impuesto_jurisdiccion_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $impuesto_jurisdiccion_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener todos los impuestos por jurisdicción
function obtenerImpuestosJurisdicciones($conexion, $empresa_idx, $pagina_id)
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

    $sql = "SELECT ij.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   it.impuesto_tipo,
                   j.jurisdiccion_nombre, j.jurisdiccion_codigo,
                   cc.codigo as cuenta_codigo, cc.nombre as cuenta_nombre,
                   CONCAT(cc.codigo, ' - ', cc.nombre) as cuenta_contable
            FROM gestion__impuestos_jurisdicciones ij
            LEFT JOIN conf__estados_registros er ON ij.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__impuestos_tipos it ON ij.impuesto_tipo_id = it.impuesto_tipo_id
            LEFT JOIN gestion__jurisdicciones j ON ij.jurisdiccion_id = j.jurisdiccion_id
            LEFT JOIN conf__cuentas_contables cc ON ij.cuenta_contable_id = cc.cuenta_contable_id
            ORDER BY ij.orden, it.impuesto_tipo, j.jurisdiccion_nombre";

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

// Agregar nuevo impuesto por jurisdicción
function agregarImpuestoJurisdiccion($conexion, $data)
{
    $impuesto_tipo_id = intval($data['impuesto_tipo_id'] ?? 0);
    $jurisdiccion_id = intval($data['jurisdiccion_id'] ?? 0);
    $tipo_calculo = mysqli_real_escape_string($conexion, trim($data['tipo_calculo'] ?? ''));
    $codigo_local = mysqli_real_escape_string($conexion, trim($data['codigo_local'] ?? ''));
    $cuenta_contable_id = !empty($data['cuenta_contable_id']) ? intval($data['cuenta_contable_id']) : null;
    $requiere_padron = intval($data['requiere_padron'] ?? 0);
    $orden = intval($data['orden'] ?? 1);

    if ($impuesto_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de impuesto'];
    }

    if ($jurisdiccion_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una jurisdicción'];
    }

    if (empty($tipo_calculo) || !in_array($tipo_calculo, ['manual', 'padron', 'regla'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de cálculo válido'];
    }

    if (strlen($codigo_local) > 20) {
        return ['resultado' => false, 'error' => 'El código local no puede exceder los 20 caracteres'];
    }

    // Verificar unicidad de la combinación impuesto + jurisdicción
    $sql_check = "SELECT impuesto_jurisdiccion_id FROM gestion__impuestos_jurisdicciones 
                  WHERE impuesto_tipo_id = ? AND jurisdiccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $impuesto_tipo_id, $jurisdiccion_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'Ya existe una configuración para este impuesto y jurisdicción'];
        }
        mysqli_stmt_close($stmt);
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    $sql = "INSERT INTO gestion__impuestos_jurisdicciones 
            (impuesto_tipo_id, jurisdiccion_id, tipo_calculo, codigo_local, 
             cuenta_contable_id, requiere_padron, orden, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iissiiii", $impuesto_tipo_id, $jurisdiccion_id, 
                          $tipo_calculo, $codigo_local, $cuenta_contable_id, 
                          $requiere_padron, $orden, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $impuesto_jurisdiccion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'impuesto_jurisdiccion_id' => $impuesto_jurisdiccion_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el registro: ' . mysqli_error($conexion)];
    }
}

// Editar impuesto por jurisdicción existente
function editarImpuestoJurisdiccion($conexion, $id, $data)
{
    $id = intval($id);
    $impuesto_tipo_id = intval($data['impuesto_tipo_id'] ?? 0);
    $jurisdiccion_id = intval($data['jurisdiccion_id'] ?? 0);
    $tipo_calculo = mysqli_real_escape_string($conexion, trim($data['tipo_calculo'] ?? ''));
    $codigo_local = mysqli_real_escape_string($conexion, trim($data['codigo_local'] ?? ''));
    $cuenta_contable_id = !empty($data['cuenta_contable_id']) ? intval($data['cuenta_contable_id']) : null;
    $requiere_padron = intval($data['requiere_padron'] ?? 0);
    $orden = intval($data['orden'] ?? 1);

    if ($impuesto_tipo_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de impuesto'];
    }

    if ($jurisdiccion_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar una jurisdicción'];
    }

    if (empty($tipo_calculo) || !in_array($tipo_calculo, ['manual', 'padron', 'regla'])) {
        return ['resultado' => false, 'error' => 'Debe seleccionar un tipo de cálculo válido'];
    }

    if (strlen($codigo_local) > 20) {
        return ['resultado' => false, 'error' => 'El código local no puede exceder los 20 caracteres'];
    }

    // Verificar unicidad (excluyendo el registro actual)
    $sql_check = "SELECT impuesto_jurisdiccion_id FROM gestion__impuestos_jurisdicciones 
                  WHERE impuesto_tipo_id = ? AND jurisdiccion_id = ? AND impuesto_jurisdiccion_id != ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iii", $impuesto_tipo_id, $jurisdiccion_id, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return ['resultado' => false, 'error' => 'Ya existe una configuración para este impuesto y jurisdicción'];
        }
        mysqli_stmt_close($stmt);
    }

    $sql = "UPDATE gestion__impuestos_jurisdicciones 
            SET impuesto_tipo_id = ?, jurisdiccion_id = ?, tipo_calculo = ?, codigo_local = ?, 
                cuenta_contable_id = ?, requiere_padron = ?, orden = ?
            WHERE impuesto_jurisdiccion_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "iissiiii", $impuesto_tipo_id, $jurisdiccion_id, 
                          $tipo_calculo, $codigo_local, $cuenta_contable_id, 
                          $requiere_padron, $orden, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el registro: ' . mysqli_error($conexion)];
    }
}

// Obtener impuesto por jurisdicción específico
function obtenerImpuestoJurisdiccionPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT * FROM gestion__impuestos_jurisdicciones WHERE impuesto_jurisdiccion_id = ?";
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