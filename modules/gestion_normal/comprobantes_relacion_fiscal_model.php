<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

/**
 * Modelo para gestión de comprobantes relación fiscal
 * Matriz de cruce emisor/receptor vs comprobante fiscal y discriminación de IVA
 * Configuración de estados y botones dinámica desde conf__paginas_funciones
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
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

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
    if (!$stmt) return null;

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
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
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
                'destino_id' => $funcion['tabla_estado_registro_destino_id'],
                'es_confirmable' => in_array(strtolower($funcion['accion_js']), ['inactivar', 'eliminar', 'habilitar', 'bloquear']) ? 1 : 0
            ];
        }
    }

    return $botones;
}

// ✅ Obtener botón "Agregar" (origen_id = 0)
function obtenerBotonAgregar($conexion, $pagina_id)
{
    $funciones = permisos_filtrar_funciones($conexion, obtenerFuncionesPagina($conexion, $pagina_id));
    if (permisos_sin_funcion_creacion($funciones)) {
        return null;
    }
    foreach ($funciones as $funcion) {
        if ($funcion['tabla_estado_registro_origen_id'] == 0 && strtolower($funcion['accion_js']) === 'agregar') {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'],
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }
    return null;
}

// ✅ Ejecutar transición de estado dinámicamente
function ejecutarTransicionEstado($conexion, $relacion_fiscal_id, $accion_js, $pagina_id)
{
    $relacion_fiscal_id = intval($relacion_fiscal_id);
    $pagina_id = intval($pagina_id);

    $sql = "SELECT tabla_estado_registro_id FROM gestion__comprobantes_relacion_fiscal WHERE relacion_fiscal_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['success' => false, 'error' => 'Error al preparar la consulta'];

    mysqli_stmt_bind_param($stmt, "i", $relacion_fiscal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$fila) return ['success' => false, 'error' => 'Registro no encontrado'];

    $estado_actual = $fila['tabla_estado_registro_id'];

    $sql_funcion = "SELECT tabla_estado_registro_destino_id, nombre_funcion 
                    FROM conf__paginas_funciones 
                    WHERE pagina_id = ? 
                    AND tabla_estado_registro_origen_id = ? 
                    AND accion_js = ? 
                    AND tabla_estado_registro_id = 1 
                    LIMIT 1";

    $stmt_func = mysqli_prepare($conexion, $sql_funcion);
    if (!$stmt_func) return ['success' => false, 'error' => 'Error al buscar la transición'];

    mysqli_stmt_bind_param($stmt_func, "iis", $pagina_id, $estado_actual, $accion_js);
    mysqli_stmt_execute($stmt_func);
    $result_func = mysqli_stmt_get_result($stmt_func);
    $transicion = mysqli_fetch_assoc($result_func);
    mysqli_stmt_close($stmt_func);

    if (!$transicion) {
        return ['success' => false, 'error' => 'Acción no permitida para el estado actual'];
    }

    $nuevo_estado = $transicion['tabla_estado_registro_destino_id'];

    $sql_update = "UPDATE gestion__comprobantes_relacion_fiscal 
                   SET tabla_estado_registro_id = ? 
                   WHERE relacion_fiscal_id = ?";
    $stmt_up = mysqli_prepare($conexion, $sql_update);
    if (!$stmt_up) return ['success' => false, 'error' => 'Error al preparar la actualización'];

    mysqli_stmt_bind_param($stmt_up, "ii", $nuevo_estado, $relacion_fiscal_id);
    $success = mysqli_stmt_execute($stmt_up);
    mysqli_stmt_close($stmt_up);

    if ($success) {
        $info_estado = obtenerInfoEstado($conexion, $nuevo_estado);
        return [
            'success' => true,
            'message' => "Estado actualizado a '{$info_estado['estado_registro']}' correctamente",
            'nuevo_estado' => $nuevo_estado,
            'estado_info' => $info_estado
        ];
    }

    return ['success' => false, 'error' => 'Error al actualizar el estado'];
}

// ✅ Obtener catálogo de condiciones fiscales
function obtenerCondicionesFiscales($conexion)
{
    $sql = "SELECT condicion_fiscal_id, condicion_fiscal, condicion_fiscal_codigo, condicion_fiscal_descripcion 
            FROM gestion__condiciones_fiscales 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY condicion_fiscal_id";
    $result = mysqli_query($conexion, $sql);
    $catalogo = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $catalogo[] = $row;
        }
    }
    return $catalogo;
}

// ✅ Obtener catálogo de comprobantes fiscales
function obtenerComprobantesFiscales($conexion)
{
    $sql = "SELECT comprobante_fiscal_id, comprobante_fiscal, codigo, letra 
            FROM gestion__comprobantes_fiscales 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY codigo";
    $result = mysqli_query($conexion, $sql);
    $catalogo = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $catalogo[] = $row;
        }
    }
    return $catalogo;
}

// ✅ Obtener todos los registros de relación fiscal
function obtenerComprobantesRelacionFiscal($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);

    $sql = "SELECT rf.relacion_fiscal_id,
                   rf.condicion_fiscal_emisor_id,
                   ce.condicion_fiscal AS emisor_nombre,
                   ce.condicion_fiscal_codigo AS emisor_codigo,
                   rf.condicion_fiscal_receptor_id,
                   cr.condicion_fiscal AS receptor_nombre,
                   cr.condicion_fiscal_codigo AS receptor_codigo,
                   rf.comprobante_fiscal_id,
                   cf.comprobante_fiscal,
                   cf.codigo AS comprobante_codigo,
                   cf.letra,
                   rf.discrimina_iva,
                   rf.tabla_estado_registro_id
            FROM gestion__comprobantes_relacion_fiscal rf
            LEFT JOIN gestion__condiciones_fiscales ce ON rf.condicion_fiscal_emisor_id = ce.condicion_fiscal_id
            LEFT JOIN gestion__condiciones_fiscales cr ON rf.condicion_fiscal_receptor_id = cr.condicion_fiscal_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON rf.comprobante_fiscal_id = cf.comprobante_fiscal_id
            ORDER BY ce.condicion_fiscal_id ASC, cr.condicion_fiscal_id ASC";

    $result = mysqli_query($conexion, $sql);
    if (!$result) return [];

    $registros = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $fila['estado_info'] = obtenerInfoEstado($conexion, $fila['tabla_estado_registro_id']);
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['tabla_estado_registro_id']);
        $registros[] = $fila;
    }

    return $registros;
}

// ✅ Obtener relación fiscal por ID
function obtenerComprobanteRelacionFiscalPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT rf.relacion_fiscal_id,
                   rf.condicion_fiscal_emisor_id,
                   ce.condicion_fiscal AS emisor_nombre,
                   ce.condicion_fiscal_codigo AS emisor_codigo,
                   rf.condicion_fiscal_receptor_id,
                   cr.condicion_fiscal AS receptor_nombre,
                   cr.condicion_fiscal_codigo AS receptor_codigo,
                   rf.comprobante_fiscal_id,
                   cf.comprobante_fiscal,
                   cf.codigo AS comprobante_codigo,
                   cf.letra,
                   rf.discrimina_iva,
                   rf.tabla_estado_registro_id
            FROM gestion__comprobantes_relacion_fiscal rf
            LEFT JOIN gestion__condiciones_fiscales ce ON rf.condicion_fiscal_emisor_id = ce.condicion_fiscal_id
            LEFT JOIN gestion__condiciones_fiscales cr ON rf.condicion_fiscal_receptor_id = cr.condicion_fiscal_id
            LEFT JOIN gestion__comprobantes_fiscales cf ON rf.comprobante_fiscal_id = cf.comprobante_fiscal_id
            WHERE rf.relacion_fiscal_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $registro = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $registro;
}

// ✅ Agregar nueva relación fiscal
function agregarComprobanteRelacionFiscal($conexion, $data)
{
    $emisor_id = intval($data['condicion_fiscal_emisor_id'] ?? 0);
    $receptor_id = intval($data['condicion_fiscal_receptor_id'] ?? 0);
    $comprobante_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $discrimina_iva = !empty($data['discrimina_iva']) ? 1 : 0;

    if ($emisor_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la condición fiscal del emisor'];
    }
    if ($receptor_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la condición fiscal del receptor'];
    }
    if ($comprobante_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el comprobante fiscal'];
    }

    // Verificar unicidad del par emisor/receptor
    $sql_check = "SELECT relacion_fiscal_id FROM gestion__comprobantes_relacion_fiscal 
                  WHERE condicion_fiscal_emisor_id = ? AND condicion_fiscal_receptor_id = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if (!$stmt_check) return ['resultado' => false, 'error' => 'Error al preparar la verificación'];

    mysqli_stmt_bind_param($stmt_check, "ii", $emisor_id, $receptor_id);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);
    if (mysqli_num_rows($res_check) > 0) {
        mysqli_stmt_close($stmt_check);
        return ['resultado' => false, 'error' => 'Ya existe una regla configurada para este cruce de Emisor y Receptor'];
    }
    mysqli_stmt_close($stmt_check);

    $sql = "INSERT INTO gestion__comprobantes_relacion_fiscal 
            (condicion_fiscal_emisor_id, condicion_fiscal_receptor_id, comprobante_fiscal_id, discrimina_iva, tabla_estado_registro_id) 
            VALUES (?, ?, ?, ?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error al preparar la inserción'];

    mysqli_stmt_bind_param($stmt, "iiii", $emisor_id, $receptor_id, $comprobante_id, $discrimina_iva);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        $id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'id' => $id, 'mensaje' => 'Relación fiscal agregada exitosamente'];
    } else {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al guardar la relación fiscal: ' . $error];
    }
}

// ✅ Editar relación fiscal existente
function editarComprobanteRelacionFiscal($conexion, $id, $data)
{
    $id = intval($id);
    $emisor_id = intval($data['condicion_fiscal_emisor_id'] ?? 0);
    $receptor_id = intval($data['condicion_fiscal_receptor_id'] ?? 0);
    $comprobante_id = intval($data['comprobante_fiscal_id'] ?? 0);
    $discrimina_iva = !empty($data['discrimina_iva']) ? 1 : 0;

    if ($id <= 0) {
        return ['resultado' => false, 'error' => 'ID de relación fiscal inválido'];
    }
    if ($emisor_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la condición fiscal del emisor'];
    }
    if ($receptor_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar la condición fiscal del receptor'];
    }
    if ($comprobante_id <= 0) {
        return ['resultado' => false, 'error' => 'Debe seleccionar el comprobante fiscal'];
    }

    // Verificar unicidad excluyendo el registro actual
    $sql_check = "SELECT relacion_fiscal_id FROM gestion__comprobantes_relacion_fiscal 
                  WHERE condicion_fiscal_emisor_id = ? AND condicion_fiscal_receptor_id = ? AND relacion_fiscal_id != ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    if (!$stmt_check) return ['resultado' => false, 'error' => 'Error al preparar la verificación'];

    mysqli_stmt_bind_param($stmt_check, "iii", $emisor_id, $receptor_id, $id);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);
    if (mysqli_num_rows($res_check) > 0) {
        mysqli_stmt_close($stmt_check);
        return ['resultado' => false, 'error' => 'Ya existe otra regla configurada para este cruce de Emisor y Receptor'];
    }
    mysqli_stmt_close($stmt_check);

    $sql = "UPDATE gestion__comprobantes_relacion_fiscal 
            SET condicion_fiscal_emisor_id = ?, 
                condicion_fiscal_receptor_id = ?, 
                comprobante_fiscal_id = ?, 
                discrimina_iva = ? 
            WHERE relacion_fiscal_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['resultado' => false, 'error' => 'Error al preparar la actualización'];

    mysqli_stmt_bind_param($stmt, "iiiii", $emisor_id, $receptor_id, $comprobante_id, $discrimina_iva, $id);
    $success = mysqli_stmt_execute($stmt);

    if ($success) {
        mysqli_stmt_close($stmt);
        return ['resultado' => true, 'mensaje' => 'Relación fiscal actualizada exitosamente'];
    } else {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['resultado' => false, 'error' => 'Error al actualizar la relación fiscal: ' . $error];
    }
}
