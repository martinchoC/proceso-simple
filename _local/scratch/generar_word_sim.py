"""
Genera un archivo Word (.docx) válido con el registro de la simulación integral
en Empresa 1000, usando únicamente la librería estándar de Python (zipfile + xml).
"""
import json
import os
import zipfile
from xml.sax.saxutils import escape

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
JSON_PATH = os.path.join(BASE_DIR, 'scratch', 'sim_emp1000_resultado.json')
DOCX_PATH = os.path.join(BASE_DIR, 'docs', 'gestion', 'registro_simulacion_integral_emp1000.docx')


def w_p(texto, bold=False, size_pt=11, color=None, space_after=100, heading=0):
    if heading == 1:
        size_pt, bold, color, space_after = 16, True, '1F4E79', 140
    elif heading == 2:
        size_pt, bold, color, space_after = 13, True, '2E5B88', 120
    rpr = f'<w:sz w:val="{size_pt * 2}"/><w:szCs w:val="{size_pt * 2}"/>'
    if bold:
        rpr += '<w:b/><w:bCs/>'
    if color:
        rpr += f'<w:color w:val="{color}"/>'
    return (
        f'<w:p><w:pPr><w:spacing w:after="{space_after}"/></w:pPr>'
        f'<w:r><w:rPr>{rpr}</w:rPr><w:t xml:space="preserve">{escape(str(texto))}</w:t></w:r></w:p>'
    )


def w_tabla(filas, anchos=None):
    if not filas:
        return ''
    cols = len(filas[0])
    if not anchos:
        anchos = [int(9000 / cols)] * cols
    grid = ''.join(f'<w:gridCol w:w="{a}"/>' for a in anchos)
    trs = []
    for i, fila in enumerate(filas):
        tcs = []
        for j, celda in enumerate(fila):
            shd = '<w:shd w:val="clear" w:color="auto" w:fill="E9EFF5"/>' if i == 0 else ''
            bold = '<w:b/>' if i == 0 else ''
            tc = (
                f'<w:tc><w:tcPr><w:tcW w:w="{anchos[j]}" w:type="dxa"/>{shd}'
                f'<w:tcMar><w:top w:w="70" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/>'
                f'<w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tcMar></w:tcPr>'
                f'<w:p><w:pPr><w:spacing w:after="20"/></w:pPr>'
                f'<w:r><w:rPr>{bold}<w:sz w:val="19"/><w:szCs w:val="19"/></w:rPr>'
                f'<w:t xml:space="preserve">{escape(str(celda))}</w:t></w:r></w:p></w:tc>'
            )
            tcs.append(tc)
        trs.append('<w:tr>' + ''.join(tcs) + '</w:tr>')
    return (
        '<w:tbl><w:tblPr><w:tblW w:w="9000" w:type="dxa"/>'
        '<w:tblBorders>'
        '<w:top w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>'
        '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>'
        '<w:left w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>'
        '<w:right w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>'
        '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="D9D9D9"/>'
        '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="D9D9D9"/>'
        '</w:tblBorders></w:tblPr>'
        f'<w:tblGrid>{grid}</w:tblGrid>' + ''.join(trs) + '</w:tbl>'
    )


def construir_docx(reg):
    partes = []
    partes.append(w_p('REGISTRO DE SIMULACIÓN INTEGRAL — EMPRESA 1000', heading=1))
    partes.append(w_p(f"Empresa: {reg.get('empresa_id')} - {reg.get('empresa_nombre')} (módulo {reg.get('modulo')})", size_pt=10))
    partes.append(w_p(f"Fecha y hora de ejecución: {reg.get('fecha_ejecucion')}", size_pt=10))
    partes.append(w_p('Objetivo: validar de punta a punta el circuito de distribuidora (Compra por presentación -> Stock -> Carrito B2B -> App del Vendedor -> Distribución -> Remito -> Factura) dejando los registros persistidos en la base local para monitoreo.', space_after=140))

    # Maestros configurados
    partes.append(w_p('1. Maestros y permisos configurados', heading=2))
    m = reg.get('maestros', {})
    filas_m = [['Concepto', 'Valor / ID']]
    for k, v in m.items():
        filas_m.append([k, json.dumps(v, ensure_ascii=False) if isinstance(v, (list, dict)) else str(v)])
    partes.append(w_tabla(filas_m, [3500, 5500]))
    partes.append(w_p('', space_after=80))

    # Casos ejecutados
    partes.append(w_p('2. Casos integrales ejecutados', heading=2))
    for c in reg.get('casos', []):
        partes.append(w_p(f"Caso {c.get('numero')}: {c.get('titulo')}", bold=True, size_pt=11, color='1F4E79', space_after=60))
        filas_c = [['Dato', 'Valor registrado']]
        for k, v in (c.get('datos') or {}).items():
            if isinstance(v, list):
                txt = json.dumps(v, ensure_ascii=False)
            elif isinstance(v, dict):
                txt = json.dumps(v, ensure_ascii=False)
            else:
                txt = str(v)
            filas_c.append([k, txt])
        partes.append(w_tabla(filas_c, [2800, 6200]))
        partes.append(w_p('', space_after=80))

    # Stock comparativo
    partes.append(w_p('3. Evolución de Stock (Depósito Central - boca 305)', heading=2))
    ini = (reg.get('stock_snapshot') or {}).get('inicial', {})
    fin = (reg.get('stock_snapshot') or {}).get('final', {})
    filas_s = [['Producto', 'Stock Inicial (u)', 'Stock Final (u)', 'Variación Neta']]
    for prod in ini.keys():
        vi = float(ini.get(prod, 0))
        vf = float(fin.get(prod, 0))
        filas_s.append([prod, f'{vi:g}', f'{vf:g}', f'{vf - vi:+g}'])
    partes.append(w_tabla(filas_s, [3600, 1800, 1800, 1800]))
    partes.append(w_p('', space_after=80))

    # Validaciones / Checks
    partes.append(w_p('4. Verificaciones automáticas', heading=2))
    filas_chk = [['Etapa', 'Verificación', 'Resultado', 'Detalle']]
    for ch in reg.get('checks', []):
        filas_chk.append([
            ch.get('caso', ''),
            ch.get('chequeo', ''),
            'OK' if ch.get('ok') else 'FALLA',
            (ch.get('detalle') or '')[:180],
        ])
    partes.append(w_tabla(filas_chk, [2000, 3000, 900, 3100]))
    partes.append(w_p('', space_after=80))

    # Guía de monitoreo
    partes.append(w_p('5. Qué monitorear en las pantallas del sistema', heading=2))
    guia = [
        'Compras > Órdenes de Compra / Facturas de Proveedores: verificar la OC y FP vinculada con su presentación (Caja x 10 y Caja x 20) y conversión a unidades base.',
        'Stock > Consulta / Tablero de Stock: validar que Depósito Central (boca 305) refleja exactamente la suma de la compra y la resta del remito parcial.',
        'Ventas > Pedidos: revisar los dos pedidos generados con origen CARRITO (Almacén Don José) y APP (Kiosco y Polirrubro Central, vendedor Santiago Passoni).',
        'Distribución > Hojas de Ruta: revisar la hoja confirmada en Camion Rojo (AA890ZK) con ambos pedidos y su Lista de Carga consolidada.',
        'Ventas > Remitos y Facturas: revisar el remito parcial del pedido CARRITO y la factura asociada sin doble descuento de stock.',
    ]
    for g in guia:
        partes.append(w_p(f'• {g}', size_pt=10, space_after=60))

    doc_xml = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:body>' + ''.join(partes) + '<w:sectPr/></w:body></w:document>'
    )
    content_types = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        '<Default Extension="xml" ContentType="application/xml"/>'
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        '</Types>'
    )
    rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        '</Relationships>'
    )

    os.makedirs(os.path.dirname(DOCX_PATH), exist_ok=True)
    with zipfile.ZipFile(DOCX_PATH, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', content_types)
        z.writestr('_rels/.rels', rels)
        z.writestr('word/document.xml', doc_xml)
    return DOCX_PATH


if __name__ == '__main__':
    with open(JSON_PATH, 'r', encoding='utf-8') as f:
        reg = json.load(f)
    ruta = construir_docx(reg)
    print(f'Word generado: {ruta}')
