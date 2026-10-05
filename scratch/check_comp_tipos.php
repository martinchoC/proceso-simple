<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT comprobante_tipo_id, comprobante_tipo, codigo, letra FROM gestion__comprobantes_tipos WHERE tabla_estado_registro_id = 1 LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['comprobante_tipo_id']} | Tipo: {$r['comprobante_tipo']} | Letra: {$r['letra']} | Codigo: {$r['codigo']}\n";
}
