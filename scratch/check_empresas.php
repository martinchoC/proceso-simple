<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['empresa_id']} | Empresa: {$r['empresa']} | CUIT: {$r['documento_numero']}\n";
}
