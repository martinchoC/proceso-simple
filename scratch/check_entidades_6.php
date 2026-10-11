<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT entidad_id, empresa_id, entidad_razon_social, entidad_nombre_fantasia, entidad_cuit, es_cliente, es_proveedor FROM gestion__entidades WHERE empresa_id = 6");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['entidad_id']} | CUIT: {$r['entidad_cuit']} | Razon: {$r['entidad_razon_social']} | Cli: {$r['es_cliente']} | Prov: {$r['es_proveedor']}\n";
}
