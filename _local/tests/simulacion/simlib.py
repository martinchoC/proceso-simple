"""Biblioteca del simulador de circuitos de gestion_multipyme.

Llama a los _ajax.php reales (vía run_ajax.php, con sesión simulada) contra una COPIA de la base
(gestion_multipyme_sim) y ofrece ayudas para consultar la copia y registrar chequeos.
Nunca escribe en la base real: run_ajax.php fija DB_NAME a la copia antes de cargar .env.

Rutas configurables por variables de entorno (por defecto, XAMPP en C:\\xampp):
  SIM_PHP, SIM_MYSQL, SIM_MYSQLDUMP, SIM_DB (nombre de la copia), SIM_DB_ORIGEN (base a clonar).
"""
import datetime
import json
import os
import subprocess
import tempfile

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDAS = os.path.join(AQUI, 'salidas')
os.makedirs(SALIDAS, exist_ok=True)

PHP = os.environ.get('SIM_PHP', r'C:\xampp\php\php.exe')
MYSQL = os.environ.get('SIM_MYSQL', r'C:\xampp\mysql\bin\mysql.exe')
MYSQLDUMP = os.environ.get('SIM_MYSQLDUMP', r'C:\xampp\mysql\bin\mysqldump.exe')
DB = os.environ.get('SIM_DB', 'gestion_multipyme_sim')
DB_ORIGEN = os.environ.get('SIM_DB_ORIGEN', 'gestion_multipyme')
HOY = datetime.date.today().isoformat()

# Modo base local (run.py --base-local): corre los casos directamente sobre la base origen para poder mirar el
# resultado en las pantallas. Solo a pedido explícito del usuario; el agente nunca lo usa.
BASE_LOCAL = os.environ.get('SIM_BASE_LOCAL') == '1'
if BASE_LOCAL:
    DB = DB_ORIGEN
elif DB == DB_ORIGEN:
    raise SystemExit('SIM_DB no puede ser la base real: el simulador escribe en la copia.')

RESULTADOS = []      # (caso, chequeo, ok, detalle)
BRECHAS = []         # (caso, texto, detalle): lo que no se puede hacer desde la aplicación (no cuenta como falla)
CASO = ['']


# ------------------------------------------------------------------ copia de la base
def clonar_base(scripts=()):
    """DROP + CREATE de la copia, volcado de la base origen (con rutinas y triggers) y scripts SQL opcionales."""
    subprocess.run([MYSQL, '-u', 'root', '-e', f'DROP DATABASE IF EXISTS `{DB}`; CREATE DATABASE `{DB}` '
                    'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'], check=True)
    dump = subprocess.Popen([MYSQLDUMP, '-u', 'root', '--routines', '--triggers', '--single-transaction', DB_ORIGEN],
                            stdout=subprocess.PIPE)
    subprocess.run([MYSQL, '-u', 'root', DB], stdin=dump.stdout, check=True)
    dump.wait()
    for s in scripts:
        with open(s, 'rb') as f:
            r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB], stdin=f, capture_output=True)
        if r.returncode != 0:
            raise SystemExit(f'Falló el script {s}:\n{r.stderr.decode("utf-8", "replace")}')
        print(f'Script aplicado en la copia: {os.path.basename(s)}')


# ------------------------------------------------------------------ registro de chequeos
def caso(nombre):
    CASO[0] = nombre
    print('\n' + '=' * 70 + '\n' + nombre + '\n' + '=' * 70)


def check(cond, texto, detalle=''):
    RESULTADOS.append((CASO[0], texto, bool(cond), detalle))
    print(('  OK    ' if cond else '  FALLA ') + texto + (f'  [{detalle}]' if detalle and not cond else ''))
    return bool(cond)


def brecha(texto, detalle=''):
    """Registra algo que la aplicación no permite hacer (p. ej. un maestro sin página): se completa por SQL y se sigue."""
    BRECHAS.append((CASO[0], texto, detalle))
    print('  BRECHA ' + texto + (f'  [{detalle}]' if detalle else ''))


def resumen():
    print('\n' + '#' * 70 + '\nRESUMEN\n' + '#' * 70)
    casos = []
    for c, _, _, _ in RESULTADOS:
        if c not in casos:
            casos.append(c)
    for c in casos:
        filas = [r for r in RESULTADOS if r[0] == c]
        fallas = [r for r in filas if not r[2]]
        print(f"{'OK   ' if not fallas else 'FALLA'} {c}  ({len(filas) - len(fallas)}/{len(filas)})")
        for r in fallas:
            print(f"        - {r[1]}" + (f": {r[3]}" if r[3] else ''))
    if BRECHAS:
        print('\nBRECHAS (sin página en la aplicación; completadas por SQL):')
        for c, t, d in BRECHAS:
            print(f"  - [{c}] {t}" + (f": {d}" if d else ''))
    total_f = sum(1 for r in RESULTADOS if not r[2])
    print(f"\nChequeos: {len(RESULTADOS)}  Fallas: {total_f}")
    with open(os.path.join(SALIDAS, 'resultados.json'), 'w', encoding='utf-8') as f:
        json.dump({'chequeos': RESULTADOS, 'brechas': BRECHAS}, f, ensure_ascii=False, indent=1)
    return total_f


# ------------------------------------------------------------------ llamadas a los _ajax.php
def ajax(archivo, params, method='POST', usuario_id=1, modulo='gestion', empresa_id=2):
    """Ejecuta modules/<modulo>/<archivo> como el navegador y devuelve el JSON de respuesta.
    La sesión simulada queda en empresa_id (2 Casa Lucho por defecto; gestion_normal se prueba con la 6).
    'detalles' e 'impuestos_adicionales' se mandan como JSON string, igual que las pantallas."""
    p = dict(params)
    p['_method'] = method
    p['_usuario_id'] = usuario_id
    p['_modulo'] = modulo
    p['_empresa_id'] = empresa_id
    for k, v in list(p.items()):
        if isinstance(v, (list, dict)) and k in ('detalles', 'impuestos_adicionales'):
            p[k] = json.dumps(v)
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
        json.dump(p, f)
        ruta = f.name
    try:
        r = subprocess.run([PHP, os.path.join(AQUI, 'run_ajax.php'), archivo, ruta, DB] + (['permitir_base_real'] if BASE_LOCAL else []),
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
    finally:
        os.unlink(ruta)
    salida = r.stdout.strip()
    inicio = min([i for i in (salida.find('{'), salida.find('[')) if i >= 0], default=-1)
    if inicio < 0:
        return {'_raw': salida, '_stderr': r.stderr[-2000:]}
    for i in range(inicio, len(salida)):
        if salida[i] in '{[':
            try:
                return json.loads(salida[i:])
            except Exception:
                continue
    return {'_raw': salida[-3000:], '_stderr': r.stderr[-2000:]}


def core(funcion, *args, requires=('core/pedidos_venta/externo.php',), base=None):
    """Llama a una función de core/ con la conexión a la copia como primer argumento (run_core.php; canales externos,
    doc 28). Devuelve el resultado, o {'_error': mensaje} si lanzó una excepción."""
    with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
        json.dump({'requires': list(requires), 'funcion': funcion, 'args': list(args)}, f)
        ruta = f.name
    try:
        r = subprocess.run([PHP, os.path.join(AQUI, 'run_core.php'), ruta, base or DB],
                           capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=300)
    finally:
        os.unlink(ruta)
    salida = r.stdout.strip()
    try:
        j = json.loads(salida[salida.find('{'):])
    except Exception:
        return {'_error': 'salida no JSON: ' + salida[-1500:] + r.stderr[-800:]}
    return j.get('resultado') if j.get('ok') else {'_error': j.get('error')}


def ok_resp(r):
    return isinstance(r, dict) and bool(r.get('success') or r.get('resultado'))


def err(r):
    if isinstance(r, dict):
        return r.get('error') or r.get('message') or r.get('_raw', '')[:400] or json.dumps(r)[:400]
    return str(r)[:400]


# ------------------------------------------------------------------ consultas a la copia
def sql(q):
    r = subprocess.run([MYSQL, '-u', 'root', DB, '--default-character-set=utf8mb4', '-B', '-e', q],
                       capture_output=True, text=True, encoding='utf-8', errors='replace')
    if r.returncode != 0:
        raise RuntimeError(r.stderr + '\n' + q)
    lineas = [l for l in r.stdout.splitlines() if l != '']
    if not lineas:
        return []
    cols = lineas[0].split('\t')
    return [dict(zip(cols, [None if v == 'NULL' else v for v in l.split('\t')])) for l in lineas[1:]]


def uno(q):
    f = sql(q)
    return f[0] if f else None


def val(q):
    f = uno(q)
    return None if f is None else list(f.values())[0]


def num(v):
    return float(v or 0)


def proximo_nro_proveedor(pv, base):
    """Primer número libre de factura de proveedor en ese PV (los casos no deben chocar con corridas anteriores
    en la base local, ni con los datos que la copia trae de ella)."""
    return max(int(base), int(val(f"SELECT IFNULL(MAX(comprobante_nro),0) FROM gestion__facturas_proveedores WHERE comprobante_pv={int(pv)}") or 0)) + 1


def stock(producto_id, boca_id=1, empresa_id=2):
    return num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id={int(empresa_id)} AND boca_id={boca_id} AND producto_id={producto_id}"))


# ------------------------------------------------------------------ armado de detalles
def linea(producto_id, cantidad, precio, iva=21.0, iva_id=1, **extra):
    """Línea de detalle con los importes calculados como en el front (compras / OC / pedidos)."""
    neto = round(cantidad * precio, 2)
    iva_imp = round(neto * iva / 100, 2)
    d = dict(producto_id=producto_id, cantidad=cantidad, precio_unitario=precio, precio_unitario_bruto=precio,
             precio_unitario_neto=precio, descuento_item_pct=0, descuento_general_pct=0, descuento_item=0,
             descuento_general=0, descuento=0, no_gravado=0, exento=0, iva_alicuota_id=iva_id,
             iva_porcentaje=iva, neto_gravado=neto, iva_importe=iva_imp, total_linea=round(neto + iva_imp, 2))
    d.update(extra)
    return d


def totales(dets):
    neto = round(sum(d['neto_gravado'] for d in dets), 2)
    iva = round(sum(d['iva_importe'] for d in dets), 2)
    return dict(subtotal=neto, impuestos=iva, descuentos=0, no_gravado=0, exento=0, total=round(neto + iva, 2))
