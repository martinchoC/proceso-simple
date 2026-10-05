<?php
// Redirección al archivo principal en modules/gestion/
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: modules/gestion/exportar_productos_offline.php" . $queryString);
exit;
