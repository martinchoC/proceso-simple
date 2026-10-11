<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/../modules/gestion_normal/cobranzas_model.php';

echo "=== TEST COBRANZA CON RETENCIONES (IVA, GANANCIAS, IIBB ARBA) ===\n";

// Buscar una entidad válida (cliente)
$res_ent = mysqli_query($conn, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE es_cliente = 1 LIMIT 1");
$row_ent = mysqli_fetch_assoc($res_ent);
if (!$row_ent) {
    die("No se encontró cliente en la base de datos.");
}
$entidad_id = intval($row_ent['entidad_id']);
echo "Usando cliente: {$row_ent['entidad_nombre']} (ID: $entidad_id)\n";

$empresa_id = 2;
$usuario_id = 1;

// Guardar borrador de cobranza con retenciones
$data_cobranza = [
    'empresa_id'          => $empresa_id,
    'sucursal_id'         => 1,
    'comprobante_pv'      => 1,
    'comprobante_tipo_id' => 24,
    'entidad_id'          => $entidad_id, // Cliente
    'f_emision'           => date('Y-m-d'),
    'f_contabilidad'      => date('Y-m-d'),
    'observaciones'       => 'Cobranza Test Retenciones IVA, Ganancias e IIBB',
    'usuario_id'          => $usuario_id,
    'medios' => [
        [
            'medio_pago_id'             => 1, // Efectivo
            'tesoreria_cuenta_id'       => 1,
            'moneda_id'                 => 1,
            'tipo_cambio'               => 1.0,
            'importe'                   => 5000.00,
            'importe_comprobante'       => 5000.00
        ],
        [
            'medio_pago_id'             => 5, // Retención IVA
            'moneda_id'                 => 1,
            'tipo_cambio'               => 1.0,
            'importe'                   => 300.00,
            'importe_comprobante'       => 300.00,
            'impuesto_tipo_id'          => 5, // Retención IVA
            'retencion_certificado_nro' => 'CERT-IVA-001',
            'retencion_base_imponible'  => 10000.00,
            'retencion_alicuota'        => 3.00
        ],
        [
            'medio_pago_id'             => 6, // Retención Ganancias
            'moneda_id'                 => 1,
            'tipo_cambio'               => 1.0,
            'importe'                   => 400.00,
            'importe_comprobante'       => 400.00,
            'impuesto_tipo_id'          => 21, // Retención Ganancias
            'retencion_certificado_nro' => 'CERT-GAN-002',
            'retencion_base_imponible'  => 20000.00,
            'retencion_alicuota'        => 2.00
        ],
        [
            'medio_pago_id'             => 7, // Retención IIBB
            'moneda_id'                 => 1,
            'tipo_cambio'               => 1.0,
            'importe'                   => 300.00,
            'importe_comprobante'       => 300.00,
            'impuesto_tipo_id'          => 4, // Retención IIBB
            'jurisdiccion_id'           => 2, // ARBA (Buenos Aires)
            'retencion_certificado_nro' => 'CERT-ARBA-003',
            'retencion_base_imponible'  => 15000.00,
            'retencion_alicuota'        => 2.00
        ]
    ],
    'imputaciones' => []
];

try {
    mysqli_begin_transaction($conn);

    $cobranza_id = guardarCobranzaBorrador($conn, $data_cobranza);
    echo "1. Borrador Creado exitosamente! ID: $cobranza_id\n";

    // Obtener detalles del borrador
    $detalle = obtenerCobranzaDetalle($conn, $cobranza_id, $empresa_id);
    echo "2. Medios recuperados (" . count($detalle['medios']) . " medios):\n";
    foreach ($detalle['medios'] as $m) {
        echo "   - Medio: {$m['medio_pago']} | Tipo: {$m['medio_pago_tipo']}";
        if ($m['medio_pago_tipo'] === 'RETENCION') {
            echo " | Certificado: {$m['retencion_certificado_nro']} | Impuesto: {$m['impuesto_tipo']} | Jurisdiccion: " . ($m['jurisdiccion_nombre'] ?? 'N/A') . " | Base: {$m['retencion_base_imponible']} | Ali: {$m['retencion_alicuota']}%";
        }
        echo " | Importe: $" . number_format($m['importe_comprobante'], 2) . "\n";
    }

    // Confirmar cobranza
    $res_conf = confirmarCobranza($conn, $cobranza_id, $empresa_id, $usuario_id);
    echo "3. Confirmación: " . ($res_conf['success'] ? "ÉXITO - " . $res_conf['message'] : "FALLO: " . $res_conf['error']) . "\n";

    // Rollback para no dejar datos ficticios en la BD
    mysqli_rollback($conn);
    echo "4. Rollback ejecutado limpiamente (Base de Datos intacta).\n";

    echo "\n=== PRUEBA COMPLETADA CON ÉXITO ===\n";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "ERROR EN TEST: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
