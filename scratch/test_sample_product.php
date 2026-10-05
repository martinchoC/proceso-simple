<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/productos_model.php';

// Obtener un producto de ejemplo
$res = mysqli_query($conn, "SELECT producto_id, producto_codigo, producto_nombre FROM gestion__productos WHERE empresa_id = 2 AND controla_stock = 1 LIMIT 1");
$prod = mysqli_fetch_assoc($res);
echo "Producto de prueba: " . json_encode($prod) . "\n";

if ($prod) {
    $detalle = obtenerProductoPorId($conn, $prod['producto_id'], 2);
    echo "Detalle:\n" . json_encode($detalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
