<?php
require_once "conexion.php";

// ✅ Configuración de la tabla (para gestion__comprobantes_fiscales)
$tabla_idx = 44; // Tabla ID para gestion__comprobantes_fiscales
$pagina_idx = 49;

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
        'nombre_funcion' => 'Nuevo Comprobante',
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
function ejecutarTransicionEstado($conexion, $comprobante_fiscal_id, $funcion_nombre, $pagina_id) {
    $comprobante_fiscal_id = intval($comprobante_fiscal_id);
    
    // Obtener el código estándar actual del comprobante fiscal
    $codigo_actual = obtenerCodigoEstandarComprobanteFiscal($conexion, $comprobante_fiscal_id);
    
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
    $sql = "UPDATE gestion__comprobantes_fiscales 
            SET estado_registro_id = ? 
            WHERE comprobante_fiscal_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $comprobante_fiscal_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'nuevo_estado' => obtenerCodigoEstandarPorEstado($conexion, $estado_destino_id)];
    } else {
        return ['success' => false, 'error' => 'Error en la base de datos: ' . mysqli_error($conexion)];
    }
}

function obtenerCodigoEstandarComprobanteFiscal($conexion, $comprobante_fiscal_id) {
    global $tabla_idx;
    $comprobante_fiscal_id = intval($comprobante_fiscal_id);
    $sql = "SELECT er.codigo_estandar 
            FROM gestion__comprobantes_fiscales c
            INNER JOIN conf__tablas_estados_registros er ON c.estado_registro_id = er.estado_registro_id
            WHERE c.comprobante_fiscal_id = $comprobante_fiscal_id AND er.tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Funciones CRUD básicas para comprobantes fiscales
function obtenerComprobantesFiscales($conexion, $pagina_id) {
    global $tabla_idx;
    $sql = "SELECT c.*, er.estado_registro, er.codigo_estandar 
            FROM gestion__comprobantes_fiscales c 
            LEFT JOIN conf__tablas_estados_registros er ON c.estado_registro_id = er.estado_registro_id
            WHERE er.tabla_id = $tabla_idx
            ORDER BY c.codigo";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        // Agregar botones disponibles para cada comprobante según su código estándar
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['codigo_estandar']);
        $data[] = $fila;
    }
    return $data;
}

function agregarComprobanteFiscal($conexion, $data) {
    global $tabla_idx;
    $codigo = intval($data['codigo']);
    $comprobante_fiscal = mysqli_real_escape_string($conexion, $data['comprobante_fiscal']);

    // Obtener el estado inicial (Confirmado - código 20)
    $estado_inicial = obtenerEstadoPorCodigoEstandar($conexion, '20');
    if (!$estado_inicial) {
        // Fallback: buscar cualquier estado activo para esta tabla
        $sql_estado = "SELECT estado_registro_id FROM conf__tablas_estados_registros WHERE tabla_id = $tabla_idx LIMIT 1";
        $res_estado = mysqli_query($conexion, $sql_estado);
        $fila_estado = mysqli_fetch_assoc($res_estado);
        $estado_inicial = $fila_estado ? $fila_estado['estado_registro_id'] : 1;
    }

    // Verificar si ya existe el código
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_fiscales` 
                  WHERE codigo = $codigo";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe_codigo = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe_codigo > 0) {
        return false; // Ya existe este código
    }

    // Verificar si ya existe el nombre
    $sql_check_nombre = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_fiscales` 
                         WHERE comprobante_fiscal = '$comprobante_fiscal'";
    $res_check_nombre = mysqli_query($conexion, $sql_check_nombre);
    $existe_nombre = mysqli_fetch_assoc($res_check_nombre)['existe'];
    
    if ($existe_nombre > 0) {
        return false; // Ya existe este nombre
    }

    $sql = "INSERT INTO gestion__comprobantes_fiscales 
            (codigo, comprobante_fiscal, estado_registro_id) 
            VALUES 
            ($codigo, '$comprobante_fiscal', $estado_inicial)";
    
    return mysqli_query($conexion, $sql);
}

function editarComprobanteFiscal($conexion, $id, $data) {
    $id = intval($id);
    $codigo = intval($data['codigo']);
    $comprobante_fiscal = mysqli_real_escape_string($conexion, $data['comprobante_fiscal']);

    // Verificar si ya existe el código (excluyendo el registro actual)
    $sql_check_codigo = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_fiscales` 
                         WHERE codigo = $codigo 
                         AND comprobante_fiscal_id != $id";
    $res_check_codigo = mysqli_query($conexion, $sql_check_codigo);
    $existe_codigo = mysqli_fetch_assoc($res_check_codigo)['existe'];
    
    if ($existe_codigo > 0) {
        return false; // Ya existe este código
    }

    // Verificar si ya existe el nombre (excluyendo el registro actual)
    $sql_check_nombre = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_fiscales` 
                         WHERE comprobante_fiscal = '$comprobante_fiscal'
                         AND comprobante_fiscal_id != $id";
    $res_check_nombre = mysqli_query($conexion, $sql_check_nombre);
    $existe_nombre = mysqli_fetch_assoc($res_check_nombre)['existe'];
    
    if ($existe_nombre > 0) {
        return false; // Ya existe este nombre
    }

    $sql = "UPDATE gestion__comprobantes_fiscales SET
            codigo = $codigo,
            comprobante_fiscal = '$comprobante_fiscal'
            WHERE comprobante_fiscal_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoComprobanteFiscal($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__comprobantes_fiscales SET estado_registro_id = $nuevo_estado WHERE comprobante_fiscal_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarComprobanteFiscal($conexion, $id) {
    $id = intval($id);
    
    $sql = "DELETE FROM gestion__comprobantes_fiscales WHERE comprobante_fiscal_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerComprobanteFiscalPorId($conexion, $id) {
    global $tabla_idx;
    $id = intval($id);
    $sql = "SELECT c.*, er.codigo_estandar 
            FROM gestion__comprobantes_fiscales c
            INNER JOIN conf__tablas_estados_registros er ON c.estado_registro_id = er.estado_registro_id
            WHERE c.comprobante_fiscal_id = $id AND er.tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>