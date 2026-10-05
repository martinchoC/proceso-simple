<?php
require_once __DIR__ . '/../db.php';

echo "=== 1. CONSTANTE USUARIOS_ACCESO_TOTAL ===\n";
// Buscar dónde se define USUARIOS_ACCESO_TOTAL
echo defined('USUARIOS_ACCESO_TOTAL') ? "Definida: " . print_r(USUARIOS_ACCESO_TOTAL, true) . "\n" : "No definida aún\n";

echo "=== 2. CONF__PAGINAS PARA MODULO 4 ===\n";
$res_p = mysqli_query($conn, "SELECT pagina_id, nombre, url, modulo_id, padre_id, orden, tabla_estado_registro_id FROM conf__paginas WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res_p)) {
    echo "ID: {$r['pagina_id']} | Nombre: {$r['nombre']} | Padre: " . var_export($r['padre_id'], true) . " | URL: {$r['url']} | Estado: {$r['tabla_estado_registro_id']}\n";
}

echo "=== 3. EMPRESA CASA LUCHO ===\n";
$res_emp = mysqli_query($conn, "SELECT * FROM conf__empresas");
while ($r = mysqli_fetch_assoc($res_emp)) {
    print_r($r);
}

echo "=== 4. PERFILES DE PABLO (usuario_id = 1) ===\n";
$res_up = mysqli_query($conn, "SELECT * FROM conf__usuarios_perfiles WHERE usuario_id = 1");
while ($r = mysqli_fetch_assoc($res_up)) {
    print_r($r);
}

echo "=== 5. CONF__EMPRESAS_MODULOS ===\n";
$res_em = mysqli_query($conn, "SELECT * FROM conf__empresas_modulos WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res_em)) {
    print_r($r);
}
