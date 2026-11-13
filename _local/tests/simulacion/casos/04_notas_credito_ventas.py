from simlib import *

CLI = 313
P1, P3 = 1529, 3500
CTX = {}


def estado_vf(fid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id={fid}"))


def comp_vf(fid):
    return uno(f"SELECT c.comprobante_id, c.tabla_estado_registro_id estado, c.importe_total, c.importe_pendiente, c.signo "
               f"FROM gestion__ventas_facturas vf JOIN gestion__comprobantes c ON c.comprobante_id=vf.comprobante_id WHERE vf.venta_factura_id={fid}")


def caja():
    return num(val("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id=1"))


def vf_alta(pv, tipo, entidad, lineas, **extra):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=pv, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=entidad, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM NC',
             detalles=[dict(producto_id=p, cantidad=c, precio_unitario=pr, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)
                       for p, c, pr in lineas], **extra))
    fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    return r, fid


def vf_confirmar(fid):
    """confirma (3->4) y aprobada (4->5); devuelve la última respuesta."""
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma', empresa_idx=2, pagina_idx=56))
    if ok_resp(r) and estado_vf(fid) == 4:
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    return r


def ultimo_recibo():
    return uno("SELECT c.cobranza_id, c.importe_total, c.comprobante_nro, c.tabla_estado_registro_id estado, c.observaciones, c.comprobante_id, c.entidad_id "
               "FROM gestion__cobranzas c ORDER BY c.cobranza_id DESC LIMIT 1")


def movs_desde(m0):
    return int(val(f"SELECT COUNT(*) FROM gestion__tesoreria_movimientos WHERE tesoreria_movimiento_id > {m0}"))


def max_mov():
    return int(val("SELECT IFNULL(MAX(tesoreria_movimiento_id),0) FROM gestion__tesoreria_movimientos"))


caso('Caso 21 - NC asociada a una factura con saldo: se aplica sola con recibo en $0')
r, fa = vf_alta(1, 4, CLI, [(P1, 3, 1000)])
vf_confirmar(fa)
ca = comp_vf(fa)
check(ca and int(ca['estado']) == 5 and abs(num(ca['importe_pendiente']) - 3630) < 0.01, 'factura A confirmada con saldo 3630', str(ca))
r = ajax('ventas_facturas_ajax.php', dict(accion='comprobantes_asociables', entidad_id=CLI, comprobante_tipo_id=7, empresa_idx=2), 'GET')
check(ok_resp(r) and int(ca['comprobante_id']) in [x['comprobante_id'] for x in r['data']], 'la factura figura entre los comprobantes asociables de la NC A', err(r))
m0, caja0 = max_mov(), caja()
r, nc = vf_alta(1, 7, CLI, [(P1, 1, 1000)], comprobante_asociado_id=ca['comprobante_id'])
check(ok_resp(r) and nc, 'alta NC A asociada a la factura', err(r))
if nc:
    check(val(f"SELECT comprobante_asociado_id FROM gestion__ventas_facturas WHERE venta_factura_id={nc}") == ca['comprobante_id'], 'guarda el comprobante asociado')
    r = vf_confirmar(nc)
    check(ok_resp(r) and estado_vf(nc) in (40,), 'NC confirmada y aplicada (40 Cancelado total)', err(r) + f' estado {estado_vf(nc)}')
    c2 = comp_vf(fa)
    check(abs(num(c2['importe_pendiente']) - 2420) < 0.01 and int(c2['estado']) == 39, 'factura queda en 39 con saldo 2420', str(c2))
    check(estado_vf(fa) == 39, 'la factura de venta acompaña el 39')
    rec = ultimo_recibo()
    check(rec and num(rec['importe_total']) == 0 and int(rec['estado']) == 5 and num(rec['comprobante_nro']) > 0,
          'recibo en $0 confirmado y numerado', str(rec))
    check(movs_desde(m0) == 0 and abs(caja() - caja0) < 0.01, 'la aplicación no mueve caja', f'm0={m0} max={max_mov()} caja {caja0}->{caja()}')
    check(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id={rec['comprobante_id']}") == '0', 'el recibo en $0 no genera asiento')
    check('ok' if r.get('message', '').find('aplicadas') >= 0 else None, 'el mensaje informa la aplicación', r.get('message', ''))
    # Detalle del recibo en $0: la factura en positivo y la NC en negativo
    d = ajax('cobranzas_ajax.php', dict(accion='obtener', id=rec['cobranza_id'], empresa_idx=2, pagina_idx=105), 'GET')
    imps = (d.get('data') or {}).get('imputaciones', []) if isinstance(d, dict) else []
    cn = comp_vf(nc)
    por_comp = {int(i['comprobante_id']): num(i['importe_imputado']) for i in imps}
    check(len(imps) == 2 and abs(por_comp.get(int(ca['comprobante_id']), 0) - 1210) < 0.01 and abs(por_comp.get(int(cn['comprobante_id']), 0) + 1210) < 0.01,
          'el recibo detalla la factura (+1210) y la NC (-1210)', str([(i.get('comprobante_tipo_codigo'), i.get('importe_imputado')) for i in imps]))
    check(abs(sum(num(i['importe_imputado']) for i in imps)) < 0.01, 'el detalle suma 0')
CTX['fa'] = fa

caso('Caso 22 - NC asociada a una factura sin saldo: queda con saldo a favor')
r, fb = vf_alta(1, 4, CLI, [(P1, 1, 1000)])
vf_confirmar(fb)
cb = comp_vf(fb)
r = ajax('cobranzas_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=105, entidad_id=CLI, sucursal_id=1, comprobante_pv=1,
         comprobante_tipo_id=24, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=1, importe=1210, importe_comprobante=1210, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
         imputaciones=[dict(comprobante_destino_id=int(cb['comprobante_id']), importe_imputado=1210)]))
ajax('cobranzas_ajax.php', dict(accion='confirmar', id=r.get('cobranza_id'), empresa_idx=2, pagina_idx=105))
check(int(comp_vf(fb)['estado']) == 40, 'factura B-base cobrada (40)')
r, nc2 = vf_alta(1, 7, CLI, [(P1, 1, 1000)], comprobante_asociado_id=cb['comprobante_id'])
r = vf_confirmar(nc2) if nc2 else r
c = comp_vf(nc2) if nc2 else None
check(c and int(c['estado']) == 5 and abs(num(c['importe_pendiente']) - 1210) < 0.01, 'NC queda confirmada con saldo 1210', str(c) + ' ' + err(r))
check('saldo a favor' in (r.get('message') or ''), 'el mensaje avisa que queda con saldo a favor', r.get('message', ''))
CTX['nc2'] = nc2

caso('Caso 23 - Factura nueva: la pantalla ofrece imputar la NC con saldo')
r, fc = vf_alta(1, 4, CLI, [(P1, 2, 1000)])
r = vf_confirmar(fc)
disp = r.get('notas_credito_disponibles') if isinstance(r, dict) else None
nc2_comp = int(comp_vf(CTX['nc2'])['comprobante_id']) if CTX.get('nc2') else None
check(disp and nc2_comp in [n['comprobante_id'] for n in disp['notas']], 'la confirmación devuelve la NC con saldo para ofrecerla', str(disp)[:300])
if disp:
    r = ajax('ventas_facturas_ajax.php', dict(accion='aplicar_notas_credito', venta_factura_id=fc, empresa_idx=2,
             aplicaciones=json.dumps([dict(nc_comprobante_id=nc2_comp, importe=0)])))
    check(ok_resp(r), 'aplicar la NC elegida', err(r))
    c = comp_vf(fc)
    check(abs(num(c['importe_pendiente']) - (2420 - 1210)) < 0.01 and int(c['estado']) == 39, 'factura con saldo 1210 (39)', str(c))
    check(estado_vf(CTX['nc2']) == 40, 'la NC queda aplicada (40)')
    rec = ultimo_recibo()
    check(rec and num(rec['importe_total']) == 0, 'recibo en $0 de la aplicación', str(rec))
    r = ajax('ventas_facturas_ajax.php', dict(accion='aplicar_notas_credito', venta_factura_id=fc, empresa_idx=2,
             aplicaciones=json.dumps([dict(nc_comprobante_id=nc2_comp, importe=0)])))
    check(not ok_resp(r), 'no deja aplicar dos veces la misma NC', err(r))

caso('Caso 24 - Devolución de dinero de una NC con saldo (OP al cliente)')
r, fd = vf_alta(1, 4, CLI, [(P3, 1, 500)])
vf_confirmar(fd)
cd_ = comp_vf(fd)
r = ajax('cobranzas_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=105, entidad_id=CLI, sucursal_id=1, comprobante_pv=1,
         comprobante_tipo_id=24, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=1, importe=605, importe_comprobante=605, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
         imputaciones=[dict(comprobante_destino_id=int(cd_['comprobante_id']), importe_imputado=605)]))
ajax('cobranzas_ajax.php', dict(accion='confirmar', id=r.get('cobranza_id'), empresa_idx=2, pagina_idx=105))
r, nc3 = vf_alta(1, 7, CLI, [(P3, 1, 500)], comprobante_asociado_id=cd_['comprobante_id'])
vf_confirmar(nc3)
c = comp_vf(nc3)
check(c and int(c['estado']) == 5 and abs(num(c['importe_pendiente']) - 605) < 0.01, 'NC con saldo a favor 605', str(c))
r = ajax('pagos_ajax.php', dict(accion='maestros', empresa_idx=2), 'GET')
check(ok_resp(r) and CLI in [int(x['entidad_id']) for x in r['data']['proveedores']], 'Órdenes de Pago lista al cliente')
r = ajax('pagos_ajax.php', dict(accion='comprobantes_pendientes', entidad_id=CLI, empresa_idx=2), 'GET')
pend = {int(x['comprobante_id']): x for x in r.get('data', [])} if ok_resp(r) else {}
check(int(c['comprobante_id']) in pend and num(pend[int(c['comprobante_id'])]['importe_pendiente']) == 605, 'la OP ofrece la NC con su saldo positivo', str(list(pend.values()))[:300])
caja0 = caja()
r = ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=CLI, sucursal_id=1, f_emision=HOY, moneda_id=1, tipo_cambio=1,
         medios=[dict(medio_pago_id=1, importe=605, importe_comprobante=605, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
         imputaciones=[dict(comprobante_destino_id=int(c['comprobante_id']), importe_imputado=605)]))
pago = r.get('pago_id') if isinstance(r, dict) else None
r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2)) if pago else r
check(ok_resp(r), 'OP de devolución confirmada', err(r))
check(int(comp_vf(nc3)['estado']) == 40 and estado_vf(nc3) == 40, 'la NC queda cancelada (40)')
check(abs(caja() - (caja0 - 605)) < 0.01, f'caja -605 ({caja0} -> {caja()})')
cop = val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id={pago}") if pago else None
deud = val(f"SELECT SUM(d.importe_local) FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id=a.cont_asiento_id "
           f"WHERE a.comprobante_id={cop} AND d.cuenta_id=111") if cop else None
check(deud is not None and abs(num(deud) - 605) < 0.01, 'asiento de la OP: Deudores por Ventas al Debe 605', str(deud))

caso('Caso 25 - Venta de contado a consumidor final: recibo automático y caja')
caja0, m0 = caja(), max_mov()
r, fcf = vf_alta(1, 12, 0, [(P1, 1, 1000)], consumidor_final_nombre='Mostrador SIM')
r = vf_confirmar(fcf) if fcf else r
c = comp_vf(fcf) if fcf else None
check(c and int(c['estado']) == 40 and abs(num(c['importe_pendiente'])) < 0.01, 'factura B a CF queda cancelada (40)', str(c) + ' ' + err(r))
check(fcf and estado_vf(fcf) == 40, 'la factura de venta acompaña el 40')
check(abs(caja() - (caja0 + 1210)) < 0.01 and movs_desde(m0) == 1, f'ingreso a caja 1210 ({caja0} -> {caja()})')
rec = ultimo_recibo()
check(rec and rec['entidad_id'] is None and num(rec['importe_total']) == 1210 and int(rec['estado']) == 5, 'recibo de consumidor final confirmado', str(rec))
a = uno(f"SELECT COUNT(*) n FROM gestion__cont_asientos WHERE comprobante_id={rec['comprobante_id']}") if rec else None
check(a and a['n'] == '1', 'asiento del recibo (Caja contra Deudores)')
r = ajax('cobranzas_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=105), 'GET')
lista = r.get('data', r) if isinstance(r, dict) else r
check(isinstance(lista, list) and any(int(x.get('cobranza_id', x.get('id', 0))) == int(rec['cobranza_id']) for x in lista), 'el recibo aparece en el listado de Cobranzas', str(r)[:200])

caso('Caso 21b - Anular el recibo en $0 de la aplicación: la factura y la NC recuperan su saldo')
r, fx = vf_alta(1, 4, CLI, [(P1, 2, 1000)])
vf_confirmar(fx)
cx = comp_vf(fx)
r, ncx = vf_alta(1, 7, CLI, [(P1, 1, 1000)], comprobante_asociado_id=cx['comprobante_id'])
vf_confirmar(ncx)
rec = ultimo_recibo()
check(abs(num(comp_vf(fx)['importe_pendiente']) - 1210) < 0.01 and estado_vf(ncx) == 40, 'NC aplicada: factura con saldo 1210, NC cancelada (40)')
r = ajax('cobranzas_ajax.php', dict(accion='anular', id=rec['cobranza_id'], motivo='SIM anular aplicación', empresa_idx=2, pagina_idx=105))
check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id={rec['cobranza_id']}")) == 38, 'recibo en $0 anulado', err(r))
c1, c2 = comp_vf(fx), comp_vf(ncx)
check(abs(num(c1['importe_pendiente']) - 2420) < 0.01 and int(c1['estado']) == 5, 'la factura vuelve a saldo 2420 (5)', str(c1))
check(abs(num(c2['importe_pendiente']) - 1210) < 0.01 and int(c2['estado']) == 5 and estado_vf(ncx) == 5, 'la NC vuelve a saldo 1210 a favor (5)', str(c2) + f' vf {estado_vf(ncx)}')
