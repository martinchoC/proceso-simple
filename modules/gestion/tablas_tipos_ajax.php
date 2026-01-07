<?php
// Configurar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir archivos necesarios
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once "tablas_tipos_model.php";

// Obtener la acción solicitada
$accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

// Configurar cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');

// Establecer conexión a la base de datos
$conexion = conexion();

// Procesar según la acción
switch ($accion) {
    // ============================================================================
    // ACCIONES PARA TIPOS DE TABLAS
    // ============================================================================

    case 'listar_tipos':
        $tipos = obtenerTablasTipos($conexion);
        echo json_encode($tipos);
        break;

    case 'obtener_tipo':
        $id = intval($_GET['tabla_tipo_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'ID inválido']);
            break;
        }

        $tipo = obtenerTablaTipoPorId($conexion, $id);
        if ($tipo) {
            echo json_encode($tipo);
        } else {
            echo json_encode(['error' => 'Tipo no encontrado']);
        }
        break;

    case 'agregar_tipo':
        // Obtener datos desde GET o POST
        $data = [
            'tabla_tipo' => $_POST['tabla_tipo'] ?? ($_GET['tabla_tipo'] ?? ''),
            'tabla_tabla_estado_registro_id' => $_POST['tabla_tabla_estado_registro_id'] ?? ($_GET['tabla_tabla_estado_registro_id'] ?? 1)
        ];

        // Validar datos obligatorios
        if (empty(trim($data['tabla_tipo']))) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del tipo de tabla es obligatorio']);
            break;
        }

        // Agregar tipo
        $resultado = agregarTablaTipo($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_tipo':
        $id = intval($_GET['tabla_tipo_id'] ?? $_POST['tabla_tipo_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
            break;
        }

        $data = [
            'tabla_tipo' => $_POST['tabla_tipo'] ?? ($_GET['tabla_tipo'] ?? ''),
            'tabla_tabla_estado_registro_id' => $_POST['tabla_tabla_estado_registro_id'] ?? ($_GET['tabla_tabla_estado_registro_id'] ?? 1)
        ];

        // Validar datos obligatorios
        if (empty(trim($data['tabla_tipo']))) {
            echo json_encode(['resultado' => false, 'error' => 'El nombre del tipo de tabla es obligatorio']);
            break;
        }

        // Editar tipo
        $resultado = editarTablaTipo($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_tipo':
        $id = intval($_GET['tabla_tipo_id'] ?? 0);
        $nuevo_estado = intval($_GET['nuevo_estado'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
            break;
        }

        $resultado = cambiarEstadoTablaTipo($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    // ============================================================================
    // ACCIONES PARA ESTADOS DE TIPOS DE TABLAS
    // ============================================================================

    case 'listar_estados':
        $tabla_tipo_id = intval($_GET['tabla_tipo_id'] ?? 0);
        if ($tabla_tipo_id <= 0) {
            echo json_encode(['error' => 'ID de tipo de tabla inválido']);
            break;
        }

        $estados = obtenerEstadosPorTablaTipo($conexion, $tabla_tipo_id);
        echo json_encode($estados);
        break;

    case 'obtener_estado':
        $id = intval($_GET['tabla_tipo_estado_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'ID inválido']);
            break;
        }

        $estado = obtenerTablaTipoEstadoPorId($conexion, $id);
        if ($estado) {
            echo json_encode($estado);
        } else {
            echo json_encode(['error' => 'Estado no encontrado']);
        }
        break;

    case 'agregar_estado':
        // Obtener datos desde POST (preferido) o GET
        $data = [
            'tabla_tipo_id' => $_POST['tabla_tipo_id'] ?? ($_GET['tabla_tipo_id'] ?? null),
            'tabla_tipo_estado' => $_POST['tabla_tipo_estado'] ?? ($_GET['tabla_tipo_estado'] ?? ''),
            'valor' => $_POST['valor'] ?? ($_GET['valor'] ?? 1),
            'tabla_tabla_estado_registro_id' => $_POST['tabla_tabla_estado_registro_id'] ?? ($_GET['tabla_tabla_estado_registro_id'] ?? 1),
            'es_inicial' => $_POST['es_inicial'] ?? ($_GET['es_inicial'] ?? 0)
        ];

        // Validar datos obligatorios
        if (empty($data['tabla_tipo_estado']) || empty($data['tabla_tipo_id'])) {
            echo json_encode(['resultado' => false, 'error' => 'El estado y el tipo de tabla son obligatorios']);
            break;
        }

        // Validar que el valor sea positivo
        if ($data['valor'] < 1) {
            $data['valor'] = 1;
        }

        // Agregar estado
        $resultado = agregarTablaTipoEstado($conexion, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar_estado':
        $id = intval($_GET['tabla_tipo_estado_id'] ?? $_POST['tabla_tipo_estado_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
            break;
        }

        $data = [
            'tabla_tipo_estado' => $_POST['tabla_tipo_estado'] ?? ($_GET['tabla_tipo_estado'] ?? ''),
            'valor' => $_POST['valor'] ?? ($_GET['valor'] ?? 1),
            'tabla_tabla_estado_registro_id' => $_POST['tabla_tabla_estado_registro_id'] ?? ($_GET['tabla_tabla_estado_registro_id'] ?? 1),
            'es_inicial' => $_POST['es_inicial'] ?? ($_GET['es_inicial'] ?? 0)
        ];

        // Validar datos obligatorios
        if (empty($data['tabla_tipo_estado'])) {
            echo json_encode(['resultado' => false, 'error' => 'El estado es obligatorio']);
            break;
        }

        // Validar que el valor sea positivo
        if ($data['valor'] < 1) {
            $data['valor'] = 1;
        }

        // Editar estado
        $resultado = editarTablaTipoEstado($conexion, $id, $data);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado_estado':
        $id = intval($_GET['tabla_tipo_estado_id'] ?? 0);
        $nuevo_estado = intval($_GET['nuevo_estado'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'ID inválido']);
            break;
        }

        $resultado = cambiarEstadoTablaTipoEstado($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    // ============================================================================
    // ACCIONES PARA ESTADOS REGISTROS (NUEVA)
    // ============================================================================

    case 'listar_estados_registros':
        $estados = obtenerEstadosRegistros($conexion);
        echo json_encode($estados);
        break;

    // ============================================================================
    // ACCIÓN POR DEFECTO
    // ============================================================================

    default:
        echo json_encode(['error' => 'Acción no definida', 'accion_recibida' => $accion]);
        break;
}

// Cerrar conexión a la base de datos
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>