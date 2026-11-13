<?php
// Pantalla de inicio del módulo: widgets, accesos directos y su personalización.
// La lógica está en core/dashboard/ (la misma para gestion y gestion_normal); acá solo se fija el módulo.
ob_start();
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard();   // sesión + empresa de la sesión; el permiso de cada widget se valida en el servicio
$modudo_idx = 5;
require __DIR__ . '/../../core/dashboard/dashboard_endpoint.php';
