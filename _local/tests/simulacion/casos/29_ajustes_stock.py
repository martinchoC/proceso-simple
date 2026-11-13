"""Caso 29 - Ajustes de inventario (stock_ajustes.php, gestion, empresa 2).

10 ajustes por las pantallas reales (stock_ajustes_ajax.php) + 1 caso extra de borde:
  1 sobrante | 2 faltante | 3 conteo fisico + | 4 conteo fisico - | 5 varias lineas | 6 costo promedio
  7 doble confirmacion | 8 anulacion (reversion) | 9 faltante mayor al stock | 10 edicion en borrador y confirmado
  11 (extra) tipo "suma" con diferencia negativa

Todo se descubre de la base (tipos AST, estados, funciones de la pagina, boca) y lo que falta se
configura por SQL SOLO en la copia (con --base-local no se toca nada: queda como brecha).
Los chequeos 10b y 11 apuntan a huecos posibles del modelo: si fallan, es un hallazgo, no un error del caso.
"""
import re

from simlib import *

PAG = 77                        # stock_ajustes.php (modulo 2)
EMP = 2
P1, P2, P3 = 1529, 1878, 3500   # productos con stock controlado de la empresa 2
CTX = {}
ESP = {P1: 0.0, P2: 0.0, P3: 0.0}     # variacion de stock esperada por el conjunto de casos 1-10
ENT = {}                              # entorno descubierto


def cerca(a, b, tol=0.0001):
    return abs(float(a or 0) - float(b or 0)) <= tol


def prepara(q, texto):
    """Configuracion que falta: solo en la copia. Con --base-local no se modifica nada."""
    if BASE_LOCAL:
        brecha('Falta configuracion y --base-local no la modifica: ' + texto)
        return False
    sql(q)
    brecha('Configurado por SQL solo en la copia: ' + texto)
    return True


def st(p):
    return stock(p, ENT['boca'], EMP)


def costos(p):
    f = uno(f"SELECT cantidad, costo_promedio, costo_ultima_compra FROM gestion__stock "
            f"WHERE empresa_id={EMP} AND boca_id={ENT['boca']} AND producto_id={p}")
    return (num(f['cantidad']), num(f['costo_promedio']), num(f['costo_ultima_compra'])) if f else (0.0, 0.0, 0.0)


def det(prod, diff=None, fisico=None, costo=0.0, obs='SIM'):
    sis = st(prod)
    if fisico is not None:
        diff = round(fisico - sis, 6)
    return dict(producto_id=prod, deposito_id=ENT['boca'], stock_sistema=sis, stock_fisico=round(sis + diff, 6),
                diferencia=diff, cantidad_ajuste=diff, costo_unitario=costo, costo_total=round(abs(diff) * costo, 2),
                observacion=obs)


def alta(tipo, dets, desc):
    r = ajax('stock_ajustes_ajax.php', dict(accion='agregar', empresa_idx=EMP, pagina_idx=PAG, sucursal_id=ENT['suc'],
             deposito_id=ENT['boca'], comprobante_tipo_id=tipo, fecha=HOY, descripcion=desc, detalles=dets))
    aid = r.get('stock_ajuste_id') if isinstance(r, dict) else None
    return (int(aid) if aid else None), r


def editar(aid, tipo, dets, desc):
    return ajax('stock_ajustes_ajax.php', dict(accion='editar', stock_ajuste_id=aid, empresa_idx=EMP, pagina_idx=PAG,
                sucursal_id=ENT['suc'], deposito_id=ENT['boca'], comprobante_tipo_id=tipo, fecha=HOY,
                descripcion=desc, detalles=dets))


def accion(aid, acc):
    return ajax('stock_ajustes_ajax.php', dict(accion='ejecutar_accion', stock_ajuste_id=aid, accion_js=acc,
                empresa_idx=EMP, pagina_idx=PAG))


def estado(aid):
    return str(val(f"SELECT tabla_estado_registro_id FROM gestion__stock_ajustes WHERE stock_ajuste_id={aid}"))


def movs(aid):
    return sql(f"SELECT tipo_movimiento t, cantidad c, producto_id p FROM gestion__stock_movimientos "
               f"WHERE empresa_id={EMP} AND tabla_origen_id={ENT['tabla']} AND registro_origen_id={aid} ORDER BY stock_movimiento_id")


def netos(aid):
    out = {}
    for m in movs(aid):
        out[int(m['p'])] = out.get(int(m['p']), 0.0) + (num(m['c']) if m['t'] == 'INGRESO' else -num(m['c']))
    return {p: q for p, q in out.items() if abs(q) > 0.0001}


def verificar_libro(aid, esperado):
    n = netos(aid)
    ok = set(n) == {p for p, q in esperado.items() if abs(q) > 0.0001} and all(cerca(n[p], esperado[p]) for p in n)
    check(ok, f'libro de stock del ajuste {aid}: neto por producto {esperado}', f'obtuve {n}')


def verificar_espejo(aid, destino=None):
    c = uno(f"SELECT comprobante_id, tabla_estado_registro_id e FROM gestion__comprobantes "
            f"WHERE tabla_origen_id={ENT['tabla']} AND registro_origen_id={aid}")
    a = uno(f"SELECT comprobante_id, tabla_estado_registro_id e FROM gestion__stock_ajustes WHERE stock_ajuste_id={aid}")
    ok = bool(c and a and c['comprobante_id'] == a['comprobante_id'] and c['e'] == a['e'])
    if destino is not None:
        ok = ok and a['e'] == str(destino)
    check(ok, 'espejo en gestion__comprobantes sincronizado con el estado del ajuste', f'comprobante {c} / ajuste {a}')


# ---------------------------------------------------------------------------------------------
def preparar_entorno():
    ENT['tabla'] = int(val(f"SELECT tabla_id FROM conf__paginas WHERE pagina_id={PAG}") or 0)
    check(ENT['tabla'] > 0, f'la pagina {PAG} (stock_ajustes) existe y tiene tabla asociada')
    if ENT['tabla'] <= 0:
        return False

    # boca / sucursal
    try:
        b = uno(f"SELECT boca_id, sucursal_id FROM gestion__bocas WHERE empresa_id={EMP} AND es_deposito=1 "
                f"AND tabla_estado_registro_id=1 ORDER BY (boca_id=1) DESC, boca_id LIMIT 1")
    except RuntimeError:
        b = uno(f"SELECT boca_id, sucursal_id FROM gestion__bocas WHERE empresa_id={EMP} "
                f"AND tabla_estado_registro_id=1 ORDER BY (boca_id=1) DESC, boca_id LIMIT 1")
    check(bool(b), 'hay una boca/deposito activa en la empresa 2')
    if not b:
        return False
    ENT['boca'], ENT['suc'] = int(b['boca_id']), int(b['sucursal_id'])
    fl = uno(f"SELECT permite_ingresos pi, permite_egresos pe FROM gestion__bocas WHERE boca_id={ENT['boca']}")
    if fl['pi'] != '1' or fl['pe'] != '1':
        # La boca debe admitir ingresos y egresos para que el stock se aplique (stock_core_model.php)
        prepara(f"UPDATE gestion__bocas SET permite_ingresos=1, permite_egresos=1 WHERE boca_id={ENT['boca']} AND empresa_id={EMP}",
                f'boca {ENT["boca"]} con permite_ingresos/permite_egresos = 1')
    r = ajax('stock_ajustes_ajax.php', dict(accion='obtener_depositos', sucursal_id=ENT['suc'], empresa_idx=EMP, pagina_idx=PAG), 'GET')
    check(isinstance(r, list) and ENT['boca'] in [int(x['deposito_id']) for x in r],
          'la pantalla ofrece la boca como deposito de la sucursal', str(r)[:300])

    # productos con stock controlado
    for p in (P1, P2, P3):
        f = uno(f"SELECT controla_stock cs, es_servicio es FROM gestion__productos WHERE producto_id={p} AND empresa_id={EMP}")
        if not f:
            check(False, f'el producto {p} existe en la empresa {EMP}')
            return False
        if f['cs'] != '1' or f['es'] != '0':
            prepara(f"UPDATE gestion__productos SET controla_stock=1, es_servicio=0 WHERE producto_id={p} AND empresa_id={EMP}",
                    f'producto {p} con controla_stock=1')

    # tipos de comprobante (subgrupo AST) y su sentido en stock
    r = ajax('stock_ajustes_ajax.php', dict(accion='obtener_comprobantes_tipos', empresa_idx=EMP, pagina_idx=PAG), 'GET')
    ids = [int(t['comprobante_tipo_id']) for t in r] if isinstance(r, list) else []
    check(len(ids) > 0, 'la pantalla ofrece tipos de comprobante de ajuste (subgrupo AST)', str(r)[:300])
    if not ids:
        return False
    tipos = {int(t['id']): t for t in sql(
        f"SELECT comprobante_tipo_id id, comprobante_tipo nombre, impacta_stock imp, stock_signo signo "
        f"FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id IN ({','.join(map(str, ids))})")}
    for i, t in tipos.items():
        if t['imp'] != '1' or not t['signo']:
            n = t['nombre'] or ''
            signo = '+' if re.search('sobrante|ingreso|entrada|alta', n, re.I) else \
                    '-' if re.search('faltante|egreso|merma|baja|salida|rotura', n, re.I) else '+/-'
            if prepara(f"UPDATE gestion__comprobantes_tipos SET impacta_stock=1, stock_signo='{signo}' WHERE comprobante_tipo_id={i}",
                       f"tipo {i} '{n}' impacta stock con signo {signo}"):
                t['imp'], t['signo'] = '1', signo
    if not any(t['signo'] == '+/-' for t in tipos.values()):
        i = ids[-1]
        if prepara(f"UPDATE gestion__comprobantes_tipos SET impacta_stock=1, stock_signo='+/-' WHERE comprobante_tipo_id={i}",
                   f"tipo {i} como '+/-' para poder simular conteos fisicos"):
            tipos[i]['imp'], tipos[i]['signo'] = '1', '+/-'
    por = lambda s: next((i for i, t in tipos.items() if t['imp'] == '1' and t['signo'] == s), None)
    ENT['mix'] = por('+/-')
    ENT['mas'] = por('+') or ENT['mix']
    ENT['menos'] = por('-') or ENT['mix']
    ENT['mas_puro'] = por('+')
    check(ENT['mas'] and ENT['menos'], f'hay tipos que suman y que restan stock (suma {ENT["mas"]}, resta {ENT["menos"]}, mixto {ENT["mix"]})')
    if not (ENT['mas'] and ENT['menos']):
        return False

    # estados y funciones de la pagina
    ini = val(f"SELECT ter.estado_registro_id FROM conf__paginas p JOIN conf__tablas_estados_registros ter ON ter.tabla_id=p.tabla_id "
              f"WHERE p.pagina_id={PAG} AND ter.es_inicial=1 LIMIT 1")
    ENT['ini'] = str(ini)
    funcs = sql(f"SELECT pf.accion_js acc, pf.nombre_funcion nombre, pf.tabla_estado_registro_origen_id o, "
                f"pf.tabla_estado_registro_destino_id d, IFNULL(ter.aplica_stock,0) aplica "
                f"FROM conf__paginas_funciones pf JOIN conf__paginas p ON p.pagina_id=pf.pagina_id "
                f"LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id=p.tabla_id AND ter.estado_registro_id=pf.tabla_estado_registro_destino_id "
                f"WHERE pf.pagina_id={PAG} AND pf.tabla_estado_registro_id=1")
    desde_ini = [f for f in funcs if f['o'] == ENT['ini'] and f['d'] != f['o']]
    conf = [f for f in desde_ini if f['aplica'] == '1']
    if not conf:
        cand = [f for f in desde_ini if re.search('confirm|aprob|aplic', f"{f['acc']} {f['nombre']}", re.I)]
        if cand and prepara(f"UPDATE conf__tablas_estados_registros SET aplica_stock=1 WHERE tabla_id={ENT['tabla']} AND estado_registro_id={cand[0]['d']}",
                            f"estado {cand[0]['d']} (destino de '{cand[0]['acc']}') con aplica_stock=1"):
            conf = cand
    check(bool(conf), 'existe una funcion de la pagina que lleva el ajuste a un estado que aplica stock',
          f'funciones desde el estado inicial {ENT["ini"]}: {[(f["acc"], f["d"], f["aplica"]) for f in desde_ini]}')
    if not conf:
        return False
    ENT['conf'], ENT['dest_conf'] = conf[0]['acc'], conf[0]['d']
    anul = [f for f in funcs if f['o'] == ENT['dest_conf'] and f['d'] != f['o'] and f['aplica'] == '0']
    ENT['anul'], ENT['dest_anul'] = (anul[0]['acc'], anul[0]['d']) if anul else (None, None)
    if not anul:
        brecha('El ajuste confirmado no tiene funcion de anulacion/reversion hacia un estado sin aplica_stock: el caso 8 no se puede simular')
    return True


# ---------------------------------------------------------------------------------------------
def caso1():
    caso('Caso 1 - Sobrante: +20 de P1')
    s = st(P1)
    aid, r = alta(ENT['mas'], [det(P1, 20)], 'SIM 1 sobrante')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a1'] = aid
    check(estado(aid) == ENT['ini'], 'queda en el estado inicial', estado(aid))
    check(cerca(st(P1), s) and not movs(aid), 'en borrador no mueve stock ni escribe en el libro')
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P1), s + 20), f'stock +20 ({s} -> {st(P1)})')
    check(estado(aid) == str(ENT['dest_conf']), 'estado destino de la confirmacion', estado(aid))
    verificar_libro(aid, {P1: 20})
    verificar_espejo(aid, ENT['dest_conf'])
    ESP[P1] += 20


def caso2():
    caso('Caso 2 - Faltante: -2 de P1')
    s = st(P1)
    aid, r = alta(ENT['menos'], [det(P1, -2)], 'SIM 2 faltante')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a2'] = aid
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P1), s - 2), f'stock -2 ({s} -> {st(P1)})')
    verificar_libro(aid, {P1: -2})
    verificar_espejo(aid, ENT['dest_conf'])
    ESP[P1] -= 2


def caso3():
    caso('Caso 3 - Conteo fisico con diferencia positiva (P2: fisico = sistema + 10)')
    s = st(P2)
    aid, r = alta(ENT['mix'], [det(P2, fisico=s + 10)], 'SIM 3 conteo +')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a3'] = aid
    d = uno(f"SELECT stock_sistema ss, stock_fisico sf, diferencia df, cantidad_ajuste ca FROM gestion__stock_ajustes_detalles WHERE stock_ajuste_id={aid}")
    check(d and cerca(d['ss'], s) and cerca(d['sf'], s + 10) and cerca(d['df'], 10) and cerca(d['ca'], 10),
          'el detalle guarda sistema / fisico / diferencia / ajuste', str(d))
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P2), s + 10), f'stock +10 ({s} -> {st(P2)})')
    verificar_libro(aid, {P2: 10})
    verificar_espejo(aid, ENT['dest_conf'])
    ESP[P2] += 10


def caso4():
    caso('Caso 4 - Conteo fisico con diferencia negativa (P2: fisico = sistema - 4)')
    s = st(P2)
    aid, r = alta(ENT['mix'], [det(P2, fisico=s - 4)], 'SIM 4 conteo -')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a4'] = aid
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P2), s - 4), f'stock -4 ({s} -> {st(P2)})')
    verificar_libro(aid, {P2: -4})
    verificar_espejo(aid, ENT['dest_conf'])
    ESP[P2] -= 4


def caso5():
    caso('Caso 5 - Un ajuste con tres lineas (P1 +3, P2 -1, P3 +6)')
    s1, s2, s3 = st(P1), st(P2), st(P3)
    aid, r = alta(ENT['mix'], [det(P1, 3), det(P2, -1), det(P3, 6)], 'SIM 5 multilinea')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a5'] = aid
    r = ajax('stock_ajustes_ajax.php', dict(accion='obtener', stock_ajuste_id=aid, empresa_idx=EMP, pagina_idx=PAG), 'GET')
    check(isinstance(r, dict) and len(r.get('detalles', [])) == 3, 'obtener devuelve las 3 lineas', str(r)[:300])
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P1), s1 + 3) and cerca(st(P2), s2 - 1) and cerca(st(P3), s3 + 6),
          f'stock P1 {s1}->{st(P1)}, P2 {s2}->{st(P2)}, P3 {s3}->{st(P3)}')
    verificar_libro(aid, {P1: 3, P2: -1, P3: 6})
    verificar_espejo(aid, ENT['dest_conf'])
    ESP[P1] += 3
    ESP[P2] -= 1
    ESP[P3] += 6


def caso6():
    caso('Caso 6 - Sobrante con costo: recalcula costo promedio y ultimo costo')
    c0, avg0, _ = costos(P3)
    aid, r = alta(ENT['mas'], [det(P3, 10, costo=100.0)], 'SIM 6 con costo')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a6'] = aid
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    c1, avg1, ult1 = costos(P3)
    base = max(c0, 0.0)
    esperado = round((base * avg0 + 10 * 100.0) / (base + 10), 6)
    check(cerca(c1, c0 + 10), f'stock +10 ({c0} -> {c1})')
    check(cerca(ult1, 100.0), 'costo_ultima_compra = 100', str(ult1))
    check(cerca(avg1, esperado, 0.001), f'costo promedio ponderado {avg0} -> {avg1} (esperado {esperado})')
    verificar_libro(aid, {P3: 10})
    ESP[P3] += 10


def caso7():
    caso('Caso 7 - Confirmar dos veces el mismo ajuste (idempotencia)')
    aid = CTX.get('a3')
    if not aid:
        brecha('Caso 7 omitido: no hay ajuste del caso 3')
        return
    s, n = st(P2), len(movs(aid))
    r = accion(aid, ENT['conf'])
    check(not ok_resp(r), 'la segunda confirmacion se rechaza', str(r)[:300])
    check('permitida' in str(err(r)).lower(), 'el mensaje explica el motivo ("Accion no permitida para este estado")', err(r))
    check(cerca(st(P2), s) and len(movs(aid)) == n, 'no mueve stock ni agrega movimientos')


def caso8():
    caso('Caso 8 - Anular un ajuste confirmado: revierte el stock')
    aid = CTX.get('a1')
    if not aid or not ENT['anul']:
        brecha('Caso 8 omitido: falta el ajuste del caso 1 o la funcion de anulacion')
        return
    s = st(P1)
    r = accion(aid, ENT['anul'])
    check(ok_resp(r), f"ejecutar '{ENT['anul']}'", err(r))
    check(cerca(st(P1), s - 20), f'stock -20 por la reversion ({s} -> {st(P1)})')
    check(estado(aid) == str(ENT['dest_anul']), 'estado destino de la anulacion', estado(aid))
    verificar_libro(aid, {})
    ms = movs(aid)
    check(len(ms) == 2 and {m['t'] for m in ms} == {'INGRESO', 'EGRESO'}, 'el libro conserva el ingreso y suma el egreso compensatorio (no borra)', str(ms))
    verificar_espejo(aid, ENT['dest_anul'])
    ESP[P1] -= 20


def caso9():
    caso('Caso 9 - Faltante mayor al stock disponible: se rechaza sin dejar nada a medias')
    f = uno(f"SELECT permite_stock_negativo n FROM gestion__bocas WHERE boca_id={ENT['boca']}")
    if f['n'] == '1' and not prepara(f"UPDATE gestion__bocas SET permite_stock_negativo=0 WHERE boca_id={ENT['boca']} AND empresa_id={EMP}",
                                     f"boca {ENT['boca']} sin stock negativo"):
        brecha('Caso 9 omitido: la boca permite stock negativo')
        return
    s = st(P1)
    aid, r = alta(ENT['menos'], [det(P1, -(s + 1000))], 'SIM 9 faltante excesivo')
    check(aid, 'el alta en borrador se acepta (la validacion es al confirmar)', str(r)[:300])
    if not aid:
        return
    CTX['a9'] = aid
    r = accion(aid, ENT['conf'])
    check(not ok_resp(r), 'confirmar se rechaza', str(r)[:300])
    check('insuficiente' in str(err(r)).lower(), 'el mensaje dice "Stock insuficiente"', err(r))
    check(cerca(st(P1), s), 'el stock no cambia')
    check(estado(aid) == ENT['ini'], 'el ajuste sigue en el estado inicial', estado(aid))
    check(not movs(aid), 'no quedan movimientos en el libro')
    n = val(f"SELECT COUNT(*) FROM gestion__comprobantes WHERE tabla_origen_id={ENT['tabla']} AND registro_origen_id={aid} AND tabla_estado_registro_id<>{ENT['ini']}")
    check(n == '0', 'no queda un espejo en estado confirmado', n)


def caso10():
    caso('Caso 10 - Editar en borrador y editar un ajuste ya confirmado')
    s = st(P3)
    aid, r = alta(ENT['mas'], [det(P3, 5)], 'SIM 10 original')
    check(aid, 'alta del ajuste', str(r)[:300])
    if not aid:
        return
    CTX['a10'] = aid
    r = editar(aid, ENT['mas'], [det(P3, 8)], 'SIM 10 editado')
    check(ok_resp(r), 'editar en borrador', err(r))
    f = uno(f"SELECT COUNT(*) n, SUM(cantidad_ajuste) q FROM gestion__stock_ajustes_detalles WHERE stock_ajuste_id={aid}")
    check(f['n'] == '1' and cerca(f['q'], 8), 'queda una sola linea con la cantidad editada (8)', str(f))
    check(cerca(st(P3), s) and not movs(aid), 'editar en borrador no mueve stock')
    r = accion(aid, ENT['conf'])
    check(ok_resp(r), 'confirmar', err(r))
    check(cerca(st(P3), s + 8), f'stock +8, no +5 ({s} -> {st(P3)})')
    verificar_libro(aid, {P3: 8})
    ESP[P3] += 8

    # 10b: la edicion de un ajuste YA CONFIRMADO no deberia estar permitida (el ajax no valida el estado)
    r = editar(aid, ENT['mas'], [det(P3, 1)], 'SIM 10 editado confirmado')
    check(not ok_resp(r), '10b: editar un ajuste confirmado se rechaza', 'el modelo lo acepto: ' + str(r)[:200])
    f = uno(f"SELECT SUM(cantidad_ajuste) q FROM gestion__stock_ajustes_detalles WHERE stock_ajuste_id={aid}")
    check(cerca(f['q'], netos(aid).get(P3, 0)), '10b: el detalle del ajuste confirmado coincide con lo que movio el libro',
          f"detalle {f['q']} vs libro {netos(aid).get(P3, 0)}")


def caso11():
    caso('Caso 11 (extra) - Tipo que SUMA con diferencia negativa no debe sumar stock')
    if not ENT['mas_puro']:
        brecha('Caso 11 omitido: no hay un tipo de ajuste que solo sume')
        return
    s = st(P3)
    aid, r = alta(ENT['mas_puro'], [det(P3, -2)], 'SIM 11 signo cruzado')
    rechazo_alta = not aid
    rechazo_conf = False
    if aid:
        CTX['a11'] = aid
        rechazo_conf = not ok_resp(accion(aid, ENT['conf']))
    delta = round(st(P3) - s, 4)
    ESP[P3] += delta   # lo que realmente haya pasado queda dentro del cuadre final
    check(rechazo_alta or rechazo_conf or delta <= 0.0001,
          'rechazado al crear/confirmar, o al menos no suma stock', f'stock {s} -> {st(P3)} (variacion {delta})')


def cierre():
    caso('Cierre - listados y cuadre de stock')
    r = ajax('stock_ajustes_ajax.php', dict(accion='listar', empresa_idx=EMP, pagina_idx=PAG), 'GET')
    ids = {int(x['stock_ajuste_id']) for x in r} if isinstance(r, list) else set()
    propios = {v for v in CTX.values() if v}
    check(propios <= ids, f'el listado trae los {len(propios)} ajustes simulados', f'faltan {sorted(propios - ids)}')
    code = val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id={P1}")
    r = ajax('stock_ajustes_ajax.php', dict(accion='buscar_productos', deposito_id=ENT['boca'], q=code, empresa_idx=EMP, pagina_idx=PAG), 'GET')
    it = next((x for x in r if int(x['producto_id']) == P1), None) if isinstance(r, list) else None
    check(it is not None and cerca(it['stock_sistema'], st(P1)), 'buscar_productos devuelve el stock materializado de la boca', str(it))
    for p in (P1, P2, P3):
        check(cerca(st(p), ENT['ini_stock'][p] + ESP[p]),
              f'P{p}: stock final = inicial + ajustes ({ENT["ini_stock"][p]} + {ESP[p]} = {st(p)})')
    libro = num(val(f"SELECT SUM(IF(tipo_movimiento='INGRESO',cantidad,-cantidad)) FROM gestion__stock_movimientos "
                    f"WHERE empresa_id={EMP} AND boca_id={ENT['boca']} AND producto_id IN ({P1},{P2},{P3}) AND tabla_origen_id={ENT['tabla']} "
                    f"AND registro_origen_id IN ({','.join(str(v) for v in propios) or 0})"))
    check(cerca(libro, sum(ESP.values())), f'neto del libro de los ajustes simulados = {sum(ESP.values())}', str(libro))


# ---------------------------------------------------------------------------------------------
caso('Preparacion del entorno')
if preparar_entorno():
    ENT['ini_stock'] = {p: st(p) for p in (P1, P2, P3)}
    for f in (caso1, caso2, caso3, caso4, caso5, caso6, caso7, caso8, caso9, caso10, caso11, cierre):
        try:
            f()
        except Exception as e:   # un caso roto no debe cortar la corrida completa
            check(False, f'{f.__name__} aborto por una excepcion del propio caso', repr(e))
else:
    brecha('No se pudo preparar el entorno: los casos de ajustes de stock no se ejecutaron')
