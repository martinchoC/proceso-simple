<?php
require_once __DIR__ . '/../../db.php';

function asegurarTiposCompra($conn, $empresa_id) {
    // Buscar subgrupo de compras
    $subRes = mysqli_query($conn, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE empresa_id = $empresa_id AND comprobante_subgrupo LIKE '%Facturas de Compra%' LIMIT 1");
    $subRow = mysqli_fetch_assoc($subRes);
    if (!$subRow) {
        // buscar por empresa 2 o crear
        $subRes2 = mysqli_query($conn, "SELECT comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE comprobante_subgrupo LIKE '%Facturas de Compra%' LIMIT 1");
        $subRow = mysqli_fetch_assoc($subRes2);
    }
    $subgrupo_id = $subRow['comprobante_subgrupo_id'] ?? 49;

    $tipos_estandar = [
        ['tipo' => 'Factura A', 'letra' => 'A', 'cod_fiscal' => 1, 'signo' => '+'],
        ['tipo' => 'Nota de Débito A', 'letra' => 'A', 'cod_fiscal' => 2, 'signo' => '+'],
        ['tipo' => 'Nota de Crédito A', 'letra' => 'A', 'cod_fiscal' => 3, 'signo' => '-'],
        ['tipo' => 'Factura B', 'letra' => 'B', 'cod_fiscal' => 6, 'signo' => '+'],
        ['tipo' => 'Nota de Débito B', 'letra' => 'B', 'cod_fiscal' => 7, 'signo' => '+'],
        ['tipo' => 'Nota de Crédito B', 'letra' => 'B', 'cod_fiscal' => 8, 'signo' => '-'],
        ['tipo' => 'Factura C', 'letra' => 'C', 'cod_fiscal' => 11, 'signo' => '+'],
        ['tipo' => 'Nota de Débito C', 'letra' => 'C', 'cod_fiscal' => 12, 'signo' => '+'],
        ['tipo' => 'Nota de Crédito C', 'letra' => 'C', 'cod_fiscal' => 13, 'signo' => '-'],
        ['tipo' => 'Factura M', 'letra' => 'M', 'cod_fiscal' => 51, 'signo' => '+'],
        ['tipo' => 'Factura X', 'letra' => 'X', 'cod_fiscal' => 0, 'signo' => '+'],
        ['tipo' => 'Nota de Crédito X', 'letra' => 'X', 'cod_fiscal' => 0, 'signo' => '-'],
    ];

    foreach ($tipos_estandar as $t) {
        $check = mysqli_query($conn, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos 
            WHERE empresa_id = $empresa_id AND comprobante_subgrupo_id = $subgrupo_id 
              AND (comprobante_fiscal_id = {$t['cod_fiscal']} AND letra = '{$t['letra']}' AND signo = '{$t['signo']}')");
        if (mysqli_num_rows($check) === 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO gestion__comprobantes_tipos
                (empresa_id, comprobante_subgrupo_id, comprobante_tipo, letra, comprobante_fiscal_id, signo, requiere_cae, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, 0, 1)");
            mysqli_stmt_bind_param($stmt, "iissis", $empresa_id, $subgrupo_id, $t['tipo'], $t['letra'], $t['cod_fiscal'], $t['signo']);
            mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            echo "Tipo creado para empresa $empresa_id: {$t['tipo']} ({$t['letra']}) CodFiscal {$t['cod_fiscal']} (ID: $new_id)\n";
        }
    }
}

asegurarTiposCompra($conn, 6);
asegurarTiposCompra($conn, 2);
echo "Tipos de compra verificados con éxito.\n";
