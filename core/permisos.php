<?php
// core/permisos.php
// Motor de control de acceso a páginas según los perfiles activos del usuario en la empresa actual.
// Jerarquía (ver CLAUDE.md):
//   Usuarios --< Usuarios_Perfiles >-- Empresas_Perfiles --< Empresas_Perfiles_Funciones (override)
//                                                        \-- Perfiles --< Perfiles_Funciones (base)

// Superadmin con acceso total, sin pasar por conf__perfiles_funciones / conf__empresas_perfiles_funciones.
// Mientras la carga de funciones por perfil esté incompleta, esto evita que el usuario dueño del sistema
// quede bloqueado de sus propias pantallas. Sacar este bypass una vez que los perfiles estén bien cargados.
const USUARIOS_ACCESO_TOTAL = [1];

/**
 * Perfiles (empresa_perfil) activos y vigentes del usuario dentro de una empresa.
 *
 * @return array<int,array{empresa_perfil_id:int,perfil_id_base:int}>
 */
function usuario_obtener_perfiles_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): array
{
    $sql = "SELECT DISTINCT ep.empresa_perfil_id, ep.perfil_id_base
            FROM conf__usuarios_perfiles up
            INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
            WHERE up.usuario_id = ?
              AND ep.empresa_id = ?
              AND up.tabla_estado_registro_id = 1
              AND ep.tabla_estado_registro_id = 1
              AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $perfiles = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $perfiles[] = [
            'empresa_perfil_id' => (int) $row['empresa_perfil_id'],
            'perfil_id_base' => (int) $row['perfil_id_base'],
        ];
    }
    mysqli_stmt_close($stmt);

    return $perfiles;
}

/**
 * Páginas a las que el usuario tiene acceso dentro de una empresa, combinando el permiso
 * base del perfil (conf__perfiles_funciones) con el override específico de la empresa
 * (conf__empresas_perfiles_funciones, que gana si existe).
 *
 * Solo quedan sin restricción (acceso libre) las páginas sin tabla de negocio asociada
 * (tabla_id = 0/NULL) y sin funciones definidas: son dashboards/menús contenedores puros
 * (p. ej. "Principal", "Ventas", "Compras"), no pantallas de datos. Una página CRUD real
 * (tabla_id != 0) que todavía no tiene funciones cargadas en conf__paginas_funciones NO
 * se considera de acceso libre: es una configuración incompleta, no un permiso abierto, y
 * debe denegarse por defecto hasta que se termine de dar de alta (ver CLAUDE.md: motor de
 * estados / botones).
 *
 * @return array<int,true> pagina_id => true
 */
function usuario_obtener_paginas_permitidas(mysqli $conexion, int $usuario_id, int $empresa_id): array
{
    $paginas_permitidas = [];

    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $res = mysqli_query($conexion, "SELECT pagina_id FROM conf__paginas WHERE tabla_estado_registro_id = 1");
        while ($row = mysqli_fetch_assoc($res)) {
            $paginas_permitidas[(int) $row['pagina_id']] = true;
        }
        return $paginas_permitidas;
    }

    $res = mysqli_query($conexion, "
        SELECT p.pagina_id
        FROM conf__paginas p
        WHERE p.tabla_estado_registro_id = 1
        AND (p.tabla_id IS NULL OR p.tabla_id = 0)
        AND NOT EXISTS (
            SELECT 1 FROM conf__paginas_funciones pf
            WHERE pf.pagina_id = p.pagina_id AND pf.tabla_estado_registro_id = 1
        )
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $paginas_permitidas[(int) $row['pagina_id']] = true;
    }

    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id);
    if (empty($perfiles)) {
        return $paginas_permitidas;
    }

    $sql = "SELECT DISTINCT pf.pagina_id
            FROM conf__paginas_funciones pf
            LEFT JOIN conf__perfiles_funciones bpf
                ON bpf.pagina_funcion_id = pf.pagina_funcion_id AND bpf.perfil_id = ?
            LEFT JOIN conf__empresas_perfiles_funciones epf
                ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.empresa_perfil_id = ?
            WHERE pf.tabla_estado_registro_id = 1
            AND COALESCE(epf.asignado, bpf.asignado, 0) = 1";
    $stmt = mysqli_prepare($conexion, $sql);

    foreach ($perfiles as $perfil) {
        mysqli_stmt_bind_param($stmt, "ii", $perfil['perfil_id_base'], $perfil['empresa_perfil_id']);
        mysqli_stmt_execute($stmt);
        $res_pf = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res_pf)) {
            $paginas_permitidas[(int) $row['pagina_id']] = true;
        }
    }
    mysqli_stmt_close($stmt);

    return $paginas_permitidas;
}

/**
 * Chequeo puntual de acceso a una página (por ejemplo, la página que se está sirviendo).
 * pagina_id <= 0 se considera sin restricción (páginas que todavía no definen $pagina_idx).
 */
function usuario_tiene_acceso_pagina(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): bool
{
    if ($pagina_id <= 0) {
        return true;
    }

    $paginas_permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_id, $empresa_id);
    return isset($paginas_permitidas[$pagina_id]);
}
