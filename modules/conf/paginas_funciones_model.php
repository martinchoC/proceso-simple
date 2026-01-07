<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerPaginas($conexion)
{
    // CORRECCIÓN: Usar 'pagina' en lugar de 'nombre_pagina'
    $sql = "SELECT * FROM conf__paginas WHERE tabla_estado_registro_id = 1 ORDER BY pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerIconos($conexion)
{
    $sql = "SELECT * FROM conf__iconos WHERE tabla_estado_registro_id = 1 ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerColores($conexion)
{
    $sql = "SELECT * FROM conf__colores WHERE tabla_estado_registro_id = 1 ORDER BY nombre_color";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerFuncionesEstandar($conexion)
{
    $sql = "SELECT * FROM conf__paginas_funciones_tipos WHERE tabla_estado_registro_id = 1 ORDER BY nombre_funcion";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosRegistro($conexion)
{
    $sql = "SELECT * FROM conf__estados_registros ORDER BY estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPaginasFunciones($conexion)
{
    // CORRECCIÓN: Usar 'p.pagina' en lugar de 'p.nombre_pagina'
    // y 'p.url' en lugar de 'p.ruta_pagina'
    $sql = "SELECT 
                pf.*,
                p.pagina as nombre_pagina,  -- Cambiado de p.nombre_pagina
                p.url as ruta_pagina,       -- Cambiado de p.ruta_pagina
                i.icono_nombre,
                i.icono_clase,
                c.nombre_color as color_nombre,
                c.color_clase,
                ft.nombre_funcion as funcion_estandar_nombre,
                ft.accion_js as funcion_estandar_accion,
                CASE 
                    WHEN pf.tabla_estado_registro_origen_id = 0 THEN 'Sin estado'
                    ELSE eor.estado_registro
                END as estado_origen,
                ede.estado_registro as estado_destino,
                CASE 
                    WHEN pf.tabla_estado_registro_id = 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END as estado_nombre
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            LEFT JOIN conf__paginas_funciones_tipos ft ON pf.funcion_estandar_id = ft.pagina_funcion_id
            LEFT JOIN conf__estados_registros eor ON pf.tabla_estado_registro_origen_id = eor.estado_registro_id
            LEFT JOIN conf__estados_registros ede ON pf.tabla_estado_registro_destino_id = ede.estado_registro_id
            ORDER BY pf.orden, pf.nombre_funcion";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPaginaFuncion($conexion, $data)
{
    // Validar campos obligatorios
    if (
        empty($data['nombre_funcion']) ||
        empty($data['pagina_id']) ||
        empty($data['tabla_estado_registro_destino_id'])
    ) {
        return false;
    }

    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $pagina_id = intval($data['pagina_id']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? intval($data['funcion_estandar_id']) : 'NULL';
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__paginas_funciones 
            (nombre_funcion, pagina_id, accion_js, descripcion, orden, icono_id, color_id, 
             funcion_estandar_id, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, tabla_estado_registro_id) 
            VALUES 
            ('$nombre_funcion', $pagina_id, '$accion_js', '$descripcion', $orden, $icono_id, $color_id,
             $funcion_estandar_id, $estado_origen_id, $estado_destino_id, $estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarPaginaFuncion($conexion, $id, $data)
{
    // Validar campos obligatorios
    if (
        empty($data['nombre_funcion']) ||
        empty($data['pagina_id']) ||
        empty($data['tabla_estado_registro_destino_id'])
    ) {
        return false;
    }

    $id = intval($id);
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $pagina_id = intval($data['pagina_id']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? intval($data['funcion_estandar_id']) : 'NULL';
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__paginas_funciones SET
            nombre_funcion = '$nombre_funcion',
            pagina_id = $pagina_id,
            accion_js = '$accion_js',
            descripcion = '$descripcion',
            orden = $orden,
            icono_id = $icono_id,
            color_id = $color_id,
            funcion_estandar_id = $funcion_estandar_id,
            tabla_estado_registro_origen_id = $estado_origen_id,
            tabla_estado_registro_destino_id = $estado_destino_id,
            tabla_estado_registro_id = $estado_registro_id
            WHERE pagina_funcion_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarPaginaFuncion($conexion, $id)
{
    $id = intval($id);
    $sql = "DELETE FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerPaginaFuncionPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>