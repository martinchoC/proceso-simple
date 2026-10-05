<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SELECT comprobante_tipo_id, comprobante_tipo, codigo FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id < 29");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['comprobante_tipo_id']} | Codigo: {$r['codigo']} | Tipo: {$r['comprobante_tipo']}\n";
}
