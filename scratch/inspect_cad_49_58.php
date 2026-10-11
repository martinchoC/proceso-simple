<?php
require_once __DIR__ . '/../db.php';

echo "=== INSPECCION DE RENGLONES EN ASIENTOS DE COMPRA (49 al 58) ===\n";
$r = mysqli_query($conn, "SELECT d.cont_asiento_id, d.cuenta_id, c.codigo, c.nombre, d.tipo, d.importe_local, d.descripcion
FROM gestion__cont_asientos_detalles d
JOIN gestion__cont_cuentas c ON d.cuenta_id = c.cont_cuenta_id
WHERE d.cont_asiento_id BETWEEN 49 AND 58
ORDER BY d.cont_asiento_id, d.cuenta_id");

while ($row = mysqli_fetch_assoc($r)) {
    printf("Asiento %d | Cuenta: %-12s %-35s | Tipo: %s | Importe: $%10.2f | %s\n",
        $row['cont_asiento_id'],
        $row['codigo'],
        substr($row['nombre'], 0, 35),
        $row['tipo'],
        $row['importe_local'],
        $row['descripcion']
    );
}
