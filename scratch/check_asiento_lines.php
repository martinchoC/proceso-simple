<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT d.*, c.codigo, c.nombre 
FROM gestion__cont_asientos_detalles d
JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id
WHERE d.cont_asiento_id = 41");
while ($r = mysqli_fetch_assoc($res)) {
    echo "Cuenta: {$r['codigo']} - {$r['nombre']} | Importe: \${$r['importe_local']} | Tipo: {$r['tipo']}\n";
}
