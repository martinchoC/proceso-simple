<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

// Crear funciones de prueba para validar la lógica del backend
function testMapearTipoArca($conn, $empresa_id, $tipoStr) {
    // Buscar código numérico al inicio, ej "1 - Factura A" -> 1
    $cod = 0;
    if (preg_match('/^\s*(\d+)\s*[-_]/', $tipoStr, $m)) {
        $cod = intval($m[1]);
    }
    
    // Normalizar texto
    $tipoNorm = trim($tipoStr);
    
    // Buscar en comprobantes_tipos de compras para esta empresa
    $subRes = mysqli_query($conn, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE empresa_id = $empresa_id AND comprobante_subgrupo LIKE '%Facturas de Compra%' LIMIT 1");
    $subRow = mysqli_fetch_assoc($subRes);
    $subgrupo_id = $subRow['comprobante_subgrupo_id'] ?? 49;

    $sql = "SELECT comprobante_tipo_id, comprobante_tipo, letra, comprobante_fiscal_id 
            FROM gestion__comprobantes_tipos 
            WHERE empresa_id = $empresa_id AND comprobante_subgrupo_id = $subgrupo_id";
    
    if ($cod > 0) {
        $sql .= " AND comprobante_fiscal_id = $cod";
    } else {
        // buscar por nombre o letra
        if (preg_match('/Factura\s+([A-Z])/i', $tipoNorm, $lm)) {
            $sql .= " AND letra = '" . strtoupper($lm[1]) . "' AND comprobante_tipo LIKE '%Factura%'";
        } elseif (preg_match('/Nota\s+de\s+Cr[eé]dito\s+([A-Z])/i', $tipoNorm, $lm)) {
            $sql .= " AND letra = '" . strtoupper($lm[1]) . "' AND comprobante_tipo LIKE '%Cr[eé]dito%'";
        } elseif (preg_match('/Nota\s+de\s+D[eé]bito\s+([A-Z])/i', $tipoNorm, $lm)) {
            $sql .= " AND letra = '" . strtoupper($lm[1]) . "' AND comprobante_tipo LIKE '%D[eé]bito%'";
        }
    }
    $sql .= " LIMIT 1";

    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($res);
}

// Probar mapeos
$muestras = [
    '1 - Factura A',
    '6 - Factura B',
    '11 - Factura C',
    '3 - Nota de Crédito A',
    '8 - Nota de Crédito B',
    '13 - Nota de Crédito C',
    'Factura A',
    'Factura B',
    'Factura C'
];

echo "=== TEST MAPEO TIPOS ARCA (EMPRESA 6) ===\n";
foreach ($muestras as $m) {
    $res = testMapearTipoArca($conn, 6, $m);
    echo "ARCA: '$m' => " . ($res ? "ID: {$res['comprobante_tipo_id']} ({$res['comprobante_tipo']} Letra {$res['letra']} CodFiscal {$res['comprobante_fiscal_id']})" : "NO ENCONTRADO") . "\n";
}

echo "\n=== TEST MAPEO TIPOS ARCA (EMPRESA 2) ===\n";
foreach ($muestras as $m) {
    $res = testMapearTipoArca($conn, 2, $m);
    echo "ARCA: '$m' => " . ($res ? "ID: {$res['comprobante_tipo_id']} ({$res['comprobante_tipo']} Letra {$res['letra']} CodFiscal {$res['comprobante_fiscal_id']})" : "NO ENCONTRADO") . "\n";
}
