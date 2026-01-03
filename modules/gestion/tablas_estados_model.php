<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerTablasEstadosRegistros($conexion) {
    $sql = "SELECT ter.*, t.tabla_nombre, c.nombre_color, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__tablas_estados_registros ter
            LEFT JOIN conf__tablas t ON ter.tabla_id = t.tabla_id
            LEFT JOIN conf__colores c ON ter.color_id = c.color_id
            WHERE t.modulo_id=2
            ORDER BY t.tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablas($conexion) {
    $sql = "SELECT tabla_id, tabla_nombre FROM conf__tablas ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerColores($conexion) {
    $sql = "SELECT color_id, nombre_color, color_clase, bg_clase, text_clase, descripcion
            FROM conf__colores 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY nombre_color";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTablaEstadoRegistro($conexion, $data) {
    if (empty($data['tabla_id']) || empty($data['estado_registro'])) {
        return false;
    }
    
    $tabla_id = intval($data['tabla_id']);
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $codigo_estandar = mysqli_real_escape_string($conexion, $data['codigo_estandar']);
    $valor_estandar = intval($data['valor_estandar']);
    $color_id = intval($data['color_id']);
    $orden = intval($data['orden']);

    // Verificar si ya existe el estado para esta tabla
    $sql_check = "SELECT COUNT(*) as count FROM conf__tablas_estados_registros 
                  WHERE tabla_id = $tabla_id AND estado_registro = '$estado_registro'";
    $res_check = mysqli_query($conexion, $sql_check);
    $row = mysqli_fetch_assoc($res_check);
    
    if ($row['count'] > 0) {
        return false; // Ya existe este estado para esta tabla
    }

    $sql = "INSERT INTO conf__tablas_estados_registros 
            (tabla_id, estado_registro, codigo_estandar, valor_estandar, color_id, orden) 
            VALUES ($tabla_id, '$estado_registro', '$codigo_estandar', $valor_estandar, $color_id, $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaEstadoRegistro($conexion, $id, $data) {
    if (empty($data['tabla_id']) || empty($data['estado_registro'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_id = intval($data['tabla_id']);
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $codigo_estandar = mysqli_real_escape_string($conexion, $data['codigo_estandar']);
    $valor_estandar = intval($data['valor_estandar']);
    $color_id = intval($data['color_id']);
    $orden = intval($data['orden']);

    // Verificar si ya existe el estado para esta tabla en otro registro
    $sql_check = "SELECT COUNT(*) as count FROM conf__tablas_estados_registros 
                  WHERE tabla_id = $tabla_id AND estado_registro = '$estado_registro'
                  AND tabla_estado_registro_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $row = mysqli_fetch_assoc($res_check);
    
    if ($row['count'] > 0) {
        return false; // Ya existe este estado para esta tabla en otro registro
    }

    $sql = "UPDATE conf__tablas_estados_registros SET
            tabla_id = $tabla_id,
            estado_registro = '$estado_registro',
            codigo_estandar = '$codigo_estandar',
            valor_estandar = $valor_estandar,
            color_id = $color_id,
            orden = $orden
            WHERE tabla_estado_registro_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarTablaEstadoRegistro($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerTablaEstadoRegistroPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}