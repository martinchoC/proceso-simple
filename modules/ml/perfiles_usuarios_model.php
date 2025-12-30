<?php
require_once "conexion.php";

function obtenerModulos($conexion) {
    $sql = "SELECT modulo_id, modulo FROM conf__modulos WHERE estado_registro_id = 1 ORDER BY modulo";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

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

function obtenerUsuarios($conexion) {
    $sql = "SELECT usuario_id, usuario_nombre, email FROM conf__usuarios WHERE estado_registro_id = 1 ORDER BY usuario_nombre";
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionesUsuarioPerfil($conexion, $perfil_id) {
    $perfil_id = intval($perfil_id);
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
             DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            WHERE up.perfil_id = $perfil_id AND up.estado_registro_id = 1
            ORDER BY up.fecha_inicio DESC";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionPorId($conexion, $usuario_perfil_id) {
    $usuario_perfil_id = intval($usuario_perfil_id);
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            WHERE up.usuario_perfil_id = $usuario_perfil_id";
    
    $res = mysqli_query($conexion, $sql);
    
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    
    return null;
}

function asignarUsuarioAPerfil($conexion, $usuario_id, $perfil_id, $fecha_inicio, $fecha_fin, $usuario_creacion) {
    $usuario_id = intval($usuario_id);
    $perfil_id = intval($perfil_id);
    $usuario_creacion = intval($usuario_creacion);
    
    $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
    $fecha_fin = mysqli_real_escape_string($conexion, $fecha_fin);
    
    $sql = "INSERT INTO conf__usuarios_perfiles (usuario_id, perfil_id, fecha_inicio, fecha_fin, usuario_creacion) 
            VALUES ($usuario_id, $perfil_id, '$fecha_inicio', '$fecha_fin', $usuario_creacion)";
    
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
    
    $sql = "UPDATE conf__usuarios_perfiles SET estado_registro_id = 2 WHERE usuario_perfil_id = $usuario_perfil_id";
    
    return mysqli_query($conexion, $sql);
}

function verificarSolapamientoAsignacion($conexion, $usuario_id, $perfil_id, $fecha_inicio, $fecha_fin, $excluir_id = null) {
    $usuario_id = intval($usuario_id);
    $perfil_id = intval($perfil_id);
    
    $fecha_inicio = mysqli_real_escape_string($conexion, $fecha_inicio);
    $fecha_fin = mysqli_real_escape_string($conexion, $fecha_fin);
    
    $excluir_condition = "";
    if ($excluir_id) {
        $excluir_id = intval($excluir_id);
        $excluir_condition = "AND usuario_perfil_id != $excluir_id";
    }
    
    $sql = "SELECT COUNT(*) as count FROM conf__usuarios_perfiles 
            WHERE usuario_id = $usuario_id AND perfil_id = $perfil_id 
            AND estado_registro_id = 1 
            AND (
                (fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin') OR
                (fecha_fin BETWEEN '$fecha_inicio' AND '$fecha_fin') OR
                (fecha_inicio <= '$fecha_inicio' AND fecha_fin >= '$fecha_fin')
            )
            $excluir_condition";
    
    $res = mysqli_query($conexion, $sql);
    $fila = mysqli_fetch_assoc($res);
    
    return $fila['count'] > 0;
}
function obtenerUsuariosPorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    
    $sql = "SELECT DISTINCT u.usuario_id, u.usuario_nombre, u.usuario, u.email
            FROM conf__usuarios u
            INNER JOIN conf__usuarios_perfiles up ON u.usuario_id = up.usuario_id
            INNER JOIN conf__perfiles p ON up.perfil_id = p.perfil_id
            WHERE p.modulo_id = $modulo_id 
            AND u.estado_registro_id = 1 
            AND up.estado_registro_id = 1
            ORDER BY u.usuario_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}

function obtenerAsignacionesPorModulo($conexion, $modulo_id) {
    $modulo_id = intval($modulo_id);
    
    $sql = "SELECT up.*, u.usuario_nombre, u.usuario, u.email, p.perfil_nombre,
            DATE_FORMAT(up.fecha_inicio, '%d/%m/%Y') as fecha_inicio_formateada,
            DATE_FORMAT(up.fecha_fin, '%d/%m/%Y') as fecha_fin_formateada
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__usuarios u ON up.usuario_id = u.usuario_id
            INNER JOIN conf__perfiles p ON up.perfil_id = p.perfil_id
            WHERE p.modulo_id = $modulo_id 
            AND up.estado_registro_id = 1
            ORDER BY p.perfil_nombre, u.usuario_nombre";
    
    $res = mysqli_query($conexion, $sql);
    $data = [];
    while ($fila = mysqli_fetch_assoc($res)) {
        $data[] = $fila;
    }
    return $data;
}
?>