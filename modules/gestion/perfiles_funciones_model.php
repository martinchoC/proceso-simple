<?php
require_once "conexion.php";

function obtenerPerfiles($conexion) {
    $sql = "SELECT perfil_id, perfil_nombre FROM conf__perfiles WHERE estado_registro_id = 1 ORDER BY perfil_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

if (!function_exists('obtenerModulos')) {
    function obtenerModulos($conexion) {
        $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE estado_registro_id = 1 ORDER BY modulo";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
        return $data;
    }
}

if (!function_exists('obtenerPerfilesPorModulo')) {
    function obtenerPerfilesPorModulo($conexion, $modulo_id) {
        $modulo_condition = "";
        if ($modulo_id) {
            $modulo_id = intval($modulo_id);
            $modulo_condition = "AND modulo_id = $modulo_id";
        }
        
        $sql = "SELECT perfil_id, perfil_nombre FROM conf__perfiles 
                WHERE estado_registro_id = 1 $modulo_condition 
                ORDER BY perfil_nombre";
        $res = mysqli_query($conexion, $sql);
        $data = [];
        while ($fila = mysqli_fetch_assoc($res)) {
            $data[] = $fila;
        }
        return $data;
    }
}

if (!function_exists('obtenerEstructuraJerarquicaPaginas')) {
    function obtenerEstructuraJerarquicaPaginas($conexion, $modulo_id, $perfil_id = null) {
        $perfil_condition = "";
        $join_condition = "LEFT JOIN";
        
        if ($perfil_id) {
            $perfil_id = intval($perfil_id);
            $perfil_condition = "AND perfil_func.perfil_id = $perfil_id";
            $join_condition = "LEFT JOIN";
        } else {
            $perfil_id = 0; // Para evitar errores en la consulta
        }
        
        $modulo_condition = "";
        if ($modulo_id) {
            $modulo_id = intval($modulo_id);
            $modulo_condition = "AND p.modulo_id = $modulo_id";
        }
        
        // Primero obtenemos todas las páginas con su estructura jerárquica
        $sql_paginas = "SELECT 
                    p.pagina_id, 
                    p.pagina,
                    p.padre_id,
                    p.orden as pagina_orden,
                    p.modulo_id,
                    i.icono_clase as icono,
                    p.url as ruta,
                    p.pagina_descripcion
                FROM conf__paginas p
                LEFT JOIN conf__iconos i ON p.icono_id = i.icono_id
                WHERE p.estado_registro_id = 1 $modulo_condition
                ORDER BY p.padre_id IS NULL DESC, p.padre_id, p.orden, p.pagina";
        
        $res_paginas = mysqli_query($conexion, $sql_paginas);
        
        if (!$res_paginas) {
            error_log("Error en consulta de páginas: " . mysqli_error($conexion));
            return [];
        }
        
        $paginas = [];
        while ($fila = mysqli_fetch_assoc($res_paginas)) {
            $paginas[$fila['pagina_id']] = [
                'pagina_id' => $fila['pagina_id'],
                'pagina' => $fila['pagina'],
                'padre_id' => $fila['padre_id'],
                'pagina_orden' => $fila['pagina_orden'],
                'modulo_id' => $fila['modulo_id'],
                'icono' => $fila['icono'],
                'ruta' => $fila['ruta'],
                'pagina_descripcion' => $fila['pagina_descripcion'],
                'hijos' => [],
                'funciones' => []
            ];
        }
        
        // Ahora obtenemos las funciones para cada página
        $sql_funciones = "SELECT 
                    p.pagina_id,
                    pf.pagina_funcion_id,
                    pf.nombre_funcion,
                    pf.descripcion,
                    pf.orden as funcion_orden,
                    COALESCE(perfil_func.asignado, 0) as asignado,
                    perfil_func.perfil_funcion_id
                FROM conf__paginas p
                LEFT JOIN conf__paginas_funciones pf ON p.pagina_id = pf.pagina_id
                $join_condition conf__perfiles_funciones perfil_func 
                    ON pf.pagina_funcion_id = perfil_func.pagina_funcion_id 
                    AND perfil_func.perfil_id = $perfil_id
                    
                WHERE p.estado_registro_id = 1 $modulo_condition
                ORDER BY p.padre_id IS NULL DESC, p.padre_id, p.orden, p.pagina, pf.orden, pf.nombre_funcion";
        
        $res_funciones = mysqli_query($conexion, $sql_funciones);
        
        if (!$res_funciones) {
            error_log("Error en consulta de funciones: " . mysqli_error($conexion));
        } else {
            while ($fila = mysqli_fetch_assoc($res_funciones)) {
                $pagina_id = $fila['pagina_id'];
                
                if (isset($paginas[$pagina_id])) {
                    // Solo agregar la función si existe pagina_funcion_id
                    if ($fila['pagina_funcion_id']) {
                        $paginas[$pagina_id]['funciones'][] = [
                            'pagina_funcion_id' => $fila['pagina_funcion_id'],
                            'nombre_funcion' => $fila['nombre_funcion'],
                            'descripcion' => $fila['descripcion'],
                            'funcion_orden' => $fila['funcion_orden'],
                            'asignado' => $fila['asignado'],
                            'perfil_funcion_id' => $fila['perfil_funcion_id']
                        ];
                    }
                }
            }
        }
        
        // Construimos la estructura jerárquica correctamente
        $arbol_paginas = [];

        // Función auxiliar recursiva para construir el árbol
        function construirArbol(&$paginas, $padre_id = null) {
            $arbol = [];
            foreach ($paginas as $pagina_id => $pagina) {
                if ($pagina['padre_id'] == $padre_id) {
                    // Buscar hijos recursivamente
                    $hijos = construirArbol($paginas, $pagina_id);
                    if (!empty($hijos)) {
                        $pagina['hijos'] = $hijos;
                    }
                    $arbol[$pagina_id] = $pagina;
                    // Eliminar esta página del array para mejorar rendimiento
                    unset($paginas[$pagina_id]);
                }
            }
            return $arbol;
        }
        
        // Hacer una copia de las páginas para no modificar el original
        $paginas_temp = $paginas;
        $arbol_paginas = construirArbol($paginas_temp);
        
        // Si quedan páginas sin padre (posiblemente por referencias incorrectas)
        // las agregamos al final
        if (!empty($paginas_temp)) {
            foreach ($paginas_temp as $pagina_id => $pagina) {
                $arbol_paginas[$pagina_id] = $pagina;
            }
        }
        
        // Ordenamos las funciones por su orden y los hijos por orden de página
        function ordenarArbol(&$arbol) {
            foreach ($arbol as &$pagina) {
                if (!empty($pagina['funciones'])) {
                    usort($pagina['funciones'], function($a, $b) {
                        return $a['funcion_orden'] - $b['funcion_orden'];
                    });
                }
                
                if (!empty($pagina['hijos'])) {
                    // Ordenar hijos por su orden de página
                    uasort($pagina['hijos'], function($a, $b) {
                        return $a['pagina_orden'] - $b['pagina_orden'];
                    });
                    
                    // Ordenar recursivamente los hijos
                    ordenarArbol($pagina['hijos']);
                }
            }
        }
        
        ordenarArbol($arbol_paginas);
        
        return $arbol_paginas;
    }
}
if (!function_exists('obtenerPaginasConFunciones')) {
    function obtenerPaginasConFunciones($conexion, $modulo_id, $perfil_id = null) {
        $perfil_condition = "";
        $join_condition = "LEFT JOIN";
        
        if ($perfil_id) {
            $perfil_id = intval($perfil_id);
            $perfil_condition = "AND perfil_func.perfil_id = $perfil_id";
            $join_condition = "LEFT JOIN";
        } else {
            $perfil_id = 0; // Para evitar errores en la consulta
        }
        
        $modulo_condition = "";
        if ($modulo_id) {
            $modulo_id = intval($modulo_id);
            $modulo_condition = "AND p.modulo_id = $modulo_id";
        }
        
        $sql = "SELECT 
                    p.pagina_id, 
                    p.pagina,
                    p.modulo_id,
                    pf.pagina_funcion_id,
                    pf.nombre_funcion,
                    pf.descripcion,
                    COALESCE(perfil_func.asignado, 0) as asignado,
                    perfil_func.perfil_funcion_id
                FROM conf__paginas p
                LEFT JOIN conf__paginas_funciones pf ON p.pagina_id = pf.pagina_id
                $join_condition conf__perfiles_funciones perfil_func 
                    ON pf.pagina_funcion_id = perfil_func.pagina_funcion_id 
                    AND perfil_func.perfil_id = $perfil_id
                    $perfil_condition
                WHERE p.estado_registro_id = 1 AND pf.pagina_funcion_id IS NOT NULL
                $modulo_condition
                ORDER BY p.pagina, pf.orden, pf.nombre_funcion";
        
        error_log("SQL: " . $sql); // Para debugging
        
        $res = mysqli_query($conexion, $sql);
        
        if (!$res) {
            error_log("Error en consulta: " . mysqli_error($conexion));
            return [];
        }
        
        $paginas = [];
        
        while ($fila = mysqli_fetch_assoc($res)) {
            $pagina_id = $fila['pagina_id'];
            
            if (!isset($paginas[$pagina_id])) {
                $paginas[$pagina_id] = [
                    'pagina_id' => $pagina_id,
                    'pagina' => $fila['pagina'],
                    'modulo_id' => $fila['modulo_id'],
                    'funciones' => []
                ];
            }
            
            if ($fila['pagina_funcion_id']) {
                $paginas[$pagina_id]['funciones'][] = [
                    'pagina_funcion_id' => $fila['pagina_funcion_id'],
                    'nombre_funcion' => $fila['nombre_funcion'],
                    'descripcion' => $fila['descripcion'],
                    'asignado' => $fila['asignado'],
                    'perfil_funcion_id' => $fila['perfil_funcion_id']
                ];
            }
        }
        
        return array_values($paginas);
    }
}

function asignarFuncionAPerfil($conexion, $perfil_id, $pagina_funcion_id) {
    $perfil_id = intval($perfil_id);
    $pagina_funcion_id = intval($pagina_funcion_id);
    
    // Verificar si ya existe la asignación
    $sql_check = "SELECT perfil_funcion_id FROM conf__perfiles_funciones 
                  WHERE perfil_id = $perfil_id AND pagina_funcion_id = $pagina_funcion_id";
    $res_check = mysqli_query($conexion, $sql_check);
    
    if (mysqli_num_rows($res_check) > 0) {
        // Actualizar existente
        $sql = "UPDATE conf__perfiles_funciones SET asignado = 1 
                WHERE perfil_id = $perfil_id AND pagina_funcion_id = $pagina_funcion_id";
    } else {
        // Insertar nuevo
        $sql = "INSERT INTO conf__perfiles_funciones (perfil_id, pagina_funcion_id, asignado) 
                VALUES ($perfil_id, $pagina_funcion_id, 1)";
    }
    
    return mysqli_query($conexion, $sql);
}

function desasignarFuncionDePerfil($conexion, $perfil_id, $pagina_funcion_id) {
    $perfil_id = intval($perfil_id);
    $pagina_funcion_id = intval($pagina_funcion_id);
    
    $sql = "UPDATE conf__perfiles_funciones SET asignado = 0 
            WHERE perfil_id = $perfil_id AND pagina_funcion_id = $pagina_funcion_id";
    
    return mysqli_query($conexion, $sql);
}

function toggleFuncionPerfil($conexion, $perfil_id, $pagina_funcion_id, $asignado) {
    if ($asignado) {
        return asignarFuncionAPerfil($conexion, $perfil_id, $pagina_funcion_id);
    } else {
        return desasignarFuncionDePerfil($conexion, $perfil_id, $pagina_funcion_id);
    }
}
?>