<?php
require_once __DIR__ . '/../../db.php';

$sqlFile = __DIR__ . '/../sql/mantenimiento/20261014_empresas_vinculaciones_cross_tenant.sql';
$sqlContent = file_get_contents($sqlFile);

if (!$sqlContent) {
    die("Error al leer el archivo SQL\n");
}

if (mysqli_multi_query($conn, $sqlContent)) {
    do {
        if ($res = mysqli_store_result($conn)) {
            mysqli_free_result($res);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    echo "Migración ejecutada exitosamente.\n";
} else {
    echo "Error ejecutando migración: " . mysqli_error($conn) . "\n";
}

// Verificación
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'conf__empresas_vinculaciones'");
echo "Tabla conf__empresas_vinculaciones: " . (mysqli_num_rows($checkTable) ? "OK" : "FALTA") . "\n";

$checkPag = mysqli_query($conn, "SELECT pagina_id, pagina FROM conf__paginas WHERE pagina_id IN (123, 623, 1013)");
while ($p = mysqli_fetch_assoc($checkPag)) {
    echo "Pagina {$p['pagina_id']}: {$p['pagina']}\n";
}
