<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT ct.comprobante_tipo_id, ct.empresa_id, ct.comprobante_tipo, ct.letra, ct.comprobante_fiscal_id, ct.signo
FROM gestion__comprobantes_tipos ct
JOIN gestion__comprobantes_subgrupos csg ON ct.comprobante_subgrupo_id = csg.comprobante_subgrupo_id
WHERE csg.comprobante_subgrupo LIKE '%Facturas de Compra%'");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['comprobante_tipo_id']} | Emp: {$r['empresa_id']} | CodFiscal: {$r['comprobante_fiscal_id']} | Letra: {$r['letra']} | Signo: {$r['signo']} | Tipo: {$r['comprobante_tipo']}\n";
}
