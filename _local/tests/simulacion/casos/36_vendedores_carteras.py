from simlib import *

# Vendedores y carteras de clientes (doc 23, paso 5a; empresa 2, módulo 2).
# Necesita en la copia: 20261118_vendedores_carteras_01 y _02_pagina (--scripts). Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 36_vendedores_carteras.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261118_vendedores_carteras_01.sql _local/sql/mantenimiento/20261118_vendedores_carteras_02_pagina.sql

C1, C2, C3 = 3, 5, 25     # clientes de la empresa 2
V2_ENT = 28               # entidad existente que pasa a ser vendedor
USU_V = 8                 # usuario con el perfil "Pedidos" (12) de la empresa 2
CTX = {}


def estructura_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME IN ('gestion__vendedores_usuarios', 'gestion__vendedores_carteras')")) == 2 \
        and int(val("SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 147")) == 1


def vp(params, method='POST', usuario_id=1):
    return ajax('vendedores_ajax.php', dict(params, empresa_idx=2), method, usuario_id=usuario_id)


def data(r):
    return (r.get('data') if isinstance(r, dict) else None)


# ---------------------------------------------------------------------------------------------
caso('Vendedores: preparación (indicador, solo en la copia)')
if not estructura_ok():
    brecha('faltan los scripts de vendedores en la copia', 'correr con --scripts 20261118_vendedores_carteras_01 / _02_pagina')
else:
    sql("DELETE FROM gestion__vendedores_carteras WHERE empresa_id = 2")
    sql("DELETE FROM gestion__vendedores_usuarios WHERE empresa_id = 2")
    sql("UPDATE conf__empresas SET usa_vendedores = 1 WHERE empresa_id = 2")
    CTX['ok'] = True
    check(int(val("SELECT usa_vendedores FROM conf__empresas WHERE empresa_id = 2")) == 1, 'indicador prendido en la copia')

caso('Vendedores: página Vendedores y carteras')
if CTX.get('ok'):
    r = vp(dict(accion='alta', entidad_nombre='SIM Vendedor Uno', usuario_id=USU_V))
    v1 = (data(r) or {}).get('vendedor_entidad_id')
    CTX['v1'] = v1
    check(ok_resp(r) and v1 and int(val(f"SELECT COUNT(*) FROM gestion__vendedores_usuarios WHERE vendedor_entidad_id = {v1 or 0} "
                                         f"AND usuario_id = {USU_V} AND tabla_estado_registro_id = 1")) == 1,
          'alta mínima con usuario ligado', err(r))
    r = vp(dict(accion='alta', entidad_id=V2_ENT))
    CTX['v2'] = V2_ENT
    check(ok_resp(r), 'alta desde una entidad existente', err(r))
    r = vp(dict(accion='alta'))
    check(not ok_resp(r) and 'nombre' in err(r), 'alta sin entidad ni nombre se rechaza', err(r))
    r = vp(dict(accion='listar'), 'GET')
    vs = [int(x['vendedor_entidad_id']) for x in ((data(r) or {}).get('vendedores') or [])]
    check(ok_resp(r) and v1 in vs and V2_ENT in vs, 'el listado trae los dos vendedores', str(r)[:300])
    r = vp(dict(accion='asignar', vendedor_entidad_id=v1, clientes=json.dumps([C1, C2]), fecha=HOY))
    check(ok_resp(r) and (data(r) or {}).get('asignados') == 2, 'asignar 2 clientes', err(r))
    r = vp(dict(accion='cartera', vendedor_entidad_id=v1, fecha=HOY), 'GET')
    check(ok_resp(r) and sorted(int(c['cliente_entidad_id']) for c in data(r)) == sorted([C1, C2]), 'cartera del vendedor 1', str(r)[:300])
    r = vp(dict(accion='sin_vendedor', q='AUTOPARTES EBES'), 'GET')
    check(ok_resp(r) and any(int(c['cliente_entidad_id']) == C3 for c in data(r)), 'C3 figura sin vendedor', str(r)[:300])
    r = vp(dict(accion='asignar', vendedor_entidad_id=V2_ENT, clientes=json.dumps([C2, C3]), fecha=HOY))
    check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__vendedores_carteras WHERE cliente_entidad_id = {C2} AND tabla_estado_registro_id = 1 "
                                 f"AND f_desde <= '{HOY}' AND (f_hasta IS NULL OR f_hasta >= '{HOY}')")) == 1,
          'pasar C2 al vendedor 2: un solo vendedor vigente', err(r))
    r = vp(dict(accion='historial', cliente_entidad_id=C2), 'GET')
    check(ok_resp(r) and len(data(r)) == 2, 'el historial de C2 guarda las dos asignaciones', str(r)[:300])
    r = vp(dict(accion='baja', vendedor_entidad_id=v1))
    check(not ok_resp(r) and 'reasign' in err(r), 'baja con cartera se rechaza', err(r))
    # Permisos: el perfil del vendedor (12) no tiene la página
    r = vp(dict(accion='asignar', vendedor_entidad_id=v1, clientes=json.dumps([C3]), fecha=HOY), usuario_id=USU_V)
    check(not ok_resp(r), 'un perfil sin la página no asigna clientes', err(r))
    r = vp(dict(accion='usuarios'), 'GET')
    check(ok_resp(r) and any(int(u['usuario_id']) == USU_V and int(u['vendedor_entidad_id'] or 0) == v1 for u in data(r)),
          'usuarios de la empresa con el vendedor que son', str(r)[:300])
