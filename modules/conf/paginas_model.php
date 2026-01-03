<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerModulos($conexion) {
    $sql = "SELECT * FROM conf__modulos ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPadre($conexion) {
    $sql = "SELECT * FROM conf__paginas ORDER BY pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablas($conexion) {
    $sql = "SELECT * FROM conf__tablas ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerIconos($conexion) {
    $sql = "SELECT * FROM conf__iconos ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Nueva función: Obtener tipos de tabla
function obtenerTablaTipos($conexion) {
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Nueva función: Obtener funciones por tipo de tabla
function obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id) {
    $tabla_tipo_id = intval($tabla_tipo_id);
    $sql = "SELECT f.*, i.icono_clase, c.color_clase 
            FROM conf__paginas_funciones_tipos f
            LEFT JOIN conf__iconos i ON f.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON f.color_id = c.color_id
            WHERE f.tabla_tipo_id = $tabla_tipo_id AND f.tabla_estado_registro_id = 1
            ORDER BY f.orden";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Nueva función: Obtener funciones de una página específica
function obtenerFuncionesPorPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, i.icono_clase, i.icono_nombre, c.color_clase, c.nombre_color,
                   eor.tabla_estado_registro as origen_nombre, ed.tabla_estado_registro as destino_nombre
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            LEFT JOIN conf__tablas_estados_registros eor ON pf.tabla_estado_registro_origen_id = eor.tabla_estado_registro_id
            LEFT JOIN conf__tablas_estados_registros ed ON pf.tabla_estado_registro_destino_id = ed.tabla_estado_registro_id
            WHERE pf.pagina_id = $pagina_id AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.orden";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Nueva función: Copiar funciones de tipo a página
function copiarFuncionesDeTipo($conexion, $pagina_id, $tabla_tipo_id) {
    $pagina_id = intval($pagina_id);
    $tabla_tipo_id = intval($tabla_tipo_id);
    
    // Obtener funciones del tipo
    $funciones_tipo = obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id);
    
    $resultados = [];
    foreach ($funciones_tipo as $funcion) {
        $sql = "INSERT INTO conf__paginas_funciones 
                (pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, 
                 accion_js, descripcion, tabla_estado_registro_origen_id, 
                 tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
                VALUES (
                    $pagina_id,
                    " . ($funcion['icono_id'] ? intval($funcion['icono_id']) : 'NULL') . ",
                    " . ($funcion['color_id'] ? intval($funcion['color_id']) : '1') . ",
                    1,
                    '" . mysqli_real_escape_string($conexion, $funcion['nombre_funcion']) . "',
                    " . ($funcion['accion_js'] ? "'" . mysqli_real_escape_string($conexion, $funcion['accion_js']) . "'" : 'NULL') . ",
                    " . ($funcion['descripcion'] ? "'" . mysqli_real_escape_string($conexion, $funcion['descripcion']) . "'" : 'NULL') . ",
                    " . intval($funcion['tabla_estado_registro_origen_id']) . ",
                    " . intval($funcion['tabla_estado_registro_destino_id']) . ",
                    " . intval($funcion['orden']) . ",
                    1
                )";
        
        $resultados[] = mysqli_query($conexion, $sql);
    }
    
    // Retornar true si todas las inserciones fueron exitosas
    return !in_array(false, $resultados, true);
}

// Nueva función: Obtener tipo de tabla por tabla_id
function obtenerTablaTipoPorTablaId($conexion, $tabla_id) {
    $tabla_id = intval($tabla_id);
    $sql = "SELECT tabla_tipo_id FROM conf__tablas WHERE tabla_id = $tabla_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['tabla_tipo_id'] : null;
}

// Nueva función: Verificar si página ya tiene funciones
function paginaTieneFunciones($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    $sql = "SELECT COUNT(*) as total FROM conf__paginas_funciones WHERE pagina_id = $pagina_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila['total'] > 0;
}

function obtenerpaginas($conexion) {
    $sql = "SELECT p.*,  m.modulo,  padre.pagina as padre_nombre, 
                   conf__tablas.tabla_nombre, conf__iconos.icono_nombre, 
                   conf__iconos.icono_clase,
                   (SELECT COUNT(*) FROM conf__paginas_funciones pf WHERE pf.pagina_id = p.pagina_id) as tiene_funciones
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__paginas padre ON p.padre_id = padre.pagina_id
            LEFT JOIN conf__tablas ON p.tabla_id = conf__tablas.tabla_id
            LEFT JOIN conf__iconos ON p.icono_id = conf__iconos.icono_id
            ORDER BY p.orden, p.pagina ";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarpagina($conexion, $data) {
    // Validar campos obligatorios (sin incluir padre_id)
    if (empty($data['pagina']) || empty($data['modulo_id'])) {
        return false;
    }
    
    $pagina = mysqli_real_escape_string($conexion, $data['pagina']);
    $url = mysqli_real_escape_string($conexion, $data['url']);
    $pagina_descripcion = mysqli_real_escape_string($conexion, $data['pagina_descripcion']);
    $orden = mysqli_real_escape_string($conexion, $data['orden']);
    $tabla_id = mysqli_real_escape_string($conexion, $data['tabla_id']);
    $icono_id = mysqli_real_escape_string($conexion, $data['icono_id']);
    $padre_id = (!empty($data['padre_id']) && is_numeric($data['padre_id'])) ? intval($data['padre_id']) : 'NULL';
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    $sql = "INSERT INTO conf__paginas 
            (pagina, url, pagina_descripcion, orden, tabla_id, padre_id, modulo_id, tabla_estado_registro_id, icono_id) 
            VALUES 
            ('$pagina', '$url', '$pagina_descripcion', '$orden', '$tabla_id', $padre_id, $modulo_id, $tabla_estado_registro_id,'$icono_id')";
    
    return mysqli_query($conexion, $sql);
}

function editarpagina($conexion, $id, $data) {
    // Validar campos obligatorios (sin incluir padre_id)
    if (empty($data['pagina']) || empty($data['modulo_id'])) {
        return false;
    }
    $id = intval($id);
    $pagina = mysqli_real_escape_string($conexion, $data['pagina']);
    $url = mysqli_real_escape_string($conexion, $data['url']);
    $pagina_descripcion = mysqli_real_escape_string($conexion, $data['pagina_descripcion']);
    $orden = mysqli_real_escape_string($conexion, $data['orden']);
    $tabla_id = mysqli_real_escape_string($conexion, $data['tabla_id']);
    $icono_id = mysqli_real_escape_string($conexion, $data['icono_id']);
    $padre_id = (!empty($data['padre_id']) && is_numeric($data['padre_id'])) ? intval($data['padre_id']) : 'NULL';
    $modulo_id = is_numeric($data['modulo_id']) ? $data['modulo_id'] : 'NULL';
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__paginas SET
        pagina='$pagina',
        url='$url',
        pagina_descripcion='$pagina_descripcion',
        orden='$orden',
        tabla_id='$tabla_id',
        icono_id='$icono_id',
        padre_id=$padre_id,
        modulo_id=$modulo_id,
        tabla_estado_registro_id=$tabla_estado_registro_id
        WHERE pagina_id=$id";

    return mysqli_query($conexion, $sql);
}

function eliminarpagina($conexion, $id) {
    $id = intval($id);
    
    // Primero eliminar funciones asociadas
    $sql1 = "DELETE FROM conf__paginas_funciones WHERE pagina_id = $id";
    mysqli_query($conexion, $sql1);
    
    // Luego eliminar la página
    $sql2 = "DELETE FROM conf__paginas WHERE pagina_id = $id";
    return mysqli_query($conexion, $sql2);
}

function obtenerpaginaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT p.*, t.tabla_tipo_id 
            FROM conf__paginas p
            LEFT JOIN conf__tablas t ON p.tabla_id = t.tabla_id
            WHERE pagina_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
