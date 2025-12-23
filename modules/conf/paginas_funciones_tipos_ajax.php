<?php
require_once "conexion.php";
require_once "paginas_funciones_tipos_model.php";

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

case 'listar':
    echo json_encode(listarFunciones($conexion));
    break;

case 'obtener':
    echo json_encode(obtenerFuncion($conexion, intval($_GET['id'] ?? 0)));
    break;

case 'agregar':
    echo json_encode(agregarFuncion($conexion, $_POST));
    break;

case 'editar':
    echo json_encode(editarFuncion($conexion, $_POST));
    break;

case 'combos':
    echo json_encode(obtenerCombos($conexion));
    break;

default:
    echo json_encode(['resultado'=>false,'mensaje'=>'Acción inválida']);
}
