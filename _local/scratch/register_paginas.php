<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../db.php';

// Página 108: Movimientos Internos de Tesorería
$conn->query("INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, padre_id, orden, tabla_estado_registro_id) 
              VALUES (108, 2, 'Movimientos Internos', 'tesoreria_movimientos.php', 100, 40, 1)
              ON DUPLICATE KEY UPDATE pagina = 'Movimientos Internos', url = 'tesoreria_movimientos.php', padre_id = 100, orden = 40, tabla_estado_registro_id = 1");

// Página 109: Liquidaciones Impositivas y Rendiciones (Fase 6)
$conn->query("INSERT INTO conf__paginas (pagina_id, modulo_id, pagina, url, padre_id, orden, tabla_estado_registro_id) 
              VALUES (109, 2, 'Obligaciones y Rendiciones', 'obligaciones_rendiciones.php', 100, 50, 1)
              ON DUPLICATE KEY UPDATE pagina = 'Obligaciones y Rendiciones', url = 'obligaciones_rendiciones.php', padre_id = 100, orden = 50, tabla_estado_registro_id = 1");

echo "Páginas 108 y 109 registradas exitosamente.\n";
