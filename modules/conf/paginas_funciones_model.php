<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

// ============================================================
// FUNCIONES DE OBTENCIÓN DE DATOS
// ============================================================

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

// ============================================================
// FUNCIONES DEL ÁRBOL
// ============================================================

function obtenerArbolFunciones($conexion, $modulo_id = null, $pagina_id = null, $busqueda = null)
{
    $modulosData = obtenerModulosParaFiltro($conexion);
    
    // Obtener páginas
    $sql = "SELECT p.*, m.modulo, i.icono_clase
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__iconos i ON p.icono_id = i.icono_id
            WHERE p.tabla_estado_registro_id = 1";
    
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $sql .= " AND p.modulo_id = $modulo_id";
    }
    if ($pagina_id) {
        $pagina_id = intval($pagina_id);
        $sql .= " AND p.pagina_id = $pagina_id";
    }
    $sql .= " ORDER BY p.modulo_id, p.orden, p.pagina";
    
    $res = mysqli_query($conexion, $sql);
    $paginas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $paginas[] = $fila;
    }
    
    // Obtener funciones
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
                     WHERE pf.tabla_estado_registro_id = 1";
    
    if ($busqueda && strlen($busqueda) > 2) {
        $busqueda = mysqli_real_escape_string($conexion, $busqueda);
        $sqlFunciones .= " AND (pf.nombre_funcion LIKE '%$busqueda%' OR pf.descripcion LIKE '%$busqueda%' OR pf.accion_js LIKE '%$busqueda%')";
    }
    $sqlFunciones .= " ORDER BY pf.tabla_estado_registro_origen_id, pf.orden, pf.nombre_funcion";
    
    $resFunciones = mysqli_query($conexion, $sqlFunciones);
    $funcionesPorPagina = [];
    while ($fila = mysqli_fetch_assoc($resFunciones)) {
        $paginaId = $fila['pagina_id'];
        if (!isset($funcionesPorPagina[$paginaId])) {
            $funcionesPorPagina[$paginaId] = [];
        }
        $funcionesPorPagina[$paginaId][] = $fila;
    }
    
    // Construir árbol
    $arbol = [];
    foreach ($modulosData as $modulo) {
        if ($modulo_id && $modulo['modulo_id'] != $modulo_id) {
            continue;
        }
        
        $moduloNode = [
            'id' => 'modulo_' . $modulo['modulo_id'],
            'text' => $modulo['modulo'],
            'type' => 'modulo',
            'icon' => 'fas fa-folder-open text-warning',
            'children' => [],
            'state' => ['opened' => true]
        ];
        
        $moduloNode['children'] = construirArbolFuncionesRecursivo(
            $paginas, $funcionesPorPagina, $modulo['modulo_id'], null
        );
        
        if (!empty($moduloNode['children'])) {
            $arbol[] = $moduloNode;
        }
    }
    return $arbol;
}

function construirArbolFuncionesRecursivo($paginas, $funcionesPorPagina, $modulo_id, $padre_id = null)
{
    $result = [];
    foreach ($paginas as $pagina) {
        $padreActual = $pagina['padre_id'];
        if ($padreActual === null || $padreActual == 0) {
            $padreActual = null;
        }
        
        if ($pagina['modulo_id'] == $modulo_id && $padreActual == $padre_id) {
            $icono = !empty($pagina['icono_clase']) ? $pagina['icono_clase'] : 'fas fa-file-alt';
            $colorClass = $pagina['tabla_estado_registro_id'] == 1 ? 'text-success' : 'text-danger';
            
            $paginaNode = [
                'id' => 'pagina_' . $pagina['pagina_id'],
                'text' => $pagina['pagina'],
                'type' => 'pagina',
                'icon' => $icono . ' ' . $colorClass,
                'children' => [],
                'state' => ['opened' => true],
                'data' => [
                    'descripcion' => $pagina['pagina_descripcion'],
                    'url' => $pagina['url'],
                    'orden' => $pagina['orden']
                ]
            ];
            
            // Subpáginas
            $hijos = construirArbolFuncionesRecursivo($paginas, $funcionesPorPagina, $modulo_id, $pagina['pagina_id']);
            if (!empty($hijos)) {
                $paginaNode['children'] = array_merge($paginaNode['children'], $hijos);
            }
            
            // Funciones agrupadas por estado_origen
            $paginaId = $pagina['pagina_id'];
            if (isset($funcionesPorPagina[$paginaId]) && !empty($funcionesPorPagina[$paginaId])) {
                $funcionesPagina = $funcionesPorPagina[$paginaId];
                
                $funcionesAgrupadas = [];
                foreach ($funcionesPagina as $funcion) {
                    $origenId = intval($funcion['tabla_estado_registro_origen_id']);
                    if (!isset($funcionesAgrupadas[$origenId])) {
                        $funcionesAgrupadas[$origenId] = [];
                    }
                    $funcionesAgrupadas[$origenId][] = $funcion;
                }
                
                foreach ($funcionesAgrupadas as $origenId => $funcionesGrupo) {
                    $nombreOrigen = '0';
                    if (!empty($funcionesGrupo[0]['estado_origen'])) {
                        $nombreOrigen = $funcionesGrupo[0]['estado_origen'];
                    }
                    
                    $grupoNode = [
                        'id' => 'grupo_' . $pagina['pagina_id'] . '_' . $origenId,
                        'text' => 'Estado Origen: ' . $nombreOrigen . ' (' . count($funcionesGrupo) . ' funciones)',
                        'type' => 'grupo',
                        'icon' => 'fas fa-layer-group text-info',
                        'children' => [],
                        'state' => ['opened' => true],
                        'data' => [
                            'estado_origen_id' => $origenId,
                            'total_funciones' => count($funcionesGrupo)
                        ]
                    ];
                    
                    foreach ($funcionesGrupo as $funcion) {
                        $tipo = $funcion['tabla_estado_registro_id'] == 1 ? 'activa' : 'inactiva';
                        $textoFuncion = $funcion['nombre_funcion'];
                        
                        if (!empty($funcion['color_clase'])) {
                            $textoFuncion .= ' <span class="badge ' . $funcion['color_clase'] . '">' . $funcion['nombre_color'] . '</span>';
                        }
                        if ($funcion['estado_destino']) {
                            $textoFuncion .= ' <span class="funcion-estado badge bg-light text-dark">→ ' . $funcion['estado_destino'] . '</span>';
                        }
                        if (!empty($funcion['accion_js'])) {
                            $textoFuncion .= ' <span class="funcion-accion"><code>' . $funcion['accion_js'] . '</code></span>';
                        }
                        
                        $funcionNode = [
                            'id' => 'funcion_' . $funcion['pagina_funcion_id'],
                            'text' => $textoFuncion,
                            'type' => $tipo,
                            'icon' => !empty($funcion['icono_clase']) ? $funcion['icono_clase'] : 'fas fa-cog',
                            'data' => [
                                'nombre' => $funcion['nombre_funcion'],
                                'accion_js' => $funcion['accion_js'],
                                'descripcion' => $funcion['descripcion'],
                                'orden' => $funcion['orden'],
                                'color' => $funcion['nombre_color'],
                                'estado_origen' => $funcion['estado_origen'],
                                'estado_origen_id' => $funcion['tabla_estado_registro_origen_id'],
                                'estado_destino' => $funcion['estado_destino'],
                                'estado_destino_id' => $funcion['tabla_estado_registro_destino_id']
                            ]
                        ];
                        $grupoNode['children'][] = $funcionNode;
                    }
                    
                    if (!empty($grupoNode['children'])) {
                        $paginaNode['children'][] = $grupoNode;
                    }
                }
            }
            
            if (!empty($paginaNode['children'])) {
                $result[] = $paginaNode;
            }
        }
    }
    return $result;
}

// ============================================================
// FUNCIONES CRUD
// ============================================================

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
                CASE WHEN pf.tabla_estado_registro_origen_id = 0 THEN 'Sin estado' ELSE eor.estado_registro END as estado_origen,
                ede.estado_registro as estado_destino,
                CASE WHEN pf.tabla_estado_registro_id = 1 THEN 'Activo' ELSE 'Inactivo' END as estado_nombre
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
    if (empty($data['nombre_funcion']) || empty($data['pagina_id']) || empty($data['tabla_estado_registro_destino_id'])) {
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
    if (empty($data['nombre_funcion']) || empty($data['pagina_id']) || empty($data['tabla_estado_registro_destino_id'])) {
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

// ============================================================
// FUNCIÓN PARA COPIAR FUNCIÓN
// ============================================================

function copiarPaginaFuncion($conexion, $id)
{
    $id = intval($id);
    
    // Obtener la función original
    $sql = "SELECT * FROM conf__paginas_funciones WHERE pagina_funcion_id = $id";
    $res = mysqli_query($conexion, $sql);
    $funcion = mysqli_fetch_assoc($res);
    
    if (!$funcion) {
        return false;
    }
    
    // Crear copia con nombre modificado
    $nombre_base = $funcion['nombre_funcion'];
    $nombre_copia = $nombre_base . ' (copia)';
    
    // Verificar si ya existe una copia con ese nombre
    $sql_check = "SELECT COUNT(*) as total FROM conf__paginas_funciones 
                  WHERE nombre_funcion = '$nombre_copia' AND pagina_id = " . $funcion['pagina_id'];
    $res_check = mysqli_query($conexion, $sql_check);
    $check = mysqli_fetch_assoc($res_check);
    
    if ($check['total'] > 0) {
        $nombre_copia = $nombre_base . ' (copia ' . ($check['total'] + 1) . ')';
    }
    
    // Insertar copia
    $sql = "INSERT INTO conf__paginas_funciones 
            (pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, 
             accion_js, descripcion, tabla_estado_registro_origen_id, 
             tabla_estado_registro_destino_id, orden, tabla_estado_registro_id) 
            VALUES (
                {$funcion['pagina_id']},
                " . (empty($funcion['icono_id']) ? 'NULL' : $funcion['icono_id']) . ",
                {$funcion['color_id']},
                " . (empty($funcion['funcion_estandar_id']) ? 'NULL' : $funcion['funcion_estandar_id']) . ",
                '$nombre_copia',
                " . (empty($funcion['accion_js']) ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $funcion['accion_js']) . "'") . ",
                " . (empty($funcion['descripcion']) ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $funcion['descripcion']) . "'") . ",
                {$funcion['tabla_estado_registro_origen_id']},
                {$funcion['tabla_estado_registro_destino_id']},
                {$funcion['orden']},
                {$funcion['tabla_estado_registro_id']}
            )";
    
    return mysqli_query($conexion, $sql);
}
?>