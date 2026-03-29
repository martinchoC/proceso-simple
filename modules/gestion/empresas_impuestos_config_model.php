<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * Modelo para gestión de Tipos de Impuestos
 * Toda la configuración se obtiene de conf__paginas_funciones
 */

// Obtener cuentas contables de la empresa
function obtenerCuentasContables($conexion, $empresa_idx) {
    $empresa_idx = intval($empresa_idx);
    
    $sql = "SELECT cont_cuenta_id, codigo, nombre 
            FROM gestion__cont_cuentas 
            WHERE empresa_id = ? 
            AND tabla_estado_registro_id = 1
            ORDER BY codigo";
    
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $cuentas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cuentas[] = $row;
    }
    
    mysqli_stmt_close($stmt);
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
        'nombre_funcion' => 'Agregar Tipo de Impuesto',
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
function ejecutarTransicionEstado($conexion, $impuesto_tipo_id, $accion_js, $empresa_idx, $pagina_id)
{
    $impuesto_tipo_id = intval($impuesto_tipo_id);
    $pagina_id = intval($pagina_id);

    $sql_check = "SELECT impuesto_tipo_id, tabla_estado_registro_id 
                  FROM gestion__impuestos_tipos 
                  WHERE impuesto_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql_check);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $impuesto_tipo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$tipo)
        return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual_id = $tipo['tabla_estado_registro_id'];

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

    $sql_update = "UPDATE gestion__impuestos_tipos 
                   SET tabla_estado_registro_id = ? 
                   WHERE impuesto_tipo_id = ?";

    $stmt = mysqli_prepare($conexion, $sql_update);
    if (!$stmt)
        return ['success' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $impuesto_tipo_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['success' => true, 'message' => 'Estado actualizado correctamente'];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar el estado'];
    }
}

// Obtener todos los tipos de impuesto
function obtenerTiposImpuesto($conexion, $empresa_idx, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $empresa_idx = intval($empresa_idx);

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

    $sql = "SELECT it.*, 
                   er.$estado_column as estado_registro, 
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase,
                   cc.codigo as cuenta_codigo,
                   cc.nombre as cuenta_nombre
            FROM gestion__impuestos_tipos it
            LEFT JOIN conf__estados_registros er ON it.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            LEFT JOIN gestion__cont_cuentas cc ON it.cuenta_contable_id = cc.cont_cuenta_id AND cc.empresa_id = ?
            ORDER BY it.impuesto_tipo";

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
        
        $fila['cuenta_contable'] = $fila['cuenta_codigo'] ? $fila['cuenta_codigo'] . ' - ' . $fila['cuenta_nombre'] : '';

        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $data[] = $fila;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

// Agregar nuevo tipo de impuesto
function agregarTipoImpuesto($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $impuesto_tipo = mysqli_real_escape_string($conexion, trim($data['impuesto_tipo'] ?? ''));
    $codigo_afip = mysqli_real_escape_string($conexion, trim($data['codigo_afip'] ?? ''));
    $cuenta_contable_id = !empty($data['cuenta_contable_id']) ? intval($data['cuenta_contable_id']) : null;
    $aplica_compra = intval($data['aplica_compra'] ?? 1);
    $aplica_venta = intval($data['aplica_venta'] ?? 0);
    $es_retencion = intval($data['es_retencion'] ?? 0);
    $es_percepcion = intval($data['es_percepcion'] ?? 0);

    if (empty($impuesto_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de impuesto es obligatorio'];
    }

    if (strlen($impuesto_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El tipo de impuesto no puede exceder los 100 caracteres'];
    }

    if (!empty($codigo_afip) && strlen($codigo_afip) > 20) {
        return ['resultado' => false, 'error' => 'El código AFIP no puede exceder los 20 caracteres'];
    }

    $estado_inicial = obtenerEstadoInicial($conexion);

    $sql = "INSERT INTO gestion__impuestos_tipos 
            (impuesto_tipo, codigo_afip, aplica_compra, aplica_venta, es_retencion, es_percepcion, cuenta_contable_id, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiiiiii", $impuesto_tipo, $codigo_afip, $aplica_compra, $aplica_venta, 
                          $es_retencion, $es_percepcion, $cuenta_contable_id, $estado_inicial);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $impuesto_tipo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'impuesto_tipo_id' => $impuesto_tipo_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al crear el tipo de impuesto'];
    }
}

// Editar tipo de impuesto existente
function editarTipoImpuesto($conexion, $id, $data)
{
    $id = intval($id);
    $impuesto_tipo = mysqli_real_escape_string($conexion, trim($data['impuesto_tipo'] ?? ''));
    $codigo_afip = mysqli_real_escape_string($conexion, trim($data['codigo_afip'] ?? ''));
    $cuenta_contable_id = !empty($data['cuenta_contable_id']) ? intval($data['cuenta_contable_id']) : null;
    $aplica_compra = intval($data['aplica_compra'] ?? 1);
    $aplica_venta = intval($data['aplica_venta'] ?? 0);
    $es_retencion = intval($data['es_retencion'] ?? 0);
    $es_percepcion = intval($data['es_percepcion'] ?? 0);

    if (empty($impuesto_tipo)) {
        return ['resultado' => false, 'error' => 'El tipo de impuesto es obligatorio'];
    }

    if (strlen($impuesto_tipo) > 100) {
        return ['resultado' => false, 'error' => 'El tipo de impuesto no puede exceder los 100 caracteres'];
    }

    if (!empty($codigo_afip) && strlen($codigo_afip) > 20) {
        return ['resultado' => false, 'error' => 'El código AFIP no puede exceder los 20 caracteres'];
    }

    $sql_check = "SELECT impuesto_tipo_id FROM gestion__impuestos_tipos 
                  WHERE impuesto_tipo_id = ?";
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

    $sql = "UPDATE gestion__impuestos_tipos 
            SET impuesto_tipo = ?, codigo_afip = ?, aplica_compra = ?, aplica_venta = ?,
                es_retencion = ?, es_percepcion = ?, cuenta_contable_id = ?
            WHERE impuesto_tipo_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return ['resultado' => false, 'error' => 'Error en la consulta'];

    mysqli_stmt_bind_param($stmt, "ssiiiiii", $impuesto_tipo, $codigo_afip, $aplica_compra, $aplica_venta, 
                          $es_retencion, $es_percepcion, $cuenta_contable_id, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        return ['resultado' => true];
    } else {
        return ['resultado' => false, 'error' => 'Error al actualizar el tipo de impuesto'];
    }
}

// Obtener tipo de impuesto específico
function obtenerTipoImpuestoPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT * FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt)
        return null;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tipo = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    return $tipo;
}
?>