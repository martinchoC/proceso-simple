"""Carga de datos de rubro alimentos en la empresa 1000 (GINESI MAURO NICOLAS), módulo Gestión Normal, base LOCAL.

Todo por los _ajax.php reales (run_ajax.php del simulador con permitir_base_real), salvo usa_presentaciones (SQL).
Idempotente: lo que ya existe (por código / nombre / observación) no se vuelve a crear.
Ids creados en ginesi_ctx.json (al lado de este script).
"""
import json
import os
import random
import sys

ENSAYO = '--ensayo' in sys.argv          # contra una copia nueva (gestion_multipyme_sim), no la base local
if not ENSAYO:
    os.environ['SIM_BASE_LOCAL'] = '1'   # simlib: base origen (gestion_multipyme), run_ajax con permitir_base_real
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'tests', 'simulacion'))
from simlib import *  # noqa: E402,F401
if ENSAYO:
    clonar_base()

random.seed(20261112)
E = 1000
MOD = 'gestion_normal'
SUC, BOCA, PV = 305, 305, 306
MONEDA = 6
IVA = {21: 25, 10.5: 24}
TIPO_PROD = 8
LOC = 16353
OBS = 'Datos de prueba rubro alimentos'
CTX_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'ginesi_ctx_ensayo.json' if ENSAYO else f'ginesi_ctx_{DB_ORIGEN}.json')
if ENSAYO and os.path.exists(CTX_PATH):
    os.remove(CTX_PATH)
ctx = json.load(open(CTX_PATH, encoding='utf-8')) if os.path.exists(CTX_PATH) else {}
print('Base:', DB)


def guardar():
    json.dump(ctx, open(CTX_PATH, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)


def aj(archivo, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', E)
    return ajax(archivo, p, method, usuario_id=1, modulo=MOD, empresa_id=E)


def esc(s):
    return s.replace("'", "''")


def lit(s):
    """Literal SQL en hexadecimal: el cliente mysql de Windows rompe los acentos que llegan por la línea de comandos."""
    return f"CONVERT(X'{s.encode('utf-8').hex()}' USING utf8mb4) COLLATE utf8mb4_unicode_ci"


def cuit(base10):
    s = str(base10)
    r = 11 - sum(int(a) * b for a, b in zip(s, [5, 4, 3, 2, 7, 6, 5, 4, 3, 2])) % 11
    return s + str(0 if r == 11 else (9 if r == 10 else r))


def gtin_dv(cuerpo):
    """Dígito verificador GS1 (EAN-13 / GTIN-14) para el cuerpo sin el dígito."""
    t = sum(int(d) * (3 if i % 2 == 0 else 1) for i, d in enumerate(reversed(cuerpo)))
    return str((10 - t % 10) % 10)


def ean13(n):
    c = f'200{n:09d}'          # prefijo 200: uso interno (no colisiona con códigos reales)
    return c + gtin_dv(c)


def dun14(ean):
    c = '1' + ean[:12]
    return c + gtin_dv(c)


def tipo(cod):
    return int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {E} AND codigo = '{cod}'"))


# =====================================================================================================================
# Catálogo
# =====================================================================================================================
PROVEEDORES = {   # clave: (nombre, cuit base, categorías)
    'MOL': ('Molinos del Valle Fértil SA', 3071998501, ['HAR', 'ARR']),
    'INF': ('Distribuidora Andina de Infusiones SRL', 3071998502, ['INF', 'GAL']),
    'LIT': ('Alimentos del Litoral SA', 3071998503, ['ACE', 'CON']),
    'BEB': ('Bebidas y Golosinas Patagónicas SA', 3071998504, ['BEB', 'GOL']),
    'FRI': ('Lácteos y Frío del Sur SA', 3071998505, ['LAC', 'CNG']),
}
CATEGORIAS = {    # código: (nombre, margen % de la lista Mayorista)
    'INF': ('Infusiones', 30), 'HAR': ('Harinas y pastas', 25), 'ARR': ('Arroz, legumbres y granos', 25),
    'ACE': ('Aceites, aderezos y condimentos', 28), 'CON': ('Conservas', 30), 'GAL': ('Galletitas y panificados', 35),
    'BEB': ('Bebidas sin alcohol', 30), 'GOL': ('Golosinas y snacks', 45), 'LAC': ('Lácteos y refrigerados', 28),
    'CNG': ('Congelados', 32),
}
# (nombre, costo unitario, unidades por caja, cajas por pallet o None, iva, unidad de medida)
P = {
    'INF': [('Yerba mate con palo 1 kg', 3200, 10, 50), ('Yerba mate con palo 500 g', 1700, 20, 50), ('Yerba mate sin palo 1 kg', 3600, 10, 50),
            ('Yerba mate compuesta 500 g', 1900, 20, None), ('Té negro en saquitos x 25', 650, 48, None), ('Té verde en saquitos x 20', 900, 48, None),
            ('Mate cocido en saquitos x 25', 700, 48, None), ('Café molido 250 g', 3800, 24, None), ('Café instantáneo frasco 170 g', 6200, 12, None),
            ('Cacao en polvo 360 g', 2600, 24, None)],
    'HAR': [('Harina de trigo 000 1 kg', 750, 10, 100, 10.5), ('Harina de trigo 0000 1 kg', 820, 10, 100, 10.5), ('Harina leudante 1 kg', 950, 10, None, 10.5),
            ('Fideos spaghetti 500 g', 900, 20, 60), ('Fideos tirabuzón 500 g', 900, 20, 60), ('Fideos mostachol 500 g', 900, 20, 60),
            ('Fideos al huevo cinta 500 g', 1300, 20, None), ('Polenta instantánea 500 g', 700, 20, None), ('Sémola de trigo 500 g', 800, 20, None),
            ('Premezcla para pizza 500 g', 1100, 12, None)],
    'ARR': [('Arroz largo fino 1 kg', 1300, 10, 80), ('Arroz doble carolina 1 kg', 1500, 10, 80), ('Arroz parboil 1 kg', 1600, 10, 80),
            ('Arroz integral 1 kg', 1700, 10, None), ('Lentejas secas 400 g', 1200, 20, None), ('Porotos alubia 400 g', 1300, 20, None),
            ('Garbanzos 400 g', 1400, 20, None), ('Arvejas secas partidas 400 g', 900, 20, None), ('Avena arrollada 400 g', 1000, 20, None),
            ('Maíz pisingallo 400 g', 800, 20, None)],
    'ACE': [('Aceite de girasol 900 ml', 2100, 12, 60), ('Aceite de girasol 1,5 l', 3300, 6, 100), ('Aceite de oliva extra virgen 500 ml', 7800, 12, None),
            ('Vinagre de alcohol 1 l', 800, 12, None), ('Mayonesa doypack 500 g', 1900, 12, None), ('Ketchup doypack 250 g', 1100, 24, None),
            ('Mostaza doypack 250 g', 1000, 24, None), ('Sal fina 500 g', 500, 20, 80), ('Pimienta negra molida 25 g', 700, 40, None),
            ('Orégano 25 g', 600, 40, None)],
    'CON': [('Tomate triturado 520 g', 750, 24, 60), ('Puré de tomate 520 g', 700, 24, 60), ('Arvejas en lata 300 g', 650, 24, None),
            ('Choclo cremoso en lata 300 g', 900, 24, None), ('Atún al natural 170 g', 2400, 48, None), ('Caballa en aceite 380 g', 2200, 24, None),
            ('Duraznos en almíbar 820 g', 2300, 12, None), ('Jardinera en lata 300 g', 750, 24, None), ('Lentejas en lata 300 g', 800, 24, None),
            ('Mermelada de durazno 454 g', 1600, 12, None)],
    'GAL': [('Galletitas crackers 300 g', 1000, 24, None), ('Galletitas dulces de vainilla 300 g', 1100, 24, None), ('Galletitas surtidas 400 g', 1500, 18, None),
            ('Galletitas de arroz 150 g', 900, 20, None), ('Bizcochos de grasa 200 g', 800, 24, None), ('Pan lactal blanco 550 g', 2000, 12, None),
            ('Pan rallado 1 kg', 1200, 10, None), ('Tostadas clásicas 200 g', 1100, 20, None), ('Budín de vainilla 200 g', 1300, 24, None),
            ('Vainillas 160 g', 900, 24, None)],
    'BEB': [('Agua mineral sin gas 2 l', 600, 6, 120), ('Agua mineral con gas 2 l', 650, 6, 120), ('Gaseosa cola 2,25 l', 1900, 6, 100),
            ('Gaseosa lima limón 2,25 l', 1800, 6, 100), ('Gaseosa naranja 2,25 l', 1800, 6, 100), ('Agua saborizada pomelo 1,5 l', 1100, 6, 120),
            ('Jugo en polvo naranja 18 g', 250, 20, None), ('Jugo de naranja 1 l', 1600, 12, None), ('Bebida isotónica 500 ml', 1000, 6, None),
            ('Soda en sifón 2 l', 700, 6, None)],
    'GOL': [('Alfajor triple de chocolate 70 g', 700, 24, None), ('Chocolate con leche 100 g', 2000, 20, None), ('Caramelos masticables bolsa 800 g', 3500, 12, None),
            ('Chicles de menta x 10', 300, 24, None), ('Papas fritas clásicas 145 g', 1800, 20, None), ('Palitos salados 120 g', 900, 24, None),
            ('Maní salado 120 g', 800, 24, None), ('Turrón de maní 25 g', 200, 50, None), ('Obleas rellenas 100 g', 600, 30, None),
            ('Chupetín frutal', 120, 48, None)],
    'LAC': [('Leche entera sachet 1 l', 1000, 12, None), ('Leche descremada sachet 1 l', 1000, 12, None), ('Leche larga vida entera 1 l', 1300, 12, 80),
            ('Yogur bebible frutilla 1 l', 1800, 12, None), ('Queso cremoso', 7500, 4, None, 21, 2), ('Queso rallado 150 g', 2200, 20, None),
            ('Manteca 200 g', 2100, 20, None), ('Crema de leche 200 ml', 1200, 20, None), ('Dulce de leche clásico 400 g', 1900, 12, None),
            ('Queso untable 290 g', 2000, 12, None)],
    'CNG': [('Hamburguesas de carne x 4', 1900, 24, None), ('Medallones de pollo x 4', 2100, 24, None), ('Papas prefritas congeladas 1 kg', 2600, 10, None),
            ('Espinaca congelada 500 g', 1300, 12, None), ('Helado de crema 1 kg', 4500, 6, None), ('Pizza congelada muzzarella', 2500, 12, None),
            ('Empanadas de carne congeladas x 12', 6000, 6, None), ('Tapas para empanadas x 12', 900, 24, None), ('Tapas para tarta x 2', 1000, 24, None),
            ('Nuggets de pollo 400 g', 2500, 12, None)],
}
CLIENTES = [  # clave, nombre, condición IVA, régimen IIBB, cuit base, tipo de factura
    ('ESQ', 'Supermercado La Esquina SRL', 1, 1, 3071998601, 'V_FA'),
    ('FAR', 'Autoservicio El Faro SA', 1, 1, 3071998602, 'V_FA'),
    ('SMA', 'Minimercado San Martín SRL', 1, 1, 3071998603, 'V_FA'),
    ('DJO', 'Almacén Don José', 3, 3, 2071998604, 'V_FA'),
    ('KIO', 'Kiosco y Polirrubro Central', 3, 3, 2071998605, 'V_FA'),
]

# =====================================================================================================================
caso('1. Configuración de la empresa 1000')
r = ajax('empresa_inicio_ajax.php', dict(action='guardar_tipo_operacion', empresa_id=E, tipo_operacion='MIXTA'), modulo=MOD, empresa_id=E)
check(ok_resp(r), 'tipo de operación MIXTA (bienes y servicios)', err(r))
r = ajax('empresas_modulos_ajax.php', dict(accion='copiar_perfiles', empresa_id=E, modulo_id=5), 'GET', modulo='conf', empresa_id=1)
check(ok_resp(r) or isinstance(r, dict) and not r.get('error'), 'perfiles: funciones de stock, pedidos y remitos', json.dumps(r)[:200])
sql(f"UPDATE conf__empresas SET usa_presentaciones = 1 WHERE empresa_id = {E}")
check(val(f"SELECT usa_presentaciones FROM conf__empresas WHERE empresa_id = {E}") == '1', 'usa presentaciones')

if val(f"SELECT COUNT(*) FROM gestion__puntos_venta_comprobantes WHERE punto_venta_id = {PV}") == '0':
    codigos = [x['codigo'] for x in sql("SELECT DISTINCT ct.codigo FROM gestion__puntos_venta_comprobantes p JOIN gestion__comprobantes_tipos ct "
                                       "USING (comprobante_tipo_id) WHERE p.empresa_id = 2")]
    tipos = [tipo(c) for c in codigos if val(f"SELECT COUNT(*) FROM gestion__comprobantes_tipos WHERE empresa_id = {E} AND codigo = '{c}'") != '0']
    pvd = uno(f"SELECT nombre, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id = {PV}")
    r = aj('puntos_venta_ajax.php', dict(accion='editar', pagina_idx=570, punto_venta_id=PV, sucursal_id=SUC, boca_id=BOCA, nombre=pvd['nombre'],
                                        codigo_fiscal=1, sistema_emision=pvd['sistema_emision'],
                                        comprobantes=json.dumps([{'comprobante_tipo_id': t, 'requiere_afip': 0, 'ultimo_numero': 0} for t in tipos])))
    check(ok_resp(r), f'PV 0001 con {len(tipos)} tipos habilitados', err(r))

ctx.setdefault('categorias', {})
for cod, (nombre, _) in CATEGORIAS.items():
    cid = val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {E} AND producto_categoria_nombre = {lit(nombre)}")
    if not cid:
        r = aj('productos_categorias_ajax.php', dict(accion='agregar', pagina_idx=539, producto_categoria_nombre=nombre))
        cid = val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {E} AND producto_categoria_nombre = {lit(nombre)}")
        check(cid, f'categoría {nombre}', err(r))
    ctx['categorias'][cod] = int(cid)
guardar()

# =====================================================================================================================
caso('2. Proveedores y clientes')
ctx.setdefault('proveedores', {})
for k, (nombre, base, _) in PROVEEDORES.items():
    ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND entidad_nombre = {lit(nombre)}")
    if not ent:
        r = aj('proveedores_ajax.php', dict(accion='agregar_entidad', pagina_idx=593, entidad_nombre=nombre, entidad_fantasia='', entidad_tipo_id='',
                                            cuit=cuit(base), sitio_web='', domicilio_legal='Parque industrial', localidad_id=LOC, es_proveedor=1,
                                            es_cliente=0, observaciones=OBS, condicion_fiscal_id=1, regimen_iibb_id=2, numero_inscripcion_iibb=cuit(base),
                                            jurisdiccion_sede_id=2, fiscal_f_desde='2020-01-01'))
        ent = (r.get('entidad_id') if isinstance(r, dict) else None) or val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND entidad_nombre = {lit(nombre)}")
        check(ent, f'proveedor {nombre}', err(r))
    ctx['proveedores'][k] = int(ent)
ctx.setdefault('clientes', {})
for k, nombre, cond, reg, base, _ in CLIENTES:
    ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND entidad_nombre = {lit(nombre)}")
    if not ent:
        r = aj('clientes_ajax.php', dict(accion='agregar_entidad', pagina_idx=594, entidad_nombre=nombre, entidad_fantasia='', entidad_tipo_id='',
                                         cuit=cuit(base), sitio_web='', domicilio_legal='Av. San Martín 100', localidad_id=LOC, es_proveedor=0, es_cliente=1,
                                         observaciones=OBS, condicion_fiscal_id=cond, regimen_iibb_id=reg, numero_inscripcion_iibb=cuit(base),
                                         jurisdiccion_sede_id=2, fiscal_f_desde='2020-01-01'))
        ent = (r.get('entidad_id') if isinstance(r, dict) else None) or val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND entidad_nombre = {lit(nombre)}")
        check(ent, f'cliente {nombre}', err(r))
    ctx['clientes'][k] = int(ent)
guardar()

# =====================================================================================================================
caso('3. Productos (100) con presentaciones, stock mínimo y proveedor')
ctx.setdefault('productos', {})
PROV_DE_CAT = {c: k for k, (_, _, cats) in PROVEEDORES.items() for c in cats}
INFO = {}
n = 0
for cat, items in P.items():
    for i, it in enumerate(items, 1):
        n += 1
        nombre, costo, caja, pallet = it[:4]
        iva = it[4] if len(it) > 4 else 21
        um = it[5] if len(it) > 5 else 1
        cod = f'{cat}-{i:03d}'
        ean = ean13(n)
        INFO[cod] = dict(costo=costo, caja=caja, iva=iva, cat=cat)
        pid = val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {E} AND producto_codigo = '{cod}'")
        if not pid:
            ab = 'kg' if um == 2 else 'u'
            pres = [{'presentacion_nombre': f'Caja x {caja}' if um == 1 else f'Horma x {caja} kg', 'factor': caja, 'codigo_barras': dun14(ean),
                     'peso_bruto_kg': round(caja * (1.05 if um == 2 else 0.6), 2), 'es_venta_defecto': 1, 'es_compra_defecto': 0 if pallet else 1}]
            if pallet:
                pres.append({'presentacion_nombre': f'Pallet x {pallet} cajas', 'factor': caja * pallet, 'largo_cm': 120, 'ancho_cm': 100,
                             'alto_cm': 150, 'aplica_venta': 0, 'es_compra_defecto': 1})
            r = aj('productos_ajax.php', dict(accion='agregar', pagina_idx=540, producto_codigo=cod, producto_nombre=nombre,
                                              producto_descripcion=f'{nombre}. Unidad de venta: {ab}.', codigo_barras=ean if um == 1 else '',
                                              producto_tipo_id=TIPO_PROD, producto_categoria_id=ctx['categorias'][cat], unidad_medida_id=um,
                                              iva_alicuota_id=IVA[iva], controla_stock=1, es_servicio=0, aplica_compra=1, aplica_venta=1,
                                              niveles_stock=json.dumps([{'boca_id': BOCA, 'stock_minimo': caja * 3, 'stock_maximo': caja * 40}]),
                                              presentaciones=json.dumps(pres)))
            pid = r.get('producto_id') if isinstance(r, dict) else None
            if not check(ok_resp(r) and pid and r.get('presentaciones_guardadas') and not r.get('aviso'), f'producto {cod} {nombre}', err(r) + ' ' + str(r.get('aviso'))):
                continue
            prov = ctx['proveedores'][PROV_DE_CAT[cat]]
            rv = aj('productos_proveedores_ajax.php', dict(accion='agregar', pagina_idx=595, producto_id=pid, entidad_id=prov,
                                                           codigo_proveedor=f'{PROV_DE_CAT[cat]}-{n:04d}'))
            check(ok_resp(rv), f'  vínculo con el proveedor {PROV_DE_CAT[cat]}', err(rv))
        ctx['productos'][cod] = int(pid)
guardar()
check(len(ctx['productos']) == 100, f"{len(ctx['productos'])} productos")

# =====================================================================================================================
caso('4. Compras: 10 órdenes de compra recibidas con su factura de proveedor')
ctx.setdefault('compras', [])
hechas = {c['cat'] for c in ctx['compras']}
TIPO_OC, TIPO_FA_PROV = tipo('C_PC'), tipo('C_FA')
orden = [c for k, (_, _, cats) in PROVEEDORES.items() for c in cats]
for j, cat in enumerate(orden):
    if cat in hechas:
        continue
    prov_k = PROV_DE_CAT[cat]
    prov = ctx['proveedores'][prov_k]
    f_oc = f'2026-10-{13 + j * 2:02d}'
    f_fp = f'2026-10-{16 + j * 2:02d}'
    lineas = []
    for cod, inf in INFO.items():
        if inf['cat'] == cat:
            cajas = random.randint(12, 30)
            lineas.append((ctx['productos'][cod], cajas * inf['caja'], inf['costo'], inf['iva']))
    dets = [linea(p, c, pr, iva=iv, iva_id=IVA[iv]) for p, c, pr, iv in lineas]
    r = aj('ordenes_compra_ajax.php', dict(accion='agregar', pagina_idx=551, comprobante_tipo_id=TIPO_OC, sucursal_id=SUC, punto_venta_id=PV,
                                           entidad_id=prov, f_emision=f_oc, moneda_id=MONEDA, tipo_cambio=1, observaciones=f'{OBS}: reposición {CATEGORIAS[cat][0]}',
                                           detalles=dets, **totales(dets)))
    oc = r.get('orden_compra_id') if isinstance(r, dict) else None
    if not check(ok_resp(r) and oc, f'OC {CATEGORIAS[cat][0]} ({len(dets)} líneas)', err(r)):
        continue
    for acc in ('confirmar', 'pendiente_recepcion'):
        r = aj('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc, accion_js=acc))
        check(ok_resp(r), f'  OC {acc}', err(r))
    r = aj('facturas_proveedores_ajax.php', dict(accion='obtener_oc_lineas', orden_compra_id=oc, entidad_id=prov, pagina_idx=557), 'GET')
    ocl = {int(l['producto_id']): l for l in (r.get('data') or {}).get('lineas', [])} if ok_resp(r) else {}
    fdets = [linea(p, c, pr, iva=iv, iva_id=IVA[iv], ordenes_compra_detalle_id=ocl[p]['ordenes_compra_detalle_id']) for p, c, pr, iv in lineas if p in ocl]
    check(len(fdets) == len(lineas), '  la factura ve las líneas de la OC', err(r))
    nro = int(val(f"SELECT IFNULL(MAX(comprobante_nro), 1000) + 1 FROM gestion__facturas_proveedores WHERE empresa_id = {E} AND entidad_id = {prov} AND comprobante_pv = 3"))
    r = aj('facturas_proveedores_ajax.php', dict(accion='agregar', pagina_idx=557, comprobante_tipo_id=TIPO_FA_PROV, comprobante_pv=3, comprobante_nro=nro,
                                                 sucursal_id=SUC, deposito_id=BOCA, entidad_id=prov, f_emision=f_fp, f_contabilidad=f_fp, f_vencimiento='2026-11-15',
                                                 moneda_id=MONEDA, tipo_cambio=1, condicion_pago_id='', orden_compra_id=oc, descuento_general_pct=0,
                                                 observaciones=OBS, detalles=fdets, impuestos_adicionales=[], **totales(fdets)))
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if not check(ok_resp(r) and fid, f'  factura A 0003-{nro:08d} del proveedor', err(r)):
        continue
    for acc in ('aprobar', 'confirmar'):
        r = aj('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fid, accion_js=acc))
        check(ok_resp(r), f'  factura {acc}', err(r))
    est_oc = val(f"SELECT tabla_estado_registro_id FROM gestion__ordenes_compra WHERE orden_compra_id = {oc}")
    check(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {fid}") == '5', f'  factura confirmada; OC en estado {est_oc}')
    ctx['compras'].append(dict(cat=cat, orden_compra_id=int(oc), factura_proveedor_id=int(fid), total=totales(fdets)['total']))
    guardar()

# =====================================================================================================================
caso('5. Lista de precios Mayorista (costo + margen por categoría)')
lista = val(f"SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = {E} AND lista_precio_codigo = 'MAY'")
if not lista:
    r = aj('listas_precios_ajax.php', dict(accion='agregar', pagina_id=553, lista_precio_codigo='MAY', lista_precio_nombre='Mayorista',
                                           descripcion='Venta por bulto a comercios: costo + margen por categoría', lista_precio_origen_id=1,
                                           moneda_id=MONEDA, requiere_recalculo=1, observaciones=OBS))
    lista = val(f"SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = {E} AND lista_precio_codigo = 'MAY'")
    check(lista, 'lista Mayorista', err(r))
ctx['lista_precio_id'] = int(lista)
for cat, (nombre, margen) in CATEGORIAS.items():
    if val(f"SELECT COUNT(*) FROM gestion__listas_precios_reglas WHERE lista_precio_id = {lista} AND producto_categoria_id = {ctx['categorias'][cat]}") == '0':
        r = aj('listas_precios_ajax.php', dict(accion='agregar_regla', pagina_id=553, lista_precio_id=lista, regla_nombre=f'{nombre} +{margen}%',
                                               lista_precio_regla_valor_tipo_id=1, valor_ajuste=margen, producto_categoria_id=ctx['categorias'][cat],
                                               prioridad=100, f_desde='2026-10-01'))
        check(ok_resp(r), f'regla {nombre} +{margen}%', err(r))
r = aj('listas_precios_ajax.php', dict(accion='recalcular_precios', pagina_id=553, lista_precio_id=lista))
check(ok_resp(r), 'recalcular precios', err(r))
n_precios = int(val(f"SELECT COUNT(*) FROM gestion__listas_precios_productos WHERE lista_precio_id = {lista} AND tabla_estado_registro_id = 1") or 0)
check(n_precios >= 100, f'{n_precios} precios en la lista Mayorista')
guardar()


def precio(pid):
    v = val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE lista_precio_id = {lista} AND producto_id = {pid} AND tabla_estado_registro_id = 1 AND f_hasta IS NULL "
            f"ORDER BY 1 DESC LIMIT 1")
    return round(num(v), 2)


# =====================================================================================================================
caso('6. Ventas: 5 pedidos de clientes -> remito -> factura')
ctx.setdefault('ventas', [])
hechas = {v['cliente'] for v in ctx['ventas']}
TIPO_PED, TIPO_REM = tipo('PC'), tipo('S_RSA')
cods = list(INFO)
for j, (ck, nombre, _, _, _, tfac) in enumerate(CLIENTES):
    if ck in hechas:
        continue
    cli = ctx['clientes'][ck]
    f_ped, f_rem = f'2026-11-{2 + j * 2:02d}', f'2026-11-{3 + j * 2:02d}'
    elegidos = random.sample(cods, 8)
    lineas = []
    for cod in elegidos:
        pid, inf = ctx['productos'][cod], INFO[cod]
        pr = precio(pid) or round(inf['costo'] * 1.3, 2)
        lineas.append((pid, random.randint(1, 4) * inf['caja'], pr, inf['iva']))
    dets = [linea(p, c, pr, iva=iv, iva_id=IVA[iv]) for p, c, pr, iv in lineas]
    r = aj('ventas_pedidos_ajax.php', dict(accion='agregar', pagina_idx=591, comprobante_tipo_id=TIPO_PED, sucursal_id=SUC, punto_venta_id=PV,
                                           entidad_id=cli, f_emision=f_ped, moneda_id=MONEDA, tipo_cambio=1, condicion_pago_id='',
                                           descuento_general_pct=0, observaciones=OBS, detalles=dets, **totales(dets)))
    ped = (r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None
    if not check(ok_resp(r) and ped, f'pedido de {nombre} ({len(dets)} líneas)', err(r)):
        continue
    r = aj('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido', pagina_idx=591))
    check(ok_resp(r), '  pedido confirmado', err(r))
    r = aj('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', entidad_id=cli, punto_venta_id=PV, pedido_id=ped, pagina_idx=588), 'GET')
    pend = []
    for p in (r if isinstance(r, list) else []):
        pend += [p] if 'venta_pedido_detalle_id' in p else p.get('detalles', p.get('lineas', []))
    pend = [l for l in pend if int(l.get('venta_pedido_id', ped)) == int(ped)]
    ivap = {p: iv for p, _, _, iv in lineas}
    rdets = [dict(venta_pedido_detalle_id=int(l['venta_pedido_detalle_id']), producto_id=int(l['producto_id']),
                  cantidad=num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
                  precio_unitario_bruto=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')), descuento_general_pct=0,
                  iva_alicuota_id=IVA[ivap[int(l['producto_id'])]], iva_porcentaje=ivap[int(l['producto_id'])]) for l in pend]
    check(len(rdets) == len(lineas), '  el remito ve todas las líneas del pedido', str(r)[:200])
    r = aj('ventas_remitos_ajax.php', dict(accion='agregar', pagina_idx=588, punto_venta_id=PV, comprobante_tipo_id=TIPO_REM, entidad_id=cli,
                                           f_emision=f_rem, observaciones=OBS, detalles=rdets))
    rem = (r.get('venta_remito_id') or r.get('id')) if isinstance(r, dict) else None
    if not check(ok_resp(r) and rem, '  remito', err(r)):
        continue
    r = aj('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js='confirmar', pagina_idx=588))
    check(ok_resp(r), '  remito confirmado (baja stock)', err(r))
    r = aj('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', entidad_id=cli, venta_remito_id=rem, pagina_idx=556), 'GET')
    rl = []
    for x in (r if isinstance(r, list) else (r.get('data') or [] if isinstance(r, dict) else [])):
        rl += x.get('detalles', x.get('lineas', [x]))
    rl = [l for l in rl if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
    fdets = [dict(producto_id=int(l['producto_id']), cantidad=num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
                  precio_unitario=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')), descuento_general_pct=0,
                  iva_alicuota_id=IVA[ivap[int(l['producto_id'])]], iva_porcentaje=ivap[int(l['producto_id'])],
                  venta_remito_detalle_id=int(l['venta_remito_detalle_id']), venta_pedido_detalle_id=int(l.get('venta_pedido_detalle_id') or 0)) for l in rl]
    check(len(fdets) == len(lineas), '  la factura ve las líneas del remito', str(r)[:200])
    r = aj('ventas_facturas_ajax.php', dict(accion='agregar', pagina_idx=556, punto_venta_id=PV, sucursal_id=SUC, comprobante_tipo_id=tipo(tfac),
                                            entidad_id=cli, f_emision=f_rem, f_vto='2026-11-30', moneda_id=MONEDA, tipo_cambio=1, condicion_pago_id='',
                                            descuento_general_pct=0, observaciones=OBS, detalles=fdets))
    fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    if not check(fid, f'  factura {tfac[-1]} desde el remito', err(r)):
        continue
    r = aj('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fid, accion_js='confirma'))
    if val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}") == '4':
        r = aj('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fid, accion_js='aprobada'))
    c = uno(f"SELECT comprobante_nro, importe_total, tabla_estado_registro_id estado FROM gestion__comprobantes WHERE tabla_origen_id = 80 AND registro_origen_id = {fid}")
    check(c and c['estado'] == '5', f"  factura confirmada nro {c and c['comprobante_nro']} por {c and c['importe_total']}", err(r))
    ctx['ventas'].append(dict(cliente=ck, venta_pedido_id=int(ped), venta_remito_id=int(rem), venta_factura_id=int(fid), total=num(c['importe_total']) if c else 0))
    guardar()

# =====================================================================================================================
caso('7. Controles finales')
neg = val(f"SELECT COUNT(*) FROM gestion__stock WHERE empresa_id = {E} AND cantidad < 0")
check(neg == '0', 'ningún stock negativo', neg)
desb = val(f"SELECT COUNT(*) FROM (SELECT a.cont_asiento_id FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d "
           f"ON d.cont_asiento_id = a.cont_asiento_id WHERE a.empresa_id = {E} AND a.tabla_estado_registro_id NOT IN (2, 38) "
           f"GROUP BY a.cont_asiento_id HAVING ABS(SUM(d.importe_local)) > 0.01) x")
check(desb == '0', 'asientos balanceados', desb)
resumen()
