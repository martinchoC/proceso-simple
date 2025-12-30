<?php
require_once "conexion.php";

// ✅ Sistema completo de botones dinámicos
function obtenerPaginaPorUrl($conexion, $url) {
    $url = mysqli_real_escape_string($conexion, $url);
    $sql = "SELECT * FROM `conf__paginas` WHERE url = '$url' AND estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

function obtenerFuncionesPorPagina($conexion, $pagina_id) {
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
            FROM `conf__paginas_funciones` pf
            LEFT JOIN `conf__iconos` i ON pf.icono_id = i.icono_id
            LEFT JOIN `conf__colores` c ON pf.color_id = c.color_id
            WHERE pf.pagina_id = $pagina_id 
            ORDER BY pf.estado_registro_origen_id, pf.orden";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerBotonesPorEstado($conexion, $pagina_id, $estado_actual) {
    $funciones = obtenerFuncionesPorPagina($conexion, $pagina_id);
    $botones = [];
    
    foreach ($funciones as $funcion) {
        // Obtener el código estándar del estado origen para comparar
        $codigo_origen = obtenerCodigoEstandarPorEstado($conexion, $funcion['estado_registro_origen_id']);
        
        if ($codigo_origen == $estado_actual) {
            $accion_js = $funcion['accion_js'] ?? strtolower($funcion['nombre_funcion']);
            
            $botones[] = [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $accion_js,
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-outline-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'estado_destino' => $funcion['estado_registro_destino_id'],
                'es_confirmable' => $funcion['es_confirmable'] ?? 0,
                'descripcion' => $funcion['descripcion']
            ];
        }
    }
    
    return $botones;
}

function obtenerBotonAgregar($conexion, $pagina_id) {
    $funciones = obtenerFuncionesPorPagina($conexion, $pagina_id);
    
    foreach ($funciones as $funcion) {
        // Estado origen 0 significa "Agregar nuevo"
        if ($funcion['estado_registro_origen_id'] == 0 && $funcion['nombre_funcion'] == 'Agregar') {
            return [
                'nombre_funcion' => $funcion['nombre_funcion'],
                'accion_js' => $funcion['accion_js'] ?? 'agregar',
                'icono_clase' => $funcion['icono_clase'],
                'color_clase' => $funcion['color_clase'] ?? 'btn-primary',
                'bg_clase' => $funcion['bg_clase'] ?? '',
                'text_clase' => $funcion['text_clase'] ?? '',
                'descripcion' => $funcion['descripcion']
            ];
        }
    }
    
    return null;
}

// ✅ Función para obtener código estándar por estado
function obtenerCodigoEstandarPorEstado($conexion, $estado_registro_id) {
    $estado_registro_id = intval($estado_registro_id);
    $sql = "SELECT codigo_estandar FROM conf__tablas_estados_registros 
            WHERE estado_registro_id = $estado_registro_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Función para obtener estado_registro_id por código estándar
function obtenerEstadoPorCodigoEstandar($conexion, $codigo_estandar) {
    $codigo_estandar = mysqli_real_escape_string($conexion, $codigo_estandar);
    $sql = "SELECT estado_registro_id FROM conf__tablas_estados_registros 
            WHERE codigo_estandar = '$codigo_estandar' AND tabla_id = 26";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['estado_registro_id'] : null;
}

// ✅ Función para ejecutar transición de estado usando códigos estándar
function ejecutarTransicionEstado($conexion, $marca_id, $funcion_nombre, $pagina_id) {
    $marca_id = intval($marca_id);
    
    // Obtener el código estándar actual de la marca
    $codigo_actual = obtenerCodigoEstandarMarca($conexion, $marca_id);
    
    // Obtener la función para saber a qué estado transicionar
    $funciones = obtenerFuncionesPorPagina($conexion, $pagina_id);
    $estado_destino_id = null;
    
    foreach ($funciones as $funcion) {
        $codigo_origen = obtenerCodigoEstandarPorEstado($conexion, $funcion['estado_registro_origen_id']);
        
        if ($codigo_origen == $codigo_actual && $funcion['nombre_funcion'] == $funcion_nombre) {
            $estado_destino_id = $funcion['estado_registro_destino_id'];
            break;
        }
    }
    
    if ($estado_destino_id === null) {
        return ['success' => false, 'error' => 'Transición no permitida'];
    }
    
    // Obtener el código estándar del estado destino
    $codigo_destino = obtenerCodigoEstandarPorEstado($conexion, $estado_destino_id);
    
    // Ejecutar la transición actualizando el código estándar
    $sql = "UPDATE gestion__marcas 
            SET estado_registro_id = ? 
            WHERE marca_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $marca_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'nuevo_estado' => $codigo_destino];
    } else {
        return ['success' => false, 'error' => 'Error en la base de datos'];
    }
}

function obtenerCodigoEstandarMarca($conexion, $marca_id) {
    $marca_id = intval($marca_id);
    $sql = "SELECT er.codigo_estandar 
            FROM gestion__marcas m
            INNER JOIN conf__tablas_estados_registros er ON m.estado_registro_id = er.estado_registro_id
            WHERE m.marca_id = $marca_id AND er.tabla_id = 26";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Obtener etiqueta del estado para mostrar
function obtenerEtiquetaEstado($conexion, $estado_registro_id) {
    $estado_registro_id = intval($estado_registro_id);
    $sql = "SELECT estado_registro, codigo_estandar 
            FROM conf__tablas_estados_registros 
            WHERE estado_registro_id = $estado_registro_id AND tabla_id = 26";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['estado_registro'] : 'Desconocido';
}

// ✅ Funciones CRUD básicas
function obtenerMarcas($conexion, $pagina_id) {
    $sql = "SELECT m.*, er.estado_registro, er.codigo_estandar 
            FROM gestion__marcas m
            INNER JOIN conf__tablas_estados_registros er ON m.estado_registro_id = er.estado_registro_id
            WHERE er.tabla_id = 26
            ORDER BY m.marca_id DESC";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        // Agregar botones disponibles para cada marca según su código estándar
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['codigo_estandar']);
        $data[] = $fila;
    }
    return $data;
}

function agregarMarca($conexion, $data) {
    $marca_nombre = mysqli_real_escape_string($conexion, $data['marca_nombre']);
    
    // Obtener el estado inicial (Borrador) por defecto
    $estado_inicial = obtenerEstadoPorCodigoEstandar($conexion, 'BORRADOR');
    if (!$estado_inicial) {
        $estado_inicial = 51; // Fallback si no existe el código estándar
    }

    $sql = "INSERT INTO gestion__marcas (marca_nombre, estado_registro_id) 
            VALUES ('$marca_nombre', $estado_inicial)";

    return mysqli_query($conexion, $sql);
}

function editarMarca($conexion, $id, $data) {
    $id = intval($id);
    $marca_nombre = mysqli_real_escape_string($conexion, $data['marca_nombre']);

    $sql = "UPDATE gestion__marcas SET
            marca_nombre='$marca_nombre'
            WHERE marca_id=$id";

    return mysqli_query($conexion, $sql);
}

function obtenerMarcaPorId($conexion, $id) {
    $id = intval($id);
    $sql = "SELECT m.*, er.codigo_estandar 
            FROM gestion__marcas m
            INNER JOIN conf__tablas_estados_registros er ON m.estado_registro_id = er.estado_registro_id
            WHERE m.marca_id = $id AND er.tabla_id = 26";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>