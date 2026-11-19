"""
Simulación integral PROFUNDA ("a prueba de todo") en Empresa 1000 (GINESI MAURO NICOLAS,
módulo 5 gestion_normal) sobre gestion_multipyme.

Prueba circuitos Formales (PV 306 / 0001 WSFE) e Informales (PV 307 / 100001 NO_FISCAL),
roles reales no-superadmin (Ventas, Depósito, Compras, Tesorería, Consulta),
Notas de Crédito aplicadas en Recibos/OP, Cheques de Tercero + Endoso + Cheque Propio,
Retenciones, Anulaciones y Reversiones, Mostrador Formal/Informal, Cuentas Corrientes
y balanceo de todos los Asientos Contables.
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
    PHP, DB, HOY, ajax, sql, uno, val, num, ok_resp, err, check, caso, RESULTADOS
)

ERP_DIR = os.path.abspath(os.path.join(BASE_DIR, '..'))
CARRITO_DIR = os.path.abspath(os.path.join(ERP_DIR, '..', 'casalucho-carrito', 'ecommerce_app'))
CARRITO_DRIVER = os.path.join(BASE_DIR, 'tests', 'carrito', 'carrito_driver.php')

EID = 1000
MODULO = 'gestion_normal'
SUC_ID = 305
BOCA_ID = 305
PV_FISCAL = 306     # 0001 WSFE (Blanco / Fiscal)
PV_INFORMAL = 307   # 100001 NO_FISCAL (Informal / Interno)
PROV_ID = 5771      # Industrias Alimentarias del Sud S.A.
CLI_CARRITO = 5783  # Almacén Don José
CLI_APP = 5784      # Kiosco y Polirrubro Central

# Usuarios por perfil real
U_ADMIN = 1
U_VENTAS = 15       # santipasso (perfil 592)
U_DEP = int(val("SELECT usuario_id FROM conf__usuarios WHERE usuario='emp1000_dep'") or 1)
U_COMP = int(val("SELECT usuario_id FROM conf__usuarios WHERE usuario='emp1000_comp'") or 1)
U_TES = int(val("SELECT usuario_id FROM conf__usuarios WHERE usuario='emp1000_tes'") or 1)
U_CONS = int(val("SELECT usuario_id FROM conf__usuarios WHERE usuario='emp1000_cons'") or 1)

P1, P1_CAJA = 6281, 5   # INF-001 Yerba mate con palo 1 kg (Caja x 10)
P2, P2_CAJA = 6282, 7   # INF-002 Yerba mate con palo 500 g (Caja x 20)
P3, P3_CAJA = 6283, 9   # INF-003 Yerba mate sin palo 1 kg (Caja x 10)

# Medios de pago y cuentas de tesorería Empresa 1000
MP_EFECTIVO = 44
MP_CHQ_TERCERO = 45
MP_CHQ_PROPIO = 46
MP_TRANSFER = 47
MP_RET_IIBB = 53
CTA_CAJA = 26
CTA_VALORES = 27
CTA_BANCO = 28

MANIANA = (datetime.date.today() + datetime.timedelta(days=15)).isoformat()
SUF = datetime.datetime.now().strftime('%H%M%S')

REGISTRO = {
    'fecha_ejecucion': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    'empresa_id': EID,
    'empresa_nombre': 'GINESI MAURO NICOLAS',
    'modulo': MODULO,
    'usuarios': {'admin': U_ADMIN, 'ventas': U_VENTAS, 'deposito': U_DEP, 'compras': U_COMP, 'tesoreria': U_TES, 'consulta': U_CONS},
    'casos': [],
    'stock_snapshot': {},
}


def stock_emp(prod):
    return num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id={EID} AND boca_id={BOCA_ID} AND producto_id={int(prod)}") or 0)


def saldo_tes(cta_id):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id={int(cta_id)}") or 0)


def gn_ajax(archivo, params, method='POST', usuario_id=1):
    return ajax(archivo, dict(params, empresa_idx=EID), method, usuario_id=usuario_id, modulo=MODULO, empresa_id=EID)


def app_ajax(accion, params=None, method='POST', usuario_id=U_VENTAS):
    payload = dict(params or {}, accion=accion, empresa_idx=EID)
    php_code = (
        "session_start();"
        f"$_SESSION['logueado']=true;$_SESSION['usuario_id']={usuario_id};$_SESSION['empresa_id']={EID};"
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
            f"DB_HOST={env_vars.get('IP_HOST', 'localhost')}", "DB_PORT=3306", f"DB_NAME={DB}",
            f"DB_USER={env_vars.get('DB_USER', 'root')}", f"DB_PASS=\"{env_vars.get('DB_PASS', '')}\"",
            "APP_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
            "APP_ENV=testing", "APP_DEBUG=true", "SESSION_SECURE=false",
            f"ECOM_EMPRESA_ID={EID}", "ECOM_MODULO_ID=5", f"ECOM_SUCURSAL_ID={SUC_ID}",
            "ECOM_MONEDA_ID=1", "ECOM_COMPROBANTE_TIPO_PEDIDO_ID=433", "ECOM_TABLA_ORIGEN_PEDIDOS_ID=82",
            "ECOM_ENTIDAD_DOC_COLUMNA=cuit", f"ERP_CORE_PATH={ERP_DIR}",
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
        'producto_id': prod, 'cantidad': cant, 'precio_unitario': pu, 'precio_unitario_bruto': pu,
        'descuento_general_pct': 0, 'descuento_general': 0,
        'iva_alicuota_id': 23 if sin_iva else 25, 'iva_porcentaje': 0 if sin_iva else 21,
        'neto_gravado': neto, 'iva_importe': iva, 'total_linea': round(neto + iva, 2),
    }
    if pres_id:
        item['presentacion_id'] = pres_id
        item['presentacion_cantidad'] = pres_cant
        item['presentacion_precio'] = pres_pu
    return item


def estado_de(tabla, pk, rid):
    return int(val(f"SELECT tabla_estado_registro_id FROM {tabla} WHERE {pk}={int(rid or 0)}") or 0)


def comp_info(comp_id):
    return uno(f"SELECT comprobante_id, importe_total, importe_pendiente, tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id={int(comp_id or 0)}") or {}


def proximo_nro_fp(pv_cod, base):
    mx = int(val(f"SELECT IFNULL(MAX(comprobante_nro),0) FROM gestion__facturas_proveedores WHERE empresa_id={EID} AND comprobante_pv={int(pv_cod)}") or 0)
    return str(max(int(base), mx) + 1)


def emitir_factura_venta(pv_id, tipo_id, entidad_id, dets, obs, usuario_id=U_VENTAS, aprobar=True):
    r = gn_ajax('ventas_facturas_ajax.php', dict(
        accion='agregar', pagina_idx=556, punto_venta_id=pv_id, sucursal_id=SUC_ID,
        comprobante_tipo_id=tipo_id, entidad_id=entidad_id, f_emision=HOY, f_vto=HOY,
        moneda_id=1, tipo_cambio=1, condicion_pago_id=32, descuento_general_pct=0,
        observaciones=obs, detalles=json.dumps(dets)
    ), usuario_id=usuario_id)
    fid = (r.get('venta_factura_id') or d(r).get('venta_factura_id') or r.get('id')) if isinstance(r, dict) else None
    r_c = gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fid, accion_js='confirma'), usuario_id=usuario_id) if fid else {}
    r_a = gn_ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', pagina_idx=556, venta_factura_id=fid, accion_js='aprobada'), usuario_id=usuario_id) if (fid and aprobar and ok_resp(r_c)) else {}
    row = uno(f"SELECT venta_factura_id, comprobante_id, comprobante_nro, importe_total, tabla_estado_registro_id, cae FROM gestion__ventas_facturas WHERE venta_factura_id={int(fid or 0)}") if fid else {}
    return fid, row, r, r_c, r_a


# =============================================================================================
# 0. SNAPSHOT INICIAL Y VERIFICACIÓN DE MAESTROS
# =============================================================================================
caso('0. Snapshot inicial, PV 306 (Fiscal) / PV 307 (Informal) y Roles')
REGISTRO['stock_snapshot']['inicial'] = {'INF-001': stock_emp(P1), 'INF-002': stock_emp(P2), 'INF-003': stock_emp(P3)}
pv_f = uno(f"SELECT punto_venta_id, codigo_fiscal, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id={PV_FISCAL}")
pv_i = uno(f"SELECT punto_venta_id, codigo_fiscal, sistema_emision FROM gestion__puntos_venta WHERE punto_venta_id={PV_INFORMAL}")
check(pv_f and pv_f['sistema_emision'] == 'WSFE' and pv_i and pv_i['sistema_emision'] == 'NO_FISCAL',
      'PV 306 (WSFE) y PV 307 (NO_FISCAL) activos en Empresa 1000', f'{pv_f} / {pv_i}')
check(all(u > 1 for u in (U_VENTAS, U_DEP, U_COMP, U_TES, U_CONS)),
      f'Usuarios operativos por rol listos (V={U_VENTAS}, D={U_DEP}, C={U_COMP}, T={U_TES}, Q={U_CONS})')

# =============================================================================================
# 1. PRECIOS DE PROVEEDOR (Rol Compras)
# =============================================================================================
caso('1. Precios de Proveedor como usuario Compras (emp1000_comp)')
r_pp = gn_ajax('proveedor_precios_ajax.php', dict(
    accion='agregar', pagina_idx=592, entidad_id=PROV_ID, producto_id=P1,
    precio_lista=3380.00, moneda_id=1, f_vigencia_desde=HOY
), usuario_id=U_COMP)
check(ok_resp(r_pp), 'Usuario Compras actualiza precio proveedor P1=$3.380', err(r_pp))

# =============================================================================================
# 2. COMPRA FORMAL POR PRESENTACIÓN + CTA CTE MULTI-TENANT (Rol Compras)
# =============================================================================================
caso('2. Compra Formal (OC -> Factura A -> Stock + Cta Cte Proveedor) como Compras')
s1_pre, s2_pre = stock_emp(P1), stock_emp(P2)
oc_dets = [
    linea_std(P1, 2, 33800, pres_id=P1_CAJA, pres_cant=2, pres_pu=33800),
    linea_std(P2, 1, 34000, pres_id=P2_CAJA, pres_cant=1, pres_pu=34000),
]
r_oc = gn_ajax('ordenes_compra_ajax.php', dict(
    accion='agregar', pagina_idx=551, comprobante_tipo_id=443, sucursal_id=SUC_ID, punto_venta_id=PV_FISCAL,
    entidad_id=PROV_ID, f_emision=HOY, moneda_id=1, tipo_cambio=1, observaciones='SIM_PROF OC Formal',
    detalles=json.dumps(oc_dets), **calc_totales(oc_dets)
), usuario_id=U_COMP)
oc_id = r_oc.get('orden_compra_id') if isinstance(r_oc, dict) else None
check(ok_resp(r_oc) and oc_id, 'Compras crea OC borrador', err(r_oc))

fp_id = None
fp_comp_id = None
fp_total = 0.0
if oc_id:
    r_oc1 = gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='confirmar'), usuario_id=U_COMP)
    r_oc2 = gn_ajax('ordenes_compra_ajax.php', dict(accion='ejecutar_accion', pagina_idx=551, orden_compra_id=oc_id, accion_js='pendiente_recepcion'), usuario_id=U_COMP)
    check(ok_resp(r_oc1) and ok_resp(r_oc2) and estado_de('gestion__ordenes_compra', 'orden_compra_id', oc_id) == 7,
          'Compras confirma OC y pasa a Pendiente de recepción (7)', err(r_oc1) + ' ' + err(r_oc2))

    r_lin = gn_ajax('facturas_proveedores_ajax.php', dict(accion='obtener_oc_lineas', pagina_idx=557, orden_compra_id=oc_id, entidad_id=PROV_ID), 'GET', usuario_id=U_COMP)
    fp_dets = []
    for l in ((r_lin.get('data') or {}).get('lineas') or []):
        pres_id = int(l.get('presentacion_id') or 0) or None
        item = linea_std(int(l['producto_id']), num(l.get('pendiente') or l.get('cantidad')), num(l.get('precio_unitario')),
                         pres_id=pres_id, pres_cant=num(l.get('presentacion_cantidad')) if pres_id else None,
                         pres_pu=num(l.get('presentacion_precio')) if pres_id else None)
        item['ordenes_compra_detalle_id'] = int(l['ordenes_compra_detalle_id'])
        fp_dets.append(item)
    nro_fp = proximo_nro_fp(1, 92000)
    r_fp = gn_ajax('facturas_proveedores_ajax.php', dict(
        accion='agregar', pagina_idx=557, comprobante_tipo_id=452, comprobante_pv=1, comprobante_nro=nro_fp,
        sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
        moneda_id=1, tipo_cambio=1, condicion_pago_id=32, orden_compra_id=oc_id, descuento_general_pct=0,
        observaciones='SIM_PROF Factura A Proveedor', detalles=json.dumps(fp_dets), impuestos_adicionales='[]', **calc_totales(fp_dets)
    ), usuario_id=U_COMP)
    fp_id = r_fp.get('factura_proveedor_id') if isinstance(r_fp, dict) else None
    r_fp_a = gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='aprobar'), usuario_id=U_COMP) if fp_id else {}
    r_fp_c = gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=fp_id, accion_js='confirmar'), usuario_id=U_COMP) if fp_id else {}
    s1_post, s2_post = stock_emp(P1), stock_emp(P2)
    fp_row = uno(f"SELECT comprobante_id, total, tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id={int(fp_id or 0)}") or {}
    fp_comp_id = int(fp_row.get('comprobante_id') or 0)
    fp_total = float(fp_row.get('total') or 0)
    check(ok_resp(r_fp_c) and s1_post == s1_pre + 20 and s2_post == s2_pre + 20 and fp_comp_id > 0,
          f'Factura Proveedor A confirmada (+20 P1, +20 P2, total=${fp_total:.2f})', err(r_fp) + ' ' + err(r_fp_c))

# Verificar multi-tenant en proveedores_ctacte_ajax.php (solo proveedores de empresa 1000)
r_bp = gn_ajax('proveedores_ctacte_ajax.php', dict(accion='buscar_proveedores', q=''), 'GET', usuario_id=U_COMP)
ids_bp = [int(x['entidad_id']) for x in (r_bp.get('data') or [])]
ajenos = int(val(f"SELECT COUNT(*) FROM gestion__entidades WHERE entidad_id IN ({','.join(map(str, ids_bp)) or '0'}) AND empresa_id <> {EID}") or 0)
check(ok_resp(r_bp) and len(ids_bp) > 0 and ajenos == 0,
      f'Multi-tenant Cta Cte Proveedores OK ({len(ids_bp)} proveedores, 0 ajenos)', str(r_bp)[:200])

# =============================================================================================
# 3. NOTA DE CRÉDITO PROVEEDOR A + OP MIXTA (Transferencia + Cheque Propio Diferido + Efectivo)
# =============================================================================================
caso('3. NC Proveedor A + OP Mixta (Transferencia + Cheque Propio Diferido + Efectivo) como Tesorería')
s1_ncp_pre = stock_emp(P1)
ncp_dets = [linea_std(P1, 2, 3380.0)]  # Devolución 2 u INF-001 = $6.760 + IVA = $8.179,60
nro_ncp = proximo_nro_fp(1, 92000)
r_ncp = gn_ajax('facturas_proveedores_ajax.php', dict(
    accion='agregar', pagina_idx=557, comprobante_tipo_id=453, comprobante_pv=1, comprobante_nro=nro_ncp,
    sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
    moneda_id=1, tipo_cambio=1, condicion_pago_id=32, descuento_general_pct=0,
    observaciones='SIM_PROF NC A Proveedor devolución 2u', detalles=json.dumps(ncp_dets), impuestos_adicionales='[]', **calc_totales(ncp_dets)
), usuario_id=U_COMP)
ncp_id = r_ncp.get('factura_proveedor_id') if isinstance(r_ncp, dict) else None
if ncp_id:
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=ncp_id, accion_js='aprobar'), usuario_id=U_COMP)
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=ncp_id, accion_js='confirmar'), usuario_id=U_COMP)
s1_ncp_post = stock_emp(P1)
check(bool(ncp_id) and estado_de('gestion__facturas_proveedores', 'factura_proveedor_id', ncp_id) == 5 and s1_ncp_post == s1_ncp_pre - 2,
      f'NC Proveedor A confirmada y devuelve/descuenta stock ({s1_ncp_pre}->{s1_ncp_post})', str(r_ncp)[:250])

# OP Formal en PV 306 cancelando TOTALMENTE la Factura A (fp_comp_id) con importe_imputado + 3 medios!
if fp_comp_id and fp_total > 0:
    bco_pre, caja_pre = saldo_tes(CTA_BANCO), saldo_tes(CTA_CAJA)
    m_transf = round(fp_total * 0.50, 2)
    m_chq = round(fp_total * 0.30, 2)
    m_efec = round(fp_total - m_transf - m_chq, 2)
    r_op = gn_ajax('pagos_ajax.php', dict(
        accion='guardar', pagina_idx=606, entidad_id=PROV_ID, sucursal_id=SUC_ID, comprobante_pv=PV_FISCAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF OP Mixta Formal',
        imputaciones=json.dumps([{'comprobante_destino_id': fp_comp_id, 'importe_imputado': fp_total}]),
        medios=json.dumps([
            {'medio_pago_id': MP_TRANSFER, 'tesoreria_cuenta_id': CTA_BANCO, 'importe': m_transf, 'nro_operacion': f'TRF-{SUF}'},
            {'medio_pago_id': MP_CHQ_PROPIO, 'tesoreria_cuenta_id': CTA_BANCO, 'importe': m_chq,
             'cheque_propio': {'banco_id': 1, 'numero': f'CHP{SUF}', 'f_pago': MANIANA, 'tesoreria_cuenta_id': CTA_BANCO}},
            {'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': m_efec},
        ])
    ), usuario_id=U_TES)
    op_id = r_op.get('pago_id') if isinstance(r_op, dict) else None
    r_op_c = gn_ajax('pagos_ajax.php', dict(accion='confirmar', pagina_idx=606, id=op_id), usuario_id=U_TES) if op_id else {}
    ci_fp = comp_info(fp_comp_id)
    bco_post, caja_post = saldo_tes(CTA_BANCO), saldo_tes(CTA_CAJA)
    check(ok_resp(r_op) and ok_resp(r_op_c) and int(ci_fp.get('tabla_estado_registro_id') or 0) == 40 and abs(num(ci_fp.get('importe_pendiente'))) < 0.01,
          f'OP Mixta (Transf + Chq Propio Dif + Efec) cancela 100% Factura A (estado={ci_fp.get("tabla_estado_registro_id")}, pend={ci_fp.get("importe_pendiente")})',
          err(r_op) + ' ' + err(r_op_c))
    check(abs((bco_pre - bco_post) - m_transf) < 0.02 and abs((caja_pre - caja_post) - m_efec) < 0.02,
          f'Tesorería exacta: Banco -{m_transf:.2f} (chq diferido no debita aún), Caja -{m_efec:.2f}',
          f'bco {bco_pre}->{bco_post}, caja {caja_pre}->{caja_post}')

# =============================================================================================
# 4. VENTA MAYORISTA POR CARRITO B2B
# =============================================================================================
caso('4. Venta Mayorista por Carrito B2B (escala + caja + f_confirmacion)')
r_car = carrito_emp1000([
    {'op': 'vaciar'},
    {'op': 'agregar', 'producto_id': P1, 'cantidad': 25},
    {'op': 'agregar', 'producto_id': P2, 'presentacion_id': P2_CAJA, 'cantidad': 2},
    {'op': 'resumen'},
    {'op': 'confirmar', 'observaciones': 'SIM_PROF Pedido Carrito B2B'},
], CLI_CARRITO)
ops_c = r_car.get('ops') or []
ped_c = (ops_c[4] if len(ops_c) >= 5 else {}).get('venta_pedido_id')
ped_c_row = uno(f"SELECT comprobante_nro, origen, tabla_estado_registro_id, f_confirmacion, total FROM gestion__ventas_pedidos WHERE venta_pedido_id={int(ped_c or 0)}") or {}
check(bool(ped_c) and ped_c_row.get('origen') == 'CARRITO' and int(ped_c_row.get('tabla_estado_registro_id') or 0) == 5 and bool(ped_c_row.get('f_confirmacion')),
      f'Pedido Carrito confirmado (#{ped_c}, f_confirmacion={ped_c_row.get("f_confirmacion")})', json.dumps(r_car)[:300])

# =============================================================================================
# 5. VENTA POR APP DEL VENDEDOR (Rol Ventas - santipasso)
# =============================================================================================
caso('5. App del Vendedor (santipasso): consulta clientes/deudas + borrador + confirmado')
r_cli = app_ajax('clientes', {'q': 'Kiosco'}, 'GET')
cli_data = d(r_cli)
cli_list = cli_data if isinstance(cli_data, list) else (cli_data.get('clientes', []) if isinstance(cli_data, dict) else [])
check(ok_resp(r_cli) and len(cli_list) >= 1,
      f'App vendedor consulta clientes ({len(cli_list)} encontrados)', str(r_cli)[:250])
r_app_b = app_ajax('guardar', {
    'entidad_id': CLI_APP, 'sucursal_id': SUC_ID, 'observaciones': 'SIM_PROF App Vendedor', 'confirmar': 0,
    'items': json.dumps([{'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1}, {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5}]),
})
ped_a = d(r_app_b).get('venta_pedido_id')
r_app_c = app_ajax('guardar', {
    'entidad_id': CLI_APP, 'sucursal_id': SUC_ID, 'pedido_id': ped_a, 'observaciones': 'SIM_PROF App Vendedor OK', 'confirmar': 1,
    'items': json.dumps([{'producto_id': P3, 'presentacion_id': P3_CAJA, 'cantidad': 1}, {'producto_id': P1, 'presentacion_id': 0, 'cantidad': 5}]),
}) if ped_a else {}
ped_a_row = uno(f"SELECT origen, tabla_estado_registro_id, f_confirmacion FROM gestion__ventas_pedidos WHERE venta_pedido_id={int(ped_a or 0)}") or {}
check(ok_resp(r_app_c) and ped_a_row.get('origen') == 'APP' and int(ped_a_row.get('tabla_estado_registro_id') or 0) == 5 and bool(ped_a_row.get('f_confirmacion')),
      f'App vendedor borrador->confirmado (#{ped_a}, f_confirmacion={ped_a_row.get("f_confirmacion")})', str(r_app_c)[:250])

# =============================================================================================
# 6. DISTRIBUCIÓN + PICKING (ventas_pedidos_gestion) + REMITO PARCIAL (Rol Depósito)
# =============================================================================================
caso('6. Distribución, Hoja de Ruta, Picking y Remito Parcial A como Depósito (emp1000_dep)')
peds = [p for p in (ped_c, ped_a) if p]
for p in peds:
    r_lib = gn_ajax('ventas_pedidos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=555, venta_pedido_id=p, accion_js='liberar_distribucion'), usuario_id=U_VENTAS)
check(all(estado_de('gestion__ventas_pedidos', 'venta_pedido_id', p) == 42 for p in peds), 'Ventas (15) libera pedidos a distribución (42)')

r_hr = gn_ajax('distribucion_hojas_ajax.php', dict(accion='hoja_crear', pagina_idx=649, fecha_reparto=HOY, camion_id=1), usuario_id=U_DEP)
hoja_id = d(r_hr).get('hoja_id')
r_hra = gn_ajax('distribucion_hojas_ajax.php', dict(accion='agregar_pedidos', pagina_idx=649, hoja_id=hoja_id, pedidos=json.dumps(peds), forzar=1), usuario_id=U_DEP) if hoja_id else {}
r_hrc = gn_ajax('distribucion_hojas_ajax.php', dict(accion='confirmar', pagina_idx=649, hoja_id=hoja_id), usuario_id=U_DEP) if hoja_id else {}
check(hoja_id and ok_resp(r_hra) and ok_resp(r_hrc), f'Depósito ({U_DEP}) crea y confirma Hoja de Ruta #{hoja_id}', err(r_hr) + ' ' + err(r_hra) + ' ' + err(r_hrc))

# Depósito abre pedido en ventas_pedidos_gestion (42 -> 9 En preparación) y ajusta cantidad
if ped_a:
    r_open = gn_ajax('ventas_pedidos_gestion_ajax.php', dict(accion='abrir_pedido', pagina_idx=591, venta_pedido_id=ped_a), usuario_id=U_DEP)
    check(isinstance(r_open, dict) and 'pedido' in r_open and estado_de('gestion__ventas_pedidos', 'venta_pedido_id', ped_a) == 9,
          f'Depósito ({U_DEP}) abre pedido #{ped_a} -> En preparación (estado 9)', err(r_open))

# Depósito emite Remito Parcial A sobre pedido carrito
rem_id = None
s1_r_pre, s2_r_pre = stock_emp(P1), stock_emp(P2)
if ped_c:
    r_pend = gn_ajax('ventas_remitos_ajax.php', dict(accion='obtener_pedidos_pendientes_cliente', pagina_idx=588, entidad_id=CLI_CARRITO, punto_venta_id=PV_FISCAL, pedido_id=ped_c), 'GET', usuario_id=U_DEP)
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
        r_rem = gn_ajax('ventas_remitos_ajax.php', dict(
            accion='agregar', pagina_idx=588, punto_venta_id=PV_FISCAL, comprobante_tipo_id=458,
            entidad_id=CLI_CARRITO, f_emision=HOY, observaciones='SIM_PROF Remito A Parcial', detalles=json.dumps(rdets)
        ), usuario_id=U_DEP)
        rem_id = (r_rem.get('venta_remito_id') or r_rem.get('id')) if isinstance(r_rem, dict) else None
        r_rc = gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_id, accion_js='confirmar'), usuario_id=U_DEP) if rem_id else {}
        check(rem_id and ok_resp(r_rc) and stock_emp(P1) == s1_r_pre - 10 and stock_emp(P2) == s2_r_pre - 20,
              f'Depósito ({U_DEP}) emite y confirma Remito Parcial A #{rem_id} (-10 P1, -20 P2)', err(r_rem) + ' ' + err(r_rc))

# =============================================================================================
# 7. ANULACIÓN DE REMITO SIN FACTURA + FACTURA DESDE REMITO + BLOQUEO ANULACIÓN CON FACTURA
# =============================================================================================
caso('7. Anulación de Remito sin factura, Factura A desde Remito y bloqueo de anulación con factura')
# 7a. Remito de prueba -> confirmar -> anular (debe devolver stock)
s1_an_pre = stock_emp(P1)
r_ra = gn_ajax('ventas_remitos_ajax.php', dict(
    accion='agregar', pagina_idx=588, punto_venta_id=PV_FISCAL, comprobante_tipo_id=458,
    entidad_id=CLI_CARRITO, f_emision=HOY, observaciones='SIM_PROF Remito para anular',
    detalles=json.dumps([linea_std(P1, 3, 4000.0)])
), usuario_id=U_DEP)
rem_an_id = (r_ra.get('venta_remito_id') or r_ra.get('id')) if isinstance(r_ra, dict) else None
if rem_an_id:
    gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_an_id, accion_js='confirmar'), usuario_id=U_DEP)
    s1_an_mid = stock_emp(P1)
    r_an = gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_an_id, accion_js='anular'), usuario_id=U_DEP)
    s1_an_post = stock_emp(P1)
    check(ok_resp(r_an) and estado_de('gestion__ventas_remitos', 'venta_remito_id', rem_an_id) == 38 and s1_an_mid == s1_an_pre - 3 and s1_an_post == s1_an_pre,
          f'Anulación de Remito sin factura revierte stock ({s1_an_pre}->{s1_an_mid}->{s1_an_post})', err(r_an))

# 7b. Factura A desde Remito del Caso 6 (NO debe descontar stock de nuevo!)
fv_id, fv_comp_id, fv_total = None, 0, 0.0
if rem_id:
    s1_f_pre = stock_emp(P1)
    r_rp = gn_ajax('ventas_facturas_ajax.php', dict(accion='remitos_pendientes', pagina_idx=556, entidad_id=CLI_CARRITO, venta_remito_id=rem_id), 'GET', usuario_id=U_VENTAS)
    lineas_f = []
    for x in (r_rp if isinstance(r_rp, list) else (r_rp.get('data', r_rp.get('remitos', [])) if isinstance(r_rp, dict) else [])):
        lineas_f += x.get('detalles', x.get('lineas', [x])) if isinstance(x, dict) else []
    fdets = [
        {'producto_id': int(l['producto_id']), 'cantidad': num(l.get('pendiente') or l.get('cantidad')),
         'precio_unitario': num(l.get('precio_unitario_bruto') or l.get('precio_unitario')),
         'descuento_general_pct': num(l.get('descuento_general_pct')), 'iva_alicuota_id': 25, 'iva_porcentaje': 21,
         'venta_remito_detalle_id': int(l['venta_remito_detalle_id']), 'venta_pedido_detalle_id': int(l.get('venta_pedido_detalle_id') or 0)}
        for l in lineas_f if isinstance(l, dict) and l.get('venta_remito_detalle_id')
    ]
    fv_id, fv_row, r_fv, r_fvc, r_fva = emitir_factura_venta(PV_FISCAL, 434, CLI_CARRITO, fdets, 'SIM_PROF Factura A desde remito', usuario_id=U_VENTAS)
    fv_comp_id = int(fv_row.get('comprobante_id') or 0)
    fv_total = float(fv_row.get('importe_total') or 0)
    check(bool(fv_id) and int(fv_row.get('tabla_estado_registro_id') or 0) == 5 and stock_emp(P1) == s1_f_pre and estado_de('gestion__ventas_remitos', 'venta_remito_id', rem_id) == 12,
          f'Factura A #{fv_id} emitida desde remito (remito->12 Facturado, sin doble descuento stock)', err(r_fv) + ' ' + err(r_fvc) + ' ' + err(r_fva))

    # 7c. Intentar anular el Remito ya facturado -> DEBE BLOQUEAR!
    r_bloq = gn_ajax('ventas_remitos_ajax.php', dict(accion='ejecutar_accion', pagina_idx=588, venta_remito_id=rem_id, accion_js='anular'), usuario_id=U_ADMIN)
    check(not ok_resp(r_bloq) and estado_de('gestion__ventas_remitos', 'venta_remito_id', rem_id) == 12,
          'Bloqueo correcto al intentar anular remito ya facturado', str(r_bloq)[:200])

# =============================================================================================
# 8. NC VENTA A + COBRANZA MIXTA (NC + Cheque 3ro + Transf + Ret IIBB + Efectivo) (Rol Tesorería)
# =============================================================================================
caso('8. NC Venta A + Cobranza Mixta (NC + Cheque 3ro + Transf + Ret IIBB + Efec) como Tesorería')
s1_ncv_pre = stock_emp(P1)
ncv_id, ncv_row, r_ncv, r_ncvc, r_ncva = emitir_factura_venta(
    PV_FISCAL, 435, CLI_CARRITO, [linea_std(P1, 2, 4000.0)], 'SIM_PROF NC A devolución 2u', usuario_id=U_VENTAS
)
ncv_comp_id = int(ncv_row.get('comprobante_id') or 0)
ncv_total = float(ncv_row.get('importe_total') or 0)
check(bool(ncv_id) and int(ncv_row.get('tabla_estado_registro_id') or 0) == 5 and stock_emp(P1) == s1_ncv_pre + 2,
      f'NC Venta A #{ncv_id} confirmada (${ncv_total:.2f}) y devuelve stock ({s1_ncv_pre}->{stock_emp(P1)})', err(r_ncv) + ' ' + err(r_ncva))

cheque_tercero_id = None
if fv_comp_id and ncv_comp_id and fv_total > ncv_total:
    resto = round(fv_total - ncv_total, 2)
    m_chq3 = round(resto * 0.35, 2)
    m_transf = round(resto * 0.35, 2)
    m_ret = round(resto * 0.05, 2)
    m_efec = round(resto - m_chq3 - m_transf - m_ret, 2)
    val_pre, bco_pre, caja_pre = saldo_tes(CTA_VALORES), saldo_tes(CTA_BANCO), saldo_tes(CTA_CAJA)
    r_cob = gn_ajax('cobranzas_ajax.php', dict(
        accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, comprobante_pv=PV_FISCAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF Cobranza Mixta con NC',
        imputaciones=json.dumps([{'comprobante_destino_id': fv_comp_id, 'importe_imputado': fv_total}]),
        notas_credito=json.dumps([{'nc_comprobante_id': ncv_comp_id, 'importe': ncv_total}]),
        medios=json.dumps([
            {'medio_pago_id': MP_CHQ_TERCERO, 'tesoreria_cuenta_id': CTA_VALORES, 'importe': m_chq3,
             'cheque': {'banco_id': 1, 'numero': f'CHT{SUF}', 'cuit_librador': '30719985013', 'librador': 'Almacén Don José', 'f_emision': HOY, 'f_pago': MANIANA}},
            {'medio_pago_id': MP_TRANSFER, 'tesoreria_cuenta_id': CTA_BANCO, 'importe': m_transf, 'nro_operacion': f'CTR-{SUF}'},
            {'medio_pago_id': MP_RET_IIBB, 'importe': m_ret, 'impuesto_tipo_id': 4, 'jurisdiccion_id': 2,
             'retencion_certificado_nro': f'RET-{SUF}', 'retencion_base_imponible': round(fv_total / 1.21, 2), 'retencion_alicuota': 3.0},
            {'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': m_efec},
        ])
    ), usuario_id=U_TES)
    cob_id = r_cob.get('cobranza_id') if isinstance(r_cob, dict) else None
    r_cob_c = gn_ajax('cobranzas_ajax.php', dict(accion='confirmar', pagina_idx=605, id=cob_id), usuario_id=U_TES) if cob_id else {}
    ci_fv, ci_nc = comp_info(fv_comp_id), comp_info(ncv_comp_id)
    cheque_tercero_id = val(f"SELECT cheque_id FROM gestion__cheques WHERE empresa_id={EID} AND numero='CHT{SUF}' LIMIT 1")
    check(ok_resp(r_cob) and ok_resp(r_cob_c) and int(ci_fv.get('tabla_estado_registro_id') or 0) == 40 and abs(num(ci_fv.get('importe_pendiente'))) < 0.01,
          f'Cobranza Mixta + NC cancela 100% Factura A (est={ci_fv.get("tabla_estado_registro_id")}, pend={ci_fv.get("importe_pendiente")})',
          err(r_cob) + ' ' + err(r_cob_c))
    check(int(ci_nc.get('tabla_estado_registro_id') or 0) == 40 and abs(num(ci_nc.get('importe_pendiente'))) < 0.01 and bool(cheque_tercero_id),
          f'NC A consumida (est={ci_nc.get("tabla_estado_registro_id")}) y Cheque 3ro #{cheque_tercero_id} en cartera',
          f'nc={ci_nc}, chq={cheque_tercero_id}')
    check(abs((saldo_tes(CTA_VALORES) - val_pre) - m_chq3) < 0.02 and abs((saldo_tes(CTA_BANCO) - bco_pre) - m_transf) < 0.02 and abs((saldo_tes(CTA_CAJA) - caja_pre) - m_efec) < 0.02,
          f'Saldos Tesorería exactos (+Valores {m_chq3:.2f}, +Banco {m_transf:.2f}, +Caja {m_efec:.2f})')

# =============================================================================================
# 9. ENDOSO DE CHEQUE DE TERCERO A PROVEEDOR EN OP (Rol Tesorería)
# =============================================================================================
caso('9. Endoso de Cheque de Tercero a Proveedor en OP como Tesorería')
if cheque_tercero_id:
    chq_imp = num(val(f"SELECT importe FROM gestion__cheques WHERE cheque_id={int(cheque_tercero_id)}"))
    val_pre = saldo_tes(CTA_VALORES)
    r_end = gn_ajax('pagos_ajax.php', dict(
        accion='guardar', pagina_idx=606, entidad_id=PROV_ID, sucursal_id=SUC_ID, comprobante_pv=PV_FISCAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF Endoso cheque tercero',
        imputaciones=json.dumps([]),
        medios=json.dumps([{'medio_pago_id': MP_CHQ_TERCERO, 'tesoreria_cuenta_id': CTA_VALORES, 'cheque_id': int(cheque_tercero_id), 'importe': chq_imp}])
    ), usuario_id=U_TES)
    op_end_id = r_end.get('pago_id') if isinstance(r_end, dict) else None
    r_end_c = gn_ajax('pagos_ajax.php', dict(accion='confirmar', pagina_idx=606, id=op_end_id), usuario_id=U_TES) if op_end_id else {}
    chq_est = int(val(f"SELECT tabla_estado_registro_id FROM gestion__cheques WHERE cheque_id={int(cheque_tercero_id)}") or 0)
    check(ok_resp(r_end) and ok_resp(r_end_c) and chq_est == 33 and abs((val_pre - saldo_tes(CTA_VALORES)) - chq_imp) < 0.02,
          f'Cheque #{cheque_tercero_id} endosado al proveedor (estado={chq_est} ENDOSADO, Valores -{chq_imp:.2f})', err(r_end) + ' ' + err(r_end_c))

# =============================================================================================
# 10. ANULACIÓN DE COBRANZA CONFIRMADA Y REVERSIÓN DE IMPUTACIÓN (Rol Tesorería)
# =============================================================================================
caso('10. Anulación de Cobranza confirmada y reversión de saldo en Factura y Caja')
fv_an_id, fv_an_row, _, _, _ = emitir_factura_venta(PV_FISCAL, 434, CLI_CARRITO, [linea_std(P1, 1, 4000.0)], 'SIM_PROF Factura A test anulación cobro', usuario_id=U_VENTAS)
fv_an_comp = int(fv_an_row.get('comprobante_id') or 0)
fv_an_tot = float(fv_an_row.get('importe_total') or 0)
if fv_an_comp:
    caja_pre = saldo_tes(CTA_CAJA)
    r_ca = gn_ajax('cobranzas_ajax.php', dict(
        accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, comprobante_pv=PV_FISCAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF Cobro para anular',
        imputaciones=json.dumps([{'comprobante_destino_id': fv_an_comp, 'importe_imputado': fv_an_tot}]),
        medios=json.dumps([{'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': fv_an_tot}])
    ), usuario_id=U_TES)
    cob_an_id = r_ca.get('cobranza_id')
    gn_ajax('cobranzas_ajax.php', dict(accion='confirmar', pagina_idx=605, id=cob_an_id), usuario_id=U_TES)
    est_pre_an = int(comp_info(fv_an_comp).get('tabla_estado_registro_id') or 0)
    r_an_c = gn_ajax('cobranzas_ajax.php', dict(accion='anular', pagina_idx=605, id=cob_an_id, motivo='Error de prueba simulación'), usuario_id=U_TES)
    ci_post = comp_info(fv_an_comp)
    check(ok_resp(r_an_c) and est_pre_an == 40 and int(ci_post.get('tabla_estado_registro_id') or 0) == 5 and abs(num(ci_post.get('importe_pendiente')) - fv_an_tot) < 0.01 and abs(saldo_tes(CTA_CAJA) - caja_pre) < 0.02,
          f'Anulación de Cobranza revierte factura (40->5, pend=${ci_post.get("importe_pendiente")}) y Caja', err(r_an_c))

# =============================================================================================
# 11. MOSTRADOR FORMAL (PV 306 Factura B) — valorizar + pago mixto (Rol Ventas)
# =============================================================================================
caso('11. Mostrador Formal PV 306 (valorizar con IVA + cobro mixto Efectivo+Transf) como Ventas')
r_vf = gn_ajax('mostrador_ajax.php', dict(accion='valorizar', pagina_idx=650, punto_venta_id=PV_FISCAL, entidad_id=0, items=json.dumps([{'producto_id': P1, 'cantidad': 2}])), usuario_id=U_VENTAS)
tot_mf = num(d(r_vf).get('cabecera', {}).get('total'))
iva_mf = num(d(r_vf).get('cabecera', {}).get('impuestos'))
check(ok_resp(r_vf) and iva_mf > 0 and tot_mf > 0, f'Mostrador PV 306 valoriza con IVA (total=${tot_mf:.2f}, IVA=${iva_mf:.2f})', err(r_vf))
s1_mf_pre = stock_emp(P1)
r_mf = gn_ajax('mostrador_ajax.php', dict(
    accion='facturar', pagina_idx=650, punto_venta_id=PV_FISCAL, entidad_id=0, consumidor_final_nombre='Consumidor Final Formal',
    items=json.dumps([{'producto_id': P1, 'cantidad': 2}]),
    medios=json.dumps([{'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': round(tot_mf - 5000, 2)},
                       {'medio_pago_id': MP_TRANSFER, 'tesoreria_cuenta_id': CTA_BANCO, 'importe': 5000.0}]),
    observaciones='SIM_PROF Mostrador Formal Mixto'
), usuario_id=U_VENTAS)
check(ok_resp(r_mf) and d(r_mf).get('estado') == 40 and stock_emp(P1) == s1_mf_pre - 2,
      f'Mostrador Formal PV 306 aprobado (Factura B N° {d(r_mf).get("comprobante_nro")}, estado 40, stock -2)', err(r_mf))

# =============================================================================================
# 12. COMPRA INFORMAL (Remito X C_RE en PV 100001) + OP INFORMAL PV 307 CON IMPUTACIÓN REAL
# =============================================================================================
caso('12. Compra Informal (Remito X C_RE) + Pago Informal OP PV 307 cancelando Cta Cte')
s1_ci_pre = stock_emp(P1)
nro_cre = proximo_nro_fp(100001, 88500)
r_cre = gn_ajax('facturas_proveedores_ajax.php', dict(
    accion='agregar', pagina_idx=557, comprobante_tipo_id=455, comprobante_pv=100001, comprobante_nro=nro_cre,
    sucursal_id=SUC_ID, deposito_id=BOCA_ID, entidad_id=PROV_ID, f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
    moneda_id=1, tipo_cambio=1, condicion_pago_id=32, descuento_general_pct=0,
    observaciones='SIM_PROF Compra Informal Remito X', detalles=json.dumps([linea_std(P1, 15, 3000.0, sin_iva=True)]),
    impuestos_adicionales='[]', subtotal=45000.0, descuentos=0, impuestos=0, total=45000.0
), usuario_id=U_COMP)
cre_id = r_cre.get('factura_proveedor_id') if isinstance(r_cre, dict) else None
if cre_id:
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=cre_id, accion_js='aprobar'), usuario_id=U_COMP)
    gn_ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', pagina_idx=557, factura_proveedor_id=cre_id, accion_js='confirmar'), usuario_id=U_COMP)
cre_row = uno(f"SELECT comprobante_id, total FROM gestion__facturas_proveedores WHERE factura_proveedor_id={int(cre_id or 0)}") or {}
cre_comp_id = int(cre_row.get('comprobante_id') or 0)
asiento_cre = int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id={EID} AND comprobante_id={cre_comp_id}") or 0) if cre_comp_id else 0
check(bool(cre_id) and stock_emp(P1) == s1_ci_pre + 15 and asiento_cre == 0,
      f'Compra Informal C_RE confirmada (+15 P1, sin asiento oficial)', f'cre={cre_id}, asiento={asiento_cre}')

if cre_comp_id:
    r_opi = gn_ajax('pagos_ajax.php', dict(
        accion='guardar', pagina_idx=606, entidad_id=PROV_ID, sucursal_id=SUC_ID, comprobante_pv=PV_INFORMAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF OP Informal PV 307',
        imputaciones=json.dumps([{'comprobante_destino_id': cre_comp_id, 'importe_imputado': 45000.0}]),
        medios=json.dumps([{'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': 45000.0}])
    ), usuario_id=U_TES)
    opi_id = r_opi.get('pago_id')
    r_opi_c = gn_ajax('pagos_ajax.php', dict(accion='confirmar', pagina_idx=606, id=opi_id), usuario_id=U_TES) if opi_id else {}
    ci_cre = comp_info(cre_comp_id)
    check(ok_resp(r_opi_c) and int(ci_cre.get('tabla_estado_registro_id') or 0) == 40 and abs(num(ci_cre.get('importe_pendiente'))) < 0.01,
          f'OP Informal PV 307 (100001) cancela 100% Remito X C_RE (est={ci_cre.get("tabla_estado_registro_id")}, pend={ci_cre.get("importe_pendiente")})',
          err(r_opi) + ' ' + err(r_opi_c))

# =============================================================================================
# 13. CIRCUITO VENTA INFORMAL COMPLETO (Remito X + Factura X + NC X + Recibo X PV 307)
# =============================================================================================
caso('13. Venta Informal Completa: Remito X + Factura X + NC X + Recibo X en PV 307')
s1_vi_pre = stock_emp(P1)
# Factura X por 4 u a $4.000 = $16.000 (sin IVA, sin CAE, sin asiento contable)
fx_id, fx_row, r_fx, r_fxc, r_fxa = emitir_factura_venta(PV_INFORMAL, 437, CLI_CARRITO, [linea_std(P1, 4, 4000.0, sin_iva=True)], 'SIM_PROF Factura X Informal', usuario_id=U_VENTAS)
fx_comp_id = int(fx_row.get('comprobante_id') or 0)
fx_total = float(fx_row.get('importe_total') or 0)
asiento_fx = int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id={EID} AND comprobante_id={fx_comp_id}") or 0) if fx_comp_id else 0
check(bool(fx_id) and fx_row.get('cae') is None and abs(fx_total - 16000.0) < 0.01 and asiento_fx == 0 and stock_emp(P1) == s1_vi_pre - 4,
      f'Factura X #{fx_id} emitida en PV 307 ($16.000 sin IVA, sin CAE, sin asiento, stock -4)', err(r_fx) + ' ' + err(r_fxa))

# NC X por 1 u a $4.000 = $4.000 -> devuelve stock +1
ncx_id, ncx_row, _, _, _ = emitir_factura_venta(PV_INFORMAL, 438, CLI_CARRITO, [linea_std(P1, 1, 4000.0, sin_iva=True)], 'SIM_PROF NC X devolución 1u', usuario_id=U_VENTAS)
ncx_comp_id = int(ncx_row.get('comprobante_id') or 0)
check(bool(ncx_id) and stock_emp(P1) == s1_vi_pre - 3, f'NC X #{ncx_id} confirmada en PV 307 (+1 stock)')

# Recibo X en PV 307 aplicando NC X ($4.000) + Efectivo ($12.000) = $16.000 -> Factura X y NC X quedan en estado 40!
if fx_comp_id and ncx_comp_id:
    r_cx = gn_ajax('cobranzas_ajax.php', dict(
        accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, comprobante_pv=PV_INFORMAL,
        f_emision=HOY, f_contabilidad=HOY, moneda_id=6, tipo_cambio=1.0, observaciones='SIM_PROF Recibo X con NC X',
        imputaciones=json.dumps([{'comprobante_destino_id': fx_comp_id, 'importe_imputado': 16000.0}]),
        notas_credito=json.dumps([{'nc_comprobante_id': ncx_comp_id, 'importe': 4000.0}]),
        medios=json.dumps([{'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': 12000.0}])
    ), usuario_id=U_TES)
    cx_id = r_cx.get('cobranza_id')
    r_cxc = gn_ajax('cobranzas_ajax.php', dict(accion='confirmar', pagina_idx=605, id=cx_id), usuario_id=U_TES) if cx_id else {}
    ci_fx, ci_ncx = comp_info(fx_comp_id), comp_info(ncx_comp_id)
    check(ok_resp(r_cxc) and int(ci_fx.get('tabla_estado_registro_id') or 0) == 40 and int(ci_ncx.get('tabla_estado_registro_id') or 0) == 40,
          f'Recibo X PV 307 (100001) + NC X cancela 100% Factura X (FX est={ci_fx.get("tabla_estado_registro_id")}, NCX est={ci_ncx.get("tabla_estado_registro_id")})',
          err(r_cx) + ' ' + err(r_cxc))

# =============================================================================================
# 14. MOSTRADOR INFORMAL (PV 307 Factura X) — verificación del FIX en valorizar()!
# =============================================================================================
caso('14. Mostrador Informal PV 307 (valorizar SIN IVA + facturar Factura X contado)')
r_vi = gn_ajax('mostrador_ajax.php', dict(accion='valorizar', pagina_idx=650, punto_venta_id=PV_INFORMAL, entidad_id=0, items=json.dumps([{'producto_id': P1, 'cantidad': 1}])), usuario_id=U_VENTAS)
tot_mi = num(d(r_vi).get('cabecera', {}).get('total'))
iva_mi = num(d(r_vi).get('cabecera', {}).get('impuestos'))
check(ok_resp(r_vi) and iva_mi == 0.0 and tot_mi > 0,
      f'Mostrador PV 307 valoriza SIN IVA (total=${tot_mi:.2f}, IVA=${iva_mi:.2f})', str(r_vi)[:250])
s1_mi_pre = stock_emp(P1)
r_mi = gn_ajax('mostrador_ajax.php', dict(
    accion='facturar', pagina_idx=650, punto_venta_id=PV_INFORMAL, entidad_id=0, consumidor_final_nombre='Consumidor Final Informal',
    items=json.dumps([{'producto_id': P1, 'cantidad': 1}]),
    medios=json.dumps([{'medio_pago_id': MP_EFECTIVO, 'tesoreria_cuenta_id': CTA_CAJA, 'importe': tot_mi}]),
    observaciones='SIM_PROF Mostrador Informal PV 307'
), usuario_id=U_VENTAS)
check(ok_resp(r_mi) and d(r_mi).get('estado') == 40 and stock_emp(P1) == s1_mi_pre - 1,
      f'Mostrador Informal PV 307 aprobado al centavo (Factura X N° {d(r_mi).get("comprobante_nro")}, total=${tot_mi:.2f})', err(r_mi))

# =============================================================================================
# 15. SEGURIDAD DE PERFILES: USUARIO CONSULTA (emp1000_cons)
# =============================================================================================
caso('15. Seguridad de Perfiles: Usuario Consulta (lee OK, bloquea toda escritura)')
r_q1 = gn_ajax('clientes_ctacte_ajax.php', dict(accion='resumen', entidad_id=CLI_CARRITO), 'GET', usuario_id=U_CONS)
r_q2 = gn_ajax('proveedores_ctacte_ajax.php', dict(accion='resumen', entidad_id=PROV_ID), 'GET', usuario_id=U_CONS)
check(ok_resp(r_q1) and ok_resp(r_q2), 'Usuario Consulta lee Cta Cte Clientes y Proveedores', err(r_q1) + ' ' + err(r_q2))

r_b1 = gn_ajax('distribucion_hojas_ajax.php', dict(accion='hoja_crear', pagina_idx=649, fecha_reparto=HOY, camion_id=1), usuario_id=U_CONS)
r_b2 = gn_ajax('cobranzas_ajax.php', dict(accion='guardar', pagina_idx=605, entidad_id=CLI_CARRITO, sucursal_id=SUC_ID, medios='[]'), usuario_id=U_CONS)
r_b3 = gn_ajax('mostrador_ajax.php', dict(accion='facturar', pagina_idx=650, items='[]', medios='[]'), usuario_id=U_CONS)
check(not ok_resp(r_b1) and not ok_resp(r_b2) and not ok_resp(r_b3),
      'Servidor bloquea escrituras al perfil Consulta (hoja_crear, cobranzas, mostrador)',
      f'b1={ok_resp(r_b1)}, b2={ok_resp(r_b2)}, b3={ok_resp(r_b3)}')

# =============================================================================================
# 16. AUDITORÍA CONTABLE GLOBAL DE EMPRESA 1000
# =============================================================================================
caso('16. Auditoría Contable: todos los asientos de hoy balanceados (DEBE == HABER)')
desbal = sql(f"""
    SELECT a.cont_asiento_id, a.descripcion, ROUND(SUM(d.importe_local), 2) AS diferencia
    FROM gestion__cont_asientos a
    JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id
    WHERE a.empresa_id = {EID} AND a.f_asiento = '{HOY}' AND a.tabla_estado_registro_id <> 6
    GROUP BY a.cont_asiento_id
    HAVING ABS(SUM(d.importe_local)) > 0.01
""")
tot_as = int(val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE empresa_id={EID} AND f_asiento='{HOY}' AND tabla_estado_registro_id <> 6") or 0)
check(tot_as > 0 and len(desbal) == 0,
      f'{tot_as} asientos contables generados hoy en Empresa 1000, 0 desbalanceados', str(desbal)[:300])

REGISTRO['stock_snapshot']['final'] = {'INF-001': stock_emp(P1), 'INF-002': stock_emp(P2), 'INF-003': stock_emp(P3)}
REGISTRO['checks'] = [{'caso': c, 'chequeo': ch, 'ok': ok, 'detalle': det} for (c, ch, ok, det) in RESULTADOS]

out_json = os.path.join(BASE_DIR, 'scratch', 'sim_emp1000_profunda_resultado.json')
with open(out_json, 'w', encoding='utf-8') as f:
    json.dump(REGISTRO, f, ensure_ascii=False, indent=2, default=str)

fallas = [x for x in RESULTADOS if not x[2]]
print(f"\nRESUMEN PROFUNDO: {len(RESULTADOS) - len(fallas)}/{len(RESULTADOS)} checks OK. JSON en {out_json}")
if fallas:
    for c, ch, _, det in fallas:
        print(f"  FALLA [{c}] {ch}: {det}")
