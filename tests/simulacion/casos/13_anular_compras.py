"""Anular comprobantes de proveedor confirmados (5 -> 38): reversión de stock, asiento, cuenta corriente y OP automática.

Corre en gestion (empresa 2, pág. 57) y gestion_normal (empresa 6, pág. 557). Aplica en la copia (idempotentes) los scripts
de percepciones recibidas, código de producto por empresa, débito en cuenta y 20261011_facturas_proveedores_anular_01.
"""
from simlib import *

for _s in ('20261011_percepciones_recibidas_01_subgrupo_fc.sql', '20261011_percepciones_recibidas_02_provincias.sql',
           '20261011_productos_codigo_por_empresa_01.sql', '20261011_debito_en_cuenta_01_estructura.sql',
           '20261011_debito_en_cuenta_02_resumen_bancario.sql', '20261011_facturas_proveedores_anular_01.sql'):
    _r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                        stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
    if _r.returncode != 0:
        raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))


def entorno(modulo, empresa, pagina, pagina_op, prov, sucursal, deposito, prod_stock, pv):
    e = dict(modulo=modulo, empresa=empresa, pagina=pagina, pagina_op=pagina_op, prov=prov, sucursal=sucursal,
             deposito=deposito, prod=prod_stock, pv=pv)
    q = lambda s: val(s.format(e=empresa))
    e['moneda'] = int(q("SELECT moneda_id FROM gestion__monedas WHERE empresa_id = {e} AND codigo = 'ARS' LIMIT 1"))
    e['fa'] = int(q("SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e} AND codigo = 'C_FA'"))
    e['rb'] = int(q("SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e} AND codigo = 'C_RB'"))
    e['nca'] = int(q("SELECT ct.comprobante_tipo_id FROM gestion__comprobantes_tipos ct JOIN gestion__comprobantes_subgrupos sg "
                     "ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id WHERE sg.empresa_id = {e} AND sg.codigo = 'FC' "
                     "AND ct.letra = 'A' AND ct.signo = '-' LIMIT 1"))
    e['iva21'] = int(q("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = {e} AND es_gravado = 1 AND porcentaje = 21"))
    e['comision'] = int(q("SELECT producto_id FROM gestion__productos WHERE empresa_id = {e} AND producto_codigo = 'BCO_COMISION'"))
    e['banco'] = int(q("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = {e} AND cuenta_tipo = 'BANCO' "
                       "AND tabla_estado_registro_id = 1 ORDER BY tesoreria_cuenta_id LIMIT 1"))
    e['transfer'] = int(q("SELECT medio_pago_id FROM gestion__medios_pago WHERE empresa_id = {e} AND codigo = 'TRANSFER'"))
    e['nro'] = proximo_nro_proveedor(pv, 970000) - 1
    return e


f6 = uno("SELECT fp.entidad_id, fp.sucursal_id, fp.deposito_id, d.producto_id FROM gestion__facturas_proveedores fp "
         "JOIN gestion__facturas_proveedores_detalle d ON d.factura_proveedor_id = fp.factura_proveedor_id "
         "JOIN gestion__productos p ON p.producto_id = d.producto_id AND p.controla_stock = 1 AND p.es_servicio = 0 "
         "WHERE fp.empresa_id = 6 AND fp.tabla_estado_registro_id = 5 ORDER BY fp.factura_proveedor_id LIMIT 1")
ENTORNOS = [entorno('gestion', 2, 57, 106, 5765, 1, 1, 1529, 16)]
if not f6:   # la demo de la empresa 6 solo tiene servicios: solo en la copia, un producto con stock para la prueba
    if not val("SELECT producto_id FROM gestion__productos WHERE empresa_id = 6 AND producto_codigo = 'SIM_STOCK'"):
        sql("INSERT INTO gestion__productos (empresa_id, producto_codigo, producto_nombre, cont_cuenta_id, cont_cuenta_compra_id, producto_tipo_id, "
            "unidad_medida_id, iva_alicuota_id, controla_stock, es_servicio, aplica_compra, aplica_venta, tabla_estado_registro_id) "
            "SELECT 6, 'SIM_STOCK', 'Producto con stock (simulación)', pt.cont_cuenta_compra_default_id, pt.cont_cuenta_compra_default_id, "
            "pt.producto_tipo_id, 1, (SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = 6 AND porcentaje = 21 AND es_gravado = 1), "
            "1, 0, 1, 1, 1 FROM gestion__productos_tipos pt WHERE pt.empresa_id = 6 AND pt.maneja_stock = 1 LIMIT 1")
    f6 = uno("SELECT fp.entidad_id, fp.sucursal_id, fp.deposito_id, "
             "(SELECT producto_id FROM gestion__productos WHERE empresa_id = 6 AND controla_stock = 1 AND es_servicio = 0 "
             " AND tabla_estado_registro_id = 1 ORDER BY producto_id LIMIT 1) producto_id "
             "FROM gestion__facturas_proveedores fp WHERE fp.empresa_id = 6 AND fp.tabla_estado_registro_id = 5 ORDER BY 1 LIMIT 1")
if f6 and f6['producto_id']:
    ENTORNOS.append(entorno('gestion_normal', 6, 557, 606, int(f6['entidad_id']), int(f6['sucursal_id']), int(f6['deposito_id']),
                            int(f6['producto_id']), 17))


def call(e, archivo, params, method='POST'):
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa'])
    return ajax(archivo, p, method, modulo=e['modulo'], empresa_id=e['empresa'])


def fp(e, params, method='POST'):
    p = dict(params)
    p.setdefault('pagina_idx', e['pagina'])
    return call(e, 'facturas_proveedores_ajax.php', p, method)


def alta(e, tipo, prod, cant, precio, cuenta='', **extra):
    e['nro'] += 1
    dets = [linea(prod, cant, precio, iva_id=e['iva21'])]
    r = fp(e, dict(accion='agregar', comprobante_tipo_id=tipo, comprobante_pv=e['pv'], comprobante_nro=e['nro'], sucursal_id=e['sucursal'],
                   deposito_id=e['deposito'], entidad_id=e['prov'], f_emision=HOY, f_contabilidad=HOY, f_vencimiento=HOY,
                   moneda_id=e['moneda'], tipo_cambio=1, condicion_pago_id='', orden_compra_id='', descuento_general_pct=0,
                   detalles=dets, impuestos_adicionales=[], tesoreria_cuenta_debito_id=cuenta, **totales(dets), **extra))
    return r.get('factura_proveedor_id') if isinstance(r, dict) else None, r


def confirmar(e, fid):
    fp(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar'))
    return fp(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar'))


def anular(e, fid, motivo='SIM anulación'):
    return fp(e, dict(accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='anular', motivo=motivo))


def fila(fid):
    return uno(f"SELECT f.tabla_estado_registro_id estado, f.pago_automatico_id, c.comprobante_id, c.importe_total, c.importe_pendiente, "
               f"c.tabla_estado_registro_id comp_estado FROM gestion__facturas_proveedores f "
               f"JOIN gestion__comprobantes c ON c.comprobante_id = f.comprobante_id WHERE f.factura_proveedor_id = {fid}")


def stock_e(e):
    return num(val(f"SELECT cantidad FROM gestion__stock WHERE empresa_id = {e['empresa']} AND boca_id = {e['deposito']} AND producto_id = {e['prod']}"))


def saldo_cuenta(cid):
    return num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cid}"))


def asiento_estado(comp_id):
    return val(f"SELECT tabla_estado_registro_id FROM gestion__cont_asientos WHERE comprobante_id = {comp_id} LIMIT 1")


def imputaciones_vigentes(comp_id):
    return int(val(f"SELECT COUNT(*) FROM gestion__comprobantes_imputaciones WHERE (comp_destino_id = {comp_id} OR comp_origen_id = {comp_id}) "
                   f"AND tabla_estado_registro_id = 5"))


for E in ENTORNOS:
    tag = f"{E['modulo']} (empresa {E['empresa']})"

    # -----------------------------------------------------------------------------------------
    caso(f'Caso A1 - {tag}: anular una factura impaga revierte stock, asiento y saldo')
    s0 = stock_e(E)
    fid, r = alta(E, E['fa'], E['prod'], 3, 1000)
    check(fid, 'alta factura A con 3 unidades', err(r))
    if fid:
        r = confirmar(E, fid)
        f = fila(fid)
        check(ok_resp(r) and stock_e(E) == s0 + 3 and abs(num(f['importe_pendiente']) - 3630) < 0.01, 'confirmada: stock +3 y saldo 3.630', err(r))
        r = fp(E, dict(accion='listar'), 'GET')
        r = anular(E, fid)
        f = fila(fid)
        check(ok_resp(r) and int(f['estado']) == 38, 'anular -> factura en 38', err(r))
        check(int(f['comp_estado']) == 38 and abs(num(f['importe_pendiente'])) < 0.01, 'comprobante universal en 38 sin deuda pendiente', str(f))
        check(stock_e(E) == s0, f'stock vuelve a {s0} ({stock_e(E)})')
        check(asiento_estado(f['comprobante_id']) == '2', 'asiento anulado (2)', str(asiento_estado(f['comprobante_id'])))
        r = anular(E, fid)
        check(not ok_resp(r), 'no se puede anular dos veces', err(r))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso A2 - {tag}: anular un resumen bancario anula su OP automática y devuelve el banco')
    b0 = saldo_cuenta(E['banco'])
    fid, r = alta(E, E['rb'], E['comision'], 1, 2000, cuenta=E['banco'])
    if fid:
        r = confirmar(E, fid)
        f = fila(fid)
        check(ok_resp(r) and f['pago_automatico_id'] and abs(saldo_cuenta(E['banco']) - (b0 - 2420)) < 0.01,
              'confirmado: OP automática registrada en la factura y banco -2.420', err(r) + str(f))
        op = f['pago_automatico_id']
        r = anular(E, fid, 'SIM resumen mal cargado')
        f = fila(fid)
        check(ok_resp(r) and 'OP automática' in err(r), 'anular avisa que anuló la OP automática', err(r))
        check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = {op}")) == 38, 'la OP automática queda anulada (38)')
        check(abs(saldo_cuenta(E['banco']) - b0) < 0.01, f"banco restituido ({b0} -> {saldo_cuenta(E['banco'])})")
        check(imputaciones_vigentes(f['comprobante_id']) == 0 and int(f['comp_estado']) == 38, 'sin imputaciones vigentes, comprobante en 38')

    # -----------------------------------------------------------------------------------------
    caso(f'Caso A3 - {tag}: factura pagada en parte con una OP manual: la OP queda con saldo a favor')
    fid, r = alta(E, E['fa'], E['comision'], 1, 1000)    # 1.210
    if fid:
        confirmar(E, fid)
        comp = int(fila(fid)['comprobante_id'])
        r = call(E, 'pagos_ajax.php', dict(accion='guardar', pagina_idx=E['pagina_op'], entidad_id=E['prov'], sucursal_id=E['sucursal'],
                 f_emision=HOY, f_contabilidad=HOY, moneda_id=E['moneda'], tipo_cambio=1, observaciones='SIM',
                 medios=[dict(medio_pago_id=E['transfer'], importe=500, importe_comprobante=500, moneda_id=E['moneda'], tipo_cambio=1,
                              tesoreria_cuenta_id=E['banco'], nro_operacion='TRF-SIM-A3')],
                 imputaciones=[dict(comprobante_destino_id=comp, importe_imputado=500)]))
        pago = r.get('pago_id') if isinstance(r, dict) else None
        r = call(E, 'pagos_ajax.php', dict(accion='confirmar', id=pago)) if pago else r
        check(ok_resp(r) and abs(num(fila(fid)['importe_pendiente']) - 710) < 0.01, 'OP manual de 500: factura con saldo 710', err(r))
        b1 = saldo_cuenta(E['banco'])
        r = anular(E, fid)
        cop = int(val(f"SELECT comprobante_id FROM gestion__pagos WHERE pago_id = {pago}")) if pago else 0
        check(ok_resp(r) and int(fila(fid)['comp_estado']) == 38, 'factura anulada', err(r))
        check(int(val(f"SELECT tabla_estado_registro_id FROM gestion__pagos WHERE pago_id = {pago}")) == 5, 'la OP manual sigue confirmada')
        check(abs(num(val(f"SELECT importe_pendiente FROM gestion__comprobantes WHERE comprobante_id = {cop}")) - 500) < 0.01,
              'la OP manual queda con 500 a favor (imputación anulada)')
        check(abs(saldo_cuenta(E['banco']) - b1) < 0.01, 'el banco no se mueve (el pago fue real)')

    # -----------------------------------------------------------------------------------------
    caso(f'Caso A4 - {tag}: anular una NC aplicada devuelve el saldo a la factura')
    fid, r = alta(E, E['fa'], E['comision'], 1, 1000)    # 1.210
    if fid:
        confirmar(E, fid)
        comp_f = int(fila(fid)['comprobante_id'])
        nc, r = alta(E, E['nca'], E['comision'], 1, 500, comprobante_asociado_id=comp_f)   # 605
        r = confirmar(E, nc) if nc else r
        check(ok_resp(r) and abs(num(fila(fid)['importe_pendiente']) - 605) < 0.01, 'NC aplicada: factura con saldo 605', err(r))
        r = anular(E, nc)
        check(ok_resp(r) and int(fila(nc)['comp_estado']) == 38, 'NC anulada', err(r))
        f = fila(fid)
        check(abs(num(f['importe_pendiente']) - 1210) < 0.01 and int(f['comp_estado']) == 5, 'la factura recupera el saldo 1.210 y vuelve a Confirmado', str(f))

        # y al revés: anular la factura cancelada por otra NC deja la NC con saldo a favor
        nc2, r = alta(E, E['nca'], E['comision'], 1, 1000, comprobante_asociado_id=comp_f)   # 1.210, cancela la factura
        confirmar(E, nc2)
        check(int(fila(fid)['comp_estado']) == 40, 'otra NC cancela la factura (40)')
        r = anular(E, fid)
        c2 = fila(nc2)
        check(ok_resp(r) and abs(num(c2['importe_pendiente']) - 1210) < 0.01 and int(c2['comp_estado']) == 5,
              'anular la factura deja la NC con 1.210 a favor', err(r) + str(c2))

    # -----------------------------------------------------------------------------------------
    caso(f'Caso A5 - {tag}: rechazos')
    fid, r = alta(E, E['fa'], E['comision'], 1, 100)
    if fid:
        r = anular(E, fid)
        check(not ok_resp(r) and int(fila(fid)['estado']) == 3, 'no se anula un Borrador (se elimina)', err(r))
