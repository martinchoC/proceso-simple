<?php
require_once __DIR__ . '/../db.php';

echo "=== MODULOS ===" . PHP_EOL;
$res = mysqli_query($conn, "SELECT * FROM conf__modulos ORDER BY modulo_id");
while($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['modulo_id']} | Codigo: {$row['codigo']} | Nombre: {$row['modulo']}" . PHP_EOL;
}

echo PHP_EOL . "=== PAGINAS POR MODULO ===" . PHP_EOL;
$resP = mysqli_query($conn, "SELECT p.pagina_id, p.modulo_id, m.modulo, p.pagina, p.url, p.padre_id, p.orden 
                             FROM conf__paginas p 
                             LEFT JOIN conf__modulos m ON p.modulo_id = m.modulo_id 
                             ORDER BY p.modulo_id, p.padre_id, p.orden");
while($row = mysqli_fetch_assoc($resP)) {
    echo sprintf("[%d] Mod: %-12s | Padre: %-4s | %-40s | URL: %s\n", 
        $row['pagina_id'], $row['modulo'] ?? 'N/A', $row['padre_id'] ?? '-', $row['pagina'], $row['url']);
}

echo PHP_EOL . "=== TABLAS GESTION ===" . PHP_EOL;
$resT = mysqli_query($conn, "SHOW TABLES LIKE 'gestion__%'");
$gTables = [];
while($r = mysqli_fetch_row($resT)) {
    $gTables[] = $r[0];
}
echo "Total gestion tables: " . count($gTables) . PHP_EOL;
foreach($gTables as $t) {
    echo "  - $t\n";
}

echo PHP_EOL . "=== TABLAS CONF ===" . PHP_EOL;
$resC = mysqli_query($conn, "SHOW TABLES LIKE 'conf__%'");
$cTables = [];
while($r = mysqli_fetch_row($resC)) {
    $cTables[] = $r[0];
}
echo "Total conf tables: " . count($cTables) . PHP_EOL;
foreach($cTables as $t) {
    echo "  - $t\n";
}

echo PHP_EOL . "=== TABLAS CONT ===" . PHP_EOL;
$resCont = mysqli_query($conn, "SHOW TABLES LIKE 'cont_%'");
$contTables = [];
while($r = mysqli_fetch_row($resCont)) {
    $contTables[] = $r[0];
}
echo "Total cont tables: " . count($contTables) . PHP_EOL;
foreach($contTables as $t) {
    echo "  - $t\n";
}
