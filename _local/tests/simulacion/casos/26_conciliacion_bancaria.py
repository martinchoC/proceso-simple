"""Conciliación bancaria (páginas 128–133 / 628–633), en gestion (empresa 2) y gestion_normal (empresa 6).

Diseño en _local/docs/gestion/12_conciliacion_bancaria.md. Aplica en la copia (idempotentes) los scripts
20261028_conciliacion_bancaria_01/02/03. Usa el extracto real de muestra (_local/tests/fixtures/extracto_muestra.xls,
fuera de git): si no está, el caso se saltea con una brecha.

Recorre: formato (alta, prueba con el archivo, regex inválida), reglas, importación (previsualizar, confirmar, número de
cuenta asignado, archivo repetido, anular el lote y volver a importar), conciliación contra tesorería (sugerencias segura /
probable / agrupada, conciliar automáticamente, diferencia que no cierra, gasto del banco no conciliable a mano, deshacer
con motivo, clasificar / ignorar / restaurar), gastos del banco (bloqueos de proveedor y de IVA, resumen bancario C_RB con
OP por débito en cuenta conciliado con su movimiento, mes repetido, deshacer que anula el comprobante, IVA solo el declarado en el extracto) y CSRF.
Los movimientos de tesorería que se concilian se cargan por SQL (preparación: no prueban otro circuito).
"""
import base64

from simlib import *

for _s in ('20261028_conciliacion_bancaria_01_estructura.sql', '20261028_conciliacion_bancaria_02_conceptos_iniciales.sql',
           '20261028_conciliacion_bancaria_03_paginas.sql', '20261029_conciliacion_bancaria_04_reglas_aprendidas.sql',
           '20261030_conciliacion_bancaria_05_percepciones_jurisdiccion.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))

ARCHIVO = os.path.join(AQUI, '..', 'fixtures', 'extracto_muestra.xls')
MODULOS = [m for m in os.environ.get('SIM_BANCOS_MODULOS', 'gestion,gestion_normal').split(',') if m]


def entorno(modulo, empresa, desplazamiento):
    e = dict(modulo=modulo, empresa=empresa, d=desplazamiento)
    e['cuenta'] = uno(f"SELECT tesoreria_cuenta_id id, banco_id, sucursal_id, moneda_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {empresa} "
                      f"AND cuenta_tipo = 'BANCO' AND banco_id IS NOT NULL AND tabla_estado_registro_id = 1 ORDER BY tesoreria_cuenta_id LIMIT 1")
    e['comp'] = int(val(f"SELECT comprobante_id FROM gestion__comprobantes WHERE empresa_id = {empresa} ORDER BY comprobante_id LIMIT 1"))
    e['prov'] = int(val(f"SELECT e.entidad_id FROM gestion__entidades e JOIN gestion__entidades_roles r ON r.entidad_id = e.entidad_id "
                        f"JOIN gestion__roles_entidades re ON re.rol_entidad_id = r.rol_entidad_id AND re.rol_entidad_codigo = 'PRO' "
                        f"WHERE e.empresa_id = {empresa} AND e.tabla_estado_registro_id = 1 AND r.f_baja IS NULL "
                        f"AND EXISTS (SELECT 1 FROM gestion__entidades_sucursales s WHERE s.entidad_id = e.entidad_id) ORDER BY e.entidad_id LIMIT 1"))
    e['conceptos'] = {f['codigo']: int(f['concepto_id']) for f in sql(f"SELECT codigo, concepto_id FROM gestion__bancos_conceptos WHERE empresa_id = {empresa}")}
    return e


def pg(e, n):
    return n + e['d']


def call(e, archivo, params, method='POST', **kw):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    return ajax(archivo, p, method, modulo=e['modulo'], empresa_id=e['empresa'], **kw)


def mov(e, fecha, tipo, importe, concepto):
    """Movimiento de tesorería de la cuenta (preparación por SQL)."""
    sql(f"INSERT INTO gestion__tesoreria_movimientos (empresa_id, sucursal_id, tesoreria_cuenta_id, comprobante_id, tipo_movimiento, f_movimiento, f_valor, "
        f"moneda_id, tipo_cambio, importe, concepto, conciliado, tabla_estado_registro_id) VALUES ({e['empresa']}, "
        f"{int(e['cuenta']['sucursal_id'] or 1)}, {e['cuenta']['id']}, {e['comp']}, '{tipo}', '{fecha}', '{fecha}', {e['cuenta']['moneda_id']}, 1, "
        f"{importe}, '{concepto}', 0, 1)")
    sql(f"UPDATE gestion__tesoreria_cuentas SET saldo_actual = saldo_actual {'+' if tipo == 'INGRESO' else '-'} {importe} WHERE tesoreria_cuenta_id = {e['cuenta']['id']}")
    return int(val("SELECT MAX(tesoreria_movimiento_id) FROM gestion__tesoreria_movimientos"))


def linea_id(e, texto, importe):
    return int(val(f"SELECT extracto_id FROM gestion__bancos_extracto WHERE empresa_id = {e['empresa']} AND tesoreria_cuenta_id = {e['cuenta']['id']} "
                   f"AND descripcion LIKE '{texto}%' AND importe = {importe} ORDER BY extracto_id LIMIT 1"))


def correr(e):
    A_F, A_K, A_R, A_I, A_C, A_G = ('bancos_formatos_ajax.php', 'bancos_conceptos_ajax.php', 'bancos_reglas_ajax.php',
                                     'bancos_importar_ajax.php', 'bancos_conciliacion_ajax.php', 'bancos_gastos_ajax.php')
    m = e['modulo']
    cid = int(e['cuenta']['id'])
    contenido = base64.b64encode(open(ARCHIVO, 'rb').read()).decode()
    archivo = {'archivo': {'name': 'consultaMovimientos.xls', 'content_b64': contenido}}
    # Estado limpio de la cuenta en la COPIA: puede traer corridas previas o extractos importados en la base local
    sql(f"UPDATE gestion__tesoreria_cuentas SET numero = NULL, proveedor_entidad_id = NULL WHERE tesoreria_cuenta_id = {cid}")
    sql(f"UPDATE gestion__bancos_extracto SET conciliacion_id = NULL WHERE tesoreria_cuenta_id = {cid}")
    sql(f"UPDATE gestion__tesoreria_movimientos SET conciliado = 0, f_conciliacion = NULL, conciliacion_id = NULL WHERE tesoreria_cuenta_id = {cid}")
    sql(f"DELETE i FROM gestion__bancos_conciliaciones_items i JOIN gestion__bancos_conciliaciones k ON k.conciliacion_id = i.conciliacion_id "
        f"WHERE k.tesoreria_cuenta_id = {cid}")
    sql(f"DELETE FROM gestion__bancos_conciliaciones WHERE tesoreria_cuenta_id = {cid}")
    sql(f"DELETE FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid}")
    sql(f"DELETE FROM gestion__bancos_importaciones WHERE tesoreria_cuenta_id = {cid}")
    # Resúmenes bancarios de la cuenta generados en la base local: sin su conciliación quedarían "sin conciliar" y bloquean
    sql(f"UPDATE gestion__facturas_proveedores f JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id AND ct.codigo = 'C_RB' "
        f"SET f.tabla_estado_registro_id = 38 WHERE f.empresa_id = {e['empresa']} AND f.comprobante_pv = {cid} AND f.tabla_estado_registro_id NOT IN (6, 38)")

    # ---------------------------------------------------------------- formato
    caso(f'{m}: formato del banco')
    form = dict(banco_id=e['cuenta']['banco_id'], formato='SIM extracto web', tipo_archivo='XLS', hoja='report', fila_inicio=8,
                formato_fecha='d-M-Y', idioma_fecha='ES', orden_archivo='DESC', modo_importe='SIGNO', sep_decimal=',', sep_miles='.',
                col_fecha='B', col_descripcion='C', col_importe='D', col_saldo='E', cuenta_fila=5, cuenta_regex=r'Cuenta:\s*([0-9\-/]+)',
                referencia_regex=r'\(?(\d{11})\)?', f_desde='2026-01-01')
    r = call(e, A_F, dict(form, accion='agregar', cuenta_regex='Cuenta:(['))
    check(not ok_resp(r) and 'no es válida' in err(r), 'regex inválida rechazada', err(r))
    r = call(e, A_F, dict(form, accion='probar', _files=archivo))
    d = r.get('data') or {}
    check(ok_resp(r) and d.get('cantidad') == 49 and d['control']['pares_ok'] == 48, 'probar formato: 49 movimientos y 48/48 saldos', err(r) if not ok_resp(r) else d.get('control'))
    r = call(e, A_F, dict(form, accion='agregar', _sin_csrf=True))
    check(not ok_resp(r) and 'venció' in err(r), 'escritura sin token CSRF rechazada', err(r))
    r = call(e, A_F, dict(form, accion='agregar'))
    check(ok_resp(r), 'formato creado', err(r))
    e['formato'] = int((r.get('data') or {}).get('formato_id') or 0)
    f = uno(f"SELECT GROUP_CONCAT(CONCAT(campo, columna_indice) ORDER BY columna_indice) c FROM gestion__bancos_formatos_columnas WHERE formato_id = {e['formato']}")
    check(f and f['c'] == 'FECHA1,DESCRIPCION2,IMPORTE3,SALDO4', 'columnas por letra guardadas como índice', f)

    # ---------------------------------------------------------------- reglas
    caso(f'{m}: reglas')
    k = e['conceptos']
    reglas = [(10, 'EMPIEZA', 'COMISION', 'DEBITO', 'COMISION'), (20, 'REGEX', r'^IMPUESTO (DEBITO|CREDITO)\s*-\s*LEY 25413', 'DEBITO', 'LEY25413'),
              (30, 'CONTIENE', 'SIRCREB', 'DEBITO', 'SIRCREB'), (40, 'EMPIEZA', 'DB.DEBIN', 'DEBITO', 'DEBIN'),
              (50, 'EMPIEZA', 'P.SERV', 'DEBITO', 'PAGO_SERVICIOS'), (60, 'CONTIENE', 'FONDO COMUN DE INVERSION', 'AMBOS', 'FCI'),
              (70, 'EMPIEZA', 'TRANSF', 'AMBOS', 'TRANSFERENCIA'), (70, 'EMPIEZA', 'CR.TRAN.', 'AMBOS', 'TRANSFERENCIA')]
    sql(f"UPDATE gestion__bancos_reglas SET tabla_estado_registro_id = 2 WHERE empresa_id = {e['empresa']} AND banco_id = {e['cuenta']['banco_id']}")
    oks = 0
    for prio, tipo, patron, aplica, conc in reglas:
        r = call(e, A_R, dict(accion='agregar', banco_id=e['cuenta']['banco_id'], concepto_id=k[conc], prioridad=prio, tipo_coincidencia=tipo, patron=patron, aplica_a=aplica))
        oks += ok_resp(r)
    check(oks == len(reglas), '8 reglas creadas', oks)
    r = call(e, A_R, dict(accion='agregar', banco_id=e['cuenta']['banco_id'], concepto_id=k['FCI'], tipo_coincidencia='REGEX', patron='(a+)+(', aplica_a='AMBOS'))
    check(not ok_resp(r), 'regla con regex inválida rechazada', err(r))
    r = call(e, A_R, dict(accion='probar_texto', banco_id=e['cuenta']['banco_id'], descripcion='IMPUESTO CREDITO -LEY 25413', importe=-5), 'GET')
    check(ok_resp(r) and (r.get('data') or {}).get('patron', '').startswith('^IMPUESTO'), 'probar texto: lo clasifica la regla de ley 25.413', r.get('data'))

    # ---------------------------------------------------------------- importación
    caso(f'{m}: importar extracto')
    imp = dict(cuenta_id=cid, formato_id=e['formato'], _files=archivo)
    r = call(e, A_I, dict(imp, accion='previsualizar'))
    d = r.get('data') or {}
    check(ok_resp(r) and d.get('cantidad_nuevas') == 49 and not d.get('bloqueos') and d.get('sin_concepto') == 0,
          'previsualizar: 49 nuevas, sin bloqueos, todas con concepto', err(r) if not ok_resp(r) else (d.get('bloqueos'), d.get('sin_concepto')))
    check(any('no tiene número' in a for a in d.get('avisos', [])), 'avisa que la cuenta no tiene número', d.get('avisos'))
    check(num(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid}")) == 0, 'previsualizar no escribe')
    r = call(e, A_I, dict(imp, accion='agregar'))
    check(ok_resp(r) and (r.get('data') or {}).get('lineas') == 49, 'importación confirmada: 49 líneas', err(r))
    lote = int((r.get('data') or {}).get('importacion_id') or 0)
    c = uno(f"SELECT COUNT(*) n, SUM(estado = 'CLASIFICADA') cl, ROUND(SUM(importe), 2) t FROM gestion__bancos_extracto WHERE importacion_id = {lote}")
    check(c and int(c['n']) == 49 and int(c['cl']) == 49 and num(c['t']) == -408946.73, 'líneas clasificadas, Σ = −408.946,73', c)
    check(val(f"SELECT numero FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}") == '6229-69485/0', 'número de cuenta asignado desde el archivo')
    ruta = val(f"SELECT archivo_ruta FROM gestion__bancos_importaciones WHERE importacion_id = {lote}")
    check(ruta and os.path.exists(os.path.join(AQUI, '..', '..', '..', ruta)), 'archivo guardado en uploads/extractos_bancarios', ruta)
    r = call(e, A_I, dict(imp, accion='agregar'))
    check(not ok_resp(r) and 'ya se importó' in err(r), 'el mismo archivo no se importa dos veces', err(r))
    r = call(e, A_I, dict(accion='ejecutar_accion', accion_js='anular', importacion_id=lote))
    check(ok_resp(r) and num(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE importacion_id = {lote}")) == 0, 'anular el lote quita sus líneas', err(r))
    r = call(e, A_I, dict(imp, accion='agregar'))
    check(ok_resp(r), 'el archivo de un lote anulado se vuelve a importar', err(r))
    lote = int((r.get('data') or {}).get('importacion_id') or 0)
    sql(f"UPDATE gestion__tesoreria_cuentas SET numero = '1111-22222/3' WHERE tesoreria_cuenta_id = {cid}")
    r = call(e, A_I, dict(imp, accion='previsualizar'))
    check(ok_resp(r) and any('es de la cuenta' in b for b in (r.get('data') or {}).get('bloqueos', [])), 'cuenta distinta a la del archivo: bloquea', (r.get('data') or {}).get('bloqueos'))
    sql(f"UPDATE gestion__tesoreria_cuentas SET numero = '6229-69485/0' WHERE tesoreria_cuenta_id = {cid}")

    # ---------------------------------------------------------------- conciliación
    caso(f'{m}: conciliar contra tesorería')
    l_cr = linea_id(e, 'CR.TRAN.', 288273.34)
    l_tr = linea_id(e, 'TRANSF A', -500000)
    l_gr = linea_id(e, 'TRANSF DE', 712355.04)
    cuit = val(f"SELECT referencia FROM gestion__bancos_extracto WHERE extracto_id = {l_cr}")
    m_cr = mov(e, '2026-10-19', 'INGRESO', 288273.34, f'Cobranza transferencia CUIT {cuit}')
    m_tr = mov(e, '2026-10-20', 'EGRESO', 500000, 'Pago a proveedor por transferencia')
    m_g1 = mov(e, '2026-10-14', 'INGRESO', 400000, 'Cobranza parcial 1')
    m_g2 = mov(e, '2026-10-13', 'INGRESO', 312355.04, 'Cobranza parcial 2')
    m_x = mov(e, '2026-10-15', 'EGRESO', 999.99, 'Movimiento que no está en el banco')
    filtro = dict(cuenta_id=cid, desde='2026-10-01', hasta='2026-10-31')
    r = call(e, A_C, dict(filtro, accion='datos'), 'GET')
    d = r.get('data') or {}
    check(ok_resp(r) and len(d.get('lineas', [])) == 49 and d['totales']['saldo_banco'] == 90321.62, 'pantalla: 49 líneas y saldo del banco 90.321,62',
          err(r) if not ok_resp(r) else d.get('totales'))
    r = call(e, A_C, dict(filtro, accion='sugerir'), 'GET')
    # La empresa 6 tiene además una cobranza real que coincide con el extracto (importe y CUIT del cliente): otra segura
    autos = [s for s in (r.get('data') or []) if s['tipo'] == 'AUTO']
    sug = {s['tipo']: s for s in (r.get('data') or []) if s['tipo'] != 'AUTO'}
    check(any(s['extracto_ids'] == [l_cr] and s['movimiento_ids'] == [m_cr] for s in autos), 'sugerencia segura por importe y CUIT', [s['motivo'] for s in autos])
    check(sug.get('SUGERIDA', {}).get('extracto_ids') == [l_tr] and sug['SUGERIDA']['movimiento_ids'] == [m_tr], 'sugerencia probable: único movimiento con ese importe')
    check(sorted(sug.get('GRUPO', {}).get('movimiento_ids', [])) == sorted([m_g1, m_g2]), 'sugerencia agrupada: dos cobranzas suman la transferencia')
    r = call(e, A_C, dict(filtro, accion='agregar', extracto_ids=[l_tr], movimiento_ids=[m_x]))
    check(not ok_resp(r) and 'No cierra' in err(r), 'diferencia que no cierra: rechazada', err(r))
    l_ley = linea_id(e, 'IMPUESTO DEBITO', -3000)
    r = call(e, A_C, dict(filtro, accion='agregar', extracto_ids=[l_ley], movimiento_ids=[m_x]))
    check(not ok_resp(r) and 'Gastos Bancarios' in err(r), 'un gasto del banco no se concilia a mano', err(r))
    r = call(e, A_C, dict(filtro, accion='conciliar_auto'))
    check(ok_resp(r) and r['data']['conciliadas'] == len(autos), 'conciliar automáticamente: todas las seguras', err(r))
    check(val(f"SELECT estado FROM gestion__bancos_extracto WHERE extracto_id = {l_cr}") == 'CONCILIADA'
          and val(f"SELECT conciliado FROM gestion__tesoreria_movimientos WHERE tesoreria_movimiento_id = {m_cr}") == '1', 'las dos puntas quedan conciliadas')
    for s in ('SUGERIDA', 'GRUPO'):
        r = call(e, A_C, dict(filtro, accion='agregar', extracto_ids=sug[s]['extracto_ids'], movimiento_ids=sug[s]['movimiento_ids']))
        check(ok_resp(r), f'conciliar la sugerencia {s.lower()}', err(r))
    conc_grupo = int((r.get('data') or {}).get('conciliacion_id') or 0)
    check(val(f"SELECT COUNT(*) FROM gestion__bancos_conciliaciones_items WHERE conciliacion_id = {conc_grupo}") == '3', 'conciliación N:1 con 3 items')
    r = call(e, A_C, dict(filtro, accion='agregar', extracto_ids=sug['GRUPO']['extracto_ids'], movimiento_ids=sug['GRUPO']['movimiento_ids']))
    check(not ok_resp(r), 'no se concilia dos veces', err(r))
    r = call(e, A_C, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=conc_grupo, motivo=''))
    check(not ok_resp(r) and 'motivo' in err(r), 'deshacer exige motivo', err(r))
    r = call(e, A_C, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=conc_grupo, motivo='SIM prueba'))
    check(ok_resp(r) and val(f"SELECT estado FROM gestion__bancos_extracto WHERE extracto_id = {l_gr}") == 'CLASIFICADA'
          and val(f"SELECT CONCAT(conciliado, IFNULL(conciliacion_id, '-')) FROM gestion__tesoreria_movimientos WHERE tesoreria_movimiento_id = {m_g1}") == '0-'
          and val(f"SELECT tabla_estado_registro_id FROM gestion__bancos_conciliaciones WHERE conciliacion_id = {conc_grupo}") == '38',
          'deshacer libera las dos puntas y deja la conciliación en 38', err(r))
    r = call(e, A_I, dict(accion='ejecutar_accion', accion_js='anular', importacion_id=lote))
    check(not ok_resp(r) and ('historial' in err(r) or 'conciliadas' in err(r)), 'un lote con líneas conciliadas o que lo estuvieron no se anula (mensaje claro)', err(r))
    # Reimportar el mismo extracto con el día 21 en otro orden (saldos distintos): no duplica por fecha + importe
    sql(f"UPDATE gestion__bancos_extracto SET saldo = saldo + 1, hash_mov = SHA2(CONCAT(hash_mov, 'x'), 256) WHERE importacion_id = {lote} AND f_movimiento = '2026-10-21'")
    sql(f"UPDATE gestion__bancos_importaciones SET archivo_hash = SHA2(CONCAT(archivo_hash, 'x'), 256) WHERE importacion_id = {lote}")
    r = call(e, A_I, dict(imp, accion='previsualizar'))
    d = r.get('data') or {}
    check(ok_resp(r) and d.get('cantidad_nuevas') == 0, 'líneas con otro saldo pero misma fecha e importe: no se duplican', (d.get('cantidad_nuevas'), d.get('bloqueos')))
    # Asignar a mano aprende "descripción igual a" -> concepto y lo aplica a las demás líneas sin concepto iguales
    texto = 'IMPUESTO DEBITO - LEY 25413'
    sql(f"UPDATE gestion__bancos_extracto SET concepto_id = NULL, regla_id = NULL, concepto_origen = NULL, estado = 'PENDIENTE' "
        f"WHERE tesoreria_cuenta_id = {cid} AND descripcion = '{texto}' AND estado = 'CLASIFICADA'")
    iguales = int(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid} AND descripcion = '{texto}' AND estado = 'PENDIENTE'"))
    una = int(val(f"SELECT MIN(extracto_id) FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid} AND descripcion = '{texto}' AND estado = 'PENDIENTE'"))
    r = call(e, A_C, dict(accion='clasificar', operacion='asignar', concepto_id=k['LEY25413'], extracto_ids=[una]))
    d = r.get('data') or {}
    check(ok_resp(r) and d.get('lineas') == 1 and d.get('mismas') == iguales - 1 and d.get('reglas') == 1,
          f'asignar a una línea clasifica las otras {iguales - 1} con la misma descripción', err(r) if not ok_resp(r) else d)
    ra = uno(f"SELECT regla_id, prioridad, aplica_a, origen, tabla_estado_registro_id est FROM gestion__bancos_reglas WHERE empresa_id = {e['empresa']} "
             f"AND tipo_coincidencia = 'IGUAL' AND patron = '{texto}'")
    check(ra and ra['origen'] == 'APRENDIDA' and ra['prioridad'] == '1' and ra['aplica_a'] == 'DEBITO' and ra['est'] == '1',
          'queda registrada como regla aprendida (prioridad 1, solo débitos)', ra)
    r = call(e, A_R, dict(accion='probar_texto', banco_id=e['cuenta']['banco_id'], descripcion='impuesto  debito - ley 25413', importe=-1), 'GET')
    check(ok_resp(r) and str((r.get('data') or {}).get('regla_id')) == ra['regla_id'], 'la regla aprendida clasifica los próximos extractos (sin distinguir mayúsculas)')
    r = call(e, A_C, dict(accion='asignar', operacion='asignar', concepto_id=k['LEY25413'], extracto_ids=[una]))   # acción inexistente: no cambia nada
    r = call(e, A_C, dict(accion='clasificar', operacion='quitar', extracto_ids=[una]))
    check(ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__bancos_reglas WHERE regla_id = {ra['regla_id']}") == '2'
          and val(f"SELECT CONCAT(estado, '/', concepto_id) FROM gestion__bancos_extracto WHERE extracto_id = {una}") == f"CLASIFICADA/{k['LEY25413']}",
          'quitar el concepto inactiva la regla aprendida (la línea vuelve a la regla general)', err(r))
    check(num(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid} AND descripcion = '{texto}' AND concepto_id = {k['LEY25413']}")) == iguales,
          'las líneas iguales siguen con su concepto')
    l_fci = linea_id(e, 'FONDO COMUN', -3000000)
    r = call(e, A_C, dict(accion='clasificar', operacion='asignar', concepto_id=k['DEPOSITO'], extracto_ids=[l_fci]))
    check(ok_resp(r) and val(f"SELECT CONCAT(concepto_origen, '/', concepto_id) FROM gestion__bancos_extracto WHERE extracto_id = {l_fci}") == f"MANUAL/{k['DEPOSITO']}",
          'asignar concepto a mano', err(r))
    call(e, A_R, dict(accion='aplicar'))
    check(val(f"SELECT concepto_id FROM gestion__bancos_extracto WHERE extracto_id = {l_fci}") == str(k['DEPOSITO']), 'aplicar reglas no pisa lo asignado a mano')
    call(e, A_C, dict(accion='clasificar', operacion='ignorar', extracto_ids=[l_fci]))
    check(val(f"SELECT estado FROM gestion__bancos_extracto WHERE extracto_id = {l_fci}") == 'IGNORADA', 'ignorar una línea')
    call(e, A_C, dict(accion='clasificar', operacion='restaurar', extracto_ids=[l_fci]))
    call(e, A_C, dict(accion='clasificar', operacion='quitar', extracto_ids=[l_fci]))
    check(val(f"SELECT CONCAT(estado, '/', concepto_origen) FROM gestion__bancos_extracto WHERE extracto_id = {l_fci}") == 'CLASIFICADA/REGLA', 'restaurar y quitar el concepto: vuelve a la regla')

    # ---------------------------------------------------------------- gastos del banco
    caso(f'{m}: gastos del banco')
    g = dict(cuenta_id=cid, mes='2026-10')
    sql(f"UPDATE gestion__bancos_conceptos SET jurisdiccion_id = NULL WHERE concepto_id = {k['SIRCREB']}")
    r = call(e, A_G, dict(g, accion='resumen'), 'GET')
    d = r.get('data') or {}
    check(ok_resp(r) and any('proveedor del banco' in b for b in d.get('bloqueos', [])), 'sin proveedor del banco: bloquea', d.get('bloqueos'))
    check(not any('IVA' in b for b in d.get('bloqueos', [])), 'comisiones sin IVA en el extracto: no bloquea (van como no gravado)', d.get('bloqueos'))
    sir = [l for l in d.get('lineas', []) if l['concepto_codigo'] == 'SIRCREB']
    sir_ids = [l['extracto_id'] for l in sir]
    sir_total = round(-sum(num(l['importe']) for l in sir), 2)
    check(len(sir) == 8 and all(not l['incluida'] and 'provincia' in l['motivo'] for l in sir)
          and any('provincia del concepto' in b for b in d.get('bloqueos', [])), 'SIRCREB sin provincia: bloquea', d.get('bloqueos'))
    r = call(e, A_G, dict(g, accion='proveedor', entidad_id=e['prov']))
    check(ok_resp(r), 'proveedor del banco guardado', err(r))
    jur = int((((call(e, A_K, dict(accion='opciones'), 'GET').get('data') or {}).get('jurisdicciones')) or [{'jurisdiccion_id': 0}])[0]['jurisdiccion_id'])
    r = call(e, A_K, dict(accion='editar', concepto_id=k['SIRCREB'], codigo='SIRCREB', concepto='Ingresos Brutos SIRCREB (retención s/ créditos)',
                          naturaleza='GASTO_BANCARIO', rol='RETENCION_IIBB', jurisdiccion_id=jur, orden=70))
    check(ok_resp(r) and val(f"SELECT CONCAT(impuesto_tipo_id, '/', jurisdiccion_id) FROM gestion__bancos_conceptos WHERE concepto_id = {k['SIRCREB']}") == f'1/{jur}',
          'SIRCREB: provincia elegida en el concepto (percepción IIBB)', err(r))
    r = call(e, A_K, dict(accion='editar', concepto_id=k['SIRCREB'], codigo='SIRCREB', concepto='Ingresos Brutos SIRCREB',
                          naturaleza='GASTO_BANCARIO', rol='RETENCION_IIBB', jurisdiccion_id=0, orden=70))
    check(not ok_resp(r) and 'provincia' in err(r), 'sin provincia no se guarda', err(r))
    comisiones = [l['extracto_id'] for l in d.get('lineas', []) if l['concepto_codigo'] == 'COMISION']
    r = call(e, A_G, dict(g, accion='resumen', excluir=comisiones), 'GET')
    d = r.get('data') or {}
    imp = d.get('impuestos') or []
    check(ok_resp(r) and not d.get('bloqueos') and len(imp) == 1 and int(imp[0]['jurisdiccion_id']) == jur and num(imp[0]['importe']) == sir_total
          and round(d.get('total', 0) - 31854.87, 2) == sir_total, 'SIRCREB entra como percepción IIBB de la provincia', d.get('bloqueos') or imp)
    no_sir = [l['extracto_id'] for l in d.get('lineas', []) if l['extracto_id'] not in sir_ids]
    r = call(e, A_G, dict(g, accion='resumen', excluir=no_sir), 'GET')
    check(ok_resp(r) and any('no pueden ir solas' in b for b in (r.get('data') or {}).get('bloqueos', [])),
          'SIRCREB solo, sin gastos con producto: bloquea con el motivo', (r.get('data') or {}).get('bloqueos'))
    comisiones = comisiones + sir_ids   # el primer comprobante, solo la ley 25.413
    r = call(e, A_G, dict(g, accion='resumen', excluir=comisiones), 'GET')
    d = r.get('data') or {}
    check(ok_resp(r) and not d.get('bloqueos') and len(d.get('incluidas', [])) == 23 and d.get('total') == 31854.87,
          'excluyendo comisiones: 23 líneas de ley 25.413 por 31.854,87', d.get('bloqueos') or (len(d.get('incluidas', [])), d.get('total')))
    saldo_antes = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}"))
    r = call(e, A_G, dict(g, accion='agregar', excluir=comisiones))
    check(ok_resp(r), 'resumen bancario generado', err(r))
    dg = r.get('data') or {}
    fp = uno(f"SELECT f.tabla_estado_registro_id est, f.total, f.no_gravado, f.subtotal, f.tesoreria_cuenta_debito_id cta, f.pago_automatico_id op, ct.codigo, "
             f"f.comprobante_id FROM gestion__facturas_proveedores f JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id "
             f"WHERE f.factura_proveedor_id = {int(dg.get('factura_proveedor_id') or 0)}")
    check(fp and fp['codigo'] == 'C_RB' and fp['est'] == '5' and num(fp['total']) == 31854.87 and num(fp['no_gravado']) == 31854.87 and num(fp['subtotal']) == 0,
          'C_RB confirmado, todo no gravado', fp)
    check(fp and int(fp['cta'] or 0) == cid and fp['op'], 'con débito en la cuenta y OP automática', fp)
    check(fp and val(f"SELECT tabla_estado_registro_id FROM gestion__cont_asientos WHERE comprobante_id = {fp['comprobante_id']} LIMIT 1") is not None, 'asiento del comprobante')
    k2 = uno(f"SELECT origen, tabla_estado_registro_id est, importe_extracto, importe_tesoreria FROM gestion__bancos_conciliaciones WHERE conciliacion_id = {int(dg.get('conciliacion_id') or 0)}")
    check(k2 and k2['origen'] == 'GASTOS' and k2['est'] == '5' and num(k2['importe_extracto']) == -31854.87 and num(k2['importe_tesoreria']) == -31854.87,
          'conciliación GASTOS: banco = tesorería', k2)
    check(num(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE conciliacion_id = {int(dg.get('conciliacion_id') or 0)} AND estado = 'CONCILIADA'")) == 23,
          '23 líneas conciliadas')
    check(round(saldo_antes - num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}")), 2) == 31854.87, 'el banco bajó 31.854,87')
    r = call(e, A_G, dict(g, accion='resumen'), 'GET')
    d2 = r.get('data') or {}
    check(ok_resp(r) and not any('Ya hay' in b for b in d2.get('bloqueos', [])) and any('Ya hay 1 resumen' in a for a in d2.get('avisos', [])),
          'un segundo comprobante del mes se permite (aviso, no bloqueo)', (d2.get('bloqueos'), d2.get('avisos')))
    r = call(e, A_G, dict(g, accion='resumen', excluir=comisiones[1:]), 'GET')   # una comisión sola (sin SIRCREB)
    d2 = r.get('data') or {}
    una_com = [l for l in d2.get('lineas', []) if l['extracto_id'] == comisiones[0]]
    check(ok_resp(r) and d2.get('incluidas') == [comisiones[0]] and una_com and d2.get('f_emision') == una_com[0]['f_movimiento'],
          'la fecha del comprobante es la del último movimiento incluido', (d2.get('incluidas'), d2.get('f_emision')))
    r = call(e, A_C, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=dg.get('conciliacion_id'), motivo='x'))
    check(not ok_resp(r) and 'Gastos Bancarios' in err(r), 'los gastos no se deshacen desde Conciliación', err(r))
    r = call(e, A_G, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=dg.get('conciliacion_id'), motivo='SIM deshacer gastos'))
    check(ok_resp(r), 'deshacer los gastos', err(r))
    check(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {int(dg.get('factura_proveedor_id') or 0)}") == '38',
          'el resumen bancario queda anulado')
    check(round(num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}")) - saldo_antes, 2) == 0, 'el banco vuelve al saldo anterior')
    check(num(val(f"SELECT COUNT(*) FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid} AND estado = 'CLASIFICADA' "
                  f"AND concepto_id = {k['LEY25413']}")) == 23, 'las líneas vuelven a estar disponibles')

    # Sin líneas de IVA en el extracto no hubo IVA liquidado: comisiones + ley 25.413, todo no gravado
    r = call(e, A_G, dict(g, accion='resumen'), 'GET')
    d = r.get('data') or {}
    check(ok_resp(r) and not d.get('bloqueos') and d.get('total') == round(33729.32 + sir_total, 2) and d.get('iva_extracto') == 0
          and all(num(x['iva']) == 0 for x in d.get('detalles', [])), 'sin IVA en el extracto: 35 líneas (con SIRCREB) sin IVA', d.get('bloqueos') or d.get('total'))
    comisiones = [x for x in comisiones if x not in sir_ids]

    # Con una línea de IVA en el extracto: ese IVA va al comprobante, base = IVA / 21 % y el resto no gravado
    sql(f"INSERT INTO gestion__bancos_extracto (empresa_id, importacion_id, tesoreria_cuenta_id, nro_linea, f_movimiento, descripcion, importe, hash_mov, "
        f"concepto_id, concepto_origen, estado) SELECT empresa_id, importacion_id, tesoreria_cuenta_id, 9999, '2026-10-15', 'SIM IVA COMISIONES', -300.00, "
        f"SHA2('sim-iva-{m}', 256), {k['IVA']}, 'MANUAL', 'CLASIFICADA' FROM gestion__bancos_extracto WHERE extracto_id = {comisiones[0]}")
    l_iva = int(val(f"SELECT extracto_id FROM gestion__bancos_extracto WHERE tesoreria_cuenta_id = {cid} AND descripcion = 'SIM IVA COMISIONES'"))
    r = call(e, A_G, dict(g, accion='resumen', excluir=comisiones), 'GET')
    check(ok_resp(r) and any('IVA' in b for b in (r.get('data') or {}).get('bloqueos', [])),
          'IVA del extracto sin la comisión gravada que lo admite: bloquea', (r.get('data') or {}).get('bloqueos'))
    r = call(e, A_G, dict(g, accion='resumen'), 'GET')
    d = r.get('data') or {}
    iva_det = round(sum(num(x['iva']) for x in d.get('detalles', [])), 2)
    check(ok_resp(r) and not d.get('bloqueos') and d.get('iva_extracto') == 300 and iva_det == 300 and d.get('total') == round(34029.32 + sir_total, 2),
          'con IVA declarado: el comprobante lleva ese IVA (300,00)', d.get('bloqueos') or (d.get('iva_extracto'), iva_det, d.get('total')))
    r = call(e, A_G, dict(g, accion='agregar'))
    check(ok_resp(r), 'resumen bancario con comisiones e IVA generado', err(r))
    fp = uno(f"SELECT total, impuestos, subtotal, no_gravado, otros_impuestos, comprobante_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {int((r.get('data') or {}).get('factura_proveedor_id') or 0)}")
    check(fp and num(fp['total']) == round(34029.32 + sir_total, 2) and num(fp['impuestos']) == 300 and num(fp['subtotal']) == 1428.57
          and round(num(fp['subtotal']) + num(fp['no_gravado']) + num(fp['impuestos']), 2) == 34029.32,
          'IVA 300,00 sobre neto 1.428,57; el resto no gravado', fp)
    check(fp and num(fp['otros_impuestos']) == sir_total
          and val(f"SELECT CONCAT(impuesto_tipo_id, '/', jurisdiccion_id, '/', ROUND(importe, 2)) FROM gestion__comprobantes_impuestos WHERE comprobante_id = {fp['comprobante_id']} AND tabla_estado_registro_id = 1") == f'1/{jur}/{sir_total:.2f}',
          'SIRCREB en el comprobante: percepción IIBB recibida de la provincia', fp)
    conc2 = int((r.get('data') or {}).get('conciliacion_id') or 0)

    # Período de IVA cerrado (Control Fiscal): no se genera ni se deshace
    caso(f'{m}: período fiscal cerrado')
    sql(f"INSERT INTO gestion__impuestos_periodos_cierres (empresa_id, impuesto_tipo_id, jurisdiccion_id, periodo, origen, observaciones, usuario_id, "
        f"fecha_creacion, tabla_estado_registro_id) VALUES ({e['empresa']}, 7, 0, '2026-10', 'MANUAL', 'SIM conciliación', 1, NOW(), 5)")
    cierre = int(val("SELECT MAX(cierre_id) FROM gestion__impuestos_periodos_cierres"))
    r = call(e, A_G, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=conc2, motivo='SIM período cerrado'))
    check(not ok_resp(r) and 'cerrado' in err(r), 'con el período de IVA cerrado no se deshacen los gastos', err(r))
    check(val(f"SELECT tabla_estado_registro_id FROM gestion__bancos_conciliaciones WHERE conciliacion_id = {conc2}") == '5', 'la conciliación sigue vigente')
    sql(f"UPDATE gestion__impuestos_periodos_cierres SET tabla_estado_registro_id = 38, motivo_reapertura = 'SIM' WHERE cierre_id = {cierre}")
    r = call(e, A_G, dict(accion='ejecutar_accion', accion_js='deshacer', conciliacion_id=conc2, motivo='SIM período reabierto'))
    check(ok_resp(r), 'reabierto el período, se deshace', err(r))
    sql(f"UPDATE gestion__impuestos_periodos_cierres SET tabla_estado_registro_id = 5, motivo_reapertura = NULL WHERE cierre_id = {cierre}")
    r = call(e, A_G, dict(g, accion='resumen'), 'GET')
    check(ok_resp(r) and any('cerrado' in b for b in (r.get('data') or {}).get('bloqueos', [])), 'con el período cerrado no se genera', (r.get('data') or {}).get('bloqueos'))
    sql(f"DELETE FROM gestion__impuestos_periodos_cierres WHERE cierre_id = {cierre}")
    sql(f"UPDATE gestion__bancos_extracto SET estado = 'IGNORADA' WHERE extracto_id = {l_iva} AND conciliacion_id IS NULL")


if not os.path.exists(ARCHIVO):
    caso('conciliación bancaria')
    brecha('Falta el extracto de muestra', '_local/tests/fixtures/extracto_muestra.xls (no se versiona: tiene datos reales)')
else:
    ENTORNOS = {'gestion': lambda: entorno('gestion', 2, 0), 'gestion_normal': lambda: entorno('gestion_normal', 6, 500)}
    for _m in MODULOS:
        correr(ENTORNOS[_m]())
