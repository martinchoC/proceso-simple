from simlib import *

# Aplicación del vendedor (doc 28, pasos 9-7 / 9-8): app_vendedor/api.php con la sesión de un usuario vendedor.
# Empresa 2, módulo 2. Solo su cartera, borrador real (origen APP) que se retoma, confirmación con el tope de
# descuento, búsqueda por código de barras de una presentación, entrega estimada, y el mismo pedido que el ERP.
#   python _local/tests/simulacion/run.py 43_app_vendedor.py --scripts <arca_off.sql> _local/sql/mantenimiento/20261123_canales_carrito_app_01.sql

USU_V = 8                 # usuario con el perfil "Pedidos" (empresa_perfil 12) de la empresa 2
PERFIL = 12
CLI, AJENO = 313, 25
P1, P2 = 1529, 1878
CTX = {}


def app(accion, params=None, method='GET', usuario=USU_V):
    return ajax('api.php', dict(params or {}, accion=accion, empresa_idx=2), method, usuario_id=usuario, modulo='app_vendedor')


def d(r):
    return (r.get('data') if isinstance(r, dict) else None) or {}


# ---------------------------------------------------------------------------------------------
caso('App del vendedor: apagada')
r = app('inicio')
check(not ok_resp(r) and 'no está habilitada' in (r.get('message') or ''), 'con usa_app_vendedor apagado no se puede usar', str(r)[:200])

caso('App del vendedor: preparación (solo en la copia)')
CTX['ind'] = uno("SELECT usa_app_vendedor a, usa_vendedores b, usa_presentaciones c, usa_venta_presentaciones e, usa_reglas_precio_venta f, "
                 "usa_distribucion g FROM conf__empresas WHERE empresa_id = 2")
sql("UPDATE conf__empresas SET usa_app_vendedor = 1, usa_vendedores = 1, usa_presentaciones = 1, usa_venta_presentaciones = 1, "
    "usa_reglas_precio_venta = 1 WHERE empresa_id = 2")
r = app('inicio')
check(not ok_resp(r) and 'vendedor' in (r.get('message') or ''), 'un usuario que no es vendedor no entra', str(r)[:200])
r = ajax('vendedores_ajax.php', dict(accion='alta', entidad_nombre='SIM43 Vendedor', usuario_id=USU_V, empresa_idx=2))
CTX['v'] = d(r).get('vendedor_entidad_id')
ajax('vendedores_ajax.php', dict(accion='asignar', vendedor_entidad_id=CTX['v'], clientes=json.dumps([CLI]), fecha=HOY, empresa_idx=2))
r2 = ajax('vendedores_ajax.php', dict(accion='alta', entidad_nombre='SIM43 Otro vendedor', empresa_idx=2))
ajax('vendedores_ajax.php', dict(accion='asignar', vendedor_entidad_id=d(r2).get('vendedor_entidad_id'), clientes=json.dumps([AJENO]), fecha=HOY, empresa_idx=2))
for f in (241, 250):   # agregar y confirmar_pedido de "Pedidos Realizados" para el perfil del vendedor (solo en la copia)
    sql(f"INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) "
        f"SELECT 2, {PERFIL}, {f}, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = {PERFIL} AND pagina_funcion_id = {f})")
sql(f"INSERT INTO gestion__ventas_descuentos_topes (empresa_id, empresa_perfil_id, tope_pct, f_desde, tabla_estado_registro_id) VALUES (2, {PERFIL}, 5, CURDATE(), 1)")
CTX['desc_cli'] = [(x['id'], x['d']) for x in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI}")]
sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id = {CLI}")
sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, es_venta_defecto, "
    f"es_compra_defecto, orden, codigo_barras, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM43 Caja x 12', 12, 1, 0, 0, 0, 99, '7799990004312', 1)")
CTX['caja'] = int(val(f"SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE presentacion_nombre = 'SIM43 Caja x 12'"))
sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
    f"VALUES (2, 1, {CTX['caja']}, {P2}, 200000, CURDATE(), 1)")
check(CTX['v'], 'vendedor ligado al usuario, con el cliente en su cartera', err(r))

caso('App del vendedor: cartera, catálogo y pedido')
if CTX.get('v'):
    caja = CTX['caja']
    r = app('inicio')
    check(ok_resp(r) and d(r).get('sucursales') and d(r).get('descuento_manual') and float(d(r).get('tope_descuento') or 0) == 5,
          'inicio: sucursales con pedidos y tope de descuento 5 %', str(r)[:300])
    r = app('clientes')
    ids = [c['entidad_id'] for c in (r.get('data') or [])] if isinstance(r, dict) else []
    check(ids == [CLI], 've solo su cartera', str(ids))
    r = app('cliente', dict(entidad_id=AJENO))
    check(not ok_resp(r) and 'cartera' in (r.get('message') or ''), 'un cliente ajeno se rechaza (ficha)', str(r)[:200])
    r = app('buscar', dict(entidad_id=AJENO, q='BURLETE'))
    check(not ok_resp(r), 'un cliente ajeno se rechaza (catálogo)', str(r)[:200])
    r = app('cliente', dict(entidad_id=CLI))
    check(ok_resp(r) and 'cuenta' in d(r) and isinstance(d(r).get('historial'), list) and d(r).get('borrador') is None, 'ficha del cliente: cuenta e historial, sin borrador', str(r)[:300])
    r = app('buscar', dict(entidad_id=CLI, q='7799990004312'))
    prods = d(r).get('productos') or []
    check(len(prods) == 1 and prods[0]['producto_id'] == P2 and prods[0]['presentacion_escaneada'] == caja, 'código de barras de la caja: producto y presentación', str(r)[:300])
    r = app('buscar', dict(entidad_id=CLI, q=val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id = {P1}")))
    check(ok_resp(r) and any(p['producto_id'] == P1 for p in d(r).get('productos') or []) and 'costo' not in json.dumps(r).lower(),
          'búsqueda por código, sin costos', str(r)[:200])
    items = [dict(producto_id=P1, cantidad=3), dict(producto_id=P2, presentacion_id=caja, cantidad=2)]
    r = app('valorizar', dict(entidad_id=CLI, items=json.dumps(items)), 'POST')
    check(ok_resp(r) and len(d(r).get('lineas') or []) == 2 and float((d(r).get('cabecera') or {}).get('total') or 0) > 0, 'valorizar: líneas y total', str(r)[:300])
    # Guardar (borrador real), retomar y editar
    r = app('guardar', dict(entidad_id=CLI, items=json.dumps(items[:1]), observaciones='SIM43 a medias'), 'POST')
    ped = d(r).get('venta_pedido_id')
    check(ok_resp(r) and ped and not d(r).get('confirmado'), 'guarda un borrador real', str(r)[:300])
    if ped:
        f = uno(f"SELECT tabla_estado_registro_id e, origen, vendedor_entidad_id v, comprobante_nro n FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}")
        check(f and int(f['e']) == 3 and f['origen'] == 'APP' and int(f['v']) == int(CTX['v']) and int(f['n']) == 0,
              'Borrador (3), origen APP, vendedor = el del usuario, sin número', str(f))
        r = app('clientes')
        check(any(c['entidad_id'] == CLI and c['borrador_id'] == ped for c in (r.get('data') or [])), 'la cartera marca el pedido en preparación', str(r)[:200])
        r = app('cliente', dict(entidad_id=CLI))
        b = d(r).get('borrador') or {}
        check(b.get('venta_pedido_id') == ped and len(b.get('items') or []) == 1 and b.get('observaciones') == 'SIM43 a medias', 'retoma el borrador', str(b)[:200])
        r = app('guardar', dict(entidad_id=CLI, items=json.dumps(items), observaciones='SIM43', pedido_id=ped), 'POST')
        check(ok_resp(r) and d(r).get('venta_pedido_id') == ped and int(val(f"SELECT COUNT(*) FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {ped} AND tabla_estado_registro_id = 1")) == 2,
              'edita el mismo borrador (2 líneas)', str(r)[:200])
        r = app('guardar', dict(entidad_id=CLI, items=json.dumps(items), pedido_id=999999), 'POST')
        check(not ok_resp(r), 'no edita un pedido que no es su borrador', str(r)[:200])
        # Descuento manual fuera del tope: queda guardado sin confirmar
        r = app('guardar', dict(entidad_id=CLI, items=json.dumps(items), descuento_manual_pct=10, pedido_id=ped, confirmar=1), 'POST')
        check(ok_resp(r) and not d(r).get('confirmado') and int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}")) == 3,
              'descuento manual del 10 % (tope 5 %): queda en Borrador', str(r)[:300])
        # Dentro del tope: confirma
        r = app('guardar', dict(entidad_id=CLI, items=json.dumps(items), descuento_manual_pct=4, pedido_id=ped, confirmar=1), 'POST')
        check(ok_resp(r) and d(r).get('confirmado') and d(r).get('comprobante_nro', 0) > 0, 'descuento manual del 4 %: confirma y numera', str(r)[:300])
        f = uno(f"SELECT tabla_estado_registro_id e, descuento_general_pct m FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}")
        check(f and int(f['e']) == 5 and float(f['m']) == 4, 'confirmado con el manual del 4 %', str(f))
        # Mismo pedido que el servicio del ERP con los mismos ítems y manual
        ext = core('pven_valorizar', 2, CLI, items, dict(sucursal_id=1, usuario_id=USU_V, descuento_manual_pct=4))
        tot = float(val(f"SELECT total FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}"))
        check(abs(tot - float((ext.get('cabecera') or {}).get('total') or -1)) < 0.01, 'el total es el del servicio del ERP', f"{tot} / {ext.get('cabecera')}")
    r = app('guardar', dict(entidad_id=AJENO, items=json.dumps(items)), 'POST')
    check(not ok_resp(r) and 'cartera' in (r.get('message') or ''), 'no graba un pedido a un cliente ajeno', str(r)[:200])
    r = ajax('api.php', dict(accion='guardar', entidad_id=CLI, items=json.dumps(items), empresa_idx=2, _sin_csrf=True), 'POST', usuario_id=USU_V, modulo='app_vendedor')
    check(not ok_resp(r), 'sin CSRF no graba', str(r)[:200])

caso('App del vendedor: entrega estimada (distribución)')
if CTX.get('v') and int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__distribucion_zonas'")) == 1:
    r = app('cliente', dict(entidad_id=CLI))
    check(ok_resp(r) and d(r).get('entrega') is None, 'sin distribución no se muestra entrega', str(d(r).get('entrega')))
    sql("UPDATE conf__empresas SET usa_distribucion = 1 WHERE empresa_id = 2")
    manana = (datetime.date.today() + datetime.timedelta(days=1))
    zona = core('vdis_zona_guardar', 2, dict(nombre='SIM43 Zona', codigo='SIM43', dias_reparto=[manana.isoweekday()]), 1, requires=('core/distribucion/distribucion_service.php',))
    core('vdis_zona_asignar', 2, [CLI], int(zona), None, HOY, 1, requires=('core/distribucion/distribucion_service.php',))
    r = app('cliente', dict(entidad_id=CLI))
    e = d(r).get('entrega') or {}
    check(e.get('fecha') == manana.isoformat() and e.get('zona') == 'SIM43 Zona', 'entrega estimada: el próximo día de reparto de su zona', str(e))
else:
    brecha('sin la estructura de distribución en la copia', '')

# ---------------------------------------------------------------------------------------------
caso('App del vendedor: limpieza')
if CTX.get('ind'):
    i = CTX['ind']
    sql(f"UPDATE conf__empresas SET usa_app_vendedor = {i['a']}, usa_vendedores = {i['b']}, usa_presentaciones = {i['c']}, usa_venta_presentaciones = {i['e']}, "
        f"usa_reglas_precio_venta = {i['f']}, usa_distribucion = {i['g']} WHERE empresa_id = 2")
    for cid, dd in CTX.get('desc_cli', []):
        sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {dd} WHERE entidad_condicion_cliente_id = {cid}")
    if CTX.get('caja'):
        sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id = {CTX['caja']}")
    check(True, 'indicadores y datos de la copia como estaban')
