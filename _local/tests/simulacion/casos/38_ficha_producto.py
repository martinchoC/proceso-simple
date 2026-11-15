from simlib import *

# Ficha de producto completa del módulo 5 (doc 26), paso 7-1: solapa Proveedores (empresa 6, gestion_normal).
# Necesita en la copia: 20261121_ficha_producto_01 (--scripts). Prende el indicador solo en la copia.
#   python _local/tests/simulacion/run.py 38_ficha_producto.py --scripts <arca_off.sql> \
#       _local/sql/mantenimiento/20261121_ficha_producto_01.sql

E = 6
CTX = {}


def pa(params, method='POST'):
    return ajax('productos_ajax.php', dict(params, empresa_idx=E), method, modulo='gestion_normal', empresa_id=E)


def pc(params, method='GET'):
    return ajax('productos_consulta_ajax.php', dict(params, empresa_idx=E), method, modulo='gestion_normal', empresa_id=E)


def data(r):
    return r.get('data') if isinstance(r, dict) else None


# ---------------------------------------------------------------------------------------------
caso('Ficha completa: preparación (indicador, solo en la copia)')
if int(val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' "
           "AND COLUMN_NAME = 'usa_ficha_producto_completa'")) != 1:
    brecha('falta el script de la ficha en la copia', 'correr con --scripts 20261121_ficha_producto_01')
else:
    prov = val("SELECT e.entidad_id FROM gestion__entidades e WHERE e.empresa_id = %d AND e.tabla_estado_registro_id = 1 AND EXISTS ("
               "SELECT 1 FROM gestion__entidades_roles r JOIN gestion__roles_entidades c ON c.rol_entidad_id = r.rol_entidad_id "
               "AND c.rol_entidad_codigo = 'PRO' WHERE r.entidad_id = e.entidad_id AND r.f_baja IS NULL AND r.tabla_estado_registro_id = 1) "
               "ORDER BY e.entidad_id LIMIT 1" % E)
    no_prov = val("SELECT e.entidad_id FROM gestion__entidades e WHERE e.empresa_id = %d AND NOT EXISTS ("
                  "SELECT 1 FROM gestion__entidades_roles r JOIN gestion__roles_entidades c ON c.rol_entidad_id = r.rol_entidad_id "
                  "AND c.rol_entidad_codigo = 'PRO' WHERE r.entidad_id = e.entidad_id AND r.f_baja IS NULL) ORDER BY e.entidad_id LIMIT 1" % E)
    # Un producto activo sin proveedores (los vínculos de la copia se dejan como están)
    prod = val("SELECT p.producto_id FROM gestion__productos p WHERE p.empresa_id = %d AND p.tabla_estado_registro_id = 1 "
               "AND NOT EXISTS (SELECT 1 FROM gestion__productos_proveedores pp WHERE pp.producto_id = p.producto_id) "
               "ORDER BY p.producto_id LIMIT 1" % E)
    otro = val("SELECT producto_id FROM gestion__productos WHERE empresa_id <> %d ORDER BY producto_id LIMIT 1" % E)
    sql("UPDATE conf__empresas SET usa_ficha_producto_completa = 0 WHERE empresa_id = %d" % E)
    r = pa(dict(accion='ficha_proveedores_listar', producto_id=prod), 'GET')
    check(not ok_resp(r) and 'ficha' in err(r).lower(), 'con el indicador apagado las acciones no responden', err(r))
    sql("UPDATE conf__empresas SET usa_ficha_producto_completa = 1 WHERE empresa_id = %d" % E)
    CTX.update(ok=bool(prov and prod), prov=int(prov or 0), no_prov=int(no_prov or 0), prod=int(prod or 0), otro=int(otro or 0))
    check(CTX['ok'], 'datos de prueba: proveedor y producto sin vínculos de la empresa 6', str(CTX))

caso('Ficha completa: proveedores del producto')
if CTX.get('ok'):
    P, V = CTX['prod'], CTX['prov']
    cod = 'SIMPV-%d' % P
    r = pa(dict(accion='ficha_proveedores_combo'), 'GET')
    check(ok_resp(r) and any(int(x['entidad_id']) == V for x in data(r)), 'el combo trae los proveedores de la empresa', str(r)[:200])
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, entidad_id=V, codigo_proveedor=cod))
    pp = (data(r) or {}).get('producto_proveedor_id')
    check(ok_resp(r) and pp, 'vincular el proveedor con su código', err(r))
    r = pa(dict(accion='ficha_proveedores_listar', producto_id=P), 'GET')
    filas = data(r) or []
    check(ok_resp(r) and len(filas) == 1 and filas[0]['codigo_proveedor'] == cod and int(filas[0]['es_principal']) == 1,
          'el único vínculo queda como principal', str(filas)[:200])
    check(val("SELECT proveedores_busqueda FROM gestion__productos WHERE producto_id = %d" % P) == cod,
          'se recalcula la búsqueda por código de proveedor')
    r = pa(dict(accion='listar', start=0, length=10, search={'value': cod}), 'GET')
    check(ok_resp(r) and any(int(x.get('producto_id', 0)) == P for x in (r.get('data') or [])), 'el listado encuentra el producto por el código del proveedor', str(r)[:200])
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, entidad_id=V, codigo_proveedor='X'))
    check(not ok_resp(r) and 'ya está vinculado' in err(r), 'el mismo proveedor dos veces se rechaza', err(r))
    if CTX['no_prov']:
        r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, entidad_id=CTX['no_prov'], codigo_proveedor='X'))
        check(not ok_resp(r) and 'no es un proveedor' in err(r), 'una entidad que no es proveedor se rechaza', err(r))
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=CTX['otro'], entidad_id=V, codigo_proveedor='X'))
    check(not ok_resp(r), 'un producto de otra empresa se rechaza', err(r))
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, entidad_id=V, codigo_proveedor='X' * 51))
    check(not ok_resp(r) and '50' in err(r), 'código de más de 50 caracteres se rechaza', err(r))
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, producto_proveedor_id=pp, entidad_id=V, codigo_proveedor=cod + "-B'2"))
    check(ok_resp(r) and val("SELECT codigo_proveedor FROM gestion__productos_proveedores WHERE producto_proveedor_id = %d" % int(pp)) == cod + "-B'2",
          'editar el código (con comilla, sin escaparla dos veces)', err(r))
    r = pc(dict(accion='ficha_proveedores_listar', producto_id=P))
    check(ok_resp(r) and len(data(r) or []) == 1, 'la consulta de productos ve la solapa', err(r))
    r = pc(dict(accion='ficha_proveedor_quitar', producto_id=P, producto_proveedor_id=pp), 'POST')
    check(not ok_resp(r), 'la consulta no puede modificar', str(r)[:200])
    r = pa(dict(accion='ficha_proveedor_quitar', producto_id=P, producto_proveedor_id=pp))
    check(ok_resp(r) and int(val("SELECT tabla_estado_registro_id FROM gestion__productos_proveedores WHERE producto_proveedor_id = %d" % int(pp))) == 2,
          'quitar el proveedor (baja lógica)', err(r))
    check(val("SELECT proveedores_busqueda FROM gestion__productos WHERE producto_id = %d" % P) in (None, ''),
          'al quitarlo se limpia la búsqueda por código')
    r = pa(dict(accion='ficha_proveedor_guardar', producto_id=P, entidad_id=V, codigo_proveedor=cod, es_principal=1))
    check(ok_resp(r) and int((data(r) or {}).get('producto_proveedor_id') or 0) == int(pp), 'volver a vincularlo reactiva el mismo registro', err(r))
    r = pa(dict(accion='ficha_proveedor_principal', producto_id=P, producto_proveedor_id=pp))
    check(ok_resp(r), 'marcar como principal', err(r))
    # Limpieza (la copia es descartable, pero el caso se deja como lo encontró)
    sql("DELETE FROM gestion__productos_proveedores WHERE producto_proveedor_id = %d" % int(pp))
    sql("UPDATE gestion__productos SET proveedores_busqueda = NULL WHERE producto_id = %d" % P)
    sql("UPDATE conf__empresas SET usa_ficha_producto_completa = 0 WHERE empresa_id = %d" % E)

# ---------------------------------------------------------------------------------------------
# Paso 7-2: la solapa Precios usa listas_precios_productos_ajax.php del 5 tal como está.
def lpa(params, method='GET'):
    return ajax('listas_precios_productos_ajax.php', params, method, modulo='gestion_normal', empresa_id=E)


caso('Ficha completa: precios del producto (endpoint de Lista de Precios)')
if CTX.get('ok'):
    P = CTX['prod']
    listas = lpa(dict(accion='obtener_listas'))
    lista = next((int(l['lista_precio_id']) for l in (listas if isinstance(listas, list) else [])
                  if not int(val("SELECT COUNT(*) FROM gestion__listas_precios_productos WHERE lista_precio_id = %d AND producto_id = %d "
                                 "AND tabla_estado_registro_id = 1" % (int(l['lista_precio_id']), P)))), 0)
    check(lista > 0, 'hay una lista de la empresa sin precio para el producto', str(listas)[:200])
    if lista:
        r = lpa(dict(accion='agregar', lista_precio_id=lista, producto_id=P, precio_unitario=1234.5), 'POST')
        check(isinstance(r, dict) and r.get('resultado'), 'agregar el precio en la lista', str(r)[:200])
        r = lpa(dict(accion='obtener_precios_producto', producto_id=P))
        fila = next((x for x in (r if isinstance(r, list) else []) if int(x['lista_precio_id']) == lista), None)
        check(fila and abs(float(fila['precio_unitario']) - 1234.5) < 0.001, 'la solapa lo lista', str(r)[:200])
        if fila:
            lpp = int(fila['lista_precio_producto_id'])
            r = lpa(dict(accion='agregar', lista_precio_id=lista, producto_id=P, precio_unitario=10), 'POST')
            check(isinstance(r, dict) and not r.get('resultado'), 'el mismo producto dos veces en la lista se rechaza', str(r)[:200])
            r = lpa(dict(accion='editar', lista_precio_producto_id=lpp, lista_precio_id=lista, producto_id=P, precio_unitario=1500), 'POST')
            check(isinstance(r, dict) and r.get('resultado') and abs(float(val("SELECT precio_final FROM gestion__listas_precios_productos "
                  "WHERE lista_precio_producto_id = %d" % lpp)) - 1500) < 0.001, 'editar el precio', str(r)[:200])
            r = lpa(dict(accion='eliminar', lista_precio_producto_id=lpp))
            check(isinstance(r, dict) and r.get('resultado'), 'quitar el precio (baja lógica)', str(r)[:200])
            sql("DELETE FROM gestion__listas_precios_productos WHERE lista_precio_producto_id = %d" % lpp)


# ---------------------------------------------------------------------------------------------
# Paso 7-3: solapa Ubicaciones.
caso('Ficha completa: ubicaciones del producto')
if CTX.get('ok'):
    P = CTX['prod']
    sql("UPDATE conf__empresas SET usa_ficha_producto_completa = 1 WHERE empresa_id = %d" % E)
    r = pa(dict(accion='ficha_ubicaciones_opciones'), 'GET')
    sucs = (data(r) or {}).get('sucursales') or []
    suc = next((s for s in sucs if s.get('bocas')), None)
    check(ok_resp(r) and suc, 'sucursales del usuario con sus bocas', str(r)[:200])
    if suc:
        sufijo = 'SIM%d' % P
        base = dict(accion='ficha_ubicacion_crear', producto_id=P, sucursal_id=suc['sucursal_id'], boca_id=suc['bocas'][0]['boca_id'],
                    seccion=sufijo, estanteria='E1', estante='2', posicion='3', descripcion='Prueba')
        r = pa(dict(base, estante=''))
        check(not ok_resp(r) and 'Falta el estante' in err(r), 'falta un dato: se rechaza', err(r))
        r = pa(base)
        su = int((data(r) or {}).get('sucursal_ubicacion_id') or 0)
        pu = int((data(r) or {}).get('producto_ubicacion_id') or 0)
        check(ok_resp(r) and su and pu, 'crear la ubicación y asignarla', err(r))
        r = pa(base)
        check(not ok_resp(r) and 'ya existe' in err(r), 'la misma ubicación dos veces se rechaza', err(r))
        check(int(val("SELECT COUNT(*) FROM gestion__sucursales_ubicaciones WHERE seccion = '%s'" % sufijo)) == 1,
              'el rechazo no deja una ubicación a medias')
        r = pa(dict(accion='ficha_ubicaciones_listar', producto_id=P), 'GET')
        check(ok_resp(r) and [int(u['sucursal_ubicacion_id']) for u in data(r)] == [su], 'la solapa la lista', str(r)[:200])
        r = pa(dict(accion='ficha_ubicacion_asignar', producto_id=P, sucursal_ubicacion_id=su))
        check(not ok_resp(r) and 'ya está asignada' in err(r), 'asignarla dos veces se rechaza', err(r))
        ajena = int(val("SELECT sucursal_ubicacion_id FROM gestion__sucursales_ubicaciones WHERE empresa_id <> %d AND tabla_estado_registro_id = 1 LIMIT 1" % E) or 0)
        r = pa(dict(accion='ficha_ubicacion_asignar', producto_id=P, sucursal_ubicacion_id=ajena))
        check(not ok_resp(r), 'una ubicación de otra empresa se rechaza', err(r))
        r = pc(dict(accion='ficha_ubicaciones_listar', producto_id=P))
        check(ok_resp(r) and len(data(r) or []) == 1, 'la consulta ve las ubicaciones', err(r))
        r = pc(dict(accion='ficha_ubicacion_quitar', producto_id=P, producto_ubicacion_id=pu), 'POST')
        check(not ok_resp(r), 'la consulta no puede quitarlas', str(r)[:200])
        r = pa(dict(accion='ficha_ubicacion_quitar', producto_id=P, producto_ubicacion_id=pu))
        check(ok_resp(r) and int(val("SELECT tabla_estado_registro_id FROM gestion__productos_ubicaciones WHERE producto_ubicacion_id = %d" % pu)) == 2,
              'quitarla (baja lógica)', err(r))
        r = pa(dict(accion='ficha_ubicacion_asignar', producto_id=P, sucursal_ubicacion_id=su))
        check(ok_resp(r) and int((data(r) or {}).get('producto_ubicacion_id') or 0) == pu, 'volver a asignarla reactiva el registro', err(r))
        sql("DELETE FROM gestion__productos_ubicaciones WHERE sucursal_ubicacion_id = %d" % su)
        sql("DELETE FROM gestion__sucursales_ubicaciones WHERE sucursal_ubicacion_id = %d" % su)
    sql("UPDATE conf__empresas SET usa_ficha_producto_completa = 0 WHERE empresa_id = %d" % E)
