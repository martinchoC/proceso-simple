<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/facturas_proveedores_model.php';

$empresa_id = 6;
$sucursal_id = 4;
$deposito_id = 5;
$moneda_id = 3;
$usuario_id = 1;
$tabla_origen_id = 84; // gestion__facturas_proveedores
$tipo_factura_a_id = 116; // C_FA (Factura A de Compras)

echo "=== GENERACION DE 10 COMPROBANTES DE COMPRA - EMPRESA 6 ===\n";

$rubros = [
    [
        'rubro' => 'Seguro',
        'cuit' => '30500049460',
        'proveedor' => 'LA SEGUNDA COOPERATIVA DE SEGUROS GENERALES',
        'domicilio' => 'Brigadier General Juan Manuel de Rosas 956, Rosario',
        'prod_cod' => 'GASTO-SEG',
        'prod_nom' => 'Seguro Integral de Comercio y Responsabilidad Civil',
        'cuenta_gasto_id' => 1188, // Seguros
        'pv' => 12,
        'nro' => 10842,
        'f_emision' => '2026-09-05',
        'f_vto' => '2026-09-20',
        'cond_pago_id' => 11,
        'neto' => 185000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 38850.00,
        'otros_imp' => 4625.00,
        'total' => 228475.00,
        'cae' => '74360124589632',
        'vto_cae' => '2026-09-15'
    ],
    [
        'rubro' => 'Honorarios Contables',
        'cuit' => '30711234567',
        'proveedor' => 'ESTUDIO CONTABLE & ASOCIADOS S.R.L.',
        'domicilio' => 'Mitre 450 Piso 3, Bahía Blanca',
        'prod_cod' => 'GASTO-HON',
        'prod_nom' => 'Honorarios Profesionales Asesoramiento Impositivo y Contable',
        'cuenta_gasto_id' => 1185, // Honorarios Profesionales
        'pv' => 3,
        'nro' => 489,
        'f_emision' => '2026-09-10',
        'f_vto' => '2026-09-25',
        'cond_pago_id' => 15,
        'neto' => 320000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 67200.00,
        'otros_imp' => 0.00,
        'total' => 387200.00,
        'cae' => '74361258963014',
        'vto_cae' => '2026-09-20'
    ],
    [
        'rubro' => 'Combustible',
        'cuit' => '30546689979',
        'proveedor' => 'YPF SOCIEDAD ANONIMA',
        'domicilio' => 'Macacha Güemes 515, CABA',
        'prod_cod' => 'GASTO-COMB',
        'prod_nom' => 'Combustibles y Lubricantes Flota Comercial',
        'cuenta_gasto_id' => 1184, // Gastos Administración / Movilidad
        'pv' => 45,
        'nro' => 895214,
        'f_emision' => '2026-09-12',
        'f_vto' => '2026-09-12',
        'cond_pago_id' => 11,
        'neto' => 95400.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 20034.00,
        'otros_imp' => 18566.00, // ITC / Líquidos Combustibles
        'total' => 134000.00,
        'cae' => '74362369852147',
        'vto_cae' => '2026-09-22'
    ],
    [
        'rubro' => 'Luz (Energía Eléctrica)',
        'cuit' => '30692750038',
        'proveedor' => 'EDES S.A. (EMPRESA DISTRIBUIDORA DE ENERGIA SUR)',
        'domicilio' => 'Vieytes 295, Bahía Blanca',
        'prod_cod' => 'GASTO-LUZ',
        'prod_nom' => 'Suministro de Energía Eléctrica Tarifa Comercial',
        'cuenta_gasto_id' => 1187, // Servicios Públicos
        'pv' => 8,
        'nro' => 230411,
        'f_emision' => '2026-09-15',
        'f_vto' => '2026-09-30',
        'cond_pago_id' => 12,
        'neto' => 142300.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 29883.00,
        'otros_imp' => 8417.00, // Tasa Alumbrado Público / Fondo Provincial
        'total' => 180600.00,
        'cae' => '74363478520149',
        'vto_cae' => '2026-09-25'
    ],
    [
        'rubro' => 'Gas Natural',
        'cuit' => '30657864427',
        'proveedor' => 'CAMUZZI GAS PAMPEANA S.A.',
        'domicilio' => 'Alsina 180, Bahía Blanca',
        'prod_cod' => 'GASTO-GAS',
        'prod_nom' => 'Suministro de Gas Natural por Red',
        'cuenta_gasto_id' => 1187, // Servicios Públicos
        'pv' => 5,
        'nro' => 67812,
        'f_emision' => '2026-09-18',
        'f_vto' => '2026-10-03',
        'cond_pago_id' => 12,
        'neto' => 58200.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 12222.00,
        'otros_imp' => 3178.00, // Fondo Fiduciario Gas / Impuestos Locales
        'total' => 73600.00,
        'cae' => '74364589632015',
        'vto_cae' => '2026-09-28'
    ],
    [
        'rubro' => 'Internet y Telefonía',
        'cuit' => '30639453738',
        'proveedor' => 'TELECOM ARGENTINA S.A.',
        'domicilio' => 'Alicia Moreau de Justo 50, CABA',
        'prod_cod' => 'GASTO-TEL',
        'prod_nom' => 'Conectividad Fibra Óptica Dedicada y Telefonía IP',
        'cuenta_gasto_id' => 1190, // Sistemas
        'pv' => 102,
        'nro' => 998241,
        'f_emision' => '2026-09-20',
        'f_vto' => '2026-10-05',
        'cond_pago_id' => 12,
        'neto' => 115000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 24150.00,
        'otros_imp' => 5850.00, // Tasa ENACOM / Percepciones
        'total' => 145000.00,
        'cae' => '74365698741023',
        'vto_cae' => '2026-09-30'
    ],
    [
        'rubro' => 'Cloud Hosting y Servidores',
        'cuit' => '30708098046',
        'proveedor' => 'DONWEB (DATTATEC S.R.L.)',
        'domicilio' => 'San Lorenzo 1055 Piso 4, Rosario',
        'prod_cod' => 'GASTO-CLOUD',
        'prod_nom' => 'Infraestructura Cloud, Hosting Dedicado y Datacenter',
        'cuenta_gasto_id' => 1190, // Sistemas
        'pv' => 4,
        'nro' => 55219,
        'f_emision' => '2026-09-22',
        'f_vto' => '2026-09-22',
        'cond_pago_id' => 11,
        'neto' => 210000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 44100.00,
        'otros_imp' => 0.00,
        'total' => 254100.00,
        'cae' => '74366789541230',
        'vto_cae' => '2026-10-02'
    ],
    [
        'rubro' => 'Limpieza y Desinfección',
        'cuit' => '30714589234',
        'proveedor' => 'MASTER CLEAN S.R.L.',
        'domicilio' => 'Av. Colón 620, Bahía Blanca',
        'prod_cod' => 'GASTO-LIMP',
        'prod_nom' => 'Servicio Mensual de Limpieza, Higiene y Desinfección',
        'cuenta_gasto_id' => 1187, // Servicios
        'pv' => 2,
        'nro' => 3310,
        'f_emision' => '2026-09-25',
        'f_vto' => '2026-10-10',
        'cond_pago_id' => 15,
        'neto' => 165000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 34650.00,
        'otros_imp' => 0.00,
        'total' => 199650.00,
        'cae' => '74367894512301',
        'vto_cae' => '2026-10-05'
    ],
    [
        'rubro' => 'Librería y Papelería',
        'cuit' => '30589632145',
        'proveedor' => 'LIBRERIA Y PAPELERA LA CENTRAL S.A.',
        'domicilio' => 'Chiclana 245, Bahía Blanca',
        'prod_cod' => 'GASTO-PAP',
        'prod_nom' => 'Insumos de Librería, Toners y Papel para Impresión',
        'cuenta_gasto_id' => 1189, // Papelería
        'pv' => 1,
        'nro' => 12984,
        'f_emision' => '2026-09-28',
        'f_vto' => '2026-09-28',
        'cond_pago_id' => 11,
        'neto' => 78500.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 16485.00,
        'otros_imp' => 0.00,
        'total' => 94985.00,
        'cae' => '74368912345678',
        'vto_cae' => '2026-10-08'
    ],
    [
        'rubro' => 'Climatización y Mantenimiento',
        'cuit' => '30712398741',
        'proveedor' => 'CLIMATIZACION & SERVICIOS INTEGRALES S.A.',
        'domicilio' => 'Brown 830, Bahía Blanca',
        'prod_cod' => 'GASTO-CLIMA',
        'prod_nom' => 'Mantenimiento Preventivo y Reparación Equipos Climatización',
        'cuenta_gasto_id' => 1187, // Servicios / Mantenimiento
        'pv' => 7,
        'nro' => 8420,
        'f_emision' => '2026-09-30',
        'f_vto' => '2026-10-15',
        'cond_pago_id' => 15,
        'neto' => 135000.00,
        'no_gravado' => 0.00,
        'exento' => 0.00,
        'iva' => 28350.00,
        'otros_imp' => 0.00,
        'total' => 163350.00,
        'cae' => '74369023456789',
        'vto_cae' => '2026-10-10'
    ]
];

$cuenta_proveedores_id = 1233; // 2.1.01.01 Proveedores Nacionales
$cuenta_iva_credito_id = 1266; // 1.1.03.01.05 IVA 21.0%

echo "\n--- Eliminando comprobantes de compra simulados anteriores para empresa $empresa_id ---\n";
$q_fp = mysqli_query($conn, "SELECT factura_proveedor_id, comprobante_id FROM gestion__facturas_proveedores WHERE empresa_id = $empresa_id");
$fp_ids = [];
$comp_ids = [];
while ($row = mysqli_fetch_assoc($q_fp)) {
    $fp_ids[] = intval($row['factura_proveedor_id']);
    if (!empty($row['comprobante_id'])) {
        $comp_ids[] = intval($row['comprobante_id']);
    }
}

if (!empty($comp_ids)) {
    $comp_list = implode(',', $comp_ids);
    $q_as = mysqli_query($conn, "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id IN ($comp_list)");
    $as_ids = [];
    while ($r_as = mysqli_fetch_assoc($q_as)) {
        $as_ids[] = intval($r_as['cont_asiento_id']);
    }
    if (!empty($as_ids)) {
        $as_list = implode(',', $as_ids);
        mysqli_query($conn, "DELETE FROM gestion__cont_asientos_detalles WHERE cont_asiento_id IN ($as_list)");
        mysqli_query($conn, "DELETE FROM gestion__cont_asientos WHERE cont_asiento_id IN ($as_list)");
        echo "   Asientos eliminados: " . count($as_ids) . "\n";
    }
    mysqli_query($conn, "DELETE FROM gestion__comprobantes_impuestos WHERE comprobante_id IN ($comp_list)");
    mysqli_query($conn, "DELETE FROM gestion__comprobantes WHERE comprobante_id IN ($comp_list)");
    echo "   Comprobantes eliminados: " . count($comp_ids) . "\n";
}

if (!empty($fp_ids)) {
    $fp_list = implode(',', $fp_ids);
    mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id IN ($fp_list)");
    mysqli_query($conn, "DELETE FROM gestion__facturas_proveedores WHERE factura_proveedor_id IN ($fp_list)");
    echo "   Facturas de proveedor eliminadas: " . count($fp_ids) . "\n";
}
echo "--- Limpieza completada ---\n";

foreach ($rubros as $i => $item) {
    echo "\n--- [".($i+1)."/10] Procesando Rubro: {$item['rubro']} --- \n";
    
    // 1. Asegurar Producto
    $pRes = mysqli_query($conn, "SELECT producto_id FROM gestion__productos WHERE empresa_id = $empresa_id AND producto_codigo = '{$item['prod_cod']}' LIMIT 1");
    $pRow = mysqli_fetch_assoc($pRes);
    if ($pRow) {
        $producto_id = intval($pRow['producto_id']);
    } else {
        $stmt_p = mysqli_prepare($conn, "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, producto_descripcion, producto_categoria_id, cont_cuenta_compra_id, producto_tipo_id, unidad_medida_id, iva_alicuota_id, controla_stock, es_servicio, aplica_venta, aplica_compra, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, 52, ?, 5, 1, 11, 0, 1, 0, 1, 1)");
        mysqli_stmt_bind_param($stmt_p, "isssi", $empresa_id, $item['prod_cod'], $item['prod_nom'], $item['prod_nom'], $item['cuenta_gasto_id']);
        mysqli_stmt_execute($stmt_p);
        $producto_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_p);
        echo "   Producto creado: {$item['prod_cod']} (ID: $producto_id)\n";
    }

    // 2. Asegurar Proveedor
    $clean_cuit = preg_replace('/[^0-9]/', '', $item['cuit']);
    $eRes = mysqli_query($conn, "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = $empresa_id AND REPLACE(cuit, '-', '') = '$clean_cuit' LIMIT 1");
    $eRow = mysqli_fetch_assoc($eRes);
    if ($eRow) {
        $entidad_id = intval($eRow['entidad_id']);
        $sRes = mysqli_query($conn, "SELECT sucursal_id FROM gestion__entidades_sucursales WHERE entidad_id = $entidad_id LIMIT 1");
        $sRow = mysqli_fetch_assoc($sRes);
        $entidad_sucursal_id = $sRow ? intval($sRow['sucursal_id']) : null;
    } else {
        $stmt_e = mysqli_prepare($conn, "INSERT INTO gestion__entidades 
            (empresa_id, entidad_nombre, entidad_fantasia, entidad_tipo_id, cuit, domicilio_legal, localidad_id, cont_cuenta_id_proveedor, es_proveedor, es_cliente, fecha_alta, tabla_estado_registro_id)
            VALUES (?, ?, ?, 2, ?, ?, 1, ?, 1, 0, NOW(), 1)");
        mysqli_stmt_bind_param($stmt_e, "issssi", $empresa_id, $item['proveedor'], $item['proveedor'], $item['cuit'], $item['domicilio'], $cuenta_proveedores_id);
        mysqli_stmt_execute($stmt_e);
        $entidad_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_e);

        mysqli_query($conn, "INSERT INTO gestion__entidades_sucursales (entidad_id, empresa_id, sucursal_nombre, sucursal_direccion, localidad_id, tabla_estado_registro_id) 
            VALUES ($entidad_id, $empresa_id, 'Casa Central', '{$item['domicilio']}', 1, 1)");
        $entidad_sucursal_id = mysqli_insert_id($conn);

        mysqli_query($conn, "INSERT INTO gestion__entidades_condiciones_proveedores (entidad_id, condicion_pago_id, proveedor_descuento_general, f_desde, tabla_estado_registro_id) 
            VALUES ($entidad_id, {$item['cond_pago_id']}, 0, '{$item['f_emision']}', 1)");

        mysqli_query($conn, "INSERT INTO gestion__entidades_condiciones_fiscales (entidad_id, condicion_fiscal_id, f_desde, tabla_estado_registro_id)
            VALUES ($entidad_id, 1, '{$item['f_emision']}', 1)");
        
        echo "   Proveedor creado: {$item['proveedor']} (ID: $entidad_id)\n";
    }

    // 3. Crear Factura de Proveedor
    $obs = "Comprobante de compra - Rubro: {$item['rubro']} | CAE: {$item['cae']}";
    $estado_fp = 5; // Confirmado

    $stmt_fp = mysqli_prepare($conn, "INSERT INTO gestion__facturas_proveedores
        (empresa_id, sucursal_id, deposito_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
         entidad_id, entidad_sucursal_id, f_emision, f_contabilidad, f_vencimiento,
         condicion_pago_id, moneda_id, tipo_cambio, direccion_entrega, subtotal,
         no_gravado, exento, descuentos, impuestos, otros_impuestos, total,
         descuento_general_pct, observaciones, CAE, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1.000000, '', ?, ?, ?, 0, ?, ?, ?, 0, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt_fp, "iiiiiiiisssiiddddddssi",
        $empresa_id, $sucursal_id, $deposito_id, $tipo_factura_a_id, $item['pv'], $item['nro'],
        $entidad_id, $entidad_sucursal_id, $item['f_emision'], $item['f_emision'], $item['f_vto'],
        $item['cond_pago_id'], $moneda_id, $item['neto'],
        $item['no_gravado'], $item['exento'], $item['iva'], $item['otros_imp'], $item['total'],
        $obs, $item['cae'], $estado_fp
    );
    mysqli_stmt_execute($stmt_fp);
    $factura_proveedor_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_fp);

    // 4. Detalle Factura de Proveedor
    $base_linea = $item['neto'] + $item['no_gravado'] + $item['exento'];
    $stmt_det = mysqli_prepare($conn, "INSERT INTO gestion__facturas_proveedores_detalle
        (factura_proveedor_id, empresa_id, producto_id, cantidad, cantidad_recibida, precio_unitario,
         descuento_item_pct, descuento_general_pct, precio_unitario_bruto, precio_unitario_neto,
         descuento, descuento_general, descuento_item, neto_gravado, no_gravado, exento,
         iva_alicuota_id, iva_porcentaje, iva_importe, total_linea, tabla_estado_registro_id)
        VALUES (?, ?, ?, 1.00, 1.00, ?, 0, 0, ?, ?, 0, 0, 0, ?, ?, ?, 11, 21.00, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt_det, "iiidddddddd",
        $factura_proveedor_id, $empresa_id, $producto_id,
        $base_linea, $base_linea, $base_linea,
        $item['neto'], $item['no_gravado'], $item['exento'], $item['iva'], $item['total']
    );
    mysqli_stmt_execute($stmt_det);
    mysqli_stmt_close($stmt_det);

    // 5. Sincronizar comprobante (syncComprobante)
    $comprobante_data = [
        'empresa_id' => $empresa_id,
        'sucursal_id' => $sucursal_id,
        'comprobante_pv' => $item['pv'],
        'comprobante_tipo_id' => $tipo_factura_a_id,
        'comprobante_nro' => $item['nro'],
        'entidad_id' => $entidad_id,
        'entidad_sucursal_id' => $entidad_sucursal_id,
        'f_emision' => $item['f_emision'],
        'f_contabilidad' => $item['f_emision'],
        'f_vto' => $item['f_vto'],
        'moneda_id' => $moneda_id,
        'tipo_cambio' => 1.000000,
        'registro_origen_id' => $factura_proveedor_id,
        'tabla_estado_registro_id' => $estado_fp,
        'usuario_id' => $usuario_id,
        'observaciones' => $obs,
        'importe_neto' => $item['neto'],
        'descuento_general' => 0,
        'importe_no_gravado' => $item['no_gravado'],
        'importe_exento' => $item['exento'],
        'importe_iva' => $item['iva'],
        'importe_otros_impuestos' => $item['otros_imp'],
        'importe_total' => $item['total']
    ];
    $comprobante_id = syncComprobante($conn, $comprobante_data, $tabla_origen_id);
    if ($comprobante_id) {
        mysqli_query($conn, "UPDATE gestion__facturas_proveedores SET comprobante_id = $comprobante_id WHERE factura_proveedor_id = $factura_proveedor_id");
    }

    // 6. Generar Asiento contable automático mediante la función oficial del sistema
    $res_asiento = generarAsientoContableFactura($conn, $factura_proveedor_id, $empresa_id, false);
    $asiento_id = $res_asiento['success'] ? $res_asiento['asiento_id'] : null;
    $asiento_info = $res_asiento['success'] ? "Asiento ID: $asiento_id" : "Error Asiento: " . ($res_asiento['message'] ?? '');

    printf("   Factura A %04d-%08d registrada! ID: %d | Comp: %d | %s | Total: $%10.2f\n",
        $item['pv'], $item['nro'], $factura_proveedor_id, $comprobante_id, $asiento_info, $item['total']);
}

echo "\n=== PROCESO COMPLETADO EXITOSAMENTE ===\n";
