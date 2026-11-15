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


# ---------------------------------------------------------------------------------------------
P1 = 1529


def alta_pedido(cliente, cantidad=2):
    dets = [linea(P1, cantidad, 100.0, 21.0)]
    r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=55, comprobante_tipo_id=1, sucursal_id=1,
             punto_venta_id=1, entidad_id=cliente, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, detalles=dets, **totales(dets)))
    return r, ((r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None)


def accion_pedido(ped, accion_js, pagina=55):
    return ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js=accion_js, empresa_idx=2, pagina_idx=pagina))


def estado(ped):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped or 0}") or 0)


def botones(ped):
    r = ajax('ventas_pedidos_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=55), 'GET')
    fila = next((x for x in (r if isinstance(r, list) else []) if int(x['venta_pedido_id']) == int(ped or 0)), {})
    return [b['accion_js'] for b in (fila.get('botones') or [])]


caso('Distribución: liberar el pedido para distribución (estado 42)')
if CTX.get('ok'):
    r, ped = alta_pedido(C1)
    r2 = accion_pedido(ped, 'confirmar_pedido')
    check(ok_resp(r) and ok_resp(r2) and estado(ped) == 5, 'pedido confirmado (5)', err(r) + ' ' + err(r2))
    check('liberar_distribucion' in botones(ped), 'en 5 se ofrece "Liberar para distribución"', str(botones(ped)))
    sql(f"UPDATE gestion__ventas_pedidos SET boca_despacho_id = NULL WHERE venta_pedido_id = {ped or 0}")
    r = accion_pedido(ped, 'liberar_distribucion')
    check(ok_resp(r) and estado(ped) == 42, 'liberado: queda en 42', err(r))
    check(int(val(f"SELECT IFNULL(boca_despacho_id, 0) FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped or 0}")) > 0,
          'al liberar se completa el depósito de despacho')
    b = botones(ped)
    check('volver_revision' in b and 'visualizar' in b, 'en 42 se ofrecen Volver a revisión y Visualizar', str(b))
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=91), 'GET')
    check(isinstance(r, list) and int(ped or 0) in [int(x['venta_pedido_id']) for x in r], 'la preparación lista los pedidos en 42', str(r)[:200])
    r = accion_pedido(ped, 'volver_revision')
    check(ok_resp(r) and estado(ped) == 5, 'volver a revisión: 42 -> 5', err(r))
    accion_pedido(ped, 'liberar_distribucion')
    CTX['ped'] = ped
    r, ped2 = alta_pedido(C1, 3)
    accion_pedido(ped2, 'confirmar_pedido')
    accion_pedido(ped2, 'liberar_distribucion')
    CTX['ped2'] = ped2
    r, ped3 = alta_pedido(C1, 1)
    accion_pedido(ped3, 'confirmar_pedido')
    accion_pedido(ped3, 'liberar_distribucion')
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='abrir_pedido', venta_pedido_id=ped3, empresa_idx=2, pagina_idx=91))
    check(estado(ped3) == 9, 'la preparación abre un pedido en 42 y lo pasa a En preparación (9)', str(r)[:200])

    # Indicador apagado: no se ofrece ni se ejecuta
    sql("UPDATE conf__empresas SET usa_distribucion = 0 WHERE empresa_id = 2")
    r, ped4 = alta_pedido(C1)
    accion_pedido(ped4, 'confirmar_pedido')
    check('liberar_distribucion' not in botones(ped4), 'apagado: en 5 no se ofrece liberar', str(botones(ped4)))
    r = accion_pedido(ped4, 'liberar_distribucion')
    check(not ok_resp(r) and estado(ped4) == 5, 'apagado: liberar por la API se rechaza', err(r))
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='listar', empresa_idx=2, pagina_idx=91), 'GET')
    check(isinstance(r, list) and int(CTX['ped']) not in [int(x['venta_pedido_id']) for x in r], 'apagado: la preparación no lista los 42', str(r)[:200])
    b = botones(CTX['ped'])
    check('volver_revision' in b and 'visualizar' in b, 'apagado: un pedido que quedó en 42 se puede ver y volver a revisión', str(b))
    sql("UPDATE conf__empresas SET usa_distribucion = 1 WHERE empresa_id = 2")


# ---------------------------------------------------------------------------------------------
def dh(params, method='POST', usuario_id=1):
    return ajax('distribucion_hojas_ajax.php', dict(params, empresa_idx=2), method, usuario_id=usuario_id)


caso('Distribución: armado, hojas de ruta, lista de carga y rendición')
ped, ped2, k1 = CTX.get('ped'), CTX.get('ped2'), CTX.get('k1')
if CTX.get('ok') and ped and ped2 and k1:
    peso_ant = val("SELECT peso FROM gestion__productos WHERE producto_id = 1529")
    sql("UPDATE gestion__productos SET peso = 10 WHERE producto_id = 1529")   # 10 kg por unidad, solo en la copia
    r = dh(dict(accion='disponibles'), 'GET')
    disp = {int(p['venta_pedido_id']): p for p in (data(r) or [])}
    check(ok_resp(r) and ped in disp and ped2 in disp and abs(num(disp[ped2]['peso_kg']) - 30) < 0.001,
          'disponibles: los dos pedidos en 42, con su peso (3 u x 10 kg = 30 kg)', str(r)[:300])
    check(disp.get(ped, {}).get('zona_nombre'), 'el pedido trae la zona del cliente', str(disp.get(ped))[:200])
    r = dh(dict(accion='hoja_crear', fecha_reparto=HOY, camion_id=k1))
    h1 = (data(r) or {}).get('hoja_id')
    check(ok_resp(r) and h1, 'hoja nueva en borrador', err(r))
    r = dh(dict(accion='agregar_pedidos', hoja_id=h1, pedidos=json.dumps([ped2])))
    check(ok_resp(r) and (data(r) or {}).get('requiere_confirmar'), 'supera la capacidad (30 de 25 kg): pide confirmar y no graba', str(r)[:300])
    r = dh(dict(accion='agregar_pedidos', hoja_id=h1, pedidos=json.dumps([ped2, ped]), forzar=1))
    check(ok_resp(r) and (data(r) or {}).get('agregados') == 2, 'confirmado: se agregan los dos', err(r))
    r = dh(dict(accion='disponibles'), 'GET')
    check(ok_resp(r) and not ({ped, ped2} & {int(p['venta_pedido_id']) for p in data(r)}), 'ya no figuran para armar', str(r)[:200])
    r = dh(dict(accion='hoja_crear', fecha_reparto=HOY, camion_id=k1))
    h2 = (data(r) or {}).get('hoja_id')
    r = dh(dict(accion='agregar_pedidos', hoja_id=h2, pedidos=json.dumps([ped]), forzar=1))
    check(not ok_resp(r) and 'otra hoja' in err(r), 'un pedido no va en dos hojas', err(r))
    r = dh(dict(accion='ordenar', hoja_id=h1, pedidos=json.dumps([ped, ped2])))
    r2 = dh(dict(accion='hoja', hoja_id=h1), 'GET')
    orden = [int(p['venta_pedido_id']) for p in ((data(r2) or {}).get('pedidos') or [])]
    check(ok_resp(r) and orden == [ped, ped2], 'orden de visita manual', str(orden))
    r = dh(dict(accion='lista_carga', hoja_id=h1), 'GET')
    fila = next((f for f in (data(r) or []) if int(f['producto_id']) == 1529), {})
    check(ok_resp(r) and abs(num(fila.get('unidades')) - 5) < 0.001, 'lista de carga: 5 u de P1 (2 + 3)', str(r)[:300])
    r = dh(dict(accion='confirmar', hoja_id=h1))
    check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__distribucion_hojas WHERE hoja_id = {h1}")) == 5
          and abs(num(val(f"SELECT peso_kg FROM gestion__distribucion_hojas WHERE hoja_id = {h1}")) - 50) < 0.001,
          'confirmar: hoja confirmada con la foto de peso (50 kg)', err(r))
    r = accion_pedido(ped, 'volver_revision')
    check(not ok_resp(r) and estado(ped) == 42, 'un pedido de una hoja confirmada no vuelve a revisión', err(r))
    r = dh(dict(accion='cerrar_rendicion', hoja_id=h1))
    check(not ok_resp(r) and 'sin resultado' in err(r), 'no se cierra la rendición con pedidos sin resultado', err(r))
    r = dh(dict(accion='rendir_pedido', hoja_id=h1, venta_pedido_id=ped, resultado='ENTREGADO', cobrado_importe=242, cobrado_medio='Efectivo'))
    check(ok_resp(r), 'rendir: entregado y cobrado', err(r))
    r = dh(dict(accion='rendir_pedido', hoja_id=h1, venta_pedido_id=ped2, resultado='RECHAZADO'))
    check(not ok_resp(r) and 'motivo' in err(r), 'rechazado sin motivo se rechaza', err(r))
    dh(dict(accion='rendir_pedido', hoja_id=h1, venta_pedido_id=ped2, resultado='RECHAZADO', motivo='Local cerrado'))
    r = dh(dict(accion='cerrar_rendicion', hoja_id=h1))
    d = data(r) or {}
    check(ok_resp(r) and (d.get('resultados') or {}).get('ENTREGADO') == 1 and (d.get('resultados') or {}).get('RECHAZADO') == 1 and abs(num(d.get('cobrado')) - 242) < 0.01,
          'cerrar la rendición: resumen y hoja rendida', str(r)[:300])
    r = dh(dict(accion='disponibles'), 'GET')
    ids = {int(p['venta_pedido_id']) for p in (data(r) or [])}
    check(ped2 in ids and ped in ids, 'después de rendir, los que siguen en 42 vuelven al armado', str(ids))
    r = dh(dict(accion='agregar_pedidos', hoja_id=h2, pedidos=json.dumps([ped2]), forzar=1))
    r2 = dh(dict(accion='anular', hoja_id=h2))
    r3 = dh(dict(accion='disponibles'), 'GET')
    check(ok_resp(r) and ok_resp(r2) and ped2 in {int(p['venta_pedido_id']) for p in (data(r3) or [])}, 'anular una hoja devuelve sus pedidos al armado', err(r) + ' ' + err(r2))
    sql(f"UPDATE gestion__productos SET peso = {peso_ant if peso_ant is not None else 'NULL'} WHERE producto_id = 1529")

    sql("UPDATE conf__empresas SET usa_distribucion = 0 WHERE empresa_id = 2")
    r = dh(dict(accion='disponibles'), 'GET')
    check(not ok_resp(r) and 'no usa distribución' in err(r), 'apagado: el armado no corre', err(r))
    sql("UPDATE conf__empresas SET usa_distribucion = 1 WHERE empresa_id = 2")
