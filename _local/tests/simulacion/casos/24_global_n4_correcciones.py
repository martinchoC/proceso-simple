"""Prueba global de Gestión Normal — Nivel 4 (OP14–OP17): rechazos y correcciones.

OP14 rechazos al guardar (certificado repetido, IIBB sin provincia o con jurisdicción no provincial, medio con otro
impuesto, medio de otra empresa) y escrituras cruzadas entre empresas; OP15 anular el recibo de OP05 (revierte y libera
los certificados, que se vuelven a cargar); OP16 completar en Control Fiscal el certificado PENDIENTE de OP07;
OP17 retención con período de cómputo distinto del recibo. Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§5, N4).
"""
import random

from globlib import *

ctx, EMP = empresas_ctx()
SUF = f'{HOY.replace("-", "")}{random.randint(100, 999)}'
NETO, TOTAL = 100000.0, 121000.0
otra = {'S': 'B', 'B': 'S'}

for k, e in EMP.items():
    cli = e['clientes']['AGENTE']
    prod = e['productos']['SRV-SOP' if k == 'S' else 'BIE-002']
    jur_local = jur(e['provincia_local'])
    ops = ctx['ops'][k]
    o = EMP[otra[k]]

    # ------------------------------------------------------------------------------------------- OP14
    caso(f'N4 OP14 rechazos al guardar el recibo [{k}]')
    f = venta(e, cli, [(prod, 1, NETO)], fecha_op(20))
    n0 = int(val(f"SELECT COUNT(*) FROM gestion__cobranzas WHERE empresa_id = {e['empresa_id']}"))
    imp = [(f['comp_id'], 1000.0)]

    def rechazo(medios, texto, motivo):
        r = recibo(e, cli, medios, imp, fecha_op(20), confirmar=False)
        check(not ok_resp(r['r']) and texto in err(r['r']).lower(), f'[{k}] OP14: {motivo}: rechazado', err(r['r']))

    cert_op02 = ops['OP02']['certs'][0]
    rechazo([m_efectivo(e, 1000 - 2100 + 2100), m_ret(e, 'RET_IVA', 2100.0, cert_op02)], 'ya está registrado', 'certificado de IVA ya usado por el cliente (OP02)')
    rechazo([m_efectivo(e, 1000), m_ret(e, 'RET_IIBB', 500.0, f'S{k}14-A-{SUF}')], 'jurisdicci', 'IIBB sin provincia')
    rechazo([m_efectivo(e, 1000), m_ret(e, 'RET_IIBB', 500.0, f'S{k}14-B-{SUF}', jur=1)], 'jurisdicci', 'IIBB con jurisdicción no provincial (AFIP)')
    mal = m_ret(e, 'RET_GAN', 500.0, f'S{k}14-C-{SUF}')
    mal['impuesto_tipo_id'] = int(medio(e, 'RET_IVA')['impuesto_tipo_id'])
    rechazo([m_efectivo(e, 1000), mal], 'otro impuesto', 'medio de Ganancias con el impuesto de IVA')
    ajeno = m_ret(e, 'RET_IVA', 500.0, f'S{k}14-D-{SUF}')
    ajeno['medio_pago_id'] = int(medio(o, 'RET_IVA')['medio_pago_id'])
    r = recibo(e, cli, [m_efectivo(e, 1000), ajeno], imp, fecha_op(20), confirmar=False)
    check(not ok_resp(r['r']), f'[{k}] OP14: medio de pago de la empresa {otra[k]}: rechazado', err(r['r']) or str(r))
    if r.get('cob'):
        aj(e, 'cobranzas_ajax.php', dict(accion='eliminar', id=r['cob'], pagina_idx=pg(605)))
    caja_ajena = m_efectivo(e, 1000)
    caja_ajena['tesoreria_cuenta_id'] = o['caja_id']
    r = recibo(e, cli, [caja_ajena], imp, fecha_op(20))
    check(not ok_resp(r['r']) and saldos_tes(o)[o['caja_id']] == saldos_tes(o)[o['caja_id']] and
          val(f"SELECT COUNT(*) FROM gestion__tesoreria_movimientos WHERE tesoreria_cuenta_id = {o['caja_id']} AND empresa_id = {e['empresa_id']}") == '0',
          f"[{k}] OP14: efectivo a la caja de la empresa {otra[k]}: rechazado", err(r['r']))
    if r.get('cob') and val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id = {r['cob']}") == '3':
        aj(e, 'cobranzas_ajax.php', dict(accion='eliminar', id=r['cob'], pagina_idx=pg(605)))
    check(int(val(f"SELECT COUNT(*) FROM gestion__cobranzas WHERE empresa_id = {e['empresa_id']} AND tabla_estado_registro_id <> 6")) ==
          int(val(f"SELECT COUNT(*) FROM gestion__cobranzas WHERE empresa_id = {e['empresa_id']} AND tabla_estado_registro_id <> 6 AND cobranza_id <= "
                  f"(SELECT MAX(cobranza_id) FROM (SELECT cobranza_id FROM gestion__cobranzas WHERE empresa_id = {e['empresa_id']} ORDER BY cobranza_id LIMIT {n0}) x)")),
          f'[{k}] OP14: los rechazos no dejaron recibos vigentes')
    check(saldo(f['comp_id'])[0] == TOTAL, f'[{k}] OP14: la factura sigue con todo su saldo')
    ops['OP14'] = dict(factura=f['fid'], factura_comp=f['comp_id'])

    caso(f'N4 OP14 escrituras cruzadas entre empresas (configuración) [{k}]')
    # Con la sesión en esta empresa, pedir que se escriba la configuración de la otra (empresa_id en el POST)
    n_cfg = val(f"SELECT COUNT(*) FROM gestion__empresas_impuestos_config WHERE empresa_id = {o['empresa_id']}")
    # Con el Administrador de esta empresa (sin perfil en la otra): el superadmin tiene acceso a las dos
    adm = e['usuario_admin']
    r = aj(e, 'empresas_impuestos_config_ajax.php', dict(accion='agregar', pagina_idx=pg(573), empresa_id=o['empresa_id'], impuesto_tipo_id=4,
                                                         jurisdiccion_id=jur('DGR_CBA'), cont_cuenta_id='', tipo_calculo='manual', prioridad=9,
                                                         f_desde='2020-01-01', aplica_siempre=0), usuario_id=adm)
    check(val(f"SELECT COUNT(*) FROM gestion__empresas_impuestos_config WHERE empresa_id = {o['empresa_id']}") == n_cfg,
          f"[{k}] OP14: Configuración de Impuestos no escribe en la empresa {otra[k]} aunque el POST traiga su empresa_id", err(r))
    par_o = uno(f"SELECT empresa_parametro_id, punto_venta_defecto_id FROM gestion__empresas_parametros WHERE empresa_id = {o['empresa_id']} "
                f"AND tabla_estado_registro_id = 1 AND f_hasta IS NULL ORDER BY 1 DESC LIMIT 1")
    n_par = val(f"SELECT COUNT(*) FROM gestion__empresas_parametros WHERE empresa_id = {o['empresa_id']}")
    r = aj(e, 'empresas_parametros_ajax.php', dict(accion='guardar', pagina_idx=pg(611), empresa_parametro_id=0, empresa_id=o['empresa_id'],
                                                   condicion_fiscal_id=1, punto_venta_defecto_id=e['pv_id'], f_desde=HOY, cerrar_anterior=1,
                                                   observaciones='SIM escritura cruzada'), usuario_id=adm)
    par_o2 = uno(f"SELECT empresa_parametro_id, punto_venta_defecto_id FROM gestion__empresas_parametros WHERE empresa_id = {o['empresa_id']} "
                 f"AND tabla_estado_registro_id = 1 AND f_hasta IS NULL ORDER BY 1 DESC LIMIT 1")
    check(par_o2 == par_o and val(f"SELECT COUNT(*) FROM gestion__empresas_parametros WHERE empresa_id = {o['empresa_id']}") == n_par,
          f"[{k}] OP14: Parámetros de Empresa no escribe en la empresa {otra[k]} aunque el POST traiga su empresa_id", err(r))

    # ------------------------------------------------------------------------------------------- OP15
    caso(f'N4 OP15 anular el recibo de OP05 y volver a cobrar con los mismos certificados [{k}]')
    op5 = ops['OP05']
    tes0 = saldos_tes(e)
    r = aj(e, 'cobranzas_ajax.php', dict(accion='anular', id=op5['cobranza'], motivo='SIM OP15: error en el recibo', pagina_idx=pg(605)))
    check(ok_resp(r), f'[{k}] OP15: anular el recibo', err(r))
    check(val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id = {op5['cobranza']}") == '38', f'[{k}] OP15: recibo anulado (38)')
    s, est = saldo(op5['factura_comp'])
    check(s == TOTAL and est == 5, f'[{k}] OP15: la factura vuelve a 5 con saldo {TOTAL}', f'saldo {s} estado {est}')
    tes1 = saldos_tes(e)
    check(abs(tes1[e['banco_id']] - tes0[e['banco_id']] + op5['banco']) < 0.01, f"[{k}] OP15: banco -{op5['banco']}", f'{tes0} -> {tes1}')
    ren = asiento_de(op5['recibo_comp'])
    neto = {}
    for c, _, i in ren:
        neto[c] = round(neto.get(c, 0) + i, 2)
    check(all(abs(v) < 0.01 for v in neto.values()), f'[{k}] OP15: el asiento del recibo queda revertido (neto 0 por cuenta)', str(ren))
    rets = [tuple(x) for x in op5['rets']]
    r = recibo(e, cli, [m_transfer(e, op5['banco'])] + [m_ret(e, c, i, cert, jur=j) for c, i, cert, j in rets], [(op5['factura_comp'], TOTAL)], fecha_op(21))
    f5 = dict(fid=op5['factura'], comp_id=op5['factura_comp'], total=TOTAL)
    verificar_operacion(e, 'OP15 (nuevo recibo con los mismos certificados)', factura=None, rec=r, banco=op5['banco'], retenciones=rets, tes0=tes1)
    s, est = saldo(op5['factura_comp'])
    check(s == 0 and est == 40, f'[{k}] OP15: la factura queda cancelada otra vez (40)', f'saldo {s} estado {est}')
    ops['OP15'] = dict(cobranza=r.get('cob'), recibo_comp=r.get('comp_id'), anulada=op5['cobranza'])

    # ------------------------------------------------------------------------------------------- OP16
    caso(f'N4 OP16 completar el certificado PENDIENTE de OP07 en Control Fiscal [{k}]')
    cm = uno(f"SELECT cobranza_medio_id, retencion_certificado_estado est, retencion_periodo_computo per FROM gestion__cobranzas_medios "
             f"WHERE cobranza_id = {ops['OP07']['cobranza']} AND impuesto_tipo_id IS NOT NULL")
    check(cm and cm['est'] == 'PENDIENTE', f'[{k}] OP16: la retención de OP07 está PENDIENTE', str(cm))
    cert = f'S{k}16-IVA-{SUF}'
    base = dict(accion='actualizar_certificado', pagina_idx=pg(625), cobranza_medio_id=cm['cobranza_medio_id'], retencion_regimen='RG 2854',
                retencion_periodo_computo=cm['per'])
    r = aj(e, 'cierres_fiscales_ajax.php', dict(base, retencion_certificado_nro='', retencion_f_certificado='', retencion_certificado_estado='RECIBIDO'))
    check(not ok_resp(r) and 'número y fecha' in err(r), f'[{k}] OP16: RECIBIDO sin número ni fecha: rechazado', err(r))
    r = aj(e, 'cierres_fiscales_ajax.php', dict(base, retencion_certificado_nro=ops['OP02']['certs'][0], retencion_f_certificado=fecha_op(22),
                                                retencion_certificado_estado='RECIBIDO'))
    check(not ok_resp(r) and 'ya está registrado' in err(r), f'[{k}] OP16: número de un certificado ya cargado al cliente: rechazado', err(r))
    r = aj(e, 'cierres_fiscales_ajax.php', dict(base, retencion_certificado_nro=cert, retencion_f_certificado=fecha_op(22), retencion_certificado_estado='RECIBIDO'))
    check(ok_resp(r), f'[{k}] OP16: certificado RECIBIDO con número y fecha', err(r))
    r = aj(e, 'cierres_fiscales_ajax.php', dict(base, retencion_certificado_nro=cert, retencion_f_certificado=fecha_op(22), retencion_certificado_estado='CONCILIADO'))
    check(ok_resp(r), f'[{k}] OP16: certificado CONCILIADO', err(r))
    cm2 = uno(f"SELECT retencion_certificado_nro nro, retencion_certificado_estado est, retencion_regimen reg FROM gestion__cobranzas_medios "
              f"WHERE cobranza_medio_id = {cm['cobranza_medio_id']}")
    check(cm2 and cm2['nro'] == cert and cm2['est'] == 'CONCILIADO' and cm2['reg'] == 'RG 2854', f'[{k}] OP16: datos del certificado guardados', str(cm2))
    anulada = uno(f"SELECT cobranza_medio_id FROM gestion__cobranzas_medios WHERE cobranza_id = {ops['OP15']['anulada']} AND impuesto_tipo_id IS NOT NULL LIMIT 1")
    r = aj(e, 'cierres_fiscales_ajax.php', dict(base, cobranza_medio_id=anulada['cobranza_medio_id'], retencion_certificado_nro=f'S{k}16-X-{SUF}',
                                                retencion_f_certificado=fecha_op(22), retencion_certificado_estado='RECIBIDO'))
    check(not ok_resp(r) and 'confirmados' in err(r), f'[{k}] OP16: certificado de un recibo anulado: rechazado', err(r))
    medio_ajeno = val(f"SELECT cm.cobranza_medio_id FROM gestion__cobranzas_medios cm JOIN gestion__cobranzas c ON c.cobranza_id = cm.cobranza_id "
                      f"WHERE c.empresa_id = {o['empresa_id']} AND cm.impuesto_tipo_id IS NOT NULL AND c.tabla_estado_registro_id = 5 LIMIT 1")
    if medio_ajeno:
        r = aj(e, 'cierres_fiscales_ajax.php', dict(base, cobranza_medio_id=medio_ajeno, retencion_certificado_nro=f'S{k}16-Y-{SUF}',
                                                    retencion_f_certificado=fecha_op(22), retencion_certificado_estado='RECIBIDO'))
        check(not ok_resp(r), f"[{k}] OP16: retención de un recibo de la empresa {otra[k]}: no encontrada", err(r))

    # ------------------------------------------------------------------------------------------- OP17
    caso(f'N4 OP17 retención con período de cómputo distinto del recibo [{k}]')
    f = ops['OP14']
    fv = dict(fid=f['factura'], comp_id=f['factura_comp'], total=TOTAL)
    ret = [('RET_IVA', 2100.0, f'S{k}17-IVA-{SUF}', None)]
    tes0 = saldos_tes(e)
    r = recibo(e, cli, [m_efectivo(e, TOTAL - 2100), m_ret(e, 'RET_IVA', 2100.0, ret[0][2], periodo=PERIODO_SIG)], [(f['factura_comp'], TOTAL)], fecha_op(23))
    verificar_operacion(e, 'OP17', rec=r, efectivo=TOTAL - 2100, retenciones=ret, tes0=tes0)
    per = val(f"SELECT retencion_periodo_computo FROM gestion__cobranzas_medios WHERE cobranza_id = {int(r.get('cob') or 0)} AND impuesto_tipo_id IS NOT NULL")
    check(per == PERIODO_SIG, f'[{k}] OP17: período de cómputo {PERIODO_SIG} (recibo de {PERIODO_OP})', f'{per}')
    s, est = saldo(f['factura_comp'])
    check(s == 0 and est == 40, f'[{k}] OP17: factura cancelada', f'saldo {s} estado {est}')
    ops['OP17'] = dict(cobranza=r.get('cob'), recibo_comp=r.get('comp_id'))

ctx_guardar(ctx)
