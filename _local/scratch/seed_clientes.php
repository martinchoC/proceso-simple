<?php
require_once __DIR__ . '/../../db.php';

// Verificar si ya existen
$chk = mysqli_query($conn, "SELECT COUNT(*) FROM estudio__clientes WHERE empresa_id = 2");
$row = mysqli_fetch_row($chk);
if ($row[0] == 0) {
    // 1. Casa Lucho (Usa ERP - Vinculada a empresa_id 2)
    mysqli_query($conn, "INSERT INTO estudio__clientes 
        (empresa_id, cliente_tipo_id, razon_social, cuit, terminacion_cuit, condicion_iva_id, empresa_vinculada_id, mes_cierre_ejercicio, ingresos_brutos_tipo, tabla_estado_registro_id)
        VALUES 
        (2, 1, 'Casa Lucho Materiales SRL', '30541512590', 0, 1, 2, 12, 'CONVENIO_MULTILATERAL', 1)");

    // 2. Cliente Externo 1 (No usa ERP - Mini-Gestión)
    mysqli_query($conn, "INSERT INTO estudio__clientes 
        (empresa_id, cliente_tipo_id, razon_social, cuit, terminacion_cuit, condicion_iva_id, empresa_vinculada_id, mes_cierre_ejercicio, ingresos_brutos_tipo, tabla_estado_registro_id)
        VALUES 
        (2, 2, 'Estudio Jurídico Dra. Mariana Rossi', '27329845124', 4, 1, NULL, 12, 'LOCAL', 1)");

    // 3. Cliente Externo 2 (No usa ERP - Mini-Gestión)
    mysqli_query($conn, "INSERT INTO estudio__clientes 
        (empresa_id, cliente_tipo_id, razon_social, cuit, terminacion_cuit, condicion_iva_id, empresa_vinculada_id, mes_cierre_ejercicio, ingresos_brutos_tipo, tabla_estado_registro_id)
        VALUES 
        (2, 3, 'Distribuidora San Martín', '20287419632', 2, 6, NULL, 12, 'LOCAL', 1)");

    echo "Clientes iniciales insertados con éxito!\n";
} else {
    echo "Ya existen {$row[0]} clientes en estudio__clientes.\n";
}
