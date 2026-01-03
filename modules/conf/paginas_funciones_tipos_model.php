<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerTablasTipos($conexion) {
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerIconos($conexion) {
    $sql = "SELECT * FROM conf__iconos WHERE tabla_estado_registro_id = 1 ORDER BY icono_nombre";
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

function obtenerEstadosRegistro($conexion) {
    $sql = "SELECT * FROM conf__estados_registros ORDER BY estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerFuncionesTipos($conexion) {
    $sql = "SELECT 
                ft.*,
                tt.tabla_tipo,
                i.icono_nombre,
                i.icono_clase,
                c.nombre_color as color_nombre,
                c.color_clase,
                CASE 
                    WHEN ft.tabla_estado_registro_origen_id = 0 THEN 'Sin estado'
                    ELSE eor.estado_registro
                END as estado_origen,
                ede.estado_registro as estado_destino,
                CASE 
                    WHEN ft.tabla_estado_registro_id = 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END as estado_nombre
            FROM conf__paginas_funciones_tipos ft
            LEFT JOIN conf__tablas_tipos tt ON ft.tabla_tipo_id = tt.tabla_tipo_id
            LEFT JOIN conf__iconos i ON ft.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON ft.color_id = c.color_id
            LEFT JOIN conf__estados_registros eor ON ft.tabla_estado_registro_origen_id = eor.estado_registro_id
            LEFT JOIN conf__estados_registros ede ON ft.tabla_estado_registro_destino_id = ede.estado_registro_id
            ORDER BY tt.tabla_tipo, ft.orden, ft.nombre_funcion";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarFuncionTipo($conexion, $data) {
    // Validar campos obligatorios (solo destino es obligatorio)
    if (empty($data['nombre_funcion']) || 
        empty($data['tabla_estado_registro_destino_id'])) {
        return false;
    }
    
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $tabla_tipo_id = !empty($data['tabla_tipo_id']) ? intval($data['tabla_tipo_id']) : 'NULL';
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__paginas_funciones_tipos 
            (nombre_funcion, accion_js, descripcion, orden, tabla_tipo_id, icono_id, color_id, 
             tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, tabla_estado_registro_id) 
            VALUES 
            ('$nombre_funcion', '$accion_js', '$descripcion', $orden, $tabla_tipo_id, $icono_id, $color_id,
             $estado_origen_id, $estado_destino_id, $estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarFuncionTipo($conexion, $id, $data) {
    // Validar campos obligatorios (solo destino es obligatorio)
    if (empty($data['nombre_funcion']) || 
        empty($data['tabla_estado_registro_destino_id'])) {
        return false;
    }
    
    $id = intval($id);
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $tabla_tipo_id = !empty($data['tabla_tipo_id']) ? intval($data['tabla_tipo_id']) : 'NULL';
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__paginas_funciones_tipos SET
            nombre_funcion = '$nombre_funcion',
            accion_js = '$accion_js',
            descripcion = '$descripcion',
            orden = $orden,
            tabla_tipo_id = $tabla_tipo_id,
            icono_id = $icono_id,
            color_id = $color_id,
            tabla_estado_registro_origen_id = $estado_origen_id,
            tabla_estado_registro_destino_id = $estado_destino_id,
            tabla_estado_registro_id = $estado_registro_id
            WHERE pagina_funcion_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarFuncionTipo($conexion, $id) {
    $id = intval($id);
    $sql = "DELETE FROM conf__paginas_funciones_tipos WHERE pagina_funcion_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerFuncionTipoPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__paginas_funciones_tipos WHERE pagina_funcion_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}


// ... (el resto del archivo permanece igual)