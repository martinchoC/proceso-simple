<?php
require_once __DIR__ . '/../../conexion.php';

function obtenerModulos($conexion) {
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresas($conexion) {
    $sql = "SELECT empresa_id, empresa as empresa_nombre FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresasPerfilesPorModulo($conexion, $modulo_id) {
    $modulo_condition = "";
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $modulo_condition = "AND ep.modulo_id = $modulo_id";
    }
    
    $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, 
                   e.empresa_id, e.empresa as empresa_nombre, 
                   m.modulo_id, m.modulo, 
                   p.perfil_id as perfil_id_base, p.perfil_nombre as perfil_base
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.tabla_estado_registro_id = 1 $modulo_condition 
            ORDER BY e.empresa, ep.empresa_perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEmpresasPerfilesPorModuloYEmpresa($conexion, $modulo_id, $empresa_id) {
    $modulo_condition = "";
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $modulo_condition = "AND ep.modulo_id = $modulo_id";
    }
    
    $empresa_condition = "";
    if ($empresa_id) {
        $empresa_id = intval($empresa_id);
        $empresa_condition = "AND ep.empresa_id = $empresa_id";
    }
    
    $sql = "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre, 
                   e.empresa_id, e.empresa as empresa_nombre, 
                   m.modulo_id, m.modulo, 
                   p.perfil_id as perfil_id_base, p.perfil_nombre as perfil_base
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.tabla_estado_registro_id = 1 
            $modulo_condition $empresa_condition
            ORDER BY e.empresa, ep.empresa_perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPaginasFuncionesPorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    
    // CONSULTA CORREGIDA: Usar los nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   pf.orden as funcion_orden
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesPorModulo: " . mysqli_error($conexion));
        error_log("Consulta: " . $sql);
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    error_log("Paginas funciones obtenidas para modulo $modulo_id: " . count($data));
    
    return $data;
}

function obtenerPaginasFuncionesPorEmpresaPerfil($conexion, $empresa_perfil_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    // Primero obtener el módulo del perfil
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;
    
    if (!$modulo_id) {
        error_log("No se encontró módulo para empresa_perfil_id: $empresa_perfil_id");
        return [];
    }
    
    // CONSULTA CORREGIDA: Usar nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   epf.empresa_perfil_funcion_id, epf.asignado,
                   CASE WHEN epf.empresa_perfil_funcion_id IS NOT NULL THEN 1 ELSE 0 END as asignada
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__empresas_perfiles_funciones epf ON pf.pagina_funcion_id = epf.pagina_funcion_id 
                AND epf.empresa_perfil_id = $empresa_perfil_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesPorEmpresaPerfil: " . mysqli_error($conexion));
        error_log("Consulta: " . $sql);
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    error_log("Paginas funciones para empresa_perfil_id $empresa_perfil_id: " . count($data));
    
    return $data;
}

function obtenerPaginasFuncionesDisponiblesPorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    
    // CONSULTA CORREGIDA: Usar nombres correctos de campos
    $sql = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, pf.accion_js,
                   p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, p.padre_id, p.orden as pagina_orden,
                   m.modulo_id, m.modulo,
                   pf.orden as funcion_orden
            FROM conf__paginas_funciones pf
            INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE m.modulo_id = $modulo_id 
            AND pf.tabla_estado_registro_id = 1
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden, pf.orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error SQL en obtenerPaginasFuncionesDisponiblesPorModulo: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    return $data;
}

// NUEVA FUNCIÓN: Obtener estructura jerárquica de páginas
function obtenerEstructuraPaginasPorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    
    // Obtener todas las páginas del módulo
    $sql = "SELECT p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, 
                   p.padre_id, p.orden, p.pagina_descripcion,
                   m.modulo
            FROM conf__paginas p
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE p.modulo_id = $modulo_id 
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.padre_id, p.orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error SQL en obtenerEstructuraPaginasPorModulo: " . mysqli_error($conexion));
        return [];
    }
    
    $paginas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $paginas[] = $fila;
    }
    
    // Obtener funciones para cada página
    foreach ($paginas as &$pagina) {
        $pagina_id = $pagina['pagina_id'];
        $sql_funciones = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, 
                                 pf.accion_js, pf.orden as funcion_orden
                          FROM conf__paginas_funciones pf
                          WHERE pf.pagina_id = $pagina_id 
                          AND pf.tabla_estado_registro_id = 1
                          ORDER BY pf.orden";
        
        $res_funciones = mysqli_query($conexion, $sql_funciones);
        $funciones = [];
        while ($funcion = mysqli_fetch_assoc($res_funciones)) {
            $funciones[] = $funcion;
        }
        $pagina['funciones'] = $funciones;
    }
    
    return $paginas;
}

// NUEVA FUNCIÓN: Obtener estructura completa con asignaciones
function obtenerEstructuraCompletaPorEmpresaPerfil($conexion, $empresa_perfil_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    // Obtener módulo del perfil
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;
    
    if (!$modulo_id) {
        return [];
    }
    
    // Obtener páginas del módulo
    $sql_paginas = "SELECT p.pagina_id, p.pagina as pagina_nombre, p.url as ruta, 
                           p.padre_id, p.orden as pagina_orden, p.pagina_descripcion
                    FROM conf__paginas p
                    WHERE p.modulo_id = $modulo_id 
                    AND p.tabla_estado_registro_id = 1
                    ORDER BY p.padre_id, p.orden";
    
    $res_paginas = mysqli_query($conexion, $sql_paginas);
    $paginas = [];
    
    while ($pagina = mysqli_fetch_assoc($res_paginas)) {
        $pagina_id = $pagina['pagina_id'];
        
        // Obtener funciones de esta página
        $sql_funciones = "SELECT pf.pagina_funcion_id, pf.nombre_funcion, pf.descripcion, 
                                 pf.accion_js, pf.orden as funcion_orden
                          FROM conf__paginas_funciones pf
                          WHERE pf.pagina_id = $pagina_id 
                          AND pf.tabla_estado_registro_id = 1
                          ORDER BY pf.orden";
        
        $res_funciones = mysqli_query($conexion, $sql_funciones);
        $funciones = [];
        
        while ($funcion = mysqli_fetch_assoc($res_funciones)) {
            $pagina_funcion_id = $funcion['pagina_funcion_id'];
            
            // Verificar si está asignada
            $sql_asignada = "SELECT empresa_perfil_funcion_id, asignado 
                             FROM conf__empresas_perfiles_funciones 
                             WHERE empresa_perfil_id = $empresa_perfil_id 
                             AND pagina_funcion_id = $pagina_funcion_id";
            
            $res_asignada = mysqli_query($conexion, $sql_asignada);
            $asignada = mysqli_fetch_assoc($res_asignada);
            
            $funcion['asignada'] = $asignada ? 1 : 0;
            $funcion['empresa_perfil_funcion_id'] = $asignada ? $asignada['empresa_perfil_funcion_id'] : null;
            $funcion['asignado'] = $asignada ? $asignada['asignado'] : 0;
            
            $funciones[] = $funcion;
        }
        
        $pagina['funciones'] = $funciones;
        $paginas[] = $pagina;
    }
    
    return $paginas;
}

// FUNCIONES RESTANTES (sin cambios)
function asignarPaginaFuncionAEmpresaPerfil($conexion, $empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado, $usuario_creacion) {
    $empresa_id = intval($empresa_id);
    $empresa_perfil_id = intval($empresa_perfil_id);
    $pagina_funcion_id = intval($pagina_funcion_id);
    $usuario_creacion = intval($usuario_creacion);
    $asignado = intval($asignado);
    
    error_log("Asignando: empresa_id=$empresa_id, empresa_perfil_id=$empresa_perfil_id, pagina_funcion_id=$pagina_funcion_id, asignado=$asignado");
    
    // Primero verificar si ya existe la asignación
    $sql_check = "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones 
                  WHERE empresa_perfil_id = $empresa_perfil_id 
                  AND pagina_funcion_id = $pagina_funcion_id";
    $res_check = mysqli_query($conexion, $sql_check);
    
    if (mysqli_num_rows($res_check) > 0) {
        // Actualizar si ya existe
        $sql = "UPDATE conf__empresas_perfiles_funciones 
                SET asignado = $asignado, 
                    fecha_asignacion = NOW()
                WHERE empresa_perfil_id = $empresa_perfil_id 
                AND pagina_funcion_id = $pagina_funcion_id";
    } else {
        // Insertar si no existe
        $sql = "INSERT INTO conf__empresas_perfiles_funciones 
                (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) 
                VALUES ($empresa_id, $empresa_perfil_id, $pagina_funcion_id, $asignado)";
    }
    
    error_log("SQL: " . $sql);
    $resultado = mysqli_query($conexion, $sql);
    
    if (!$resultado) {
        error_log("Error en asignarPaginaFuncionAEmpresaPerfil: " . mysqli_error($conexion));
    }
    
    return $resultado;
}

function eliminarPaginaFuncionDeEmpresaPerfil($conexion, $empresa_perfil_funcion_id) {
    $empresa_perfil_funcion_id = intval($empresa_perfil_funcion_id);
    
    error_log("Eliminando empresa_perfil_funcion_id: $empresa_perfil_funcion_id");
    
    $sql = "DELETE FROM conf__empresas_perfiles_funciones 
            WHERE empresa_perfil_funcion_id = $empresa_perfil_funcion_id";
    
    $resultado = mysqli_query($conexion, $sql);
    
    if (!$resultado) {
        error_log("Error en eliminarPaginaFuncionDeEmpresaPerfil: " . mysqli_error($conexion));
    }
    
    return $resultado;
}

function actualizarAsignacionPaginaFuncion($conexion, $empresa_perfil_funcion_id, $asignado) {
    $empresa_perfil_funcion_id = intval($empresa_perfil_funcion_id);
    $asignado = intval($asignado);
    
    $sql = "UPDATE conf__empresas_perfiles_funciones 
            SET asignado = $asignado, 
                fecha_asignacion = NOW()
            WHERE empresa_perfil_funcion_id = $empresa_perfil_funcion_id";
    
    return mysqli_query($conexion, $sql);
}

function obtenerDetalleEmpresaPerfil($conexion, $empresa_perfil_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    $sql = "SELECT ep.*, e.empresa as empresa_nombre, m.modulo, p.perfil_nombre as perfil_base_nombre
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.empresa_perfil_id = $empresa_perfil_id";
    
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    
    return null;
}

function obtenerDetalleEmpresaPerfilCompleto($conexion, $empresa_perfil_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    // Obtener empresa_id del perfil para usarlo en las asignaciones
    $sql_perfil = "SELECT empresa_id, modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_perfil = mysqli_query($conexion, $sql_perfil);
    $perfil = mysqli_fetch_assoc($res_perfil);
    $empresa_id = $perfil ? $perfil['empresa_id'] : 0;
    $modulo_id = $perfil ? $perfil['modulo_id'] : 0;
    
    $sql = "SELECT ep.*, e.empresa as empresa_nombre, m.modulo, p.perfil_nombre as perfil_base_nombre
            FROM conf__empresas_perfiles ep
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.empresa_perfil_id = $empresa_perfil_id";
    
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $detalle = mysqli_fetch_assoc($res);
        $detalle['empresa_id_actual'] = $empresa_id;
        $detalle['modulo_id_actual'] = $modulo_id;
        return $detalle;
    }
    
    return null;
}

function heredarPaginasFuncionesDesdePerfilBase($conexion, $empresa_perfil_id, $perfil_id_base, $empresa_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    $perfil_id_base = intval($perfil_id_base);
    $empresa_id = intval($empresa_id);
    
    error_log("Heredando funciones: empresa_perfil_id=$empresa_perfil_id, empresa_id=$empresa_id");
    
    // Obtener módulo del perfil de empresa
    $sql_modulo = "SELECT modulo_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res_modulo = mysqli_query($conexion, $sql_modulo);
    $modulo = mysqli_fetch_assoc($res_modulo);
    $modulo_id = $modulo ? $modulo['modulo_id'] : 0;
    
    if (!$modulo_id) {
        error_log("No se encontró módulo para heredar funciones");
        return 0;
    }
    
    // Obtener todas las páginas y funciones del módulo
    $sql_funciones = "SELECT pf.pagina_funcion_id 
                     FROM conf__paginas_funciones pf
                     INNER JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
                     WHERE p.modulo_id = $modulo_id 
                     AND pf.tabla_estado_registro_id = 1
                     AND p.tabla_estado_registro_id = 1";
    
    $res_funciones = mysqli_query($conexion, $sql_funciones);
    
    if (!$res_funciones) {
        error_log("Error al obtener funciones del módulo: " . mysqli_error($conexion));
        return 0;
    }
    
    $contador = 0;
    $total_funciones = mysqli_num_rows($res_funciones);
    error_log("Total funciones encontradas en módulo $modulo_id: $total_funciones");
    
    while ($funcion = mysqli_fetch_assoc($res_funciones)) {
        $pagina_funcion_id = $funcion['pagina_funcion_id'];
        
        // Verificar si ya existe la asignación
        $sql_check = "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones 
                      WHERE empresa_perfil_id = $empresa_perfil_id 
                      AND pagina_funcion_id = $pagina_funcion_id";
        $res_check = mysqli_query($conexion, $sql_check);
        
        if (mysqli_num_rows($res_check) == 0) {
            // Insertar función heredada (asignado por defecto = 1 para permitir)
            $sql_insert = "INSERT INTO conf__empresas_perfiles_funciones 
                          (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) 
                          VALUES ($empresa_id, $empresa_perfil_id, $pagina_funcion_id, 1)";
            
            if (mysqli_query($conexion, $sql_insert)) {
                $contador++;
            } else {
                error_log("Error al insertar función $pagina_funcion_id: " . mysqli_error($conexion));
            }
        }
    }
    
    error_log("Funciones heredadas: $contador de $total_funciones");
    
    return $contador;
}

function obtenerEmpresaIdPorPerfil($conexion, $empresa_perfil_id) {
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    $sql = "SELECT empresa_id FROM conf__empresas_perfiles WHERE empresa_perfil_id = $empresa_perfil_id";
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['empresa_id'];
    }
    
    return 0;
}
?>