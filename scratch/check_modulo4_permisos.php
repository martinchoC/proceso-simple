<?php
require_once __DIR__ . '/../db.php';

echo "=== CONF__PERFILES (modulo 4) ===\n";
$res1 = mysqli_query($conn, "SELECT * FROM conf__perfiles WHERE modulo_id = 4");
while ($r = mysqli_fetch_assoc($res1)) {
    print_r($r);
}

echo "=== CONF__EMPRESAS_PERFILES (modulo 4) ===\n";
$res2 = mysqli_query($conn, "SELECT * FROM conf__empresas_perfiles WHERE modulo_id = 4");
echo "Total en conf__empresas_perfiles: " . mysqli_num_rows($res2) . "\n";
while ($r = mysqli_fetch_assoc($res2)) {
    print_r($r);
}

echo "=== CONF__PAGINAS_FUNCIONES (modulo 4) ===\n";
$res3 = mysqli_query($conn, "SELECT pf.*, p.pagina FROM conf__paginas_funciones pf INNER JOIN conf__paginas p ON p.pagina_id = pf.pagina_id WHERE p.modulo_id = 4");
echo "Total paginas_funciones: " . mysqli_num_rows($res3) . "\n";
while ($r = mysqli_fetch_assoc($res3)) {
    echo "PF ID: {$r['pagina_funcion_id']} | Pagina: {$r['pagina']} | Funcion: {$r['funcion']}\n";
}

echo "=== CONF__EMPRESAS_PERFILES_FUNCIONES (modulo 4) ===\n";
$res4 = mysqli_query($conn, "SELECT epf.* FROM conf__empresas_perfiles_funciones epf 
                             INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id 
                             WHERE ep.modulo_id = 4");
echo "Total empresas_perfiles_funciones: " . mysqli_num_rows($res4) . "\n";
