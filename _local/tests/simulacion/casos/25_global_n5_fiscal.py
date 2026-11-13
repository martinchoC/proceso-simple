"""Prueba global de Gestión Normal — Nivel 5 (OP18–OP20) y cierre de la corrida.

OP18 Posición de IVA del mes de las operaciones (débito, crédito, retenciones de IVA sufridas sin la de OP17) y su
liquidación; OP19 cierre de IIBB local en Control Fiscal: bloquea anular un recibo con retención IIBB del mes, se reabre
con motivo y se anula y se vuelve a cobrar; OP20 cuadre y registro de Control Fiscal. Cierre: retenciones = saldo de su
cuenta contable, cuentas corrientes y empresas origen sin cambios.
Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§5 N5 y §6).
"""
import random

from globlib import *

ctx, EMP = empresas_ctx()
SUF = f'{HOY.replace("-", "")}{random.randint(100, 999)}'


def ret_lineas(e, impuesto=None, periodo=None, jur_id=None):
    w = [f"co.empresa_id = {e['empresa_id']}", "co.tabla_estado_registro_id = 5", "mp.medio_pago_tipo = 'RETENCION'"]
    if impuesto:
        w.append(f"cm.impuesto_tipo_id = {impuesto}")
    if periodo:
        w.append(f"COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(co.f_contabilidad, '%Y-%m')) = '{periodo}'")
    if jur_id is not None:
        w.append(f"IFNULL(cm.jurisdiccion_id, 0) = {int(jur_id)}")
    return round(num(val(f"SELECT IFNULL(SUM(cm.importe), 0) FROM gestion__cobranzas_medios cm JOIN gestion__cobranzas co ON co.cobranza_id = cm.cobranza_id "
                         f"JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id WHERE {' AND '.join(w)}")), 2)


def saldo_cuenta(cuenta_id):
    return round(num(val(f"SELECT IFNULL(SUM(d.importe_local), 0) FROM gestion__cont_asientos_detalles d JOIN gestion__cont_asientos a "
                         f"ON a.cont_asiento_id = d.cont_asiento_id WHERE d.cuenta_id = {int(cuenta_id)} AND a.tabla_estado_registro_id NOT IN (2, 4, 6, 38)")), 2)


def cf(e, params, method='POST'):
    p = dict(params)
    p.setdefault('pagina_idx', pg(625))
    return aj(e, 'cierres_fiscales_ajax.php', p, method)


for k, e in EMP.items():
    ops = ctx['ops'][k]
    jur_local = jur(e['provincia_local'])

    # ------------------------------------------------------------------------------------------- OP18
    caso(f'N5 OP18 Posición de IVA de {PERIODO_OP} [{k}]')
    if k == 'S':   # crédito fiscal de servicios: una compra de la empresa de servicios en el mes
        fa_prov = tipo_comp(e, 'FC', 'A', '+', 'Factura%')
        r, fid, t = fp_alta_confirmar(e, fa_prov, [(e['productos']['SRV-HORA'], 10, 3000.0)], fecha_op(24))
        check(fid and ok_resp(r), f"[S] OP18: compra de servicios confirmada ({t['total']})", err(r))
    debito = round(num(val(f"SELECT IFNULL(SUM(c.importe_iva * c.signo), 0) FROM gestion__comprobantes c WHERE c.empresa_id = {e['empresa_id']} "
                           f"AND c.tabla_origen_id = 80 AND c.tabla_estado_registro_id IN (5, 39, 40) AND DATE_FORMAT(c.f_emision, '%Y-%m') = '{PERIODO_OP}'")), 2)
    credito = round(num(val(f"SELECT IFNULL(SUM(c.importe_iva * c.signo), 0) FROM gestion__comprobantes c WHERE c.empresa_id = {e['empresa_id']} "
                            f"AND c.tabla_origen_id = 84 AND c.tabla_estado_registro_id IN (5, 39, 40) AND DATE_FORMAT(c.f_contabilidad, '%Y-%m') = '{PERIODO_OP}'")), 2)
    ret_iva = ret_lineas(e, 5, PERIODO_OP)
    ret_iva_sig = ret_lineas(e, 5, PERIODO_SIG)
    check(ret_iva_sig == 2100.0, f'[{k}] OP18: la retención de OP17 computa en {PERIODO_SIG}', f'{ret_iva_sig}')
    r = aj(e, 'iva_posiciones_ajax.php', dict(accion='detalle', periodo=PERIODO_OP, pagina_idx=pg(617)), 'GET')
    d = r.get('data') if ok_resp(r) else None
    pos = (d or {}).get('posicion') or (d or {}).get('calculo') or d or {}
    check(d is not None, f'[{k}] OP18: detalle de la posición', err(r))
    for clave, esperado in (('debito_fiscal', debito), ('credito_fiscal', credito), ('retenciones_iva', ret_iva)):
        check(abs(num(pos.get(clave)) - esperado) < 0.01, f'[{k}] OP18: {clave} = {esperado}', f'{pos.get(clave)} ({list(pos)[:12]})')
    r = aj(e, 'iva_posiciones_ajax.php', dict(accion='liquidar', periodo=PERIODO_OP, sucursal_id=e['sucursal_id'], entidad_id=e['proveedor_id'],
                                              nro_ddjj=f'SIM-{k}-{SUF}', pagina_idx=pg(617)))
    check(ok_resp(r), f'[{k}] OP18: liquidar la posición', err(r))
    p = uno(f"SELECT debito_fiscal, credito_fiscal, retenciones_iva, saldo_a_pagar, saldo_a_favor, comprobante_id, cont_asiento_id "
            f"FROM gestion__iva_posiciones WHERE empresa_id = {e['empresa_id']} AND periodo = '{PERIODO_OP}' AND tabla_estado_registro_id = 5 ORDER BY 1 DESC LIMIT 1")
    a_pagar = round(debito - credito - ret_iva, 2)
    check(p and abs(num(p['saldo_a_pagar']) - max(a_pagar, 0)) < 0.01 and abs(num(p['saldo_a_favor']) - max(-a_pagar, 0)) < 0.01,
          f'[{k}] OP18: posición liquidada: a pagar {max(a_pagar, 0)} / a favor {max(-a_pagar, 0)}', str(p))
    if p and a_pagar > 0:
        check(int(p['comprobante_id'] or 0) > 0, f'[{k}] OP18: I_LIQ por el saldo a pagar', str(p))
    cta_ret_iva = int(medio(e, 'RET_IVA')['cont_cuenta_id'])
    check(saldo_cuenta(cta_ret_iva) == ret_iva_sig, f'[{k}] OP18: la cuenta de Retenciones de IVA queda solo con lo de {PERIODO_SIG} ({ret_iva_sig})',
          f'{saldo_cuenta(cta_ret_iva)}')
    cierre_iva = val(f"SELECT COUNT(*) FROM gestion__impuestos_periodos_cierres WHERE empresa_id = {e['empresa_id']} AND periodo = '{PERIODO_OP}' "
                     f"AND impuesto_tipo_id = 7 AND tabla_estado_registro_id = 5")
    check(cierre_iva == '1', f'[{k}] OP18: la liquidación cierra IVA de {PERIODO_OP}', f'{cierre_iva}')
    r = aj(e, 'cobranzas_ajax.php', dict(accion='anular', id=ops['OP02']['cobranza'], motivo='SIM: período de IVA liquidado', pagina_idx=pg(605)))
    check(not ok_resp(r) and 'cerrad' in err(r).lower(), f'[{k}] OP18: no se puede anular un recibo con retención de IVA del período liquidado', err(r))

    # ------------------------------------------------------------------------------------------- OP19
    caso(f'N5 OP19 cierre de IIBB {e["provincia_local"]} de {PERIODO_OP} [{k}]')
    r = cf(e, dict(accion='cerrar', impuesto_tipo_id=9, jurisdiccion_id=jur_local, periodo=PERIODO_OP, observaciones='SIM OP19'))
    cierre = (r.get('data') or {}).get('cierre_id') if ok_resp(r) else None
    check(cierre, f'[{k}] OP19: cerrar IIBB del período', err(r))
    op4 = ops['OP04']
    r = aj(e, 'cobranzas_ajax.php', dict(accion='anular', id=op4['cobranza'], motivo='SIM OP19', pagina_idx=pg(605)))
    check(not ok_resp(r) and 'cerrad' in err(r).lower(), f'[{k}] OP19: anular el recibo de OP04 (retención IIBB del mes): bloqueado', err(r))
    check(val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id = {op4['cobranza']}") == '5', f'[{k}] OP19: el recibo sigue confirmado')
    r = cf(e, dict(accion='reabrir', cierre_id=cierre or 0, motivo=''))
    check(not ok_resp(r), f'[{k}] OP19: reabrir sin motivo: rechazado', err(r))
    r = cf(e, dict(accion='reabrir', cierre_id=cierre or 0, motivo='SIM OP19: corrección de un recibo'))
    check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__impuestos_periodos_cierres WHERE cierre_id = {int(cierre or 0)}") == '38',
          f'[{k}] OP19: reabrir con motivo (38)', err(r))
    r = aj(e, 'cobranzas_ajax.php', dict(accion='anular', id=op4['cobranza'], motivo='SIM OP19', pagina_idx=pg(605)))
    check(ok_resp(r), f'[{k}] OP19: reabierto, el recibo se anula', err(r))
    s, est = saldo(op4['factura_comp'])
    check(s == 121000.0 and est == 5, f'[{k}] OP19: la factura de OP04 vuelve a 5', f'saldo {s} estado {est}')
    rets = [('RET_IIBB', 3000.0, op4['certs'][0], jur_local)]
    tes0 = saldos_tes(e)
    rr = recibo(e, e['clientes']['AGENTE'], [m_efectivo(e, 118000.0), m_ret(e, 'RET_IIBB', 3000.0, op4['certs'][0], jur=jur_local)],
                [(op4['factura_comp'], 121000.0)], fecha_op(25))
    verificar_operacion(e, 'OP19 (nuevo recibo)', rec=rr, efectivo=118000.0, retenciones=rets, tes0=tes0)
    check(saldo(op4['factura_comp']) == (0.0, 40), f'[{k}] OP19: factura cobrada otra vez (40)')
    r = cf(e, dict(accion='cerrar', impuesto_tipo_id=9, jurisdiccion_id=jur_local, periodo=PERIODO_OP, observaciones='SIM OP19 recierre'))
    check(ok_resp(r), f'[{k}] OP19: se vuelve a cerrar el período', err(r))

    # ------------------------------------------------------------------------------------------- OP20
    caso(f'N5 OP20 cuadre y registro de Control Fiscal [{k}]')
    r = cf(e, dict(accion='cuadre', desde=PERIODO_OP, hasta=PERIODO_SIG), 'GET')
    d = r.get('data') if ok_resp(r) else None
    check(d is not None, f'[{k}] OP20: cuadre del período', err(r))
    if d:
        check(not d.get('diferencias'), f'[{k}] OP20: sin diferencias entre retenciones y contabilidad', str(d.get('diferencias'))[:400])
        for imp in (4, 5, 21):
            filas = [i for i in d.get('impuestos', []) if int(i['impuesto_tipo_id']) == imp]
            check(filas and all(abs(num(i.get('diferencia'))) < 0.01 for i in filas), f'[{k}] OP20: impuesto {imp} cuadra por cuenta', str(filas)[:300])
    r = cf(e, dict(accion='registro', desde=PERIODO_OP, hasta=PERIODO_SIG, circuito='RET_SUFRIDA'), 'GET')
    d = r.get('data') if ok_resp(r) else None
    check(d is not None, f'[{k}] OP20: registro de retenciones sufridas', err(r))
    if d:
        total_reg = round(sum(num(x['importe']) for x in d['filas']), 2)
        total_db = ret_lineas(e)
        check(abs(total_reg - total_db) < 0.01, f'[{k}] OP20: el registro suma lo mismo que las retenciones vigentes ({total_db})', f'{total_reg}')
        certs = {x['certificado'] for x in d['filas'] if x.get('certificado')}
        check(not any(c.startswith('SB' if k == 'S' else 'SS') for c in certs), f'[{k}] OP20: el registro no muestra retenciones de la otra empresa', str(sorted(certs))[:300])

    # ------------------------------------------------------------------------------------------- cierre
    caso(f'N5 cierre de la corrida [{k}]')
    for cod, imp in (('RET_GAN', 21), ('RET_IVA', 5)):
        cta = int(medio(e, cod)['cont_cuenta_id'])
        esperado = ret_lineas(e, imp) - (ret_lineas(e, imp, PERIODO_OP) if imp == 5 else 0)
        check(saldo_cuenta(cta) == round(esperado, 2), f'[{k}] cuenta de {cod} = retenciones vigentes no liquidadas ({esperado})', f'{saldo_cuenta(cta)}')
    for cod in (['DGR_NQN'] if k == 'S' else ['DGR_RN', 'DGR_NQN', 'ARBA']):
        j = jur(cod)
        check(saldo_cuenta(cuenta_ret_iibb(e, j)) == ret_lineas(e, 4, jur_id=j), f'[{k}] cuenta de IIBB {cod} = sus retenciones ({ret_lineas(e, 4, jur_id=j)})',
              f'{saldo_cuenta(cuenta_ret_iibb(e, j))}')
    for c, cli in e['clientes'].items():
        saldo_cli = ctacte_cliente(e, cli)
        check(saldo_cli == 0, f'[{k}] cuenta corriente de {c} en 0', f'{saldo_cli}')
    r = aj(e, 'clientes_ctacte_ajax.php', dict(accion='listar', entidad_id=e['clientes']['AGENTE'], pagina_idx=pg(603)), 'GET')
    check(ok_resp(r) or isinstance(r, list) or (isinstance(r, dict) and 'data' in r), f'[{k}] la pantalla de cuenta corriente responde', err(r))

caso('N5 empresas origen de la copia sin cambios')
for eid, antes in (ctx.get('foto_origen') or {}).items():
    ahora = foto_empresa(int(eid))
    dif = {t: (antes[t], ahora[t]) for t in antes if antes[t] != ahora[t]}
    check(not dif, f'empresa {eid}: sin cambios por la prueba', str(dif))

ctx_guardar(ctx)
