<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT pagina_id, pagina, url, padre_id, orden FROM conf__paginas WHERE modulo_id = 4 ORDER BY padre_id, orden");
while ($row = mysqli_fetch_assoc($res)) {
    echo sprintf("ID: %-4d Padre: %-4d Orden: %-3d URL: %-35s Pagina: %s\n", 
        $row['pagina_id'], $row['padre_id'], $row['orden'], $row['url'], $row['pagina']);
}
