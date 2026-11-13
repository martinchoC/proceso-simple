<?php
require_once __DIR__ . '/../../db.php';

mysqli_begin_transaction($conn);

try {
    echo "1. Ajustando conf__paginas para estructura limpia y visible de menú...\n";
    // 1.1 Registrar secciones contenedoras si no existen
    // Sección 400: Clientes y Vencimientos
    mysqli_query($conn, "UPDATE conf__paginas SET pagina = 'Clientes y Vencimientos', icono_id = 75, orden = 2, padre_id = 0 WHERE pagina_id = 400");

    // Sección 420: Honorarios y Facturación
    $chk_420 = mysqli_query($conn, "SELECT pagina_id FROM conf__paginas WHERE pagina_id = 420");
    if (mysqli_num_rows($chk_420) === 0) {
        mysqli_query($conn, "INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
                             VALUES (420, 4, 'Honorarios y Facturación', '#', 78, 'Abonos periódicos y facturación masiva', 0, 3, 0, 0, 1, 0)");
    } else {
        mysqli_query($conn, "UPDATE conf__paginas SET modulo_id = 4, pagina = 'Honorarios y Facturación', url = '#', icono_id = 78, padre_id = 0, orden = 3, tabla_estado_registro_id = 1 WHERE pagina_id = 420");
    }

    // Sección 430: Matriz RACI y Licencias
    $chk_430 = mysqli_query($conn, "SELECT pagina_id FROM conf__paginas WHERE pagina_id = 430");
    if (mysqli_num_rows($chk_430) === 0) {
        mysqli_query($conn, "INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
                             VALUES (430, 4, 'Operaciones y Equipos', '#', 81, 'Matriz de tareas RACI y licencias de personal', 0, 4, 0, 0, 1, 0)");
    } else {
        mysqli_query($conn, "UPDATE conf__paginas SET modulo_id = 4, pagina = 'Operaciones y Equipos', url = '#', icono_id = 81, padre_id = 0, orden = 4, tabla_estado_registro_id = 1 WHERE pagina_id = 430");
    }

    // Sección 440: Normas, Auditoría y Activos
    $chk_440 = mysqli_query($conn, "SELECT pagina_id FROM conf__paginas WHERE pagina_id = 440");
    if (mysqli_num_rows($chk_440) === 0) {
        mysqli_query($conn, "INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, icono_id, pagina_descripcion, padre_id, orden, tabla_id, pagina_patron_id, tabla_estado_registro_id, es_acceso_directo)
                             VALUES (440, 4, 'Auditoría, Normas y Activos', '#', 80, 'Normas legales, amortizaciones y consola fiscal', 0, 5, 0, 0, 1, 0)");
    } else {
        mysqli_query($conn, "UPDATE conf__paginas SET modulo_id = 4, pagina = 'Auditoría, Normas y Activos', url = '#', icono_id = 80, padre_id = 0, orden = 5, tabla_estado_registro_id = 1 WHERE pagina_id = 440");
    }

    // 1.2 Dashboard Estudio como elemento directo en la raíz (padre_id = 0)
    mysqli_query($conn, "UPDATE conf__paginas SET 
                            padre_id = 0, 
                            url = 'dashboard_estudio.php', 
                            orden = 1, 
                            es_acceso_directo = 1, 
                            orden_acceso_directo = 1 
                         WHERE pagina_id = 401");

    // 1.3 Asignar páginas hijas a sus respectivas secciones temáticas
    // Clientes y Vencimientos (padre 400)
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 400, orden = 1, es_acceso_directo = 1, orden_acceso_directo = 2 WHERE pagina_id = 402"); // Padron Clientes
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 400, orden = 2, es_acceso_directo = 1, orden_acceso_directo = 3 WHERE pagina_id = 403"); // Tablero Vencimientos
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 400, orden = 3 WHERE pagina_id = 404"); // Generador Vencimientos

    // Honorarios y Facturación (padre 420)
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 420, orden = 1 WHERE pagina_id = 405"); // Tarifarios
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 420, orden = 2, es_acceso_directo = 1, orden_acceso_directo = 4 WHERE pagina_id = 406"); // Facturacion Abonos

    // Operaciones y Equipos (padre 430)
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 430, orden = 1, es_acceso_directo = 1, orden_acceso_directo = 5 WHERE pagina_id = 408"); // Tareas RACI
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 430, orden = 2 WHERE pagina_id = 409"); // Licencias

    // Auditoría, Normas y Activos (padre 440)
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 440, orden = 1 WHERE pagina_id = 407"); // Normas
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 440, orden = 2 WHERE pagina_id = 410"); // Activos Fijos
    mysqli_query($conn, "UPDATE conf__paginas SET padre_id = 440, orden = 3 WHERE pagina_id = 411"); // Consola Auditoria

    echo "2. Registrando conf__empresas_perfiles para empresa 2 (Casa Lucho) modulo 4...\n";
    $perfiles_mod4 = [
        ['base' => 401, 'nombre' => 'Administrador Estudio Contable'],
        ['base' => 402, 'nombre' => 'Contador / Liquidador'],
        ['base' => 403, 'nombre' => 'Auditor Fiscal']
    ];

    $ep_admin_id = 0;
    foreach ($perfiles_mod4 as $p) {
        $chk_ep = mysqli_query($conn, "SELECT empresa_perfil_id FROM conf__empresas_perfiles WHERE empresa_id = 2 AND modulo_id = 4 AND perfil_id_base = {$p['base']}");
        if (mysqli_num_rows($chk_ep) === 0) {
            mysqli_query($conn, "INSERT INTO conf__empresas_perfiles (empresa_id, modulo_id, perfil_id_base, empresa_perfil_nombre, tabla_estado_registro_id)
                                 VALUES (2, 4, {$p['base']}, '{$p['nombre']}', 1)");
            $current_ep_id = mysqli_insert_id($conn);
        } else {
            $row_ep = mysqli_fetch_assoc($chk_ep);
            $current_ep_id = (int)$row_ep['empresa_perfil_id'];
            mysqli_query($conn, "UPDATE conf__empresas_perfiles SET tabla_estado_registro_id = 1, empresa_perfil_nombre = '{$p['nombre']}' WHERE empresa_perfil_id = {$current_ep_id}");
        }
        if ($p['base'] === 401) {
            $ep_admin_id = $current_ep_id;
        }
    }

    echo "3. Asignando perfil de Administrador Estudio Contable a Pablo (usuario_id = 1) en conf__usuarios_perfiles...\n";
    if ($ep_admin_id > 0) {
        $chk_up = mysqli_query($conn, "SELECT usuario_perfil_id FROM conf__usuarios_perfiles WHERE usuario_id = 1 AND empresa_perfil_id = {$ep_admin_id}");
        if (mysqli_num_rows($chk_up) === 0) {
            mysqli_query($conn, "INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, fecha_creacion, usuario_creacion, tabla_estado_registro_id)
                                 VALUES (1, {$ep_admin_id}, '2024-01-01', '2035-12-31', NOW(), 1, 1)");
        } else {
            mysqli_query($conn, "UPDATE conf__usuarios_perfiles SET tabla_estado_registro_id = 1, fecha_fin = '2035-12-31' WHERE usuario_id = 1 AND empresa_perfil_id = {$ep_admin_id}");
        }
    }

    echo "4. Sembrando conf__paginas_funciones para páginas 401..411 y contenedores...\n";
    $paginas_ids = [401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411];
    $funciones_std = [
        ['nombre' => 'Acceso / Ver', 'accion' => 'ver'],
        ['nombre' => 'Agregar', 'accion' => 'agregar'],
        ['nombre' => 'Editar', 'accion' => 'editar'],
        ['nombre' => 'Inhabilitar / Eliminar', 'accion' => 'inhabilitar']
    ];

    foreach ($paginas_ids as $pid) {
        foreach ($funciones_std as $fn_idx => $fn) {
            $chk_pf = mysqli_query($conn, "SELECT pagina_funcion_id FROM conf__paginas_funciones WHERE pagina_id = {$pid} AND accion_js = '{$fn['accion']}'");
            if (mysqli_num_rows($chk_pf) === 0) {
                mysqli_query($conn, "INSERT INTO conf__paginas_funciones (pagina_id, icono_id, color_id, funcion_estandar_id, nombre_funcion, accion_js, orden, tabla_estado_registro_id)
                                     VALUES ({$pid}, 42, 1, 1, '{$fn['nombre']}', '{$fn['accion']}', " . ($fn_idx + 1) . ", 1)");
            }
        }
    }

    echo "5. Asignando todas las funciones en conf__empresas_perfiles_funciones al perfil de Administrador...\n";
    if ($ep_admin_id > 0) {
        $res_all_pf = mysqli_query($conn, "SELECT pf.pagina_funcion_id 
                                            FROM conf__paginas_funciones pf 
                                            INNER JOIN conf__paginas p ON p.pagina_id = pf.pagina_id 
                                            WHERE p.modulo_id = 4");
        while ($r_pf = mysqli_fetch_assoc($res_all_pf)) {
            $pf_id = (int)$r_pf['pagina_funcion_id'];
            $chk_epf = mysqli_query($conn, "SELECT empresa_perfil_funcion_id FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = {$ep_admin_id} AND pagina_funcion_id = {$pf_id}");
            if (mysqli_num_rows($chk_epf) === 0) {
                mysqli_query($conn, "INSERT INTO conf__empresas_perfiles_funciones (empresa_perfil_id, pagina_funcion_id, asignado)
                                     VALUES ({$ep_admin_id}, {$pf_id}, 1)");
            } else {
                mysqli_query($conn, "UPDATE conf__empresas_perfiles_funciones SET asignado = 1 WHERE empresa_perfil_id = {$ep_admin_id} AND pagina_funcion_id = {$pf_id}");
            }
        }
    }

    mysqli_commit($conn);
    echo "¡Ajuste completado con éxito absoluto!\n";
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo "ERROR: " . $e->getMessage() . "\n";
}
