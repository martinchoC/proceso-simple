<?php
echo "hola";
/*
function cargar_plantilla($vista_php) {
    ob_start();
    include $vista_php;
    $contenido = ob_get_clean();
    include 'templates/adminlte4/layout.php';
}

function cargar_layout_modulo($modulo_id, $contenido_path) {
    global $conn;

    $sql = "SELECT layout_nombre FROM conf__modulos WHERE id = $modulo_id";
    $res = mysqli_query($conn, $sql);
    $layout = 'adminlte4'; // por defecto

    if ($res && $row = mysqli_fetch_assoc($res)) {
        $layout = $row['layout_nombre'];
    }

    ob_start();
    include $contenido_path;
    $contenido = ob_get_clean();

    include __DIR__ . "/../templates/$layout/layout.php";
}
function obtener_menu_para_usuario($usuario_id, $empresa_id, $modulo_id) {
    global $conn;

    $usuario_id = intval($usuario_id);
    $empresa_id = intval($empresa_id);
    $modulo_id = intval($modulo_id);

    $sql = "
        SELECT DISTINCT p.pagina_id, p.nombre, p.ruta
        FROM conf__paginas p
        JOIN conf__paginas_perfiles pp ON pp.pagina_id = p.pagina_id
        JOIN conf__perfiles perf ON perf.id = pp.perfil_id
        JOIN conf__usuarios_perfiles up ON up.perfil_id = perf.id
        WHERE up.usuario_id = $usuario_id
          AND perf.empresa_id = $empresa_id
          AND p.modulo_id = $modulo_id
          AND p.estado = 1
        ORDER BY p.nombre
    ";

    $res = mysqli_query($conn, $sql);
    $menu = [];

    while ($row = mysqli_fetch_assoc($res)) {
        // Generar el pid codificado
        $pid = base64_encode($row['pagina_id'] . '|0'); // 0 función_id para acceso general
        $menu[] = [
            'nombre' => $row['nombre'],
            'url' => $row['ruta'] . '?pid=' . $pid . '&sid=' . $_GET['sid']
        ];
    }

    return $menu;
}
function cargar_layout_modulo($modulo_id, $contenido_path, $usuario) {
    global $conn;

    $sql = "SELECT layout_nombre FROM conf__modulos WHERE id = $modulo_id";
    $res = mysqli_query($conn, $sql);
    $layout = 'adminlte4';

    if ($res && $row = mysqli_fetch_assoc($res)) {
        $layout = $row['layout_nombre'];
    }

    ob_start();
    include $contenido_path;
    $contenido = ob_get_clean();

    // Se pasa $usuario a los componentes del layout
    include __DIR__ . "/../modules/$layout/layout/layout.php";
}
?>