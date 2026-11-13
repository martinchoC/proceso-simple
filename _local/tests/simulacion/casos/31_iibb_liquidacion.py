"""Liquidación mensual de Ingresos Brutos (Control Fiscal, página 125, solapa Ingresos Brutos), empresa 2, módulo gestion.

En la copia: aplica 20261111_iibb_liquidacion_mensual_01.sql, deshabilita ARCA, asigna cuentas a los conceptos IIBB_* y
da de alta por la pantalla dos inscripciones de Convenio Multilateral (Buenos Aires 0,6 y Río Negro 0,4) con sus
actividades. Vende una Factura A en el mes y liquida Buenos Aires (base repartida en dos actividades, una con mínimo),
controla I_LIQ, asiento, cierre del período y bloqueos, la anula, y liquida Río Negro solo por el mínimo.
"""
from simlib import *

_s = '20261111_iibb_liquidacion_mensual_01.sql'
_r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                    stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
if _r.returncode != 0:
    raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))

E = 2
PER = HOY[:7]
sql(f"UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id = {E}")
# El numerador de la Factura A del PV 1 puede estar atrás de los datos locales: al día en la copia
sql(f"""UPDATE gestion__comprobantes_numeradores n SET n.ultimo_numero = GREATEST(n.ultimo_numero,
        (SELECT IFNULL(MAX(v.comprobante_nro), 0) FROM gestion__ventas_facturas v WHERE v.empresa_id = n.empresa_id
         AND v.punto_venta_id = n.punto_venta_id AND v.comprobante_tipo_id = n.comprobante_tipo_id))
        WHERE n.empresa_id = {E} AND n.punto_venta_id = 1 AND n.comprobante_tipo_id = 4""")
sql(f"UPDATE gestion__iibb_posiciones SET tabla_estado_registro_id = 38 WHERE empresa_id = {E}")
sql(f"UPDATE gestion__impuestos_periodos_cierres SET tabla_estado_registro_id = 38 WHERE empresa_id = {E} AND periodo = '{PER}'")
sql(f"UPDATE gestion__empresas_iibb_inscripciones SET tabla_estado_registro_id = 2 WHERE empresa_id = {E}")
CTA = {}
for concepto, origen in (('IIBB_GASTO', 'GASTOS_GENERALES'), ('IIBB_A_PAGAR', 'IVA_A_PAGAR'), ('IIBB_SALDO_FAVOR', 'IVA_SALDO_FAVOR')):
    c = int(val(f"SELECT cont_cuenta_id FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = {E} AND concepto = '{origen}'") or 0)
    sql(f"UPDATE gestion__empresas_cuentas_conceptos SET cont_cuenta_id = {c or 'NULL'} WHERE empresa_id = {E} AND concepto = '{concepto}'")
    CTA[concepto] = c
BA = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_tipo_id = 2 AND jurisdiccion_nombre LIKE 'Buenos Aires%' LIMIT 1"))
RN = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_tipo_id = 2 AND jurisdiccion_nombre LIKE '%Negro%' LIMIT 1"))
CM = int(val("SELECT iibb_regimen_id FROM gestion__iibb_regimenes WHERE iibb_regimen_codigo = 'CONVENIO_MULTILATERAL'"))
ORG = 5765


def cf(params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', E)
    return ajax('cierres_fiscales_ajax.php', p, method)


def resumen():
    r = cf(dict(accion='iibb_resumen', periodo=PER), 'GET')
    return r['data'] if ok_resp(r) else None


def fila(res, jur):
    return next((f for f in (res or {}).get('filas', []) if int(f['jurisdiccion_id']) == jur), None)


def venta(neto, confirmar=True):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=E, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=4, entidad_id=313, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, observaciones='SIM IIBB',
             detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=neto, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
    vf = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    if vf and confirmar:
        r = confirmar_venta(vf)
    return vf, r


def confirmar_venta(vf):
    """Borrador -> 4 (confirma) -> 5 (aprobada, sin ARCA)."""
    r = None
    for acc in ('confirma', 'aprobada'):
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js=acc, empresa_idx=E, pagina_idx=56))
        if not ok_resp(r):
            break
    return r


# ------------------------------------------------------------------
caso('IIBB: maestros, inscripciones y actividades')
r = cf(dict(accion='maestros'), 'GET')
check(ok_resp(r) and r['data']['puede_liquidar_iibb'] and r['data']['iibb_disponible'], 'maestros: puede liquidar IIBB y la tabla existe', err(r))
check(all(CTA.values()), 'conceptos IIBB_* con cuenta en la copia', str(CTA))
INS = {}
for jur, coef in ((BA, '0,6'), (RN, '0,4')):
    r = cf(dict(accion='agregar_inscripcion', jurisdiccion_id=jur, iibb_regimen_id=CM, nro_inscripcion='901-SIM', coeficiente_cm=coef,
                f_desde='2020-01-01', es_sede=1 if jur == BA else 0))
    check(ok_resp(r), f'inscripción CM en jurisdicción {jur} (coef. {coef})', err(r))
    INS[jur] = int(r['data']['inscripcion_id']) if ok_resp(r) else 0

r = cf(dict(accion='agregar_actividad', inscripcion_id=INS[BA], codigo='461000', descripcion='Comercio mayorista SIM', alicuota='3,5'))
check(ok_resp(r), 'actividad BA 461000 al 3,5 % (primera = principal)', err(r))
A1 = int(r['data']['actividad_id']) if ok_resp(r) else 0
r = cf(dict(accion='agregar_actividad', inscripcion_id=INS[BA], codigo='620100', descripcion='Servicios informáticos SIM', alicuota='5', minimo_mensual='9000'))
check(ok_resp(r), 'actividad BA 620100 al 5 % con mínimo 9.000', err(r))
A2 = int(r['data']['actividad_id']) if ok_resp(r) else 0
r = cf(dict(accion='agregar_actividad', inscripcion_id=INS[BA], codigo='461000', descripcion='Repetida', alicuota='4'))
check(not ok_resp(r) and 'superpuesta' in err(r), 'misma actividad con vigencia superpuesta se rechaza', err(r))
r = cf(dict(accion='agregar_actividad', inscripcion_id=INS[BA], descripcion='Mal', alicuota='150'))
check(not ok_resp(r) and 'alícuota' in err(r).lower(), 'alícuota fuera de 0–100 se rechaza', err(r))
r = cf(dict(accion='agregar_actividad', inscripcion_id=INS[RN], codigo='461000', descripcion='Comercio RN SIM', alicuota='2', minimo_mensual='500'))
check(ok_resp(r), 'actividad RN al 2 % con mínimo 500', err(r))
prin = sql(f"SELECT actividad_id FROM gestion__empresas_iibb_actividades WHERE inscripcion_id = {INS[BA]} AND es_principal = 1 AND tabla_estado_registro_id = 1")
check(len(prin) == 1 and int(prin[0]['actividad_id']) == A1, 'una sola principal por inscripción (la primera)', str(prin))

# ------------------------------------------------------------------
caso('IIBB: base del mes y propuesta')
antes = resumen()
vf, r = venta(2000000)
check(vf and ok_resp(r), 'venta Factura A de $2.000.000 + IVA confirmada en el mes', err(r))
res = resumen()
check(res and antes and abs(res['base_total'] - antes['base_total'] - 2000000) < 0.01, 'la base total sube el neto de la venta (sin IVA)',
      f"{antes and antes['base_total']} -> {res and res['base_total']}")
fba = fila(res, BA)
check(fba and abs(fba['base_calculada'] - round(res['base_total'] * 0.6, 2)) < 0.01, 'BA: base calculada = total × 0,6', str(fba and fba['base_calculada']))
check(fba and fba['actividades'][0]['actividad_id'] == A1 and abs(fba['actividades'][0]['base_imponible'] - fba['base_calculada']) < 0.01
      and fba['actividades'][1]['base_imponible'] == 0, 'la propuesta pone la base en la actividad principal', str(fba and fba['actividades']))
check(fba and abs(fba['actividades'][1]['impuesto_determinado'] - 9000) < 0.01, 'actividad sin base paga el mínimo (9.000)')
ret_sql = num(val(f"""SELECT IFNULL(SUM(cm.importe_comprobante), 0) FROM gestion__cobranzas co JOIN gestion__cobranzas_medios cm ON cm.cobranza_id = co.cobranza_id
    JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id AND mp.medio_pago_tipo = 'RETENCION'
    WHERE co.empresa_id = {E} AND co.tabla_estado_registro_id = 5 AND COALESCE(cm.impuesto_tipo_id, mp.impuesto_tipo_id) = 4 AND cm.jurisdiccion_id = {BA}
      AND COALESCE(cm.retencion_periodo_computo, DATE_FORMAT(COALESCE(co.f_contabilidad, co.f_emision), '%Y-%m')) = '{PER}'"""))
check(fba and abs(fba['retenciones'] - ret_sql) < 0.01, 'retenciones IIBB BA del período coinciden con los recibos', f"{fba and fba['retenciones']} / {ret_sql}")

# ------------------------------------------------------------------
caso('IIBB: liquidar Buenos Aires')
b2 = round(min(100000, (fba['base_calculada'] if fba else 0) / 2), 2)   # × 5 % = como mucho 5.000 < mínimo 9.000
b1 = round(fba['base_calculada'] - b2, 2) if fba else 0
acts = json.dumps([dict(actividad_id=A1, base_imponible=b1), dict(actividad_id=A2, base_imponible=b2)])
imp_esp = round(round(b1 * 0.035, 2) + 9000, 2)
res_esp = round(imp_esp - fba['retenciones'] - fba['percepciones'] - fba['saldo_favor_anterior'], 2) if fba else 0
r = cf(dict(accion='liquidar_iibb', periodo=PER, jurisdiccion_id=BA, actividades=acts, sucursal_id=1))
check(not ok_resp(r) and 'organismo' in err(r), 'a pagar sin organismo se rechaza', err(r))
r = cf(dict(accion='liquidar_iibb', periodo=PER, jurisdiccion_id=BA, actividades=json.dumps([dict(actividad_id=A1, base_imponible=-5)]), sucursal_id=1, entidad_id=ORG))
check(not ok_resp(r) and 'negativa' in err(r), 'base negativa se rechaza', err(r))
r = cf(dict(accion='liquidar_iibb', periodo=PER, jurisdiccion_id=BA, actividades=acts, sucursal_id=1, entidad_id=ORG, nro_ddjj='SIM-CM03'))
check(ok_resp(r), 'liquidación BA registrada', err(r))
pos = uno(f"SELECT * FROM gestion__iibb_posiciones WHERE empresa_id = {E} AND jurisdiccion_id = {BA} AND periodo = '{PER}' AND tabla_estado_registro_id = 5")
check(pos and abs(num(pos['impuesto_determinado']) - imp_esp) < 0.01, f'impuesto determinado {imp_esp} (alícuota + mínimo)', str(pos and pos['impuesto_determinado']))
check(pos and abs(num(pos['base_imponible']) - fba['base_calculada']) < 0.01 and abs(num(pos['coeficiente_cm']) - 0.6) < 0.0001,
      'guarda base declarada y coeficiente')
n_act = int(val(f"SELECT COUNT(*) FROM gestion__iibb_posiciones_actividades WHERE iibb_posicion_id = {pos['iibb_posicion_id']}") if pos else 0)
check(n_act == 2, 'guarda las dos actividades declaradas')
if pos and res_esp > 0:
    c = uno(f"""SELECT c.importe_total, c.tabla_estado_registro_id estado, o.impuesto_tipo_id, o.jurisdiccion_id, o.periodo_fiscal, ot.codigo
                FROM gestion__comprobantes c JOIN gestion__obligaciones_pago o ON o.comprobante_id = c.comprobante_id
                JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = o.obligacion_tipo_id WHERE c.comprobante_id = {pos['comprobante_id']}""")
    check(c and abs(num(c['importe_total']) - res_esp) < 0.01 and int(c['estado']) == 5 and int(c['impuesto_tipo_id']) == 9
          and int(c['jurisdiccion_id']) == BA and c['periodo_fiscal'] == PER and c['codigo'] == 'IMP_IIBB',
          f'I_LIQ IMP_IIBB por {res_esp} (impuesto 9, BA, período)', str(c))
if pos:
    a = int(pos['cont_asiento_id'])
    det = sql(f"SELECT cuenta_id, importe_local FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {a}")
    check(abs(sum(num(d['importe_local']) for d in det)) < 0.01, 'asiento balanceado', str(det))
    check(abs(sum(num(d['importe_local']) for d in det if int(d['cuenta_id']) == CTA['IIBB_GASTO']) - imp_esp) < 0.01, 'Debe IIBB_GASTO = impuesto')
    if res_esp > 0:
        check(abs(sum(num(d['importe_local']) for d in det if int(d['cuenta_id']) == CTA['IIBB_A_PAGAR']) + res_esp) < 0.01, 'Haber IIBB_A_PAGAR = saldo')
cierre = uno(f"SELECT cierre_id FROM gestion__impuestos_periodos_cierres WHERE empresa_id = {E} AND impuesto_tipo_id = 9 AND jurisdiccion_id = {BA} "
             f"AND periodo = '{PER}' AND origen = 'IIBB_POSICION' AND tabla_estado_registro_id = 5")
check(cierre, 'cierra el período de IIBB BA (origen IIBB_POSICION)')

caso('IIBB: bloqueos con BA liquidado')
r = cf(dict(accion='liquidar_iibb', periodo=PER, jurisdiccion_id=BA, actividades=acts, sucursal_id=1, entidad_id=ORG))
check(not ok_resp(r) and 'ya está liquidado' in err(r), 'segunda liquidación del mismo período se rechaza', err(r))
if cierre:
    r = cf(dict(accion='reabrir', cierre_id=cierre['cierre_id'], motivo='SIM'))
    check(not ok_resp(r) and 'anulándola' in err(r), 'el cierre de la liquidación no se reabre a mano', err(r))
vf2, r = venta(1000)
check(vf2 and not ok_resp(r) and 'cerrado' in err(r), 'no se confirma una venta del período liquidado', err(r))
f2 = fila(resumen(), BA)
check(f2 and f2['liquidacion'] and abs(f2['liquidacion']['impuesto_determinado'] - imp_esp) < 0.01, 'el resumen muestra la liquidación vigente')

caso('IIBB: anular y liquidar Río Negro por el mínimo')
r = cf(dict(accion='anular_liquidacion_iibb', iibb_posicion_id=pos['iibb_posicion_id'] if pos else 0))
check(not ok_resp(r) and 'motivo' in err(r), 'anular sin motivo se rechaza', err(r))
r = cf(dict(accion='anular_liquidacion_iibb', iibb_posicion_id=pos['iibb_posicion_id'] if pos else 0, motivo='SIM rectifica'))
check(ok_resp(r), 'liquidación BA anulada', err(r))
if pos:
    p2 = uno(f"SELECT tabla_estado_registro_id e FROM gestion__iibb_posiciones WHERE iibb_posicion_id = {pos['iibb_posicion_id']}")
    check(int(p2['e']) == 38, 'posición en 38')
    check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__cont_asientos WHERE cont_asiento_id = {pos['cont_asiento_id']}")) == 2, 'asiento anulado')
    if pos['comprobante_id']:
        check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = {pos['comprobante_id']}")) == 38, 'I_LIQ anulada')
if cierre:
    check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__impuestos_periodos_cierres WHERE cierre_id = {cierre['cierre_id']}")) == 38, 'período BA reabierto')
if vf2:
    r = confirmar_venta(vf2)
    check(ok_resp(r), 'con el período reabierto la venta se confirma', err(r))

frn = fila(resumen(), RN)
rn_acts = [dict(actividad_id=a['actividad_id'], base_imponible=0) for a in (frn or {}).get('actividades', [])]
r = cf(dict(accion='liquidar_iibb', periodo=PER, jurisdiccion_id=RN, actividades=json.dumps(rn_acts), sucursal_id=1, entidad_id=ORG))
check(ok_resp(r), 'RN con base 0 liquida el mínimo', err(r))
prn = uno(f"SELECT * FROM gestion__iibb_posiciones WHERE empresa_id = {E} AND jurisdiccion_id = {RN} AND periodo = '{PER}' AND tabla_estado_registro_id = 5")
check(prn and abs(num(prn['impuesto_determinado']) - 500) < 0.01, 'impuesto RN = mínimo 500', str(prn and prn['impuesto_determinado']))
r = cf(dict(accion='inhabilitar_actividad', actividad_id=A2))
check(ok_resp(r), 'inhabilitar una actividad', err(r))
check(n_act == 2 and int(val(f"SELECT COUNT(*) FROM gestion__iibb_posiciones_actividades WHERE iibb_posicion_id = {pos['iibb_posicion_id']}")) == 2,
      'la liquidación conserva su copia de las actividades')
