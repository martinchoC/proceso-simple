<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM gestion__comprobantes_subgrupos WHERE comprobante_subgrupo LIKE '%Facturas de Compra%'");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Subgrupo ID: {$r['comprobante_subgrupo_id']} | Emp: {$r['empresa_id']} | Nombre: {$r['comprobante_subgrupo']}\n";
}
