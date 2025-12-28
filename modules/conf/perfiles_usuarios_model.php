<?php
require_once "conexion.php";

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
?>