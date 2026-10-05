<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT pagina_id, pagina, url, orden FROM conf__paginas WHERE padre_id = 100 OR pagina_id >= 100 ORDER BY orden");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['pagina_id']} | Pagina: {$r['pagina']} | URL: {$r['url']} | Orden: {$r['orden']}\n";
}
