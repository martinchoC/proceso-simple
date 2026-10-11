from simlib import *

# Lista del proveedor y listas de venta a partir de precios actuales y de compras confirmadas:
# - "Armar lista desde precios" de Lista Precios Proveedores (proveedor_precios_ajax.php)
# - Parámetros de Empresa compra_actualiza_lista_proveedor / compra_actualiza_listas_venta
#   aplicados al confirmar una factura de proveedor (fpActualizarPreciosPorCompra)
# Requiere sql/mantenimiento/20261010_precios_compra_01_estructura.sql.

PROV_LISTA = 5746     # RGL: vínculos sin precio y descuento de compra vigente
PROV = 5765           # DLC MOTORS (AMERS SRL), mismo de 01_compras
BOCA = 1
LISTA_GENERAL = 1     # origen COSTO, regla general +100%


def calcular(base, entidad=PROV_LISTA):
    return ajax('proveedor_precios_ajax.php', dict(accion='armar_lista_calcular', empresa_idx=2, pagina_idx=92,
                entidad_id=entidad, base=base), 'GET')


def ppp(producto_proveedor_id):
    return uno(f"SELECT precio_lista, origen_carga, tabla_estado_registro_id FROM gestion__productos_proveedores_precios "
               f"WHERE producto_proveedor_id={producto_proveedor_id}")


def ppp_producto(producto_id, entidad=PROV):
    return uno(f"SELECT ppp.producto_proveedor_id, ppp.precio_lista, ppp.origen_carga FROM gestion__productos_proveedores pp "
               f"JOIN gestion__productos_proveedores_precios ppp ON ppp.producto_proveedor_id = pp.producto_proveedor_id "
               f"WHERE pp.empresa_id=2 AND pp.entidad_id={entidad} AND pp.producto_id={producto_id} AND pp.tabla_estado_registro_id=1")


def precio_venta(producto_id, lista=LISTA_GENERAL):
    return val(f"SELECT precio_final FROM gestion__listas_precios_productos WHERE empresa_id=2 AND lista_precio_id={lista} "
               f"AND producto_id={producto_id} AND tabla_estado_registro_id=1")


def set_opciones(lista_proveedor, listas_venta):
    """Edita la vigencia abierta de Parámetros de Empresa por el ajax, con el resto de los campos como están."""
    pid = int(val("SELECT empresa_parametro_id FROM gestion__empresas_parametros WHERE empresa_id=2 AND f_hasta IS NULL "
                  "AND tabla_estado_registro_id=1 ORDER BY f_desde DESC LIMIT 1"))
    p = ajax('empresas_parametros_ajax.php', dict(accion='obtener_parametro', empresa_parametro_id=pid), 'GET')
    campos = ['condicion_fiscal_id', 'punto_venta_defecto_id', 'lista_precio_defecto_id', 'condicion_pago_defecto_id',
              'ingresos_brutos_nro', 'iibb_regimen_id', 'jurisdiccion_sede_id', 'inicio_actividades', 'leyenda_fiscal',
              'arca_habilitado', 'f_desde', 'f_hasta', 'observaciones']
    datos = {k: (p.get(k) if p.get(k) is not None else '') for k in campos}
    datos.update(accion='guardar', empresa_parametro_id=pid, empresa_id=2,
                 compra_actualiza_lista_proveedor=lista_proveedor, compra_actualiza_listas_venta=listas_venta)
    return ajax('empresas_parametros_ajax.php', datos)


def factura_confirmada(producto_id, precio):
    dets = [linea(producto_id, 1, precio)]
    r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=57,
             comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=proximo_nro_proveedor(7, 920000), sucursal_id=1,
             deposito_id=BOCA, entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1,
             tipo_cambio=1, condicion_pago_id='', orden_compra_id='', descuento_general_pct=0, detalles=dets,
             impuestos_adicionales=[], **totales(dets)))
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if not fid:
        return None, r
    ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar',
         empresa_idx=2, pagina_idx=57))
    r = ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar',
             empresa_idx=2, pagina_idx=57))
    return (fid if ok_resp(r) else None), r


# ---------------------------------------------------------------------------------------------
caso('Caso 1 - Armar la lista del proveedor desde el precio de venta (vista previa y carga)')
desc = num(val(f"SELECT proveedor_descuento_general FROM gestion__entidades_condiciones_proveedores WHERE entidad_id={PROV_LISTA} "
               "AND tabla_estado_registro_id=1 AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE()) ORDER BY f_desde DESC LIMIT 1"))
r = calcular(str(LISTA_GENERAL))
d = r.get('data') or {} if isinstance(r, dict) else {}
filas = d.get('filas', [])
calc = [f for f in filas if f['precio_lista_propuesto'] is not None]
check(ok_resp(r) and d.get('total', 0) > 0 and calc, f"vista previa: {d.get('total')} sin precio, {d.get('calculables')} calculables", err(r))
sin_precio_antes = int(val(f"SELECT COUNT(*) FROM gestion__productos_proveedores pp LEFT JOIN gestion__productos_proveedores_precios ppp "
                           f"ON ppp.producto_proveedor_id = pp.producto_proveedor_id WHERE pp.empresa_id=2 AND pp.entidad_id={PROV_LISTA} "
                           "AND pp.tabla_estado_registro_id=1 AND ppp.producto_proveedor_precio_id IS NULL"))
check(d.get('total') == sin_precio_antes or d.get('total', 0) <= sin_precio_antes, 'la vista previa no graba nada', f'{d.get("total")} vs {sin_precio_antes}')
if calc:
    f0 = calc[0]
    esperado = round(num(f0['precio_base']) / 2 / (1 - desc / 100), 2)
    check(abs(num(f0['precio_lista_propuesto']) - esperado) < 0.02,
          f"precio de lista = venta / 2 (regla +100%) / (1 - {desc}%) = {esperado}", str(f0))
    elegidos = [f['producto_proveedor_id'] for f in calc[:3]]
    r = ajax('proveedor_precios_ajax.php', dict(accion='armar_lista_generar', empresa_idx=2, pagina_idx=92, entidad_id=PROV_LISTA,
             base=str(LISTA_GENERAL), producto_proveedor_ids=json.dumps(elegidos)))
    check(ok_resp(r) and (r.get('data') or {}).get('insertados') == len(elegidos), f'carga solo los {len(elegidos)} tildados', err(r))
    filas_ppp = [ppp(i) for i in elegidos]
    check(all(x and x['origen_carga'] == 'ESTIMADO' and x['tabla_estado_registro_id'] == '1' for x in filas_ppp),
          'filas nuevas con origen ESTIMADO y estado 1', str(filas_ppp))
    check(abs(num(filas_ppp[0]['precio_lista']) - num(f0['precio_lista_propuesto'])) < 0.01, 'grabó el precio de la vista previa')
    r = calcular(str(LISTA_GENERAL))
    check(ok_resp(r) and (r.get('data') or {}).get('total') == d.get('total') - len(elegidos), 'ya no aparecen como sin precio', err(r))
    r = ajax('proveedor_precios_ajax.php', dict(accion='armar_lista_generar', empresa_idx=2, pagina_idx=92, entidad_id=PROV_LISTA,
             base=str(LISTA_GENERAL), producto_proveedor_ids=json.dumps(elegidos)))
    check(ok_resp(r) and (r.get('data') or {}).get('insertados') == 0, 'repetir con los mismos no duplica ni pisa', err(r))
    check(all(val(f"SELECT COUNT(*) FROM gestion__productos_proveedores_precios WHERE producto_proveedor_id={i}") == '1' for i in elegidos),
          'una sola fila por vínculo')

r = calcular('COSTO')
check(ok_resp(r), 'base COSTO responde', err(r))
r = calcular('999999')
check(not ok_resp(r), 'lista inexistente se rechaza', err(r))
r = calcular(str(LISTA_GENERAL), entidad=0)
check(not ok_resp(r), 'sin proveedor se rechaza', err(r))

# ---------------------------------------------------------------------------------------------
caso('Caso 2 - Factura de proveedor con las opciones apagadas: solo cambia el costo')
PROD = int(val(f"SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id=2 AND p.tabla_estado_registro_id=1 "
               f"AND EXISTS (SELECT 1 FROM gestion__listas_precios_productos l WHERE l.producto_id=p.producto_id "
               f"  AND l.lista_precio_id={LISTA_GENERAL} AND l.tabla_estado_registro_id=1) "
               f"AND NOT EXISTS (SELECT 1 FROM gestion__productos_proveedores pp WHERE pp.producto_id=p.producto_id AND pp.entidad_id={PROV}) "
               "ORDER BY p.producto_id DESC LIMIT 1"))
r = set_opciones(0, 0)
check(ok_resp(r), 'Parámetros de Empresa: lista proveedor 0, listas venta 0', err(r))
pv_antes = precio_venta(PROD)
fid, r = factura_confirmada(PROD, 1000)
check(fid, 'factura confirmada', err(r))
check(num(val(f"SELECT costo_actual FROM gestion__productos_costos WHERE empresa_id=2 AND producto_id={PROD}")) == 1000, 'costo = 1000')
check(ppp_producto(PROD) is None, 'no toca la lista del proveedor')
check(precio_venta(PROD) == pv_antes, 'no toca la lista de venta', f'{pv_antes} -> {precio_venta(PROD)}')

# ---------------------------------------------------------------------------------------------
caso('Caso 3 - Opciones "solo sin precio" + recalcular listas de venta')
r = set_opciones(1, 1)
check(ok_resp(r), 'Parámetros de Empresa: lista proveedor 1, listas venta 1', err(r))
check(val("SELECT compra_actualiza_lista_proveedor FROM gestion__empresas_parametros WHERE empresa_id=2 AND f_hasta IS NULL "
          "AND tabla_estado_registro_id=1 ORDER BY f_desde DESC LIMIT 1") == '1', 'quedó grabado en la vigencia abierta')
fid, r = factura_confirmada(PROD, 1100)
check(fid, 'factura confirmada', err(r))
x = ppp_producto(PROD)
check(x and num(x['precio_lista']) == 1100 and x['origen_carga'] == 'COMPRA', 'lista del proveedor cargada con 1100 (COMPRA) y vínculo creado', str(x))
check(abs(num(precio_venta(PROD)) - 2200) < 0.01, 'lista General recalculada: 1100 + 100% = 2200', precio_venta(PROD))
check('Lista del proveedor' in (r.get('message') or ''), 'el mensaje informa lo actualizado', r.get('message'))
fid, r = factura_confirmada(PROD, 1200)
x = ppp_producto(PROD)
check(fid and x and num(x['precio_lista']) == 1100, 'con precio ya cargado no lo pisa (sigue 1100)', str(x))
check(abs(num(precio_venta(PROD)) - 2400) < 0.01, 'la lista General sigue al costo: 2400', precio_venta(PROD))

# ---------------------------------------------------------------------------------------------
caso('Caso 4 - Opción "siempre": pisa la lista del proveedor con historial')
r = set_opciones(2, 0)
check(ok_resp(r), 'Parámetros de Empresa: lista proveedor 2, listas venta 0', err(r))
pv_antes = precio_venta(PROD)
fid, r = factura_confirmada(PROD, 1300)
x = ppp_producto(PROD)
check(fid and x and num(x['precio_lista']) == 1300 and x['origen_carga'] == 'COMPRA', 'lista del proveedor actualizada a 1300', str(x))
check(val(f"SELECT COUNT(*) FROM gestion__productos_proveedores_precios_historico WHERE producto_proveedor_id={x['producto_proveedor_id']} "
          "AND precio_lista=1100") == '1' if x else False, 'el 1100 anterior quedó en el histórico')
check(precio_venta(PROD) == pv_antes, 'listas de venta sin tocar (opción apagada)', f'{pv_antes} -> {precio_venta(PROD)}')
set_opciones(0, 0)
