from simlib import *

# Venta por presentación en las líneas de venta (doc 20, paso 2): pedido con cajas y unidades (empresa 2, módulo 2).
# Necesita en la copia: 20261112_productos_presentaciones_01, 20261114_venta_presentaciones_01 y
# 20261115_venta_presentaciones_02_lineas (--scripts). Prende los indicadores solo en la copia.
#   python _local/tests/simulacion/run.py 33_ventas_presentaciones.py --scripts <arca_off.sql> <los tres scripts>

CLI = 313             # ABEJER WALTER - lista 1 (General)
P1, P2 = 1529, 1878   # P2 se vende también en caja x 12
LISTA = 1
CTX = {}


def columnas_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME = 'gestion__ventas_pedidos_detalles' AND COLUMN_NAME LIKE 'presentacion\\_%'")) == 5


def linea_caja(caja, cajas, precio_caja, iva=21.0):
    """Línea por presentación tal como la arma la pantalla (assets/js/ventas-presentaciones.js)."""
    unit = round(precio_caja / 12, 6)
    neto = round(cajas * precio_caja, 2)
    iva_imp = round(neto * iva / 100, 2)
    return linea(P2, cajas * 12, unit, iva, neto_gravado=neto, iva_importe=iva_imp, total_linea=round(neto + iva_imp, 2),
                 presentacion_id=caja, presentacion_nombre='Caja x 12', presentacion_factor=12,
                 presentacion_cantidad=cajas, presentacion_precio=precio_caja)


def alta_pedido(dets):
    r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=1,
             punto_venta_id=1, entidad_id=CLI, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, detalles=dets, **totales(dets)))
    return r, ((r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None)


def linea_db(ped, producto, con_presentacion):
    cond = 'IS NOT NULL' if con_presentacion else 'IS NULL'
    return uno(f"SELECT venta_pedido_detalle_id id, cantidad, precio_unitario, neto_gravado, total_linea, presentacion_id, "
               f"presentacion_cantidad, presentacion_precio FROM gestion__ventas_pedidos_detalles "
               f"WHERE venta_pedido_id={ped} AND producto_id={producto} AND presentacion_id {cond}")


# ---------------------------------------------------------------------------------------------
caso('Presentaciones en ventas: preparación (indicadores y caja con precio, solo en la copia)')
if not columnas_ok():
    brecha('faltan los scripts de venta por presentación en la copia', 'correr con --scripts 20261112 / 20261114 / 20261115')
else:
    sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_venta_presentaciones = 1 WHERE empresa_id = 2")
    sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, "
        f"venta_minimo, orden) VALUES (2, {P2}, 'Caja x 12', 12, 1, 2, 1)")
    caja = int(val(f"SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE producto_id = {P2}"))
    sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, "
        f"f_desde, tabla_estado_registro_id) VALUES (2, {LISTA}, {caja}, {P2}, 550, CURDATE(), 1)")
    CTX['caja'] = caja
    check(caja > 0, f'caja x 12 de {P2} con precio 550 en la lista {LISTA}')

caja = CTX.get('caja')

# ---------------------------------------------------------------------------------------------
caso('Presentaciones en ventas: buscador del pedido')
if caja:
    cod = val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id = {P2}")
    r = ajax('ventas_pedidos_ajax.php', dict(accion='buscar_productos_cliente', entidad_id=CLI, q=cod, sucursal_id=1, empresa_idx=2), 'GET')
    prods = r.get('productos', r) if isinstance(r, dict) else r
    fila = next((p for p in (prods or []) if int(p.get('producto_id', 0)) == P2), None)
    ofertas = (fila or {}).get('ofertas') or []
    check(any(int(o.get('presentacion_id') or 0) == caja and num(o.get('precio')) == 550 for o in ofertas),
          'el buscador ofrece la caja a 550', str(ofertas)[:300])
    check(all('disponible' in o for o in ofertas), 'con la disponibilidad de la sucursal (solo informativa)', str(ofertas)[:200])

# ---------------------------------------------------------------------------------------------
caso('Presentaciones en ventas: pedido con cajas y unidades')
if caja:
    dets = [linea(P1, 3, 1500), linea_caja(caja, 2, 550)]
    r, ped = alta_pedido(dets)
    check(ok_resp(r) and ped, 'alta de pedido con 2 cajas y 3 unidades', err(r))
    if ped:
        CTX['pedido'] = ped
        lc = linea_db(ped, P2, True)
        check(lc and num(lc['cantidad']) == 24 and num(lc['presentacion_cantidad']) == 2 and num(lc['neto_gravado']) == 1100,
              'la caja queda en unidad base: 24 u, 2 cajas, neto 1100', str(lc))
        check(lc and abs(num(lc['precio_unitario']) - 45.833333) < 1e-6 and num(lc['presentacion_precio']) == 550,
              'precio unitario 45,833333 y precio de la caja 550', str(lc))
        lu = linea_db(ped, P1, False)
        check(lu and num(lu['cantidad']) == 3 and num(lu['neto_gravado']) == 4500, 'la línea por unidad queda como siempre', str(lu))

        r = ajax('ventas_pedidos_ajax.php', dict(accion='obtener', venta_pedido_id=ped, empresa_idx=2, pagina_idx=91), 'GET')
        dl = [d for d in (r.get('detalles') or []) if int(d.get('producto_id', 0)) == P2] if isinstance(r, dict) else []
        check(dl and dl[0].get('presentacion_nombre') == 'Caja x 12' and num(dl[0].get('presentacion_cantidad')) == 2,
              'obtener devuelve la presentación de la línea', str(dl)[:300])

        # Editar: 3 cajas (la pantalla manda la cantidad en cajas; el servidor recalcula la base)
        d2 = linea_caja(caja, 3, 550)
        d2['venta_pedido_detalle_id'] = int(lc['id'])
        du = linea(P1, 3, 1500, venta_pedido_detalle_id=int(lu['id']))
        r = ajax('ventas_pedidos_ajax.php', dict(accion='editar', venta_pedido_id=ped, empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1,
                 sucursal_id=1, punto_venta_id=1, entidad_id=CLI, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
                 descuento_general_pct=0, detalles=[du, d2], **totales([du, d2])))
        lc2 = linea_db(ped, P2, True)
        check(ok_resp(r) and lc2 and int(lc2['id']) == int(lc['id']) and num(lc2['cantidad']) == 36 and num(lc2['presentacion_cantidad']) == 3,
              'editar a 3 cajas: misma línea, 36 u', err(r) or str(lc2))

    # Validaciones del servidor
    r, ped_min = alta_pedido([linea_caja(caja, 1, 550)])
    check(not ok_resp(r) and 'mínima' in err(r), 'mínimo 2 cajas: 1 caja se rechaza', err(r))
    otra = dict(linea_caja(caja, 2, 550), producto_id=P1)
    r, _ = alta_pedido([otra])
    check(not ok_resp(r), 'la caja de otro producto se rechaza', err(r))

# ---------------------------------------------------------------------------------------------
caso('Presentaciones en ventas: Gestión de pedidos (cambio de cantidad)')
ped = CTX.get('pedido')
if ped and caja:
    lc = linea_db(ped, P2, True)
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='actualizar_cantidad_detalle', venta_pedido_id=ped,
             venta_pedido_detalle_id=int(lc['id']), cantidad=30, empresa_idx=2, pagina_idx=91))
    check(isinstance(r, dict) and not r.get('success') and num(linea_db(ped, P2, True)['cantidad']) == 36,
          '30 u (2,5 cajas) se rechaza', str(r)[:200])
    r = ajax('ventas_pedidos_gestion_ajax.php', dict(accion='actualizar_cantidad_detalle', venta_pedido_id=ped,
             venta_pedido_detalle_id=int(lc['id']), cantidad=48, empresa_idx=2, pagina_idx=91))
    lc3 = linea_db(ped, P2, True)
    check(isinstance(r, dict) and r.get('success') and num(lc3['cantidad']) == 48 and num(lc3['presentacion_cantidad']) == 4,
          '48 u: 4 cajas', str(r)[:200] + str(lc3))

# ---------------------------------------------------------------------------------------------
caso('Presentaciones en ventas: con el indicador apagado, la línea se graba como siempre')
if caja:
    sql("UPDATE conf__empresas SET usa_venta_presentaciones = 0 WHERE empresa_id = 2")
    d = linea(P2, 5, 100, presentacion_id=caja, presentacion_cantidad=9, presentacion_precio=999)
    r, ped_off = alta_pedido([d])
    l = uno(f"SELECT cantidad, precio_unitario, presentacion_id FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id={ped_off or 0}")
    check(ok_resp(r) and l and num(l['cantidad']) == 5 and num(l['precio_unitario']) == 100 and l['presentacion_id'] in (None, 'NULL'),
          'apagado: 5 u a 100, sin presentación (lo de presentación se ignora)', err(r) or str(l))
    sql("UPDATE conf__empresas SET usa_venta_presentaciones = 1 WHERE empresa_id = 2")
