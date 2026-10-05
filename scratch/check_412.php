<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT pagina_id, pagina FROM conf__paginas WHERE pagina_id = 412");
$r = mysqli_fetch_assoc($res);
echo $r ? "412 Occupied: " . $r['pagina'] : "412 is Free!\n";
