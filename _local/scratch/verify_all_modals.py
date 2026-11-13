from test_restructure import new_lines

modals = ['modalEntidad', 'modalHistorialFiscal', 'modalHistorialCliente', 'modalHistorialProveedor', 'modalSucursal']

for mod in modals:
    start = -1
    for i, l in enumerate(new_lines):
        if f'id="{mod}"' in l:
            start = i
            break
    if start == -1:
        print(f"Modal {mod} NOT FOUND!")
        continue
    depth = 0
    closed_at = -1
    for i in range(start, len(new_lines)):
        depth += new_lines[i].count('<div') - new_lines[i].count('</div')
        if depth == 0:
            closed_at = i
            break
    print(f"Modal {mod:25}: starts line {start+1}, closes line {closed_at+1}, depth={depth}")
