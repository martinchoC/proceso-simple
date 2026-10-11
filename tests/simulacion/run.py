"""Simulador de circuitos de gestion_multipyme.

Clona gestion_multipyme en gestion_multipyme_sim, corre los casos llamando a los _ajax.php reales y al
final verifica la consistencia global de todo lo generado (stock contra libro, saldos de comprobantes contra
imputaciones, estados 5/39/40, asientos balanceados, tesorería contra libro, espejos de remitos).

Uso (desde la raíz del repo):
  python tests/simulacion/run.py                      # todo
  python tests/simulacion/run.py circuitos notas      # por grupo
  python tests/simulacion/run.py 04_notas_credito_ventas.py
  python tests/simulacion/run.py --scripts sql/mantenimiento/AAAAMMDD_x.sql   # aplica scripts a la copia antes
  python tests/simulacion/run.py --sin-clonar notas   # reutiliza la copia actual (más rápido, menos aislado)
  python tests/simulacion/run.py --listar

Sale con código 1 si hay fallas. Nunca toca la base real.
"""
import argparse
import glob
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
if '--base-local' in sys.argv:
    os.environ['SIM_BASE_LOCAL'] = '1'   # antes de importar simlib, que fija la base al cargarse
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
from simlib import *  # noqa: E402,F401

CASOS_DIR = os.path.join(AQUI, 'casos')
GRUPOS = {
    'circuitos': ['01_compras.py', '02_pagos.py', '03_ventas.py'],
    'notas': ['04_notas_credito_ventas.py', '05_notas_credito_proveedores.py'],
    'consumidor_final': ['06_consumidor_final.py'],
}


def todos_los_casos():
    return sorted(os.path.basename(p) for p in glob.glob(os.path.join(CASOS_DIR, '[0-9][0-9]_*.py')))


def resolver(seleccion):
    if not seleccion:
        return todos_los_casos()
    archivos = []
    for s in seleccion:
        lista = GRUPOS.get(s, [s if s.endswith('.py') else s + '.py'])
        for a in lista:
            if not os.path.exists(os.path.join(CASOS_DIR, a)):
                raise SystemExit(f'No existe el caso o grupo: {s}')
            # Dependencias de contexto: pagos usa lo que crea compras
            if a == '02_pagos.py' and '01_compras.py' not in archivos:
                archivos.append('01_compras.py')
            if a not in archivos:
                archivos.append(a)
    return archivos


def marcas():
    return {
        'stock_mov': int(val('SELECT IFNULL(MAX(stock_movimiento_id),0) FROM gestion__stock_movimientos')),
        'comprobante': int(val('SELECT IFNULL(MAX(comprobante_id),0) FROM gestion__comprobantes')),
        'asiento': int(val('SELECT IFNULL(MAX(cont_asiento_id),0) FROM gestion__cont_asientos')),
        'tes_mov': int(val('SELECT IFNULL(MAX(tesoreria_movimiento_id),0) FROM gestion__tesoreria_movimientos')),
        'saldos': {r['tesoreria_cuenta_id']: num(r['saldo_actual'])
                   for r in sql('SELECT tesoreria_cuenta_id, saldo_actual FROM gestion__tesoreria_cuentas')},
        'stock': {(r['boca_id'], r['producto_id']): num(r['cantidad'])
                  for r in sql('SELECT boca_id, producto_id, cantidad FROM gestion__stock WHERE empresa_id=2')},
    }


def verificar_consistencia(m):
    caso('Consistencia global de lo generado por la simulación')
    # Stock: saldo materializado = saldo inicial + movimientos del libro
    movs = sql(f"SELECT boca_id, producto_id, SUM(IF(tipo_movimiento='INGRESO', cantidad, -cantidad)) neto "
               f"FROM gestion__stock_movimientos WHERE stock_movimiento_id > {m['stock_mov']} GROUP BY boca_id, producto_id")
    mal = []
    for x in movs:
        actual = num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id=2 AND boca_id={x['boca_id']} AND producto_id={x['producto_id']}"))
        esperado = m['stock'].get((x['boca_id'], x['producto_id']), 0) + num(x['neto'])
        if abs(actual - esperado) > 0.0001:
            mal.append(f"boca {x['boca_id']} prod {x['producto_id']}: {actual} vs {esperado}")
    check(not mal, f'stock materializado = inicial + libro ({len(movs)} producto/boca)', '; '.join(mal))

    # Comprobantes: destino (facturas) saldo = total - imputado; origen (recibos, OP, NC) saldo = total - aplicado
    comps = sql(f"SELECT c.comprobante_id, c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id estado, "
                f"IFNULL((SELECT SUM(i.importe_imputado) FROM gestion__comprobantes_imputaciones i "
                f"WHERE i.comp_destino_id=c.comprobante_id AND i.tabla_estado_registro_id NOT IN (2,3,6,38)),0) imputado, "
                f"IFNULL((SELECT SUM(i.importe_imputado) FROM gestion__comprobantes_imputaciones i "
                f"WHERE i.comp_origen_id=c.comprobante_id AND i.tabla_estado_registro_id NOT IN (2,3,6,38)),0) aplicado "
                f"FROM gestion__comprobantes c WHERE c.comprobante_id > {m['comprobante']} AND c.tabla_estado_registro_id IN (5,39,40)")
    mal = [f"comp {c['comprobante_id']}: pendiente {c['importe_pendiente']} vs {num(c['importe_total']) - num(c['imputado']) - num(c['aplicado'])}"
           for c in comps if abs(num(c['importe_pendiente']) - (num(c['importe_total']) - num(c['imputado']) - num(c['aplicado']))) > 0.01]
    check(not mal, f'saldo de comprobantes = total - imputado/aplicado ({len(comps)} comprobantes)', '; '.join(mal))
    mal = []
    for c in comps:
        imputado, aplicado = num(c['imputado']), num(c['aplicado'])
        if aplicado > 0 and imputado == 0:
            continue   # recibos / OP: el estado derivado por saldo es solo de los destinos
        movido = imputado + aplicado
        esperado = 40 if abs(num(c['importe_pendiente'])) < 0.01 and movido > 0 else (39 if movido > 0 else 5)
        if int(c['estado']) != esperado and num(c['importe_total']) > 0:
            mal.append(f"comp {c['comprobante_id']}: estado {c['estado']}, esperado {esperado}")
    check(not mal, 'estado de comprobantes coherente con el saldo (5 / 39 / 40)', '; '.join(mal))

    # Asientos generados: balanceados y con al menos dos renglones
    asientos = sql(f"SELECT a.cont_asiento_id, ROUND(SUM(d.importe_local),2) suma, COUNT(d.cont_asiento_id) lineas "
                   f"FROM gestion__cont_asientos a LEFT JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id=a.cont_asiento_id "
                   f"WHERE a.cont_asiento_id > {m['asiento']} GROUP BY a.cont_asiento_id")
    mal = [f"asiento {a['cont_asiento_id']}: suma {a['suma']}, {a['lineas']} líneas" for a in asientos
           if abs(num(a['suma'])) > 0.01 or int(a['lineas']) < 2]
    check(not mal, f'asientos balanceados ({len(asientos)} asientos)', '; '.join(mal))
    rotos = val("SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id > 0 AND (f_asiento='0000-00-00' OR descripcion='0' OR IFNULL(descripcion,'')='')")
    check(rotos == '0', 'ningún asiento con comprobante sin fecha o descripción', f'{rotos} asientos')

    # Tesorería: saldo materializado = inicial + movimientos del libro
    movs = sql(f"SELECT tesoreria_cuenta_id, SUM(IF(tipo_movimiento LIKE 'INGRESO%', importe, -importe)) neto "
               f"FROM gestion__tesoreria_movimientos WHERE tesoreria_movimiento_id > {m['tes_mov']} GROUP BY tesoreria_cuenta_id")
    mal = []
    for x in movs:
        actual = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id={x['tesoreria_cuenta_id']}"))
        esperado = m['saldos'].get(x['tesoreria_cuenta_id'], 0) + num(x['neto'])
        if abs(actual - esperado) > 0.01:
            mal.append(f"cuenta {x['tesoreria_cuenta_id']}: saldo {actual} vs {esperado}")
    check(not mal, f'saldo de tesorería = inicial + libro ({len(movs)} cuentas)', '; '.join(mal))

    # Espejos en gestion__comprobantes
    desinc = val("SELECT COUNT(*) FROM gestion__comprobantes c JOIN gestion__ventas_remitos r ON r.venta_remito_id=c.registro_origen_id "
                 "WHERE c.tabla_origen_id=81 AND (c.tabla_estado_registro_id<>r.tabla_estado_registro_id OR c.comprobante_nro<>r.comprobante_nro)")
    check(desinc == '0', 'espejo de los remitos sincronizado', f'{desinc} remitos')
    signo = val("SELECT COUNT(*) FROM gestion__comprobantes c JOIN gestion__comprobantes_tipos ct USING(comprobante_tipo_id) "
                "WHERE c.tabla_origen_id IN (80,84) AND c.signo <> IF(ct.signo='-', -1, 1)")
    check(signo == '0', 'signo de facturas / notas igual al de su tipo', f'{signo} comprobantes')


def main():
    ap = argparse.ArgumentParser(description='Simulador de circuitos de gestion_multipyme')
    ap.add_argument('casos', nargs='*', help='grupos (' + ', '.join(GRUPOS) + ') o archivos de casos/')
    ap.add_argument('--scripts', nargs='*', default=[], help='scripts SQL a aplicar en la copia antes de simular')
    ap.add_argument('--sin-clonar', action='store_true', help='reutiliza la copia existente')
    ap.add_argument('--sin-consistencia', action='store_true', help='omite los chequeos de consistencia global')
    ap.add_argument('--listar', action='store_true', help='lista grupos y casos')
    ap.add_argument('--base-local', action='store_true',
                    help='corre sobre la base local real (no clona) para ver el resultado en pantalla; hacer backup antes')
    a = ap.parse_args()

    if a.listar:
        for g, l in GRUPOS.items():
            print(f'{g}: {", ".join(l)}')
        print('casos:', ', '.join(todos_los_casos()))
        return 0

    archivos = resolver(a.casos)
    if a.base_local:
        if a.scripts:
            raise SystemExit('--scripts no se usa con --base-local')
        print(f'ATENCIÓN: los casos escriben en la base local {DB}.')
    elif not a.sin_clonar:
        print(f'Clonando {DB_ORIGEN} -> {DB} ...')
        clonar_base(a.scripts)
    elif a.scripts:
        raise SystemExit('--scripts requiere clonar (sacá --sin-clonar)')

    m = marcas()
    for archivo in archivos:
        codigo = open(os.path.join(CASOS_DIR, archivo), encoding='utf-8').read()
        exec(compile(codigo, archivo, 'exec'), {'__name__': '__caso__'})
    if not a.sin_consistencia:
        verificar_consistencia(m)
    return 1 if resumen() else 0


if __name__ == '__main__':
    sys.exit(main())
