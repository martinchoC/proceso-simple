"""Retenciones practicadas, etapa 2: catálogo global de regímenes, condición del proveedor, mínimo acumulado mensual,
exclusión, escala, exento, numeración por impuesto y jurisdicción, empresa no agente y aislamiento entre empresas.

Los importes y alícuotas son de PRUEBA (no normativos): los regímenes reales los carga el usuario.
Aplica en la copia los scripts 20261016_retenciones_practicadas_02 a 05 (idempotentes).
"""
from simlib import *

for _s in ('20261016_retenciones_practicadas_02_registro.sql', '20261016_retenciones_practicadas_03_numeracion_jurisdiccion.sql',
           '20261016_retenciones_practicadas_04_regimenes.sql', '20261016_retenciones_practicadas_05_pagina_regimenes.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))

PROV = 5765
P3 = 3500
NRO = [proximo_nro_proveedor(11, 940000) - 1]
MES_DESDE = HOY[:8] + '01'
GAN, IIBB = 14, 16
ARBA, NQN = 2, 7


def fp_factura(neto):
    """Factura A de proveedor confirmada por un neto dado (IVA 21 %). Devuelve el comprobante_id."""
    NRO[0] += 1
    dets = [linea(P3, 1, neto)]
    r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57, comprobante_tipo_id=34,
             comprobante_pv=11, comprobante_nro=NRO[0], sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY,
             f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='', orden_compra_id='',
             descuento_general_pct=0, detalles=dets, impuestos_adicionales=[], **totales(dets)))
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if not fid:
        return None
    for acc in ('aprobar', 'confirmar'):
        ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js=acc, empresa_idx=2, pagina_idx=57))
    return int(val(f"SELECT comprobante_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id={fid}"))


def pendiente(comp_id):
    return num(val(f"SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id={comp_id}"))


def calcular(comp_id):
    r = ajax('pagos_ajax.php', dict(accion='calcular_retenciones', empresa_idx=2, entidad_id=PROV, f_emision=HOY,
             imputaciones=[dict(comprobante_destino_id=comp_id, importe_imputado=pendiente(comp_id))]))
    return r.get('data', {}) if ok_resp(r) else {'retenciones': [], 'avisos': [err(r)]}


def ret_de(calc, impuesto, jurisdiccion=None):
    for x in calc.get('retenciones', []):
        if int(x['impuesto_tipo_id']) == impuesto and (jurisdiccion is None or int(x['jurisdiccion_id'] or 0) == jurisdiccion):
            return x
    return None


def pagar(comp_id, calc):
    """OP que cancela el comprobante: retenciones calculadas + efectivo por el resto. Devuelve pago_id confirmado."""
    total = pendiente(comp_id)
    rets = calc.get('retenciones', [])
    total_ret = round(sum(num(x['importe']) for x in rets), 2)
    medios = [dict(medio_pago_id=1, importe=round(total - total_ret, 2), importe_comprobante=round(total - total_ret, 2),
                   moneda_id=1, tipo_cambio=1, tesoreria_cuenta_id=1)]
    for x in rets:
        medios.append(dict(medio_pago_id=x['medio_pago_id'], importe=x['importe'], importe_comprobante=x['importe'], moneda_id=1,
                           tipo_cambio=1, impuesto_tipo_id=x['impuesto_tipo_id'], jurisdiccion_id=x['jurisdiccion_id'] or '',
                           retencion_base_imponible=x['retencion_base_imponible'], retencion_alicuota=x['retencion_alicuota']))
    r = ajax('pagos_ajax.php', dict(accion='guardar', empresa_idx=2, pagina_idx=106, entidad_id=PROV, sucursal_id=1, f_emision=HOY,
             f_contabilidad=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM regímenes', medios=medios,
             imputaciones=[dict(comprobante_destino_id=comp_id, importe_imputado=total)]))
    pago = r.get('pago_id') if isinstance(r, dict) else None
    if not pago:
        return None, err(r)
    r = ajax('pagos_ajax.php', dict(accion='confirmar', id=pago, empresa_idx=2))
    return (pago if ok_resp(r) else None), err(r)


def regimen(**datos):
    base = dict(accion='agregar', empresa_idx=2, impuesto_tipo_id=GAN, jurisdiccion_id='', base_calculo='NETO_GRAVADO',
                alicuota_inscripto='', alicuota_no_inscripto='', minimo_no_sujeto=0, minimo_retencion=0, tipo_minimo='POR_PAGO',
                modo_minimo='DEDUCIBLE', usa_escala=0, f_desde=MES_DESDE, f_hasta='', observaciones='SIM', escalas='[]')
    base.update(datos)
    return ajax('regimenes_retencion_ajax.php', base)


def condicion(impuesto, cond, regimen_id='', jurisdiccion='', desde=MES_DESDE):
    return ajax('retenciones_proveedores_ajax.php', dict(accion='agregar_condicion', empresa_idx=2, entidad_id=PROV, impuesto_tipo_id=impuesto,
                jurisdiccion_id=jurisdiccion, condicion=cond, regimen_id=regimen_id, f_desde=desde, f_hasta=''))


# ---------------------------------------------------------------------------------------------
caso('Caso R1 - Configuración: empresa 2 agente de Ganancias, IIBB Buenos Aires y Neuquén')
cta_ret = val("SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id=2 AND es_imputable=1 AND codigo LIKE '2.%' "
              "AND tabla_estado_registro_id=1 ORDER BY codigo LIMIT 1")
cfg_nqn = val(f"SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id=2 AND impuesto_tipo_id={IIBB} AND jurisdiccion_id={NQN}")
check(cfg_nqn is not None, 'el script dio de alta la config de IIBB Neuquén de la empresa 2')
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id=1, cont_cuenta_id={cta_ret}, f_desde='2026-01-01' "
    f"WHERE empresa_id=2 AND impuesto_tipo_id IN ({GAN}, {IIBB}) AND jurisdiccion_id IN (1, {ARBA}, {NQN})")
for cid, alic in ((5, 2), (7, 1.5), (cfg_nqn, 1)):
    r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=cid, base_calculo='NETO_GRAVADO',
             tipo_calculo='manual', alicuota=alic, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, empresa_idx=2))
    check(ok_resp(r), f'config {cid}: alícuota genérica {alic} %', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso R2 - Catálogo global de regímenes (ABM, superadmin)')
r = regimen(codigo='SIM1', regimen='SIM acumulado mensual', alicuota_inscripto=2, alicuota_no_inscripto=10,
            minimo_no_sujeto=10000, tipo_minimo='ACUMULADO_MENSUAL')
reg1 = (r.get('data') or {}).get('regimen_id') if ok_resp(r) else None
check(reg1, 'alta de régimen de Ganancias con mínimo acumulado mensual', err(r))
check(int(val(f"SELECT empresa_id FROM gestion__retenciones_regimenes WHERE regimen_id={reg1 or 0}") or -1) == 0, 'el régimen queda global (empresa 0)')
r = regimen(codigo='SIM1', regimen='duplicado', alicuota_inscripto=3)
check(not ok_resp(r), 'mismo código con vigencia superpuesta: rechazado', err(r))
r = regimen(impuesto_tipo_id=IIBB, codigo='SIMX', regimen='IIBB sin provincia', alicuota_inscripto=1)
check(not ok_resp(r), 'régimen de IIBB sin jurisdicción: rechazado', err(r))
r = regimen(codigo='SIM2', regimen='SIM escala', usa_escala=1, alicuota_no_inscripto=10, minimo_no_sujeto=1000,
            escalas=json.dumps([dict(desde=0, hasta=5000, importe_fijo=0, alicuota_excedente=5),
                                dict(desde=5000, hasta='', importe_fijo=250, alicuota_excedente=10)]))
reg2 = (r.get('data') or {}).get('regimen_id') if ok_resp(r) else None
check(reg2, 'alta de régimen con escala de dos tramos', err(r))
r = regimen(codigo='SIM3', regimen='escala mal', usa_escala=1, escalas=json.dumps([dict(desde=0, hasta=5000, alicuota_excedente=5),
                                                                                  dict(desde=4000, hasta='', alicuota_excedente=10)]))
check(not ok_resp(r), 'tramos superpuestos: rechazado', err(r))
r = ajax('regimenes_retencion_ajax.php', dict(accion='listar', empresa_idx=2), 'GET')
fila = next((x for x in r.get('data', []) if int(x['regimen_id']) == int(reg1 or 0)), None) if ok_resp(r) else None
check(fila and {b['accion_js'] for b in fila['botones']} == {'editar', 'inactivar'}, 'listado con botones del motor (editar / inhabilitar)', str(fila)[:200])
r = ajax('regimenes_retencion_ajax.php', dict(accion='ejecutar_accion', regimen_id=reg2, accion_js='inactivar', empresa_idx=2))
r2 = ajax('regimenes_retencion_ajax.php', dict(accion='ejecutar_accion', regimen_id=reg2, accion_js='habilitar', empresa_idx=2))
check(ok_resp(r) and ok_resp(r2) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__retenciones_regimenes WHERE regimen_id={reg2}")) == 1,
      'inhabilitar y habilitar por el motor de estados', err(r) + err(r2))

# ---------------------------------------------------------------------------------------------
caso('Caso R3 - Condición del proveedor y alerta sin condición')
c0 = calcular(fp_factura(1000) or 0)
check(ret_de(c0, GAN) is not None, 'sin régimen ni condición: Ganancias con la alícuota genérica de la config (etapa 1)', json.dumps(c0)[:300])
r = ajax('empresas_impuestos_config_ajax.php', dict(accion='guardar_retencion', config_id=5, base_calculo='NETO_GRAVADO', tipo_calculo='manual',
         alicuota=2, sin_dato_accion='GENERICA', minimo_imponible=0, importe_minimo=0, aplica_siempre=1, regimen_id=reg1, empresa_idx=2))
check(ok_resp(r), 'config de Ganancias con régimen por defecto SIM1', err(r))
c1 = calcular(fp_factura(1000) or 0)
check(ret_de(c1, GAN) is None and any('no tiene condición definida' in a for a in c1.get('avisos', [])),
      'con régimen y sin condición del proveedor: avisa y no retiene', json.dumps(c1.get('avisos'))[:300])
r = ajax('retenciones_proveedores_ajax.php', dict(accion='datos', empresa_idx=2, entidad_id=PROV), 'GET')
agente = r.get('data', {}).get('agente', []) if ok_resp(r) else []
check(len(agente) >= 3 and any(a['nombre'].endswith('Neuquén (DGR)') for a in agente), 'el proveedor ve las retenciones de las que la empresa es agente',
      str([a['nombre'] for a in agente]))
r = condicion(GAN, 'INSCRIPTO', reg1)
check(ok_resp(r), 'condición INSCRIPTO con régimen habitual SIM1', err(r))
r = condicion(GAN, 'NO_INSCRIPTO', reg1)
check(not ok_resp(r), 'otra condición que empieza el mismo día: rechazada', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso R4 - Mínimo no sujeto acumulado mensual (régimen SIM1: mínimo 10.000, inscripto 2 %)')
pagadas_mes = num(val(f"SELECT COALESCE(SUM(c.importe_neto * ci.importe_imputado / c.importe_total), 0) FROM gestion__pagos p "
                      f"JOIN gestion__comprobantes_imputaciones ci ON ci.comp_origen_id=p.comprobante_id AND ci.tabla_estado_registro_id=5 "
                      f"JOIN gestion__comprobantes c ON c.comprobante_id=ci.comp_destino_id "
                      f"WHERE p.empresa_id=2 AND p.entidad_id={PROV} AND p.tabla_estado_registro_id=5 AND p.f_emision BETWEEN '{MES_DESDE}' AND LAST_DAY('{HOY}')"))
a1 = fp_factura(2000)
c = calcular(a1)
g = ret_de(c, GAN)
esperado_base = round(pagadas_mes + 2000, 2)
if esperado_base <= 10000:
    check(g is None and any('mínimo no sujeto' in x for x in c.get('avisos', [])), f'acumulado {esperado_base} bajo el mínimo: no retiene', json.dumps(c)[:300])
else:
    check(g is not None, f'acumulado {esperado_base} sobre el mínimo: retiene', json.dumps(c)[:300])
pago1, e = pagar(a1, c)
check(pago1, 'OP 1 confirmada', e)
acum = round(esperado_base, 2)
a2 = fp_factura(9000)
c = calcular(a2)
g = ret_de(c, GAN)
previas = num(val(f"SELECT COALESCE(SUM(importe), 0) FROM gestion__pagos_retenciones WHERE entidad_id={PROV} AND impuesto_tipo_id={GAN} "
                  f"AND regimen_id={reg1} AND tabla_estado_registro_id=5 AND f_retencion BETWEEN '{MES_DESDE}' AND LAST_DAY('{HOY}')"))
base_mes = round(acum + 9000, 2)
esperado = round((base_mes - 10000) * 0.02 - previas, 2)
check(g and abs(num(g['base_acumulada_mes']) - base_mes) < 0.02 and abs(num(g['importe']) - esperado) < 0.02 and g['origen'] == 'REGIMEN',
      f'acumulado del mes {base_mes}: ({base_mes} - 10.000) x 2 % - {previas} ya retenido = {esperado}', json.dumps(g))
pago2, e = pagar(a2, c)
check(pago2, 'OP 2 confirmada', e)
r = uno(f"SELECT regimen_id, regimen, condicion, base_acumulada_mes, retenciones_previas_mes FROM gestion__pagos_retenciones "
        f"WHERE pago_id={pago2 or 0} AND impuesto_tipo_id={GAN}")
check(r and int(r['regimen_id']) == int(reg1) and r['condicion'] == 'INSCRIPTO' and r['regimen'].startswith('SIM1'),
      'la retención registra régimen, condición y acumulado', str(r))
a3 = fp_factura(5000)
c = calcular(a3)
g = ret_de(c, GAN)
ya = round(previas + esperado, 2)
esperado3 = round((base_mes + 5000 - 10000) * 0.02 - ya, 2)
check(g and abs(num(g['retenciones_previas_mes']) - ya) < 0.02 and abs(num(g['importe']) - esperado3) < 0.02,
      f'tercer pago: descuenta lo retenido en el mes ({ya}) = {esperado3}', json.dumps(g))

# ---------------------------------------------------------------------------------------------
caso('Caso R5 - Certificado de exclusión, escala y proveedor exento')
r = ajax('retenciones_proveedores_ajax.php', dict(accion='agregar_exclusion', empresa_idx=2, entidad_id=PROV, impuesto_tipo_id=GAN,
         jurisdiccion_id='', porcentaje_exclusion=50, numero_certificado='SIM-EXC-1', f_desde=MES_DESDE, f_hasta='2099-12-31'))
check(ok_resp(r), 'certificado de exclusión del 50 % de Ganancias', err(r))
r = ajax('retenciones_proveedores_ajax.php', dict(accion='agregar_exclusion', empresa_idx=2, entidad_id=PROV, impuesto_tipo_id=GAN,
         jurisdiccion_id='', porcentaje_exclusion=50, numero_certificado='SIM-EXC-2', f_desde=MES_DESDE, f_hasta=''))
check(not ok_resp(r), 'certificado sin vencimiento: rechazado', err(r))
c = calcular(a3)
g = ret_de(c, GAN)
check(g and abs(num(g['importe']) - round(esperado3 * 0.5, 2)) < 0.02 and abs(num(g['porcentaje_exclusion']) - 50) < 0.01,
      f'con exclusión: {esperado3} x 50 %', json.dumps(g))
cid = val(f"SELECT entidad_retencion_id FROM gestion__entidades_retenciones WHERE entidad_id={PROV} AND impuesto_tipo_id={GAN} AND tabla_estado_registro_id=1 ORDER BY entidad_retencion_id DESC LIMIT 1")
r = ajax('retenciones_proveedores_ajax.php', dict(accion='inhabilitar_condicion', empresa_idx=2, id=cid))
r2 = condicion(GAN, 'INSCRIPTO', reg2)
check(ok_resp(r) and ok_resp(r2), 'cambio al régimen con escala SIM2', err(r) + err(r2))
c = calcular(a3)
g = ret_de(c, GAN)
# escala SIM2: base 5.000 - mínimo 1.000 = 4.000 -> tramo 0-5.000 al 5 % = 200; exclusión 50 % = 100
check(g and g['origen'] == 'ESCALA' and abs(num(g['importe']) - 100) < 0.02, 'escala: (5.000 - 1.000) x 5 % = 200, con exclusión 50 % = 100', json.dumps(g))
cid = val(f"SELECT entidad_retencion_id FROM gestion__entidades_retenciones WHERE entidad_id={PROV} AND impuesto_tipo_id={GAN} AND tabla_estado_registro_id=1 ORDER BY entidad_retencion_id DESC LIMIT 1")
ajax('retenciones_proveedores_ajax.php', dict(accion='inhabilitar_condicion', empresa_idx=2, id=cid))
r = condicion(GAN, 'EXENTO')
c = calcular(a3)
check(ok_resp(r) and ret_de(c, GAN) is None and any('exento' in x for x in c.get('avisos', [])), 'proveedor exento: no retiene Ganancias', json.dumps(c)[:300])

# ---------------------------------------------------------------------------------------------
caso('Caso R6 - Numeración del certificado por impuesto y jurisdicción')
c = calcular(a3)
arba, nqn = ret_de(c, IIBB, ARBA), ret_de(c, IIBB, NQN)
check(arba and nqn, 'IIBB Buenos Aires y Neuquén calculadas', json.dumps(c)[:300])
antes = {j: int(val(f"SELECT COALESCE(MAX(ultimo_numero), 0) FROM gestion__retenciones_numeradores WHERE empresa_id=2 AND impuesto_tipo_id={IIBB} AND jurisdiccion_id={j}") or 0)
         for j in (ARBA, NQN)}
pago3, e = pagar(a3, c)
check(pago3, 'OP con retenciones de IIBB en dos jurisdicciones', e)
certs = {int(f['jurisdiccion_id']): int(f['certificado_nro']) for f in sql(
    f"SELECT jurisdiccion_id, certificado_nro FROM gestion__pagos_retenciones WHERE pago_id={pago3 or 0} AND impuesto_tipo_id={IIBB}")}
check(certs.get(ARBA) == antes[ARBA] + 1 and certs.get(NQN) == antes[NQN] + 1,
      f'cada jurisdicción con su serie: Buenos Aires {certs.get(ARBA)}, Neuquén {certs.get(NQN)}', str(certs))

# ---------------------------------------------------------------------------------------------
caso('Caso R7 - Empresa no agente y aislamiento entre empresas')
sql("UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id=2 WHERE empresa_id=6 AND impuesto_tipo_id IN (14, 15, 16, 17)")
r = ajax('pagos_ajax.php', dict(accion='maestros', empresa_idx=6), 'GET', modulo='gestion_normal', empresa_id=6)
d = r.get('data', {}) if ok_resp(r) else {}
check(ok_resp(r) and not d.get('retenciones_config') and not any(m['medio_pago_tipo'] == 'RETENCION' for m in d.get('medios_pago', [])),
      'empresa 6 (no agente, gestion_normal): sin retenciones ni medios de retención en la OP', err(r))
r = ajax('retenciones_proveedores_ajax.php', dict(accion='datos', empresa_idx=6, entidad_id=PROV), 'GET', modulo='gestion_normal', empresa_id=6)
check(not ok_resp(r), 'la empresa 6 no puede leer las retenciones de un proveedor de la empresa 2', err(r))
r = ajax('retenciones_proveedores_ajax.php', dict(accion='agregar_condicion', empresa_idx=6, entidad_id=PROV, impuesto_tipo_id=GAN, jurisdiccion_id='',
         condicion='INSCRIPTO', regimen_id='', f_desde=HOY, f_hasta=''), modulo='gestion_normal', empresa_id=6)
check(not ok_resp(r), 'ni cargarle una condición', err(r))
