<?php
require_once "conexion.php";

function obtenerMarcas($conexion, $empresa_id = 1) {
    $empresa_id = intval($empresa_id);
    
    $sql = "SELECT * FROM gestion__marcas 
            WHERE empresa_id = $empresa_id 
            ORDER BY marca_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    return $data;
}

function obtenerEmpresaPorId($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    
    $sql = "SELECT empresa_id, empresa FROM conf__empresas 
            WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1";
    
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    
    return null;
}

// NUEVA FUNCIÓN: Obtener funciones de página según estado
function obtenerFuncionesPagina($conexion, $pagina_id, $estado_registro_id) {
    $pagina_id = intval($pagina_id);
    $estado_registro_id = intval($estado_registro_id);
    
    $sql = "SELECT pf.*, 
                   i.nombre_icono,
                   c.color_nombre,
                   c.color_hex,
                   fe.nombre_funcion as funcion_estandar_nombre
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            LEFT JOIN conf__funciones_estandar fe ON pf.funcion_estandar_id = fe.funcion_estandar_id
            WHERE pf.pagina_id = $pagina_id 
                        
            ORDER BY pf.orden ASC";
    
    $res = mysqli_query($conexion, $sql);
    $funciones = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $funciones[] = $fila;
    }
    
    return $funciones;
}

// NUEVA FUNCIÓN: Obtener todos los estados disponibles
function obtenerEstadosRegistros($conexion) {
    $sql = "SELECT * FROM conf__estados_registros ORDER BY orden_estandar";
    
    $res = mysqli_query($conexion, $sql);
    $estados = [];
    
    while ($fila = mysqli_fetch_assoc($res)) {
        $estados[$fila['estado_registro_id']] = $fila;
    }
    
    return $estados;
}

function existeMarca($conexion, $empresa_id, $marca_nombre) {
    $empresa_id = intval($empresa_id);
    $marca_nombre = mysqli_real_escape_string($conexion, trim($marca_nombre));
    
    $sql = "SELECT marca_id FROM gestion__marcas 
            WHERE empresa_id = $empresa_id 
            AND LOWER(marca_nombre) = LOWER('$marca_nombre')";
    
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function existeMarcaEditando($conexion, $empresa_id, $marca_nombre, $excluir_id) {
    $empresa_id = intval($empresa_id);
    $marca_nombre = mysqli_real_escape_string($conexion, trim($marca_nombre));
    $excluir_id = intval($excluir_id);
    
    $sql = "SELECT marca_id FROM gestion__marcas 
            WHERE empresa_id = $empresa_id 
            AND LOWER(marca_nombre) = LOWER('$marca_nombre')
            AND marca_id != $excluir_id";
    
    $res = mysqli_query($conexion, $sql);
    return mysqli_num_rows($res) > 0;
}

function agregarMarca($conexion, $data) {
    if (empty($data['marca_nombre'])) {
        return false;
    }
    
    $empresa_id = intval($data['empresa_id']);
    $marca_nombre = mysqli_real_escape_string($conexion, trim($data['marca_nombre']));
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO gestion__marcas (empresa_id, marca_nombre, tabla_estado_registro_id) 
            VALUES ($empresa_id, '$marca_nombre', $tabla_estado_registro_id)";
    
    return mysqli_query($conexion, $sql);
}

function editarMarca($conexion, $id, $data) {
    if (empty($data['marca_nombre'])) {
        return false;
    }
    
    $id = intval($id);
    $empresa_id = intval($data['empresa_id']);
    $marca_nombre = mysqli_real_escape_string($conexion, trim($data['marca_nombre']));
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE gestion__marcas SET
            empresa_id = $empresa_id,
            marca_nombre = '$marca_nombre',
            tabla_estado_registro_id = $tabla_estado_registro_id
            WHERE marca_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoMarca($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE gestion__marcas SET 
            tabla_estado_registro_id = $nuevo_estado 
            WHERE marca_id = $id";
    
    return mysqli_query($conexion, $sql);
}

function obtenerMarcaPorId($conexion, $id) {
    $id = intval($id);
    
    $sql = "SELECT * FROM gestion__marcas WHERE marca_id = $id";
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    
    return null;
}

// NUEVA FUNCIÓN: Ejecutar acción específica según función estándar
function ejecutarAccionMarca($conexion, $marca_id, $funcion_estandar_id, $nuevo_estado = null) {
    $marca_id = intval($marca_id);
    $funcion_estandar_id = intval($funcion_estandar_id);
    
    // Obtener información de la función estándar
    $sql_funcion = "SELECT nombre_funcion, accion_sql FROM conf__funciones_estandar 
                    WHERE funcion_estandar_id = $funcion_estandar_id";
    $res_funcion = mysqli_query($conexion, $sql_funcion);
    
    if (!$res_funcion || mysqli_num_rows($res_funcion) == 0) {
        return false;
    }
    
    $funcion = mysqli_fetch_assoc($res_funcion);
    
    // Ejecutar acción según el tipo de función
    switch ($funcion_estandar_id) {
        case 1: // Editar - no hace cambio de estado
            return true; // Solo retorna true para permitir la edición
        
        case 2: // Activar/Desactivar (Toggle)
            if ($nuevo_estado !== null) {
                return cambiarEstadoMarca($conexion, $marca_id, $nuevo_estado);
            }
            break;
            
        case 3: // Eliminar (baja lógica)
            $sql = "UPDATE gestion__marcas SET tabla_estado_registro_id = 0 WHERE marca_id = $marca_id";
            return mysqli_query($conexion, $sql);
            
        case 4: // Restaurar
            $sql = "UPDATE gestion__marcas SET tabla_estado_registro_id = 1 WHERE marca_id = $marca_id";
            return mysqli_query($conexion, $sql);
            
        default:
            // Para funciones personalizadas, ejecutar el SQL definido
            if (!empty($funcion['accion_sql'])) {
                $sql = str_replace('{marca_id}', $marca_id, $funcion['accion_sql']);
                return mysqli_query($conexion, $sql);
            }
            break;
    }
    
    return false;
}
?>