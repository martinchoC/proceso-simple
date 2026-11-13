"""Ayudas de la prueba global de Gestión Normal (casos 20_ a 25_; diseño en _local/docs/pruebas/prueba_global_gestion_normal.md).

Dos empresas nuevas (S = servicios, B = bienes) creadas por la aplicación en el nivel 0. Los ids que crea cada nivel
quedan en salidas/ctx_global_<módulo>.json para los niveles siguientes.
"""
import datetime
import json
import os

from simlib import *

# Módulo bajo prueba: gestion_normal (5, grupo global_normal) o gestion (2, grupo global_gestion; run.py fija la variable).
# Los casos escriben los ids de página del módulo 5; pg() los traduce (módulo 2 = id - 500).
MOD_CARPETA = os.environ.get('SIM_GLOBAL_MODULO', 'gestion_normal')
MOD_ID = 2 if MOD_CARPETA == 'gestion' else 5
MOD_OTRO_ID = 5 if MOD_ID == 2 else 2                     # excluyente con el módulo bajo prueba
PERFIL_BASE = 200 if MOD_ID == 2 else 500                 # perfiles base 201–208 / 501–508


def pg(pagina_id_modulo5):
    return pagina_id_modulo5 - 500 if MOD_ID == 2 else pagina_id_modulo5


CTX_PATH = os.path.join(SALIDAS, f'ctx_global_{MOD_CARPETA}.json')

# Las operaciones van en el mes anterior a la corrida (N5 lo liquida y lo cierra); las de cómputo distinto, en el actual.
_hoy = datetime.date.today()
_ini_mes = _hoy.replace(day=1)
MES_OP = (_ini_mes - datetime.timedelta(days=1)).replace(day=1)          # primer día del mes anterior
PERIODO_OP = MES_OP.strftime('%Y-%m')
PERIODO_SIG = _ini_mes.strftime('%Y-%m')


def fecha_op(dia):
    """Fecha del mes de las operaciones (día 1..28)."""
    return MES_OP.replace(day=int(dia)).isoformat()


def cuit(base10):
    """CUIT de 11 dígitos con el dígito verificador correcto a partir de los 10 primeros."""
    s = str(base10)
    pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]
    r = 11 - sum(int(a) * b for a, b in zip(s, pesos)) % 11
    dv = 0 if r == 11 else (9 if r == 10 else r)
    return s + str(dv)


EMPRESAS = {
    'S': dict(nombre='SIM Servicios Patagonia SRL' + (' G' if MOD_ID == 2 else ''), cuit=cuit(3071998001 if MOD_ID == 2 else 3071999001), tipo_operacion='SERVICIOS', origen=6,
              iibb_regimen_id=1, sede_id=15, localidad='NEUQUEN', provincia_local='DGR_NQN'),
    'B': dict(nombre='SIM Distribuidora Valle SA' + (' G' if MOD_ID == 2 else ''), cuit=cuit(3071998002 if MOD_ID == 2 else 3071999002), tipo_operacion='BIENES', origen=2,
              iibb_regimen_id=2, sede_id=16, localidad='CIPOLLETTI', provincia_local='DGR_RN'),
}


def ctx_cargar():
    if os.path.exists(CTX_PATH):
        with open(CTX_PATH, encoding='utf-8') as f:
            return json.load(f)
    return {}


def ctx_guardar(ctx):
    with open(CTX_PATH, 'w', encoding='utf-8') as f:
        json.dump(ctx, f, ensure_ascii=False, indent=1)


def aj(e, archivo, params, method='POST', usuario_id=1):
    """Llama a un _ajax.php del módulo bajo prueba con la sesión en la empresa e (dict con 'empresa_id')."""
    p = dict(params)
    p.setdefault('empresa_idx', e['empresa_id'])
    return ajax(archivo, p, method, usuario_id=usuario_id, modulo=MOD_CARPETA, empresa_id=e['empresa_id'])


def ajc(archivo, params, method='POST', empresa_id=1):
    """Llama a un _ajax.php del módulo conf (sesión en la empresa 1 CONFIGURACION, superadmin)."""
    return ajax(archivo, params, method, modulo='conf', empresa_id=empresa_id)


def jur(codigo):
    return int(val(f"SELECT jurisdiccion_id FROM gestion__jurisdicciones WHERE jurisdiccion_codigo = '{codigo}'"))


def ids_de(r):
    """Lista de dicts de una respuesta {data: [...]} o [...]."""
    if isinstance(r, list):
        return r
    if isinstance(r, dict):
        d = r.get('data')
        return d if isinstance(d, list) else []
    return []


def tipo_comp(e, subgrupo, letra=None, signo=None, like=None):
    """Tipo de comprobante de la empresa por código de subgrupo (+ letra / signo / nombre)."""
    w = [f"sg.empresa_id = {e['empresa_id']}", f"sg.codigo = '{subgrupo}'", "ct.tabla_estado_registro_id = 1"]
    if letra:
        w.append(f"ct.letra = '{letra}'")
    if signo:
        w.append(f"ct.signo = '{signo}'")
    if like:
        w.append(f"ct.comprobante_tipo LIKE '{like}'")
    return int(val(f"SELECT ct.comprobante_tipo_id FROM gestion__comprobantes_tipos ct JOIN gestion__comprobantes_subgrupos sg "
                   f"ON sg.comprobante_subgrupo_id = ct.comprobante_subgrupo_id WHERE {' AND '.join(w)} "
                   f"ORDER BY ct.comprobante_tipo_id LIMIT 1") or 0)


def fp_alta_confirmar(e, tipo, lineas, fecha, pv_prov=1, nro=None):
    """Factura de proveedor por la pantalla (pág. 557): alta, aprobar y confirmar. lineas = [(producto_id, cant, precio)]."""
    nro = nro or int(val(f"SELECT IFNULL(MAX(comprobante_nro),0) + 1 FROM gestion__facturas_proveedores WHERE empresa_id = {e['empresa_id']} "
                         f"AND entidad_id = {e['proveedor_id']} AND comprobante_pv = {pv_prov}"))
    dets = [linea(p, c, pr, iva_id=e['iva21']) for p, c, pr in lineas]
    t = totales(dets)
    base = dict(empresa_idx=e['empresa_id'], pagina_idx=pg(557))
    r = aj(e, 'facturas_proveedores_ajax.php', dict(base, accion='agregar', comprobante_tipo_id=tipo, comprobante_pv=pv_prov, comprobante_nro=nro,
                                                    sucursal_id=e['sucursal_id'], deposito_id=e['boca_id'], entidad_id=e['proveedor_id'],
                                                    f_emision=fecha, f_contabilidad=fecha, f_vencimiento=fecha, moneda_id=e['moneda_id'], tipo_cambio=1,
                                                    condicion_pago_id='', orden_compra_id='', descuento_general_pct=0, detalles=dets,
                                                    impuestos_adicionales=[], **t))
    fid = r.get('factura_proveedor_id') if isinstance(r, dict) else None
    if not fid:
        return r, None, t
    aj(e, 'facturas_proveedores_ajax.php', dict(base, accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='aprobar'))
    r = aj(e, 'facturas_proveedores_ajax.php', dict(base, accion='ejecutar_accion', factura_proveedor_id=fid, accion_js='confirmar'))
    return r, int(fid), t


# ================================================================== operaciones de venta y cobranza (niveles 1 a 5)
def empresas_ctx():
    """Las dos empresas con todo lo que dejó el nivel 0 (S y B)."""
    ctx = ctx_cargar()
    out = {}
    for k in ('S', 'B'):
        if k in ctx:
            e = dict(EMPRESAS[k])
            e.update(ctx[k])
            e['k'] = k
            out[k] = e
    return ctx, out


def medio(e, codigo):
    return uno(f"SELECT medio_pago_id, impuesto_tipo_id, cont_cuenta_id, tesoreria_cuenta_id FROM gestion__medios_pago "
               f"WHERE empresa_id = {e['empresa_id']} AND codigo = '{codigo}'")


def m_efectivo(e, importe):
    return dict(medio_pago_id=int(medio(e, 'EFECTIVO')['medio_pago_id']), importe=importe, importe_comprobante=importe,
                moneda_id=e['moneda_id'], tipo_cambio=1, tesoreria_cuenta_id=e['caja_id'])


def m_transfer(e, importe):
    return dict(medio_pago_id=int(medio(e, 'TRANSFER')['medio_pago_id']), importe=importe, importe_comprobante=importe,
                moneda_id=e['moneda_id'], tipo_cambio=1, tesoreria_cuenta_id=e['banco_id'], referencia='SIM transferencia')


def m_ret(e, codigo, importe, cert, jur=None, base=None, alicuota=None, periodo=None):
    """Retención sufrida: codigo RET_IVA / RET_GAN / RET_IIBB; jur = id de jurisdicción (solo IIBB)."""
    d = dict(medio_pago_id=int(medio(e, codigo)['medio_pago_id']), importe=importe, importe_comprobante=importe,
             moneda_id=e['moneda_id'], tipo_cambio=1, retencion_certificado_nro=cert,
             retencion_base_imponible=base if base is not None else round(importe * 100 / 3, 2), retencion_alicuota=alicuota or 3)
    if jur:
        d['jurisdiccion_id'] = jur
    if periodo:
        d['retencion_periodo_computo'] = periodo
    return d


def venta(e, cliente, lineas, fecha, tipo_cod='V_FA', observaciones='SIM global'):
    """Factura de venta por la pantalla (pág. 556): alta, confirmar y aprobar (ARCA deshabilitado).
    lineas = [(producto_id, cantidad, precio_unitario)] al 21 %. Devuelve dict con ids, total y respuesta."""
    tipo = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = '{tipo_cod}'") or 0)
    dets = [dict(producto_id=p, cantidad=c, precio_unitario=pr, descuento_general_pct=0, iva_alicuota_id=e['iva21'], iva_porcentaje=21)
            for p, c, pr in lineas]
    base = dict(pagina_idx=pg(556))
    r = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='agregar', punto_venta_id=e['pv_id'], sucursal_id=e['sucursal_id'],
                                               comprobante_tipo_id=tipo, entidad_id=cliente, f_emision=fecha, f_vto=fecha,
                                               moneda_id=e['moneda_id'], tipo_cambio=1, condicion_pago_id='', descuento_general_pct=0,
                                               observaciones=observaciones, detalles=dets))
    fid = None
    if isinstance(r, dict):
        fid = r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')
    out = dict(r=r, fid=int(fid) if fid else None, tipo=tipo, comp_id=None, total=0, nro=0, estado=None)
    if not fid:
        return out
    r = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=fid, accion_js='confirma'))
    if ok_resp(r) and val(f"SELECT tabla_estado_registro_id FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}") == '4':
        r = aj(e, 'ventas_facturas_ajax.php', dict(base, accion='ejecutar_accion', venta_factura_id=fid, accion_js='aprobada'))
    out['r'] = r
    c = uno(f"SELECT comprobante_id, importe_total, comprobante_nro, tabla_estado_registro_id estado FROM gestion__comprobantes "
            f"WHERE tabla_origen_id = 80 AND registro_origen_id = {fid}")
    if c:
        out.update(comp_id=int(c['comprobante_id']), total=num(c['importe_total']), nro=int(c['comprobante_nro'] or 0), estado=int(c['estado']))
    return out


def recibo(e, cliente, medios, imputaciones, fecha, confirmar=True):
    """Recibo de cobranza por la pantalla (pág. 605). imputaciones = [(comprobante_id, importe)]."""
    base = dict(pagina_idx=pg(605))
    tipo = int(val(f"SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = {e['empresa_id']} AND codigo = 'RCX'") or 0)
    r = aj(e, 'cobranzas_ajax.php', dict(base, accion='guardar', entidad_id=cliente, sucursal_id=e['sucursal_id'], comprobante_pv=e['pv_id'],
                                         comprobante_tipo_id=tipo, f_emision=fecha, f_contabilidad=fecha, moneda_id=e['moneda_id'], tipo_cambio=1,
                                         observaciones='SIM global', medios=medios,
                                         imputaciones=[dict(comprobante_destino_id=c, importe_imputado=i) for c, i in imputaciones]))
    cob = r.get('cobranza_id') if isinstance(r, dict) else None
    out = dict(r=r, cob=int(cob) if cob else None, comp_id=None)
    if cob and confirmar:
        out['r'] = aj(e, 'cobranzas_ajax.php', dict(base, accion='confirmar', id=cob))
        cid = val(f"SELECT comprobante_id FROM gestion__comprobantes WHERE tabla_origen_id = 275 AND registro_origen_id = {cob}")
        out['comp_id'] = int(cid) if cid else None
    return out


def saldo(comp_id):
    c = uno(f"SELECT importe_pendiente, tabla_estado_registro_id estado FROM gestion__comprobantes WHERE comprobante_id = {int(comp_id or 0)}")
    return (num(c['importe_pendiente']), int(c['estado'])) if c else (None, None)


def asiento_de(comp_id):
    """Renglones del asiento vigente del comprobante: [(cuenta_id, empresa de la cuenta, importe)]."""
    return [(int(x['cuenta_id']), int(x['emp']), num(x['importe_local'])) for x in sql(
        f"SELECT d.cuenta_id, c.empresa_id emp, d.importe_local FROM gestion__cont_asientos a "
        f"JOIN gestion__cont_asientos_detalles d ON d.cont_asiento_id = a.cont_asiento_id "
        f"JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id "
        f"WHERE a.comprobante_id = {int(comp_id or 0)} AND a.tabla_estado_registro_id NOT IN (2, 38)")]


def debe(renglones, cuenta_id):
    return round(sum(i for c, _, i in renglones if c == int(cuenta_id) and i > 0), 2)


def cuenta_ret_iibb(e, jur_id):
    return int(val(f"SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config WHERE empresa_id = {e['empresa_id']} AND impuesto_tipo_id = 4 "
                   f"AND jurisdiccion_id = {int(jur_id)} AND tabla_estado_registro_id = 1 LIMIT 1") or 0)


def verificar_operacion(e, op, factura=None, rec=None, efectivo=0.0, banco=0.0, retenciones=(), saldo_esperado=None, valores=0.0,
                        tes0=None, stock_esperado=None):
    """Chequeos estándar de una operación (diseño §4). retenciones = [(codigo_medio, importe, cert, jur)].
    tes0 = saldos de tesorería antes de la operación; stock_esperado = {producto_id: cantidad}."""
    tag = f"[{e['k']}] {op}"
    if factura is not None:
        check(factura.get('fid') and factura.get('estado') == 5 and factura.get('nro', 0) > 0,
              f'{tag}: factura confirmada y numerada', err(factura.get('r')) + f" estado {factura.get('estado')} nro {factura.get('nro')}")
        prev = val(f"SELECT MAX(c.comprobante_nro) FROM gestion__comprobantes c WHERE c.empresa_id = {e['empresa_id']} "
                   f"AND c.comprobante_tipo_id = {factura['tipo']} AND c.comprobante_pv = {e['pv_id']} AND c.comprobante_id < {factura.get('comp_id') or 0}")
        check(factura.get('nro') == int(prev or 0) + 1, f'{tag}: número correlativo ({factura.get("nro")} después de {prev})')
        ren = asiento_de(factura.get('comp_id'))
        check(ren and abs(sum(i for _, _, i in ren)) < 0.01 and all(emp == e['empresa_id'] for _, emp, _ in ren),
              f'{tag}: asiento de la factura balanceado y con cuentas propias', str(ren))
    if rec is not None:
        check(rec.get('cob') and ok_resp(rec.get('r')) and rec.get('comp_id'), f'{tag}: recibo confirmado', err(rec.get('r')))
        if not rec.get('comp_id'):
            return
        total_ret = round(sum(i for _, i, _, _ in retenciones), 2)
        tot = num(val(f"SELECT importe_total FROM gestion__comprobantes WHERE comprobante_id = {rec['comp_id']}"))
        check(abs(tot - (efectivo + banco + valores + total_ret)) < 0.01, f'{tag}: total del recibo = medios ({tot})', f'{efectivo}+{banco}+{valores}+{total_ret}')
        movs = sql(f"SELECT tesoreria_cuenta_id, importe, tipo_movimiento FROM gestion__tesoreria_movimientos WHERE comprobante_id = {rec['comp_id']}")
        mov_total = round(sum(num(m['importe']) for m in movs), 2)
        check(abs(mov_total - (efectivo + banco + valores)) < 0.01, f'{tag}: tesorería mueve solo el dinero ({mov_total}); las retenciones no', str(movs))
        if tes0:
            for cuenta, esperado in ((e['caja_id'], efectivo), (e['banco_id'], banco)):
                actual = num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {cuenta}"))
                check(abs(actual - tes0[cuenta] - esperado) < 0.01, f'{tag}: cuenta de tesorería {cuenta} +{esperado}', f'{tes0[cuenta]} -> {actual}')
        ren = asiento_de(rec['comp_id'])
        check(ren and abs(sum(i for _, _, i in ren)) < 0.01 and all(emp == e['empresa_id'] for _, emp, _ in ren),
              f'{tag}: asiento del recibo balanceado y con cuentas propias', str(ren))
        por_cuenta = {}
        for cod, imp, cert, jur in retenciones:
            cta = cuenta_ret_iibb(e, jur) if jur else int(medio(e, cod)['cont_cuenta_id'])
            por_cuenta[cta] = round(por_cuenta.get(cta, 0) + imp, 2)
        for cta, imp in por_cuenta.items():
            check(debe(ren, cta) == imp, f'{tag}: Debe {imp} en la cuenta de retención {cta}', str(ren))
        lineas = sql(f"SELECT cm.medio_pago_id, cm.impuesto_tipo_id, cm.jurisdiccion_id, cm.importe, cm.retencion_certificado_nro cert, "
                     f"cm.retencion_certificado_estado est, cm.retencion_periodo_computo per FROM gestion__cobranzas_medios cm "
                     f"WHERE cm.cobranza_id = {rec['cob']} AND cm.impuesto_tipo_id IS NOT NULL")
        check(len(lineas) == len(retenciones), f'{tag}: {len(retenciones)} líneas de retención', str(lineas))
        for cod, imp, cert, jur in retenciones:
            m = medio(e, cod)
            l = next((x for x in lineas if int(x['medio_pago_id']) == int(m['medio_pago_id']) and abs(num(x['importe']) - imp) < 0.01
                      and (x['cert'] or '') == (cert or '')), None)
            ok = bool(l) and int(l['impuesto_tipo_id']) == int(m['impuesto_tipo_id']) and int(l['jurisdiccion_id'] or 0) == int(jur or 0)
            ok = ok and (l['est'] == 'PENDIENTE' if not cert else l['est'] in ('RECIBIDO', 'CONCILIADO'))
            check(ok, f'{tag}: línea {cod} {imp} (impuesto del medio, jurisdicción, certificado {cert or "PENDIENTE"})', str(l))
    if factura is not None and saldo_esperado is not None:
        s, est = saldo(factura.get('comp_id'))
        esperado_est = 40 if abs(saldo_esperado) < 0.01 else (39 if saldo_esperado < factura['total'] - 0.01 else 5)
        check(s is not None and abs(s - saldo_esperado) < 0.01 and est == esperado_est,
              f'{tag}: saldo de la factura {saldo_esperado} y estado {esperado_est}', f'saldo {s} estado {est}')
    if stock_esperado:
        for p, q in stock_esperado.items():
            s = stock(p, e['boca_id'], e['empresa_id'])
            check(abs(s - q) < 0.0001, f'{tag}: stock del producto {p} = {q}', f'{s}')


def saldos_tes(e):
    return {c: num(val(f"SELECT saldo_actual FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = {c}"))
            for c in (e['caja_id'], e['banco_id'], e['valores_id'])}


def ctacte_cliente(e, cliente):
    """Saldo del cliente: facturas / ND con saldo menos recibos / NC con saldo a favor."""
    # Solo facturas / notas (80) y recibos (275): el espejo de pedidos y remitos no es deuda del cliente
    return round(num(val(f"SELECT IFNULL(SUM(c.importe_pendiente * IF(c.tabla_origen_id = 275 OR c.signo < 0, -1, 1)), 0) FROM gestion__comprobantes c "
                         f"WHERE c.empresa_id = {e['empresa_id']} AND c.entidad_id = {cliente} AND c.tabla_estado_registro_id IN (5, 39, 40) "
                         f"AND c.tabla_origen_id IN (80, 275)")), 2)


def foto_empresa(eid):
    """Conteos y saldos de una empresa para verificar que la prueba no la tocó."""
    q = {t: f"SELECT COUNT(*) FROM gestion__{t} WHERE empresa_id = {eid}" for t in (
        'comprobantes', 'cont_asientos', 'cobranzas', 'empresas_impuestos_config', 'empresas_parametros', 'medios_pago',
        'cont_cuentas', 'comprobantes_tipos', 'tesoreria_movimientos', 'stock_movimientos', 'impuestos_periodos_cierres')}
    out = {t: val(s) for t, s in q.items()}
    out['saldo_tesoreria'] = val(f"SELECT ROUND(IFNULL(SUM(saldo_actual),0),2) FROM gestion__tesoreria_cuentas WHERE empresa_id = {eid}")
    return out
