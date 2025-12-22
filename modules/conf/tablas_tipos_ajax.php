<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir archivos necesarios - Asegúrate de que no haya espacios antes de esta línea
require_once "conexion.php";
require_once "tablas_tipos_model.php";

// Establecer headers JSON
header('Content-Type: application/json; charset=utf-8');

// Crear conexión
$conexion = conectarBD();

// Obtener acción
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// Buffer de salida para evitar espacios en blanco
ob_start();

$response = [];

try {
    switch ($accion) {
        
        // ========== TIPOS DE TABLAS ==========
        case 'listar_tipos':
            $tipos = obtenerTablasTipos($conexion);
            $response = ['success' => true, 'data' => $tipos];
            break;
            
        case 'obtener_tipo':
            $id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            if ($id > 0) {
                $tipo = obtenerTablaTipoPorId($conexion, $id);
                $response = ['success' => true, 'data' => $tipo];
            } else {
                $response = ['success' => false, 'error' => 'ID inválido'];
            }
            break;
            
        case 'agregar_tipo':
            $tabla_tipo = isset($_GET['tabla_tipo']) ? trim($_GET['tabla_tipo']) : '';
            $tabla_estado_registro_id = isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1;
            
            if (empty($tabla_tipo)) {
                $response = ['success' => false, 'error' => 'El nombre del tipo es obligatorio'];
                break;
            }
            
            $data = [
                'tabla_tipo' => $tabla_tipo,
                'tabla_estado_registro_id' => $tabla_estado_registro_id
            ];
            
            $resultado = agregarTablaTipo($conexion, $data);
            
            if ($resultado) {
                $response = ['success' => true, 'message' => 'Tipo creado correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al crear tipo: ' . $error];
            }
            break;
            
        case 'editar_tipo':
            $id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            $tabla_tipo = isset($_GET['tabla_tipo']) ? trim($_GET['tabla_tipo']) : '';
            $tabla_estado_registro_id = isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1;
            
            if ($id <= 0) {
                $response = ['success' => false, 'error' => 'ID inválido'];
                break;
            }
            
            if (empty($tabla_tipo)) {
                $response = ['success' => false, 'error' => 'El nombre del tipo es obligatorio'];
                break;
            }
            
            $data = [
                'tabla_tipo' => $tabla_tipo,
                'tabla_estado_registro_id' => $tabla_estado_registro_id
            ];
            
            $resultado = editarTablaTipo($conexion, $id, $data);
            
            if ($resultado) {
                $response = ['success' => true, 'message' => 'Tipo actualizado correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al actualizar tipo: ' . $error];
            }
            break;
            
        case 'cambiar_estado_tipo':
            $id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : 1;
            
            if ($id <= 0) {
                $response = ['success' => false, 'error' => 'ID inválido'];
                break;
            }
            
            $resultado = cambiarEstadoTablaTipo($conexion, $id, $nuevo_estado);
            
            if ($resultado) {
                $estado_texto = $nuevo_estado == 1 ? 'activado' : 'desactivado';
                $response = ['success' => true, 'message' => 'Tipo ' . $estado_texto . ' correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al cambiar estado: ' . $error];
            }
            break;
            
        // ========== ESTADOS DE TIPOS DE TABLAS ==========
        case 'listar_estados':
            $tabla_tipo_id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            
            if ($tabla_tipo_id <= 0) {
                $response = ['success' => false, 'error' => 'ID de tipo inválido'];
                break;
            }
            
            $estados = obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id);
            $response = ['success' => true, 'data' => $estados];
            break;
            
        case 'obtener_estado':
            $id = isset($_GET['tabla_tipo_estado_id']) ? intval($_GET['tabla_tipo_estado_id']) : 0;
            
            if ($id <= 0) {
                $response = ['success' => false, 'error' => 'ID inválido'];
                break;
            }
            
            $estado = obtenerTablaTipoEstadoPorId($conexion, $id);
            
            if ($estado) {
                $response = ['success' => true, 'data' => $estado];
            } else {
                $response = ['success' => false, 'error' => 'Estado no encontrado'];
            }
            break;
            
        case 'agregar_estado':
            $tabla_tipo_id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            $estado_registro_id = isset($_GET['estado_registro_id']) ? intval($_GET['estado_registro_id']) : 0;
            $orden = isset($_GET['orden']) ? intval($_GET['orden']) : 1;
            $es_inicial = isset($_GET['es_inicial']) ? intval($_GET['es_inicial']) : 0;
            $tabla_estado_registro_id = isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1;
            
            if ($tabla_tipo_id <= 0 || $estado_registro_id <= 0) {
                $response = ['success' => false, 'error' => 'Datos incompletos'];
                break;
            }
            
            $data = [
                'tabla_tipo_id' => $tabla_tipo_id,
                'estado_registro_id' => $estado_registro_id,
                'orden' => $orden,
                'es_inicial' => $es_inicial,
                'tabla_estado_registro_id' => $tabla_estado_registro_id
            ];
            
            $resultado = agregarTablaTipoEstado($conexion, $data);
            
            if ($resultado) {
                $response = ['success' => true, 'message' => 'Estado agregado correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al agregar estado: ' . $error];
            }
            break;
            
        case 'editar_estado':
            $id = isset($_GET['tabla_tipo_estado_id']) ? intval($_GET['tabla_tipo_estado_id']) : 0;
            $orden = isset($_GET['orden']) ? intval($_GET['orden']) : 1;
            $es_inicial = isset($_GET['es_inicial']) ? intval($_GET['es_inicial']) : 0;
            $tabla_estado_registro_id = isset($_GET['tabla_estado_registro_id']) ? intval($_GET['tabla_estado_registro_id']) : 1;
            
            if ($id <= 0) {
                $response = ['success' => false, 'error' => 'ID inválido'];
                break;
            }
            
            $data = [
                'orden' => $orden,
                'es_inicial' => $es_inicial,
                'tabla_estado_registro_id' => $tabla_estado_registro_id
            ];
            
            $resultado = editarTablaTipoEstado($conexion, $id, $data);
            
            if ($resultado) {
                $response = ['success' => true, 'message' => 'Estado actualizado correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al actualizar estado: ' . $error];
            }
            break;
            
        case 'cambiar_estado_estado':
            $id = isset($_GET['tabla_tipo_estado_id']) ? intval($_GET['tabla_tipo_estado_id']) : 0;
            $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : 1;
            
            if ($id <= 0) {
                $response = ['success' => false, 'error' => 'ID inválido'];
                break;
            }
            
            $resultado = cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado);
            
            if ($resultado) {
                $estado_texto = $nuevo_estado == 1 ? 'activado' : 'desactivado';
                $response = ['success' => true, 'message' => 'Estado ' . $estado_texto . ' correctamente'];
            } else {
                $error = mysqli_error($conexion);
                $response = ['success' => false, 'error' => 'Error al cambiar estado: ' . $error];
            }
            break;
            
        // ========== ESTADOS REGISTROS ==========
        case 'obtener_estados_registros':
            $estados = obtenerEstadosRegistros($conexion);
            $response = ['success' => true, 'data' => $estados];
            break;
            
        case 'obtener_estados_registros_disponibles':
            $tabla_tipo_id = isset($_GET['tabla_tipo_id']) ? intval($_GET['tabla_tipo_id']) : 0;
            
            if ($tabla_tipo_id <= 0) {
                $response = ['success' => false, 'error' => 'ID de tipo inválido'];
                break;
            }
            
            $estados = obtenerEstadosRegistrosDisponibles($conexion, $tabla_tipo_id);
            $response = ['success' => true, 'data' => $estados];
            break;
            
        // ========== ACCIÓN POR DEFECTO ==========
        default:
            $response = ['success' => false, 'error' => 'Acción no definida'];
            break;
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'error' => 'Excepción: ' . $e->getMessage()];
}

// Limpiar buffer y enviar respuesta
$output = ob_get_clean();
if (!empty($output)) {
    // Si hay output, puede ser un error de PHP
    error_log("Output no deseado en AJAX: " . $output);
    // Agregar al response para debugging
    $response['debug_output'] = $output;
}

// Enviar respuesta JSON
echo json_encode($response);

// Cerrar conexión
if (isset($conexion)) {
    mysqli_close($conexion);
}
// NO PONER NADA DESPUÉS DE ESTA LÍNEA
?>