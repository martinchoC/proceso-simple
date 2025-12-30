<?php
require_once "conexion.php";

function obtenerSubmodelos($conexion) {
    $sql = "SELECT s.*, m.modelo_nombre, ma.marca_nombre, ma.marca_id
            FROM gestion__submodelos s 
            INNER JOIN gestion__modelos m ON s.modelo_id = m.modelo_id 
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id 
            ORDER BY s.submodelo_id DESC";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerMarcas($conexion) {
    $sql = "SELECT * FROM gestion__marcas WHERE tabla_estado_registro_id = 1 ORDER BY marca_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModelosPorMarca($conexion, $marca_id) {
    $marca_id = intval($marca_id);
    $sql = "SELECT * FROM gestion__modelos WHERE marca_id = $marca_id AND tabla_estado_registro_id = 1 ORDER BY modelo_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarSubmodelo($conexion, $data) {
    $modelo_id = intval($data['modelo_id']);
    $submodelo_nombre = mysqli_real_escape_string($conexion, $data['submodelo_nombre']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO gestion__submodelos (modelo_id, submodelo_nombre, tabla_estado_registro_id) 
            VALUES ($modelo_id, '$submodelo_nombre', $tabla_estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarSubmodelo($conexion, $id, $data) {
    $id = intval($id);
    $modelo_id = intval($data['modelo_id']);
    $submodelo_nombre = mysqli_real_escape_string($conexion, $data['submodelo_nombre']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE gestion__submodelos SET
            modelo_id=$modelo_id,
            submodelo_nombre='$submodelo_nombre',
            tabla_estado_registro_id=$tabla_estado_registro_id
            WHERE submodelo_id=$id";

    return mysqli_query($conexion, $sql);
}

function eliminarSubmodelo($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM gestion__submodelos WHERE submodelo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerSubmodeloPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__submodelos WHERE submodelo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
