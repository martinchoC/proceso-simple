<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../db.php';

$res = $conn->query("SELECT pagina_id, modulo_id, pagina, url FROM conf__paginas WHERE url LIKE '%entidad%' OR url LIKE '%cliente%' OR url LIKE '%proveedor%' OR pagina LIKE '%entidad%' OR pagina LIKE '%cliente%' OR pagina LIKE '%proveedor%'");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['pagina_id']} | Mod: {$row['modulo_id']} | Nombre: {$row['pagina']} | URL: {$row['url']}\n";
}
