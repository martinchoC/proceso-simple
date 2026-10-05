<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT cliente_id, empresa_id, razon_social, cuit, empresa_vinculada_id FROM estudio__clientes");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
