<?php
require_once 'config/db.php';

header('Content-Type: application/json');
$tabla = $_GET['tabla'] ?? '';
$clave = $_GET['clave'] ?? '';
$valor = $_GET['valor'] ?? '';

if ($tabla && $clave && $valor) {
  $sql = "SELECT $clave AS id, $valor AS nombre FROM $tabla ORDER BY $valor";
  $res = $conn->query($sql);

  $datos = [];
  while ($row = $res->fetch_assoc()) {
    $datos[] = $row;
  }

  echo json_encode($datos);
} else {
  echo json_encode([]);
}