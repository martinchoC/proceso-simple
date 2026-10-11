from simlib import *

PROV = 5765
P1, P3 = 1529, 3500
NRO = [proximo_nro_proveedor(11, 930000) - 1]


def comp_fp(fid):
    return uno(f"SELECT c.comprobante_id, c.tabla_estado_registro_id estado, c.importe_total, c.importe_pendiente, c.signo "
               f"FROM gestion__facturas_proveedores f JOIN gestion__comprobantes c ON c.comprobante_id=f.comprobante_id WHERE f.factura_proveedor_id={fid}")


def fp_alta(tipo, lineas, **extra):
    NRO[0] += 1
    dets = [linea(p, c, pr) for p, c, pr in lineas]
    r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57, comprobante_tipo_id=tipo,
             comprobante_pv=11, comprobante_nro=NRO[0], sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY,
             f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='', orden_compra_id='',
             descuento_general_pct=0, detalles=dets, impuestos_adicionales=[], **totales(dets), **extra))
    return r, (r.get('factura_proveedor_id') if isinstance(r, dict) else None)


def fp_confirmar(fid):
    ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar', empresa_idx=2, pagina_idx=57))
    return ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar', empresa_idx=2, pagina_idx=57))


def caja():
    return num(val("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id=1"))


def asiento_cuenta(comprobante_id, cuenta_id):
    a = val(f"SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id={comprobante_id} AND tabla_estado_registro_id<>2 LIMIT 1")
    if not a:
        return None
    return num(val(f"SELECT IFNULL(SUM(importe_local),0) FROM gestion__cont_asientos_detalles WHERE cont_asiento_id={a} AND cuenta_id={cuenta_id}"))


def ultima_op():
    return uno("SELECT pago_id, importe_total, comprobante_nro, tabla_estado_registro_id estado, comprobante_id FROM gestion__pagos ORDER BY pago_id DESC LIMIT 1")


caso('Caso 26 - NC de proveedor asociada a una factura con saldo: se aplica sola con OP en $0')
r, fa = fp_alta(34, [(P1, 5, 800)])
r = fp_confirmar(fa)
ca = comp_fp(fa)
check(ok_resp(r) and ca and int(ca['estado']) == 5 and abs(num(ca['importe_pendiente']) - 4840) < 0.01, 'factura A proveedor confirmada (asiento ok) saldo 4840', err(r) + str(ca))
r = ajax('facturas_proveedores_ajax.php', dict(accion='comprobantes_asociables', entidad_id=PROV, comprobante_tipo_id=35, empresa_idx=2), 'GET')
check(ok_resp(r) and int(ca['comprobante_id']) in [x['comprobante_id'] for x in r['data']], 'la factura figura entre los asociables de la NC A', err(r))
s0 = stock(P1)
r, nc = fp_alta(35, [(P1, 2, 800)], comprobante_asociado_id=ca['comprobante_id'])
check(ok_resp(r) and nc, 'alta NC A proveedor asociada', err(r))
if nc:
    r = fp_confirmar(nc)
    cn = comp_fp(nc)
    check(ok_resp(r) and int(cn['estado']) == 40 and int(cn['signo']) == -1, 'NC confirmada, signo -, aplicada (40)', err(r) + str(cn))
    c2 = comp_fp(fa)
    check(int(c2['estado']) == 39 and abs(num(c2['importe_pendiente']) - 2904) < 0.01, 'factura queda en 39 con saldo 2904', str(c2))
    op = ultima_op()
    check(op and num(op['importe_total']) == 0 and int(op['estado']) == 5 and num(op['comprobante_nro']) > 0, 'OP en $0 confirmada y numerada', str(op))
    check(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id={op['comprobante_id']}") == '0', 'la OP en $0 no genera asiento')
    check(stock(P1) == s0 - 2, 'stock -2 por la devolución')
    p = asiento_cuenta(cn['comprobante_id'], 301)
    check(p is not None and abs(p - 1936) < 0.01, 'asiento de la NC: Proveedores al Debe 1936', str(p))
    check('aplicadas' in (r.get('message') or ''), 'el mensaje informa la aplicación', r.get('message', ''))

caso('Caso 27 - NC de proveedor sin saldo para aplicar y oferta al confirmar la siguiente factura')
r, fb = fp_alta(34, [(P3, 1, 1000)])
fp_confirmar(fb)
cb = comp_fp(fb)
r = ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=PROV, sucursal_id=1, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=4, importe=1210, importe_comprobante=1210, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=3)],
         imputaciones=[dict(comprobante_destino_id=int(cb['comprobante_id']), importe_imputado=1210)]))
ajax('pagos_ajax.php', dict(accion='confirmar', id=r.get('pago_id'), empresa_idx=2))
check(int(comp_fp(fb)['estado']) == 40, 'factura pagada (40)')
r, nc2 = fp_alta(35, [(P3, 1, 500)], comprobante_asociado_id=cb['comprobante_id'])
r = fp_confirmar(nc2)
cn2 = comp_fp(nc2)
check(int(cn2['estado']) == 5 and abs(num(cn2['importe_pendiente']) - 605) < 0.01, 'NC queda con saldo a favor 605', str(cn2) + err(r))
check('saldo a favor' in (r.get('message') or ''), 'el mensaje avisa que queda con saldo', r.get('message', ''))
r, fc = fp_alta(34, [(P3, 2, 1000)])
r = fp_confirmar(fc)
disp = r.get('notas_credito_disponibles') if isinstance(r, dict) else None
check(disp and int(cn2['comprobante_id']) in [n['comprobante_id'] for n in disp['notas']], 'la confirmación ofrece la NC con saldo', str(disp)[:200])
if disp:
    r = ajax('facturas_proveedores_ajax.php', dict(accion='aplicar_notas_credito', factura_proveedor_id=fc, empresa_idx=2,
             aplicaciones=json.dumps([dict(nc_comprobante_id=int(cn2['comprobante_id']), importe=0)])))
    check(ok_resp(r), 'aplicar la NC elegida', err(r))
    c = comp_fp(fc)
    check(int(c['estado']) == 39 and abs(num(c['importe_pendiente']) - (2420 - 605)) < 0.01, 'factura con saldo 1815 (39)', str(c))
    check(int(comp_fp(nc2)['estado']) == 40, 'NC aplicada (40)')

caso('Caso 28 - Devolución de dinero de una NC de proveedor (recibo en Cobranzas)')
r, fd = fp_alta(34, [(P3, 1, 300)])
fp_confirmar(fd)
cd_ = comp_fp(fd)
r = ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=PROV, sucursal_id=1, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=4, importe=363, importe_comprobante=363, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=3)],
         imputaciones=[dict(comprobante_destino_id=int(cd_['comprobante_id']), importe_imputado=363)]))
ajax('pagos_ajax.php', dict(accion='confirmar', id=r.get('pago_id'), empresa_idx=2))
r, nc3 = fp_alta(35, [(P3, 1, 300)], comprobante_asociado_id=cd_['comprobante_id'])
fp_confirmar(nc3)
cn3 = comp_fp(nc3)
check(int(cn3['estado']) == 5 and abs(num(cn3['importe_pendiente']) - 363) < 0.01, 'NC con saldo a favor 363', str(cn3))
r = ajax('cobranzas_ajax.php', dict(accion='maestros', empresa_idx=2, pagina_idx=105), 'GET')
check(ok_resp(r) and PROV in [int(x['entidad_id']) for x in r['data']['clientes']], 'Cobranzas lista al proveedor')
r = ajax('cobranzas_ajax.php', dict(accion='comprobantes_pendientes', entidad_id=PROV, empresa_idx=2), 'GET')
pend = {int(x['comprobante_id']): x for x in r.get('data', [])} if ok_resp(r) else {}
check(int(cn3['comprobante_id']) in pend and num(pend[int(cn3['comprobante_id'])]['importe_pendiente']) == 363, 'el recibo ofrece la NC con su saldo', str(list(pend.values()))[:200])
caja0 = caja()
r = ajax('cobranzas_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=105, entidad_id=PROV, sucursal_id=1, comprobante_pv=1,
         comprobante_tipo_id=24, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=1, importe=363, importe_comprobante=363, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
         imputaciones=[dict(comprobante_destino_id=int(cn3['comprobante_id']), importe_imputado=363)]))
cob = r.get('cobranza_id') if isinstance(r, dict) else None
r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob, empresa_idx=2, pagina_idx=105)) if cob else r
check(ok_resp(r), 'recibo de devolución confirmado', err(r))
check(int(comp_fp(nc3)['estado']) == 40, 'NC cancelada (40)')
check(abs(caja() - (caja0 + 363)) < 0.01, f'caja +363 ({caja0} -> {caja()})')
crc = val(f"SELECT comprobante_id FROM gestion__cobranzas WHERE cobranza_id={cob}") if cob else None
p = asiento_cuenta(crc, 301) if crc else None
check(p is not None and abs(p + 363) < 0.01, 'asiento del recibo: Proveedores al Haber 363', str(p))

caso('Caso 29 - NC X de proveedor con la configuración corregida')
s0 = stock(P1)
r, ncx = fp_alta(39, [(P1, 1, 200)])
r = fp_confirmar(ncx) if ncx else r
c = comp_fp(ncx) if ncx else None
check(ok_resp(r) and c and int(c['signo']) == -1, 'NC X confirmada con signo -', err(r) + str(c))
check(stock(P1) == s0 - 1, 'devuelve mercadería (stock -1)')
check(asiento_cuenta(c['comprobante_id'], 301) is not None, 'genera asiento (Proveedores al Debe)')
r, ndp = fp_alta(36, [(P3, 1, 400)])
r = fp_confirmar(ndp) if ndp else r
c = comp_fp(ndp) if ndp else None
p = asiento_cuenta(c['comprobante_id'], 301) if c else None
check(p is not None and abs(p + 484) < 0.01, 'ND A de proveedor genera asiento (Proveedores al Haber 484)', str(p) + err(r))
