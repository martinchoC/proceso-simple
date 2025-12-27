<?php
require_once "conexion.php";

function obtenerEmpresasModulos($conexion) {
    $sql = "SELECT em.*, e.empresa, m.modulo 
            FROM conf__empresas_modulos em
            LEFT JOIN conf__empresas e ON em.empresa_id = e.empresa_id
            LEFT JOIN conf__modulos m ON em.modulo_id = m.modulo_id
            ORDER BY e.empresa, m.modulo";
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

function existeEmpresaModulo($conexion, $empresa_id, $modulo_id) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    
    $sql = "SELECT empresa_modulo_id FROM conf__empresas_modulos 
            WHERE empresa_id = $empresa_id AND modulo_id = $modulo_id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function existeEmpresaModuloEditando($conexion, $empresa_id, $modulo_id, $excluir_id) {
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    $excluir_id = intval($excluir_id);
    
    $sql = "SELECT empresa_modulo_id FROM conf__empresas_modulos 
            WHERE empresa_id = $empresa_id 
            AND modulo_id = $modulo_id
            AND empresa_modulo_id != $excluir_id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function agregarEmpresaModulo($conexion, $data) {
    if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }
    
    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, tabla_estado_registro_id) 
            VALUES ($empresa_id, $modulo_id, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarEmpresaModulo($conexion, $id, $data) {
    if (empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__empresas_modulos SET
            empresa_id = $empresa_id,
            modulo_id = $modulo_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE empresa_modulo_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoEmpresaModulo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__empresas_modulos SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_modulo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEmpresaModuloPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__empresas_modulos WHERE empresa_modulo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}