<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT icono_id, icono_clase FROM conf__iconos WHERE icono_clase LIKE '%cash%' OR icono_clase LIKE '%calculator%' OR icono_clase LIKE '%store%' OR icono_clase LIKE '%briefcase%' LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['icono_id']} | Icono: {$r['icono_clase']}\n";
}
