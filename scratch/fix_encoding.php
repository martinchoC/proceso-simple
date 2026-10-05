<?php
require_once __DIR__ . '/../db.php';
mysqli_query($conn, "UPDATE ml__entidades_tipos SET nombre = 'Terminal Logística / Portuaria' WHERE entidad_tipo_id = 2");
echo "Updated.\n";
