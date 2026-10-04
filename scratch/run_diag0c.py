import subprocess

sql = """
SET @tes_empresa_id = 2;

SELECT v.bloque, v.clave, v.cont_codigo,
       CASE
           WHEN cc.cont_cuenta_id IS NULL THEN 'NO EXISTE en gestion__cont_cuentas'
           WHEN cc.es_imputable = 0       THEN 'CUENTA TITULO (no imputable)'
           ELSE 'INACTIVA'
       END AS problema
FROM (
              SELECT '1 tesoreria' AS bloque, 'CAJA_BB' AS clave, '1.1.01.01' AS cont_codigo
    UNION ALL SELECT '1 tesoreria', 'CAJA_MDP',        '1.1.01.01'
    UNION ALL SELECT '1 tesoreria', 'BNA_CC_ARS',      '1.1.01.10'
    UNION ALL SELECT '1 tesoreria', 'BPBA_CC_ARS',     '1.1.01.12'
    UNION ALL SELECT '1 tesoreria', 'GAL_CC_ARS',      '1.1.01.13'
    UNION ALL SELECT '1 tesoreria', 'VALORES_ARS',     '1.1.01.03'
    UNION ALL SELECT '1 tesoreria', 'CUPONES_ARS',     '1.1.02.06'
    UNION ALL SELECT '2 medios',    'RET_IVA',         '1.1.03.06'
    UNION ALL SELECT '2 medios',    'RET_GAN',         '1.1.03.07'
    UNION ALL SELECT '2 medios',    'RET_IIBB',        '1.1.03.08.01'
    UNION ALL SELECT '3 categorias', 'LIBRERIA',       '6.4.01'
    UNION ALL SELECT '3 categorias', 'REFRIGERIO',     '6.4.02'
    UNION ALL SELECT '3 categorias', 'VIATICOS',       '6.4.03'
    UNION ALL SELECT '3 categorias', 'MANTENIMIENTO',  '6.4.04'
    UNION ALL SELECT '3 categorias', 'CORREO',         '6.4.05'
    UNION ALL SELECT '3 categorias', 'PEAJES',         '6.4.06'
    UNION ALL SELECT '3 categorias', 'RODADOS',        '6.4.07'
    UNION ALL SELECT '6 conceptos',  'VALORES_A_PAGAR','2.1.01.03'
    UNION ALL SELECT '6 conceptos',  'EMPLEADOS',      '1.1.03.09'
) v
LEFT JOIN gestion__cont_cuentas cc
       ON cc.empresa_id = @tes_empresa_id
      AND cc.codigo     = v.cont_codigo
WHERE cc.cont_cuenta_id IS NULL
   OR cc.es_imputable   = 0
   OR cc.tabla_estado_registro_id != 1;
"""

cmd = ["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "gestion_multipyme", "-e", sql]
p = subprocess.run(cmd, capture_output=True, text=True)
print("Diagnostic 0c output:")
print(p.stdout)
if p.stderr:
    print("Stderr:", p.stderr)
