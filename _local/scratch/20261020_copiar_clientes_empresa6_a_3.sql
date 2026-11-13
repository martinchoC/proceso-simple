-- Copia los clientes (rol CLI) de la empresa 6 (Developsam SAS) a la empresa 3, solo base local.
-- Copia: entidad, rol CLI (cuenta remapeada por codigo), condicion de cliente vigente
-- (condicion de pago y lista por codigo) y sucursales de la entidad.
-- Se saltea un cliente si la empresa 3 ya tiene una entidad con ese CUIT.

SET @orig := 6;
SET @dest := 3;
SET @rol_cli := (SELECT rol_entidad_id FROM gestion__roles_entidades WHERE rol_entidad_codigo = 'CLI');

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_cli_orig;
CREATE TEMPORARY TABLE tmp_cli_orig AS
SELECT e.entidad_id, e.cuit
FROM gestion__entidades e
JOIN gestion__entidades_roles r ON r.entidad_id = e.entidad_id AND r.rol_entidad_id = @rol_cli
WHERE e.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__entidades x WHERE x.empresa_id = @dest AND x.cuit = e.cuit);

-- 1) Entidades
INSERT INTO gestion__entidades
  (empresa_id, entidad_nombre, entidad_fantasia, entidad_tipo_id, cuit, sitio_web, domicilio_legal,
   localidad_id, observaciones, fecha_alta, usuario_alta, tabla_estado_registro_id)
SELECT @dest, e.entidad_nombre, e.entidad_fantasia, e.entidad_tipo_id, e.cuit, e.sitio_web, e.domicilio_legal,
       e.localidad_id, e.observaciones, NOW(), e.usuario_alta, e.tabla_estado_registro_id
FROM gestion__entidades e
JOIN tmp_cli_orig t ON t.entidad_id = e.entidad_id;

DROP TEMPORARY TABLE IF EXISTS tmp_map_ent;
CREATE TEMPORARY TABLE tmp_map_ent AS
SELECT t.entidad_id AS orig_id, d.entidad_id AS dest_id
FROM tmp_cli_orig t
JOIN gestion__entidades d ON d.empresa_id = @dest AND d.cuit = t.cuit;

-- 2) Rol cliente (cuenta por codigo del plan de la empresa 3)
INSERT INTO gestion__entidades_roles (entidad_id, rol_entidad_id, cont_cuenta_id, f_alta, f_baja, tabla_estado_registro_id)
SELECT m.dest_id, r.rol_entidad_id,
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = r.cont_cuenta_id),
       r.f_alta, r.f_baja, r.tabla_estado_registro_id
FROM gestion__entidades_roles r
JOIN tmp_map_ent m ON m.orig_id = r.entidad_id
WHERE r.rol_entidad_id = @rol_cli;

-- 3) Condiciones de cliente (condicion de pago y lista por codigo; tipo de cliente es global)
INSERT INTO gestion__entidades_condiciones_clientes
  (entidad_id, condicion_pago_id, entidad_cliente_tipo_id, lista_precio_id, limite_credito,
   cliente_descuento_general, f_desde, f_hasta, tabla_estado_registro_id)
SELECT m.dest_id,
       (SELECT cd.condicion_pago_id FROM gestion__condiciones_pago co JOIN gestion__condiciones_pago cd ON cd.empresa_id = @dest AND cd.codigo = co.codigo WHERE co.condicion_pago_id = c.condicion_pago_id),
       c.entidad_cliente_tipo_id,
       (SELECT ld.lista_precio_id FROM gestion__listas_precios lo JOIN gestion__listas_precios ld ON ld.empresa_id = @dest AND ld.lista_precio_codigo = lo.lista_precio_codigo WHERE lo.lista_precio_id = c.lista_precio_id),
       c.limite_credito, c.cliente_descuento_general, c.f_desde, c.f_hasta, c.tabla_estado_registro_id
FROM gestion__entidades_condiciones_clientes c
JOIN tmp_map_ent m ON m.orig_id = c.entidad_id;

-- 4) Sucursales de la entidad
INSERT INTO gestion__entidades_sucursales
  (entidad_id, empresa_id, sucursal_numero, sucursal_nombre, sucursal_direccion, localidad_id,
   sucursal_telefono, sucursal_email, sucursal_contacto, tabla_estado_registro_id)
SELECT m.dest_id, @dest, s.sucursal_numero, s.sucursal_nombre, s.sucursal_direccion, s.localidad_id,
       s.sucursal_telefono, s.sucursal_email, s.sucursal_contacto, s.tabla_estado_registro_id
FROM gestion__entidades_sucursales s
JOIN tmp_map_ent m ON m.orig_id = s.entidad_id;

COMMIT;
