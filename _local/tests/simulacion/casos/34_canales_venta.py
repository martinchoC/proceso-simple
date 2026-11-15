from simlib import *

# Canales de venta (doc 21, paso 3): canal de la lista, canales que vende cada sucursal, lista del cliente unificada
# y validación de sucursal en pedidos, remitos y facturas (empresa 2, módulo 2).
# Necesita en la copia el script 20261116_canales_venta_01.sql (--scripts). Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 34_canales_venta.py --scripts <arca_off.sql> _local/sql/mantenimiento/20261116_canales_venta_01.sql

CLI = 313              # ABEJER WALTER - lista 1 (General)
P1 = 1529
LISTA_GRAL, LISTA_MOST = 1, 3
SUC_BB, SUC_MDP = 1, 2  # Bahía Blanca vende todo; Mar del Plata solo Mayorista
CTX = {}


def estructura_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME IN ('gestion__canales_venta', 'gestion__sucursales_canales_venta')")) == 2


def lp(params, method='POST'):
    return ajax('listas_precios_ajax.php', dict(params, empresa_idx=2, pagina_id=53), method)


def editar_lista(lista_id, **extra):
    r = lp(dict(accion='obtener', lista_precio_id=lista_id), 'GET')
    d = r if isinstance(r, dict) else {}
    datos = dict(accion='editar', lista_precio_id=lista_id, lista_precio_codigo=d.get('lista_precio_codigo', ''),
                 lista_precio_nombre=d.get('lista_precio_nombre', ''), descripcion=d.get('descripcion') or '',
                 lista_precio_origen_id=d.get('lista_precio_origen_id', 0), lista_base_id=d.get('lista_base_id') or '',
                 moneda_id=d.get('moneda_id') or '', observaciones=d.get('observaciones') or '')
    if int(d.get('requiere_recalculo') or 0) == 1:
        datos['requiere_recalculo'] = '1'
    datos.update(extra)
    return lp(datos)


def editar_sucursal(suc_id, canales):
    s = uno(f"SELECT sucursal_nombre, descripcion, sucursal_tipo_id, localidad_id, direccion, telefono, email "
            f"FROM gestion__sucursales WHERE sucursal_id = {suc_id}")
    return ajax('sucursales_ajax.php', dict(accion='editar', sucursal_id=suc_id, sucursal_nombre=s['sucursal_nombre'],
                descripcion=s['descripcion'] or '', sucursal_tipo_id=s['sucursal_tipo_id'], localidad_id=s['localidad_id'] or '',
                direccion=s['direccion'] or '', telefono=s['telefono'] or '', email=s['email'] or '',
                empresa_idx=2, pagina_idx=36, canales_venta=json.dumps(canales)))


# ---------------------------------------------------------------------------------------------
caso('Canales de venta: preparación (indicador, solo en la copia)')
if not estructura_ok():
    brecha('falta el script de canales de venta en la copia', 'correr con --scripts 20261116_canales_venta_01.sql')
else:
    sql("UPDATE conf__empresas SET usa_canales_venta = 1 WHERE empresa_id = 2")
    CTX['ok'] = True
    check(int(val("SELECT usa_canales_venta FROM conf__empresas WHERE empresa_id = 2")) == 1, 'indicador prendido en la copia')

caso('Canales de venta: ventana Canales en Listas de precios')
if CTX.get('ok'):
    r = lp(dict(accion='canal_guardar', nombre='Minorista', codigo='MIN', orden=1))
    check(ok_resp(r), 'alta Minorista', err(r))
    r = lp(dict(accion='canal_guardar', nombre='Mayorista', orden=2))
    check(ok_resp(r), 'alta Mayorista (código automático)', err(r))
    r = lp(dict(accion='canal_guardar', nombre='Otro', codigo='MIN'))
    check(not ok_resp(r) and 'Ya hay' in err(r), 'código repetido se rechaza', err(r))
    r = lp(dict(accion='canales_listar'))
    canales = {c['codigo']: c['canal_venta_id'] for c in ((r.get('data') or {}).get('canales') or [])} if isinstance(r, dict) else {}
    check(set(canales) == {'MIN', 'MAYORISTA'}, 'listar los dos canales', str(r)[:300])
    CTX['min'], CTX['may'] = canales.get('MIN'), canales.get('MAYORISTA')

caso('Canales de venta: canal de cada lista')
if CTX.get('min') and CTX.get('may'):
    r = editar_lista(LISTA_GRAL, canal_venta_id=CTX['may'])
    check(isinstance(r, dict) and r.get('resultado'), 'General → Mayorista', err(r))
    r = editar_lista(LISTA_MOST, canal_venta_id=CTX['min'])
    check(isinstance(r, dict) and r.get('resultado'), 'Mostrador → Minorista', err(r))
    check(int(val(f"SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = {LISTA_GRAL}")) == CTX['may']
          and int(val(f"SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = {LISTA_MOST}")) == CTX['min'],
          'quedó grabado en las listas')
    r = editar_lista(LISTA_GRAL)  # sin el campo: no se toca
    check(int(val(f"SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = {LISTA_GRAL}")) == CTX['may'],
          'editar la lista sin el campo conserva el canal')
    r = editar_lista(LISTA_GRAL, canal_venta_id=999999)
    check(isinstance(r, dict) and not r.get('resultado') and 'canal' in (r.get('error') or ''), 'canal inexistente se rechaza', str(r)[:200])

caso('Canales de venta: canales que vende cada sucursal')
if CTX.get('may'):
    r = editar_sucursal(SUC_MDP, [CTX['may']])
    check(isinstance(r, dict) and r.get('resultado'), 'Mar del Plata vende solo Mayorista', err(r))
    r = ajax('sucursales_ajax.php', dict(accion='obtener', sucursal_id=SUC_MDP, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and r.get('canales_venta') == [CTX['may']], 'obtener devuelve los canales', str(r)[:200])
    r = ajax('sucursales_ajax.php', dict(accion='obtener', sucursal_id=SUC_BB, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and r.get('canales_venta') == [], 'Bahía Blanca sin restricción', str(r)[:200])
    r = editar_sucursal(SUC_BB, [999999])
    check(isinstance(r, dict) and not r.get('resultado'), 'canal inexistente en la sucursal se rechaza', str(r)[:200])

caso('Canales de venta: con el indicador apagado, listas y sucursales no cambian')
if CTX.get('min') and CTX.get('may'):
    sql("UPDATE conf__empresas SET usa_canales_venta = 0 WHERE empresa_id = 2")
    r = editar_lista(LISTA_GRAL, canal_venta_id=CTX['min'])
    check(isinstance(r, dict) and r.get('resultado') and
          int(val(f"SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = {LISTA_GRAL}")) == CTX['may'],
          'la lista se guarda y el canal no se toca', err(r))
    r = editar_sucursal(SUC_MDP, [])
    check(isinstance(r, dict) and r.get('resultado') and
          int(val(f"SELECT COUNT(*) FROM gestion__sucursales_canales_venta WHERE sucursal_id = {SUC_MDP} AND tabla_estado_registro_id = 1")) == 1,
          'la sucursal se guarda y sus canales no se tocan', err(r))
    r = ajax('sucursales_ajax.php', dict(accion='obtener', sucursal_id=SUC_MDP, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and 'canales_venta' not in r, 'obtener no agrega canales', str(r)[:200])
    r = lp(dict(accion='canales_listar'))
    check(not ok_resp(r), 'la ventana de canales no responde', str(r)[:200])
    sql("UPDATE conf__empresas SET usa_canales_venta = 1 WHERE empresa_id = 2")

# ---------------------------------------------------------------------------------------------
SIN_COND = 5747        # cliente de la empresa 2 sin condición comercial
COD_EN_LISTA, COD_FUERA = '30034', '27008D'   # P1 (en la lista General) y un producto sin precio en ella


def buscar(archivo, accion, entidad_id, q, **extra):
    r = ajax(archivo, dict(accion=accion, entidad_id=entidad_id, q=q, empresa_idx=2, **extra), 'GET')
    if isinstance(r, dict):
        return r.get('productos') if 'productos' in r else (r.get('data') if isinstance(r.get('data'), list) else [])
    return r if isinstance(r, list) else []


def prod(lista, codigo):
    return next((p for p in (lista or []) if p.get('producto_codigo') == codigo), None)


caso('Canales de venta: lista del cliente unificada')
if CTX.get('may'):
    r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener_condiciones_cliente', entidad_id=CLI, empresa_idx=2), 'GET')
    d = (r.get('data') or {}) if isinstance(r, dict) else {}
    check(int(d.get('lista_precio_id') or 0) == LISTA_GRAL and d.get('lista_origen') == 'condicion' and d.get('canal') == 'Mayorista',
          'pedido: cliente con condición → su lista, con canal', str(r)[:300])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener_condiciones_cliente', entidad_id=SIN_COND, empresa_idx=2), 'GET')
    d = (r.get('data') or {}) if isinstance(r, dict) else {}
    check(ok_resp(r) and int(d.get('lista_precio_id') or 0) == LISTA_GRAL and d.get('lista_origen') == 'defecto',
          'pedido: cliente sin condición → lista por defecto (antes, sin lista)', str(r)[:300])
    r = ajax('ventas_remitos_ajax.php', dict(accion='obtener_condiciones_cliente', entidad_id=SIN_COND, empresa_idx=2), 'GET')
    d = (r.get('data') or {}) if isinstance(r, dict) else {}
    check(int(d.get('lista_precio_id') or 0) == LISTA_GRAL and d.get('lista_origen') == 'defecto', 'remito: igual', str(r)[:300])
    r = ajax('ventas_facturas_ajax.php', dict(accion='condicion_cliente', entidad_id=SIN_COND, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and int(r.get('lista_precio_id') or 0) == LISTA_GRAL and r.get('lista_precio_nombre') == 'General',
          'factura: igual, con el nombre de la lista', str(r)[:300])
    r = ajax('ventas_facturas_ajax.php', dict(accion='condicion_cliente', entidad_id=0, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and int(r.get('lista_precio_id') or 0) == LISTA_MOST, 'factura a consumidor final: Mostrador (como hoy)', str(r)[:200])

caso('Canales de venta: aviso "sin precio en la lista" en los buscadores')
if CTX.get('may'):
    for archivo, accion in [('ventas_pedidos_ajax.php', 'buscar_productos_cliente'), ('ventas_remitos_ajax.php', 'buscar_productos_cliente'),
                            ('ventas_facturas_ajax.php', 'buscar_productos')]:
        a, b = prod(buscar(archivo, accion, CLI, COD_EN_LISTA), COD_EN_LISTA), prod(buscar(archivo, accion, CLI, COD_FUERA), COD_FUERA)
        # El buscador de remitos solo ofrece productos con precio en la lista: el de afuera no aparece
        fuera_ok = (b is None) if 'remitos' in archivo else (b and b.get('en_lista') is False)
        check(a and a.get('en_lista') is True and fuera_ok,
              f'{archivo.split("_ajax")[0]}: en_lista true / false', f'{a and a.get("en_lista")} / {b and b.get("en_lista")}')
    sql("UPDATE conf__empresas SET usa_canales_venta = 0 WHERE empresa_id = 2")
    a = prod(buscar('ventas_pedidos_ajax.php', 'buscar_productos_cliente', CLI, COD_EN_LISTA), COD_EN_LISTA)
    check(a and 'en_lista' not in a, 'con el indicador apagado no se agrega en_lista', str(a)[:200])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener_condiciones_cliente', entidad_id=SIN_COND, empresa_idx=2), 'GET')
    check(isinstance(r, dict) and not ok_resp(r), 'con el indicador apagado, el cliente sin condición sigue sin lista', str(r)[:200])
    sql("UPDATE conf__empresas SET usa_canales_venta = 1 WHERE empresa_id = 2")

# ---------------------------------------------------------------------------------------------
MINORISTA = 5742       # cliente sin condición: en la copia se le da la lista Mostrador (canal Minorista)
NO_VENDE = 'no vende al canal'


def pedido(entidad, sucursal, pv):
    dets = [linea(P1, 1, 100.0)]
    return ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=sucursal,
                punto_venta_id=pv, entidad_id=entidad, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
                descuento_general_pct=0, detalles=dets, **totales(dets)))


def remito(entidad, pv):
    return ajax('ventas_remitos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=88, punto_venta_id=pv, comprobante_tipo_id=40,
                entidad_id=entidad, f_emision=HOY, detalles=[linea(P1, 1, 100.0)]))


def factura(entidad, sucursal, pv, **extra):
    datos = dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=pv, sucursal_id=sucursal, comprobante_tipo_id=4,
                 entidad_id=entidad, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
                 descuento_general_pct=0, observaciones='SIM canales', detalles=[linea(P1, 1, 100.0)])
    datos.update(extra)
    return ajax('ventas_facturas_ajax.php', datos)


caso('Canales de venta: la sucursal solo vende a sus canales (pedido, remito, factura)')
if CTX.get('may'):
    sql(f"INSERT INTO gestion__entidades_condiciones_clientes (entidad_id, lista_precio_id, f_desde, tabla_estado_registro_id) "
        f"VALUES ({MINORISTA}, {LISTA_MOST}, '2020-01-01', 1)")
    editar_sucursal(SUC_MDP, [CTX['may']])
    r = pedido(MINORISTA, SUC_MDP, 2)
    check(not ok_resp(r) and NO_VENDE in err(r) and 'Minorista' in err(r), 'pedido de un minorista en Mar del Plata: rechazado', err(r))
    r = pedido(MINORISTA, SUC_BB, 1)
    check(NO_VENDE not in err(r), 'el mismo pedido en Bahía Blanca: no lo frena el canal', err(r))
    r = pedido(CLI, SUC_MDP, 2)
    check(NO_VENDE not in err(r), 'pedido de un mayorista en Mar del Plata: no lo frena el canal', err(r))
    r = remito(MINORISTA, 2)
    check(not ok_resp(r) and NO_VENDE in err(r), 'remito de un minorista desde Mar del Plata: rechazado', err(r))
    r = remito(CLI, 2)
    check(NO_VENDE not in err(r), 'remito de un mayorista desde Mar del Plata: no lo frena el canal', err(r))
    r = factura(MINORISTA, SUC_MDP, 2)
    check(not ok_resp(r) and NO_VENDE in err(r), 'factura de un minorista en Mar del Plata: rechazada', err(r))
    r = factura('', SUC_MDP, 2, consumidor_final_nombre='SIM mostrador')
    check(not ok_resp(r) and NO_VENDE in err(r), 'venta de mostrador (consumidor final) en Mar del Plata: rechazada', err(r))
    r = factura(MINORISTA, SUC_BB, 1)
    check(NO_VENDE not in err(r), 'factura del minorista en Bahía Blanca: no la frena el canal', err(r))

    sql("UPDATE conf__empresas SET usa_canales_venta = 0 WHERE empresa_id = 2")
    r = pedido(MINORISTA, SUC_MDP, 2)
    check(NO_VENDE not in err(r), 'con el indicador apagado, el pedido del minorista en Mar del Plata no se frena', err(r))
    r = factura(MINORISTA, SUC_MDP, 2)
    check(NO_VENDE not in err(r), 'con el indicador apagado, la factura tampoco', err(r))
    sql("UPDATE conf__empresas SET usa_canales_venta = 1 WHERE empresa_id = 2")
