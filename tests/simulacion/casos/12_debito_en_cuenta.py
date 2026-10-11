"""Débito directo en cuenta bancaria: Resumen bancario (RG 1415, cód. 039) y facturas debitadas automáticamente.

Corre en gestion (empresa 2, pág. 57) y gestion_normal (empresa 6, pág. 557). Aplica en la copia (idempotentes):
20261011_percepciones_recibidas_01/02 y 20261011_debito_en_cuenta_01/02. El resumen se carga como comprobante de
compra (entra al Libro IVA con su IVA por alícuota, no gravado y percepciones) y al confirmarlo se cancela con una
OP automática (medio DEB_CTA) que egresa de la cuenta bancaria indicada.
"""
from simlib import *

for _s in ('20261011_percepciones_recibidas_01_subgrupo_fc.sql', '20261011_percepciones_recibidas_02_provincias.sql',
           '20261011_debito_en_cuenta_01_estructura.sql', '20261011_debito_en_cuenta_02_resumen_bancario.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))


def entorno(modulo, empresa, pagina, prov, sucursal, deposito, pv):
    e = dict(modulo=modulo, empresa=empresa, pagina=pagina, prov=prov, sucursal=sucursal, deposito=deposito, pv=pv)
    q = lambda s: val(s.format(e=empresa))
    e['moneda'] = int(q("SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {e} AND codigo = 'ARS' LIMIT 1"))
    e['rb'] = int(q("SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e} AND codigo = 'C_RB'"))
    e['fa'] = int(q("SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e} AND codigo = 'C_FA'"))
    e['nca'] = int(q("SELECT ct.comprobante_tipo_id FROM gestion__comprobantes_tipos ct JOIN gestion__comprobantes_subgrupos sg "
                     "ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id WHERE sg.empresa_id = {e} AND sg.codigo = 'FC' "
                     "AND ct.letra = 'A' AND ct.signo = '-' LIMIT 1"))
    e['iva21'] = int(q("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e} AND es_gravado = 1 AND porcentaje = 21"))
    e['iva105'] = int(q("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e} AND es_gravado = 1 AND porcentaje = 10.5"))
    e['nograv'] = int(q("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e} AND es_no_gravado = 1"))
    e['prod'] = {k: int(q(f"SELECT producto_id FROM gestion__productos WHERE producto_codigo = 'BCO_{k}_E{{e}}'"))
                 for k in ('COMISION', 'INTERES', 'LEY25413')}
    e['banco'] = uno(f"SELECT tesoreria_cuenta_id id, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {empresa} "
                     f"AND cuenta_tipo = 'BANCO' AND moneda_id = {e['moneda']} AND tabla_estado_registro_id = 1 ORDER BY tesoreria_cuenta_id LIMIT 1")
    e['caja'] = int(q("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {e} AND cuenta_tipo = 'CAJA' LIMIT 1"))
    e['perc_iva'] = uno(f"SELECT empresa_impuesto_config_id id, cont_cuenta_id FROM gestion__empresas_impuestos_config "
                        f"WHERE empresa_id = {empresa} AND impuesto_tipo_id = 2 AND tabla_estado_registro_id = 1 LIMIT 1")
    e['nro'] = proximo_nro_proveedor(pv, 960000) - 1
    return e


f6 = uno("SELECT fp.entidad_id, fp.sucursal_id, fp.deposito_id FROM gestion__facturas_proveedores fp "
         "WHERE fp.empresa_id = 6 AND fp.tabla_estado_registro_id = 5 ORDER BY fp.factura_proveedor_id LIMIT 1")
ENTORNOS = [entorno('gestion', 2, 57, 5765, 1, 1, 14),
            entorno('gestion_normal', 6, 557, int(f6['entidad_id']), int(f6['sucursal_id']), int(f6['deposito_id']), 15)]


def call(e, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    p.setdefault('pagina_idx', e['pagina'])
    return ajax('facturas_proveedores_ajax.php', p, method, modulo=e['modulo'], empresa_id=e['empresa'])


def linea_ng(producto_id, importe, iva_id):
    """Línea no gravada (impuesto ley 25.413), como la arma el front."""
    return dict(producto_id=producto_id, cantidad=1, precio_unitario=importe, precio_unitario_bruto=importe, precio_unitario_neto=importe,
                descuento_item_pct=0, descuento_general_pct=0, descuento_item=0, descuento_general=0, descuento=0,
                no_gravado=importe, exento=0, iva_alicuota_id=iva_id, iva_porcentaje=0, neto_gravado=0, iva_importe=0, total_linea=importe)


def alta(e, tipo, dets, imps=(), cuenta='', **extra):
    e['nro'] += 1
    neto = round(sum(d['neto_gravado'] for d in dets), 2)
    iva = round(sum(d['iva_importe'] for d in dets), 2)
    ng = round(sum(d['no_gravado'] for d in dets), 2)
    otros = round(sum(i['importe'] for i in imps), 2)
    r = call(e, dict(accion='agregar', comprobante_tipo_id=tipo, comprobante_pv=e['pv'], comprobante_nro=e['nro'], sucursal_id=e['sucursal'],
                     deposito_id=e['deposito'], entidad_id=e['prov'], f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
                     moneda_id=e['moneda'], tipo_cambio=1, condicion_pago_id='', orden_compra_id='', descuento_general_pct=0,
                     detalles=dets, impuestos_adicionales=list(imps), tesoreria_cuenta_debito_id=cuenta,
                     subtotal=neto, impuestos=iva, descuentos=0, no_gravado=ng, exento=0, total=round(neto + iva + ng + otros, 2), **extra))
    return r, (r.get('factura_proveedor_id') if isinstance(r, dict) else None)


def confirmar(e, fid):
    call(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar'))
    return call(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar'))


def fila(fid):
    return uno(f"SELECT f.tesoreria_cuenta_debito_id cuenta, f.total, f.no_gravado, f.impuestos, f.otros_impuestos, c.comprobante_id, "
               f"c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id comp_estado FROM gestion__facturas_proveedores f "
               f"JOIN gestion__comprobantes c ON c.comprobante_id = f.comprobante_id WHERE f.factura_proveedor_id = {fid}")


def saldo_cuenta(cid):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}"))


def op_de(comp_id):
    """OP que cancela el comprobante (imputación) con su medio de pago."""
    return uno(f"SELECT p.pago_id, p.importe_total, p.tabla_estado_registro_id estado, p.comprobante_id, mp.codigo medio, pm.tesoreria_cuenta_id cuenta "
               f"FROM gestion__comprobantes_imputaciones i JOIN gestion__pagos p ON p.comprobante_id = i.comp_origen_id "
               f"JOIN gestion__pagos_medios pm ON pm.pago_id = p.pago_id JOIN gestion__medios_pago mp ON mp.medio_pago_id = pm.medio_pago_id "
               f"WHERE i.comp_destino_id = {comp_id} AND i.tabla_estado_registro_id <> 2 ORDER BY p.pago_id DESC LIMIT 1")


def asiento_cuenta(comp_id, cuenta_id):
    a = val(f"SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = {comp_id} AND tabla_estado_registro_id <> 2 LIMIT 1")
    if not a:
        return None
    return num(val(f"SELECT IFNULL(SUM(importe_local),0) FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {a} AND cuenta_id = {cuenta_id}"))


def cuenta_compra(prod):
    return int(val(f"SELECT cont_cuenta_compra_id FROM gestion__productos WHERE producto_id = {prod}"))


def cuenta_iva(iva_id):
    return int(val(f"SELECT cont_cuenta_compra_id FROM gestion__impuestos__iva_alicuotas WHERE iva_alicuota_id = {iva_id}"))


for E in ENTORNOS:
    tag = f"{E['modulo']} (empresa {E['empresa']})"
    B = int(E['banco']['id'])

    # -----------------------------------------------------------------------------------------
    caso(f'Caso D1 - {tag}: Resumen bancario debitado de la cuenta, con IVA 21 % y 10,5 %, no gravado y percepción de IVA')
    r = call(E, dict(accion='obtener_comprobantes_tipos'), 'GET')
    rb = [t for t in r if int(t['comprobante_tipo_id']) == E['rb']] if isinstance(r, list) else []
    check(rb and int(rb[0]['requiere_debito_cuenta']) == 1 and rb[0]['signo'] == '+', 'la pantalla ofrece "Resumen bancario" que exige la cuenta', str(rb))
    check(val(f"SELECT cf.codigo FROM gestion__comprobantes_tipos ct JOIN gestion__comprobantes_fiscales cf "
              f"ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id WHERE ct.comprobante_tipo_id = {E['rb']}") == '039',
          'el tipo informa el código ARCA 039 (RG 1415)')
    r = call(E, dict(accion='obtener_cuentas_debito'), 'GET')
    check(ok_resp(r) and B in [int(c['tesoreria_cuenta_id']) for c in r['data']] and E['caja'] not in [int(c['tesoreria_cuenta_id']) for c in r['data']],
          'el combo de cuentas debitables trae los bancos y no la caja', err(r))

    dets = [linea(E['prod']['COMISION'], 1, 5000, iva_id=E['iva21']),                  # 5.000 + IVA 1.050
            linea(E['prod']['INTERES'], 1, 2000, iva=10.5, iva_id=E['iva105']),         # 2.000 + IVA 210
            linea_ng(E['prod']['LEY25413'], 1200, E['nograv'])]                        # 1.200 no gravado
    perc = [dict(empresa_impuesto_config_id=int(E['perc_iva']['id']), tipo_origen='manual', base_imponible=7000, alicuota=3,
                 importe=210, detalle_origen='SIM percepción IVA bancaria')]
    r, _ = alta(E, E['rb'], dets, perc)
    check(not ok_resp(r) and 'cuenta bancaria debitada' in err(r), 'sin cuenta debitada se rechaza', err(r))

    saldo0 = saldo_cuenta(B)
    stock0 = int(val("SELECT COUNT(*) FROM gestion__stock_movimientos"))
    r, fid = alta(E, E['rb'], dets, perc, cuenta=B)
    check(ok_resp(r) and fid and int(fila(fid)['cuenta']) == B, 'alta del resumen con la cuenta debitada', err(r))
    if fid:
        f = fila(fid)
        check(abs(num(f['total']) - 9670) < 0.01 and abs(num(f['no_gravado']) - 1200) < 0.01 and abs(num(f['impuestos']) - 1260) < 0.01,
              'total 9.670: neto 7.000, IVA 1.260, no gravado 1.200, percepción 210', str(f))
        r = call(E, dict(accion='obtener', factura_proveedor_id=fid), 'GET')
        check(isinstance(r, dict) and int(r.get('tesoreria_cuenta_debito_id') or 0) == B, 'obtener devuelve la cuenta debitada (para la pantalla)')
        r = confirmar(E, fid)
        f = fila(fid)
        check(ok_resp(r) and 'débito en' in err(r), 'confirmar avisa la OP por débito en cuenta', err(r))
        check(int(f['comp_estado']) == 40 and abs(num(f['importe_pendiente'])) < 0.01, 'resumen cancelado (40) sin saldo con el banco', str(f))
        op = op_de(f['comprobante_id'])
        check(op and op['medio'] == 'DEB_CTA' and int(op['cuenta']) == B and abs(num(op['importe_total']) - 9670) < 0.01 and int(op['estado']) == 5,
              'OP automática confirmada por 9.670 con medio DEB_CTA desde el banco', str(op))
        check(abs(saldo_cuenta(B) - (saldo0 - 9670)) < 0.01, f'banco -9.670 ({saldo0} -> {saldo_cuenta(B)})')
        check(int(val("SELECT COUNT(*) FROM gestion__stock_movimientos")) == stock0, 'no mueve stock (tipo sin stock, conceptos de servicio)')
        c = f['comprobante_id']
        esperado = [(cuenta_compra(E['prod']['COMISION']), 5000, 'gastos bancarios'), (cuenta_compra(E['prod']['INTERES']), 2000, 'intereses'),
                    (cuenta_compra(E['prod']['LEY25413']), 1200, 'impuesto ley 25.413'), (cuenta_iva(E['iva21']), 1050, 'IVA CF 21 %'),
                    (cuenta_iva(E['iva105']), 210, 'IVA CF 10,5 %'), (int(E['perc_iva']['cont_cuenta_id']), 210, 'percepción IVA')]
        for cta, imp, nom in esperado:
            v = asiento_cuenta(c, cta)
            check(v is not None and abs(v - imp) < 0.01, f'asiento del resumen: Debe {imp} en {nom}', f'{v}')
        if op and E['banco']['cont_cuenta_id']:
            v = asiento_cuenta(op['comprobante_id'], int(E['banco']['cont_cuenta_id']))
            check(v is not None and abs(v + 9670) < 0.01, 'asiento de la OP: Haber 9.670 en la cuenta contable del banco', f'{v}')

    # -----------------------------------------------------------------------------------------
    caso(f'Caso D2 - {tag}: factura A con débito automático opcional; sin cuenta queda a pagar')
    r, fd = alta(E, E['fa'], [linea(E['prod']['COMISION'], 1, 1000, iva_id=E['iva21'])], cuenta=B)
    check(ok_resp(r) and fd, 'factura A con cuenta debitada (débito automático de un servicio)', err(r))
    if fd:
        s0 = saldo_cuenta(B)
        r = confirmar(E, fd)
        f = fila(fd)
        check(ok_resp(r) and int(f['comp_estado']) == 40 and abs(saldo_cuenta(B) - (s0 - 1210)) < 0.01, 'confirmada, cancelada y banco -1.210', err(r) + str(f))
    r, fn = alta(E, E['fa'], [linea(E['prod']['COMISION'], 1, 1000, iva_id=E['iva21'])])
    if fn:
        r = confirmar(E, fn)
        f = fila(fn)
        check(ok_resp(r) and int(f['comp_estado']) == 5 and abs(num(f['importe_pendiente']) - 1210) < 0.01 and op_de(f['comprobante_id']) is None,
              'sin cuenta: confirmada con saldo 1.210 y sin OP', err(r) + str(f))
        # editar en Borrador: quitar la cuenta de un resumen no se permite
    r, fe = alta(E, E['fa'], [linea(E['prod']['COMISION'], 1, 500, iva_id=E['iva21'])], cuenta=B)
    if fe:
        r = call(E, dict(accion='editar', factura_proveedor_id=fe, comprobante_tipo_id=E['fa'], comprobante_pv=E['pv'], comprobante_nro=E['nro'],
                         sucursal_id=E['sucursal'], deposito_id=E['deposito'], entidad_id=E['prov'], f_emision=HOY, f_contabilidad=HOY,
                         f_vencimiento=HOY, moneda_id=E['moneda'], tipo_cambio=1, orden_compra_id='',
                         detalles=[linea(E['prod']['COMISION'], 1, 500, iva_id=E['iva21'])], impuestos_adicionales=[],
                         tesoreria_cuenta_debito_id='', subtotal=500, impuestos=105, descuentos=0, no_gravado=0, exento=0, total=605))
        check(ok_resp(r) and fila(fe)['cuenta'] is None, 'editar en Borrador sin cuenta la quita (pasa a pagarse con OP)', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso D3 - {tag}: rechazos de la cuenta debitada')
    det = [linea(E['prod']['COMISION'], 1, 100, iva_id=E['iva21'])]
    antes = int(val("SELECT IFNULL(MAX(factura_proveedor_id),0) FROM gestion__facturas_proveedores"))
    r, _ = alta(E, E['rb'], det, cuenta=E['caja'])
    check(not ok_resp(r) and 'cuenta bancaria activa' in err(r), 'rechaza una cuenta de CAJA', err(r))
    ajena = int(val(f"SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id <> {E['empresa']} AND cuenta_tipo = 'BANCO' LIMIT 1"))
    r, _ = alta(E, E['rb'], det, cuenta=ajena)
    check(not ok_resp(r), 'rechaza una cuenta bancaria de otra empresa', err(r))
    r, _ = alta(E, E['nca'], det, cuenta=B)
    check(not ok_resp(r) and 'débito en cuenta' in err(r), 'rechaza débito en cuenta en una nota de crédito', err(r))
    check(int(val("SELECT IFNULL(MAX(factura_proveedor_id),0) FROM gestion__facturas_proveedores")) == antes, 'no quedó nada a medias')
