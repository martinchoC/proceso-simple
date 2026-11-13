"""Percepciones recibidas en compras: IVA, Ganancias e IIBB en facturas y NC de proveedores.

Corre el mismo circuito en gestion (empresa 2, pág. 57) y en gestion_normal (empresa 6, pág. 557):
la pantalla ofrece las percepciones de la configuración de impuestos vinculada al subgrupo FC
(scripts 20261011_percepciones_recibidas_01_subgrupo_fc.sql y _02_provincias.sql, que el caso aplica en la copia: son
idempotentes; el 02 da de alta IIBB de las 24 jurisdicciones para la empresa 2),
la factura las guarda en gestion__comprobantes_impuestos, suma otros_impuestos al comprobante y el asiento
las debita en la cuenta de cada configuración. La NC las revierte (signo del tipo).
"""
from simlib import *

for _s in ('20261011_percepciones_recibidas_01_subgrupo_fc.sql', '20261011_percepciones_recibidas_02_provincias.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))

# Percepciones simuladas sobre el neto gravado: (impuesto_tipo_id, alícuota %)
PERCEPCIONES = [(2, 3.0), (3, 2.0), (1, 1.5)]   # IVA, Ganancias, IIBB
NOMBRE = {1: 'IIBB', 2: 'IVA', 3: 'Ganancias'}


def entorno_empresa_6():
    """Datos de la empresa 6 (Developsam, demo de gestion_normal) tomados de una compra suya confirmada."""
    f = uno("SELECT fp.entidad_id, fp.sucursal_id, fp.deposito_id, d.producto_id FROM gestion__facturas_proveedores fp "
            "JOIN gestion__facturas_proveedores_detalle d ON d.factura_proveedor_id = fp.factura_proveedor_id "
            "WHERE fp.empresa_id = 6 AND fp.tabla_estado_registro_id = 5 ORDER BY fp.factura_proveedor_id LIMIT 1")
    return dict(modulo='gestion_normal', empresa=6, pagina=557, prov=int(f['entidad_id']), prod=int(f['producto_id']),
                sucursal=int(f['sucursal_id']), deposito=int(f['deposito_id']), pv=13, nro_base=950000)


ENTORNOS = [
    dict(modulo='gestion', empresa=2, pagina=57, prov=5765, prod=1529, sucursal=1, deposito=1, pv=12, nro_base=940000),
    entorno_empresa_6(),
]


def tipo_id(e, letra, signo, nombre_like):
    return int(val(f"SELECT ct.comprobante_tipo_id FROM gestion__comprobantes_tipos ct "
                   f"JOIN gestion__comprobantes_subgrupos sg ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id "
                   f"WHERE sg.empresa_id = {e['empresa']} AND sg.codigo = 'FC' AND ct.letra = '{letra}' AND ct.signo = '{signo}' "
                   f"AND ct.comprobante_tipo LIKE '{nombre_like}' ORDER BY ct.comprobante_tipo_id LIMIT 1"))


def preparar(e):
    e['moneda'] = int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {e['empresa']} AND codigo = 'ARS' LIMIT 1"))
    e['iva_id'] = int(val(f"SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e['empresa']} "
                          f"AND porcentaje = 21 ORDER BY iva_alicuota_id LIMIT 1"))
    e['fa'] = tipo_id(e, 'A', '+', 'Factura%')
    e['nca'] = tipo_id(e, 'A', '-', 'Nota de Cr%')
    e['cfg'] = {int(r['impuesto_tipo_id']): r for r in sql(
        f"SELECT cfg.impuesto_tipo_id, cfg.empresa_impuesto_config_id id, cfg.cont_cuenta_id FROM gestion__empresas_impuestos_config cfg "
        f"WHERE cfg.empresa_id = {e['empresa']} AND cfg.impuesto_tipo_id IN (1,2,3) AND cfg.tabla_estado_registro_id = 1")}
    e['nro'] = proximo_nro_proveedor(e['pv'], e['nro_base']) - 1


def call(e, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    p.setdefault('pagina_idx', e['pagina'])
    return ajax('facturas_proveedores_ajax.php', p, method, modulo=e['modulo'], empresa_id=e['empresa'])


def armar_impuestos(e, neto, percepciones):
    imps = [dict(empresa_impuesto_config_id=int(e['cfg'][t]['id']), tipo_origen='manual', base_imponible=neto,
                 alicuota=a, importe=round(neto * a / 100, 2), detalle_origen=f'SIM percepción {NOMBRE[t]}')
            for t, a in percepciones]
    return imps, round(sum(i['importe'] for i in imps), 2)


def params_factura(e, tipo, dets, imps, otros, **extra):
    t = totales(dets)
    t['total'] = round(t['total'] + otros, 2)
    return dict(comprobante_tipo_id=tipo, comprobante_pv=e['pv'], comprobante_nro=e['nro'], sucursal_id=e['sucursal'],
                deposito_id=e['deposito'], entidad_id=e['prov'], f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
                moneda_id=e['moneda'], tipo_cambio=1, condicion_pago_id='', orden_compra_id='', descuento_general_pct=0,
                detalles=dets, impuestos_adicionales=imps, **t, **extra)


def fp_alta(e, tipo, cantidad, precio, percepciones, **extra):
    e['nro'] += 1
    dets = [linea(e['prod'], cantidad, precio, iva_id=e['iva_id'])]
    imps, otros = armar_impuestos(e, dets[0]['neto_gravado'], percepciones)
    r = call(e, dict(accion='agregar', **params_factura(e, tipo, dets, imps, otros, **extra)))
    return r, (r.get('factura_proveedor_id') if isinstance(r, dict) else None), dets, otros


def fp_confirmar(e, fid):
    call(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar'))
    return call(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar'))


def fila(fid):
    return uno(f"SELECT f.otros_impuestos, f.total, f.tabla_estado_registro_id estado, c.comprobante_id, c.importe_otros_impuestos, "
               f"c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id comp_estado FROM gestion__facturas_proveedores f "
               f"JOIN gestion__comprobantes c ON c.comprobante_id = f.comprobante_id WHERE f.factura_proveedor_id = {fid}")


def impuestos_guardados(comp_id):
    return {int(r['impuesto_tipo_id']): r for r in sql(
        f"SELECT impuesto_tipo_id, jurisdiccion_id, empresa_impuesto_config_id, base_imponible, alicuota, importe "
        f"FROM gestion__comprobantes_impuestos WHERE comprobante_id = {comp_id} AND tabla_estado_registro_id = 1")}


def asiento_cuenta(comp_id, cuenta_id):
    a = val(f"SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = {comp_id} AND tabla_estado_registro_id <> 2 LIMIT 1")
    if not a:
        return None
    return num(val(f"SELECT IFNULL(SUM(importe_local),0) FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {a} AND cuenta_id = {cuenta_id}"))


def asiento_balance(comp_id):
    return num(val(f"SELECT IFNULL(SUM(d.importe_local),0) FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d "
                   f"ON d.cont_asiento_id = a.cont_asiento_id WHERE a.comprobante_id = {comp_id} AND a.tabla_estado_registro_id <> 2"))


for E in ENTORNOS:
    preparar(E)
    tag = f"{E['modulo']} (empresa {E['empresa']})"

    # -----------------------------------------------------------------------------------------
    caso(f'Caso P1 - {tag}: la pantalla ofrece las percepciones de IVA, Ganancias e IIBB')
    check(all(t in E['cfg'] for t in (1, 2, 3)), 'la empresa tiene config activa de las tres percepciones', str(list(E['cfg'])))
    r = call(E, dict(accion='obtener_impuestos_config'), 'GET')
    ofrecidos = {int(x['impuesto_tipo_id']) for x in r} if isinstance(r, list) else set()
    check({1, 2, 3} <= ofrecidos, 'obtener_impuestos_config devuelve IVA, Ganancias e IIBB', str(r)[:300])
    check(all(int(c['cont_cuenta_id'] or 0) > 0 for c in E['cfg'].values()), 'cada percepción tiene cuenta contable')

    # -----------------------------------------------------------------------------------------
    caso(f'Caso P2 - {tag}: factura A con percepciones de IVA 3 %, Ganancias 2 % e IIBB 1,5 %')
    r, fa, dets, otros = fp_alta(E, E['fa'], 10, 1000, PERCEPCIONES)   # neto 10.000, IVA 2.100, percepciones 650
    check(ok_resp(r) and fa, 'alta en Borrador', err(r))
    if fa:
        E['fa_id'] = fa
        f = fila(fa)
        total = round(12100 + otros, 2)
        check(abs(otros - 650) < 0.01 and abs(num(f['otros_impuestos']) - 650) < 0.01, 'factura: otros_impuestos = 650', str(f))
        check(abs(num(f['importe_otros_impuestos']) - 650) < 0.01 and abs(num(f['importe_total']) - total) < 0.01,
              f'comprobante: otros impuestos 650 y total {total}', str(f))
        g = impuestos_guardados(f['comprobante_id'])
        esperado = {2: 300.0, 3: 200.0, 1: 150.0}
        check(set(g) == {1, 2, 3} and all(abs(num(g[t]['importe']) - v) < 0.01 for t, v in esperado.items()),
              'gestion__comprobantes_impuestos: IVA 300, Ganancias 200, IIBB 150', str(g))
        check(all(int(g[t]['empresa_impuesto_config_id']) == int(E['cfg'][t]['id']) and abs(num(g[t]['base_imponible']) - 10000) < 0.01
                  for t in g), 'cada fila con su config y base imponible 10.000', str(g))
        jur_iibb = val(f"SELECT jurisdiccion_id FROM gestion__empresas_impuestos_config WHERE empresa_impuesto_config_id = {E['cfg'][1]['id']}")
        check(1 in g and g[1]['jurisdiccion_id'] == jur_iibb, 'la percepción de IIBB guarda la jurisdicción de su config', str(g.get(1)))

        r = fp_confirmar(E, fa)
        f = fila(fa)
        check(ok_resp(r) and int(f['comp_estado']) == 5, 'confirmar (asiento sin faltantes)', err(r))
        check(abs(num(f['importe_pendiente']) - total) < 0.01, f'saldo del proveedor = total con percepciones ({total})', str(f))
        for t, v in esperado.items():
            imp = asiento_cuenta(f['comprobante_id'], int(E['cfg'][t]['cont_cuenta_id']))
            check(imp is not None and abs(imp - v) < 0.01, f'asiento: Debe {v} en la cuenta de percepción {NOMBRE[t]}', f'{imp}')
        check(abs(asiento_balance(f['comprobante_id'])) < 0.01, 'asiento balanceado', f"{asiento_balance(f['comprobante_id'])}")
        r = call(E, dict(accion='obtener_impuestos_factura', factura_proveedor_id=fa), 'GET')
        check(isinstance(r, list) and len(r) == 3, 'obtener_impuestos_factura devuelve las 3 al reabrir', str(r)[:200])

    # -----------------------------------------------------------------------------------------
    caso(f'Caso P3 - {tag}: editar en Borrador reemplaza las percepciones (y vaciarlas las borra)')
    r, fb, dets, otros = fp_alta(E, E['fa'], 4, 500, PERCEPCIONES)     # neto 2.000
    check(ok_resp(r) and fb, 'alta con las tres percepciones', err(r))
    if fb:
        imps, otros2 = armar_impuestos(E, 2000, [(2, 3.0), (1, 2.5)])  # sin Ganancias, IIBB 2,5 %
        r = call(E, dict(accion='editar', factura_proveedor_id=fb, **params_factura(E, E['fa'], dets, imps, otros2)))
        f = fila(fb)
        g = impuestos_guardados(f['comprobante_id'])
        check(ok_resp(r) and set(g) == {1, 2} and abs(num(g[1]['importe']) - 50) < 0.01, 'quedan IVA 60 e IIBB 50, sin Ganancias', err(r) + str(g))
        check(abs(num(f['otros_impuestos']) - 110) < 0.01 and abs(num(f['importe_otros_impuestos']) - 110) < 0.01, 'otros impuestos = 110', str(f))
        r = call(E, dict(accion='editar', factura_proveedor_id=fb, **params_factura(E, E['fa'], dets, [], 0)))
        f = fila(fb)
        check(ok_resp(r) and not impuestos_guardados(f['comprobante_id']) and num(f['otros_impuestos']) == 0,
              'editar sin percepciones las da de baja', err(r) + str(f))
        r = fp_confirmar(E, fb)
        check(ok_resp(r) and abs(asiento_balance(fila(fb)['comprobante_id'])) < 0.01, 'confirma sin percepciones, asiento balanceado', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso P4 - {tag}: rechazos')
    otra = 6 if E['empresa'] == 2 else 2
    ajena = int(val(f"SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {otra} AND impuesto_tipo_id = 2 LIMIT 1"))
    E['nro'] += 1
    dets = [linea(E['prod'], 1, 1000, iva_id=E['iva_id'])]
    antes = int(val("SELECT IFNULL(MAX(factura_proveedor_id),0) FROM gestion__facturas_proveedores"))
    imp_ajeno = [dict(empresa_impuesto_config_id=ajena, tipo_origen='manual', base_imponible=1000, alicuota=3, importe=30)]
    r = call(E, dict(accion='agregar', **params_factura(E, E['fa'], dets, imp_ajeno, 30)))
    check(not ok_resp(r) and 'impuesto' in err(r).lower(), 'rechaza la config de impuesto de otra empresa', err(r))
    check(int(val("SELECT IFNULL(MAX(factura_proveedor_id),0) FROM gestion__facturas_proveedores")) == antes, 'no quedó la factura a medias')
    venta = int(val(f"SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {E['empresa']} "
                    f"AND impuesto_tipo_id = 6 LIMIT 1") or 0)
    if venta:
        imp_venta = [dict(empresa_impuesto_config_id=venta, tipo_origen='manual', base_imponible=1000, alicuota=1, importe=10)]
        r = call(E, dict(accion='agregar', **params_factura(E, E['fa'], dets, imp_venta, 10)))
        check(not ok_resp(r), 'rechaza una percepción practicada (de ventas) en una compra', err(r))
    neg = [dict(empresa_impuesto_config_id=int(E['cfg'][2]['id']), tipo_origen='manual', base_imponible=1000, alicuota=3, importe=-30)]
    r = call(E, dict(accion='agregar', **params_factura(E, E['fa'], dets, neg, -30)))
    check(not ok_resp(r) and 'negativo' in err(r), 'rechaza un importe negativo', err(r))
    fa = E.get('fa_id')
    if fa:
        r = call(E, dict(accion='guardar_impuestos_factura', factura_proveedor_id=fa, impuestos_adicionales=[]))
        check(not ok_resp(r) and len(impuestos_guardados(fila(fa)['comprobante_id'])) == 3,
              'no deja tocar las percepciones de una factura confirmada', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso P5 - {tag}: NC A con percepción de IVA revierte el asiento')
    fa = E.get('fa_id')
    if fa:
        comp_fa = fila(fa)['comprobante_id']
        r, nc, dets, otros = fp_alta(E, E['nca'], 2, 1000, [(2, 3.0)], comprobante_asociado_id=comp_fa)   # neto 2.000, IVA perc 60
        check(ok_resp(r) and nc, 'alta NC A asociada con percepción de IVA', err(r))
        if nc:
            r = fp_confirmar(E, nc)
            f = fila(nc)
            check(ok_resp(r) and int(f['comp_estado']) in (5, 39, 40), 'NC confirmada', err(r) + str(f))
            check(abs(num(f['importe_total']) - 2480) < 0.01 and abs(num(f['importe_otros_impuestos']) - 60) < 0.01, 'NC total 2.480 (60 de percepción)', str(f))
            imp = asiento_cuenta(f['comprobante_id'], int(E['cfg'][2]['cont_cuenta_id']))
            check(imp is not None and abs(imp + 60) < 0.01, 'asiento de la NC: Haber 60 en percepciones de IVA', f'{imp}')
            check(abs(asiento_balance(f['comprobante_id'])) < 0.01, 'asiento de la NC balanceado')


# ---------------------------------------------------------------------------------------------
E = ENTORNOS[0]   # Casa Lucho: IIBB de todas las provincias (script 02)
caso('Caso P6 - gestion (empresa 2): IIBB de varias provincias en la misma factura, cada una a su cuenta')
iibb = {r['jurisdiccion_codigo']: r for r in sql(
    "SELECT j.jurisdiccion_codigo, c.empresa_impuesto_config_id id, c.cont_cuenta_id, c.prioridad FROM gestion__empresas_impuestos_config c "
    "JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = c.jurisdiccion_id "
    "WHERE c.empresa_id = 2 AND c.impuesto_tipo_id = 1 AND c.tabla_estado_registro_id = 1")}
check(len(iibb) == 24, 'la empresa 2 tiene percepción de IIBB recibida en las 24 jurisdicciones', str(sorted(iibb)))
check(len({r['cont_cuenta_id'] for r in iibb.values()}) == 24, 'cada jurisdicción con su propia cuenta contable')
r = call(E, dict(accion='obtener_impuestos_config'), 'GET')
ofrecidas = [x for x in r if int(x['impuesto_tipo_id']) == 1] if isinstance(r, list) else []
check(len(ofrecidas) == 24, 'la pantalla ofrece las 24 en "Agregar impuesto"', str(len(ofrecidas)))
check(ofrecidas and ofrecidas[0]['jurisdiccion_codigo'] == 'ARBA', 'la fila de IIBB sugerida al abrir sigue siendo Buenos Aires (prioridad 1)',
      ofrecidas[0]['jurisdiccion_codigo'] if ofrecidas else '')
if all(k in iibb for k in ('DGR_RN', 'DGR_CBA', 'AGIP')):
    E['nro'] += 1
    dets = [linea(E['prod'], 10, 1000, iva_id=E['iva_id'])]     # neto 10.000
    imps = [dict(empresa_impuesto_config_id=int(iibb[k]['id']), tipo_origen='manual', base_imponible=10000, alicuota=a,
                 importe=round(100 * a, 2), detalle_origen=f'SIM IIBB {k}') for k, a in (('DGR_RN', 1.0), ('DGR_CBA', 2.0), ('AGIP', 0.5))]
    imps.append(dict(empresa_impuesto_config_id=int(E['cfg'][2]['id']), tipo_origen='manual', base_imponible=10000, alicuota=3, importe=300))
    r = call(E, dict(accion='agregar', **params_factura(E, E['fa'], dets, imps, 650)))   # 100 + 200 + 50 + 300
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    check(ok_resp(r) and fid, 'alta con IIBB Río Negro 1 %, Córdoba 2 %, CABA 0,5 % e IVA 3 %', err(r))
    if fid:
        f = fila(fid)
        filas = sql(f"SELECT jurisdiccion_id, importe FROM gestion__comprobantes_impuestos WHERE comprobante_id = {f['comprobante_id']} "
                    f"AND impuesto_tipo_id = 1 AND tabla_estado_registro_id = 1")
        check(len(filas) == 3 and len({x['jurisdiccion_id'] for x in filas}) == 3, 'tres percepciones de IIBB, una por jurisdicción', str(filas))
        r = fp_confirmar(E, fid)
        f = fila(fid)
        check(ok_resp(r) and int(f['comp_estado']) == 5 and abs(num(f['importe_pendiente']) - 12750) < 0.01, 'confirmada con saldo 12.750', err(r) + str(f))
        for k, v in (('DGR_RN', 100), ('DGR_CBA', 200), ('AGIP', 50)):
            imp = asiento_cuenta(f['comprobante_id'], int(iibb[k]['cont_cuenta_id']))
            check(imp is not None and abs(imp - v) < 0.01, f'asiento: Debe {v} en la cuenta de IIBB {k}', f'{imp}')
        check(abs(asiento_balance(f['comprobante_id'])) < 0.01, 'asiento balanceado')
