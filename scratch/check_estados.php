<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM conf__tablas");
echo "conf__tablas columns:\n";
while ($r = mysqli_fetch_assoc($res)) echo "  {$r['Field']}\n";

$res = mysqli_query($conn, "SHOW COLUMNS FROM conf__estados_registros");
echo "conf__estados_registros columns:\n";
while ($r = mysqli_fetch_assoc($res)) echo "  {$r['Field']}\n";

$res = mysqli_query($conn, "SELECT er.*, t.tabla FROM conf__estados_registros er JOIN conf__tablas t ON er.tabla_id = t.tabla_id WHERE t.tabla IN ('gestion__ventas_facturas', 'gestion__comprobantes')");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['tabla_estado_registro_id']} | Tabla: {$r['tabla']} | Estado: {$r['estado_registro']}\n";
}
