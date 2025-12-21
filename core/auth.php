<?php
require_once __DIR__ . '/../config/db.php';

function validar_sesion($sid) {
    global $conn;
    $sid = mysqli_real_escape_string($conn, $sid);

    $sql = "SELECT u.*, s.empresa_id, s.creado_en, s.ultimo_acceso FROM conf__usuarios_sesiones s
            JOIN conf__usuarios u ON s.usuario_id = u.usuario_id
            WHERE s.sid = '$sid'";

    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) === 1) {
        $sesion = mysqli_fetch_assoc($res);

        // Validar duración de sesión según duracion_sid_minutos
        $creado = strtotime($sesion['creado_en']);
        $duracion = intval($sesion['duracion_sid_minutos']);
        $ahora = time();

        if (($ahora - $creado) > ($duracion * 60)) {
            // Sesión expirada
            // Opcional: borrar sesión de BD
            mysqli_query($conn, "DELETE FROM conf__usuarios_sesiones WHERE sid = '$sid'");
            return false;
        }

        // Actualizar último acceso
        mysqli_query($conn, "UPDATE conf__usuarios_sesiones SET ultimo_acceso = NOW() WHERE sid = '$sid'");

        return $sesion;
    }
    return false;
}
/*
function validar_acceso_pagina($usuario_id, $empresa_id, $pagina_id) {
    global $conn;

    $usuario_id = intval($usuario_id);
    $empresa_id = intval($empresa_id);
    $pagina_id = intval($pagina_id);

    $sql = "
        SELECT 1
        FROM conf__usuarios_perfiles up
        JOIN conf__perfiles p ON up.perfil_id = p.id
        JOIN conf__paginas_perfiles pp ON pp.perfil_id = p.id
        WHERE up.usuario_id = $usuario_id
          AND p.empresa_id = $empresa_id
          AND pp.pagina_id = $pagina_id
        LIMIT 1
    ";

    $res = mysqli_query($conn, $sql);
    return ($res && mysqli_num_rows($res) === 1);
}

function validar_funcion_en_pagina($perfil_id, $pagina_id, $funcion_id) {
    global $conn;

    $perfil_id = intval($perfil_id);
    $pagina_id = intval($pagina_id);
    $funcion_id = intval($funcion_id);

    $sql = "
        SELECT 1
        FROM conf__paginas_funciones pf
        JOIN conf__perfiles_funciones pf2 ON pf2.funcion_id = pf.id
        WHERE pf.pagina_id = $pagina_id
          AND pf2.perfil_id = $perfil_id
          AND pf.id = $funcion_id
        LIMIT 1
    ";

    $res = mysqli_query($conn, $sql);
    return ($res && mysqli_num_rows($res) === 1);
}
