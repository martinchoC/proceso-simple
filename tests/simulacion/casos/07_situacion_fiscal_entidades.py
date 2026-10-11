# Situación fiscal (IVA + IIBB) cargada desde "Datos Principales" de entidades / clientes / proveedores:
# cada cambio respecto de la vigente cierra la vigente y abre una fila nueva (historial).
from simlib import *  # noqa: F401,F403

IVA = 'gestion__entidades_condiciones_fiscales'
IIBB = 'gestion__entidades_condiciones_iibb'

conds = [int(r['condicion_fiscal_id']) for r in sql(
    "SELECT condicion_fiscal_id FROM gestion__condiciones_fiscales WHERE tabla_estado_registro_id = 1 ORDER BY condicion_fiscal_id LIMIT 2")]
regs = [int(r['regimen_iibb_id']) for r in sql(
    "SELECT regimen_iibb_id FROM gestion__regimenes_iibb WHERE tabla_estado_registro_id = 1 ORDER BY regimen_iibb_id LIMIT 2")]
sede = int(val("SELECT MIN(jurisdiccion_sede_id) FROM gestion__iibb_jurisdicciones_sedes WHERE tabla_estado_registro_id = 1"))
hoy = val("SELECT CURDATE()")
hace10 = val("SELECT CURDATE() - INTERVAL 10 DAY")
hace3 = val("SELECT CURDATE() - INTERVAL 3 DAY")
hace4 = val("SELECT CURDATE() - INTERVAL 4 DAY")
hace5 = val("SELECT CURDATE() - INTERVAL 5 DAY")
manana = val("SELECT CURDATE() + INTERVAL 1 DAY")


def datos(nombre, **fiscal):
    d = {'entidad_nombre': nombre, 'entidad_fantasia': '', 'entidad_tipo_id': '', 'cuit': '', 'sitio_web': '',
         'domicilio_legal': 'SIM', 'localidad_id': '', 'es_proveedor': 0, 'es_cliente': 1, 'observaciones': 'SIM',
         'empresa_idx': 2, 'pagina_idx': 50}
    d.update(fiscal)
    return d


def filas(tabla, eid):
    return sql(f"SELECT * FROM {tabla} WHERE entidad_id = {eid} ORDER BY 1")


caso('Situación fiscal - alta de entidad con IVA e IIBB')
nombre = 'SIM FISCAL ' + hoy
r = ajax('entidades_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[0], regimen_iibb_id=regs[0],
                                          numero_inscripcion_iibb='901-123', jurisdiccion_sede_id=sede,
                                          fiscal_f_desde=hace10), accion='agregar_entidad'))
check(ok_resp(r), 'alta aceptada', err(r))
eid = int(r.get('entidad_id') or 0)
iva = filas(IVA, eid)
iibb = filas(IIBB, eid)
check(len(iva) == 1 and int(iva[0]['condicion_fiscal_id']) == conds[0] and iva[0]['f_desde'] == hace10 and iva[0]['f_hasta'] is None,
      'una condición IVA abierta desde la fecha indicada', iva)
check(len(iibb) == 1 and iibb[0]['numero_inscripcion'] == '901-123' and int(iibb[0]['jurisdiccion_sede_id']) == sede,
      'una condición IIBB con N° y sede', iibb)

caso('Situación fiscal - guardar sin cambios no genera historial')
r = ajax('entidades_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[0], regimen_iibb_id=regs[0],
                                          numero_inscripcion_iibb='901-123', jurisdiccion_sede_id=sede,
                                          fiscal_f_desde=hoy), accion='editar_entidad', entidad_id=eid))
check(ok_resp(r), 'edición aceptada', err(r))
check(len(filas(IVA, eid)) == 1 and len(filas(IIBB, eid)) == 1, 'sigue habiendo una fila por nivel',
      [len(filas(IVA, eid)), len(filas(IIBB, eid))])

caso('Situación fiscal - cambio con fecha: cierra la vigente y abre otra')
r = ajax('entidades_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[1], regimen_iibb_id=regs[0],
                                          numero_inscripcion_iibb='901-999', jurisdiccion_sede_id=sede,
                                          fiscal_f_desde=hace3), accion='editar_entidad', entidad_id=eid))
check(ok_resp(r), 'edición aceptada', err(r))
iva = filas(IVA, eid)
iibb = filas(IIBB, eid)
check(len(iva) == 2 and iva[0]['f_hasta'] == hace4 and iva[1]['f_desde'] == hace3 and iva[1]['f_hasta'] is None
      and int(iva[1]['condicion_fiscal_id']) == conds[1], 'IVA: anterior cerrada el día previo, nueva abierta', iva)
check(len(iibb) == 2 and iibb[0]['f_hasta'] == hace4 and iibb[1]['numero_inscripcion'] == '901-999',
      'IIBB: cambio de N° de inscripción versionado', iibb)

caso('Situación fiscal - fecha anterior a la vigente se rechaza y no guarda nada')
r = ajax('entidades_ajax.php', dict(datos(nombre + ' X', condicion_fiscal_id=conds[0], regimen_iibb_id=regs[0],
                                          numero_inscripcion_iibb='901-999', jurisdiccion_sede_id=sede,
                                          fiscal_f_desde=hace5), accion='editar_entidad', entidad_id=eid))
check(not ok_resp(r) and 'Historial' in err(r), 'rechazada con mensaje que deriva al Historial', err(r))
check(val(f"SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = {eid}") == nombre,
      'el nombre no cambió (rollback de toda la edición)', val(f"SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = {eid}"))
check(len(filas(IVA, eid)) == 2, 'no se agregó fila IVA', filas(IVA, eid))

caso('Situación fiscal - fecha futura y N° sin régimen se rechazan')
r = ajax('entidades_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[0], fiscal_f_desde=manana),
                                    accion='editar_entidad', entidad_id=eid))
check(not ok_resp(r), 'fecha futura rechazada', err(r))
r = ajax('entidades_ajax.php', dict(datos(nombre, regimen_iibb_id='', numero_inscripcion_iibb='123'),
                                    accion='editar_entidad', entidad_id=eid))
check(not ok_resp(r) and 'régimen' in err(r), 'N° de inscripción sin régimen rechazado', err(r))

caso('Situación fiscal - corrección del mismo día deja la anterior Inactiva (clientes_ajax)')
r = ajax('clientes_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[1], regimen_iibb_id=regs[-1],
                                         numero_inscripcion_iibb='901-999', jurisdiccion_sede_id=sede,
                                         fiscal_f_desde=hoy), accion='editar_entidad', entidad_id=eid))
check(ok_resp(r), 'edición por clientes_ajax aceptada', err(r))
r = ajax('clientes_ajax.php', dict(datos(nombre, condicion_fiscal_id=conds[1], regimen_iibb_id=regs[0],
                                         numero_inscripcion_iibb='901-999', jurisdiccion_sede_id=sede,
                                         fiscal_f_desde=hoy), accion='editar_entidad', entidad_id=eid))
check(ok_resp(r), 'segunda edición del día aceptada', err(r))
iibb = filas(IIBB, eid)
activas_abiertas = [f for f in iibb if f['tabla_estado_registro_id'] == '1' and f['f_hasta'] is None]
check(len(activas_abiertas) == 1 and int(activas_abiertas[0]['regimen_iibb_id']) == regs[0],
      'una sola IIBB activa abierta, con el último régimen', iibb)
if len(regs) > 1:
    check(any(f['tabla_estado_registro_id'] == '2' and f['f_desde'] == hoy for f in iibb),
          'la corrección del día quedó como Inactiva en el historial', iibb)
vig = ajax('clientes_ajax.php', {'accion': 'obtener_condicion_iibb_vigente', 'entidad_id': eid, 'empresa_idx': 2}, 'GET')
check(isinstance(vig, dict) and int(vig.get('regimen_iibb_id') or 0) == regs[0], 'la vigente que lee el formulario es la última', vig)
