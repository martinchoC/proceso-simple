<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerTiposTablas($conexion)
{
    $sql = "SELECT tt.*, 
                   COUNT(tt_estado.tabla_tipo_estado_id) as cantidad_estados
            FROM conf__tablas_tipos tt
            LEFT JOIN conf__tablas_tipos_estados tt_estado 
                ON tt.tabla_tipo_id = tt_estado.tabla_tipo_id 
                AND tt_estado.tabla_estado_registro_id = 1
            GROUP BY tt.tabla_tipo_id
            ORDER BY tt.tabla_tipo ASC";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarTipoTabla($conexion, $tabla_tipo)
{
    $tabla_tipo = mysqli_real_escape_string($conexion, $tabla_tipo);

    $sql = "INSERT INTO conf__tablas_tipos (tabla_tipo) VALUES ('$tabla_tipo')";
    return mysqli_query($conexion, $sql);
}

function editarTipoTabla($conexion, $id, $tabla_tipo)
{
    $id = intval($id);
    $tabla_tipo = mysqli_real_escape_string($conexion, $tabla_tipo);

    $sql = "UPDATE conf__tablas_tipos SET tabla_tipo = '$tabla_tipo' WHERE tabla_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function cambiarEstadoTipoTabla($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $sql = "UPDATE conf__tablas_tipos SET tabla_estado_registro_id = $nuevo_estado WHERE tabla_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarTipoTabla($conexion, $id)
{
    $id = intval($id);

    // Primero eliminar todos los estados asociados
    $sql1 = "DELETE FROM conf__tablas_tipos_estados WHERE tabla_tipo_id = $id";
    mysqli_query($conexion, $sql1);

    // Luego eliminar el tipo
    $sql2 = "DELETE FROM conf__tablas_tipos WHERE tabla_tipo_id = $id";
    return mysqli_query($conexion, $sql2);
}

function obtenerTipoTablaPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_tipo_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function obtenerEstadosPorTipo($conexion, $tipo_id)
{
    $tipo_id = intval($tipo_id);

    $sql = "SELECT tte.*, er.estado_registro
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros er ON tte.estado_registro_id = er.estado_registro_id
            WHERE tte.tabla_tipo_id = $tipo_id
            ORDER BY tte.orden ASC";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTodosEstados($conexion)
{
    $sql = "SELECT * FROM conf__estados_registros ORDER BY estado_registro ASC";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function estadoExisteParaTipo($conexion, $tipo_id, $estado_id)
{
    $tipo_id = intval($tipo_id);
    $estado_id = intval($estado_id);

    $sql = "SELECT COUNT(*) as total 
            FROM conf__tablas_tipos_estados 
            WHERE tabla_tipo_id = $tipo_id 
            AND estado_registro_id = $estado_id
            AND tabla_estado_registro_id = 1";

    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila['total'] > 0;
}

function agregarEstadoTipo($conexion, $data)
{
    $tabla_tipo_id = intval($data['tabla_tipo_id']);
    $estado_registro_id = intval($data['estado_registro_id']);
    $orden = intval($data['orden']);
    $es_inicial = intval($data['es_inicial']);

    // Si es inicial, quitar el inicial de otros estados del mismo tipo
    if ($es_inicial == 1) {
        $sqlReset = "UPDATE conf__tablas_tipos_estados 
                     SET es_inicial = 0 
                     WHERE tabla_tipo_id = $tabla_tipo_id";
        mysqli_query($conexion, $sqlReset);
    }

    $sql = "INSERT INTO conf__tablas_tipos_estados 
            (tabla_tipo_id, estado_registro_id, orden, es_inicial) 
            VALUES 
            ($tabla_tipo_id, $estado_registro_id, $orden, $es_inicial)";

    return mysqli_query($conexion, $sql);
}

function editarEstadoTipo($conexion, $id, $data)
{
    $id = intval($id);
    $estado_registro_id = intval($data['estado_registro_id']);
    $orden = intval($data['orden']);
    $es_inicial = intval($data['es_inicial']);

    // Obtener el tipo_id para saber a qué tipo pertenece
    $estadoActual = obtenerEstadoTipoPorId($conexion, $id);
    $tabla_tipo_id = $estadoActual['tabla_tipo_id'];

    // Si es inicial, quitar el inicial de otros estados del mismo tipo
    if ($es_inicial == 1) {
        $sqlReset = "UPDATE conf__tablas_tipos_estados 
                     SET es_inicial = 0 
                     WHERE tabla_tipo_id = $tabla_tipo_id 
                     AND tabla_tipo_estado_id != $id";
        mysqli_query($conexion, $sqlReset);
    }

    $sql = "UPDATE conf__tablas_tipos_estados SET
            estado_registro_id = $estado_registro_id,
            orden = $orden,
            es_inicial = $es_inicial
            WHERE tabla_tipo_estado_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoEstadoTipo($conexion, $id, $nuevo_estado)
{
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);

    $sql = "UPDATE conf__tablas_tipos_estados 
            SET tabla_estado_registro_id = $nuevo_estado 
            WHERE tabla_tipo_estado_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarEstadoTipo($conexion, $id)
{
    $id = intval($id);

    $sql = "DELETE FROM conf__tablas_tipos_estados WHERE tabla_tipo_estado_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerEstadoTipoPorId($conexion, $id)
{
    $id = intval($id);

    $sql = "SELECT tte.*, er.estado_registro
            FROM conf__tablas_tipos_estados tte
            LEFT JOIN conf__estados_registros er ON tte.estado_registro_id = er.estado_registro_id
            WHERE tte.tabla_tipo_estado_id = $id";

    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}