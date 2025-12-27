<?php
require_once "../../conexion.php";

function obtenerModulos($conexion) {
    $sql = "SELECT * FROM conf__modulos ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTiposTabla($conexion) {
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablas($conexion) {
    $sql = "SELECT t.*, m.modulo, tt.tabla_tipo 
            FROM conf__tablas t
            LEFT JOIN conf__modulos m ON t.modulo_id = m.modulo_id
            LEFT JOIN conf__tablas_tipos tt ON t.tabla_tipo_id = tt.tabla_tipo_id
            ORDER BY t.tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTabla($conexion, $data) {
    if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $tabla_nombre = mysqli_real_escape_string($conexion, $data['tabla_nombre']);
    $tabla_descripcion = mysqli_real_escape_string($conexion, $data['tabla_descripcion']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__tablas 
            (tabla_nombre, tabla_descripcion, modulo_id, tabla_tipo_id, tabla_estado_registro_id) 
            VALUES 
            ('$tabla_nombre', '$tabla_descripcion', $modulo_id, $tabla_tipo_id, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTabla($conexion, $id, $data) {
    if (empty($data['tabla_nombre']) || empty($data['modulo_id']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_nombre = mysqli_real_escape_string($conexion, $data['tabla_nombre']);
    $tabla_descripcion = mysqli_real_escape_string($conexion, $data['tabla_descripcion']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__tablas SET
            tabla_nombre = '$tabla_nombre',
            tabla_descripcion = '$tabla_descripcion',
            modulo_id = $modulo_id,
            tabla_tipo_id = $tabla_tipo_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE tabla_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTabla($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas SET tabla_estado_registro_id = $nuevo_estado WHERE tabla_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerTablaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas WHERE tabla_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// NUEVAS FUNCIONES PARA GESTIÓN DE ESTADOS

function obtenerEstadosPatronPorTipoTabla($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    $sql = "SELECT te.*, er.estado_registro, er.valor_estandar, er.codigo_estandar
            FROM conf__tablas_tipos_estados te
            INNER JOIN conf__estados_registros er ON te.estado_registro_id = er.estado_registro_id
            WHERE te.tabla_tipo_id = $tabla_tipo_id 
            AND te.tabla_estado_registro_id = 1
            ORDER BY te.orden";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function verificarTablaTieneEstados($conexion, $tabla_id) {
    $tabla_id = intval($tabla_id);
    
    // Primero obtener el tipo de tabla
    $sql_tabla = "SELECT tabla_tipo_id FROM conf__tablas WHERE tabla_id = $tabla_id";
    $res_tabla = mysqli_query($conexion, $sql_tabla);
    $tabla = mysqli_fetch_assoc($res_tabla);
    
    if (!$tabla || empty($tabla['tabla_tipo_id'])) {
        return false;
    }
    
    $tabla_tipo_id = intval($tabla['tabla_tipo_id']);
    
    // Verificar si hay estados patrón para este tipo de tabla
    $sql_estados = "SELECT COUNT(*) as total 
                    FROM conf__tablas_tipos_estados 
                    WHERE tabla_tipo_id = $tabla_tipo_id 
                    AND tabla_estado_registro_id = 1";
    
    $res_estados = mysqli_query($conexion, $sql_estados);
    $estados = mysqli_fetch_assoc($res_estados);
    
    return $estados['total'] > 0;
}

function obtenerTablasSinEstadosConfigurados($conexion) {
    $sql = "SELECT t.*, m.modulo, tt.tabla_tipo 
            FROM conf__tablas t
            LEFT JOIN conf__modulos m ON t.modulo_id = m.modulo_id
            LEFT JOIN conf__tablas_tipos tt ON t.tabla_tipo_id = tt.tabla_tipo_id
            WHERE t.tabla_estado_registro_id = 1
            AND t.tabla_tipo_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM conf__tablas_tipos_estados te 
                WHERE te.tabla_tipo_id = t.tabla_tipo_id 
                AND te.tabla_estado_registro_id = 1
            )
            ORDER BY t.tabla_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
function agregarEstadosTabla($conexion, $tabla_id, $tabla_tipo_id, $agregar_todos = true) {
    $tabla_id = intval($tabla_id);
    $tabla_tipo_id = intval($tabla_tipo_id);
    
    // Primero obtener los estados patrón del tipo de tabla
    $estados_patron = obtenerEstadosPatronPorTipoTabla($conexion, $tabla_tipo_id);
    
    if (empty($estados_patron)) {
        return ['resultado' => false, 'error' => 'No hay estados patrón para este tipo de tabla'];
    }
    
    // Verificar si la tabla existe
    $sql_verificar = "SELECT tabla_nombre FROM conf__tablas WHERE tabla_id = $tabla_id";
    $res_verificar = mysqli_query($conexion, $sql_verificar);
    
    if (!$res_verificar || mysqli_num_rows($res_verificar) == 0) {
        return ['resultado' => false, 'error' => 'La tabla no existe'];
    }
    
    $tabla = mysqli_fetch_assoc($res_verificar);
    $tabla_nombre = $tabla['tabla_nombre'];
    
    // Obtener el máximo tabla_estado_registro_id actual para esta tabla
    $sql_max_id = "SELECT MAX(tabla_estado_registro_id) as max_id 
                   FROM conf__tablas_estados_registros 
                   WHERE tabla_id = $tabla_id";
    $res_max_id = mysqli_query($conexion, $sql_max_id);
    $max_id_data = mysqli_fetch_assoc($res_max_id);
    $next_id = $max_id_data['max_id'] ? $max_id_data['max_id'] + 1 : 1;
    
    // Obtener color por defecto (el primero disponible)
    $sql_color = "SELECT color_id FROM conf__colores WHERE tabla_estado_registro_id = 1 LIMIT 1";
    $res_color = mysqli_query($conexion, $sql_color);
    $color_data = mysqli_fetch_assoc($res_color);
    $color_id_default = $color_data ? $color_data['color_id'] : 1;
    
    $estados_agregados = 0;
    $errores = [];
    
    // Iniciar transacción para asegurar que todos los estados se agreguen o ninguno
    mysqli_begin_transaction($conexion);
    
    try {
        foreach ($estados_patron as $estado) {
            // Verificar si agregar todos o solo los activos
            if ($agregar_todos || $estado['tabla_estado_registro_id'] == 1) {
                
                // Verificar si ya existe este estado para esta tabla
                $sql_existe = "SELECT COUNT(*) as existe 
                               FROM conf__tablas_estados_registros 
                               WHERE tabla_id = $tabla_id 
                               AND estado_registro_id = {$estado['estado_registro_id']}";
                $res_existe = mysqli_query($conexion, $sql_existe);
                $existe_data = mysqli_fetch_assoc($res_existe);
                
                if ($existe_data['existe'] == 0) {
                    // Obtener el nombre del estado desde la tabla conf__estados_registros
                    $sql_estado_nombre = "SELECT estado_registro 
                                          FROM conf__estados_registros 
                                          WHERE estado_registro_id = {$estado['estado_registro_id']}";
                    $res_estado_nombre = mysqli_query($conexion, $sql_estado_nombre);
                    $estado_nombre_data = mysqli_fetch_assoc($res_estado_nombre);
                    $estado_nombre = $estado_nombre_data ? $estado_nombre_data['estado_registro'] : 'Estado ' . $estado['estado_registro_id'];
                    
                    // Insertar el estado en conf__tablas_estados_registros
                    $sql_insert = "INSERT INTO conf__tablas_estados_registros 
                                   (tabla_id, estado_registro_id, 
                                    tabla_estado_registro, color_id, es_inicial, orden) 
                                   VALUES 
                                   ($tabla_id, {$estado['estado_registro_id']}, 
                                    '$estado_nombre', $color_id_default, {$estado['es_inicial']}, {$estado['orden']})";
                    
                    if (mysqli_query($conexion, $sql_insert)) {
                        $estados_agregados++;
                        $next_id++;
                    } else {
                        $errores[] = "Error al agregar estado {$estado['estado_registro_id']}: " . mysqli_error($conexion);
                    }
                } else {
                    // El estado ya existe para esta tabla
                    $errores[] = "El estado {$estado['estado_registro_id']} ya existe para esta tabla";
                }
            }
        }
        
        if ($estados_agregados > 0) {
            // Todo salió bien, confirmar transacción
            mysqli_commit($conexion);
            return [
                'resultado' => true, 
                'mensaje' => "Se agregaron $estados_agregados estados a la tabla",
                'estados_agregados' => $estados_agregados
            ];
        } else {
            // No se agregó ningún estado, revertir
            mysqli_rollback($conexion);
            return [
                'resultado' => false, 
                'error' => empty($errores) ? 'No se pudo agregar ningún estado' : implode(', ', $errores)
            ];
        }
        
    } catch (Exception $e) {
        // Error en la transacción, revertir
        mysqli_rollback($conexion);
        return ['resultado' => false, 'error' => 'Error en la transacción: ' . $e->getMessage()];
    }
}

// También necesitarás esta función para obtener los estados actuales de una tabla
function obtenerEstadosTablaActual($conexion, $tabla_id) {
    $tabla_id = intval($tabla_id);
    
    $sql = "SELECT ter.*, er.estado_registro, er.valor_estandar, er.codigo_estandar, 
                   c.nombre_color, c.color_clase
            FROM conf__tablas_estados_registros ter
            INNER JOIN conf__estados_registros er ON ter.estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c ON ter.color_id = c.color_id
            WHERE ter.tabla_id = $tabla_id
            ORDER BY ter.orden, ter.tabla_estado_registro_id";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>
