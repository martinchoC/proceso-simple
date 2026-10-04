<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/permisos.php';

$usuario_id = 1;
$empresa_id = 2;

echo "USUARIOS_ACCESO_TOTAL:\n";
print_r(USUARIOS_ACCESO_TOTAL);

echo "\nEMPRESAS_MODULOS para usuario 1:\n";
$em = usuario_obtener_empresas_modulos($conn, $usuario_id);
print_r($em);

echo "\nPAGINAS_PERMITIDAS para usuario 1 en empresa 2:\n";
$pp = usuario_obtener_paginas_permitidas($conn, $usuario_id, $empresa_id);
echo "Total páginas permitidas: " . count($pp) . "\n";
echo "Tiene página 303 (empresas_eventos): " . (isset($pp[303]) ? 'SI' : 'NO') . "\n";
echo "Tiene página 352 (evidencias): " . (isset($pp[352]) ? 'SI' : 'NO') . "\n";
echo "Tiene página 362 (cargas_masivas): " . (isset($pp[362]) ? 'SI' : 'NO') . "\n";

echo "\nSUBMENUS / CONTENEDORES DE ML (modulo 3) EN conf__paginas:\n";
$sql = "SELECT pagina_id, pagina, padre_id, url FROM conf__paginas WHERE modulo_id = 3 AND tabla_estado_registro_id = 1 AND padre_id = 0 ORDER BY orden";
$res = mysqli_query($conn, $sql);
while ($r = mysqli_fetch_assoc($res)) {
    echo "Contenedor #{$r['pagina_id']} '{$r['pagina']}' (padre 0):\n";
    $hijos_sql = "SELECT pagina_id, pagina, url FROM conf__paginas WHERE padre_id = {$r['pagina_id']} AND tabla_estado_registro_id = 1 ORDER BY orden";
    $rh = mysqli_query($conn, $hijos_sql);
    $cant_hijos = 0;
    while ($h = mysqli_fetch_assoc($rh)) {
        $cant_hijos++;
        $perm = isset($pp[(int)$h['pagina_id']]) ? 'PERMITIDA' : 'DENEGADA';
        echo "   -> Hijo #{$h['pagina_id']} '{$h['pagina']}' [{$h['url']}] : {$perm}\n";
    }
    if ($cant_hijos === 0) {
        $perm_padre = isset($pp[(int)$r['pagina_id']]) ? 'PERMITIDA' : 'DENEGADA';
        echo "   (Sin hijos definidos, evalua permiso directo: {$perm_padre})\n";
    }
}
