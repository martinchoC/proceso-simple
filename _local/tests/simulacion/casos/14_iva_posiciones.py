"""IVA - Posición Mensual (página 117 / 617): agregados del mes, liquidación (saldo a favor / a pagar con I_LIQ),
arrastre del saldo a favor, anulación y bloqueos.

Período A = mes anterior (compra con percepción de IVA -> saldo a favor), período B = mes actual (empresa 2: venta
Factura A -> a pagar). Aplica en la copia los scripts del 2026-10-11 (idempotentes) y deshabilita ARCA en la copia.
"""
import datetime as _dt
from simlib import *

for _s in ('20261011_percepciones_recibidas_01_subgrupo_fc.sql', '20261011_productos_codigo_por_empresa_01.sql',
           '20261011_debito_en_cuenta_01_estructura.sql', '20261011_debito_en_cuenta_02_resumen_bancario.sql',
           '20261011_facturas_proveedores_anular_01.sql', '20261011_iva_posiciones_01.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))
# Solo en la copia: sin ARCA (la venta se numera al confirmar) y sin liquidaciones de IVA previas
sql("UPDATE gestion__empresas_parametros SET arca_habilitado = 0 WHERE empresa_id IN (2, 6)")
sql("UPDATE gestion__iva_posiciones SET tabla_estado_registro_id = 38 WHERE empresa_id IN (2, 6)")

_hoy = _dt.date.fromisoformat(HOY)
PER_B = HOY[:7]
_a = (_hoy.replace(day=1) - _dt.timedelta(days=1))
PER_A = _a.strftime('%Y-%m')
FECHA_A = _a.replace(day=15).isoformat()


def entorno(modulo, empresa, pagina, pagina_fp, prov, sucursal, deposito, pv):
    e = dict(modulo=modulo, empresa=empresa, pagina=pagina, pagina_fp=pagina_fp, prov=prov, sucursal=sucursal, deposito=deposito, pv=pv)
    q = lambda s: val(s.format(e=empresa))
    e['moneda'] = int(q("SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {e} AND codigo = 'ARS' LIMIT 1"))
    e['fa'] = int(q("SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e} AND codigo = 'C_FA'"))
    e['iva21'] = int(q("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e} AND es_gravado = 1 AND porcentaje = 21"))
    e['prod'] = int(q("SELECT producto_id FROM gestion__productos WHERE empresa_id = {e} AND producto_codigo = 'BCO_COMISION'"))
    e['perc_iva'] = int(q("SELECT empresa_impuesto_config_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {e} "
                          "AND impuesto_tipo_id = 2 AND tabla_estado_registro_id = 1 LIMIT 1"))
    e['cta'] = {k: int(q(f"SELECT cont_cuenta_id FROM gestion__empresas_cuentas_conceptos WHERE empresa_id = {{e}} AND concepto = '{k}'") or 0)
                for k in ('IVA_SALDO_FAVOR', 'IVA_A_PAGAR', 'IVA_DEBITO')}
    e['nro'] = proximo_nro_proveedor(pv, 980000) - 1
    return e


f6 = uno("SELECT entidad_id, sucursal_id, deposito_id FROM gestion__facturas_proveedores WHERE empresa_id = 6 AND tabla_estado_registro_id = 5 LIMIT 1")
ENTORNOS = [entorno('gestion', 2, 117, 57, 5765, 1, 1, 18)]
if f6:
    ENTORNOS.append(entorno('gestion_normal', 6, 617, 557, int(f6['entidad_id']), int(f6['sucursal_id']), int(f6['deposito_id']), 19))


def iva(e, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    return ajax('iva_posiciones_ajax.php', p, method, modulo=e['modulo'], empresa_id=e['empresa'])


def detalle(e, periodo):
    r = iva(e, dict(accion='detalle', periodo=periodo), 'GET')
    return r['data'] if ok_resp(r) else None


def compra_a(e, neto, perc_pct):
    """Factura A de proveedor en el período A con percepción de IVA, confirmada."""
    e['nro'] += 1
    dets = [linea(e['prod'], 1, neto, iva_id=e['iva21'])]
    perc = round(neto * perc_pct / 100, 2)
    t = totales(dets)
    t['total'] = round(t['total'] + perc, 2)
    r = ajax('facturas_proveedores_ajax.php', dict(accion='agregar', empresa_idx=e['empresa'], pagina_idx=e['pagina_fp'], comprobante_tipo_id=e['fa'],
             comprobante_pv=e['pv'], comprobante_nro=e['nro'], sucursal_id=e['sucursal'], deposito_id=e['deposito'], entidad_id=e['prov'],
             f_emision=FECHA_A, f_contabilidad=FECHA_A, f_vencimiento=FECHA_A, moneda_id=e['moneda'], tipo_cambio=1, condicion_pago_id='',
             orden_compra_id='', descuento_general_pct=0, detalles=dets,
             impuestos_adicionales=[dict(empresa_impuesto_config_id=e['perc_iva'], tipo_origen='manual', base_imponible=neto, alicuota=perc_pct, importe=perc)],
             **t), modulo=e['modulo'], empresa_id=e['empresa'])
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if fid:
        for acc in ('aprobar', 'confirmar'):
            r = ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js=acc,
                     empresa_idx=e['empresa'], pagina_idx=e['pagina_fp']), modulo=e['modulo'], empresa_id=e['empresa'])
    return fid, r


def asiento_balance(asiento_id):
    return num(val(f"SELECT IFNULL(SUM(importe_local),0) FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {asiento_id}"))


def asiento_cuenta(asiento_id, cuenta):
    return num(val(f"SELECT IFNULL(SUM(importe_local),0) FROM gestion__cont_asientos_detalles WHERE cont_asiento_id = {asiento_id} AND cuenta_id = {cuenta}"))


def posicion(e, periodo):
    return uno(f"SELECT * FROM gestion__iva_posiciones WHERE empresa_id = {e['empresa']} AND periodo = '{periodo}' AND tabla_estado_registro_id = 5")


for E in ENTORNOS:
    tag = f"{E['modulo']} (empresa {E['empresa']})"

    # -----------------------------------------------------------------------------------------
    caso(f'Caso I1 - {tag}: el mes suma la compra con percepción de IVA y queda con saldo a favor')
    r = iva(E, dict(accion='maestros'), 'GET')
    check(ok_resp(r) and r['data']['puede_liquidar'] and r['data']['puede_anular'], 'maestros: el usuario puede liquidar y anular', err(r))
    check(E['cta']['IVA_SALDO_FAVOR'] > 0, 'la empresa tiene el concepto IVA_SALDO_FAVOR (script)')
    d0 = detalle(E, PER_A)
    fid, r = compra_a(E, 100000, 3)
    check(fid and ok_resp(r), f'factura A de $100.000 + IVA + percepción 3 % en {PER_A}, confirmada', err(r))
    d1 = detalle(E, PER_A)
    check(d0 and d1 and abs(d1['calculo']['credito_fiscal'] - d0['calculo']['credito_fiscal'] - 21000) < 0.01
          and abs(d1['calculo']['percepciones_iva'] - d0['calculo']['percepciones_iva'] - 3000) < 0.01,
          'crédito fiscal +21.000 y percepciones de IVA +3.000', str(d1 and d1['calculo']))
    r = iva(E, dict(accion='resumen', hasta=PER_B, meses=3), 'GET')
    fila_a = [m for m in r['data'] if m['periodo'] == PER_A] if ok_resp(r) else []
    check(fila_a and abs(fila_a[0]['credito_fiscal'] - d1['calculo']['credito_fiscal']) < 0.01 and fila_a[0]['posicion'] is None,
          'el resumen de 12 meses muestra el mes A sin liquidar', str(fila_a))
    r = iva(E, dict(accion='comprobantes', periodo=PER_A), 'GET')
    check(ok_resp(r) and any(x['libro'] == 'COMPRA' and abs(num(x['iva']) - 21000) < 0.01 for x in r['data']), 'la compra figura en el libro del período')

    # -----------------------------------------------------------------------------------------
    caso(f'Caso I2 - {tag}: liquidar A (empresa 2: saldo a favor, asiento sin comprobante)')
    calc = d1['calculo']
    r = iva(E, dict(accion='liquidar', periodo=PER_A, sucursal_id=E['sucursal'], entidad_id=E['prov']))
    pa = posicion(E, PER_A)
    check(ok_resp(r) and pa, 'liquidación de A registrada', err(r))
    if pa:
        check(abs(num(pa['saldo_a_favor']) - calc['saldo_a_favor']) < 0.01 and abs(num(pa['saldo_a_pagar']) - calc['saldo_a_pagar']) < 0.01,
              f"saldos iguales al cálculo (a favor {calc['saldo_a_favor']}, a pagar {calc['saldo_a_pagar']})", str(pa))
        if calc['saldo_a_pagar'] == 0:
            check(pa['comprobante_id'] is None, 'saldo a favor: sin I_LIQ')
        a = int(pa['cont_asiento_id'])
        check(abs(asiento_balance(a)) < 0.01, 'asiento de liquidación balanceado')
        check(abs(asiento_cuenta(a, E['cta']['IVA_SALDO_FAVOR']) - (calc['saldo_a_favor'] - calc['saldo_favor_anterior'])) < 0.01,
              'Debe en IVA saldo a favor por el saldo del mes', f"{asiento_cuenta(a, E['cta']['IVA_SALDO_FAVOR'])}")
    r = iva(E, dict(accion='liquidar', periodo=PER_A, sucursal_id=E['sucursal'], entidad_id=E['prov']))
    check(not ok_resp(r) and 'ya está liquidado' in err(r), 'no deja liquidar dos veces el mismo período', err(r))
    r = ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='anular', motivo='SIM',
             empresa_idx=E['empresa'], pagina_idx=E['pagina_fp']), modulo=E['modulo'], empresa_id=E['empresa'])
    check(not ok_resp(r) and 'IVA' in err(r) and int(val(f"SELECT tabla_estado_registro_id FROM gestion__facturas_proveedores WHERE factura_proveedor_id = {fid}")) == 5,
          'no deja anular una compra de un período de IVA liquidado', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso I3 - {tag}: el mes B arrastra el saldo a favor de A' + (' y una venta lo hace dar a pagar' if E['empresa'] == 2 else ''))
    if E['empresa'] == 2:
        r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
                 comprobante_tipo_id=4, entidad_id=313, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1, condicion_pago_id='',
                 descuento_general_pct=0, observaciones='SIM IVA',
                 detalles=[dict(producto_id=1529, cantidad=1, precio_unitario=2000000, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)]))
        vf = (r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id')) if isinstance(r, dict) else None
        if vf:
            r = ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=vf, accion_js='confirma', empresa_idx=2, pagina_idx=56))
        check(vf and ok_resp(r), 'venta Factura A de $2.000.000 + IVA confirmada en B', err(r))
    db = detalle(E, PER_B)
    check(db and abs(db['calculo']['saldo_favor_anterior'] - num(pa['saldo_a_favor'] if pa else 0)) < 0.01 and db['calculo']['saldo_favor_anterior_periodo'] == PER_A,
          'B toma el saldo a favor de A', str(db and db['calculo']))
    if db and (abs(db['calculo']['debito_fiscal']) + abs(db['calculo']['credito_fiscal']) + abs(db['calculo']['saldo_favor_anterior'])) > 0:
        calc = db['calculo']
        org = E['prov']
        if calc['saldo_a_pagar'] > 0:
            r = iva(E, dict(accion='liquidar', periodo=PER_B, sucursal_id=E['sucursal']))
            check(not ok_resp(r) and 'organismo' in err(r), 'a pagar sin organismo recaudador se rechaza', err(r))
        r = iva(E, dict(accion='liquidar', periodo=PER_B, sucursal_id=E['sucursal'], entidad_id=org, nro_ddjj='SIM-DDJJ'))
        pb = posicion(E, PER_B)
        check(ok_resp(r) and pb, 'liquidación de B registrada', err(r))
        if pb:
            a = int(pb['cont_asiento_id'])
            check(abs(asiento_balance(a)) < 0.01, 'asiento de B balanceado')
            check(abs(asiento_cuenta(a, E['cta']['IVA_SALDO_FAVOR']) - (calc['saldo_a_favor'] - calc['saldo_favor_anterior'])) < 0.01,
                  'el asiento consume el saldo a favor anterior')
            if calc['saldo_a_pagar'] > 0:
                c = uno(f"SELECT c.importe_total, c.importe_pendiente, c.tabla_estado_registro_id estado, o.impuesto_tipo_id, o.periodo_fiscal "
                        f"FROM gestion__comprobantes c JOIN gestion__obligaciones_pago o ON o.comprobante_id = c.comprobante_id "
                        f"WHERE c.comprobante_id = {pb['comprobante_id']}")
                check(c and abs(num(c['importe_total']) - calc['saldo_a_pagar']) < 0.01 and int(c['estado']) == 5 and int(c['impuesto_tipo_id']) == 7
                      and c['periodo_fiscal'] == PER_B, f"I_LIQ por {calc['saldo_a_pagar']} pendiente de pago (impuesto 7, período B)", str(c))
                check(abs(asiento_cuenta(a, E['cta']['IVA_A_PAGAR']) + calc['saldo_a_pagar']) < 0.01, 'Haber IVA a pagar por el saldo')
                check(int(val(f"SELECT comprobante_id FROM gestion__cont_asientos WHERE cont_asiento_id = {a}")) == int(pb['comprobante_id']),
                      'el asiento cuelga de la I_LIQ')
            r = iva(E, dict(accion='resumen', hasta=PER_B, meses=2), 'GET')
            fb = [m for m in r['data'] if m['periodo'] == PER_B] if ok_resp(r) else []
            check(fb and fb[0]['posicion'] and (fb[0]['posicion']['liquidacion'] is not None) == (calc['saldo_a_pagar'] > 0),
                  'el resumen muestra B liquidado', str(fb))

        # -------------------------------------------------------------------------------------
        caso(f'Caso I4 - {tag}: anulación (orden y reliquidación)')
        r = iva(E, dict(accion='anular', periodo=PER_A, motivo='SIM'))
        check(not ok_resp(r) and 'posterior' in err(r) and posicion(E, PER_A), 'no deja anular A mientras B está liquidado', err(r))
        r = iva(E, dict(accion='anular', periodo=PER_B, motivo=''))
        check(not ok_resp(r) and 'motivo' in err(r), 'pide el motivo', err(r))
        r = iva(E, dict(accion='anular', periodo=PER_B, motivo='SIM corrección'))
        check(ok_resp(r) and posicion(E, PER_B) is None, 'anula B', err(r))
        if pb:
            check(val(f"SELECT tabla_estado_registro_id FROM gestion__cont_asientos WHERE cont_asiento_id = {pb['cont_asiento_id']}") == '2', 'asiento de B anulado')
            if pb['comprobante_id']:
                check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__comprobantes WHERE comprobante_id = {pb['comprobante_id']}")) == 38
                      and int(val(f"SELECT tabla_estado_registro_id FROM gestion__obligaciones_pago WHERE comprobante_id = {pb['comprobante_id']}")) == 38,
                      'I_LIQ y obligación anuladas')
        r = iva(E, dict(accion='liquidar', periodo=PER_B, sucursal_id=E['sucursal'], entidad_id=E['prov']))
        check(ok_resp(r) and posicion(E, PER_B), 'se vuelve a liquidar B', err(r))
        iva(E, dict(accion='anular', periodo=PER_B, motivo='SIM limpieza'))
    r = iva(E, dict(accion='anular', periodo=PER_A, motivo='SIM limpieza'))
    check(ok_resp(r) and posicion(E, PER_A) is None, 'anula A (sin liquidaciones posteriores)', err(r))
    r = ajax('facturas_proveedores_ajax.php', dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='anular', motivo='SIM',
             empresa_idx=E['empresa'], pagina_idx=E['pagina_fp']), modulo=E['modulo'], empresa_id=E['empresa'])
    check(ok_resp(r), 'con A sin liquidar, la compra se puede anular', err(r))

    caso(f'Caso I5 - {tag}: libro IVA en CSV')
    r = iva(E, dict(accion='exportar_libro', periodo=PER_A), 'GET')
    raw = r.get('_raw', '') if isinstance(r, dict) else ''
    check('Libro;Fecha;Tipo' in raw, 'exportar_libro devuelve el CSV con encabezado', raw[:120])
