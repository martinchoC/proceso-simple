<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 1, 'paginas_url' => ['empresas_perfiles.php', 'perfiles.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "perfiles_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

function perfilesDatosRequest(): array
{
    return [
        'perfil_nombre' => $_POST['perfil_nombre'] ?? '',
        'modulo_id' => $_POST['modulo_id'] ?? 0,
        'tipo_operacion' => $_POST['tipo_operacion'] ?? 'TODAS',
        'tabla_estado_registro_id' => $_POST['tabla_estado_registro_id'] ?? 1,
    ];
}

switch ($accion) {
    case 'listar':
        echo json_encode(obtenerPerfiles($conexion));
        break;

    case 'agregar':
        echo json_encode(agregarPerfil($conexion, perfilesDatosRequest()));
        break;

    case 'editar':
        echo json_encode(editarPerfil($conexion, intval($_POST['perfil_id'] ?? 0), perfilesDatosRequest()));
        break;

    case 'cambiar_estado':
        echo json_encode(cambiarEstadoPerfil($conexion, intval($_POST['perfil_id'] ?? 0), intval($_POST['nuevo_estado'] ?? 0)));
        break;

    case 'obtener':
        echo json_encode(obtenerPerfilPorId($conexion, intval($_GET['perfil_id'] ?? 0)));
        break;

    case 'obtener_modulos':
        echo json_encode(obtenerModulos($conexion));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no definida']);
}
