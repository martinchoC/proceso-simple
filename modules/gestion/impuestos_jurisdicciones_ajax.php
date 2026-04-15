<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);  // mostrar errores en pantalla

require_once __DIR__ . '/../../db.php';
require_once "impuestos_jurisdicciones_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 76);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $registros = obtenerImpuestosJurisdicciones($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($registros, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_tipos_impuesto':
            $tipos = obtenerTiposImpuesto($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_jurisdicciones':
            $jurisdicciones = obtenerJurisdiccionesParaSelect($conexion);
            echo json_encode($jurisdicciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'listar_cuentas_contables':
            $cuentas = obtenerCuentasContables($conexion);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'impuesto_tipo_id' => intval($_POST['impuesto_tipo_id'] ?? 0),
                'jurisdiccion_id' => intval($_POST['jurisdiccion_id'] ?? 0),
                'tipo_calculo' => trim($_POST['tipo_calculo'] ?? ''),
                'codigo_local' => trim($_POST['codigo_local'] ?? ''),
                'cuenta_contable_id' => !empty($_POST['cuenta_contable_id']) ? intval($_POST['cuenta_contable_id']) : null,
                'requiere_padron' => intval($_POST['requiere_padron'] ?? 0),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = agregarImpuestoJurisdiccion($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['impuesto_jurisdiccion_id'] ?? 0);
            $data = [
                'impuesto_tipo_id' => intval($_POST['impuesto_tipo_id'] ?? 0),
                'jurisdiccion_id' => intval($_POST['jurisdiccion_id'] ?? 0),
                'tipo_calculo' => trim($_POST['tipo_calculo'] ?? ''),
                'codigo_local' => trim($_POST['codigo_local'] ?? ''),
                'cuenta_contable_id' => !empty($_POST['cuenta_contable_id']) ? intval($_POST['cuenta_contable_id']) : null,
                'requiere_padron' => intval($_POST['requiere_padron'] ?? 0),
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = editarImpuestoJurisdiccion($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $impuesto_jurisdiccion_id = intval($_POST['impuesto_jurisdiccion_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($impuesto_jurisdiccion_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $impuesto_jurisdiccion_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['impuesto_jurisdiccion_id'] ?? $_GET['impuesto_jurisdiccion_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $registro = obtenerImpuestoJurisdiccionPorId($conexion, $id);
            if ($registro) {
                echo json_encode($registro, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Registro no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>