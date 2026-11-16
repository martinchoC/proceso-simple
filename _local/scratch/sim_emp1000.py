"""
Simulación integral en Empresa 1000 (GINESI MAURO NICOLAS, módulo 5 gestion_normal)
sobre la base local (gestion_multipyme), desde Compra por Presentación hasta Venta
por Carrito, App del Vendedor, Distribución, Remito y Factura.

Genera el registro JSON en _local/scratch/sim_emp1000_resultado.json.
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
PV_ID = 306
PROV_ID = 5771
CLI_CARRITO = 5783   # Almacén Don José
CLI_APP = 5784       # Kiosco y Polirrubro Central
USU_VENDEDOR = 15    # santipasso (Santiago Passoni)
PERFIL_VENTAS = 592
VENDEDOR_ID = 5785
P1 = 6281            # INF-001 Yerba mate con palo 1 kg (Caja x 10 = id 5)
P1_CAJA = 5
P2 = 6282            # INF-002 Yerba mate con palo 500 g (Caja x 20 = id 7)
P2_CAJA = 7
P3 = 6283            # INF-003 Yerba mate sin palo 1 kg (Caja x 10 = id 9)
P3_CAJA = 9

REGISTRO = {
    'fecha_ejecucion': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    'empresa_id': EID,
    'empresa_nombre': 'GINESI MAURO NICOLAS',
    'modulo': MODULO,
    'maestros': {},
    'casos': [],
    'stock_snapshot': {},
}


def stock_emp(prod):
    return num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id = {EID} AND boca_id = {BOCA_ID} AND producto_id = {int(prod)}") or 0)


def gn_ajax(archivo, params, method='POST', usuario_id=1):
    return ajax(archivo, dict(params, empresa_idx=EID, _empresa_id=EID), method, usuario_id=usuario_id, modulo=MODULO)


def app_ajax(accion, params=None, method='POST'):
    """Ejecuta app_vendedor/api.php con sesión real del vendedor (usuario 15, empresa 1000)."""
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
    """Ejecuta carrito_driver.php con un .env temporal apuntando a gestion_multipyme y empresa 1000."""
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


def linea_std(prod, cant, pu, pres_id=None, pres_cant=None, pres_pu=None):
    neto = round(cant * pu, 2)
    iva = round(neto * 0.21, 2)
    item = {
        'producto_id': prod,
        'cantidad': cant,
        'precio_unitario': pu,
        'precio_unitario_bruto': pu,
        'descuento_general_pct': 0,
        'descuento_general': 0,
        'iva_alicuota_id': 25,
        'iva_porcentaje': 21,
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
caso('0. Preparación de maestros y permisos en Empresa 1000')
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

REGISTRO['maestros'] = {
    'permisos_perfil_592_pag_555': [5241, 5242, 5247, 5250],
    'lista_precio_mayorista_id': 19,
    'precio_caja_inf002_lista19': 41000,
    'regla_escala_id': regla_escala_id,
    'zona_id': zona_id,
    'camion_id': 1,
}
check(bool(regla_escala_id and zona_id), 'Maestros y permisos listos en Empresa 1000', json.dumps(REGISTRO['maestros']))

# =============================================================================================
# CASO 1: COMPRA POR PRESENTACIÓN (OC -> FACTURA PROVEEDOR -> STOCK)
# =============================================================================================
caso('1. Compra por presentación: OC -> Factura Proveedor -> Stock')
s1_pre, s2_pre = stock_emp(P1), stock_emp(P2)

oc_dets = [
    linea_std(P1, 2, 32000, pres_id=P1_CAJA, pres_cant=2, pres_pu=32000),   # 2 Cajas x 10 = 20 u a $3.200
    linea_std(P2, 1, 34000, pres_id=P2_CAJA, pres_cant=1, pres_pu=34000),   # 1 Caja x 20  = 20 u a $1.700
]
r_oc = gn_ajax('ordenes_compra_ajax.php', dict(accion='agregar', pagina_idx=551, comprobante_tipo_id=443, sucursal_id=SUC_ID, punto_venta_id=PV_ID,
               entidad_id=PROV_ID, f_emision=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM_INT Compra Yerba por caja',
               detalles=json.dumps(oc_dets), **calc_totales(oc_dets)))
oc_id = r_oc.get('orden_compra_id') if isinstance(r_oc, dict) else None
check(ok_resp(r_oc) and oc_id, 'OC creada en Borrador', err(r_oc))

if oc_id:
    gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='confirmar'))
    gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='pendiente_recepcion'))
    oc_row = uno(f"SELECT comprobante_nro, tabla_estado_registro_id, total FROM gestion__ordenes_compra WHERE orden_compra_id = {oc_id}")
    check(int(oc_row['tabla_estado_registro_id']) == 7, 'OC confirmada y Pendiente de recepción (estado 7)', str(oc_row))

    # Factura de proveedor desde la OC
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

    nro_fp = str(90000 + int(oc_id))
    r_fp = gn_ajax('facturas_proveedores_ajax.php', dict(accion='agregar', pagina_idx=557, comprobante_tipo_id=452, comprobante_pv=1, comprobante_nro=nro_fp,
                   sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
                   moneda_id=1, tipo_cambio=1, condicion_pago_id=32, orden_compra_id=oc_id, descuento_general_pct=0,
                   observaciones='SIM_INT Recepción OC ' + str(oc_row['comprobante_nro']),
                   detalles=json.dumps(fp_dets), impuestos_adicionales='[]', **calc_totales(fp_dets)))
    fp_id = r_fp.get('factura_proveedor_id') if isinstance(r_fp, dict) else None
    if fp_id:
        gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='aprobar'))
    r_fp_conf = gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='confirmar')) if fp_id else {}
    s1_post, s2_post = stock_emp(P1), stock_emp(P2)
    check(ok_resp(r_fp) and ok_resp(r_fp_conf) and s1_post > s1_pre and s2_post > s2_pre,
          f'Factura Proveedor confirmada y stock actualizado (INF-001: {s1_pre}->{s1_post}, INF-002: {s2_pre}->{s2_post})',
          err(r_fp) + ' ' + err(r_fp_conf))

    REGISTRO['casos'].append({
        'numero': 1,
        'titulo': 'Compra por presentación (OC -> Factura de Proveedor -> Stock)',
        'datos': {
            'proveedor': 'Industrias Alimentarias del Sud S.A. (5771)',
            'orden_compra_id': oc_id,
            'oc_nro': oc_row['comprobante_nro'],
            'oc_estado': int(oc_row['tabla_estado_registro_id']),
            'factura_proveedor_id': fp_id,
            'fp_nro': f'0001-{nro_fp}',
            'fp_estado': estado_de('gestion__facturas_proveedores', 'factura_proveedor_id', fp_id) if fp_id else None,
            'stock_inf001': f'{s1_pre} -> {s1_post}',
            'stock_inf002': f'{s2_pre} -> {s2_post}',
        }
    })

# =============================================================================================
# CASO 2: VENTA MAYORISTA POR CARRITO (casalucho-carrito) CON ESCALA Y PRESENTACIÓN
# =============================================================================================
caso('2. Venta por Carrito (casalucho-carrito): Escala + Presentación')
r_car = carrito_emp1000([
    {'op': 'vaciar'},
    {'op': 'agregar', 'producto_id': P1, 'cantidad': 25},                          # 25 u INF-001 -> escala 5%
    {'op': 'agregar', 'producto_id': P2, 'presentacion_id': P2_CAJA, 'cantidad': 2}, # 2 Cajas x 20 INF-002 = 40 u
    {'op': 'resumen'},
    {'op': 'confirmar', 'observaciones': 'SIM_INT Pedido desde Carrito'},
], CLI_CARRITO)
ops_c = r_car.get('ops') or []
res_c = ops_c[3] if len(ops_c) >= 4 else {}
conf_c = ops_c[4] if len(ops_c) >= 5 else {}
ped_c = conf_c.get('venta_pedido_id')
ped_c_row = uno(f"SELECT comprobante_nro, origen, tabla_estado_registro_id, subtotal, descuentos, impuestos, total FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped_c or 0)}") if ped_c else {}
check(bool(ped_c) and ped_c_row.get('origen') == 'CARRITO' and int(ped_c_row.get('tabla_estado_registro_id') or 0) == 5,
      'Pedido del carrito confirmado (origen CARRITO, estado 5)', json.dumps(r_car, ensure_ascii=False)[:400])

REGISTRO['casos'].append({
    'numero': 2,
    'titulo': 'Venta mayorista por Carrito (casalucho-carrito) con escala y caja',
    'datos': {
        'cliente': 'Almacén Don José (5783)',
        'carrito_integrado': r_car.get('integrado'),
        'venta_pedido_id': ped_c,
        'comprobante_nro': ped_c_row.get('comprobante_nro'),
        'origen': ped_c_row.get('origen'),
        'estado': ped_c_row.get('tabla_estado_registro_id'),
        'total_pedido': ped_c_row.get('total'),
        'descuentos': ped_c_row.get('descuentos'),
        'lineas_resumen': res_c.get('lineas', []),
    }
})

# =============================================================================================
# CASO 3: VENTA POR APP DEL VENDEDOR (Santiago Passoni - santipasso)
# =============================================================================================
caso('3. Venta por App del Vendedor (santipasso): Borrador -> Confirmado')
r_app_b = app_ajax('guardar', {
    'entidad_id': CLI_APP,
    'sucursal_id': SUC_ID,
    'items': json.dumps([
        {'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1},  # 1 Caja x 10 INF-003
        {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5},        # 5 u INF-001
    ]),
    'observaciones': 'SIM_INT Pedido desde App Vendedor',
    'descuento_manual_pct': 0,
    'confirmar': 0,
})
ped_a = d(r_app_b).get('venta_pedido_id')
check(ok_resp(r_app_b) and ped_a and estado_de('gestion__ventas_pedidos', 'venta_pedido_id', ped_a) == 3,
      'App vendedor: borrador guardado (estado 3)', str(r_app_b)[:300])

r_app_c = app_ajax('guardar', {
    'entidad_id': CLI_APP,
    'sucursal_id': SUC_ID,
    'pedido_id': ped_a,
    'items': json.dumps([
        {'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1},
        {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5},
    ]),
    'observaciones': 'SIM_INT Pedido desde App Vendedor (confirmado)',
    'descuento_manual_pct': 0,
    'confirmar': 1,
}) if ped_a else {}
ped_a_row = uno(f"SELECT comprobante_nro, origen, vendedor_entidad_id, tabla_estado_registro_id, total FROM gestion__ventas_pedidos WHERE venta_pedido_id = {int(ped_a or 0)}") if ped_a else {}
check(ok_resp(r_app_c) and ped_a_row.get('origen') == 'APP' and int(ped_a_row.get('tabla_estado_registro_id') or 0) == 5,
      'App vendedor: pedido confirmado (origen APP, estado 5)', str(r_app_c)[:300])

REGISTRO['casos'].append({
    'numero': 3,
    'titulo': 'Venta en la calle por App del Vendedor (santipasso)',
    'datos': {
        'vendedor': 'Santiago Passoni (usuario 15 / entidad 5785)',
        'cliente': 'Kiosco y Polirrubro Central (5784)',
        'venta_pedido_id': ped_a,
        'comprobante_nro': ped_a_row.get('comprobante_nro'),
        'origen': ped_a_row.get('origen'),
        'estado': ped_a_row.get('tabla_estado_registro_id'),
        'total_pedido': ped_a_row.get('total'),
        'entrega_estimada': d(r_app_c).get('entrega'),
    }
})

# =============================================================================================
# CASO 4: DISTRIBUCIÓN -> HOJA DE RUTA -> REMITO PARCIAL -> FACTURA DE VENTA
# =============================================================================================
caso('4. Distribución, Hoja de Ruta, Remito Parcial y Factura')
peds = [p for p in (ped_c, ped_a) if p]
for p in peds:
    gn_ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=555, venta_pedido_id=p, accion_js='liberar_distribucion'))
check(all(estado_de('gestion__ventas_pedidos', 'venta_pedido_id', p) == 42 for p in peds),
      'Pedidos liberados para distribución (estado 42)', str(peds))

r_hr = gn_ajax('distribucion_hojas_ajax.php', dict(accion='hoja_crear', pagina_idx=649, fecha_reparto=HOY, camion_id=1))
hoja_id = d(r_hr).get('hoja_id')
r_hr_add = gn_ajax('distribucion_hojas_ajax.php', dict(accion='agregar_pedidos', pagina_idx=649, hoja_id=hoja_id, pedidos=json.dumps(peds), forzar=1)) if hoja_id else {}
r_hr_conf = gn_ajax('distribucion_hojas_ajax.php', dict(accion='confirmar', pagina_idx=649, hoja_id=hoja_id)) if hoja_id else {}
lista_carga = d(gn_ajax('distribucion_hojas_ajax.php', dict(accion='lista_carga', pagina_idx=649, hoja_id=hoja_id), 'GET')) if hoja_id else []
check(hoja_id and ok_resp(r_hr_add) and ok_resp(r_hr_conf), 'Hoja de ruta creada y confirmada', err(r_hr) + ' ' + err(r_hr_add) + ' ' + err(r_hr_conf))

# Remito parcial del pedido del carrito (10 de 25 u de INF-001, 1 de 2 cajas = 20 u de INF-002)
rem_id = None
fv_id = None
s_rem_pre = {'INF-001': stock_emp(P1), 'INF-002': stock_emp(P2)}
if ped_c:
    r_pend = gn_ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', pagina_idx=588, entidad_id=CLI_CARRITO, punto_venta_id=PV_ID, pedido_id=ped_c), 'GET')
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
        r_rem = gn_ajax('ventas_remitos_ajax.php', dict(accion='agregar', pagina_idx=588, punto_venta_id=PV_ID, comprobante_tipo_id=458,
                        entidad_id=CLI_CARRITO, f_emision=HOY, observaciones='SIM_INT Remito parcial carrito', detalles=json.dumps(rdets)))
        rem_id = (r_rem.get('venta_remito_id') or r_rem.get('id')) if isinstance(r_rem, dict) else None
        r_rem_c = gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_id, accion_js='confirmar')) if rem_id else {}
        s_rem_post = {'INF-001': stock_emp(P1), 'INF-002': stock_emp(P2)}
        check(rem_id and ok_resp(r_rem_c) and s_rem_post['INF-001'] == s_rem_pre['INF-001'] - 10 and s_rem_post['INF-002'] == s_rem_pre['INF-002'] - 20,
              f'Remito parcial confirmado y stock descontado (-10 INF-001, -20 INF-002)', err(r_rem) + ' ' + err(r_rem_c))

        # Factura de Venta desde ese Remito
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
            r_fv = gn_ajax('ventas_facturas_ajax.php', dict(accion='agregar', pagina_idx=556, punto_venta_id=PV_ID, sucursal_id=SUC_ID,
                           comprobante_tipo_id=434, entidad_id=CLI_CARRITO, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
                           condicion_pago_id=32, descuento_general_pct=0, observaciones='SIM_INT Factura desde remito', detalles=json.dumps(fdets)))
            fv_id = (r_fv.get('venta_factura_id') or d(r_fv).get('venta_factura_id') or r_fv.get('id')) if isinstance(r_fv, dict) else None
            r_fv_c = gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fv_id, accion_js='confirma')) if fv_id else {}
            if fv_id and ok_resp(r_fv_c):
                gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fv_id, accion_js='aprobada'))
            check(bool(fv_id), 'Factura de venta generada desde remito (confirmación: ' + ('OK' if ok_resp(r_fv_c) else err(r_fv_c)) + ')', err(r_fv) + ' ' + err(r_fv_c))

REGISTRO['stock_snapshot']['final'] = {
    'INF-001 (6281)': stock_emp(P1),
    'INF-002 (6282)': stock_emp(P2),
    'INF-003 (6283)': stock_emp(P3),
}

rem_row = uno(f"SELECT comprobante_nro, tabla_estado_registro_id FROM gestion__ventas_remitos WHERE venta_remito_id = {int(rem_id or 0)}") if rem_id else {}
fv_row = uno(f"SELECT comprobante_nro, tabla_estado_registro_id, cae FROM gestion__ventas_facturas WHERE venta_factura_id = {int(fv_id or 0)}") if fv_id else {}

REGISTRO['casos'].append({
    'numero': 4,
    'titulo': 'Distribución, Hoja de Ruta, Remito Parcial y Factura de Venta',
    'datos': {
        'hoja_ruta_id': hoja_id,
        'camion': 'Camion Rojo (AA890ZK)',
        'lista_carga': lista_carga if isinstance(lista_carga, list) else [],
        'venta_remito_id': rem_id,
        'remito_nro': rem_row.get('comprobante_nro'),
        'remito_estado': rem_row.get('tabla_estado_registro_id'),
        'venta_factura_id': fv_id,
        'factura_nro': fv_row.get('comprobante_nro'),
        'factura_estado': fv_row.get('tabla_estado_registro_id'),
        'factura_cae': fv_row.get('cae'),
    }
})

REGISTRO['checks'] = [{'caso': c, 'chequeo': ch, 'ok': ok, 'detalle': det} for (c, ch, ok, det) in RESULTADOS]

out_json = os.path.join(BASE_DIR, 'scratch', 'sim_emp1000_resultado.json')
with open(out_json, 'w', encoding='utf-8') as f:
    json.dump(REGISTRO, f, ensure_ascii=False, indent=2, default=str)

fallas = [x for x in RESULTADOS if not x[2]]
print(f"\nRESUMEN: {len(RESULTADOS) - len(fallas)}/{len(RESULTADOS)} checks OK. JSON en {out_json}")
if fallas:
    for c, ch, _, det in fallas:
        print(f"  FALLA [{c}] {ch}: {det}")
