<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el modelo
require_once "perfiles_funciones_model.php";

$accion = $_GET['accion'] ?? '';

// Establecer cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener la acción
$accion = $_GET['accion'] ?? '';

// Crear conexión si no existe
if (!isset($conexion) || !$conexion) {
    $conexion = conectarBD();
}

switch ($accion) {
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;
        
    case 'obtener_perfiles_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        $perfiles = obtenerPerfilesPorModulo($conexion, $modulo_id);
        echo json_encode($perfiles);
        break;
        
    case 'obtener_paginas_funciones':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        $perfil_id = isset($_GET['perfil_id']) ? intval($_GET['perfil_id']) : null;
        $paginas = obtenerEstructuraJerarquicaPaginas($conexion, $modulo_id, $perfil_id);
        echo json_encode($paginas);
        break;
        
    case 'toggle_funcion':
        $perfil_id = intval($_GET['perfil_id']);
        $pagina_funcion_id = intval($_GET['pagina_funcion_id']);
        $asignado = intval($_GET['asignado']);
        
        $resultado = toggleFuncionPerfil($conexion, $perfil_id, $pagina_funcion_id, $asignado);
        echo json_encode(['resultado' => $resultado]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}

// Cerrar conexión si es necesario
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}?>