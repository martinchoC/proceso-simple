# -*- coding: utf-8 -*-
"""
Script que genera 'Especificacion_Tecnica_Sistema_Gestion.docx'
"""

import os
import sys
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn

def build_word_specification():
    doc = docx.Document()
    
    # Configurar márgenes de página (2.5 cm)
    for section in doc.sections:
        section.top_margin = Inches(0.98)
        section.bottom_margin = Inches(0.98)
        section.left_margin = Inches(0.98)
        section.right_margin = Inches(0.98)
        section.different_first_page_header_footer = True
        
        # Pie de página para páginas siguientes
        footer = section.footer
        f_p = footer.paragraphs[0]
        f_p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        f_run = f_p.add_run("Sistema de Gestión ERP · Especificación Técnica de Arquitectura y Negocio")
        f_run.font.name = "Arial"
        f_run.font.size = Pt(8.5)
        f_run.font.color.rgb = RGBColor(120, 120, 120)

    # Configuración de estilos base
    normal_style = doc.styles['Normal']
    normal_style.font.name = 'Arial'
    normal_style.font.size = Pt(10)
    normal_style.font.color.rgb = RGBColor(40, 40, 40)
    normal_style.paragraph_format.line_spacing = 1.15
    normal_style.paragraph_format.space_after = Pt(4)

    def set_cell_background(cell, hex_color):
        shading_elm = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>')
        cell._tc.get_or_add_tcPr().append(shading_elm)

    def set_cell_margins(cell, top=100, bottom=100, left=140, right=140):
        tcPr = cell._tc.get_or_add_tcPr()
        tcMar = OxmlElement('w:tcMar')
        for m, val in [('top', top), ('bottom', bottom), ('left', left), ('right', right)]:
            node = OxmlElement(f'w:{m}')
            node.set(qn('w:w'), str(val))
            node.set(qn('w:type'), 'dxa')
            tcMar.append(node)
        tcPr.append(tcMar)

    def set_table_borders(table, color="D0D7DE", sz="4", val="single"):
        tblPr = table._tbl.tblPr
        borders = parse_xml(
            f'<w:tblBorders {nsdecls("w")}>'
            f'  <w:top w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
            f'  <w:bottom w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
            f'  <w:insideH w:val="{val}" w:sz="{sz}" w:space="0" w:color="{color}"/>'
            f'  <w:insideV w:val="none"/>'
            f'  <w:left w:val="none"/>'
            f'  <w:right w:val="none"/>'
            f'</w:tblBorders>'
        )
        tblPr.append(borders)

    def add_h1(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(22)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(15)
        run.font.bold = True
        run.font.color.rgb = RGBColor(27, 54, 93) # Deep Navy
        return p

    def add_h2(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(14)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(12.5)
        run.font.bold = True
        run.font.color.rgb = RGBColor(44, 62, 80) # Slate
        return p

    def add_h3(text):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(10)
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(10.5)
        run.font.bold = True
        run.font.color.rgb = RGBColor(0, 102, 153) # Accent Blue
        return p

    def add_p(text, bold_prefix=None, space_after=4):
        p = doc.add_paragraph()
        p.paragraph_format.space_before = Pt(0)
        p.paragraph_format.space_after = Pt(space_after)
        p.paragraph_format.line_spacing = 1.15
        if bold_prefix:
            r_pre = p.add_run(bold_prefix)
            r_pre.font.name = "Arial"
            r_pre.font.size = Pt(10)
            r_pre.font.bold = True
            r_pre.font.color.rgb = RGBColor(27, 54, 93)
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(10)
        run.font.color.rgb = RGBColor(40, 40, 40)
        return p

    def add_bullet(text, bold_prefix=None):
        p = doc.add_paragraph(style='List Bullet')
        p.paragraph_format.space_before = Pt(1)
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.line_spacing = 1.15
        if bold_prefix:
            r_pre = p.add_run(bold_prefix)
            r_pre.font.name = "Arial"
            r_pre.font.size = Pt(10)
            r_pre.font.bold = True
            r_pre.font.color.rgb = RGBColor(27, 54, 93)
        run = p.add_run(text)
        run.font.name = "Arial"
        run.font.size = Pt(10)
        run.font.color.rgb = RGBColor(40, 40, 40)
        return p

    def add_callout(text_list, title="NOTA TÉCNICA", callout_type="info"):
        table = doc.add_table(rows=1, cols=1)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        table.autofit = False
        
        cell = table.cell(0, 0)
        cell.width = Inches(6.5)
        
        if callout_type == "warning":
            border_color = "E67E22"
            bg_color = "FDF7E7"
            icon = "[ADVERTENCIA]"
            title_color = RGBColor(180, 80, 10)
        elif callout_type == "critical":
            border_color = "C0392B"
            bg_color = "FDEDEC"
            icon = "[CRÍTICO]"
            title_color = RGBColor(192, 57, 43)
        elif callout_type == "rule":
            border_color = "27AE60"
            bg_color = "EAFAF1"
            icon = "[REGLA DE ORO]"
            title_color = RGBColor(39, 174, 96)
        else: # info
            border_color = "1B365D"
            bg_color = "F0F4F8"
            icon = "[INFORMACIÓN]"
            title_color = RGBColor(27, 54, 93)
            
        set_cell_background(cell, bg_color)
        set_cell_margins(cell, top=120, bottom=120, left=180, right=180)
        
        tcPr = cell._tc.get_or_add_tcPr()
        borders = parse_xml(
            f'<w:tcBorders {nsdecls("w")}>'
            f'  <w:top w:val="none"/>'
            f'  <w:bottom w:val="none"/>'
            f'  <w:left w:val="single" w:sz="24" w:space="0" w:color="{border_color}"/>'
            f'  <w:right w:val="none"/>'
            f'</w:tcBorders>'
        )
        tcPr.append(borders)
        
        p = cell.paragraphs[0]
        p.paragraph_format.space_before = Pt(2)
        p.paragraph_format.space_after = Pt(4)
        run_t = p.add_run(f"{icon} {title}")
        run_t.font.name = "Arial"
        run_t.font.size = Pt(10)
        run_t.font.bold = True
        run_t.font.color.rgb = title_color
        
        for t in text_list:
            p2 = cell.add_paragraph()
            p2.paragraph_format.space_before = Pt(1)
            p2.paragraph_format.space_after = Pt(2)
            run_txt = p2.add_run(t)
            run_txt.font.name = "Arial"
            run_txt.font.size = Pt(9.5)
            run_txt.font.color.rgb = RGBColor(40, 40, 40)
            
        doc.add_paragraph().paragraph_format.space_after = Pt(4)

    def add_code_block(code_str, caption=None):
        if caption:
            p_cap = doc.add_paragraph()
            p_cap.paragraph_format.space_before = Pt(6)
            p_cap.paragraph_format.space_after = Pt(2)
            r_cap = p_cap.add_run(f"Patrón de Código: {caption}")
            r_cap.font.name = "Arial"
            r_cap.font.size = Pt(9)
            r_cap.font.bold = True
            r_cap.font.color.rgb = RGBColor(80, 80, 80)
            
        table = doc.add_table(rows=1, cols=1)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        table.autofit = False
        
        cell = table.cell(0, 0)
        cell.width = Inches(6.5)
        set_cell_background(cell, "F4F6F9")
        set_cell_margins(cell, top=100, bottom=100, left=150, right=150)
        
        tcPr = cell._tc.get_or_add_tcPr()
        borders = parse_xml(
            f'<w:tcBorders {nsdecls("w")}>'
            f'  <w:top w:val="single" w:sz="4" w:space="0" w:color="D0D7DE"/>'
            f'  <w:bottom w:val="single" w:sz="4" w:space="0" w:color="D0D7DE"/>'
            f'  <w:left w:val="single" w:sz="12" w:space="0" w:color="1B365D"/>'
            f'  <w:right w:val="single" w:sz="4" w:space="0" w:color="D0D7DE"/>'
            f'</w:tcBorders>'
        )
        tcPr.append(borders)
        
        lines = code_str.strip().split("\n")
        for i, line in enumerate(lines):
            p = cell.paragraphs[0] if i == 0 else cell.add_paragraph()
            p.paragraph_format.space_before = Pt(0)
            p.paragraph_format.space_after = Pt(1)
            r = p.add_run(line)
            r.font.name = "Consolas"
            r.font.size = Pt(8.5)
            r.font.color.rgb = RGBColor(30, 30, 30)
            
        doc.add_paragraph().paragraph_format.space_after = Pt(4)

    def format_styled_table(col_widths, headers, data_rows):
        table = doc.add_table(rows=1, cols=len(headers))
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        set_table_borders(table)
        
        hdr_cells = table.rows[0].cells
        for i, h in enumerate(headers):
            hdr_cells[i].text = h
            hdr_cells[i].width = col_widths[i]
            set_cell_background(hdr_cells[i], "1B365D")
            set_cell_margins(hdr_cells[i], top=100, bottom=100, left=120, right=120)
            for p in hdr_cells[i].paragraphs:
                p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                for r in p.runs:
                    r.font.name = "Arial"
                    r.font.size = Pt(9)
                    r.font.bold = True
                    r.font.color.rgb = RGBColor(255, 255, 255)
                    
        for row_idx, row_data in enumerate(data_rows):
            row_cells = table.add_row().cells
            bg_col = "F8F9FA" if row_idx % 2 == 1 else "FFFFFF"
            for i, val in enumerate(row_data):
                row_cells[i].text = str(val)
                row_cells[i].width = col_widths[i]
                set_cell_background(row_cells[i], bg_col)
                set_cell_margins(row_cells[i], top=70, bottom=70, left=120, right=120)
                for p in row_cells[i].paragraphs:
                    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                    for r in p.runs:
                        r.font.name = "Arial"
                        r.font.size = Pt(8.5)
                        r.font.color.rgb = RGBColor(40, 40, 40)
        doc.add_paragraph().paragraph_format.space_after = Pt(4)
        return table

    # -------------------------------------------------------------
    # PORTADA
    # -------------------------------------------------------------
    p_title_space = doc.add_paragraph()
    p_title_space.paragraph_format.space_before = Pt(40)
    
    p_pre = doc.add_paragraph()
    r_pre = p_pre.add_run("DEVELOP SAM SAS · ARQUITECTURA DE SOFTWARE")
    r_pre.font.name = "Arial"
    r_pre.font.size = Pt(11)
    r_pre.font.bold = True
    r_pre.font.color.rgb = RGBColor(0, 102, 153)
    p_pre.paragraph_format.space_after = Pt(10)
    
    p_main_title = doc.add_paragraph()
    r_mt = p_main_title.add_run("ESPECIFICACIÓN TÉCNICA DEL SISTEMA DE GESTIÓN (ERP)")
    r_mt.font.name = "Arial"
    r_mt.font.size = Pt(24)
    r_mt.font.bold = True
    r_mt.font.color.rgb = RGBColor(27, 54, 93)
    p_main_title.paragraph_format.space_after = Pt(8)
    
    p_sub = doc.add_paragraph()
    r_sub = p_sub.add_run("Arquitectura Multi-tenant, Motor de Estados, Circuito de Comprobantes, Módulos Operativos y Manual de Ingeniería para Google Gemini")
    r_sub.font.name = "Arial"
    r_sub.font.size = Pt(13)
    r_sub.font.color.rgb = RGBColor(100, 110, 120)
    p_sub.paragraph_format.space_after = Pt(36)
    
    # Tabla de Metadatos de Portada
    meta_table = doc.add_table(rows=6, cols=2)
    meta_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    meta_widths = [Inches(2.0), Inches(4.5)]
    meta_data = [
        ("Proyecto / Repositorio:", "casalucho (gestion_multipyme)"),
        ("Stack Tecnológico:", "PHP 8.x puro (sin framework) + MySQL/MariaDB + AdminLTE 4 / Bootstrap 5 + jQuery"),
        ("Arquitectura Core:", "Multi-tenant estricto (empresa_id) + Motor de Estados dinámico + Comprobantes universales"),
        ("Versión del Sistema:", "2.6 (Fase Tesorería, Facturación ARCA y Remitos Pendientes activa)"),
        ("Fecha de Documento:", "Octubre 2026"),
        ("Propósito del Documento:", "Especificación canónica para ingeniería interna y contexto exhaustivo para Google Gemini")
    ]
    for idx, (label, val) in enumerate(meta_data):
        row = meta_table.rows[idx]
        row.cells[0].text = label
        row.cells[1].text = val
        row.cells[0].width = meta_widths[0]
        row.cells[1].width = meta_widths[1]
        set_cell_background(row.cells[0], "F0F4F8")
        set_cell_background(row.cells[1], "FAFAFA")
        set_cell_margins(row.cells[0], 60, 60, 100, 100)
        set_cell_margins(row.cells[1], 60, 60, 100, 100)
        for p in row.cells[0].paragraphs:
            for r in p.runs:
                r.font.name = "Arial"
                r.font.size = Pt(9)
                r.font.bold = True
                r.font.color.rgb = RGBColor(27, 54, 93)
        for p in row.cells[1].paragraphs:
            for r in p.runs:
                r.font.name = "Arial"
                r.font.size = Pt(9)
                r.font.color.rgb = RGBColor(50, 50, 50)
    set_table_borders(meta_table)

    doc.add_page_break()

    # -------------------------------------------------------------
    # ÍNDICE GENERAL
    # -------------------------------------------------------------
    add_h1("ÍNDICE GENERAL")
    
    index_items = [
        ("1. Visión General, Objetivos y Modelo Multi-Tenant", "Propósito del ERP, contexto PyME argentino, gobernanza multi-tenant y aislamiento estricto por empresa."),
        ("2. Arquitectura del Sistema y Patrón de las 4 Capas", "Stack tecnológico sin frameworks, estructura de 4 archivos por pantalla, dispatcher AJAX y ciclo de vida de peticiones."),
        ("3. El Motor Central de Estados y Botones Dinámicos", "La columna tabla_estado_registro_id, catálogo conf__tablas, conf__estados_registros y motor dinámico de transiciones."),
        ("4. El Motor Universal de Comprobantes e Imputaciones", "gestion__comprobantes, syncComprobante(), imputaciones M:N, prevención de concurrencia y saldos."),
        ("5. Subsistemas Operativos Implementados (Lo que está en el sistema)", "Especificación detallada de Ventas, Compras, Tesorería, Contabilidad, Facturación Electrónica ARCA, Stock, Precios y Maestros."),
        ("6. Diccionario de Datos y Mapeo Exhaustivo de Tablas", "Catálogo integral clasificado de las 133 tablas gestion__ y 37 tablas conf__."),
        ("7. Manual de Ingeniería y Reglas de Oro para Google Gemini", "Prompt Estándar ABM, estructura de archivos obligatoria, anti-patrones prohibidos y checklist de validación.")
    ]
    
    for title, desc in index_items:
        add_p(f"{desc}", bold_prefix=f"{title}: ")
    
    doc.add_paragraph().paragraph_format.space_after = Pt(12)

    # -------------------------------------------------------------
    # CAPÍTULO 1
    # -------------------------------------------------------------
    add_h1("1. VISIÓN GENERAL, OBJETIVOS Y MODELO MULTI-TENANT")
    
    add_p(
        "El presente sistema, denominado 'gestion_multipyme' (desarrollado por Developsam SAS), es una plataforma ERP "
        "integral de gestión empresarial orientada a Pequeñas y Medianas Empresas (PyMEs) argentinas con actividades "
        "comerciales, productivas, de distribución y de servicios. Su arquitectura está optimizada para la legislación fiscal "
        "local (AFIP/ARCA, Ingresos Brutos, Convenio Multilateral), el control financiero riguroso de cobranzas, pagos y valores, "
        "y la trazabilidad operativa extremo a extremo (compras, inventario, ventas y contabilidad en línea)."
    )
    
    add_h2("1.1. Principios de Diseño del Sistema")
    add_bullet(" Simplicidad y Cero Dependencias: El backend está programado en PHP 8.x puro, sin frameworks pesados (sin Laravel, Symfony ni CodeIgniter) y sin gestores de dependencias como Composer ni empaquetadores como Webpack/Vite. El código se ejecuta directamente sobre Apache/XAMPP.", "1. Cero Bloatware:")
    add_bullet(" Rendimiento Nativo y Prepared Statements: No existe ORM (Object-Relational Mapping). Todas las consultas son SQL directo ejecutadas mediante la extensión mysqli utilizando estrictamente sentencias preparadas con enlace de tipos (bind_param) para garantizar velocidad máxima y protección total contra inyección SQL.", "2. SQL Directo y Seguro:")
    add_bullet(" Interfaz Dinámica Single-Page (AJAX): El usuario opera la totalidad de las pantallas mediante peticiones asíncronas jQuery AJAX sin recargas completas de navegador, interactuando con modales Bootstrap 5, tablas DataTables avanzadas y alertas SweetAlert2.", "3. Experiencia Fluida:")
    add_bullet(" Motor de Estados Basado en Metadatos: Las acciones, botones y transiciones operativas no están codificadas en duro en el HTML/JS, sino gobernadas por un motor declarativo en base de datos.", "4. Arquitectura Dirigida por Estados:")

    add_h2("1.2. Gobernanza de Datos Multi-Tenant Estricta")
    add_p(
        "El sistema es nativamente multi-tenant compartido en base de datos. Múltiples empresas coexisten en la misma base "
        "de datos física ('gestion_multipyme'). El aislamiento total entre clientes se logra mediante la presencia transversal "
        "de la columna 'empresa_id' (o 'empresa_idx' en parámetros internos) en prácticamente todas las tablas de negocio."
    )
    
    add_callout([
        "Regla Inquebrantable de Multi-Tenancy:",
        "1. Toda consulta SQL (SELECT, INSERT, UPDATE, DELETE) en cualquier archivo _model.php DEBE incluir obligatoriamente el filtro 'WHERE empresa_id = ?'.",
        "2. Omitir el filtro de empresa constituye una brecha crítica de seguridad y fuga de datos entre inquilinos.",
        "3. El backend resuelve la empresa activa en 'header1.php' a partir de $_GET['empresa_id'] (validando contra conf__empresas_perfiles y conf__usuarios_perfiles que el usuario autenticado tenga permiso asignado)."
    ], title="AISLAMIENTO MULTI-TENANT OBLIGATORIO", callout_type="critical")

    add_h2("1.3. Jerarquía de Seguridad y Perfiles")
    add_p(
        "El control de accesos se gestiona a través de una jerarquía de 4 niveles que permite override por empresa:",
        bold_prefix="Modelo de Seguridad: "
    )
    add_bullet("Empresas ──< Empresas_Modulos >── Módulos ──< Páginas (árbol con padre_id) ──< Páginas_Funciones (acciones)")
    add_bullet("Usuarios ──< Usuarios_Perfiles >── Perfiles ──< Perfiles_Funciones (permisos estándar por perfil)")
    add_bullet("Perfiles ──< Empresas_Perfiles ──< Empresas_Perfiles_Funciones (override específico por empresa y perfil)")
    
    add_p(
        "Esto garantiza que una empresa pueda restringir o habilitar funciones específicas a un perfil operativo sin "
        "afectar la configuración global del resto de los clientes del ERP."
    )

    # -------------------------------------------------------------
    # CAPÍTULO 2
    # -------------------------------------------------------------
    add_h1("2. ARQUITECTURA TÉCNICA Y PATRÓN DE LAS 4 CAPAS")
    
    add_p(
        "Cada funcionalidad o pantalla del sistema se construye siguiendo de manera rigurosa un patrón arquitectónico "
        "cuádruple compuesto por 4 archivos homónimos que delimitan claramente responsabilidades:"
    )

    format_styled_table(
        [Inches(1.8), Inches(1.2), Inches(3.5)],
        ["Archivo", "Capa", "Responsabilidad Principal"],
        [
            ("pagina.php", "Vista HTML", "Estructura visual, meta-tags, inclusión de header1.php/footer1.php, definición de $modudo_idx y $pagina_idx, markup de DataTables y modales."),
            ("pagina_ajax.php", "Controlador", "Dispatcher de peticiones HTTP (GET/POST). Evalúa el parámetro 'accion', invoca al modelo, gestiona transacciones y emite JSON."),
            ("pagina_model.php", "Lógica / SQL", "Acceso a datos exclusivo. Queries SQL preparadas (mysqli_prepare), cálculo numérico de impuestos/totales, lógica de negocio."),
            ("pagina.js", "Frontend JS", "Controlador de interfaz jQuery. Inicialización de DataTables, validaciones de formularios, llamadas AJAX, SweetAlert2 y modales.")
        ]
    )

    add_h2("2.1. El Typo Intencional '$modudo_idx'")
    add_callout([
        "En todo el sistema, en cada archivo 'pagina.php' y en 'templates/adminlte/header1.php', la variable de contexto de módulo se denomina intencionalmente '$modudo_idx' (con 'd', no con 'l').",
        "JAMÁS debe corregirse a '$modulo_idx' en archivos existentes ni generarse código nuevo con '$modulo_idx', ya que esto rompería la resolución del menú lateral y la inclusión de assets de AdminLTE."
    ], title="CONVENCIÓN CRÍTICA DE VARIABLE: $modudo_idx", callout_type="warning")

    add_h2("2.2. Convención de Respuestas JSON del Dispatcher AJAX")
    add_p(
        "Todo archivo '_ajax.php' debe establecer el encabezado 'Content-Type: application/json; charset=utf-8' y devolver "
        "un objeto estructurado estándar. Aunque en pantallas legacy coexiste el formato '{ resultado: bool }', para todo el "
        "sistema actual y código asistido por IA, el formato canónico es:"
    )
    
    add_code_block(
        "// Estructura Estándar de Respuesta JSON\n"
        "{\n"
        '    "success": true,               // boolean: true en éxito, false en error\n'
        '    "message": "Operación exitosa",// string: mensaje amigable para el usuario\n'
        '    "data": { ... },               // mixed: payload con registros, IDs creados, etc.\n'
        '    "error": null                  // string|null: detalle técnico en caso de fallo\n'
        "}",
        caption="Formato Canónico de Salida JSON"
    )

    add_h2("2.3. Configuración de Base de Datos y Conexión Activa")
    add_p(
        "La conexión oficial y vigente del sistema se realiza en cascada: 'config.php' incluye 'db.php', el cual lee "
        "el archivo de entorno '.env' situado en la raíz del proyecto. Las variables se definen como constantes PHP:",
        bold_prefix="Flujo de Conexión: "
    )
    add_bullet("IP_HOST: 'localhost'")
    add_bullet("DB_USER: 'root'")
    add_bullet("DB_PASS: '' (o contraseña del entorno)")
    add_bullet("DB_NAME: 'gestion_multipyme'")
    add_bullet("Charset: mysqli_set_charset($conn, 'utf8mb4')")
    add_bullet("Timezone: date_default_timezone_set('America/Argentina/Buenos_Aires') obligatorio para prevenir desfases de fecha en comprobantes fiscales.")

    add_h2("2.4. Código Muerto / Legacy que NO debe utilizarse como plantilla")
    add_p("Al desarrollar o interactuar con Gemini, deben evitarse expresamente los siguientes archivos legacy:")
    add_bullet("modules/conf/backup/** (copias de seguridad históricas obsoletas).")
    add_bullet("Archivos '* copy.php' (ej. paginas_controlador copy.php).")
    add_bullet("modules/gestion/layout/* y core/plantilla.php (layout AdminLTE 3 / Bootstrap 4 descontinuado).")
    add_bullet("boostrap.php en la raíz (apunta a config/app.php inexistente).")
    add_bullet("starter.php y test.php en la raíz.")

    # -------------------------------------------------------------
    # CAPÍTULO 3
    # -------------------------------------------------------------
    add_h1("3. EL MOTOR CENTRAL DE ESTADOS Y BOTONES DINÁMICOS")
    
    add_p(
        "El motor de estados es la pieza arquitectónica más importante del ERP. Determina el ciclo de vida de los registros, "
        "la auditoría, la validez fiscal y qué acciones puede ejecutar un usuario en cada momento."
    )
    
    add_h2("3.1. Estructura de Metadatos de Estados")
    add_p(
        "Cada tabla de negocio del ERP posee una columna denominada 'tabla_estado_registro_id' (NO 'estado_id' directo). "
        "Esta columna es una FK hacia la tabla de metadatos 'conf__tablas_estados_registros':"
    )
    add_bullet("conf__tablas: Contiene la lista de tablas físicas del sistema (ej. 'gestion__ventas_facturas', 'gestion__cobranzas') y su 'tabla_tipo_id'.", "1. ")
    add_bullet("conf__estados_registros: Catálogo maestro de estados universales: Activo (1), Inactivo (2), Borrador (3), Pendiente de Aprobación (4), Confirmado (5), Eliminado/Anulado (6), etc.", "2. ")
    add_bullet("conf__tablas_estados_registros: Tabla intermedia que asocia una tabla física con los estados que le son válidos, asignando un ID único de estado por tabla.", "3. ")
    add_bullet("conf__paginas_funciones: Define para cada pantalla qué funciones/botones existen, sus atributos visuales (texto, icono FontAwesome, clase CSS) y su transición de estados (tabla_estado_registro_origen_id -> tabla_estado_registro_destino_id).", "4. ")

    add_h2("3.2. Clasificación de Tablas por Complejidad ('conf__tablas_tipos')")
    format_styled_table(
        [Inches(1.5), Inches(1.5), Inches(3.5)],
        ["Tipo de Tabla", "Complejidad", "Comportamiento y Ciclo de Vida"],
        [
            ("1. Diccionario", "CRUD Simple", "Tablas maestras de configuración. Solo alternan entre Activo (1) e Inactivo (2). Ejemplos: marcas, modelos, depositos, categorias_gasto."),
            ("2. Proceso Corto", "Workflow Básico", "Procesos con pocos pasos de transición: Borrador -> Confirmado -> Anulado. Ejemplos: stock_ajustes, transferencias."),
            ("3. Proceso Largo", "Workflow Extendido", "Ciclos complejos con múltiples estados intermedios y subdetalles: Borrador -> Pendiente -> Confirmado -> En Tránsito -> Recibido/Pagado. Ejemplos: ordenes_compra, facturas_proveedores, ventas_facturas, cobranzas.")
        ]
    )

    add_h2("3.3. Cálculo Dinámico de Botones y Despacho en JavaScript")
    add_p(
        "Los botones de acción NO se hardcodean en el frontend. El flujo de renderizado y ejecución opera así:"
    )
    add_bullet("Al listar registros en la DataTable, el backend consulta 'conf__paginas_funciones' filtrando por 'pagina_id' y buscando aquellas funciones cuyo 'tabla_estado_registro_origen_id' coincida exactamente con el 'tabla_estado_registro_id' de la fila.")
    add_bullet("Las funciones autorizadas para el perfil del usuario se devuelven en el JSON y se dibujan como botones con atributos 'data-accion', 'data-id' y 'data-origen'.")
    add_bullet("Al pulsar un botón, el script 'pagina.js' ejecuta la función 'ejecutarAccion(id, accion_js)' que dispara una petición AJAX al endpoint genérico 'ejecutar_accion' del archivo '_ajax.php'.")
    add_bullet("El backend valida la transición, actualiza 'tabla_estado_registro_id = destino_id', ejecuta efectos secundarios (actualización de stock, imputaciones, asientos contables) y devuelve el nuevo estado.")

    add_callout([
        "Regla de Oro para Creación de Pantallas:",
        "Antes de programar el código de una pantalla con workflow de estados, deben registrarse en la base de datos:",
        "  1. La fila correspondiente en 'conf__tablas' (con su 'tabla_tipo_id').",
        "  2. Sus estados específicos en 'conf__tablas_estados_registros'.",
        "  3. Sus botones y transiciones en 'conf__paginas_funciones'.",
        "Si estos registros no existen, el motor de botones no tendrá ninguna acción que renderizar en la grilla."
    ], title="SECUENCIA DE CONFIGURACIÓN PREVIA", callout_type="rule")

    # -------------------------------------------------------------
    # CAPÍTULO 4
    # -------------------------------------------------------------
    add_h1("4. EL MOTOR UNIVERSAL DE COMPROBANTES E IMPUTACIONES")
    
    add_p(
        "El núcleo contable y financiero del ERP se fundamenta en dos tablas universales que desacoplan las operaciones "
        "comerciales de los saldos deudores y acreedores: 'gestion__comprobantes' y 'gestion__comprobantes_imputaciones'."
    )
    
    add_h2("4.1. Comprobante Universal: 'gestion__comprobantes'")
    add_p(
        "Todo documento mercantil del sistema (Factura de Venta, Factura de Compra, Remito, Recibo de Cobranza, Orden de Pago, "
        "Liquidación Impositiva, Rendición de Gastos, Movimiento de Caja) tiene un registro gemelo en 'gestion__comprobantes'.",
        bold_prefix="Principio de Unicidad: "
    )
    add_bullet("Vínculo Polimórfico 1:1: Se vincula con la tabla operativa mediante 'tabla_origen_id' + 'registro_origen_id' bajo el índice único 'idx_origen'.")
    add_bullet("Patrón 'syncComprobante()': El comprobante se crea desde el estado Borrador (no al confirmar). Esto permite asociar imputaciones y borradores de pago desde la carga inicial. Su estado en 'gestion__comprobantes' espeja el estado de la tabla operativa.")
    add_bullet("Campos Clave: 'comprobante_id' (BIGINT UNSIGNED), 'empresa_id', 'comprobante_tipo_id', 'punto_venta_id', 'comprobante_nro', 'entidad_id', 'f_emision', 'f_vencimiento', 'f_contabilidad', 'moneda_id', 'tipo_cambio', 'importe_total', 'importe_pendiente'.")

    add_h2("4.2. Motor de Imputaciones: 'gestion__comprobantes_imputaciones'")
    add_p(
        "La cancelación de saldos entre documentos se realiza mediante relaciones M:N en 'gestion__comprobantes_imputaciones':"
    )
    add_bullet("Comprobante Cancelador (Origen): El que entrega el saldo cancelatorio (ej. Recibo de Cobranza RCX, Orden de Pago OP, Nota de Crédito NC).")
    add_bullet("Comprobante Cancelable (Destino): El documento cuya deuda se extingue (ej. Factura de Venta V_FA, Factura de Compra F_FA, Liquidación de Impuesto I_LIQ).")
    add_bullet("Regla de Signos: Los importes imputados ('importe_imputado') se almacenan SIEMPRE en valor positivo. El saldo se calcula estrictamente como: Saldo = Importe Total − SUM(Importes Imputados Vigentes). El signo del comprobante solo afecta a la cuenta corriente y a la contabilidad.")

    add_h2("4.3. Concurrencia Crítica y Control de Sobre-Imputación (Locks)")
    add_p(
        "En un entorno multiusuario (múltiples sucursales cobrando facturas en simultáneo), existe riesgo de sobre-imputación "
        "si dos cobradores imputan la misma factura al mismo tiempo. El sistema implementa la siguiente regla transaccional:"
    )
    
    add_code_block(
        "// Protocolo de Bloqueo Concurrente al Confirmar Imputaciones\n"
        "mysqli_begin_transaction($conn);\n"
        "try {\n"
        "    // 1. Bloquear comprobantes destino en orden ascendente para evitar Deadlocks\n"
        "    $sqlLock = 'SELECT comprobante_id, importe_total, importe_pendiente '\n"
        "             . 'FROM gestion__comprobantes '\n"
        "             . 'WHERE comprobante_id IN (?, ?) '\n"
        "             . 'ORDER BY comprobante_id ASC FOR UPDATE';\n"
        "    // 2. Recalcular saldo real: Saldo = importe_total - SUM(imputaciones_activas)\n"
        "    // 3. Validar: if ($monto_a_imputar > $saldo_real) throw new Exception('Sobre-imputación');\n"
        "    // 4. Insertar en gestion__comprobantes_imputaciones con estado CONFIRMADO\n"
        "    // 5. Actualizar caché atómica: UPDATE gestion__comprobantes SET importe_pendiente = ...\n"
        "    mysqli_commit($conn);\n"
        "} catch (Exception $e) {\n"
        "    mysqli_rollback($conn);\n"
        "}",
        caption="Bloqueo Pesimista (FOR UPDATE) en Imputaciones"
    )

    add_callout([
        "Manejo de 'importe_pendiente' como Caché:",
        "La columna 'gestion__comprobantes.importe_pendiente' funciona como una caché de alta velocidad para consultas rápidas.",
        "Sin embargo, la función 'syncComprobante()' NO DEBE sobreescribir 'importe_pendiente' con 'importe_total' cuando el comprobante ya posee imputaciones registradas. La verdad definitiva del saldo proviene de la conciliación: 'importe_total - SUM(imputaciones)'."
    ], title="PROTECCIÓN DE SALDO CACHÉ", callout_type="warning")

    # -------------------------------------------------------------
    # CAPÍTULO 5
    # -------------------------------------------------------------
    add_h1("5. SUBSISTEMAS OPERATIVOS IMPLEMENTADOS")
    
    add_p(
        "A continuación se detalla cada uno de los subsistemas y módulos de negocio activos en la carpeta 'modules/gestion' "
        "y sus tablas asociadas en la base de datos 'gestion_multipyme':"
    )

    # 5.1 Ventas
    add_h2("5.1. Circuito Comercial de Ventas y Facturación")
    add_p("El flujo comercial de ventas cubre desde la preventa hasta la cobranza definitiva:")
    add_bullet("Presupuestos ('ventas_presupuestos.php' / 'gestion__ventas_presupuestos'): Cotizaciones comerciales emitidas a prospectos y clientes con detalle de artículos, alícuotas de IVA y validez de oferta.")
    add_bullet("Pedidos de Clientes ('ventas_pedidos.php', 'ventas_pedidos_gestion.php'): Notas de venta aprobadas. El archivo 'ventas_pedidos_gestion.php' proporciona una interfaz de carga ultrarrápida (Point-Of-Sale / Preventa) con selección táctil de artículos y clientes.")
    add_bullet("Remitos de Entrega ('ventas_remitos.php' / 'gestion__ventas_remitos'): Comprobantes de despacho físico. Impactan directamente el stock ('impacta_stock = 1'), descontando cantidades del inventario del depósito correspondiente pero sin generar deuda exigible hasta su facturación.")
    add_bullet("Cola de Facturación / Remitos Pendientes ('ventas_remitos_pendientes_facturacion.php'): Pantalla operativa crítica que lista los remitos despachados que aún no fueron facturados o tienen facturación parcial. Permite seleccionar remitos y consolidarlos en una única Factura de Venta.")
    add_bullet("Facturación de Ventas ('ventas_facturas.php' / 'gestion__ventas_facturas'): Generación de comprobantes fiscales de venta (Facturas A, B, C, MiPyME, Notas de Crédito, Notas de Débito). Conexión en línea con ARCA/AFIP para la obtención de CAE. Impacta en Cuenta Corriente ('impacta_ctacte = 1') y genera asiento contable automático.")
    add_bullet("Maestro de Clientes ('clientes.php' / 'gestion__entidades'): Gestión de clientes con CUIT, condición frente al IVA (Responsable Inscripto, Monotributista, Exento, Consumidor Final), lista de precios asignada, límite de crédito, condición de pago por defecto y padrón de alícuotas de Ingresos Brutos (ARBA, AGIP).")

    # 5.2 Compras
    add_h2("5.2. Circuito de Aprovisionamiento y Compras")
    add_bullet("Pedidos de Compra ('compras_pedidos.php' / 'gestion__compras_pedidos'): Requerimientos internos de mercadería o insumos de las distintas sucursales.")
    add_bullet("Órdenes de Compra ('ordenes_compra.php' / 'gestion__ordenes_compra'): Compromiso formal con proveedores con precios pactados, fecha de entrega y condiciones comerciales.")
    add_bullet("Facturas de Proveedores ('facturas_proveedores.php' / 'gestion__facturas_proveedores'): Uno de los modelos más robustos del sistema (~2800 líneas de código). Registra comprobantes fiscales de compra recibidos, desglosa alícuotas de IVA (21%, 10.5%, 27%), percepciones de IVA, percepciones de IIBB provinciales, impuestos internos y conceptos no gravados. Al confirmarse, actualiza automáticamente el stock físico en depósito, recalcula el costo del producto (reposición y ponderado) y genera el asiento contable en el Libro Diario.")
    add_bullet("Remitos de Proveedores ('proveedores_remitos.php'): Ingreso físico de mercadería remitida por proveedores pendiente de comprobante fiscal definitivo.")
    add_bullet("Maestro de Proveedores y Listas de Precios ('proveedores.php', 'proveedor_precios.php'): Registro de proveedores, condiciones de compra, catálogo de códigos de artículo de proveedor y actualización masiva de listas de precios de compra mediante planillas Excel o interfaces interactivas.")

    # 5.3 Tesorería
    add_h2("5.3. Circuito de Tesorería, Fondos y Valores")
    add_p(
        "El módulo de Tesorería fue diseñado bajo el principio de caja centralizada y trazabilidad inmutable de fondos:"
    )
    add_bullet("Cobranzas ('cobranzas.php' / 'gestion__cobranzas'): Emisión de Recibos de Cobranza (tipo RCX, código 24). Permite cancelar múltiples facturas pendientes del cliente imputando fondos recibidos en diversas modalidades: Efectivo, Cheques de Terceros (en cartera), Transferencias bancarias y Retenciones impositivas sufridas (IIBB, Ganancias, IVA).")
    add_bullet("Órdenes de Pago ('pagos.php' / 'gestion__pagos'): Emisión de Órdenes de Pago a Proveedores (tipo OP, código 26). Imputa facturas de compra pendientes y registra la salida de fondos: Efectivo de caja, Cheques propios emitidos, Cheques de terceros endosados, Transferencias bancarias y Emisión de Retenciones impositivas practicadas con numeradores oficiales ('gestion__retenciones_numeradores').")
    add_bullet("Cartera de Cheques ('cheques_cartera.php' / 'gestion__cheques'): Gestión integral de cheques físicos y ECHEQs (propios y de terceros). Control del ciclo de vida completo: En Cartera -> Depositado -> Debitado -> Endosado -> Rechazado -> Devuelto. Registro de banco emisor, cuenta libradora, CUIT de librador, fecha de cobro y trazabilidad de operaciones bancarias ('gestion__cheques_operaciones').")
    add_bullet("Cuentas de Tesorería Unificadas ('gestion__tesoreria_cuentas'): Modela en una estructura homogénea Cajas de efectivo, Cuentas Bancarias corrientes y en pesos/dólares, Valores en Cartera y Cupones de Tarjetas de crédito/débito.")
    add_bullet("Libro Inmutable de Tesorería ('tesoreria_movimientos.php' / 'gestion__tesoreria_movimientos'): Toda entrada o salida monetaria genera un registro inmutable. Las correcciones o anulaciones no eliminan filas, sino que insertan contramovimientos de reversa vinculados mediante 'reversa_de_movimiento_id'.")
    add_bullet("Obligaciones y Rendiciones ('obligaciones_rendiciones.php'): Liquidación y pago de obligaciones impositivas y previsionales (I_LIQ, I_PAG), rendiciones de fondos fijos / caja chica con comprobantes anexos (RG_REND), solicitud de anticipos a empleados (RG_ANT) y reembolsos de gastos (RG_REEM).")

    # 5.4 Contabilidad
    add_h2("5.4. Contabilidad General Integrada")
    add_bullet("Plan de Cuentas ('plan_cuentas.php' / 'gestion__cont_cuentas'): Árbol jerárquico contable con clasificación por ramas (Activo, Pasivo, Patrimonio Neto, Ingresos, Egresos), niveles de agrupación y marcas de imputabilidad.")
    add_bullet("Libro Diario / Asientos ('cont_asientos.php' / 'gestion__cont_asientos'): Registro de asientos contables partida doble (Debe / Haber). Soporta asientos manuales y asientos automáticos generados por el ERP con origen 'AUTO' (asientos de compra generados por 'facturas_proveedores', asientos de venta generados por 'ventas_facturas', asientos de cobranza y pago).")
    add_bullet("Libro Mayor ('cont__mayores.php'): Consulta en tiempo real de movimientos y saldos acumulados deudores/acreedores por cuenta contable entre rangos de fechas.")
    add_bullet("Balance de Sumas y Saldos ('cont__balances.php'): Reporte de balance de comprobación para control contable y cierre de ejercicio.")

    # 5.5 ARCA / AFIP
    add_h2("5.5. Facturación Electrónica e Integraciones Fiscales (ARCA / AFIP)")
    add_p(
        "El ERP cuenta con un framework desacoplado de integraciones en 'modules/gestion/arca_integraciones.php' "
        "y sus modelos asociados, preparado para interactuar con los Web Services de la Agencia de Recaudación y Control "
        "Aduanero (ARCA, ex-AFIP):"
    )
    add_bullet("conf__integraciones y conf__integraciones_ambientes: Separa la definición del servicio externo de sus ambientes (Homologación / Testing y Producción).")
    add_bullet("Autenticación WSAA ('gestion__integraciones_autenticaciones'): Gestiona el certificado digital X.509 (.crt) y la clave privada (.key). Solicita el Ticket de Acceso (Access Ticket: Token y Sign) y lo almacena en base de datos reutilizándolo en cada petición hasta su expiración (evitando llamadas redundantes).")
    add_bullet("Facturación WSFE: Emisión de comprobantes fiscales electrónicos mediante el método 'FECAESolicitar'. Calcula bases imponibles por alícuota de IVA, envía el lote a ARCA y almacena el Código de Autorización Electrónico (CAE), la fecha de vencimiento del CAE y el número oficial asignado.")
    add_bullet("Auditoría Histórica ('gestion__integraciones_resultados'): Almacena el log completo de cada request y response (XML/JSON), código de resultado, observaciones y errores de AFIP para diagnóstico y trazabilidad legal.")

    # 5.6 Stock y Logística
    add_h2("5.6. Stock, Depósitos y Logística")
    add_bullet("Estructura Espacial: Sucursales ('sucursales.php') -> Bocas ('bocas.php') -> Depósitos y Ubicaciones físicas ('sucursales_ubicaciones.php').")
    add_bullet("Ajustes de Inventario ('stock_ajustes.php' / 'gestion__stock_ajustes'): Altas, bajas y recuentos físicos con motivo de ajuste (roturas, inventario inicial, mermas).")
    add_bullet("Movimientos de Stock ('gestion__stock_movimientos'): Historial de movimientos disparados por remitos de venta, facturas de compra y transferencias entre depósitos.")

    # 5.7 Precios y Costos
    add_h2("5.7. Precios, Costos y Reglas Comerciales")
    add_bullet("Listas de Precios ('listas_precios.php', 'listas_precios_productos.php'): Definición de listas (Mayorista, Minorista, Distribuidor) con cálculo de margen sobre costo o valores fijos.")
    add_bullet("Costos de Productos ('productos_costos.php', 'productos_costos_ajustes.php'): Determinación de costo de reposición, costo estándar y costo ponderado. Permite ajustes masivos de costos por porcentaje o por proveedor.")

    # 5.8 Maestros e Impuestos
    add_h2("5.8. Maestros Generales, Impuestos y Jurisdicciones")
    add_bullet("Entidades Comerciales ('entidades.php'): Modelo unificado de personas físicas y jurídicas con asignación de roles múltiples (Cliente, Proveedor, Empleado, Organismo de Recaudación).")
    add_bullet("Impuestos y Alícuotas ('iva_alicuotas.php', 'impuestos_tipos.php', 'jurisdicciones.php'): Configuración de tasas de IVA (0%, 2.5%, 5%, 10.5%, 21%, 27%), jurisdicciones provinciales de Ingresos Brutos y reglas fiscales por empresa ('empresas_impuestos_config.php').")

    # -------------------------------------------------------------
    # CAPÍTULO 6
    # -------------------------------------------------------------
    add_h1("6. DICCIONARIO DE DATOS Y MAPEO EXHAUSTIVO DE TABLAS")
    
    add_p(
        "La base de datos 'gestion_multipyme' cuenta con 250 tablas físicas en total, distribuidas en: 133 tablas del módulo "
        "de gestión ('gestion__'), 37 tablas del motor de configuración y seguridad ('conf__'), 69 tablas de matriz legal ('ml__') "
        "y 11 tablas auxiliares. A continuación se presenta el catálogo estructurado de las tablas principales del Sistema de Gestión:"
    )

    add_h2("6.1. Tablas Core de Gestión ('gestion__') Agrupadas por Subsistema")

    subsystems = [
        ("Ventas y Facturación", [
            ("gestion__ventas_presupuestos", "Cabecera de cotizaciones/presupuestos a clientes."),
            ("gestion__ventas_presupuestos_detalles", "Líneas de artículos, precios e IVA del presupuesto."),
            ("gestion__ventas_pedidos", "Cabecera de pedidos comerciales de venta."),
            ("gestion__ventas_pedidos_detalles", "Líneas de pedido de venta con cantidades y descuentos."),
            ("gestion__ventas_remitos", "Cabecera de remitos de entrega física de mercadería."),
            ("gestion__ventas_remitos_detalles", "Líneas remitidas con cantidades entregadas y lote."),
            ("gestion__ventas_facturas", "Cabecera de facturas, notas de débito y crédito de venta (CAE, PV, Nro)."),
            ("gestion__ventas_facturas_detalles", "Detalle facturado con alícuotas de IVA y subtotales.")
        ]),
        ("Compras y Proveedores", [
            ("gestion__compras_pedidos", "Requerimientos internos de compra."),
            ("gestion__compras_pedidos_detalles", "Artículos y cantidades requeridas por sucursal."),
            ("gestion__ordenes_compra", "Órdenes de compra autorizadas enviadas a proveedores."),
            ("gestion__ordenes_compra_detalle", "Líneas de la orden de compra con precios pactados."),
            ("gestion__facturas_proveedores", "Facturas y notas recibidas de proveedores con CAE/CAI e impuestos."),
            ("gestion__facturas_proveedores_detalle", "Ítems de factura de compra con impacto contable y stock.")
        ]),
        ("Tesorería, Cobranzas y Pagos", [
            ("gestion__cobranzas", "Recibos de cobranza a clientes (RCX) con total e imputaciones."),
            ("gestion__cobranzas_medios", "Medios de cobro: efectivo, cheques, transferencias, retenciones."),
            ("gestion__pagos", "Órdenes de pago a proveedores (OP) con total cancelado."),
            ("gestion__pagos_medios", "Medios de pago salientes entregados al proveedor."),
            ("gestion__pagos_retenciones", "Retenciones practicadas de Ganancias, IIBB e IVA en la OP."),
            ("gestion__retenciones_numeradores", "Numeradores oficiales correlativos para certificados de retención."),
            ("gestion__cheques", "Padrón maestro de cheques físicos y ECHEQs propios y de terceros."),
            ("gestion__cheques_operaciones", "Historial de transacciones de cheques (depósito, débito, rechazo)."),
            ("gestion__tesoreria_cuentas", "Cuentas financieras unificadas (cajas, bancos, valores, cupones)."),
            ("gestion__tesoreria_movimientos", "Libro inmutable de movimientos de fondos y arqueos."),
            ("gestion__transferencias", "Transferencias entre cuentas de tesorería de la empresa."),
            ("gestion__rendiciones", "Rendición de fondos fijos y gastos de empleados (RG_REND)."),
            ("gestion__rendiciones_items", "Comprobantes y gastos detallados dentro de la rendición."),
            ("gestion__rendiciones_anticipos", "Anticipos de dinero entregados para rendición posterior (RG_ANT)."),
            ("gestion__rendiciones_reembolsos", "Reembolso final al empleado por gastos excedentes (RG_REEM).")
        ]),
        ("Comprobantes e Imputaciones", [
            ("gestion__comprobantes", "Tabla universal de documentos comerciales y financieros del ERP."),
            ("gestion__comprobantes_detalles", "Líneas unificadas de comprobantes para contabilidad."),
            ("gestion__comprobantes_imputaciones", "Cancelaciones cruzadas de comprobantes (origen/destino/importe)."),
            ("gestion__comprobantes_tipos", "Catálogo de tipos de comprobante (FA, FC, NC, ND, RCX, OP, etc.)."),
            ("gestion__comprobantes_grupos", "Agrupación principal de comprobantes (Ventas, Compras, Fondos)."),
            ("gestion__comprobantes_subgrupos", "Subagrupación operativa para parametrización impositiva."),
            ("gestion__comprobantes_numeradores", "Control de numeración correlativa por empresa, tipo y punto de venta."),
            ("gestion__comprobantes_fiscales", "Códigos fiscales oficiales según tablas de AFIP/ARCA.")
        ]),
        ("Contabilidad", [
            ("gestion__cont_cuentas", "Catálogo del plan de cuentas contables de la empresa."),
            ("gestion__cont_tipos_cuentas", "Naturaleza de cuentas: Activo, Pasivo, PN, Resultado Pos/Neg."),
            ("gestion__cont_asientos", "Cabecera del libro diario general de asientos contables."),
            ("gestion__cont_asientos_detalles", "Partidas individuales del asiento (cuenta, Debe, Haber, comprobante)."),
            ("gestion__cont_tipos_asientos", "Clasificación de asientos: Apertura, Operativo, Cierre, Ajuste."),
            ("gestion__cont_agrupaciones", "Rubros y agrupaciones para emisión de balances.")
        ]),
        ("Productos, Costos y Stock", [
            ("gestion__productos", "Catálogo maestro de productos y servicios de la empresa."),
            ("gestion__productos_categorias", "Categorías y subcategorías de productos."),
            ("gestion__marcas / gestion__modelos", "Marcas y líneas de productos."),
            ("gestion__productos_costos", "Costos vigentes de reposición y ponderados de cada artículo."),
            ("gestion__productos_costos_ajustes", "Lotes de actualización y ajuste de costos."),
            ("gestion__listas_precios", "Encabezado de listas de precios de venta."),
            ("gestion__listas_precios_productos", "Precios finales calculados por artículo y lista."),
            ("gestion__stock", "Existencias físicas actuales por artículo, depósito y ubicación."),
            ("gestion__stock_ajustes", "Comprobantes de ajuste físico de inventario.")
        ]),
        ("Entidades y Fiscal", [
            ("gestion__entidades", "Padrón unificado de Clientes, Proveedores y Empleados."),
            ("gestion__entidades_roles", "Asignación de roles comerciales a las entidades."),
            ("gestion__entidades_condiciones_fiscales", "Condición fiscal frente al IVA (RI, Monotributo, Exento)."),
            ("gestion__impuestos__iva_alicuotas", "Alícuotas de IVA vigentes (0, 10.5, 21, 27%)."),
            ("gestion__jurisdicciones", "Provincias y regímenes de Ingresos Brutos."),
            ("gestion__empresas_impuestos_config", "Parámetros de cálculo impositivo específico por empresa.")
        ]),
        ("Integraciones ARCA / AFIP", [
            ("gestion__integraciones_empresas", "Parámetros de certificados y CUIT de cada empresa."),
            ("gestion__integraciones_autenticaciones", "Almacenamiento de Access Tickets (Token y Sign) vigentes."),
            ("gestion__integraciones_resultados", "Bitácora histórica de solicitudes y respuestas de Web Services.")
        ])
    ]

    for subsys_name, t_list in subsystems:
        add_h3(f"Subsistema: {subsys_name}")
        format_styled_table(
            [Inches(2.5), Inches(4.0)],
            ["Nombre de Tabla", "Descripción y Rol en el ERP"],
            t_list
        )

    # 6.2 Tablas Conf
    add_h2("6.2. Tablas del Motor de Configuración y Seguridad ('conf__')")
    format_styled_table(
        [Inches(2.4), Inches(4.1)],
        ["Tabla de Configuración", "Función en la Arquitectura"],
        [
            ("conf__empresas", "Empresas clientes del sistema (inquilinos multi-tenant)."),
            ("conf__usuarios", "Cuentas de usuario con hash de contraseña bcrypt."),
            ("conf__perfiles", "Roles de acceso funcionales (Administrador, Ventas, Tesorería, etc.)."),
            ("conf__modulos", "Módulos globales del ERP (1=Conf, 2=Gestion, 3=Matriz Legal)."),
            ("conf__paginas", "Árbol de pantallas y menú lateral (padre_id, orden, url)."),
            ("conf__paginas_funciones", "Acciones de cada página y transiciones de estado (accion_js)."),
            ("conf__tablas", "Registro de tablas del sistema y su tipo (1=Diccionario, 2=Corto, 3=Largo)."),
            ("conf__estados_registros", "Catálogo unificado de estados (Activo, Inactivo, Borrador, Confirmado...)."),
            ("conf__tablas_estados_registros", "Mapeo de estados específicos por tabla de negocio."),
            ("conf__perfiles_funciones", "Matriz de funciones permitidas por perfil de usuario."),
            ("conf__empresas_perfiles_funciones", "Override de permisos de perfil para una empresa específica."),
            ("conf__integraciones", "Catálogo de proveedores de servicios externos (ej. ARCA, Interbanking)."),
            ("conf__integraciones_ambientes", "Ambientes de integración (Homologación / Producción).")
        ]
    )

    # -------------------------------------------------------------
    # CAPÍTULO 7
    # -------------------------------------------------------------
    add_h1("7. MANUAL DE INGENIERÍA Y REGLAS DE ORO PARA GOOGLE GEMINI")
    
    add_p(
        "Esta sección establece el protocolo estándar de prompting, convenciones de codificación y reglas inviolables "
        "que Google Gemini (o cualquier modelo de lenguaje de asistencia técnica) debe cumplir al generar, modificar "
        "o auditar código dentro de este repositorio."
    )

    add_h2("7.1. Protocolo para Implementar un Nuevo Módulo (Prompt Estándar ABM)")
    add_p(
        "Al solicitar a Gemini la creación de un nuevo módulo o pantalla, debe proveerse la plantilla de parámetros basada "
        "en el documento oficial 'prompt estandar ABM.docx':"
    )
    
    add_code_block(
        "### Parámetros de Entrada Requeridos:\n"
        "- Nombre del módulo: [nombre_modulo] (ej. 'ordenes_compra', 'remitos_entrega')\n"
        "- Tabla de negocio: gestion__[nombre_modulo]\n"
        "- Campo ID primario: [nombre_modulo]_id (ej. 'orden_compra_id')\n"
        "- tabla_id (de conf__tablas): [ID numérico]\n"
        "- pagina_id (de conf__paginas): [ID numérico]\n"
        "- empresa_idx: obtenido de sesión o GET (ej. 2)\n"
        "- Referencia de arquitectura: \n"
        "    * Si es Diccionario (CRUD simple): usar 'depositos' como plantilla.\n"
        "    * Si es Proceso Largo (Workflow/Detalle): usar 'facturas_proveedores' como plantilla.",
        caption="Plantilla de Prompt para Nuevas Pantallas"
    )

    add_h2("7.2. Estructura Exacta de los 4 Archivos a Generar")
    add_bullet("1. [modulo].php: Debe declarar '$modudo_idx' y '$pagina_idx'. Incluir 'header1.php' y 'footer1.php'. Maquetar card AdminLTE con DataTable, botones de exportación y modales Bootstrap 5 con IDs únicos ('modal_agregar', 'modal_editar', 'modal_detalle').", "Vista: ")
    add_bullet("2. [modulo]_ajax.php: Debe recibir 'accion' por GET/POST. Manejar obligatoriamente las acciones mínimas: 'listar', 'obtener_boton_agregar', 'ejecutar_accion' (cambio de estado), 'agregar', 'editar', 'obtener', 'obtener_estados'. Envolver operaciones de escritura en transacciones y responder JSON canónico.", "Dispatcher AJAX: ")
    add_bullet("3. [modulo]_model.php: Funciones puras con sentencias preparadas mysqli. Reciben siempre '$empresa_idx' o '$empresa_id' y lo aplican en la cláusula WHERE. Toda escritura realiza 'mysqli_begin_transaction', 'mysqli_stmt_execute' y 'mysqli_commit' con bloque try/catch que ejecuta 'mysqli_rollback'. Si es documento contable, debe invocar 'syncComprobante()'.", "Modelo SQL: ")
    add_bullet("4. [modulo].js: Inicializa DataTable con lenguaje en español, paginación, búsqueda AJAX, botones Excel/PDF. Vincula eventos 'click' a botones dinámicos usando delegación '$(document).on(\"click\", \".btn-accion\", ...)', solicita confirmación con SweetAlert2 ('Swal.fire') y recarga la tabla con 'tabla.ajax.reload(null, false)'.", "Controlador JS: ")

    add_h2("7.3. Los 10 Mandamientos de Desarrollo (Anti-patrones Prohibidos)")
    
    mandamientos = [
        ("1. NUNCA inventar frameworks ni composer", "No proponer código que dependa de Laravel, Eloquent, Doctrine, Composer o bundlers npm. Todo debe ser PHP nativo y librerías por CDN/assets locales."),
        ("2. NUNCA olvidar el filtro de empresa", "Toda query en _model.php DEBE filtrar por 'empresa_id = ?'. Omitirlo destruye el aislamiento multi-tenant."),
        ("3. NUNCA concatenar variables en el SQL", "Prohibido 'SELECT ... WHERE id = ' . $id. Usar SIEMPRE 'mysqli_prepare($conn, ...)' y 'mysqli_stmt_bind_param()'. Sin excepciones."),
        ("4. NUNCA usar 'estado_id' directo en tablas operativas", "La columna debe llamarse estrictamente 'tabla_estado_registro_id' y apuntar a 'conf__tablas_estados_registros'."),
        ("5. NUNCA corregir el typo '$modudo_idx'", "Mantener '$modudo_idx' con 'd' en todas las vistas 'pagina.php' y llamadas a header1.php."),
        ("6. NUNCA calcular saldos con signos invertidos", "En imputaciones, 'importe_imputado' es siempre positivo. Saldo = Total − SUM(Imputado). El signo del comprobante solo afecta a la cuenta corriente."),
        ("7. NUNCA omitir transacciones en operaciones de escritura", "Todo INSERT/UPDATE con impacto financiero, stock o comprobante debe envolverse en 'mysqli_begin_transaction' y bloque try/catch."),
        ("8. NUNCA alterar la caché 'importe_pendiente' a ciegas", "'syncComprobante()' no debe sobreescribir 'importe_pendiente' con el total si el comprobante ya posee imputaciones."),
        ("9. NUNCA usar 'var_dump' ni 'print_r' en producción", "Para depuración técnica, utilizar 'error_log()' con prefijos claros tipo '=== INICIO [funcion] ID: $id ==='."),
        ("10. NUNCA hardcodear botones de acción", "Los botones de fila deben ser generados dinámicamente según 'conf__paginas_funciones' y el estado del registro.")
    ]

    for num, desc in mandamientos:
        add_callout([desc], title=num, callout_type="critical" if "NUNCA" in num else "rule")

    add_h2("7.4. Checklist de Calidad antes de Entregar Código")
    add_bullet("¿Se utilizó el patrón de 4 archivos (vista, ajax, model, js)?")
    add_bullet("¿Todas las consultas SELECT/INSERT/UPDATE/DELETE tienen 'empresa_id = ?'?")
    add_bullet("¿Todos los parámetros dinámicos pasan por 'mysqli_stmt_bind_param'?")
    add_bullet("¿Las funciones de escritura están dentro de un bloque try/catch con commit y rollback?")
    add_bullet("¿El archivo AJAX devuelve 'Content-Type: application/json' y el formato canónico {success, data, message}?")
    add_bullet("¿Se registraron previamente las filas en 'conf__tablas', 'conf__tablas_estados_registros' y 'conf__paginas_funciones'?")
    add_bullet("¿Si es un documento financiero, se integra con 'syncComprobante()' y 'gestion__comprobantes'?")
    add_bullet("¿El script JS utiliza SweetAlert2 para mensajes y confirmaciones?")

    # Guardar documento
    output_filename = "Especificacion_Tecnica_Sistema_Gestion.docx"
    doc.save(output_filename)
    print(f"Documento generado exitosamente en: {output_filename}")
    
    # También copiarlo a docs/gestion/ para que quede en la documentación del módulo
    docs_copy_path = os.path.join("docs", "gestion", output_filename)
    doc.save(docs_copy_path)
    print(f"Copia de seguridad guardada en: {docs_copy_path}")

if __name__ == "__main__":
    build_word_specification()
