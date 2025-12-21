<?php
require_once "conexion.php";

function obtenerTablasTipos($conexion) {
    $sql = "SELECT tt.*, COUNT(tte.tabla_tipo_estado_id) as cantidad_estados
            FROM conf__tablas_tipos tt
            LEFT JOIN conf__tablas_tipos_estados tte ON tt.tabla_tipo_id = tte.tabla_tipo_id AND tte.tabla_estado_registro_id = 1
            GROUP BY tt.tabla_tipo_id
            ORDER BY tt.tabla_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablaTipoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_tipo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function agregarTablaTipo($conexion, $data) {
    if (empty($data['tabla_tipo'])) {
        return false;
    }
    
    $tabla_tipo = mysqli_real_escape_string($conexion, $data['tabla_tipo']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__tablas_tipos (tabla_tipo, tabla_estado_registro_id) 
            VALUES ('$tabla_tipo', $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaTipo($conexion, $id, $data) {
    if (empty($data['tabla_tipo'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_tipo = mysqli_real_escape_string($conexion, $data['tabla_tipo']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__tablas_tipos SET
            tabla_tipo = '$tabla_tipo',
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE tabla_tipo_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTablaTipo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas_tipos SET tabla_estado_registro_id = $nuevo_estado WHERE tabla_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

// Funciones para los estados de tipos de tablas
function obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    $sql = "SELECT * FROM conf__tablas_tipos_estados 
            WHERE tabla_tipo_id = $tabla_tipo_id 
            ORDER BY valor, tabla_tipo_estado";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablaTipoEstadoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_tipos_estados WHERE tabla_tipo_estado_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function agregarTablaTipoEstado($conexion, $data) {
    if (empty($data['tabla_tipo_estado']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_tipo_estado = mysqli_real_escape_string($conexion, $data['tabla_tipo_estado']);
    $tabla_tipo_estado_descripcion = mysqli_real_escape_string($conexion, $data['tabla_tipo_estado_descripcion'] ?? '');
    $valor = intval($data['valor'] ?? 0);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__tablas_tipos_estados 
            (tabla_tipo_id, tabla_tipo_estado, tabla_tipo_estado_descripcion, valor, tabla_estado_registro_id) 
            VALUES 
            ($tabla_tipo_id, '$tabla_tipo_estado', '$tabla_tipo_estado_descripcion', $valor, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaTipoEstado($conexion, $id, $data) {
    if (empty($data['tabla_tipo_estado'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_tipo_estado = mysqli_real_escape_string($conexion, $data['tabla_tipo_estado']);
    $tabla_tipo_estado_descripcion = mysqli_real_escape_string($conexion, $data['tabla_tipo_estado_descripcion'] ?? '');
    $valor = intval($data['valor'] ?? 0);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__tablas_tipos_estados SET
            tabla_tipo_estado = '$tabla_tipo_estado',
            tabla_tipo_estado_descripcion = '$tabla_tipo_estado_descripcion',
            valor = $valor,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE tabla_tipo_estado_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas_tipos_estados SET tabla_estado_registro_id = $nuevo_estado WHERE tabla_tipo_estado_id = $id";
    return mysqli_query($conexion, $sql);
}
?>