<?php

function listarFunciones(mysqli $c): array
{
    $sql = "
        SELECT 
            f.pagina_funcion_id,
            p.pagina,
            f.nombre_funcion,
            eo.estado_nombre AS estado_origen,
            ed.estado_nombre AS estado_destino,
            f.orden,
            er.estado_nombre
        FROM conf__paginas_funciones_tipos f
        LEFT JOIN conf__paginas p 
            ON p.pagina_id = f.pagina_id
        LEFT JOIN conf__estados_registros eo 
            ON eo.estado_registro_id = f.tabla_estado_registro_origen_id
        LEFT JOIN conf__estados_registros ed 
            ON ed.estado_registro_id = f.tabla_estado_registro_destino_id
        LEFT JOIN conf__estados_registros er 
            ON er.estado_registro_id = f.tabla_estado_registro_id
        ORDER BY p.pagina, f.orden
    ";

    $res = mysqli_query($c, $sql);
    if (!$res) {
        return [];
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    return $data;
}

function obtenerFuncion(mysqli $c, int $id): array
{
    $res = mysqli_query(
        $c,
        "SELECT * FROM conf__paginas_funciones_tipos WHERE pagina_funcion_id = $id"
    );
    return mysqli_fetch_assoc($res) ?: [];
}

function agregarFuncion(mysqli $c, array $d): array
{
    if (
        empty($d['pagina_id']) ||
        empty($d['nombre_funcion']) ||
        empty($d['tabla_estado_registro_origen_id']) ||
        empty($d['tabla_estado_registro_destino_id'])
    ) {
        return ['resultado' => false, 'mensaje' => 'Datos incompletos'];
    }

    $sql = "
        INSERT INTO conf__paginas_funciones_tipos
        (
            pagina_id,
            nombre_funcion,
            accion_js,
            icono_id,
            color_id,
            tabla_estado_registro_origen_id,
            tabla_estado_registro_destino_id,
            orden,
            descripcion
        )
        VALUES (
            ".intval($d['pagina_id']).",
            '".mysqli_real_escape_string($c, $d['nombre_funcion'])."',
            '".mysqli_real_escape_string($c, $d['accion_js'] ?? '')."',
            ".intval($d['icono_id'] ?? 0).",
            ".intval($d['color_id'] ?? 1).",
            ".intval($d['tabla_estado_registro_origen_id']).",
            ".intval($d['tabla_estado_registro_destino_id']).",
            ".intval($d['orden'] ?? 0).",
            '".mysqli_real_escape_string($c, $d['descripcion'] ?? '')."'
        )
    ";

    if (!mysqli_query($c, $sql)) {
        return ['resultado' => false, 'mensaje' => mysqli_error($c)];
    }

    return ['resultado' => true, 'mensaje' => 'Función creada correctamente'];
}

function editarFuncion(mysqli $c, array $d): array
{
    $id = intval($d['pagina_funcion_id'] ?? 0);
    if ($id <= 0) {
        return ['resultado' => false, 'mensaje' => 'ID inválido'];
    }

    $sql = "
        UPDATE conf__paginas_funciones_tipos SET
            pagina_id = ".intval($d['pagina_id']).",
            nombre_funcion = '".mysqli_real_escape_string($c, $d['nombre_funcion'])."',
            accion_js = '".mysqli_real_escape_string($c, $d['accion_js'] ?? '')."',
            icono_id = ".intval($d['icono_id'] ?? 0).",
            color_id = ".intval($d['color_id'] ?? 1).",
            tabla_estado_registro_origen_id = ".intval($d['tabla_estado_registro_origen_id']).",
            tabla_estado_registro_destino_id = ".intval($d['tabla_estado_registro_destino_id']).",
            orden = ".intval($d['orden'] ?? 0).",
            descripcion = '".mysqli_real_escape_string($c, $d['descripcion'] ?? '')."'
        WHERE pagina_funcion_id = $id
        LIMIT 1
    ";

    if (!mysqli_query($c, $sql)) {
        return ['resultado' => false, 'mensaje' => mysqli_error($c)];
    }

    return ['resultado' => true, 'mensaje' => 'Función actualizada'];
}

function obtenerCombos(mysqli $c): array
{
    return [
        'paginas' => combo($c, 'conf__paginas', 'pagina_id', 'pagina'),
        'iconos'  => combo($c, 'conf__iconos', 'icono_id', 'icono_nombre'),
        'colores' => combo($c, 'conf__colores', 'color_id', 'nombre_color'),
        'estados' => combo($c, 'conf__estados_registros', 'estado_registro_id', 'estado_nombre')
    ];
}

function combo(mysqli $c, string $tabla, string $id, string $label): string
{
    $res = mysqli_query($c, "SELECT $id, $label FROM $tabla ORDER BY $label");
    $html = '<option value="">Seleccionar</option>';

    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $html .= "<option value='{$r[$id]}'>{$r[$label]}</option>";
        }
    }
    return $html;
}
