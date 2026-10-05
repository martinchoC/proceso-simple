<?php
require_once __DIR__ . '/../db.php';
try {
    echo "--- GESTION PAGOS COLUMNAS ---\n";
    $r = $conn->query('DESCRIBE gestion__pagos');
    while($row = $r->fetch_assoc()) {
        echo "{$row['Field']} | {$row['Type']} | Null: {$row['Null']}\n";
    }

    echo "--- GESTION PAGOS MEDIOS COLUMNAS ---\n";
    $r2 = $conn->query('DESCRIBE gestion__pagos_medios');
    while($row = $r2->fetch_assoc()) {
        echo "{$row['Field']} | {$row['Type']} | Null: {$row['Null']}\n";
    }

    echo "--- PAGINAS DE TESORERIA ---\n";
    $r_p = $conn->query("SELECT pagina_id, pagina, url, padre_id, orden FROM conf__paginas WHERE padre_id = 100 OR pagina_id = 100");
    while($row = $r_p->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }

    $r_chk = $conn->query("SELECT pagina_id FROM conf__paginas WHERE pagina_id = 106 OR url = 'pagos.php'");
    if ($r_chk->num_rows == 0) {
        $conn->query("INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, pagina_descripcion, padre_id, orden, tabla_id, tabla_estado_registro_id)
                      VALUES (106, 2, 'Órdenes de Pago', 'pagos.php', 'Pagos a Proveedores y Emisión de OP', 100, 20, 276, 1)");
    }
    echo "--- PERFILES FUNCIONES COLUMNAS ---\n";
    $r_pf = $conn->query("DESCRIBE conf__empresas_perfiles_funciones");
    while($row = $r_pf->fetch_assoc()) {
        echo "{$row['Field']} | {$row['Type']}\n";
    }

    echo "--- MATRIZ CANCELACIONES OP ---\n";
    $r_mc = $conn->query("SELECT tc.*, cto.codigo as origen, ctd.codigo as destino 
                          FROM gestion__comprobantes_tipos_cancelaciones tc
                          JOIN gestion__comprobantes_tipos cto ON cto.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                          JOIN gestion__comprobantes_tipos ctd ON ctd.comprobante_tipo_id = tc.comprobante_tipo_destino_id
                          WHERE cto.codigo = 'OP'");
    while($row = $r_mc->fetch_assoc()) {
        echo "Empresa {$row['empresa_id']}: {$row['origen']} cancela {$row['destino']}\n";
    }
    $r3 = $conn->query("SELECT comprobante_tipo_id, codigo, comprobante_tipo, modulo_id FROM gestion__comprobantes_tipos WHERE codigo IN ('OP', 'OPX', 'PAGO')");
    while($row = $r3->fetch_assoc()) {
        echo "{$row['comprobante_tipo_id']} | {$row['codigo']} | {$row['comprobante_tipo']}\n";
    }

    echo "--- PROVEEDORES (empresa 2) ---\n";
    $r4 = $conn->query("SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE empresa_id = 2 AND es_proveedor = 1 LIMIT 5");
    while($row = $r4->fetch_assoc()) {
        echo "{$row['entidad_id']} | {$row['entidad_nombre']} | {$row['cuit']}\n";
    }

    echo "--- TABLA ORDENES PAGO ID EN CONF_TABLAS ---\n";
    $r5 = $conn->query("SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre = 'gestion__ordenes_pago'");
    while($row = $r5->fetch_assoc()) {
        echo "{$row['tabla_id']} | {$row['tabla_nombre']}\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
