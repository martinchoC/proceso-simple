<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT pagina_id, pagina, url, padre_id, orden, es_acceso_directo FROM conf__paginas WHERE modulo_id = 2 AND padre_id = 0 ORDER BY orden");
echo "=== PAGINAS PADRE_ID = 0 EN MODULO 2 (GESTION) ===\n";
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['pagina_id']} | Pagina: {$r['pagina']} | URL: {$r['url']}\n";
    $sub = mysqli_query($conn, "SELECT pagina_id, pagina, url FROM conf__paginas WHERE padre_id = {$r['pagina_id']} ORDER BY orden");
    while ($s = mysqli_fetch_assoc($sub)) {
        echo "   -> Sub ID: {$s['pagina_id']} | Pagina: {$s['pagina']} | URL: {$s['url']}\n";
    }
}
