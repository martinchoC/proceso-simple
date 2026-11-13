# Facturación electrónica contra ARCA HOMOLOGACIÓN (llamadas reales a WSAA/WSFE).
# Fuera del glob NN_*.py a propósito: no corre con "run.py" a secas, solo con
#   python _local/tests/simulacion/run.py arca_homologacion
# Requiere arca_habilitado = 1 en la vigencia abierta de gestion__empresas_parametros (empresa 2)
# y la integración WSFE de homologación activa. Cada corrida consume números de homologación.
from simlib import *  # noqa: F401,F403

CLI_RI = 313   # ABEJER WALTER - Responsable Inscripto
PROD = 1529

check(val("SELECT arca_habilitado FROM gestion__empresas_parametros WHERE empresa_id = 2 AND f_hasta IS NULL") == '1',
      'ARCA habilitado en la empresa 2')


def accion_vf(fid, accion):
    return ajax('ventas_facturas_ajax.php', dict(accion='ejecutar_accion', venta_factura_id=fid, accion_js=accion,
                empresa_idx=2, pagina_idx=56))


def factura(tipo, entidad, precio, **extra):
    r = ajax('ventas_facturas_ajax.php', dict(accion='agregar', empresa_idx=2, pagina_idx=56, punto_venta_id=1, sucursal_id=1,
             comprobante_tipo_id=tipo, entidad_id=entidad, f_emision=HOY, f_vto=HOY, moneda_id=1, tipo_cambio=1,
             condicion_pago_id='', descuento_general_pct=0, observaciones='SIM ARCA homologación',
             detalles=[dict(producto_id=PROD, cantidad=1, precio_unitario=precio, descuento_general_pct=0, iva_alicuota_id=1, iva_porcentaje=21)],
             **extra))
    fid = None
    if isinstance(r, dict):
        fid = r.get('venta_factura_id') or (r.get('data') or {}).get('venta_factura_id') or r.get('id')
    return r, fid


def vf(fid):
    return uno(f"SELECT tabla_estado_registro_id estado, comprobante_nro, CAE, f_vto_CAE, comprobante_id, importe_neto, importe_iva, importe_total "
               f"FROM gestion__ventas_facturas WHERE venta_factura_id = {fid}")


def log_desde(desde):
    return sql(f"SELECT o.integracion_operacion_id op, r.resultado, r.codigo, LEFT(r.descripcion, 160) descripcion "
               f"FROM gestion__integraciones_resultados r LEFT JOIN conf__integraciones_operaciones o ON o.integracion_operacion_id = r.integracion_operacion_id "
               f"WHERE r.integracion_resultado_id > {desde} ORDER BY r.integracion_resultado_id")


def circuito(nombre, tipo, entidad, precio, estado_final='5', **extra):
    caso(nombre)
    marca = int(val("SELECT IFNULL(MAX(integracion_resultado_id), 0) FROM gestion__integraciones_resultados"))
    r, fid = factura(tipo, entidad, precio, **extra)
    check(ok_resp(r) and fid, 'alta en borrador', err(r))
    if not fid:
        return
    r = accion_vf(fid, 'confirma')
    f = vf(fid)
    check(ok_resp(r) and f['estado'] == '4' and f['comprobante_nro'] == '0',
          'confirmar -> 4 Pendiente de ARCA, sin número', f'{err(r)} | {f}')
    r = accion_vf(fid, 'aprobada')
    f = vf(fid)
    print('     respuesta Aprobada:', json.dumps(r, ensure_ascii=False)[:600])
    print('     factura:', f)
    for fila in log_desde(marca):
        print('     log ARCA:', fila)
    check(ok_resp(r) and f['estado'] == estado_final, f'Aprobada -> {estado_final}', f"{err(r)} | estado {f['estado']}")
    check(bool(f['CAE']) and len(f['CAE']) == 14, 'CAE de 14 dígitos guardado', f['CAE'])
    check(int(f['comprobante_nro'] or 0) > 0, 'número asignado por ARCA', f['comprobante_nro'])
    c = uno(f"SELECT comprobante_nro, tabla_estado_registro_id estado FROM gestion__comprobantes WHERE comprobante_id = {int(f['comprobante_id'] or 0)}")
    check(c and c['comprobante_nro'] == f['comprobante_nro'], 'espejo en gestion__comprobantes con el mismo número', c)
    a = val(f"SELECT COUNT(*) FROM gestion__cont_asientos WHERE comprobante_id = {int(f['comprobante_id'] or 0)}")
    check(int(a or 0) >= 1, 'asiento generado', a)


# Consumidor final de contado: al autorizarse se cobra en caja y queda 40 (Cancelado total)
circuito('ARCA - Factura B a consumidor final (PV 1)', 12, 0, 1210, estado_final='40', consumidor_final_nombre='Consumidor final SIM')
circuito('ARCA - Factura A a Responsable Inscripto (PV 1)', 4, CLI_RI, 1000)
