<?php
require_once __DIR__ . '/../../db.php';

function q($sql) {
    global $conn;
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

echo "=== conf__modulos (modulo_id = 4) ===\n";
print_r(q("SELECT * FROM conf__modulos WHERE modulo_id = 4"));

echo "=== conf__tablas count for modulo_id = 4 ===\n";
print_r(q("SELECT COUNT(*) as cnt FROM conf__tablas WHERE modulo_id = 4"));

echo "=== conf__tablas_estados_registros count ===\n";
print_r(q("SELECT COUNT(*) as cnt FROM conf__tablas_estados_registros WHERE tabla_id BETWEEN 401 AND 425"));

echo "=== conf__paginas for modulo_id = 4 ===\n";
print_r(q("SELECT pagina_id, pagina, url, icono_id, padre_id, tabla_id FROM conf__paginas WHERE modulo_id = 4"));

echo "=== conf___perfiles_paginas for modulo_id = 4 ===\n";
print_r(q("SELECT COUNT(*) as cnt FROM conf___perfiles_paginas WHERE pagina_id >= 400"));
