<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once "tablas_estados_model.php";

$accion = $_GET['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $estados = obtenerTablasEstadosRegistros($conexion);
        echo json_encode($estados);
        break;

    case 'agregar':
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro' => $_GET['estado_registro'] ?? '',
            'codigo_estandar' => $_GET['codigo_estandar'] ?? '',
            'valor_estandar' => $_GET['valor_estandar'] ?? 1,
            'color_id' => $_GET['color_id'] ?? 1,
            'orden' => $_GET['orden'] ?? 1
        ];

        if (empty($data['tabla_id']) || empty($data['estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'La tabla y el estado son obligatorios']);
            break;
        }

        $resultado = agregarTablaEstadoRegistro($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_GET['tabla_tabla_estado_registro_id']);
        $data = [
            'tabla_id' => $_GET['tabla_id'] ?? '',
            'estado_registro' => $_GET['estado_registro'] ?? '',
            'codigo_estandar' => $_GET['codigo_estandar'] ?? '',
            'valor_estandar' => $_GET['valor_estandar'] ?? 1,
            'color_id' => $_GET['color_id'] ?? 1,
            'orden' => $_GET['orden'] ?? 1
        ];

        if (empty($data['tabla_id']) || empty($data['estado_registro'])) {
            echo json_encode(['resultado' => false, 'error' => 'La tabla y el estado son obligatorios']);
            break;
        }

        $resultado = editarTablaEstadoRegistro($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['tabla_tabla_estado_registro_id']);
        $resultado = eliminarTablaEstadoRegistro($conexion, $id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['tabla_tabla_estado_registro_id']);
        $estado = obtenerTablaEstadoRegistroPorId($conexion, $id);
        echo json_encode($estado);
        break;

    case 'obtener_tablas':
        $tablas = obtenerTablas($conexion);
        echo json_encode($tablas);
        break;

    case 'obtener_colores':
        $colores = obtenerColores($conexion);
        echo json_encode($colores);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}