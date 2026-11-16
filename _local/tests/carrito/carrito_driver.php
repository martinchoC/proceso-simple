<?php
// Maneja el carrito (Kernel real del carrito de ECOM_CARRITO_DIR) con una lista de operaciones, como lo haría el
// navegador a través de los controladores: agregar / fijar / eliminar / vaciar / resumen / confirmar.
// Lo corre run_carrito.php: php run_carrito.php carrito_driver.php <base> <erp|-> [carpeta] <ops.json>
// ops.json: {"usuario_id": 1, "entidad_id": 313, "ops": [{"op": "agregar", "producto_id": 1529, "presentacion_id": 0,
//            "cantidad": 3}, {"op": "confirmar", "observaciones": "x"}]}
// Imprime un JSON con el resultado de cada operación ({"ok": true, ...} o {"ok": false, "error": "..."}).
$carrito = (string) getenv('ECOM_CARRITO_DIR');
require_once $carrito . '/bootstrap/autoload.php';

use App\Support\Config;
use App\Support\Env;
use App\Support\Logger;

Env::load((string) getenv('ECOM_TEST_ENV'));
Config::load(require $carrito . '/config/app.php');
date_default_timezone_set((string) Config::get('app.timezone'));
$p = json_decode((string) file_get_contents($argv[1] ?? ''), true);
$logger = new Logger(sys_get_temp_dir() . '/ecom_driver.log');
$kernel = new App\Kernel($carrito, $logger);
$u = (int) $p['usuario_id'];
$e = (int) $p['entidad_id'];
$integrado = method_exists($kernel->carrito(), 'integrado') ? $kernel->carrito()->integrado() : false;
$out = ['integrado' => $integrado, 'ops' => []];
foreach ($p['ops'] as $op) {
    try {
        $pres = (int) ($op['presentacion_id'] ?? 0);
        $args = $pres > 0 ? [$pres] : [];
        switch ($op['op']) {
            case 'agregar':
                $kernel->carrito()->agregar($u, $e, (int) $op['producto_id'], (float) $op['cantidad'], ...$args);
                $r = ['ok' => true];
                break;
            case 'fijar':
                $r = ['ok' => true, 'mensaje' => $kernel->carrito()->fijarCantidad($u, $e, (int) $op['producto_id'], (float) $op['cantidad'], ...$args)];
                break;
            case 'eliminar':
                $kernel->carrito()->eliminar($u, $e, (int) $op['producto_id'], ...$args);
                $r = ['ok' => true];
                break;
            case 'vaciar':
                $kernel->carrito()->vaciar($u, $e);
                $r = ['ok' => true];
                break;
            case 'resumen':
                $res = $kernel->carrito()->resumen($u, $e);
                $r = ['ok' => true, 'total' => $res->total(), 'neto' => $res->subtotalNeto(), 'iva' => $res->iva(), 'no_disponibles' => $res->noDisponibles,
                      'lineas' => array_map(static fn ($l) => ['producto_id' => $l->productoId, 'clave' => method_exists($l, 'clave') ? $l->clave() : (string) $l->productoId,
                          'nombre' => $l->nombre, 'cantidad' => $l->cantidad, 'precio' => $l->precioListaNeto, 'descuento_pct' => $l->descuentoPct,
                          'neto' => $l->netoGravado(), 'etiqueta' => $l->etiqueta ?? null], $res->lineas)];
                break;
            case 'confirmar':
                $r = ['ok' => true] + $kernel->pedidoService()->confirmar($u, $e, (string) ($op['observaciones'] ?? ''), (int) ($op['entidad_sucursal_id'] ?? 0),
                    (int) ($op['sucursal_compra_id'] ?? 0), (string) ($op['direccion'] ?? ''));
                break;
            case 'catalogo':
                // Como CatalogoController: con la integración, productos solo en presentación y marca comercial
                $filtros = array_merge(['terminos' => [], 'codigo' => '', 'marca_id' => 0, 'modelo_id' => 0, 'submodelo_id' => 0], (array) ($op['filtros'] ?? []));
                if ($integrado) {
                    $filtros['_con_presentaciones'] = true;
                }
                $res = $kernel->catalogo()->listar($e, $filtros, 1, 60);
                $ids = array_map(static fn ($p) => (int) $p['producto_id'], $res['items'] ?? []);
                $of = $integrado ? ($kernel->carritoIntegrado()->ofertas($e, $ids)['productos'] ?? []) : [];
                $r = ['ok' => true, 'productos' => $ids, 'ofertas' => $of];
                break;
            case 'pedido_detalle':
                $r = ['ok' => true, 'detalle' => $kernel->pedidoService()->detalle((int) $op['venta_pedido_id'], $e) !== null];
                break;
            default:
                $r = ['ok' => false, 'error' => 'operación desconocida'];
        }
    } catch (Throwable $ex) {
        $r = ['ok' => false, 'error' => $ex->getMessage(), 'tipo' => get_class($ex)];
    }
    $out['ops'][] = $r;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
