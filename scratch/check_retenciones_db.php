<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo FROM gestion__medios_pago WHERE tabla_estado_registro_id = 1 AND es_cobro = 1");
while($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
