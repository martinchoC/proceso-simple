from simlib import *

PROV = 5765           # DLC MOTORS (AMERS SRL)
P1, P2, P3 = 1529, 1878, 3500
BOCA = 1
CTX = {}


def crear_oc(lineas_oc):
    dets = [linea(p, c, pr) for p, c, pr in lineas_oc]
    r = ajax('ordenes_compra_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=51, comprobante_tipo_id=17,
             sucursal_id=1, punto_venta_id=1, entidad_id=PROV, f_emision=HOY, moneda_id=1, tipo_cambio=1,
             detalles=dets, **totales(dets)))
    return r


def accion_oc(oc_id, accion):
    return ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', orden_compra_id=oc_id, accion_js=accion,
                empresa_idx=2, pagina_idx=51))


def crear_factura_prov(nro, dets, orden_compra_id=''):
    return ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57,
                comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=nro, sucursal_id=1, deposito_id=BOCA,
                entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1, tipo_cambio=1,
                condicion_pago_id='', orden_compra_id=orden_compra_id, descuento_general_pct=0, detalles=dets,
                impuestos_adicionales=[], **totales(dets)))


def accion_fp(fid, accion):
    return ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid,
                accion_js=accion, empresa_idx=2, pagina_idx=57))


def estado_oc(oc_id):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ordenes_compra WHERE orden_compra_id={oc_id}"))


def recibido(oc_id, prod):
    return num(val(f"SELECT cantidad_recibida FROM gestion__ordenes_compra_detalle WHERE orden_compra_id={oc_id} AND producto_id={prod}"))


def comprobante_fp(fid):
    return uno(f"SELECT comprobante_id, tabla_estado_registro_id, comprobante_nro, importe_total, importe_pendiente, signo "
               f"FROM gestion__comprobantes WHERE tabla_origen_id=84 AND registro_origen_id={fid}")


# ---------------------------------------------------------------------------------------------
caso('Caso 1 - Compra con OC y recepción parcial (OC -> factura proveedor parcial)')
s1, s2 = stock(P1), stock(P2)
r = crear_oc([(P1, 10, 1000), (P2, 5, 500)])
oc = r.get('orden_compra_id') if isinstance(r, dict) else None
check(ok_resp(r) and oc, 'alta de OC en Borrador', err(r))
if oc:
    CTX['oc'] = oc
    r = accion_oc(oc, 'confirmar')
    check(ok_resp(r) and estado_oc(oc) == 5, 'confirmar OC -> 5 con número', err(r))
    check(num(val(f"SELECT comprobante_nro FROM gestion__ordenes_compra WHERE orden_compra_id={oc}")) > 0, 'OC numerada')
    r = accion_oc(oc, 'pendiente_recepcion')
    check(ok_resp(r) and estado_oc(oc) == 7, 'OC -> 7 Pendiente de recepción', err(r))
    check(stock(P1) == s1, 'la OC no mueve stock')

    r = ajax('facturas_proveedores_ajax.php', dict(accion='listar_oc_pendientes', entidad_id=PROV, empresa_idx=2), 'GET')
    check(ok_resp(r) and any(o['orden_compra_id'] == oc for o in r.get('data', [])), 'la factura ve la OC como pendiente', err(r))
    r = ajax('facturas_proveedores_ajax.php', dict(accion='obtener_oc_lineas', orden_compra_id=oc, entidad_id=PROV, empresa_idx=2), 'GET')
    lineas = {l['producto_id']: l for l in r.get('data', {}).get('lineas', [])} if ok_resp(r) else {}
    check(len(lineas) == 2 and lineas[P1]['pendiente'] == 10, 'líneas de la OC con pendiente 10 / 5', err(r))

    dets = [linea(P1, 4, 1000, ordenes_compra_detalle_id=lineas[P1]['ordenes_compra_detalle_id'])] if lineas else []
    r = crear_factura_prov(proximo_nro_proveedor(7, 910000), dets, oc)
    f1 = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    check(ok_resp(r) and f1, 'factura de proveedor importando 4 de 10', err(r))
    if f1:
        CTX['f1'] = f1
        check(int(val(f"SELECT IFNULL(orden_compra_id,0) FROM gestion__facturas_proveedores WHERE factura_proveedor_id={f1}")) == oc, 'factura vinculada a la OC')
        r = accion_fp(f1, 'aprobar')
        check(ok_resp(r), 'factura -> Pendiente de validación', err(r))
        check(stock(P1) == s1, 'sin confirmar no mueve stock')
        check(estado_oc(oc) == 7, 'OC sigue en 7 mientras la factura no está confirmada')
        r = accion_fp(f1, 'confirmar')
        check(ok_resp(r), 'confirmar factura', err(r))
        check(stock(P1) == s1 + 4, f'stock P1 +4 ({s1} -> {stock(P1)})')
        check(recibido(oc, P1) == 4, 'OC: recibido línea 1 = 4')
        check(estado_oc(oc) == 41, 'OC -> 41 Recibido parcial', f'estado {estado_oc(oc)}')
        c = comprobante_fp(f1)
        check(c and int(c['tabla_estado_registro_id']) == 5 and abs(num(c['importe_pendiente']) - 4840) < 0.01,
              'comprobante universal confirmado con saldo 4840', str(c))
        check(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id={c['comprobante_id']}") not in (None, '0') if c else False,
              'asiento contable generado')

# ---------------------------------------------------------------------------------------------
caso('Caso 2 - Segunda recepción que completa la OC (con exceso de cantidad)')
oc = CTX.get('oc')
if oc:
    r = ajax('facturas_proveedores_ajax.php', dict(accion='obtener_oc_lineas', orden_compra_id=oc, entidad_id=PROV, empresa_idx=2), 'GET')
    lineas = {l['producto_id']: l for l in r.get('data', {}).get('lineas', [])} if ok_resp(r) else {}
    check(lineas and lineas[P1]['pendiente'] == 6 and lineas[P2]['pendiente'] == 5, 'pendientes 6 / 5', err(r))
    s1, s2 = stock(P1), stock(P2)
    dets = [linea(P1, 8, 1000, ordenes_compra_detalle_id=lineas[P1]['ordenes_compra_detalle_id']),
            linea(P2, 5, 500, ordenes_compra_detalle_id=lineas[P2]['ordenes_compra_detalle_id']),
            linea(P3, 2, 300)] if lineas else []
    NRO_F2 = proximo_nro_proveedor(7, 910000)
    r = crear_factura_prov(NRO_F2, dets, oc)
    f2 = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    check(ok_resp(r) and f2, 'factura con 8 (exceso), 5 y un producto fuera de la OC', err(r))
    if f2:
        CTX['f2'] = f2
        accion_fp(f2, 'aprobar')
        r = accion_fp(f2, 'confirmar')
        check(ok_resp(r), 'confirmar factura 2', err(r))
        check(stock(P1) == s1 + 8 and stock(P2) == s2 + 5, 'stock +8 / +5')
        check(recibido(oc, P1) == 12 and recibido(oc, P2) == 5, 'OC: recibido 12 / 5')
        check(estado_oc(oc) == 8, 'OC -> 8 Recibido', f'estado {estado_oc(oc)}')
        r = ajax('facturas_proveedores_ajax.php', dict(accion='listar_oc_pendientes', entidad_id=PROV, empresa_idx=2), 'GET')
        check(ok_resp(r) and not any(o['orden_compra_id'] == oc for o in r.get('data', [])), 'OC recibida ya no se ofrece')
        r = ajax('facturas_proveedores_ajax.php', dict(accion='editar', factura_proveedor_id=f2, empresa_idx=2, pagina_idx=57,
                 comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=NRO_F2, sucursal_id=1, deposito_id=BOCA,
                 entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1, tipo_cambio=1,
                 orden_compra_id=oc, detalles=dets, **totales(dets)))
        check(not ok_resp(r), 'no deja editar una factura confirmada', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 3 - Factura de proveedor sin OC, con producto no vinculado al proveedor')
prod_nv = int(val(f"SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id=2 AND p.tabla_estado_registro_id=1 "
                  f"AND NOT EXISTS (SELECT 1 FROM gestion__productos_proveedores pp WHERE pp.producto_id=p.producto_id AND pp.entidad_id={PROV}) "
                  f"AND p.producto_id NOT IN ({P1},{P2},{P3}) ORDER BY p.producto_id LIMIT 1"))
CTX['prod_nv'] = prod_nv
codigo = val(f"SELECT producto_codigo FROM gestion__productos WHERE producto_id={prod_nv}")
r = ajax('facturas_proveedores_ajax.php', dict(accion='buscar_productos_proveedor', entidad_id=PROV, q=codigo, empresa_idx=2), 'GET')
encontrado = [x for x in r if int(x['producto_id']) == prod_nv] if isinstance(r, list) else []
check(encontrado and int(encontrado[0]['vinculado']) == 0, 'la búsqueda trae el producto no vinculado marcado vinculado=0', str(r)[:200])
cod = f'SIM-{prod_nv}'
r = ajax('facturas_proveedores_ajax.php', dict(accion='vincular_producto_proveedor', producto_id=prod_nv, entidad_id=PROV, codigo_proveedor=cod, empresa_idx=2))
check(ok_resp(r), 'vincular con código de proveedor', err(r))
check(val(f"SELECT COUNT(*) FROM gestion__productos_proveedores WHERE producto_id={prod_nv} AND entidad_id={PROV} AND codigo_proveedor='{cod}' AND tabla_estado_registro_id=1") == '1', 'vínculo activo creado')
check(cod in (val(f"SELECT proveedores_busqueda FROM gestion__productos WHERE producto_id={prod_nv}") or ''), 'proveedores_busqueda actualizado')
r = ajax('facturas_proveedores_ajax.php', dict(accion='buscar_productos_proveedor', entidad_id=PROV, q=cod, empresa_idx=2), 'GET')
check(isinstance(r, list) and any(int(x['producto_id']) == prod_nv and int(x['vinculado']) == 1 for x in r), 'se encuentra por código de proveedor')
s = stock(prod_nv)
dets = [linea(prod_nv, 3, 2000)]
r = crear_factura_prov(proximo_nro_proveedor(7, 910000), dets)
f3 = r.get('factura_proveedor_id') if isinstance(r, dict) else None
check(ok_resp(r) and f3, 'factura sin OC', err(r))
if f3:
    CTX['f3'] = f3
    accion_fp(f3, 'aprobar')
    r = accion_fp(f3, 'confirmar')
    check(ok_resp(r), 'confirmar', err(r))
    check(stock(prod_nv) == s + 3, f'stock +3 ({s} -> {stock(prod_nv)})')
    check(val(f"SELECT IFNULL(orden_compra_id,0) FROM gestion__facturas_proveedores WHERE factura_proveedor_id={f3}") == '0', 'sin OC vinculada')

with open(os.path.join(SALIDAS, 'ctx_compras.json'), 'w') as f:
    json.dump(CTX, f)
