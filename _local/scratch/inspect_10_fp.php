<?php
require_once __DIR__ . '/../../db.php';

$res = $conexion->query("
    SELECT fp.factura_proveedor_id, fp.comprobante_pv, fp.comprobante_nro, fp.subtotal, fp.no_gravado, fp.exento, fp.impuestos, fp.otros_impuestos, fp.total,
           e.entidad_nombre
    FROM gestion__facturas_proveedores fp
    JOIN gestion__entidades e ON fp.entidad_id = e.entidad_id
    WHERE fp.empresa_id = 6
    ORDER BY fp.factura_proveedor_id
");

while ($r = $res->fetch_assoc()) {
    echo sprintf(
        "ID %d | PV %d-%d | %s | Subtotal: %s | IVA: %s | Otros: %s | Total: %s\n",
        $r['factura_proveedor_id'],
        $r['comprobante_pv'],
        $r['comprobante_nro'],
        $r['entidad_nombre'],
        $r['subtotal'],
        $r['impuestos'],
        $r['otros_impuestos'],
        $r['total']
    );
    
    // Ver detalles
    $det = $conexion->query("SELECT * FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = {$r['factura_proveedor_id']}");
    while ($d = $det->fetch_assoc()) {
        echo "   Detalle: Prod {$d['producto_id']} | Neto: {$d['neto_gravado']} | IVA: {$d['iva_importe']} | Total: {$d['total_linea']}\n";
    }

    // Ver impuestos
    $imp = $conexion->query("SELECT * FROM gestion__comprobantes_impuestos WHERE comprobante_id = (SELECT comprobante_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {$r['factura_proveedor_id']})");
    while ($i = $imp->fetch_assoc()) {
        echo "   Impuesto: Tipo {$i['impuesto_tipo_id']} | Importe: {$i['importe']}\n";
    }
}
