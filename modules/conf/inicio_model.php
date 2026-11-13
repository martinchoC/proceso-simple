<?php
// Pantalla de inicio del módulo conf (index.php): indicadores y puntos de la configuración para revisar.
// Lee todas las empresas a propósito: conf administra el sistema completo, no una empresa.
require_once __DIR__ . '/../../db.php';

function confInicioUno(mysqli $c, string $sql): int
{
    $res = mysqli_query($c, $sql);
    $fila = $res ? mysqli_fetch_row($res) : null;
    return $fila ? (int)$fila[0] : 0;
}

function confInicioLista(mysqli $c, string $sql): array
{
    $res = mysqli_query($c, $sql);
    $data = [];
    while ($res && ($fila = mysqli_fetch_assoc($res))) {
        $data[] = $fila;
    }
    return $data;
}

function confInicioIndicadores(mysqli $c): array
{
    return [
        'empresas' => confInicioUno($c, "SELECT COUNT(*) FROM conf__empresas WHERE tabla_estado_registro_id = 1"),
        'usuarios' => confInicioUno($c, "SELECT COUNT(*) FROM conf__usuarios WHERE tabla_estado_registro_id = 1"),
        'perfiles' => confInicioUno($c, "SELECT COUNT(*) FROM conf__empresas_perfiles WHERE tabla_estado_registro_id = 1"),
        'modulos'  => confInicioUno($c, "SELECT COUNT(*) FROM conf__modulos WHERE tabla_estado_registro_id = 1"),
        'paginas'  => confInicioUno($c,
            "SELECT COUNT(*) FROM conf__paginas p
              WHERE p.tabla_estado_registro_id = 1
                AND NOT EXISTS (SELECT 1 FROM conf__paginas h WHERE h.padre_id = p.pagina_id AND h.tabla_estado_registro_id = 1)"),
        'funciones' => confInicioUno($c,
            "SELECT COUNT(*) FROM conf__paginas_funciones f JOIN conf__paginas p ON p.pagina_id = f.pagina_id
              WHERE f.tabla_estado_registro_id = 1 AND p.tabla_estado_registro_id = 1"),
    ];
}

/**
 * Puntos a revisar. Cada uno: clave, título, detalle, pagina_id (dónde se corrige), url e items (texto).
 * Solo se informan los que tienen algo.
 */
function confInicioRevisar(mysqli $c): array
{
    $puntos = [];
    $agregar = function (string $clave, string $titulo, string $detalle, int $pagina_id, string $url, array $items) use (&$puntos) {
        if ($items) {
            $puntos[] = compact('clave', 'titulo', 'detalle', 'pagina_id', 'url', 'items');
        }
    };

    // Hojas activas del menú (las carpetas tienen hijos)
    $hoja = "p.tabla_estado_registro_id = 1
             AND NOT EXISTS (SELECT 1 FROM conf__paginas h WHERE h.padre_id = p.pagina_id AND h.tabla_estado_registro_id = 1)";

    $filas = confInicioLista($c,
        "SELECT CONCAT(ep.empresa_perfil_nombre, ' — ', e.empresa, ' · ', m.modulo) AS t
           FROM conf__empresas_perfiles ep
           JOIN conf__empresas e ON e.empresa_id = ep.empresa_id AND e.tabla_estado_registro_id = 1
           JOIN conf__modulos m ON m.modulo_id = ep.modulo_id
          WHERE ep.tabla_estado_registro_id = 1
            AND NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones x WHERE x.empresa_perfil_id = ep.empresa_perfil_id AND x.asignado = 1)
          ORDER BY e.empresa, m.modulo_id, ep.empresa_perfil_nombre");
    $agregar('perfiles_sin_funciones', 'Perfiles de empresa sin funciones',
        'Sus usuarios entran al módulo pero no ven ninguna página.', 62, 'empresas_perfiles_funciones.php', array_column($filas, 't'));

    $filas = confInicioLista($c,
        "SELECT CONCAT(u.usuario_nombre, ' (', u.usuario, ')') AS t
           FROM conf__usuarios u
          WHERE u.tabla_estado_registro_id = 1
            AND NOT EXISTS (SELECT 1 FROM conf__usuarios_perfiles up
                             JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
                            WHERE up.usuario_id = u.usuario_id AND up.tabla_estado_registro_id = 1
                              AND (up.fecha_fin IS NULL OR up.fecha_fin >= CURDATE()))
          ORDER BY u.usuario_nombre");
    $agregar('usuarios_sin_perfil', 'Usuarios activos sin perfil',
        'Pueden iniciar sesión pero no tienen ninguna empresa ni módulo.', 26, 'perfiles_usuarios.php', array_column($filas, 't'));

    $filas = confInicioLista($c,
        "SELECT CONCAT(e.empresa) AS t
           FROM conf__empresas e
          WHERE e.tabla_estado_registro_id = 1
            AND NOT EXISTS (SELECT 1 FROM conf__empresas_modulos em WHERE em.empresa_id = e.empresa_id AND em.tabla_estado_registro_id = 1)
          ORDER BY e.empresa");
    $agregar('empresas_sin_modulos', 'Empresas sin módulos',
        'Nadie puede trabajar en ellas hasta habilitarles un módulo.', 60, 'empresas_modulos.php', array_column($filas, 't'));

    $filas = confInicioLista($c,
        "SELECT CONCAT(p.pagina, ' — ', m.modulo, ' (', p.url, ')') AS t
           FROM conf__paginas p JOIN conf__modulos m ON m.modulo_id = p.modulo_id AND m.tabla_estado_registro_id = 1
          WHERE $hoja
            AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id AND f.tabla_estado_registro_id = 1)
          ORDER BY m.modulo_id, p.pagina");
    $agregar('paginas_sin_funciones', 'Páginas sin funciones',
        'Una página sin funciones no se puede asignar a ningún perfil: solo la ve el superadministrador.', 24, 'paginas_funciones.php', array_column($filas, 't'));

    $filas = confInicioLista($c,
        "SELECT CONCAT(p.pagina, ' — ', m.modulo) AS t
           FROM conf__paginas p JOIN conf__modulos m ON m.modulo_id = p.modulo_id AND m.tabla_estado_registro_id = 1
          WHERE $hoja
            AND EXISTS (SELECT 1 FROM conf__paginas_funciones f WHERE f.pagina_id = p.pagina_id AND f.tabla_estado_registro_id = 1)
            AND NOT EXISTS (SELECT 1 FROM conf__paginas_funciones f
                              JOIN conf__empresas_perfiles_funciones x ON x.pagina_funcion_id = f.pagina_funcion_id AND x.asignado = 1
                              JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = x.empresa_perfil_id AND ep.tabla_estado_registro_id = 1
                             WHERE f.pagina_id = p.pagina_id)
          ORDER BY m.modulo_id, p.pagina");
    $agregar('paginas_sin_perfiles', 'Páginas que ningún perfil puede usar',
        'Tienen funciones, pero ningún perfil de empresa activo las tiene asignadas.', 62, 'empresas_perfiles_funciones.php', array_column($filas, 't'));

    // Páginas del menú cuyo archivo no existe en la carpeta del módulo
    $faltan = [];
    $raiz = realpath(__DIR__ . '/../../');
    foreach (confInicioLista($c,
        "SELECT p.pagina, p.url, m.modulo, m.modulo_url
           FROM conf__paginas p JOIN conf__modulos m ON m.modulo_id = p.modulo_id AND m.tabla_estado_registro_id = 1
          WHERE $hoja AND p.url NOT IN ('', '#')
          ORDER BY m.modulo_id, p.pagina") as $p) {
        $archivo = preg_replace('/[?#].*$/', '', (string)$p['url']);
        $carpeta = trim((string)$p['modulo_url'], '/');
        if ($archivo === '' || $carpeta === '') {
            continue;
        }
        if (!is_file($raiz . '/modules/' . $carpeta . '/' . $archivo) && !is_file($raiz . '/' . $archivo)) {
            $faltan[] = $p['pagina'] . ' — ' . $p['modulo'] . ' (' . $p['url'] . ')';
        }
    }
    $agregar('paginas_sin_archivo', 'Páginas del menú que no existen',
        'El menú tiene un enlace a un archivo que no está en el servidor: el usuario ve un error.', 2, 'paginas.php', $faltan);

    return $puntos;
}
