<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el modelo
require_once "perfiles_usuarios_model.php";

// Crear conexión si no existe
if (!isset($conexion) || !$conexion) {
    $conexion = conectarBD();
}

// Determinar la acción (GET o POST)
$accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

// Establecer cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'obtener_modulos':
        $modulos = obtenerModulos($conexion);
        echo json_encode($modulos);
        break;

    case 'obtener_asignacion_por_id':
        $usuario_perfil_id = isset($_GET['usuario_perfil_id']) ? intval($_GET['usuario_perfil_id']) : null;
        if ($usuario_perfil_id) {
            $asignacion = obtenerAsignacionPorId($conexion, $usuario_perfil_id);
            if ($asignacion) {
                echo json_encode(['resultado' => true, 'datos' => $asignacion]);
            } else {
                echo json_encode(['resultado' => false, 'mensaje' => 'Asignación no encontrada']);
            }
        } else {
            echo json_encode(['resultado' => false, 'mensaje' => 'ID de asignación no válido']);
        }
        break;

    case 'obtener_perfiles_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        $perfiles = obtenerPerfilesPorModulo($conexion, $modulo_id);
        echo json_encode($perfiles);
        break;
        
    case 'obtener_usuarios':
        $usuarios = obtenerUsuarios($conexion);
        echo json_encode($usuarios);
        break;
        
    case 'obtener_asignaciones_usuario_perfil':
        $perfil_id = isset($_GET['perfil_id']) ? intval($_GET['perfil_id']) : null;
        $asignaciones = obtenerAsignacionesUsuarioPerfil($conexion, $perfil_id);
        echo json_encode($asignaciones);
        break;
        
    case 'asignar_usuario_perfil':
        $usuario_id = intval($_POST['usuario_id']);
        $perfil_id = intval($_POST['perfil_id']);
        $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
        $fecha_fin = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
        $usuario_creacion = intval($_POST['usuario_creacion']);
        
        // Verificar si se debe validar solapamiento
        $validar_solapamiento = isset($_POST['validar_solapamiento']) && $_POST['validar_solapamiento'] == 'true';
        
        if ($validar_solapamiento) {
            // Verificar solapamiento
            if (verificarSolapamientoAsignacion($conexion, $usuario_id, $perfil_id, $fecha_inicio, $fecha_fin)) {
                echo json_encode(['resultado' => false, 'mensaje' => 'El usuario ya está asignado a este perfil en el período seleccionado']);
                break;
            }
        }
        
        $resultado = asignarUsuarioAPerfil($conexion, $usuario_id, $perfil_id, $fecha_inicio, $fecha_fin, $usuario_creacion);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Asignación creada correctamente' : 'Error al crear la asignación']);
        break;
        
    case 'actualizar_asignacion_usuario_perfil':
        $usuario_perfil_id = intval($_POST['usuario_perfil_id']);
        $fecha_inicio = mysqli_real_escape_string($conexion, $_POST['fecha_inicio']);
        $fecha_fin = mysqli_real_escape_string($conexion, $_POST['fecha_fin']);
        $usuario_actualizacion = intval($_POST['usuario_actualizacion']);
        
        // Verificar si se debe validar solapamiento
        $validar_solapamiento = isset($_POST['validar_solapamiento']) && $_POST['validar_solapamiento'] == 'true';
        
        if ($validar_solapamiento) {
            // Obtener datos actuales para verificar solapamiento excluyendo el registro actual
            $sql_actual = "SELECT usuario_id, perfil_id FROM conf__usuarios_perfiles WHERE usuario_perfil_id = $usuario_perfil_id";
            $res_actual = mysqli_query($conexion, $sql_actual);
            $actual = mysqli_fetch_assoc($res_actual);
            
            if (verificarSolapamientoAsignacion($conexion, $actual['usuario_id'], $actual['perfil_id'], $fecha_inicio, $fecha_fin, $usuario_perfil_id)) {
                echo json_encode(['resultado' => false, 'mensaje' => 'El usuario ya está asignado a este perfil en el período seleccionado']);
                break;
            }
        }
        
        $resultado = actualizarAsignacionUsuarioPerfil($conexion, $usuario_perfil_id, $fecha_inicio, $fecha_fin, $usuario_actualizacion);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Asignación actualizada correctamente' : 'Error al actualizar la asignación']);
        break;
        
    case 'eliminar_asignacion_usuario_perfil':
        $usuario_perfil_id = intval($_POST['usuario_perfil_id']);
        $resultado = eliminarAsignacionUsuarioPerfil($conexion, $usuario_perfil_id);
        echo json_encode(['resultado' => $resultado, 'mensaje' => $resultado ? 'Asignación eliminada correctamente' : 'Error al eliminar la asignación']);
        break;
    case 'obtener_usuarios_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        if ($modulo_id) {
            $usuarios = obtenerUsuariosPorModulo($conexion, $modulo_id);
            echo json_encode($usuarios);
        } else {
            echo json_encode([]);
        }
        break;
        
    case 'obtener_asignaciones_por_modulo':
        $modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
        if ($modulo_id) {
            $asignaciones = obtenerAsignacionesPorModulo($conexion, $modulo_id);
            echo json_encode($asignaciones);
        } else {
            echo json_encode([]);
        }
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}

// Cerrar conexión si es necesario
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>