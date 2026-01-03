<?php
require_once __DIR__ . '/../../conexion.php';

// ✅ Configuración de la tabla (para gestion__comprobantes_tipos)
$tabla_idx = 36; // Tabla ID para gestion__comprobantes_tipos
$pagina_idx = 45;

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
        'nombre_funcion' => 'Nuevo Tipo',
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
function ejecutarTransicionEstado($conexion, $comprobante_tipo_id, $funcion_nombre, $pagina_id) {
    $comprobante_tipo_id = intval($comprobante_tipo_id);
    
    // Obtener el código estándar actual del tipo de comprobante
    $codigo_actual = obtenerCodigoEstandarComprobanteTipo($conexion, $comprobante_tipo_id);
    
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
    $sql = "UPDATE gestion__comprobantes_tipos 
            SET estado_registro_id = ? 
            WHERE comprobante_tipo_id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $estado_destino_id, $comprobante_tipo_id);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'nuevo_estado' => obtenerCodigoEstandarPorEstado($conexion, $estado_destino_id)];
    } else {
        return ['success' => false, 'error' => 'Error en la base de datos: ' . mysqli_error($conexion)];
    }
}

function obtenerCodigoEstandarComprobanteTipo($conexion, $comprobante_tipo_id) {
    global $tabla_idx;
    $comprobante_tipo_id = intval($comprobante_tipo_id);
    $sql = "SELECT er.codigo_estandar 
            FROM gestion__comprobantes_tipos ct
            INNER JOIN conf__tablas_estados_registros er ON ct.estado_registro_id = er.estado_registro_id
            WHERE ct.comprobante_tipo_id = $comprobante_tipo_id AND er.tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['codigo_estandar'] : null;
}

// ✅ Funciones CRUD básicas para tipos de comprobantes
function obtenerComprobantesTipos($conexion, $empresa_id, $pagina_id) {
    global $tabla_idx;
    $empresa_id = intval($empresa_id);
    
    $sql = "SELECT 
                ct.*,
                cg.comprobante_grupo,
                IFNULL(cf.comprobante_fiscal, 'Sin tipo fiscal') as comprobante_fiscal,
                er.estado_registro,
                er.codigo_estandar
            FROM `gestion__comprobantes_tipos` ct
            LEFT JOIN `gestion__comprobantes_grupos` cg ON ct.comprobante_grupo_id = cg.comprobante_grupo_id
            LEFT JOIN `gestion__comprobantes_fiscales` cf ON ct.comprobante_fiscal_id = cf.comprobante_fiscal_id
            INNER JOIN conf__tablas_estados_registros er ON ct.estado_registro_id = er.estado_registro_id
            WHERE cg.empresa_id = $empresa_id AND er.tabla_id = $tabla_idx
            ORDER BY cg.comprobante_grupo_id, ct.orden, ct.comprobante_tipo";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        // Agregar botones disponibles para cada tipo según su código estándar
        $fila['botones'] = obtenerBotonesPorEstado($conexion, $pagina_id, $fila['codigo_estandar']);
        $data[] = $fila;
    }
    return $data;
}

function obtenerComprobantesGruposActivos($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT * FROM `gestion__comprobantes_grupos` 
            WHERE empresa_id = $empresa_id 
            AND estado_registro_id = 1
            ORDER BY comprobante_grupo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerComprobantesFiscalesActivos($conexion) {
    $sql = "SELECT * FROM `gestion__comprobantes_fiscales` 
            WHERE estado_registro_id = 1
            ORDER BY comprobante_fiscal";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarComprobanteTipo($conexion, $data) {
    global $tabla_idx;
    
    if (empty($data['comprobante_tipo']) || empty($data['codigo']) || empty($data['comprobante_grupo_id'])) {
        return false;
    }
    
    $comprobante_tipo = mysqli_real_escape_string($conexion, $data['comprobante_tipo']);
    $codigo = mysqli_real_escape_string($conexion, $data['codigo']);
    $letra = mysqli_real_escape_string($conexion, $data['letra'] ?? '');
    $comentario = mysqli_real_escape_string($conexion, $data['comentario'] ?? '');
    $comprobante_grupo_id = intval($data['comprobante_grupo_id']);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id']);
    $orden = intval($data['orden'] ?? 0);
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $signo = mysqli_real_escape_string($conexion, $data['signo'] ?? '+');

    // Obtener el estado inicial (Confirmado - código 20)
    $estado_inicial = obtenerEstadoPorCodigoEstandar($conexion, '20');
    if (!$estado_inicial) {
        // Fallback: buscar cualquier estado activo para esta tabla
        $sql_estado = "SELECT estado_registro_id FROM conf__tablas_estados_registros WHERE tabla_id = $tabla_idx LIMIT 1";
        $res_estado = mysqli_query($conexion, $sql_estado);
        $fila_estado = mysqli_fetch_assoc($res_estado);
        $estado_inicial = $fila_estado ? $fila_estado['estado_registro_id'] : 1;
    }

    // Verificar si ya existe el tipo para este grupo
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_tipos` 
                  WHERE comprobante_tipo = '$comprobante_tipo' 
                  AND comprobante_grupo_id = $comprobante_grupo_id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe > 0) {
        return false; // Ya existe este tipo para este grupo
    }

    $sql = "INSERT INTO `gestion__comprobantes_tipos` 
            (comprobante_grupo_id, comprobante_fiscal_id, orden, impacta_stock, impacta_contabilidad, 
             impacta_ctacte, comprobante_tipo, codigo, letra, signo, comentario, estado_registro_id) 
            VALUES 
            ($comprobante_grupo_id, $comprobante_fiscal_id, $orden, $impacta_stock, $impacta_contabilidad,
             $impacta_ctacte, '$comprobante_tipo', '$codigo', '$letra', '$signo', '$comentario', $estado_inicial)";
    
    return mysqli_query($conexion, $sql);
}

function editarComprobanteTipo($conexion, $id, $data) {
    if (empty($data['comprobante_tipo']) || empty($data['codigo']) || empty($data['comprobante_grupo_id'])) {
        return false;
    }
    
    $id = intval($id);
    $comprobante_tipo = mysqli_real_escape_string($conexion, $data['comprobante_tipo']);
    $codigo = mysqli_real_escape_string($conexion, $data['codigo']);
    $letra = mysqli_real_escape_string($conexion, $data['letra'] ?? '');
    $comentario = mysqli_real_escape_string($conexion, $data['comentario'] ?? '');
    $comprobante_grupo_id = intval($data['comprobante_grupo_id']);
    $comprobante_fiscal_id = intval($data['comprobante_fiscal_id']);
    $orden = intval($data['orden'] ?? 0);
    $impacta_stock = intval($data['impacta_stock'] ?? 0);
    $impacta_contabilidad = intval($data['impacta_contabilidad'] ?? 0);
    $impacta_ctacte = intval($data['impacta_ctacte'] ?? 0);
    $signo = mysqli_real_escape_string($conexion, $data['signo'] ?? '+');

    // Verificar si ya existe el tipo (excluyendo el registro actual)
    $sql_check = "SELECT COUNT(*) as existe FROM `gestion__comprobantes_tipos` 
                  WHERE comprobante_tipo = '$comprobante_tipo' 
                  AND comprobante_grupo_id = $comprobante_grupo_id
                  AND comprobante_tipo_id != $id";
    $res_check = mysqli_query($conexion, $sql_check);
    $existe = mysqli_fetch_assoc($res_check)['existe'];
    
    if ($existe > 0) {
        return false; // Ya existe este tipo para este grupo
    }

    $sql = "UPDATE `gestion__comprobantes_tipos` SET
            comprobante_grupo_id = $comprobante_grupo_id,
            comprobante_fiscal_id = $comprobante_fiscal_id,
            orden = $orden,
            impacta_stock = $impacta_stock,
            impacta_contabilidad = $impacta_contabilidad,
            impacta_ctacte = $impacta_ctacte,
            comprobante_tipo = '$comprobante_tipo',
            codigo = '$codigo',
            letra = '$letra',
            signo = '$signo',
            comentario = '$comentario'
            WHERE comprobante_tipo_id = $id";

    return mysqli_query($conexion, $sql);
}

function cambiarEstadoComprobanteTipo($conexion, $id, $nuevo_estado) {
    $id = intval($id);
    $nuevo_estado = intval($nuevo_estado);
    
    $sql = "UPDATE `gestion__comprobantes_tipos` 
            SET estado_registro_id = $nuevo_estado 
            WHERE comprobante_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function eliminarComprobanteTipo($conexion, $id) {
    $id = intval($id);
    
    // Verificar si hay comprobantes asociados (dependiendo de tu estructura)
    // $sql_check = "SELECT COUNT(*) as tiene_comprobantes FROM `gestion__comprobantes` 
    //               WHERE comprobante_tipo_id = $id";
    // $res_check = mysqli_query($conexion, $sql_check);
    // $tiene_comprobantes = mysqli_fetch_assoc($res_check)['tiene_comprobantes'];
    
    // if ($tiene_comprobantes > 0) {
    //     return false; // No se puede eliminar porque tiene comprobantes asociados
    // }
    
    $sql = "DELETE FROM `gestion__comprobantes_tipos` 
            WHERE comprobante_tipo_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerComprobanteTipoPorId($conexion, $id) {
    global $tabla_idx;
    $id = intval($id);
    $sql = "SELECT ct.*, er.codigo_estandar 
            FROM `gestion__comprobantes_tipos` ct
            INNER JOIN conf__tablas_estados_registros er ON ct.estado_registro_id = er.estado_registro_id
            WHERE ct.comprobante_tipo_id = $id AND er.tabla_id = $tabla_idx";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>