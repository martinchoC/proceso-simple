<?php
require_once __DIR__ . '/../db.php';
$check = mysqli_query($conn, "SHOW COLUMNS FROM gestion__facturas_proveedores LIKE 'CAE'");
if (mysqli_num_rows($check) === 0) {
    mysqli_query($conn, "ALTER TABLE gestion__facturas_proveedores ADD COLUMN CAE varchar(50) NULL AFTER observaciones");
    echo "Columna CAE agregada a gestion__facturas_proveedores\n";
} else {
    echo "Columna CAE ya existe en gestion__facturas_proveedores\n";
}
