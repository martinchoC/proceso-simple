import pymysql

try:
    conn = pymysql.connect(host='localhost', user='root', password='', database='gestion_multipyme')
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    
    print('=== SUCURSALES ===')
    cursor.execute('SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales')
    for r in cursor.fetchall():
        print(r)
        
    print('=== BOCAS ===')
    cursor.execute('SELECT boca_id, empresa_id, sucursal_id, boca_nombre, codigo, es_deposito, es_principal FROM gestion__bocas')
    for r in cursor.fetchall():
        print(r)
        
    print('=== PUNTOS DE VENTA ===')
    cursor.execute('SELECT punto_venta_id, empresa_id, sucursal_id, boca_id, nombre, codigo_fiscal FROM gestion__puntos_venta')
    for r in cursor.fetchall():
        print(r)

    print('=== CUENTAS TESORERIA ===')
    cursor.execute("SELECT tesoreria_cuenta_id, empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, saldo_actual FROM gestion__tesoreria_cuentas")
    for r in cursor.fetchall():
        print(r)
        
except Exception as e:
    print('Error:', e)
