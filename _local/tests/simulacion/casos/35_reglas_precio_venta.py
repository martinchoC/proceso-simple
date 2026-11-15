from simlib import *

# Escalas por cantidad, promociones y descuento del vendedor (doc 22, paso 4; empresa 2, módulo 2).
# Necesita en la copia: 20261116_canales_venta_01 y 20261117_reglas_precio_venta_01 / _02_pagina (--scripts).
# Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 35_reglas_precio_venta.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261116_canales_venta_01.sql _local/sql/mantenimiento/20261117_reglas_precio_venta_01.sql \
#       _local/sql/mantenimiento/20261117_reglas_precio_venta_02_pagina.sql

CLI = 313              # ABEJER WALTER - lista 1 (General)
P1, P2 = 1529, 1878
LISTA = 1
VEND, PERFIL_VEND = 8, 12   # usuario con el perfil "Pedidos" de la empresa 2
CTX = {}


def estructura_ok():
    return int(val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
                   "AND TABLE_NAME IN ('gestion__precios_reglas_venta', 'gestion__ventas_descuentos_topes')")) == 2 \
        and int(val("SELECT COUNT(*) FROM conf__paginas WHERE pagina_id = 146")) == 1


def pg(params, method='POST', usuario_id=1):
    return ajax('precios_reglas_venta_ajax.php', dict(params, empresa_idx=2), method, usuario_id=usuario_id)


# ---------------------------------------------------------------------------------------------
caso('Reglas de precio: preparación (indicador, solo en la copia)')
if not estructura_ok():
    brecha('faltan los scripts de reglas de precio en la copia', 'correr con --scripts 20261116 / 20261117_01 / 20261117_02')
else:
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 1 WHERE empresa_id = 2")
    # El cliente de prueba tiene descuento propio (20 %), que le ganaría a escalas y promos: en la copia, 0 durante el caso
    CTX['desc_cli'] = [(r['id'], r['d']) for r in sql(f"SELECT entidad_condicion_cliente_id id, cliente_descuento_general d "
                                                        f"FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {CLI}")]
    sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = 0 WHERE entidad_id = {CLI}")
    CTX['ok'] = True
    check(int(val("SELECT usa_reglas_precio_venta FROM conf__empresas WHERE empresa_id = 2")) == 1, 'indicador prendido en la copia')

caso('Reglas de precio: página Escalas y promociones')
if CTX.get('ok'):
    r = pg(dict(accion='agregar', tipo='ESCALA', nombre='Escala P1', producto_id=P1, f_desde=HOY,
                tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}, {'cantidad_desde': 120, 'descuento_pct': 8}])))
    check(ok_resp(r), 'alta de una escala con dos tramos', err(r))
    CTX['escala'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Promo P1', producto_id=P1, descuento_pct=7, f_desde=HOY))
    check(ok_resp(r), 'alta de una promoción', err(r))
    CTX['promo'] = (r.get('data') or {}).get('regla_venta_id') if isinstance(r, dict) else None
    r = pg(dict(accion='agregar', tipo='ESCALA', nombre='Sin tramos', f_desde=HOY, tramos='[]'))
    check(not ok_resp(r) and 'tramo' in err(r), 'escala sin tramos se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Mal', descuento_pct=150, f_desde=HOY))
    check(not ok_resp(r), 'promoción de 150 % se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Fechas', descuento_pct=5, f_desde=HOY, f_hasta='2000-01-01'))
    check(not ok_resp(r) and 'hasta' in err(r), 'fecha hasta anterior a desde se rechaza', err(r))
    r = pg(dict(accion='agregar', tipo='PROMOCION', nombre='Pres ajena', producto_id=P1, presentacion_id=999999, descuento_pct=5, f_desde=HOY))
    check(not ok_resp(r), 'presentación que no es del producto se rechaza', err(r))
    r = pg(dict(accion='listar', estado=1), 'GET')
    reglas = (r.get('data') or {}).get('reglas', []) if isinstance(r, dict) else []
    esc = next((x for x in reglas if x.get('regla_venta_id') == CTX.get('escala')), None)
    check(len(reglas) == 2 and esc and len(esc.get('tramos') or []) == 2 and '30034' in (esc.get('alcance_texto') or ''),
          'el listado trae las dos reglas, los tramos y el alcance', str(r)[:300])
    r = pg(dict(accion='editar', regla_venta_id=CTX.get('escala'), tipo='ESCALA', nombre='Escala P1', producto_id=P1, f_desde=HOY,
                tramos=json.dumps([{'cantidad_desde': 24, 'descuento_pct': 5}, {'cantidad_desde': 120, 'descuento_pct': 8},
                                   {'cantidad_desde': 240, 'descuento_pct': 10}])))
    check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__precios_reglas_venta_tramos WHERE regla_venta_id = {CTX.get('escala') or 0}")) == 3,
          'editar reemplaza los tramos', err(r))

    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=24, cliente_descuento_pct=3), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    check(ok_resp(r) and num(d.get('auto_pct')) == 7 and d.get('regla_tipo') == 'PROMOCION', 'probar: 24 u → promo 7 %', str(r)[:300])
    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=240, cliente_descuento_pct=3), 'GET')
    d = r.get('data') or {} if isinstance(r, dict) else {}
    check(num(d.get('auto_pct')) == 10 and d.get('regla_tipo') == 'ESCALA', 'probar: 240 u → escala 10 %', str(r)[:300])

    r = pg(dict(accion='estado', regla_venta_id=CTX.get('promo'), estado=2))
    check(ok_resp(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__precios_reglas_venta WHERE regla_venta_id = {CTX.get('promo') or 0}")) == 2,
          'inactivar la promoción (no se borra)', err(r))
    r = pg(dict(accion='vista_previa', producto_id=P1, cantidad=24, cliente_descuento_pct=3), 'GET')
    check(num((r.get('data') or {}).get('auto_pct')) == 5, 'sin la promo: escala 5 %', str(r)[:200])
    pg(dict(accion='estado', regla_venta_id=CTX.get('promo'), estado=1))

    r = pg(dict(accion='tope_guardar', empresa_perfil_id=PERFIL_VEND, tope_pct=5))
    topes = r.get('data') or [] if isinstance(r, dict) else []
    check(ok_resp(r) and any(t.get('empresa_perfil_id') == PERFIL_VEND and num(t.get('tope_pct')) == 5 for t in topes), 'tope 5 % al perfil Pedidos', err(r))
    r = pg(dict(accion='tope_guardar', empresa_perfil_id=PERFIL_VEND, tope_pct=10))
    check(ok_resp(r) and int(val(f"SELECT COUNT(*) FROM gestion__ventas_descuentos_topes WHERE empresa_perfil_id = {PERFIL_VEND} AND tabla_estado_registro_id = 2")) == 1,
          'cambiar el tope deja el anterior como historial', err(r))
    otro = int(val("SELECT MIN(empresa_perfil_id) FROM conf__empresas_perfiles WHERE empresa_id <> 2") or 0)
    r = pg(dict(accion='tope_guardar', empresa_perfil_id=otro, tope_pct=10))
    check(not ok_resp(r), 'perfil de otra empresa se rechaza', err(r))
    r = pg(dict(accion='lista_tope_guardar', lista_precio_id=LISTA, descuento_tope_pct=20))
    check(ok_resp(r) and num(val(f"SELECT descuento_tope_pct FROM gestion__listas_precios WHERE lista_precio_id = {LISTA}")) == 20, 'tope de la lista General 20 %', err(r))


# ---------------------------------------------------------------------------------------------
def total_pct(auto, manual):
    return round((1 - (1 - auto / 100) * (1 - manual / 100)) * 100, 2)


def linea_pct(producto, cantidad, precio, pct):
    neto = round(cantidad * precio * (1 - pct / 100), 2)
    iva = round(neto * 0.21, 2)
    return linea(producto, cantidad, precio, 21.0, descuento_general_pct=pct, descuento_general=round(precio * pct / 100, 6),
                 precio_unitario_neto=round(precio * (1 - pct / 100), 6), neto_gravado=neto, iva_importe=iva, total_linea=round(neto + iva, 2))


def alta_pedido(dets, usuario_id=1):
    r = ajax('ventas_pedidos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=91, comprobante_tipo_id=1, sucursal_id=1,
             punto_venta_id=1, entidad_id=CLI, f_emision=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
             descuento_general_pct=0, detalles=dets, **totales(dets)), usuario_id=usuario_id)
    return r, ((r.get('venta_pedido_id') or r.get('id')) if isinstance(r, dict) else None)


def regla_linea(ped):
    return uno(f"SELECT precio_regla_tipo, precio_regla_id, CAST(descuento_auto_pct AS DOUBLE) auto, CAST(descuento_manual_pct AS DOUBLE) manual, "
               f"descuento_aprobado_por FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {ped or 0} LIMIT 1")


def confirmar(ped, usuario_id):
    return ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', venta_pedido_id=ped, accion_js='confirmar_pedido',
                empresa_idx=2, pagina_idx=91), usuario_id=usuario_id)


caso('Reglas de precio: la pantalla del pedido pide los descuentos')
if CTX.get('ok'):
    lineas = json.dumps([{'producto_id': P1, 'presentacion_id': 0, 'cantidad': 12}, {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 12},
                         {'producto_id': P2, 'presentacion_id': 0, 'cantidad': 1}])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='resolver_descuentos', empresa_idx=2, entidad_id=CLI, sucursal_id=1, fecha=HOY,
             descuento_manual_pct=0, lineas=lineas))
    d = (r.get('data') or {}) if isinstance(r, dict) else {}
    ls = d.get('lineas') or []
    check(d.get('activo') and len(ls) == 3 and num(ls[0].get('total_pct')) == 7 and ls[0].get('regla_tipo') == 'PROMOCION'
          and num(ls[1].get('total_pct')) == 7, 'P1 12 + 12 u: promo 7 % en las dos líneas', str(r)[:400])
    r = ajax('ventas_pedidos_ajax.php', dict(accion='resolver_descuentos', empresa_idx=2, entidad_id=CLI, sucursal_id=1, fecha=HOY,
             descuento_manual_pct=5, lineas=json.dumps([{'producto_id': P1, 'cantidad': 240}])))
    ls = ((r.get('data') or {}).get('lineas') or []) if isinstance(r, dict) else []
    check(ls and num(ls[0].get('auto_pct')) == 10 and num(ls[0].get('total_pct')) == total_pct(10, 5), '240 u: escala 10 % + manual 5 %', str(r)[:300])

caso('Reglas de precio: pedido con descuento manual y tope del vendedor')
if CTX.get('ok'):
    # Vendedor (tope 10 %): automático 7 % (promo) + manual 5 % → se graba aprobado por él
    r, ped = alta_pedido([linea_pct(P1, 24, 100.0, total_pct(7, 5))], usuario_id=VEND)
    CTX['ped_ok'] = ped
    l = regla_linea(ped)
    check(ok_resp(r) and l and l['precio_regla_tipo'] == 'PROMOCION' and num(l['auto']) == 7 and abs(num(l['manual']) - 5) < 0.02
          and int(l['descuento_aprobado_por'] or 0) == VEND, 'manual 5 % dentro del tope: queda aprobado por el vendedor', err(r) + ' ' + str(l))
    # Manual 15 % (supera el tope): se graba, pero el vendedor no lo puede confirmar
    r, ped2 = alta_pedido([linea_pct(P1, 24, 100.0, total_pct(7, 15))], usuario_id=VEND)
    l = regla_linea(ped2)
    check(ok_resp(r) and l and abs(num(l['manual']) - 15) < 0.02 and l['descuento_aprobado_por'] is None,
          'manual 15 %: se graba en Borrador sin aprobar', err(r) + ' ' + str(l))
    r = confirmar(ped2, VEND)
    check(not ok_resp(r) and 'tope' in err(r), 'el vendedor no lo puede confirmar', err(r))
    check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {ped2 or 0}")) == 3, '…y sigue en Borrador')
    r = confirmar(ped2, 1)
    l = regla_linea(ped2)
    check(ok_resp(r) and int(l['descuento_aprobado_por'] or 0) == 1, 'lo confirma el administrador y queda aprobado por él', err(r) + ' ' + str(l))
    # Sin descuento manual ni cliente: NINGUNA / CLIENTE según corresponda, nunca falla
    r, ped3 = alta_pedido([linea_pct(P2, 1, 50.0, 0)])
    l = regla_linea(ped3)
    check(ok_resp(r) and l and l['precio_regla_tipo'] in ('NINGUNA', 'CLIENTE') and num(l['manual']) == 0, 'producto sin reglas: sin manual', err(r) + ' ' + str(l))

    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 0 WHERE empresa_id = 2")
    r, ped4 = alta_pedido([linea_pct(P1, 24, 100.0, 30)], usuario_id=VEND)
    l = regla_linea(ped4)
    check(ok_resp(r) and l and l['precio_regla_tipo'] is None and l['manual'] is None, 'con el indicador apagado: se graba como siempre (columnas en NULL)', err(r) + ' ' + str(l))
    r = ajax('ventas_pedidos_ajax.php', dict(accion='resolver_descuentos', empresa_idx=2, entidad_id=CLI, lineas='[]'))
    check(isinstance(r, dict) and (r.get('data') or {}).get('activo') is False, 'la pantalla no pide descuentos', str(r)[:200])
    r = confirmar(ped4, VEND)
    check('tope' not in err(r), 'y el vendedor confirma sin control de tope', err(r))
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 1 WHERE empresa_id = 2")


# ---------------------------------------------------------------------------------------------
def regla_de(tabla, pk_padre, id_padre):
    return uno(f"SELECT precio_regla_tipo, precio_regla_id, CAST(descuento_auto_pct AS DOUBLE) auto, CAST(descuento_manual_pct AS DOUBLE) manual, "
               f"descuento_aprobado_por FROM gestion__ventas_{tabla}_detalles WHERE {pk_padre} = {id_padre or 0} LIMIT 1")


def remito_35(dets, usuario_id=1):
    r = ajax('ventas_remitos_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=88, punto_venta_id=1, comprobante_tipo_id=40,
             entidad_id=CLI, f_emision=HOY, detalles=dets), usuario_id=usuario_id)
    return r, ((r.get('venta_remito_id') or r.get('id')) if isinstance(r, dict) else None)


def factura_35(dets, usuario_id=1):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=4, entidad_id=CLI, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM reglas de precio', detalles=dets), usuario_id=usuario_id)
    fv = None
    if isinstance(r, dict):
        fv = r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')
    return r, fv


def lin_rem(pct, **extra):
    return dict(dict(producto_id=P1, cantidad=24, precio_unitario_bruto=100.0, descuento_general_pct=pct, iva_alicuota_id=1, iva_porcentaje=21), **extra)


def lin_fac(pct, **extra):
    return dict(dict(producto_id=P1, cantidad=24, precio_unitario=100.0, descuento_general_pct=pct, iva_alicuota_id=1, iva_porcentaje=21), **extra)


caso('Reglas de precio: remito y factura heredan la regla del pedido')
ped = CTX.get('ped_ok')
if CTX.get('ok') and ped:
    r = confirmar(ped, 1)
    check(ok_resp(r), 'confirmar el pedido aprobado', err(r))
    vpd = val(f"SELECT venta_pedido_detalle_id FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = {ped} LIMIT 1")
    r, rem = remito_35([lin_rem(total_pct(7, 5), venta_pedido_detalle_id=int(vpd or 0))])
    l = regla_de('remitos', 'venta_remito_id', rem)
    check(ok_resp(r) and l and l['precio_regla_tipo'] == 'PROMOCION' and num(l['auto']) == 7 and abs(num(l['manual']) - 5) < 0.02
          and int(l['descuento_aprobado_por'] or 0) == VEND, 'el remito desde el pedido hereda promo 7 % + manual 5 % aprobado por el vendedor',
          err(r) + ' ' + str(l))
    if rem:
        r = ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', venta_remito_id=rem, accion_js='confirmar', empresa_idx=2, pagina_idx=88))
        check(ok_resp(r), 'confirmar el remito', err(r))
        vrd = val(f"SELECT venta_remito_detalle_id FROM gestion__ventas_remitos_detalles WHERE venta_remito_id = {rem} LIMIT 1")
        r, fv = factura_35([lin_fac(total_pct(7, 5), venta_remito_detalle_id=int(vrd or 0), venta_pedido_detalle_id=int(vpd or 0))])
        l = regla_de('facturas', 'venta_factura_id', fv)
        check(ok_resp(r) and l and l['precio_regla_tipo'] == 'PROMOCION' and int(l['descuento_aprobado_por'] or 0) == VEND,
              'la factura desde el remito hereda la regla', err(r) + ' ' + str(l))

caso('Reglas de precio: remito y factura directos con tope del usuario')
if CTX.get('ok'):
    r, rem = remito_35([lin_rem(total_pct(7, 15))], usuario_id=VEND)
    check(not ok_resp(r) and 'tope' in err(r), 'remito directo con manual 15 % (tope 10): se rechaza', err(r))
    r, rem = remito_35([lin_rem(total_pct(7, 5))], usuario_id=VEND)
    l = regla_de('remitos', 'venta_remito_id', rem)
    check(ok_resp(r) and l and l['precio_regla_tipo'] == 'PROMOCION' and abs(num(l['manual']) - 5) < 0.02 and int(l['descuento_aprobado_por'] or 0) == VEND,
          'remito directo con manual 5 %: se graba aprobado por el vendedor', err(r) + ' ' + str(l))
    r, fv = factura_35([lin_fac(total_pct(7, 15))], usuario_id=VEND)
    check(not ok_resp(r) and 'tope' in err(r), 'factura directa con manual 15 %: se rechaza', err(r))
    r, fv = factura_35([lin_fac(total_pct(7, 15))])
    l = regla_de('facturas', 'venta_factura_id', fv)
    check(ok_resp(r) and l and abs(num(l['manual']) - 15) < 0.02 and int(l['descuento_aprobado_por'] or 0) == 1,
          'la misma factura cargada por el administrador: aprobada por él', err(r) + ' ' + str(l))
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 0 WHERE empresa_id = 2")
    r, fv = factura_35([lin_fac(30)], usuario_id=VEND)
    l = regla_de('facturas', 'venta_factura_id', fv)
    check(ok_resp(r) and l and l['precio_regla_tipo'] is None, 'con el indicador apagado: factura directa al 30 % sin control (columnas en NULL)',
          err(r) + ' ' + str(l))
    r, rem = remito_35([lin_rem(30)], usuario_id=VEND)
    l = regla_de('remitos', 'venta_remito_id', rem)
    check(ok_resp(r) and l and l['precio_regla_tipo'] is None, 'con el indicador apagado: remito directo al 30 % sin control', err(r) + ' ' + str(l))
    sql("UPDATE conf__empresas SET usa_reglas_precio_venta = 1 WHERE empresa_id = 2")

if CTX.get('ok'):
    for i, d in CTX.get('desc_cli', []):
        sql(f"UPDATE gestion__entidades_condiciones_clientes SET cliente_descuento_general = {d if d is not None else 'NULL'} WHERE entidad_condicion_cliente_id = {i}")
