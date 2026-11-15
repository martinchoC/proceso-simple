<?php
/**
 * Acciones ficha_* de productos_ajax.php del módulo 5 (doc 26). pfic_ajax_acciones() responde JSON y corta si la
 * acción es suya; si no, no hace nada. Lectura: con el acceso a la página que ya validó ajax_guard (Productos o su
 * consulta). Escritura: función "editar" de la página y nunca desde la consulta (PRODUCTOS_CONSULTA).
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/proveedores.php';

if (!function_exists('pfic_acciones_lectura')) {
    /** Acciones de lectura (las que admite la consulta de productos). */
    function pfic_acciones_lectura(): array
    {
        return ['ficha_proveedores_listar', 'ficha_proveedores_combo'];
    }
}

if (!function_exists('pfic_ajax_acciones')) {
    function pfic_ajax_acciones(mysqli $conexion, array $ctx, string $accion, int $pagina_id): void
    {
        $escritura = ['ficha_proveedor_guardar', 'ficha_proveedor_quitar', 'ficha_proveedor_principal'];
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
        try {
            switch ($accion) {
                case 'ficha_proveedores_listar':
                    $responder(['success' => true, 'data' => pfpv_listar($conexion, $empresa_id, $producto_id), 'message' => '']);
                case 'ficha_proveedores_combo':
                    $responder(['success' => true, 'data' => pfpv_proveedores($conexion, $empresa_id), 'message' => '']);
                case 'ficha_proveedor_guardar':
                    $id = pfpv_guardar($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0),
                        intval($_POST['entidad_id'] ?? 0), (string) ($_POST['codigo_proveedor'] ?? ''), !empty($_POST['es_principal']));
                    $responder(['success' => true, 'data' => ['producto_proveedor_id' => $id], 'message' => 'Guardado']);
                case 'ficha_proveedor_quitar':
                    pfpv_quitar($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Proveedor quitado']);
                case 'ficha_proveedor_principal':
                    pfpv_principal($conexion, $empresa_id, $producto_id, intval($_POST['producto_proveedor_id'] ?? 0));
                    $responder(['success' => true, 'data' => null, 'message' => 'Proveedor principal actualizado']);
            }
        } catch (InvalidArgumentException $e) {
            $responder(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('pfic_ajax_acciones ' . $accion . ': ' . $e->getMessage());
            $responder(['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.']);
        }
    }
}
