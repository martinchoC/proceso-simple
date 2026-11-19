"""
Simulación integral profunda en Empresa 1000 (GINESI MAURO NICOLAS, módulo 5 gestion_normal)
sobre la base local (gestion_multipyme), cubriendo el ciclo operativo completo:
  - OPERACIONES FORMALES / FISCALES (ARCA - PV 306, Códigos de Comprobante Oficiales con IVA discriminado)
  - OPERACIONES INFORMALES / INTERNAS (NO FISCAL - PV 307 código 100001, Factura X, Remito X, Recibo X, OP X)

Circuitos incluidos:
  1. Gestión de Precios de Proveedor (proveedor_precios_ajax)
  2. Compra Formal por Presentación (OC -> Factura Proveedor A -> Stock)
  3. Pago Formal a Proveedor (OP PV 306 -> Imputación Factura Compra A -> Cta Cte)
  4. Venta Mayorista Formal por Carrito B2B (casalucho-carrito con escala y presentación)
  5. Venta Formal en la Calle por App del Vendedor (santipasso / usuario 15)
  6. Distribución, Hoja de Ruta, Remito Formal Parcial (Remito A) y Factura A
  7. Cobranza Formal de Cliente (Recibo RCX PV 306 -> Imputación Factura A)
  8. Venta Minorista Formal por Mostrador (Factura A / Consumidor Final contado)
  9. Compra Informal / Mercado Interno (Recepción por Remito X en PV 100001 -> Stock)
 10. Pago Informal a Proveedor (OP PV 307 No Fiscal en efectivo)
 11. Venta Informal por Remito de Entrega (Remito X en PV 307 -> Descuento Stock)
 12. Venta Informal Facturada (Factura X en PV 307 -> Saldo Cta Cte / Gestión interna)
 13. Cobranza Informal (Recibo X en PV 307 -> Cancelación Factura X)
 14. Venta Minorista Informal por Mostrador (Factura X en PV 307 de contado)
"""
import datetime
import json
import os
import subprocess
import sys
import tempfile

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SIM_DIR = os.path.join(BASE_DIR, 'tests', 'simulacion')
sys.path.insert(0, SIM_DIR)
os.environ['SIM_BASE_LOCAL'] = '1'

from simlib import (  # noqa: E402
    PHP, DB, HOY, ajax, sql, uno, val, num, ok_resp, err, stock, check, caso, RESULTADOS
)

ERP_DIR = os.path.abspath(os.path.join(BASE_DIR, '..'))
CARRITO_DIR = os.path.abspath(os.path.join(ERP_DIR, '..', 'casalucho-carrito', 'ecommerce_app'))
CARRITO_DRIVER = os.path.join(BASE_DIR, 'tests', 'carrito', 'carrito_driver.php')

EID = 1000
MODULO = 'gestion_normal'
SUC_ID = 305
BOCA_ID = 305
PV_FISCAL = 306     # Punto de Venta 0001 (WSFE / Fiscal)
PV_INFORMAL = 307   # Punto de Venta 100001 (NO_FISCAL / Informal)
PROV_ID = 5771
CLI_CARRITO = 5783  # Almacén Don José
CLI_APP = 5784      # Kiosco y Polirrubro Central
USU_VENDEDOR = 15   # santipasso (Santiago Passoni)
PERFIL_VENTAS = 592
VENDEDOR_ID = 5785
P1 = 6281           # INF-001 Yerba mate con palo 1 kg (Caja x 10 = id 5)
P1_CAJA = 5
P2 = 6282           # INF-002 Yerba mate con palo 500 g (Caja x 20 = id 7)
P2_CAJA = 7
P3 = 6283           # INF-003 Yerba mate sin palo 1 kg (Caja x 10 = id 9)
P3_CAJA = 9

REGISTRO = {
    'fecha_ejecucion': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    'empresa_id': EID,
    'empresa_nombre': 'GINESI MAURO NICOLAS',
    'modulo': MODULO,
    'puntos_venta': {
        'fiscal_blanco': {'punto_venta_id': PV_FISCAL, 'codigo_fiscal': 1, 'sistema_emision': 'WSFE'},
        'informal_negro': {'punto_venta_id': PV_INFORMAL, 'codigo_fiscal': 100001, 'sistema_emision': 'NO_FISCAL'}
    },
    'maestros': {},
    'casos': [],
    'stock_snapshot': {},
}


def stock_emp(prod):
    return num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id = {EID} AND boca_id = {BOCA_ID} AND producto_id = {int(prod)}") or 0)


def gn_ajax(archivo, params, method='POST', usuario_id=1):
    return ajax(archivo, dict(params, empresa_idx=EID, _empresa_id=EID), method, usuario_id=usuario_id, modulo=MODULO)


def app_ajax(accion, params=None, method='POST'):
    payload = dict(params or {}, accion=accion, empresa_idx=EID)
    php_code = (
        "session_start();"
        f"$_SESSION['logueado']=true;$_SESSION['usuario_id']={USU_VENDEDOR};$_SESSION['empresa_id']={EID};"
        "$_SESSION['csrf_token']='sim-csrf';$_SERVER['HTTP_X_CSRF_TOKEN']='sim-csrf';"
        f"$_SERVER['REQUEST_METHOD']={json.dumps(method)};"
        f"$p=json_decode({json.dumps(json.dumps(payload))},true);"
        "if($_SERVER['REQUEST_METHOD']==='GET'){$_GET=$p;}else{$_POST=$p;}$_REQUEST=$p;"
        f"require {json.dumps(os.path.join(ERP_DIR, 'app_vendedor', 'api.php'))};"
    )
    r = subprocess.run([PHP, '-r', php_code], capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=60)
    s = r.stdout
    try:
        return json.loads(s[s.find('{'):])
    except Exception:
        return {'success': False, 'message': (s + '\n' + r.stderr)[-500:]}


def carrito_emp1000(ops, entidad_id):
    env_vars = {}
    with open(os.path.join(ERP_DIR, '.env'), 'r', encoding='utf-8') as f:
        for line in f:
            if "define('" in line:
                parts = line.split("'")
                if len(parts) >= 4:
                    env_vars[parts[1]] = parts[3]
    env_tmp = tempfile.NamedTemporaryFile('w', suffix='.env', delete=False, encoding='utf-8')
    ops_tmp = tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8')
    try:
        env_tmp.write('\n'.join([
            f"DB_HOST={env_vars.get('IP_HOST', 'localhost')}",
            "DB_PORT=3306",
            f"DB_NAME={DB}",
            f"DB_USER={env_vars.get('DB_USER', 'root')}",
            f"DB_PASS=\"{env_vars.get('DB_PASS', '')}\"",
            "APP_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
            "APP_ENV=testing",
            "APP_DEBUG=true",
            "SESSION_SECURE=false",
            f"ECOM_EMPRESA_ID={EID}",
            "ECOM_MODULO_ID=5",
            f"ECOM_SUCURSAL_ID={SUC_ID}",
            "ECOM_MONEDA_ID=1",
            "ECOM_COMPROBANTE_TIPO_PEDIDO_ID=433",
            "ECOM_TABLA_ORIGEN_PEDIDOS_ID=82",
            "ECOM_ENTIDAD_DOC_COLUMNA=cuit",
            f"ERP_CORE_PATH={ERP_DIR}",
        ]) + '\n')
        env_tmp.close()
        json.dump({'usuario_id': 1, 'entidad_id': entidad_id, 'ops': ops}, ops_tmp)
        ops_tmp.close()

        env = dict(os.environ, ECOM_TEST_ENV=env_tmp.name, ECOM_TEST_BASE=DB, ECOM_CARRITO_DIR=CARRITO_DIR)
        r = subprocess.run([PHP, CARRITO_DRIVER, ops_tmp.name], capture_output=True, text=True, encoding='utf-8', errors='replace', env=env, cwd=CARRITO_DIR, timeout=120)
        s = r.stdout
        try:
            return json.loads(s[s.find('{'):])
        except Exception:
            return {'_error': (s + '\n' + r.stderr)[-1200:], 'ops': []}
    finally:
        for p in (env_tmp.name, ops_tmp.name):
            if os.path.exists(p):
                os.unlink(p)


def d(r):
    return (r.get('data') if isinstance(r, dict) else None) or {}


def calc_totales(dets):
    sub = round(sum(float(x['cantidad']) * float(x['precio_unitario']) for x in dets), 2)
    imp = round(sum(float(x['cantidad']) * float(x['precio_unitario']) * float(x.get('iva_porcentaje', 21)) / 100.0 for x in dets), 2)
    return {'subtotal': sub, 'descuentos': 0, 'impuestos': imp, 'total': round(sub + imp, 2)}


def linea_std(prod, cant, pu, pres_id=None, pres_cant=None, pres_pu=None, sin_iva=False):
    neto = round(cant * pu, 2)
    iva = 0.0 if sin_iva else round(neto * 0.21, 2)
    item = {
        'producto_id': prod,
        'cantidad': cant,
        'precio_unitario': pu,
        'precio_unitario_bruto': pu,
        'descuento_general_pct': 0,
        'descuento_general': 0,
        'iva_alicuota_id': 25 if not sin_iva else 1,
        'iva_porcentaje': 21 if not sin_iva else 0,
        'neto_gravado': neto,
        'iva_importe': iva,
        'total_linea': round(neto + iva, 2),
    }
    if pres_id:
        item['presentacion_id'] = pres_id
        item['presentacion_cantidad'] = pres_cant
        item['presentacion_precio'] = pres_pu
    return item


def estado_de(tabla, pk, rid):
    return int(val(f"SELECT tabla_estado_registro_id FROM {tabla} WHERE {pk} = {int(rid or 0)}") or 0)


# =============================================================================================
# 0. PREPARACIÓN DE MAESTROS EN EMPRESA 1000
# =============================================================================================
caso('0. Preparación de maestros y puntos de venta en Empresa 1000')
REGISTRO['stock_snapshot']['inicial'] = {
    'INF-001 (6281)': stock_emp(P1),
    'INF-002 (6282)': stock_emp(P2),
    'INF-003 (6283)': stock_emp(P3),
}

# Permisos para Santiago Passoni (perfil 592) en página 555 (Pedidos)
for fid in (5241, 5242, 5247, 5250):
    sql(f"INSERT INTO conf__empresas_perfiles_funciones (empresa_id, empresa_perfil_id, pagina_funcion_id, asignado) "
        f"SELECT {EID}, {PERFIL_VENTAS}, {fid}, 1 FROM DUAL "
        f"WHERE NOT EXISTS (SELECT 1 FROM conf__empresas_perfiles_funciones WHERE empresa_perfil_id = {PERFIL_VENTAS} AND pagina_funcion_id = {fid})")

# Condiciones comerciales vigentes para 5783 y 5784 (Lista 19 Mayorista)
for cid in (CLI_CARRITO, CLI_APP):
    existe_cond = int(val(f"SELECT COUNT(*) FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {cid} AND tabla_estado_registro_id = 1") or 0)
    if existe_cond == 0:
        sql(f"INSERT INTO gestion__entidades_condiciones_clientes (entidad_id, lista_precio_id, condicion_pago_id, cliente_descuento_general, limite_credito, f_desde, tabla_estado_registro_id) "
            f"VALUES ({cid}, 19, 32, 0, 5000000, CURDATE(), 1)")

# Precio propio por presentación para INF-002 Caja x 20 (id 7) e INF-003 Caja x 10 (id 9) en Lista 19
for pr_id, p_id, pr_val in [(P2_CAJA, P2, 41000), (P3_CAJA, P3, 44000)]:
    existe_lp_pres = int(val(f"SELECT COUNT(*) FROM gestion__listas_precios_presentaciones WHERE empresa_id = {EID} AND lista_precio_id = 19 AND presentacion_id = {pr_id} AND tabla_estado_registro_id = 1") or 0)
    if existe_lp_pres == 0:
        sql(f"INSERT INTO gestion__listas_precios_presentaciones (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, tabla_estado_registro_id) "
            f"VALUES ({EID}, 19, {pr_id}, {p_id}, {pr_val}, CURDATE(), 1)")

# Regla de escala por cantidad (INF-001 >= 20 u -> 5% dto)
regla_escala_id = val(f"SELECT regla_venta_id FROM gestion__precios_reglas_venta WHERE empresa_id = {EID} AND nombre = 'SIM_INT Escala INF-001' AND tabla_estado_registro_id = 1 LIMIT 1")
if not regla_escala_id:
    r = gn_ajax('precios_reglas_venta_ajax.php', dict(accion='agregar', pagina_idx=646, tipo='ESCALA', nombre='SIM_INT Escala INF-001',
                producto_id=P1, f_desde=HOY, tramos=json.dumps([{'cantidad_desde': 20, 'descuento_pct': 5}])))
    regla_escala_id = d(r).get('regla_venta_id')

# Zona de distribución y asignación a los dos clientes
zona_id = val(f"SELECT zona_id FROM gestion__distribucion_zonas WHERE empresa_id = {EID} AND nombre = 'Zona Centro' AND tabla_estado_registro_id = 1 LIMIT 1")
if not zona_id:
    z = gn_ajax('distribucion_config_ajax.php', dict(accion='zona_agregar', pagina_idx=648, nombre='Zona Centro', codigo='ZC', dias_reparto='1,2,3,4,5,6,7'))
    zona_id = d(z).get('zona_id')
gn_ajax('distribucion_config_ajax.php', dict(accion='zona_asignar', pagina_idx=648, zona_id=zona_id, clientes=json.dumps([CLI_CARRITO, CLI_APP]), fecha=HOY))

# Verificar ambos puntos de venta (306 Fiscal y 307 No Fiscal)
pv_fisc = uno(f"SELECT punto_venta_id, nombre, codigo_fiscal, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id = {PV_FISCAL}")
pv_inf = uno(f"SELECT punto_venta_id, nombre, codigo_fiscal, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id = {PV_INFORMAL}")
check(bool(pv_fisc and pv_inf), 'Puntos de Venta listos (PV 306 Fiscal y PV 307 Informal)', f"PV 306: {pv_fisc}, PV 307: {pv_inf}")

REGISTRO['maestros'] = {
    'pv_fiscal_306': pv_fisc,
    'pv_informal_307': pv_inf,
    'lista_precio_mayorista_id': 19,
    'lista_precio_mostrador_id': 20,
    'regla_escala_id': regla_escala_id,
    'zona_id': zona_id,
}


# =============================================================================================
# CASO 1: GESTIÓN DE PRECIOS DE PROVEEDOR
# =============================================================================================
caso('1. Precios de Proveedor (proveedor_precios_ajax)')
r_prv_precio = gn_ajax('proveedor_precios_ajax.php', dict(
    accion='agregar', pagina_idx=592, entidad_id=PROV_ID, producto_id=P1,
    precio_lista=3350.00, moneda_id=1, f_vigencia_desde=HOY
))
check(ok_resp(r_prv_precio), 'Precio de proveedor actualizado correctamente (P1 a $3.350)', err(r_prv_precio))


# =============================================================================================
# CASO 2: COMPRA FORMAL POR PRESENTACIÓN (OC -> Factura Proveedor A -> Stock)
# =============================================================================================
caso('2. Compra Formal por presentación: OC -> Factura Proveedor A -> Stock')
s1_pre, s2_pre = stock_emp(P1), stock_emp(P2)

oc_dets = [
    linea_std(P1, 2, 33500, pres_id=P1_CAJA, pres_cant=2, pres_pu=33500),   # 2 Cajas x 10 = 20 u
    linea_std(P2, 1, 34000, pres_id=P2_CAJA, pres_cant=1, pres_pu=34000),   # 1 Caja x 20  = 20 u
]
r_oc = gn_ajax('ordenes_compra_ajax.php', dict(accion='agregar', pagina_idx=551, comprobante_tipo_id=443, sucursal_id=SUC_ID, punto_venta_id=PV_FISCAL,
               entidad_id=PROV_ID, f_emision=HOY, moneda_id=1, tipo_cambio=1, observaciones='Compra Formal de Yerba por caja',
               detalles=json.dumps(oc_dets), **calc_totales(oc_dets)))
oc_id = r_oc.get('orden_compra_id') if isinstance(r_oc, dict) else None
check(ok_resp(r_oc) and oc_id, 'OC Formal creada en Borrador', err(r_oc))

fp_id = None
if oc_id:
    gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='confirmar'))
    gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='pendiente_recepcion'))

    r_oc_lin = gn_ajax('facturas_proveedores_ajax.php', dict(accion='obtener_oc_lineas', pagina_idx=557, orden_compra_id=oc_id, entidad_id=PROV_ID), 'GET')
    oc_lineas = (r_oc_lin.get('data') or {}).get('lineas') or []
    fp_dets = []
    for l in oc_lineas:
        pend = num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad'))
        pu = num(l.get('precio_unitario'))
        pres_id = int(l.get('presentacion_id') or 0) or None
        pres_cant = num(l.get('presentacion_cantidad')) if pres_id else None
        pres_pu = num(l.get('presentacion_precio')) if pres_id else None
        item = linea_std(int(l['producto_id']), pend, pu, pres_id=pres_id, pres_cant=pres_cant, pres_pu=pres_pu)
        item['ordenes_compra_detalle_id'] = int(l['ordenes_compra_detalle_id'])
        fp_dets.append(item)

    nro_fp = str(91000 + int(oc_id))
    r_fp = gn_ajax('facturas_proveedores_ajax.php', dict(accion='agregar', pagina_idx=557, comprobante_tipo_id=452, comprobante_pv=1, comprobante_nro=nro_fp,
                   sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
                   moneda_id=1, tipo_cambio=1, condicion_pago_id=32, orden_compra_id=oc_id, descuento_general_pct=0,
                   observaciones='Factura Proveedor A (Formal) Recepción OC ' + str(oc_id),
                   detalles=json.dumps(fp_dets), impuestos_adicionales='[]', **calc_totales(fp_dets)))
    fp_id = r_fp.get('factura_proveedor_id') if isinstance(r_fp, dict) else None
    if fp_id:
        gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='aprobar'))
    r_fp_conf = gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='confirmar')) if fp_id else {}
    s1_post, s2_post = stock_emp(P1), stock_emp(P2)
    check(ok_resp(r_fp) and ok_resp(r_fp_conf) and s1_post > s1_pre and s2_post > s2_pre,
          f'Factura Proveedor A confirmada y stock actualizado (+20 INF-001, +20 INF-002)', err(r_fp) + ' ' + err(r_fp_conf))


# =============================================================================================
# CASO 3: PAGO FORMAL A PROVEEDOR (OP PV 306 -> Imputación Factura Compra A)
# =============================================================================================
caso('3. Pago Formal a Proveedor (OP PV 306 -> Imputación Factura Compra A)')
pago_id = None
op_nro = None
if fp_id:
    comp_fp = uno(f"SELECT comprobante_id, total AS importe_total FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {fp_id}")
    if comp_fp and comp_fp.get('comprobante_id'):
        comp_id = int(comp_fp['comprobante_id'])
        total_pago = float(comp_fp['importe_total'])
        r_pago = gn_ajax('pagos_ajax.php', dict(
            accion='guardar', pagina_idx=606, entidad_id=PROV_ID, sucursal_id=SUC_ID, punto_venta_id=PV_FISCAL,
            f_emision=HOY, f_pago=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='Pago Formal OP Factura Compra A',
            imputaciones=json.dumps([{'comprobante_id': comp_id, 'importe': total_pago}]),
            medios=json.dumps([{'medio_pago_id': 44, 'importe': total_pago}])
        ))
        pago_id = r_pago.get('pago_id') if isinstance(r_pago, dict) else None
        check(ok_resp(r_pago) and pago_id, 'Orden de Pago Formal guardada', err(r_pago))
        if pago_id:
            r_pago_c = gn_ajax('pagos_ajax.php', dict(accion='confirmar', pagina_idx=606, id=pago_id))
            op_nro = r_pago_c.get('comprobante_nro')
            check(ok_resp(r_pago_c), f'Orden de Pago Formal confirmada (OP 0001 N° {op_nro})', err(r_pago_c))


# =============================================================================================
# CASO 4: VENTA MAYORISTA FORMAL POR CARRITO B2B (con Escala y Cajas)
# =============================================================================================
caso('4. Venta Mayorista Formal por Carrito (casalucho-carrito)')
r_car = carrito_emp1000([
    {'op': 'vaciar'},
    {'op': 'agregar', 'producto_id': P1, 'cantidad': 25},                          # 25 u INF-001 -> escala 5%
    {'op': 'agregar', 'producto_id': P2, 'presentacion_id': P2_CAJA, 'cantidad': 2}, # 2 Cajas x 20 INF-002 = 40 u
    {'op': 'resumen'},
    {'op': 'confirmar', 'observaciones': 'Pedido Formal desde Carrito B2B'},
], CLI_CARRITO)
ops_c = r_car.get('ops') or []
conf_c = ops_c[4] if len(ops_c) >= 5 else {}
ped_c = conf_c.get('venta_pedido_id')
ped_c_row = uno(f"SELECT comprobante_nro, origen, tabla_estado_registro_id, total FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped_c or 0)}") if ped_c else {}
check(bool(ped_c) and ped_c_row.get('origen') == 'CARRITO' and int(ped_c_row.get('tabla_estado_registro_id') or 0) == 5,
      'Pedido del carrito confirmado (origen CARRITO, estado 5)', json.dumps(r_car, ensure_ascii=False)[:300])


# =============================================================================================
# CASO 5: VENTA FORMAL POR APP DEL VENDEDOR (Santiago Passoni)
# =============================================================================================
caso('5. Venta Formal por App del Vendedor (santipasso)')
r_app_b = app_ajax('guardar', {
    'entidad_id': CLI_APP,
    'sucursal_id': SUC_ID,
    'items': json.dumps([
        {'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1},
        {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5},
    ]),
    'observaciones': 'Pedido Formal App Vendedor',
    'confirmar': 0,
})
ped_a = d(r_app_b).get('venta_pedido_id')
r_app_c = app_ajax('guardar', {
    'entidad_id': CLI_APP,
    'sucursal_id': SUC_ID,
    'pedido_id': ped_a,
    'items': json.dumps([
        {'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1},
        {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5},
    ]),
    'observaciones': 'Pedido Formal App Vendedor (confirmado)',
    'confirmar': 1,
}) if ped_a else {}
ped_a_row = uno(f"SELECT comprobante_nro, origen, tabla_estado_registro_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped_a or 0)}") if ped_a else {}
check(ok_resp(r_app_c) and ped_a_row.get('origen') == 'APP' and int(ped_a_row.get('tabla_estado_registro_id') or 0) == 5,
      'App vendedor: pedido formal confirmado (origen APP, estado 5)', str(r_app_c)[:300])


# =============================================================================================
# CASO 6: DISTRIBUCIÓN, HOJA DE RUTA, REMITO FORMAL (A) Y FACTURA A
# =============================================================================================
caso('6. Distribución, Remito Formal Parcial (A) y Factura A')
peds = [p for p in (ped_c, ped_a) if p]
for p in peds:
    gn_ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=555, venta_pedido_id=p, accion_js='liberar_distribucion'))

r_hr = gn_ajax('distribucion_hojas_ajax.php', dict(accion='hoja_crear', pagina_idx=649, fecha_reparto=HOY, camion_id=1))
hoja_id = d(r_hr).get('hoja_id')
if hoja_id:
    gn_ajax('distribucion_hojas_ajax.php', dict(accion='agregar_pedidos', pagina_idx=649, hoja_id=hoja_id, pedidos=json.dumps(peds), forzar=1))
    gn_ajax('distribucion_hojas_ajax.php', dict(accion='confirmar', pagina_idx=649, hoja_id=hoja_id))

rem_id = None
fv_id = None
if ped_c:
    r_pend = gn_ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', pagina_idx=588, entidad_id=CLI_CARRITO, punto_venta_id=PV_FISCAL, pedido_id=ped_c), 'GET')
    pend = []
    for p in (r_pend if isinstance(r_pend, list) else []):
        pend += [p] if 'venta_pedido_detalle_id' in p else p.get('detalles', p.get('lineas', []))
    pend = [l for l in pend if int(l.get('venta_pedido_id', ped_c)) == int(ped_c)]
    por = {int(l['producto_id']): l for l in pend}
    if P1 in por and P2 in por:
        rdets = [
            {'venta_pedido_detalle_id': int(por[P1]['venta_pedido_detalle_id']), 'producto_id': P1, 'cantidad': 10,
             'precio_unitario_bruto': num(por[P1].get('precio_unitario_bruto') or por[P1].get('precio_unitario')),
             'descuento_general_pct': num(por[P1].get('descuento_general_pct')), 'iva_alicuota_id': 25, 'iva_porcentaje': 21},
            {'venta_pedido_detalle_id': int(por[P2]['venta_pedido_detalle_id']), 'producto_id': P2, 'cantidad': 20,
             'precio_unitario_bruto': num(por[P2].get('precio_unitario_bruto') or por[P2].get('precio_unitario')),
             'descuento_general_pct': num(por[P2].get('descuento_general_pct')), 'iva_alicuota_id': 25, 'iva_porcentaje': 21},
        ]
        r_rem = gn_ajax('ventas_remitos_ajax.php', dict(accion='agregar', pagina_idx=588, punto_venta_id=PV_FISCAL, comprobante_tipo_id=458,
                        entidad_id=CLI_CARRITO, f_emision=HOY, observaciones='Remito Formal A', detalles=json.dumps(rdets)))
        rem_id = (r_rem.get('venta_remito_id') or r_rem.get('id')) if isinstance(r_rem, dict) else None
        if rem_id:
            gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_id, accion_js='confirmar'))

        r_rp = gn_ajax('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', pagina_idx=556, entidad_id=CLI_CARRITO, venta_remito_id=rem_id), 'GET')
        lineas_f = []
        for x in (r_rp if isinstance(r_rp, list) else (r_rp.get('data', r_rp.get('remitos', [])) if isinstance(r_rp, dict) else [])):
            lineas_f += x.get('detalles', x.get('lineas', [x])) if isinstance(x, dict) else []
        lineas_f = [l for l in lineas_f if isinstance(l, dict) and l.get('venta_remito_detalle_id')]
        fdets = [
            {'producto_id': int(l['producto_id']), 'cantidad': num(l.get('pendiente') or l.get('cantidad_pendiente') or l.get('cantidad')),
             'precio_unitario': num(l.get('precio_unitario_bruto') or l.get('precio_unitario')),
             'descuento_general_pct': num(l.get('descuento_general_pct')),
             'iva_alicuota_id': 25, 'iva_porcentaje': 21,
             'venta_remito_detalle_id': int(l['venta_remito_detalle_id']),
             'venta_pedido_detalle_id': int(l.get('venta_pedido_detalle_id') or 0)}
            for l in lineas_f
        ]
        if fdets:
            r_fv = gn_ajax('ventas_facturas_ajax.php', dict(accion='agregar', pagina_idx=556, punto_venta_id=PV_FISCAL, sucursal_id=SUC_ID,
                           comprobante_tipo_id=434, entidad_id=CLI_CARRITO, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
                           condicion_pago_id=32, descuento_general_pct=0, observaciones='Factura A Formal desde remito', detalles=json.dumps(fdets)))
            fv_id = (r_fv.get('venta_factura_id') or d(r_fv).get('venta_factura_id') or r_fv.get('id')) if isinstance(r_fv, dict) else None
            if fv_id:
                gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fv_id, accion_js='confirma'))
                gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fv_id, accion_js='aprobada'))
            check(bool(fv_id), 'Factura A Formal emitida y confirmada en PV 306', str(r_fv))


# =============================================================================================
# CASO 7: COBRANZA FORMAL DE CLIENTE (Recibo RCX en PV 306)
# =============================================================================================
caso('7. Cobranza Formal de Cliente (Recibo RCX en PV 306)')
cob_id = None
if fv_id:
    comp_fv = uno(f"SELECT comprobante_id, importe_total FROM gestion__ventas_facturas WHERE venta_factura_id = {fv_id}")
    if comp_fv and comp_fv.get('comprobante_id'):
        comp_id = int(comp_fv['comprobante_id'])
        total_cob = float(comp_fv['importe_total'])
        r_cob = gn_ajax('cobranzas_ajax.php', dict(
            accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, punto_venta_id=PV_FISCAL,
            f_emision=HOY, f_cobro=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='Cobranza Formal Recibo RCX',
            imputaciones=json.dumps([{'comprobante_id': comp_id, 'importe': total_cob}]),
            medios=json.dumps([{'medio_pago_id': 44, 'importe': total_cob}])
        ))
        cob_id = r_cob.get('cobranza_id') if isinstance(r_cob, dict) else None
        if cob_id:
            r_cob_c = gn_ajax('cobranzas_ajax.php', dict(accion='confirmar', pagina_idx=605, id=cob_id))
            rcx_nro = r_cob_c.get('comprobante_nro')
            check(ok_resp(r_cob_c), f'Recibo de Cobro Formal confirmado (RCX N° {rcx_nro})', err(r_cob_c))


# =============================================================================================
# CASO 8: VENTA MINORISTA FORMAL POR MOSTRADOR (Consumidor Final de contado)
# =============================================================================================
caso('8. Venta Minorista Formal por Mostrador (Consumidor Final PV 306)')
r_most_f = gn_ajax('mostrador_ajax.php', dict(
    accion='facturar', pagina_idx=650, punto_venta_id=PV_FISCAL, entidad_id=0, consumidor_final_nombre='Cliente Mostrador Formal',
    items=json.dumps([{'producto_id': P1, 'cantidad': 2}]),
    medios=json.dumps([{'medio_pago_id': 44, 'importe': 10890.00}]),
    observaciones='Venta Mostrador Formal PV 306'
))
check(ok_resp(r_most_f) and d(r_most_f).get('estado') == 40,
      f"Factura Mostrador Formal emitida (Factura N° {d(r_most_f).get('comprobante_nro')})", err(r_most_f))


# =============================================================================================
# CASO 9: COMPRA INFORMAL / MERCADO INTERNO (Recepción por Remito X)
# =============================================================================================
caso('9. Compra Informal: Recepción por Remito X en PV 100001 (proveedor informal)')
s1_inf_pre = stock_emp(P1)
nro_rem_x = str(88100 + int(s1_inf_pre))
r_rx_compra = gn_ajax('facturas_proveedores_ajax.php', dict(
    accion='agregar', pagina_idx=557, comprobante_tipo_id=455, comprobante_pv=100001, comprobante_nro=nro_rem_x,
    sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
    moneda_id=1, tipo_cambio=1, condicion_pago_id=32, descuento_general_pct=0,
    observaciones='Recepción mercadería informal (Remito X)',
    detalles=json.dumps([linea_std(P1, 15, 3000.0, sin_iva=True)]),
    subtotal=45000.0, descuentos=0, impuestos=0, total=45000.0
))
rx_compra_id = r_rx_compra.get('factura_proveedor_id')
if rx_compra_id:
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=rx_compra_id, accion_js='aprobar'))
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=rx_compra_id, accion_js='confirmar'))
s1_inf_post = stock_emp(P1)
check(bool(rx_compra_id) and s1_inf_post == s1_inf_pre + 15,
      f'Recepción informal confirmada y stock incrementado (+15 INF-001: {s1_inf_pre}->{s1_inf_post})', str(r_rx_compra))


# =============================================================================================
# CASO 10: PAGO INFORMAL A PROVEEDOR (OP PV 307 No Fiscal)
# =============================================================================================
caso('10. Pago Informal a Proveedor (OP No Fiscal en PV 307)')
r_pago_x = gn_ajax('pagos_ajax.php', dict(
    accion='guardar', pagina_idx=606, entidad_id=PROV_ID, sucursal_id=SUC_ID, punto_venta_id=PV_INFORMAL,
    f_emision=HOY, f_pago=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='Pago Informal OP PV 307',
    imputaciones=json.dumps([]),
    medios=json.dumps([{'medio_pago_id': 44, 'importe': 45000.00}])
))
pago_x_id = r_pago_x.get('pago_id') if isinstance(r_pago_x, dict) else None
if pago_x_id:
    r_px_c = gn_ajax('pagos_ajax.php', dict(accion='confirmar', pagina_idx=606, id=pago_x_id))
    op_x_nro = r_px_c.get('comprobante_nro')
    check(ok_resp(r_px_c), f'Orden de Pago Informal confirmada (OP 100001 N° {op_x_nro})', err(r_px_c))


# =============================================================================================
# CASO 11: VENTA INFORMAL POR REMITO X (Despacho sin comprobante fiscal)
# =============================================================================================
caso('11. Venta Informal: Remito X de Salida en PV 307')
s1_rem_x_pre = stock_emp(P1)
r_rem_x = gn_ajax('ventas_remitos_ajax.php', dict(
    accion='agregar', pagina_idx=588, punto_venta_id=PV_INFORMAL, comprobante_tipo_id=459, # Remito de Salida X
    entidad_id=CLI_CARRITO, f_emision=HOY, observaciones='Remito X Entrega Informal',
    detalles=json.dumps([linea_std(P1, 5, 4000.0, sin_iva=True)])
))
rem_x_id = (r_rem_x.get('venta_remito_id') or r_rem_x.get('id')) if isinstance(r_rem_x, dict) else None
if rem_x_id:
    r_rx_conf = gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_x_id, accion_js='confirmar'))
s1_rem_x_post = stock_emp(P1)
check(bool(rem_x_id) and s1_rem_x_post == s1_rem_x_pre - 5,
      f'Remito X confirmado y stock descontado (-5 INF-001: {s1_rem_x_pre}->{s1_rem_x_post})', str(r_rem_x))


# =============================================================================================
# CASO 12: VENTA INFORMAL FACTURADA (Factura X en PV 307)
# =============================================================================================
caso('12. Venta Informal: Factura X en PV 307 (Sin CAE / Mercado Interno)')
r_fx = gn_ajax('ventas_facturas_ajax.php', dict(
    accion='agregar', pagina_idx=556, punto_venta_id=PV_INFORMAL, sucursal_id=SUC_ID,
    comprobante_tipo_id=437, # Factura X
    entidad_id=CLI_CARRITO, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
    condicion_pago_id=32, descuento_general_pct=0, observaciones='Factura X Venta Informal PV 307',
    detalles=json.dumps([linea_std(P1, 4, 4000.0, sin_iva=True)])
))
fx_id = (r_fx.get('venta_factura_id') or d(r_fx).get('venta_factura_id') or r_fx.get('id')) if isinstance(r_fx, dict) else None
if fx_id:
    gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fx_id, accion_js='confirma'))
    gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fx_id, accion_js='aprobada'))
fx_row = uno(f"SELECT comprobante_nro, punto_venta_id, comprobante_tipo_id, cae, tabla_estado_registro_id, importe_total FROM gestion__ventas_facturas WHERE venta_factura_id = {int(fx_id or 0)}") if fx_id else {}
check(bool(fx_id) and fx_row.get('cae') is None and int(fx_row.get('tabla_estado_registro_id') or 0) == 5,
      f"Factura X emitida y aprobada sin ARCA (N° {fx_row.get('comprobante_nro')}, CAE: {fx_row.get('cae')})", str(r_fx))


# =============================================================================================
# CASO 13: COBRANZA INFORMAL (Recibo X en PV 307 -> Imputación Factura X)
# =============================================================================================
caso('13. Cobranza Informal: Recibo X en PV 307')
cob_x_id = None
if fx_id:
    comp_fx = uno(f"SELECT comprobante_id, importe_total FROM gestion__ventas_facturas WHERE venta_factura_id = {fx_id}")
    if comp_fx and comp_fx.get('comprobante_id'):
        comp_id = int(comp_fx['comprobante_id'])
        total_cob = float(comp_fx['importe_total'])
        r_cob_x = gn_ajax('cobranzas_ajax.php', dict(
            accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, punto_venta_id=PV_INFORMAL,
            f_emision=HOY, f_cobro=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='Cobro Recibo X Informal PV 307',
            imputaciones=json.dumps([{'comprobante_id': comp_id, 'importe': total_cob}]),
            medios=json.dumps([{'medio_pago_id': 44, 'importe': total_cob}])
        ))
        cob_x_id = r_cob_x.get('cobranza_id') if isinstance(r_cob_x, dict) else None
        if cob_x_id:
            r_cx_c = gn_ajax('cobranzas_ajax.php', dict(accion='confirmar', pagina_idx=605, id=cob_x_id))
            rcx_x_nro = r_cx_c.get('comprobante_nro')
            check(ok_resp(r_cx_c), f'Recibo de Cobro Informal confirmado (Recibo 100001 N° {rcx_x_nro})', err(r_cx_c))


# =============================================================================================
# CASO 14: VENTA MINORISTA INFORMAL POR MOSTRADOR (Consumidor Final PV 307)
# =============================================================================================
caso('14. Venta Minorista Informal por Mostrador (Consumidor Final PV 307 Factura X)')
s1_most_x_pre = stock_emp(P1)
r_most_x = gn_ajax('mostrador_ajax.php', dict(
    accion='facturar', pagina_idx=650, punto_venta_id=PV_INFORMAL, entidad_id=0, consumidor_final_nombre='Cliente Minorista Informal',
    items=json.dumps([{'producto_id': P1, 'cantidad': 1}]),
    medios=json.dumps([{'medio_pago_id': 44, 'importe': 4500.00}]),
    observaciones='Venta Mostrador Informal PV 307 (Factura X de contado)'
))
most_x_data = d(r_most_x)
s1_most_x_post = stock_emp(P1)
check(ok_resp(r_most_x) and most_x_data.get('estado') == 40 and s1_most_x_post == s1_most_x_pre - 1,
      f"Factura Mostrador Informal emitida (Factura X N° {most_x_data.get('comprobante_nro')}, stock INF-001: {s1_most_x_pre}->{s1_most_x_post})",
      err(r_most_x))

REGISTRO['stock_snapshot']['final'] = {
    'INF-001 (6281)': stock_emp(P1),
    'INF-002 (6282)': stock_emp(P2),
    'INF-003 (6283)': stock_emp(P3),
}

REGISTRO['checks'] = [{'caso': c, 'chequeo': ch, 'ok': ok, 'detalle': det} for (c, ch, ok, det) in RESULTADOS]

out_json = os.path.join(BASE_DIR, 'scratch', 'sim_emp1000_resultado.json')
with open(out_json, 'w', encoding='utf-8') as f:
    json.dump(REGISTRO, f, ensure_ascii=False, indent=2, default=str)

fallas = [x for x in RESULTADOS if not x[2]]
print(f"\nRESUMEN GLOBAL: {len(RESULTADOS) - len(fallas)}/{len(RESULTADOS)} checks OK. JSON en {out_json}")
if fallas:
    for c, ch, _, det in fallas:
        print(f"  FALLA [{c}] {ch}: {det}")
