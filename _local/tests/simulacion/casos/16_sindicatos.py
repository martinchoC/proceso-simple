"""Módulo 6 - Sindicatos (_local/docs/sindicatos/): circuito de aportes de punta a punta sobre la empresa 7 (AEC).

Usa la configuración que deja _local/sql/mantenimiento/20261012_sindicatos_12_alta_empresa_sindicato.sql (plan de cuentas,
punto de venta, tipos de comprobante, cuentas de tesorería y medios de cobro). Sesión: usuario 1 en la empresa 7,
módulo 'sindicatos'.

Circuito: alta de empleador con Casa Central y sucursales -> DDJJ -> volante -> rendición y cobro (con ND de
intereses) -> pagos repetidos, fuera de término o con importe distinto -> rectificativa sobre DDJJ pagada y sobre
DDJJ con volante vigente -> plan de pagos y su anulación -> acta de inspección y su anulación -> seguridad
(registro de otra empresa, perfil sin permiso).

La rendición se carga por SQL (subir_archivo necesita $_FILES); el procesamiento sí va por rendiciones_ajax.php.
"""
import json
import time

from simlib import *

E = 7
MOD = 'sindicatos'
CTX = {}


def sx(archivo, params, method='POST', **kw):
    return ajax(archivo, params, method, modulo=MOD, empresa_id=kw.pop('empresa_id', E), **kw)


def ok_sindi(r):
    return ok_resp(r) or (isinstance(r, dict) and r.get('status') == 'ok')


def comps(ids):
    ids = [int(i) for i in ids] or [0]
    return sql(f"SELECT comprobante_id, empresa_id, sucursal_id, comprobante_pv, comprobante_nro, signo, tabla_estado_registro_id estado, "
               f"importe_total, importe_pendiente, comprobante_codigo FROM gestion__comprobantes WHERE comprobante_id IN ({','.join(map(str, ids))})")


def pendiente(ids):
    return round(sum(num(c['importe_pendiente']) for c in comps(ids)), 2)


def asientos_ok(ids):
    """Asientos de esos comprobantes: balanceados y con cuentas de la empresa 7."""
    ids = [int(i) for i in ids] or [0]
    det = sql(f"SELECT a.cont_asiento_id, d.importe_local, cc.empresa_id FROM gestion__cont_asientos a "
              f"JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
              f"JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = d.cuenta_id "
              f"WHERE a.comprobante_id IN ({','.join(map(str, ids))})")
    por = {}
    for d in det:
        por.setdefault(d['cont_asiento_id'], []).append(d)
    balance = all(abs(sum(num(x['importe_local']) for x in l)) < 0.01 for l in por.values())
    propias = all(int(d['empresa_id']) == E for d in det)
    return bool(por) and balance and propias, f'{len(por)} asientos, balanceados={balance}, cuentas propias={propias}'


def saldo_cta(codigo):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE empresa_id = {E} AND codigo = '{codigo}'"))


def rendir(identificador, f_pago, importe, nombre):
    """Rendición del Banco Nación con una línea (como la deja subir_archivo) y su procesamiento por el ajax."""
    medio = val(f"SELECT medio_pago_id FROM sindi__medios_pago WHERE empresa_id = {E} AND driver = 'BNA_BARRAS' LIMIT 1")
    sql(f"INSERT INTO sindi__rendiciones (empresa_id, medio_pago_id, f_rendicion, archivo_nombre, archivo_hash, total_registros, "
        f"total_importe, tabla_estado_registro_id, usuario_id, fecha_creacion) VALUES ({E}, {medio}, '{HOY}', '{nombre}', "
        f"SHA2('{nombre}{time.time()}', 256), 1, {importe}, 8, 1, NOW())")
    rid = int(val(f"SELECT MAX(rendicion_id) FROM sindi__rendiciones WHERE empresa_id = {E}"))
    sql(f"INSERT INTO sindi__rendiciones_lineas (rendicion_id, empresa_id, numero_linea, identificador_externo, f_pago, f_acreditacion, "
        f"importe_rendido, canal_origen, resultado_conciliacion, fecha_creacion) VALUES ({rid}, {E}, 1, '{identificador}', '{f_pago}', "
        f"'{f_pago}', {importe}, 'BNA_BARRAS', 'OK', NOW())")
    r = sx('rendiciones_ajax.php', dict(accion='procesar', rendicion_id=rid))
    linea = uno(f"SELECT * FROM sindi__rendiciones_lineas WHERE rendicion_id = {rid}")
    return r, linea


def ddjj(empleador, periodo, nomina, ddjj_id=0):
    cab = dict(empleador_id=empleador, periodo=periodo, ddjj_id=ddjj_id, tipo='EMPLEADOR', origen_ingreso='MANUAL')
    r = sx('ddjj_ajax.php', dict(accion='guardar_borrador', cabecera=json.dumps(cab), nomina=json.dumps(nomina)))
    did = (r.get('ddjj_id') or (r.get('data') or {}).get('ddjj_id')) if isinstance(r, dict) else None
    return r, int(did or 0)


def confirmar(did):
    r = sx('ddjj_ajax.php', dict(accion='confirmar', id=did))
    return r, [int(c) for c in (r.get('comprobantes') or [])] if isinstance(r, dict) else []


CAT = int(val(f"SELECT categoria_id FROM sindi__categorias WHERE empresa_id = {E} ORDER BY categoria_id LIMIT 1"))
NOMINA = [dict(cuil='20333333334', nombre='SIM ACOSTA LUIS', categoria_id=CAT, rem_1=4000000, rem_2=0, afiliado=1),
          dict(cuil='27222222228', nombre='SIM BRAVO EVA', categoria_id=CAT, rem_1=3500000, rem_2=0, afiliado=0)]
F1 = time.strftime('%Y-%m-%d', time.localtime(time.time() + 5 * 86400))
F2 = time.strftime('%Y-%m-%d', time.localtime(time.time() + 15 * 86400))

# ---------------------------------------------------------------------------------------------
caso('Caso 160 - Alta de empleador: Casa Central N° 1 automática y sucursales')
cuit = '3071' + str(int(time.time()))[-7:]
r = sx('empleadores_ajax.php', dict(accion='guardar', cuit=cuit, razon_social='SIM COMERCIO SINDICATO SA', domicilio='Calle 1',
                                    telefono='111', email='sim@ejemplo.com', rubro='Comercio'))
check(ok_resp(r), 'alta de empleador', err(r))
EMP = int(((r.get('data') or {}).get('empleador_id')) or 0) if isinstance(r, dict) else 0
CTX['emp'] = EMP
suc = sql(f"SELECT s.sucursal_numero, s.sucursal_nombre FROM gestion__entidades_sucursales s JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id "
          f"WHERE e.empleador_id = {EMP}")
check(len(suc) == 1 and suc[0]['sucursal_numero'] == '1' and suc[0]['sucursal_nombre'] == 'Casa Central', 'Casa Central N° 1 creada', str(suc))
r = sx('empleadores_ajax.php', dict(accion='guardar', empleador_id=EMP, cuit=cuit, razon_social='SIM COMERCIO SINDICATO SA',
                                    domicilio='Calle 2', telefono='222', email='sim@ejemplo.com', rubro='Comercio'))
n = int(val(f"SELECT COUNT(*) FROM gestion__entidades_sucursales s JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id WHERE e.empleador_id = {EMP}"))
check(ok_resp(r) and n == 1, 'guardar de nuevo no duplica la Casa Central', f'{n} sucursales')
r = sx('empleadores_ajax.php', dict(accion='guardar_sucursal', empleador_id=EMP, sucursal_nombre='SIM Sucursal Centro', sucursal_direccion='Calle 3'))
check(ok_resp(r) and 'N° 2' in (r.get('message') or ''), 'nueva sucursal toma el N° 2', err(r))
lista = sx('empleadores_ajax.php', dict(accion='listar_sucursales', empleador_id=EMP), 'GET')
central = next((s for s in (lista.get('data') or []) if s['sucursal_numero'] in (1, '1')), None) if isinstance(lista, dict) else None
r = sx('empleadores_ajax.php', dict(accion='estado_sucursal', empleador_id=EMP, sucursal_id=(central or {}).get('sucursal_id', 0), estado=2))
check(not ok_resp(r) and 'Casa Central' in err(r), 'la Casa Central no se puede inhabilitar', err(r))
roles = int(val(f"SELECT COUNT(*) FROM gestion__entidades_roles r JOIN sindi__empleadores e ON e.entidad_id = r.entidad_id WHERE e.empleador_id = {EMP}"))
check(roles == 1, 'rol EMPLEADOR una sola vez', f'{roles} roles')

# ---------------------------------------------------------------------------------------------
caso('Caso 161 - DDJJ confirmada: comprobantes por concepto con PV, sucursal y cuentas de la empresa')
r, D1 = ddjj(EMP, 202609, NOMINA)
check(ok_resp(r) and D1, 'borrador de DDJJ', err(r))
r, C1 = confirmar(D1)
check(ok_resp(r) and len(C1) >= 1, 'confirmar DDJJ', err(r))
CTX['D1'], CTX['C1'] = D1, C1
pv = uno(f"SELECT pv.punto_venta_id, pv.sucursal_id FROM gestion__puntos_venta pv JOIN gestion__bocas b ON b.boca_id = pv.boca_id "
         f"WHERE pv.empresa_id = {E} AND b.codigo = 'BOC-SINDI'")
cs = comps(C1)
check(all(int(c['empresa_id']) == E and int(c['comprobante_pv']) == int(pv['punto_venta_id']) and int(c['sucursal_id']) == int(pv['sucursal_id'])
          and c['estado'] == '5' and c['signo'] == '1' for c in cs), 'empresa, PV y sucursal propios, estado 5, signo +', str(cs))
okA, det = asientos_ok(C1)
check(okA, 'asientos balanceados con cuentas de la empresa 7', det)
r2, _ = confirmar(D1)
check(not ok_resp(r2), 'una segunda confirmación se rechaza', err(r2))
antes_ddjj = int(val(f"SELECT COUNT(*) FROM sindi__ddjj WHERE empresa_id = {E}"))
r2, _ = ddjj(EMP, 202605, NOMINA + [dict(cuil='27444444445', nombre='SIM CUIL MAL', categoria_id=CAT, rem_1=1000, rem_2=0, afiliado=0)])
check(not ok_resp(r2) and 'CUIL' in err(r2) and int(val(f"SELECT COUNT(*) FROM sindi__ddjj WHERE empresa_id = {E}")) == antes_ddjj,
      'un CUIL con dígito verificador inválido rechaza el borrador sin dejar nada a medias', err(r2))
TOTAL1 = round(sum(num(c['importe_total']) for c in cs), 2)

# ---------------------------------------------------------------------------------------------
caso('Caso 162 - Volante de pago: intereses fijados al emitir, código de barras y deuda bloqueada')
r = sx('volantes_ajax.php', dict(accion='emitir', empleador_id=EMP, comprobante_ids=C1, f_pago_1=F1, f_pago_2=F2))
check(ok_resp(r), 'emitir volante', err(r))
V1 = int(r.get('volante_id') or 0) if isinstance(r, dict) else 0
vol = uno(f"SELECT * FROM sindi__volantes WHERE volante_id = {V1}") or {}
pref = val(f"SELECT barras_prefijo FROM sindi__medios_pago WHERE empresa_id = {E} AND driver = 'BNA_BARRAS' LIMIT 1")
check(vol.get('tabla_estado_registro_id') == '31' and (vol.get('codigo_barras') or '').startswith(pref or '-'),
      'volante Emitido con el prefijo de barras del medio Banco Nación', str({k: vol.get(k) for k in ('numero', 'codigo_barras', 'tabla_estado_registro_id')}))
r = sx('volantes_ajax.php', dict(accion='emitir', empleador_id=EMP, comprobante_ids=C1, f_pago_1=F1, f_pago_2=F2))
check(not ok_resp(r), 'la misma deuda no entra en otro volante vigente', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 163 - Rendición del Banco Nación: cobro, tesorería, ND de intereses e imputaciones')
antes = saldo_cta('BNA_RECAUDA')
r, lin = rendir(vol.get('codigo_barras'), HOY, num(vol.get('importe_1')), 'SIM_BNA_1')
check(ok_resp(r) and lin and lin['resultado_conciliacion'] == 'OK', 'línea conciliada OK', err(r) + ' ' + str(lin))
check(abs(saldo_cta('BNA_RECAUDA') - antes - num(vol.get('importe_1'))) < 0.01, 'ingreso en la cuenta BNA_RECAUDA por el importe del 1er vencimiento',
      f"{saldo_cta('BNA_RECAUDA') - antes} vs {vol.get('importe_1')}")
check(pendiente(C1) == 0 and {c['estado'] for c in comps(C1)} == {'40'}, 'deudas canceladas (40)', str(comps(C1)))
check(val(f"SELECT tabla_estado_registro_id FROM sindi__volantes WHERE volante_id = {V1}") == '40', 'volante Cancelado total')
cobro = [lin['comprobante_cobro_id']] + ([lin['nota_debito_interes_id']] if lin.get('nota_debito_interes_id') else [])
okA, det = asientos_ok(cobro)
check(okA, 'asientos del cobro balanceados con cuentas propias', det)
if num(vol.get('importe_interes_1')) > 0:
    nd = comps([lin['nota_debito_interes_id']])
    check(nd and abs(num(nd[0]['importe_total']) - num(vol['importe_interes_1'])) < 0.01 and num(nd[0]['importe_pendiente']) == 0,
          'ND de intereses por el interés fijado en el volante, cancelada', str(nd))

# ---------------------------------------------------------------------------------------------
caso('Caso 164 - Pagos con problemas: repetido, importe distinto y fuera de término (no mueven dinero)')
antes = saldo_cta('BNA_RECAUDA')
r, lin = rendir(vol.get('codigo_barras'), HOY, num(vol.get('importe_1')), 'SIM_BNA_REPETIDO')
check(lin and lin['resultado_conciliacion'] == 'DUPLICADA', 'pago rendido dos veces: DUPLICADA', str(lin))
r, D2 = ddjj(EMP, 202608, NOMINA)
r, C2 = confirmar(D2)
CTX['D2'], CTX['C2'] = D2, C2
r = sx('volantes_ajax.php', dict(accion='emitir', empleador_id=EMP, comprobante_ids=C2, f_pago_1=F1, f_pago_2=F2))
V2 = int(r.get('volante_id') or 0) if isinstance(r, dict) else 0
CTX['V2'] = V2
vol2 = uno(f"SELECT * FROM sindi__volantes WHERE volante_id = {V2}") or {}
r, lin = rendir(vol2.get('codigo_barras'), HOY, num(vol2.get('importe_1')) - 10, 'SIM_BNA_DIF')
check(lin and lin['resultado_conciliacion'] == 'DIFERENCIA', 'importe distinto: DIFERENCIA', str(lin))
tarde = time.strftime('%Y-%m-%d', time.localtime(time.time() + 20 * 86400))
r, lin = rendir(vol2.get('codigo_barras'), tarde, num(vol2.get('importe_2')), 'SIM_BNA_TARDE')
check(lin and lin['resultado_conciliacion'] == 'DIFERENCIA' and 'segundo vencimiento' in (lin.get('observaciones') or ''),
      'pagado después del 2.º vencimiento: DIFERENCIA', str(lin))
check(abs(saldo_cta('BNA_RECAUDA') - antes) < 0.01 and pendiente(C2) > 0, 'ninguno movió tesorería ni deuda',
      f"banco {saldo_cta('BNA_RECAUDA') - antes}, pendiente {pendiente(C2)}")

# ---------------------------------------------------------------------------------------------
caso('Caso 165 - Rectificativa sobre DDJJ pagada: reversión total y saldo a favor aplicado')
r = sx('ddjj_ajax.php', dict(accion='iniciar_rectificativa', id=CTX['D1']))
D1R = int(r.get('ddjj_id') or (r.get('data') or {}).get('ddjj_id') or 0) if isinstance(r, dict) else 0
check(D1R and D1R != CTX['D1'], 'borrador de rectificativa', err(r))
nom2 = [dict(e, rem_1=e['rem_1'] * 1.2) for e in NOMINA]
r, _ = ddjj(EMP, 202609, nom2, D1R)
r, C1R = confirmar(D1R)
check(ok_resp(r), 'confirmar rectificativa', err(r))
total_r = round(sum(num(c['importe_total']) for c in comps(C1R)), 2)
check(abs(pendiente(C1R) - (total_r - TOTAL1)) < 0.02, f'pendiente = nueva {total_r} - pagado {TOTAL1}', f'{pendiente(C1R)}')
revs = sql(f"SELECT comprobante_id, importe_pendiente FROM gestion__comprobantes WHERE empresa_id = {E} AND tabla_origen_id = 524 "
           f"AND registro_origen_id IN (SELECT -ddjj_concepto_id FROM sindi__ddjj_conceptos WHERE ddjj_id = {CTX['D1']})")
check(len(revs) == len(CTX['C1']) and all(num(x['importe_pendiente']) == 0 for x in revs), 'una reversión por concepto, con el saldo aplicado', str(revs))
check(val(f"SELECT tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = {CTX['D1']}") == '2', 'la original queda Rectificada (2)')
okA, det = asientos_ok(C1R + [x['comprobante_id'] for x in revs])
check(okA, 'asientos de la rectificativa balanceados', det)
CTX['C1R'] = C1R

# ---------------------------------------------------------------------------------------------
caso('Caso 166 - Rectificativa con volante vigente: el volante se anula')
r = sx('ddjj_ajax.php', dict(accion='iniciar_rectificativa', id=CTX['D2']))
D2R = int(r.get('ddjj_id') or (r.get('data') or {}).get('ddjj_id') or 0) if isinstance(r, dict) else 0
r, _ = ddjj(EMP, 202608, nom2, D2R)
r, C2R = confirmar(D2R)
check(ok_resp(r), 'confirmar rectificativa', err(r))
check(val(f"SELECT tabla_estado_registro_id FROM sindi__volantes WHERE volante_id = {CTX['V2']}") == '38', 'volante vigente de la DDJJ rectificada anulado (38)')
check(pendiente(CTX['C2']) == 0 and abs(pendiente(C2R) - round(sum(num(c['importe_total']) for c in comps(C2R)), 2)) < 0.01,
      'sin pagos previos: la anterior queda en 0 y la nueva debe todo', f"anterior {pendiente(CTX['C2'])}, nueva {pendiente(C2R)}")
CTX['C2R'] = C2R

# ---------------------------------------------------------------------------------------------
caso('Caso 167 - Plan de pagos: refinanciación RPL, cuotas y anulación')
deudas = CTX['C1R'] + CTX['C2R']
capital = pendiente(deudas)
tipo_plan = val(f"SELECT plan_tipo_id FROM sindi__planes_tipos WHERE empresa_id = {E} ORDER BY cuotas_max DESC LIMIT 1")
r = sx('planes_ajax.php', dict(action='crear_y_confirmar', empleador_id=EMP, plan_tipo_id=tipo_plan, capital_original=capital + 100,
                               cantidad_cuotas=3, comprobantes_origen_ids=json.dumps(deudas)))
check(not ok_sindi(r) and 'no coincide' in err(r), 'capital distinto del saldo de las deudas: rechazado', err(r))
r = sx('planes_ajax.php', dict(action='crear_y_confirmar', empleador_id=EMP, plan_tipo_id=tipo_plan, capital_original=capital,
                               cantidad_cuotas=3, comprobantes_origen_ids=json.dumps(deudas)))
check(ok_sindi(r), 'plan confirmado', err(r))
plan = (r.get('data') or {}) if isinstance(r, dict) else {}
pid = int(plan.get('plan_id') or 0)
cuotas = [int(x['comprobante_id']) for x in (plan.get('cuotas_detalle') or [])]
check(pendiente(deudas) == 0 and abs(pendiente(cuotas) - num(plan.get('total_financiado'))) < 0.02,
      'deudas de origen canceladas y cuotas por el total financiado', f"origen {pendiente(deudas)}, cuotas {pendiente(cuotas)} vs {plan.get('total_financiado')}")
rpl = val(f"SELECT comprobante_id FROM gestion__comprobantes WHERE tabla_origen_id = 538 AND registro_origen_id = {pid}")
check(rpl is not None and comps([rpl])[0]['signo'] == '-1', 'crédito RPL de refinanciación (signo -)', str(rpl))
r = sx('planes_ajax.php', dict(action='anular', plan_id=pid, motivo='SIM'))
check(ok_sindi(r) and abs(pendiente(deudas) - capital) < 0.02 and {c['estado'] for c in comps(cuotas)} == {'38'},
      'anular: las deudas recuperan su saldo y las cuotas quedan anuladas', f"{err(r)} origen {pendiente(deudas)}")

# ---------------------------------------------------------------------------------------------
caso('Caso 168 - Acta de inspección: determinación, comprobante ACT y anulación con asiento inverso')
conc = val(f"SELECT concepto_id FROM sindi__conceptos WHERE empresa_id = {E} AND codigo = 'ART100'")
r = sx('actas_ajax.php', dict(action='crear', empleador_id=EMP, tipo_acta='NORMAL', f_inicio=HOY, observaciones='SIM'))
check(ok_sindi(r), 'crear acta', err(r))
aid = int(r.get('acta_id') or 0) if isinstance(r, dict) else 0
r = sx('actas_ajax.php', dict(action='agregar_periodo', acta_id=aid, periodo=202605,
                              items=json.dumps([dict(concepto_id=int(conc), declarado=0, determinado=50000, pagado=0)])))
check(ok_sindi(r), 'período con capital determinado', err(r))
r = sx('actas_ajax.php', dict(action='confirmar', acta_id=aid))
check(ok_sindi(r), 'confirmar acta', err(r))
ac = val(f"SELECT comprobante_id FROM sindi__actas WHERE acta_id = {aid}")
ca = comps([ac])
check(ca and int(ca[0]['empresa_id']) == E and ca[0]['estado'] == '5' and num(ca[0]['importe_pendiente']) > 0, 'comprobante ACT de la empresa con saldo', str(ca))
okA, det = asientos_ok([ac])
check(okA, 'asiento de la determinación balanceado con cuentas propias', det)
r = sx('actas_ajax.php', dict(action='anular', acta_id=aid, motivo='SIM'))
ca = comps([ac])
okA, det = asientos_ok([ac])
check(ok_sindi(r) and ca[0]['estado'] == '38' and num(ca[0]['importe_pendiente']) == 0 and okA,
      'anular: comprobante 38 sin saldo y asiento inverso balanceado', f'{err(r)} {ca} {det}')

# ---------------------------------------------------------------------------------------------
caso('Caso 169 - Seguridad: acta de otra empresa y usuario sin permiso')
otra = val("SELECT acta_id FROM sindi__actas WHERE empresa_id <> 7 LIMIT 1")
if otra:
    r = sx('actas_ajax.php', dict(action='obtener', acta_id=otra), 'GET')
    check(not ok_sindi(r), 'un acta de otra empresa no se puede consultar desde la empresa 7', err(r))
ext = uno("SELECT up.usuario_id, ep.empresa_id FROM conf__usuarios_perfiles up JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id "
          "WHERE ep.perfil_id_base = 604 AND up.tabla_estado_registro_id = 1 AND up.usuario_id NOT IN (1) LIMIT 1")
if ext:
    r = sx('actas_ajax.php', dict(action='listar'), 'GET', usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check(not ok_sindi(r), 'un usuario Empleador no accede a las actas (página sin permiso)', err(r))
    r = sx('medios_pago_ajax.php', dict(accion='listar'), 'GET', usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check(not ok_sindi(r) and not (isinstance(r, dict) and r.get('data')), 'un usuario Empleador no accede a Medios de Cobro (datos de acceso a bancos)', err(r))
    r = sx('empleadores_ajax.php', dict(accion='listar'), 'GET', usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    filas = (r.get('data') or []) if isinstance(r, dict) else []
    asignados = int(val(f"SELECT COUNT(*) FROM sindi__usuarios_empleadores WHERE usuario_id = {ext['usuario_id']} AND empresa_id = {ext['empresa_id']} AND tabla_estado_registro_id = 1"))
    check(len(filas) <= asignados, 'el Empleador solo ve sus empleadores asignados', f'{len(filas)} filas, {asignados} asignados')
r = ajax('actas_ajax.php', dict(action='listar'), 'GET', usuario_id=0, modulo=MOD, empresa_id=E)
check(not ok_sindi(r), 'sin sesión válida: rechazado', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 171 - Importación de nómina (página 1021): planilla CSV con filas rechazadas, F.931 y archivo inválido')
csv = ("CUIL;Nombre;Categoria;Remunerativo;NoRemunerativo;MediaJornada;Afiliado;Jubilado\n"
       "20333333334;SIM ACOSTA LUIS;;4100000;0;0;1;0\n"
       "27222222228;SIM BRAVO EVA;;3600000;0;0;0;0\n"
       "12345678901;CUIL MAL;;100;0;0;0;0\n")
r = sx('ddjj_importar_ajax.php', dict(accion='importar', empleador_id=EMP, periodo=202606, formato='EXCEL_CSV',
                                       _files={'archivo': {'name': 'nomina_junio.csv', 'content': csv}}))
data = (r.get('data') or {}) if isinstance(r, dict) else {}
check(ok_resp(r) and data.get('aceptadas') == 2 and data.get('rechazadas') == 1 and data.get('ddjj_id'),
      'CSV: 2 aceptadas, 1 rechazada (CUIL inválido) y borrador de DDJJ generado', err(r) + ' ' + str(data))
imp = uno(f"SELECT * FROM sindi__ddjj_importaciones WHERE importacion_id = {int(data.get('importacion_id') or 0)}") or {}
check(imp.get('tabla_estado_registro_id') == '5' and imp.get('formato') == 'EXCEL_CSV' and 'CUIL' in (imp.get('errores_detalle') or ''),
      'importación registrada (Procesada) con el log del rechazo', str({k: imp.get(k) for k in ('tabla_estado_registro_id', 'formato', 'errores_detalle')}))
dd = uno(f"SELECT origen_ingreso, tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = {int(data.get('ddjj_id') or 0)}") or {}
nemp = int(val(f"SELECT COUNT(*) FROM sindi__ddjj_empleados WHERE ddjj_id = {int(data.get('ddjj_id') or 0)}"))
check(dd.get('origen_ingreso') == 'PLANILLA' and dd.get('tabla_estado_registro_id') == '3' and nemp == 2, 'borrador con origen PLANILLA y 2 empleados', f'{dd} {nemp}')
ruta = imp.get('archivo_ruta') or ''
check(ruta.startswith('uploads/sindicatos/') and ruta.endswith('.csv') and 'nomina_junio' not in ruta, 'archivo guardado con nombre aleatorio en la carpeta privada', ruta)
# La misma nómina por F.931 (ancho fijo) sobre el mismo período: reemplaza la nómina del borrador abierto
def linea931(cuil, nombre, bruto):
    l = list(' ' * 420)
    l[0:11] = cuil
    l[11:41] = nombre.ljust(30)[:30]
    l[46:48] = '00'
    l[61:67] = '000000'
    l[212:224] = f'{bruto:12.2f}'.replace('.', ',')
    l[405:417] = f'{0:12.2f}'.replace('.', ',')
    return ''.join(l)
f931 = linea931('20333333334', 'SIM ACOSTA LUIS', 4200000) + '\n'
r = sx('ddjj_importar_ajax.php', dict(accion='importar', empleador_id=EMP, periodo=202606, formato='F931_SICOSS',
                                       _files={'archivo': {'name': 'f931.txt', 'content': f931}}))
d2 = (r.get('data') or {}) if isinstance(r, dict) else {}
nemp = int(val(f"SELECT COUNT(*) FROM sindi__ddjj_empleados WHERE ddjj_id = {int(d2.get('ddjj_id') or 0)}"))
check(ok_resp(r) and d2.get('ddjj_id') == data.get('ddjj_id') and nemp == 1, 'F.931 sobre el mismo período: actualiza el mismo borrador (1 empleado)', err(r) + f' {d2} {nemp}')
r = sx('ddjj_importar_ajax.php', dict(accion='importar', empleador_id=EMP, periodo=202606, formato='EXCEL_CSV',
                                       _files={'archivo': {'name': 'malware.php', 'content': '<?php echo 1;'}}))
check(not ok_resp(r) and '.txt' in err(r), 'extensión no permitida rechazada', err(r))
r = sx('ddjj_importar_ajax.php', dict(accion='importar', empleador_id=EMP, periodo=202606, formato='EXCEL_CSV',
                                       _files={'archivo': {'name': 'vacio.csv', 'content': 'CUIL;Nombre\n99;X\n'}}))
d3 = (r.get('data') or {}) if isinstance(r, dict) else {}
check(not ok_resp(r) and not d3.get('ddjj_id') and val(f"SELECT tabla_estado_registro_id FROM sindi__ddjj_importaciones WHERE importacion_id = {int(d3.get('importacion_id') or 0)}") == '27',
      'sin filas válidas: queda Con errores (27) y no toca la DDJJ', err(r))
lista = sx('ddjj_importar_ajax.php', dict(accion='listar'), 'GET')
fila = next((x for x in (lista.get('data') or []) if int(x['importacion_id']) == int(data.get('importacion_id') or 0)), {}) if isinstance(lista, dict) else {}
check('btn-accion-ver' in (fila.get('acciones') or ''), 'el listado ofrece Ver detalle', str(fila)[:200])

# ---------------------------------------------------------------------------------------------
caso('Caso 172 - Sucursal del empleado en la nómina')
sucs = {int(s['sucursal_numero']): int(s['sucursal_id']) for s in sql(
    f"SELECT s.sucursal_numero, s.sucursal_id FROM gestion__entidades_sucursales s JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id "
    f"WHERE e.empleador_id = {CTX['emp']} AND s.sucursal_numero IS NOT NULL")}
otra_suc = val(f"SELECT s.sucursal_id FROM gestion__entidades_sucursales s JOIN sindi__empleadores e ON e.entidad_id = s.entidad_id "
               f"WHERE e.empresa_id = {E} AND e.empleador_id <> {CTX['emp']} AND s.sucursal_numero = 1 LIMIT 1")
nom_suc = [dict(NOMINA[0]), dict(NOMINA[1], sucursal_id=sucs.get(2), baja_motivo_id=None, f_baja='2026-04-15')]
r, Ds = ddjj(CTX['emp'], 202604, nom_suc)
renglones = {x['cuil']: x for x in sql(f"SELECT p.cuil, de.sucursal_id, de.f_baja FROM sindi__ddjj_empleados de JOIN sindi__personas p ON p.persona_id = de.persona_id WHERE de.ddjj_id = {Ds}")}
check(ok_resp(r) and int(renglones.get('20333333334', {}).get('sucursal_id') or 0) == sucs.get(1)
      and int(renglones.get('27222222228', {}).get('sucursal_id') or 0) == sucs.get(2),
      'sin sucursal: Casa Central; con sucursal N° 2: la N° 2', err(r) + ' ' + str(renglones))
check(renglones.get('27222222228', {}).get('f_baja') == '2026-04-15', 'la fecha de baja del renglón se graba bien', str(renglones.get('27222222228')))
if otra_suc:
    r, _ = ddjj(CTX['emp'], 202603, [dict(NOMINA[0], sucursal_id=int(otra_suc))])
    check(not ok_resp(r) and 'sucursal' in err(r).lower(), 'una sucursal de otro empleador se rechaza', err(r))
r, _ = confirmar(Ds)
r = sx('ddjj_ajax.php', dict(accion='iniciar_rectificativa', id=Ds))
Dsr = int(r.get('ddjj_id') or (r.get('data') or {}).get('ddjj_id') or 0) if isinstance(r, dict) else 0
suc_r = val(f"SELECT de.sucursal_id FROM sindi__ddjj_empleados de JOIN sindi__personas p ON p.persona_id = de.persona_id "
            f"WHERE de.ddjj_id = {Dsr} AND p.cuil = '27222222228'")
check(Dsr and int(suc_r or 0) == sucs.get(2), 'la rectificativa conserva la sucursal del renglón', f'{Dsr} {suc_r}')
r = sx('ddjj_ajax.php', dict(accion='combo_sucursales', empleador_id=CTX['emp']), 'GET')
check(ok_resp(r) and len(r.get('data') or []) >= 1, 'combo de sucursales del empleador (activas)', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 173 - Relaciones laborales mantenidas por la DDJJ')
def rel(cuil):
    return sql(f"SELECT rl.relacion_laboral_id, rl.f_alta, rl.f_baja, rl.ddjj_alta_id, rl.ddjj_baja_id, rl.tabla_estado_registro_id estado, rl.sucursal_id "
               f"FROM sindi__relaciones_laborales rl JOIN sindi__personas p ON p.persona_id = rl.persona_id "
               f"WHERE rl.empleador_id = {CTX['emp']} AND p.cuil = '{cuil}' ORDER BY rl.relacion_laboral_id")
ra, rb = rel('20333333334'), rel('27222222228')
check(len(ra) == 1 and ra[0]['f_baja'] is None and ra[0]['f_alta'] == '2026-04-01' and ra[0]['estado'] == '1',
      'una sola relación abierta por persona; el alta se adelanta al período más antiguo declarado (abril)', str(ra))
check(len(rb) == 1 and rb[0]['f_baja'] == '2026-04-15' and int(rb[0]['ddjj_baja_id'] or 0) == Ds,
      'la baja declarada en el renglón cierra la relación con su fecha', str(rb))
# Rectificativa de abril: la baja de B no se declara (se reabre) y se agrega C
nom_r = [dict(NOMINA[0]), dict(NOMINA[1], sucursal_id=sucs.get(2)),
         dict(cuil='20111111112', nombre='SIM CARRIZO JUAN', categoria_id=CAT, rem_1=3000000, rem_2=0, afiliado=0)]
r, _ = ddjj(CTX['emp'], 202604, nom_r, Dsr)
r, _ = confirmar(Dsr)
check(ok_resp(r), 'confirmar rectificativa de abril', err(r))
rb, rc = rel('27222222228'), rel('20111111112')
check(len(rb) == 1 and rb[0]['f_baja'] is None and rb[0]['ddjj_baja_id'] is None, 'la rectificativa reabre la baja que había cerrado la anterior', str(rb))
check(len(rc) == 1 and rc[0]['estado'] == '1' and int(rc[0]['ddjj_alta_id'] or 0) == Dsr and rc[0]['f_alta'] == '2026-04-01',
      'persona nueva: alta desde el primer día del período', str(rc))
# Otra rectificativa sin C: el alta que había hecho la anterior se anula
r = sx('ddjj_ajax.php', dict(accion='iniciar_rectificativa', id=Dsr))
Dsr2 = int(r.get('ddjj_id') or (r.get('data') or {}).get('ddjj_id') or 0) if isinstance(r, dict) else 0
r, _ = ddjj(CTX['emp'], 202604, nom_r[:2], Dsr2)
r, _ = confirmar(Dsr2)
rc = rel('20111111112')
check(ok_resp(r) and len(rc) == 1 and rc[0]['estado'] == '38', 'quien ya no figura en la rectificativa: su alta queda anulada (38)', err(r) + ' ' + str(rc))
abiertas = int(val(f"SELECT COUNT(*) FROM sindi__relaciones_laborales WHERE empleador_id = {CTX['emp']} AND f_baja IS NULL AND tabla_estado_registro_id = 1"))
check(abiertas == 2, 'quedan abiertas solo las dos personas declaradas', f'{abiertas}')

# ---------------------------------------------------------------------------------------------
caso('Caso 174 - Afiliaciones: actividad desde la DDJJ, solicitudes, grupo familiar, vigencias, bajas y cuotas')
def af(**kw):
    return sx('afiliados_ajax.php', kw)

act = sql(f"SELECT a.afiliacion_id, a.numero_afiliado, t.empresa_id tipo_emp, t.codigo FROM sindi__afiliaciones a "
          f"JOIN sindi__afiliaciones_tipos t ON t.afiliacion_tipo_id = a.afiliacion_tipo_id JOIN sindi__personas p ON p.persona_id = a.persona_id "
          f"WHERE a.empleador_id = {CTX['emp']} AND p.cuil = '20333333334' AND a.tabla_estado_registro_id = 1")
check(len(act) == 1 and act[0]['codigo'] == 'ACTIVIDAD' and int(act[0]['tipo_emp']) == E and act[0]['numero_afiliado'],
      'varias DDJJ con el mismo afiliado: una sola afiliación de ACTIVIDAD, del tipo de la empresa y con número', str(act))

r = sx('afiliaciones_solicitudes_ajax.php', dict(accion='guardar', tipo_codigo='ACTIVIDAD', cuil='20222222223', apellido='SIM VOLUNTARIO'))
check(not ok_resp(r) and 'DDJJ' in err(r), 'la afiliación de ACTIVIDAD no se pide por solicitud', err(r))
r = sx('afiliaciones_solicitudes_ajax.php', dict(accion='guardar', tipo_codigo='VOLUNTARIA', cuil='20222222223', apellido='SIM VOLUNTARIO',
                                                  nombre='PEDRO', f_nacimiento='1980-05-10', email='pedro@ejemplo.com'))
sid = int(((r.get('data') or {}).get('solicitud_id')) or 0) if isinstance(r, dict) else 0
check(ok_resp(r) and sid, 'solicitud voluntaria en Borrador', err(r))
r = sx('afiliaciones_solicitudes_ajax.php', dict(accion='ejecutar_accion', id=sid, accion_js='aprobar'))
check(not ok_resp(r), 'no se aprueba sin enviarla antes', err(r))
r1 = sx('afiliaciones_solicitudes_ajax.php', dict(accion='ejecutar_accion', id=sid, accion_js='enviar'))
r2 = sx('afiliaciones_solicitudes_ajax.php', dict(accion='ejecutar_accion', id=sid, accion_js='aprobar'))
TIT = int(((r2.get('data') or {}).get('afiliacion_id')) or 0) if isinstance(r2, dict) else 0
tit = uno(f"SELECT a.numero_afiliado, a.tabla_estado_registro_id, p.entidad_id FROM sindi__afiliaciones a JOIN sindi__personas p ON p.persona_id = a.persona_id WHERE a.afiliacion_id = {TIT}") or {}
rol = val(f"SELECT COUNT(*) FROM gestion__entidades_roles r JOIN gestion__roles_entidades c ON c.rol_entidad_id = r.rol_entidad_id "
          f"WHERE r.entidad_id = {int(tit.get('entidad_id') or 0)} AND c.rol_entidad_codigo = 'AFIL'")
check(ok_resp(r1) and ok_resp(r2) and tit.get('tabla_estado_registro_id') == '1' and tit.get('numero_afiliado') and rol == '1',
      'enviar y aprobar: afiliación voluntaria activa, con número y cuenta corriente (entidad con rol AFIL)', err(r2) + ' ' + str(tit))

r = af(accion='alta_familiar', titular_afiliacion_id=TIT, cuil='20444444445', apellido='SIM VOLUNTARIO', nombre='MAYOR',
       f_nacimiento='2000-01-01', parentesco_id=val(f"SELECT parentesco_id FROM sindi__parentescos WHERE empresa_id = {E} AND codigo = 'HIJO'"))
check(not ok_resp(r) and 'edad' in err(r), 'hijo de 26 años: rechazado por edad', err(r))
HIJO = val(f"SELECT parentesco_id FROM sindi__parentescos WHERE empresa_id = {E} AND codigo = 'HIJO'")
nac23 = str(int(HOY[:4]) - 23) + '-06-01'
r = af(accion='alta_familiar', titular_afiliacion_id=TIT, cuil='27333333339', apellido='SIM VOLUNTARIO', nombre='ESTUDIANTE', f_nacimiento=nac23, parentesco_id=HIJO)
check(not ok_resp(r), 'hijo de 23 años sin certificado: rechazado', err(r))
r = af(accion='alta_familiar', titular_afiliacion_id=TIT, cuil='27333333339', apellido='SIM VOLUNTARIO', nombre='ESTUDIANTE', f_nacimiento=nac23,
       parentesco_id=HIJO, documento='Certificado de alumno regular', f_presentacion=HOY)
FAM1 = int(((r.get('data') or {}).get('afiliacion_id')) or 0) if isinstance(r, dict) else 0
f1 = uno(f"SELECT numero_afiliado, f_vigencia FROM sindi__afiliaciones WHERE afiliacion_id = {FAM1}") or {}
limite25 = str(int(nac23[:4]) + 25) + '-05-31'
check(ok_resp(r) and f1.get('numero_afiliado') == tit.get('numero_afiliado') + '/01' and f1.get('f_vigencia') and f1['f_vigencia'] <= limite25,
      'con certificado: alta como N°/01 y vigencia hasta el vencimiento del certificado o los 25 años', err(r) + ' ' + str(f1))
r = af(accion='alta_familiar', titular_afiliacion_id=TIT, cuil='27555555550', apellido='SIM ESPOSA', nombre='ANA', f_nacimiento='1982-03-03',
       parentesco_id=val(f"SELECT parentesco_id FROM sindi__parentescos WHERE empresa_id = {E} AND codigo = 'CONYUGE'"))
FAM2 = int(((r.get('data') or {}).get('afiliacion_id')) or 0) if isinstance(r, dict) else 0
f2 = uno(f"SELECT numero_afiliado, f_vigencia FROM sindi__afiliaciones WHERE afiliacion_id = {FAM2}") or {}
check(ok_resp(r) and f2.get('numero_afiliado', '').endswith('/02') and f2.get('f_vigencia') is None, 'cónyuge: N°/02 sin límite de edad', err(r) + ' ' + str(f2))

# Vencimiento: el certificado vence y el cron pasa a Vencida; presentar uno nuevo la reactiva
sql(f"UPDATE sindi__afiliaciones_documentos SET f_vencimiento = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE afiliacion_id = {FAM1}")
sql(f"UPDATE sindi__afiliaciones SET f_vigencia = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE afiliacion_id = {FAM1}")
import subprocess
salida = subprocess.run([PHP, os.path.join(AQUI, 'run_cron.php'), '--empresa=7', '--sin-cuotas'],
                        env=dict(os.environ, SIM_CRON='sindicatos_afiliaciones.php', SIM_DB_COPIA=DB),
                        capture_output=True, text=True, encoding='utf-8', errors='replace').stdout
check(val(f"SELECT tabla_estado_registro_id FROM sindi__afiliaciones WHERE afiliacion_id = {FAM1}") == '20'
      and val(f"SELECT tabla_estado_registro_id FROM sindi__afiliaciones WHERE afiliacion_id = {FAM2}") == '1',
      'el proceso diario vence al hijo sin certificado vigente; el cónyuge sigue activo', salida[-300:])
r = af(accion='documento', afiliacion_id=FAM1, documento='Certificado de alumno regular', f_presentacion=HOY,
       _files={'archivo': {'name': 'certificado.pdf', 'content': '%PDF-1.4 sim'}})
check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM sindi__afiliaciones WHERE afiliacion_id = {FAM1}") == '1',
      'presentar el certificado nuevo reactiva la cobertura (con archivo)', err(r))

# Cuota directa: VOLUNTARIA con importe; se genera una vez por período, con asiento
tipo_vol = val(f"SELECT afiliacion_tipo_id FROM sindi__afiliaciones_tipos WHERE empresa_id = {E} AND codigo = 'VOLUNTARIA'")
r = sx('afiliaciones_config_ajax.php', dict(accion='guardar_tipo', afiliacion_tipo_id=tipo_vol, nombre='Afiliación Voluntaria', paga_cuota_directa=1, importe_cuota=5000))
check(ok_resp(r), 'configurar cuota de la afiliación voluntaria', err(r))
periodo = int(HOY[:4] + HOY[5:7])
r = af(accion='generar_cuotas', periodo=periodo)
cuota = uno(f"SELECT c.comprobante_id, c.importe FROM sindi__afiliaciones_cuotas c WHERE c.afiliacion_id = {TIT} AND c.periodo = {periodo}") or {}
comp = comps([cuota.get('comprobante_id') or 0])
okA, det = asientos_ok([cuota.get('comprobante_id') or 0])
check(ok_resp(r) and num(cuota.get('importe')) == 5000 and comp and num(comp[0]['importe_pendiente']) == 5000 and okA,
      'cuota del mes: comprobante CAF de 5.000 pendiente, con asiento balanceado', err(r) + f' {cuota} {det}')
r = af(accion='generar_cuotas', periodo=periodo)
check(ok_resp(r) and (r.get('data') or {}).get('generadas') == 0, 'volver a generar el mismo período no duplica', err(r))

# Volante de afiliado por la cuota directa: mismas bocas de pago, cancela contra deudores por cuotas de afiliados
CAF = int(cuota.get('comprobante_id') or 0)
ficha = sx('afiliados_ajax.php', dict(accion='ficha', id=TIT), 'GET')
cta = ((ficha.get('data') or {}).get('cuenta') or {}) if isinstance(ficha, dict) else {}
check(any(int(x['comprobante_id']) == CAF for x in cta.get('deudas') or []) and (ficha.get('data') or {}).get('puede_volante'),
      'la ficha muestra la cuota pendiente y permite emitir el volante', str(cta)[:300])
r = af(accion='emitir_volante', afiliacion_id=FAM2, comprobante_ids=[CAF], f_pago_1=F1, f_pago_2=F2)
check(not ok_resp(r), 'el volante no se emite a un familiar', err(r))
deuda_emp = val(f"SELECT c.comprobante_id FROM gestion__comprobantes c JOIN sindi__empleadores e ON e.entidad_id = c.entidad_id AND e.empresa_id = c.empresa_id "
                f"WHERE c.empresa_id = {E} AND c.modulo = 'sindicatos' AND c.signo = 1 AND c.importe_pendiente > 0 LIMIT 1")
r = af(accion='emitir_volante', afiliacion_id=TIT, comprobante_ids=[int(deuda_emp or 0)], f_pago_1=F1, f_pago_2=F2)
check(not ok_resp(r), 'no puede incluir deudas de un empleador', err(r))
r = af(accion='emitir_volante', afiliacion_id=TIT, comprobante_ids=[CAF], f_pago_1=F1, f_pago_2=F2)
VA = int(r.get('volante_id') or 0) if isinstance(r, dict) else 0
volA = uno(f"SELECT * FROM sindi__volantes WHERE volante_id = {VA}") or {}
check(ok_resp(r) and volA.get('tipo_volante') == 'AFILIADO' and volA.get('empleador_id') is None and volA.get('persona_id')
      and abs(num(volA.get('importe_1')) - 5000) < 0.01 and volA.get('tabla_estado_registro_id') == '31',
      'volante AFILIADO emitido por 5.000, sin empleador', err(r) + ' ' + str({k: volA.get(k) for k in ('tipo_volante', 'empleador_id', 'persona_id', 'importe_1')}))
r = af(accion='emitir_volante', afiliacion_id=TIT, comprobante_ids=[CAF], f_pago_1=F1, f_pago_2=F2)
check(not ok_resp(r), 'la cuota ya incluida no entra en otro volante', err(r))
vols = sx('volantes_ajax.php', dict(accion='listar'), 'GET')
fila = next((x for x in (vols.get('data') or []) if int(x['volante_id']) == VA), None) if isinstance(vols, dict) else None
check(fila is not None and 'SIM' in (fila.get('empleador_nombre') or '').upper(), 'el volante figura en el listado general con el nombre del afiliado', str(fila)[:200])
if ext:
    vols = sx('volantes_ajax.php', dict(accion='listar'), 'GET', usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check(not any(int(x['volante_id']) == VA for x in ((vols.get('data') or []) if isinstance(vols, dict) else [])),
          'un usuario Empleador no ve volantes de afiliados', str(vols)[:200])
    r = sx('volantes_ajax.php', dict(accion='obtener', volante_id=VA), 'GET', usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check(not ok_resp(r), 'ni puede abrirlo por id', err(r))
cuentaAfil = val(f"SELECT cont_cuenta_id FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = {E} AND concepto = 'SINDI_DEUDORES_AFILIADOS' AND tabla_estado_registro_id = 1 LIMIT 1")
antes = saldo_cta('BNA_RECAUDA')
r, lin = rendir(volA.get('codigo_barras'), HOY, num(volA.get('importe_1')), 'SIM_BNA_AFIL')
check(ok_resp(r) and lin and lin['resultado_conciliacion'] == 'OK', 'rendición: pago del volante de afiliado conciliado', err(r) + ' ' + str(lin))
check(pendiente([CAF]) == 0 and comps([CAF])[0]['estado'] == '40' and val(f"SELECT tabla_estado_registro_id FROM sindi__volantes WHERE volante_id = {VA}") == '40',
      'cuota CAF y volante cancelados', str(comps([CAF])))
check(abs(saldo_cta('BNA_RECAUDA') - antes - 5000) < 0.01, 'ingreso de 5.000 en BNA_RECAUDA', str(saldo_cta('BNA_RECAUDA') - antes))
okA, det = asientos_ok([lin.get('comprobante_cobro_id') or 0])
haber = val(f"SELECT d.cuenta_id FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
            f"WHERE a.comprobante_id = {int(lin.get('comprobante_cobro_id') or 0)} AND d.importe_local < 0 LIMIT 1")
check(okA and cuentaAfil and haber == cuentaAfil, 'el cobro cancela contra la cuenta de deudores por cuotas de afiliados', det + f' haber {haber} vs {cuentaAfil}')

# Carnet imprimible (afiliados_carnet.php): HTML, se lee la salida completa de run_ajax.php
import re
def html(archivo, params, usuario_id=1, empresa_id=E):
    p = dict(params, _method='GET', _usuario_id=usuario_id, _modulo=MOD, _empresa_id=empresa_id)
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as fh:
        json.dump(p, fh)
        ruta = fh.name
    try:
        return subprocess.run([PHP, os.path.join(AQUI, 'run_ajax.php'), archivo, ruta, DB], capture_output=True, text=True,
                              encoding='utf-8', errors='replace', timeout=120).stdout
    finally:
        os.unlink(ruta)

activos = int(val(f"SELECT COUNT(*) FROM sindi__afiliaciones WHERE titular_afiliacion_id = {TIT} AND tabla_estado_registro_id = 1"))
out = html('afiliados_carnet.php', dict(afiliacion_id=TIT, grupo=1))
tarjetas = re.findall(r'data-afiliacion="(\d+)"', out)
num_tit = val(f"SELECT numero_afiliado FROM sindi__afiliaciones WHERE afiliacion_id = {TIT}")
check(len(tarjetas) == activos + 1 and str(TIT) in tarjetas and num_tit in out,
      'carnets del grupo: titular y familiares activos, con el N° de afiliado', f'{tarjetas} activos {activos} {out[:300]}')
codigos = re.findall(r'data-qr="([^"]+)"', out)
cod_tit = next((c for c in codigos if re.match(rf'^{E}-{TIT}-\d{{8}}-[0-9a-f]{{16}}$', c)), '')
check(bool(cod_tit), 'el QR lleva el código firmado (sin datos personales)', str(codigos))
out = html('afiliados_carnet.php', dict(afiliacion_id=FAM2))
check(re.findall(r'data-afiliacion="(\d+)"', out) == [str(FAM2)] and 'GRUPO FAMILIAR' in out, 'carnet de un familiar solo', out[:300])
out = html('afiliados_carnet.php', dict(verificar=cod_tit))
check('data-resultado="VIGENTE"' in out, 'verificar el QR: afiliación vigente', out[-400:])
falso = cod_tit[:-1] + ('0' if cod_tit[-1] != '0' else '1')
out = html('afiliados_carnet.php', dict(verificar=falso))
check('data-resultado="INVALIDA"' in out, 'un código adulterado no verifica', out[-400:])
if ext:
    out = html('afiliados_carnet.php', dict(afiliacion_id=TIT, grupo=1), usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check('data-afiliacion' not in out, 'un usuario Empleador no imprime credenciales', out[:200])

# Baja del titular: arrastra al grupo familiar
r = af(accion='baja', afiliacion_id=TIT, f_baja=HOY)
estados = {x['afiliacion_id']: x['tabla_estado_registro_id'] for x in sql(f"SELECT afiliacion_id, tabla_estado_registro_id FROM sindi__afiliaciones WHERE afiliacion_id IN ({TIT},{FAM1},{FAM2})")}
check(ok_resp(r) and set(estados.values()) == {'2'}, 'baja del titular: él y su grupo familiar quedan en Baja', err(r) + ' ' + str(estados))
out = html('afiliados_carnet.php', dict(verificar=cod_tit))
check('data-resultado="NO_VIGENTE"' in out, 'después de la baja, el mismo carnet verifica como no vigente', out[-400:])
out = html('afiliados_carnet.php', dict(afiliacion_id=TIT, grupo=1))
check('data-afiliacion' not in out and 'no está activa' in out, 'no se imprime el carnet de una afiliación dada de baja', out[:300])
r = sx('afiliaciones_config_ajax.php', dict(accion='guardar_vigencia', parentesco_id=HIJO, edad_maxima=21, edad_maxima_excepcion=20, documento_excepcion='X', meses_validez_documento=12))
check(not ok_resp(r), 'vigencia con excepción menor que la edad máxima: rechazada', err(r))
lista = af(accion='listar', estado=2)
check(isinstance(lista, dict) and any(int(x['afiliacion_id']) == TIT for x in (lista.get('data') or [])), 'el listado filtra por estado (Baja)', str(lista)[:200])

# ---------------------------------------------------------------------------------------------
caso('Caso 170 - Motor de estados: botones por estado y permiso, y funciones exigidas en el servidor')
r, Dx = ddjj(CTX['emp'], 202607, NOMINA)
lista = sx('ddjj_ajax.php', dict(accion='listar'), 'GET')
filas = {int(x['ddjj_id']): x for x in (lista.get('data') or [])} if isinstance(lista, dict) else {}
acc_borr = (filas.get(Dx) or {}).get('acciones') or ''
acc_pres = (filas.get(CTX['D2']) or {}).get('acciones') or ''
check('btn-accion-confirmar' in acc_borr and 'btn-accion-anular' in acc_borr and 'btn-accion-rectificar' not in acc_borr,
      'DDJJ en Borrador: Presentar y Anular, sin Rectificar', acc_borr[:300])
d1r_estado = val(f"SELECT tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = {CTX['D1']}")
acc_rect = (filas.get(CTX['D1']) or {}).get('acciones') or ''
check(d1r_estado == '2' and 'btn-accion-ver' in acc_rect and 'btn-accion-confirmar' not in acc_rect,
      'DDJJ Rectificada: solo Ver', acc_rect[:300])
vols = sx('volantes_ajax.php', dict(accion='listar'), 'GET')
fv = {int(x['volante_id']): x for x in (vols.get('data') or [])} if isinstance(vols, dict) else {}
check('btn-accion-anular' not in ((fv.get(V1) or {}).get('acciones') or '') and 'btn-accion-ver' in ((fv.get(V1) or {}).get('acciones') or ''),
      'volante cancelado: Ver, sin Anular', ((fv.get(V1) or {}).get('acciones') or '')[:200])
if ext:
    r = sx('rendiciones_ajax.php', dict(accion='procesar', rendicion_id=1), usuario_id=int(ext['usuario_id']), empresa_id=int(ext['empresa_id']))
    check(not ok_resp(r), 'un usuario Empleador no puede procesar rendiciones', err(r))
# Un perfil interno sin la función "anular" de DDJJ: se le quita en la copia y no puede anular
interno = uno(f"SELECT up.usuario_id FROM conf__usuarios_perfiles up JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id "
              f"WHERE ep.empresa_id = {E} AND ep.perfil_id_base = 602 AND up.tabla_estado_registro_id = 1 AND up.usuario_id <> 1 LIMIT 1")
if interno:
    sql(f"DELETE epf FROM conf__empresas_perfiles_funciones epf JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id "
        f"JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id "
        f"WHERE ep.empresa_id = {E} AND pf.pagina_id = 1020 AND pf.accion_js = 'anular'")
    r = sx('ddjj_ajax.php', dict(accion='anular', id=Dx), usuario_id=int(interno['usuario_id']))
    check(not ok_resp(r) and 'permiso' in err(r).lower() and val(f"SELECT tabla_estado_registro_id FROM sindi__ddjj WHERE ddjj_id = {Dx}") == '3',
          'sin la función Anular: rechazado en el servidor y la DDJJ sigue en Borrador', err(r))
