with open('docs/gestion/temp_v3_11_text.txt', encoding='utf-8') as f:
    text = f.read()

paras = text.split('\n')
print(f"Total lines: {len(paras)}")

# Look for sections 9, 8, 4, 3, 2, 1
keywords = ['9. Pendientes', '9.1 Antes', '9.2 Pendiente de código', '9.3 Puntos abiertos', '9.4 Casos de prueba', 'Anexo']
for kw in keywords:
    for i, p in enumerate(paras):
        if kw.lower() in p.lower():
            print(f"Found '{kw}' at line {i}: {p[:120]}")
