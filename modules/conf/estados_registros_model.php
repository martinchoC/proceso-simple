<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerColores($conexion) {
    $sql = "SELECT * FROM conf__colores WHERE tabla_estado_registro_id = 1 ORDER BY nombre_color";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT 
                er.*,
                c.nombre_color as color_nombre,
                c.color_clase
            FROM conf__estados_registros er
            LEFT JOIN conf__colores c ON er.color_id = c.color_id
            ORDER BY er.orden_estandar, er.estado_registro";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarEstadoRegistro($conexion, $data) {
    // Validar campo obligatorio
    if (empty($data['estado_registro'])) {
        return false;
    }
    
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $codigo_estandar = mysqli_real_escape_string($conexion, $data['codigo_estandar']);
    $valor_estandar = !empty($data['valor_estandar']) ? intval($data['valor_estandar']) : 'NULL';
    $color_id = !empty($data['color_id']) ? intval($data['color_id']) : '1';
    $orden_estandar = !empty($data['orden_estandar']) ? intval($data['orden_estandar']) : 'NULL';

    $sql = "INSERT INTO conf__estados_registros 
            (estado_registro, codigo_estandar, valor_estandar, color_id, orden_estandar) 
            VALUES 
            ('$estado_registro', '$codigo_estandar', $valor_estandar, $color_id, $orden_estandar)";
    
    return mysqli_query($conexion, $sql);
}

function editarEstadoRegistro($conexion, $id, $data) {
    // Validar campo obligatorio
    if (empty($data['estado_registro'])) {
        return false;
    }
    
    $id = intval($id);
    $estado_registro = mysqli_real_escape_string($conexion, $data['estado_registro']);
    $codigo_estandar = mysqli_real_escape_string($conexion, $data['codigo_estandar']);
    $valor_estandar = !empty($data['valor_estandar']) ? intval($data['valor_estandar']) : 'NULL';
    $color_id = !empty($data['color_id']) ? intval($data['color_id']) : '1';
    $orden_estandar = !empty($data['orden_estandar']) ? intval($data['orden_estandar']) : 'NULL';

    $sql = "UPDATE conf__estados_registros SET
            estado_registro = '$estado_registro',
            codigo_estandar = '$codigo_estandar',
            valor_estandar = $valor_estandar,
            color_id = $color_id,
            orden_estandar = $orden_estandar
            WHERE estado_registro_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarEstadoRegistro($conexion, $id) {
    $id = intval($id);
    $sql = "DELETE FROM conf__estados_registros WHERE estado_registro_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEstadoRegistroPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__estados_registros WHERE estado_registro_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}