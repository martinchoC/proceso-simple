"""Caso 30 - Stock y Movimientos (stock_consulta.php, página 138, gestion, empresa 2): planilla de conteo y
blanqueo de stock negativo (genera Ajustes de Stock, página 77).

  1 grilla: búsqueda por palabras y filtro de compatibilidad (marca), contra SQL
  2 planilla de conteo: trae lo mismo que la grilla
  3 negativo con 4 decimales (empresa en 2): el blanqueo lo deja exactamente en 0
  4 blanqueo en Borrador: no mueve stock
  5 blanqueo filtrado por compatibilidad: solo esos productos
  6 blanqueo del resto de la boca: todo en 0; repetirlo avisa que no hay negativos
Todo sobre la copia (gestion_multipyme_sim).
"""
from simlib import *

EMP, PAG, BOCA = 2, 138, 1
CTX = {}


def neg_boca(extra=''):
    return sql(f"SELECT s.producto_id p, s.cantidad c FROM gestion__stock s JOIN gestion__productos pr ON pr.producto_id = s.producto_id "
               f"AND pr.empresa_id = s.empresa_id AND pr.controla_stock = 1 WHERE s.empresa_id = {EMP} AND s.boca_id = {BOCA} AND s.cantidad < 0 {extra}")


def sc(params, method='GET'):
    p = dict(params)
    p.setdefault('empresa_idx', EMP)
    return ajax('stock_consulta_ajax.php', p, method)


def ajustes_blanqueo():
    return sql(f"SELECT stock_ajuste_id id, tabla_estado_registro_id e FROM gestion__stock_ajustes WHERE empresa_id = {EMP} "
               f"AND descripcion LIKE 'Blanqueo de stock negativo%' ORDER BY stock_ajuste_id")


def libro_ok():
    n = val(f"SELECT COUNT(*) FROM gestion__stock s LEFT JOIN (SELECT boca_id, producto_id, SUM(IF(tipo_movimiento='INGRESO', cantidad, -cantidad)) l "
            f"FROM gestion__stock_movimientos WHERE empresa_id = {EMP} GROUP BY boca_id, producto_id) m ON m.boca_id = s.boca_id AND m.producto_id = s.producto_id "
            f"WHERE s.empresa_id = {EMP} AND s.boca_id = {BOCA} AND ABS(s.cantidad - COALESCE(m.l, 0)) > 0.00005")
    check(n == '0', 'stock materializado de la boca = libro', f'{n} pares distintos')


# ---------------------------------------------------------------------------------------------
caso('Preparación')
sql(f"UPDATE gestion__bocas SET permite_stock_negativo = 1, permite_ingresos = 1, permite_egresos = 1 WHERE boca_id = {BOCA} AND empresa_id = {EMP}")
brecha('Configurado por SQL solo en la copia: boca 1 con stock negativo permitido e ingresos / egresos')
negs = neg_boca()
check(len(negs) > 0, f'hay productos con stock negativo en la boca {BOCA}', str(len(negs)))
tipo_menos = val(f"SELECT t.comprobante_tipo_id FROM gestion__comprobantes_tipos t JOIN gestion__comprobantes_subgrupos s ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id "
                 f"WHERE t.empresa_id = {EMP} AND s.codigo = 'CEF' AND t.tabla_estado_registro_id = 1 AND t.stock_signo = '-' LIMIT 1")
suc = val(f"SELECT sucursal_id FROM gestion__bocas WHERE boca_id = {BOCA}")


caso('1 - Grilla: búsqueda y compatibilidad')
fila = uno(f"SELECT p.producto_id, p.producto_codigo, p.producto_nombre FROM gestion__stock s JOIN gestion__productos p ON p.producto_id = s.producto_id "
           f"WHERE s.empresa_id = {EMP} AND p.controla_stock = 1 AND CHAR_LENGTH(p.producto_nombre) > 8 ORDER BY s.producto_id LIMIT 1")
palabras = fila['producto_nombre'].split()
q = ' '.join([palabras[0], palabras[-1]]) if len(palabras) > 1 else palabras[0]
r = sc({'accion': 'listar_stock', 'filtro': 'movidos', 'draw': 1, 'start': 0, 'length': 50, 'search[value]': q})
ids = [int(x['producto_id']) for x in r.get('data', [])] if isinstance(r, dict) else []
check(int(fila['producto_id']) in ids, f'buscar "{q}" (palabras sueltas) trae el producto {fila["producto_id"]}', str(r)[:300])

marcas = sc({'accion': 'compat_marcas'})
check(ok_resp(marcas), 'compat_marcas responde', err(marcas))
m = uno(f"SELECT pc.marca_id, COUNT(DISTINCT s.producto_id) n FROM gestion__stock s JOIN gestion__productos_compatibilidad pc ON pc.producto_id = s.producto_id "
        f"AND pc.empresa_id = s.empresa_id AND pc.tabla_estado_registro_id = 1 JOIN gestion__productos p ON p.producto_id = s.producto_id AND p.controla_stock = 1 "
        f"WHERE s.empresa_id = {EMP} GROUP BY pc.marca_id ORDER BY n DESC LIMIT 1")
if m:
    CTX['marca'] = int(m['marca_id'])
    check(CTX['marca'] in [int(x['marca_id']) for x in marcas.get('data', [])], 'la marca con más productos con stock está en el combo')
    r = sc({'accion': 'listar_stock', 'filtro': 'movidos', 'draw': 1, 'start': 0, 'length': 10, 'marca_id': CTX['marca']})
    check(isinstance(r, dict) and int(r.get('recordsFiltered', -1)) == int(m['n']),
          f'filtro por marca {CTX["marca"]}: {m["n"]} productos con stock registrado', f"obtuve {r.get('recordsFiltered') if isinstance(r, dict) else r}")
    mods = sc({'accion': 'compat_modelos', 'marca_id': CTX['marca']})
    check(ok_resp(mods) and len(mods['data']) > 0, 'compat_modelos de la marca', err(mods))
else:
    brecha('No hay productos con stock y compatibilidad: el filtro por marca no se prueba')


caso('2 - Planilla de conteo')
r = sc({'accion': 'conteo_listado', 'filtro': 'negativos', 'boca_id': BOCA})
tot = sc({'accion': 'listar_stock', 'filtro': 'negativos', 'boca_id': BOCA, 'draw': 1, 'start': 0, 'length': 10})
check(ok_resp(r) and len(r['data']['filas']) == int(tot.get('recordsFiltered', -1)) and not r['data']['truncado'],
      'la planilla trae los mismos productos que la grilla (negativos de la boca)', f"{len(r.get('data', {}).get('filas', []))} vs {tot.get('recordsFiltered')}")
check(all(str(BOCA) in (f['por_boca'] or {}) for f in r['data']['filas']), 'cada fila trae el stock de la boca')


caso('3 - Negativo con 4 decimales: el blanqueo lo deja exactamente en 0')
p_dec = val(f"SELECT p.producto_id FROM gestion__productos p LEFT JOIN gestion__stock s ON s.producto_id = p.producto_id AND s.boca_id = {BOCA} "
            f"WHERE p.empresa_id = {EMP} AND p.controla_stock = 1 AND p.es_servicio = 0 AND p.tabla_estado_registro_id = 1 AND COALESCE(s.cantidad, 0) = 0 LIMIT 1")
sql(f"UPDATE conf__empresas SET decimales_stock = 4 WHERE empresa_id = {EMP}")
a = ajax('stock_ajustes_ajax.php', dict(accion='agregar', empresa_idx=EMP, pagina_idx=77, sucursal_id=suc, deposito_id=BOCA,
         comprobante_tipo_id=tipo_menos, fecha=HOY, descripcion='SIM 30 faltante con decimales',
         detalles=[dict(producto_id=int(p_dec), deposito_id=BOCA, stock_sistema=0, stock_fisico=-1.2345, diferencia=-1.2345, cantidad_ajuste=-1.2345)]))
c = ajax('stock_ajustes_ajax.php', dict(accion='ejecutar_accion', stock_ajuste_id=a.get('stock_ajuste_id'), accion_js='confirmar', empresa_idx=EMP, pagina_idx=77))
sql(f"UPDATE conf__empresas SET decimales_stock = 2 WHERE empresa_id = {EMP}")
check(ok_resp(c) and abs(stock(int(p_dec), BOCA) + 1.2345) < 0.00001, f'producto {p_dec} en -1,2345', f'{err(c)} / {stock(int(p_dec), BOCA)}')
prev = sc({'accion': 'blanqueo_previa', 'producto_id': p_dec})
check(ok_resp(prev) and len(prev['data']['bocas']) == 1 and prev['data']['bocas'][0]['productos'] == 1, 'la vista previa muestra 1 producto en 1 boca', str(prev)[:300])
check(prev['data'].get('puede_agregar') and prev['data'].get('puede_confirmar'), 'el superadmin puede agregar y confirmar')
g = sc({'accion': 'blanqueo_generar', 'producto_id': p_dec, 'confirmar': '1'}, 'POST')
check(ok_resp(g) and g['data'][0]['confirmado'], 'blanqueo confirmado', err(g))
check(stock(int(p_dec), BOCA) == 0, f'stock exactamente 0 (no -0,0045 por redondear a 2)', str(stock(int(p_dec), BOCA)))
det = uno(f"SELECT cantidad_ajuste q FROM gestion__stock_ajustes_detalles WHERE stock_ajuste_id = {g['data'][0]['stock_ajuste_id']}")
check(abs(num(det['q']) - 1.2345) < 0.00001, 'el detalle guarda 1,2345', str(det))


caso('4 - Blanqueo en Borrador: no mueve stock')
p4 = neg_boca('ORDER BY s.producto_id LIMIT 1')[0]
g = sc({'accion': 'blanqueo_generar', 'producto_id': p4['p'], 'confirmar': '0'}, 'POST')
check(ok_resp(g) and not g['data'][0]['confirmado'], 'ajuste generado sin confirmar', err(g))
aid = g['data'][0]['stock_ajuste_id']
check(val(f"SELECT tabla_estado_registro_id FROM gestion__stock_ajustes WHERE stock_ajuste_id = {aid}") == '3', 'queda en Borrador (3)')
check(abs(stock(int(p4['p']), BOCA) - num(p4['c'])) < 0.00001, 'el stock no cambia', str(stock(int(p4['p']), BOCA)))
c = ajax('stock_ajustes_ajax.php', dict(accion='ejecutar_accion', stock_ajuste_id=aid, accion_js='confirmar', empresa_idx=EMP, pagina_idx=77))
check(ok_resp(c) and stock(int(p4['p']), BOCA) == 0, 'confirmado después desde Ajustes de Stock, queda en 0', err(c))


caso('5 - Blanqueo filtrado por compatibilidad')
if CTX.get('marca'):
    filtro_m = (f"AND EXISTS (SELECT 1 FROM gestion__productos_compatibilidad pc WHERE pc.producto_id = s.producto_id AND pc.empresa_id = s.empresa_id "
                f"AND pc.tabla_estado_registro_id = 1 AND pc.marca_id = {CTX['marca']})")
    con_marca, sin_marca = neg_boca(filtro_m), neg_boca(f"AND NOT {filtro_m[4:]}")
    if con_marca:
        g = sc({'accion': 'blanqueo_generar', 'marca_id': CTX['marca'], 'boca_id': BOCA, 'confirmar': '1'}, 'POST')
        check(ok_resp(g), 'blanqueo por marca', err(g))
        check(not neg_boca(filtro_m), f'los {len(con_marca)} negativos de la marca quedan en 0')
        check(len(neg_boca(f"AND NOT {filtro_m[4:]}")) == len(sin_marca), f'los {len(sin_marca)} negativos de otras marcas no se tocan')
    else:
        brecha('La marca elegida no tiene productos negativos en la boca: el caso 5 no se prueba')
else:
    brecha('Sin marca para probar (caso 1)')


caso('6 - Blanqueo del resto de la boca')
antes = len(ajustes_blanqueo())
g = sc({'accion': 'blanqueo_generar', 'boca_id': BOCA, 'confirmar': '1'}, 'POST')
check(ok_resp(g) and len(g['data']) == 1, 'un ajuste para la boca', err(g))
check(not neg_boca(), 'no quedan negativos en la boca')
check(all(x['e'] == '5' for x in ajustes_blanqueo()[antes:]), 'el ajuste quedó confirmado (5)')
g = sc({'accion': 'blanqueo_generar', 'boca_id': BOCA, 'confirmar': '1'}, 'POST')
check(not ok_resp(g) and 'no hay productos' in err(g).lower(), 'repetirlo avisa que no hay negativos', err(g))
prev = sc({'accion': 'blanqueo_previa', 'boca_id': BOCA})
check(ok_resp(prev) and prev['data']['bocas'] == [], 'la vista previa queda vacía')
libro_ok()
