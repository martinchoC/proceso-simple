"""
Genera los archivos de "Mis Comprobantes Emitidos / Recibidos" de ARCA de 5 clientes externos del estudio
contable (3 Responsables Inscriptos y 2 Monotributistas), agosto a octubre de 2026, para probar la importación.

Salida: _local/tests/fixtures/estudio_arca/ (un directorio por cliente, un .xlsx y un .csv por mes y sentido),
más clientes.csv (padrón) y esperado.json (totales por cliente y mes para controlar lo importado).

Datos ficticios con semilla fija: correr de nuevo da los mismos archivos.
    python _local/tools/estudio_arca_fixtures/generar.py
"""
import csv
import json
import os
import random
from datetime import date, timedelta

import openpyxl
from openpyxl.styles import Font

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..'))
SALIDA = os.path.join(RAIZ, '_local', 'tests', 'fixtures', 'estudio_arca')
MESES = [(2026, 8), (2026, 9), (2026, 10)]
ALICUOTAS = [0, 2.5, 5, 10.5, 21, 27]

rnd = random.Random(20261104)

TIPOS = {
    1: 'Factura A', 2: 'Nota de Débito A', 3: 'Nota de Crédito A',
    6: 'Factura B', 7: 'Nota de Débito B', 8: 'Nota de Crédito B',
    11: 'Factura C', 12: 'Nota de Débito C', 13: 'Nota de Crédito C',
    19: 'Factura E',
}


def cuit(prefijo, base):
    """CUIT válido (prefijo + 8 dígitos + verificador); si el verificador da 10, prueba el siguiente."""
    while True:
        n = f'{prefijo}{base:08d}'
        pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]
        r = 11 - sum(int(d) * p for d, p in zip(n, pesos)) % 11
        dv = 0 if r == 11 else r
        if dv != 10:
            return n + str(dv)
        base += 1


def fmt_cuit(c):
    return f'{c[:2]}-{c[2:10]}-{c[10]}'


# ---------- Clientes del estudio ----------
CLIENTES = [
    dict(clave='01_ferreteria_del_valle', razon_social='Ferretería del Valle SRL', cuit=cuit('30', 71844213),
         condicion='RI', condicion_iva_id=1, cliente_tipo_id=3, iibb='LOCAL', jurisdicciones=['DGR_RN'],
         mes_cierre=6, empleador=True, empleados=6, domicilio='Viedma, Río Negro', pvs={'A': 2, 'B': 3},
         actividad='Venta minorista y mayorista de ferretería y materiales eléctricos'),
    dict(clave='02_transportes_patagonicos', razon_social='Transportes Patagónicos SA', cuit=cuit('30', 70992381),
         condicion='RI', condicion_iva_id=1, cliente_tipo_id=1, iibb='CONVENIO_MULTILATERAL',
         jurisdicciones=['DGR_RN', 'ARBA'], cm05={'DGR_RN': 0.6200, 'ARBA': 0.3800},
         mes_cierre=12, empleador=True, empleados=14, domicilio='San Antonio Oeste, Río Negro', pvs={'A': 1},
         actividad='Transporte de cargas por carretera'),
    dict(clave='03_agro_insumos_carmen', razon_social='Agro Insumos Carmen SAS', cuit=cuit('30', 71633057),
         condicion='RI', condicion_iva_id=1, cliente_tipo_id=1, iibb='LOCAL', jurisdicciones=['ARBA'],
         mes_cierre=3, empleador=True, empleados=3, domicilio='Carmen de Patagones, Buenos Aires', pvs={'A': 1, 'B': 4},
         actividad='Venta de semillas, agroquímicos y alimento balanceado'),
    dict(clave='04_lucia_fernandez', razon_social='Fernández, Lucía Belén', cuit=cuit('27', 31455820),
         condicion='MT', condicion_iva_id=3, cliente_tipo_id=2, iibb='LOCAL', jurisdicciones=['DGR_RN'],
         mes_cierre=12, empleador=False, empleados=0, domicilio='Viedma, Río Negro', pvs={'C': 1},
         categoria='D', actividad='Servicios de psicología (consultorio)'),
    dict(clave='05_martin_gomez', razon_social='Gómez, Martín Alejandro', cuit=cuit('20', 28761934),
         condicion='MT', condicion_iva_id=3, cliente_tipo_id=3, iibb='LOCAL', jurisdicciones=['ARBA'],
         mes_cierre=12, empleador=False, empleados=0, domicilio='Carmen de Patagones, Buenos Aires', pvs={'C': 2},
         categoria='H', actividad='Almacén y despensa ("Despensa Don Martín")'),
]
POR_CLAVE = {c['clave']: c for c in CLIENTES}

# ---------- Contrapartes ficticias ----------
def contraparte(nombre, prefijo, base, cond):
    return dict(nombre=nombre, cuit=cuit(prefijo, base), cond=cond)

CLI_RI = [contraparte(n, '30', b, 'RI') for n, b in [
    ('Constructora Los Álamos SRL', 71120456), ('Hotel Costanera Viedma SA', 70845512),
    ('Servicios Eléctricos Patagonia SRL', 71388104), ('Instalaciones Sur SA', 71004987),
    ('Frigorífico Valle Inferior SA', 70613370), ('Acopiadora del Sur SA', 71267719),
    ('Cooperativa Agrícola Stroeder Ltda', 70519842), ('Logística Comahue SRL', 71550023),
]]
CLI_MT = [contraparte(n, p, b, 'MT') for n, p, b in [
    ('Pereyra, Juan Carlos', '20', 25311470), ('Sosa, Mariela', '27', 30128455),
    ('Ibáñez, Ricardo', '20', 22780931), ('Quiroga, Ana Laura', '27', 34511209),
]]
OBRAS_SOCIALES = [contraparte(n, '30', b, 'EX') for n, b in [
    ('Obra Social del Personal Patagónico', 70288410), ('Mutual Médica Atlántica', 70455126),
]]
NOMBRES_CF = ['Martínez, Laura', 'Rodríguez, Pablo', 'Gutiérrez, Sofía', 'Benítez, Carlos', 'Romero, Julieta',
              'Acosta, Diego', 'Medina, Valeria', 'Herrera, Tomás', 'Domínguez, Camila', 'Castro, Federico',
              'Vega, Milagros', 'Ríos, Nicolás']

PROV = {
    'mayorista_ferr': contraparte('Mayorista Ferretero Patagonia SA', '30', 70711452, 'RI'),
    'electricos': contraparte('Distribuidora de Materiales Eléctricos del Sur SRL', '30', 71422618, 'RI'),
    'telefonia': contraparte('Telecomunicaciones Australes SA', '30', 70110934, 'RI'),
    'energia': contraparte('Electro Servicios del Sur SA', '30', 70399127, 'RI'),
    'fletero': contraparte('Paredes, Hugo Daniel', '20', 23455817, 'MT'),
    'combustible': contraparte('Combustibles del Comahue SA', '30', 70922045, 'RI'),
    'repuestos': contraparte('Repuestos Pesados Bahía SRL', '30', 71155309, 'RI'),
    'seguros': contraparte('Aseguradora del Atlántico Sur SA', '30', 70004418, 'RI'),
    'neumaticos': contraparte('Neumáticos Ruta 3 SA', '30', 71633920, 'RI'),
    'semillas': contraparte('Semillera Pampeana SA', '30', 70500213, 'RI'),
    'agroquimicos': contraparte('Agroquímicos del Oeste SA', '30', 70788014, 'RI'),
    'alquiler_consult': contraparte('Mansilla, Roberto (alquiler)', '20', 14522873, 'MT'),
    'internet': contraparte('Telecomunicaciones Australes SA', '30', 70110934, 'RI'),
    'insumos_salud': contraparte('Librería y Papelería Centro SRL', '30', 71299347, 'RI'),
    'mayorista_alim': contraparte('Mayorista de Alimentos Patagones SA', '30', 70866251, 'RI'),
    'bebidas': contraparte('Distribuidora de Bebidas Atlántica SRL', '30', 71077582, 'RI'),
    'panaderia': contraparte('Panificadora La Espiga (Ruiz, Elsa)', '27', 18227961, 'MT'),
    'lacteos': contraparte('Lácteos del Valle SA', '30', 70340871, 'RI'),
}

# ---------- Comprobantes ----------
emitidos = {c['clave']: [] for c in CLIENTES}
recibidos = {c['clave']: [] for c in CLIENTES}
numeradores = {}          # (cuit emisor, pv, tipo) -> último número
casos_borde = {c['clave']: [] for c in CLIENTES}
cruces = []


def proximo(emisor_cuit, pv, tipo):
    clave = (emisor_cuit, pv, tipo)
    if clave not in numeradores:
        numeradores[clave] = rnd.randint(120, 2400)
    numeradores[clave] += 1
    return numeradores[clave]


def cae():
    return str(rnd.randint(76_000_000_000_000, 76_999_999_999_999))


def dia(anio, mes, desde=1, hasta=None):
    ultimo = (date(anio + (mes == 12), mes % 12 + 1, 1) - timedelta(days=1)).day
    return date(anio, mes, rnd.randint(desde, min(hasta or ultimo, ultimo)))


def importes(netos=None, no_gravado=0.0, exento=0.0, otros=0.0, total_c=None):
    """netos: {alícuota: neto}. total_c: comprobante C (sin IVA discriminado)."""
    if total_c is not None:
        return dict(netos={}, ivas={}, neto_total=0.0, no_gravado=0.0, exento=0.0, otros=0.0, iva_total=0.0,
                    total=round(total_c, 2))
    netos = {a: round(v, 2) for a, v in (netos or {}).items() if v}
    ivas = {a: round(v * a / 100, 2) for a, v in netos.items()}
    neto_total = round(sum(netos.values()), 2)
    iva_total = round(sum(ivas.values()), 2)
    total = round(neto_total + iva_total + no_gravado + exento + otros, 2)
    return dict(netos=netos, ivas=ivas, neto_total=neto_total, no_gravado=round(no_gravado, 2),
                exento=round(exento, 2), otros=round(otros, 2), iva_total=iva_total, total=total)


def comprobante(fecha, tipo, pv, emisor, receptor, imp, moneda='$', tc=1.0, nro_desde=None, nro_hasta=None):
    nro = nro_desde or proximo(emisor['cuit'], pv, tipo)
    return dict(fecha=fecha, tipo=tipo, pv=pv, nro_desde=nro, nro_hasta=nro_hasta or nro, cae=cae(),
                emisor=emisor, receptor=receptor, moneda=moneda, tc=tc, **imp)


def yo(c):
    return dict(nombre=c['razon_social'], cuit=c['cuit'], cond=c['condicion'])


def receptor_cf(dni=True):
    if dni:
        return dict(nombre=rnd.choice(NOMBRES_CF), doc_tipo='DNI', doc=str(rnd.randint(18_000_000, 45_999_999)), cond='CF')
    return dict(nombre='Consumidor Final', doc_tipo='Sin identificar', doc='0', cond='CF')


def emitir(clave, comp, nota=None):
    emitidos[clave].append(comp)
    if nota:
        casos_borde[clave].append(f"Emitido {TIPOS[comp['tipo']]} {comp['pv']:05d}-{comp['nro_desde']:08d}: {nota}")


def recibir(clave, comp, nota=None):
    recibidos[clave].append(comp)
    if nota:
        casos_borde[clave].append(f"Recibido {TIPOS[comp['tipo']]} {comp['pv']:05d}-{comp['nro_desde']:08d} de {comp['emisor']['nombre']}: {nota}")


def cruzar(clave_emisor, clave_receptor, comp):
    """Operación entre dos clientes del estudio: el mismo comprobante en Emitidos de uno y Recibidos del otro."""
    emitir(clave_emisor, comp)
    recibir(clave_receptor, comp)
    cruces.append(dict(emisor=clave_emisor, receptor=clave_receptor, tipo=comp['tipo'], pv=comp['pv'],
                       numero=comp['nro_desde'], cae=comp['cae'], total=comp['total']))


def monto(desde, hasta):
    return round(rnd.uniform(desde, hasta), 2)


ferr, transp, agro, lucia, martin = (POR_CLAVE[k] for k in POR_CLAVE)

for anio, mes in MESES:
    # ===== 1. Ferretería del Valle SRL (RI) =====
    k = ferr['clave']
    for _ in range(rnd.randint(7, 10)):
        n21 = monto(80_000, 1_400_000)
        n105 = monto(20_000, 250_000) if rnd.random() < 0.3 else 0
        emitir(k, comprobante(dia(anio, mes), 1, ferr['pvs']['A'], yo(ferr), rnd.choice(CLI_RI), importes({21: n21, 10.5: n105})))
    for _ in range(rnd.randint(14, 20)):
        rec = rnd.choice(CLI_MT) if rnd.random() < 0.25 else receptor_cf(dni=rnd.random() < 0.6)
        emitir(k, comprobante(dia(anio, mes), 6, ferr['pvs']['B'], yo(ferr), rec, importes({21: monto(8_000, 180_000)})))
    if rnd.random() < 0.9:
        orig = rnd.choice([c for c in emitidos[k] if c['tipo'] == 1])
        emitir(k, comprobante(dia(anio, mes, 15), 3, orig['pv'], yo(ferr), orig['receptor'],
                              importes({21: round(orig['netos'].get(21, 0) * 0.15, 2)})),
               f"NC A por devolución parcial de la {orig['pv']:05d}-{orig['nro_desde']:08d}")
    # Venta a Transportes Patagónicos y a Lucía Fernández (clientes del estudio)
    cruzar(k, transp['clave'], comprobante(dia(anio, mes), 1, ferr['pvs']['A'], yo(ferr), yo(transp), importes({21: monto(150_000, 420_000)})))
    if mes in (8, 10):
        cruzar(k, lucia['clave'], comprobante(dia(anio, mes), 6, ferr['pvs']['B'], yo(ferr), yo(lucia), importes({21: monto(25_000, 60_000)})))
    if mes == 9:
        # Rango de tiques a consumidor final agrupados (Número Desde ≠ Hasta): el importador de ventas no los toma
        d = proximo(ferr['cuit'], ferr['pvs']['B'], 6)
        numeradores[(ferr['cuit'], ferr['pvs']['B'], 6)] += 11
        emitir(k, comprobante(date(anio, mes, 30), 6, ferr['pvs']['B'], yo(ferr), receptor_cf(dni=False),
                              importes({21: 412_396.69}), nro_desde=d, nro_hasta=d + 11),
               'rango de 12 comprobantes a consumidor final en una sola fila (Número Desde ≠ Número Hasta)')

    for _ in range(rnd.randint(4, 6)):
        n = monto(400_000, 3_200_000)
        recibir(k, comprobante(dia(anio, mes), 1, 4, PROV['mayorista_ferr'], yo(ferr),
                               importes({21: n, 10.5: monto(30_000, 200_000)}, otros=round(n * 0.03, 2))),
                'percepción IIBB Río Negro 3 % en Otros Tributos' if _ == 0 and mes == 8 else None)
    for _ in range(rnd.randint(2, 3)):
        recibir(k, comprobante(dia(anio, mes), 1, 12, PROV['electricos'], yo(ferr), importes({21: monto(250_000, 1_100_000)})))
    recibir(k, comprobante(dia(anio, mes, 5, 12), 1, 9, PROV['telefonia'], yo(ferr),
                           importes({27: monto(48_000, 62_000)}, otros=monto(1_500, 2_500))), 'IVA 27 % (telefonía)' if mes == 8 else None)
    recibir(k, comprobante(dia(anio, mes, 8, 18), 1, 31, PROV['energia'], yo(ferr),
                           importes({27: monto(130_000, 190_000)}, no_gravado=monto(9_000, 14_000), otros=monto(6_000, 11_000))),
            'energía: IVA 27 %, no gravado (tasas) y otros tributos' if mes == 8 else None)
    recibir(k, comprobante(dia(anio, mes), 11, 2, PROV['fletero'], yo(ferr), importes(total_c=monto(60_000, 140_000))),
            'Factura C de un monotributista: sin crédito fiscal' if mes == 8 else None)
    if mes == 9:
        recibir(k, comprobante(dia(anio, mes, 20), 3, 4, PROV['mayorista_ferr'], yo(ferr), importes({21: 185_000.00})),
                'NC A del proveedor por bonificación comercial')

    # ===== 2. Transportes Patagónicos SA (RI, Convenio Multilateral) =====
    k = transp['clave']
    for _ in range(rnd.randint(8, 12)):
        emitir(k, comprobante(dia(anio, mes), 1, 1, yo(transp), rnd.choice(CLI_RI), importes({21: monto(350_000, 2_800_000)})))
    cruzar(k, agro['clave'], comprobante(dia(anio, mes), 1, 1, yo(transp), yo(agro), importes({21: monto(420_000, 980_000)})))
    if mes == 9:
        orig = next(c for c in emitidos[k] if c['tipo'] == 1)
        emitir(k, comprobante(date(anio, mes, 25), 2, 1, yo(transp), orig['receptor'], importes({21: 96_500.00})),
               'ND A por estadía del camión')
    if mes == 10:
        cliente_ext = dict(nombre='Transportes Andinos Ltda. (Chile)', doc_tipo='CUIT', doc='55000002206', cond='EXT')
        emitir(k, comprobante(date(anio, mes, 14), 19, 3, yo(transp), cliente_ext,
                              importes(exento=4_800.00), moneda='DOL', tc=1_412.50),
               'Factura E de exportación de servicios en dólares (los importadores solo toman pesos)')
    for _ in range(rnd.randint(6, 9)):
        litros = monto(800_000, 2_600_000)
        recibir(k, comprobante(dia(anio, mes), 1, 7, PROV['combustible'], yo(transp),
                               importes({21: litros}, no_gravado=round(litros * 0.18, 2), otros=round(litros * 0.02, 2))),
                'combustible: no gravado (impuesto a los combustibles) y percepción IIBB' if mes == 8 and _ == 0 else None)
    for _ in range(rnd.randint(2, 4)):
        recibir(k, comprobante(dia(anio, mes), 1, 3, PROV['repuestos'], yo(transp), importes({21: monto(180_000, 900_000)})))
    recibir(k, comprobante(dia(anio, mes, 1, 5), 1, 2, PROV['seguros'], yo(transp),
                           importes({21: monto(1_100_000, 1_250_000)}, exento=monto(40_000, 60_000), otros=monto(30_000, 45_000))),
            'seguro de flota: exento y otros tributos' if mes == 8 else None)
    if mes == 10:
        recibir(k, comprobante(date(anio, mes, 6), 1, 5, PROV['neumaticos'], yo(transp), importes({21: 3_950_000.00})),
                'compra grande de neumáticos (bien de uso o gasto: lo decide el contador)')

    # ===== 3. Agro Insumos Carmen SAS (RI) =====
    k = agro['clave']
    for _ in range(rnd.randint(9, 13)):
        emitir(k, comprobante(dia(anio, mes), 1, agro['pvs']['A'], yo(agro), rnd.choice(CLI_RI),
                              importes({10.5: monto(300_000, 2_500_000), 21: monto(0, 400_000) if rnd.random() < 0.5 else 0})))
    for _ in range(rnd.randint(6, 9)):
        rec = rnd.choice(CLI_MT) if rnd.random() < 0.5 else receptor_cf(dni=True)
        emitir(k, comprobante(dia(anio, mes), 6, agro['pvs']['B'], yo(agro), rec, importes({10.5: monto(30_000, 260_000)})))
    if mes == 10:
        rec = rnd.choice(CLI_RI)
        n = 1_850_000.00
        emitir(k, comprobante(date(anio, mes, 22), 1, agro['pvs']['A'], yo(agro), rec, importes({10.5: n}, otros=round(n * 0.025, 2))),
               'venta con percepción IIBB ARBA practicada en Otros Tributos (el importador de ventas no toma Otros Tributos)')
        emitir(k, comprobante(date(anio, mes, 23), 1, agro['pvs']['A'], yo(agro), rnd.choice(CLI_RI), importes(exento=640_000.00)),
               'venta exenta (semilla fiscalizada): solo Op. Exentas')
    for _ in range(rnd.randint(3, 5)):
        n = monto(900_000, 4_500_000)
        recibir(k, comprobante(dia(anio, mes), 1, 15, PROV['semillas'], yo(agro),
                               importes({10.5: n}, otros=round(n * 0.03, 2) + round(n * 0.015, 2))),
                'percepciones de IVA e IIBB sumadas en Otros Tributos (ARCA no las separa)' if mes == 8 and _ == 0 else None)
    for _ in range(rnd.randint(2, 3)):
        recibir(k, comprobante(dia(anio, mes), 1, 6, PROV['agroquimicos'], yo(agro), importes({21: monto(500_000, 2_100_000)})))
    recibir(k, comprobante(dia(anio, mes, 8, 18), 1, 31, PROV['energia'], yo(agro), importes({27: monto(60_000, 90_000)}, otros=monto(3_000, 5_000))))

    # ===== 4. Lucía Fernández (Monotributo D, psicóloga) =====
    k = lucia['clave']
    for _ in range(rnd.randint(8, 12)):
        emitir(k, comprobante(dia(anio, mes), 11, 1, yo(lucia), receptor_cf(dni=True), importes(total_c=rnd.choice([28_000, 32_000, 35_000, 40_000]))))
    for os_ in OBRAS_SOCIALES:
        emitir(k, comprobante(dia(anio, mes, 25), 11, 1, yo(lucia), os_, importes(total_c=monto(180_000, 320_000))),
               'Factura C a obra social (exenta en IVA, con CUIT)' if mes == 8 else None)
    if mes == 9:
        orig = next(c for c in emitidos[k] if c['tipo'] == 11 and c['receptor']['cond'] == 'EX')
        emitir(k, comprobante(date(anio, mes, 28), 13, 1, yo(lucia), orig['receptor'], importes(total_c=36_000.00)),
               'NC C a la obra social por sesiones no autorizadas')
    recibir(k, comprobante(date(anio, mes, 1), 11, 1, PROV['alquiler_consult'], yo(lucia), importes(total_c=420_000.00)),
            'alquiler del consultorio a un monotributista (Factura C)' if mes == 8 else None)
    recibir(k, comprobante(dia(anio, mes, 3, 10), 6, 9, PROV['internet'], yo(lucia), importes({27: 21_850.00})),
            'Factura B de un RI: ARCA informa neto e IVA aunque el comprador no los computa' if mes == 8 else None)
    recibir(k, comprobante(dia(anio, mes), 6, 3, PROV['insumos_salud'], yo(lucia), importes({21: monto(9_000, 26_000)})))

    # ===== 5. Martín Gómez (Monotributo H, almacén) =====
    k = martin['clave']
    for _ in range(rnd.randint(22, 30)):
        emitir(k, comprobante(dia(anio, mes), 11, 2, yo(martin), receptor_cf(dni=rnd.random() < 0.3),
                              importes(total_c=monto(4_000, 95_000))))
    for _ in range(rnd.randint(4, 6)):
        recibir(k, comprobante(dia(anio, mes), 6, 8, PROV['mayorista_alim'], yo(martin), importes({21: monto(200_000, 900_000), 10.5: monto(80_000, 300_000)})))
    for _ in range(rnd.randint(2, 4)):
        recibir(k, comprobante(dia(anio, mes), 6, 2, PROV['bebidas'], yo(martin), importes({21: monto(150_000, 500_000)})))
    for _ in range(rnd.randint(3, 5)):
        recibir(k, comprobante(dia(anio, mes), 11, 1, PROV['panaderia'], yo(martin), importes(total_c=monto(40_000, 110_000))))
    if mes == 10:
        recibir(k, comprobante(date(anio, mes, 9), 1, 4, PROV['lacteos'], yo(martin), importes({21: 310_000.00, 10.5: 95_000.00})),
                'Factura A a un monotributista (RG 5003): el monotributista igual no computa el IVA')
    recibir(k, comprobante(dia(anio, mes, 8, 18), 6, 31, PROV['energia'], yo(martin), importes({27: monto(90_000, 140_000)}, otros=monto(4_000, 7_000))))


# ---------- Escritura ----------
def cols_importes():
    cols = []
    for a in ALICUOTAS:
        txt = f'{a:g}'.replace('.', ',')
        # Mismo orden que el archivo real: "IVA x%" antes de "Neto Grav. IVA x%" (salvo el 0 %, que no tiene IVA)
        if a:
            cols.append(('iva', a, f'IVA {txt}%'))
        cols.append(('neto', a, f'Neto Grav. IVA {txt}%'))
    return cols + [('fijo', 'neto_total', 'Neto Gravado Total'), ('fijo', 'no_gravado', 'Neto No Gravado'),
                   ('fijo', 'exento', 'Op. Exentas'), ('fijo', 'otros', 'Otros Tributos'),
                   ('fijo', 'iva_total', 'Total IVA'), ('fijo', 'total', 'Imp. Total')]


def doc_de(p):
    if 'doc_tipo' in p:
        return p['doc_tipo'], p['doc']
    return 'CUIT', p['cuit']


def encabezados(sentido):
    base = ['Fecha', 'Tipo', 'Punto de Venta', 'Número Desde', 'Número Hasta', 'Cód. Autorización']
    if sentido == 'Emitidos':
        base += ['Tipo Doc. Receptor', 'Nro. Doc. Receptor', 'Denominación Receptor']
    else:
        base += ['Tipo Doc. Emisor', 'Nro. Doc. Emisor', 'Denominación Emisor', 'Tipo Doc. Receptor', 'Nro. Doc. Receptor']
    return base + ['Tipo Cambio', 'Moneda'] + [c[2] for c in cols_importes()]


def fila(sentido, c):
    contra = c['receptor'] if sentido == 'Emitidos' else c['emisor']
    dt, dn = doc_de(contra)
    # Como el .xlsx real: fecha en texto, CAE y documento numéricos, alícuotas sin uso vacías
    f = [c['fecha'].strftime('%d/%m/%Y'), f"{c['tipo']} - {TIPOS[c['tipo']]}", c['pv'], c['nro_desde'], c['nro_hasta'],
         int(c['cae']), dt, int(dn), contra['nombre']]
    if sentido == 'Recibidos':
        rt, rn = doc_de(c['receptor'])
        f += [rt, int(rn)]
    f += [c['tc'], c['moneda']]
    for clase, clave, _ in cols_importes():
        if clase == 'neto':
            f.append(c['netos'].get(clave))
        elif clase == 'iva':
            f.append(c['ivas'].get(clave))
        else:
            f.append(c[clave])
    return f


def escribir(cliente, sentido, anio, mes, comps):
    carpeta = os.path.join(SALIDA, cliente['clave'])
    os.makedirs(carpeta, exist_ok=True)
    nombre = f'Mis Comprobantes {sentido} - CUIT {cliente["cuit"]} - {anio}-{mes:02d}'
    comps = sorted(comps, key=lambda c: (c['fecha'], c['tipo'], c['pv'], c['nro_desde']))
    enc = encabezados(sentido)

    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = 'Comprobantes'
    ws.append([f'Mis Comprobantes {sentido} - CUIT {cliente["cuit"]}'])
    ws['A1'].font = Font(bold=True)
    ws.append(enc)
    for c in comps:
        ws.append(fila(sentido, c))
    for col in ws.iter_cols(min_row=2, max_row=2):
        ws.column_dimensions[col[0].column_letter].width = max(12, len(str(col[0].value)) + 2)
    wb.save(os.path.join(carpeta, nombre + '.xlsx'))

    # CSV como el de ARCA: ; como separador y coma decimal
    with open(os.path.join(carpeta, nombre + '.csv'), 'w', newline='', encoding='utf-8-sig') as fh:
        w = csv.writer(fh, delimiter=';')
        w.writerow(enc)
        for c in comps:
            w.writerow(['' if v is None else (f'{v:.2f}'.replace('.', ',') if isinstance(v, float) else v)
                        for v in fila(sentido, c)])
    return len(comps)


def signo(c):
    return -1 if c['tipo'] in (3, 8, 13) else 1


esperado = {'generado': date.today().isoformat(), 'periodos': [f'{a}-{m:02d}' for a, m in MESES], 'clientes': [], 'cruces': cruces}
os.makedirs(SALIDA, exist_ok=True)
for cl in CLIENTES:
    k = cl['clave']
    resumen = dict(clave=k, razon_social=cl['razon_social'], cuit=cl['cuit'], condicion=cl['condicion'], meses={},
                   casos_borde=casos_borde[k])
    for anio, mes in MESES:
        em = [c for c in emitidos[k] if c['fecha'].year == anio and c['fecha'].month == mes]
        re_ = [c for c in recibidos[k] if c['fecha'].year == anio and c['fecha'].month == mes]
        escribir(cl, 'Emitidos', anio, mes, em)
        escribir(cl, 'Recibidos', anio, mes, re_)
        pesos = [c for c in em if c['moneda'] == '$']
        # Crédito fiscal: solo A (y M) recibidas por un RI; las B / C no discriminan IVA para el comprador
        computables = [c for c in re_ if cl['condicion'] == 'RI' and c['tipo'] in (1, 2, 3)]
        resumen['meses'][f'{anio}-{mes:02d}'] = dict(
            emitidos=len(em), recibidos=len(re_),
            ventas_total_pesos=round(sum(signo(c) * c['total'] for c in pesos), 2),
            ventas_neto_gravado=round(sum(signo(c) * c['neto_total'] for c in pesos), 2),
            debito_fiscal=round(sum(signo(c) * c['iva_total'] for c in pesos), 2) if cl['condicion'] == 'RI' else 0.0,
            compras_total=round(sum(signo(c) * c['total'] for c in re_), 2),
            credito_fiscal=round(sum(signo(c) * c['iva_total'] for c in computables), 2),
            otros_tributos_sufridos=round(sum(signo(c) * c['otros'] for c in computables), 2),
            ventas_moneda_extranjera=[dict(numero=f"{c['pv']:05d}-{c['nro_desde']:08d}", moneda=c['moneda'], importe=c['total'], tipo_cambio=c['tc'])
                                      for c in em if c['moneda'] != '$'],
        )
    esperado['clientes'].append(resumen)

with open(os.path.join(SALIDA, 'esperado.json'), 'w', encoding='utf-8') as fh:
    json.dump(esperado, fh, ensure_ascii=False, indent=2)

with open(os.path.join(SALIDA, 'clientes.csv'), 'w', newline='', encoding='utf-8-sig') as fh:
    w = csv.writer(fh, delimiter=';')
    w.writerow(['carpeta', 'razon_social', 'cuit', 'condicion_iva', 'categoria_monotributo', 'ingresos_brutos',
                'jurisdicciones', 'mes_cierre', 'empleados', 'domicilio', 'actividad'])
    for cl in CLIENTES:
        w.writerow([cl['clave'], cl['razon_social'], fmt_cuit(cl['cuit']), cl['condicion'], cl.get('categoria', ''),
                    cl['iibb'], ', '.join(cl['jurisdicciones']), cl['mes_cierre'], cl['empleados'], cl['domicilio'], cl['actividad']])

for cl in esperado['clientes']:
    print(cl['clave'], cl['cuit'], {m: (v['emitidos'], v['recibidos']) for m, v in cl['meses'].items()})
print('cruces:', len(cruces))
