"""Prueba global de Gestión Normal — Nivel 3 (OP10–OP13): otras páginas y otros medios.

OP10 cobro desde la factura (Facturación > Cobrar) con retención IIBB; OP11 cheque de terceros + retención de IVA y
depósito del cheque (Cartera de Cheques); OP12 NC asociada (S bonificación, B devolución con stock) y recibo con retención
por el saldo neto; OP13 S: Factura B a un exento con retención IIBB / B: pedido -> remito parcial -> factura desde el remito
cobrada con retenciones de IVA e IIBB Río Negro. Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§5, N3).
"""
import random

from globlib import *

ctx, EMP = empresas_ctx()
SUF = f'{HOY.replace("-", "")}{random.randint(100, 999)}'
NETO, TOTAL = 100000.0, 121000.0

for k, e in EMP.items():
    cli = e['clientes']['AGENTE']
    prod = e['productos']['SRV-IMPL' if k == 'S' else 'BIE-003']
    jur_local = jur(e['provincia_local'])
    ops = ctx.setdefault('ops', {}).setdefault(k, {})

    # ------------------------------------------------------------------------------------------- OP10
    caso(f'N3 OP10 cobro desde la factura con retención IIBB [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(12))
    r = aj(e, 'ventas_facturas_ajax.php', dict(accion='cobro_factura', venta_factura_id=f['fid'], pagina_idx=pg(556)), 'GET')
    check(ok_resp(r) and (r.get('data') or {}).get('puede_cobrar'), f'[{k}] OP10: la factura se puede cobrar desde Facturación', err(r))
    r = aj(e, 'ventas_facturas_ajax.php', dict(accion='cobro_maestros', pagina_idx=pg(556)), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    check(jur_local in {int(j['jurisdiccion_id']) for j in d.get('jurisdicciones', [])}
          and 'RET_IIBB' in {m.get('codigo') for m in d.get('medios_pago', [])}, f'[{k}] OP10: cobro_maestros ofrece la provincia y RET_IIBB', err(r))
    t = aj(e, 'ventas_facturas_ajax.php', dict(accion='cobro_tipos_por_punto_venta', punto_venta_id=e['pv_id'], pagina_idx=pg(556)), 'GET')
    tipos = t.get('data') if isinstance(t, dict) else t
    tipo_rec = int((tipos or [{}])[0].get('comprobante_tipo_id') or 0) if isinstance(tipos, list) else 0
    check(tipo_rec > 0, f'[{k}] OP10: tipo de recibo para el PV', str(t)[:200])
    ret = [('RET_IIBB', 3000.0, f'S{k}10-IIBB-{SUF}', jur_local)]
    tes0 = saldos_tes(e)
    rr = aj(e, 'ventas_facturas_ajax.php', dict(accion='emitir_recibo', venta_factura_id=f['fid'], pagina_idx=pg(556), comprobante_pv=e['pv_id'],
                                                comprobante_tipo_id=tipo_rec, f_emision=fecha_op(12), observaciones='SIM OP10',
                                                medios=json.dumps([m_efectivo(e, TOTAL - 3000), m_ret(e, 'RET_IIBB', 3000.0, ret[0][2], jur=jur_local)])))
    check(ok_resp(rr), f'[{k}] OP10: emitir recibo desde la factura', err(rr))
    cob = val(f"SELECT c.cobranza_id FROM gestion__cobranzas c JOIN gestion__comprobantes_imputaciones i ON i.comp_origen_id = c.comprobante_id "
              f"WHERE i.comp_destino_id = {f['comp_id']} AND c.tabla_estado_registro_id = 5 ORDER BY c.cobranza_id DESC LIMIT 1")
    rec = dict(r=rr, cob=int(cob) if cob else None,
               comp_id=int(val(f"SELECT comprobante_id FROM gestion__cobranzas WHERE cobranza_id = {int(cob or 0)}") or 0) or None)
    verificar_operacion(e, 'OP10', factura=f, rec=rec, efectivo=TOTAL - 3000, retenciones=ret, saldo_esperado=0, tes0=tes0)
    ops['OP10'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=rec['cob'], recibo_comp=rec['comp_id'])

    # ------------------------------------------------------------------------------------------- OP11
    caso(f'N3 OP11 cheque de terceros + retención de IVA y depósito del cheque [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(13))
    ret = [('RET_IVA', 2100.0, f'S{k}11-IVA-{SUF}', None)]
    chq_nro = f'{random.randint(10000000, 99999999)}'
    chq = dict(medio_pago_id=int(medio(e, 'CHQ_TERCERO')['medio_pago_id']), importe=TOTAL - 2100, importe_comprobante=TOTAL - 2100,
               moneda_id=e['moneda_id'], tipo_cambio=1, tesoreria_cuenta_id=e['valores_id'],
               cheque=dict(banco_id=1, numero=chq_nro, f_pago=fecha_op(13), librador_nombre='SIM Agente', librador_cuit=cuit(3071999101)))
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [chq, m_ret(e, 'RET_IVA', 2100.0, ret[0][2])], [(f['comp_id'], TOTAL)], fecha_op(13))
    verificar_operacion(e, 'OP11', factura=f, rec=r, valores=TOTAL - 2100, retenciones=ret, saldo_esperado=0)
    v1 = saldos_tes(e)[e['valores_id']]
    check(abs(v1 - tes0[e['valores_id']] - (TOTAL - 2100)) < 0.01, f'[{k}] OP11: valores en cartera +{TOTAL - 2100}', f"{tes0[e['valores_id']]} -> {v1}")
    ch = uno(f"SELECT cheque_id, importe, tabla_estado_registro_id, tesoreria_cuenta_id FROM gestion__cheques WHERE empresa_id = {e['empresa_id']} AND numero = '{chq_nro}'")
    check(ch and abs(num(ch['importe']) - (TOTAL - 2100)) < 0.01 and int(ch['tesoreria_cuenta_id']) == e['valores_id'],
          f'[{k}] OP11: cheque en cartera por el importe sin la retención', str(ch))
    if ch:
        tes1 = saldos_tes(e)
        rd = aj(e, 'cheques_cartera_ajax.php', dict(accion='depositar', pagina_idx=pg(607), cheque_id=ch['cheque_id'], banco_cuenta_id=e['banco_id'],
                                                    f_deposito=fecha_op(14)))
        check(ok_resp(rd), f'[{k}] OP11: depositar el cheque en el banco', err(rd))
        tes2 = saldos_tes(e)
        check(abs(tes2[e['valores_id']] - tes1[e['valores_id']] + (TOTAL - 2100)) < 0.01 and abs(tes2[e['banco_id']] - tes1[e['banco_id']] - (TOTAL - 2100)) < 0.01,
              f'[{k}] OP11: valores -> banco por {TOTAL - 2100}', f'{tes1} -> {tes2}')
    ops['OP11'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'), cheque=(ch or {}).get('cheque_id'))

    # ------------------------------------------------------------------------------------------- OP12
    caso(f'N3 OP12 NC asociada y recibo con retención por el saldo neto [{k}]')
    s0 = stock(prod, e['boca_id'], e['empresa_id'])
    f = venta(e, cli, [(prod, 5, 20000.0)], fecha_op(15))     # 100.000 + IVA
    nca = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = 'V_NCA'") or 0)
    r = aj(e, 'ventas_facturas_ajax.php', dict(accion='comprobantes_asociables', entidad_id=cli, comprobante_tipo_id=nca, pagina_idx=pg(556)), 'GET')
    check(ok_resp(r) and f['comp_id'] in [int(x['comprobante_id']) for x in (r.get('data') or [])], f'[{k}] OP12: la factura es asociable a la NC A', err(r))
    base = dict(pagina_idx=pg(556))
    rn = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='agregar', punto_venta_id=e['pv_id'], sucursal_id=e['sucursal_id'], comprobante_tipo_id=nca,
                                                entidad_id=cli, f_emision=fecha_op(15), f_vto=fecha_op(15), moneda_id=e['moneda_id'], tipo_cambio=1,
                                                condicion_pago_id='', descuento_general_pct=0, observaciones='SIM OP12 NC',
                                                comprobante_asociado_id=f['comp_id'],
                                                detalles=[dict(producto_id=prod, cantidad=1, precio_unitario=20000.0, descuento_general_pct=0,
                                                               iva_alicuota_id=e['iva21'], iva_porcentaje=21)]))
    nc = (rn.get('venta_factura_id') or (rn.get('data') or {}).get('venta_factura_id')) if isinstance(rn, dict) else None
    check(nc, f'[{k}] OP12: alta de la NC A por 24.200', err(rn))
    if nc:
        aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=nc, accion_js='confirma'))
        if val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {nc}") == '4':
            rn = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=nc, accion_js='aprobada'))
        check(ok_resp(rn), f'[{k}] OP12: NC confirmada', err(rn))
    s, est = saldo(f['comp_id'])
    check(abs(s - 96800) < 0.01 and est == 39, f'[{k}] OP12: la NC se aplica sola: factura con saldo 96.800 (39)', f'saldo {s} estado {est}')
    if k == 'B':
        devuelve = val(f"SELECT impacta_stock FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = {nca}") == '1'
        esperado = s0 - 5 + (1 if devuelve else 0)
        check(stock(prod, e['boca_id'], e['empresa_id']) == esperado, f'[B] OP12: stock {s0} - 5 vendidos + {1 if devuelve else 0} devuelto',
              f"{stock(prod, e['boca_id'], e['empresa_id'])}")
    ret = [('RET_IVA', 1680.0, f'S{k}12-IVA-{SUF}', None)]
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [m_efectivo(e, 96800 - 1680), m_ret(e, 'RET_IVA', 1680.0, ret[0][2])], [(f['comp_id'], 96800.0)], fecha_op(16))
    verificar_operacion(e, 'OP12', factura=f, rec=r, efectivo=96800 - 1680, retenciones=ret, saldo_esperado=0, tes0=tes0)
    ops['OP12'] = dict(factura=f['fid'], factura_comp=f['comp_id'], nc=nc, cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

    # ------------------------------------------------------------------------------------------- OP13
    if k == 'S':
        caso('N3 OP13 Factura B a un exento con retención IIBB [S]')
        exento = e['clientes']['EXENTO']
        fa_mal = venta(e, exento, [(prod, 1, NETO)], fecha_op(17), tipo_cod='V_FA')
        check(not fa_mal.get('comp_id') or fa_mal.get('estado') != 5, '[S] OP13: Factura A a un exento: no se confirma (relación fiscal)',
              f"se confirmó la factura {fa_mal.get('fid')}")
        if fa_mal.get('fid') and fa_mal.get('estado') != 5:
            aj(e, 'ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fa_mal['fid'], accion_js='eliminar', pagina_idx=pg(556)))
        f = venta(e, exento, [(prod, 1, NETO)], fecha_op(17), tipo_cod='V_FB')
        check(abs(f['total'] - TOTAL) < 0.01, '[S] OP13: Factura B por 121.000 (IVA incluido)', f"{f['total']} {err(f['r'])}")
        ret = [('RET_IIBB', 3000.0, f'SS13-IIBB-{SUF}', jur_local)]
        tes0 = saldos_tes(e)
        r = recibo(e, exento, [m_efectivo(e, TOTAL - 3000), m_ret(e, 'RET_IIBB', 3000.0, ret[0][2], jur=jur_local)], [(f['comp_id'], TOTAL)], fecha_op(17))
        verificar_operacion(e, 'OP13', factura=f, rec=r, efectivo=TOTAL - 3000, retenciones=ret, saldo_esperado=0, tes0=tes0)
        ops['OP13'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))
    else:
        caso('N3 OP13 pedido -> remito parcial -> factura desde el remito, cobrada con retenciones [B]')
        cli_rn = e['clientes']['CM_RN']
        p4 = e['productos']['BIE-004']
        s3, s4 = stock(prod, e['boca_id'], e['empresa_id']), stock(p4, e['boca_id'], e['empresa_id'])
        tipo_ped = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = 'PC'") or 0)
        dets = [linea(prod, 4, 20000.0, iva_id=e['iva21']), linea(p4, 2, 150000.0, iva_id=e['iva21'])]
        r = aj(e, 'ventas_pedidos_ajax.php', dict(accion='agregar', pagina_idx=pg(591), comprobante_tipo_id=tipo_ped, sucursal_id=e['sucursal_id'],
                                                  punto_venta_id=e['pv_id'], entidad_id=cli_rn, f_emision=fecha_op(18), moneda_id=e['moneda_id'],
                                                  tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, detalles=dets, **totales(dets)))
        ped = (r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None
        check(ok_resp(r) and ped, '[B] OP13: alta de pedido', err(r))
        fdesde = None
        if ped:
            r = aj(e, 'ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido', pagina_idx=pg(591)))
            check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}") == '5',
                  '[B] OP13: pedido confirmado (5)', err(r))
            r = aj(e, 'ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', entidad_id=cli_rn, punto_venta_id=e['pv_id'],
                                                      pedido_id=ped, pagina_idx=pg(588)), 'GET')
            pend = []
            for p in (r if isinstance(r, list) else []):
                pend += [p] if 'venta_pedido_detalle_id' in p else p.get('detalles', p.get('lineas', []))
            por_prod = {int(l['producto_id']): l for l in pend if int(l.get('venta_pedido_id', ped)) == int(ped)}
            check(len(por_prod) == 2, '[B] OP13: el remito ve las dos líneas del pedido', str(r)[:300])
            tipo_rem = int(val(f"SELECT pvc.comprobante_tipo_id FROM gestion__puntos_venta_comprobantes pvc JOIN gestion__comprobantes_tipos ct "
                               f"USING (comprobante_tipo_id) WHERE pvc.punto_venta_id = {e['pv_id']} AND ct.codigo IN ('S_RSA', 'S_RSX', 'S_RCPe') "
                               f"ORDER BY ct.codigo = 'S_RSA' DESC LIMIT 1") or 0)
            rdets = [dict(venta_pedido_detalle_id=int(por_prod[p]['venta_pedido_detalle_id']), producto_id=p, cantidad=c,
                          precio_unitario_bruto=num(por_prod[p].get('precio_unitario_bruto') or por_prod[p].get('precio_unitario')),
                          descuento_general_pct=0, iva_alicuota_id=e['iva21'], iva_porcentaje=21)
                     for p, c in ((prod, 3), (p4, 2)) if p in por_prod]
            r = aj(e, 'ventas_remitos_ajax.php', dict(accion='agregar', pagina_idx=pg(588), punto_venta_id=e['pv_id'], comprobante_tipo_id=tipo_rem,
                                                      entidad_id=cli_rn, f_emision=fecha_op(18), detalles=rdets))
            rem = (r.get('venta_remito_id') or r.get('id')) if isinstance(r, dict) else None
            check(ok_resp(r) and rem, '[B] OP13: remito parcial (3 de 4 y 2 de 2)', err(r))
            if rem:
                r = aj(e, 'ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js='confirmar', pagina_idx=pg(588)))
                check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_remitos WHERE venta_remito_id = {rem}") == '13',
                      '[B] OP13: remito confirmado (13 pendiente de facturación)', err(r))
                check(stock(prod, e['boca_id'], e['empresa_id']) == s3 - 3 and stock(p4, e['boca_id'], e['empresa_id']) == s4 - 2, '[B] OP13: el remito baja stock 3 / 2')
                check(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}") == '10', '[B] OP13: pedido con entrega parcial (10)')
                r = aj(e, 'ventas_facturas_ajax.php', dict(accion='remitos_pendientes', entidad_id=cli_rn, venta_remito_id=rem, pagina_idx=pg(556)), 'GET')
                lineas = []
                for x in (r if isinstance(r, list) else (r.get('data') or [] if isinstance(r, dict) else [])):
                    lineas += x.get('detalles', x.get('lineas', [x]))
                lineas = [l for l in lineas if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
                fdets = [dict(producto_id=int(l['producto_id']), cantidad=num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
                              precio_unitario=num(l.get('precio_unitario_bruto') or l.get('precio_unitario')), descuento_general_pct=0,
                              iva_alicuota_id=e['iva21'], iva_porcentaje=21, venta_remito_detalle_id=int(l['venta_remito_detalle_id']),
                              venta_pedido_detalle_id=int(l.get('venta_pedido_detalle_id') or 0)) for l in lineas]
                check(len(fdets) == 2, '[B] OP13: la factura ve las dos líneas del remito', str(r)[:300])
                tipo_fa = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = 'V_FA'"))
                r = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='agregar', punto_venta_id=e['pv_id'], sucursal_id=e['sucursal_id'],
                                                           comprobante_tipo_id=tipo_fa, entidad_id=cli_rn, f_emision=fecha_op(19), f_vto=fecha_op(19),
                                                           moneda_id=e['moneda_id'], tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0,
                                                           observaciones='SIM OP13 desde remito', detalles=fdets))
                fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
                check(fid, '[B] OP13: factura desde el remito', err(r))
                if fid:
                    aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma'))
                    if val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}") == '4':
                        r = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada'))
                    c = uno(f"SELECT comprobante_id, importe_total, comprobante_nro, tabla_estado_registro_id estado FROM gestion__comprobantes "
                            f"WHERE tabla_origen_id = 80 AND registro_origen_id = {fid}")
                    fdesde = dict(r=r, fid=int(fid), tipo=tipo_fa, comp_id=int(c['comprobante_id']), total=num(c['importe_total']),
                                  nro=int(c['comprobante_nro'] or 0), estado=int(c['estado'])) if c else None
                    check(fdesde and abs(fdesde['total'] - 435600) < 0.01, '[B] OP13: factura por 360.000 + IVA = 435.600', str(fdesde))
                    check(stock(prod, e['boca_id'], e['empresa_id']) == s3 - 3 and stock(p4, e['boca_id'], e['empresa_id']) == s4 - 2,
                          '[B] OP13: la factura no vuelve a bajar stock')
                    check(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_remitos WHERE venta_remito_id = {rem}") == '12', '[B] OP13: remito facturado (12)')
        if fdesde:
            JRN = jur('DGR_RN')
            ret = [('RET_IVA', 7560.0, f'SB13-IVA-{SUF}', None), ('RET_IIBB', 10800.0, f'SB13-IIBB-{SUF}', JRN)]
            tes0 = saldos_tes(e)
            banco = 435600 - 7560 - 10800
            r = recibo(e, cli_rn, [m_transfer(e, banco), m_ret(e, 'RET_IVA', 7560.0, ret[0][2]), m_ret(e, 'RET_IIBB', 10800.0, ret[1][2], jur=JRN)],
                       [(fdesde['comp_id'], 435600.0)], fecha_op(19))
            verificar_operacion(e, 'OP13', factura=fdesde, rec=r, banco=banco, retenciones=ret, saldo_esperado=0, tes0=tes0)
            ops['OP13'] = dict(factura=fdesde['fid'], factura_comp=fdesde['comp_id'], pedido=ped, cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

ctx_guardar(ctx)
