<?php
require_once __DIR__ . '/../../conexion.php';

function conectarBD() {
    // Ajusta estos valores según tu configuración
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "tu_base_de_datos";
    
    $conexion = mysqli_connect($host, $user, $pass, $db);
    
    if (!$conexion) {
        die("Error de conexión: " . mysqli_connect_error());
    }
    
    mysqli_set_charset($conexion, "utf8");
    
    return $conexion;
}

function obtenerEmpresas($conexion) {
    $sql = "SELECT empresa_id, empresa FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa";
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerModulos($conexion) {
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE tabla_estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerPerfilesPorModuloEmpresa($conexion, $modulo_id, $empresa_id) {
    if (!$modulo_id || !$empresa_id) {
        return [];
    }
    
    $modulo_id = intval($modulo_id);
    $empresa_id = intval($empresa_id);
    
    $sql = "SELECT ep.empresa_perfil_id, 
                   ep.empresa_perfil_nombre,
                   ep.perfil_id_base,
                   ep.empresa_id,
                   p.perfil_nombre as perfil_base_nombre
            FROM conf__empresas_perfiles ep
            LEFT JOIN conf__perfiles p ON ep.perfil_id_base = p.perfil_id
            WHERE ep.tabla_estado_registro_id = 1 
            AND ep.modulo_id = $modulo_id 
            AND ep.empresa_id = $empresa_id
            ORDER BY ep.empresa_perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// Obtener todos los usuarios (sin filtro)
function obtenerTodosUsuarios($conexion) {
    $sql = "SELECT usuario_id, usuario_nombre, usuario, email 
            FROM conf__usuarios 
            WHERE tabla_estado_registro_id = 1 
            ORDER BY usuario_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// NUEVA: Obtener asignaciones por usuario
function obtenerAsignacionesPorUsuario($conexion, $usuario_id) {
    if (!$usuario_id) {
        return [];
    }
    
    $usuario_id = intval($usuario_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_nombre, ep.empresa_perfil_id,
            m.modulo, m.modulo_id,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            WHERE up.usuario_id = $usuario_id 
            AND up.tabla_estado_registro_id = 1
            ORDER BY e.empresa, m.modulo, ep.empresa_perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

// NUEVA: Obtener todas las asignaciones de todos los usuarios
function obtenerTodasAsignaciones($conexion) {
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_nombre, ep.empresa_perfil_id,
            m.modulo, m.modulo_id,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            WHERE up.tabla_estado_registro_id = 1
            ORDER BY u.usuario_nombre, e.empresa, m.modulo, ep.empresa_perfil_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionesUsuarioPerfil($conexion, $empresa_perfil_id) {
    if (!$empresa_perfil_id) {
        return [];
    }
    
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_nombre,
            m.modulo, m.modulo_id,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            WHERE up.empresa_perfil_id = $empresa_perfil_id 
            AND up.tabla_estado_registro_id = 1
            ORDER BY up.fecha_inicio DESC";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionPorId($conexion, $usuario_perfil_id) {
    if (!$usuario_perfil_id) {
        return null;
    }
    
    $usuario_perfil_id = intval($usuario_perfil_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_id, ep.empresa_perfil_nombre,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            WHERE up.usuario_perfil_id = $usuario_perfil_id";
    
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    
    return null;
}

function obtenerAsignacionesPorEmpresa($conexion, $empresa_id) {
    if (!$empresa_id) {
        return [];
    }
    
    $empresa_id = intval($empresa_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_nombre, ep.empresa_perfil_id,
            m.modulo, m.modulo_id,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            WHERE e.empresa_id = $empresa_id 
            AND up.tabla_estado_registro_id = 1
            ORDER BY m.modulo, ep.empresa_perfil_nombre, u.usuario_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionesPorModuloEmpresa($conexion, $empresa_id, $modulo_id) {
    if (!$empresa_id || !$modulo_id) {
        return [];
    }
    
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            e.empresa, e.empresa_id,
            ep.empresa_perfil_nombre, ep.empresa_perfil_id,
            m.modulo, m.modulo_id,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            WHERE e.empresa_id = $empresa_id 
            AND m.modulo_id = $modulo_id
            AND up.tabla_estado_registro_id = 1
            ORDER BY ep.empresa_perfil_nombre, u.usuario_nombre";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function asignarUsuarioAPerfil($conexion, $usuario_id, $empresa_perfil_id, $fecha_inicio, $fecha_fin, $usuario_creacion) {
    $usuario_id = intval($usuario_id);
    $empresa_perfil_id = intval($empresa_perfil_id);
    $usuario_creacion = intval($usuario_creacion);
    
    $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
    $fecha_fin = mysqli_real_escape_string($conexion, $fecha_fin);
    
    $sql = "INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, usuario_creacion) 
            VALUES ($usuario_id, $empresa_perfil_id, '$fecha_inicio', '$fecha_fin', $usuario_creacion)";
    
    return mysqli_query($conexion, $sql);
}

function actualizarAsignacionUsuarioPerfil($conexion, $usuario_perfil_id, $fecha_inicio, $fecha_fin, $usuario_actualizacion) {
    $usuario_perfil_id = intval($usuario_perfil_id);
    $usuario_actualizacion = intval($usuario_actualizacion);
    
    $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
    $fecha_fin = mysqli_real_escape_string($conexion, $fecha_fin);
    
    $sql = "UPDATE conf__usuarios_perfiles 
            SET fecha_inicio = '$fecha_inicio', fecha_fin = '$fecha_fin', 
                usuario_actualizacion = $usuario_actualizacion, fecha_actualizacion = NOW()
            WHERE usuario_perfil_id = $usuario_perfil_id";
    
    return mysqli_query($conexion, $sql);
}

function eliminarAsignacionUsuarioPerfil($conexion, $usuario_perfil_id) {
    $usuario_perfil_id = intval($usuario_perfil_id);
    
    $sql = "UPDATE conf__usuarios_perfiles SET tabla_estado_registro_id = 2 WHERE usuario_perfil_id = $usuario_perfil_id";
    
    return mysqli_query($conexion, $sql);
}

function verificarSolapamientoAsignacion($conexion, $usuario_id, $empresa_perfil_id, $fecha_inicio, $fecha_fin, $excluir_id = null) {
    $usuario_id = intval($usuario_id);
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
    $fecha_fin = mysqli_real_escape_string($conexion, $fecha_fin);
    
    $excluir_condition = "";
    if ($excluir_id) {
        $excluir_id = intval($excluir_id);
        $excluir_condition = "AND usuario_perfil_id != $excluir_id";
    }
    
    $sql = "SELECT COUNT(*) as count FROM conf__usuarios_perfiles 
            WHERE usuario_id = $usuario_id AND empresa_perfil_id = $empresa_perfil_id 
            AND tabla_estado_registro_id = 1 
            AND (
                (fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin') OR
                (fecha_fin BETWEEN '$fecha_inicio' AND '$fecha_fin') OR
                (fecha_inicio <= '$fecha_inicio' AND fecha_fin >= '$fecha_fin')
            )
            $excluir_condition";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return false;
    }
    
    $fila = mysqli_fetch_assoc($res);
    
    return $fila['count'] > 0;
}
function obtenerPaginasPorModulo($conexion, $modulo_id) {
    if (!$modulo_id) {
        return [];
    }
    
    $modulo_id = intval($modulo_id);
    
    // Obtener páginas principales (padre_id = 0)
    $sql = "SELECT p.*, m.modulo
            FROM conf__paginas p
            INNER JOIN conf__modulos m ON p.modulo_id = m.modulo_id
            WHERE p.modulo_id = $modulo_id 
            AND p.padre_id = 0
            AND p.tabla_estado_registro_id = 1
            ORDER BY p.orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $paginas = [];
    while ($pagina = mysqli_fetch_assoc($res)) {
        // Obtener subpáginas (hijos)
        $pagina_id = $pagina['pagina_id'];
        $sql_hijos = "SELECT * FROM conf__paginas 
                      WHERE padre_id = $pagina_id 
                      AND tabla_estado_registro_id = 1
                      ORDER BY orden";
        
        $res_hijos = mysqli_query($conexion, $sql_hijos);
        $subpaginas = [];
        
        if ($res_hijos) {
            while ($subpagina = mysqli_fetch_assoc($res_hijos)) {
                $subpaginas[] = $subpagina;
            }
        }
        
        $pagina['subpaginas'] = $subpaginas;
        $paginas[] = $pagina;
    }
    
    return $paginas;
}

// NUEVA: Obtener funciones por página
function obtenerFuncionesPorPagina($conexion, $pagina_id) {
    if (!$pagina_id) {
        return [];
    }
    
    $pagina_id = intval($pagina_id);
    
    $sql = "SELECT * FROM conf__paginas_funciones 
            WHERE pagina_id = $pagina_id 
            AND tabla_estado_registro_id = 1
            ORDER BY orden";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $funciones = [];
    while ($funcion = mysqli_fetch_assoc($res)) {
        $funciones[] = $funcion;
    }
    
    return $funciones;
}

// NUEVA: Obtener funciones asignadas a un perfil
function obtenerFuncionesAsignadasPerfil($conexion, $empresa_perfil_id) {
    if (!$empresa_perfil_id) {
        return [];
    }
    
    $empresa_perfil_id = intval($empresa_perfil_id);
    
    $sql = "SELECT epf.*, pf.nombre_funcion, pf.pagina_id, pf.descripcion
            FROM conf__empresas_perfiles_funciones epf
            INNER JOIN conf__paginas_funciones pf ON epf.pagina_funcion_id = pf.pagina_funcion_id
            WHERE epf.empresa_perfil_id = $empresa_perfil_id 
            AND epf.asignado = 1";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $funciones = [];
    while ($funcion = mysqli_fetch_assoc($res)) {
        $funciones[] = $funcion;
    }
    
    return $funciones;
}

// NUEVA: Obtener vista detallada de permisos por usuario
function obtenerVistaDetalladaPermisosUsuario($conexion, $usuario_id) {
    if (!$usuario_id) {
        return [];
    }
    
    $usuario_id = intval($usuario_id);
    
    // Obtener todas las asignaciones de perfiles del usuario
    $sql_asignaciones = "SELECT up.empresa_perfil_id, ep.empresa_perfil_nombre, 
                                ep.modulo_id, m.modulo, e.empresa
                         FROM conf__usuarios_perfiles up
                         INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
                         INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
                         INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
                         WHERE up.usuario_id = $usuario_id 
                         AND up.tabla_estado_registro_id = 1
                         AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin";
    
    $res_asignaciones = mysqli_query($conexion, $sql_asignaciones);
    
    if (!$res_asignaciones) {
        return [];
    }
    
    $resultado = [];
    
    while ($asignacion = mysqli_fetch_assoc($res_asignaciones)) {
        $modulo_id = $asignacion['modulo_id'];
        $empresa_perfil_id = $asignacion['empresa_perfil_id'];
        
        // Obtener páginas del módulo
        $paginas = obtenerPaginasPorModulo($conexion, $modulo_id);
        
        // Obtener funciones asignadas al perfil
        $funciones_asignadas = obtenerFuncionesAsignadasPerfil($conexion, $empresa_perfil_id);
        
        // Crear array de IDs de funciones asignadas para búsqueda rápida
        $funciones_asignadas_ids = [];
        foreach ($funciones_asignadas as $funcion) {
            $funciones_asignadas_ids[$funcion['pagina_funcion_id']] = true;
        }
        
        // Procesar páginas y sus funciones
        $paginas_procesadas = [];
        foreach ($paginas as $pagina) {
            $pagina_id = $pagina['pagina_id'];
            
            // Obtener funciones de esta página
            $funciones_pagina = obtenerFuncionesPorPagina($conexion, $pagina_id);
            
            $funciones_procesadas = [];
            foreach ($funciones_pagina as $funcion) {
                $funcion_id = $funcion['pagina_funcion_id'];
                
                $funciones_procesadas[] = [
                    'pagina_funcion_id' => $funcion_id,
                    'nombre_funcion' => $funcion['nombre_funcion'],
                    'descripcion' => $funcion['descripcion'],
                    'asignada' => isset($funciones_asignadas_ids[$funcion_id]),
                    'accion_js' => $funcion['accion_js']
                ];
            }
            
            // Procesar subpáginas si existen
            $subpaginas_procesadas = [];
            if (!empty($pagina['subpaginas'])) {
                foreach ($pagina['subpaginas'] as $subpagina) {
                    $subpagina_id = $subpagina['pagina_id'];
                    $funciones_subpagina = obtenerFuncionesPorPagina($conexion, $subpagina_id);
                    
                    $funciones_subprocesadas = [];
                    foreach ($funciones_subpagina as $funcion) {
                        $funcion_id = $funcion['pagina_funcion_id'];
                        
                        $funciones_subprocesadas[] = [
                            'pagina_funcion_id' => $funcion_id,
                            'nombre_funcion' => $funcion['nombre_funcion'],
                            'descripcion' => $funcion['descripcion'],
                            'asignada' => isset($funciones_asignadas_ids[$funcion_id]),
                            'accion_js' => $funcion['accion_js']
                        ];
                    }
                    
                    $subpaginas_procesadas[] = [
                        'pagina_id' => $subpagina_id,
                        'pagina' => $subpagina['pagina'],
                        'url' => $subpagina['url'],
                        'funciones' => $funciones_subprocesadas
                    ];
                }
            }
            
            $paginas_procesadas[] = [
                'pagina_id' => $pagina_id,
                'pagina' => $pagina['pagina'],
                'url' => $pagina['url'],
                'funciones' => $funciones_procesadas,
                'subpaginas' => $subpaginas_procesadas
            ];
        }
        
        $resultado[] = [
            'empresa_perfil_id' => $empresa_perfil_id,
            'empresa_perfil_nombre' => $asignacion['empresa_perfil_nombre'],
            'empresa' => $asignacion['empresa'],
            'modulo_id' => $modulo_id,
            'modulo' => $asignacion['modulo'],
            'paginas' => $paginas_procesadas,
            'total_funciones' => count($funciones_asignadas_ids),
            'total_funciones_modulo' => count($funciones_asignadas) // Solo las asignadas
        ];
    }
    
    return $resultado;
}

// NUEVA: Obtener vista detallada para todos los perfiles de un usuario
function obtenerVistaDetalladaTodosPerfilesUsuario($conexion, $usuario_id) {
    return obtenerVistaDetalladaPermisosUsuario($conexion, $usuario_id);
}

// NUEVA: Obtener resumen de permisos por usuario
function obtenerResumenPermisosUsuario($conexion, $usuario_id) {
    if (!$usuario_id) {
        return [];
    }
    
    $usuario_id = intval($usuario_id);
    
    $sql = "SELECT m.modulo_id, m.modulo, 
                   COUNT(DISTINCT epf.pagina_funcion_id) as funciones_asignadas,
                   COUNT(DISTINCT pf.pagina_funcion_id) as total_funciones_modulo,
                   e.empresa,
                   ep.empresa_perfil_nombre
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__empresas_perfiles ep ON up.empresa_perfil_id = ep.empresa_perfil_id
            INNER JOIN conf__modulos m ON ep.modulo_id = m.modulo_id
            INNER JOIN conf__empresas e ON ep.empresa_id = e.empresa_id
            LEFT JOIN conf__empresas_perfiles_funciones epf ON ep.empresa_perfil_id = epf.empresa_perfil_id 
                AND epf.asignado = 1
            LEFT JOIN conf__paginas_funciones pf ON m.modulo_id = (
                SELECT p.modulo_id FROM conf__paginas p 
                WHERE p.pagina_id = pf.pagina_id
                LIMIT 1
            )
            WHERE up.usuario_id = $usuario_id 
            AND up.tabla_estado_registro_id = 1
            AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
            GROUP BY m.modulo_id, m.modulo, e.empresa_id, ep.empresa_perfil_id
            ORDER BY e.empresa, m.modulo";
    
    $res = mysqli_query($conexion, $sql);
    
    if (!$res) {
        return [];
    }
    
    $resultado = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $resultado[] = $fila;
    }
    
    return $resultado;
}
?>