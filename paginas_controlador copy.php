<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
  case 'listar':
    $sql = "SELECT p.pagina_id, p.pagina_nombre, p.pagina_ruta, p.pagina_descripcion,
            m.modulo, t.tabla_nombre AS tabla
            FROM conf__paginas p
            LEFT JOIN conf__modulos__c m ON p.modulo_id = m.modulo_id
            LEFT JOIN conf__tablas t ON p.tabla_id = t.tabla_id
            ORDER BY p.pagina_nombre";
    $res = $conn->query($sql);
    $datos = [];
    while ($row = $res->fetch_assoc()) {
      $datos[] = $row;
    }
    echo json_encode($datos);
    break;

  case 'obtener':
    $id = intval($_GET['id'] ?? 0);
    $sql = "SELECT * FROM conf__paginas WHERE pagina_id = $id";
    $res = $conn->query($sql);
    echo json_encode($res->fetch_assoc());
    break;

  case 'guardar':
    $pagina_id = intval($_POST['pagina_id'] ?? 0);
    $pagina_nombre = $conn->real_escape_string($_POST['pagina_nombre'] ?? '');
    $pagina_ruta = $conn->real_escape_string($_POST['pagina_ruta'] ?? '');
    $pagina_descripcion = $conn->real_escape_string($_POST['pagina_descripcion'] ?? '');
    $modulo_id = intval($_POST['modulo_id'] ?? 0) ?: "NULL";
    $tabla_id = intval($_POST['tabla_id'] ?? 0) ?: "NULL";

    if ($pagina_id > 0) {
      $sql = "UPDATE conf__paginas SET
              pagina_nombre = '$pagina_nombre',
              pagina_ruta = '$pagina_ruta',
              pagina_descripcion = '$pagina_descripcion',
              modulo_id = " . ($modulo_id === "NULL" ? "NULL" : $modulo_id) . ",
              tabla_id = " . ($tabla_id === "NULL" ? "NULL" : $tabla_id) . "
              WHERE pagina_id = $pagina_id";
    } else {
      $sql = "INSERT INTO conf__paginas
              (pagina_nombre, pagina_ruta, pagina_descripcion, modulo_id, tabla_id)
              VALUES
              ('$pagina_nombre', '$pagina_ruta', '$pagina_descripcion', " . ($modulo_id === "NULL" ? "NULL" : $modulo_id) . ", " . ($tabla_id === "NULL" ? "NULL" : $tabla_id) . ")";
    }
    $conn->query($sql);
    echo json_encode(['ok' => true]);
    break;

  default:
    echo json_encode([]);
    break;
}
