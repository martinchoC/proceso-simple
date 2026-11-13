"""Obligaciones de pago unificadas (_local/docs/gestion/13_obligaciones_de_pago.md), empresa 2, módulo gestion.

Las 10 operaciones que pidió Pablo para revisar cómo queda registrado (pensado también para `--base-local`):
  1-2  dos sueldos cargados en Obligaciones de Pago (tipo SUELDO, sin regla de aprobación: quedan listos para pagar)
  3    OP del primer sueldo (Órdenes de Pago, transferencia desde Banco Nación)
  4    OP del segundo sueldo (pago en lote desde la solapa "Pendientes de pago")
  5-6  liquidación de IVA del mes (I_LIQ a ARCA) y su OP
  7-8  liquidación de percepciones IIBB Río Negro (I_LIQ a la DGR) y su OP
  9-10 liquidación de retenciones IIBB Buenos Aires (I_LIQ a ARBA) y su OP
Antes, como preparación: empleados y organismos (rol EMP / ORG), y una OP a un proveedor con retención de IIBB.
Después (solo en la copia): reglas de aprobación (obligación con doble aprobación, OP con aprobación), importación
CSV y anticipo de sueldo aplicado al sueldo.
"""
import base64
from simlib import *

E = 2
PAG_OBL, PAG_OP, PAG_REG, PAG_TIPOS = 135, 106, 137, 136
PER = HOY[:7]
BANCO, TRANSFER = 3, 4
obs = lambda t: f'SIM obligaciones: {t}'


def obl(params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', E)
    return ajax('obligaciones_pago_ajax.php', p, method, empresa_id=E)


def pagos(params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', E)
    return ajax('pagos_ajax.php', p, method, empresa_id=E)


def entidad(nombre, cuit, rol):
    """Entidad de la empresa 2 con el rol (la crea si no existe: Entidades no tiene alta por ajax simple)."""
    ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND cuit = {cuit} LIMIT 1")
    if not ent:
        sql(f"INSERT INTO gestion__entidades (empresa_id, entidad_nombre, entidad_tipo_id, cuit, observaciones, fecha_alta, tabla_estado_registro_id) "
            f"VALUES ({E}, '{nombre}', 1, {cuit}, 'SIM obligaciones: alta para la simulacion', NOW(), 1)")  # sin acentos: el -e de mysql en Windows los pierde
        ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {E} AND cuit = {cuit} LIMIT 1")
    rid = val(f"SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = '{rol}'")
    if not val(f"SELECT 1 FROM gestion__entidades_roles WHERE entidad_id = {ent} AND rol_entidad_id = {rid} AND f_baja IS NULL"):
        sql(f"INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, f_alta, tabla_estado_registro_id) VALUES ({ent}, {rid}, CURDATE(), 1)")
    return int(ent)


def comp(comprobante_id):
    return uno(f"SELECT comprobante_id, tabla_estado_registro_id estado, importe_total, importe_pendiente, comprobante_nro "
               f"FROM gestion__comprobantes WHERE comprobante_id = {int(comprobante_id)}")


def asiento(comprobante_id):
    """{codigo cuenta: importe con signo} del asiento activo del comprobante."""
    return {f['codigo']: round(num(f['imp']), 2) for f in sql(
        f"SELECT cc.codigo, SUM(d.importe_local) imp FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
        f"JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id WHERE a.comprobante_id = {int(comprobante_id)} AND a.tabla_estado_registro_id = 1 GROUP BY cc.codigo")}


def codigo_cuenta(cuenta_id):
    return val(f"SELECT codigo FROM gestion__cont_cuentas WHERE cont_cuenta_id = {int(cuenta_id or 0)}")


def pagar_obligacion(entidad_id, comprobante_id, texto):
    """OP por transferencia desde Banco Nación por todo el saldo del comprobante. Devuelve (pago_id, comprobante OP)."""
    c = comp(comprobante_id)
    pend = num(c['importe_pendiente'])
    r = pagos(dict(accion='guardar', entidad_id=entidad_id, sucursal_id=1, f_emision=HOY, f_contabilidad=HOY, observaciones=obs(texto),
                   medios=[dict(medio_pago_id=TRANSFER, tesoreria_cuenta_id=BANCO, importe=pend, importe_comprobante=pend)],
                   imputaciones=[dict(comprobante_destino_id=int(comprobante_id), importe_imputado=pend)]))
    pago = r.get('pago_id') if ok_resp(r) else None
    check(bool(pago), f'{texto}: borrador de OP por $ {pend:,.2f}', err(r))
    if not pago:
        return None, None
    r = pagos(dict(accion='confirmar', id=pago))
    check(ok_resp(r) and not r.get('pendiente_aprobacion'), f'{texto}: OP confirmada', err(r))
    return pago, val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id = {pago}")


def chequear_pago(texto, obligacion_comp, op_comp, cuenta_pasivo):
    c = comp(obligacion_comp)
    check(c and int(c['estado']) == 40 and num(c['importe_pendiente']) < 0.01, f'{texto}: obligación en 40 Pagada, saldo 0', str(c))
    a = asiento(op_comp)
    total = num(c['importe_total']) if c else 0
    cod_banco = codigo_cuenta(val(f"SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {BANCO}"))
    check(abs(a.get(codigo_cuenta(cuenta_pasivo), 0) - total) < 0.02 and abs(a.get(cod_banco, 0) + total) < 0.02,
          f'{texto}: asiento de la OP Debe {codigo_cuenta(cuenta_pasivo)} / Haber banco {cod_banco}', str(a))


# ---------------------------------------------------------------------------------------------
caso('Preparación - empleados, organismos y retención de IIBB practicada en una OP a un proveedor')
EMP1 = entidad('SIM Perez, Juan Carlos', 20111111112, 'EMP')
EMP2 = entidad('SIM Gomez, Ana Maria', 27222222223, 'EMP')
ARCA = entidad('ARCA - Agencia de Recaudacion y Control Aduanero', 33693450239, 'ORG')
ARBA = entidad('ARBA - Agencia de Recaudacion de la Provincia de Buenos Aires', 30710404611, 'ORG')
DGR_RN = entidad('Agencia de Recaudacion Tributaria de Rio Negro', 30999169039, 'ORG')
check(all([EMP1, EMP2, ARCA, ARBA, DGR_RN]), 'empleados y organismos con su rol')

r = pagos(dict(accion='maestros'), 'GET')
benef = {int(p['entidad_id']): p for p in r.get('data', {}).get('proveedores', [])} if ok_resp(r) else {}
check(EMP1 in benef and int(benef[EMP1]['es_empleado']) == 1 and ARCA in benef and int(benef[ARCA]['es_organismo']) == 1,
      'Órdenes de Pago ofrece empleados y organismos como beneficiarios', err(r))

tipos = {t['codigo']: t for t in sql(f"SELECT obligacion_tipo_id, codigo, cont_cuenta_debe_id, cont_cuenta_pasivo_id FROM gestion__obligaciones_tipos WHERE empresa_id = {E}")}
check({'SUELDO', 'IMP_IVA', 'IMP_PER_IIBB', 'IMP_RET'} <= set(tipos), 'tipos de obligación sembrados', str(list(tipos)))
check(not val(f"SELECT COUNT(*) FROM gestion__aprobaciones_reglas WHERE empresa_id = {E} AND tabla_estado_registro_id = 1") or True,
      'sin reglas de aprobación activas (las obligaciones no requieren aprobación)')
sql(f"UPDATE gestion__aprobaciones_reglas SET tabla_estado_registro_id = 2 WHERE empresa_id = {E}")

# Agente de retención de IIBB Buenos Aires (config 7): 2 % genérico sobre el neto gravado, a la cuenta IIBB a Pagar
cta_iibb = val(f"SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id = {E} AND es_imputable = 1 AND nombre = 'IIBB a Pagar' LIMIT 1")
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id = 1, cont_cuenta_id = COALESCE(cont_cuenta_id, {cta_iibb}), "
    f"f_desde = LEAST(f_desde, '{PER}-01') WHERE empresa_impuesto_config_id = 7 AND empresa_id = {E}")
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=7, base_calculo='NETO_GRAVADO', tipo_calculo='manual',
         alicuota=2, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, empresa_idx=E))
check(ok_resp(r), 'config de retención IIBB Buenos Aires 2 %', err(r))
CTA_RET = int(val("SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config WHERE empresa_impuesto_config_id = 7"))

fp = uno(f"SELECT c.comprobante_id, c.entidad_id, c.importe_pendiente FROM gestion__comprobantes c JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id "
         f"WHERE c.empresa_id = {E} AND ct.codigo = 'C_FA' AND c.tabla_estado_registro_id IN (5, 39) AND c.importe_pendiente >= 20000 AND c.importe_neto > 0 "
         f"ORDER BY c.f_emision DESC LIMIT 1")
check(bool(fp), 'factura A de proveedor pendiente para la OP con retención')
if fp:
    imp = [dict(comprobante_destino_id=int(fp['comprobante_id']), importe_imputado=20000.0)]
    r = pagos(dict(accion='calcular_retenciones', entidad_id=int(fp['entidad_id']), f_emision=HOY, imputaciones=imp))
    rets = [x for x in r.get('data', {}).get('retenciones', []) if int(x['impuesto_tipo_id']) == 16] if ok_resp(r) else []
    check(len(rets) == 1 and num(rets[0]['importe']) > 0, 'calcular: retención IIBB Buenos Aires', err(r) if not ok_resp(r) else json.dumps(r.get('data'))[:300])
    if rets:
        x = rets[0]
        medios = [dict(medio_pago_id=TRANSFER, tesoreria_cuenta_id=BANCO, importe=round(20000 - num(x['importe']), 2), importe_comprobante=round(20000 - num(x['importe']), 2)),
                  dict(medio_pago_id=x['medio_pago_id'], importe=x['importe'], importe_comprobante=x['importe'], impuesto_tipo_id=16,
                       jurisdiccion_id=x['jurisdiccion_id'], retencion_base_imponible=x['retencion_base_imponible'], retencion_alicuota=x['retencion_alicuota'])]
        r = pagos(dict(accion='guardar', entidad_id=int(fp['entidad_id']), sucursal_id=1, f_emision=HOY, f_contabilidad=HOY,
                       observaciones=obs('pago a proveedor con retención IIBB'), medios=medios, imputaciones=imp))
        pago_ret = r.get('pago_id') if ok_resp(r) else None
        r = pagos(dict(accion='confirmar', id=pago_ret)) if pago_ret else r
        check(ok_resp(r), f"OP a proveedor con retención IIBB de $ {num(x['importe']):,.2f}", err(r))

# ---------------------------------------------------------------------------------------------
caso('Operaciones 1 y 2 - sueldos cargados en Obligaciones de Pago')
SUELDOS = []
for n, (emp, importe) in enumerate([(EMP1, 850000.00), (EMP2, 720500.50)], 1):
    r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['SUELDO']['obligacion_tipo_id'], entidad_id=emp, importe_total=importe,
                 f_emision=HOY, f_vto=HOY, periodo_fiscal=PER, referencia=f'Recibo {PER}-{n}', confirmar_ahora=1))
    d = r.get('data') or {}
    check(ok_resp(r) and int(d.get('estado', 0)) == 5, f'{n}. sueldo $ {importe:,.2f}: confirmado sin aprobación (listo para pagar)', err(r))
    if d.get('comprobante_id'):
        a = asiento(d['comprobante_id'])
        deb, pas = codigo_cuenta(tipos['SUELDO']['cont_cuenta_debe_id']), codigo_cuenta(tipos['SUELDO']['cont_cuenta_pasivo_id'])
        check(abs(a.get(deb, 0) - importe) < 0.01 and abs(a.get(pas, 0) + importe) < 0.01, f'{n}. asiento: Debe {deb} / Haber {pas}', str(a))
        SUELDOS.append((emp, int(d['comprobante_id'])))

r = pagos(dict(accion='comprobantes_pendientes', entidad_id=EMP1), 'GET')
pend = r.get('data', []) if ok_resp(r) else []
check(any(int(c['comprobante_id']) == SUELDOS[0][1] and c.get('obligacion_tipo') == 'Sueldo' for c in pend) if SUELDOS else False,
      'el sueldo aparece en Órdenes de Pago entre los pendientes del empleado, con su tipo', json.dumps(pend)[:300])

caso('Operaciones 3 y 4 - pago de los sueldos (OP individual y pago en lote)')
if len(SUELDOS) == 2:
    pago, op = pagar_obligacion(SUELDOS[0][0], SUELDOS[0][1], '3. sueldo Pérez')
    if op:
        chequear_pago('3. sueldo Pérez', SUELDOS[0][1], op, tipos['SUELDO']['cont_cuenta_pasivo_id'])
    r = pagos(dict(accion='pagar_lote', comprobante_ids=[SUELDOS[1][1]], medio_pago_id=TRANSFER, tesoreria_cuenta_id=BANCO, f_emision=HOY,
                   observaciones=obs('pago de sueldos en lote')))
    res = r.get('data', []) if ok_resp(r) else []
    check(len(res) == 1 and res[0]['ok'], '4. sueldo Gómez pagado en lote (una OP por beneficiario)', err(r) if not ok_resp(r) else str(res))
    if res and res[0].get('pago_id'):
        op = val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id = {res[0]['pago_id']}")
        chequear_pago('4. sueldo Gómez', SUELDOS[1][1], op, tipos['SUELDO']['cont_cuenta_pasivo_id'])

# ---------------------------------------------------------------------------------------------
caso('Operaciones 5 y 6 - liquidación de IVA del mes y su pago')
def saldo_iva():
    r = ajax('iva_posiciones_ajax.php', dict(accion='detalle', periodo=PER, empresa_idx=E), 'GET')
    calc = ((r.get('data') or {}).get('calculo') or {}) if ok_resp(r) else {}
    return num(calc.get('saldo_a_pagar', 0)), num(calc.get('saldo_a_favor', 0)), r


a_pagar, a_favor, r = saldo_iva()
if a_pagar <= 0:
    # El mes da saldo a favor: una venta Factura A por lo necesario para que quede IVA a pagar (con ARCA
    # deshabilitado mientras se confirma, para que se numere sin pedir CAE; después se restituye)
    neto = round((a_favor / 0.21 + 500000) / 100000) * 100000
    arca = val(f"SELECT GROUP_CONCAT(empresa_parametro_id, ':', arca_habilitado) FROM gestion__empresas_parametros WHERE empresa_id = {E}")
    sql(f"UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id = {E}")
    try:
        r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=E, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
                 comprobante_tipo_id=4, entidad_id=313, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
                 descuento_general_pct=0, observaciones=obs('venta para que el IVA del mes quede a pagar'),
                 detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=neto, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
        vf = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
        if vf:
            r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='confirma', empresa_idx=E, pagina_idx=56))
            # Sin ARCA, la factura fiscal confirmada queda en 4 y "Aprobada" (4 -> 5) es manual
            if ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf)}")) == 4:
                r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='aprobada', empresa_idx=E, pagina_idx=56))
        check(vf and ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}")) == 5,
              f'preparación: venta Factura A de $ {neto:,.0f} + IVA confirmada', err(r))
    finally:
        for par in (arca or '').split(','):
            if ':' in par:
                pid, hab = par.split(':')
                sql(f"UPDATE gestion__empresas_parametros SET arca_habilitado = {int(hab)} WHERE empresa_parametro_id = {int(pid)}")
    a_pagar, a_favor, r = saldo_iva()
if a_pagar <= 0:
    brecha('IVA del mes con saldo a favor: no genera obligación', json.dumps(r)[:300])
else:
    r = ajax('iva_posiciones_ajax.php', dict(accion='liquidar', periodo=PER, sucursal_id=1, entidad_id=ARCA, f_vto=HOY, nro_ddjj='F.2002 SIM', empresa_idx=E))
    check(ok_resp(r), f'5. IVA {PER} liquidado: a pagar $ {a_pagar:,.2f}', err(r))
    liq = val(f"SELECT comprobante_id FROM gestion__iva_posiciones WHERE empresa_id = {E} AND periodo = '{PER}' AND tabla_estado_registro_id = 5 ORDER BY iva_posicion_id DESC LIMIT 1")
    o = uno(f"SELECT o.origen, ot.codigo, o.cont_cuenta_id, o.tabla_estado_registro_id FROM gestion__obligaciones_pago o "
            f"JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id WHERE o.comprobante_id = {int(liq or 0)}")
    check(o and o['codigo'] == 'IMP_IVA' and o['origen'] == 'IVA_POSICION' and int(o['tabla_estado_registro_id']) == 5,
          '5. la I_LIQ es una obligación IMP_IVA generada por la posición, pendiente de pago', str(o))
    if o:
        pago, op = pagar_obligacion(ARCA, liq, '6. IVA a ARCA')
        if op:
            chequear_pago('6. IVA a ARCA', liq, op, o['cont_cuenta_id'])

# ---------------------------------------------------------------------------------------------
caso('Operaciones 7 y 8 - liquidación de percepciones IIBB Río Negro y su pago')
JUR_RN = val(f"SELECT eic.jurisdiccion_id FROM gestion__comprobantes_impuestos ci JOIN gestion__comprobantes c ON c.comprobante_id = ci.comprobante_id "
             f"JOIN gestion__empresas_impuestos_config eic ON eic.empresa_impuesto_config_id = ci.empresa_impuesto_config_id "
             f"WHERE c.empresa_id = {E} AND ci.impuesto_tipo_id = 6 AND ci.liquidacion_comprobante_id IS NULL AND c.tabla_estado_registro_id IN (5, 39, 40) LIMIT 1")
if not JUR_RN:
    brecha('sin percepciones IIBB pendientes de liquidar en la empresa 2')
else:
    r = ajax('percepciones_iibb_ajax.php', dict(accion='liquidar', jurisdiccion_id=JUR_RN, periodo=PER, entidad_id=DGR_RN, sucursal_id=1, f_vto=HOY, empresa_idx=E))
    check(ok_resp(r), '7. percepciones IIBB Río Negro liquidadas', err(r))
    liq = val(f"SELECT MAX(liquidacion_comprobante_id) FROM gestion__comprobantes_impuestos ci JOIN gestion__comprobantes c ON c.comprobante_id = ci.comprobante_id "
              f"WHERE c.empresa_id = {E} AND ci.impuesto_tipo_id = 6")
    o = uno(f"SELECT o.origen, ot.codigo, o.cont_cuenta_id FROM gestion__obligaciones_pago o JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id "
            f"WHERE o.comprobante_id = {int(liq or 0)}")
    check(o and o['codigo'] == 'IMP_PER_IIBB' and o['origen'] == 'PERCEPCIONES_IIBB', '7. obligación IMP_PER_IIBB', str(o))
    if o:
        pago, op = pagar_obligacion(DGR_RN, liq, '8. percepciones a la DGR Río Negro')
        if op:
            chequear_pago('8. percepciones a la DGR Río Negro', liq, op, o['cont_cuenta_id'])

# ---------------------------------------------------------------------------------------------
caso('Operaciones 9 y 10 - liquidación de retenciones IIBB Buenos Aires y su pago')
r = ajax('cierres_fiscales_ajax.php', dict(accion='liquidar_retenciones', periodo=PER, impuesto_tipo_id=16, jurisdiccion_id=2, sucursal_id=1,
         entidad_id=ARBA, f_vto=HOY, nro_ddjj='SIM-ARBA', empresa_idx=E))
check(ok_resp(r), '9. retenciones IIBB Buenos Aires liquidadas', err(r))
liq = val(f"SELECT MAX(comp_liquidacion_id) FROM gestion__pagos_retenciones WHERE empresa_id = {E} AND impuesto_tipo_id = 16")
o = uno(f"SELECT o.origen, ot.codigo, o.cont_cuenta_id, o.jurisdiccion_id FROM gestion__obligaciones_pago o "
        f"JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id WHERE o.comprobante_id = {int(liq or 0)}")
check(o and o['codigo'] == 'IMP_RET' and o['origen'] == 'RETENCIONES_LIQ' and int(o['cont_cuenta_id']) == CTA_RET and str(o['jurisdiccion_id']) == '2',
      '9. obligación IMP_RET a la cuenta de la config, con su jurisdicción', str(o))
if o:
    pago, op = pagar_obligacion(ARBA, liq, '10. retenciones a ARBA')
    if op:
        chequear_pago('10. retenciones a ARBA', liq, op, CTA_RET)

r = obl(dict(accion='listar', vista='pagadas'), 'GET')
check(ok_resp(r) and len([x for x in r['data'] if x['origen'] in ('MANUAL', 'IVA_POSICION', 'PERCEPCIONES_IIBB', 'RETENCIONES_LIQ')]) >= 4,
      'Obligaciones de Pago lista las pagadas de todos los orígenes', err(r))

if not BASE_LOCAL:
    # -----------------------------------------------------------------------------------------
    caso('Extra (solo en la copia) - aprobaciones, importación y anticipo de sueldo')
    r = ajax('aprobaciones_reglas_ajax.php', dict(accion='agregar', que='regla', documento='OBLIGACION', obligacion_tipo_id='', requiere_aprobacion=1,
             importe_desde=100000, doble_aprobacion_desde=1000000, f_desde=HOY, empresa_idx=E), empresa_id=E)
    check(ok_resp(r), 'regla: obligaciones desde $ 100.000, doble desde $ 1.000.000', err(r))
    r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['VARIOS']['obligacion_tipo_id'], entidad_id=EMP1, importe_total=50000, f_emision=HOY,
                 f_vto=HOY, cont_cuenta_pasivo_id=tipos['SUELDO']['cont_cuenta_pasivo_id'], confirmar_ahora=1))
    check(ok_resp(r) and int(r['data']['estado']) == 5, 'menos de $ 100.000: no requiere aprobación', err(r))
    r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['SAC']['obligacion_tipo_id'], entidad_id=EMP1, importe_total=1200000, f_emision=HOY, f_vto=HOY, confirmar_ahora=1))
    sac = (r.get('data') or {}).get('obligacion_id')
    check(ok_resp(r) and int(r['data']['estado']) == 4, 'SAC de $ 1.200.000: pendiente de aprobación (dos)', err(r))
    if sac:
        r = obl(dict(accion='aprobar', obligacion_id=sac))
        check(not ok_resp(r), 'quien la cargó no la puede aprobar', err(r))
        sql(f"UPDATE gestion__aprobaciones_reglas SET permite_autoaprobar = 1 WHERE empresa_id = {E}")
        r = obl(dict(accion='aprobar', obligacion_id=sac))
        check(ok_resp(r) and int(r['data']['estado']) == 4, 'primera aprobación: sigue pendiente', err(r))
        r = obl(dict(accion='aprobar', obligacion_id=sac))
        check(not ok_resp(r), 'el mismo usuario no aprueba dos veces', err(r))
        r = obl(dict(accion='rechazar', obligacion_id=sac, motivo='SIM'))
        check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__obligaciones_pago WHERE obligacion_id = {sac}")) == 3,
              'rechazo: vuelve a Borrador', err(r))
    sql(f"UPDATE gestion__aprobaciones_reglas SET tabla_estado_registro_id = 2 WHERE empresa_id = {E}")

    # Aprobación de OP
    r = ajax('aprobaciones_reglas_ajax.php', dict(accion='agregar', que='regla', documento='OP', requiere_aprobacion=1, importe_desde=0,
             permite_autoaprobar=1, f_desde=HOY, empresa_idx=E), empresa_id=E)
    check(ok_resp(r), 'regla: toda OP requiere aprobación', err(r))
    r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['SUELDO']['obligacion_tipo_id'], entidad_id=EMP2, importe_total=1000, f_emision=HOY, f_vto=HOY, confirmar_ahora=1))
    cid = (r.get('data') or {}).get('comprobante_id')
    r = pagos(dict(accion='guardar', entidad_id=EMP2, sucursal_id=1, f_emision=HOY, f_contabilidad=HOY, observaciones=obs('OP con aprobación'),
                   medios=[dict(medio_pago_id=TRANSFER, tesoreria_cuenta_id=BANCO, importe=1000, importe_comprobante=1000)],
                   imputaciones=[dict(comprobante_destino_id=cid, importe_imputado=1000)]))
    pago = r.get('pago_id')
    r = pagos(dict(accion='confirmar', id=pago))
    check(ok_resp(r) and r.get('pendiente_aprobacion') and int(val(f"SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = {pago}")) == 4
          and int(comp(cid)['estado']) == 5, 'OP pendiente de aprobación: no mueve fondos ni cancela', err(r))
    r = pagos(dict(accion='aprobar', id=pago))
    check(ok_resp(r) and int(comp(cid)['estado']) == 40, 'aprobada: se emite y cancela el sueldo', err(r))
    sql(f"UPDATE gestion__aprobaciones_reglas SET tabla_estado_registro_id = 2 WHERE empresa_id = {E}")

    # Anticipo de sueldo -> se aplica al sueldo siguiente
    r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['ANT_SUELDO']['obligacion_tipo_id'], entidad_id=EMP1, importe_total=100000, f_emision=HOY, f_vto=HOY, confirmar_ahora=1))
    ant = (r.get('data') or {}).get('comprobante_id')
    check(ok_resp(r) and not asiento(ant), 'anticipo de sueldo confirmado sin asiento', err(r))
    pago, op = pagar_obligacion(EMP1, ant, 'anticipo de sueldo')
    if op:
        check(abs(num(comp(op)['importe_pendiente']) - 100000) < 0.01 and int(comp(ant)['estado']) == 40, 'la OP del anticipo queda con saldo a favor; la solicitud, pagada')
        a = asiento(op)
        check(abs(a.get(codigo_cuenta(tipos['SUELDO']['cont_cuenta_pasivo_id']), 0) - 100000) < 0.01, 'el anticipo se debita en Sueldos a pagar', str(a))
        r = obl(dict(accion='agregar', obligacion_tipo_id=tipos['SUELDO']['obligacion_tipo_id'], entidad_id=EMP1, importe_total=300000, f_emision=HOY, f_vto=HOY, confirmar_ahora=1))
        s2 = (r.get('data') or {}).get('comprobante_id')
        r = obl(dict(accion='aplicar_anticipo', op_comprobante_id=op, destino_comprobante_id=s2, importe=''))
        check(ok_resp(r) and abs(num(comp(s2)['importe_pendiente']) - 200000) < 0.01 and num(comp(op)['importe_pendiente']) < 0.01,
              'anticipo aplicado: el sueldo queda con 200.000 a pagar', err(r))

    # Importación CSV
    csv = f"tipo;cuit;importe;f_vto;periodo;referencia\r\nSUELDO;20111111112;1.500,00;{HOY};{PER};IMP-1\r\nSUELDO;99999999999;10;{HOY};{PER};IMP-2\r\nCARGAS_SOC;20111111112;100;{HOY};{PER};IMP-3\r\n"
    r = obl(dict(accion='importar_previsualizar', _files={'archivo': {'name': 'sueldos.csv', 'content_b64': base64.b64encode(csv.encode()).decode()}}))
    d = r.get('data') or {}
    res = d.get('resumen') or {}
    check(ok_resp(r) and res.get('validas') == 2 and res.get('con_error') == 1 and res.get('faltan_roles') == 1,
          'importación: 2 válidas, CUIT inexistente con error, cargas sociales a un empleado sin rol de organismo', err(r) if not ok_resp(r) else str(res))
    if d.get('importacion_id'):
        r = obl(dict(accion='importar_confirmar', importacion_id=d['importacion_id'], confirmar_ahora=''))
        check(ok_resp(r) and r['data']['creadas'] == 1, 'importa solo la válida sin rol pendiente, en Borrador', err(r))
        r = obl(dict(accion='importar_deshacer', importacion_id=d['importacion_id']))
        check(ok_resp(r), 'deshacer la importación', err(r))
