<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Cambiar temporalmente para depurar
ini_set('log_errors', 1);
error_log("=== INICIO cont_asientos_ajax.php ===");

function enviarRespuesta($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function manejarError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuesta(['error' => $mensaje]);
}

require_once __DIR__ . '/../../db.php';
require_once "cont_asientos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

error_log("Acción recibida: " . $accion);
error_log("POST data: " . print_r($_POST, true));

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 84);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar_asientos':
            $asientos = obtenerAsientosContables($conexion, $empresa_idx, $pagina_id);
            enviarRespuesta($asientos);
            break;

        case 'ejecutar_accion':
            $registro_id = intval($_POST['id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $pagina_id = intval($_POST['pagina_id'] ?? $pagina_id);
            
            if (empty($registro_id) || empty($accion_js)) {
                enviarRespuesta(['success' => false, 'error' => 'Datos incompletos']);
                break;
            }
            
            $resultado = ejecutarTransicionEstado($conexion, null, $registro_id, $accion_js, $pagina_id);
            enviarRespuesta($resultado);
            break;
            
        case 'obtener_asiento':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 84);
            
            if (empty($id)) {
                enviarRespuesta(['error' => 'ID no proporcionado']);
                break;
            }
            $asiento = obtenerAsientoPorId($conexion, $id, $empresa_idx, $pagina_id);
            if ($asiento) {
                enviarRespuesta($asiento);
            } else {
                enviarRespuesta(['error' => 'Asiento no encontrado']);
            }
            break;
            
        case 'agregar_asiento':
            // Verificar que se recibió la fecha
            $fecha = $_POST['f_asiento'] ?? $_POST['fecha'] ?? '';
            error_log("Fecha recibida en agregar_asiento: " . $fecha);
            
            if (empty($fecha)) {
                enviarRespuesta(['resultado' => false, 'error' => 'La fecha es obligatoria']);
                break;
            }
            
            $data = [
                'fecha' => $fecha,
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_id' => intval($_POST['comprobante_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 1),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            $resultado = agregarAsiento($conexion, $data);
            error_log("Resultado agregar_asiento: " . print_r($resultado, true));
            enviarRespuesta($resultado);
            break;
            
        case 'editar_asiento':
            $id = intval($_POST['id'] ?? 0);
            $fecha = $_POST['f_asiento'] ?? $_POST['fecha'] ?? '';
            error_log("Fecha recibida en editar_asiento: " . $fecha);
            
            if (empty($fecha)) {
                enviarRespuesta(['resultado' => false, 'error' => 'La fecha es obligatoria']);
                break;
            }
            
            $data = [
                'fecha' => $fecha,
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_id' => intval($_POST['comprobante_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 1),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            $resultado = editarAsiento($conexion, $id, $data);
            error_log("Resultado editar_asiento: " . print_r($resultado, true));
            enviarRespuesta($resultado);
            break;
            
        case 'eliminar_asiento':
            $id = intval($_POST['id'] ?? 0);
            $resultado = eliminarAsiento($conexion, $id, $empresa_idx);
            enviarRespuesta($resultado);
            break;
            
        case 'anular_asiento':
            $id = intval($_POST['id'] ?? 0);
            $resultado = anularAsiento($conexion, $id, $empresa_idx);
            enviarRespuesta($resultado);
            break;
            
        case 'registrar_asiento':
            $id = intval($_POST['id'] ?? 0);
            $resultado = registrarAsiento($conexion, $id, $empresa_idx);
            enviarRespuesta($resultado);
            break;
        
        case 'listar_detalles':
            $cont_asiento_id = intval($_GET['cont_asiento_id'] ?? 0);
            $detalles = obtenerDetallesAsiento($conexion, $cont_asiento_id, $empresa_idx);
            enviarRespuesta($detalles);
            break;
            
        case 'obtener_detalle':
            $cont_asiento_id = intval($_GET['cont_asiento_id'] ?? 0);
            $cuenta_id = intval($_GET['cuenta_id'] ?? 0);
            $empresa_idx = intval($_GET['empresa_idx'] ?? 0);
            
            error_log("=== obtener_detalle ===");
            error_log("cont_asiento_id: " . $cont_asiento_id);
            error_log("cuenta_id: " . $cuenta_id);
            
            if ($cont_asiento_id <= 0 || $cuenta_id <= 0) {
                enviarRespuesta(['error' => 'Parámetros inválidos']);
                break;
            }
            
            $detalle = obtenerDetallePorId($conexion, $cont_asiento_id, $cuenta_id, $empresa_idx);
            enviarRespuesta($detalle);
            break;
            
        case 'agregar_detalle':
            $data = [
                'cont_asiento_id' => intval($_POST['cont_asiento_id'] ?? 0),
                'cuenta_id' => intval($_POST['cuenta_id'] ?? 0),
                'importe_local_debe' => floatval($_POST['importe_local_debe'] ?? 0),
                'importe_local_haber' => floatval($_POST['importe_local_haber'] ?? 0),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            $resultado = agregarDetalleAsiento($conexion, $data);
            enviarRespuesta($resultado);
            break;
            
        case 'editar_detalle':
            $id = intval($_POST['id'] ?? 0);
            $data = [
                'cont_asiento_id' => intval($_POST['cont_asiento_id'] ?? 0),
                'cuenta_id' => intval($_POST['cuenta_id'] ?? 0),
                'importe_local_debe' => floatval($_POST['importe_local_debe'] ?? 0),
                'importe_local_haber' => floatval($_POST['importe_local_haber'] ?? 0),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            $resultado = editarDetalleAsiento($conexion, $id, $data);
            enviarRespuesta($resultado);
            break;
            
       case 'eliminar_detalle':
        // Recibir correctamente los parámetros
        $cuenta_id = intval($_POST['cuenta_id'] ?? 0);
        $cont_asiento_id = intval($_POST['cont_asiento_id'] ?? 0);
        $empresa_idx_val = intval($_POST['empresa_idx'] ?? 0);
        
        error_log("=== eliminar_detalle ===");
        error_log("POST completo: " . print_r($_POST, true));
        error_log("cuenta_id recibido: " . $cuenta_id);
        error_log("cont_asiento_id recibido: " . $cont_asiento_id);
        error_log("empresa_idx recibido: " . $empresa_idx_val);
        
        if ($cuenta_id <= 0) {
            enviarRespuesta(['success' => false, 'error' => 'ID de cuenta no válido: ' . $cuenta_id]);
            break;
        }
        
        if ($cont_asiento_id <= 0) {
            enviarRespuesta(['success' => false, 'error' => 'ID de asiento no válido: ' . $cont_asiento_id]);
            break;
        }
        
        $resultado = eliminarDetalleAsiento($conexion, $cuenta_id, $cont_asiento_id, $empresa_idx_val);
        error_log("Resultado eliminar_detalle: " . print_r($resultado, true));
        enviarRespuesta($resultado);
        break;
            
        case 'obtener_totales':
            $cont_asiento_id = intval($_GET['cont_asiento_id'] ?? 0);
            $totales = obtenerTotalesAsiento($conexion, $cont_asiento_id);
            enviarRespuesta($totales);
            break;
            
        case 'obtener_comprobantes':
            $comprobantes = obtenerComprobantes($conexion, $empresa_idx);
            enviarRespuesta($comprobantes);
            break;
            
        case 'obtener_sucursales':
            $sucursales = obtenerSucursales($conexion, $empresa_idx);
            enviarRespuesta($sucursales);
            break;
            
        case 'obtener_depositos':
            $depositos = obtenerDepositos($conexion, $empresa_idx);
            enviarRespuesta($depositos);
            break;
            
        case 'obtener_cuentas':
            $cuentas = obtenerCuentasContables($conexion, $empresa_idx);
            enviarRespuesta($cuentas);
            break;
            
        case 'obtener_monedas':
            $monedas = obtenerMonedas($conexion, $empresa_idx);
            enviarRespuesta($monedas);
            break;
            
        case 'obtener_entidades':
            $entidades = obtenerEntidades($conexion, $empresa_idx);
            enviarRespuesta($entidades);
            break;
        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            enviarRespuesta($boton_agregar);
            break;
            
       default:
            enviarRespuesta(['error' => 'Acción no definida: ' . $accion]);
    }
} catch (Exception $e) {
    error_log("Excepción en cont_asientos_ajax.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    enviarRespuesta(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}

?>