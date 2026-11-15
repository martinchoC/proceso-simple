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


# ---------------------------------------------------------------------------------------------
P1 = 1529


def alta_pedido(cliente, usuario_id=1, **extra):
    dets = [linea(P1, 2, 100.0, 21.0)]
    r = ajax('ventas_pedidos_ajax.php', dict(dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=1,
             punto_venta_id=1, entidad_id=cliente, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, detalles=dets, **totales(dets)), **extra), usuario_id=usuario_id)
    return r, ((r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None)


def vend_ped(ped):
    return uno(f"SELECT vendedor_entidad_id v, origen o FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped or 0}")


caso('Vendedores: pedido con vendedor, origen y cartera')
v1 = CTX.get('v1')
if CTX.get('ok') and v1:
    # Estado de la cartera tras la parte de la página: C1 -> vendedor 1; C2 y C3 -> vendedor 2. Usuario 8 = vendedor 1.
    r, p1 = alta_pedido(C1, usuario_id=USU_V)
    l = vend_ped(p1)
    check(ok_resp(r) and l and int(l['v'] or 0) == v1 and l['o'] == 'VENDEDOR', 'el vendedor carga un pedido de su cliente: a su nombre, origen VENDEDOR', err(r) + ' ' + str(l))
    r, p2 = alta_pedido(C2, usuario_id=USU_V)
    check(not ok_resp(r) and 'cartera' in err(r), 'el vendedor no carga pedidos de un cliente de otra cartera', err(r))
    r, p3 = alta_pedido(C2)
    l = vend_ped(p3)
    check(ok_resp(r) and l and int(l['v'] or 0) == V2_ENT and l['o'] == 'ERP', 'administración carga un pedido: vendedor de la cartera, origen ERP', err(r) + ' ' + str(l))
    r, p4 = alta_pedido(C3, vendedor_entidad_id=v1)
    l = vend_ped(p4)
    check(ok_resp(r) and l and int(l['v'] or 0) == v1, 'administración elige otro vendedor ("Asignar vendedor")', err(r) + ' ' + str(l))
    CTX['p3'] = p3

    r = ajax('ventas_pedidos_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=91), 'GET', usuario_id=USU_V)
    ids = [int(x['venta_pedido_id']) for x in (r if isinstance(r, list) else [])]
    clientes = {int(x['entidad_id']) for x in (r if isinstance(r, list) else [])}
    check(p1 in ids and p4 in ids and p3 not in ids and C2 not in clientes,
          'el listado del vendedor trae su cartera y sus pedidos, no los de otros', str(r)[:300])
    fila = next((x for x in (r if isinstance(r, list) else []) if int(x['venta_pedido_id']) == p1), {})
    check(fila.get('vendedor_nombre') == 'SIM Vendedor Uno', 'el listado trae el nombre del vendedor', str(fila)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=91), 'GET')
    check(isinstance(r, list) and p3 in [int(x['venta_pedido_id']) for x in r], 'el administrador ve todos los pedidos', str(r)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener', venta_pedido_id=p3, empresa_idx=2, pagina_idx=91), 'GET', usuario_id=USU_V)
    check(isinstance(r, dict) and 'cartera' in err(r), 'el vendedor no abre un pedido de otra cartera', str(r)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener_clientes', empresa_idx=2, pagina_idx=91), 'GET', usuario_id=USU_V)
    cl = {int(x['entidad_id']) for x in (r if isinstance(r, list) else [])}
    check(cl == {C1}, 'el selector de clientes del vendedor trae solo su cartera', str(r)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='vendedores_opciones', empresa_idx=2, pagina_idx=91), 'GET')
    d = data(r) or {}
    check(d.get('activo') and d.get('puede_asignar') and any(int(v['id']) == v1 for v in d.get('vendedores', [])), 'opciones de la pantalla para administración', str(r)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='vendedores_opciones', empresa_idx=2, pagina_idx=91), 'GET', usuario_id=USU_V)
    d = data(r) or {}
    check(d.get('activo') and not d.get('puede_asignar') and d.get('restringido'), 'el vendedor no elige vendedor y está restringido', str(r)[:200])
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=55), 'GET', usuario_id=1)
    check(isinstance(r, list), 'Pedidos Realizados lista con el indicador prendido', str(r)[:200])

    # Indicador apagado: todo como antes
    sql("UPDATE conf__empresas SET usa_vendedores = 0 WHERE empresa_id = 2")
    r, p5 = alta_pedido(C2, usuario_id=USU_V)
    l = vend_ped(p5)
    check(ok_resp(r) and l and l['v'] is None and l['o'] is None, 'apagado: el vendedor carga cualquier cliente y las columnas quedan en NULL', err(r) + ' ' + str(l))
    r = ajax('ventas_pedidos_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=91), 'GET', usuario_id=USU_V)
    check(isinstance(r, list) and CTX['p3'] in [int(x['venta_pedido_id']) for x in r] and all('vendedor_nombre' not in x for x in r),
          'apagado: el listado no filtra ni agrega el vendedor', str(r)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='vendedores_opciones', empresa_idx=2, pagina_idx=91), 'GET')
    check(isinstance(r, dict) and (data(r) or {}).get('activo') is False, 'apagado: la pantalla no agrega el selector', str(r)[:200])
    sql("UPDATE conf__empresas SET usa_vendedores = 1 WHERE empresa_id = 2")
