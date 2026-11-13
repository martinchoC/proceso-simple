<?php
require_once __DIR__ . '/../../db.php';

function seedTiposCompra($conn, $empresa_id) {
    // Buscar grupo y subgrupo de compras para la empresa
    $subRes = mysqli_query($conn, "SELECT comprobante_grupo_id, comprobante_subgrupo_id FROM gestion__comprobantes_subgrupos WHERE empresa_id = $empresa_id AND comprobante_subgrupo LIKE '%Facturas de Compra%' LIMIT 1");
    $subRow = mysqli_fetch_assoc($subRes);
    if (!$subRow) return;

    $grupo_id = $subRow['comprobante_grupo_id'];
    $subgrupo_id = $subRow['comprobante_subgrupo_id'];

    $nuevos = [
        ['tipo' => 'Factura B', 'codigo' => 'C_FB', 'letra' => 'B', 'cod_fiscal' => 6, 'signo' => '+', 'stock_signo' => '+'],
        ['tipo' => 'Nota de Débito B', 'codigo' => 'C_NDB', 'letra' => 'B', 'cod_fiscal' => 7, 'signo' => '+', 'stock_signo' => '+'],
        ['tipo' => 'Nota de Crédito B', 'codigo' => 'C_NCB', 'letra' => 'B', 'cod_fiscal' => 8, 'signo' => '-', 'stock_signo' => '-'],
        ['tipo' => 'Factura C', 'codigo' => 'C_FC', 'letra' => 'C', 'cod_fiscal' => 11, 'signo' => '+', 'stock_signo' => '+'],
        ['tipo' => 'Nota de Débito C', 'codigo' => 'C_NDC', 'letra' => 'C', 'cod_fiscal' => 12, 'signo' => '+', 'stock_signo' => '+'],
        ['tipo' => 'Nota de Crédito C', 'codigo' => 'C_NCC', 'letra' => 'C', 'cod_fiscal' => 13, 'signo' => '-', 'stock_signo' => '-'],
        ['tipo' => 'Factura M', 'codigo' => 'C_FM', 'letra' => 'M', 'cod_fiscal' => 51, 'signo' => '+', 'stock_signo' => '+'],
    ];

    foreach ($nuevos as $t) {
        $c = mysqli_query($conn, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = $empresa_id AND comprobante_subgrupo_id = $subgrupo_id AND comprobante_fiscal_id = {$t['cod_fiscal']}");
        if (mysqli_num_rows($c) === 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO gestion__comprobantes_tipos
                (empresa_id, comprobante_grupo_id, comprobante_subgrupo_id, comprobante_fiscal_id, impacta_stock, stock_signo, impacta_contabilidad, impacta_ctacte, comprobante_tipo, codigo, letra, signo, comentario, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, 1, ?, 1, 1, ?, ?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, "iiiissssss",
                $empresa_id, $grupo_id, $subgrupo_id, $t['cod_fiscal'],
                $t['stock_signo'], $t['tipo'], $t['codigo'], $t['letra'], $t['signo'], $t['tipo']
            );
            mysqli_stmt_execute($stmt);
            echo "Tipo creado empresa $empresa_id: {$t['tipo']} (ID: " . mysqli_insert_id($conn) . ")\n";
            mysqli_stmt_close($stmt);
        }
    }
}

seedTiposCompra($conn, 6);
seedTiposCompra($conn, 2);
echo "Tipos de compra asegurados con éxito.\n";
