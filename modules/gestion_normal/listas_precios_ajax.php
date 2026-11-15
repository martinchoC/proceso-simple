<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['listas_precios.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "listas_precios_model.php";
require_once __DIR__ . '/../../core/productos_presentaciones.php'; // incluye listas_precios_presentaciones.php

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 53);
// Solo una página de este módulo (si no, la de listas_precios.php del módulo): contra ella se validan botones y acciones
$pagina_id = permisos_pagina_modulo($conn, 5, (int)$pagina_id, 'listas_precios.php');

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $listas = obtenerListasPrecios($conexion, $empresa_idx, $pagina_id);
            echo json_encode($listas, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_reglas':
            $lista_precio_id = isset($_GET['lista_precio_id']) && $_GET['lista_precio_id'] != '' ? intval($_GET['lista_precio_id']) : null;
            $reglas = obtenerReglas($conexion, $empresa_idx, $pagina_id, $lista_precio_id);
            echo json_encode($reglas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $lista_precio_id = intval($_POST['lista_precio_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_id'] ?? 0);
            
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $lista_precio_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'ejecutar_accion_regla':
            $regla_id = intval($_POST['regla_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_id'] ?? 0);
            
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            $resultado = ejecutarTransicionEstadoRegla($conexion, $regla_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'agregar', true);
            $data = [
                'lista_precio_codigo' => trim($_POST['lista_precio_codigo'] ?? ''),
                'lista_precio_nombre' => trim($_POST['lista_precio_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'lista_precio_origen_id' => intval($_POST['lista_precio_origen_id'] ?? 0),
                'lista_base_id' => !empty($_POST['lista_base_id']) ? intval($_POST['lista_base_id']) : null,
                'moneda_id' => !empty($_POST['moneda_id']) ? intval($_POST['moneda_id']) : null,
                'requiere_recalculo' => isset($_POST['requiere_recalculo']),
                'f_ultimo_recalculo' => !empty($_POST['f_ultimo_recalculo']) ? $_POST['f_ultimo_recalculo'] : null,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_id' => $pagina_id
            ];

            $resultado = agregarListaPrecio($conexion, $data);
            // Venta por presentación: solo si el formulario trae la casilla (core/listas_precios_presentaciones.php)
            if (!empty($resultado['resultado']) && isset($_POST['vende_unidad_base'])) {
                lpre_guardar_vende_unidad($conexion, (int)$empresa_idx, (int)($resultado['lista_precio_id'] ?? 0), $_POST['vende_unidad_base'] === '1');
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'editar', true);
            $id = intval($_POST['lista_precio_id'] ?? 0);
            
            $data = [
                'lista_precio_codigo' => trim($_POST['lista_precio_codigo'] ?? ''),
                'lista_precio_nombre' => trim($_POST['lista_precio_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'lista_precio_origen_id' => intval($_POST['lista_precio_origen_id'] ?? 0),
                'lista_base_id' => !empty($_POST['lista_base_id']) ? intval($_POST['lista_base_id']) : null,
                'moneda_id' => !empty($_POST['moneda_id']) ? intval($_POST['moneda_id']) : null,
                'requiere_recalculo' => isset($_POST['requiere_recalculo']),
                'f_ultimo_recalculo' => !empty($_POST['f_ultimo_recalculo']) ? $_POST['f_ultimo_recalculo'] : null,
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarListaPrecio($conexion, $id, $data);
            // Venta por presentación: solo si el formulario trae la casilla (core/listas_precios_presentaciones.php)
            if (!empty($resultado['resultado']) && isset($_POST['vende_unidad_base'])) {
                lpre_guardar_vende_unidad($conexion, (int)$empresa_idx, $id, $_POST['vende_unidad_base'] === '1');
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'importar_presentaciones_simular':
        case 'importar_presentaciones_confirmar':
        case 'importar_presentaciones_plantilla':
            // Precios por presentación desde planilla (core/listas_precios_presentaciones_import.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'editar', true);
            require_once __DIR__ . '/../../core/listas_precios_presentaciones_import.php';
            echo json_encode(lpre_imp_despachar($conexion, (int)$empresa_idx, (int)$ctx['usuario_id'], $accion), JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_regla':
            $data = [
                'lista_precio_id' => intval($_POST['lista_precio_id'] ?? 0),                
                'regla_nombre' => trim($_POST['regla_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'lista_precio_regla_valor_tipo_id' => intval($_POST['lista_precio_regla_valor_tipo_id'] ?? 0),
                'valor_ajuste' => floatval($_POST['valor_ajuste'] ?? 0),
                'producto_id' => !empty($_POST['producto_id']) ? intval($_POST['producto_id']) : null,
                'marca_id' => !empty($_POST['marca_id']) ? intval($_POST['marca_id']) : null,
                'modelo_id' => !empty($_POST['modelo_id']) ? intval($_POST['modelo_id']) : null,
                'submodelo_id' => !empty($_POST['submodelo_id']) ? intval($_POST['submodelo_id']) : null,
                'producto_categoria_id' => !empty($_POST['producto_categoria_id']) ? intval($_POST['producto_categoria_id']) : null,
                'producto_tipo_id' => !empty($_POST['producto_tipo_id']) ? intval($_POST['producto_tipo_id']) : null,
                'entidad_id' => !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null,
                'prioridad' => !empty($_POST['prioridad']) ? intval($_POST['prioridad']) : 100,
                'f_desde' => $_POST['f_desde'] ?? date('Y-m-d'),
                'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
                'es_promocion' => isset($_POST['es_promocion']),
                'permite_acumulacion' => isset($_POST['permite_acumulacion']),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = agregarRegla($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_regla':
            $id = intval($_POST['lista_precio_regla_id'] ?? 0);
            
            $data = [
                'lista_precio_id' => intval($_POST['lista_precio_id'] ?? 0),                
                'regla_nombre' => trim($_POST['regla_nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'lista_precio_regla_valor_tipo_id' => intval($_POST['lista_precio_regla_valor_tipo_id'] ?? 0),
                'valor_ajuste' => floatval($_POST['valor_ajuste'] ?? 0),
                'producto_id' => !empty($_POST['producto_id']) ? intval($_POST['producto_id']) : null,
                'marca_id' => !empty($_POST['marca_id']) ? intval($_POST['marca_id']) : null,
                'modelo_id' => !empty($_POST['modelo_id']) ? intval($_POST['modelo_id']) : null,
                'submodelo_id' => !empty($_POST['submodelo_id']) ? intval($_POST['submodelo_id']) : null,
                'producto_categoria_id' => !empty($_POST['producto_categoria_id']) ? intval($_POST['producto_categoria_id']) : null,
                'producto_tipo_id' => !empty($_POST['producto_tipo_id']) ? intval($_POST['producto_tipo_id']) : null,
                'entidad_id' => !empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null,
                'prioridad' => !empty($_POST['prioridad']) ? intval($_POST['prioridad']) : 100,
                'f_desde' => $_POST['f_desde'] ?? date('Y-m-d'),
                'f_hasta' => !empty($_POST['f_hasta']) ? $_POST['f_hasta'] : null,
                'es_promocion' => isset($_POST['es_promocion']),
                'permite_acumulacion' => isset($_POST['permite_acumulacion']),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarRegla($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['lista_precio_id'] ?? $_GET['lista_precio_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $lista = obtenerListaPrecioPorId($conexion, $id, $empresa_idx);
            if ($lista) {
                echo json_encode($lista, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Lista de precio no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_regla':
            $id = intval($_POST['lista_precio_regla_id'] ?? $_GET['lista_precio_regla_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $regla = obtenerReglaPorId($conexion, $id, $empresa_idx);
            if ($regla) {
                echo json_encode($regla, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Regla no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_origenes':
            $origenes = obtenerOrigenesLista($conexion);
            echo json_encode($origenes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_listas_base':
            $exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : null;
            $listas = obtenerListasBase($conexion, $empresa_idx, $exclude_id);
            echo json_encode($listas, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_modelos':
            $modelos = obtenerModelos($conexion, $empresa_idx);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_submodelos':
            $submodelos = obtenerSubmodelos($conexion, $empresa_idx);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_modelos_por_marca':
            $marca_id = intval($_GET['marca_id'] ?? 0);
            $modelos = obtenerModelosPorMarca($conexion, $empresa_idx, $marca_id);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_submodelos_por_modelo':
            $modelo_id = intval($_GET['modelo_id'] ?? 0);
            $submodelos = obtenerSubmodelosPorModelo($conexion, $empresa_idx, $modelo_id);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;
       

        case 'obtener_tipos_valor_regla':
            $tipos = obtenerTiposValorRegla($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_monedas':
            $monedas = obtenerMonedas($conexion, $empresa_idx);
            echo json_encode($monedas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_productos':
            $productos = obtenerProductos($conexion, $empresa_idx);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            $marcas = obtenerMarcas($conexion, $empresa_idx);
            echo json_encode($marcas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_categorias':
            $categorias = obtenerCategorias($conexion, $empresa_idx);
            echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
            break;

       case 'obtener_modelos':
            $modelos = obtenerModelos($conexion, $empresa_idx);
            echo json_encode($modelos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_submodelos':
            $submodelos = obtenerSubmodelos($conexion, $empresa_idx);
            echo json_encode($submodelos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_tipos_producto':
            $tipos = obtenerTiposProducto($conexion, $empresa_idx);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_entidades':
            $entidades = obtenerEntidades($conexion, $empresa_idx);
            echo json_encode($entidades, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_productos_lista':
            $lista_precio_id = intval($_GET['lista_precio_id'] ?? 0);
            if (empty($lista_precio_id)) {
                echo json_encode(['error' => 'ID de lista no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $productos = obtenerProductosLista($conexion, $lista_precio_id, $empresa_idx);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'recalcular_precios':
            // Reescribe los precios de la lista: mismo permiso que editarla (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'editar', true);
            $lista_precio_id = intval($_POST['lista_precio_id'] ?? 0);
            if (empty($lista_precio_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de lista no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if (listaPrecioOrigenCodigo($conexion, $lista_precio_id, $empresa_idx) === 'MANUAL') {
                echo json_encode(['success' => false, 'error' => LISTA_PRECIO_MANUAL_NO_RECALCULA], JSON_UNESCAPED_UNICODE);
                break;
            }
            $resultado = recalcularPreciosLista($conexion, $lista_precio_id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'simular_recalculo':
            // Muestra qué cambiaría el recálculo sin escribir nada (mismo cálculo, se revierte al final)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'editar', true);
            $lista_precio_id = intval($_POST['lista_precio_id'] ?? 0);
            if (empty($lista_precio_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de lista no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if (listaPrecioOrigenCodigo($conexion, $lista_precio_id, $empresa_idx) === 'MANUAL') {
                echo json_encode(['success' => false, 'error' => LISTA_PRECIO_MANUAL_NO_RECALCULA], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(recalcularPreciosLista($conexion, $lista_precio_id, $empresa_idx, true), JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_precio_manual':
            $lista_precio_producto_id = intval($_POST['lista_precio_producto_id'] ?? 0);
            $precio_manual = floatval($_POST['precio_manual'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            if (empty($lista_precio_producto_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de producto no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if ($precio_manual <= 0) {
                echo json_encode(['success' => false, 'error' => 'Precio manual inválido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = actualizarPrecioManual($conexion, $lista_precio_producto_id, $precio_manual, $observaciones, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    // Throwable, no solo Exception: un ValueError (p.ej. de
    // mysqli_stmt_bind_param con un string de tipos mal contado) no es una
    // Exception y se escapaba de este catch, cortando la respuesta antes de
    // devolver JSON válido.
    error_log("Excepción en listas_precios_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>