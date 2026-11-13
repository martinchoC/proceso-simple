<?php
require_once __DIR__ . '/../../db.php';
mysqli_query($conn, "TRUNCATE TABLE conf__empresas_vinculaciones");
echo "Tabla conf__empresas_vinculaciones inicializada.\n";
