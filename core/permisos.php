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
 * Páginas a las que el usuario tiene acceso dentro de una empresa.
 *
 * La única fuente de verdad es conf__empresas_perfiles_funciones (asignado = 1 para ese
 * empresa_perfil_id): es el mismo criterio que ya usa el panel de administración de
 * permisos para decidir qué función está "asignada" (ver
 * modules/conf/empresas_perfiles_funciones_model.php, función
 * obtenerPaginasFuncionesPorEmpresaPerfil / obtenerEstructuraCompletaPorEmpresaPerfil:
 * ahí "asignada" es 1 únicamente si existe la fila en empresas_perfiles_funciones, sin
 * mirar nunca conf__perfiles_funciones). Esa tabla base solo se usa como plantilla para
 * sembrar empresas_perfiles_funciones al crear un empresa_perfil
 * (heredarPaginasFuncionesDesdePerfilBase); no es un fallback en tiempo de ejecución, así
 * que acá tampoco se consulta.
 *
 * No hay "acceso libre" para páginas sin funciones definidas: si una página de negocio
 * (tabla_id != 0) todavía no tiene funciones cargadas, queda denegada hasta que se
 * configure. Los menús contenedores (páginas con hijos en conf__paginas) no necesitan
 * su propio permiso: header1.php los muestra u oculta según si al menos uno de sus hijos
 * está permitido, independientemente de esta lista.
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

    $perfiles = usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id);
    if (empty($perfiles)) {
        return $paginas_permitidas;
    }

    $sql = "SELECT DISTINCT pf.pagina_id
            FROM conf__paginas_funciones pf
            INNER JOIN conf__empresas_perfiles_funciones epf
                ON epf.pagina_funcion_id = pf.pagina_funcion_id AND epf.empresa_perfil_id = ?
            WHERE pf.tabla_estado_registro_id = 1
            AND epf.asignado = 1";
    $stmt = mysqli_prepare($conexion, $sql);

    foreach ($perfiles as $perfil) {
        mysqli_stmt_bind_param($stmt, "i", $perfil['empresa_perfil_id']);
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

/**
 * Empresas y módulos habilitados para el usuario según los perfiles que tiene asignados:
 * no "todos los módulos habilitados en la empresa", sino específicamente el módulo de cada
 * conf__empresas_perfiles del usuario (una fila de conf__usuarios_perfiles apunta a un
 * empresa_perfil, y ese empresa_perfil tiene un modulo_id propio). Se usa tanto para el
 * selector de empresa/módulo del sidebar como para decidir el redirect automático de
 * index.php cuando el usuario solo tiene una combinación posible.
 *
 * @return array<int,array{nombre:string,modulos:array<int,array{id:int,nombre:string,url:string}>}>
 */
function usuario_obtener_empresas_modulos(mysqli $conexion, int $usuario_id): array
{
    $empresas_modulos = [];

    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        $res = mysqli_query($conexion, "
            SELECT e.empresa_id, e.empresa, m.modulo_id, m.modulo, m.modulo_url
            FROM conf__empresas e
            INNER JOIN conf__empresas_modulos em ON em.empresa_id = e.empresa_id AND em.tabla_estado_registro_id = 1
            INNER JOIN conf__modulos m ON m.modulo_id = em.modulo_id AND m.tabla_estado_registro_id = 1
            WHERE e.tabla_estado_registro_id = 1
            ORDER BY e.empresa, m.modulo
        ");
    } else {
        $sql = "SELECT DISTINCT
                    e.empresa_id, e.empresa,
                    m.modulo_id, m.modulo, m.modulo_url
                FROM conf__usuarios_perfiles up
                INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                INNER JOIN conf__empresas e ON e.empresa_id = ep.empresa_id
                INNER JOIN conf__empresas_modulos em
                    ON em.empresa_id = ep.empresa_id AND em.modulo_id = ep.modulo_id
                    AND em.tabla_estado_registro_id = 1
                INNER JOIN conf__modulos m ON m.modulo_id = em.modulo_id AND m.tabla_estado_registro_id = 1
                WHERE up.usuario_id = ?
                  AND up.tabla_estado_registro_id = 1
                  AND ep.tabla_estado_registro_id = 1
                  AND e.tabla_estado_registro_id = 1
                  AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
                ORDER BY e.empresa, m.modulo";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $usuario_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $eid = (int) $row['empresa_id'];
        if (!isset($empresas_modulos[$eid])) {
            $empresas_modulos[$eid] = [
                'nombre' => $row['empresa'],
                'modulos' => [],
            ];
        }
        $empresas_modulos[$eid]['modulos'][] = [
            'id' => (int) $row['modulo_id'],
            'nombre' => $row['modulo'],
            'url' => $row['modulo_url'],
        ];
    }

    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

    return $empresas_modulos;
}
