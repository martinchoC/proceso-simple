<?php
require_once "conexion.php";

function obtenerTablasEstados($conexion) {
    $sql = "SELECT conf__tablas_estados.*, conf__tablas.tabla_nombre, conf__estados_registros.estado_registro 
        FROM conf__tablas_estados
        LEFT JOIN conf__tablas ON conf__tablas_estados.tabla_id = conf__tablas.tabla_id
        LEFT JOIN conf__estados_registros ON conf__tablas_estados.estado_registro_id = conf__estados_registros.estado_registro_id
        ORDER BY conf__tablas.tabla_nombre, conf__tablas_estados.orden";
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

function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT conf__tablas_tipos.tabla_tipo, conf__estados_registros.estado_registro, conf__estados_registros.estado_registro_id
FROM conf__estados_registros
LEFT JOIN conf__tablas_tipos ON conf__estados_registros.tabla_tipo_id = conf__tablas_tipos.tabla_tipo_id
ORDER BY conf__tablas_tipos.tabla_tipo, conf__estados_registros.orden";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTablaEstado($conexion, $data) {
    if (empty($data['tabla_id']) || empty($data['estado_registro_id'])) {
        return false;
    }
    
    $tabla_id = intval($data['tabla_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $orden = intval($data['orden']);

    // Verificar si ya existe la combinación tabla_id + estado_registro_id
    $sql_check = "SELECT COUNT(*) as count FROM conf__tablas_estados 
                  WHERE tabla_id = $tabla_id AND estado_registro_id = $estado_registro_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $row = mysqli_fetch_assoc($res_check);
    
    if ($row['count'] > 0) {
        return false; // Ya existe esta combinación
    }

    $sql = "INSERT INTO conf__tablas_estados (tabla_id, estado_registro_id, orden) 
            VALUES ($tabla_id, $estado_registro_id, $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaEstado($conexion, $id, $data) {
    if (empty($data['tabla_id']) || empty($data['estado_registro_id'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_id = intval($data['tabla_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $orden = intval($data['orden']);

    // Verificar si ya existe la combinación tabla_id + estado_registro_id en otro registro
    $sql_check = "SELECT COUNT(*) as count FROM conf__tablas_estados 
                  WHERE tabla_id = $tabla_id AND estado_registro_id = $estado_registro_id
                  AND tabla_estado_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $row = mysqli_fetch_assoc($res_check);
    
    if ($row['count'] > 0) {
        return false; // Ya existe esta combinación en otro registro
    }

    $sql = "UPDATE conf__tablas_estados SET
            tabla_id = $tabla_id,
            estado_registro_id = $estado_registro_id,
            orden = $orden
            WHERE tabla_estado_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarTablaEstado($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM conf__tablas_estados WHERE tabla_estado_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerTablaEstadoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_estados WHERE tabla_estado_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}