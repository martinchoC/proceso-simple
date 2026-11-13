"""Carga en Developsam SAS (empresa 6, módulo 5 gestion_normal, base LOCAL) las 6 facturas B de
_local/docs/202609-SIRIS-*.pdf (abonos 09/2026, PV 00002, N° 76 a 81, con CAE ya otorgado por ARCA).

Todo por los _ajax.php reales (runner del simulador en modo base local) con la sesión de jgili:
punto de venta 0002, clientes, servicios y facturas (alta + Confirmar -> Pendiente de ARCA). El CAE
existente se registra con 20261024_registrar_cae_existente.php (vfArcaGuardarAutorizacion), sin pedirlo
de nuevo a ARCA. Idempotente: lo que ya existe se reutiliza.
"""
import json
import os
import subprocess
import sys

os.environ['SIM_BASE_LOCAL'] = '1'
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'tests', 'simulacion'))
from simlib import ajax, ok_resp, err, val, uno, PHP  # noqa: E402

EMP = 6
USR = 11  # jgili
SUC = 4   # Casa Central de Developsam
BOCA = 5
F_EMISION, F_VTO = '2026-09-30', '2026-10-15'
VTO_CAE = '20261010'


def aj(archivo, params):
    p = dict(params, empresa_idx=EMP)
    return ajax(archivo, p, 'POST', usuario_id=USR, modulo='gestion_normal', empresa_id=EMP)


def falla(texto, r=None):
    raise SystemExit(f'ERROR {texto}: {err(r) if r is not None else ""}')


def q(texto):
    return texto.replace("'", "''")


# ---------------------------------------------------------------- punto de venta 0002
# Numerador de Factura B en 81: la próxima que se apruebe con ARCA es la 82 (la que espera ARCA).
tipo_fb = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {EMP} AND codigo = 'V_FB'"))
ULTIMO_FB = 81
pv = val(f"SELECT punto_venta_id FROM gestion__puntos_venta WHERE empresa_id = {EMP} AND codigo_fiscal = 2")
if not pv:
    tipos = [int(x) for x in val(
        f"SELECT GROUP_CONCAT(comprobante_tipo_id) FROM gestion__puntos_venta_comprobantes "
        f"WHERE empresa_id = {EMP} AND punto_venta_id = 303 AND tabla_estado_registro_id = 1").split(',')]
    r = aj('puntos_venta_ajax.php', dict(accion='agregar', pagina_idx=570, sucursal_id=SUC, boca_id=BOCA, nombre='Punto de Venta 2',
                                         descripcion='Facturación Electrónica ARCA PV 00002', codigo_fiscal=2, sistema_emision='WSFE',
                                         comprobantes=json.dumps([dict(comprobante_tipo_id=t, requiere_afip=1, ultimo_numero=ULTIMO_FB if t == tipo_fb else 0)
                                                                  for t in tipos])))
    if not (isinstance(r, dict) and r.get('resultado')):
        falla('alta del punto de venta 0002', r)
    pv = r['punto_venta_id']
pv = int(pv)
print('PV 0002 =', pv)

# ---------------------------------------------------------------- clientes
# clave, razón social, CUIT, domicilio, localidad_id, condición IVA (4 exento / 2 CF), sede IIBB
CLIENTES = [
    ('AECRC', 'ASOCIACION EMPLEADOS DE COMERCIO DE RIO COLORADO', '30645039927', 'Hipolito Yrigoyen 851', 5416, 4, 16),
    ('CECCdP', 'CENTRO EMPLEADOS DE COMERCIO', '30635090525', 'Garibaldi 323', 3013, 4, 2),
    ('CECGP', 'CENTRO EMPLEADOS DE COMERCIO DE GRAL PICO', '30541512590', '13 758', 7093, 2, 11),
    ('SECT', 'SINDICATO DE EMPLEADOS DE COMERCIO', '30548970837', 'Edison 330', 25772, 4, 7),
    ('AECN', 'SINDICATO EMPLEADOS DE COMERCIO DE NECOCHEA', '33533636409', '59 3131', 21493, 4, 2),
    ('AECBB', 'ASOCIACION EMPLEADOS DE COMERCIO DE BAHIA BLANCA', '30526358054', 'Rodriguez 60', 16353, 4, 2),
]
cli = {}
for clave, nombre, cuit, dom, loc, cond, sede in CLIENTES:
    ent = val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {EMP} AND cuit = '{cuit}'")
    if not ent:
        r = aj('clientes_ajax.php', dict(accion='agregar_entidad', pagina_idx=594, entidad_nombre=nombre, entidad_fantasia=clave, entidad_tipo_id='',
                                         cuit=cuit, sitio_web='', domicilio_legal=dom, localidad_id=loc, es_proveedor=0, es_cliente=1,
                                         observaciones='', condicion_fiscal_id=cond, regimen_iibb_id=3, numero_inscripcion_iibb='',
                                         jurisdiccion_sede_id=sede, fiscal_f_desde='2025-08-01'))
        if not ok_resp(r):
            falla(f'cliente {clave}', r)
        ent = (r.get('entidad_id') if isinstance(r, dict) else None) or val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {EMP} AND cuit = '{cuit}'")
    suc = val(f"SELECT sucursal_id FROM gestion__entidades_sucursales WHERE entidad_id = {int(ent)} ORDER BY sucursal_numero LIMIT 1")
    cli[clave] = (int(ent), int(suc) if suc else '')
print('clientes =', cli)

# ---------------------------------------------------------------- servicios
PRODUCTOS = [
    ('SIRIS', 'SIRIS - Abono mensual Sistema de Recaudación Web'),
    ('SIGA', 'SIGA - Abono mensual Sistema Integral de Gestión de Afiliados Web'),
    ('SIRIS-SIND', 'SIRIS-SINDICATO Abono mensual'),
    ('SIRIS-MUT', 'SIRIS-MUTUAL Abono mensual'),
]
tipo_serv = int(val(f"SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = {EMP} AND maneja_stock = 0 ORDER BY 1 LIMIT 1"))
cat = int(val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {EMP} AND producto_categoria_nombre = 'Licencias SaaS'"))
iva21 = int(val(f"SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {EMP} AND porcentaje = 21 AND es_gravado = 1"))
prod = {}
for cod, nombre in PRODUCTOS:
    pid = val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {EMP} AND producto_codigo = '{cod}'")
    if not pid:
        r = aj('productos_ajax.php', dict(accion='agregar', pagina_idx=540, producto_codigo=cod, producto_nombre=nombre, producto_descripcion=nombre,
                                          producto_tipo_id=tipo_serv, controla_stock=0, es_servicio=1, aplica_venta=1, aplica_compra=0,
                                          iva_alicuota_id=iva21, unidad_medida_id=1, producto_categoria_id=cat))
        if not ok_resp(r):
            falla(f'servicio {cod}', r)
        pid = (r.get('producto_id') if isinstance(r, dict) else None) or val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {EMP} AND producto_codigo = '{cod}'")
    prod[cod] = int(pid)
print('servicios =', prod)

# ---------------------------------------------------------------- facturas
# Precio unitario = precio final de la factura B (IVA 21 % contenido): neto = final / 1,21 con 4 decimales,
# así el total recalculado por el sistema da exacto al centavo.
MES = 'correspondiente al mes 09/26'
FACTURAS = [
    (76, 'AECRC', '86405733042928', [('SIRIS', 131917.60, MES)]),
    (77, 'CECCdP', '86405733152570', [('SIRIS', 131917.60, MES)]),
    (78, 'CECGP', '86405733478324', [('SIRIS', 356177.52, MES), ('SIGA', 356177.52, MES)]),
    (79, 'SECT', '86405734115271', [('SIRIS-SIND', 609459.31, 'Total de declarados 6723 según tarifador único mes 09/26'),
                                    ('SIRIS-MUT', 263835.20, 'Total de declarados 1280 según tarifador único mes 09/26')]),
    (80, 'AECN', '86405734395384', [('SIRIS', 430974.80, MES)]),
    (81, 'AECBB', '86405734617074', [('SIRIS', 779298.21, MES)]),
]
moneda =int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {EMP} ORDER BY 1 LIMIT 1"))
cond_pago = int(val(f"SELECT condicion_pago_id FROM gestion__condiciones_pago WHERE empresa_id = {EMP} AND codigo = '15D'"))
for nro, clave, cae, lineas in FACTURAS:
    ent, suc = cli[clave]
    ya = uno(f"SELECT venta_factura_id, tabla_estado_registro_id estado, CAE FROM gestion__ventas_facturas WHERE empresa_id = {EMP} "
             f"AND punto_venta_id = {pv} AND comprobante_tipo_id = {tipo_fb} AND comprobante_nro = {nro}")
    if ya:
        print(f'N° {nro}: ya cargada (id {ya["venta_factura_id"]}, estado {ya["estado"]})')
        continue
    total_pdf = round(sum(p for _, p, _ in lineas), 2)
    # Un borrador de una corrida anterior (mismo cliente, PV, tipo y fecha, sin número) se reutiliza
    fid = val(f"SELECT venta_factura_id FROM gestion__ventas_facturas WHERE empresa_id = {EMP} AND punto_venta_id = {pv} "
              f"AND comprobante_tipo_id = {tipo_fb} AND entidad_id = {ent} AND f_emision = '{F_EMISION}' "
              f"AND comprobante_nro = 0 AND tabla_estado_registro_id = 3 ORDER BY 1 LIMIT 1")
    if not fid:
        dets = [dict(producto_id=prod[c], cantidad=1, precio_unitario=round(p / 1.21, 4), descuento_general_pct=0,
                     iva_alicuota_id=iva21, iva_porcentaje=21, leyenda=ley) for c, p, ley in lineas]
        r = aj('ventas_facturas_ajax.php', dict(accion='agregar', pagina_idx=556, punto_venta_id=pv, sucursal_id=SUC, comprobante_tipo_id=tipo_fb,
                                                entidad_id=ent, entidad_sucursal_id=suc, f_emision=F_EMISION, f_vto=F_VTO, moneda_id=moneda,
                                                tipo_cambio=1, condicion_pago_id=cond_pago, descuento_general_pct=0, comprobante_nro=0,
                                                observaciones='Período facturado: 01/09/2026 al 30/09/2026', detalles=dets))
        fid = r.get('venta_factura_id') if isinstance(r, dict) else None
        if not fid:
            falla(f'alta de la factura {nro}', r)
    total = float(val(f"SELECT importe_total FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}"))
    if abs(total - total_pdf) >= 0.005:
        falla(f'factura {nro}: total {total} distinto del PDF {total_pdf} (queda en borrador, id {fid})')
    # Confirmar con el CAE ya otorgado (Confirmar + Aprobada sin pedir otro CAE a ARCA)
    s = subprocess.run([PHP, os.path.join(AQUI, '20261024_registrar_cae_existente.php'), str(EMP), str(USR), str(fid), str(nro), cae, VTO_CAE, '2'],
                       capture_output=True, text=True, encoding='utf-8', errors='replace')
    salida = s.stdout.strip()
    try:
        res = json.loads(salida[salida.find('{'):])
    except ValueError:
        res = {'success': False, 'error': salida + s.stderr[-1500:]}
    if not res.get('success'):
        falla(f'CAE de la factura {nro} (id {fid})', res)
    print(f'N° {nro} {clave}: id {fid}, total {total:.2f} -', res.get('message'))

num = val(f"SELECT ultimo_numero FROM gestion__comprobantes_numeradores WHERE empresa_id = {EMP} AND punto_venta_id = {pv} AND comprobante_tipo_id = {tipo_fb}")
print('Numerador PV 0002 Factura B (último):', num)
