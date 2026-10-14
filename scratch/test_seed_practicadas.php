<?php
require 'db.php';

// Check which empresas exist with medios de pago
$empresas = [];
$res = $conexion->query("SELECT DISTINCT empresa_id FROM gestion__medios_pago");
while($r = $res->fetch_assoc()) {
    $empresas[] = intval($r['empresa_id']);
}

echo "Empresas encontradas: " . implode(', ', $empresas) . "\n";

$medios = [
    [
        'codigo' => 'RET_IVA_P',
        'medio_pago' => 'Retención IVA',
        'impuesto_tipo_id' => 15, // Retencion IVA practicada
        'orden' => 20
    ],
    [
        'codigo' => 'RET_GAN_P',
        'medio_pago' => 'Retención Ganancias',
        'impuesto_tipo_id' => 14, // Retencion Ganancias practicada
        'orden' => 21
    ],
    [
        'codigo' => 'RET_IIBB_P',
        'medio_pago' => 'Retención IIBB',
        'impuesto_tipo_id' => 16, // Retencion IIBB practicada
        'orden' => 22
    ],
    [
        'codigo' => 'RET_SUSS_P',
        'medio_pago' => 'Retención SUSS',
        'impuesto_tipo_id' => 17, // Retencion SUSS practicada
        'orden' => 23
    ],
];

foreach ($empresas as $empresa_id) {
    foreach ($medios as $m) {
        $chk = $conexion->query("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = $empresa_id AND codigo = '{$m['codigo']}'");
        if ($chk && $chk->num_rows > 0) {
            echo "Empresa $empresa_id: ya existe {$m['codigo']}\n";
        } else {
            $stmt = $conexion->prepare("INSERT INTO gestion__medios_pago (empresa_id, codigo, medio_pago, medio_pago_tipo, impuesto_tipo_id, requiere_detalle, aplica_cobro, aplica_pago, orden, tabla_estado_registro_id) VALUES (?, ?, ?, 'RETENCION', ?, 1, 0, 1, ?, 1)");
            $stmt->bind_param("issii", $empresa_id, $m['codigo'], $m['medio_pago'], $m['impuesto_tipo_id'], $m['orden']);
            $stmt->execute();
            echo "Empresa $empresa_id: insertado {$m['codigo']} (ID: {$stmt->insert_id})\n";
        }
    }
}
