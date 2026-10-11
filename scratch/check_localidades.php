<?php
require_once __DIR__ . '/../db.php';
$cities = ['Bahia Blanca', 'Necochea', 'Rio Colorado', 'Carmen de Patagones', 'General Pico', 'Trelew'];
foreach ($cities as $city) {
    $res = mysqli_query($conn, "SELECT localidad_id, localidad, provincia_id FROM conf__localidades WHERE localidad LIKE '%$city%' LIMIT 3");
    echo "=== City: $city ===\n";
    while ($r = mysqli_fetch_assoc($res)) {
        echo "ID: {$r['localidad_id']} | {$r['localidad']} (Prov: {$r['provincia_id']})\n";
    }
}
