-- @migracion: particular (no se aplica a todas las bases: ver htdocs/proyectos)
-- =====================================================================================================
-- Cuentas de Tesorería: descubierto en "Banco Provincia CC $" de Developsam SAS (empresa 6)
-- -----------------------------------------------------------------------------------------------------
-- Gastos Bancarios no podía confirmar el resumen bancario (C_RB) porque la OP automática (DEB_CTA) deja
-- la cuenta en negativo y la cuenta no admitía descubierto ("Saldo insuficiente en 'Banco Provincia CC $'").
-- Es lo mismo que el switch "Admite saldo negativo (descubierto)" de Tesorería > Cuentas de Tesorería.
-- La cuenta se busca por empresa + código (no por id): en ps_gestion_developsam_produccion es la 25.
-- Idempotente. MariaDB 10.4 y MySQL 8.
-- =====================================================================================================

UPDATE gestion__tesoreria_cuentas
SET permite_saldo_negativo = 1, fecha_modificacion = NOW()
WHERE empresa_id = 6 AND codigo = 'PBSAS_CC_ARS' AND cuenta_tipo = 'BANCO';

-- Control (solo lectura): debe dar una fila con permite_saldo_negativo = 1
SELECT tesoreria_cuenta_id, empresa_id, codigo, tesoreria_cuenta, saldo_actual, permite_saldo_negativo, tabla_estado_registro_id
FROM gestion__tesoreria_cuentas
WHERE empresa_id = 6 AND cuenta_tipo = 'BANCO';
