<?php
require_once "conexion.php";

// ========== FUNCIONES PARA TIPOS DE TABLAS ==========

function obtenerTablasTipos($conexion) {
    $sql = "SELECT 
                tt.*,
                COUNT(DISTINCT tte.tabla_tipo_estado_id) as cantidad_estados
            FROM conf__tablas_tipos tt
            LEFT JOIN conf__tablas_tipos_estados tte ON tt.tabla_tipo_id = tte.tabla_tipo_id 
                AND tte.tabla_estado_registro_id = 1
            GROUP BY tt.tabla_tipo_id
            ORDER BY tt.tabla_tipo";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerTablasTipos: " . mysqli_error($conexion));
        return [];
    }
    
    $tipos = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $tipos[] = $fila;
    }
    
    return $tipos;
}

function obtenerTablaTipoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_tipo_id = $id";
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerTablaTipoPorId: " . mysqli_error($conexion));
        return null;
    }
    
    return mysqli_fetch_assoc($result);
}

function agregarTablaTipo($conexion, $data) {
    $tabla_tipo = mysqli_real_escape_string($conexion, $data['tabla_tipo']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);
    
    $sql = "INSERT INTO conf__tablas_tipos (tabla_tipo, tabla_estado_registro_id) 
            VALUES ('$tabla_tipo', $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaTipo($conexion, $id, $data) {
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
    
    $sql = "UPDATE conf__tablas_tipos SET 
            tabla_estado_registro_id = $nuevo_estado 
            WHERE tabla_tipo_id = $id";
    
    return mysqli_query($conexion, $sql);
}

// ========== FUNCIONES PARA ESTADOS DE TIPOS DE TABLAS ==========

function obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    
    $sql = "SELECT 
                tte.*,
                cer.estado_registro,
                cer.codigo_estandar
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros cer ON tte.estado_registro_id = cer.estado_registro_id
            WHERE tte.tabla_tipo_id = $tabla_tipo_id
            ORDER BY tte.orden, cer.orden_estandar";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerEstadosPorTablaTipo: " . mysqli_error($conexion));
        return [];
    }
    
    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = $fila;
    }
    
    return $estados;
}

function obtenerTablaTipoEstadoPorId($conexion, $id) {
    $id = intval($id);
    
    $sql = "SELECT 
                tte.*,
                cer.estado_registro,
                cer.codigo_estandar
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros cer ON tte.estado_registro_id = cer.estado_registro_id
            WHERE tte.tabla_tipo_estado_id = $id";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerTablaTipoEstadoPorId: " . mysqli_error($conexion));
        return null;
    }
    
    return mysqli_fetch_assoc($result);
}

function agregarTablaTipoEstado($conexion, $data) {
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $orden = intval($data['orden']);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == '1' ? 1 : 0;
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);
    
    // Verificar si ya existe este estado para este tipo
    $sql_check = "SELECT tabla_tipo_estado_id FROM conf__tablas_tipos_estados 
                  WHERE tabla_tipo_id = $tabla_tipo_id 
                  AND estado_registro_id = $estado_registro_id";
    
    $check = mysqli_query($conexion, $sql_check);
    
    if (mysqli_num_rows($check) > 0) {
        return false; // Ya existe
    }
    
    $sql = "INSERT INTO conf__tablas_tipos_estados 
            (tabla_tipo_id, estado_registro_id, orden, es_inicial, tabla_estado_registro_id) 
            VALUES 
            ($tabla_tipo_id, $estado_registro_id, $orden, $es_inicial, $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarTablaTipoEstado($conexion, $id, $data) {
    $id = intval($id);
    $orden = intval($data['orden']);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == '1' ? 1 : 0;
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);
    
    $sql = "UPDATE conf__tablas_tipos_estados SET 
            orden = $orden,
            es_inicial = $es_inicial,
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE tabla_tipo_estado_id = $id";
    
    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas_tipos_estados SET 
            tabla_estado_registro_id = $nuevo_estado 
            WHERE tabla_tipo_estado_id = $id";
    
    return mysqli_query($conexion, $sql);
}

// ========== FUNCIONES PARA ESTADOS REGISTROS ==========

function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT * FROM conf__estados_registros 
            ORDER BY orden_estandar";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerEstadosRegistros: " . mysqli_error($conexion));
        return [];
    }
    
    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = $fila;
    }
    
    return $estados;
}

function obtenerEstadosRegistrosDisponibles($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    
    $sql = "SELECT cer.* 
            FROM conf__estados_registros cer
            WHERE cer.estado_registro_id NOT IN (
                SELECT tte.estado_registro_id 
                FROM conf__tablas_tipos_estados tte
                WHERE tte.tabla_tipo_id = $tabla_tipo_id
            )
            ORDER BY cer.orden_estandar";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en obtenerEstadosRegistrosDisponibles: " . mysqli_error($conexion));
        return [];
    }
    
    $estados = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $estados[] = $fila;
    }
    
    return $estados;
}

// ========== FUNCIONES UTILITARIAS ==========

function obtenerUltimoId($conexion) {
    return mysqli_insert_id($conexion);
}

function verificarError($conexion) {
    return mysqli_error($conexion);
}
?>