<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function obtenerModulos($conexion)
{
    $sql = "SELECT * FROM conf__modulos ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPadre($conexion, $modulo_id = null)
{
    $sql = "SELECT p.*, 
                   (SELECT COUNT(*) FROM conf__paginas WHERE padre_id = p.pagina_id) as tiene_hijos
            FROM conf__paginas p";
    
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $sql .= " WHERE p.modulo_id = $modulo_id";
    }
    
    $sql .= " ORDER BY p.orden, p.pagina";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablas($conexion)
{
    $sql = "SELECT * FROM conf__tablas ORDER BY tabla_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerIconos($conexion)
{
    $sql = "SELECT * FROM conf__iconos ORDER BY icono_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerTablaTipos($conexion)
{
    $sql = "SELECT * FROM conf__tablas_tipos WHERE tabla_estado_registro_id = 1 ORDER BY tabla_tipo_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id)
{
    $tabla_tipo_id = intval($tabla_tipo_id);
    $sql = "SELECT f.*, 
                   i.icono_clase, i.icono_id, i.icono_nombre,
                   c.color_clase, c.color_id, c.nombre_color,
                   f.tabla_estado_registro_origen_id,
                   f.tabla_estado_registro_destino_id
            FROM conf__paginas_funciones_tipos f
            LEFT JOIN conf__iconos i ON f.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON f.color_id = c.color_id
            WHERE f.tabla_tipo_id = $tabla_tipo_id 
            AND f.tabla_estado_registro_id = 1
            ORDER BY f.orden, f.nombre_funcion";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en consulta obtenerFuncionesPorTipoTabla: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    return $data;
}

function obtenerFuncionesPorPagina($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $sql = "SELECT pf.*, 
                   i.icono_clase, i.icono_nombre, 
                   c.color_clase, c.nombre_color,
                   eor.tabla_estado_registro as origen_nombre, 
                   ed.tabla_estado_registro as destino_nombre,
                   eor.tabla_estado_registro_id as origen_id,
                   ed.tabla_estado_registro_id as destino_id
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
            LEFT JOIN conf__colores c ON pf.color_id = c.color_id
            LEFT JOIN conf__tablas_estados_registros eor ON pf.tabla_estado_registro_origen_id = eor.tabla_estado_registro_id
            LEFT JOIN conf__tablas_estados_registros ed ON pf.tabla_estado_registro_destino_id = ed.tabla_estado_registro_id
            WHERE pf.pagina_id = $pagina_id 
            AND pf.tabla_estado_registro_id = 1
            ORDER BY pf.orden, pf.nombre_funcion";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        error_log("Error en consulta obtenerFuncionesPorPagina: " . mysqli_error($conexion));
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    
    return $data;
}

function obtenerArbolPaginas($conexion, $modulo_id = null, $busqueda = null)
{
    // Obtener todos los módulos
    $modulosData = obtenerModulos($conexion);
    
    // Obtener todas las páginas con sus datos
    $sql = "SELECT p.*, m.modulo, i.icono_clase
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__iconos i ON p.icono_id = i.icono_id
            WHERE 1=1";
    
    if ($modulo_id) {
        $modulo_id = intval($modulo_id);
        $sql .= " AND p.modulo_id = $modulo_id";
    }
    
    if ($busqueda && strlen($busqueda) > 2) {
        $busqueda = mysqli_real_escape_string($conexion, $busqueda);
        $sql .= " AND (p.pagina LIKE '%$busqueda%' OR p.pagina_descripcion LIKE '%$busqueda%' OR p.url LIKE '%$busqueda%')";
    }
    
    $sql .= " ORDER BY p.modulo_id, p.orden, p.pagina";
    
    $res = mysqli_query($conexion, $sql);
    $paginas = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $paginas[] = $fila;
    }
    
    // Construir el árbol
    $arbol = [];
    
    // Para cada módulo, construir su árbol de páginas
    foreach ($modulosData as $modulo) {
        // Si hay filtro de módulo, solo incluir el módulo seleccionado
        if ($modulo_id && $modulo['modulo_id'] != $modulo_id) {
            continue;
        }
        
        // Construir el nodo del módulo - SIN icono en el texto
        $moduloNode = [
            'id' => 'modulo_' . $modulo['modulo_id'],
            'text' => $modulo['modulo'], // Solo el nombre del módulo, sin HTML
            'type' => 'modulo',
            'icon' => 'fas fa-folder-open text-warning',
            'children' => [],
            'state' => [
                'opened' => true
            ]
        ];
        
        // Función recursiva para construir el árbol de páginas
        $moduloNode['children'] = construirArbolPaginasRecursivo($paginas, $modulo['modulo_id'], null);
        
        // Solo agregar el módulo si tiene páginas o no hay filtro de búsqueda
        if (!empty($moduloNode['children']) || !$busqueda || $busqueda == '') {
            $arbol[] = $moduloNode;
        }
    }
    
    return $arbol;
}

function construirArbolPaginasRecursivo($paginas, $modulo_id, $padre_id = null)
{
    $result = [];
    
    foreach ($paginas as $pagina) {
        // Verificar si la página pertenece al módulo y tiene el padre correcto
        $padreActual = $pagina['padre_id'];
        if ($padreActual === null || $padreActual == 0) {
            $padreActual = null;
        }
        
        if ($pagina['modulo_id'] == $modulo_id && $padreActual == $padre_id) {
            // Determinar el icono - SOLO para el icono de jstree, no en el texto
            $icono = !empty($pagina['icono_clase']) ? $pagina['icono_clase'] : 'fas fa-file-alt';
            $colorClass = $pagina['tabla_estado_registro_id'] == 1 ? 'text-success' : 'text-danger';
            
            // El texto solo debe contener el nombre de la página, sin iconos HTML
            $node = [
                'id' => 'pagina_' . $pagina['pagina_id'],
                'text' => $pagina['pagina'], // Solo el nombre, sin HTML
                'type' => $pagina['tabla_estado_registro_id'] == 1 ? 'activo' : 'inactivo',
                'icon' => $icono . ' ' . $colorClass, // El icono se maneja con el plugin types
                'data' => [
                    'descripcion' => $pagina['pagina_descripcion'],
                    'url' => $pagina['url'],
                    'orden' => $pagina['orden'],
                    'modulo_id' => $pagina['modulo_id'],
                    'pagina_nombre' => $pagina['pagina'],
                    'es_acceso_directo' => $pagina['es_acceso_directo']
                ],
                'children' => construirArbolPaginasRecursivo($paginas, $modulo_id, $pagina['pagina_id']),
                'state' => [
                    'opened' => false
                ]
            ];
            
            $result[] = $node;
        }
    }
    
    return $result;
}


// NUEVA FUNCIÓN: Actualizar orden de página
function actualizarOrdenPagina($conexion, $pagina_id, $padre_id = null, $posicion = null)
{
    $pagina_id = intval($pagina_id);
    if ($pagina_id <= 0) {
        return false;
    }
    
    // Si se especifica un padre, actualizarlo
    if ($padre_id !== null && $padre_id !== '#' && $padre_id !== '') {
        $str_padre = (string)$padre_id;
        if (strpos($str_padre, 'modulo_') !== false) {
            // Se movió a la raíz de un módulo
            $mod_id = intval(str_replace('modulo_', '', $str_padre));
            $sql_padre = "UPDATE conf__paginas SET padre_id = 0, modulo_id = ? WHERE pagina_id = ?";
            $stmt = mysqli_prepare($conexion, $sql_padre);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $mod_id, $pagina_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            // Se movió bajo otra página
            $nuevo_padre_id = intval(str_replace('pagina_', '', $str_padre));
            // Obtener el modulo_id del nuevo padre para mantener consistencia
            $res_p = mysqli_query($conexion, "SELECT modulo_id FROM conf__paginas WHERE pagina_id = $nuevo_padre_id");
            $row_p = mysqli_fetch_assoc($res_p);
            $mod_id = $row_p && !empty($row_p['modulo_id']) ? intval($row_p['modulo_id']) : null;
            
            if ($mod_id) {
                $sql_padre = "UPDATE conf__paginas SET padre_id = ?, modulo_id = ? WHERE pagina_id = ?";
                $stmt = mysqli_prepare($conexion, $sql_padre);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "iii", $nuevo_padre_id, $mod_id, $pagina_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                $sql_padre = "UPDATE conf__paginas SET padre_id = ? WHERE pagina_id = ?";
                $stmt = mysqli_prepare($conexion, $sql_padre);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ii", $nuevo_padre_id, $pagina_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
    
    // Actualizar el orden
    if ($posicion !== null) {
        $posicion = intval($posicion);
        
        // Obtener el padre actual de la página
        $sql_actual = "SELECT padre_id, modulo_id FROM conf__paginas WHERE pagina_id = $pagina_id";
        $res_actual = mysqli_query($conexion, $sql_actual);
        $fila_actual = mysqli_fetch_assoc($res_actual);
        if ($fila_actual) {
            $padre_actual = intval($fila_actual['padre_id']);
            $modulo_actual = intval($fila_actual['modulo_id']);
            
            // Obtener todas las páginas del mismo nivel, excluyendo la actual
            $sql = "SELECT pagina_id, orden FROM conf__paginas 
                    WHERE modulo_id = $modulo_actual AND padre_id = $padre_actual AND pagina_id != $pagina_id 
                    ORDER BY orden, pagina";
            $res = mysqli_query($conexion, $sql);
            $paginas = [];
            while ($fila = mysqli_fetch_assoc($res)) {
                $paginas[] = $fila;
            }
            
            // Recalcular órdenes
            $nuevo_orden = 0;
            $insertado = false;
            
            // Insertar la página en la posición indicada
            for ($i = 0; $i <= count($paginas); $i++) {
                if (!$insertado && $i == $posicion) {
                    $sql_update = "UPDATE conf__paginas SET orden = $nuevo_orden WHERE pagina_id = $pagina_id";
                    mysqli_query($conexion, $sql_update);
                    $nuevo_orden++;
                    $insertado = true;
                }
                
                if ($i < count($paginas)) {
                    $sql_update = "UPDATE conf__paginas SET orden = $nuevo_orden WHERE pagina_id = " . intval($paginas[$i]['pagina_id']);
                    mysqli_query($conexion, $sql_update);
                    $nuevo_orden++;
                }
            }
            
            // Si no se insertó (porque la posición es al final)
            if (!$insertado) {
                $sql_update = "UPDATE conf__paginas SET orden = $nuevo_orden WHERE pagina_id = $pagina_id";
                mysqli_query($conexion, $sql_update);
            }
        }
    }
    
    return true;
}

function copiarFuncionesDeTipo($conexion, $pagina_id, $tabla_tipo_id, $forzar = false)
{
    $pagina_id = intval($pagina_id);
    $tabla_tipo_id = intval($tabla_tipo_id);

    $funciones_tipo = obtenerFuncionesPorTipoTabla($conexion, $tabla_tipo_id);
    
    if (empty($funciones_tipo)) {
        $_SESSION['resultado_copia_funciones'] = [
            'nuevas' => 0,
            'existentes' => 0,
            'total_tipo' => 0,
            'errores' => 0,
            'mensaje' => 'No hay funciones definidas para este tipo de tabla'
        ];
        return false;
    }
    
    $funciones_existentes = obtenerFuncionesPorPagina($conexion, $pagina_id);
    
    $claves_existentes = [];
    foreach ($funciones_existentes as $existente) {
        $clave = $existente['nombre_funcion'] . '|' . 
                 ($existente['tabla_estado_registro_origen_id'] ?? '0') . '|' . 
                 ($existente['tabla_estado_registro_destino_id'] ?? '0');
        $claves_existentes[$clave] = true;
    }

    $contador_nuevas = 0;
    $contador_existentes = 0;
    $errores = 0;
    
    foreach ($funciones_tipo as $funcion) {
        $clave_tipo = $funcion['nombre_funcion'] . '|' . 
                      ($funcion['tabla_estado_registro_origen_id'] ?? '0') . '|' . 
                      ($funcion['tabla_estado_registro_destino_id'] ?? '0');
        
        if (isset($claves_existentes[$clave_tipo])) {
            $contador_existentes++;
            continue;
        }
        
        $icono_id = $funcion['icono_id'] ? intval($funcion['icono_id']) : 'NULL';
        $color_id = $funcion['color_id'] ? intval($funcion['color_id']) : '1';
        $nombre_funcion = mysqli_real_escape_string($conexion, $funcion['nombre_funcion']);
        $accion_js = $funcion['accion_js'] ? "'" . mysqli_real_escape_string($conexion, $funcion['accion_js']) . "'" : 'NULL';
        $descripcion = $funcion['descripcion'] ? "'" . mysqli_real_escape_string($conexion, $funcion['descripcion']) . "'" : 'NULL';
        $origen_id = intval($funcion['tabla_estado_registro_origen_id'] ?? 0);
        $destino_id = intval($funcion['tabla_estado_registro_destino_id'] ?? 0);
        $orden = intval($funcion['orden'] ?? 0);
        
        $sql = "INSERT INTO conf__paginas_funciones 
                (pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, 
                 accion_js, descripcion, tabla_estado_registro_origen_id, 
                 tabla_estado_registro_destino_id, orden, tabla_estado_registro_id)
                VALUES (
                    $pagina_id,
                    $icono_id,
                    $color_id,
                    1,
                    '$nombre_funcion',
                    $accion_js,
                    $descripcion,
                    $origen_id,
                    $destino_id,
                    $orden,
                    1
                )";

        if (mysqli_query($conexion, $sql)) {
            $contador_nuevas++;
        } else {
            $errores++;
            error_log("Error al insertar función: " . mysqli_error($conexion) . " SQL: " . $sql);
        }
    }

    $_SESSION['resultado_copia_funciones'] = [
        'nuevas' => $contador_nuevas,
        'existentes' => $contador_existentes,
        'total_tipo' => count($funciones_tipo),
        'errores' => $errores
    ];

    return ($contador_nuevas > 0) || ($contador_existentes > 0 && $errores == 0);
}

function obtenerTablaTipoPorTablaId($conexion, $tabla_id)
{
    $tabla_id = intval($tabla_id);
    $sql = "SELECT tabla_tipo_id FROM conf__tablas WHERE tabla_id = $tabla_id";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila ? $fila['tabla_tipo_id'] : null;
}

function paginaTieneFunciones($conexion, $pagina_id)
{
    $pagina_id = intval($pagina_id);
    $sql = "SELECT COUNT(*) as total FROM conf__paginas_funciones WHERE pagina_id = $pagina_id AND tabla_estado_registro_id = 1";
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    return $fila['total'] > 0;
}

function obtenerpaginas($conexion, $modulo_id = null)
{
    $sql = "SELECT p.*,  m.modulo,  padre.pagina as padre_nombre, 
                   conf__tablas.tabla_nombre, conf__iconos.icono_nombre, 
                   conf__iconos.icono_clase,
                   (SELECT COUNT(*) FROM conf__paginas_funciones pf WHERE pf.pagina_id = p.pagina_id) as tiene_funciones
            FROM conf__paginas p
            LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__paginas padre ON p.padre_id = padre.pagina_id
            LEFT JOIN conf__tablas ON p.tabla_id = conf__tablas.tabla_id
            LEFT JOIN conf__iconos ON p.icono_id = conf__iconos.icono_id";

    if ($modulo_id !== null && $modulo_id !== '' && $modulo_id > 0) {
        $modulo_id = intval($modulo_id);
        $sql .= " WHERE p.modulo_id = $modulo_id";
    }

    $sql .= " ORDER BY p.modulo_id, p.orden, p.pagina";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function agregarpagina($conexion, $data)
{
    if (empty($data['pagina']) || empty($data['modulo_id'])) {
        return false;
    }

    $pagina = trim($data['pagina']);
    $url = trim($data['url'] ?? '');
    $pagina_descripcion = trim($data['pagina_descripcion'] ?? '');
    $orden = isset($data['orden']) && is_numeric($data['orden']) ? intval($data['orden']) : 0;
    $tabla_id = !empty($data['tabla_id']) && is_numeric($data['tabla_id']) && intval($data['tabla_id']) > 0 ? intval($data['tabla_id']) : null;
    $padre_id = !empty($data['padre_id']) && is_numeric($data['padre_id']) ? intval($data['padre_id']) : 0;
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = isset($data['tabla_estado_registro_id']) && is_numeric($data['tabla_estado_registro_id']) ? intval($data['tabla_estado_registro_id']) : 1;
    $icono_id = !empty($data['icono_id']) && is_numeric($data['icono_id']) ? intval($data['icono_id']) : 0;
    $es_acceso_directo = !empty($data['es_acceso_directo']) ? 1 : 0;

    $sql = "INSERT INTO conf__paginas 
            (pagina, url, pagina_descripcion, orden, tabla_id, padre_id, modulo_id, tabla_estado_registro_id, icono_id, es_acceso_directo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando agregarpagina: " . mysqli_error($conexion));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sssiisiiii", 
        $pagina, 
        $url, 
        $pagina_descripcion, 
        $orden, 
        $tabla_id, 
        $padre_id, 
        $modulo_id, 
        $tabla_estado_registro_id, 
        $icono_id, 
        $es_acceso_directo
    );

    $res = mysqli_stmt_execute($stmt);
    if (!$res) {
        error_log("Error ejecutando agregarpagina: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $res;
}

function editarpagina($conexion, $id, $data)
{
    $id = intval($id);
    if ($id <= 0 || empty($data['pagina']) || empty($data['modulo_id'])) {
        return false;
    }

    $pagina = trim($data['pagina']);
    $url = trim($data['url'] ?? '');
    $pagina_descripcion = trim($data['pagina_descripcion'] ?? '');
    $orden = isset($data['orden']) && is_numeric($data['orden']) ? intval($data['orden']) : 0;
    $tabla_id = !empty($data['tabla_id']) && is_numeric($data['tabla_id']) && intval($data['tabla_id']) > 0 ? intval($data['tabla_id']) : null;
    $padre_id = !empty($data['padre_id']) && is_numeric($data['padre_id']) ? intval($data['padre_id']) : 0;
    $modulo_id = intval($data['modulo_id']);
    $tabla_estado_registro_id = isset($data['tabla_estado_registro_id']) && is_numeric($data['tabla_estado_registro_id']) ? intval($data['tabla_estado_registro_id']) : 1;
    $icono_id = !empty($data['icono_id']) && is_numeric($data['icono_id']) ? intval($data['icono_id']) : 0;
    $es_acceso_directo = !empty($data['es_acceso_directo']) ? 1 : 0;

    $sql = "UPDATE conf__paginas SET
            pagina = ?,
            url = ?,
            pagina_descripcion = ?,
            orden = ?,
            tabla_id = ?,
            icono_id = ?,
            padre_id = ?,
            modulo_id = ?,
            tabla_estado_registro_id = ?,
            es_acceso_directo = ?
            WHERE pagina_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error preparando editarpagina: " . mysqli_error($conexion));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sssiisiiiii",
        $pagina,
        $url,
        $pagina_descripcion,
        $orden,
        $tabla_id,
        $icono_id,
        $padre_id,
        $modulo_id,
        $tabla_estado_registro_id,
        $es_acceso_directo,
        $id
    );

    $res = mysqli_stmt_execute($stmt);
    if (!$res) {
        error_log("Error ejecutando editarpagina: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $res;
}

function eliminarpagina($conexion, $id)
{
    $id = intval($id);

    // Primero eliminar las subpáginas recursivamente
    $sql_hijos = "SELECT pagina_id FROM conf__paginas WHERE padre_id = $id";
    $res_hijos = mysqli_query($conexion, $sql_hijos);
    while ($hijo = mysqli_fetch_assoc($res_hijos)) {
        eliminarpagina($conexion, $hijo['pagina_id']);
    }

    // Eliminar funciones asociadas
    $sql1 = "DELETE FROM conf__paginas_funciones WHERE pagina_id = $id";
    mysqli_query($conexion, $sql1);

    // Eliminar la página
    $sql2 = "DELETE FROM conf__paginas WHERE pagina_id = $id";
    return mysqli_query($conexion, $sql2);
}

function obtenerpaginaPorId($conexion, $id)
{
    $id = intval($id);
    $sql = "SELECT p.*, t.tabla_tipo_id 
            FROM conf__paginas p
            LEFT JOIN conf__tablas t ON p.tabla_id = t.tabla_id
            WHERE pagina_id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}