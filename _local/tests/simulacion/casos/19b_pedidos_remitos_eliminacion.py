"""Eliminación de remitos y pedidos: el pendiente de entrega del pedido tiene que seguir a los remitos vivos.

Empresa 2 (módulo gestion, páginas 91 pedidos / 88 remitos). Un remito descuenta cantidad_entregada del pedido
desde que se da de alta en Borrador; eliminarlo (3 -> 6) o editarlo tiene que devolver ese pendiente. Un pedido
eliminado o en Borrador no se ofrece para remitir.
"""
from simlib import *

CLI = 313             # ABEJER WALTER - Responsable Inscripto
P1, P2 = 1529, 1878
TIPO_REM = 40


def estado(tabla, pk, id_):
    return int(val(f"SELECT tabla_estado_registro_id FROM {tabla} WHERE {pk}={id_}"))


def entregado(ped, prod):
    return num(val(f"SELECT cantidad_entregada FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id={ped} AND producto_id={prod}"))


def pendientes(ped):
    """Líneas que la pantalla de remitos ofrece para el pedido, por producto -> pendiente."""
    r = ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', entidad_id=CLI, punto_venta_id=1,
                                             pedido_id=ped, empresa_idx=2, pagina_idx=88), 'GET')
    lineas = r if isinstance(r, list) else []
    return {int(l['producto_id']): l for l in lineas if int(l.get('venta_pedido_id', 0)) == int(ped)}


def pedido(cantidades, confirmar=True):
    dets = [linea(p, c, 1500) for p, c in cantidades]
    r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=1,
             punto_venta_id=1, entidad_id=CLI, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, detalles=dets, **totales(dets)))
    ped = (r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None
    check(ok_resp(r) and ped, 'alta de pedido', err(r))
    if ped and confirmar:
        r = ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido', empresa_idx=2, pagina_idx=91))
        check(ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 5, 'pedido confirmado (5)', err(r))
    return int(ped or 0)


def remito(ped, cantidades, accion='agregar', rem_id=None):
    pend = pendientes(ped)
    dets = [dict(venta_pedido_detalle_id=int(pend[p]['venta_pedido_detalle_id']), producto_id=p, cantidad=c,
                 precio_unitario_bruto=num(pend[p]['precio_unitario_bruto']), descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)
            for p, c in cantidades if p in pend]
    p = dict(accion=accion, empresa_idx=2, pagina_idx=88, punto_venta_id=1, comprobante_tipo_id=TIPO_REM, entidad_id=CLI, f_emision=HOY, detalles=dets)
    if rem_id:
        p.update(venta_remito_id=rem_id, id=rem_id)
    r = ajax('ventas_remitos_ajax.php', p)
    return r, int(rem_id or (r.get('venta_remito_id') or r.get('id') or 0) if isinstance(r, dict) else 0)


def accion_rem(rem, accion):
    return ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js=accion, empresa_idx=2, pagina_idx=88))


# ---------------------------------------------------------------------------------------------
caso('E1 - Remito en Borrador eliminado: el pedido recupera el pendiente')
s1 = stock(P1)
ped = pedido([(P1, 3), (P2, 2)])
if ped:
    r, rem = remito(ped, [(P1, 2), (P2, 2)])
    check(ok_resp(r) and rem, 'alta de remito en Borrador (2 de 3 y 2 de 2)', err(r))
    check(entregado(ped, P1) == 2 and entregado(ped, P2) == 2, 'el Borrador ya descuenta del pedido (entregado 2 / 2)',
          f'{entregado(ped, P1)} / {entregado(ped, P2)}')
    pend = pendientes(ped)
    check(P1 in pend and num(pend[P1]['pendiente']) == 1 and P2 not in pend, 'pendientes: queda 1 de P1 y nada de P2', str({k: v['pendiente'] for k, v in pend.items()}))
    if rem:
        r = accion_rem(rem, 'eliminar')
        check(ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 6, 'eliminar remito (3 -> 6)', err(r))
        check(entregado(ped, P1) == 0 and entregado(ped, P2) == 0, 'el pedido vuelve a entregado 0 / 0', f'{entregado(ped, P1)} / {entregado(ped, P2)}')
        pend = pendientes(ped)
        check(num(pend.get(P1, {}).get('pendiente', 0)) == 3 and num(pend.get(P2, {}).get('pendiente', 0)) == 2,
              'pendientes: vuelven 3 de P1 y 2 de P2', str({k: v['pendiente'] for k, v in pend.items()}))
        check(estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 5, 'el pedido sigue confirmado (5)',
              f"estado {estado('gestion__ventas_pedidos', 'venta_pedido_id', ped)}")
        check(stock(P1) == s1, 'stock sin cambios (el Borrador no lo había movido)')
        c = uno(f"SELECT tabla_estado_registro_id e FROM gestion__comprobantes WHERE tabla_origen_id = 81 AND registro_origen_id = {rem}")
        check(c is None or c['e'] == '6', 'espejo del remito en 6 (o sin espejo)', str(c))
    # Se puede volver a remitir todo
    r, rem2 = remito(ped, [(P1, 3), (P2, 2)])
    check(ok_resp(r) and rem2, 'un remito nuevo por el total del pedido se acepta', err(r))
    if rem2:
        r = accion_rem(rem2, 'confirmar')
        check(ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 11, 'al confirmarlo el pedido queda con entrega total (11)', err(r))
        check(not pendientes(ped), 'pendientes: nada', str(pendientes(ped)))

# ---------------------------------------------------------------------------------------------
caso('E2 - Remito en Borrador editado: el pedido toma la cantidad nueva')
ped = pedido([(P1, 5)])
if ped:
    r, rem = remito(ped, [(P1, 4)])
    check(ok_resp(r) and rem and entregado(ped, P1) == 4, 'Borrador por 4 de 5', err(r) + f' entregado {entregado(ped, P1)}')
    if rem:
        r, _ = remito(ped, [(P1, 1)], accion='editar', rem_id=rem)
        check(ok_resp(r) and entregado(ped, P1) == 1, 'editado a 1: entregado 1', err(r) + f' entregado {entregado(ped, P1)}')
        check(num(pendientes(ped).get(P1, {}).get('pendiente', 0)) == 4, 'pendientes: 4', str(pendientes(ped)))
        accion_rem(rem, 'eliminar')
        check(entregado(ped, P1) == 0, 'y al eliminarlo, entregado 0', f'{entregado(ped, P1)}')

# ---------------------------------------------------------------------------------------------
caso('E3 - Entrega parcial confirmada + Borrador por el resto eliminado')
ped = pedido([(P1, 4)])
if ped:
    r, rem = remito(ped, [(P1, 1)])
    accion_rem(rem, 'confirmar')
    check(estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 10, 'remito confirmado por 1 de 4: pedido en entrega parcial (10)')
    r, rem_b = remito(ped, [(P1, 3)])
    check(ok_resp(r) and entregado(ped, P1) == 4, 'Borrador por los 3 restantes: entregado 4', err(r))
    if rem_b:
        accion_rem(rem_b, 'eliminar')
        check(entregado(ped, P1) == 1, 'eliminado el Borrador: entregado vuelve a 1 (el confirmado)', f'{entregado(ped, P1)}')
        check(num(pendientes(ped).get(P1, {}).get('pendiente', 0)) == 3, 'pendientes: 3', str(pendientes(ped)))
        check(estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 10, 'el pedido sigue en entrega parcial (10)')
    r = accion_rem(rem, 'eliminar')
    check(not ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 13 and entregado(ped, P1) == 1,
          'un remito confirmado no se elimina y no toca el pedido', err(r))

# ---------------------------------------------------------------------------------------------
caso('E4 - Pedidos en Borrador o eliminados no se ofrecen para remitir')
ped_b = pedido([(P1, 2)], confirmar=False)
if ped_b:
    check(not pendientes(ped_b), 'pedido en Borrador: no aparece en pendientes', str(pendientes(ped_b))[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped_b, accion_js='eliminar', empresa_idx=2, pagina_idx=91))
    check(ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped_b) == 6, 'eliminar pedido en Borrador (3 -> 6)', err(r))
    check(not pendientes(ped_b), 'pedido eliminado: no aparece en pendientes', str(pendientes(ped_b))[:200])
    # Aunque la pantalla no lo ofrezca, el alta del remito lo rechaza (request armado a mano)
    vpd = int(val(f"SELECT venta_pedido_detalle_id FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {ped_b} LIMIT 1"))
    r = ajax('ventas_remitos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=88, punto_venta_id=1, comprobante_tipo_id=TIPO_REM,
             entidad_id=CLI, f_emision=HOY, detalles=[dict(venta_pedido_detalle_id=vpd, producto_id=P1, cantidad=1, precio_unitario_bruto=1500,
                                                          descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
    check(not ok_resp(r) and 'eliminado' in err(r) and entregado(ped_b, P1) == 0, 'un remito contra el pedido eliminado se rechaza', err(r))
    c = uno(f"SELECT tabla_estado_registro_id e FROM gestion__comprobantes WHERE tabla_origen_id = 82 AND registro_origen_id = {ped_b}")
    check(c is None or c['e'] == '6', 'espejo del pedido en 6 (o sin espejo)', str(c))
ped_c = pedido([(P1, 2)])
if ped_c:
    r = ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped_c, accion_js='eliminar', empresa_idx=2, pagina_idx=91))
    check(not ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped_c) == 5, 'un pedido confirmado no se elimina', err(r))

# ---------------------------------------------------------------------------------------------
caso('E5 - Anular un remito confirmado (13 -> 38): vuelve al pedido como pendiente y devuelve el stock')
s1 = stock(P1)
ped = pedido([(P1, 3)])
if ped:
    r, rem = remito(ped, [(P1, 3)])
    r = accion_rem(rem, 'confirmar')
    check(ok_resp(r) and estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 11 and stock(P1) == s1 - 3,
          'remito confirmado por el total: pedido entregado (11) y stock -3', err(r) + f' stock {s1}->{stock(P1)}')
    r = accion_rem(rem, 'anular')
    check(ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 38, 'anular remito (13 -> 38)', err(r))
    check(entregado(ped, P1) == 0, 'el pedido vuelve a entregado 0', f'{entregado(ped, P1)}')
    check(num(pendientes(ped).get(P1, {}).get('pendiente', 0)) == 3, 'pendientes de entrega: vuelven los 3', str(pendientes(ped)))
    check(estado('gestion__ventas_pedidos', 'venta_pedido_id', ped) == 5, 'el pedido vuelve a confirmado (5)',
          f"estado {estado('gestion__ventas_pedidos', 'venta_pedido_id', ped)}")
    check(stock(P1) == s1, f'stock devuelto ({s1} -> {stock(P1)})')
    c = uno(f"SELECT tabla_estado_registro_id e, comprobante_nro n FROM gestion__comprobantes WHERE tabla_origen_id = 81 AND registro_origen_id = {rem}")
    check(c is not None and c['e'] == '38' and num(c['n']) > 0, 'espejo del remito en 38, con su número', str(c))
    r = accion_rem(rem, 'anular')
    check(not ok_resp(r), 'un remito anulado no se vuelve a anular', err(r))

# ---------------------------------------------------------------------------------------------
caso('E6 - Un remito con líneas en una factura (aunque sea borrador) no se anula')
ped = pedido([(P1, 2)])
if ped:
    r, rem = remito(ped, [(P1, 2)])
    accion_rem(rem, 'confirmar')
    r = ajax('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', entidad_id=CLI, venta_remito_id=rem, empresa_idx=2, pagina_idx=56), 'GET')
    lineas = [l for l in (r if isinstance(r, list) else []) if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
    fdets = [dict(producto_id=int(l['producto_id']), cantidad=num(l['cantidad_pendiente_facturar']), precio_unitario=num(l['precio_unitario_bruto']),
                  descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21, venta_remito_detalle_id=int(l['venta_remito_detalle_id']),
                  venta_pedido_detalle_id=int(l.get('venta_pedido_detalle_id') or 0)) for l in lineas]
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=4, entidad_id=CLI, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM anular remito', detalles=fdets))
    fv = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    check(ok_resp(r) and fv, 'factura en Borrador con las líneas del remito', err(r))
    er = estado('gestion__ventas_remitos', 'venta_remito_id', rem)
    if er == 13:
        r = accion_rem(rem, 'anular')
        check(not ok_resp(r) and 'factura' in err(r) and entregado(ped, P1) == 2, 'anular se rechaza y no toca el pedido', err(r))
    else:
        r = accion_rem(rem, 'anular')
        check(not ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == er,
              f'el remito quedó en {er} por el borrador: Anular no se ofrece ahí', err(r))
    if fv:
        ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fv, accion_js='eliminar', empresa_idx=2, pagina_idx=56))
        er = estado('gestion__ventas_remitos', 'venta_remito_id', rem)
        r = accion_rem(rem, 'anular')
        check(ok_resp(r) and estado('gestion__ventas_remitos', 'venta_remito_id', rem) == 38 and entregado(ped, P1) == 0,
              f'eliminada la factura (remito en {er}), el remito se anula', err(r))
