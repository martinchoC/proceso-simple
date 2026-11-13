"""Prueba global de Gestión Normal — Nivel 2 (OP05–OP09): retenciones combinadas.

OP05 IVA + Ganancias + IIBB local en un recibo; OP06 IIBB de otra provincia (Buenos Aires): en S no hay configuración
de esa provincia -> rechazo con mensaje; en B (Convenio, alta en ARBA) va a su cuenta; OP07 certificado PENDIENTE;
OP08 cobro parcial con retención (39) y segundo recibo que completa (40); OP09 un recibo que cancela tres facturas con una
retención de Ganancias. Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§5, N2).
"""
import random

from globlib import *

ctx, EMP = empresas_ctx()
SUF = f'{HOY.replace("-", "")}{random.randint(100, 999)}'
NETO, TOTAL = 100000.0, 121000.0
ARBA = jur('ARBA')

for k, e in EMP.items():
    cli = e['clientes']['AGENTE']
    prod = e['productos']['SRV-HORA' if k == 'S' else 'BIE-002']
    jur_local = jur(e['provincia_local'])
    ops = ctx.setdefault('ops', {}).setdefault(k, {})

    # ------------------------------------------------------------------------------------------- OP05
    caso(f'N2 OP05 IVA + Ganancias + IIBB local en un recibo [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(6))
    rets = [('RET_IVA', 2100.0, f'S{k}5-IVA-{SUF}', None), ('RET_GAN', 2000.0, f'S{k}5-GAN-{SUF}', None),
            ('RET_IIBB', 3000.0, f'S{k}5-IIBB-{SUF}', jur_local)]
    tes0 = saldos_tes(e)
    banco = TOTAL - 7100
    r = recibo(e, cli, [m_transfer(e, banco)] + [m_ret(e, c, i, cert, jur=j) for c, i, cert, j in rets], [(f['comp_id'], TOTAL)], fecha_op(6))
    verificar_operacion(e, 'OP05', factura=f, rec=r, banco=banco, retenciones=rets, saldo_esperado=0, tes0=tes0)
    ops['OP05'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'),
                       certs=[c for _, _, c, _ in rets], rets=rets, banco=banco)

    # ------------------------------------------------------------------------------------------- OP06
    caso(f'N2 OP06 IIBB de otra provincia (Buenos Aires) [{k}]')
    cli_arba = e['clientes']['ARBA']
    f = venta(e, cli_arba, [(prod, 1, NETO)], fecha_op(7))
    ret = [('RET_IIBB', 1500.0, f'S{k}6-ARBA-{SUF}', ARBA)]
    tes0 = saldos_tes(e)
    if k == 'S':
        # Sin configuración de IIBB Buenos Aires: no se puede contabilizar, el recibo no se confirma
        r = recibo(e, cli_arba, [m_efectivo(e, TOTAL - 1500), m_ret(e, 'RET_IIBB', 1500.0, ret[0][2], jur=ARBA)], [(f['comp_id'], TOTAL)], fecha_op(7))
        estado_cob = val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id = {int(r.get('cob') or 0)}")
        check(not ok_resp(r['r']) and 'cuenta contable' in err(r['r']).lower(), '[S] OP06: IIBB de una provincia sin configuración: no confirma y explica el motivo', err(r['r']))
        check(estado_cob in (None, '3'), '[S] OP06: el recibo queda en borrador (o no se guarda)', f'estado {estado_cob}')
        check(saldo(f['comp_id'])[0] == TOTAL and saldos_tes(e) == tes0, '[S] OP06: nada se movió (saldo de la factura, caja)')
        check(not asiento_de(r.get('comp_id')), '[S] OP06: sin asiento')
        if r.get('cob') and estado_cob == '3':
            rr = aj(e, 'cobranzas_ajax.php', dict(accion='eliminar', id=r['cob'], pagina_idx=pg(605)))
            check(ok_resp(rr), '[S] OP06: se elimina el borrador', err(rr))
        r = recibo(e, cli_arba, [m_efectivo(e, TOTAL)], [(f['comp_id'], TOTAL)], fecha_op(7))
        verificar_operacion(e, 'OP06 (cobro en efectivo)', factura=f, rec=r, efectivo=TOTAL, saldo_esperado=0, tes0=tes0)
    else:
        r = recibo(e, cli_arba, [m_efectivo(e, TOTAL - 1500), m_ret(e, 'RET_IIBB', 1500.0, ret[0][2], jur=ARBA)], [(f['comp_id'], TOTAL)], fecha_op(7))
        verificar_operacion(e, 'OP06', factura=f, rec=r, efectivo=TOTAL - 1500, retenciones=ret, saldo_esperado=0, tes0=tes0)
        check(cuenta_ret_iibb(e, ARBA) != cuenta_ret_iibb(e, jur_local), '[B] OP06: Buenos Aires y Río Negro van a cuentas distintas')
    ops['OP06'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

    # ------------------------------------------------------------------------------------------- OP07
    caso(f'N2 OP07 retención con certificado PENDIENTE [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(8))
    ret = [('RET_IVA', 2100.0, '', None)]
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [m_efectivo(e, TOTAL - 2100), m_ret(e, 'RET_IVA', 2100.0, '')], [(f['comp_id'], TOTAL)], fecha_op(8))
    verificar_operacion(e, 'OP07', factura=f, rec=r, efectivo=TOTAL - 2100, retenciones=ret, saldo_esperado=0, tes0=tes0)
    per = val(f"SELECT retencion_periodo_computo FROM gestion__cobranzas_medios WHERE cobranza_id = {int(r.get('cob') or 0)} AND impuesto_tipo_id IS NOT NULL")
    check(per == PERIODO_OP, f'[{k}] OP07: período de cómputo = mes del recibo ({PERIODO_OP})', f'{per}')
    ops['OP07'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

    # ------------------------------------------------------------------------------------------- OP08
    caso(f'N2 OP08 cobro parcial con retención y segundo recibo [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(9))
    ret = [('RET_IVA', 2100.0, f'S{k}8-IVA-{SUF}', None)]
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [m_efectivo(e, 57900.0), m_ret(e, 'RET_IVA', 2100.0, ret[0][2])], [(f['comp_id'], 60000.0)], fecha_op(9))
    verificar_operacion(e, 'OP08 (1er recibo)', factura=f, rec=r, efectivo=57900.0, retenciones=ret, saldo_esperado=61000.0, tes0=tes0)
    tes0 = saldos_tes(e)
    r2 = recibo(e, cli, [m_efectivo(e, 61000.0)], [(f['comp_id'], 61000.0)], fecha_op(10))
    verificar_operacion(e, 'OP08 (2do recibo)', factura=f, rec=r2, efectivo=61000.0, saldo_esperado=0, tes0=tes0)
    ops['OP08'] = dict(factura=f['fid'], factura_comp=f['comp_id'], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'),
                       cobranza2=r2.get('cob'))

    # ------------------------------------------------------------------------------------------- OP09
    caso(f'N2 OP09 un recibo para tres facturas con retención de Ganancias [{k}]')
    fs = [venta(e, cli, [(prod, 1, neto)], fecha_op(11)) for neto in (10000.0, 20000.0, 30000.0)]
    check([round(x['total'], 2) for x in fs] == [12100.0, 24200.0, 36300.0], f'[{k}] OP09: tres facturas', str([x['total'] for x in fs]))
    ret = [('RET_GAN', 1200.0, f'S{k}9-GAN-{SUF}', None)]
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [m_efectivo(e, 71400.0), m_ret(e, 'RET_GAN', 1200.0, ret[0][2])],
               [(x['comp_id'], x['total']) for x in fs], fecha_op(11))
    verificar_operacion(e, 'OP09', rec=r, efectivo=71400.0, retenciones=ret, tes0=tes0)
    for i, x in enumerate(fs):
        verificar_operacion(e, f'OP09 factura {i + 1}', factura=x, saldo_esperado=0)
    imp = sql(f"SELECT comp_destino_id, importe_imputado FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = {int(r.get('comp_id') or 0)} "
              f"AND tabla_estado_registro_id NOT IN (2,3,6,38)")
    check(len(imp) == 3 and abs(sum(num(i['importe_imputado']) for i in imp) - 72600) < 0.01, f'[{k}] OP09: tres imputaciones por 72.600', str(imp))
    ops['OP09'] = dict(facturas=[x['fid'] for x in fs], facturas_comp=[x['comp_id'] for x in fs], cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

    caso(f'N2 cierre del nivel [{k}]')
    for c in ('AGENTE', 'ARBA'):
        check(ctacte_cliente(e, e['clientes'][c]) == 0, f'[{k}] cuenta corriente de {c} en 0', f"{ctacte_cliente(e, e['clientes'][c])}")

ctx_guardar(ctx)
