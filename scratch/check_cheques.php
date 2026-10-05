<?php
require_once __DIR__ . '/../db.php';
try {
    echo "--- TABLA CHEQUES ID EN CONF_TABLAS ---\n";
    $r = $conn->query("SELECT tabla_id, tabla_nombre FROM conf__tablas WHERE tabla_nombre = 'gestion__cheques'");
    $t = $r->fetch_assoc();
    $tabla_id = $t['tabla_id'];
    echo "Tabla ID: $tabla_id\n";

    echo "--- ESTADOS REGISTRADOS PARA GESTION__CHEQUES ---\n";
    $r2 = $conn->query("SELECT ter.tabla_estado_registro_id, er.estado_registro_id, er.codigo_estandar, er.estado_registro
                        FROM conf__tablas_estados_registros ter
                        JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
                        WHERE ter.tabla_id = $tabla_id");
    while($row = $r2->fetch_assoc()) {
        echo "{$row['tabla_estado_registro_id']} | {$row['codigo_estandar']} | {$row['estado_registro']}\n";
    }

    echo "--- TABLA CONCILIACION BANCARIA ---\n";
    $r3 = $conn->query("SHOW TABLES LIKE '%concilia%'");
    while($row = $r3->fetch_row()) {
        echo "Tabla: {$row[0]}\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
