-- Copia cuentas de tesoreria y medios de pago de la empresa 6 (Developsam SAS) a la empresa 3, solo base local.
-- Cuentas de tesoreria con saldo 0 (el saldo lo mantiene el libro de movimientos), sin numero / CBU / alias.
-- Cuenta contable por codigo del plan de la 3, moneda por codigo, sucursal = la unica de la 3 (o NULL).
-- Medios de pago por codigo; la cuenta fija de tesoreria se remapea por codigo de cuenta de tesoreria.

SET @orig := 6;
SET @dest := 3;
SET @suc_dest := (SELECT MIN(sucursal_id) FROM gestion__sucursales WHERE empresa_id = @dest AND tabla_estado_registro_id = 1);

START TRANSACTION;

-- 1) Cuentas de tesoreria (por codigo)
INSERT INTO gestion__tesoreria_cuentas
  (empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, moneda_id, banco_id, banco_cuenta_tipo,
   numero, cbu, alias, cont_cuenta_id, saldo_actual, permite_saldo_negativo, orden, tabla_estado_registro_id, usuario_id)
SELECT @dest,
       IF(t.sucursal_id IS NULL, NULL, @suc_dest),
       t.cuenta_tipo, t.codigo, t.tesoreria_cuenta,
       (SELECT md.moneda_id FROM gestion__monedas mo JOIN gestion__monedas md ON md.empresa_id = @dest AND md.codigo = mo.codigo WHERE mo.moneda_id = t.moneda_id),
       t.banco_id, t.banco_cuenta_tipo, NULL, NULL, NULL,
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = t.cont_cuenta_id),
       0, t.permite_saldo_negativo, t.orden, t.tabla_estado_registro_id, t.usuario_id
FROM gestion__tesoreria_cuentas t
WHERE t.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__tesoreria_cuentas x WHERE x.empresa_id = @dest AND x.codigo = t.codigo);

-- 2) Medios de pago (por codigo)
INSERT INTO gestion__medios_pago
  (empresa_id, codigo, medio_pago, medio_pago_tipo, tesoreria_cuenta_tipo, tesoreria_cuenta_id, cont_cuenta_id,
   impuesto_tipo_id, requiere_detalle, aplica_cobro, aplica_pago, orden, tabla_estado_registro_id, usuario_id)
SELECT @dest, m.codigo, m.medio_pago, m.medio_pago_tipo, m.tesoreria_cuenta_tipo,
       (SELECT td.tesoreria_cuenta_id FROM gestion__tesoreria_cuentas tor JOIN gestion__tesoreria_cuentas td ON td.empresa_id = @dest AND td.codigo = tor.codigo WHERE tor.tesoreria_cuenta_id = m.tesoreria_cuenta_id),
       (SELECT d.cont_cuenta_id FROM gestion__cont_cuentas s JOIN gestion__cont_cuentas d ON d.empresa_id = @dest AND d.codigo = s.codigo WHERE s.cont_cuenta_id = m.cont_cuenta_id),
       m.impuesto_tipo_id, m.requiere_detalle, m.aplica_cobro, m.aplica_pago, m.orden, m.tabla_estado_registro_id, m.usuario_id
FROM gestion__medios_pago m
WHERE m.empresa_id = @orig
  AND NOT EXISTS (SELECT 1 FROM gestion__medios_pago x WHERE x.empresa_id = @dest AND x.codigo = m.codigo);

COMMIT;
