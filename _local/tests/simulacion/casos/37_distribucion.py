from simlib import *

# Distribución: zonas, camiones, estado 42, armado, hojas de ruta y rendición (doc 24, paso 5b; empresa 2, módulo 2).
# Necesita en la copia: 20261119_distribucion_01 y _02_paginas (--scripts). Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 37_distribucion.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261119_distribucion_01.sql _local/sql/mantenimiento/20261119_distribucion_02_paginas.sql

C1, C2 = 3, 5
CTX = {}


def estructura_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME LIKE 'gestion__distribucion_%'")) == 5 \
        and int(val("SELECT COUNT(*) FROM conf__paginas WHERE pagina_id IN (148, 149)")) == 2 \
        and int(val("SELECT COUNT(*) FROM conf__paginas_funciones WHERE pagina_funcion_id = 3605")) == 1


def dc(params, method='POST', usuario_id=1):
    return ajax('distribucion_config_ajax.php', dict(params, empresa_idx=2), method, usuario_id=usuario_id)


def data(r):
    return (r.get('data') if isinstance(r, dict) else None)


# ---------------------------------------------------------------------------------------------
caso('Distribución: preparación (indicador, solo en la copia)')
if not estructura_ok():
    brecha('faltan los scripts de distribución en la copia', 'correr con --scripts 20261119_distribucion_01 / _02_paginas')
else:
    sql("UPDATE gestion__distribucion_zonas_clientes SET tabla_estado_registro_id = 2 WHERE empresa_id = 2")
    sql("UPDATE gestion__distribucion_zonas SET tabla_estado_registro_id = 2 WHERE empresa_id = 2")
    sql("UPDATE gestion__distribucion_camiones SET tabla_estado_registro_id = 2 WHERE empresa_id = 2")
    sql("UPDATE conf__empresas SET usa_distribucion = 1 WHERE empresa_id = 2")
    CTX['ok'] = True
    check(int(val("SELECT usa_distribucion FROM conf__empresas WHERE empresa_id = 2")) == 1, 'indicador prendido en la copia')

caso('Distribución: configuración (zonas, clientes, camiones)')
if CTX.get('ok'):
    sufijo = str(int(val("SELECT IFNULL(MAX(zona_id), 0) + 1 FROM gestion__distribucion_zonas")))
    r = dc(dict(accion='zona_agregar', nombre='SIM Centro ' + sufijo, codigo='C', dias_reparto='1,3,5'))
    z1 = (data(r) or {}).get('zona_id')
    check(ok_resp(r) and z1, 'alta de zona', err(r))
    r = dc(dict(accion='zona_agregar', nombre=''))
    check(not ok_resp(r) and 'nombre' in err(r), 'zona sin nombre se rechaza', err(r))
    r = dc(dict(accion='zona_editar', zona_id=z1, nombre='SIM Centro ' + sufijo, dias_reparto='2,4'))
    r2 = dc(dict(accion='zonas_listar'), 'GET')
    z = next((x for x in (data(r2) or []) if int(x['zona_id']) == int(z1 or 0)), {})
    check(ok_resp(r) and z.get('dias_texto') == 'Mar, Jue', 'editar los días de la zona', str(z)[:200])
    r = dc(dict(accion='zona_asignar', zona_id=z1, clientes=json.dumps([C1, C2]), fecha=HOY))
    check(ok_resp(r) and (data(r) or {}).get('asignados') == 2, 'poner 2 clientes en la zona', err(r))
    r = dc(dict(accion='zona_clientes', zona_id=z1), 'GET')
    check(ok_resp(r) and sorted(int(c['cliente_entidad_id']) for c in data(r)) == sorted([C1, C2]), 'clientes de la zona', str(r)[:200])
    r = dc(dict(accion='buscar_clientes', q='CONTRERAS'), 'GET')
    check(ok_resp(r) and any(int(c['entidad_id']) == C1 and c.get('zona_nombre') for c in data(r)), 'la búsqueda muestra la zona del cliente', str(r)[:200])
    r = dc(dict(accion='zona_quitar', clientes=json.dumps([C2]), fecha=HOY))
    r2 = dc(dict(accion='sin_zona', q='ACINASE'), 'GET')
    check(ok_resp(r) and any(int(c['cliente_entidad_id']) == C2 for c in (data(r2) or [])), 'quitar de la zona: queda sin zona', str(r2)[:200])
    CTX['z1'] = z1
    dep = (data(dc(dict(accion='catalogos'), 'GET')) or {}).get('depositos') or []
    check(len(dep) > 0, 'depósitos de la empresa', str(dep)[:200])
    r = dc(dict(accion='camion_agregar', nombre='SIM Camión ' + sufijo, patente='SIM' + sufijo, capacidad_kg=25, capacidad_m3=1, boca_id=dep[0]['boca_id'] if dep else 0, chofer='Juan'))
    k1 = (data(r) or {}).get('camion_id')
    check(ok_resp(r) and k1, 'alta de camión (25 kg / 1 m³)', err(r))
    r = dc(dict(accion='camion_agregar', nombre='X', capacidad_kg=-3))
    check(not ok_resp(r), 'capacidad negativa se rechaza', err(r))
    CTX['k1'] = k1
    CTX['deposito'] = int(dep[0]['boca_id']) if dep else 0
    r = dc(dict(accion='zona_agregar', nombre='No'), usuario_id=8)
    check(not ok_resp(r), 'un perfil sin la página no la usa', err(r))
