"""
Genera archivos "Mis Comprobantes Emitidos / Recibidos" de ARCA con datos inventados, uno por cliente del estudio,
a partir de los archivos reales de _local/docs (CUIT 30719136482), para probar Importar Mis Comprobantes (página 419).

Qué conserva de cada fila: fecha, tipo, punto de venta, número, columnas con importe (qué alícuotas, residuales de
combustible de los tiques, diferencias de centavos, notas de crédito) y la cantidad de filas.
Qué inventa: CUIT y razón social de las contrapartes (CUIT con dígito verificador válido, el mismo proveedor / cliente
sigue siendo el mismo dentro de los archivos de un cliente), CAE e importes (cada fila se multiplica por un factor al
azar entre 0,55 y 1,75; todas sus columnas por el mismo, así neto + IVA + otros siguen cerrando como en el original).
El receptor (Recibidos) y el título pasan a ser el CUIT del cliente.

Según la condición frente al IVA del cliente (estudio__clientes.condicion_iva_id):
  1 Responsable Inscripto (y cualquier otra): tipos como el original.
  3 Monotributista: emite C (sin IVA, solo Imp. Total) y recibe B en lugar de A.

Uso (Python de Anaconda, no hay python en el PATH):
  /c/Users/devel/anaconda3/python.exe -I _local/tools/mis_comprobantes_prueba.py [--empresa 2] [--base gestion_multipyme] [--salida DIR]
Salida por defecto: _local/docs/mis_comprobantes_prueba/ con el nombre de ARCA y el CUIT del cliente.
Es determinístico: correrlo de nuevo genera los mismos archivos (sirve para probar duplicados al reimportar).
"""
import argparse
import glob
import os
import random
import re
import subprocess
import sys

import openpyxl

RAIZ = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
DOCS = os.path.join(RAIZ, '_local', 'docs')
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
CUIT_ORIGEN = 30719136482

NOMBRES = ['MARIA', 'JUAN', 'LUCIA', 'PABLO', 'SOFIA', 'DIEGO', 'VALERIA', 'MARTIN', 'CAROLINA', 'NICOLAS', 'ANDREA',
           'GUSTAVO', 'FLORENCIA', 'SERGIO', 'NATALIA', 'HERNAN', 'PAULA', 'GABRIEL', 'ROMINA', 'FACUNDO']
APELLIDOS = ['ACOSTA', 'BENITEZ', 'CORREA', 'DOMINGUEZ', 'ESCOBAR', 'FIGUEROA', 'GIMENEZ', 'HERRERA', 'IBARRA', 'JUAREZ',
             'LEDESMA', 'MOLINA', 'NAVARRO', 'OJEDA', 'PERALTA', 'QUIROGA', 'RIOS', 'SOSA', 'TOLEDO', 'VERA', 'ZALAZAR']
RUBROS = ['DISTRIBUIDORA', 'SERVICIOS', 'COMERCIAL', 'INSUMOS', 'TRANSPORTES', 'AGROPECUARIA', 'CONSTRUCTORA',
          'LIBRERIA', 'FERRETERIA', 'COMBUSTIBLES', 'ALIMENTOS', 'TECNOLOGIA', 'LOGISTICA', 'MAYORISTA']
LUGARES = ['DEL SUR', 'PATAGONICA', 'DEL VALLE', 'ATLANTICA', 'CENTRAL', 'DEL NORTE', 'PAMPEANA', 'ANDINA', 'DEL LITORAL',
           'COSTERA', 'SERRANA', 'DEL PLATA', 'DEL OESTE', 'AUSTRAL']
SOCIEDADES = ['SA', 'SRL', 'SAS', 'SRL', 'SA']

# Tipos ARCA: original -> monotributista
EMITIDOS_MONO = {'6 - Factura B': '11 - Factura C', '7 - Nota de Débito B': '12 - Nota de Débito C',
                 '8 - Nota de Crédito B': '13 - Nota de Crédito C', '1 - Factura A': '11 - Factura C',
                 '2 - Nota de Débito A': '12 - Nota de Débito C', '3 - Nota de Crédito A': '13 - Nota de Crédito C'}
RECIBIDOS_MONO = {'1 - Factura A': '6 - Factura B', '2 - Nota de Débito A': '7 - Nota de Débito B',
                  '3 - Nota de Crédito A': '8 - Nota de Crédito B', '81 - Tique Factura A': '82 - Tique Factura B'}


def cuit_dv(base10: str) -> int:
    pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]
    r = 11 - sum(int(d) * p for d, p in zip(base10, pesos)) % 11
    return 0 if r == 11 else r  # 10 = inválido, se descarta afuera


def cuit_al_azar(rng: random.Random, prefijo: str, prohibidos: set) -> int:
    while True:
        base = prefijo + ''.join(str(rng.randint(0, 9)) for _ in range(8))
        dv = cuit_dv(base)
        if dv == 10:
            continue
        cuit = int(base + str(dv))
        if cuit not in prohibidos:
            return cuit


def nombre_al_azar(rng: random.Random, persona: bool) -> str:
    if persona:
        return f'{rng.choice(APELLIDOS)} {rng.choice(NOMBRES)}' + (f' {rng.choice(NOMBRES)}' if rng.random() < 0.4 else '')
    return f'{rng.choice(RUBROS)} {rng.choice(LUGARES)} {rng.choice(SOCIEDADES)}'


def clientes(base: str, empresa: int) -> list:
    sql = ("SELECT cuit, condicion_iva_id FROM estudio__clientes "
           f"WHERE empresa_id = {int(empresa)} AND tabla_estado_registro_id = 1 ORDER BY cliente_id")
    out = subprocess.run([MYSQL, '-uroot', '-N', '--default-character-set=utf8mb4', base, '-e', sql],
                         capture_output=True, text=True, encoding='utf-8', check=True).stdout
    filas = []
    for linea in out.strip().splitlines():
        cuit, cond = linea.split('\t')
        cuit = re.sub(r'\D', '', cuit)
        if len(cuit) == 11:
            filas.append((int(cuit), int(cond or 1)))
    return filas


def columnas(ws) -> dict:
    return {str(c.value).strip(): c.column for c in ws[2] if c.value is not None}


def generar(origen: str, sentido: str, cliente: int, condicion: int, todos: set, salida: str):
    wb = openpyxl.load_workbook(origen)
    ws = wb.active
    col = columnas(ws)
    doc_col = col.get('Nro. Doc. Emisor' if sentido == 'Recibidos' else 'Nro. Doc. Receptor')
    nom_col = col.get('Denominación Emisor' if sentido == 'Recibidos' else 'Denominación Receptor')
    tdoc_col = col.get('Tipo Doc. Emisor' if sentido == 'Recibidos' else 'Tipo Doc. Receptor')
    rec_col = col.get('Nro. Doc. Receptor') if sentido == 'Recibidos' else None
    cae_col = col['Cód. Autorización']
    total_col = col['Imp. Total']
    primera_importe = col['Tipo Cambio'] + 2  # después de Tipo Cambio y Moneda
    importes = list(range(primera_importe, total_col + 1))
    iva_y_neto = [c for c in importes if c != total_col]
    mono = condicion == 3
    mapa_tipos = (RECIBIDOS_MONO if sentido == 'Recibidos' else EMITIDOS_MONO) if mono else {}

    ws.cell(1, 1).value = f'Mis Comprobantes {sentido} - CUIT {cliente}'
    contrapartes = {}
    for fila in range(3, ws.max_row + 1):
        tipo = ws.cell(fila, col['Tipo']).value
        if not tipo:
            continue
        clave = f"{cliente}|{sentido}|{ws.cell(fila, col['Fecha']).value}|{tipo}|{ws.cell(fila, col['Punto de Venta']).value}|" \
                f"{ws.cell(fila, col['Número Desde']).value}|{ws.cell(fila, doc_col).value}"
        rng = random.Random(clave)

        # Contraparte: mismo CUIT original -> mismo CUIT inventado para este cliente
        doc = ws.cell(fila, doc_col).value
        tdoc = str(ws.cell(fila, tdoc_col).value or '')
        if doc and int(doc) != 0:
            if doc not in contrapartes:
                r2 = random.Random(f'{cliente}|{doc}')
                s = str(int(doc))
                if tdoc == 'CUIT' and len(s) == 11:
                    nuevo = cuit_al_azar(r2, s[:2], todos | {CUIT_ORIGEN})
                    persona = s[:2] in ('20', '23', '24', '27')
                else:
                    nuevo = r2.randint(10_000_000, 45_000_000)
                    persona = True
                contrapartes[doc] = (nuevo, nombre_al_azar(r2, persona))
            ws.cell(fila, doc_col).value, ws.cell(fila, nom_col).value = contrapartes[doc]
        if rec_col:
            ws.cell(fila, rec_col).value = cliente
        if ws.cell(fila, cae_col).value:
            ws.cell(fila, cae_col).value = int(str(ws.cell(fila, cae_col).value)[:2] + ''.join(str(rng.randint(0, 9)) for _ in range(12)))

        # Importes: un factor por fila para todas las columnas
        k = rng.uniform(0.55, 1.75)
        for c in importes:
            v = ws.cell(fila, c).value
            if isinstance(v, (int, float)) and v != 0:
                ws.cell(fila, c).value = round(v * k, 2)

        # Monotributista: tipos C (emitidos, sin IVA ni neto) y B (recibidos)
        if tipo in mapa_tipos:
            ws.cell(fila, col['Tipo']).value = mapa_tipos[tipo]
            if sentido == 'Emitidos':
                for c in iva_y_neto:
                    if ws.cell(fila, c).value not in (None,):
                        ws.cell(fila, c).value = None if c < col['Neto Gravado Total'] else 0

    nombre = os.path.basename(origen).replace(str(CUIT_ORIGEN), str(cliente))
    destino = os.path.join(salida, nombre)
    wb.save(destino)
    return destino, ws.max_row - 2, len(contrapartes)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--empresa', type=int, default=2, help='empresa del estudio')
    ap.add_argument('--base', default='gestion_multipyme')
    ap.add_argument('--salida', default=os.path.join(DOCS, 'mis_comprobantes_prueba'))
    a = ap.parse_args()

    origenes = sorted(glob.glob(os.path.join(DOCS, f'Mis Comprobantes * - CUIT {CUIT_ORIGEN}*.xlsx')))
    if not origenes:
        sys.exit(f'No hay archivos de origen en {DOCS}')
    lista = clientes(a.base, a.empresa)
    if not lista:
        sys.exit('No hay clientes activos en el estudio')
    os.makedirs(a.salida, exist_ok=True)
    todos = {c for c, _ in lista}
    for cliente, cond in lista:
        for o in origenes:
            sentido = 'Recibidos' if 'Recibidos' in os.path.basename(o) else 'Emitidos'
            destino, filas, cps = generar(o, sentido, cliente, cond, todos, a.salida)
            print(f'{cliente}  cond {cond}  {sentido:9}  {filas:3} filas  {cps:3} contrapartes  {os.path.basename(destino)}')


if __name__ == '__main__':
    main()
