from simlib import *

# Pedidos desde afuera del ERP (doc 28, paso 9-3): el mismo pedido cargado como lo hace la pantalla de pedidos
# (ventas_pedidos_ajax.php, página 55) y por core/pedidos_venta/externo.php (pven_crear_externo, como el carrito y la
# app) tiene que quedar igual columna por columna. Con los indicadores apagados y prendidos. Empresa 2, módulo 2.
# Necesita en la copia los scripts de la distribuidora (ya aplicados en el maestro) y 20261123_canales_carrito_app_01.
#   python _local/tests/simulacion/run.py 41_pedidos_externos.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261123_canales_carrito_app_01.sql

CLI = 313              # ABEJER WALTER - lista 1 (General), descuento propio 30 %
P1, P2 = 1529, 1878
PAGINA = 55            # Pedidos Realizados (módulo 2)
CTX = {}

CAB = ['empresa_id', 'sucursal_id', 'punto_venta_id', 'comprobante_tipo_id', 'entidad_id', 'entidad_sucursal_id', 'f_emision',
       'condicion_pago_id', 'moneda_id', 'tipo_cambio', 'direccion_entrega', 'subtotal', 'descuento_general_pct', 'descuentos',
       'impuestos', 'total', 'tabla_estado_registro_id']
DET = ['producto_id', 'cantidad', 'cantidad_entregada', 'precio_unitario', 'descuento_item_pct', 'descuento_general_pct',
       'descuento_general', 'descuento_item', 'precio_unitario_bruto', 'precio_unitario_neto', 'neto_gravado', 'iva_alicuota_id',
       'iva_porcentaje', 'iva_importe', 'no_gravado', 'exento', 'total_linea', 'tabla_estado_registro_id']


def cols_extra(tabla):
    """Columnas de presentación y de regla de precio que existan en la tabla."""
    return [r['c'] for r in sql(f"SELECT COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{tabla}' "
                                f"AND (COLUMN_NAME LIKE 'presentacion\\_%' OR COLUMN_NAME LIKE 'precio\\_regla\\_%' OR COLUMN_NAME LIKE 'descuento\\_auto%' "
                                f"OR COLUMN_NAME LIKE 'descuento\\_manual%') ORDER BY ORDINAL_POSITION")]


def foto_pedido(ped):
    cab = uno(f"SELECT {', '.join(CAB)} FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped)}")
    det_cols = DET + cols_extra('gestion__ventas_pedidos_detalles')
    det = sql(f"SELECT {', '.join(det_cols)} FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {int(ped)} "
              f"ORDER BY producto_id, presentacion_id IS NULL DESC" if 'presentacion_id' in det_cols else
              f"SELECT {', '.join(det_cols)} FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {int(ped)} ORDER BY producto_id")
    comp = uno(f"SELECT c.comprobante_tipo_id, c.comprobante_pv, c.entidad_id, c.importe_neto, c.importe_iva, c.importe_total, c.descuento_general, "
               f"c.tabla_estado_registro_id FROM gestion__comprobantes c JOIN gestion__ventas_pedidos vp ON vp.comprobante_id = c.comprobante_id "
               f"WHERE vp.venta_pedido_id = {int(ped)}")
    return {'cab': cab, 'det': det, 'comp': comp}


def diferencias(a, b):
    out = []
    for parte in ('cab', 'comp'):
        for k in (a[parte] or {}):
            if str(a[parte].get(k)) != str((b[parte] or {}).get(k)):
                out.append(f"{parte}.{k}: {a[parte].get(k)} != {(b[parte] or {}).get(k)}")
    if len(a['det']) != len(b['det']):
        out.append(f"líneas {len(a['det'])} != {len(b['det'])}")
    for i, (x, y) in enumerate(zip(a['det'], b['det'])):
        for k in x:
            if str(x[k]) != str(y.get(k)):
                out.append(f"línea {i + 1}.{k}: {x[k]} != {y.get(k)}")
    return out


def pv_tipo():
    return core('pven_pv_tipo', 2, PAGINA, 1)


def precio_lista(pid):
    return float(val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE lista_precio_id = 1 AND producto_id = {pid} "
                     f"AND empresa_id = 2 AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE()) "
                     f"ORDER BY f_desde DESC LIMIT 1"))


def linea_unidad(pid, cant, pct, iva=21.0):
    """ventas_pedidos.js: línea por unidad (sin redondear)."""
    p = precio_lista(pid)
    desc = p * (pct / 100)
    neto = p - desc
    ng = cant * neto
    iv = ng * (iva / 100)
    return dict(venta_pedido_detalle_id=0, producto_id=pid, cantidad=cant, cantidad_entregada=0, precio_unitario=p,
                descuento_general_pct=pct, descuento_general=desc, precio_unitario_neto=neto, no_gravado=0, exento=0,
                iva_alicuota_id=1, iva_porcentaje=iva, neto_gravado=ng, iva_importe=iv, total_linea=ng + iv)


def linea_pres(pid, pres, nombre, factor, cajas, precio_caja, pct, iva=21.0):
    """ventas-presentaciones.js: nuevaLinea + recalcular."""
    unit = round(precio_caja / factor, 6)
    desc = round(unit * pct / 100, 6)
    ng = round(cajas * precio_caja * (1 - pct / 100), 2)
    iv = round(ng * iva / 100, 2)
    return dict(venta_pedido_detalle_id=0, producto_id=pid, cantidad_entregada=0, no_gravado=0, exento=0, iva_alicuota_id=1,
                iva_porcentaje=iva, presentacion_id=pres, presentacion_nombre=nombre, presentacion_factor=factor,
                presentacion_cantidad=cajas, presentacion_precio=precio_caja, descuento_general_pct=pct,
                cantidad=round(cajas * factor, 4), precio_unitario=unit, descuento_general=desc,
                precio_unitario_neto=round(unit - desc, 6), neto_gravado=ng, iva_importe=iv, total_linea=round(ng + iv, 2))


def alta_erp(dets, pct_cab, confirmar=True):
    """Alta como la pantalla: totales como actualizarTotales() de ventas_pedidos.js."""
    pv, tipo = pv_tipo()
    sn = sum(d['neto_gravado'] for d in dets)
    si = sum(d['iva_importe'] for d in dets)
    sd = sum(d['descuento_general'] * d['cantidad'] for d in dets)
    r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=PAGINA, comprobante_tipo_id=tipo, sucursal_id=1,
             punto_venta_id=pv, entidad_id=CLI, entidad_sucursal_id=0, f_emision=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id=val(f"SELECT condicion_pago_id FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI} "
                                   f"AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1") or 0,
             descuento_general_pct=pct_cab, detalles=dets, subtotal=f"{sn:.2f}", descuentos=f"{sd:.2f}", impuestos=f"{si:.2f}",
             total=f"{sn + si:.2f}"))
    ped = (r.get('venta_pedido_id') if isinstance(r, dict) else None)
    if ped and confirmar:
        t = ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido',
                 empresa_idx=2, pagina_idx=PAGINA))
        if not ok_resp(t):
            return None, err(t)
    return ped, err(r)


def alta_ext(items, confirmar=True, **extra):
    return core('pven_crear_externo', 2, dict(dict(entidad_id=CLI, sucursal_id=1, items=items, origen='CARRITO', usuario_id=1,
                                                    confirmar=confirmar), **extra))


def comparar(nombre, dets_erp, pct_cab, items):
    ped, e = alta_erp(dets_erp, pct_cab)
    ext = alta_ext(items)
    pe = ext.get('venta_pedido_id') if isinstance(ext, dict) else None
    check(ped and pe and ext.get('confirmado'), f'{nombre}: alta y confirmación por los dos caminos', f'{e} / {ext}')
    if ped and pe:
        d = diferencias(foto_pedido(ped), foto_pedido(pe))
        check(not d, f'{nombre}: mismo pedido columna por columna (cabecera, líneas y comprobante)', '; '.join(d[:6]))
    return ped, pe


# ---------------------------------------------------------------------------------------------
caso('Pedidos externos: con los indicadores apagados')
CTX['ind'] = uno("SELECT usa_presentaciones a, usa_venta_presentaciones b, usa_reglas_precio_venta c, usa_vendedores d, usa_canales_venta e "
                 "FROM conf__empresas WHERE empresa_id = 2")
pv, tipo = pv_tipo() or (0, 0)
check(pv > 0 and tipo > 0, 'la sucursal 1 tiene punto de venta y tipo de pedido', f'{pv}/{tipo}')
pct = float(val(f"SELECT cliente_descuento_general FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI} "
                f"AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1") or 0)
ped, pe = comparar('apagado', [linea_unidad(P1, 3, pct), linea_unidad(P2, 7, pct)], pct,
                   [dict(producto_id=P1, cantidad=3), dict(producto_id=P2, cantidad=7)])
if pe:
    check(int(val(f"SELECT comprobante_nro FROM gestion__ventas_pedidos WHERE venta_pedido_id = {pe}") or 0) > 0, 'apagado: el pedido externo queda numerado')
r = alta_ext([dict(producto_id=P1, cantidad=2, precio_unitario=1, descuento_general_pct=99, total_linea=1)])
pe2 = r.get('venta_pedido_id') if isinstance(r, dict) else None
check(pe2 and abs(float(val(f"SELECT precio_unitario FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {pe2}")) - precio_lista(P1)) < 0.01
      and float(val(f"SELECT descuento_general_pct FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {pe2}")) == pct,
      'precio, descuento y total que mande el navegador se ignoran', str(r)[:200])
sin_precio = val("SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id = 2 AND p.tabla_estado_registro_id = 1 AND NOT EXISTS "
                 "(SELECT 1 FROM gestion__listas_precios_productos lp WHERE lp.producto_id = p.producto_id AND lp.lista_precio_id = 1 "
                 "AND lp.tabla_estado_registro_id = 1 AND lp.precio_final > 0) LIMIT 1")
r = alta_ext([dict(producto_id=int(sin_precio), cantidad=1)])
check(isinstance(r, dict) and not r.get('success') and 'no se vende' in (r.get('message') or ''), 'un producto sin precio en la lista no se vende', str(r)[:200])
r = alta_ext([dict(producto_id=P1, cantidad=0)])
check(isinstance(r, dict) and not r.get('success'), 'cantidad cero se rechaza', str(r)[:150])
r = alta_ext([dict(producto_id=P1, cantidad=1)], origen='OTRO')
check(isinstance(r, dict) and not r.get('success') and 'Origen' in (r.get('message') or ''), 'origen desconocido se rechaza', str(r)[:150])
otro = val("SELECT e.entidad_id FROM gestion__entidades e WHERE e.empresa_id <> 2 AND e.tabla_estado_registro_id = 1 LIMIT 1")
r = core('pven_crear_externo', 2, dict(entidad_id=int(otro or 0), sucursal_id=1, items=[dict(producto_id=P1, cantidad=1)], origen='CARRITO', usuario_id=1))
check(isinstance(r, dict) and not r.get('success') and 'cliente' in (r.get('message') or '').lower(), 'un cliente de otra empresa se rechaza', str(r)[:150])
o = core('pven_ofertas', 2, CLI, [P1, P2, int(sin_precio)], 1)
prods = (o or {}).get('productos') or {}
check(str(P1) in prods and str(P2) in prods and str(sin_precio) not in prods and abs(prods[str(P1)]['ofertas'][0]['precio_final']
      - round(precio_lista(P1) * (1 - pct / 100), 2)) < 0.01, 'ofertas: con precio y el descuento del cliente; sin precio no se ofrece', str(o)[:300])
check('costo' not in json.dumps(o).lower(), 'ofertas: sin costos')

# ---------------------------------------------------------------------------------------------
caso('Pedidos externos: con presentaciones, escalas y vendedores')
pres_ok = len(cols_extra('gestion__ventas_pedidos_detalles')) >= 5
if not pres_ok:
    brecha('faltan las columnas de presentación / reglas en la copia', '')
else:
    sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_venta_presentaciones = 1, usa_reglas_precio_venta = 1, usa_vendedores = 1 WHERE empresa_id = 2")
    CTX['desc_cli'] = [(r['id'], r['d']) for r in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d "
                                                        f"FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI}")]
    sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id = {CLI}")
    sql(f"INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_venta, aplica_compra, "
        f"es_venta_defecto, es_compra_defecto, orden, tabla_estado_registro_id) VALUES (2, {P2}, 'SIM41 Caja x 12', 12, 1, 0, 0, 0, 99, 1)")
    caja = int(val(f"SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE producto_id = {P2} AND presentacion_nombre = 'SIM41 Caja x 12'"))
    CTX['caja'] = caja
    sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, "
        f"f_desde, tabla_estado_registro_id) VALUES (2, 1, {caja}, {P2}, 200000, CURDATE(), 1)")
    r = ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', tipo='ESCALA', nombre='SIM41 Escala P1', producto_id=P1, f_desde=HOY,
             tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}, {'cantidad_desde': 120, 'descuento_pct': 8}]), empresa_idx=2))
    CTX['escala'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    check(CTX['escala'], 'escala de P1 dada de alta', err(r))
    # % por línea como la pantalla: acción resolver_descuentos del ERP
    rd = ajax('ventas_pedidos_ajax.php', dict(accion='resolver_descuentos', empresa_idx=2, pagina_idx=PAGINA, entidad_id=CLI, sucursal_id=1,
              f_emision=HOY, descuento_manual_pct=0, lineas=json.dumps([{'producto_id': P1, 'presentacion_id': 0, 'cantidad': 30},
                                                                          {'producto_id': P2, 'presentacion_id': caja, 'cantidad': 24}])))
    lin = ((rd.get('data') or {}).get('lineas') or {}) if isinstance(rd, dict) else {}
    lin = lin if isinstance(lin, list) else [lin.get('0') or {}, lin.get('1') or {}]
    p1 = float((lin[0] if len(lin) > 0 else {}).get('total_pct') or 0)
    p2 = float((lin[1] if len(lin) > 1 else {}).get('total_pct') or 0)
    check(p1 == 5, 'la pantalla resuelve la escala del 5 % para 30 unidades', str(rd)[:200])
    ped, pe = comparar('prendido', [linea_unidad(P1, 30, p1), linea_pres(P2, caja, 'SIM41 Caja x 12', 12, 2, 200000, p2)], 0,
                       [dict(producto_id=P1, cantidad=30), dict(producto_id=P2, presentacion_id=caja, cantidad=2)])
    if pe:
        o = uno(f"SELECT origen, vendedor_entidad_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {pe}")
        oe = uno(f"SELECT origen, vendedor_entidad_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped}") if ped else {}
        check(o and o['origen'] == 'CARRITO' and str(o['vendedor_entidad_id']) == str((oe or {}).get('vendedor_entidad_id')),
              'origen CARRITO y el vendedor de la cartera (el mismo que el ERP)', f'{o} / {oe}')
    r = alta_ext([dict(producto_id=P2, presentacion_id=caja, cantidad=1.5)])
    check(isinstance(r, dict) and not r.get('success') and 'enteras' in (r.get('message') or ''), 'cajas no enteras se rechazan (vpre)', str(r)[:200])
    sql(f"UPDATE gestion__productos_presentaciones SET venta_minimo = 3 WHERE presentacion_id = {caja}")
    r = alta_ext([dict(producto_id=P2, presentacion_id=caja, cantidad=2)])
    check(isinstance(r, dict) and not r.get('success') and 'mínim' in (r.get('message') or '').lower(), 'debajo del mínimo se rechaza', str(r)[:200])
    sql(f"UPDATE gestion__productos_presentaciones SET venta_minimo = NULL WHERE presentacion_id = {caja}")
    o = core('pven_ofertas', 2, CLI, [P1, P2], 1)
    ofs = {(of['presentacion_id'] or 0): of for of in (((o or {}).get('productos') or {}).get(str(P2)) or {}).get('ofertas', [])}
    check(caja in ofs and ofs[caja]['precio'] == 200000 and ofs[caja]['disponible'] is not None, 'ofertas: la caja con su precio y la disponibilidad', str(ofs)[:300])
    of1 = ((((o or {}).get('productos') or {}).get(str(P1)) or {}).get('ofertas') or [{}])[0]
    check(of1.get('etiqueta') is None, 'ofertas: 1 unidad no llega a la escala (sin etiqueta)', str(of1)[:200])
    v = core('pven_valorizar', 2, CLI, [dict(producto_id=P1, cantidad=150)], dict(sucursal_id=1))
    check(isinstance(v, dict) and (v.get('lineas') or [{}])[0].get('descuento_pct') == 8 and 'Escala' in ((v.get('lineas') or [{}])[0].get('etiqueta') or ''),
          'valorizar: 150 unidades llegan al tramo del 8 % con su etiqueta', str(v)[:300])
    r = alta_ext([dict(producto_id=P1, cantidad=30)], descuento_manual_pct=10)
    pe3 = r.get('venta_pedido_id') if isinstance(r, dict) else None
    check(pe3 and float(val(f"SELECT descuento_general_pct FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {pe3}")) == 5,
          'el carrito no acepta descuento manual (solo la app)', str(r)[:200])

# ---------------------------------------------------------------------------------------------
caso('Pedidos externos: limpieza')
if CTX.get('ind'):
    i = CTX['ind']
    sql(f"UPDATE conf__empresas SET usa_presentaciones = {i['a']}, usa_venta_presentaciones = {i['b']}, usa_reglas_precio_venta = {i['c']}, "
        f"usa_vendedores = {i['d']}, usa_canales_venta = {i['e']} WHERE empresa_id = 2")
    for cid, d in CTX.get('desc_cli', []):
        sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {d} WHERE entidad_condicion_cliente_id = {cid}")
    if CTX.get('caja'):
        sql(f"UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id = {CTX['caja']}")
    if CTX.get('escala'):
        sql(f"UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = 2 WHERE regla_venta_id = {CTX['escala']}")
    check(True, 'indicadores, descuentos y datos de la copia como estaban')
