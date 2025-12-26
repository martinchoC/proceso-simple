<?php
require_once "conexion.php";

function obtenerPerfiles($conexion) {
    $sql = "SELECT conf__perfiles.*, conf__modulos.modulo, conf__empresas.empresa
    FROM conf__perfiles 
    LEFT JOIN conf__modulos ON conf__perfiles.modulo_id = conf__modulos.modulo_id
    LEFT JOIN conf__empresas ON conf__perfiles.empresa_id = conf__empresas.empresa_id
    ORDER BY perfil_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresas($conexion) {
    $sql = "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulos($conexion) {
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPerfil($conexion, $data) {
    if (empty($data['perfil_nombre'])) {
        return false;
    }
    
    $perfil_nombre = mysqli_real_escape_string($conexion, $data['perfil_nombre']);
    $empresa_id = $data['empresa_id'] ? intval($data['empresa_id']) : 'NULL';
    $modulo_id = $data['modulo_id'] ? intval($data['modulo_id']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__perfiles (empresa_id, modulo_id, perfil_nombre, tabla_estado_registro_id) 
            VALUES ($empresa_id, $modulo_id, '$perfil_nombre', $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarPerfil($conexion, $id, $data) {
    if (empty($data['perfil_nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $perfil_nombre = mysqli_real_escape_string($conexion, $data['perfil_nombre']);
    $empresa_id = $data['empresa_id'] ? intval($data['empresa_id']) : 'NULL';
    $modulo_id = $data['modulo_id'] ? intval($data['modulo_id']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__perfiles SET
            empresa_id = $empresa_id,
            modulo_id = $modulo_id,
            perfil_nombre = '$perfil_nombre',
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE perfil_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoPerfil($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__perfiles SET tabla_estado_registro_id = $nuevo_estado WHERE perfil_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerPerfilPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__perfiles WHERE perfil_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}