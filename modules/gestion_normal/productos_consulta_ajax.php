<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['productos_consulta.php']]);
// Endpoint de productos_consulta.php (consulta de productos, SOLO LECTURA).
//
// No duplica lógica: acepta únicamente las acciones de lectura de
// productos_ajax.php y le delega la ejecución. Cualquier acción de escritura
// (agregar, editar, ejecutar_accion, compatibilidades, imágenes, ubicaciones,
// proveedores, etc.) se rechaza acá, así que la página de consulta no puede
// modificar datos aunque se llame al endpoint a mano.

$acciones_permitidas = [
    'listar',
    'obtener',
    'obtener_estados',
    'obtener_combos',
    'obtener_niveles_stock',
    'obtener_presentaciones',
    'marcas_listar',
];
// Lectura de la ficha completa (solapas; core/productos_ficha/acciones.php)
require_once __DIR__ . '/../../core/productos_ficha/acciones.php';
$acciones_permitidas = array_merge($acciones_permitidas, pfic_acciones_lectura());

// Misma precedencia GET/POST que productos_ajax.php, para que la acción validada
// sea exactamente la que se ejecuta.
$accion_consulta = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (!in_array($accion_consulta, $acciones_permitidas, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Acción no permitida en la consulta de productos'], JSON_UNESCAPED_UNICODE);
    exit;
}

// productos_ajax.php acepta a los usuarios de la consulta solo cuando entran por acá
// (acciones ya filtradas) y usa la página de la consulta para los botones de cada fila.
define('PRODUCTOS_CONSULTA', true);
require __DIR__ . '/productos_ajax.php';
