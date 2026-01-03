<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerEmpresasPerfiles($conexion) {
    $sql = "SELECT ep.*, 
                   e.empresa, 
                   m.modulo,
                   p.perfil_nombre as perfil_base
            FROM conf__empresas_perfiles ep
            LEFT JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            LEFT JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            ORDER BY e.empresa, m.modulo, ep.empresa_perfil_nombre";
    
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

function obtenerPerfilesBase($conexion) {
    $sql = "SELECT p.perfil_id, p.perfil_nombre, m.modulo, p.modulo_id
            FROM conf__perfiles p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE p.tabla_estado_registro_id = 1
            ORDER BY m.modulo, p.perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPerfilesBasePorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    $sql = "SELECT perfil_id, perfil_nombre 
            FROM conf__perfiles 
            WHERE modulo_id = $modulo_id 
            AND tabla_estado_registro_id = 1
            ORDER BY perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarEmpresaPerfil($conexion, $data) {
    if (empty($data['empresa_perfil_nombre']) || empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }
    
    $empresa_perfil_nombre = mysqli_real_escape_string($conexion, $data['empresa_perfil_nombre']);
    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $perfil_id_base = $data['perfil_id_base'] ? intval($data['perfil_id_base']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__empresas_perfiles 
            (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id) 
            VALUES ($empresa_id, $modulo_id, $perfil_id_base, '$empresa_perfil_nombre', $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarEmpresaPerfil($conexion, $id, $data) {
    if (empty($data['empresa_perfil_nombre']) || empty($data['empresa_id']) || empty($data['modulo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $empresa_perfil_nombre = mysqli_real_escape_string($conexion, $data['empresa_perfil_nombre']);
    $empresa_id = intval($data['empresa_id']);
    $modulo_id = intval($data['modulo_id']);
    $perfil_id_base = $data['perfil_id_base'] ? intval($data['perfil_id_base']) : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__empresas_perfiles SET
            empresa_id = $empresa_id,
            modulo_id = $modulo_id,
            perfil_id_base = $perfil_id_base,
            empresa_perfil_nombre = '$empresa_perfil_nombre',
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE empresa_perfil_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoEmpresaPerfil($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = $nuevo_estado WHERE empresa_perfil_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEmpresaPerfilPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__empresas_perfiles WHERE empresa_perfil_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}