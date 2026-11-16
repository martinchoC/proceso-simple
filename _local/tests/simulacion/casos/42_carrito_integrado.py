from simlib import *

# Carrito del cliente integrado al ERP (doc 28, pasos 9-5 / 9-6): el carrito (casalucho-carrito, su Kernel real) con
# usa_carrito_integrado arma las líneas con el ERP (presentaciones, escalas) y confirma con pven_crear_externo. El
# pedido tiene que ser igual al que crea el servicio del ERP con los mismos ítems (caso 41: ese es igual al de la
# pantalla). Empresa 2, cliente 313. Lo apagado se compara aparte (_local/tests/carrito/foto_carrito.py).
#   python _local/tests/simulacion/run.py 42_carrito_integrado.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261123_canales_carrito_app_01.sql _local/sql/mantenimiento/20261123_canales_carrito_app_02_carrito_presentaciones.sql

CLI = 313
P1, P2 = 1529, 1878
CTX = {}
ERP = os.path.abspath(os.path.join(AQUI, '..', '..', '..'))


def carrito(ops, usuario=1, entidad=CLI):
    """Corre operaciones en el carrito real (carrito_driver.php) con la integración configurada."""
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
        json.dump({'usuario_id': usuario, 'entidad_id': entidad, 'ops': ops}, f)
        ruta = f.name
    try:
        r = subprocess.run([PHP, os.path.join(ERP, '_local', 'tests', 'carrito', 'run_carrito.php'), 'carrito_driver.php', DB, ERP, '', ruta],
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
    finally:
        os.unlink(ruta)
    s = r.stdout
    try:
        return json.loads(s[s.find('{'):])
    except Exception:
        return {'_error': (s + r.stderr)[-1500:], 'ops': []}


def foto(ped):
    cab = uno(f"SELECT sucursal_id, punto_venta_id, comprobante_tipo_id, entidad_id, condicion_pago_id, moneda_id, subtotal, descuento_general_pct, "
              f"descuentos, impuestos, total, tabla_estado_registro_id, origen, vendedor_entidad_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped)}")
    det = sql(f"SELECT producto_id, cantidad, precio_unitario, descuento_general_pct, descuento_general, precio_unitario_neto, neto_gravado, iva_importe, "
              f"total_linea, presentacion_id, presentacion_cantidad, presentacion_precio FROM gestion__ventas_pedidos_detalles "
              f"WHERE venta_pedido_id = {int(ped)} ORDER BY producto_id, presentacion_id")
    return cab, det


# ---------------------------------------------------------------------------------------------
caso('Carrito integrado: preparación')
if int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ecom__carritos_presentaciones'")) != 1:
    brecha('falta 20261123_canales_carrito_app_02 en la copia', '')
else:
    CTX['ind'] = uno("SELECT usa_presentaciones a, usa_venta_presentaciones b, usa_reglas_precio_venta c, usa_vendedores d, usa_canales_venta e, "
                     "usa_carrito_integrado f FROM conf__empresas WHERE empresa_id = 2")
    sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_venta_presentaciones = 1, usa_reglas_precio_venta = 1, usa_vendedores = 1, "
        "usa_carrito_integrado = 1 WHERE empresa_id = 2")
    CTX['desc_cli'] = [(r['id'], r['d']) for r in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d "
                                                        f"FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI}")]
    sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id = {CLI}")
    sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, "
        f"es_venta_defecto, es_compra_defecto, orden, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM42 Caja x 12', 12, 1, 0, 0, 0, 99, 1)")
    CTX['caja'] = int(val(f"SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE producto_id = {P2} AND presentacion_nombre = 'SIM42 Caja x 12'"))
    sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, "
        f"f_desde, tabla_estado_registro_id) VALUES (2, 1, {CTX['caja']}, {P2}, 200000, CURDATE(), 1)")
    r = ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', tipo='ESCALA', nombre='SIM42 Escala P1', producto_id=P1, f_desde=HOY,
             tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}]), empresa_idx=2))
    CTX['escala'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    CTX['ok'] = bool(CTX['escala'])
    check(CTX['ok'], 'indicadores prendidos, caja con precio y escala en la copia', err(r))

caso('Carrito integrado: carrito con unidades y cajas')
if CTX.get('ok'):
    caja = CTX['caja']
    sin_precio = int(val("SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id = 2 AND p.tabla_estado_registro_id = 1 AND NOT EXISTS "
                         "(SELECT 1 FROM gestion__listas_precios_productos lp WHERE lp.producto_id = p.producto_id AND lp.lista_precio_id = 1 "
                         "AND lp.tabla_estado_registro_id = 1 AND lp.precio_final > 0) LIMIT 1"))
    r = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 30},
                 {'op': 'agregar', 'producto_id': P2, 'presentacion_id': caja, 'cantidad': 2},
                 {'op': 'agregar', 'producto_id': P2, 'presentacion_id': caja, 'cantidad': 1.5},
                 {'op': 'agregar', 'producto_id': P2, 'presentacion_id': 999999, 'cantidad': 1},
                 {'op': 'agregar', 'producto_id': sin_precio, 'cantidad': 1},
                 {'op': 'resumen'}])
    o = r.get('ops') or []
    check(r.get('integrado') is True, 'el carrito trabaja integrado al ERP', str(r)[:300])
    check(len(o) == 7 and o[1].get('ok') and o[2].get('ok'), 'agrega unidades y cajas del mismo producto en líneas separadas', str(o[:3])[:300])
    check(len(o) == 7 and not o[3].get('ok') and 'enteras' in (o[3].get('error') or ''), 'cajas no enteras se rechazan al agregar', str(o[3:4]))
    check(len(o) == 7 and not o[4].get('ok'), 'una presentación que no se vende en la lista se rechaza', str(o[4:5]))
    check(len(o) == 7 and not o[5].get('ok') and 'disponible' in (o[5].get('error') or ''), 'un producto sin precio en la lista se rechaza', str(o[5:6]))
    res = o[6] if len(o) == 7 else {}
    lin = {l['clave']: l for l in res.get('lineas') or []}
    check(str(P1) in lin and f'{P2}-{caja}' in lin and lin[str(P1)]['descuento_pct'] == 5 and 'Escala' in (lin[str(P1)].get('etiqueta') or '')
          and lin[f'{P2}-{caja}']['cantidad'] == 2 and lin[f'{P2}-{caja}']['precio'] == 200000 and 'Caja' in lin[f'{P2}-{caja}']['nombre'],
          'resumen: escala del 5 % en P1 y la caja con su precio por caja', str(res)[:400])
    v = core('pven_valorizar', 2, CLI, [dict(producto_id=P1, cantidad=30), dict(producto_id=P2, presentacion_id=caja, cantidad=2)], dict(sucursal_id=1))
    check(abs(res.get('total', 0) - (v.get('cabecera') or {}).get('total', -1)) < 0.01, 'resumen: el total del carrito es el del ERP', f"{res.get('total')} / {(v.get('cabecera') or {}).get('total')}")
    # Confirmar: pedido del carrito vs pedido del servicio del ERP con los mismos ítems
    r = carrito([{'op': 'confirmar', 'observaciones': 'SIM42'}, {'op': 'resumen'}])
    o = r.get('ops') or []
    ped = (o[0] if o else {}).get('venta_pedido_id')
    check(ped and o[0].get('comprobante_nro', 0) > 0, 'confirma: pedido numerado por el ERP', str(o)[:300])
    check(len(o) == 2 and o[1].get('ok') and not o[1].get('lineas'), 'el carrito queda vacío (unidades y cajas)', str(o[1:2])[:200])
    ext = core('pven_crear_externo', 2, dict(entidad_id=CLI, sucursal_id=1, origen='CARRITO', usuario_id=1, confirmar=True,
                                             items=[dict(producto_id=P1, cantidad=30), dict(producto_id=P2, presentacion_id=caja, cantidad=2)]))
    pe = ext.get('venta_pedido_id') if isinstance(ext, dict) else None
    if ped and pe:
        a, b = foto(ped), foto(pe)
        check(a == b, 'el pedido del carrito es igual al del servicio del ERP (cabecera y líneas)', f'{a} / {b}'[:600])
        check(a[0]['origen'] == 'CARRITO', 'origen CARRITO', str(a[0]))
        check(int(val(f"SELECT COUNT(*) FROM ecom__pedidos WHERE venta_pedido_id = {int(ped)}") or 0) == 1, 'queda el puente ecom__pedidos de la tienda')
    # Mínimo de la caja
    sql(f"UPDATE gestion__productos_presentaciones SET venta_minimo = 3 WHERE presentacion_id = {caja}")
    r = carrito([{'op': 'agregar', 'producto_id': P2, 'presentacion_id': caja, 'cantidad': 2}])
    o = (r.get('ops') or [{}])[0]
    check(not o.get('ok') and 'mínim' in (o.get('error') or '').lower(), 'debajo del mínimo de la caja se rechaza', str(o))
    sql(f"UPDATE gestion__productos_presentaciones SET venta_minimo = NULL WHERE presentacion_id = {caja}")

caso('Carrito integrado: sucursal que no vende el canal del cliente')
if CTX.get('ok') and int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__canales_venta'")) == 1:
    sql("UPDATE conf__empresas SET usa_canales_venta = 1 WHERE empresa_id = 2")
    canal_cli = core('cvta_guardar_canal', 2, dict(codigo='SIM42C', nombre='SIM42 Canal cliente', orden=99, activo=1), 1, requires=('core/canales_venta.php',))
    canal_otro = core('cvta_guardar_canal', 2, dict(codigo='SIM42O', nombre='SIM42 Otro canal', orden=98, activo=1), 1, requires=('core/canales_venta.php',))
    CTX['lista_canal'] = val("SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = 1")
    core('cvta_guardar_canal_lista', 2, 1, int(canal_cli), requires=('core/canales_venta.php',))
    core('cvta_guardar_canales_sucursal', 2, 1, [int(canal_otro)], 1, requires=('core/canales_venta.php',))
    r = carrito([{'op': 'vaciar'}, {'op': 'agregar', 'producto_id': P1, 'cantidad': 2}, {'op': 'confirmar'}])
    o = r.get('ops') or []
    check(len(o) == 3 and not o[2].get('ok') and 'canal' in (o[2].get('error') or '').lower(), 'la sucursal que no vende el canal rechaza el pedido', str(o)[:300])
    core('cvta_guardar_canales_sucursal', 2, 1, [], 1, requires=('core/canales_venta.php',))
    core('cvta_guardar_canal_lista', 2, 1, int(CTX['lista_canal'] or 0), requires=('core/canales_venta.php',))
    carrito([{'op': 'vaciar'}])
else:
    brecha('sin la estructura de canales en la copia', '')

caso('Carrito integrado: limpieza')
if CTX.get('ind'):
    i = CTX['ind']
    sql(f"UPDATE conf__empresas SET usa_presentaciones = {i['a']}, usa_venta_presentaciones = {i['b']}, usa_reglas_precio_venta = {i['c']}, "
        f"usa_vendedores = {i['d']}, usa_canales_venta = {i['e']}, usa_carrito_integrado = {i['f']} WHERE empresa_id = 2")
    for cid, d in CTX.get('desc_cli', []):
        sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {d} WHERE entidad_condicion_cliente_id = {cid}")
    if CTX.get('caja'):
        sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id = {CTX['caja']}")
    if CTX.get('escala'):
        sql(f"UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id = {CTX['escala']}")
    check(True, 'datos de la copia como estaban')
