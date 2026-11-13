"""Lista de precios "Tarifador único" de Developsam SAS (empresa 6, gestion_normal, base LOCAL), según
_local/docs/Tarifador unico Julio a noviembre.pdf, vigente desde septiembre 2026 (Aporte Base 26.383,52).

Valor mensual (final, IVA incluido) = tramo de empleados x Aporte Base x % del tramo; más de 8.000 empleados =
empleados x Aporte Base x 0,30 % (servicio "por empleado", cantidad = declarados). La lista guarda el NETO
(la factura suma el IVA): neto = final / 1,21 con 4 decimales.

Todo por los _ajax.php reales con la sesión de jgili: servicios SIRIS-T* / SIGA-T* (+ -EMP), lista
TARIFADOR (origen MANUAL), precios, lista asignada en la condición de cliente de los 6 clientes, y baja de
los precios que había en la lista General. Idempotente.

Para una paritaria nueva: cambiar APORTE_BASE y F_DESDE y volver a correr (actualiza los precios).
"""
import os
import sys

os.environ['SIM_BASE_LOCAL'] = '1'
AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(AQUI, '..', 'tests', 'simulacion'))
from simlib import ajax, ok_resp, err, val, uno, sql  # noqa: E402

EMP = 6
USR = 11  # jgili
APORTE_BASE = 26383.52          # desde septiembre 2026 (acuerdo 23/07/2026)
F_DESDE = '2026-09-01'
TRAMOS = [(1000, 0.50), (2000, 0.50), (3000, 0.45), (4000, 0.40), (5000, 0.35), (6000, 0.35), (7000, 0.33), (8000, 0.30)]
PCT_MAS_8000 = 0.30
SISTEMAS = [('SIRIS', 'SIRIS - Abono mensual Sistema de Recaudación Web'),
            ('SIGA', 'SIGA - Abono mensual Sistema Integral de Gestión de Afiliados Web')]


def aj(archivo, params, method='POST'):
    return ajax(archivo, dict(params, empresa_idx=EMP), method, usuario_id=USR, modulo='gestion_normal', empresa_id=EMP)


def falla(texto, r=None):
    raise SystemExit(f'ERROR {texto}: {err(r) if r is not None else ""}')


def neto(final):
    return round(final / 1.21, 4)


# ---------------------------------------------------------------- servicios
tipo_serv = int(val(f"SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = {EMP} AND maneja_stock = 0 ORDER BY 1 LIMIT 1"))
cat = int(val(f"SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = {EMP} AND producto_categoria_nombre = 'Licencias SaaS'"))
iva21 = int(val(f"SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {EMP} AND porcentaje = 21 AND es_gravado = 1"))

items = []  # (codigo, nombre, precio final)
for sis, nombre in SISTEMAS:
    for tramo, pct in TRAMOS:
        items.append((f'{sis}-T{tramo}', f'{nombre} - Tramo hasta {tramo:,} empleados'.replace(',', '.'),
                      round(tramo * APORTE_BASE * pct / 100, 2)))
    items.append((f'{sis}-EMP', f'{nombre} - Más de 8.000 empleados (por empleado declarado)',
                   APORTE_BASE * PCT_MAS_8000 / 100))

prod = {}
for cod, nombre, _ in items:
    pid = val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {EMP} AND producto_codigo = '{cod}'")
    if not pid:
        r = aj('productos_ajax.php', dict(accion='agregar', pagina_idx=540, producto_codigo=cod, producto_nombre=nombre, producto_descripcion=nombre,
                                          producto_tipo_id=tipo_serv, controla_stock=0, es_servicio=1, aplica_venta=1, aplica_compra=0,
                                          iva_alicuota_id=iva21, unidad_medida_id=1, producto_categoria_id=cat))
        if not ok_resp(r):
            falla(f'servicio {cod}', r)
        pid = val(f"SELECT producto_id FROM gestion__productos WHERE empresa_id = {EMP} AND producto_codigo = '{cod}'")
    prod[cod] = int(pid)
print(f'{len(prod)} servicios')

# ---------------------------------------------------------------- lista
lista = val(f"SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = {EMP} AND lista_precio_codigo = 'TARIFADOR'")
if not lista:
    moneda = int(val(f"SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {EMP} ORDER BY 1 LIMIT 1"))
    r = aj('listas_precios_ajax.php', dict(accion='agregar', pagina_idx=553, lista_precio_codigo='TARIFADOR', lista_precio_nombre='Tarifador único',
                                           descripcion='Abonos SIRIS / SIGA por tramo de empleados declarados (tramo x Aporte Base x %). Precios netos de IVA.',
                                           lista_precio_origen_id=3, moneda_id=moneda, observaciones=''))
    if not ok_resp(r):
        falla('alta de la lista', r)
    lista = val(f"SELECT lista_precio_id FROM gestion__listas_precios WHERE empresa_id = {EMP} AND lista_precio_codigo = 'TARIFADOR'")
lista = int(lista)
print('lista Tarifador único =', lista)

# ---------------------------------------------------------------- precios
for cod, _, final in items:
    precio = neto(final) if not cod.endswith('-EMP') else round(final / 1.21, 4)
    fila = uno(f"SELECT lista_precio_producto_id id, precio_final FROM gestion__listas_precios_productos WHERE empresa_id = {EMP} "
               f"AND lista_precio_id = {lista} AND producto_id = {prod[cod]} AND tabla_estado_registro_id = 1")
    if fila and abs(float(fila['precio_final']) - precio) < 0.00005:
        continue
    base = dict(lista_precio_id=lista, producto_id=prod[cod], precio_unitario=f'{precio:.4f}', ajuste_id='')
    r = aj('listas_precios_productos_ajax.php', dict(base, accion='editar', lista_precio_producto_id=fila['id']) if fila else dict(base, accion='agregar'))
    if not (isinstance(r, dict) and r.get('resultado')):
        falla(f'precio {cod}', r)
    print(f'  {cod:10s} final {final:>12,.2f}  neto {precio:,.4f}')

# ---------------------------------------------------------------- clientes: lista en la condición vigente
CLIENTES = ['30645039927', '30635090525', '30541512590', '30548970837', '33533636409', '30526358054']
cond_pago = int(val(f"SELECT condicion_pago_id FROM gestion__condiciones_pago WHERE empresa_id = {EMP} AND codigo = '15D'"))
for cuit in CLIENTES:
    ent = int(val(f"SELECT entidad_id FROM gestion__entidades WHERE empresa_id = {EMP} AND cuit = '{cuit}'"))
    c = uno(f"SELECT * FROM gestion__entidades_condiciones_clientes WHERE entidad_id = {ent} AND tabla_estado_registro_id = 1 "
            f"AND (f_hasta IS NULL OR f_hasta >= CURDATE()) ORDER BY f_desde DESC LIMIT 1")
    if c and c['lista_precio_id'] == str(lista):
        continue
    datos = dict(entidad_id=ent, lista_precio_id=lista, pagina_idx=594)
    if c:
        datos.update(accion='editar_condicion_cliente', condicion_cliente_id=c['entidad_condicion_cliente_id'],
                     condicion_pago_id=c['condicion_pago_id'] or cond_pago, entidad_cliente_tipo_id=c['entidad_cliente_tipo_id'] or '',
                     limite_credito=c['limite_credito'] or '', cliente_descuento_general=c['cliente_descuento_general'] or '',
                     f_desde=c['f_desde'], f_hasta=c['f_hasta'] or '')
    else:
        datos.update(accion='agregar_condicion_cliente', condicion_pago_id=cond_pago, entidad_cliente_tipo_id='', limite_credito='',
                     cliente_descuento_general='', f_desde=str(__import__('datetime').date.today()), f_hasta='')
    r = aj('clientes_ajax.php', datos)
    if not (isinstance(r, dict) and (r.get('resultado') or r.get('success'))):
        falla(f'condición del cliente {cuit}', r)
    print('  lista asignada a', cuit)

# ---------------------------------------------------------------- baja de los precios de la lista General
for f in sql(f"SELECT lpp.lista_precio_producto_id id FROM gestion__listas_precios_productos lpp JOIN gestion__listas_precios lp "
             f"ON lp.lista_precio_id = lpp.lista_precio_id WHERE lpp.empresa_id = {EMP} AND lp.lista_precio_nombre = 'General' "
             f"AND lpp.tabla_estado_registro_id = 1"):
    r = aj('listas_precios_productos_ajax.php', dict(accion='eliminar', lista_precio_producto_id=f['id']), 'GET')
    if not (isinstance(r, dict) and r.get('resultado')):
        falla(f'baja del precio {f["id"]}', r)
    print('  baja precio lista General', f['id'])
print('listo')
