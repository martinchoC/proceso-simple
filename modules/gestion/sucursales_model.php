<?php
require_once "conexion.php";

function obtenerLocales($conexion) {
    $sql = "SELECT l.*, e.empresa as empresa_nombre, lt.sucursal_tipo as sucursal_tipo_nombre, loc.localidad as localidad_nombre
            FROM gestion__sucursales l
            INNER JOIN conf__empresas e ON l.empresa_id = e.empresa_id
            INNER JOIN gestion__sucursales_tipos lt ON l.sucursal_tipo_id = lt.sucursal_tipo_id
            LEFT JOIN conf__localidades loc ON l.localidad_id = loc.localidad_id
            ORDER BY l.sucursal_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresas($conexion) {
    $sql = "SELECT empresa_id, empresa FROM conf__empresas WHERE estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerLocalesTipos($conexion) {
    $sql = "SELECT sucursal_tipo_id, sucursal_tipo FROM gestion__sucursales_tipos WHERE estado_registro_id = 1 ORDER BY sucursal_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Nueva función para obtener localidades
function obtenerLocalidades($conexion) {
    $sql = "SELECT localidad_id, localidad FROM conf__localidades WHERE estado_registro_id = 1 ORDER BY localidad";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarLocal($conexion, $data) {
    if (empty($data['empresa_id']) || empty($data['sucursal_tipo_id']) || empty($data['sucursal_tipo'])) {
        return false;
    }
    
    $empresa_id = intval($data['empresa_id']);
    $sucursal_tipo_id = intval($data['sucursal_tipo_id']);
    $nombre = mysqli_real_escape_string($conexion, $data['nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $localidad_id = !empty($data['localidad_id']) ? intval($data['localidad_id']) : 'NULL';
    $direccion = mysqli_real_escape_string($conexion, $data['direccion']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "INSERT INTO gestion__sucursales 
            (empresa_id, sucursal_tipo_id, nombre, descripcion, localidad_id, direccion, telefono, email, estado_registro_id, usuario_creacion_id) 
            VALUES 
            ($empresa_id, $sucursal_tipo_id, '$nombre', '$descripcion', $localidad_id, '$direccion', '$telefono', '$email', $estado_registro_id, $usuario_creacion_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarLocal($conexion, $id, $data) {
    if (empty($data['empresa_id']) || empty($data['sucursal_tipo_id']) || empty($data['nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $empresa_id = intval($data['empresa_id']);
    $sucursal_tipo_id = intval($data['sucursal_tipo_id']);
    $sucursal_nombre = mysqli_real_escape_string($conexion, $data['sucursal_nombre']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $localidad_id = !empty($data['localidad_id']) ? intval($data['localidad_id']) : 'NULL';
    $direccion = mysqli_real_escape_string($conexion, $data['direccion']);
    $telefono = mysqli_real_escape_string($conexion, $data['telefono']);
    $email = mysqli_real_escape_string($conexion, $data['email']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $usuario_creacion_id = !empty($data['usuario_creacion_id']) ? intval($data['usuario_creacion_id']) : 'NULL';

    $sql = "UPDATE gestion__sucursales SET
            empresa_id = $empresa_id,
            sucursal_tipo_id = $sucursal_tipo_id,
            sucursal_nombre = '$sucursal_nombre',
            descripcion = '$descripcion',
            localidad_id = $localidad_id,
            direccion = '$direccion',
            telefono = '$telefono',
            email = '$email',
            estado_registro_id = $estado_registro_id,
            usuario_creacion_id = $usuario_creacion_id
            WHERE sucursal_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoLocal($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__sucursales SET estado_registro_id = $nuevo_estado WHERE sucursal_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarLocal($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM gestion__sucursales WHERE sucursal_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerLocalPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM gestion__sucursales WHERE sucursal_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}