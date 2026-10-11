"""10 ventas a clientes de Río Negro con percepción de IIBB (docs/gestion/09_percepciones_iibb.md).

Pensado también para correr con --base-local y mirar el resultado en Ventas > Facturas y en la DDJJ (página 116).
Setup (en la copia, o en la base local con --base-local):
- habilita la config de percepción de Río Negro de la empresa 2 (la deja ACTIVA: se crea inactiva hasta tener los
  datos del contador; los datos de agente / régimen / tributo ARCA no se tocan);
- carga condición fiscal (RI o Monotributo) y condición IIBB a 8 clientes que ya tienen sucursal en Río Negro
  (no hace falta cambiar direcciones), solo si no la tienen vigente;
- deshabilita ARCA mientras corre (las facturas se numeran localmente, sin CAE) y lo restituye al final.

Operaciones: 8 facturas A (inscriptos local/CM con alta en RN 1 %, monotributista simplificado 0,05 %,
no inscripto 0 % registrado para la DDJJ), una segunda factura a un cliente y una NC A asociada que revierte.
"""
from simlib import *

F_SETUP = HOY[:8] + '01'
PERIODO = HOY[:7]

cfg = uno("SELECT cfg.empresa_impuesto_config_id id, cfg.cont_cuenta_id, cfg.tabla_estado_registro_id est FROM gestion__empresas_impuestos_config cfg "
          "JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id "
          "WHERE cfg.empresa_id = 2 AND cfg.impuesto_tipo_id = 6 AND j.jurisdiccion_codigo = 'DGR_RN' AND cfg.f_hasta IS NULL")
JUR_RN = int(val("SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = 'DGR_RN'"))
SEDE = {c: int(val(f"SELECT jurisdiccion_sede_id FROM gestion__iibb_jurisdicciones_sedes WHERE codigo_comarb = '{c}'")) for c in ('901', '916')}
REG = {r['regimen_iibb_codigo']: int(r['regimen_iibb_id']) for r in sql("SELECT regimen_iibb_id, regimen_iibb_codigo FROM gestion__regimenes_iibb")}
RI, MONO = 1, 3

# cliente: (sucursal en RN, condición fiscal, régimen IIBB o None, sede, jurisdicciones de alta CM, situación esperada, alícuota)
CLIENTES = {
    417:  (79,  RI,   'LOCAL',        '916', [],      'INSCRIPTO_JURISDICCION', 1.0),     # Cipolletti
    128:  (26,  RI,   'LOCAL',        '916', [],      'INSCRIPTO_JURISDICCION', 1.0),     # San Antonio Oeste
    132:  (27,  RI,   'CM',           '901', ['916'], 'INSCRIPTO_JURISDICCION', 1.0),     # CM sede Bs.As. con alta en RN
    478:  (97,  RI,   'LOCAL',        '916', [],      'INSCRIPTO_JURISDICCION', 1.0),     # Bariloche
    525:  (119, RI,   'CM',           '916', [],      'INSCRIPTO_JURISDICCION', 1.0),     # CM sede RN (Viedma)
    4398: (394, RI,   'LOCAL',        '916', [],      'INSCRIPTO_JURISDICCION', 1.0),
    425:  (81,  MONO, 'SIMPLIFICADO', '916', [],      'SIMPLIFICADO_JURISDICCION', 0.05),  # monotributista
    270:  (46,  RI,   None,           None,  [],      'NO_INSCRIPTO', 0.0),               # Viedma, sin condición IIBB
}

arca_orig = val("SELECT arca_habilitado FROM gestion__empresas_parametros WHERE empresa_id = 2 AND f_hasta IS NULL")


def estado_vf(fid):
    return int(val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id={fid}"))


def fila_vf(fid):
    return uno(f"SELECT vf.importe_neto, vf.importe_iva, vf.importe_otros_impuestos, vf.importe_total, vf.comprobante_id, "
               f"c.comprobante_nro FROM gestion__ventas_facturas vf JOIN gestion__comprobantes c ON c.comprobante_id = vf.comprobante_id "
               f"WHERE vf.venta_factura_id = {fid}")


def percepciones(comp_id):
    return sql(f"SELECT * FROM gestion__comprobantes_impuestos WHERE comprobante_id = {comp_id} AND impuesto_tipo_id = 6 AND tabla_estado_registro_id = 1")


def vf_alta(tipo, entidad, sucursal, lineas, **extra):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=entidad, entidad_sucursal_id=sucursal, f_emision=HOY, f_vto=HOY, moneda_id=1,
             tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0, observaciones='SIM ventas Río Negro',
             detalles=[dict(producto_id=p, cantidad=c, precio_unitario=pr, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)
                       for p, c, pr in lineas], **extra))
    fid = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
    return r, fid


def vf_confirmar(fid):
    r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma', empresa_idx=2, pagina_idx=56))
    if ok_resp(r) and estado_vf(fid) == 4:
        r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada', empresa_idx=2, pagina_idx=56))
    return r


def asiento(comp_id):
    return sql(f"SELECT d.cuenta_id, d.importe_local FROM gestion__cont_asientos a JOIN gestion__cont_asientos_detalles d "
               f"ON d.cont_asiento_id = a.cont_asiento_id WHERE a.comprobante_id = {comp_id}")


# ---------------------------------------------------------------------------------------------
caso('Caso 50 - Setup: config de Río Negro activa, ARCA deshabilitado, condiciones de 8 clientes de Río Negro')
check(cfg, 'existe la config de percepción de Río Negro (script 20261010_percepciones_iibb_03)')
sql(f"UPDATE gestion__empresas_impuestos_config SET tabla_estado_registro_id = 1 WHERE empresa_impuesto_config_id = {cfg['id']}")
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id = 2 AND f_hasta IS NULL")
for ent, (suc, cfis, reg, sede, altas, _, _) in CLIENTES.items():
    prov = val(f"SELECT l.provincia_id FROM gestion__entidades_sucursales es JOIN conf__localidades l ON l.localidad_id = es.localidad_id "
               f"WHERE es.sucursal_id = {suc} AND es.entidad_id = {ent}")
    check(prov == '24', f'cliente {ent}: sucursal {suc} en Río Negro', prov)
    if not val(f"SELECT 1 FROM gestion__entidades_condiciones_fiscales WHERE entidad_id = {ent} AND tabla_estado_registro_id = 1 "
               f"AND f_desde <= '{HOY}' AND (f_hasta IS NULL OR f_hasta >= '{HOY}')"):
        sql(f"INSERT INTO gestion__entidades_condiciones_fiscales (entidad_id, condicion_fiscal_id, f_desde, f_hasta, tabla_estado_registro_id) "
            f"VALUES ({ent}, {cfis}, '{F_SETUP}', NULL, 1)")
    if reg is None:
        continue
    cond = val(f"SELECT entidad_condicion_iibb_id FROM gestion__entidades_condiciones_iibb WHERE entidad_id = {ent} AND tabla_estado_registro_id = 1 "
               f"AND f_desde <= '{HOY}' AND (f_hasta IS NULL OR f_hasta >= '{HOY}') ORDER BY f_desde DESC LIMIT 1")
    if cond:
        # 417 ya era Local sin sede: se le asigna la sede de Río Negro
        sql(f"UPDATE gestion__entidades_condiciones_iibb SET regimen_iibb_id = {REG[reg]}, jurisdiccion_sede_id = {SEDE[sede]} "
            f"WHERE entidad_condicion_iibb_id = {cond} AND jurisdiccion_sede_id IS NULL")
    else:
        sql(f"INSERT INTO gestion__entidades_condiciones_iibb (entidad_id, regimen_iibb_id, numero_inscripcion, jurisdiccion_sede_id, f_desde, "
            f"tabla_estado_registro_id) VALUES ({ent}, {REG[reg]}, NULL, {SEDE[sede]}, '{F_SETUP}', 1)")
        cond = val(f"SELECT MAX(entidad_condicion_iibb_id) FROM gestion__entidades_condiciones_iibb WHERE entidad_id = {ent}")
    for a in altas:
        if not val(f"SELECT 1 FROM gestion__entidades_condiciones_iibb_jurisdicciones WHERE entidad_condicion_iibb_id = {cond} "
                   f"AND jurisdiccion_sede_id = {SEDE[a]} AND tabla_estado_registro_id = 1"):
            sql(f"INSERT INTO gestion__entidades_condiciones_iibb_jurisdicciones (entidad_condicion_iibb_id, jurisdiccion_sede_id, f_desde, "
                f"tabla_estado_registro_id) VALUES ({cond}, {SEDE[a]}, '{F_SETUP}', 1)")

# ---------------------------------------------------------------------------------------------
OPS = [  # (cliente, líneas (producto, cantidad, precio neto))
    (417,  [(533, 2, 46132.04), (1109, 4, 10321.61)]),
    (128,  [(3232, 1, 78857.24)]),
    (132,  [(1976, 3, 34747.26), (362, 2, 32938.40)]),
    (478,  [(3838, 5, 22440.00)]),
    (525,  [(1720, 6, 13479.72), (3642, 10, 6296.22)]),
    (4398, [(533, 1, 46132.04), (3232, 2, 78857.24)]),
    (425,  [(1109, 2, 10321.61)]),
    (270,  [(362, 1, 32938.40)]),
    (128,  [(1976, 2, 34747.26), (3838, 3, 22440.00)]),
]
FACT = []
for n, (ent, lineas) in enumerate(OPS, 1):
    suc, _, _, _, _, sit, alic = CLIENTES[ent]
    caso(f'Caso 5{n} - Factura A {n}: cliente {ent} ({sit}, {alic} %)')
    neto = round(sum(c * p for _, c, p in lineas), 2)
    perc = round(neto * alic / 100, 2)
    r, fid = vf_alta(4, ent, suc, lineas)
    check(ok_resp(r) and fid, 'alta en Borrador', err(r))
    if not fid:
        continue
    r = vf_confirmar(fid)
    check(ok_resp(r) and estado_vf(fid) == 5, 'confirmada -> 5', err(r))
    v = fila_vf(fid)
    ps = percepciones(v['comprobante_id'])
    check(len(ps) == 1 and ps[0]['situacion'] == sit and abs(num(ps[0]['importe']) - perc) < 0.01
          and abs(num(ps[0]['base_imponible']) - neto) < 0.01 and int(ps[0]['jurisdiccion_id']) == JUR_RN,
          f'percepción {perc:.2f} sobre neto {neto:.2f}', str(ps))
    check(abs(num(v['importe_otros_impuestos']) - perc) < 0.01 and abs(num(v['importe_total']) - (neto + num(v['importe_iva']) + perc)) < 0.01,
          'total = neto + IVA + percepción', str(v))
    det = asiento(v['comprobante_id'])
    check(abs(sum(num(d['importe_local']) for d in det)) < 0.01 and (perc == 0 or any(
          int(d['cuenta_id']) == int(cfg['cont_cuenta_id']) and abs(num(d['importe_local']) + perc) < 0.01 for d in det)),
          'asiento balanceado con la percepción al Haber', str(det))
    FACT.append((fid, ent, v['comprobante_id'], v['comprobante_nro'], neto, perc))

# ---------------------------------------------------------------------------------------------
caso('Caso 60 - NC A asociada a la factura 2 (cliente 128): revierte el 1 % de lo devuelto')
f2 = next((f for f in FACT if f[1] == 128), None)
if f2:
    r, nc = vf_alta(7, 128, CLIENTES[128][0], [(3232, 1, 30000.00)], comprobante_asociado_id=f2[2])
    check(ok_resp(r) and nc, 'alta NC A asociada', err(r))
    if nc:
        r = vf_confirmar(nc)
        v = fila_vf(nc)
        ps = percepciones(v['comprobante_id'])
        # se aplica sola contra la factura (recibo en $0), así que puede quedar en 39/40
        check(ok_resp(r) and estado_vf(nc) in (5, 39, 40) and len(ps) == 1 and abs(num(ps[0]['importe']) - 300) < 0.01,
              'NC confirmada con percepción 300 (1 % de 30.000)', err(r) + ' ' + str(ps))
        FACT.append((nc, 128, v['comprobante_id'], v['comprobante_nro'], -30000.0, -300.0))

# ---------------------------------------------------------------------------------------------
caso('Caso 61 - DDJJ del período: lista las 10 operaciones')
r = ajax('percepciones_iibb_ajax.php', dict(accion='listar', jurisdiccion_id=JUR_RN, periodo=PERIODO, empresa_idx=2), 'GET')
check(ok_resp(r), 'listar período', err(r))
esperado = round(sum(f[5] for f in FACT), 2)
if ok_resp(r):
    s = r['data']['resumen']
    print(f"  Resumen DDJJ {PERIODO}: {s}")
    check(num(s['percibido']) >= esperado - 0.01, f'percibido del período incluye {esperado:.2f}', str(s))

print('\n  Operaciones generadas (venta_factura_id, cliente, comprobante_id, nro, neto, percepción):')
for f in FACT:
    print('   ', f)

# Restituir ARCA como estaba (la config de Río Negro queda activa)
sql(f"UPDATE gestion__empresas_parametros SET arca_habilitado = {int(arca_orig or 0)} WHERE empresa_id = 2 AND f_hasta IS NULL")
