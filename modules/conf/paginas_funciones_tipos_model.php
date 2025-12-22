<?php
require_once "conexion.php";

function obtenerPaginasFunciones($conexion) {
    $sql = "SELECT pf.*, p.pagina, t.tabla_nombre, 
                   eo.estado_registro as estado_origen, ed.estado_registro as estado_destino
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            LEFT JOIN conf__tablas t ON pf.tabla_id = t.tabla_id
            LEFT JOIN conf__estados_registros eo ON pf.tabla_estado_registro_origen_id = eo.estado_registro_id
            LEFT JOIN conf__estados_registros ed ON pf.tabla_estado_registro_destino_id = ed.estado_registro_id
            ORDER BY p.pagina, pf.orden";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPaginas($conexion) {
    $sql = "SELECT pagina_id, pagina, tabla_id FROM conf__paginas ORDER BY pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
// Añadir esta función para obtener la tabla de una página
function obtenerTablaPorPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT tabla_id FROM conf__paginas WHERE pagina_id = $pagina_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['tabla_id'] : null;
}

function obtenerTablas($conexion) {
    $sql = "SELECT tabla_id, tabla_nombre FROM conf__tablas ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosPorTabla($conexion, $tabla_id) {
    $tabla_id = intval($tabla_id);
    $sql = "SELECT er.tabla_estado_registro_id, er.estado_registro 
            FROM conf__tablas_estados te
            LEFT JOIN conf__estados_registros er ON te.tabla_estado_registro_id = er.tabla_estado_registro_id
            WHERE te.tabla_id = $tabla_id
            ORDER BY te.orden, er.estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPaginaFuncion($conexion, $data) {
    if (empty($data['pagina_id']) || empty($data['nombre_funcion'])) {
        return false;
    }
    
    $pagina_id = intval($data['pagina_id']);
    $tabla_id = $data['tabla_id'] ? intval($data['tabla_id']) : 'NULL';
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion'] ?? '');
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $es_confirmable = isset($data['es_confirmable']) ? 1 : 0;
    $orden = intval($data['orden'] ?? 0);

    $sql = "INSERT INTO conf__paginas_funciones 
            (pagina_id, tabla_id, nombre_funcion, descripcion, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, es_confirmable, orden) 
            VALUES ($pagina_id, $tabla_id, '$nombre_funcion', '$descripcion', $estado_origen_id, $estado_destino_id, $es_confirmable, $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarPaginaFuncion($conexion, $id, $data) {
    if (empty($data['pagina_id']) || empty($data['nombre_funcion'])) {
        return false;
    }
    
    $id = intval($id);
    $pagina_id = intval($data['pagina_id']);
    $tabla_id = $data['tabla_id'] ? intval($data['tabla_id']) : 'NULL';
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion'] ?? '');
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $es_confirmable = isset($data['es_confirmable']) ? 1 : 0;
    $orden = intval($data['orden'] ?? 0);

    $sql = "UPDATE conf__paginas_funciones SET
            pagina_id = $pagina_id,
            tabla_id = $tabla_id,
            nombre_funcion = '$nombre_funcion',
            descripcion = '$descripcion',
            tabla_estado_registro_origen_id = $estado_origen_id,
            tabla_estado_registro_destino_id = $estado_destino_id,
            es_confirmable = $es_confirmable,
            orden = $orden
            WHERE pagina_funcion_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarPaginaFuncion($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerPaginaFuncionPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}