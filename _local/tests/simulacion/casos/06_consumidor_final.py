from simlib import *

P1 = 1529
PROV = 5765


def estado_vf(fid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id={fid}"))


def comp_vf(fid):
    return uno(f"SELECT c.comprobante_id, c.tabla_estado_registro_id estado, c.importe_total, c.importe_pendiente, c.signo "
               f"FROM gestion__ventas_facturas vf JOIN gestion__comprobantes c ON c.comprobante_id=vf.comprobante_id WHERE vf.venta_factura_id={fid}")


def caja():
    return num(val("SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id=1"))


def vf_cf(tipo, cant, precio):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=0, consumidor_final_nombre='Mostrador SIM', f_emision=HOY, f_vto=HOY, moneda_id=1,
             tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, observaciones='SIM CF',
             detalles=[dict(producto_id=P1, cantidad=cant, precio_unitario=precio, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
    fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    if fid:
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma', empresa_idx=2, pagina_idx=56))
        if ok_resp(r) and estado_vf(fid) == 4:
            r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    return r, fid


caso('Caso 30 - Nota de crédito a consumidor final: devolución automática en efectivo')
r = ajax('ventas_facturas_ajax.php', dict(accion='tipos_por_punto_venta', punto_venta_id=1, entidad_id=0, empresa_idx=2, pagina_idx=56), 'GET')
tipos = r.get('data', r) if isinstance(r, dict) else r
ids = [int(t['comprobante_tipo_id']) for t in tipos] if isinstance(tipos, list) else []
check(13 in ids, 'el PV ofrece NC B para consumidor final', str(ids))
r, fb = vf_cf(12, 2, 1000)
check(fb and estado_vf(fb) == 40, 'venta B de contado cobrada (40)', err(r))
s0, caja0 = stock(P1), caja()
r, nc = vf_cf(13, 1, 1000)
c = comp_vf(nc) if nc else None
check(ok_resp(r) and c and int(c['estado']) == 40 and abs(num(c['importe_pendiente'])) < 0.01, 'NC B a CF confirmada y cancelada (40)', err(r) + str(c))
check(nc and estado_vf(nc) == 40, 'la NC de venta acompaña el 40')
check(abs(caja() - (caja0 - 1210)) < 0.01, f'caja -1210 ({caja0} -> {caja()})')
check(stock(P1) == s0 + 1, 'la devolución vuelve el producto al stock (+1)')
check('Devolución en efectivo' in (r.get('message') or ''), 'el mensaje informa la devolución', r.get('message', ''))
op = uno("SELECT pago_id, entidad_id, importe_total, tabla_estado_registro_id estado, comprobante_id FROM gestion__pagos ORDER BY pago_id DESC LIMIT 1")
check(op and op['entidad_id'] is None and num(op['importe_total']) == 1210 and int(op['estado']) == 5, 'OP de devolución sin entidad confirmada', str(op))
d = val(f"SELECT SUM(d.importe_local) FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id=a.cont_asiento_id "
        f"WHERE a.comprobante_id={op['comprobante_id']} AND d.cuenta_id=111") if op else None
check(d is not None and abs(num(d) - 1210) < 0.01, 'asiento de la OP: Deudores por Ventas al Debe 1210', str(d))
r = ajax('pagos_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=106), 'GET')
lista = r.get('data', r) if isinstance(r, dict) else r
check(isinstance(lista, list) and any('Consumidor Final' in json.dumps(x, ensure_ascii=False) for x in lista), 'la OP aparece en el listado como Consumidor Final', str(r)[:200])

caso('Caso 31 - Factura de proveedor que no puede generar asiento: no se confirma y explica por qué')
prod = int(val("SELECT producto_id FROM gestion__productos WHERE empresa_id=2 AND tabla_estado_registro_id=1 AND IFNULL(cont_cuenta_id,0)=0 "
               "AND IFNULL(cont_cuenta_compra_id,0)=0 AND producto_id NOT IN (1529,1878,3500) ORDER BY producto_id LIMIT 1"))
tipo_original = val(f"SELECT producto_tipo_id FROM gestion__productos WHERE producto_id={prod}")
# Servicio: sin cuenta de compra por defecto. Se restaura al final del caso (también corre con --base-local).
sql(f"UPDATE gestion__productos SET producto_tipo_id=2 WHERE producto_id={prod}")
dets = [linea(prod, 1, 1000)]
r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57, comprobante_tipo_id=34, comprobante_pv=12,
         comprobante_nro=proximo_nro_proveedor(12, 940000), sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
         moneda_id=1, tipo_cambio=1, condicion_pago_id='', orden_compra_id='', descuento_general_pct=0, detalles=dets,
         impuestos_adicionales=[], **totales(dets)))
fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar', empresa_idx=2, pagina_idx=57))
s0 = stock(prod)
r = ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar', empresa_idx=2, pagina_idx=57))
msg = err(r)
check(not ok_resp(r), 'la confirmación se rechaza')
check('asiento contable' in msg and 'cuenta contable de compra del producto' in msg and 'tipo de producto' in msg,
      'el motivo dice qué falta y dónde cargarlo', msg)
check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id={fid}")) == 4, 'la factura queda en Pendiente de validación (4)')
check(stock(prod) == s0, 'no movió stock')
check(val(f"SELECT COUNT(*) FROM gestion__cont_asientos a JOIN gestion__facturas_proveedores f ON f.comprobante_id=a.comprobante_id WHERE f.factura_proveedor_id={fid}") == '0', 'no generó asiento')
print('   motivo mostrado:', msg)
sql(f"UPDATE gestion__productos SET producto_tipo_id={tipo_original} WHERE producto_id={prod}")
check(val(f"SELECT producto_tipo_id FROM gestion__productos WHERE producto_id={prod}") == tipo_original, 'configuración del producto restaurada')

caso('Caso 30b - Contado a consumidor final con los medios de pago elegidos al aprobar')
efvo = int(val("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id=2 AND medio_pago_tipo='EFECTIVO' AND tabla_estado_registro_id=1 ORDER BY medio_pago_id LIMIT 1"))
transf = int(val("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id=2 AND medio_pago_tipo='TRANSFERENCIA' AND tabla_estado_registro_id=1 ORDER BY medio_pago_id LIMIT 1"))
ret = val("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id=2 AND medio_pago_tipo='RETENCION' AND tabla_estado_registro_id=1 ORDER BY medio_pago_id LIMIT 1")
banco = int(val("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id=2 AND cuenta_tipo='BANCO' AND tabla_estado_registro_id=1 ORDER BY tesoreria_cuenta_id LIMIT 1"))
saldo_banco = lambda: num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id={banco}"))
r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
         comprobante_tipo_id=12, entidad_id=0, consumidor_final_nombre='Mostrador SIM medios', f_emision=HOY, f_vto=HOY, moneda_id=1,
         tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, observaciones='SIM CF medios',
         detalles=[dict(producto_id=P1, cantidad=1, precio_unitario=1000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
fm = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fm, accion_js='confirma', empresa_idx=2, pagina_idx=56))
check(fm and estado_vf(fm) == 4, 'factura B a CF confirmada, pendiente de aprobación (4)')
r = ajax('ventas_facturas_ajax.php', dict(accion='cobro_contado_info', venta_factura_id=fm, empresa_idx=2, pagina_idx=56), 'GET')
info = r.get('data') or {}
check(ok_resp(r) and info.get('requiere') is True and abs(num(info.get('total')) - 1210) < 0.01, 'la pantalla sabe que tiene que pedir los medios (total 1210)', str(r))

def aprobar(medios):
    return ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fm, accion_js='aprobada', empresa_idx=2, pagina_idx=56,
                medios_contado=json.dumps(medios)))

r = aprobar([dict(medio_pago_id=efvo, importe=1000, tesoreria_cuenta_id=1)])
check(not ok_resp(r) and 'sumar el total' in err(r) and estado_vf(fm) == 4, 'medios que no suman el total: se rechaza y queda en 4', err(r))
if ret:
    r = aprobar([dict(medio_pago_id=int(ret), importe=1210, impuesto_tipo_id=5, retencion_certificado_nro='X1')])
    check(not ok_resp(r) and 'retenciones' in err(r) and estado_vf(fm) == 4, 'retención a consumidor final: se rechaza', err(r))
caja0, banco0 = caja(), saldo_banco()
r = aprobar([dict(medio_pago_id=efvo, importe=210, tesoreria_cuenta_id=1),
             dict(medio_pago_id=transf, importe=1000, tesoreria_cuenta_id=banco, nro_operacion='SIM-123')])
c = comp_vf(fm)
check(ok_resp(r) and estado_vf(fm) == 40 and c and abs(num(c['importe_pendiente'])) < 0.01, 'aprobada y cancelada (40) con los dos medios', err(r) + str(c))
check(abs(caja() - (caja0 + 210)) < 0.01, f'caja +210 ({caja0} -> {caja()})')
check(abs(saldo_banco() - (banco0 + 1000)) < 0.01, f'banco +1000 ({banco0} -> {saldo_banco()})')
rec = uno("SELECT cobranza_id, entidad_id, importe_total FROM gestion__cobranzas ORDER BY cobranza_id DESC LIMIT 1")
n = val(f"SELECT COUNT(*) FROM gestion__cobranzas_medios WHERE cobranza_id={rec['cobranza_id']}") if rec else '0'
check(rec and rec['entidad_id'] is None and num(rec['importe_total']) == 1210 and n == '2', 'recibo de contado sin entidad, 1210, con 2 medios', str(rec) + ' medios=' + str(n))
r = ajax('ventas_facturas_ajax.php', dict(accion='cobro_factura', venta_factura_id=fm, empresa_idx=2, pagina_idx=56), 'GET')
check(ok_resp(r) and 'consumidor final' not in (r['data'].get('motivo') or '').lower(), 'la solapa Medios de Pago ya no bloquea al consumidor final', str(r.get('data', {}).get('motivo')))
