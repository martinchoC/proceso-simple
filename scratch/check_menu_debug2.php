<?php
require_once __DIR__ . '/../db.php';

echo "=== 1. CONF__PAGINAS PARA MODULO 4 ===\n";
$res_p = mysqli_query($conn, "SELECT pagina_id, pagina, url, modulo_id, padre_id, orden, tabla_estado_registro_id FROM conf__paginas WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res_p)) {
    echo "ID: {$r['pagina_id']} | Pagina: {$r['pagina']} | Padre: " . var_export($r['padre_id'], true) . " | URL: {$r['url']} | Estado: {$r['tabla_estado_registro_id']}\n";
}

echo "\n=== 2. EMPRESAS (nombre de empresa) ===\n";
$res_emp = mysqli_query($conn, "SELECT empresa_id, descripcion, nombre_fantasia, razon_social FROM conf__empresas");
while ($r = mysqli_fetch_assoc($res_emp)) {
    print_r($r);
}

echo "\n=== 3. PERFILES DE PABLO (usuario_id = 1) ===\n";
$res_up = mysqli_query($conn, "SELECT * FROM conf__usuarios_perfiles WHERE usuario_id = 1");
while ($r = mysqli_fetch_assoc($res_up)) {
    print_r($r);
}

echo "\n=== 4. TODOS LOS PERFILES ===\n";
$res_pf = mysqli_query($conn, "SELECT * FROM conf__perfiles");
while ($r = mysqli_fetch_assoc($res_pf)) {
    print_r($r);
}

echo "\n=== 5. CONF__EMPRESAS_MODULOS ===\n";
$res_em = mysqli_query($conn, "SELECT * FROM conf__empresas_modulos WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res_em)) {
    print_r($r);
}

echo "\n=== 6. MODULO 4 EN CONF__MODULOS ===\n";
$res_mod = mysqli_query($conn, "SELECT * FROM conf__modulos WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res_mod)) {
    print_r($r);
}
