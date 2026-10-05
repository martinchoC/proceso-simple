<?php
$conexion = mysqli_connect('localhost', 'root', '', 'gestion_multipyme');
$res = $conexion->query("
    SELECT comprobante_tipo_id, codigo, comprobante_tipo, letra, signo, impacta_ctacte
    FROM gestion__comprobantes_tipos
    WHERE codigo LIKE 'V_%'
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
