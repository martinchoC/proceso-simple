<?php
require_once "conexion.php";

function obtenerModulos($conexion) {
    $sql = "SELECT * FROM conf__modulos WHERE modulo_id=2 ORDER BY modulo";
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

function obtenerpaginas($conexion) {
    $modulo_idx=2;
    $sql = "SELECT p.*,  m.modulo,  padre.pagina as padre_nombre, conf__tablas.tabla_nombre, conf__iconos.icono_nombre, conf__iconos.icono_clase
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__paginas padre ON p.padre_id = padre.pagina_id
            LEFT JOIN conf__tablas ON p.tabla_id = conf__tablas.tabla_id
            LEFT JOIN conf__iconos ON p.icono_id = conf__iconos.icono_id
            WHERE m.modulo_id=$modulo_idx
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
    $sql = "DELETE FROM conf__paginas WHERE pagina_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerpaginaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT * FROM conf__paginas WHERE pagina_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
