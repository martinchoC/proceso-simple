"""Control Fiscal (página 125 / 625): cierres de períodos fiscales, cuadre contable, registro de los cuatro circuitos,
inscripciones IIBB de la empresa, Libro IVA con columnas por alícuota y percepción (etapas 5 y 6) y liquidación de las
retenciones practicadas (etapa 3, sin exportación) y certificados de las retenciones sufridas (etapa 4).

Un período cerrado de una obligación bloquea confirmar y anular los comprobantes que la afectan: compras fiscales
(IVA), ventas fiscales (IVA e IIBB), cobranzas con retenciones sufridas y OP con retenciones practicadas. La
liquidación de IVA cierra su período sola y lo reabre al anularse. El cuadre compara cada percepción / retención
con lo contabilizado en su cuenta. Aplica en la copia los scripts necesarios (idempotentes) y deshabilita ARCA.
"""
import datetime as _dt
from simlib import *

for _s in ('20261011_percepciones_recibidas_01_subgrupo_fc.sql', '20261011_iva_posiciones_01.sql',
           '20261012_retenciones_sufridas_01_cuentas_iibb.sql',
           '20261016_retenciones_practicadas_02_registro.sql', '20261016_retenciones_practicadas_03_numeracion_jurisdiccion.sql',
           '20261016_retenciones_practicadas_04_regimenes.sql', '20261016_retenciones_practicadas_05_pagina_regimenes.sql',
           '20261016_cierres_fiscales_01_estructura.sql', '20261016_cierres_fiscales_02_registro_inscripciones.sql',
           '20261016_cierres_fiscales_03_liquidacion_retenciones.sql', '20261016_retenciones_sufridas_02_certificados.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))
# Solo en la copia: sin ARCA y sin liquidaciones de IVA ni cierres previos
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__iva_posiciones SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__impuestos_periodos_cierres SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")

_hoy = _dt.date.fromisoformat(HOY)
PER_B = HOY[:7]
_a = _hoy.replace(day=1) - _dt.timedelta(days=1)
PER_A = _a.strftime('%Y-%m')
FECHA_A = _a.replace(day=15).isoformat()
PER_FUT = (_hoy.replace(day=28) + _dt.timedelta(days=10)).strftime('%Y-%m')
IVA, IIBB, GAN_PRAC = 7, 9, 14
ARBA = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'ARBA'"))
PROV = 5765
NRO = [proximo_nro_proveedor(11, 960000) - 1]


def cf(params, method='POST', modulo='gestion', empresa=2):
    p = dict(params)
    p.setdefault('empresa_idx', empresa)
    return ajax('cierres_fiscales_ajax.php', p, method, modulo=modulo, empresa_id=empresa)


def cerrar(impuesto, periodo, jur=0, obs='SIM'):
    r = cf(dict(accion='cerrar', impuesto_tipo_id=impuesto, jurisdiccion_id=jur, periodo=periodo, observaciones=obs))
    return (r.get('data') or {}).get('cierre_id') if ok_resp(r) else None, r


def reabrir(cierre_id, motivo='SIM reapertura', empresa=2, modulo='gestion'):
    return cf(dict(accion='reabrir', cierre_id=cierre_id, motivo=motivo), modulo=modulo, empresa=empresa)


def estado_cierre(cierre_id):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__impuestos_periodos_cierres WHERE cierre_id = {int(cierre_id or 0)}") or 0)


def fp_borrador(fecha, neto=10000, impuestos=None):
    """Factura A de proveedor aprobada (sin confirmar). Devuelve factura_proveedor_id."""
    NRO[0] += 1
    dets = [linea(3500, 1, neto)]
    t = totales(dets)
    t['total'] = round(t['total'] + sum(i['importe'] for i in (impuestos or [])), 2)
    r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57, comprobante_tipo_id=34,
             comprobante_pv=11, comprobante_nro=NRO[0], sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=fecha,
             f_contabilidad=fecha, f_vencimiento=fecha, moneda_id=1, tipo_cambio=1, condicion_pago_id='', orden_compra_id='',
             descuento_general_pct=0, detalles=dets, impuestos_adicionales=impuestos or [], **t))
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if fid:
        ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar', empresa_idx=2, pagina_idx=57))
    return fid


def fp_accion(fid, accion):
    return ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js=accion, motivo='SIM',
                empresa_idx=2, pagina_idx=57))


def fp_estado(fid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {int(fid or 0)}") or 0)


def bloqueado(r, texto='cerrado'):
    return not ok_resp(r) and texto in err(r)


# ---------------------------------------------------------------------------------------------
caso('Caso C1 - Maestros y validaciones del cierre manual')
r = cf(dict(accion='maestros'), 'GET')
obl = {int(o['impuesto_tipo_id']): o for o in (r.get('data') or {}).get('obligaciones', [])} if ok_resp(r) else {}
check(ok_resp(r) and r['data']['puede_cerrar'] and r['data']['puede_reabrir'], 'maestros: el usuario puede cerrar y reabrir', err(r))
check(set(obl) == {6, 7, 8, 9, 14, 15, 16, 17, 22} and not obl[22]['requiere_jurisdiccion'] and obl[IIBB]['requiere_jurisdiccion'] and not obl[IVA]['requiere_jurisdiccion'],
      'obligaciones cerrables (IIBB y percepciones / retenciones IIBB por jurisdicción)', str(sorted(obl)))
_, r = cerrar(IIBB, PER_A)
check(not ok_resp(r) and 'jurisdicci' in err(r), 'IIBB sin jurisdicción: rechazado', err(r))
_, r = cerrar(IVA, PER_FUT)
check(not ok_resp(r) and 'futuro' in err(r), 'período futuro: rechazado', err(r))
_, r = cerrar(3, PER_A)
check(not ok_resp(r), 'un componente que no es obligación (percepción de Ganancias recibida): rechazado', err(r))
c_iva_a, r = cerrar(IVA, PER_A, obs='SIM DDJJ IVA')
check(c_iva_a and estado_cierre(c_iva_a) == 5, f'cierre manual de IVA {PER_A}', err(r))
_, r = cerrar(IVA, PER_A)
check(not ok_resp(r) and 'ya está cerrado' in err(r), 'el mismo período dos veces: rechazado', err(r))
r = cf(dict(accion='listar'), 'GET')
check(ok_resp(r) and any(int(x['cierre_id']) == c_iva_a and x['origen'] == 'MANUAL' for x in r['data']), 'figura en el listado', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C2 - Compra fiscal: no se confirma ni se anula con el período de IVA cerrado')
fid = fp_borrador(FECHA_A)
check(fid and fp_estado(fid) != 5, f'factura A de proveedor del {FECHA_A} sin confirmar')
r = fp_accion(fid, 'confirmar')
check(bloqueado(r) and 'IVA' in err(r) and fp_estado(fid) != 5, 'confirmar: bloqueado por el cierre de IVA', err(r))
r = reabrir(c_iva_a, motivo='')
check(not ok_resp(r) and 'motivo' in err(r), 'reabrir pide el motivo', err(r))
r = reabrir(c_iva_a)
check(ok_resp(r) and estado_cierre(c_iva_a) == 38, 'reabierto con motivo', err(r))
r = fp_accion(fid, 'confirmar')
check(ok_resp(r) and fp_estado(fid) == 5, 'con el período abierto se confirma', err(r))
c2, r = cerrar(IVA, PER_A)
check(c2, 'se vuelve a cerrar (cierre nuevo; el anterior queda reabierto en el historial)', err(r))
r = fp_accion(fid, 'anular')
check(bloqueado(r) and fp_estado(fid) == 5, 'anular: bloqueado', err(r))
r = cf(dict(accion='listar', incluir_reabiertos=1), 'GET')
check(ok_resp(r) and any(int(x['cierre_id']) == c_iva_a and x['motivo_reapertura'] == 'SIM reapertura' for x in r['data']),
      'el historial guarda la reapertura con su motivo')
reabrir(c2)

# ---------------------------------------------------------------------------------------------
caso('Caso C3 - La liquidación de IVA cierra el período y lo reabre al anularse')
r = ajax('iva_posiciones_ajax.php', dict(accion='liquidar', empresa_idx=2, periodo=PER_A, sucursal_id=1, entidad_id=PROV))
pos = uno(f"SELECT iva_posicion_id FROM gestion__iva_posiciones WHERE empresa_id = 2 AND periodo = '{PER_A}' AND tabla_estado_registro_id = 5")
check(ok_resp(r) and pos, f'IVA {PER_A} liquidado', err(r))
ci = uno(f"SELECT cierre_id, tabla_estado_registro_id FROM gestion__impuestos_periodos_cierres WHERE origen = 'IVA_POSICION' "
         f"AND referencia_id = {int(pos['iva_posicion_id']) if pos else 0}")
check(ci and int(ci['tabla_estado_registro_id']) == 5, 'la liquidación dejó el cierre IVA_POSICION', str(ci))
if ci:
    r = reabrir(ci['cierre_id'])
    check(not ok_resp(r) and 'anulándola' in err(r), 'ese cierre no se reabre desde la página', err(r))
r = fp_accion(fid, 'anular')
check(bloqueado(r) and 'anule la liquidación' in err(r), 'anular la compra: bloqueado, indica anular la liquidación', err(r))
r = ajax('iva_posiciones_ajax.php', dict(accion='anular', empresa_idx=2, periodo=PER_A, motivo='SIM cierres'))
check(ok_resp(r) and ci and estado_cierre(ci['cierre_id']) == 38, 'anular la liquidación reabre el período', err(r))
r = fp_accion(fid, 'anular')
check(ok_resp(r) and fp_estado(fid) == 38, 'con el período abierto la compra se anula', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C4 - Venta fiscal: un cierre de IIBB de cualquier jurisdicción bloquea confirmarla')
r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
         comprobante_tipo_id=4, entidad_id=313, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
         descuento_general_pct=0, observaciones='SIM cierres',
         detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=1000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
vf = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
check(vf, 'Factura A en borrador', err(r))
c_iibb, r = cerrar(IIBB, PER_B, jur=ARBA)
check(c_iibb, f'cierre de IIBB Buenos Aires {PER_B}', err(r))
r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='confirma', empresa_idx=2, pagina_idx=56))
est = int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}") or 0)
nro = int(val(f"SELECT comprobante_nro FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}") or 0)
check(bloqueado(r) and 'Ingresos Brutos' in err(r) and est == 3 and nro == 0, 'confirmar: bloqueado, sigue en borrador y sin número', err(r))
reabrir(c_iibb)
r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='confirma', empresa_idx=2, pagina_idx=56))
check(ok_resp(r), 'reabierto, se confirma (queda pendiente de aprobación)', err(r))


def vf_estado():
    return int(val(f'SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {int(vf or 0)}') or 0)


c_iibb, r = cerrar(IIBB, PER_B, jur=ARBA)
if vf_estado() == 4:
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    check(bloqueado(r) and vf_estado() == 4, 'aprobar (entra al libro): bloqueado por el cierre', err(r))
    reabrir(c_iibb)
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    check(ok_resp(r) and vf_estado() == 5, 'reabierto, se aprueba', err(r))
else:
    reabrir(c_iibb)
    check(vf_estado() == 5, 'confirmada', str(vf_estado()))

# ---------------------------------------------------------------------------------------------
caso('Caso C5 - Cobranza: solo la bloquean sus retenciones sufridas')
mp = {x['codigo']: x for x in sql("SELECT codigo, medio_pago_id FROM gestion__medios_pago WHERE empresa_id = 2 AND codigo IN ('RET_IVA', 'EFECTIVO')")}
pv = int(val("SELECT punto_venta_id FROM gestion__puntos_venta WHERE empresa_id = 2 ORDER BY punto_venta_id LIMIT 1"))
m = ajax('cobranzas_ajax.php', dict(accion='maestros', empresa_idx=2, pagina_idx=105), 'GET')
pv = int(((m.get('data') or {}).get('puntos_venta') or [{}])[0].get('punto_venta_id') or pv) if ok_resp(m) else pv
t = ajax('cobranzas_ajax.php', dict(accion='tipos_por_punto_venta', punto_venta_id=pv, empresa_idx=2, pagina_idx=105), 'GET')
tipos = t.get('data', t) if isinstance(t, dict) else t
tipo = int((tipos or [{}])[0].get('comprobante_tipo_id') or 24) if isinstance(tipos, list) else 24
caja = int(val("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = 2 AND sucursal_id = 1 AND cuenta_tipo = 'CAJA' "
               "AND tabla_estado_registro_id = 1 LIMIT 1") or 0)


def cobranza(medios):
    r = ajax('cobranzas_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=105, entidad_id=313, sucursal_id=1, comprobante_pv=pv,
             comprobante_tipo_id=tipo, f_emision=HOY, f_contabilidad=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM cierres',
             medios=medios, imputaciones=[]))
    return (r.get('cobranza_id') or (r.get('data') or {}).get('cobranza_id')) if isinstance(r, dict) else None, r


def cob_estado(cid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__cobranzas WHERE cobranza_id = {int(cid or 0)}") or 0)


efe = dict(medio_pago_id=int(mp['EFECTIVO']['medio_pago_id']), importe=500, importe_comprobante=500, moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=caja)
ret = dict(medio_pago_id=int(mp['RET_IVA']['medio_pago_id']), importe=50, importe_comprobante=50, moneda_id=1, tipo_cambio=1,
           retencion_certificado_nro=f'SIM-CF-{HOY}-{NRO[0]}', retencion_base_imponible=1000, retencion_alicuota=5)
cob_ret, r = cobranza([efe, ret])
cob_efe, r2 = cobranza([dict(efe, importe=300, importe_comprobante=300)])
check(cob_ret and cob_efe, 'dos recibos en borrador: con retención de IVA y solo efectivo', err(r) + err(r2))
c_iva_b, r = cerrar(IVA, PER_B)
check(c_iva_b, f'cierre de IVA {PER_B}', err(r))
r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob_ret, cobranza_id=cob_ret, empresa_idx=2, pagina_idx=105))
check(bloqueado(r) and cob_estado(cob_ret) == 3, 'el recibo con retención de IVA no se confirma', err(r))
r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob_efe, cobranza_id=cob_efe, empresa_idx=2, pagina_idx=105))
check(ok_resp(r) and cob_estado(cob_efe) == 5, 'el recibo sin retenciones se confirma igual', err(r))
reabrir(c_iva_b)
r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob_ret, cobranza_id=cob_ret, empresa_idx=2, pagina_idx=105))
check(ok_resp(r) and cob_estado(cob_ret) == 5, 'reabierto, se confirma', err(r))
c_iva_b, r = cerrar(IVA, PER_B)
r = ajax('cobranzas_ajax.php', dict(accion='anular', id=cob_ret, cobranza_id=cob_ret, motivo='SIM', empresa_idx=2, pagina_idx=105))
check(bloqueado(r) and cob_estado(cob_ret) == 5, 'anular el recibo con retención: bloqueado', err(r))
reabrir(c_iva_b)

# ---------------------------------------------------------------------------------------------
caso('Caso C6 - Orden de pago con retención de Ganancias practicada')
cta = val("SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id=2 AND es_imputable=1 AND codigo = '2.1.04.05'")   # SICORE a Depositar
# Solo Ganancias (otros grupos pueden haber dejado activas las retenciones de IIBB)
sql("UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id=2 WHERE empresa_id=2 AND impuesto_tipo_id IN (15, 16, 17)")
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id=1, cont_cuenta_id={cta}, f_desde='2026-01-01', regimen_id=NULL "
    f"WHERE empresa_id=2 AND impuesto_tipo_id={GAN_PRAC} AND jurisdiccion_id=1")
sql("UPDATE gestion__entidades_retenciones SET tabla_estado_registro_id=2 WHERE empresa_id=2")
sql(f"UPDATE gestion__entidades_iibb_exclusiones SET tabla_estado_registro_id=2 WHERE entidad_id={PROV} AND sentido='RETENCION'")
cfg_gan = val(f"SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id=2 AND impuesto_tipo_id={GAN_PRAC} AND jurisdiccion_id=1")
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=cfg_gan, base_calculo='NETO_GRAVADO', tipo_calculo='manual',
         alicuota=2, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, empresa_idx=2))
check(cfg_gan and ok_resp(r), 'empresa 2 agente de Ganancias (alícuota genérica de prueba 2 %)', err(r))
f2 = fp_borrador(HOY, 10000)
fp_accion(f2, 'confirmar')
comp = int(val(f"SELECT comprobante_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {int(f2 or 0)}") or 0)
pend = num(val(f"SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = {comp}"))
calc = ajax('pagos_ajax.php', dict(accion='calcular_retenciones', empresa_idx=2, entidad_id=PROV, f_emision=HOY,
            imputaciones=[dict(comprobante_destino_id=comp, importe_imputado=pend)]))
rets = [x for x in (calc.get('data') or {}).get('retenciones', []) if int(x['impuesto_tipo_id']) == GAN_PRAC] if ok_resp(calc) else []
check(rets and abs(num(rets[0]['importe']) - 200) < 0.01, 'retención de Ganancias calculada: 2 % de 10.000 = 200', err(calc))
pago = None
if rets:
    x = rets[0]
    medios = [dict(medio_pago_id=1, importe=round(pend - num(x['importe']), 2), importe_comprobante=round(pend - num(x['importe']), 2),
                   moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1),
              dict(medio_pago_id=x['medio_pago_id'], importe=x['importe'], importe_comprobante=x['importe'], moneda_id=1, tipo_cambio=1,
                   impuesto_tipo_id=x['impuesto_tipo_id'], jurisdiccion_id=x['jurisdiccion_id'] or '',
                   retencion_base_imponible=x['retencion_base_imponible'], retencion_alicuota=x['retencion_alicuota'])]
    r = ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=PROV, sucursal_id=1, f_emision=HOY,
             f_contabilidad=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM cierres', medios=medios,
             imputaciones=[dict(comprobante_destino_id=comp, importe_imputado=pend)]))
    pago = r.get('pago_id') if isinstance(r, dict) else None
check(pago, 'OP en borrador con la retención', err(calc))


def op_estado(p):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = {int(p or 0)}") or 0)


c_gan, r = cerrar(GAN_PRAC, PER_B)
check(c_gan, f'cierre de Retención de Ganancias practicada {PER_B}', err(r))
c_iva_b, r = cerrar(IVA, PER_B)
r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
check(bloqueado(r) and 'Ganancias' in err(r) and 'IVA' not in err(r).split('cerrado para')[-1] and op_estado(pago) == 3,
      'confirmar: bloqueado solo por Ganancias (la OP no afecta IVA)', err(r))
reabrir(c_iva_b)
reabrir(c_gan)
r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
check(ok_resp(r) and op_estado(pago) == 5, 'reabierto, se confirma y numera el certificado', err(r))
c_gan, r = cerrar(GAN_PRAC, PER_B)
r = ajax('pagos_ajax.php', dict(accion='anular', id=pago, motivo='SIM', empresa_idx=2))
check(bloqueado(r) and op_estado(pago) == 5, 'anular: bloqueado', err(r))
reabrir(c_gan)

# ---------------------------------------------------------------------------------------------
caso('Caso C7 - Cuadre contable de percepciones y retenciones')
r = cf(dict(accion='cuadre', desde=PER_A, hasta=PER_B), 'GET')
d = r.get('data') if ok_resp(r) else None
check(d is not None, 'calcula el cuadre', err(r))
if d:
    # Otros grupos del simulador pueden haber dejado OP con otra cuenta: se mira solo la de este caso
    op_lbl = 'OP ' + str(val(f"SELECT c.comprobante_nro FROM gestion__pagos p JOIN gestion__comprobantes c ON c.comprobante_id = p.comprobante_id "
                             f"WHERE p.pago_id = {int(pago or 0)}") or 0).zfill(8)
    gan = [i for i in d['impuestos'] if i['impuesto_tipo_id'] == GAN_PRAC and i['cuenta_id'] == int(cta)]
    check(gan and not any(x['comprobante'].startswith(op_lbl) for x in d['diferencias']),
          'la retención de Ganancias practicada figura al Haber y cuadra con el asiento', str(gan))
    ivr = [i for i in d['impuestos'] if i['impuesto_tipo_id'] == 5]
    check(ivr and ivr[0]['esperado'] >= 50 and abs(ivr[0]['diferencia']) < 0.01, 'la retención de IVA sufrida figura al Debe y cuadra', str(ivr))
    otras = [x for x in d['diferencias'] if not x['comprobante'].startswith('OP ')]
    check(not otras, 'ventas, compras y cobranzas sin diferencias', str(otras[:3]))
    # Se desvía el asiento de la OP en la copia: el cuadre lo detecta
    det = uno(f"SELECT d.cont_asiento_id, d.importe_local FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d "
              f"ON d.cont_asiento_id = a.cont_asiento_id JOIN gestion__pagos p ON p.comprobante_id = a.comprobante_id "
              f"WHERE p.pago_id = {int(pago or 0)} AND d.cuenta_id = {cta} AND a.tabla_estado_registro_id NOT IN (2, 4, 6, 38) LIMIT 1")
    if det:
        sql(f"UPDATE gestion__cont_asientos_detalles SET importe_local = importe_local + 10 WHERE cont_asiento_id = {det['cont_asiento_id']} AND cuenta_id = {cta}")
        r = cf(dict(accion='cuadre', desde=PER_B, hasta=PER_B), 'GET')
        dd = (r.get('data') or {}).get('diferencias', []) if ok_resp(r) else []
        check(any(x['comprobante'].startswith(op_lbl) and x['motivo'] == 'Importe distinto' and abs(x['diferencia'] - 10) < 0.01 for x in dd),
              'un asiento desviado en $10 aparece como diferencia de la OP', str(dd[:3]))
        sql(f"UPDATE gestion__cont_asientos_detalles SET importe_local = importe_local - 10 WHERE cont_asiento_id = {det['cont_asiento_id']} AND cuenta_id = {cta}")
    else:
        check(False, 'asiento de la OP en la cuenta de la retención')
    cuentas = [k for k in d['cuentas'] if k['cuenta_id'] == int(cta)]
    check(cuentas and abs(cuentas[0]['mov_comprobantes']) >= 200, 'por cuenta: la cuenta de la retención muestra el movimiento de comprobantes', str(cuentas))
r = cf(dict(accion='cuadre', desde=PER_B, hasta=PER_A), 'GET')
check(not ok_resp(r) and 'posterior' in err(r), 'rango invertido: rechazado', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C8 - Aislamiento entre empresas y página del módulo 5')
c_aisl, r = cerrar(IVA, PER_A)
r = cf(dict(accion='listar'), 'GET', modulo='gestion_normal', empresa=6)
check(ok_resp(r) and not any(int(x['cierre_id']) == int(c_aisl or 0) for x in r['data']), 'la empresa 6 no ve los cierres de la 2', err(r))
r = reabrir(c_aisl, empresa=6, modulo='gestion_normal')
check(not ok_resp(r) and estado_cierre(c_aisl) == 5, 'la empresa 6 no puede reabrir un cierre de la 2', err(r))
reabrir(c_aisl)
r = cf(dict(accion='maestros'), 'GET', modulo='gestion_normal', empresa=6)
check(ok_resp(r) and r['data']['puede_cerrar'], 'página 625: maestros', err(r))
r = cf(dict(accion='cerrar', impuesto_tipo_id=16, jurisdiccion_id=ARBA, periodo=PER_A, empresa_idx=6), modulo='gestion_normal', empresa=6)
c6 = (r.get('data') or {}).get('cierre_id') if ok_resp(r) else None
check(c6 and int(val(f"SELECT empresa_id FROM gestion__impuestos_periodos_cierres WHERE cierre_id = {int(c6 or 0)}") or 0) == 6,
      'la empresa 6 cierra Retención IIBB Buenos Aires en su página', err(r))
r = reabrir(c6, empresa=6, modulo='gestion_normal')
check(ok_resp(r) and estado_cierre(c6) == 38, 'y lo reabre', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C9 - Libro IVA con columnas por alícuota y por percepción')
cfg_piva = val("SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = 2 AND impuesto_tipo_id = 2 "
               "AND tabla_estado_registro_id = 1 LIMIT 1")
f3 = fp_borrador(HOY, 10000, [dict(empresa_impuesto_config_id=int(cfg_piva or 0), tipo_origen='manual', base_imponible=10000, alicuota=3, importe=300)])
r = fp_accion(f3, 'confirmar')
check(cfg_piva and ok_resp(r) and fp_estado(f3) == 5, 'factura A de $10.000 + IVA + percepción de IVA $300, confirmada', err(r))
nro3 = int(val(f"SELECT comprobante_nro FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {int(f3 or 0)}") or 0)


def libro(nombre, periodo=PER_B):
    r = ajax('iva_posiciones_ajax.php', dict(accion='libro', empresa_idx=2, periodo=periodo, libro=nombre), 'GET')
    return r.get('data') if ok_resp(r) else None, r


lc, r = libro('COMPRAS')
check(lc is not None, 'libro de compras del mes', err(r))
if lc:
    claves = {c['clave']: c for c in lc['columnas']}
    perc_iva = [k for k, c in claves.items() if c['grupo'] == 'percepcion' and k.startswith('perc_2_')]
    check('neto_21' in claves and 'iva_21' in claves and perc_iva, 'columnas dinámicas: Neto 21 %, IVA 21 % y la percepción de IVA', str(list(claves)))
    fila = next((f for f in lc['filas'] if f['numero'] == nro3 and f['pv'] == 11), None)
    check(fila and fila['valores']['neto_21'] == 10000 and fila['valores']['iva_21'] == 2100 and perc_iva
          and fila['valores'][perc_iva[0]] == 300 and abs(fila['valores']['control']) < 0.01,
          'la fila de la factura: neto 10.000, IVA 2.100, percepción 300 y control en cero', str(fila))
    suma = round(sum(f['valores']['total'] for f in lc['filas']), 2)
    check(abs(suma - lc['totales']['total']) < 0.01, 'el total del libro es la suma de las filas')
lv, r = libro('VENTAS')
check(lv and any(f['valores'].get('neto_21') == 1000 for f in lv['filas']), 'libro de ventas: la factura del caso C4 con neto 21 % de 1.000', err(r))
r = ajax('iva_posiciones_ajax.php', dict(accion='exportar_libro', empresa_idx=2, periodo=PER_B, libro='COMPRAS'), 'GET')
raw = r.get('_raw', '') if isinstance(r, dict) else ''
check('Proveedor;CUIT' in raw and 'Neto 21 %' in raw and 'Totales' in raw, 'CSV del libro de compras con columnas dinámicas y totales', raw[:200])
_, r = libro('OTRO')
check(not ok_resp(r), 'libro inválido: rechazado', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C10 - Registro de los cuatro circuitos')


def registro(**f):
    p = dict(accion='registro', desde=PER_A, hasta=PER_B)
    p.update(f)
    r = cf(p, 'GET')
    return r.get('data') if ok_resp(r) else None, r


d, r = registro()
check(d is not None, 'consulta del rango', err(r))
if d:
    circuitos = {x['circuito'] for x in d['filas']}
    check({'PERC_SUFRIDA', 'RET_SUFRIDA', 'RET_PRACTICADA'} <= circuitos, 'trae percepciones sufridas, retenciones sufridas y practicadas', str(circuitos))
    cert = ret['retencion_certificado_nro']
    check(any(x['circuito'] == 'RET_SUFRIDA' and x['certificado'] == cert and abs(x['importe'] - 50) < 0.01 for x in d['filas']),
          'la retención de IVA sufrida del caso C5 con su certificado', cert)
    check(any(x['circuito'] == 'RET_PRACTICADA' and x['certificado'] and int(x['impuesto_tipo_id']) == GAN_PRAC for x in d['filas']),
          'la retención de Ganancias practicada del caso C6 con su certificado')
    check(any(x['circuito'] == 'PERC_SUFRIDA' and int(x['impuesto_tipo_id']) == 2 and abs(x['importe'] - 300) < 0.01 for x in d['filas']),
          'la percepción de IVA sufrida del caso C9')
    tot = round(sum(x['importe'] for x in d['filas']), 2)
    check(abs(tot - round(sum(x['importe'] for x in d['resumen']), 2)) < 0.01, 'el resumen suma lo mismo que el detalle')
d2, r = registro(circuito='RET_SUFRIDA', impuesto_tipo_id=5)
check(d2 is not None and d2['filas'] and all(x['circuito'] == 'RET_SUFRIDA' and int(x['impuesto_tipo_id']) == 5 for x in d2['filas']),
      'filtro por circuito e impuesto', err(r))
cuit_313 = str(val("SELECT cuit FROM gestion__entidades WHERE entidad_id = 313") or '')
if cuit_313:
    d3, r = registro(tercero=cuit_313[-6:])
    check(d3 is not None and d3['filas'] and all(str(x['cuit'] or '').endswith(cuit_313[-6:]) or cuit_313[-6:] in str(x['cuit'] or '') for x in d3['filas']),
          'filtro por CUIT del tercero', err(r))
r = cf(dict(accion='exportar_registro', desde=PER_A, hasta=PER_B), 'GET')
raw = r.get('_raw', '') if isinstance(r, dict) else ''
check('Circuito;Fecha;Período;Comprobante' in raw, 'CSV del registro', raw[:120])
d6, r = registro(empresa_idx=6)
r6 = cf(dict(accion='registro', desde=PER_A, hasta=PER_B), 'GET', modulo='gestion_normal', empresa=6)
check(ok_resp(r6) and not any(x['certificado'] == ret['retencion_certificado_nro'] for x in r6['data']['filas']),
      'la empresa 6 no ve el registro de la 2', err(r6))

# ---------------------------------------------------------------------------------------------
caso('Caso C11 - Inscripciones IIBB de la empresa')
sql("UPDATE gestion__empresas_iibb_inscripciones SET tabla_estado_registro_id = 2 WHERE empresa_id = 2")
NQN = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'DGR_NQN'"))
LOCAL = int(val("SELECT iibb_regimen_id FROM gestion__iibb_regimenes WHERE iibb_regimen_codigo = 'LOCAL'"))
CM = int(val("SELECT iibb_regimen_id FROM gestion__iibb_regimenes WHERE iibb_regimen_codigo = 'CONVENIO_MULTILATERAL'"))


def inscribir(**d):
    p = dict(accion='agregar_inscripcion', jurisdiccion_id=ARBA, iibb_regimen_id=CM, nro_inscripcion='901-SIM', es_sede=0,
             coeficiente_cm='', f_desde='2026-01-01', f_hasta='', observaciones='SIM')
    p.update(d)
    r = cf(p)
    return (r.get('data') or {}).get('inscripcion_id') if ok_resp(r) else None, r


_, r = inscribir(iibb_regimen_id=LOCAL, coeficiente_cm='0,5')
check(not ok_resp(r) and 'Convenio' in err(r), 'coeficiente con régimen local: rechazado', err(r))
_, r = inscribir(coeficiente_cm='1,5')
check(not ok_resp(r) and 'de 0 a 1' in err(r), 'coeficiente mayor que 1: rechazado', err(r))
_, r = inscribir(jurisdiccion_id=1)
check(not ok_resp(r) and 'provincia' in err(r), 'jurisdicción no provincial (AFIP): rechazada', err(r))
i_ba, r = inscribir(es_sede=1, coeficiente_cm='0,6')
check(i_ba, 'Buenos Aires, convenio multilateral, sede, coeficiente 0,6', err(r))
_, r = inscribir(f_desde='2026-06-01')
check(not ok_resp(r) and 'superpuesta' in err(r), 'misma provincia con vigencia superpuesta: rechazada', err(r))
_, r = inscribir(jurisdiccion_id=NQN, es_sede=1, coeficiente_cm='0,4')
check(not ok_resp(r) and 'sede' in err(r), 'segunda sede: rechazada', err(r))
i_nqn, r = inscribir(jurisdiccion_id=NQN, coeficiente_cm='0,4', f_hasta=_a.replace(day=1).isoformat())
check(i_nqn, f'Neuquén, convenio multilateral, hasta el {_a.replace(day=1).isoformat()}', err(r))
r = cf(dict(accion='inscripciones'), 'GET')
check(ok_resp(r) and {int(x['inscripcion_id']) for x in r['data']} >= {int(i_ba or 0), int(i_nqn or 0)}, 'listado de inscripciones activas', err(r))
_, r = cerrar(IIBB, PER_B, jur=NQN)
check(not ok_resp(r) and 'inscripta' in err(r), f'cerrar IIBB Neuquén {PER_B} (inscripción vencida): rechazado', err(r))
c_ba, r = cerrar(IIBB, PER_B, jur=ARBA)
check(c_ba, f'cerrar IIBB Buenos Aires {PER_B} (inscripta): permitido', err(r))
reabrir(c_ba)
r = cf(dict(accion='inhabilitar_inscripcion', inscripcion_id=i_ba), modulo='gestion_normal', empresa=6)
check(not ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__empresas_iibb_inscripciones WHERE inscripcion_id = {int(i_ba or 0)}")) == 1,
      'la empresa 6 no puede inhabilitar una inscripción de la 2', err(r))
for i in (i_ba, i_nqn):
    r = cf(dict(accion='inhabilitar_inscripcion', inscripcion_id=i))
check(ok_resp(r) and int(val("SELECT COUNT(*) FROM gestion__empresas_iibb_inscripciones WHERE empresa_id = 2 AND tabla_estado_registro_id = 1")) == 0,
      'inhabilitadas (sin inscripciones, el cierre de IIBB no se valida)', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso C12 - Liquidación de retenciones practicadas (sin exportación)')


def liq_fila(periodo=PER_B):
    r = cf(dict(accion='liquidacion_resumen', periodo=periodo), 'GET')
    filas = r['data']['filas'] if ok_resp(r) else []
    return next((f for f in filas if f['impuesto_tipo_id'] == GAN_PRAC), None), r


f, r = liq_fila()
check(f and f['pendientes'] >= 1 and f['importe_pendiente'] > 0 and f['tiene_cuenta'] and not f['cerrado'],
      'resumen del mes: Ganancias con la retención del caso C6 pendiente y cuenta configurada', err(r) if not f else str(f))
pend = f['importe_pendiente'] if f else 0
org = int(val("SELECT entidad_id FROM gestion__entidades WHERE empresa_id = 2 AND EXISTS (SELECT 1 FROM gestion__entidades_roles xr JOIN gestion__roles_entidades xc ON xc.rol_entidad_id = xr.rol_entidad_id AND xc.rol_entidad_codigo = 'PRO' WHERE xr.entidad_id = gestion__entidades.entidad_id AND xr.f_baja IS NULL AND xr.tabla_estado_registro_id = 1) AND tabla_estado_registro_id = 1 ORDER BY entidad_id LIMIT 1"))
base_liq = dict(accion='liquidar_retenciones', periodo=PER_B, impuesto_tipo_id=GAN_PRAC, jurisdiccion_id=0, sucursal_id=1, f_vto=HOY, nro_ddjj='SIM-SICORE')
r = cf(dict(base_liq, entidad_id=''))
check(not ok_resp(r) and 'organismo' in err(r), 'sin organismo recaudador: rechazado', err(r))
r = cf(dict(base_liq, impuesto_tipo_id=16, jurisdiccion_id=0, entidad_id=org))
check(not ok_resp(r) and 'jurisdicci' in err(r), 'IIBB sin jurisdicción: rechazado', err(r))
c_gan, _ = cerrar(GAN_PRAC, PER_B)
r = cf(dict(base_liq, entidad_id=org))
check(bloqueado(r), 'con el período cerrado no se liquida', err(r))
reabrir(c_gan)
r = cf(dict(base_liq, entidad_id=org))
liq = (r.get('data') or {}).get('comprobante_id') if ok_resp(r) else None
check(liq and abs(r['data']['total'] - pend) < 0.01, f'liquidación (I_LIQ) por {pend}', err(r))
if liq:
    o = uno(f"SELECT o.impuesto_tipo_id, o.periodo_fiscal, o.cont_cuenta_id, o.nro_ddjj, c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id "
            f"FROM gestion__obligaciones_pago o JOIN gestion__comprobantes c ON c.comprobante_id = o.comprobante_id WHERE o.comprobante_id = {liq}")
    check(o and int(o['impuesto_tipo_id']) == GAN_PRAC and o['periodo_fiscal'] == PER_B and int(o['cont_cuenta_id']) == int(cta)
          and o['nro_ddjj'] == 'SIM-SICORE' and int(o['tabla_estado_registro_id']) == 5 and abs(num(o['importe_pendiente']) - pend) < 0.01,
          'obligación impositiva: impuesto, período, cuenta de la retención como pasivo, DDJJ y pendiente de pago', str(o))
    check(int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id = {liq}")) == 0,
          'la I_LIQ no lleva asiento (la retención ya está en el pasivo desde la OP)')
    check(int(val(f"SELECT COUNT(*) FROM gestion__pagos_retenciones WHERE pago_id = {int(pago or 0)} AND comp_liquidacion_id = {liq}")) >= 1,
          'la retención de la OP queda marcada con la liquidación')
f, r = liq_fila()
check(f and f['pendientes'] == 0 and len(f['liquidaciones']) == 1 and not f['liquidaciones'][0]['pagada'], 'resumen: sin pendientes y una liquidación a pagar', str(f))
r = cf(dict(base_liq, entidad_id=org))
check(not ok_resp(r) and 'pendientes' in err(r), 'liquidar de nuevo sin pendientes: rechazado', err(r))
r = cf(dict(accion='liquidacion_detalle', comprobante_id=liq), 'GET')
check(ok_resp(r) and len(r['data']) >= 1 and all(x['certificado'] for x in r['data']), 'detalle de la liquidación con certificados', err(r))
d, r = registro(circuito='RET_PRACTICADA', impuesto_tipo_id=GAN_PRAC)
check(d and d['filas'] and all(int(x['declarada']) == 1 for x in d['filas'] if x['periodo'] == PER_B), 'en el registro figura como declarada', err(r))
r = ajax('pagos_ajax.php', dict(accion='anular', id=pago, motivo='SIM', empresa_idx=2))
check(not ok_resp(r) and op_estado(pago) == 5, 'la OP con la retención liquidada no se puede anular', err(r))
r = cf(dict(accion='anular_liquidacion_retenciones', comprobante_id=liq, motivo='SIM'), modulo='gestion_normal', empresa=6)
check(not ok_resp(r), 'la empresa 6 no puede anular una liquidación de la 2', err(r))
c_gan, _ = cerrar(GAN_PRAC, PER_B)
r = cf(dict(accion='anular_liquidacion_retenciones', comprobante_id=liq, motivo='SIM'))
check(bloqueado(r), 'con el período cerrado no se anula la liquidación', err(r))
reabrir(c_gan)
r = cf(dict(accion='anular_liquidacion_retenciones', comprobante_id=liq, motivo=''))
check(not ok_resp(r) and 'motivo' in err(r), 'anular pide motivo', err(r))
r = cf(dict(accion='anular_liquidacion_retenciones', comprobante_id=liq, motivo='SIM corrección'))
check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = {int(liq or 0)}")) == 38
      and int(val(f"SELECT tabla_estado_registro_id FROM gestion__obligaciones_pago WHERE comprobante_id = {int(liq or 0)}")) == 38
      and int(val(f"SELECT COUNT(*) FROM gestion__pagos_retenciones WHERE comp_liquidacion_id = {int(liq or 0)}")) == 0,
      'anulada: I_LIQ y obligación en 38, retenciones otra vez pendientes', err(r))
f, r = liq_fila()
check(f and f['pendientes'] >= 1 and not f['liquidaciones'], 'resumen: vuelven a estar pendientes', str(f))

# ---------------------------------------------------------------------------------------------
caso('Caso C13 - Certificados de retenciones sufridas: pendiente, datos, período de cómputo')
ret_p = dict(ret, importe=40, importe_comprobante=40, retencion_certificado_nro='')
cob_p, r = cobranza([ret_p])
r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob_p, empresa_idx=2, pagina_idx=105))
check(cob_p and ok_resp(r) and cob_estado(cob_p) == 5, 'recibo con retención de IVA sin certificado, confirmado', err(r))
medio_p = int(val(f"SELECT cm.cobranza_medio_id FROM gestion__cobranzas_medios cm WHERE cm.cobranza_id = {int(cob_p or 0)} "
                  f"AND cm.impuesto_tipo_id IS NOT NULL") or 0)
d, r = registro(circuito='RET_SUFRIDA', certificado_estado='PENDIENTE')
fila = next((x for x in (d or {}).get('filas', []) if int(x['cobranza_medio_id']) == medio_p), None)
check(fila and fila['certificado_estado'] == 'PENDIENTE' and fila['periodo_computo'] == PER_B and not fila['certificado'],
      'el registro lo lista entre los certificados pendientes', str(fila))
d2, r = registro(circuito='RET_SUFRIDA', certificado_estado='RECIBIDO')
check(d2 is not None and not any(int(x['cobranza_medio_id']) == medio_p for x in d2['filas']), 'y no entre los recibidos')


def cert(**dd):
    p = dict(accion='actualizar_certificado', cobranza_medio_id=medio_p, retencion_certificado_estado='RECIBIDO', retencion_periodo_computo=PER_B,
             retencion_certificado_nro='', retencion_f_certificado='', retencion_regimen='')
    p.update(dd)
    return cf(p)


r = cert()
check(not ok_resp(r) and 'número y fecha' in err(r), 'recibido sin número ni fecha: rechazado', err(r))
r = cert(retencion_certificado_nro=ret['retencion_certificado_nro'], retencion_f_certificado=HOY)
check(not ok_resp(r) and 'ya está registrado' in err(r), 'número repetido para el cliente e impuesto: rechazado', err(r))
r = cert(retencion_certificado_estado='OTRO')
check(not ok_resp(r), 'estado inválido: rechazado', err(r))
nro_p = f'SIM-P-{HOY}-{medio_p}'
r = cert(retencion_certificado_nro=nro_p, retencion_f_certificado=FECHA_A, retencion_regimen='RG 2408 - IVA')
m = uno(f"SELECT retencion_certificado_nro, retencion_certificado_estado, retencion_f_certificado, retencion_regimen, retencion_periodo_computo, "
        f"importe_comprobante FROM gestion__cobranzas_medios WHERE cobranza_medio_id = {medio_p}")
check(ok_resp(r) and m and m['retencion_certificado_nro'] == nro_p and m['retencion_certificado_estado'] == 'RECIBIDO'
      and m['retencion_f_certificado'] == FECHA_A and m['retencion_regimen'] == 'RG 2408 - IVA' and abs(num(m['importe_comprobante']) - 40) < 0.01,
      'certificado recibido: número, fecha y régimen guardados (el importe no cambia)', err(r) + str(m))
# Período de cómputo: el mes anterior. Con IVA de ese mes cerrado no se permite; abierto, la posición de IVA lo toma
iva_a0 = num((ajax('iva_posiciones_ajax.php', dict(accion='detalle', empresa_idx=2, periodo=PER_A), 'GET').get('data') or {}).get('calculo', {}).get('retenciones_iva'))
iva_b0 = num((ajax('iva_posiciones_ajax.php', dict(accion='detalle', empresa_idx=2, periodo=PER_B), 'GET').get('data') or {}).get('calculo', {}).get('retenciones_iva'))
c_iva_a2, _ = cerrar(IVA, PER_A)
r = cert(retencion_certificado_nro=nro_p, retencion_f_certificado=FECHA_A, retencion_periodo_computo=PER_A)
check(bloqueado(r), f'llevar el cómputo a {PER_A} con IVA cerrado: bloqueado', err(r))
reabrir(c_iva_a2)
r = cert(retencion_certificado_nro=nro_p, retencion_f_certificado=FECHA_A, retencion_periodo_computo=PER_A)
iva_a1 = num((ajax('iva_posiciones_ajax.php', dict(accion='detalle', empresa_idx=2, periodo=PER_A), 'GET').get('data') or {}).get('calculo', {}).get('retenciones_iva'))
iva_b1 = num((ajax('iva_posiciones_ajax.php', dict(accion='detalle', empresa_idx=2, periodo=PER_B), 'GET').get('data') or {}).get('calculo', {}).get('retenciones_iva'))
check(ok_resp(r) and abs(iva_a1 - iva_a0 - 40) < 0.01 and abs(iva_b0 - iva_b1 - 40) < 0.01,
      f'cómputo en {PER_A}: la posición de IVA pasa los $40 de {PER_B} a {PER_A}', f'{err(r)} A {iva_a0}->{iva_a1} B {iva_b0}->{iva_b1}')
c_iva_a2, _ = cerrar(IVA, PER_A)
r = ajax('cobranzas_ajax.php', dict(accion='anular', id=cob_p, motivo='SIM', empresa_idx=2, pagina_idx=105))
check(bloqueado(r) and cob_estado(cob_p) == 5, 'anular el recibo: bloqueado por el cierre del período de cómputo (no el del recibo)', err(r))
reabrir(c_iva_a2)
r = cert(retencion_certificado_estado='CONCILIADO', retencion_certificado_nro=nro_p, retencion_f_certificado=FECHA_A, retencion_periodo_computo=PER_A)
check(ok_resp(r) and val(f"SELECT retencion_certificado_estado FROM gestion__cobranzas_medios WHERE cobranza_medio_id = {medio_p}") == 'CONCILIADO',
      'marcado como conciliado', err(r))
r = cf(dict(accion='ver_certificado', cobranza_medio_id=medio_p), 'GET')
check(not ok_resp(r) and 'archivo' in err(r), 'sin archivo adjunto: ver_certificado lo informa', err(r))
r = cf(dict(accion='actualizar_certificado', cobranza_medio_id=medio_p, retencion_certificado_estado='PENDIENTE', retencion_periodo_computo=PER_A),
       modulo='gestion_normal', empresa=6)
check(not ok_resp(r) and val(f"SELECT retencion_certificado_estado FROM gestion__cobranzas_medios WHERE cobranza_medio_id = {medio_p}") == 'CONCILIADO',
      'la empresa 6 no puede tocar el certificado de la 2', err(r))

check(int(val("SELECT COUNT(*) FROM gestion__impuestos_periodos_cierres WHERE tabla_estado_registro_id = 5 AND empresa_id IN (2, 6)")) == 0,
      'el caso no deja períodos cerrados')
