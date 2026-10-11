<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT em.*, e.empresa, m.modulo FROM conf__empresas_modulos em JOIN conf__empresas e ON e.empresa_id=em.empresa_id JOIN conf__modulos m ON m.modulo_id=em.modulo_id WHERE em.tabla_estado_registro_id=1");
$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
foreach ($rows as $r) {
    echo "Empresa: {$r['empresa_id']} ({$r['empresa']}) | Modulo: {$r['modulo_id']} ({$r['modulo']})\n";
}
