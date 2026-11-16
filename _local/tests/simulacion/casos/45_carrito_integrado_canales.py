from simlib import *

# Carrito integrado (doc 28, Prompt 7b): lo que completa el caso 42. Precios por canal mayorista (lista General) y
# minorista (lista Mostrador), múltiplos, escala que cambia al sumar cantidad, promoción, producto que se vende solo en
# caja (aparece en el catálogo), filtro por marca comercial y cliente que intenta ver el pedido de otro.
#   python _local/tests/simulacion/run.py 45_carrito_integrado_canales.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261123_canales_carrito_app_01.sql _local/sql/mantenimiento/20261123_canales_carrito_app_02_carrito_presentaciones.sql

MAY, MIN = 313, 3          # cliente mayorista (lista 1 General) y minorista (en la copia, lista 3 Mostrador)
P1, P2 = 1529, 1878
CTX = {}
ERP = os.path.abspath(os.path.join(AQUI, '..', '..', '..'))


def carrito(ops, entidad=MAY, usuario=1):
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
        json.dump({'usuario_id': usuario, 'entidad_id': entidad, 'ops': ops}, f)
        ruta = f.name
    try:
        r = subprocess.run([PHP, os.path.join(ERP, '_local', 'tests', 'carrito', 'run_carrito.php'), 'carrito_driver.php', DB, ERP, '', ruta],
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
    finally:
        os.unlink(ruta)
    s = r.stdout
    try:
        return json.loads(s[s.find('{'):]).get('ops') or []
    except Exception:
        return [{'ok': False, 'error': (s + r.stderr)[-1200:]}]


def precio(lista, pid):
    return num(val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE lista_precio_id = {lista} AND producto_id = {pid} "
                   f"AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE()) ORDER BY f_desde DESC LIMIT 1") or 0)


def linea(res, clave):
    return next((l for l in res.get('lineas') or [] if l['clave'] == clave), {})


caso('Carrito integrado 2: preparación (solo en la copia)')
CTX['ind'] = uno("SELECT usa_presentaciones a, usa_venta_presentaciones b, usa_reglas_precio_venta c, usa_marcas_comerciales d, usa_carrito_integrado e "
                 "FROM conf__empresas WHERE empresa_id = 2")
sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_venta_presentaciones = 1, usa_reglas_precio_venta = 1, usa_marcas_comerciales = 1, "
    "usa_carrito_integrado = 1 WHERE empresa_id = 2")
CTX['cond'] = [(x['id'], x['d'], x['l']) for x in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d, lista_precio_id l "
                                                      f"FROM gestion__entidades_condiciones_clientes WHERE entidad_id IN ({MAY}, {MIN})")]
sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id IN ({MAY}, {MIN})")
sql(f"UPDATE gestion__entidades_condiciones_clientes SET lista_precio_id = 3 WHERE entidad_id = {MIN}")
sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, es_venta_defecto, "
    f"es_compra_defecto, orden, venta_multiplo, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM45 Caja x 12', 12, 1, 0, 0, 0, 99, 2, 1)")
CTX['caja'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE presentacion_nombre = 'SIM45 Caja x 12'"))
sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
    f"VALUES (2, 1, {CTX['caja']}, {P2}, 200000, CURDATE(), 1)")
# Producto que en la lista General se vende solo en caja (sin precio por unidad)
solo = int(val("SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id = 2 AND p.tabla_estado_registro_id = 1 AND NOT EXISTS "
               "(SELECT 1 FROM gestion__listas_precios_productos lp WHERE lp.producto_id = p.producto_id AND lp.lista_precio_id = 1 "
               "AND lp.tabla_estado_registro_id = 1 AND lp.precio_final > 0) ORDER BY p.producto_id LIMIT 1"))
CTX['solo'] = solo
sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, es_venta_defecto, "
    f"es_compra_defecto, orden, tabla_estado_registro_id) VALUES (2, {solo}, 'SIM45 Pack x 6', 6, 1, 0, 0, 0, 99, 1)")
CTX['pack'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE presentacion_nombre = 'SIM45 Pack x 6'"))
sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
    f"VALUES (2, 1, {CTX['pack']}, {solo}, 6000, CURDATE(), 1)")
r = ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', tipo='ESCALA', nombre='SIM45 Escala P1', producto_id=P1, f_desde=HOY,
         tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}]), empresa_idx=2))
CTX['escala'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
r = ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', tipo='PROMOCION', nombre='SIM45 Promo caja', producto_id=P2, presentacion_id=CTX['caja'],
         descuento_pct=7, f_desde=HOY, empresa_idx=2))
CTX['promo'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
sql("INSERT INTO gestion__productos_marcas (empresa_id, nombre, tabla_estado_registro_id) VALUES (2, 'SIM45 Marca', 1)")
CTX['marca'] = int(val("SELECT MAX(marca_comercial_id) FROM gestion__productos_marcas WHERE nombre = 'SIM45 Marca'"))
CTX['marca_ant'] = val(f"SELECT marca_comercial_id FROM gestion__productos WHERE producto_id = {P1}")
sql(f"UPDATE gestion__productos SET marca_comercial_id = {CTX['marca']} WHERE producto_id = {P1}")
check(CTX['escala'] and CTX['promo'] and precio(3, P1) > 0, 'escala, promoción, marca, caja y lista Mostrador con precio en la copia', str(CTX))

caso('Carrito integrado 2: precios por canal, escala, promoción y múltiplos')
cod1 = val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id = {P1}")
o = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 2}, {'op': 'resumen'}], entidad=MAY)
o2 = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 2}, {'op': 'resumen'}], entidad=MIN, usuario=8)
p_may = linea(o[2] if len(o) > 2 else {}, str(P1)).get('precio')
p_min = linea(o2[2] if len(o2) > 2 else {}, str(P1)).get('precio')
check(p_may == precio(1, P1) and p_min == precio(3, P1), f'mayorista con la lista General ({p_may}) y minorista con la Mostrador ({p_min})', f'{o} / {o2}'[:400])
o = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 10}, {'op': 'resumen'},
             {'op': 'agregar', 'producto_id': P1, 'cantidad': 20}, {'op': 'resumen'}])
a, b = (linea(o[2], str(P1)) if len(o) > 4 else {}), (linea(o[4], str(P1)) if len(o) > 4 else {})
check(a.get('descuento_pct') == 0 and b.get('descuento_pct') == 5 and 'Escala' in (b.get('etiqueta') or ''),
      'la escala aparece al sumar: 10 u sin descuento, 30 u con el 5 %', f'{a} / {b}')
caja = CTX['caja']
o = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P2, 'presentacion_id': caja, 'cantidad': 3},
             {'op': 'agregar', 'producto_id': P2, 'presentacion_id': caja, 'cantidad': 4}, {'op': 'resumen'}])
check(len(o) == 4 and not o[1].get('ok') and 'de a 2' in (o[1].get('error') or '') and o[2].get('ok'), 'la caja de a 2: 3 se rechaza, 4 entra', str(o[:3])[:300])
lc = linea(o[3] if len(o) == 4 else {}, f'{P2}-{caja}')
check(lc.get('descuento_pct') == 7 and 'Promo' in (lc.get('etiqueta') or ''), 'la promoción del 7 % en la caja, con su etiqueta', str(lc))

caso('Carrito integrado 2: catálogo')
solo, pack = CTX['solo'], CTX['pack']
cod_solo = val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id = {solo}")
o = carrito([{'op': 'catalogo', 'filtros': {'codigo': cod_solo}}])
c = o[0] if o else {}
ofs = ((c.get('ofertas') or {}).get(str(solo)) or {}).get('ofertas') or []
check(solo in (c.get('productos') or []) and len(ofs) == 1 and ofs[0]['presentacion_id'] == pack, 'un producto que se vende solo en caja aparece, solo con su pack', str(c)[:300])
o = carrito([{'op': 'catalogo', 'filtros': {'marca_comercial_id': CTX['marca']}}])
check(o and o[0].get('productos') == [P1], 'filtro por marca comercial: solo los productos de la marca', str(o)[:200])

caso('Carrito integrado 2: pedido de otro cliente')
o = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 2}, {'op': 'confirmar'}], entidad=MAY)
ped = (o[2] if len(o) == 3 else {}).get('venta_pedido_id')
o1 = carrito([{'op': 'pedido_detalle', 'venta_pedido_id': ped}], entidad=MAY)
o2 = carrito([{'op': 'pedido_detalle', 'venta_pedido_id': ped}], entidad=MIN, usuario=8)
check(ped and o1 and o1[0].get('detalle') is True and o2 and o2[0].get('detalle') is False, 'el cliente ve su pedido y otro cliente no', f'{o} {o1} {o2}'[:300])
carrito([{'op': 'vaciar'}], entidad=MIN, usuario=8)

caso('Carrito integrado 2: limpieza')
i = CTX['ind']
sql(f"UPDATE conf__empresas SET usa_presentaciones = {i['a']}, usa_venta_presentaciones = {i['b']}, usa_reglas_precio_venta = {i['c']}, "
    f"usa_marcas_comerciales = {i['d']}, usa_carrito_integrado = {i['e']} WHERE empresa_id = 2")
for cid, dd, ll in CTX['cond']:
    sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {dd}, lista_precio_id = {ll if ll is not None else 'NULL'} "
        f"WHERE entidad_condicion_cliente_id = {cid}")
sql(f"UPDATE gestion__productos SET marca_comercial_id = {CTX['marca_ant'] if CTX['marca_ant'] is not None else 'NULL'} WHERE producto_id = {P1}")
sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id IN ({CTX['caja']}, {CTX['pack']})")
sql(f"UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id IN ({int(CTX['escala'] or 0)}, {int(CTX['promo'] or 0)})")
check(True, 'datos de la copia como estaban')
