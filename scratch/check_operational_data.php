<?php
require_once __DIR__ . '/../db.php';

$tables = [
    'gestion__ventas_facturas',
    'gestion__ventas_facturas_detalles',
    'gestion__comprobantes',
    'gestion__comprobantes_imputaciones',
    'gestion__cobranzas',
    'gestion__cobranzas_medios',
    'gestion__facturas_proveedores',
    'gestion__facturas_proveedores_detalle',
    'gestion__tesoreria_movimientos',
    'gestion__cont_asientos',
    'gestion__cont_asientos_detalles',
    'gestion__entidades',
    'gestion__entidades_sucursales',
    'gestion__entidades_condiciones_clientes',
    'gestion__entidades_condiciones_proveedores',
    'gestion__productos',
    'gestion__listas_precios_productos'
];

foreach ($tables as $t) {
    $hasEmpresa = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE 'empresa_id'")) > 0;
    if ($hasEmpresa) {
        $q = "SELECT * FROM `$t` WHERE empresa_id = 6";
    } else {
        $q = "SELECT * FROM `$t` LIMIT 5";
    }
    $res = mysqli_query($conn, $q);
    $cnt = mysqli_num_rows($res);
    echo "=== Table: $t (empresa_id col: " . ($hasEmpresa ? 'YES' : 'NO') . ") - count: $cnt ===\n";
    if ($cnt > 0 && $cnt <= 10) {
        while ($r = mysqli_fetch_assoc($res)) {
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    } elseif ($cnt > 10) {
        echo "Showing first 3 of $cnt:\n";
        $i = 0;
        while ($r = mysqli_fetch_assoc($res)) {
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
            if (++$i >= 3) break;
        }
    }
}
