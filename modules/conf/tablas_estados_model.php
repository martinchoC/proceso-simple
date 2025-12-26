<?php
require_once "conexion.php";

function obtenerTablas($conexion) {
    $sql = "SELECT * FROM conf__tablas WHERE tabla_estado_registro_id = 1 ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosRegistro($conexion) {
    $sql = "SELECT * FROM conf__estados_registros ORDER BY estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerColores($conexion) {
    $sql = "SELECT * FROM conf__colores WHERE tabla_estado_registro_id = 1 ORDER BY nombre_color";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablasEstados($conexion) {
    $sql = "SELECT 
                cter.*,
                ct.tabla_nombre,
                cer.estado_registro,
                cc.nombre_color as color_nombre,
                cc.color_clase,
                -- Como no hay campo de estado activo/inactivo, siempre mostramos Activo
                'Activo' as estado_nombre,
                CASE 
                    WHEN cter.es_inicial = 1 THEN 'Sí'
                    ELSE 'No'
                END as es_inicial_nombre
            FROM conf__tablas_estados_registros cter
            LEFT JOIN conf__tablas ct ON cter.tabla_id = ct.tabla_id
            LEFT JOIN conf__estados_registros cer ON cter.estado_registro_id = cer.estado_registro_id
            LEFT JOIN conf__colores cc ON cter.color_id = cc.color_id
            ORDER BY ct.tabla_nombre, cter.orden, cter.tabla_estado_registro";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTablaEstado($conexion, $data) {
    // Validar campos obligatorios
    if (empty($data['tabla_id']) || 
        empty($data['estado_registro_id']) || 
        empty($data['tabla_estado_registro'])) {
        return false;
    }
    
    $tabla_id = intval($data['tabla_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $tabla_estado_registro = mysqli_real_escape_string($conexion, $data['tabla_estado_registro']);
    $color_id = intval($data['color_id']);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == 1 ? 1 : 0;
    $orden = intval($data['orden']);
    
    // NOTA: No pasamos tabla_estado_registro_id porque es AUTO_INCREMENT
    $sql = "INSERT INTO conf__tablas_estados_registros 
            (tabla_id, estado_registro_id, tabla_estado_registro, color_id, es_inicial, orden) 
            VALUES 
            ($tabla_id, $estado_registro_id, '$tabla_estado_registro', $color_id, $es_inicial, $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaEstado($conexion, $id, $data) {
    // Validar campos obligatorios
    if (empty($data['tabla_id']) || 
        empty($data['estado_registro_id']) || 
        empty($data['tabla_estado_registro'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_id = intval($data['tabla_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $tabla_estado_registro = mysqli_real_escape_string($conexion, $data['tabla_estado_registro']);
    $color_id = intval($data['color_id']);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == 1 ? 1 : 0;
    $orden = intval($data['orden']);

    $sql = "UPDATE conf__tablas_estados_registros SET
            tabla_id = $tabla_id,
            estado_registro_id = $estado_registro_id,
            tabla_estado_registro = '$tabla_estado_registro',
            color_id = $color_id,
            es_inicial = $es_inicial,
            orden = $orden
            WHERE tabla_estado_registro_id = $id";
    
    return mysqli_query($conexion, $sql);
}

function eliminarTablaEstado($conexion, $id) {
    $id = intval($id);
    $sql = "DELETE FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerTablaEstadoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_estados_registros WHERE tabla_estado_registro_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function verificarEstadoInicial($conexion, $tabla_id, $excluir_id = 0) {
    $tabla_id = intval($tabla_id);
    $excluir_id = intval($excluir_id);
    
    $sql = "SELECT COUNT(*) as total 
            FROM conf__tablas_estados_registros 
            WHERE tabla_id = $tabla_id 
            AND es_inicial = 1 
            AND tabla_estado_registro_id != $excluir_id";
    
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila['total'] > 0;
}