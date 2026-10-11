<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/ventas_facturas_model.php';

echo "=== CARGA DE DATOS REALES DE FACTURAS Y CLIENTES (EMPRESA_ID = 6) ===\n";

mysqli_begin_transaction($conn);

try {
    $empresa_id = 6;
    $sucursal_id = 4;
    $punto_venta_id = 8; // PV 2 con codigo_fiscal 2
    $comprobante_tipo_id = 100; // Factura B (cod_fiscal 6)
    $moneda_id = 3; // ARS
    $tipo_cambio = 1.000000;
    $estado_confirmado = 5; // Confirmado
    $tabla_origen_id = 80; // gestion__ventas_facturas
    $condicion_pago_cc = 15; // Cuenta Corriente 15 días (o 12)
    $cuenta_cliente_id = 1215; // Deudores por Ventas
    $cuenta_venta_id = 1271; // Abonos de Soporte y Hosting

    // 1. CREAR PRODUCTOS
    $productos_def = [
        'SIRIS-REC' => [
            'nombre' => 'SIRIS- Abono mensual Sistema de Recaudacion Web',
            'desc' => 'Abono mensual Sistema de Recaudacion Web correspondiente al mes',
            'es_servicio' => 1, 'aplica_venta' => 1, 'aplica_compra' => 0
        ],
        'SIGA-AFIL' => [
            'nombre' => 'SIGA- Abono mensual Sistema Integral de Gestión de Afiliados Web',
            'desc' => 'Abono mensual Sistema Integral de Gestión de Afiliados Web',
            'es_servicio' => 1, 'aplica_venta' => 1, 'aplica_compra' => 0
        ],
        'SIRIS-SIND' => [
            'nombre' => 'SIRIS-SINDICATO Abono mensual según tarifador único',
            'desc' => 'SIRIS-SINDICATO Abono mensual según tarifador único',
            'es_servicio' => 1, 'aplica_venta' => 1, 'aplica_compra' => 0
        ],
        'SIRIS-MUT' => [
            'nombre' => 'SIRIS-MUTUAL Abono mensual según tarifador único',
            'desc' => 'SIRIS-MUTUAL Abono mensual según tarifador único',
            'es_servicio' => 1, 'aplica_venta' => 1, 'aplica_compra' => 0
        ],
        'GASTO-GRAL' => [
            'nombre' => 'Gastos Generales / Compras Varias',
            'desc' => 'Concepto genérico para comprobantes de proveedores importados de ARCA',
            'es_servicio' => 1, 'aplica_venta' => 0, 'aplica_compra' => 1
        ]
    ];

    $prod_map = [];
    foreach ($productos_def as $codigo => $p) {
        $stmt_p = mysqli_prepare($conn, "INSERT INTO gestion__productos 
            (empresa_id, producto_codigo, producto_nombre, producto_descripcion, producto_categoria_id, cont_cuenta_id, cont_cuenta_compra_id, cont_cuenta_venta_id, producto_tipo_id, unidad_medida_id, iva_alicuota_id, controla_stock, es_servicio, aplica_venta, aplica_compra, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, 52, 131, ?, ?, 5, 1, 11, 0, ?, ?, ?, 1)");
        $cc_compra = $p['aplica_compra'] ? 1206 : null;
        $cc_venta = $p['aplica_venta'] ? $cuenta_venta_id : null;
        mysqli_stmt_bind_param($stmt_p, "isssiiiii", 
            $empresa_id, $codigo, $p['nombre'], $p['desc'],
            $cc_compra, $cc_venta,
            $p['es_servicio'], $p['aplica_venta'], $p['aplica_compra']
        );
        mysqli_stmt_execute($stmt_p);
        $prod_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_p);
        $prod_map[$codigo] = $prod_id;
        echo "Producto creado: $codigo (ID: $prod_id)\n";
    }

    // 2. CREAR CLIENTES
    $clientes_def = [
        'AECRC' => [
            'nombre' => 'ASOCIACION EMPLEADOS DE COMERCIO DE RIO COLORADO',
            'fantasia' => 'AEC Rio Colorado',
            'cuit' => '30645039927',
            'domicilio' => 'Hipolito Yrigoyen 851',
            'localidad_id' => 11042, // Rio Colorado, Rio Negro
            'condicion_iva' => 'IVA Sujeto Exento'
        ],
        'CECCdP' => [
            'nombre' => 'CENTRO EMPLEADOS DE COMERCIO',
            'fantasia' => 'CEC Carmen de Patagones',
            'cuit' => '30635090525',
            'domicilio' => 'Garibaldi 323',
            'localidad_id' => 3013, // Carmen de Patagones, Buenos Aires
            'condicion_iva' => 'IVA Sujeto Exento'
        ],
        'CECGP' => [
            'nombre' => 'CENTRO EMPLEADOS DE COMERCIO DE GRAL PICO',
            'fantasia' => 'CEC General Pico',
            'cuit' => '30541512590',
            'domicilio' => '13 758',
            'localidad_id' => 7093, // General Pico, La Pampa
            'condicion_iva' => 'Consumidor Final'
        ],
        'SECT' => [
            'nombre' => 'SINDICATO DE EMPLEADOS DE COMERCIO',
            'fantasia' => 'SEC Trelew',
            'cuit' => '30548970837',
            'domicilio' => 'Edison 330',
            'localidad_id' => 25772, // Trelew, Chubut
            'condicion_iva' => 'IVA Sujeto Exento'
        ],
        'AECN' => [
            'nombre' => 'SINDICATO EMPLEADOS DE COMERCIO DE NECOCHEA',
            'fantasia' => 'SEC Necochea',
            'cuit' => '33533636409',
            'domicilio' => '59 3131',
            'localidad_id' => 21493, // Necochea, Buenos Aires
            'condicion_iva' => 'IVA Sujeto Exento'
        ],
        'AECBB' => [
            'nombre' => 'ASOCIACION EMPLEADOS DE COMERCIO DE BAHIA BLANCA',
            'fantasia' => 'AEC Bahia Blanca',
            'cuit' => '30526358054',
            'domicilio' => 'Rodriguez 60',
            'localidad_id' => 26985, // Bahia Blanca, Buenos Aires
            'condicion_iva' => 'IVA Sujeto Exento'
        ]
    ];

    $client_map = [];
    foreach ($clientes_def as $k => $c) {
        $stmt_cli = mysqli_prepare($conn, "INSERT INTO gestion__entidades
            (empresa_id, entidad_nombre, entidad_fantasia, entidad_tipo_id, cuit, domicilio_legal, localidad_id, cont_cuenta_id_cliente, es_proveedor, es_cliente, fecha_alta, tabla_estado_registro_id)
            VALUES (?, ?, ?, 2, ?, ?, ?, ?, 0, 1, NOW(), 1)");
        mysqli_stmt_bind_param($stmt_cli, "issssii",
            $empresa_id, $c['nombre'], $c['fantasia'], $c['cuit'], $c['domicilio'], $c['localidad_id'], $cuenta_cliente_id
        );
        mysqli_stmt_execute($stmt_cli);
        $entidad_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_cli);

        // Sucursal entidad
        $stmt_suc = mysqli_prepare($conn, "INSERT INTO gestion__entidades_sucursales
            (entidad_id, empresa_id, sucursal_nombre, sucursal_direccion, localidad_id, tabla_estado_registro_id)
            VALUES (?, ?, 'Casa Central', ?, ?, 1)");
        mysqli_stmt_bind_param($stmt_suc, "iisi", $entidad_id, $empresa_id, $c['domicilio'], $c['localidad_id']);
        mysqli_stmt_execute($stmt_suc);
        $entidad_sucursal_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_suc);

        // Condicion comercial cliente
        $stmt_cond = mysqli_prepare($conn, "INSERT INTO gestion__entidades_condiciones_clientes
            (entidad_id, condicion_pago_id, entidad_cliente_tipo_id, lista_precio_id, limite_credito, cliente_descuento_general, f_desde, tabla_estado_registro_id)
            VALUES (?, ?, 1, 4, 0, 0, '2026-01-01', 1)");
        mysqli_stmt_bind_param($stmt_cond, "ii", $entidad_id, $condicion_pago_cc);
        mysqli_stmt_execute($stmt_cond);
        mysqli_stmt_close($stmt_cond);

        $client_map[$k] = [
            'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => $entidad_sucursal_id
        ];
        echo "Cliente dado de alta: {$c['nombre']} (ID: $entidad_id)\n";
    }

    // 3. FACTURAS REALES A CARGAR
    $facturas_def = [
        [
            'nro' => 76,
            'cliente_key' => 'AECRC',
            'cae' => '86405733042928',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 131917.60,
            'iva' => 22894.79,
            'neto' => 109022.81,
            'items' => [
                [
                    'prod' => 'SIRIS-REC',
                    'desc' => 'SIRIS- Abono mensual Sistema de Recaudacion Web correspondiente al mes 09/26',
                    'cant' => 1.00,
                    'precio' => 131917.60,
                    'neto' => 109022.81,
                    'iva' => 22894.79,
                    'total' => 131917.60
                ]
            ]
        ],
        [
            'nro' => 77,
            'cliente_key' => 'CECCdP',
            'cae' => '86405733152570',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 131917.60,
            'iva' => 22894.79,
            'neto' => 109022.81,
            'items' => [
                [
                    'prod' => 'SIRIS-REC',
                    'desc' => 'SIRIS- Abono mensual Sistema de Recaudacion Web correspondiente al mes 09/26',
                    'cant' => 1.00,
                    'precio' => 131917.60,
                    'neto' => 109022.81,
                    'iva' => 22894.79,
                    'total' => 131917.60
                ]
            ]
        ],
        [
            'nro' => 78,
            'cliente_key' => 'CECGP',
            'cae' => '86405733478324',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 712355.04,
            'iva' => 123631.86,
            'neto' => 588723.18,
            'items' => [
                [
                    'prod' => 'SIRIS-REC',
                    'desc' => 'SIRIS- Abono mensual Sistema de Recaudacion Web correspondiente al mes 09/26',
                    'cant' => 1.00,
                    'precio' => 356177.52,
                    'neto' => 294361.59,
                    'iva' => 61815.93,
                    'total' => 356177.52
                ],
                [
                    'prod' => 'SIGA-AFIL',
                    'desc' => 'SIGA- Abono mensual Sistema Integral de Gestión de Afiliados Web correspondiente mes 09/26',
                    'cant' => 1.00,
                    'precio' => 356177.52,
                    'neto' => 294361.59,
                    'iva' => 61815.93,
                    'total' => 356177.52
                ]
            ]
        ],
        [
            'nro' => 79,
            'cliente_key' => 'SECT',
            'cae' => '86405734115271',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 873294.51,
            'iva' => 151563.51,
            'neto' => 721731.00,
            'items' => [
                [
                    'prod' => 'SIRIS-SIND',
                    'desc' => 'SIRIS-SINDICATO Abono mensual - Total de declarados 6723 según tarifador único mes 09/26',
                    'cant' => 1.00,
                    'precio' => 609459.31,
                    'neto' => 503685.38,
                    'iva' => 105773.93,
                    'total' => 609459.31
                ],
                [
                    'prod' => 'SIRIS-MUT',
                    'desc' => 'SIRIS-MUTUAL Abono mensual - Total de declarados 1280 según tarifador único mes 09/26',
                    'cant' => 1.00,
                    'precio' => 263835.20,
                    'neto' => 218045.62,
                    'iva' => 45789.58,
                    'total' => 263835.20
                ]
            ]
        ],
        [
            'nro' => 80,
            'cliente_key' => 'AECN',
            'cae' => '86405734395384',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 430974.80,
            'iva' => 74797.28,
            'neto' => 356177.52,
            'items' => [
                [
                    'prod' => 'SIRIS-REC',
                    'desc' => 'SIRIS- Abono mensual Sistema de Recaudacion Web correspondiente al mes 09/26',
                    'cant' => 1.00,
                    'precio' => 430974.80,
                    'neto' => 356177.52,
                    'iva' => 74797.28,
                    'total' => 430974.80
                ]
            ]
        ],
        [
            'nro' => 81,
            'cliente_key' => 'AECBB',
            'cae' => '86405734617074',
            'f_vto_cae' => '2026-10-10',
            'f_emision' => '2026-09-30',
            'f_vto' => '2026-10-15',
            'total' => 779298.21,
            'iva' => 135248.34,
            'neto' => 644049.87,
            'items' => [
                [
                    'prod' => 'SIRIS-REC',
                    'desc' => 'SIRIS- Abono mensual Sistema de Recaudacion Web correspondiente al mes 09/26',
                    'cant' => 1.00,
                    'precio' => 779298.21,
                    'neto' => 644049.87,
                    'iva' => 135248.34,
                    'total' => 779298.21
                ]
            ]
        ]
    ];

    foreach ($facturas_def as $f) {
        $cinfo = $client_map[$f['cliente_key']];
        $entidad_id = $cinfo['entidad_id'];
        $entidad_sucursal_id = $cinfo['entidad_sucursal_id'];
        $nro = $f['nro'];
        $total = $f['total'];
        $iva = $f['iva'];
        $neto = $f['neto'];
        $cae = $f['cae'];
        $f_vto_cae = $f['f_vto_cae'];
        $f_emision = $f['f_emision'];
        $f_vto = $f['f_vto'];

        // A. Insertar factura de venta
        $sql_vf = "INSERT INTO gestion__ventas_facturas
            (empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id, comprobante_nro,
             comprobante_id, entidad_id, entidad_sucursal_id, condicion_pago_id, f_emision,
             f_contabilidad, f_vto, moneda_id, tipo_cambio, importe_bruto, descuento_general_pct,
             descuento_general, importe_neto, importe_exento, importe_no_gravado, importe_iva,
             importe_otros_impuestos, importe_total, observaciones, CAE, f_vto_CAE, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, 0, ?, 0, ?, 'Período facturado: 01/09/2026 al 30/09/2026', ?, ?, ?)";
        $stmt_vf = mysqli_prepare($conn, $sql_vf);
        mysqli_stmt_bind_param($stmt_vf, "iiiiiiissssidddddssi",
            $empresa_id, $sucursal_id, $comprobante_tipo_id, $punto_venta_id, $nro,
            $entidad_id, $entidad_sucursal_id, $condicion_pago_cc, $f_emision,
            $f_emision, $f_vto, $moneda_id, $tipo_cambio, $total,
            $neto, $iva, $total, $cae, $f_vto_cae, $estado_confirmado
        );
        mysqli_stmt_execute($stmt_vf);
        $venta_factura_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_vf);

        // B. Insertar detalles
        foreach ($f['items'] as $item) {
            $pid = $prod_map[$item['prod']];
            $stmt_det = mysqli_prepare($conn, "INSERT INTO gestion__ventas_facturas_detalles
                (venta_factura_id, producto_id, cantidad, precio_unitario, descuento_general_pct, descuento_general,
                 precio_unitario_neto, importe_neto, iva_alicuota_id, porcentaje_iva, importe_iva, importe_no_gravado, importe_exento, importe_linea, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, 0, 0, ?, ?, 11, 21.00, ?, 0, 0, ?, 1)");
            $pu_neto = $item['neto'] / $item['cant'];
            mysqli_stmt_bind_param($stmt_det, "iidddddd",
                $venta_factura_id, $pid, $item['cant'], $item['precio'],
                $pu_neto, $item['neto'], $item['iva'], $item['total']
            );
            mysqli_stmt_execute($stmt_det);
            mysqli_stmt_close($stmt_det);
        }

        // C. Sincronizar comprobante (gestion__comprobantes) con SALDO = TOTAL (NADA ESTÁ PAGO)
        $comprobante_data = [
            'empresa_id' => $empresa_id,
            'sucursal_id' => $sucursal_id,
            'comprobante_pv' => $punto_venta_id, // PV 2
            'comprobante_tipo_id' => $comprobante_tipo_id,
            'comprobante_nro' => $nro,
            'entidad_id' => $entidad_id,
            'entidad_sucursal_id' => $entidad_sucursal_id,
            'f_emision' => $f_emision,
            'f_contabilidad' => $f_emision,
            'f_vto' => $f_vto,
            'moneda_id' => $moneda_id,
            'tipo_cambio' => $tipo_cambio,
            'registro_origen_id' => $venta_factura_id,
            'tabla_estado_registro_id' => $estado_confirmado,
            'usuario_id' => 1,
            'observaciones' => "Factura B 00002-" . str_pad($nro, 8, '0', STR_PAD_LEFT) . " CAE $cae",
            'importe_neto' => $neto,
            'descuento_general' => 0,
            'importe_no_gravado' => 0,
            'importe_exento' => 0,
            'importe_iva' => $iva,
            'importe_otros_impuestos' => 0,
            'importe_total' => $total
        ];
        $comprobante_id = syncComprobante($conn, $comprobante_data, $tabla_origen_id);

        // Asegurar que comprobante_id quede en la factura y el saldo en gestion__comprobantes sea exactamente $total (impago)
        mysqli_query($conn, "UPDATE gestion__ventas_facturas SET comprobante_id = $comprobante_id WHERE venta_factura_id = $venta_factura_id");
        mysqli_query($conn, "UPDATE gestion__comprobantes SET importe_pendiente = importe_total, comprobante_nro = $nro WHERE comprobante_id = $comprobante_id");

        // D. Generar Asiento Contable
        $asiento_res = vfGenerarAsientoContableFactura($conn, $venta_factura_id, $empresa_id, true);

        echo "Factura B 00002-" . str_pad($nro, 8, '0', STR_PAD_LEFT) . " cargada con éxito! (ID: $venta_factura_id, CompID: $comprobante_id, Asiento: " . ($asiento_res['cont_asiento_id'] ?? 'Generado') . ", Saldo: \$$total)\n";
    }

    // 4. ACTUALIZAR NUMERADOR PARA PUNTO DE VENTA 8 (PV 2) Y TIPO 100 (FACTURA B) A 81
    mysqli_query($conn, "INSERT INTO gestion__comprobantes_numeradores 
        (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero, created_at, updated_at)
        VALUES ($empresa_id, $punto_venta_id, $comprobante_tipo_id, 81, NOW(), NOW())
        ON DUPLICATE KEY UPDATE ultimo_numero = 81, updated_at = NOW()");
    echo "Numerador actualizado a 81.\n";

    mysqli_commit($conn);
    echo "\n=== PROCESO DE CARGA DE FACTURAS COMPLETADO CON ÉXITO ===\n";

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR en la carga: " . $e->getMessage() . "\n";
}
