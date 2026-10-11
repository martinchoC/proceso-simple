<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT ct.comprobante_tipo_id, ct.empresa_id, ct.comprobante_tipo, ct.letra, ct.comprobante_fiscal_id, ct.signo, cg.comprobante_grupo, csg.comprobante_subgrupo
FROM gestion__comprobantes_tipos ct
LEFT JOIN gestion__comprobantes_subgrupos csg ON ct.comprobante_subgrupo_id = csg.comprobante_subgrupo_id
LEFT JOIN gestion__comprobantes_grupos cg ON csg.comprobante_grupo_id = cg.comprobante_grupo_id
WHERE (ct.empresa_id = 6 OR ct.empresa_id = 2 OR ct.empresa_id = 0)
  AND (ct.comprobante_tipo LIKE '%Factura%' OR ct.comprobante_tipo LIKE '%Nota%' OR cg.comprobante_grupo LIKE '%Compra%')
ORDER BY ct.empresa_id DESC, ct.comprobante_fiscal_id ASC");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['comprobante_tipo_id']} | Emp: {$r['empresa_id']} | CodFiscal: {$r['comprobante_fiscal_id']} | Letra: {$r['letra']} | Signo: {$r['signo']} | Tipo: {$r['comprobante_tipo']} | Grupo: {$r['comprobante_grupo']} | Subgrupo: {$r['comprobante_subgrupo']}\n";
}
