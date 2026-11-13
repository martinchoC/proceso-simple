"""Prueba global de Gestión Normal — Nivel 0: alta y puesta en marcha de dos empresas nuevas.

S = servicios (copia la configuración de la empresa 6), B = bienes (copia de la empresa 2). Todo por los _ajax.php
de las pantallas; lo que no tiene página en el módulo 5 se registra como brecha y se completa por SQL.
Diseño: _local/docs/pruebas/prueba_global_gestion_normal.md (§3).
"""
from globlib import *

ctx = {'periodo_op': PERIODO_OP}
loc = {k: int(val(f"SELECT localidad_id FROM conf__localidades WHERE localidad = '{e['localidad']}' "
                  f"ORDER BY localidad_id LIMIT 1")) for k, e in EMPRESAS.items()}

# ---------------------------------------------------------------------------------------------------------
caso('N0.1 - Alta de las empresas (Empresas, módulo conf)')
r = ajc('empresas_ajax.php', dict(accion='agregar', empresa='SIM sin CUIT', documento_numero='', localidad_id=loc['S']))
check(not ok_resp(r) and 'obligatorios' in err(r), 'sin CUIT: rechazado', err(r))
r = ajc('empresas_ajax.php', dict(accion='agregar', empresa='SIM sin localidad', documento_numero=cuit(3071999009), localidad_id=''))
check(not ok_resp(r), 'sin localidad: rechazado', err(r))

for k, e in EMPRESAS.items():
    # Corridas sobre la base local (--base-local): si ya existe, se reutiliza
    existente = val(f"SELECT empresa_id FROM conf__empresas WHERE documento_numero = '{e['cuit']}'")
    if not existente:
        r = ajc('empresas_ajax.php', dict(accion='agregar', empresa=e['nombre'], documento_tipo_id=1, documento_numero=e['cuit'],
                                          domicilio='Calle SIM 123', localidad_id=loc[k], condicion_fiscal_id=1,
                                          iibb_regimen_id=e['iibb_regimen_id'], jurisdiccion_sede_id=e['sede_id'],
                                          ingresos_brutos_nro=e['cuit'], inicio_actividades='2020-01-01',
                                          email=f'sim{k.lower()}@example.com', tipo_operacion=e['tipo_operacion']))
        check(ok_resp(r), f"[{k}] alta de {e['nombre']}", err(r))
    fila = uno(f"SELECT empresa_id, tipo_operacion, condicion_fiscal_id, iibb_regimen_id, jurisdiccion_sede_id "
               f"FROM conf__empresas WHERE documento_numero = '{e['cuit']}' ORDER BY empresa_id DESC LIMIT 1")
    check(fila and fila['tipo_operacion'] == e['tipo_operacion'] and fila['condicion_fiscal_id'] == '1'
          and int(fila['jurisdiccion_sede_id']) == e['sede_id'], f"[{k}] fila en conf__empresas con tipo {e['tipo_operacion']}", str(fila))
    e['empresa_id'] = int(fila['empresa_id'])
    par = uno(f"SELECT COUNT(*) n, MAX(arca_habilitado) arca FROM gestion__empresas_parametros WHERE empresa_id = {e['empresa_id']} "
              f"AND tabla_estado_registro_id = 1 AND f_hasta IS NULL")
    check(par and int(par['n']) == 1, f"[{k}] parámetros iniciales con vigencia abierta", str(par))
    ctx[k] = {'empresa_id': e['empresa_id']}

# ---------------------------------------------------------------------------------------------------------
caso(f'N0.2 - Módulo {MOD_CARPETA} ({MOD_ID}) y perfiles base')
for k, e in EMPRESAS.items():
    eid = e['empresa_id']
    if not val(f"SELECT empresa_modulo_id FROM conf__empresas_modulos WHERE empresa_id = {eid} AND modulo_id = {MOD_ID}"):
        r = ajc('empresas_modulos_ajax.php', dict(accion='agregar', empresa_id=eid, modulo_id=MOD_ID, tabla_estado_registro_id=1), 'GET')
        check(ok_resp(r) and r.get('preguntar_copiar_perfiles'), f'[{k}] módulo {MOD_ID} asignado', err(r))
        r = ajc('empresas_modulos_ajax.php', dict(accion='copiar_perfiles', empresa_id=eid, modulo_id=MOD_ID), 'GET')
        check(ok_resp(r) and int(r.get('perfiles_copiados', 0)) > 0, f"[{k}] perfiles base copiados ({r.get('perfiles_copiados') if isinstance(r, dict) else ''})", err(r))
    r = ajc('empresas_modulos_ajax.php', dict(accion='agregar', empresa_id=eid, modulo_id=MOD_OTRO_ID, tabla_estado_registro_id=1), 'GET')
    check(not ok_resp(r) and 'excluyentes' in err(r), f'[{k}] el módulo {MOD_OTRO_ID} se rechaza (excluyente con el {MOD_ID})', err(r))

    perfiles = {p['perfil_id_base']: p for p in sql(f"SELECT empresa_perfil_id, perfil_id_base, empresa_perfil_nombre FROM conf__empresas_perfiles "
                                                     f"WHERE empresa_id = {eid} AND modulo_id = {MOD_ID}")}
    esperado = {str(PERFIL_BASE + n) for n in (1, 2, 3, 4, 5, 8, 6 if k == 'B' else 7)}
    check(set(perfiles) == esperado, f'[{k}] perfiles base según tipo de operación', str(sorted(perfiles)))
    adm_base = str(PERFIL_BASE + 1)
    ctx[k]['perfil_admin'] = int(perfiles[adm_base]['empresa_perfil_id']) if adm_base in perfiles else 0
    bienes = int(val(f"SELECT COUNT(*) FROM conf__empresas_perfiles_funciones epf "
                     f"JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = epf.empresa_perfil_id "
                     f"JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id "
                     f"JOIN conf__paginas p ON p.pagina_id = pf.pagina_id "
                     f"WHERE ep.empresa_id = {eid} AND p.requiere_bienes = 1") or 0)
    if k == 'S':
        check(bienes == 0, '[S] ninguna función de páginas de bienes en sus perfiles', f'{bienes} funciones')
    else:
        check(bienes > 0, f'[B] funciones de páginas de bienes en sus perfiles ({bienes})')

# ---------------------------------------------------------------------------------------------------------
caso('N0.3 - Usuario Administrador (no superadmin): aislamiento entre las dos empresas')
# El alta del usuario no es lo que se prueba: va por SQL. Un usuario por empresa, con su perfil Administrador.
for k, e in EMPRESAS.items():
    usuario = f'sim_admin_{k.lower()}'
    uid = val(f"SELECT usuario_id FROM conf__usuarios WHERE usuario = '{usuario}'")
    if not uid:
        sql(f"INSERT INTO conf__usuarios (usuario_nombre, usuario, email, password, tabla_estado_registro_id) "
            f"VALUES ('SIM Admin {k}', '{usuario}', '{usuario}@example.com', '-', 1)")
        uid = val(f"SELECT usuario_id FROM conf__usuarios WHERE usuario = '{usuario}'")
        sql(f"INSERT INTO conf__usuarios_perfiles (usuario_id, empresa_perfil_id, fecha_inicio, fecha_fin, tabla_estado_registro_id) "
            f"VALUES ({uid}, {ctx[k]['perfil_admin']}, '2020-01-01', '2099-12-31', 1)")
    ctx[k]['usuario_admin'] = int(uid)
for k, otra in (('S', 'B'), ('B', 'S')):
    e = EMPRESAS[k]
    r = ajax('empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=e['empresa_id']), 'GET',
             usuario_id=ctx[k]['usuario_admin'], modulo=MOD_CARPETA, empresa_id=e['empresa_id'])
    check(ok_resp(r), f'[{k}] su Administrador entra al asistente de su empresa', err(r))
    r = ajax('empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=EMPRESAS[otra]['empresa_id']), 'GET',
             usuario_id=ctx[k]['usuario_admin'], modulo=MOD_CARPETA, empresa_id=e['empresa_id'])
    check(not ok_resp(r) and 'permisos' in err(r).lower(), f'[{k}] su Administrador no entra a la empresa {otra} (403)', err(r))
    r = ajax('empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=6), 'GET',
             usuario_id=ctx[k]['usuario_admin'], modulo=MOD_CARPETA, empresa_id=e['empresa_id'])
    check(not ok_resp(r), f'[{k}] ni a la empresa 6', err(r))

# ---------------------------------------------------------------------------------------------------------
caso('N0.4 - Autorización de copia desde la empresa origen')
for k, e in EMPRESAS.items():
    origen = {'empresa_id': e['origen']}
    if not val(f"SELECT autorizacion_id FROM gestion__empresas_autorizaciones_copia WHERE empresa_origen_id = {e['origen']} "
               f"AND empresa_destino_id = {e['empresa_id']}"):
        r = aj(origen, 'empresas_autorizaciones_copia_ajax.php', dict(
            action='guardar', empresa_id=e['origen'], empresa_destino_id=e['empresa_id'], permite_plan_cuentas=1,
            permite_conceptos_contables=1, permite_roles_cuentas=1, permite_comprobantes_tipos=1, permite_condiciones_pago=1,
            permite_sucursales_bocas=0, permite_listas_precios=1, permite_parametros=1, fecha_desde=HOY,
            motivo='SIM prueba global: alta de empresa'))
        check(ok_resp(r), f"[{k}] la empresa {e['origen']} autoriza la copia", err(r))
    aut = uno(f"SELECT * FROM gestion__empresas_autorizaciones_copia WHERE empresa_origen_id = {e['origen']} AND empresa_destino_id = {e['empresa_id']}")
    check(aut and aut['tabla_estado_registro_id'] == '1' and aut['permite_plan_cuentas'] == '1', f'[{k}] autorización vigente', str(aut))
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='obtener_empresas_autorizadas', empresa_id=e['empresa_id']), 'GET')
    check(ok_resp(r) and e['origen'] in [int(x.get('empresa_origen_id') or x.get('empresa_id') or 0) for x in ids_de(r)],
          f"[{k}] el asistente ofrece copiar desde la {e['origen']}", str(r)[:300])

# ---------------------------------------------------------------------------------------------------------
caso('N0.5 - Asistente de inicio: diagnóstico, copia, sucursal rápida y accesos')
COMPONENTES = dict(plan_cuentas=1, conceptos_contables=1, roles_cuentas=1, comprobantes_tipos=1, condiciones_pago=1,
                   listas_precios=1, parametros=1, sucursales_bocas=0)
for k, e in EMPRESAS.items():
    eid = e['empresa_id']
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=eid), 'GET')
    check(ok_resp(r), f'[{k}] diagnóstico inicial', err(r))
    ctx[k]['diagnostico_inicial'] = r.get('data') if isinstance(r, dict) else None
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='previsualizar_copia', empresa_id=eid, empresa_origen_id=e['origen']), 'GET')
    check(ok_resp(r), f'[{k}] previsualizar la copia', err(r))
    if not val(f"SELECT COUNT(*) FROM gestion__cont_cuentas WHERE empresa_id = {eid}") not in (None, '0'):
        r = aj(e, 'empresa_inicio_ajax.php', dict(action='ejecutar_copia', empresa_id=eid, empresa_origen_id=e['origen'],
                                                  componentes=json.dumps(COMPONENTES)))
        check(ok_resp(r), f'[{k}] ejecutar la copia', err(r))
        ctx[k]['copia'] = r
    n_orig = int(val(f"SELECT COUNT(*) FROM gestion__cont_cuentas WHERE empresa_id = {e['origen']}"))
    n_dest = int(val(f"SELECT COUNT(*) FROM gestion__cont_cuentas WHERE empresa_id = {eid}"))
    check(n_dest == n_orig, f'[{k}] plan de cuentas copiado ({n_dest} de {n_orig})')
    padres = val(f"SELECT COUNT(*) FROM gestion__cont_cuentas c JOIN gestion__cont_cuentas p ON p.cont_cuenta_id = c.cuenta_padre_id "
                 f"WHERE c.empresa_id = {eid} AND p.empresa_id <> {eid}")
    check(padres == '0', f'[{k}] ninguna cuenta con padre de otra empresa', f'{padres} cuentas')
    for tabla, campo in (('empresas_cuentas_conceptos', 'cont_cuenta_id'), ('empresas_roles_cuentas', 'cont_cuenta_id')):
        n = int(val(f"SELECT COUNT(*) FROM gestion__{tabla} WHERE empresa_id = {eid}") or 0)
        ajenas = val(f"SELECT COUNT(*) FROM gestion__{tabla} t JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = t.{campo} "
                     f"WHERE t.empresa_id = {eid} AND c.empresa_id <> {eid}")
        check(n > 0 and ajenas == '0', f'[{k}] {tabla}: {n} filas, todas con cuentas propias', f'{ajenas} con cuenta ajena')
    n_t = int(val(f"SELECT COUNT(*) FROM gestion__comprobantes_tipos WHERE empresa_id = {eid}") or 0)
    sub_ajeno = val(f"SELECT COUNT(*) FROM gestion__comprobantes_tipos t JOIN gestion__comprobantes_subgrupos s "
                    f"ON s.comprobante_subgrupo_id = t.comprobante_subgrupo_id WHERE t.empresa_id = {eid} AND s.empresa_id <> {eid}")
    check(n_t > 0 and sub_ajeno == '0', f'[{k}] {n_t} tipos de comprobante, con subgrupos propios', f'{sub_ajeno} con subgrupo ajeno')
    par = uno(f"SELECT condicion_fiscal_id, arca_habilitado FROM gestion__empresas_parametros WHERE empresa_id = {eid} "
              f"AND tabla_estado_registro_id = 1 AND f_hasta IS NULL ORDER BY 1 DESC LIMIT 1")
    check(par and str(par['arca_habilitado']) in ('0', 'None') and par['condicion_fiscal_id'] == '1',
          f'[{k}] parámetros: condición de la empresa y ARCA deshabilitado', str(par))

    r = aj(e, 'empresa_inicio_ajax.php', dict(action='guardar_tipo_operacion', empresa_id=eid, tipo_operacion=e['tipo_operacion']))
    check(ok_resp(r), f"[{k}] tipo de operación {e['tipo_operacion']}", err(r))
    if not val(f"SELECT COUNT(*) FROM gestion__sucursales WHERE empresa_id = {eid}") not in (None, '0'):
        r = aj(e, 'empresa_inicio_ajax.php', dict(action='crear_sucursal_rapida', empresa_id=eid))
        check(ok_resp(r), f'[{k}] sucursal rápida (Central + depósito + PV 0001)', err(r))
    suc = uno(f"SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = {eid} ORDER BY sucursal_id LIMIT 1")
    boca = uno(f"SELECT boca_id FROM gestion__bocas WHERE empresa_id = {eid} ORDER BY boca_id LIMIT 1")
    pv = uno(f"SELECT punto_venta_id, codigo_fiscal FROM gestion__puntos_venta WHERE empresa_id = {eid} ORDER BY punto_venta_id LIMIT 1")
    check(suc and boca and pv and int(pv['codigo_fiscal']) == 1, f'[{k}] sucursal, boca y PV 0001', f'{suc} {boca} {pv}')
    if suc and boca and pv:
        ctx[k].update(sucursal_id=int(suc['sucursal_id']), boca_id=int(boca['boca_id']), pv_id=int(pv['punto_venta_id']))
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='asignar_accesos_globales', empresa_id=eid))
    check(ok_resp(r), f'[{k}] accesos a todas las sucursales para todos los perfiles', err(r))
    sin_acc = val(f"SELECT COUNT(*) FROM conf__empresas_perfiles ep WHERE ep.empresa_id = {eid} AND NOT EXISTS "
                  f"(SELECT 1 FROM gestion__perfiles_sucursales ps WHERE ps.empresa_perfil_id = ep.empresa_perfil_id AND ps.tabla_estado_registro_id = 1)")
    check(sin_acc == '0', f'[{k}] todos los perfiles con acceso a sucursales', f'{sin_acc} sin acceso')
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=eid), 'GET')
    score = int((r.get('data') or {}).get('score') or 0) if isinstance(r, dict) else 0
    ctx[k]['score_tras_copia'] = score
    faltan = [t for t, q in (('moneda', 'monedas'), ('alícuotas de IVA', 'impuestos__iva_alicuotas'), ('medios de pago', 'medios_pago'),
                             ('cuentas de tesorería', 'tesoreria_cuentas'), ('tipos de asiento', 'cont_tipos_asientos'),
                             ('comprobantes por punto de venta', 'puntos_venta_comprobantes'))
              if val(f"SELECT COUNT(*) FROM gestion__{q} WHERE empresa_id = {eid}") == '0']
    # Hallazgo: el asistente da la empresa por lista aunque no puede facturar ni cobrar.
    check(not (score >= 100 and faltan), f'[{k}] el diagnóstico no da 100 puntos si faltan maestros para operar',
          f'score {score} y faltan: {", ".join(faltan)}')

ctx_guardar(ctx)


# =========================================================================================================
# 0.6 / 0.7 - Completar lo que la copia no trae
# =========================================================================================================
def cta(e, cuenta_origen_id):
    """Cuenta de la empresa nueva con el mismo código que la cuenta de la empresa origen (0 si no hay)."""
    if not cuenta_origen_id or int(cuenta_origen_id) <= 0:
        return 0
    return int(val(f"SELECT d.cont_cuenta_id FROM gestion__cont_cuentas o JOIN gestion__cont_cuentas d ON d.codigo = o.codigo "
                   f"AND d.empresa_id = {e['empresa_id']} WHERE o.cont_cuenta_id = {int(cuenta_origen_id)} LIMIT 1") or 0)


def tipo_dest(e, tipo_origen_id):
    return int(val(f"SELECT d.comprobante_tipo_id FROM gestion__comprobantes_tipos o JOIN gestion__comprobantes_tipos d "
                   f"ON d.codigo = o.codigo AND d.empresa_id = {e['empresa_id']} WHERE o.comprobante_tipo_id = {int(tipo_origen_id)} LIMIT 1") or 0)


for k, e in EMPRESAS.items():
    e.update(ctx[k])

caso('N0.6 - Monedas y cuentas de tesorería por sus páginas (626 / 627); relaciones entre tipos copiadas por el asistente')
for k, e in EMPRESAS.items():
    eid, org = e['empresa_id'], e['origen']
    adm = e['usuario_admin']
    # Monedas (página 626)
    if val(f"SELECT COUNT(*) FROM gestion__monedas WHERE empresa_id = {eid}") == '0':
        r = aj(e, 'monedas_ajax.php', dict(accion='agregar', codigo='usd', moneda='Dólar estadounidense', simbolo='US$', cantidad_decimales=2,
                                           cotizacion_actual=0, orden=2))
        check(not ok_resp(r) and 'cotizaci' in err(r).lower(), f'[{k}] moneda con cotización 0: rechazada', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='agregar', codigo='ARS', moneda='Peso argentino', simbolo='$', cantidad_decimales=2,
                                           cotizacion_actual=5, orden=1), usuario_id=adm)
        check(ok_resp(r), f'[{k}] alta de ARS por el Administrador de la empresa', err(r))
        m = uno(f"SELECT es_moneda_base, cotizacion_actual FROM gestion__monedas WHERE empresa_id = {eid} AND codigo = 'ARS'")
        check(m and m['es_moneda_base'] == '1' and num(m['cotizacion_actual']) == 1, f'[{k}] la primera moneda es la base y cotiza 1', str(m))
        r = aj(e, 'monedas_ajax.php', dict(accion='agregar', codigo='ARS', moneda='Otro peso', simbolo='$', cantidad_decimales=2, cotizacion_actual=1))
        check(not ok_resp(r) and 'ya tiene' in err(r), f'[{k}] código repetido: rechazado', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='agregar', codigo='USD', moneda='Dólar estadounidense', simbolo='US$', cantidad_decimales=2,
                                           cotizacion_actual='1450,50', permite_editar_cotizacion=1, orden=2))
        check(ok_resp(r), f'[{k}] alta de USD', err(r))
        usd = int(((r.get('data') or {}).get('moneda_id')) or 0) if isinstance(r, dict) else 0
        check(num(val(f"SELECT cotizacion_actual FROM gestion__monedas WHERE moneda_id = {usd}")) == 1450.5, f'[{k}] USD cotiza 1450,50')
        ars = int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {eid} AND codigo = 'ARS'"))
        r = aj(e, 'monedas_ajax.php', dict(accion='editar', moneda_id=ars, codigo='ARS', moneda='Peso argentino', simbolo='$', cantidad_decimales=2,
                                           cotizacion_actual=1, es_moneda_base=0))
        check(not ok_resp(r) and 'moneda base' in err(r), f'[{k}] desmarcar la base sin elegir otra: rechazado', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='ejecutar_accion', moneda_id=ars, accion_js='inactivar'))
        check(not ok_resp(r) and 'base' in err(r), f'[{k}] inactivar la moneda base: rechazado', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='ejecutar_accion', moneda_id=usd, accion_js='inactivar'))
        check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__monedas WHERE moneda_id = {usd}") == '2', f'[{k}] inactivar USD', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='ejecutar_accion', moneda_id=usd, accion_js='habilitar'))
        check(ok_resp(r), f'[{k}] habilitar USD', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='editar', moneda_id=int(medio_otra := val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {org} LIMIT 1")),
                                           codigo='XXX', moneda='Ajena', simbolo='X', cantidad_decimales=2, cotizacion_actual=1), usuario_id=adm)
        check(not ok_resp(r) and val(f"SELECT codigo FROM gestion__monedas WHERE moneda_id = {medio_otra}") != 'XXX',
              f'[{k}] editar una moneda de la empresa {org}: no la encuentra', err(r))
        r = aj(e, 'monedas_ajax.php', dict(accion='listar'), 'GET', usuario_id=adm)
        lst = r.get('data') if ok_resp(r) else []
        check(len(lst) == 2 and all(int(x['empresa_id']) == eid for x in lst) and lst[0]['codigo'] == 'ARS'
              and {b['accion_js'] for b in lst[0]['botones']} >= {'editar', 'inactivar'}, f'[{k}] listado: 2 monedas de la empresa, base primero, con botones del motor', str(lst)[:300])
    e['moneda_id'] = int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {eid} AND es_moneda_base = 1 LIMIT 1") or 0)
    check(e['moneda_id'] > 0, f'[{k}] moneda base')

    # Cuentas de tesorería (página 627): caja de la sucursal, banco y valores, con la cuenta contable del mismo código que en la origen
    if val(f"SELECT COUNT(*) FROM gestion__tesoreria_cuentas WHERE empresa_id = {eid}") == '0':
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(accion='maestros'), 'GET')
        mae = r.get('data') if ok_resp(r) else {}
        check(mae and [int(s['sucursal_id']) for s in mae['sucursales']] == [e['sucursal_id']] and len(mae['monedas']) == 2
              and mae['cont_cuentas'] and all(int(x['cont_cuenta_id']) in {int(y['cont_cuenta_id']) for y in sql(f"SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id = {eid}")}
                                             for x in mae['cont_cuentas']),
              f'[{k}] maestros de la pantalla: sucursal, monedas y plan de cuentas de la empresa', str(r)[:300])
        o = {t: uno(f"SELECT * FROM gestion__tesoreria_cuentas WHERE empresa_id = {org} AND cuenta_tipo = '{t}' AND tabla_estado_registro_id = 1 "
                    f"ORDER BY orden, tesoreria_cuenta_id LIMIT 1") for t in ('CAJA', 'BANCO', 'VALORES')}
        base_tc = dict(accion='agregar', moneda_id=e['moneda_id'], orden=1)
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='CAJA', tesoreria_cuenta='Caja sin sucursal', cont_cuenta_id=cta(e, o['CAJA']['cont_cuenta_id'])))
        check(not ok_resp(r) and 'sucursal' in err(r), f'[{k}] caja sin sucursal: rechazada', err(r))
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='BANCO', tesoreria_cuenta='Banco sin banco', cont_cuenta_id=cta(e, o['BANCO']['cont_cuenta_id']),
                                                     banco_cuenta_tipo='CC'))
        check(not ok_resp(r) and 'banco' in err(r), f'[{k}] cuenta bancaria sin banco: rechazada', err(r))
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='CAJA', tesoreria_cuenta='Caja con cuenta ajena', sucursal_id=e['sucursal_id'],
                                                     cont_cuenta_id=o['CAJA']['cont_cuenta_id']))
        check(not ok_resp(r) and 'plan de cuentas' in err(r), f'[{k}] cuenta contable de otra empresa: rechazada', err(r))
        no_imp = val(f"SELECT cont_cuenta_id FROM gestion__cont_cuentas WHERE empresa_id = {eid} AND es_imputable = 0 LIMIT 1")
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='CAJA', tesoreria_cuenta='Caja a cuenta madre', sucursal_id=e['sucursal_id'], cont_cuenta_id=no_imp))
        check(not ok_resp(r) and 'imputable' in err(r), f'[{k}] cuenta contable no imputable: rechazada', err(r))
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='BANCO', tesoreria_cuenta='Banco CBU corto', cont_cuenta_id=cta(e, o['BANCO']['cont_cuenta_id']),
                                                     banco_id=o['BANCO']['banco_id'], banco_cuenta_tipo='CC', cbu='123'))
        check(not ok_resp(r) and '22' in err(r), f'[{k}] CBU de 3 dígitos: rechazado', err(r))
        ids = {}
        for t in ('CAJA', 'BANCO', 'VALORES'):
            p = dict(base_tc, cuenta_tipo=t, codigo=o[t]['codigo'], tesoreria_cuenta=f"{o[t]['tesoreria_cuenta']} SIM", cont_cuenta_id=cta(e, o[t]['cont_cuenta_id']),
                     permite_saldo_negativo=o[t]['permite_saldo_negativo'], orden=o[t]['orden'] or 1)
            if t == 'CAJA':
                p['sucursal_id'] = e['sucursal_id']
            if t == 'BANCO':
                p.update(banco_id=o[t]['banco_id'], banco_cuenta_tipo=o[t]['banco_cuenta_tipo'] or 'CC', numero='123-456/7', cbu='0110599520000001234567', alias='SIM.EMPRESA.' + k)
            r = aj(e, 'tesoreria_cuentas_ajax.php', p, usuario_id=adm)
            check(ok_resp(r), f'[{k}] alta de {t} por el Administrador de la empresa', err(r))
            ids[t] = int(((r.get('data') or {}).get('tesoreria_cuenta_id')) or 0) if isinstance(r, dict) else 0
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(base_tc, cuenta_tipo='BANCO', tesoreria_cuenta='Banco duplicado', cont_cuenta_id=cta(e, o['BANCO']['cont_cuenta_id']),
                                                     banco_id=o['BANCO']['banco_id'], banco_cuenta_tipo='CA', cbu='0110599520000001234567'))
        check(not ok_resp(r) and 'CBU' in err(r), f'[{k}] CBU repetido en la empresa: rechazado', err(r))
        caja = tcj = val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {ids['CAJA']}")
        check(caja is not None and num(caja) == 0, f'[{k}] saldo inicial 0 (lo mantiene el libro)')
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(accion='ejecutar_accion', tesoreria_cuenta_id=ids['VALORES'], accion_js='inactivar'), usuario_id=adm)
        check(ok_resp(r), f'[{k}] inactivar valores (saldo 0, sin medios)', err(r))
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(accion='ejecutar_accion', tesoreria_cuenta_id=ids['VALORES'], accion_js='habilitar'), usuario_id=adm)
        check(ok_resp(r), f'[{k}] habilitar valores', err(r))
        otra_k = 'B' if k == 'S' else 'S'
        r = aj(e, 'tesoreria_cuentas_ajax.php', dict(accion='editar', tesoreria_cuenta_id=val(f"SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {org} LIMIT 1"),
                                                     cuenta_tipo='CAJA', tesoreria_cuenta='Ajena', moneda_id=e['moneda_id'], sucursal_id=e['sucursal_id'],
                                                     cont_cuenta_id=cta(e, o['CAJA']['cont_cuenta_id'])), usuario_id=adm)
        check(not ok_resp(r), f'[{k}] editar una cuenta de tesorería de la empresa {org}: no la encuentra', err(r))
    tc = {r_['cuenta_tipo']: int(r_['tesoreria_cuenta_id']) for r_ in sql(f"SELECT cuenta_tipo, tesoreria_cuenta_id FROM gestion__tesoreria_cuentas "
                                                                          f"WHERE empresa_id = {eid} AND tabla_estado_registro_id = 1 ORDER BY tesoreria_cuenta_id DESC")}
    e.update(caja_id=tc.get('CAJA', 0), banco_id=tc.get('BANCO', 0), valores_id=tc.get('VALORES', 0))
    check(e['caja_id'] and e['banco_id'] and e['valores_id'], f'[{k}] caja, banco y valores', str(tc))
    ajena = val(f"SELECT COUNT(*) FROM gestion__tesoreria_cuentas t JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = t.cont_cuenta_id "
                f"WHERE t.empresa_id = {eid} AND c.empresa_id <> {eid}")
    check(ajena == '0', f'[{k}] cuentas de tesorería con cuentas contables propias', f'{ajena} ajenas')
    # Un usuario sin el perfil de la empresa no puede usar las páginas
    otro_adm = EMPRESAS['B' if k == 'S' else 'S'].get('usuario_admin') or ctx['B' if k == 'S' else 'S']['usuario_admin']
    r = ajax('monedas_ajax.php', dict(accion='agregar', empresa_idx=eid, codigo='EUR', moneda='Euro', simbolo='€', cotizacion_actual=1600),
             usuario_id=otro_adm, modulo=MOD_CARPETA, empresa_id=eid)
    check(not ok_resp(r) and val(f"SELECT COUNT(*) FROM gestion__monedas WHERE empresa_id = {eid} AND codigo = 'EUR'") == '0',
          f'[{k}] el Administrador de la otra empresa no da de alta monedas acá', err(r))

    # Relaciones entre tipos: las copia el asistente con los tipos (remapeadas por código), activas de la origen
    for tabla, c1, c2 in (('comprobantes_tipos_cancelaciones', 'comprobante_tipo_origen_id', 'comprobante_tipo_destino_id'),
                          ('comprobantes_tipos_dependencias', 'comprobante_tipo_id', 'comprobante_tipo_padre_id')):
        esperadas = int(val(f"SELECT COUNT(*) FROM gestion__{tabla} x JOIN gestion__comprobantes_tipos o1 ON o1.comprobante_tipo_id = x.{c1} "
                            f"JOIN gestion__comprobantes_tipos o2 ON o2.comprobante_tipo_id = x.{c2} "
                            f"JOIN gestion__comprobantes_tipos d1 ON d1.empresa_id = {eid} AND d1.codigo = o1.codigo "
                            f"JOIN gestion__comprobantes_tipos d2 ON d2.empresa_id = {eid} AND d2.codigo = o2.codigo "
                            f"WHERE x.empresa_id = {org} AND x.tabla_estado_registro_id = 1"))
        n_d = int(val(f"SELECT COUNT(*) FROM gestion__{tabla} WHERE empresa_id = {eid} AND tabla_estado_registro_id = 1"))
        ajenas = val(f"SELECT COUNT(*) FROM gestion__{tabla} x JOIN gestion__comprobantes_tipos t ON t.comprobante_tipo_id IN (x.{c1}, x.{c2}) "
                     f"WHERE x.empresa_id = {eid} AND t.empresa_id <> {eid}")
        check(n_d == esperadas and n_d > 0 and ajenas == '0', f'[{k}] el asistente copió {tabla}: {n_d} de {esperadas}, con tipos propios',
              f'{n_d} vs {esperadas}, {ajenas} con tipos ajenos')

caso('N0.6b - Relaciones entre tipos de comprobante (Tipos de Comprobante > Relaciones, página 545)')
for k, e in EMPRESAS.items():
    eid = e['empresa_id']
    adm = e['usuario_admin']
    tid = {c: int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {eid} AND codigo = '{c}'") or 0)
           for c in ('RCX', 'V_FA', 'V_FX', 'V_NCA')}

    def rel(params, method='POST', usuario=adm):
        return ajax('comprobantes_tipos_ajax.php', dict(params, empresa_idx=eid), method, usuario_id=usuario, modulo=MOD_CARPETA, empresa_id=eid)

    r = rel(dict(accion='relaciones_obtener', comprobante_tipo_id=tid['RCX']), 'GET')
    d = r.get('data') if ok_resp(r) else None
    cancela = {int(x['comprobante_tipo_id']): int(x['exige_misma_entidad']) for x in (d or {}).get('cancela', [])}
    check(d and tid['V_FA'] in cancela and tid['V_FX'] in cancela, f'[{k}] el recibo cancela Factura A y Factura X (copiado del origen)', str(cancela))
    check(d and all(int(t['comprobante_tipo_id']) != tid['RCX'] or True for t in d['tipos'])
          and all(int(val(f"SELECT empresa_id FROM gestion__comprobantes_tipos WHERE comprobante_tipo_id = {int(t['comprobante_tipo_id'])}")) == eid
                  for t in d['tipos'][:5]), f'[{k}] los tipos elegibles son de la empresa')
    r = rel(dict(accion='relaciones_obtener', comprobante_tipo_id=tid['V_FA']), 'GET')
    check(ok_resp(r) and tid['RCX'] in r['data']['cancelado_por'] and tid['V_NCA'] in r['data']['hijos'],
          f'[{k}] la Factura A muestra las inversas: la cancela el recibo y la NC A se asocia a ella', str(r)[:300])

    # Quitar Factura X de lo que cancela el recibo y volver a ponerla (con "misma entidad" en 0)
    sin_fx = [dict(comprobante_tipo_id=t, exige_misma_entidad=m) for t, m in cancela.items() if t != tid['V_FX']]
    r = rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['RCX'], cancela=json.dumps(sin_fx), padres='[]'))
    est = val(f"SELECT tabla_estado_registro_id FROM gestion__comprobantes_tipos_cancelaciones WHERE empresa_id = {eid} "
              f"AND comprobante_tipo_origen_id = {tid['RCX']} AND comprobante_tipo_destino_id = {tid['V_FX']}")
    check(ok_resp(r) and est == '2', f'[{k}] quitar Factura X: la relación queda inactiva (2), no se borra', err(r) + f' estado {est}')
    con_fx = sin_fx + [dict(comprobante_tipo_id=tid['V_FX'], exige_misma_entidad=0)]
    r = rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['RCX'], cancela=json.dumps(con_fx), padres='[]'))
    f = uno(f"SELECT tabla_estado_registro_id est, exige_misma_entidad m FROM gestion__comprobantes_tipos_cancelaciones WHERE empresa_id = {eid} "
            f"AND comprobante_tipo_origen_id = {tid['RCX']} AND comprobante_tipo_destino_id = {tid['V_FX']}")
    check(ok_resp(r) and f and f['est'] == '1' and f['m'] == '0', f'[{k}] volver a ponerla la reactiva con "misma entidad" en 0', str(f))
    rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['RCX'],
             cancela=json.dumps([dict(comprobante_tipo_id=t, exige_misma_entidad=m) for t, m in cancela.items()]), padres='[]'))
    check(int(val(f"SELECT exige_misma_entidad FROM gestion__comprobantes_tipos_cancelaciones WHERE empresa_id = {eid} "
                  f"AND comprobante_tipo_origen_id = {tid['RCX']} AND comprobante_tipo_destino_id = {tid['V_FX']}")) == cancela[tid['V_FX']],
          f'[{k}] relaciones del recibo restituidas')

    # Rechazos
    r = rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['RCX'], cancela=json.dumps([dict(comprobante_tipo_id=tid['RCX'])]), padres='[]'))
    check(not ok_resp(r) and 'sí mismo' in err(r), f'[{k}] un tipo que se cancela a sí mismo: rechazado', err(r))
    ajeno = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['origen']} AND codigo = 'V_FA'"))
    r = rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['RCX'], cancela=json.dumps([dict(comprobante_tipo_id=ajeno)]), padres='[]'))
    check(not ok_resp(r) and 'no es un tipo activo de la empresa' in err(r), f'[{k}] tipo de la empresa {e["origen"]}: rechazado', err(r))
    r = rel(dict(accion='relaciones_guardar', comprobante_tipo_id=tid['V_FA'], cancela='[]', padres=json.dumps([tid['V_NCA']])))
    check(not ok_resp(r) and 'ya depende' in err(r), f'[{k}] Factura A con padre NC A (que depende de ella): ciclo rechazado', err(r))
    check(val(f"SELECT COUNT(*) FROM gestion__comprobantes_tipos_dependencias WHERE empresa_id = {eid} AND comprobante_tipo_id = {tid['V_FA']} "
              f"AND tabla_estado_registro_id = 1") == '0', f'[{k}] los rechazos no dejaron cambios')
    otro = ctx['B' if k == 'S' else 'S']['usuario_admin']
    r = rel(dict(accion='relaciones_obtener', comprobante_tipo_id=tid['RCX']), 'GET', usuario=otro)
    check(not ok_resp(r), f'[{k}] el Administrador de la otra empresa no accede', err(r))

caso('N0.7 - Maestros por su página: tipos de asiento, IVA, medios de pago, impuestos, PV, tipos de producto, parámetros')
for k, e in EMPRESAS.items():
    eid, org = e['empresa_id'], e['origen']
    # Tipos de asiento (página 587)
    for o in sql(f"SELECT codigo, cont_tipo_asiento, origen FROM gestion__cont_tipos_asientos WHERE empresa_id = {org} AND tabla_estado_registro_id = 1"):
        if val(f"SELECT COUNT(*) FROM gestion__cont_tipos_asientos WHERE empresa_id = {eid} AND codigo = '{o['codigo']}'") == '0':
            r = aj(e, 'cont_tipos_asientos_ajax.php', dict(accion='agregar', empresa_id=eid, pagina_idx=pg(587), codigo=o['codigo'],
                                                           cont_tipo_asiento=o['cont_tipo_asiento'], origen=o['origen'], estado='activo'))
            check(ok_resp(r), f"[{k}] tipo de asiento {o['codigo']}", err(r))
    check(val(f"SELECT COUNT(*) FROM gestion__cont_tipos_asientos WHERE empresa_id = {eid} AND codigo = 'AUT' AND tabla_estado_registro_id = 1") == '1',
          f'[{k}] tipo de asiento AUT activo (lo usan los asientos automáticos)')

    # Alícuotas de IVA (página 565)
    for o in sql(f"SELECT * FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {org} AND tabla_estado_registro_id = 1"):
        if val(f"SELECT COUNT(*) FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {eid} AND codigo = '{o['codigo']}'") == '0':
            r = aj(e, 'iva_alicuotas_ajax.php', dict(accion='agregar', pagina_idx=pg(565), codigo=o['codigo'], iva_alicuota=o['iva_alicuota'],
                                                     porcentaje=o['porcentaje'], cont_cuenta_compra_id=cta(e, o['cont_cuenta_compra_id']),
                                                     cont_cuenta_venta_id=cta(e, o['cont_cuenta_venta_id']), es_gravado=o['es_gravado'],
                                                     es_exento=o['es_exento'], es_no_gravado=o['es_no_gravado']))
            check(ok_resp(r), f"[{k}] alícuota {o['iva_alicuota']}", err(r))
    iva = {r_['codigo']: r_ for r_ in sql(f"SELECT iva_alicuota_id, codigo, porcentaje FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {eid}")}
    check('21' in iva and '10.5' in iva and 'EX' in iva, f'[{k}] alícuotas 21 / 10,5 / exento', str(list(iva)))
    e['iva21'] = int(iva.get('21', {}).get('iva_alicuota_id') or 0)
    e['iva105'] = int(iva.get('10.5', {}).get('iva_alicuota_id') or 0)

    # Medios de pago (página 620), con la cuenta contable y la de tesorería de la empresa
    tc_cod = {r_['codigo']: int(r_['tesoreria_cuenta_id']) for r_ in sql(f"SELECT codigo, tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {eid}")}
    for o in sql(f"SELECT m.*, t.codigo tc_codigo FROM gestion__medios_pago m LEFT JOIN gestion__tesoreria_cuentas t ON t.tesoreria_cuenta_id = m.tesoreria_cuenta_id "
                 f"WHERE m.empresa_id = {org} AND m.tabla_estado_registro_id = 1"):
        if val(f"SELECT COUNT(*) FROM gestion__medios_pago WHERE empresa_id = {eid} AND codigo = '{o['codigo']}'") == '0':
            r = aj(e, 'medios_pago_ajax.php', dict(accion='guardar', pagina_idx=pg(620), medio_pago_id=0, codigo=o['codigo'], medio_pago=o['medio_pago'],
                                                   medio_pago_tipo=o['medio_pago_tipo'], tesoreria_cuenta_tipo=o['tesoreria_cuenta_tipo'] or '',
                                                   tesoreria_cuenta_id=tc_cod.get(o['tc_codigo'] or '', 0), cont_cuenta_id=cta(e, o['cont_cuenta_id']),
                                                   impuesto_tipo_id=o['impuesto_tipo_id'] or 0, requiere_detalle=o['requiere_detalle'],
                                                   aplica_cobro=o['aplica_cobro'], aplica_pago=o['aplica_pago'], orden=o['orden']))
            check(ok_resp(r), f"[{k}] medio de pago {o['codigo']}", err(r))
    med = {r_['codigo']: r_ for r_ in sql(f"SELECT m.medio_pago_id, m.codigo, m.impuesto_tipo_id, m.cont_cuenta_id, c.empresa_id cta_emp "
                                          f"FROM gestion__medios_pago m LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = m.cont_cuenta_id "
                                          f"WHERE m.empresa_id = {eid}")}
    for cod in ('EFECTIVO', 'TRANSFER', 'CHQ_TERCERO', 'RET_IVA', 'RET_GAN', 'RET_IIBB'):
        check(cod in med, f'[{k}] medio {cod} disponible', str(list(med)))
    for cod in ('RET_IVA', 'RET_GAN', 'RET_IIBB'):
        m = med.get(cod) or {}
        check(int(m.get('impuesto_tipo_id') or 0) > 0 and int(m.get('cta_emp') or 0) == eid,
              f'[{k}] {cod} con su impuesto y una cuenta contable propia', str(m))

    # Retención de IIBB sufrida por provincia (página 573): S solo Neuquén; B Río Negro, Neuquén y Buenos Aires
    provincias = ['DGR_NQN'] if k == 'S' else ['DGR_RN', 'DGR_NQN', 'ARBA']
    for cod in provincias:
        j = jur(cod)
        if val(f"SELECT COUNT(*) FROM gestion__empresas_impuestos_config WHERE empresa_id = {eid} AND impuesto_tipo_id = 4 AND jurisdiccion_id = {j}") == '0':
            o = uno(f"SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {org} AND impuesto_tipo_id = 4 AND jurisdiccion_id = {j} LIMIT 1")
            r = aj(e, 'empresas_impuestos_config_ajax.php', dict(accion='agregar', pagina_idx=pg(573), impuesto_tipo_id=4, jurisdiccion_id=j,
                                                                 cont_cuenta_id=cta(e, (o or {}).get('cont_cuenta_id')), tipo_calculo='manual',
                                                                 prioridad=2, f_desde='2020-01-01', aplica_siempre=0))
            check(ok_resp(r), f'[{k}] configuración de retención IIBB sufrida {cod}', err(r))
        c = uno(f"SELECT x.cont_cuenta_id, c.empresa_id, c.nombre FROM gestion__empresas_impuestos_config x LEFT JOIN gestion__cont_cuentas c "
                f"ON c.cont_cuenta_id = x.cont_cuenta_id WHERE x.empresa_id = {eid} AND x.impuesto_tipo_id = 4 AND x.jurisdiccion_id = {j} "
                f"AND x.tabla_estado_registro_id = 1")
        check(c and int(c['empresa_id'] or 0) == eid, f"[{k}] IIBB {cod} con cuenta propia ({(c or {}).get('nombre')})", str(c))

    # Tipos de comprobante habilitados en el PV 0001 (página 570): los de todos los PV de la origen
    pv = aj(e, 'puntos_venta_ajax.php', dict(accion='obtener', punto_venta_id=e['pv_id'], id=e['pv_id']), 'GET')
    pvd = pv.get('data', pv) if isinstance(pv, dict) else {}
    tipos = sorted({tipo_dest(e, t['comprobante_tipo_id']) for t in sql(
        f"SELECT DISTINCT comprobante_tipo_id FROM gestion__puntos_venta_comprobantes WHERE empresa_id = {org}")} - {0})
    if val(f"SELECT COUNT(*) FROM gestion__puntos_venta_comprobantes WHERE punto_venta_id = {e['pv_id']}") == '0':
        r = aj(e, 'puntos_venta_ajax.php', dict(accion='editar', pagina_idx=pg(570), punto_venta_id=e['pv_id'], sucursal_id=e['sucursal_id'],
                                                boca_id=e['boca_id'], nombre=pvd.get('nombre') or 'Punto de Venta 0001', codigo_fiscal=1,
                                                sistema_emision=pvd.get('sistema_emision') or 'WSFE',
                                                comprobantes=json.dumps([{'comprobante_tipo_id': t, 'requiere_afip': 0, 'ultimo_numero': 0} for t in tipos])))
        check(ok_resp(r), f'[{k}] PV 0001 con {len(tipos)} tipos de comprobante', err(r))
    n = int(val(f"SELECT COUNT(*) FROM gestion__puntos_venta_comprobantes WHERE punto_venta_id = {e['pv_id']}"))
    check(n == len(tipos) and n > 0, f'[{k}] tipos habilitados en el PV: {n}', f'esperados {len(tipos)}')

    # Tipos de producto (página 596)
    for o in sql(f"SELECT * FROM gestion__productos_tipos WHERE empresa_id = {org} AND tabla_estado_registro_id = 1"):
        cod = 'PROD' if int(o['maneja_stock']) else 'SERV'
        if val(f"SELECT COUNT(*) FROM gestion__productos_tipos WHERE empresa_id = {eid} AND producto_tipo_codigo = '{cod}'") == '0':
            r = aj(e, 'productos_tipos_ajax.php', dict(accion='agregar', pagina_idx=pg(596), producto_tipo=o['producto_tipo'], producto_tipo_codigo=cod,
                                                       maneja_stock=o['maneja_stock'], es_compuesto=o['es_compuesto'], afecta_facturacion=o['afecta_facturacion'],
                                                       cont_cuenta_compra_default_id=cta(e, o['cont_cuenta_compra_default_id']),
                                                       cont_cuenta_venta_default_id=cta(e, o['cont_cuenta_venta_default_id']),
                                                       cont_cuenta_cmv_default_id=cta(e, o['cont_cuenta_cmv_default_id'])))
            check(ok_resp(r), f"[{k}] tipo de producto {o['producto_tipo']}", err(r))
    pt = {r_['producto_tipo_codigo']: int(r_['producto_tipo_id']) for r_ in sql(f"SELECT producto_tipo_id, producto_tipo_codigo FROM gestion__productos_tipos WHERE empresa_id = {eid}")}
    e['tipo_serv'], e['tipo_prod'] = pt.get('SERV', 0), pt.get('PROD', 0)
    check(e['tipo_serv'] and e['tipo_prod'], f'[{k}] tipos de producto Producto y Servicio', str(pt))

    # Parámetros de empresa (página 611): PV, lista y condición de pago por defecto
    par = uno(f"SELECT * FROM gestion__empresas_parametros WHERE empresa_id = {eid} AND tabla_estado_registro_id = 1 AND f_hasta IS NULL ORDER BY 1 DESC LIMIT 1")
    lista = val(f"SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = {eid} ORDER BY lista_precio_id LIMIT 1")
    cond = val(f"SELECT condicion_pago_id FROM gestion__condiciones_pago WHERE empresa_id = {eid} ORDER BY orden, condicion_pago_id LIMIT 1")
    r = aj(e, 'empresas_parametros_ajax.php', dict(accion='guardar', pagina_idx=pg(611), empresa_parametro_id=par['empresa_parametro_id'], empresa_id=eid,
                                                   condicion_fiscal_id=1, punto_venta_defecto_id=e['pv_id'], lista_precio_defecto_id=lista,
                                                   condicion_pago_defecto_id=cond, ingresos_brutos_nro=e['cuit'], iibb_regimen_id=e['iibb_regimen_id'],
                                                   jurisdiccion_sede_id=e['sede_id'], inicio_actividades='2020-01-01', leyenda_fiscal='IVA Responsable Inscripto',
                                                   arca_habilitado=0, f_desde=par['f_desde'], observaciones='SIM prueba global'))
    check(ok_resp(r), f'[{k}] parámetros: PV, lista y condición de pago por defecto', err(r))
    par = uno(f"SELECT punto_venta_defecto_id, lista_precio_defecto_id, arca_habilitado FROM gestion__empresas_parametros WHERE empresa_id = {eid} "
              f"AND tabla_estado_registro_id = 1 AND f_hasta IS NULL ORDER BY 1 DESC LIMIT 1")
    check(par and int(par['punto_venta_defecto_id'] or 0) == e['pv_id'] and str(par['arca_habilitado']) == '0', f'[{k}] parámetros vigentes', str(par))

    # Inscripciones IIBB de la empresa (Control Fiscal, página 625); B en Convenio Multilateral
    inscs = [('DGR_NQN', 1, 1)] if k == 'S' else [('DGR_RN', 2, 1), ('DGR_NQN', 2, 0), ('ARBA', 2, 0)]
    for cod, reg, sede in inscs:
        j = jur(cod)
        if val(f"SELECT COUNT(*) FROM gestion__empresas_iibb_inscripciones WHERE empresa_id = {eid} AND jurisdiccion_id = {j}") == '0':
            r = aj(e, 'cierres_fiscales_ajax.php', dict(accion='agregar_inscripcion', pagina_idx=pg(625), jurisdiccion_id=j, iibb_regimen_id=reg,
                                                        nro_inscripcion=e['cuit'], es_sede=sede, f_desde='2020-01-01',
                                                        coeficiente_cm=('' if reg == 1 else {'DGR_RN': '0.6', 'DGR_NQN': '0.3', 'ARBA': '0.1'}[cod])))
            check(ok_resp(r), f'[{k}] inscripción IIBB {cod}', err(r))

for k, e in EMPRESAS.items():
    r = aj(e, 'empresa_inicio_ajax.php', dict(action='obtener_diagnostico', empresa_id=e['empresa_id']), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    pendientes = [c for c, v in (d.get('checks') or {}).items() if not v]
    check(int(d.get('score') or 0) == 100 and not pendientes, f"[{k}] con los maestros cargados, el diagnóstico da 100",
          f"score {d.get('score')}, pendientes {pendientes}, operación {d.get('operacion')}")

ctx_guardar(ctx)

# =========================================================================================================
# 0.8 - Maestros de negocio: clientes, proveedor y productos / servicios
# =========================================================================================================
caso('N0.8 - Clientes, proveedor y productos')
CLIENTES = [  # clave, nombre, condición IVA, régimen IIBB, sede IIBB (jurisdiccion_sede), cuit base
    ('AGENTE', 'SIM Agente de Retencion SA', 1, 2, 15, 3071999101),
    ('AGIIBB', 'SIM Agente solo IIBB SRL', 1, 1, 15, 3071999102),
    ('MONO', 'SIM Monotributista', 3, 3, 15, 2071999103),
    ('EXENTO', 'SIM Fundacion Exenta', 4, 3, 15, 3071999104),
    ('ARBA', 'SIM Cliente Bonaerense SA', 1, 1, 2, 3071999105),
    ('CF', 'SIM Consumidor Final', 2, 0, 0, 0),
]
for k, e in EMPRESAS.items():
    eid = e['empresa_id']
    lista_cli = CLIENTES + ([('CM_RN', 'SIM Cliente CM Rio Negro SA', 1, 2, 16, 3071999106)] if k == 'B' else [])
    e['clientes'] = {}
    for clave, nombre, cond, reg, sede, base in lista_cli:
        cu = cuit(base) if base else ''
        ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {eid} AND entidad_nombre = '{nombre}'")
        if not ent:
            r = aj(e, 'clientes_ajax.php', dict(accion='agregar_entidad', pagina_idx=pg(594), entidad_nombre=nombre, entidad_fantasia='', entidad_tipo_id='',
                                                cuit=cu, sitio_web='', domicilio_legal='SIM', localidad_id=loc[k], es_proveedor=0, es_cliente=1,
                                                observaciones='SIM prueba global', condicion_fiscal_id=cond, regimen_iibb_id=reg or '',
                                                numero_inscripcion_iibb=cu, jurisdiccion_sede_id=sede or '', fiscal_f_desde='2020-01-01'))
            check(ok_resp(r), f'[{k}] cliente {clave}', err(r))
            ent = (r.get('entidad_id') if isinstance(r, dict) else None) or val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {eid} AND entidad_nombre = '{nombre}'")
        e['clientes'][clave] = int(ent or 0)
    vig = val(f"SELECT COUNT(*) FROM gestion__entidades en WHERE en.empresa_id = {eid} AND EXISTS (SELECT 1 FROM gestion__entidades_roles xr JOIN gestion__roles_entidades xc ON xc.rol_entidad_id = xr.rol_entidad_id AND xc.rol_entidad_codigo = 'CLI' WHERE xr.entidad_id = en.entidad_id AND xr.f_baja IS NULL AND xr.tabla_estado_registro_id = 1) AND EXISTS "
              f"(SELECT 1 FROM gestion__entidades_condiciones_fiscales f WHERE f.entidad_id = en.entidad_id AND f.f_hasta IS NULL)")
    check(int(vig) == len(lista_cli), f'[{k}] {len(lista_cli)} clientes con condición IVA vigente', f'{vig}')
    suc_cli = val(f"SELECT COUNT(*) FROM gestion__entidades_sucursales s JOIN gestion__entidades en ON en.entidad_id = s.entidad_id WHERE en.empresa_id = {eid}")
    ctx[k]['sucursales_clientes'] = int(suc_cli or 0)

    prov = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {eid} AND entidad_nombre = 'SIM Proveedor Mayorista SA'")
    if not prov:
        r = aj(e, 'proveedores_ajax.php', dict(accion='agregar_entidad', pagina_idx=pg(593), entidad_nombre='SIM Proveedor Mayorista SA', entidad_fantasia='',
                                               entidad_tipo_id='', cuit=cuit(3071999201), sitio_web='', domicilio_legal='SIM', localidad_id=loc[k],
                                               es_proveedor=1, es_cliente=0, observaciones='SIM', condicion_fiscal_id=1, regimen_iibb_id=1,
                                               numero_inscripcion_iibb='', jurisdiccion_sede_id=e['sede_id'], fiscal_f_desde='2020-01-01'))
        check(ok_resp(r), f'[{k}] proveedor', err(r))
        prov = (r.get('entidad_id') if isinstance(r, dict) else None) or val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {eid} AND entidad_nombre = 'SIM Proveedor Mayorista SA'")
    e['proveedor_id'] = int(prov or 0)

    # Roles de entidad (gestion__entidades_roles, única fuente desde 20261019_entidades_roles_*): el alta por la página
    # de clientes da CLI y la de proveedores PRO, sin el otro; una cuenta del rol, si la hay, es de la empresa.
    roles = {}
    for f in sql(f"SELECT r.entidad_id, c.rol_entidad_codigo rol FROM gestion__entidades_roles r "
                 f"JOIN gestion__roles_entidades c ON c.rol_entidad_id = r.rol_entidad_id JOIN gestion__entidades en ON en.entidad_id = r.entidad_id "
                 f"WHERE en.empresa_id = {eid} AND r.f_baja IS NULL AND r.tabla_estado_registro_id = 1"):
        roles.setdefault(int(f['entidad_id']), set()).add(f['rol'])
    mal_cli = [c for c, i in e['clientes'].items() if roles.get(i) != {'CLI'}]
    check(not mal_cli, f'[{k}] los {len(e["clientes"])} clientes tienen solo el rol CLI', str({c: roles.get(e['clientes'][c]) for c in mal_cli}))
    check(roles.get(e['proveedor_id']) == {'PRO'}, f'[{k}] el proveedor tiene solo el rol PRO', str(roles.get(e['proveedor_id'])))
    ajenas = val(f"SELECT COUNT(*) FROM gestion__entidades_roles r JOIN gestion__entidades en ON en.entidad_id = r.entidad_id "
                 f"JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = r.cont_cuenta_id WHERE en.empresa_id = {eid} AND cc.empresa_id <> {eid}")
    check(ajenas == '0', f'[{k}] ninguna cuenta de rol de otra empresa', f'{ajenas} roles')

    # Productos / servicios (página 540)
    if k == 'S':
        items = [('SRV-ABONO', 'Abono mensual de soporte', 0), ('SRV-HORA', 'Hora técnica', 0),
                 ('SRV-IMPL', 'Implementación', 0), ('SRV-SOP', 'Soporte en sitio', 0)]
    else:
        items = [('BIE-001', 'Bomba centrífuga 1HP', 1), ('BIE-002', 'Caño PVC 110 x 4m', 1),
                 ('BIE-003', 'Válvula esférica 2"', 1), ('BIE-004', 'Tanque 1000 l', 1), ('SRV-FLETE', 'Flete', 0)]
    # Categoría (página 539): el producto la exige
    cat_nombre = 'SIM Servicios' if k == 'S' else 'SIM Hidraulica'
    cat = val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {eid} AND producto_categoria_nombre = '{cat_nombre}'")
    if not cat:
        r = aj(e, 'productos_categorias_ajax.php', dict(accion='agregar', pagina_idx=pg(539), producto_categoria_nombre=cat_nombre))
        check(ok_resp(r), f'[{k}] categoría de productos', err(r))
        cat = val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {eid} AND producto_categoria_nombre = '{cat_nombre}'")
    e['productos'] = {}
    for cod, nombre, stock_ in items:
        pid = val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {eid} AND producto_codigo = '{cod}'")
        if not pid:
            r = aj(e, 'productos_ajax.php', dict(accion='agregar', pagina_idx=pg(540), producto_codigo=cod, producto_nombre=nombre, producto_descripcion=nombre,
                                                 producto_tipo_id=e['tipo_prod'] if stock_ else e['tipo_serv'], controla_stock=stock_, es_servicio=0 if stock_ else 1,
                                                 aplica_venta=1, aplica_compra=1, iva_alicuota_id=e['iva21'], unidad_medida_id=1,
                                                 producto_categoria_id=cat))
            check(ok_resp(r), f'[{k}] producto {cod}', err(r))
            pid = (r.get('producto_id') if isinstance(r, dict) else None) or val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {eid} AND producto_codigo = '{cod}'")
        e['productos'][cod] = int(pid or 0)
    check(all(e['productos'].values()), f"[{k}] {len(items)} productos / servicios", str(e['productos']))
    ajeno = val(f"SELECT COUNT(*) FROM gestion__productos p JOIN gestion__productos_tipos t ON t.producto_tipo_id = p.producto_tipo_id "
                f"WHERE p.empresa_id = {eid} AND t.empresa_id <> {eid}")
    check(ajeno == '0', f'[{k}] productos con tipo de la propia empresa', f'{ajeno}')
    for c in ('empresa_id', 'moneda_id', 'caja_id', 'banco_id', 'valores_id', 'iva21', 'iva105', 'tipo_serv', 'tipo_prod', 'clientes', 'proveedor_id', 'productos'):
        ctx[k][c] = e[c]

ctx_guardar(ctx)

# =========================================================================================================
# 0.9 - Stock inicial de B: factura de proveedor confirmada
# =========================================================================================================
caso('N0.9 - Stock inicial (solo B) por factura de proveedor')
e = EMPRESAS['B']
fa_prov = tipo_comp(e, 'FC', 'A', '+', 'Factura%')
check(fa_prov > 0, '[B] tipo Factura A de proveedor', str(fa_prov))
CANT = {'BIE-001': 20, 'BIE-002': 100, 'BIE-003': 50, 'BIE-004': 10}
COSTO = {'BIE-001': 80000, 'BIE-002': 9000, 'BIE-003': 6000, 'BIE-004': 120000}
if not val(f"SELECT COUNT(*) FROM gestion__facturas_proveedores WHERE empresa_id = {e['empresa_id']} AND tabla_estado_registro_id = 5") not in (None, '0'):
    r, fid, t = fp_alta_confirmar(e, fa_prov, [(e['productos'][c], CANT[c], COSTO[c]) for c in CANT], fecha_op(1))
    check(fid and ok_resp(r), f"[B] factura de proveedor confirmada por {t['total']}", err(r))
    ctx['B']['fp_stock_id'] = fid
f = uno(f"SELECT f.factura_proveedor_id, f.tabla_estado_registro_id estado, f.comprobante_id, c.importe_total, c.importe_pendiente "
        f"FROM gestion__facturas_proveedores f JOIN gestion__comprobantes c ON c.comprobante_id = f.comprobante_id "
        f"WHERE f.empresa_id = {e['empresa_id']} ORDER BY 1 LIMIT 1")
check(f and f['estado'] == '5' and abs(num(f['importe_pendiente']) - num(f['importe_total'])) < 0.01, '[B] factura confirmada con saldo a pagar', str(f))
for c, q in CANT.items():
    s = stock(e['productos'][c], e['boca_id'], e['empresa_id'])
    check(s == q, f'[B] stock {c} = {q}', f'{s}')
    costo = val(f"SELECT costo_actual FROM gestion__productos_costos WHERE producto_id = {e['productos'][c]} ORDER BY 1 DESC LIMIT 1")
    check(costo and abs(num(costo) - COSTO[c]) < 0.01, f'[B] costo {c} = {COSTO[c]}', f'{costo}')
if f:
    a = uno(f"SELECT a.cont_asiento_id, ROUND(SUM(d.importe_local),2) suma, SUM(c.empresa_id <> {e['empresa_id']}) ajenas "
            f"FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
            f"JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id WHERE a.comprobante_id = {f['comprobante_id']} GROUP BY a.cont_asiento_id")
    check(a and abs(num(a['suma'])) < 0.01 and int(a['ajenas']) == 0, '[B] asiento de la compra balanceado y con cuentas propias', str(a))
stock_s = val(f"SELECT COUNT(*) FROM gestion__stock_movimientos m JOIN gestion__bocas b ON b.boca_id = m.boca_id WHERE b.empresa_id = {EMPRESAS['S']['empresa_id']}")
check(stock_s == '0', '[S] sin movimientos de stock')
ctx_guardar(ctx)

# Foto de las empresas origen de la copia: el nivel 5 verifica que la prueba no las modificó
ctx['foto_origen'] = {str(x): foto_empresa(x) for x in (2, 6)}
ctx_guardar(ctx)
