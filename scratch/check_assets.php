<?php
require_once __DIR__ . '/../config.php';

$assets = [
    'css/dataTables.bootstrap5.min.css' => asset_local('css/dataTables.bootstrap5.min.css'),
    'css/all.min.css' => asset_local('css/all.min.css'),
    'css/sweetalert2-bootstrap-4.min.css' => asset_local('css/sweetalert2-bootstrap-4.min.css'),
    'css/adminlte.min.css' => asset_local('css/adminlte.min.css'),
    'css/overlayscrollbars.min.css' => asset_local('css/overlayscrollbars.min.css'),
    'css/bootstrap-icons.css' => asset_local('css/bootstrap-icons.css'),
    'css/bootstrap.min.css' => asset_local('css/bootstrap.min.css'),
    'css/styles.css' => asset_local('css/styles.css'),
    'js/jquery.min.js' => asset_local('js/jquery.min.js'),
    'js/dataTables.min.js' => asset_local('js/dataTables.min.js'),
    'js/dataTables.bootstrap5.min.js' => asset_local('js/dataTables.bootstrap5.min.js'),
    'js/overlayscrollbars.browser.es6.min.js' => asset_local('js/overlayscrollbars.browser.es6.min.js'),
    'js/popper.min.js' => asset_local('js/popper.min.js'),
    'js/bootstrap.min.js' => asset_local('js/bootstrap.min.js'),
    'adminlte.js' => asset('js/adminlte.js'),
    'js/Sortable.min.js' => asset_local('js/Sortable.min.js'),
];

echo "=== VERIFICACION DE ASSETS EN DISCO ===\n";
foreach ($assets as $key => $url) {
    // Convertir URL a ruta local
    $rel = str_replace(BASE_URL . '/', '', $url);
    $path = ROOT_PATH . '/' . $rel;
    $exists = file_exists($path);
    echo ($exists ? "[OK] " : "[FALTA] ") . "$key => $path (Size: " . ($exists ? filesize($path) : 0) . ")\n";
}
