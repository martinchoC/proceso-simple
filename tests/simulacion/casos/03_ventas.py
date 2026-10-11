from simlib import *

CLI = 313             # ABEJER WALTER - Responsable Inscripto
P1, P2 = 1529, 1878
CTX = {}


def estado(tabla, pk, id_):
    return int(val(f"SELECT tabla_estado_registro_id FROM {tabla} WHERE {pk}={id_}"))


def comp(tabla_id, rid):
    return uno(f"SELECT comprobante_id, tabla_estado_registro_id estado, comprobante_nro, importe_total, importe_pendiente "
               f"FROM gestion__comprobantes WHERE tabla_origen_id={tabla_id} AND registro_origen_id={rid}")


def saldo_cuenta(cid):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id={cid}"))


def accion_vf(fid, accion):
    return ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js=accion,
                empresa_idx=2, pagina_idx=56))


# ---------------------------------------------------------------------------------------------
caso('Caso 6 - Pedido de cliente -> remito con entrega parcial')
# stock inicial para poder entregar (ajuste de inventario por la página real)
s1, s2 = stock(P1), stock(P2)
dets = [linea(P1, 3, 1500), linea(P2, 2, 800)]
r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=1,
         punto_venta_id=1, entidad_id=CLI, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
         descuento_general_pct=0, detalles=dets, **totales(dets)))
ped = (r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None
check(ok_resp(r) and ped, 'alta de pedido', err(r))
if ped:
    CTX['pedido'] = ped
    r = ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido', empresa_idx=2, pagina_idx=91))
    check(ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 5, 'confirmar pedido -> 5', err(r))
    check(stock(P1) == s1, 'el pedido no mueve stock')

    r = ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', entidad_id=CLI, punto_venta_id=1, pedido_id=ped, empresa_idx=2), 'GET')
    pend = []
    if isinstance(r, list):
        for p in r:
            if 'venta_pedido_detalle_id' in p:
                pend.append(p)
            else:
                pend += p.get('detalles', p.get('lineas', []))
    pend = [l for l in pend if int(l.get('venta_pedido_id', ped)) == ped]
    check(len(pend) == 2, 'el remito ve las 2 líneas pendientes del pedido', str(r)[:300])
    if pend:
        por_prod = {int(l['producto_id']): l for l in pend}
        rdets = []
        for prod, cant in ((P1, 2), (P2, 2)):
            l = por_prod[prod]
            rdets.append(dict(venta_pedido_detalle_id=int(l['venta_pedido_detalle_id']), producto_id=prod, cantidad=cant,
                              precio_unitario_bruto=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')),
                              descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21))
        r = ajax('ventas_remitos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=88, punto_venta_id=1, comprobante_tipo_id=40,
                 entidad_id=CLI, f_emision=HOY, detalles=rdets))
        rem = (r.get('venta_remito_id') or r.get('id')) if isinstance(r, dict) else None
        check(ok_resp(r) and rem, 'alta de remito (2 de 3 y 2 de 2)', err(r))
        if rem:
            CTX['remito'] = rem
            r = ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js='confirmar', empresa_idx=2, pagina_idx=88))
            check(ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 13, 'confirmar remito -> 13 Pend. facturación', err(r))
            check(stock(P1) == s1 - 2 and stock(P2) == s2 - 2, f'stock -2 / -2 ({s1}->{stock(P1)}, {s2}->{stock(P2)})')
            ep = estado('gestion__ventas_pedidos', 'venta_pedido_id', ped)
            check(ep == 10, 'pedido -> 10 Entrega parcial', f'estado {ep}')
            check(num(val(f"SELECT cantidad_entregada FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id={ped} AND producto_id={P1}")) == 2,
                  'pedido: entregado 2 de P1')

# ---------------------------------------------------------------------------------------------
caso('Caso 7 - Factura de venta desde el remito (sin descontar stock dos veces)')
rem = CTX.get('remito')
if rem:
    r = ajax('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', entidad_id=CLI, venta_remito_id=rem, empresa_idx=2, pagina_idx=56), 'GET')
    lineas = []
    if isinstance(r, list):
        for x in r:
            lineas += x.get('detalles', x.get('lineas', [])) if isinstance(x, dict) and ('detalles' in x or 'lineas' in x) else [x]
    elif isinstance(r, dict):
        for x in r.get('data', r.get('remitos', [])):
            lineas += x.get('detalles', x.get('lineas', [x]))
    lineas = [l for l in lineas if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
    check(len(lineas) == 2, 'la factura ve las 2 líneas del remito', str(r)[:300])
    s1, s2 = stock(P1), stock(P2)
    fdets = [dict(producto_id=int(l['producto_id']), cantidad=num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
                  precio_unitario=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')), descuento_general_pct=0,
                  iva_alicuota_id=1, iva_porcentaje=21, venta_remito_detalle_id=int(l['venta_remito_detalle_id']),
                  venta_pedido_detalle_id=int(l.get('venta_pedido_detalle_id') or 0)) for l in lineas]
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=4, entidad_id=CLI, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM desde remito', detalles=fdets))
    fv = None
    if isinstance(r, dict):
        fv = r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')
    check(ok_resp(r) and fv, 'alta de Factura A desde remito', err(r))
    if fv:
        CTX['fv_remito'] = fv
        r = accion_vf(fv, 'confirma')
        check(ok_resp(r), 'confirmar factura (3 -> 4)', err(r))
        r = accion_vf(fv, 'aprobada')
        check(ok_resp(r) and estado('gestion__ventas_facturas', 'venta_factura_id', fv) == 5, 'aprobar factura -> 5 (ARCA deshabilitado)', err(r))
        check(stock(P1) == s1 and stock(P2) == s2, f'stock sin cambios (ya lo bajó el remito) ({s1}->{stock(P1)})')
        er = estado('gestion__ventas_remitos', 'venta_remito_id', rem)
        check(er == 12, 'remito -> 12 Facturado', f'estado {er}')
        cr = comp(81, rem)
        nro_rem = val(f"SELECT comprobante_nro FROM gestion__ventas_remitos WHERE venta_remito_id={rem}")
        check(cr and int(cr['estado']) == 12 and cr['comprobante_nro'] == nro_rem and num(nro_rem) > 0,
              'comprobante del remito sincronizado (12, con número)', str(cr))
        c = comp(80, fv)
        check(c and int(c['estado']) == 5 and num(c['comprobante_nro']) > 0 and num(c['importe_pendiente']) > 0,
              'comprobante numerado con saldo pendiente', str(c))
        check(c and val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id={c['comprobante_id']}") != '0', 'asiento contable generado')

# ---------------------------------------------------------------------------------------------
caso('Caso 8 - Facturas de venta directas (sin remito): consumidor final y cliente')


def factura_directa(pv, tipo, entidad, prod, cant, precio, **extra):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=pv, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=entidad, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM directa',
             detalles=[dict(producto_id=prod, cantidad=cant, precio_unitario=precio, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)],
             **extra))
    fid = None
    if isinstance(r, dict):
        fid = r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')
    return r, fid


def confirmar_vf(fid):
    r = accion_vf(fid, 'confirma')
    if ok_resp(r) and estado('gestion__ventas_facturas', 'venta_factura_id', fid) == 4:
        r = accion_vf(fid, 'aprobada')
    return r


s1 = stock(P1)
r, fcf = factura_directa(3, 9, 0, P1, 1, 2500, consumidor_final_nombre='Cliente mostrador SIM')
check(ok_resp(r) and fcf, 'alta de Factura X a consumidor final', err(r))
if fcf:
    r = confirmar_vf(fcf)
    check(estado('gestion__ventas_facturas', 'venta_factura_id', fcf) == 40, 'factura CF de contado -> 40 (cobro automático en caja)', err(r))
    check(stock(P1) == s1 - 1, f'stock -1 ({s1}->{stock(P1)})')
    r = ajax('ventas_facturas_ajax.php', dict(accion='cobro_factura', venta_factura_id=fcf, empresa_idx=2), 'GET')
    check(ok_resp(r) and not r['data'].get('puede_cobrar'), 'regla: la factura a consumidor final no se cobra con recibo')
s1 = stock(P1)
r, fcli = factura_directa(3, 9, CLI, P1, 2, 2500)
check(ok_resp(r) and fcli, 'alta de Factura X a cliente', err(r))
if fcli:
    CTX['fv_directa'] = fcli
    r = confirmar_vf(fcli)
    check(estado('gestion__ventas_facturas', 'venta_factura_id', fcli) == 5, 'factura cliente -> 5', err(r))
    check(stock(P1) == s1 - 2, f'stock -2 ({s1}->{stock(P1)})')
    c = comp(80, fcli)
    check(c and abs(num(c['importe_pendiente']) - num(c['importe_total'])) < 0.01, 'saldo pendiente = total', str(c))

# ---------------------------------------------------------------------------------------------
caso('Caso 9 - Cobranza total de la factura (recibo por la página de Cobranzas)')
fv = CTX.get('fv_remito')
c = comp(80, fv) if fv else None
if c:
    r = ajax('cobranzas_ajax.php', dict(accion='comprobantes_pendientes', entidad_id=CLI, empresa_idx=2), 'GET')
    ids = [int(x.get('comprobante_id', 0)) for x in r.get('data', [])] if ok_resp(r) else []
    check(int(c['comprobante_id']) in ids, 'la factura figura en pendientes de cobro', err(r))
    total = num(c['importe_pendiente'])
    caja0 = saldo_cuenta(1)
    r = ajax('cobranzas_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=105, entidad_id=CLI, sucursal_id=1,
             comprobante_pv=1, comprobante_tipo_id=24, f_emision=HOY, f_contabilidad=HOY, moneda_id=1, tipo_cambio=1,
             observaciones='SIM', medios=[dict(medio_pago_id=1, importe=total, importe_comprobante=total, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
             imputaciones=[dict(comprobante_destino_id=int(c['comprobante_id']), importe_imputado=total)]))
    cob = r.get('cobranza_id') if isinstance(r, dict) else None
    check(ok_resp(r) and cob, f'borrador de recibo por {total}', err(r))
    if cob:
        r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob, empresa_idx=2, pagina_idx=105))
        check(ok_resp(r), 'confirmar recibo', err(r))
        c2 = comp(80, fv)
        check(int(c2['estado']) == 40 and abs(num(c2['importe_pendiente'])) < 0.01, 'factura -> 40 Cancelado total', str(c2))
        check(estado('gestion__ventas_facturas', 'venta_factura_id', fv) == 40, 'la factura de venta acompaña el estado 40')
        check(abs(saldo_cuenta(1) - (caja0 + total)) < 0.01, f'caja sube {total} ({caja0} -> {saldo_cuenta(1)})')
        cr = comp(275, cob)
        check(cr and num(cr['comprobante_nro']) > 0, 'recibo numerado', str(cr))
        a = uno(f"SELECT f_asiento, descripcion FROM gestion__cont_asientos WHERE comprobante_id={cr['comprobante_id']}") if cr else None
        check(a and a['f_asiento'] == HOY and a['descripcion'] not in (None, '', '0'), 'asiento del recibo con fecha y descripción', str(a))

# ---------------------------------------------------------------------------------------------
caso('Caso 10 - Cobro parcial desde la factura (Medios de pago) y anulación del recibo')
fv = CTX.get('fv_directa')
c = comp(80, fv) if fv else None
if c:
    pend0 = num(c['importe_pendiente'])
    caja0 = saldo_cuenta(1)
    r = ajax('ventas_facturas_ajax.php', dict(accion='cobro_factura', venta_factura_id=fv, empresa_idx=2), 'GET')
    check(ok_resp(r) and r['data'].get('puede_cobrar'), 'la factura se puede cobrar', err(r))
    tipos = ajax('ventas_facturas_ajax.php', dict(accion='cobro_tipos_por_punto_venta', punto_venta_id=3, empresa_idx=2), 'GET')
    tipo_rec = (tipos.get('data') or [{}])[0].get('comprobante_tipo_id', 24) if ok_resp(tipos) else 24
    r = ajax('ventas_facturas_ajax.php', dict(accion='emitir_recibo', venta_factura_id=fv, empresa_idx=2, comprobante_pv=3,
             comprobante_tipo_id=tipo_rec, f_emision=HOY, observaciones='SIM parcial',
             medios=json.dumps([dict(medio_pago_id=1, importe=1000, tesoreria_cuenta_id=1)])))
    check(ok_resp(r), 'emitir recibo parcial de 1000', err(r))
    c2 = comp(80, fv)
    check(int(c2['estado']) == 39 and abs(num(c2['importe_pendiente']) - (pend0 - 1000)) < 0.01, 'factura -> 39 Cancelado parcial', str(c2))
    check(estado('gestion__ventas_facturas', 'venta_factura_id', fv) == 39, 'la factura de venta acompaña el 39')
    check(abs(saldo_cuenta(1) - (caja0 + 1000)) < 0.01, 'caja +1000')
    cob = val(f"SELECT c.cobranza_id FROM gestion__cobranzas c JOIN gestion__comprobantes_imputaciones i ON i.comp_origen_id=c.comprobante_id "
              f"WHERE i.comp_destino_id={c['comprobante_id']} ORDER BY c.cobranza_id DESC LIMIT 1")
    check(cob, 'recibo localizado por imputación')
    if cob:
        r = ajax('cobranzas_ajax.php', dict(accion='anular', id=int(cob), motivo='Simulación: recibo anulado', empresa_idx=2, pagina_idx=105))
        check(ok_resp(r), 'anular recibo', err(r))
        c3 = comp(80, fv)
        check(int(c3['estado']) == 5 and abs(num(c3['importe_pendiente']) - pend0) < 0.01, 'factura vuelve a 5 con saldo original', str(c3))
        check(estado('gestion__ventas_facturas', 'venta_factura_id', fv) == 5, 'la factura de venta vuelve a 5')
        check(abs(saldo_cuenta(1) - caja0) < 0.01, 'caja restituida')

with open(os.path.join(SALIDAS, 'ctx_ventas.json'), 'w') as f:
    json.dump(CTX, f)
