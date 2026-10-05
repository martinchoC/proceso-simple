<?php
require_once __DIR__ . '/../db.php';

foreach ([1, 2, 3, 4] as $mid) {
    echo "=== MODULO $mid ===\n";
    $res = mysqli_query($conn, "SELECT pagina_id, pagina, url, padre_id, orden, es_acceso_directo FROM conf__paginas WHERE modulo_id = $mid ORDER BY padre_id, orden");
    while ($r = mysqli_fetch_assoc($res)) {
        echo "ID: {$r['pagina_id']} | Padre: {$r['padre_id']} | Pagina: {$r['pagina']} | URL: {$r['url']} | AccesoDir: {$r['es_acceso_directo']}\n";
    }
}
