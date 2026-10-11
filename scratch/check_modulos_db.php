<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT pagina_id, modulo_id, pagina, url FROM conf__paginas WHERE modulo_id IN (2, 5) ORDER BY pagina_id ASC");
while($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['pagina_id']} | Mod: {$r['modulo_id']} | {$r['pagina']} ({$r['url']})\n";
}
