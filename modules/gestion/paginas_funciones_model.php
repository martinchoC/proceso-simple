<?php
require_once "conexion.php";

function obtenerPaginasFunciones($conexion) {
    $sql = "SELECT pf.*, p.pagina, t.tabla_nombre, 
                   eo.estado_registro as estado_origen, ed.estado_registro as estado_destino,
                   i.icono_nombre, i.icono_clase,
                   c.nombre_color, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            LEFT JOIN conf__tablas t ON pf.tabla_id = t.tabla_id
            LEFT JOIN conf__tablas_estados_registros eo ON pf.estado_registro_origen_id = eo.estado_registro_id
            LEFT JOIN conf__tablas_estados_registros ed ON pf.estado_registro_destino_id = ed.estado_registro_id
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE p.modulo_id=2
            ORDER BY p.pagina, pf.orden";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPaginas($conexion) {
    $sql = "SELECT pagina_id, pagina, tabla_id FROM conf__paginas WHERE modulo_id=2 ORDER BY pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Función para obtener todos los iconos disponibles
function obtenerIconos($conexion) {
    $sql = "SELECT icono_id, icono_nombre, icono_clase 
            FROM conf__iconos 
            WHERE estado_registro_id = 1 
            ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Función para obtener todos los colores disponibles
function obtenerColores($conexion) {
    $sql = "SELECT color_id, nombre_color, color_clase, bg_clase, text_clase 
            FROM conf__colores 
            WHERE estado_registro_id = 1 
            ORDER BY nombre_color";
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
    $sql = "SELECT tabla_id FROM conf__paginas WHERE modulo_id=2 AND pagina_id = $pagina_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['tabla_id'] : null;
}

function obtenerTablas($conexion) {
    $sql = "SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE modulo_id=2 ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosPorTabla($conexion, $tabla_id) {
    $tabla_id = intval($tabla_id);
    $sql = "SELECT er.estado_registro_id, er.estado_registro 
            FROM conf__tablas_estados_registros er
            WHERE er.tabla_id = $tabla_id
            ORDER BY er.orden, er.estado_registro";
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
    $icono_id = $data['icono_id'] ? intval($data['icono_id']) : 'NULL';
    $color_id = $data['color_id'] ? intval($data['color_id']) : 'NULL';
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $accion_js = $data['accion_js'] ? "'" . mysqli_real_escape_string($conexion, $data['accion_js']) . "'" : 'NULL';
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion'] ?? '');
    $estado_origen_id = intval($data['estado_registro_origen_id']);
    $estado_destino_id = intval($data['estado_registro_destino_id']);
    
    $orden = intval($data['orden'] ?? 0);

    $sql = "INSERT INTO conf__paginas_funciones 
            (pagina_id, tabla_id, icono_id, color_id, nombre_funcion, accion_js, descripcion, estado_registro_origen_id, estado_registro_destino_id, orden) 
            VALUES ($pagina_id, $tabla_id, $icono_id, $color_id, '$nombre_funcion', $accion_js, '$descripcion', $estado_origen_id, $estado_destino_id, $orden)";
    
    return mysqli_query($conexion, $sql);
}

function editarPaginaFuncion($conexion, $id, $data) {
    if (empty($data['pagina_id']) || empty($data['nombre_funcion'])) {
        return false;
    }
    
    $id = intval($id);
    $pagina_id = intval($data['pagina_id']);
    $tabla_id = $data['tabla_id'] ? intval($data['tabla_id']) : 'NULL';
    $icono_id = $data['icono_id'] ? intval($data['icono_id']) : 'NULL';
    $color_id = $data['color_id'] ? intval($data['color_id']) : 'NULL';
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $accion_js = $data['accion_js'] ? "'" . mysqli_real_escape_string($conexion, $data['accion_js']) . "'" : 'NULL';
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion'] ?? '');
    $estado_origen_id = intval($data['estado_registro_origen_id']);
    $estado_destino_id = intval($data['estado_registro_destino_id']);
    
    $orden = intval($data['orden'] ?? 0);

    $sql = "UPDATE conf__paginas_funciones SET
            pagina_id = $pagina_id,
            tabla_id = $tabla_id,
            icono_id = $icono_id,
            color_id = $color_id,
            nombre_funcion = '$nombre_funcion',
            accion_js = $accion_js,
            descripcion = '$descripcion',
            estado_registro_origen_id = $estado_origen_id,
            estado_registro_destino_id = $estado_destino_id,
            
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
?>