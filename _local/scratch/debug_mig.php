<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require 'db.php';
    echo "1. Insert pagina...\n";
    $checkPag = mysqli_query($conn, "SELECT pagina_id FROM conf__paginas WHERE pagina_id = 111");
    if (mysqli_num_rows($checkPag) === 0) {
        mysqli_query($conn, "INSERT INTO conf__paginas (pagina_id, padre_id, pagina, url, modulo_id, orden, icono, tabla_estado_registro_id) 
                             VALUES (111, 37, 'Parámetros de Empresa', 'empresas_parametros.php', 2, 405, 'fas fa-sliders-h', 1)");
        echo "Pagina 111 insertada\n";
    } else {
        echo "Pagina 111 ya existe\n";
    }
} catch (Exception $e) {
    echo "EXCEPCION: " . $e->getMessage() . "\n";
}
