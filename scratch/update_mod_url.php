<?php
require_once __DIR__ . '/../db.php';
mysqli_query($conn, "UPDATE conf__modulos SET modulo_url = 'estudio_contable' WHERE modulo_id = 4");
echo "Updated modulo_url to estudio_contable\n";
