<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['clientes.php', 'pedidos_clientes.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "clientes_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($ctx['empresa_id']);
// Sin default propio de esta página (a diferencia de entidades_ajax.php,
// que cae a 50): clientes.php arma pagina_idx desde $_GET['pagina_id']
// y lo manda siempre en cada llamada, así que si llega vacío es porque la
// página no se abrió con el pagina_id correcto.
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar_entidades':
            if (isset($_GET['draw'])) {
                $params = [
                    'draw' => intval($_GET['draw'] ?? 1),
                    'start' => max(0, intval($_GET['start'] ?? 0)),
                    'length' => intval($_GET['length'] ?? 10),
                    'search_value' => trim($_GET['search']['value'] ?? ''),
                    'order_column' => intval($_GET['order'][0]['column'] ?? 1),
                    'order_dir' => strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC',
                ];

                $resultado = obtenerEntidadesPaginadas($conexion, $empresa_idx, $pagina_idx, $params);
                echo json_encode([
                    'draw' => $params['draw'],
                    'recordsTotal' => $resultado['total'],
                    'recordsFiltered' => $resultado['filtered'],
                    'data' => $resultado['data']
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $entidades = obtenerEntidades($conexion, $empresa_idx, $pagina_idx);
                echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'listar_sucursales':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $sucursales = obtenerSucursalesEntidad($conexion, $empresa_idx, $entidad_id, $pagina_idx);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_tipos_entidad':
            $tipos = obtenerTiposEntidad($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_cuentas_contables':
            $cuentas = obtenerCuentasContables($conexion, $empresa_idx);
            echo json_encode($cuentas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_localidades':
            $localidades = obtenerLocalidades($conexion);
            echo json_encode($localidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_entidad':
            $data = [
                'empresa_id' => $empresa_idx,
                'entidad_nombre' => trim($_POST['entidad_nombre'] ?? ''),
                'entidad_fantasia' => trim($_POST['entidad_fantasia'] ?? ''),
                'entidad_tipo_id' => intval($_POST['entidad_tipo_id'] ?? 0),
                'cont_cuenta_id_proveedor' => !empty($_POST['cont_cuenta_id_proveedor']) ? intval($_POST['cont_cuenta_id_proveedor']) : null,
                'cont_cuenta_id_cliente' => !empty($_POST['cont_cuenta_id_cliente']) ? intval($_POST['cont_cuenta_id_cliente']) : null,
                'cuit' => $_POST['cuit'] ? intval($_POST['cuit']) : null,
                'sitio_web' => trim($_POST['sitio_web'] ?? ''),
                'domicilio_legal' => trim($_POST['domicilio_legal'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'es_proveedor' => isset($_POST['es_proveedor']) ? intval($_POST['es_proveedor']) : 0,
                'es_cliente' => isset($_POST['es_cliente']) ? intval($_POST['es_cliente']) : 0,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'pagina_idx' => $pagina_idx
            ];

            // Situación fiscal (sección de Datos Principales): se versiona en la misma transacción
            $fiscal = [
                'condicion_fiscal_id' => intval($_POST['condicion_fiscal_id'] ?? 0),
                'regimen_iibb_id' => intval($_POST['regimen_iibb_id'] ?? 0),
                'numero_inscripcion_iibb' => trim($_POST['numero_inscripcion_iibb'] ?? ''),
                'jurisdiccion_sede_id' => intval($_POST['jurisdiccion_sede_id'] ?? 0),
                'fiscal_f_desde' => trim($_POST['fiscal_f_desde'] ?? '')
            ];
            $resultado = guardarEntidadConSituacionFiscal($conexion, null, $data, $fiscal);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_entidad':
            $id = intval($_POST['entidad_id'] ?? 0);
            $data = [
                'entidad_nombre' => trim($_POST['entidad_nombre'] ?? ''),
                'entidad_fantasia' => trim($_POST['entidad_fantasia'] ?? ''),
                'entidad_tipo_id' => intval($_POST['entidad_tipo_id'] ?? 0),
                'cont_cuenta_id_proveedor' => !empty($_POST['cont_cuenta_id_proveedor']) ? intval($_POST['cont_cuenta_id_proveedor']) : null,
                'cont_cuenta_id_cliente' => !empty($_POST['cont_cuenta_id_cliente']) ? intval($_POST['cont_cuenta_id_cliente']) : null,
                'cuit' => $_POST['cuit'] ? intval($_POST['cuit']) : null,
                'sitio_web' => trim($_POST['sitio_web'] ?? ''),
                'domicilio_legal' => trim($_POST['domicilio_legal'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'es_proveedor' => isset($_POST['es_proveedor']) ? intval($_POST['es_proveedor']) : 0,
                'es_cliente' => isset($_POST['es_cliente']) ? intval($_POST['es_cliente']) : 0,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            // Situación fiscal (sección de Datos Principales): se versiona en la misma transacción
            $fiscal = [
                'condicion_fiscal_id' => intval($_POST['condicion_fiscal_id'] ?? 0),
                'regimen_iibb_id' => intval($_POST['regimen_iibb_id'] ?? 0),
                'numero_inscripcion_iibb' => trim($_POST['numero_inscripcion_iibb'] ?? ''),
                'jurisdiccion_sede_id' => intval($_POST['jurisdiccion_sede_id'] ?? 0),
                'fiscal_f_desde' => trim($_POST['fiscal_f_desde'] ?? '')
            ];
            $resultado = guardarEntidadConSituacionFiscal($conexion, $id, $data, $fiscal);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_sucursal':
            $data = [
                'empresa_id' => $empresa_idx,
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'sucursal_direccion' => trim($_POST['sucursal_direccion'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'sucursal_telefono' => trim($_POST['sucursal_telefono'] ?? ''),
                'sucursal_email' => trim($_POST['sucursal_email'] ?? ''),
                'sucursal_contacto' => trim($_POST['sucursal_contacto'] ?? ''),
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarSucursal($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_sucursal':
            $id = intval($_POST['sucursal_id'] ?? 0);
            $data = [
                'sucursal_nombre' => trim($_POST['sucursal_nombre'] ?? ''),
                'sucursal_direccion' => trim($_POST['sucursal_direccion'] ?? ''),
                'localidad_id' => $_POST['localidad_id'] ? intval($_POST['localidad_id']) : null,
                'sucursal_telefono' => trim($_POST['sucursal_telefono'] ?? ''),
                'sucursal_email' => trim($_POST['sucursal_email'] ?? ''),
                'sucursal_contacto' => trim($_POST['sucursal_contacto'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarSucursal($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_entidad':
            $entidad_id = intval($_POST['entidad_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($entidad_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $entidad_id, $accion_js, $empresa_idx, $pagina_idx, 'entidad');
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion_sucursal':
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($sucursal_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $sucursal_id, $accion_js, $empresa_idx, $pagina_idx, 'sucursal');
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_entidad':
            $id = intval($_POST['entidad_id'] ?? $_GET['entidad_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $entidad = obtenerEntidadPorId($conexion, $id, $empresa_idx);
            if ($entidad) {
                echo json_encode($entidad, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Entidad no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_sucursal':
            $id = intval($_POST['sucursal_id'] ?? $_GET['sucursal_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $sucursal = obtenerSucursalPorId($conexion, $id, $empresa_idx);
            if ($sucursal) {
                echo json_encode($sucursal, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Sucursal no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;
            case 'obtener_condiciones_pago':
    $condiciones = obtenerCondicionesPago($conexion, $empresa_idx);
    echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_listas_precios':
    $listas = obtenerListasPrecios($conexion, $empresa_idx);
    echo json_encode($listas, JSON_UNESCAPED_UNICODE);
    break;

case 'obtener_categorias_proveedores':
    $categorias = obtenerCategoriasProveedores($conexion);
    echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
    break;

case 'listar_condiciones_cliente':
    $entidad_id = intval($_GET['entidad_id'] ?? 0);
    if (empty($entidad_id)) {
        echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
        break;
    }
    
    $condiciones = obtenerCondicionesCliente($conexion, $empresa_idx, $entidad_id, $pagina_idx);
    echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
    break;

case 'editar_condicion_cliente':
    $id = intval($_POST['condicion_cliente_id'] ?? 0);
    $data = [
        'entidad_id' => intval($_POST['entidad_id'] ?? 0),
        'entidad_cliente_tipo_id' => isset($_POST['entidad_cliente_tipo_id']) && $_POST['entidad_cliente_tipo_id'] !== '' ? intval($_POST['entidad_cliente_tipo_id']) : null,
        'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
        'lista_precio_id' => isset($_POST['lista_precio_id']) && $_POST['lista_precio_id'] !== '' ? intval($_POST['lista_precio_id']) : null,
        'limite_credito' => isset($_POST['limite_credito']) && $_POST['limite_credito'] !== '' ? floatval($_POST['limite_credito']) : null,
        'cliente_descuento_general' => isset($_POST['cliente_descuento_general']) && $_POST['cliente_descuento_general'] !== '' ? floatval($_POST['cliente_descuento_general']) : null,
        'f_desde' => $_POST['f_desde'] ?? '',
        'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null
    ];
    
    $resultado = editarCondicionCliente($conexion, $id, $data);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    break;

case 'listar_condiciones_proveedor':
    $entidad_id = intval($_GET['entidad_id'] ?? 0);
    if (empty($entidad_id)) {
        echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
        break;
    }
    
    $condiciones = obtenerCondicionesProveedor($conexion, $empresa_idx, $entidad_id, $pagina_idx);
    echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
    break;
    // Obtener condiciones de cliente para una entidad - CON TIPO DE CLIENTE (CORREGIDO)
    function obtenerCondicionesCliente($conexion, $empresa_idx, $entidad_id, $pagina_id) {
        $pagina_id = intval($pagina_id);
        $entidad_id = intval($entidad_id);
        
        $sql = "SELECT cc.*, 
                    cp.condicion_pago,
                    lp.lista_precio_nombre AS lista_precio,
                    ect.entidad_cliente_tipo,
                    er.estado_registro,
                    ec.color_clase, ec.bg_clase, ec.text_clase
                FROM gestion__entidades_condiciones_clientes cc
                LEFT JOIN gestion__condiciones_pago cp ON cc.condicion_pago_id = cp.condicion_pago_id
                LEFT JOIN gestion__listas_precios lp ON cc.lista_precio_id = lp.lista_precio_id
                LEFT JOIN gestion__entidades_clientes_tipos ect ON cc.entidad_cliente_tipo_id = ect.entidad_cliente_tipo_id
                LEFT JOIN conf__estados_registros er ON cc.tabla_estado_registro_id = er.estado_registro_id
                LEFT JOIN conf__colores ec ON er.color_id = ec.color_id
                WHERE cc.entidad_id = ?
                ORDER BY cc.f_desde DESC";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return [];
        
        mysqli_stmt_bind_param($stmt, "i", $entidad_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $data = [];
        while ($fila = mysqli_fetch_assoc($result)) {
            $color_clase = $fila['color_clase'] ?? 'btn-dark';
            $bg_clase = $fila['bg_clase'] ?? 'bg-dark';
            $text_clase = $fila['text_clase'] ?? 'text-white';
            
            $fila['estado_info'] = [
                'estado_registro' => $fila['estado_registro'] ?? ($fila['tabla_estado_registro_id'] == 1 ? 'Activo' : 'Inactivo'),
                'codigo_estandar' => $fila['codigo_estandar'] ?? ($fila['tabla_estado_registro_id'] == 1 ? 'ACTIVO' : 'INACTIVO'),
                'color_clase' => $color_clase,
                'bg_clase' => $bg_clase,
                'text_clase' => $text_clase
            ];
            
            // Asegurar que el tipo de cliente esté disponible
            $fila['entidad_cliente_tipo'] = $fila['entidad_cliente_tipo'] ?? null;
            
            $data[] = $fila;
        }
        
        mysqli_stmt_close($stmt);
        return $data;
    }
    case 'agregar_condicion_cliente':
        // Debug
        error_log("POST en agregar_condicion_cliente: " . print_r($_POST, true));
        
        $data = [
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'condicion_pago_id' => isset($_POST['condicion_pago_id']) && $_POST['condicion_pago_id'] !== '' ? intval($_POST['condicion_pago_id']) : null,
            'entidad_cliente_tipo_id' => isset($_POST['entidad_cliente_tipo_id']) && $_POST['entidad_cliente_tipo_id'] !== '' ? intval($_POST['entidad_cliente_tipo_id']) : null,
            'lista_precio_id' => isset($_POST['lista_precio_id']) && $_POST['lista_precio_id'] !== '' ? intval($_POST['lista_precio_id']) : null,
            'limite_credito' => isset($_POST['limite_credito']) && $_POST['limite_credito'] !== '' ? floatval($_POST['limite_credito']) : null,
            'cliente_descuento_general' => isset($_POST['cliente_descuento_general']) && $_POST['cliente_descuento_general'] !== '' ? floatval($_POST['cliente_descuento_general']) : null,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null
        ];
        
        error_log("Data procesada: " . print_r($data, true));
        
        $resultado = agregarCondicionCliente($conexion, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'editar_condicion_cliente':
        $id = intval($_POST['condicion_cliente_id'] ?? 0);
        $data = [
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'condicion_pago_id' => isset($_POST['condicion_pago_id']) && $_POST['condicion_pago_id'] !== '' ? intval($_POST['condicion_pago_id']) : null,
            'entidad_cliente_tipo_id' => isset($_POST['entidad_cliente_tipo_id']) && $_POST['entidad_cliente_tipo_id'] !== '' ? intval($_POST['entidad_cliente_tipo_id']) : null,
            'lista_precio_id' => isset($_POST['lista_precio_id']) && $_POST['lista_precio_id'] !== '' ? intval($_POST['lista_precio_id']) : null,
            'limite_credito' => isset($_POST['limite_credito']) && $_POST['limite_credito'] !== '' ? floatval($_POST['limite_credito']) : null,
            'cliente_descuento_general' => isset($_POST['cliente_descuento_general']) && $_POST['cliente_descuento_general'] !== '' ? floatval($_POST['cliente_descuento_general']) : null,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null
        ];
        
        $resultado = editarCondicionCliente($conexion, $id, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'agregar_condicion_proveedor':
        $data = [
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
            'proveedor_categoria_id' => $_POST['proveedor_categoria_id'] ? intval($_POST['proveedor_categoria_id']) : null,
            'proveedor_descuento_general' => $_POST['proveedor_descuento_general'] ?? null,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => $_POST['f_hasta'] ?? null
        ];
        
        $resultado = agregarCondicionProveedor($conexion, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condicion_cliente':
        $id = intval($_POST['condicion_cliente_id'] ?? $_GET['condicion_cliente_id'] ?? 0);
        if (empty($id)) {
            echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $condicion = obtenerCondicionClientePorId($conexion, $id);
        if ($condicion) {
            echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Condición no encontrada'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'obtener_condicion_proveedor':
        $id = intval($_POST['condicion_proveedor_id'] ?? $_GET['condicion_proveedor_id'] ?? 0);
        if (empty($id)) {
            echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $condicion = obtenerCondicionProveedorPorId($conexion, $id);
        if ($condicion) {
            echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Condición no encontrada'], JSON_UNESCAPED_UNICODE);
        }
        break;

        case 'obtener_condicion_cliente_vigente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $condicion = obtenerCondicionClienteVigente($conexion, $entidad_id);
            echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_condicion_proveedor_vigente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $condicion = obtenerCondicionProveedorVigente($conexion, $entidad_id);
            echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_historial_condiciones_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $historial = obtenerHistorialCondicionesCliente($conexion, $entidad_id);
            echo json_encode($historial, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_historial_condiciones_proveedor':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $historial = obtenerHistorialCondicionesProveedor($conexion, $entidad_id);
            echo json_encode($historial, JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_sucursales_compra':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Modificar la función para que solo traiga activas
            $sucursales = obtenerSucursalesCompraPorEntidad($conexion, $empresa_idx, $entidad_id);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

    case 'obtener_entidades_por_sucursal':
        $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
        if (empty($sucursal_id)) {
            echo json_encode(['error' => 'ID de sucursal no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $entidades = obtenerEntidadesPorSucursalCompra($conexion, $empresa_idx, $sucursal_id);
        echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
        break;

    case 'agregar_vinculacion_compra':
        // Debug: Ver qué está llegando
        error_log("POST data: " . print_r($_POST, true));
        
        $data = [
            'empresa_id' => $empresa_idx,
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
            'es_principal' => isset($_POST['es_principal']) ? intval($_POST['es_principal']) : 0,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
            'observaciones' => trim($_POST['observaciones'] ?? '')
        ];
        
        error_log("Data a enviar a agregarVinculacionCompra: " . print_r($data, true));
        
        $resultado = agregarVinculacionCompra($conexion, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'cerrar_vinculacion_compra':
        $vinculacion_id = intval($_POST['vinculacion_id'] ?? 0);
        $fecha_cierre = $_POST['fecha_cierre'] ?? null;
        
        $resultado = cerrarVinculacionCompra($conexion, $vinculacion_id, $fecha_cierre);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_sucursales_sociedad':
        // Obtener todas las sucursales de la sociedad para combo
        $sql = "SELECT sucursal_id, sucursal_nombre, direccion 
                FROM gestion__sucursales 
                WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                ORDER BY sucursal_nombre";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $sucursales = [];
        while ($fila = mysqli_fetch_assoc($result)) {
            $sucursales[] = $fila;
        }
        
        mysqli_stmt_close($stmt);
        echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_tipos_cliente':
        $tipos = obtenerTiposCliente($conexion);
        echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
        break;
    
    case 'verificar_acceso_web_cliente':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $resultado = verificarAccesoWebCliente($conexion, $entidad_id);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'verificar_usuario_web_entidad':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resultado = obtenerUsuarioWebEntidad($conexion, $entidad_id);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'alta_usuario_web_entidad':
        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['resultado' => false, 'error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resultado = altaUsuarioWebEntidad($conexion, $entidad_id);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'regenerar_password_usuario_web_entidad':
        $entidad_id = intval($_POST['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['resultado' => false, 'error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $resultado = regenerarPasswordUsuarioWebEntidad($conexion, $entidad_id);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    // ============================================
    // CONDICIONES FISCALES (gestion__entidades_condiciones_fiscales)
    // ============================================
    case 'obtener_condicion_fiscal_vigente':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $condicion = obtenerCondicionFiscalVigente($conexion, $entidad_id);
        echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        break;

    case 'listar_condiciones_fiscales':
    case 'obtener_historial_condiciones_fiscales':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $historial = obtenerHistorialCondicionesFiscales($conexion, $entidad_id);
        echo json_encode($historial, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condiciones_fiscales_catalogo':
        $catalogo = obtenerCondicionesFiscalesCatalogo($conexion);
        echo json_encode($catalogo, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condicion_fiscal':
        $id = intval($_GET['entidad_condicion_fiscal_id'] ?? 0);
        if (empty($id)) {
            echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $condicion = obtenerCondicionFiscalPorId($conexion, $id);
        echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        break;

    case 'guardar_condicion_fiscal':
        $data = [
            'entidad_condicion_fiscal_id' => intval($_POST['entidad_condicion_fiscal_id'] ?? 0),
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'condicion_fiscal_id' => intval($_POST['condicion_fiscal_id'] ?? 0),
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
            'cerrar_anterior' => isset($_POST['cerrar_anterior']) ? intval($_POST['cerrar_anterior']) : 0
        ];
        $resultado = guardarCondicionFiscal($conexion, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'cambiar_estado_condicion_fiscal':
        $id = intval($_POST['entidad_condicion_fiscal_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
        $resultado = cambiarEstadoCondicionFiscal($conexion, $id, $nuevo_estado);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    // ============================================
    // NIVEL PROVINCIAL (IIBB) (gestion__entidades_condiciones_iibb)
    // ============================================
    case 'obtener_regimenes_iibb':
        $regimenes = obtenerRegimenesIIBB($conexion);
        echo json_encode($regimenes, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_jurisdicciones_sedes_iibb':
        $sedes = obtenerJurisdiccionesSedesIIBB($conexion);
        echo json_encode($sedes, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condicion_iibb_vigente':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $condicion = obtenerCondicionIIBBVigente($conexion, $entidad_id);
        echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        break;

    case 'listar_condiciones_iibb':
    case 'obtener_historial_condiciones_iibb':
        $entidad_id = intval($_GET['entidad_id'] ?? 0);
        if (empty($entidad_id)) {
            echo json_encode(['error' => 'ID de entidad no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $historial = obtenerHistorialCondicionesIIBB($conexion, $entidad_id);
        echo json_encode($historial, JSON_UNESCAPED_UNICODE);
        break;

    case 'obtener_condicion_iibb':
        $id = intval($_GET['entidad_condicion_iibb_id'] ?? 0);
        if (empty($id)) {
            echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $condicion = obtenerCondicionIIBBPorId($conexion, $id);
        echo json_encode($condicion, JSON_UNESCAPED_UNICODE);
        break;

    case 'guardar_condicion_iibb':
        $data = [
            'entidad_condicion_iibb_id' => intval($_POST['entidad_condicion_iibb_id'] ?? 0),
            'entidad_id' => intval($_POST['entidad_id'] ?? 0),
            'regimen_iibb_id' => intval($_POST['regimen_iibb_id'] ?? 0),
            'numero_inscripcion' => $_POST['numero_inscripcion'] ?? null,
            'jurisdiccion_sede_id' => !empty($_POST['jurisdiccion_sede_id']) ? intval($_POST['jurisdiccion_sede_id']) : null,
            'f_desde' => $_POST['f_desde'] ?? '',
            'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
            'observaciones' => $_POST['observaciones'] ?? null,
            'cerrar_anterior' => isset($_POST['cerrar_anterior']) ? intval($_POST['cerrar_anterior']) : 0
        ];
        $resultado = guardarCondicionIIBB($conexion, $data);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        break;

    case 'cambiar_estado_condicion_iibb':
        $id = intval($_POST['entidad_condicion_iibb_id'] ?? 0);
        $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
        $resultado = cambiarEstadoCondicionIIBB($conexion, $id, $nuevo_estado);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
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