<?php
// Activar reporte de errores para ver si hay fallos (Quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Asegurar que la respuesta sea JSON siempre
header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conn) {
    echo json_encode(['error' => 'Error de conexión a la BD']);
    exit;
}

session_start();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? null;

switch ($accion) {
  // --- EMPRESAS ---
  case 'listar':
    $sql = "SELECT * FROM conf__empresas ORDER BY empresa_id DESC";
    $res = $conn->query($sql);

    // CONTROL DE ERRORES SQL
    if (!$res) {
        echo json_encode(['error' => 'Error SQL: ' . $conn->error]);
        exit;
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    break;

  case 'obtener':
    $id = $_GET['id'] ?? null;
    $stmt = $conn->prepare("SELECT * FROM conf__empresas WHERE empresa_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_assoc());
    break;

  case 'guardar':
    $empresa_id = $_POST['empresa_id'] ?? null;
    $empresa = $_POST['empresa'];
    // NULL si vienen vacíos
    $documento_tipo_id = !empty($_POST['documento_tipo_id']) ? $_POST['documento_tipo_id'] : null;
    $documento_numero = $_POST['documento_numero'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $domicilio = $_POST['domicilio'] ?? '';
    $localidad_id = !empty($_POST['localidad_id']) ? $_POST['localidad_id'] : null;
    $email = $_POST['email'] ?? '';
    $base_conf = $_POST['base_conf'] ?? '';

    if ($empresa_id) {
      $stmt = $conn->prepare("UPDATE conf__empresas SET empresa=?, documento_tipo_id=?, documento_numero=?, telefono=?, domicilio=?, localidad_id=?, email=?, base_conf=? WHERE empresa_id=?");
      // CORRECCIÓN DE TIPOS: 'd' (double) para teléfono estaba mal, se cambia a 's'.
      // Tipos: s=string, i=integer
      $stmt->bind_param("sisssissi", $empresa, $documento_tipo_id, $documento_numero, $telefono, $domicilio, $localidad_id, $email, $base_conf, $empresa_id);
    } else {
      $stmt = $conn->prepare("INSERT INTO conf__empresas (empresa, documento_tipo_id, documento_numero, telefono, domicilio, localidad_id, email, base_conf, estado_registro_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
      $stmt->bind_param("sisssiss", $empresa, $documento_tipo_id, $documento_numero, $telefono, $domicilio, $localidad_id, $email, $base_conf);
    }

    if ($stmt->execute()) {
        echo json_encode(['estado' => 'ok']);
    } else {
        echo json_encode(['error' => 'Error al guardar: ' . $stmt->error]);
    }
    break;

  case 'eliminar': // (Nota: Esta acción no la llamo desde el JS, pero la dejo corregida)
    $id = $_POST['id'] ?? null;
    $stmt = $conn->prepare("UPDATE conf__empresas SET estado_registro_id = 2 WHERE empresa_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['estado' => 'ok']);
    break;

  case 'estado':
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? 1;
    $stmt = $conn->prepare("UPDATE conf__empresas SET estado_registro_id = ? WHERE empresa_id = ?");
    $stmt->bind_param("ii", $estado, $id);
    $stmt->execute();
    echo json_encode(['estado' => 'ok']);
    break;

  // --- MÓDULOS ASIGNADOS A EMPRESAS ---
  case 'listar_modulos':
    $empresa_id = $_GET['empresa_id'] ?? 0;
    // IMPORTANTE: Verificar que las tablas existan y los nombres sean correctos
    $sql = "SELECT em.empresa_modulo_id, m.modulo, em.estado_registro_id
            FROM conf__empresas_modulos em
            JOIN conf__modulos m ON em.modulo_id = m.modulo_id
            WHERE em.empresa_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['error' => 'Error SQL Modulos: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $datos = [];
    while ($row = $res->fetch_assoc()) {
      $datos[] = $row;
    }
    echo json_encode($datos);
    break;

  case 'asignar_modulo':
    $empresa_id = $_POST['empresa_id'] ?? 0;
    $modulo_id = $_POST['modulo_id'] ?? 0;

    $check = $conn->prepare("SELECT 1 FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id = ?");
    $check->bind_param("ii", $empresa_id, $modulo_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {
      $stmt = $conn->prepare("INSERT INTO conf__empresas_modulos (empresa_id, modulo_id, estado_registro_id) VALUES (?, ?, 1)");
      $stmt->bind_param("ii", $empresa_id, $modulo_id);
      $stmt->execute();
    }
    echo json_encode(['ok' => true]);
    break;

  case 'cambiar_estado_modulo':
    $empresa_modulo_id = $_POST['empresa_modulo_id'] ?? 0;
    $estado = $_POST['estado'] ?? 1;
    $stmt = $conn->prepare("UPDATE conf__empresas_modulos SET estado_registro_id = ? WHERE empresa_modulo_id = ?");
    $stmt->bind_param("ii", $estado, $empresa_modulo_id);
    $stmt->execute();
    echo json_encode(['ok' => true]);
    break;

  default:
    echo json_encode(['error' => 'Acción no válida o no recibida. GET: ' . print_r($_GET, true)]);
}
?>