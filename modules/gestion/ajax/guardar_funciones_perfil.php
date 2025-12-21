<?php
require_once '../../../config/db.php';

$perfil_id = intval($_POST['perfil_id']);
$pagina_id = intval($_POST['pagina_id']);
$funciones = $_POST['funciones'] ?? [];

if ($perfil_id > 0 && $pagina_id > 0) {
    // Borrar funciones anteriores de esa página para ese perfil
    $sql_borrar = "DELETE pf 
                   FROM conf__perfiles_funciones pf 
                   JOIN conf__paginas_funciones f ON pf.funcion_id = f.funcion_id 
                   WHERE f.pagina_id = $pagina_id AND pf.perfil_id = $perfil_id";
    mysqli_query($conn, $sql_borrar);

    // Insertar nuevas funciones marcadas
    foreach ($funciones as $funcion_id) {
        $funcion_id = intval($funcion_id);
        mysqli_query($conn, "INSERT INTO conf__perfiles_funciones (perfil_id, funcion_id) VALUES ($perfil_id, $funcion_id)");
    }

    echo "Permisos actualizados correctamente.";
} else {
    echo "Datos inválidos.";
}

