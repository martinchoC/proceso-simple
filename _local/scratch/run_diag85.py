import subprocess

sql = """
SET @empresa_id = 2;

-- a) importe_pendiente vs SUM de imputaciones vigentes
SELECT c.comprobante_id, ct.codigo, c.importe_total, c.importe_pendiente,
       c.importe_total - COALESCE(ap.aplicado, 0)                         AS pendiente_calculado,
       c.importe_pendiente - (c.importe_total - COALESCE(ap.aplicado, 0)) AS diferencia
  FROM gestion__comprobantes c
  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
  LEFT JOIN (
        SELECT x.comprobante_id, SUM(x.importe) AS aplicado
          FROM (SELECT ci.comp_destino_id AS comprobante_id, ci.importe_imputado AS importe,
                       ci.tabla_estado_registro_id
                  FROM gestion__comprobantes_imputaciones ci WHERE ci.empresa_id = @empresa_id
                UNION ALL
                SELECT ci.comp_origen_id, ci.importe_origen, ci.tabla_estado_registro_id
                  FROM gestion__comprobantes_imputaciones ci WHERE ci.empresa_id = @empresa_id) x
          JOIN conf__tablas_estados_registros teri
            ON teri.tabla_estado_registro_id = x.tabla_estado_registro_id
          JOIN conf__estados_registros eri ON eri.estado_registro_id = teri.estado_registro_id
         WHERE eri.codigo_estandar = 'CONFIRMADO'
         GROUP BY x.comprobante_id) ap ON ap.comprobante_id = c.comprobante_id
 WHERE c.empresa_id = @empresa_id
   AND c.comprobante_tipo_id IN (
         SELECT comprobante_tipo_origen_id  FROM gestion__comprobantes_tipos_cancelaciones
          WHERE empresa_id = @empresa_id
         UNION
         SELECT comprobante_tipo_destino_id FROM gestion__comprobantes_tipos_cancelaciones
          WHERE empresa_id = @empresa_id)
   AND c.importe_pendiente <> c.importe_total - COALESCE(ap.aplicado, 0);

-- b) saldo_actual de las cuentas de tesoreria vs su libro
SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.saldo_actual,
       COALESCE(SUM(CASE m.tipo_movimiento WHEN 'INGRESO' THEN m.importe
                                           ELSE -m.importe END), 0) AS saldo_calculado
  FROM gestion__tesoreria_cuentas tc
  LEFT JOIN gestion__tesoreria_movimientos m ON m.tesoreria_cuenta_id = tc.tesoreria_cuenta_id
 WHERE tc.empresa_id = @empresa_id
 GROUP BY tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.saldo_actual
HAVING tc.saldo_actual <> saldo_calculado;
"""

cmd = ["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "gestion_multipyme", "-e", sql]
p = subprocess.run(cmd, capture_output=True, text=True)
print("Diagnostic 8.5 output:")
print(p.stdout)
