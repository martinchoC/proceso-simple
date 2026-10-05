<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT * FROM conf__iconos WHERE icono_id IN (23, 69, 74, 75, 76, 77, 78, 80, 81)");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['icono_id']} | Clase: {$r['icono_clase']}\n";
}
