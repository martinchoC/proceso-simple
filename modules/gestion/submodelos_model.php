<?php
require_once "conexion.php";

// ✅ Configuración de la tabla (para gestion__submodelos)
$tabla_idx = 33; // Tabla ID para gestion__submodelos
$pagina_idx = 42;

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
    
    // Botón por defecto si no hay configuración
    return [
        'nombre_funcion' => 'Agregar Submodelo',
        'accion_js' => 'agregar',
        'icono_clase' => 'fas fa-plus',
        'color_clase' => 'btn-primary',
        'bg_clase' => 'btn-primary',
        'text_clase' => 'text-white'
    ];
}

// ✅ Función para obtener código estándar por estado
function obtenerCodigoEstandarPorEstado($conexion, $estado_registro_id) {
    global $tabla_idx;
    $estado_registro_id = intval($estado_registro_id);
    
    if ($estado_registro_id == 0) return '0'; // Para botón agregar
    
    $sql = "SELECT codigo_estandar FROM conf__tablas_estados_registros 
            WHERE estado_registro_id = $estado_registro_id AND tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Función para obtener estado_registro_id por código estándar
function obtenerEstadoPorCodigoEstandar($conexion, $codigo_estandar) {
    global $tabla_idx;
    $codigo_estandar = mysqli_real_escape_string($conexion, $codigo_estandar);
    $sql = "SELECT estado_registro_id FROM conf__tablas_estados_registros 
            WHERE codigo_estandar = '$codigo_estandar' AND tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['estado_registro_id'] : null;
}

// ✅ Función para ejecutar transición de estado usando códigos estándar
function ejecutarTransicionEstado($conexion, $submodelo_id, $funcion_nombre, $pagina_id) {
    $submodelo_id = intval($submodelo_id);
    
    // Obtener el código estándar actual del submodelo
    $codigo_actual = obtenerCodigoEstandarSubmodelo($conexion, $submodelo_id);
    
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
    
    // Ejecutar la transición actualizando el estado
    $sql = "UPDATE gestion__submodelos 
            SET estado_registro_id = ? 
            WHERE submodelo_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $submodelo_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'nuevo_estado' => obtenerCodigoEstandarPorEstado($conexion, $estado_destino_id)];
    } else {
        return ['success' => false, 'error' => 'Error en la base de datos: ' . mysqli_error($conexion)];
    }
}

function obtenerCodigoEstandarSubmodelo($conexion, $submodelo_id) {
    global $tabla_idx;
    $submodelo_id = intval($submodelo_id);
    $sql = "SELECT er.codigo_estandar 
            FROM gestion__submodelos s
            INNER JOIN conf__tablas_estados_registros er ON s.estado_registro_id = er.estado_registro_id
            WHERE s.submodelo_id = $submodelo_id AND er.tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Funciones CRUD básicas para submodelos
function obtenerSubmodelos($conexion, $pagina_id) {
    global $tabla_idx;
    $sql = "SELECT s.*, m.modelo_nombre, ma.marca_nombre, ma.marca_id, er.estado_registro, er.codigo_estandar 
            FROM gestion__submodelos s 
            INNER JOIN gestion__modelos m ON s.modelo_id = m.modelo_id 
            INNER JOIN gestion__marcas ma ON m.marca_id = ma.marca_id 
            INNER JOIN conf__tablas_estados_registros er ON s.estado_registro_id = er.estado_registro_id
            WHERE er.tabla_id = $tabla_idx
            ORDER BY s.submodelo_id DESC";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        // Agregar botones disponibles para cada submodelo según su código estándar
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['codigo_estandar']);
        $data[] = $fila;
    }
    return $data;
}

function obtenerMarcas($conexion) {
    // CORREGIDO: Consulta simplificada
    $sql = "SELECT marca_id, marca_nombre 
            FROM gestion__marcas 
            ORDER BY marca_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModelosPorMarca($conexion, $marca_id) {
    $marca_id = intval($marca_id);
    
    // CORREGIDO: Consulta simplificada y correcta
    $sql = "SELECT modelo_id, modelo_nombre 
            FROM gestion__modelos 
            WHERE marca_id = $marca_id 
            ORDER BY modelo_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarSubmodelo($conexion, $data) {
    global $tabla_idx;
    $modelo_id = intval($data['modelo_id']);
    $submodelo_nombre = mysqli_real_escape_string($conexion, $data['submodelo_nombre']);
    
    // Obtener el estado inicial (Confirmado - código 20)
    $estado_inicial = obtenerEstadoPorCodigoEstandar($conexion, '20');
    if (!$estado_inicial) {
        // Fallback: buscar cualquier estado activo para esta tabla
        $sql_estado = "SELECT estado_registro_id FROM conf__tablas_estados_registros WHERE tabla_id = $tabla_idx LIMIT 1";
        $res_estado = mysqli_query($conexion, $sql_estado);
        $fila_estado = mysqli_fetch_assoc($res_estado);
        $estado_inicial = $fila_estado ? $fila_estado['estado_registro_id'] : 1;
    }

    $sql = "INSERT INTO gestion__submodelos (modelo_id, submodelo_nombre, estado_registro_id) 
            VALUES ($modelo_id, '$submodelo_nombre', $estado_inicial)";

    return mysqli_query($conexion, $sql);
}

function editarSubmodelo($conexion, $id, $data) {
    $id = intval($id);
    $modelo_id = intval($data['modelo_id']);
    $submodelo_nombre = mysqli_real_escape_string($conexion, $data['submodelo_nombre']);

    $sql = "UPDATE gestion__submodelos SET
            modelo_id=$modelo_id,
            submodelo_nombre='$submodelo_nombre'
            WHERE submodelo_id=$id";

    return mysqli_query($conexion, $sql);
}

function obtenerSubmodeloPorId($conexion, $id) {
    global $tabla_idx;
    $id = intval($id);
    
    // CORREGIDO: Incluir marca_id en la consulta
    $sql = "SELECT s.*, m.marca_id, er.codigo_estandar 
            FROM gestion__submodelos s
            INNER JOIN gestion__modelos m ON s.modelo_id = m.modelo_id
            INNER JOIN conf__tablas_estados_registros er ON s.estado_registro_id = er.estado_registro_id
            WHERE s.submodelo_id = $id AND er.tabla_id = $tabla_idx";
    
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>