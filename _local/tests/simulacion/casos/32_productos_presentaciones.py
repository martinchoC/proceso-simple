"""Presentaciones de productos (etapa 1: ABM), módulos gestion_normal (empresa 6) y gestion (empresa 2).

En la copia: aplica 20261112_productos_presentaciones_01.sql y prende conf__empresas.usa_presentaciones en 2 y 6.
Da de alta un producto con dos presentaciones, lo busca por el DUN de la caja, rechaza un DUN de otro producto
(sin grabar el producto), edita (cambia una, quita otra, agrega una), comprueba que sin el campo no se tocan y repite
alta / búsqueda / validación en el módulo 2. Diseño en _local/docs/gestion/17_productos_presentaciones.md.
"""
import json

from simlib import *

_s = '20261112_productos_presentaciones_01.sql'
_r = subprocess.run([MYSQL, '-u', 'root', '--default-character-set=utf8mb4', DB],
                    stdin=open(os.path.join(AQUI, '..', '..', 'sql', 'mantenimiento', _s), 'rb'), capture_output=True)
if _r.returncode != 0:
    raise SystemExit(f'Falló {_s}: ' + _r.stderr.decode('utf-8', 'replace'))


def pres(lst):
    return json.dumps(lst)


def activas(pid):
    return [r['presentacion_nombre'] for r in (sql(f"SELECT presentacion_nombre FROM gestion__productos_presentaciones "
                                                   f"WHERE producto_id = {pid} AND tabla_estado_registro_id = 1 ORDER BY orden") or [])]


# Restos de una corrida anterior sobre la misma copia
sql("DELETE pr FROM gestion__productos_presentaciones pr JOIN gestion__productos p ON p.producto_id = pr.producto_id "
    "WHERE p.producto_codigo LIKE 'SIM-PRES-%'")
sql("DELETE FROM gestion__productos WHERE producto_codigo LIKE 'SIM-PRES-%'")
sql("UPDATE conf__empresas SET usa_presentaciones = 0 WHERE empresa_id = 6")

caso('Presentaciones: Gestión Normal con el indicador apagado')
r = ajax('productos_ajax.php', {'accion': 'obtener_presentaciones', 'producto_id': 1}, 'GET', modulo='gestion_normal', empresa_id=6)
check(r.get('success') and r.get('data') == [], 'obtener_presentaciones devuelve vacío', err(r))

sql("UPDATE conf__empresas SET usa_presentaciones = 1 WHERE empresa_id IN (2, 6)")
BASE = {'producto_tipo_id': val("SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = 6 AND tabla_estado_registro_id = 1 LIMIT 1"),
        'producto_categoria_id': val("SELECT producto_categoria_id FROM gestion__productos_categorias WHERE empresa_id = 6 AND tabla_estado_registro_id = 1 LIMIT 1"),
        'iva_alicuota_id': val("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = 6 AND tabla_estado_registro_id = 1 LIMIT 1"),
        'controla_stock': 1, 'aplica_compra': 1, 'aplica_venta': 1}

caso('Presentaciones: alta con presentaciones (Gestión Normal)')
r = ajax('productos_ajax.php', dict(BASE, accion='agregar', producto_codigo='SIM-PRES-YERBA', producto_nombre='Yerba mate 1 kg',
                                    codigo_barras='SIM-EAN-1',
                                    presentaciones=pres([{'presentacion_nombre': 'Caja x 10', 'factor': '10', 'codigo_barras': 'SIM-DUN-1', 'es_venta_defecto': 1},
                                                         {'presentacion_nombre': 'Pallet', 'factor': '600', 'es_compra_defecto': 1}])),
         modulo='gestion_normal', empresa_id=6)
check(ok_resp(r) and r.get('presentaciones_guardadas') is True, 'alta con presentaciones_guardadas', err(r))
PID = int(r.get('producto_id') or 0)
check(activas(PID) == ['Caja x 10', 'Pallet'], 'dos presentaciones activas, en orden', str(activas(PID)))
check(val(f"SELECT CONCAT(es_venta_defecto, es_compra_defecto) FROM gestion__productos_presentaciones WHERE producto_id = {PID} AND presentacion_nombre = 'Caja x 10'") == '10',
      'la caja es la habitual de venta')

caso('Presentaciones: lectura y búsqueda (Gestión Normal)')
r = ajax('productos_ajax.php', {'accion': 'listar', 'start': 0, 'length': 10, 'search': {'value': 'SIM-DUN-1'}}, 'GET', modulo='gestion_normal', empresa_id=6)
check([int(x['producto_id']) for x in (r.get('data') or [])] == [PID], 'el listado lo encuentra por el DUN de la caja', json.dumps(r)[:300])
r = ajax('productos_consulta_ajax.php', {'accion': 'obtener_presentaciones', 'producto_id': PID}, 'GET', modulo='gestion_normal', empresa_id=6)
check(r.get('success') and len(r.get('data') or []) == 2, 'la consulta de productos las lee', err(r))

caso('Presentaciones: un error no graba el producto (Gestión Normal)')
r = ajax('productos_ajax.php', dict(BASE, accion='agregar', producto_codigo='SIM-PRES-OTRO', producto_nombre='Otro',
                                    presentaciones=pres([{'presentacion_nombre': 'Caja', 'factor': '20', 'codigo_barras': 'SIM-DUN-1'}])),
         modulo='gestion_normal', empresa_id=6)
check(not ok_resp(r) and 'SIM-PRES-YERBA' in err(r), 'rechaza el DUN de otro producto', err(r))
check(int(val("SELECT COUNT(*) FROM gestion__productos WHERE producto_codigo = 'SIM-PRES-OTRO'")) == 0, 'el producto no quedó grabado')

caso('Presentaciones: edición (Gestión Normal)')
caja = ajax('productos_ajax.php', {'accion': 'obtener_presentaciones', 'producto_id': PID}, 'GET', modulo='gestion_normal', empresa_id=6)['data'][0]
r = ajax('productos_ajax.php', dict(BASE, accion='editar', producto_id=PID, producto_codigo='SIM-PRES-YERBA', producto_nombre='Yerba mate 1 kg',
                                    codigo_barras='SIM-EAN-1',
                                    presentaciones=pres([dict(caja, presentacion_nombre='Caja x 12', factor='12'),
                                                         {'presentacion_nombre': 'Pack x 3', 'factor': '3'}])),
         modulo='gestion_normal', empresa_id=6)
check(ok_resp(r) and r.get('presentaciones_guardadas') is True, 'edición OK', err(r))
check(activas(PID) == ['Caja x 12', 'Pack x 3'], 'cambia la caja y agrega el pack', str(activas(PID)))
check(int(val(f"SELECT presentacion_id FROM gestion__productos_presentaciones WHERE producto_id = {PID} AND presentacion_nombre = 'Caja x 12'")) == int(caja['presentacion_id']),
      'la caja conserva su id')
check(val(f"SELECT tabla_estado_registro_id FROM gestion__productos_presentaciones WHERE producto_id = {PID} AND presentacion_nombre = 'Pallet'") == '2',
      'el pallet queda inactivo (no se borra)')
r = ajax('productos_ajax.php', dict(BASE, accion='editar', producto_id=PID, producto_codigo='SIM-PRES-YERBA', producto_nombre='Yerba mate 1 kg'),
         modulo='gestion_normal', empresa_id=6)
check(ok_resp(r) and activas(PID) == ['Caja x 12', 'Pack x 3'], 'editar sin el campo no las toca', err(r))

caso('Presentaciones: Gestión (módulo 2)')
TIPO2 = val("SELECT producto_tipo_id FROM gestion__productos_tipos WHERE empresa_id = 2 AND tabla_estado_registro_id = 1 LIMIT 1")
IVA2 = val("SELECT iva_alicuota_id FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = 2 AND tabla_estado_registro_id = 1 LIMIT 1")
r = ajax('productos_ajax.php', {'accion': 'agregar', 'producto_codigo': 'SIM-PRES-CAJA', 'producto_nombre': 'Producto con caja', 'codigo_barras': '',
                                'producto_tipo_id': TIPO2, 'iva_alicuota_id': IVA2, 'niveles_stock': '[]',
                                'presentaciones': pres([{'presentacion_nombre': 'Caja x 6', 'factor': '6', 'codigo_barras': 'SIM-DUN-22'}])},
         modulo='gestion', empresa_id=2)
check(ok_resp(r) and r.get('presentaciones_guardadas') is True, 'alta con presentaciones', err(r))
PID2 = int(r.get('producto_id') or 0)
r = ajax('productos_ajax.php', {'accion': 'listar', 'start': 0, 'length': 10, 'search': {'value': 'SIM-DUN-22'}}, 'GET', modulo='gestion', empresa_id=2)
check(PID2 in [int(x.get('producto_id', 0)) for x in (r.get('data') or [])], 'el listado lo encuentra por el DUN', json.dumps(r)[:300])
r = ajax('productos_ajax.php', {'accion': 'editar', 'producto_id': PID2, 'producto_codigo': 'SIM-PRES-CAJA', 'producto_nombre': 'Producto con caja',
                                'producto_tipo_id': TIPO2, 'iva_alicuota_id': IVA2, 'niveles_stock': '[]',
                                'presentaciones': pres([{'presentacion_nombre': 'A', 'factor': '0'}])},
         modulo='gestion', empresa_id=2)
check(not ok_resp(r) and 'más de cero' in err(r), 'factor 0 se rechaza', err(r))
check(activas(PID2) == ['Caja x 6'], 'la caja sigue como estaba', str(activas(PID2)))
