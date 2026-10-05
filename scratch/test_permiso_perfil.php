<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/permisos.php';

echo "=== TEST PERMISOS DE PABLO (usuario_id = 1) EN CASA LUCHO (empresa_id = 2) ===\n";

// 1. Chequeo de superadmin
$es_super = in_array(1, USUARIOS_ACCESO_TOTAL, true);
echo "Es superusuario (bypass código): " . ($es_super ? "SI (ACCESO TOTAL)" : "NO") . "\n";

// 2. Perfiles asignados en base de datos
$perfiles = usuario_obtener_perfiles_empresa($conn, 1, 2);
echo "Perfiles en BD empresa 2: " . json_encode($perfiles) . "\n";

// 3. Paginas permitidas en base de datos
$paginas = usuario_obtener_paginas_permitidas($conn, 1, 2);
echo "Total paginas permitidas en BD: " . count($paginas) . "\n";

// 4. Ver si tiene acceso a cada pagina del modulo 4
foreach ([401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411] as $pid) {
    $ok = usuario_tiene_acceso_pagina($conn, 1, 2, $pid);
    echo "  Pagina $pid: " . ($ok ? "PERMITIDA" : "DENEGADA") . "\n";
}
