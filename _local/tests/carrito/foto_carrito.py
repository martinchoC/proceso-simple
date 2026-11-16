"""Foto de lo que graba el carrito al confirmar un pedido (doc 28, paso 9-6): para comparar el carrito antes / después
con la integración apagada. Clona la copia, confirma siempre el mismo carrito con el código del carrito de la carpeta
indicada y escribe las filas nuevas (sin ids, fechas ni números) de las cuatro tablas del pedido y de ecom__*.
Uso: py _local/tests/carrito/foto_carrito.py <carpeta del carrito> <salida.txt> [erp_core_path|-]"""
import json, os, subprocess, sys, tempfile

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'simulacion'))
import simlib  # noqa: E402

carpeta, salida = sys.argv[1], sys.argv[2]
core = sys.argv[3] if len(sys.argv) > 3 else os.path.abspath(os.path.join(AQUI, '..', '..', '..'))
ERP = os.path.abspath(os.path.join(AQUI, '..', '..', '..'))
simlib.clonar_base([
    os.environ['ARCA_OFF'], os.path.join(ERP, '_local', 'sql', 'mantenimiento', '20261123_canales_carrito_app_01.sql'),
    os.path.join(ERP, '_local', 'sql', 'mantenimiento', '20261123_canales_carrito_app_02_carrito_presentaciones.sql')])
maxes = {t: int(simlib.val(f"SELECT IFNULL(MAX({pk}),0) FROM {t}") or 0) for t, pk in [
    ('gestion__ventas_pedidos', 'venta_pedido_id'), ('gestion__ventas_pedidos_detalles', 'venta_pedido_detalle_id'),
    ('gestion__comprobantes', 'comprobante_id'), ('ecom__pedidos', 'venta_pedido_id')]}
ops = {'usuario_id': 1, 'entidad_id': 313, 'ops': [
    {'op': 'vaciar'}, {'op': 'agregar', 'producto_id': 1529, 'cantidad': 3}, {'op': 'agregar', 'producto_id': 1878, 'cantidad': 7},
    {'op': 'fijar', 'producto_id': 1878, 'cantidad': 5}, {'op': 'resumen'}, {'op': 'confirmar', 'observaciones': 'foto carrito'}]}
with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
    json.dump(ops, f)
    ruta = f.name
try:
    r = subprocess.run([simlib.PHP, os.path.join(AQUI, 'run_carrito.php'), 'carrito_driver.php', simlib.DB, core, carpeta, ruta],
                       capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
finally:
    os.unlink(ruta)
res = r.stdout
out = ['== driver', res.strip()[:2000]]


def filas(sql):
    return [json.dumps(x, ensure_ascii=False, sort_keys=True, default=str) for x in simlib.sql(sql)]


def cols(tabla, fuera):
    return ', '.join(c['c'] for c in simlib.sql(f"SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{tabla}' "
                                                 f"ORDER BY ORDINAL_POSITION") if c['c'] not in fuera and 'fecha' not in c['c'] and not c['c'].startswith('f_')
                     and not c['c'].endswith('_en') and c['c'] not in ('creado', 'actualizado'))


FUERA = {'venta_pedido_id', 'venta_pedido_detalle_id', 'comprobante_id', 'registro_origen_id', 'comprobante_nro', 'comprobante_nro_unico'}
out.append('== pedidos')
out += filas(f"SELECT {cols('gestion__ventas_pedidos', FUERA)} FROM gestion__ventas_pedidos WHERE venta_pedido_id > {maxes['gestion__ventas_pedidos']}")
out.append('== detalles')
out += filas(f"SELECT {cols('gestion__ventas_pedidos_detalles', FUERA)} FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_detalle_id > {maxes['gestion__ventas_pedidos_detalles']} ORDER BY producto_id")
out.append('== comprobantes')
out += filas(f"SELECT {cols('gestion__comprobantes', FUERA | {'usuario_modificacion_id'})} FROM gestion__comprobantes WHERE comprobante_id > {maxes['gestion__comprobantes']}")
out.append('== ecom__pedidos')
out += filas(f"SELECT {cols('ecom__pedidos', FUERA)} FROM ecom__pedidos WHERE venta_pedido_id > {maxes['ecom__pedidos']}")
out.append('== ecom__pedidos_margenes')
out += filas(f"SELECT {cols('ecom__pedidos_margenes', FUERA | {'pedido_id', 'pedido_margen_id'})} FROM ecom__pedidos_margenes WHERE pedido_id > {maxes['gestion__ventas_pedidos']}")
out.append('== carrito vacío')
out += filas("SELECT COUNT(*) n FROM ecom__carritos_items ci JOIN ecom__carritos c ON c.carrito_id = ci.carrito_id WHERE c.usuario_id = 1 AND c.empresa_id = 2")
open(salida, 'w', encoding='utf-8').write('\n'.join(out) + '\n')
print('\n'.join(out[:3]))
