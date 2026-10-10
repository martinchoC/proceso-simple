<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM conf__paginas_funciones WHERE pagina_id IN (402, 410)");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
