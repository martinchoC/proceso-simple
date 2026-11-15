from simlib import *

# Compra por presentación (doc 27): órdenes de compra (8-2), facturas de proveedor con costo (8-3) y presentación del
# proveedor con la carga de facturas desde archivo (8-4). Empresa 2, módulo 2.
# Necesita en la copia: 20261112_productos_presentaciones_01 y 20261122_compra_presentaciones_01 / _02 (--scripts).
# Prende los indicadores y crea una presentación de compra solo en la copia; al final deja los indicadores como estaban.
#   python _local/tests/simulacion/run.py 40_compras_presentaciones.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261122_compra_presentaciones_01.sql _local/sql/mantenimiento/20261122_compra_presentaciones_02.sql

PROV = 5765           # DLC MOTORS (AMERS SRL), el proveedor del caso 01
P1, P2 = 1529, 1878
CTX = {}


def oc_ajax(params, method='POST'):
    return ajax('ordenes_compra_ajax.php', dict(params, empresa_idx=2, pagina_idx=51), method)


def guardar_oc(dets, oc_id=None):
    base = dict(comprobante_tipo_id=17, sucursal_id=1, punto_venta_id=1, entidad_id=PROV, f_emision=HOY, moneda_id=1,
                tipo_cambio=1, detalles=json.dumps(dets), **totales(dets))
    if oc_id:
        return oc_ajax(dict(base, accion='editar', orden_compra_id=oc_id))
    return oc_ajax(dict(base, accion='agregar'))


def lineas_oc(oc_id):
    return sql("SELECT producto_id, cantidad, precio_unitario, neto_gravado, total_linea, presentacion_id, presentacion_cantidad, presentacion_precio "
               "FROM gestion__ordenes_compra_detalle WHERE orden_compra_id = %d ORDER BY ordenes_compra_detalle_id" % int(oc_id))


# ---------------------------------------------------------------------------------------------
caso('Compra por presentación: preparación (indicadores y presentación, solo en la copia)')
if int(val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__ordenes_compra_detalle' "
           "AND COLUMN_NAME = 'presentacion_id'")) != 1:
    brecha('falta el script de compra por presentación en la copia', 'correr con --scripts 20261122_compra_presentaciones_01')
else:
    CTX['ind'] = uno("SELECT usa_presentaciones p, usa_compra_presentaciones c FROM conf__empresas WHERE empresa_id = 2")
    sql("UPDATE conf__empresas SET usa_presentaciones = 1, usa_compra_presentaciones = 1 WHERE empresa_id = 2")
    sql("INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_compra, aplica_venta, "
        "es_compra_defecto, es_venta_defecto, orden, tabla_estado_registro_id) VALUES (2, %d, 'SIM Caja x 12', 12, 1, 0, 1, 0, 99, 1)" % P1)
    CTX['pres'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE producto_id = %d AND presentacion_nombre = 'SIM Caja x 12'" % P1))
    CTX['ok'] = True
    r = oc_ajax(dict(accion='cpre_presentaciones', producto_id=P1), 'GET')
    check(ok_resp(r) and any(int(x['presentacion_id']) == CTX['pres'] for x in (r.get('data') or [])), 'la pantalla recibe las presentaciones de compra', str(r)[:200])

caso('Compra por presentación: orden de compra')
if CTX.get('ok'):
    PR = CTX['pres']
    cajas = linea(P1, 2.5, 9600, presentacion_id=PR, presentacion_cantidad=2.5, presentacion_precio=9600)
    r = guardar_oc([cajas, linea(P2, 5, 500)])
    oc = r.get('orden_compra_id') if isinstance(r, dict) else None
    check(ok_resp(r) and oc, 'alta de una OC con 2,5 cajas y una línea por unidad', err(r))
    if oc:
        CTX['oc'] = oc
        l = lineas_oc(oc)
        check(len(l) == 2 and num(l[0]['cantidad']) == 30 and num(l[0]['precio_unitario']) == 800 and num(l[0]['neto_gravado']) == 24000,
              'la línea en cajas queda en unidades: 30 a $ 800, neto $ 24.000', str(l)[:300])
        check(int(l[0]['presentacion_id'] or 0) == PR and num(l[0]['presentacion_cantidad']) == 2.5 and num(l[0]['presentacion_precio']) == 9600,
              'con la foto: 2,5 SIM Caja x 12 a $ 9.600', str(l[0]))
        check(l[1]['presentacion_id'] is None and num(l[1]['cantidad']) == 5, 'la línea por unidad no lleva foto', str(l[1]))
        r = oc_ajax(dict(accion='obtener', orden_compra_id=oc), 'GET')
        d = next((x for x in (r.get('detalles') or []) if int(x['producto_id']) == P1), {}) if isinstance(r, dict) else {}
        check(int(d.get('presentacion_id') or 0) == PR and num(d.get('presentacion_cantidad')) == 2.5, 'obtener devuelve la foto para editar', str(d)[:200])
        cajas3 = linea(P1, 3, 9000, presentacion_id=PR, presentacion_cantidad=3, presentacion_precio=9000)
        r = guardar_oc([cajas3, linea(P2, 5, 500)], oc)
        l = lineas_oc(oc)
        check(ok_resp(r) and num(l[0]['cantidad']) == 36 and num(l[0]['presentacion_cantidad']) == 3 and num(l[0]['precio_unitario']) == 750,
              'editar: 3 cajas a $ 9.000 = 36 u a $ 750', str(l)[:300])
        html = ajax('ordenes_compra_print.php', dict(orden_compra_id=oc, empresa_idx=2), 'GET')
        html = html.get('_raw', '') if isinstance(html, dict) else ''
        check('SIM Caja x 12 (36 u)' in html, 'la impresión muestra la caja y las unidades', html[:120])
    r = guardar_oc([linea(P2, 1, 100, presentacion_id=PR, presentacion_cantidad=1, presentacion_precio=100)])
    check(not ok_resp(r) and 'no es de ese producto' in err(r), 'una presentación de otro producto se rechaza', err(r))
    sql("UPDATE gestion__productos_presentaciones SET aplica_compra = 0 WHERE presentacion_id = %d" % PR)
    r = guardar_oc([linea(P1, 1, 100, presentacion_id=PR, presentacion_cantidad=1, presentacion_precio=100)])
    check(not ok_resp(r) and 'compras' in err(r), 'una presentación que no es de compra se rechaza', err(r))
    sql("UPDATE gestion__productos_presentaciones SET aplica_compra = 1 WHERE presentacion_id = %d" % PR)
    # Indicador apagado: la presentación que mande la pantalla se ignora (todo como siempre)
    sql("UPDATE conf__empresas SET usa_compra_presentaciones = 0 WHERE empresa_id = 2")
    r = guardar_oc([linea(P1, 7, 100, presentacion_id=PR, presentacion_cantidad=1, presentacion_precio=100)])
    oc2 = r.get('orden_compra_id') if isinstance(r, dict) else None
    l = lineas_oc(oc2) if oc2 else []
    check(ok_resp(r) and l and num(l[0]['cantidad']) == 7 and l[0]['presentacion_id'] is None, 'apagado: la línea queda como la mandó la pantalla, sin foto', str(l)[:200])
    r = oc_ajax(dict(accion='cpre_presentaciones', producto_id=P1), 'GET')
    check(ok_resp(r) and (r.get('data') or []) == [], 'apagado: la pantalla no recibe presentaciones', str(r)[:200])
    sql("UPDATE conf__empresas SET usa_compra_presentaciones = 1 WHERE empresa_id = 2")


# ---------------------------------------------------------------------------------------------
# Paso 8-3: factura de proveedor (desde la OC y a mano), stock, costo con precisión e impresión.
def fp_ajax(params, method='POST'):
    return ajax('facturas_proveedores_ajax.php', dict(params, empresa_idx=2, pagina_idx=57), method)


caso('Compra por presentación: factura de proveedor')
oc = CTX.get('oc')
if CTX.get('ok') and oc:
    PR = CTX['pres']
    oc_ajax(dict(accion='ejecutar_accion', orden_compra_id=oc, accion_js='confirmar'))
    oc_ajax(dict(accion='ejecutar_accion', orden_compra_id=oc, accion_js='pendiente_recepcion'))
    r = fp_ajax(dict(accion='obtener_oc_lineas', orden_compra_id=oc, entidad_id=PROV), 'GET')
    lineas = {int(l['producto_id']): l for l in (r.get('data') or {}).get('lineas', [])} if ok_resp(r) else {}
    l1 = lineas.get(P1, {})
    check(int(l1.get('presentacion_id') or 0) == PR and num(l1.get('presentacion_factor')) == 12,
          'las líneas de la OC traen su presentación para heredarla', str(l1)[:200])
    s1 = stock(P1)
    dets = [
        # 2 de las 3 cajas de la OC, a $ 9.000 la caja (lo que arma la pantalla al importar la OC)
        linea(P1, 24, 750, ordenes_compra_detalle_id=l1.get('ordenes_compra_detalle_id'), presentacion_id=PR, presentacion_cantidad=2, presentacion_precio=9000),
        # 1 caja a mano a $ 1.000: $ 83,3333 la unidad (la columna de 2 decimales guardaría 83,33)
        linea(P1, 1, 1000, presentacion_id=PR, presentacion_cantidad=1, presentacion_precio=1000),
    ]
    dets[1]['cantidad'] = 1   # la pantalla manda cajas; el servidor recalcula 12 unidades
    r = fp_ajax(dict(accion='agregar', comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=proximo_nro_proveedor(7, 940000),
                     sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1,
                     tipo_cambio=1, condicion_pago_id='', orden_compra_id=oc, descuento_general_pct=0, detalles=json.dumps(dets),
                     impuestos_adicionales='[]', **totales([linea(P1, 24, 750), linea(P1, 12, 1000 / 12)])))
    f = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    check(ok_resp(r) and f, 'factura con 2 cajas de la OC y 1 caja a mano', err(r))
    if f:
        l = sql("SELECT cantidad, precio_unitario, neto_gravado, presentacion_id, presentacion_cantidad, presentacion_precio "
                "FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = %d ORDER BY factura_proveedor_detalle_id" % int(f))
        check(len(l) == 2 and num(l[0]['cantidad']) == 24 and num(l[0]['neto_gravado']) == 18000 and num(l[0]['presentacion_cantidad']) == 2,
              'línea de la OC: 24 u, neto $ 18.000, foto 2 cajas', str(l)[:300])
        check(num(l[1]['cantidad']) == 12 and num(l[1]['neto_gravado']) == 1000 and num(l[1]['presentacion_precio']) == 1000,
              'línea a mano: 12 u, neto $ 1.000 (no $ 999,96)', str(l)[:300])
        r = fp_ajax(dict(accion='obtener', factura_proveedor_id=f), 'GET')
        check(isinstance(r, dict) and any(int(x.get('presentacion_id') or 0) == PR for x in (r.get('detalles') or [])), 'obtener devuelve la foto', str(r)[:200])
        fp_ajax(dict(accion='ejecutar_accion', factura_proveedor_id=f, accion_js='aprobar'))
        r = fp_ajax(dict(accion='ejecutar_accion', factura_proveedor_id=f, accion_js='confirmar'))
        check(ok_resp(r), 'confirmar la factura', err(r))
        check(stock(P1) == s1 + 36, 'el stock entra en unidades: +36 (%s -> %s)' % (s1, stock(P1)))
        check(num(val("SELECT cantidad_recibida FROM gestion__ordenes_compra_detalle WHERE ordenes_compra_detalle_id = %d" % int(l1.get('ordenes_compra_detalle_id') or 0))) == 24,
              'la OC queda con 24 u recibidas (2 de 3 cajas)')
        costo = num(val("SELECT costo_actual FROM gestion__productos_costos WHERE empresa_id = 2 AND producto_id = %d" % P1))
        check(abs(costo - 83.3333) < 0.00005, 'el costo de la caja de $ 1.000 queda en $ 83,3333 la unidad (no 83,33)', str(costo))
        html = ajax('facturas_proveedores_print.php', dict(factura_proveedor_id=f, empresa_idx=2), 'GET')
        html = html.get('_raw', '') if isinstance(html, dict) else ''
        check('SIM Caja x 12 (12 u)' in html, 'la impresión muestra las cajas', html[:120])
    r = fp_ajax(dict(accion='agregar', comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=proximo_nro_proveedor(7, 940000),
                     sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1,
                     tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, impuestos_adicionales='[]',
                     detalles=json.dumps([linea(P2, 1, 100, presentacion_id=PR, presentacion_cantidad=1, presentacion_precio=100)]),
                     **totales([linea(P2, 1, 100)])))
    check(not ok_resp(r) and 'no es de ese producto' in err(r), 'factura: una presentación de otro producto se rechaza', err(r))

# ---------------------------------------------------------------------------------------------
# Paso 8-4: presentación en la que vende el proveedor y carga de facturas desde archivo.
LECTURA_PHP = r"""<?php
define('DB_NAME', $argv[1]);
set_error_handler(function ($no, $str) { return strpos($str, 'already defined') !== false; });
$raiz = $argv[2];
require_once "$raiz/.env";
require_once "$raiz/modules/gestion/facturas_proveedores_model.php";
require_once "$raiz/modules/gestion/facturas_proveedores_lectura_model.php";
$r = new ResultadoExtraccion();
$r->set('neto_gravado', (float) $argv[5], 'alta', 'test');
$r->set('iva_alicuotas', ['21.00' => round((float) $argv[5] * 0.21, 2)], 'alta', 'test');
$r->items = json_decode($argv[6], true);
[$detalles, $items_ref, $avisos, $error] = fplArmarLineas($conexion, (int) $argv[3], $r, (int) $argv[4], 'A');
echo json_encode(['detalles' => $detalles, 'avisos' => $avisos, 'error' => $error]);
"""


def lectura(neto, items):
    """fplArmarLineas (core/facturas_lectura) sobre la copia, con una lectura armada a mano."""
    with tempfile.NamedTemporaryFile('w', suffix='.php', delete=False, encoding='utf-8') as f:
        f.write(LECTURA_PHP)
        ruta = f.name
    raiz = os.path.abspath(os.path.join(AQUI, '..', '..', '..'))
    try:
        r = subprocess.run([PHP, ruta, DB, raiz, '2', str(PROV), str(neto), json.dumps(items)],
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=120)
    finally:
        os.unlink(ruta)
    s = r.stdout
    try:
        return json.loads(s[s.find('{'):])
    except Exception:
        return {'_raw': s[-1500:], '_stderr': r.stderr[-1500:]}


caso('Compra por presentación: presentación del proveedor y lectura de facturas')
tiene_col = int(val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestion__productos_proveedores' "
                    "AND COLUMN_NAME = 'presentacion_id'")) == 1
if not tiene_col:
    brecha('falta 20261122_compra_presentaciones_02 en la copia', 'correr con --scripts ..._02.sql')
elif CTX.get('ok'):
    PR = CTX['pres']
    # Restos de una corrida cortada antes de la limpieza
    sql("UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE empresa_id = 2 AND producto_id = %d "
        "AND presentacion_nombre LIKE 'SIM %%' AND presentacion_id <> %d" % (P1, PR))
    # Una segunda presentación de compra (por defecto de la empresa) para ver que la del proveedor gana
    sql("UPDATE gestion__productos_presentaciones SET es_compra_defecto = 0 WHERE presentacion_id = %d" % PR)
    sql("INSERT INTO gestion__productos_presentaciones (empresa_id, producto_id, presentacion_nombre, factor, aplica_compra, aplica_venta, "
        "es_compra_defecto, es_venta_defecto, orden, tabla_estado_registro_id) VALUES (2, %d, 'SIM Pack x 6', 6, 1, 0, 1, 0, 98, 1)" % P1)
    CTX['pres6'] = int(val("SELECT MAX(presentacion_id) FROM gestion__productos_presentaciones WHERE producto_id = %d AND presentacion_nombre = 'SIM Pack x 6'" % P1))
    CTX['vinc'] = uno("SELECT producto_proveedor_id id, codigo_proveedor cod, presentacion_id pres FROM gestion__productos_proveedores "
                      "WHERE empresa_id = 2 AND entidad_id = %d AND producto_id = %d AND tabla_estado_registro_id = 1" % (PROV, P1))
    r = oc_ajax(dict(accion='cpre_presentaciones', producto_id=P1, entidad_id=PROV), 'GET')
    d = (r.get('data') or []) if ok_resp(r) else []
    check(d and int(d[0]['presentacion_id']) == CTX['pres6'] and not any(int(x.get('es_proveedor') or 0) for x in d),
          'sin presentación del proveedor: primero la de compra por defecto, ninguna es_proveedor', str(d)[:200])
    if CTX['vinc']:
        sql("UPDATE gestion__productos_proveedores SET presentacion_id = %d, codigo_proveedor = 'SIMCJ12' WHERE producto_proveedor_id = %d" % (PR, int(CTX['vinc']['id'])))
        r = oc_ajax(dict(accion='cpre_presentaciones', producto_id=P1, entidad_id=PROV), 'GET')
        d = (r.get('data') or []) if ok_resp(r) else []
        check(d and int(d[0]['presentacion_id']) == PR and int(d[0].get('es_proveedor') or 0) == 1,
              'OC: la presentación del proveedor va primero y marcada', str(d)[:200])
        r = fp_ajax(dict(accion='cpre_presentaciones', producto_id=P1, entidad_id=PROV), 'GET')
        d = (r.get('data') or []) if ok_resp(r) else []
        check(d and int(d[0]['presentacion_id']) == PR and int(d[0].get('es_proveedor') or 0) == 1, 'factura: también', str(d)[:200])
        r = fp_ajax(dict(accion='cpre_presentaciones', producto_id=P1, entidad_id=999999), 'GET')
        d = (r.get('data') or []) if ok_resp(r) else []
        check(d and not any(int(x.get('es_proveedor') or 0) for x in d), 'otro proveedor: sin marca', str(d)[:200])
        # Lectura: 2 cajas a $ 9.000 con el código del proveedor + un ítem sin código (imputación)
        L = lectura(20000, [{'codigo': 'SIMCJ12', 'descripcion': 'Caja', 'cantidad': 2, 'precio_unitario': 9000, 'alicuota': 21, 'subtotal': 18000},
                            {'codigo': 'ZZZ', 'descripcion': 'Otro', 'cantidad': 1, 'precio_unitario': 2000, 'alicuota': 21, 'subtotal': 2000}])
        dets = L.get('detalles') or []
        caja = next((x for x in dets if x.get('codigo_proveedor') == 'SIMCJ12'), {})
        check(int(caja.get('presentacion_id') or 0) == PR and num(caja.get('cantidad')) == 24 and num(caja.get('presentacion_cantidad')) == 2
              and abs(num(caja.get('precio_unitario')) - 750) < 0.000001 and num(caja.get('presentacion_precio')) == 9000 and num(caja.get('neto_gravado')) == 18000,
              'lectura: el ítem del proveedor entra como 2 cajas = 24 u a $ 750, neto $ 18.000', str(L)[:400])
        otras = [x for x in dets if x.get('codigo_proveedor') != 'SIMCJ12']
        check(otras and not any(x.get('presentacion_id') for x in otras) and abs(sum(num(x['neto_gravado']) for x in otras) - 2000) < 0.01,
              'lectura: el resto va a la línea de imputación, sin presentación', str(otras)[:300])
        check(any('presentación del proveedor' in a for a in (L.get('avisos') or [])), 'lectura: aviso de los ítems en presentación', str(L.get('avisos'))[:200])
        # Lo que arma la lectura se guarda por el formulario (agregar) y queda con la foto
        if caja:
            caja2 = dict(caja, ordenes_compra_detalle_id=None)
            r = fp_ajax(dict(accion='agregar', comprobante_tipo_id=34, comprobante_pv=7, comprobante_nro=proximo_nro_proveedor(7, 940000),
                             sucursal_id=1, deposito_id=1, entidad_id=PROV, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY, moneda_id=1,
                             tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, impuestos_adicionales='[]',
                             detalles=json.dumps([caja2]), **totales([linea(P1, 24, 750)])))
            f = r.get('factura_proveedor_id') if isinstance(r, dict) else None
            l = uno("SELECT cantidad, presentacion_id, presentacion_cantidad, neto_gravado FROM gestion__facturas_proveedores_detalle WHERE factura_proveedor_id = %d" % int(f or 0)) if f else None
            check(l and num(l['cantidad']) == 24 and int(l['presentacion_id'] or 0) == PR and num(l['presentacion_cantidad']) == 2 and num(l['neto_gravado']) == 18000,
                  'la línea leída se guarda con la foto (24 u = 2 cajas)', err(r) + ' ' + str(l))
        # Apagado: la lectura no toca las líneas
        sql("UPDATE conf__empresas SET usa_compra_presentaciones = 0 WHERE empresa_id = 2")
        L = lectura(18000, [{'codigo': 'SIMCJ12', 'descripcion': 'Caja', 'cantidad': 2, 'precio_unitario': 9000, 'alicuota': 21, 'subtotal': 18000}])
        caja = next((x for x in (L.get('detalles') or []) if x.get('codigo_proveedor') == 'SIMCJ12'), {})
        check(caja and num(caja.get('cantidad')) == 2 and 'presentacion_id' not in caja, 'apagado: la lectura carga 2 a $ 9.000, como siempre', str(L)[:300])
        r = oc_ajax(dict(accion='cpre_presentaciones', producto_id=P1, entidad_id=PROV), 'GET')
        check(ok_resp(r) and r.get('data') == [], 'apagado: la pantalla no recibe presentaciones', str(r)[:200])
        sql("UPDATE conf__empresas SET usa_compra_presentaciones = 1 WHERE empresa_id = 2")
    else:
        brecha('el proveedor del caso no tiene vínculo con el producto en la copia', str(PROV))

# ---------------------------------------------------------------------------------------------
caso('Compra por presentación: limpieza')
if CTX.get('ind') is not None:
    sql("UPDATE gestion__productos_presentaciones SET tabla_estado_registro_id = 2 WHERE presentacion_id IN (%d, %d)" % (CTX.get('pres', 0), CTX.get('pres6', 0)))
    if CTX.get('vinc'):
        v = CTX['vinc']
        sql("UPDATE gestion__productos_proveedores SET presentacion_id = %s, codigo_proveedor = %s WHERE producto_proveedor_id = %d"
            % (v['pres'] if v['pres'] is not None else 'NULL', "'%s'" % v['cod'] if v['cod'] is not None else 'NULL', int(v['id'])))
    sql("UPDATE conf__empresas SET usa_presentaciones = %s, usa_compra_presentaciones = %s WHERE empresa_id = 2" % (CTX['ind']['p'], CTX['ind']['c']))
    check(True, 'indicadores de la copia como estaban')
