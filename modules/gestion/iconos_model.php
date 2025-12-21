<?php
require_once "conexion.php";

function obtenerIconos($conexion) {
    $sql = "SELECT * FROM conf__iconos ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarIcono($conexion, $data) {
    if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
        return false;
    }
    
    $icono_nombre = mysqli_real_escape_string($conexion, $data['icono_nombre']);
    $icono_clase = mysqli_real_escape_string($conexion, $data['icono_clase']);
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "INSERT INTO conf__iconos (icono_nombre, icono_clase, estado_registro_id) 
            VALUES ('$icono_nombre', '$icono_clase', $estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarIcono($conexion, $id, $data) {
    if (empty($data['icono_nombre']) || empty($data['icono_clase'])) {
        return false;
    }
    
    $id = intval($id);
    $icono_nombre = mysqli_real_escape_string($conexion, $data['icono_nombre']);
    $icono_clase = mysqli_real_escape_string($conexion, $data['icono_clase']);
    $estado_registro_id = intval($data['estado_registro_id']);

    $sql = "UPDATE conf__iconos SET
            icono_nombre = '$icono_nombre',
            icono_clase = '$icono_clase',
            estado_registro_id = $estado_registro_id
            WHERE icono_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoIcono($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__iconos SET estado_registro_id = $nuevo_estado WHERE icono_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerIconoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__iconos WHERE icono_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}