<?php
/**
 * Acciones ficha_* de productos_ajax.php del módulo 5 (doc 26). pfic_ajax_acciones() responde JSON y corta si la
 * acción es suya; si no, no hace nada. Lectura: con el acceso a la página que ya validó ajax_guard (Productos o su
 * consulta). Escritura: función "editar" de la página y nunca desde la consulta (PRODUCTOS_CONSULTA).
 * Proveedores (pfpv_*), Ubicaciones (pfub_*) e Imágenes (pfim_*); Precios usa listas_precios_productos_ajax.php.
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/proveedores.php';
require_once __DIR__ . '/ubicaciones.php';
require_once __DIR__ . '/imagenes.php';

if (!function_exists('pfic_acciones_lectura')) {
    /** Acciones de lectura (las que admite la consulta de productos). */
    function pfic_acciones_lectura(): array
    {
        return ['ficha_proveedores_listar', 'ficha_proveedores_combo', 'ficha_ubicaciones_listar', 'ficha_ubicaciones_opciones',
                'ficha_imagenes_listar'];
    }
}

if (!function_exists('pfic_ajax_acciones')) {
    function pfic_ajax_acciones(mysqli $conexion, array $ctx, string $accion, int $pagina_id): void
    {
        $escritura = ['ficha_proveedor_guardar', 'ficha_proveedor_quitar', 'ficha_proveedor_principal',
                      'ficha_ubicacion_asignar', 'ficha_ubicacion_quitar', 'ficha_ubicacion_crear',
                      'ficha_imagen_subir', 'ficha_imagen_editar', 'ficha_imagen_principal', 'ficha_imagen_mover', 'ficha_imagen_quitar'];
        if (!in_array($accion, pfic_acciones_lectura(), true) && !in_array($accion, $escritura, true)) {
            return;
        }
        $empresa_id = (int) $ctx['empresa_id'];
        $responder = function (array $r) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($r, JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!pfic_empresa_usa($conexion, $empresa_id)) {
            $responder(['success' => false, 'data' => null, 'message' => 'La empresa no usa la ficha de producto completa (Configuración > Empresas).']);
        }
        if (in_array($accion, $escritura, true)) {
            if (defined('PRODUCTOS_CONSULTA')) {
                $responder(['success' => false, 'data' => null, 'message' => 'La consulta de productos es de solo lectura.']);
            }
            if (function_exists('permisos_exigir_funcion')) {
                permisos_exigir_funcion($conexion, $pagina_id, 'editar', true);
            }
        }
        $producto_id = intval($_REQUEST['producto_id'] ?? 0);
        $usuario_id = (int) ($ctx['usuario_id'] ?? 0);
        try {
            switch ($accion) {
                case 'ficha_proveedores_listar':
                    // presentaciones_compra: solo con la compra por presentación (la pantalla muestra el selector)
                    $responder(['success' => true, 'data' => pfpv_listar($conexion, $empresa_id, $producto_id), 'message' => '',
                        'presentaciones_compra' => cpre_proveedor_activo($conexion, $empresa_id)
                            ? (cpre_presentaciones($conexion, $empresa_id, [$producto_id])[$producto_id] ?? []) : null]);
                case 'ficha_proveedores_combo':
                    $responder(['success' => true, 'data' => pfpv_proveedores($conexion, $empresa_id), 'message' => '']);
                case 'ficha_proveedor_guardar':
                    $con_pres = isset($_POST['presentacion_id']) && cpre_proveedor_activo($conexion, $empresa_id);
                    if ($con_pres) {
                        cpre_validar_presentacion_proveedor($conexion, $empresa_id, $producto_id, intval($_POST['presentacion_id']));
                    }
                    $id = pfpv_guardar($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0),
                        intval($_POST['entidad_id'] ?? 0), (string) ($_POST['codigo_proveedor'] ?? ''), !empty($_POST['es_principal']));
                    if ($con_pres) {
                        cpre_guardar_presentacion_proveedor($conexion, $empresa_id, $producto_id, $id, intval($_POST['presentacion_id']));
                    }
                    $responder(['success' => true, 'data' => ['producto_proveedor_id' => $id], 'message' => 'Guardado']);
                case 'ficha_proveedor_quitar':
                    pfpv_quitar($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Proveedor quitado']);
                case 'ficha_proveedor_principal':
                    pfpv_principal($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Proveedor principal actualizado']);
                case 'ficha_ubicaciones_listar':
                    $responder(['success' => true, 'data' => pfub_listar($conexion, $empresa_id, $usuario_id, $producto_id), 'message' => '']);
                case 'ficha_ubicaciones_opciones':
                    $responder(['success' => true, 'data' => ['ubicaciones' => pfub_disponibles($conexion, $empresa_id, $usuario_id),
                        'sucursales' => pfub_sucursales($conexion, $empresa_id, $usuario_id)], 'message' => '']);
                case 'ficha_ubicacion_asignar':
                    $id = pfub_asignar($conexion, $empresa_id, $usuario_id, $producto_id, intval($_POST['sucursal_ubicacion_id'] ?? 0));
                    $responder(['success' => true, 'data' => ['producto_ubicacion_id' => $id], 'message' => 'Ubicación asignada']);
                case 'ficha_ubicacion_quitar':
                    pfub_quitar($conexion, $empresa_id, $usuario_id, $producto_id, intval($_POST['producto_ubicacion_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Ubicación quitada']);
                case 'ficha_ubicacion_crear':
                    // Crea la ubicación de la sucursal y la asigna al producto, todo o nada
                    pfic_producto_de_empresa($conexion, $empresa_id, $producto_id);
                    mysqli_begin_transaction($conexion);
                    try {
                        $su = pfub_crear($conexion, $empresa_id, $usuario_id, $_POST);
                        $id = pfub_asignar($conexion, $empresa_id, $usuario_id, $producto_id, $su);
                        mysqli_commit($conexion);
                    } catch (Throwable $e) {
                        mysqli_rollback($conexion);
                        throw $e;
                    }
                    $responder(['success' => true, 'data' => ['sucursal_ubicacion_id' => $su, 'producto_ubicacion_id' => $id], 'message' => 'Ubicación creada y asignada']);
                case 'ficha_imagenes_listar':
                    $responder(['success' => true, 'data' => pfim_listar($conexion, $empresa_id, $producto_id), 'message' => '']);
                case 'ficha_imagen_subir':
                    $r = pfim_subir($conexion, $empresa_id, $usuario_id, $producto_id, $_FILES['imagen'] ?? null,
                        (string) ($_POST['descripcion'] ?? ''), !empty($_POST['es_principal']));
                    $responder(['success' => true, 'data' => $r, 'message' => 'Imagen subida']);
                case 'ficha_imagen_editar':
                    pfim_editar($conexion, $empresa_id, $producto_id, intval($_POST['producto_imagen_id'] ?? 0), (string) ($_POST['descripcion'] ?? ''));
                    $responder(['success' => true, 'data' => null, 'message' => 'Guardado']);
                case 'ficha_imagen_principal':
                    pfim_principal($conexion, $empresa_id, $producto_id, intval($_POST['producto_imagen_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Imagen principal actualizada']);
                case 'ficha_imagen_mover':
                    pfim_mover($conexion, $empresa_id, $producto_id, intval($_POST['producto_imagen_id'] ?? 0), intval($_POST['sentido'] ?? 1));
                    $responder(['success' => true, 'data' => null, 'message' => 'Orden actualizado']);
                case 'ficha_imagen_quitar':
                    pfim_quitar($conexion, $empresa_id, $producto_id, intval($_POST['producto_imagen_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Imagen quitada']);
            }
        } catch (InvalidArgumentException $e) {
            $responder(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('pfic_ajax_acciones ' . $accion . ': ' . $e->getMessage());
            $responder(['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.']);
        }
    }
}
