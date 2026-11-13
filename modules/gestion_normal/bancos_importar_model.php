<?php
/**
 * Extractos Bancarios (página 129 / 629): importación del extracto en dos pasos y lotes importados
 * (tabla 445 gestion__bancos_importaciones, estados 5 Importado / 38 Anulado; líneas en gestion__bancos_extracto).
 * La lógica está en core/bancos/bancos_extracto_service.php (compartida con gestion_normal).
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/bancos/bancos_extracto_service.php';
$conexion = $conn;
