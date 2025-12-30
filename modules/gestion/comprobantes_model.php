<?php
require_once "conexion.php";

function obtenerComprobantesSucursales($conexion) {
    $sql = "SELECT cs.*, 
                   s.nombre as sucursal_nombre,
                   ct.comprobante_tipo,
                   ct.comprobante_codigo,
                   cg.comprobante_grupo,
                   ct.letra,
                   ct.signo
            FROM `gestion__comprobantes_sucursales` cs
            INNER JOIN `gestion__sucursales` s ON cs.sucursal_id = s.sucursal_id
            INNER JOIN `gestion__comprobantes_tipos` ct ON cs.comprobante_tipo_id = ct.comprobante_tipo_id
            INNER JOIN `gestion__comprobantes_grupos` cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            ORDER BY s.nombre, ct.comprobante_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerSucursales($conexion) {
    $sql = "SELECT sucursal_id, nombre FROM `gestion__sucursales` WHERE tabla_estado_registro_id = 1 ORDER BY nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerComprobantesTipos($conexion) {
    $sql = "SELECT ct.*, cg.comprobante_grupo
            FROM `gestion__comprobantes_tipos` ct
            INNER JOIN `gestion__comprobantes_grupos` cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            ORDER BY cg.comprobante_grupo, ct.comprobante_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerComprobantesGrupos($conexion) {
    $sql = "SELECT comprobante_grupo_id, comprobante_grupo FROM `gestion__comprobantes_grupos` ORDER BY comprobante_grupo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarComprobanteSucursal($conexion, $data) {
    if (empty($data['sucursal_id']) || empty($data['comprobante_tipo_id'])) {
        return false;
    }
    
    $sucursal_id = intval($data['sucursal_id']);
    $comprobante_tipo_id = intval($data['comprobante_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    // Verificar si ya existe la combinación
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_sucursales` 
                  WHERE sucursal_id = $sucursal_id AND comprobante_tipo_id = $comprobante_tipo_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe > 0) {
        return false; // Ya existe esta combinación
    }

    $sql = "INSERT INTO `gestion__comprobantes_sucursales` 
            (sucursal_id, comprobante_tipo_id, tabla_estado_registro_id) 
            VALUES 
            ($sucursal_id, $comprobante_tipo_id, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarComprobanteSucursal($conexion, $id, $data) {
    if (empty($data['sucursal_id']) || empty($data['comprobante_tipo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $sucursal_id = intval($data['sucursal_id']);
    $comprobante_tipo_id = intval($data['comprobante_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    // Verificar si ya existe la combinación (excluyendo el registro actual)
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_sucursales` 
                  WHERE sucursal_id = $sucursal_id AND comprobante_tipo_id = $comprobante_tipo_id 
                  AND comprobante_sucursal_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe > 0) {
        return false; // Ya existe esta combinación
    }

    $sql = "UPDATE `gestion__comprobantes_sucursales` SET
            sucursal_id = $sucursal_id,
            comprobante_tipo_id = $comprobante_tipo_id,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE comprobante_sucursal_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoComprobanteSucursal($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE `gestion__comprobantes_sucursales` SET tabla_estado_registro_id = $nuevo_estado WHERE comprobante_sucursal_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarComprobanteSucursal($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM `gestion__comprobantes_sucursales` WHERE comprobante_sucursal_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerComprobanteSucursalPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM `gestion__comprobantes_sucursales` WHERE comprobante_sucursal_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}