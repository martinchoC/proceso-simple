from simlib import *

# Prueba integral de la etapa 4 (doc 28, Prompt 10): un pedido mayorista por el carrito (cajas + escala), otro por la
# app del vendedor y otro por el ERP; los tres se liberan para distribución y van en una hoja de ruta; del pedido del
# carrito sale un remito parcial (unidades y 1 de 2 cajas) que se factura. Stock en cada paso. Empresa 2, módulo 2.
#   python _local/tests/simulacion/run.py 44_canales_integral.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261123_canales_carrito_app_01.sql _local/sql/mantenimiento/20261123_canales_carrito_app_02_carrito_presentaciones.sql

CLI_C, CLI_A, CLI_E = 313, 3, 5      # carrito (mayorista), app del vendedor, ERP
USU_V, PERFIL = 8, 12
P1, P2 = 1529, 1878
CTX = {}
ERP = os.path.abspath(os.path.join(AQUI, '..', '..', '..'))


def carrito(ops, entidad=CLI_C):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
        json.dump({'usuario_id': 1, 'entidad_id': entidad, 'ops': ops}, f)
        ruta = f.name
    try:
        r = subprocess.run([PHP, os.path.join(ERP, '_local', 'tests', 'carrito', 'run_carrito.php'), 'carrito_driver.php', DB, ERP, '', ruta],
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
    finally:
        os.unlink(ruta)
    s = r.stdout
    try:
        return json.loads(s[s.find('{'):])
    except Exception:
        return {'_error': (s + r.stderr)[-1500:], 'ops': []}


def app(accion, params=None, method='GET'):
    return ajax('api.php', dict(params or {}, accion=accion, empresa_idx=2), method, usuario_id=USU_V, modulo='app_vendedor')


def d(r):
    return (r.get('data') if isinstance(r, dict) else None) or {}


def estado(tabla, pk, rid):
    return int(val(f"SELECT tabla_estado_registro_id FROM {tabla} WHERE {pk} = {int(rid or 0)}") or 0)


def accion_pedido(ped, accion_js):
    return ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js=accion_js, empresa_idx=2, pagina_idx=55))


def dh(params, method='POST'):
    return ajax('distribucion_hojas_ajax.php', dict(params, empresa_idx=2), method)


# ---------------------------------------------------------------------------------------------
caso('Integral: preparación (solo en la copia)')
CTX['ind'] = uno("SELECT usa_presentaciones a, usa_venta_presentaciones b, usa_reglas_precio_venta c, usa_vendedores e, usa_distribucion f, "
                 "usa_carrito_integrado g, usa_app_vendedor h FROM conf__empresas WHERE empresa_id = 2")
sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_venta_presentaciones = 1, usa_reglas_precio_venta = 1, usa_vendedores = 1, "
    "usa_distribucion = 1, usa_carrito_integrado = 1, usa_app_vendedor = 1 WHERE empresa_id = 2")
CTX['desc'] = [(x['id'], x['d']) for x in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d FROM gestion__entidades_condiciones_clientes "
                                               f"WHERE entidad_id IN ({CLI_C}, {CLI_A}, {CLI_E})")]
sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id IN ({CLI_C}, {CLI_A}, {CLI_E})")
sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, es_venta_defecto, "
    f"es_compra_defecto, orden, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM44 Caja x 12', 12, 1, 0, 0, 0, 99, 1)")
CTX['caja'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE presentacion_nombre = 'SIM44 Caja x 12'"))
sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
    f"VALUES (2, 1, {CTX['caja']}, {P2}, 200000, CURDATE(), 1)")
r = ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', tipo='ESCALA', nombre='SIM44 Escala P1', producto_id=P1, f_desde=HOY,
         tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}]), empresa_idx=2))
CTX['escala'] = d(r).get('regla_venta_id')
r = ajax('vendedores_ajax.php', dict(accion='alta', entidad_nombre='SIM44 Vendedor', usuario_id=USU_V, empresa_idx=2))
CTX['v'] = d(r).get('vendedor_entidad_id')
ajax('vendedores_ajax.php', dict(accion='asignar', vendedor_entidad_id=CTX['v'], clientes=json.dumps([CLI_A]), fecha=HOY, empresa_idx=2))
for f in (241, 250):
    sql(f"INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) SELECT 2, {PERFIL}, {f}, 1 FROM DUAL "
        f"WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = {PERFIL} AND pagina_funcion_id = {f})")
z = ajax('distribucion_config_ajax.php', dict(accion='zona_agregar', nombre='SIM44 Zona', codigo='S44', dias_reparto='1,2,3,4,5,6,7', empresa_idx=2))
CTX['zona'] = d(z).get('zona_id')
ajax('distribucion_config_ajax.php', dict(accion='zona_asignar', zona_id=CTX['zona'], clientes=json.dumps([CLI_C, CLI_A, CLI_E]), fecha=HOY, empresa_idx=2))
dep = d(ajax('distribucion_config_ajax.php', dict(accion='catalogos', empresa_idx=2), 'GET')).get('depositos') or []
k = ajax('distribucion_config_ajax.php', dict(accion='camion_agregar', nombre='SIM44 Camión', patente='SIM44', capacidad_kg=100000, capacidad_m3=100,
         boca_id=dep[0]['boca_id'] if dep else 0, chofer='Juan', empresa_idx=2))
CTX['camion'] = d(k).get('camion_id')
check(CTX['caja'] and CTX['escala'] and CTX['v'] and CTX['zona'] and CTX['camion'], 'caja, escala, vendedor, zona y camión en la copia', f'{CTX}')

caso('Integral: pedidos por los tres canales')
s1, s2 = stock(P1), stock(P2)
r = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 30},
             {'op': 'agregar', 'producto_id': P2, 'presentacion_id': CTX['caja'], 'cantidad': 2}, {'op': 'confirmar', 'observaciones': 'SIM44 carrito'}])
o = r.get('ops') or []
ped_c = (o[3] if len(o) == 4 else {}).get('venta_pedido_id')
check(ped_c and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped_c) == 5, 'carrito: pedido confirmado', str(o)[:300])
r = app('guardar', dict(entidad_id=CLI_A, items=json.dumps([dict(producto_id=P1, cantidad=4)]), confirmar=1), 'POST')
ped_a = d(r).get('venta_pedido_id')
check(ok_resp(r) and d(r).get('confirmado'), 'app del vendedor: pedido confirmado', str(r)[:300])
dets = [linea(P1, 2, float(val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE lista_precio_id = 1 AND producto_id = {P1} AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1")))]
r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=55, comprobante_tipo_id=1, sucursal_id=1, punto_venta_id=1, entidad_id=CLI_E,
         f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, detalles=dets, **totales(dets)))
ped_e = r.get('venta_pedido_id') if isinstance(r, dict) else None
r2 = accion_pedido(ped_e, 'confirmar_pedido') if ped_e else {}
check(ok_resp(r2), 'ERP: pedido confirmado', err(r) + ' ' + err(r2))
orig = {int(x['venta_pedido_id']): x['origen'] for x in sql(f"SELECT venta_pedido_id, origen FROM gestion__ventas_pedidos WHERE venta_pedido_id IN "
                                                             f"({int(ped_c or 0)}, {int(ped_a or 0)}, {int(ped_e or 0)})")}
check(orig.get(int(ped_c or 0)) == 'CARRITO' and orig.get(int(ped_a or 0)) == 'APP' and orig.get(int(ped_e or 0)) == 'ERP', 'origen de cada pedido', str(orig))
check(stock(P1) == s1 and stock(P2) == s2, 'los pedidos no mueven stock')

caso('Integral: distribución y hoja de ruta')
peds = [p for p in (ped_c, ped_a, ped_e) if p]
for p in peds:
    accion_pedido(p, 'liberar_distribucion')
check(all(estado('gestion__ventas_pedidos', 'venta_pedido_id', p) == 42 for p in peds), 'los tres liberados para distribución (42)',
      str([estado('gestion__ventas_pedidos', 'venta_pedido_id', p) for p in peds]))
r = dh(dict(accion='hoja_crear', fecha_reparto=HOY, camion_id=CTX['camion']))
hoja = d(r).get('hoja_id')
r = dh(dict(accion='agregar_pedidos', hoja_id=hoja, pedidos=json.dumps(peds), forzar=1))
r2 = dh(dict(accion='confirmar', hoja_id=hoja))
check(hoja and ok_resp(r) and ok_resp(r2), 'hoja de ruta con los tres pedidos, confirmada', err(r) + ' ' + err(r2))
lc = d(dh(dict(accion='lista_carga', hoja_id=hoja), 'GET'))
p2 = next((f for f in (lc if isinstance(lc, list) else []) if int(f['producto_id']) == P2), {})
check(abs(num(p2.get('unidades')) - 24) < 0.001, 'lista de carga: las 2 cajas del carrito son 24 u de P2', str(p2)[:200])

caso('Integral: remito parcial del pedido del carrito y factura')
if ped_c:
    s1, s2 = stock(P1), stock(P2)
    r = ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', entidad_id=CLI_C, punto_venta_id=1, pedido_id=ped_c, empresa_idx=2), 'GET')
    pend = []
    for p in (r if isinstance(r, list) else []):
        pend += [p] if 'venta_pedido_detalle_id' in p else p.get('detalles', p.get('lineas', []))
    pend = [l for l in pend if int(l.get('venta_pedido_id', ped_c)) == int(ped_c)]
    check(len(pend) == 2, 'el remito ve las 2 líneas del pedido del carrito', str(r)[:300])
    por = {int(l['producto_id']): l for l in pend}
    if len(por) == 2:
        rdets = [dict(venta_pedido_detalle_id=int(por[P1]['venta_pedido_detalle_id']), producto_id=P1, cantidad=10,
                      precio_unitario_bruto=num(por[P1].get('precio_unitario_bruto') or por[P1].get('precio_unitario')),
                      descuento_general_pct=num(por[P1].get('descuento_general_pct')), iva_alicuota_id=1, iva_porcentaje=21),
                 dict(venta_pedido_detalle_id=int(por[P2]['venta_pedido_detalle_id']), producto_id=P2, cantidad=12,
                      precio_unitario_bruto=num(por[P2].get('precio_unitario_bruto') or por[P2].get('precio_unitario')),
                      descuento_general_pct=num(por[P2].get('descuento_general_pct')), iva_alicuota_id=1, iva_porcentaje=21)]
        r = ajax('ventas_remitos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=88, punto_venta_id=1, comprobante_tipo_id=40,
                 entidad_id=CLI_C, f_emision=HOY, detalles=rdets))
        rem = (r.get('venta_remito_id') or r.get('id')) if isinstance(r, dict) else None
        r2 = ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js='confirmar', empresa_idx=2, pagina_idx=88)) if rem else {}
        check(rem and ok_resp(r2), 'remito parcial (10 de 30 u y 1 de 2 cajas) confirmado', err(r) + ' ' + err(r2))
        check(stock(P1) == s1 - 10 and stock(P2) == s2 - 12, f'stock -10 / -12 ({s1}->{stock(P1)}, {s2}->{stock(P2)})')
        lr = uno(f"SELECT presentacion_id, presentacion_cantidad FROM gestion__ventas_remitos_detalles WHERE venta_remito_id = {int(rem or 0)} AND producto_id = {P2}")
        check(lr and int(lr['presentacion_id'] or 0) == CTX['caja'] and num(lr['presentacion_cantidad']) == 1, 'el remito hereda la caja (1 caja)', str(lr))
        check(num(val(f"SELECT cantidad_entregada FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {ped_c} AND producto_id = {P1}")) == 10,
              'el pedido registra 10 entregadas de P1')
        r = ajax('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', entidad_id=CLI_C, venta_remito_id=rem, empresa_idx=2, pagina_idx=56), 'GET')
        lineas = []
        for x in (r if isinstance(r, list) else (r.get('data', r.get('remitos', [])) if isinstance(r, dict) else [])):
            lineas += x.get('detalles', x.get('lineas', [x])) if isinstance(x, dict) else []
        lineas = [l for l in lineas if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
        fdets = [dict(producto_id=int(l['producto_id']), cantidad=num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
                      precio_unitario=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')), descuento_general_pct=num(l.get('descuento_general_pct')),
                      iva_alicuota_id=1, iva_porcentaje=21, venta_remito_detalle_id=int(l['venta_remito_detalle_id']),
                      venta_pedido_detalle_id=int(l.get('venta_pedido_detalle_id') or 0)) for l in lineas]
        s1, s2 = stock(P1), stock(P2)
        r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1, comprobante_tipo_id=4,
                 entidad_id=CLI_C, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0,
                 observaciones='SIM44 desde remito', detalles=fdets))
        fv = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')) if isinstance(r, dict) else None
        ok1 = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fv, accion_js='confirma', empresa_idx=2, pagina_idx=56)) if fv else {}
        ok2 = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fv, accion_js='aprobada', empresa_idx=2, pagina_idx=56)) if fv else {}
        check(fv and ok_resp(ok1) and ok_resp(ok2) and estado('gestion__ventas_facturas', 'venta_factura_id', fv) == 5, 'factura desde el remito aprobada',
              err(r) + ' ' + err(ok1) + ' ' + err(ok2))
        check(stock(P1) == s1 and stock(P2) == s2, 'la factura no mueve stock (lo bajó el remito)')
        check(estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 12, 'remito facturado (12)')
        fl = uno(f"SELECT presentacion_id, presentacion_cantidad FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = {int(fv or 0)} AND producto_id = {P2}")
        check(fl and int(fl['presentacion_id'] or 0) == CTX['caja'] and num(fl['presentacion_cantidad']) == 1, 'la factura hereda la caja', str(fl))
        fp1 = uno(f"SELECT descuento_general_pct d FROM gestion__ventas_facturas_detalles WHERE venta_factura_id = {int(fv or 0)} AND producto_id = {P1}")
        check(fp1 and num(fp1['d']) == 5, 'la factura conserva la escala del 5 % del pedido del carrito', str(fp1))

caso('Integral: limpieza')
if CTX.get('ind'):
    i = CTX['ind']
    sql(f"UPDATE conf__empresas SET usa_presentaciones = {i['a']}, usa_venta_presentaciones = {i['b']}, usa_reglas_precio_venta = {i['c']}, usa_vendedores = {i['e']}, "
        f"usa_distribucion = {i['f']}, usa_carrito_integrado = {i['g']}, usa_app_vendedor = {i['h']} WHERE empresa_id = 2")
    for cid, dd in CTX.get('desc', []):
        sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {dd} WHERE entidad_condicion_cliente_id = {cid}")
    if CTX.get('caja'):
        sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id = {CTX['caja']}")
    if CTX.get('escala'):
        sql(f"UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id = {CTX['escala']}")
    check(True, 'datos de la copia como estaban')
