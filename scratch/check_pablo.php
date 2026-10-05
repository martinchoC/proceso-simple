<?php
require_once __DIR__ . '/../db.php';

echo "=== USUARIO PABLO ===\n";
$res_u = mysqli_query($conn, "SELECT * FROM conf__usuarios WHERE usuario LIKE '%pablo%'");
$pablo = null;
while ($r = mysqli_fetch_assoc($res_u)) {
    print_r($r);
    $pablo = $r;
}

echo "=== EMPRESAS ===\n";
$res_e = mysqli_query($conn, "SELECT * FROM conf__empresas");
while ($r = mysqli_fetch_assoc($res_e)) {
    echo "ID: {$r['empresa_id']} - Nombre: {$r['empresa_nombre']}\n";
}

echo "=== TABLAS DE PERFILES / PERMISOS RELACIONADAS CON PABLO ===\n";
$tables = ['conf__usuarios_empresas_perfiles', 'conf__usuarios_perfiles', 'conf__perfiles', 'conf___perfiles_paginas', 'conf__empresas_modulos'];
foreach ($tables as $t) {
    $chk = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (mysqli_num_rows($chk) > 0) {
        echo "Tabla $t existe.\n";
    }
}
