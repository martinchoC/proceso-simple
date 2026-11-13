<?php
/**
 * Cliente activo del estudio: el último cliente elegido en una página del estudio queda en la sesión (por empresa) y
 * las demás páginas con selector de cliente lo preseleccionan (Gestión del Cliente y las páginas a las que lleva:
 * Mini-Gestión, Tablero de Vencimientos, Tareas, Inspecciones, Cierres Contables [por la empresa vinculada del cliente],
 * Contabilidad del Cliente y Libros Societarios). Lo guarda estudio_cliente_activo_ajax.php; el JS común es estudio_cliente_activo.js.
 *
 * Uso en una vista, después de header1.php y antes del JS de la página:
 *   require_once __DIR__ . '/estudio_cliente_activo.php';
 *   estudio_cliente_activo_script((int) $current_empresa_id);
 * y en su JS: EstudioCliente.inicial() para elegir al cargar y EstudioCliente.guardar(id) al cambiar.
 */

/** Páginas que comparten el cliente activo (las que pueden guardarlo). */
const ESTUDIO_CLIENTE_ACTIVO_PAGINAS = ['cliente_gestion.php', 'minigestion.php', 'contabilidad_cierre.php', 'cliente_contabilidad.php',
    'libros_societarios.php', 'vencimientos_tablero.php', 'tareas_raci.php', 'inspecciones.php'];

function estudio_cliente_activo_get(int $empresa_id): int
{
    return (int) ($_SESSION['estudio_cliente_activo'][$empresa_id] ?? 0);
}

function estudio_cliente_activo_set(int $empresa_id, int $cliente_id): void
{
    if ($cliente_id > 0) {
        $_SESSION['estudio_cliente_activo'][$empresa_id] = $cliente_id;
    } else {
        unset($_SESSION['estudio_cliente_activo'][$empresa_id]);
    }
}

/** Imprime el valor inicial y carga el JS común. */
function estudio_cliente_activo_script(int $empresa_id): void
{
    $js = __DIR__ . '/estudio_cliente_activo.js';
    echo '<script>window.ESTUDIO_CLIENTE_ACTIVO = ' . estudio_cliente_activo_get($empresa_id) . ';</script>' . "\n";
    echo '<script src="estudio_cliente_activo.js?v=' . (int) @filemtime($js) . '"></script>' . "\n";
}
