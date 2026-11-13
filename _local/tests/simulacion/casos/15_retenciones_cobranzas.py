"""Retenciones sufridas en cobranzas: IVA, Ganancias e Ingresos Brutos que nos practica el cliente.

Corre el mismo circuito en gestion (empresa 2, pág. 105) y en gestion_normal (empresa 6, pág. 605):
la pantalla ofrece los medios RET_IVA / RET_GAN / RET_IIBB con su impuesto, solo impuestos de retención
sufrida (aplica_venta) y solo provincias como jurisdicción; el recibo valida impuesto del medio, certificado
obligatorio y no repetido para el cliente, y provincia en IIBB; al confirmar, las retenciones no mueven
tesorería y van al Debe: IVA y Ganancias a la cuenta del medio, IIBB a la cuenta de su provincia en la
configuración de impuestos (script 20261012_retenciones_sufridas_01_cuentas_iibb.sql, que el caso aplica en la
copia: es idempotente).
"""
import random

from simlib import *

for _s in ('20261012_retenciones_sufridas_01_cuentas_iibb.sql', '20261015_retenciones_practicadas_01_padron.sql',
           '20261016_retenciones_practicadas_02_registro.sql', '20261016_cierres_fiscales_01_estructura.sql',
           '20261016_cierres_fiscales_02_registro_inscripciones.sql', '20261016_cierres_fiscales_03_liquidacion_retenciones.sql',
           '20261016_retenciones_sufridas_02_certificados.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))

SUFIJO = f'{HOY.replace("-", "")}-{random.randint(1000, 9999)}'
JUR_ARBA = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'ARBA'"))
JUR_NQN = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'DGR_NQN'"))


def cliente_empresa(empresa):
    return int(val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {empresa} AND EXISTS (SELECT 1 FROM gestion__entidades_roles xr JOIN gestion__roles_entidades xc ON xc.rol_entidad_id = xr.rol_entidad_id AND xc.rol_entidad_codigo = 'CLI' WHERE xr.entidad_id = gestion__entidades.entidad_id AND xr.f_baja IS NULL AND xr.tabla_estado_registro_id = 1) "
                   f"AND tabla_estado_registro_id = 1 ORDER BY entidad_id LIMIT 1"))


ENTORNOS = [
    dict(modulo='gestion', empresa=2, pagina=105, cliente=313),
    dict(modulo='gestion_normal', empresa=6, pagina=605, cliente=cliente_empresa(6)),
]


def medio(e, codigo):
    return uno(f"SELECT medio_pago_id, impuesto_tipo_id, cont_cuenta_id FROM gestion__medios_pago "
               f"WHERE empresa_id = {e['empresa']} AND codigo = '{codigo}'")


def guardar(e, medios, cobranza_id=0):
    p = dict(accion='guardar', empresa_idx=e['empresa'], pagina_idx=e['pagina'], entidad_id=e['cliente'],
             sucursal_id=e['sucursal'], comprobante_pv=e['pv'], comprobante_tipo_id=e['tipo'], f_emision=HOY,
             f_contabilidad=HOY, moneda_id=e['moneda'], tipo_cambio=1, observaciones='SIM retenciones',
             medios=medios, imputaciones=[])
    if cobranza_id:
        p['cobranza_id'] = cobranza_id
    return ajax('cobranzas_ajax.php', p, modulo=e['modulo'], empresa_id=e['empresa'])


def ret(m, importe, cert, jur=None, impuesto=None):
    d = dict(medio_pago_id=int(m['medio_pago_id']), importe=importe, importe_comprobante=importe, moneda_id=1, tipo_cambio=1,
             retencion_certificado_nro=cert, retencion_base_imponible=round(importe * 100 / 3, 2), retencion_alicuota=3)
    if jur:
        d['jurisdiccion_id'] = jur
    if impuesto:
        d['impuesto_tipo_id'] = impuesto
    return d


for e in ENTORNOS:
    tag = f"[{e['modulo']} / empresa {e['empresa']}]"
    # -----------------------------------------------------------------------------------------
    caso(f'Caso 1 - Maestros de la cobranza {tag}')
    r = ajax('cobranzas_ajax.php', dict(accion='maestros', empresa_idx=e['empresa'], pagina_idx=e['pagina']), 'GET',
             modulo=e['modulo'], empresa_id=e['empresa'])
    check(ok_resp(r), 'maestros', err(r))
    if not ok_resp(r):
        continue
    d = r['data']
    imps = {int(i['impuesto_tipo_id']): i for i in d.get('impuestos_retencion', [])}
    practicadas = [int(x['impuesto_tipo_id']) for x in sql("SELECT impuesto_tipo_id FROM gestion__impuestos_tipos WHERE es_retencion = 1 AND aplica_venta = 0")]
    check(imps and not (set(imps) & set(practicadas)), 'solo impuestos de retención sufrida (sin las practicadas)', str(list(imps)))
    iibb = [i for i in imps.values() if int(i.get('es_iibb', 0)) == 1]
    check(len(iibb) >= 1, 'el impuesto de IIBB viene marcado es_iibb', str(list(imps.values())))
    jurs = {int(j['jurisdiccion_id']) for j in d.get('jurisdicciones', [])}
    no_prov = {int(x['jurisdiccion_id']) for x in sql("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_tipo_id <> 2")}
    check(JUR_NQN in jurs and not (jurs & no_prov), 'jurisdicciones: solo provincias (sin AFIP / SIRCREB / SIRTAC)', str(sorted(jurs)))
    medios_cod = {m['codigo']: m for m in d.get('medios_pago', [])}
    check(all(c in medios_cod and int(medios_cod[c].get('impuesto_tipo_id') or 0) > 0 for c in ('RET_IVA', 'RET_GAN', 'RET_IIBB')),
          'los medios RET_IVA / RET_GAN / RET_IIBB vienen con su impuesto', str(list(medios_cod)))
    check('DEB_CTA' not in medios_cod, 'Débito en cuenta (aplica_cobro = 0) no se ofrece en cobranzas')
    pv = (d.get('puntos_venta') or [{}])[0]
    e['pv'] = int(pv.get('punto_venta_id') or 0)
    e['sucursal'] = int(pv.get('sucursal_id') or val(f"SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = {e['empresa']} ORDER BY sucursal_id LIMIT 1"))
    t = ajax('cobranzas_ajax.php', dict(accion='tipos_por_punto_venta', punto_venta_id=e['pv'], empresa_idx=e['empresa'], pagina_idx=e['pagina']),
             'GET', modulo=e['modulo'], empresa_id=e['empresa'])
    tipos = t.get('data', t) if isinstance(t, dict) else t
    e['tipo'] = int((tipos or [{}])[0].get('comprobante_tipo_id') or 24) if isinstance(tipos, list) else 24
    e['moneda'] = int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {e['empresa']} ORDER BY moneda_id LIMIT 1") or 1)
    check(e['pv'] > 0, 'punto de venta de recibos', str(pv))

    m_iva, m_gan, m_iibb = medio(e, 'RET_IVA'), medio(e, 'RET_GAN'), medio(e, 'RET_IIBB')
    m_efe = medio(e, 'EFECTIVO')
    caja = int(val(f"SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {e['empresa']} "
                   f"AND sucursal_id = {e['sucursal']} AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1 LIMIT 1") or 0)
    efectivo = dict(medio_pago_id=int(m_efe['medio_pago_id']), importe=1000, importe_comprobante=1000, moneda_id=1, tipo_cambio=1,
                    tesoreria_cuenta_id=caja)

    # -----------------------------------------------------------------------------------------
    caso(f'Caso 2 - Rechazos al guardar {tag}')
    n0 = int(val(f"SELECT COUNT(*) FROM gestion__cobranzas WHERE empresa_id = {e['empresa']}"))
    r = guardar(e, [efectivo, ret(m_iibb, 15, f'SIM-IIBB-{SUFIJO}')])
    check(not ok_resp(r) and 'jurisdicci' in err(r).lower(), 'IIBB sin provincia: rechazado', err(r))
    r = guardar(e, [ret(m_gan, 20, f'SIM-X-{SUFIJO}', impuesto=int(m_iva['impuesto_tipo_id']))])
    check(not ok_resp(r) and 'otro impuesto' in err(r).lower(), 'medio de Ganancias con impuesto de IVA: rechazado', err(r))
    r = guardar(e, [ret(m_iibb, 15, f'SIM-AFIP-{SUFIJO}', jur=1)])
    check(not ok_resp(r) and 'jurisdicci' in err(r).lower(), 'IIBB con jurisdicción no provincial (AFIP): rechazado', err(r))
    r = guardar(e, [ret(m_iva, 30, f'SIM-DUP-{SUFIJO}'), ret(m_iva, 30, f'SIM-DUP-{SUFIJO}')])
    check(not ok_resp(r) and 'dos veces' in err(r).lower(), 'mismo certificado dos veces en el recibo: rechazado', err(r))
    check(int(val(f"SELECT COUNT(*) FROM gestion__cobranzas WHERE empresa_id = {e['empresa']}")) == n0, 'los rechazos no dejaron borradores')
    # Sin número de certificado se acepta: queda PENDIENTE (etapa 4; se completa en Control Fiscal)
    r = guardar(e, [ret(m_iva, 30, '')])
    cob_p = (r.get('cobranza_id') or (r.get('data') or {}).get('cobranza_id')) if isinstance(r, dict) else None
    est = uno(f"SELECT cm.retencion_certificado_estado, cm.retencion_periodo_computo FROM gestion__cobranzas_medios cm "
              f"WHERE cm.cobranza_id = {int(cob_p or 0)} AND cm.impuesto_tipo_id IS NOT NULL")
    check(cob_p and est and est['retencion_certificado_estado'] == 'PENDIENTE' and est['retencion_periodo_computo'] == HOY[:7],
          'retención sin certificado: se guarda con el certificado PENDIENTE y período de cómputo del recibo', err(r) + str(est))
    if cob_p:
        ajax('cobranzas_ajax.php', dict(accion='eliminar', id=cob_p, empresa_idx=e['empresa'], pagina_idx=e['pagina']), modulo=e['modulo'], empresa_id=e['empresa'])

    # -----------------------------------------------------------------------------------------
    caso(f'Caso 3 - Recibo con efectivo y retenciones de IVA, Ganancias e IIBB (ARBA y Neuquén) {tag}')
    cert = {k: f'SIM-{k}-{SUFIJO}' for k in ('IVA', 'GAN', 'ARBA', 'NQN')}
    r = guardar(e, [efectivo, ret(m_iva, 30, cert['IVA']), ret(m_gan, 20, cert['GAN']),
                    ret(m_iibb, 10, cert['ARBA'], jur=JUR_ARBA), ret(m_iibb, 15, cert['NQN'], jur=JUR_NQN)])
    cob = r.get('cobranza_id') if isinstance(r, dict) else None
    check(ok_resp(r) and cob, 'borrador del recibo por 1075', err(r))
    if not cob:
        continue
    filas = sql(f"SELECT cm.medio_pago_id, cm.impuesto_tipo_id, cm.jurisdiccion_id, cm.retencion_certificado_nro FROM gestion__cobranzas_medios cm "
                f"WHERE cm.cobranza_id = {cob} AND cm.retencion_certificado_nro IS NOT NULL ORDER BY cm.cobranza_medio_id")
    check(len(filas) == 4, '4 líneas de retención guardadas', str(filas))
    iva_f = next((f for f in filas if f['retencion_certificado_nro'] == cert['IVA']), {})
    check(int(iva_f.get('impuesto_tipo_id') or 0) == int(m_iva['impuesto_tipo_id']) and iva_f.get('jurisdiccion_id') is None,
          'IVA: impuesto tomado del medio y sin jurisdicción', str(iva_f))
    nqn_f = next((f for f in filas if f['retencion_certificado_nro'] == cert['NQN']), {})
    check(int(nqn_f.get('jurisdiccion_id') or 0) == JUR_NQN, 'IIBB Neuquén guarda la jurisdicción', str(nqn_f))

    caja0 = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {caja}"))
    r = ajax('cobranzas_ajax.php', dict(accion='confirmar', id=cob, empresa_idx=e['empresa'], pagina_idx=e['pagina']),
             modulo=e['modulo'], empresa_id=e['empresa'])
    check(ok_resp(r), 'confirmar el recibo', err(r))
    cr = uno(f"SELECT comprobante_id, importe_total FROM gestion__comprobantes WHERE tabla_origen_id = 275 AND registro_origen_id = {cob}")
    comp_id = int(cr['comprobante_id']) if cr else 0
    check(comp_id and abs(num(cr['importe_total']) - 1075) < 0.01, 'comprobante del recibo por 1075', str(cr))
    movs = sql(f"SELECT tesoreria_cuenta_id, importe FROM gestion__tesoreria_movimientos WHERE comprobante_id = {comp_id}")
    check(len(movs) == 1 and abs(num(movs[0]['importe']) - 1000) < 0.01, 'tesorería: solo el efectivo (las retenciones no mueven fondos)', str(movs))
    caja1 = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {caja}"))
    check(abs(caja1 - caja0 - 1000) < 0.01, f'caja +1000 ({caja0} -> {caja1})')

    det = sql(f"SELECT d.cuenta_id AS cont_cuenta_id, cc.nombre, d.importe_local, d.descripcion FROM gestion__cont_asientos a "
              f"JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
              f"JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id "
              f"WHERE a.comprobante_id = {comp_id} AND a.tabla_estado_registro_id <> 2")
    def debe(cuenta_id):
        return round(sum(num(x['importe_local']) for x in det if int(x['cont_cuenta_id']) == int(cuenta_id) and num(x['importe_local']) > 0), 2)
    check(debe(m_iva['cont_cuenta_id']) == 30, 'asiento: Debe 30 en la cuenta de Retenciones de IVA del medio', str(det))
    check(debe(m_gan['cont_cuenta_id']) == 20, 'asiento: Debe 20 en la cuenta de Retenciones de Ganancias del medio', str(det))
    check(debe(m_iibb['cont_cuenta_id']) == 10, 'asiento: Debe 10 de IIBB ARBA en la cuenta del medio (la que ya tenía la empresa)', str(det))
    cta_nqn = val(f"SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {e['empresa']} "
                  f"AND impuesto_tipo_id = {m_iibb['impuesto_tipo_id']} AND jurisdiccion_id = {JUR_NQN} AND tabla_estado_registro_id = 1")
    nom_nqn = val(f"SELECT nombre FROM gestion__cont_cuentas WHERE cont_cuenta_id = {int(cta_nqn or 0)} AND empresa_id = {e['empresa']}")
    check(cta_nqn and 'Neuqu' in (nom_nqn or '') and debe(cta_nqn) == 15, f'asiento: Debe 15 de IIBB Neuquén en su cuenta ({nom_nqn})', str(det))
    check(any(cert['NQN'] in (x['descripcion'] or '') for x in det), 'la línea del asiento lleva el número de certificado', str(det))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso 4 - Certificado repetido para el mismo cliente {tag}')
    r = guardar(e, [efectivo, ret(m_iva, 30, cert['IVA'])])
    check(not ok_resp(r) and 'ya está registrado' in err(r), 'el mismo certificado de IVA en otro recibo: rechazado', err(r))
    r = guardar(e, [efectivo, ret(m_iibb, 15, cert['NQN'], jur=JUR_ARBA)])
    cob_otro = r.get('cobranza_id') if isinstance(r, dict) else None
    check(ok_resp(r), 'el mismo número en otra provincia es otro certificado: se acepta', err(r))
    if cob_otro:
        ajax('cobranzas_ajax.php', dict(accion='eliminar', id=cob_otro, empresa_idx=e['empresa'], pagina_idx=e['pagina']),
             modulo=e['modulo'], empresa_id=e['empresa'])

    # -----------------------------------------------------------------------------------------
    caso(f'Caso 5 - Anular el recibo libera los certificados {tag}')
    r = ajax('cobranzas_ajax.php', dict(accion='anular', id=cob, motivo='Simulación: retenciones', empresa_idx=e['empresa'], pagina_idx=e['pagina']),
             modulo=e['modulo'], empresa_id=e['empresa'])
    check(ok_resp(r), 'anular el recibo', err(r))
    check(abs(num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {caja}")) - caja0) < 0.01, 'caja restituida')
    r = guardar(e, [efectivo, ret(m_iva, 30, cert['IVA'])])
    cob2 = r.get('cobranza_id') if isinstance(r, dict) else None
    check(ok_resp(r), 'con el recibo anulado, el certificado se puede volver a cargar', err(r))
    if cob2:
        r = ajax('cobranzas_ajax.php', dict(accion='eliminar', id=cob2, empresa_idx=e['empresa'], pagina_idx=e['pagina']),
                 modulo=e['modulo'], empresa_id=e['empresa'])
        check(ok_resp(r), 'eliminar el borrador de prueba', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso 6 - Cobro desde la factura: maestros con provincias {tag}')
    r = ajax('ventas_facturas_ajax.php', dict(accion='cobro_maestros', empresa_idx=e['empresa']), 'GET', modulo=e['modulo'], empresa_id=e['empresa'])
    check(ok_resp(r) and JUR_NQN in {int(j['jurisdiccion_id']) for j in r['data'].get('jurisdicciones', [])}
          and not (set(int(i['impuesto_tipo_id']) for i in r['data'].get('impuestos_retencion', [])) & set(practicadas)),
          'cobro_maestros trae provincias y solo retenciones sufridas', err(r))
