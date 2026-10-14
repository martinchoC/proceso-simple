<?php
require __DIR__ . '/../db.php';

$res = $conn->query('SELECT entidad_id, entidad_nombre, es_cliente, es_proveedor FROM gestion__entidades WHERE entidad_id <= 10');
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
