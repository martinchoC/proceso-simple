import subprocess

def run_sql(query):
    cmd = ["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "gestion_multipyme", "-e", query]
    p = subprocess.run(cmd, capture_output=True, text=True)
    return p.stdout

tables = [
    'conf__bancos',
    'gestion__categorias_gasto',
    'gestion__cheques',
    'gestion__cheques_operaciones',
    'gestion__cheques_operaciones_items',
    'gestion__cobranzas',
    'gestion__cobranzas_medios',
    'gestion__comprobantes_tipos_cancelaciones',
    'gestion__empresas_cuentas_conceptos',
    'gestion__medios_pago',
    'gestion__movimientos_caja',
    'gestion__obligaciones_impositivas',
    'gestion__pagos',
    'gestion__pagos_impuestos',
    'gestion__pagos_impuestos_medios',
    'gestion__pagos_medios',
    'gestion__pagos_retenciones',
    'gestion__rendiciones',
    'gestion__rendiciones_anticipos',
    'gestion__rendiciones_items',
    'gestion__rendiciones_reembolsos',
    'gestion__rendiciones_reembolsos_medios',
    'gestion__retenciones_numeradores',
    'gestion__tesoreria_cuentas',
    'gestion__tesoreria_movimientos',
    'gestion__transferencias',
    'gestion__ventas_cobranzas'
]

print("=== ROW COUNTS IN LIVE MARIADB ===")
for t in tables:
    res = run_sql(f"SELECT COUNT(*) FROM `{t}`;")
    count = res.splitlines()[-1].strip() if len(res.splitlines()) > 1 else 'ERR'
    print(f"{t}: {count} rows")
