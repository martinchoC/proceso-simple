<?php
require_once __DIR__ . '/../../conexion.php';

/**
 * Obtiene todos los tipos de tablas con la cantidad de estados activos
 * @param mysqli $conexion Conexión a la base de datos
 * @return array Lista de tipos de tablas
 */
function obtenerTablasTipos($conexion) {
    $sql = "SELECT tt.*, 
            COALESCE(COUNT(tte.tabla_tipo_estado_id), 0) as cantidad_estados
            FROM conf__tablas_tipos tt
            LEFT JOIN conf__tablas_tipos_estados tte 
                ON tt.tabla_tipo_id = tte.tabla_tipo_id 
                AND tte.tabla_tabla_estado_registro_id = 1
            GROUP BY tt.tabla_tipo_id
            ORDER BY tt.tabla_tipo";
    
    $res = mysqli_query($conexion, $sql);
    if (!$res) {
        error_log("Error en obtenerTablasTipos: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

/**
 * Obtiene un tipo de tabla por su ID
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del tipo de tabla
 * @return array|null Datos del tipo de tabla o null si no existe
 */
function obtenerTablaTipoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_tipo_id = $id";
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en obtenerTablaTipoPorId: " . mysqli_error($conexion));
        return null;
    }
    
    return mysqli_fetch_assoc($res);
}

/**
 * Agrega un nuevo tipo de tabla
 * @param mysqli $conexion Conexión a la base de datos
 * @param array $data Datos del tipo de tabla
 * @return bool True si se agregó correctamente
 */
function agregarTablaTipo($conexion, $data) {
    if (empty($data['tabla_tipo'])) {
        return false;
    }
    
    $tabla_tipo = mysqli_real_escape_string($conexion, $data['tabla_tipo']);
    $tabla_tabla_estado_registro_id = intval($data['tabla_tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__tablas_tipos (tabla_tipo, tabla_tabla_estado_registro_id) 
            VALUES ('$tabla_tipo', $tabla_tabla_estado_registro_id)";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en agregarTablaTipo: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_insert_id($conexion) > 0;
}

/**
 * Edita un tipo de tabla existente
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del tipo de tabla a editar
 * @param array $data Nuevos datos del tipo de tabla
 * @return bool True si se editó correctamente
 */
function editarTablaTipo($conexion, $id, $data) {
    if (empty($data['tabla_tipo'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_tipo = mysqli_real_escape_string($conexion, $data['tabla_tipo']);
    $tabla_tabla_estado_registro_id = intval($data['tabla_tabla_estado_registro_id']);

    $sql = "UPDATE conf__tablas_tipos SET
            tabla_tipo = '$tabla_tipo',
            tabla_tabla_estado_registro_id = $tabla_tabla_estado_registro_id
            WHERE tabla_tipo_id = $id";

    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en editarTablaTipo: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_affected_rows($conexion) > 0;
}

/**
 * Cambia el estado de un tipo de tabla
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del tipo de tabla
 * @param int $nuevo_estado Nuevo estado (1 = activo, 0 = inactivo)
 * @return bool True si se cambió correctamente
 */
function cambiarEstadoTablaTipo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas_tipos SET tabla_tabla_estado_registro_id = $nuevo_estado WHERE tabla_tipo_id = $id";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en cambiarEstadoTablaTipo: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_affected_rows($conexion) > 0;
}

// ============================================================================
// FUNCIONES PARA ESTADOS DE TIPOS DE TABLAS (ADAPTADAS A TU ESTRUCTURA)
// ============================================================================

/**
 * Obtiene los estados asociados a un tipo de tabla
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $tabla_tipo_id ID del tipo de tabla
 * @return array Lista de estados
 */
function obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    
    $sql = "SELECT 
                tte.tabla_tipo_estado_id,
                tte.tabla_tipo_id,
                tte.tabla_estado_registro_id,
                er.estado_registro as tabla_tipo_estado,
                '' as tabla_tipo_estado_descripcion,
                tte.orden as valor,
                tte.tabla_tabla_estado_registro_id,
                tte.es_inicial
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros er ON tte.tabla_estado_registro_id = er.tabla_estado_registro_id
            WHERE tte.tabla_tipo_id = $tabla_tipo_id 
            ORDER BY tte.orden, tte.tabla_estado_registro_id";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en obtenerEstadosPorTablaTipo: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

/**
 * Obtiene un estado específico por su ID
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del estado
 * @return array|null Datos del estado o null si no existe
 */
function obtenerTablaTipoEstadoPorId($conexion, $id) {
    $id = intval($id);
    
    $sql = "SELECT 
                tte.tabla_tipo_estado_id,
                tte.tabla_tipo_id,
                tte.tabla_estado_registro_id,
                er.estado_registro as tabla_tipo_estado,
                '' as tabla_tipo_estado_descripcion,
                tte.orden as valor,
                tte.tabla_tabla_estado_registro_id,
                tte.es_inicial
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros er ON tte.tabla_estado_registro_id = er.tabla_estado_registro_id
            WHERE tte.tabla_tipo_estado_id = $id";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en obtenerTablaTipoEstadoPorId: " . mysqli_error($conexion));
        return null;
    }
    
    return mysqli_fetch_assoc($res);
}

/**
 * Agrega un nuevo estado a un tipo de tabla
 * @param mysqli $conexion Conexión a la base de datos
 * @param array $data Datos del estado
 * @return bool True si se agregó correctamente
 */
function agregarTablaTipoEstado($conexion, $data) {
    if (empty($data['tabla_tipo_estado']) || empty($data['tabla_tipo_id'])) {
        return false;
    }
    
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $tabla_estado_registro_id = intval($data['tabla_tipo_estado']);
    $valor = intval($data['valor'] ?? 1);
    $tabla_tabla_estado_registro_id = intval($data['tabla_tabla_estado_registro_id'] ?? 1);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == 1 ? 1 : 0;

    $sql = "INSERT INTO conf__tablas_tipos_estados 
            (tabla_tipo_id, tabla_estado_registro_id, orden, es_inicial, tabla_tabla_estado_registro_id) 
            VALUES 
            ($tabla_tipo_id, $tabla_estado_registro_id, $valor, $es_inicial, $tabla_tabla_estado_registro_id)";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en agregarTablaTipoEstado: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_insert_id($conexion) > 0;
}

/**
 * Edita un estado existente
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del estado a editar
 * @param array $data Nuevos datos del estado
 * @return bool True si se editó correctamente
 */
function editarTablaTipoEstado($conexion, $id, $data) {
    if (empty($data['tabla_tipo_estado'])) {
        return false;
    }
    
    $id = intval($id);
    $tabla_estado_registro_id = intval($data['tabla_tipo_estado']);
    $valor = intval($data['valor'] ?? 1);
    $tabla_tabla_estado_registro_id = intval($data['tabla_tabla_estado_registro_id']);
    $es_inicial = isset($data['es_inicial']) && $data['es_inicial'] == 1 ? 1 : 0;

    $sql = "UPDATE conf__tablas_tipos_estados SET
            tabla_estado_registro_id = $tabla_estado_registro_id,
            orden = $valor,
            es_inicial = $es_inicial,
            tabla_tabla_estado_registro_id = $tabla_tabla_estado_registro_id
            WHERE tabla_tipo_estado_id = $id";

    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en editarTablaTipoEstado: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_affected_rows($conexion) > 0;
}

/**
 * Cambia el estado de un estado específico
 * @param mysqli $conexion Conexión a la base de datos
 * @param int $id ID del estado
 * @param int $nuevo_estado Nuevo estado (1 = activo, 0 = inactivo)
 * @return bool True si se cambió correctamente
 */
function cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE conf__tablas_tipos_estados SET tabla_tabla_estado_registro_id = $nuevo_estado WHERE tabla_tipo_estado_id = $id";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        error_log("Error en cambiarEstadoTablaTipoEstado: " . mysqli_error($conexion));
        return false;
    }
    
    return mysqli_affected_rows($conexion) > 0;
}

/**
 * Obtiene todos los estados registros disponibles
 * @param mysqli $conexion Conexión a la base de datos
 * @return array Lista de estados registros
 */
function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT tabla_estado_registro_id, estado_registro 
            FROM conf__estados_registros 
            ORDER BY estado_registro";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en obtenerEstadosRegistros: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>