from simlib import *

# Mostrador minorista (doc 29): mostrador_ajax.php del módulo 2 (página 150). Consumidor final con la lista Mostrador,
# código de barras de una caja, unidades y cajas, cobro de contado y factura aprobada en el momento; con cliente, a su
# cuenta corriente; apagado y sin la función, no se usa.
#   python _local/tests/simulacion/run.py 46_mostrador.py --scripts <arca_off.sql> _local/sql/mantenimiento/20261124_mostrador_01.sql

P1, P2 = 1529, 1878
CLI = 313
CTX = {}


def m(accion, params=None, method='GET', usuario=1):
    return ajax('mostrador_ajax.php', dict(params or {}, accion=accion, empresa_idx=2, pagina_idx=150), method, usuario_id=usuario)


def d(r):
    return (r.get('data') if isinstance(r, dict) else None) or {}


caso('Mostrador: apagado')
r = m('inicio')
check(not ok_resp(r) and 'no está habilitado' in (r.get('message') or ''), 'con usa_mostrador apagado no se usa', str(r)[:200])

caso('Mostrador: preparación (solo en la copia)')
CTX['ind'] = uno("SELECT usa_mostrador a, usa_presentaciones b, usa_venta_presentaciones c FROM conf__empresas WHERE empresa_id = 2")
sql("UPDATE conf__empresas SET usa_mostrador = 1, usa_presentaciones = 1, usa_venta_presentaciones = 1 WHERE empresa_id = 2")
most = int(val("SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = 2 AND lista_precio_codigo = 'MOST' AND tabla_estado_registro_id = 1 LIMIT 1") or 3)
sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, es_venta_defecto, "
    f"es_compra_defecto, orden, codigo_barras, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM46 Caja x 12', 12, 1, 0, 0, 0, 99, '7799990004699', 1)")
CTX['caja'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE presentacion_nombre = 'SIM46 Caja x 12'"))
sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
    f"VALUES (2, {most}, {CTX['caja']}, {P2}, 120000, CURDATE(), 1)")
check(int(val("SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (150, 650)")) == 2, 'páginas 150 / 650 del script', '')

caso('Mostrador: venta a consumidor final')
caja = CTX['caja']
r = m('inicio')
pvs, medios = d(r).get('puntos_venta') or [], d(r).get('medios') or []
check(ok_resp(r) and pvs and any(x['tipo'] == 'EFECTIVO' for x in medios) and d(r).get('lista_mostrador') == most and d(r).get('puede_facturar'),
      'inicio: punto de venta con factura a consumidor final, medios y lista Mostrador', str(r)[:300])
pv = pvs[0]['punto_venta_id'] if pvs else 0
r = m('buscar', dict(q='7799990004699', entidad_id=0, punto_venta_id=pv))
ps = d(r).get('productos') or []
check(len(ps) == 1 and ps[0]['presentacion_escaneada'] == caja and any(o['presentacion_id'] == caja and o['precio'] == 120000 for o in ps[0]['ofertas']),
      'el código de barras de la caja trae la caja con el precio de la lista Mostrador', str(r)[:300])
items = [dict(producto_id=P1, cantidad=2), dict(producto_id=P2, presentacion_id=caja, cantidad=1)]
r = m('valorizar', dict(items=json.dumps(items), entidad_id=0, punto_venta_id=pv), 'POST')
total = float((d(r).get('cabecera') or {}).get('total') or 0)
p1_most = num(val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE lista_precio_id = {most} AND producto_id = {P1} AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1"))
check(ok_resp(r) and abs(total - round((2 * p1_most + 120000) * 1.21, 2)) < 0.05, f'total con la lista Mostrador: {total}', str(r)[:300])
efvo = next((x for x in medios if x['tipo'] == 'EFECTIVO'), {})
caja_cta = int(val("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = 2 AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1 ORDER BY tesoreria_cuenta_id LIMIT 1"))
r = m('facturar', dict(items=json.dumps(items), entidad_id=0, punto_venta_id=pv, medios=json.dumps([dict(medio_pago_id=efvo.get('medio_pago_id'), tesoreria_cuenta_id=caja_cta, importe=100)])), 'POST')
fid = d(r).get('venta_factura_id')
check(not d(r).get('aprobada') and fid, 'medios que no cubren el total: la factura queda sin aprobar', str(r)[:300])
s1, s2 = stock(P1), stock(P2)
saldo0 = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {caja_cta}"))
r = m('facturar', dict(items=json.dumps(items), entidad_id=0, punto_venta_id=pv, consumidor_final_nombre='SIM46 Mostrador',
                       medios=json.dumps([dict(medio_pago_id=efvo.get('medio_pago_id'), tesoreria_cuenta_id=caja_cta, importe=total)])), 'POST')
f = d(r)
check(ok_resp(r) and f.get('aprobada') and f.get('comprobante_nro', 0) > 0 and abs(f.get('total', 0) - total) < 0.01, 'cobrar y facturar: factura aprobada y numerada', str(r)[:300])
if f.get('venta_factura_id'):
    fid = f['venta_factura_id']
    check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}")) in (5, 40), 'estado de la factura', '')
    check(stock(P1) == s1 - 2 and stock(P2) == s2 - 12, f'stock -2 / -12 ({s1}->{stock(P1)}, {s2}->{stock(P2)})')
    lr = uno(f"SELECT presentacion_id, presentacion_cantidad FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = {fid} AND producto_id = {P2}")
    check(lr and int(lr['presentacion_id'] or 0) == caja and num(lr['presentacion_cantidad']) == 1, 'la factura guarda la caja', str(lr))
    check(abs(num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {caja_cta}")) - (saldo0 + total)) < 0.01, 'la caja suma el cobro')
    check(int(val(f"SELECT entidad_id IS NULL OR entidad_id = 0 FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}") or 0) == 1, 'a consumidor final')

caso('Mostrador: venta a un cliente (cuenta corriente)')
r = m('clientes', dict(q='ABEJER'))
check(ok_resp(r) and any(int(c['entidad_id']) == CLI for c in (r.get('data') or [])), 'busca el cliente', str(r)[:200])
r = m('facturar', dict(items=json.dumps([dict(producto_id=P1, cantidad=1)]), entidad_id=CLI, punto_venta_id=pv), 'POST')
check(ok_resp(r) and d(r).get('venta_factura_id'), 'factura al cliente (sin cobro en el momento)', str(r)[:300])

caso('Mostrador: permisos')
r = m('facturar', dict(items=json.dumps(items), entidad_id=0, punto_venta_id=pv, medios='[]'), 'POST', usuario=8)
check(not ok_resp(r), 'un usuario sin la página / función no factura', str(r)[:200])
r = ajax('mostrador_ajax.php', dict(accion='facturar', items=json.dumps(items), entidad_id=0, punto_venta_id=pv, medios='[]', empresa_idx=2, pagina_idx=150, _sin_csrf=True), 'POST')
check(not ok_resp(r), 'sin CSRF no factura', str(r)[:200])

caso('Mostrador: limpieza')
i = CTX['ind']
sql(f"UPDATE conf__empresas SET usa_mostrador = {i['a']}, usa_presentaciones = {i['b']}, usa_venta_presentaciones = {i['c']} WHERE empresa_id = 2")
sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id = {CTX['caja']}")
check(True, 'datos de la copia como estaban')
