<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerPaginas($conexion)
{
    $sql = "SELECT p.*, m.modulo as nombre_modulo
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE p.tabla_estado_registro_id = 1 
            ORDER BY m.modulo, p.orden, p.pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerIconos($conexion)
{
    $sql = "SELECT * FROM conf__iconos WHERE tabla_estado_registro_id = 1 ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerColores($conexion)
{
    $sql = "SELECT * FROM conf__colores WHERE tabla_estado_registro_id = 1 ORDER BY nombre_color";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerFuncionesEstandar($conexion)
{
    $sql = "SELECT * FROM conf__paginas_funciones_tipos WHERE tabla_estado_registro_id = 1 ORDER BY nombre_funcion";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerEstadosRegistro($conexion)
{
    $sql = "SELECT * FROM conf__estados_registros ORDER BY estado_registro";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulosParaFiltro($conexion)
{
    $sql = "SELECT * FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// FUNCIÓN CORREGIDA: Obtener árbol de funciones por módulo → página → función
function obtenerArbolFunciones($conexion, $modulo_id = null, $pagina_id = null, $busqueda = null)
{
    // Obtener todos los módulos activos
    $sqlModulos = "SELECT * FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $sqlModulos .= " AND modulo_id = $modulo_id";
    }
    $resModulos = mysqli_query($conexion, $sqlModulos);
    $modulos = [];
    while ($fila = mysqli_fetch_assoc($resModulos)) {
        $modulos[] = $fila;
    }
    
    // Obtener todas las páginas
    $sqlPaginas = "SELECT p.*, i.icono_clase, i.icono_nombre
                   FROM conf__paginas p
                   LEFT JOIN conf__iconos i ON p.icono_id = i.icono_id
                   WHERE p.tabla_estado_registro_id = 1";
    
    if ($pagina_id) {
        $pagina_id = intval($pagina_id);
        $sqlPaginas .= " AND p.pagina_id = $pagina_id";
    }
    
    $sqlPaginas .= " ORDER BY p.modulo_id, p.orden, p.pagina";
    
    $resPaginas = mysqli_query($conexion, $sqlPaginas);
    $paginas = [];
    while ($fila = mysqli_fetch_assoc($resPaginas)) {
        $paginas[] = $fila;
    }
    
    // Construir el árbol
    $arbol = [];
    
    foreach ($modulos as $modulo) {
        // Filtrar páginas de este módulo
        $paginasModulo = array_filter($paginas, function($pagina) use ($modulo) {
            return $pagina['modulo_id'] == $modulo['modulo_id'];
        });
        
        // Si no hay páginas en este módulo, saltar
        if (empty($paginasModulo)) {
            continue;
        }
        
        // Nodo del módulo - SIN icono en el texto
        $moduloNode = [
            'id' => 'modulo_' . $modulo['modulo_id'],
            'text' => $modulo['modulo'],
            'type' => 'modulo',
            'icon' => 'fas fa-folder-open text-warning',
            'children' => [],
            'state' => [
                'opened' => true
            ]
        ];
        
        // Construir páginas del módulo
        foreach ($paginasModulo as $pagina) {
            // Obtener funciones de esta página
            $sqlFunciones = "SELECT pf.*, 
                                    i.icono_clase, i.icono_nombre,
                                    c.nombre_color, c.color_clase,
                                    eor.estado_registro as estado_origen,
                                    ede.estado_registro as estado_destino
                             FROM conf__paginas_funciones pf
                             LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
                             LEFT JOIN conf__colores c ON pf.color_id = c.color_id
                             LEFT JOIN conf__estados_registros eor ON pf.tabla_estado_registro_origen_id = eor.estado_registro_id
                             LEFT JOIN conf__estados_registros ede ON pf.tabla_estado_registro_destino_id = ede.estado_registro_id
                             WHERE pf.pagina_id = " . $pagina['pagina_id'] . "
                             AND pf.tabla_estado_registro_id = 1";
            
            if ($busqueda && strlen($busqueda) > 2) {
                $busqueda = mysqli_real_escape_string($conexion, $busqueda);
                $sqlFunciones .= " AND (pf.nombre_funcion LIKE '%$busqueda%' OR pf.descripcion LIKE '%$busqueda%' OR pf.accion_js LIKE '%$busqueda%')";
            }
            
            // ORDEN CORREGIDO: Primero por tabla_estado_registro_origen_id, luego por orden
            $sqlFunciones .= " ORDER BY pf.tabla_estado_registro_origen_id, pf.orden, pf.nombre_funcion";
            
            $resFunciones = mysqli_query($conexion, $sqlFunciones);
            $funciones = [];
            while ($fila = mysqli_fetch_assoc($resFunciones)) {
                $funciones[] = $fila;
            }
            
            // Solo mostrar la página si tiene funciones o no hay búsqueda
            if (empty($funciones) && $busqueda) {
                continue;
            }
            
            // Nodo de la página - SOLO texto sin icono HTML
            $paginaNode = [
                'id' => 'pagina_' . $pagina['pagina_id'],
                'text' => $pagina['pagina'], // Solo el nombre
                'type' => 'pagina',
                'icon' => !empty($pagina['icono_clase']) ? $pagina['icono_clase'] : 'fas fa-file-alt',
                'children' => [],
                'state' => [
                    'opened' => true
                ],
                'data' => [
                    'url' => $pagina['url'],
                    'descripcion' => $pagina['pagina_descripcion']
                ]
            ];
            
            // Agregar funciones como hijos - CORREGIDO: No duplicar iconos
            foreach ($funciones as $funcion) {
                // Determinar el tipo según el estado
                $tipo = $funcion['tabla_estado_registro_id'] == 1 ? 'activa' : 'inactiva';
                
                // Construir el texto de la función - EL ICONO SE MANEJA CON LA PROPIEDAD 'icon' DE JSTREE
                $textoFuncion = $funcion['nombre_funcion'];
                
                // Agregar color como badge (si tiene)
                if (!empty($funcion['color_clase'])) {
                    $textoFuncion .= ' <span class="badge ' . $funcion['color_clase'] . '">' . $funcion['nombre_color'] . '</span>';
                }
                
                // Agregar estados (origen → destino)
                if ($funcion['estado_origen'] || $funcion['estado_destino']) {
                    $origen = $funcion['estado_origen'] ?: '0';
                    $destino = $funcion['estado_destino'] ?: '-';
                    $textoFuncion .= ' <span class="funcion-estado badge bg-light text-dark">' . $origen . ' → ' . $destino . '</span>';
                }
                
                // Agregar acción JS
                if (!empty($funcion['accion_js'])) {
                    $textoFuncion .= ' <span class="funcion-accion"><code>' . $funcion['accion_js'] . '</code></span>';
                }
                
                // Determinar el icono para jstree
                $iconoFuncion = !empty($funcion['icono_clase']) ? $funcion['icono_clase'] : 'fas fa-cog';
                
                $funcionNode = [
                    'id' => 'funcion_' . $funcion['pagina_funcion_id'],
                    'text' => $textoFuncion, // Solo texto, sin icono HTML
                    'type' => $tipo,
                    'icon' => $iconoFuncion, // El icono se maneja aquí
                    'data' => [
                        'nombre' => $funcion['nombre_funcion'],
                        'accion_js' => $funcion['accion_js'],
                        'descripcion' => $funcion['descripcion'],
                        'orden' => $funcion['orden'],
                        'color' => $funcion['nombre_color'],
                        'estado_origen' => $funcion['estado_origen'],
                        'estado_destino' => $funcion['estado_destino'],
                        'estado_origen_id' => $funcion['tabla_estado_registro_origen_id']
                    ]
                ];
                
                $paginaNode['children'][] = $funcionNode;
            }
            
            // Solo agregar la página si tiene funciones
            if (!empty($paginaNode['children'])) {
                $moduloNode['children'][] = $paginaNode;
            }
        }
        
        // Solo agregar el módulo si tiene páginas con funciones
        if (!empty($moduloNode['children'])) {
            $arbol[] = $moduloNode;
        }
    }
    
    return $arbol;
}

function obtenerPaginasFunciones($conexion)
{
    $sql = "SELECT 
                pf.*,
                p.pagina as nombre_pagina,
                p.url as ruta_pagina,
                m.modulo as nombre_modulo,
                i.icono_nombre,
                i.icono_clase,
                c.nombre_color as color_nombre,
                c.color_clase,
                ft.nombre_funcion as funcion_estandar_nombre,
                ft.accion_js as funcion_estandar_accion,
                CASE 
                    WHEN pf.tabla_estado_registro_origen_id = 0 THEN 'Sin estado'
                    ELSE eor.estado_registro
                END as estado_origen,
                ede.estado_registro as estado_destino,
                CASE 
                    WHEN pf.tabla_estado_registro_id = 1 THEN 'Activo'
                    ELSE 'Inactivo'
                END as estado_nombre
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__paginas p ON pf.pagina_id = p.pagina_id
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            LEFT JOIN conf__paginas_funciones_tipos ft ON pf.funcion_estandar_id = ft.pagina_funcion_id
            LEFT JOIN conf__estados_registros eor ON pf.tabla_estado_registro_origen_id = eor.estado_registro_id
            LEFT JOIN conf__estados_registros ede ON pf.tabla_estado_registro_destino_id = ede.estado_registro_id
            ORDER BY m.modulo, p.orden, p.pagina, pf.tabla_estado_registro_origen_id, pf.orden, pf.nombre_funcion";

    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarPaginaFuncion($conexion, $data)
{
    if (
        empty($data['nombre_funcion']) ||
        empty($data['pagina_id']) ||
        empty($data['tabla_estado_registro_destino_id'])
    ) {
        return false;
    }

    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $pagina_id = intval($data['pagina_id']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? intval($data['funcion_estandar_id']) : 'NULL';
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "INSERT INTO conf__paginas_funciones 
            (nombre_funcion, pagina_id, accion_js, descripcion, orden, icono_id, color_id, 
             funcion_estandar_id, tabla_estado_registro_origen_id, tabla_estado_registro_destino_id, tabla_estado_registro_id) 
            VALUES 
            ('$nombre_funcion', $pagina_id, '$accion_js', '$descripcion', $orden, $icono_id, $color_id,
             $funcion_estandar_id, $estado_origen_id, $estado_destino_id, $estado_registro_id)";

    return mysqli_query($conexion, $sql);
}

function editarPaginaFuncion($conexion, $id, $data)
{
    if (
        empty($data['nombre_funcion']) ||
        empty($data['pagina_id']) ||
        empty($data['tabla_estado_registro_destino_id'])
    ) {
        return false;
    }

    $id = intval($id);
    $nombre_funcion = mysqli_real_escape_string($conexion, $data['nombre_funcion']);
    $pagina_id = intval($data['pagina_id']);
    $accion_js = mysqli_real_escape_string($conexion, $data['accion_js']);
    $descripcion = mysqli_real_escape_string($conexion, $data['descripcion']);
    $orden = intval($data['orden']);
    $icono_id = !empty($data['icono_id']) ? intval($data['icono_id']) : 'NULL';
    $color_id = intval($data['color_id']);
    $funcion_estandar_id = !empty($data['funcion_estandar_id']) ? intval($data['funcion_estandar_id']) : 'NULL';
    $estado_origen_id = intval($data['tabla_estado_registro_origen_id']);
    $estado_destino_id = intval($data['tabla_estado_registro_destino_id']);
    $estado_registro_id = intval($data['tabla_estado_registro_id']);

    $sql = "UPDATE conf__paginas_funciones SET
            nombre_funcion = '$nombre_funcion',
            pagina_id = $pagina_id,
            accion_js = '$accion_js',
            descripcion = '$descripcion',
            orden = $orden,
            icono_id = $icono_id,
            color_id = $color_id,
            funcion_estandar_id = $funcion_estandar_id,
            tabla_estado_registro_origen_id = $estado_origen_id,
            tabla_estado_registro_destino_id = $estado_destino_id,
            tabla_estado_registro_id = $estado_registro_id
            WHERE pagina_funcion_id = $id";

    return mysqli_query($conexion, $sql);
}

function eliminarPaginaFuncion($conexion, $id)
{
    $id = intval($id);
    $sql = "DELETE FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    return mysqli_query($conexion, $sql);
}

function obtenerPaginaFuncionPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT * FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}
?>