<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

/**
 * MODELO - jurisdicciones_tipos
 * Replica exacta de lógica IVA
 */

// ===============================
// FUNCIONES BASE (NO TOCAR)
// ===============================

function obtenerFuncionesPagina($conexion, $pagina_id)
{
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = ? 
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.tabla_estado_registro_origen_id, pf.orden";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $pagina_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $funciones = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $funciones[] = $fila;
    }

    return $funciones;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual_id)
{
    $funciones = obtenerFuncionesPagina($conexion, $pagina_id);
    $botones = [];

    foreach ($funciones as $f) {
        if ($f['tabla_estado_registro_origen_id'] == $estado_actual_id) {
            $botones[] = [
                'nombre_funcion' => $f['nombre_funcion'],
                'accion_js' => $f['accion_js'],
                'icono_clase' => $f['icono_clase'],
                'color_clase' => $f['color_clase'],
                'descripcion' => $f['descripcion'],
                'estado_destino_id' => $f['tabla_estado_registro_destino_id']
            ];
        }
    }

    return $botones;
}

function obtenerEstadoInicial($conexion)
{
    $sql = "SELECT estado_registro_id 
            FROM conf__estados_registros 
            ORDER BY valor_estandar ASC LIMIT 1";

    $res = mysqli_query($conexion, $sql);
    $row = mysqli_fetch_assoc($res);

    return $row['estado_registro_id'] ?? 1;
}

// ===============================
// LISTAR
// ===============================

function obtenerJurisdiccionesTipos($conexion, $empresa_idx, $pagina_id)
{
    $sql = "SELECT jt.*, 
                   er.estado_registro,
                   er.codigo_estandar,
                   c.color_clase, c.bg_clase, c.text_clase
            FROM gestion__jurisdicciones_tipos jt
            LEFT JOIN conf__estados_registros er 
                ON jt.tabla_estado_registro_id = er.estado_registro_id
            LEFT JOIN conf__colores c 
                ON er.color_id = c.color_id
            ORDER BY jt.orden";

    $result = mysqli_query($conexion, $sql);

    $data = [];

    while ($fila = mysqli_fetch_assoc($result)) {

        $fila['estado_info'] = [
            'estado_registro' => $fila['estado_registro'],
            'codigo_estandar' => $fila['codigo_estandar'],
            'bg_clase' => $fila['bg_clase'],
            'text_clase' => $fila['text_clase']
        ];

        $fila['botones'] = obtenerBotonesPorEstado(
            $conexion,
            $pagina_id,
            $fila['tabla_estado_registro_id']
        );

        $data[] = $fila;
    }

    return $data;
}

// ===============================
// AGREGAR
// ===============================

function agregarJurisdiccionTipo($conexion, $data)
{
    $estado = obtenerEstadoInicial($conexion);

    $sql = "INSERT INTO gestion__jurisdicciones_tipos
            (jurisdiccion_tipo, codigo, descripcion, orden, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "sssii",
        $data['jurisdiccion_tipo'],
        $data['codigo'],
        $data['descripcion'],
        $data['orden'],
        $estado
    );

    mysqli_stmt_execute($stmt);

    return ['resultado' => true];
}

// ===============================
// EDITAR
// ===============================

function editarJurisdiccionTipo($conexion, $id, $data)
{
    $sql = "UPDATE gestion__jurisdicciones_tipos SET
            jurisdiccion_tipo=?,
            codigo=?,
            descripcion=?,
            orden=?
            WHERE jurisdiccion_tipo_id=?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "sssii",
        $data['jurisdiccion_tipo'],
        $data['codigo'],
        $data['descripcion'],
        $data['orden'],
        $id
    );

    mysqli_stmt_execute($stmt);

    return ['resultado' => true];
}

// ===============================
// TRANSICIÓN ESTADO (CLAVE)
// ===============================

function ejecutarTransicionEstado($conexion, $id, $accion_js, $empresa_idx, $pagina_id)
{
    $sql = "SELECT tabla_estado_registro_id 
            FROM gestion__jurisdicciones_tipos 
            WHERE jurisdiccion_tipo_id=?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    $estado_actual = $row['tabla_estado_registro_id'];

    $sql = "SELECT * FROM conf__paginas_funciones
            WHERE pagina_id=?
            AND tabla_estado_registro_origen_id=?
            AND accion_js=?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $pagina_id, $estado_actual, $accion_js);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);
    $funcion = mysqli_fetch_assoc($res);

    if (!$funcion) {
        return ['success' => false];
    }

    $estado_destino = $funcion['tabla_estado_registro_destino_id'];

    $sql = "UPDATE gestion__jurisdicciones_tipos
            SET tabla_estado_registro_id=?
            WHERE jurisdiccion_tipo_id=?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino, $id);
    mysqli_stmt_execute($stmt);

    return ['success' => true];
}