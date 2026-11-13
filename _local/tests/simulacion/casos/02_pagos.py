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

# ---------------------------------------------------------------------------------------------
caso('Caso 6 - Retenciones practicadas: Ganancias + IIBB Buenos Aires calculadas sobre la factura pagada')
f1 = CTX.get('f1')
c = comp_fp(f1) if f1 else None
cta_ret = val("SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id=2 AND es_imputable=1 AND codigo LIKE '2.%' "
              "AND tabla_estado_registro_id=1 ORDER BY codigo LIMIT 1")
# Empresa agente: Ganancias 2% s/ neto gravado y IIBB Buenos Aires 1,5% genérica (configs 5 y 7 del volcado)
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id=1, cont_cuenta_id={cta_ret}, f_desde='2026-01-01' "
    f"WHERE empresa_impuesto_config_id IN (5, 7)")
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=5, base_calculo='NETO_GRAVADO',
         tipo_calculo='manual', alicuota=2, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, empresa_idx=2))
check(ok_resp(r), 'ABM: config de retención de Ganancias', err(r))
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=7, base_calculo='NETO_GRAVADO',
         tipo_calculo='padron', alicuota=1.5, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, empresa_idx=2))
check(ok_resp(r), 'ABM: config de retención de IIBB Buenos Aires (padrón, genérica si no figura)', err(r))
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=1, base_calculo='NETO', tipo_calculo='manual',
         alicuota=2, empresa_idx=2))
check(not ok_resp(r), 'ABM: rechaza datos de retención en una config de percepción', err(r))

r = ajax('pagos_ajax.php', dict(accion='maestros', empresa_idx=2), 'GET')
cfgs = r.get('data', {}).get('retenciones_config', []) if ok_resp(r) else []
check(any(int(x['es_iibb']) == 1 and int(x['jurisdiccion_id']) == 2 for x in cfgs) and len(cfgs) == 2,
      'maestros: solo las retenciones de las que es agente, IIBB con su jurisdicción', json.dumps(cfgs)[:300])
medios_ret = [m for m in r.get('data', {}).get('medios_pago', []) if m['medio_pago_tipo'] == 'RETENCION'] if ok_resp(r) else []
check(sorted(m['codigo'] for m in medios_ret) == ['RET_GAN_P', 'RET_IIBB_P'], 'maestros: medios de retención de IVA y SUSS ocultos',
      str([m['codigo'] for m in medios_ret]))

if c:
    pend = num(c['importe_pendiente'])
    comp_id = int(c['comprobante_id'])
    r = ajax('pagos_ajax.php', dict(accion='calcular_retenciones', empresa_idx=2, entidad_id=PROV, f_emision=HOY,
             imputaciones=[dict(comprobante_destino_id=comp_id, importe_imputado=pend)]))
    rets = r.get('data', {}).get('retenciones', []) if ok_resp(r) else []
    check(len(rets) == 2, 'calcular: Ganancias + IIBB', err(r) if not ok_resp(r) else json.dumps(r.get('data'))[:400])
    neto = num(val(f"SELECT importe_neto FROM gestion__comprobantes WHERE comprobante_id={comp_id}"))
    base = round(neto * pend / num(c['importe_total']), 2)
    for x in rets:
        alic = 2 if int(x['impuesto_tipo_id']) == 14 else 1.5
        check(abs(num(x['retencion_base_imponible']) - base) < 0.02 and abs(num(x['importe']) - round(base * alic / 100, 2)) < 0.02,
              f"{x['impuesto_tipo']}: {base} x {alic}% = {x['importe']}", json.dumps(x))
    total_ret = round(sum(num(x['importe']) for x in rets), 2)
    medios = [dict(medio_pago_id=1, importe=round(pend - total_ret, 2), importe_comprobante=round(pend - total_ret, 2), moneda_id=1,
                   tipo_cambio=1, tesoreria_cuenta_id=1)]
    for x in rets:
        medios.append(dict(medio_pago_id=x['medio_pago_id'], importe=x['importe'], importe_comprobante=x['importe'], moneda_id=1, tipo_cambio=1,
                           impuesto_tipo_id=x['impuesto_tipo_id'], jurisdiccion_id=x['jurisdiccion_id'] or '',
                           retencion_base_imponible=x['retencion_base_imponible'], retencion_alicuota=x['retencion_alicuota']))
    imps = [dict(comprobante_destino_id=comp_id, importe_imputado=pend)]

    sin_jur = [dict(m) for m in medios]
    for m in sin_jur:
        if m.get('jurisdiccion_id'):
            m['jurisdiccion_id'] = ''
    r = guardar_pago(sin_jur, imps)
    check(not ok_resp(r), 'IIBB sin jurisdicción: rechazada', err(r))

    # Ajuste manual: importe distinto del calculado sin motivo -> rechazo; con motivo -> se guarda auditado
    ajustado = [dict(m) for m in medios]
    ajustado[1]['importe'] = ajustado[1]['importe_comprobante'] = num(ajustado[1]['importe']) + 5
    ajustado[0]['importe'] = ajustado[0]['importe_comprobante'] = round(num(ajustado[0]['importe']) - 5, 2)
    r = guardar_pago(ajustado, imps)
    check(not ok_resp(r), 'ajuste sin motivo: rechazado', err(r))
    ajustado[1]['retencion_motivo_ajuste'] = 'SIM: ajuste por redondeo del contador'
    r = guardar_pago(ajustado, imps)
    pago_aj = r.get('pago_id') if isinstance(r, dict) else None
    check(ok_resp(r) and pago_aj, 'ajuste con motivo: aceptado', err(r))
    if pago_aj:
        a = uno(f"SELECT importe, importe_calculado, motivo_ajuste, tabla_estado_registro_id estado, certificado_nro "
                f"FROM gestion__pagos_retenciones WHERE pago_id={pago_aj} AND motivo_ajuste IS NOT NULL")
        check(a and abs(num(a['importe']) - num(a['importe_calculado']) - 5) < 0.01 and int(a['estado']) == 3 and a['certificado_nro'] is None,
              'retención en borrador: calculado, aplicado y motivo, sin certificado', str(a))
        r = ajax('pagos_ajax.php', dict(accion='eliminar', id=pago_aj, empresa_idx=2))
        check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__pagos_retenciones WHERE pago_id={pago_aj}")) == 0,
              'eliminar el borrador borra sus retenciones', err(r))

    ult = {(int(x['impuesto_tipo_id']), int(x['jurisdiccion_id'] or 0)): int(val(
               f"SELECT COALESCE(MAX(ultimo_numero), 0) FROM gestion__retenciones_numeradores WHERE empresa_id=2 "
               f"AND impuesto_tipo_id={int(x['impuesto_tipo_id'])} AND jurisdiccion_id={int(x['jurisdiccion_id'] or 0)}") or 0) for x in rets}
    r = guardar_pago(medios, imps)
    pago = r.get('pago_id') if isinstance(r, dict) else None
    check(ok_resp(r) and pago, 'borrador de OP con retenciones', err(r))
    if pago:
        jur = val(f"SELECT jurisdiccion_id FROM gestion__pagos_medios WHERE pago_id={pago} AND impuesto_tipo_id=16")
        check(str(jur) == '2', 'la retención de IIBB guarda la jurisdicción', str(jur))
        check(int(val(f"SELECT COUNT(*) FROM gestion__pagos_retenciones WHERE pago_id={pago} AND tabla_estado_registro_id=3 "
                      f"AND importe = importe_calculado AND pago_medio_id IS NOT NULL")) == 2, 'dos retenciones registradas en borrador')
        r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
        check(ok_resp(r), 'confirmar OP con retenciones', err(r))
        c2 = comp_fp(f1)
        check(int(c2['estado']) == 40 and abs(num(c2['importe_pendiente'])) < 0.01, 'factura -> 40 Cancelado total', str(c2))
        cop = val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id={pago}")
        haber_ret = num(val(f"SELECT -SUM(d.importe_local) FROM gestion__cont_asientos_detalles d "
                            f"JOIN gestion__cont_asientos a ON a.cont_asiento_id = d.cont_asiento_id "
                            f"WHERE a.comprobante_id={cop} AND d.cuenta_id={cta_ret} AND d.importe_local < 0"))
        check(abs(haber_ret - total_ret) < 0.02, f'asiento: retenciones al haber de la cuenta de la config ({haber_ret} = {total_ret})')
        filas = sql(f"SELECT r.retencion_id, r.impuesto_tipo_id, COALESCE(r.jurisdiccion_id, 0) jur, r.certificado_nro, r.tabla_estado_registro_id estado, r.f_retencion, "
                    f"m.retencion_certificado_nro texto FROM gestion__pagos_retenciones r "
                    f"JOIN gestion__pagos_medios m ON m.pago_medio_id = r.pago_medio_id WHERE r.pago_id={pago}")
        check(len(filas) == 2 and all(int(f['estado']) == 5 and f['f_retencion'] == HOY for f in filas),
              'retenciones confirmadas con la fecha de la OP', str(filas))
        check(all(int(f['certificado_nro']) == ult[(int(f['impuesto_tipo_id']), int(f['jur']))] + 1 and f['texto'] == str(int(f['certificado_nro'])).zfill(8)
                  for f in filas), 'certificado correlativo por impuesto y jurisdicción, copiado a la línea del medio', str(filas))
        html = ajax('pagos_retencion_print.php', dict(retencion_id=filas[0]['retencion_id'], empresa_idx=2), 'GET')
        raw = html.get('_raw', '') if isinstance(html, dict) else ''
        check('Firma y aclaración del agente' in raw, 'imprime el certificado', raw[:200])
        r = ajax('pagos_ajax.php', dict(accion='anular', id=pago, motivo='SIM: anulación con retenciones', empresa_idx=2))
        check(ok_resp(r), 'anular OP con retenciones', err(r))
        an = sql(f"SELECT certificado_nro, tabla_estado_registro_id estado FROM gestion__pagos_retenciones WHERE pago_id={pago}")
        check(len(an) == 2 and all(int(a['estado']) == 38 and a['certificado_nro'] is not None for a in an),
              'retenciones anuladas conservando el número de certificado', str(an))

with open(os.path.join(SALIDAS, 'ctx_compras.json'), 'w') as f:
    json.dump(CTX, f)
