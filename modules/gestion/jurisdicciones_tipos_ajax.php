<?php
require_once __DIR__ . '/../../db.php';
require_once "jurisdicciones_tipos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 75);

header('Content-Type: application/json');

switch ($accion) {

    case 'listar':
        echo json_encode(
            obtenerJurisdiccionesTipos($conexion, $empresa_idx, $pagina_idx)
        );
        break;

    case 'agregar':
        echo json_encode(
            agregarJurisdiccionTipo($conexion, $_POST)
        );
        break;

    case 'editar':
        echo json_encode(
            editarJurisdiccionTipo($conexion, $_POST['id'], $_POST)
        );
        break;

    case 'ejecutar_accion':
        echo json_encode(
            ejecutarTransicionEstado(
                $conexion,
                $_POST['id'],
                $_POST['accion_js'],
                $empresa_idx,
                $pagina_idx
            )
        );
        break;
}