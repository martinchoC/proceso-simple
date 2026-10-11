from simlib import *

PROV = 5765
CTX = json.load(open(os.path.join(SALIDAS, 'ctx_compras.json')))


def comp_fp(fid):
    return uno(f"SELECT comprobante_id, tabla_estado_registro_id estado, importe_total, importe_pendiente "
               f"FROM gestion__comprobantes WHERE tabla_origen_id=84 AND registro_origen_id={fid}")


def saldo_cuenta(cid):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id={cid}"))


def guardar_pago(medios, imputaciones):
    return ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=PROV, sucursal_id=1,
                f_emision=HOY, f_contabilidad=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM',
                medios=medios, imputaciones=imputaciones))


# ---------------------------------------------------------------------------------------------
caso('Caso 4 - Orden de pago total por transferencia (cancela factura de proveedor)')
f3 = CTX.get('f3')
c = comp_fp(f3) if f3 else None
r = ajax('pagos_ajax.php', dict(accion='comprobantes_pendientes', entidad_id=PROV, empresa_idx=2), 'GET')
ids = [int(x.get('comprobante_id', 0)) for x in r.get('data', [])] if ok_resp(r) else []
check(c and int(c['comprobante_id']) in ids, 'la factura figura en pendientes de pago', err(r))
if c:
    total = num(c['importe_total'])
    banco0 = saldo_cuenta(3)
    r = guardar_pago([dict(medio_pago_id=4, importe=total, importe_comprobante=total, moneda_id=1, tipo_cambio=1,
                           tesoreria_cuenta_id=3, nro_operacion='TRF-SIM-1')],
                     [dict(comprobante_destino_id=int(c['comprobante_id']), importe_imputado=total)])
    pago = r.get('pago_id') if isinstance(r, dict) else None
    check(ok_resp(r) and pago, f'borrador de OP por {total}', err(r))
    if pago:
        CTX['pago_total'] = pago
        r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
        check(ok_resp(r), 'confirmar OP', err(r))
        c2 = comp_fp(f3)
        check(int(c2['estado']) == 40 and abs(num(c2['importe_pendiente'])) < 0.01, 'factura -> 40 Cancelado total, saldo 0', str(c2))
        check(abs(saldo_cuenta(3) - (banco0 - total)) < 0.01, f'banco baja {total} ({banco0} -> {saldo_cuenta(3)})')
        cop = val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id={pago}")
        a = uno(f"SELECT f_asiento, descripcion FROM gestion__cont_asientos WHERE comprobante_id={cop}")
        check(a and a['f_asiento'] == HOY and a['descripcion'] not in (None, '', '0'), 'asiento de la OP con fecha y descripción', str(a))
        check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id={f3}")) in (5, 40),
              'la factura de proveedor queda confirmada o cancelada')

# ---------------------------------------------------------------------------------------------
caso('Caso 5 - Pago parcial en efectivo y anulación del pago')
f1 = CTX.get('f1')
c = comp_fp(f1) if f1 else None
if c:
    caja0 = saldo_cuenta(1)
    pend0 = num(c['importe_pendiente'])
    r = guardar_pago([dict(medio_pago_id=1, importe=2000, importe_comprobante=2000, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)],
                     [dict(comprobante_destino_id=int(c['comprobante_id']), importe_imputado=2000)])
    pago = r.get('pago_id') if isinstance(r, dict) else None
    check(ok_resp(r) and pago, 'borrador de OP parcial 2000', err(r))
    if pago:
        r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
        check(ok_resp(r), 'confirmar OP parcial', err(r))
        c2 = comp_fp(f1)
        check(int(c2['estado']) == 39 and abs(num(c2['importe_pendiente']) - (pend0 - 2000)) < 0.01,
              f'factura -> 39 Cancelado parcial, saldo {pend0 - 2000}', str(c2))
        check(abs(saldo_cuenta(1) - (caja0 - 2000)) < 0.01, f'caja baja 2000 ({caja0} -> {saldo_cuenta(1)})')
        r = ajax('pagos_ajax.php', dict(accion='anular', id=pago, motivo='Simulación: pago anulado', empresa_idx=2))
        check(ok_resp(r), 'anular OP', err(r))
        c3 = comp_fp(f1)
        check(int(c3['estado']) == 5 and abs(num(c3['importe_pendiente']) - pend0) < 0.01, 'factura vuelve a 5 con saldo original', str(c3))
        check(abs(saldo_cuenta(1) - caja0) < 0.01, f'caja restituida ({saldo_cuenta(1)})')
        check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id={pago}")) == 38, 'OP en 38 Anulado')

with open(os.path.join(SALIDAS, 'ctx_compras.json'), 'w') as f:
    json.dump(CTX, f)
