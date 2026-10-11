<?php
require_once __DIR__ . '/../db.php';
$r = mysqli_query($conn, "SELECT cont_cuenta_id, codigo, nombre, es_imputable FROM gestion__cont_cuentas WHERE empresa_id = 6 AND (codigo LIKE '4.%' OR codigo LIKE '5.%' OR codigo LIKE '6.%' OR nombre LIKE '%gasto%' OR nombre LIKE '%servicio%') LIMIT 25");
while ($row = mysqli_fetch_assoc($r)) {
    echo "{$row['cont_cuenta_id']} | {$row['codigo']} | {$row['nombre']} | imputable: {$row['es_imputable']}\n";
}
