#!/usr/bin/env python3
"""
Generador de manuales PDF para Altamira ERP.

Produce tres manuales en storage/app/public/manuales/:
  - Manual_Configuracion.pdf
  - Manual_Personas.pdf
  - Manual_Inventario.pdf

Ejecutar desde la raiz del proyecto:
    python scripts/generar_manuales.py

Requiere: pip install reportlab
"""

import os
import sys
from pathlib import Path

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor, white, black
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT, TA_JUSTIFY
from reportlab.platypus import (
    BaseDocTemplate,
    PageTemplate,
    Frame,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    KeepTogether,
    PageBreak,
    NextPageTemplate,
)
from reportlab.platypus.flowables import Flowable
from reportlab.pdfgen.canvas import Canvas

# ---------------------------------------------------------------------------
# Constantes de diseno
# ---------------------------------------------------------------------------

PAGE_W, PAGE_H = A4  # 595.27, 841.89 pt
MARGIN_LR = 1.5 * cm  # 42.52 pt cada lado
MARGIN_TB = 1.8 * cm  # 51.02 pt arriba/abajo
AVAIL_W = PAGE_W - 2 * MARGIN_LR  # ~510.24 pt
AVAIL_H = PAGE_H - 2 * MARGIN_TB

GOLD = HexColor("#F59E0B")
DARK_NAVY = HexColor("#1E293B")
TABLE_HEADER_BG = HexColor("#334155")
ROW_ALT = HexColor("#F1F5F9")
NOTE_BG = HexColor("#FEFCE8")
WARNING_BG = HexColor("#FEF2F2")
WARNING_BORDER = HexColor("#DC2626")

FONT = "Helvetica"
FONT_BOLD = "Helvetica-Bold"
FONT_ITALIC = "Helvetica-Oblique"
FONT_BOLD_ITALIC = "Helvetica-BoldOblique"

# ---------------------------------------------------------------------------
# Estilos de parrafo
# ---------------------------------------------------------------------------

_base = getSampleStyleSheet()


def _make_styles():
    """Devuelve diccionario de ParagraphStyle reutilizables."""
    s = {}
    s["body"] = ParagraphStyle(
        "body",
        fontName=FONT,
        fontSize=10,
        leading=14,
        alignment=TA_JUSTIFY,
        spaceAfter=6,
        textColor=black,
    )
    s["body_white"] = ParagraphStyle(
        "body_white",
        parent=s["body"],
        textColor=white,
    )
    s["bold"] = ParagraphStyle(
        "bold",
        parent=s["body"],
        fontName=FONT_BOLD,
    )
    s["italic"] = ParagraphStyle(
        "italic",
        parent=s["body"],
        fontName=FONT_ITALIC,
    )
    s["small"] = ParagraphStyle(
        "small",
        parent=s["body"],
        fontSize=8,
        leading=10,
    )
    s["toc_item"] = ParagraphStyle(
        "toc_item",
        fontName=FONT,
        fontSize=10,
        leading=16,
        leftIndent=12,
        textColor=DARK_NAVY,
    )
    s["toc_title"] = ParagraphStyle(
        "toc_title",
        fontName=FONT_BOLD,
        fontSize=14,
        leading=18,
        textColor=DARK_NAVY,
        spaceAfter=10,
    )
    s["intro"] = ParagraphStyle(
        "intro",
        parent=s["body"],
        fontSize=10.5,
        leading=15,
        spaceAfter=8,
    )
    s["table_header"] = ParagraphStyle(
        "table_header",
        fontName=FONT_BOLD,
        fontSize=8.5,
        leading=11,
        textColor=white,
    )
    s["table_cell"] = ParagraphStyle(
        "table_cell",
        fontName=FONT,
        fontSize=8.5,
        leading=11,
        textColor=black,
    )
    s["table_cell_bold"] = ParagraphStyle(
        "table_cell_bold",
        fontName=FONT_BOLD,
        fontSize=8.5,
        leading=11,
        textColor=black,
    )
    s["note_text"] = ParagraphStyle(
        "note_text",
        fontName=FONT_ITALIC,
        fontSize=9,
        leading=12,
        textColor=HexColor("#92400E"),
    )
    s["warning_text"] = ParagraphStyle(
        "warning_text",
        fontName=FONT_ITALIC,
        fontSize=9,
        leading=12,
        textColor=HexColor("#991B1B"),
    )
    s["step_num"] = ParagraphStyle(
        "step_num",
        fontName=FONT_BOLD,
        fontSize=10,
        leading=13,
        alignment=TA_CENTER,
        textColor=DARK_NAVY,
    )
    s["step_desc"] = ParagraphStyle(
        "step_desc",
        fontName=FONT,
        fontSize=9.5,
        leading=13,
        textColor=black,
    )
    return s


STYLES = _make_styles()


# ---------------------------------------------------------------------------
# Flowables auxiliares
# ---------------------------------------------------------------------------


class _ColoredBlock(Flowable):
    """Rectangulo de color completo como fondo de seccion."""

    def __init__(self, width, height, color):
        super().__init__()
        self.width = width
        self.height = height
        self._color = color

    def draw(self):
        self.canv.setFillColor(self._color)
        self.canv.rect(0, 0, self.width, self.height, stroke=0, fill=1)


# ---------------------------------------------------------------------------
# Funciones generadoras de flowables
# ---------------------------------------------------------------------------


def make_section_header(text: str) -> list:
    """Encabezado de seccion: fondo oscuro, texto dorado, ancho completo."""
    style = ParagraphStyle(
        "sec_hdr",
        fontName=FONT_BOLD,
        fontSize=13,
        leading=17,
        textColor=GOLD,
    )
    para = Paragraph(text, style)
    t = Table(
        [[para]],
        colWidths=[AVAIL_W],
        rowHeights=[28],
    )
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), DARK_NAVY),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ]
        )
    )
    return [Spacer(1, 14), t, Spacer(1, 8)]


def make_subsection_header(text: str) -> list:
    """Subtitulo negro en negrita con subrayado dorado."""
    style = ParagraphStyle(
        "subsec_hdr",
        fontName=FONT_BOLD,
        fontSize=11,
        leading=14,
        textColor=black,
        spaceAfter=2,
    )
    para = Paragraph(text, style)
    line_table = Table(
        [[""]],
        colWidths=[AVAIL_W],
        rowHeights=[2],
    )
    line_table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), GOLD),
                ("LEFTPADDING", (0, 0), (-1, -1), 0),
                ("RIGHTPADDING", (0, 0), (-1, -1), 0),
                ("TOPPADDING", (0, 0), (-1, -1), 0),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ]
        )
    )
    return [Spacer(1, 10), para, line_table, Spacer(1, 6)]


def make_paragraph(text: str, style_key: str = "body") -> Paragraph:
    return Paragraph(text, STYLES[style_key])


def make_field_table(headers: list[str], rows: list[list[str]], col_ratios: list[float] | None = None) -> Table:
    """
    Tabla de campos con cabecera oscura y filas alternadas.
    col_ratios: proporciones relativas (se normalizan automaticamente).
    """
    n_cols = len(headers)
    if col_ratios is None:
        col_ratios = [1.0] * n_cols
    total_ratio = sum(col_ratios)
    col_widths = [AVAIL_W * r / total_ratio for r in col_ratios]

    header_cells = [Paragraph(h, STYLES["table_header"]) for h in headers]
    data = [header_cells]
    for row in rows:
        cells = []
        for val in row:
            cells.append(Paragraph(str(val), STYLES["table_cell"]))
        data.append(cells)

    t = Table(data, colWidths=col_widths, repeatRows=1)
    style_cmds = [
        ("BACKGROUND", (0, 0), (-1, 0), TABLE_HEADER_BG),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("FONTNAME", (0, 0), (-1, 0), FONT_BOLD),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("GRID", (0, 0), (-1, -1), 0.4, HexColor("#CBD5E1")),
    ]
    for i in range(1, len(data)):
        if i % 2 == 0:
            style_cmds.append(("BACKGROUND", (0, i), (-1, i), ROW_ALT))
    t.setStyle(TableStyle(style_cmds))
    return t


def make_step_table(steps: list[tuple[str, str]]) -> Table:
    """Tabla de pasos numerados: numero en recuadro dorado a la izq, descripcion a la der."""
    num_w = 40
    desc_w = AVAIL_W - num_w
    data = []
    for num, desc in steps:
        n_para = Paragraph(f"<b>{num}</b>", STYLES["step_num"])
        d_para = Paragraph(desc, STYLES["step_desc"])
        data.append([n_para, d_para])
    t = Table(data, colWidths=[num_w, desc_w])
    style_cmds = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (0, -1), 4),
        ("RIGHTPADDING", (0, 0), (0, -1), 4),
        ("LEFTPADDING", (1, 0), (1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LINEAFTER", (0, 0), (0, -1), 0.5, HexColor("#CBD5E1")),
    ]
    for i in range(len(data)):
        style_cmds.append(("BACKGROUND", (0, i), (0, i), HexColor("#FEF3C7")))
    t.setStyle(TableStyle(style_cmds))
    return t


def make_note_box(text: str) -> Table:
    """Recuadro de nota: fondo amarillo claro, borde superior dorado."""
    para = Paragraph(f"<b>Nota:</b> {text}", STYLES["note_text"])
    t = Table([[para]], colWidths=[AVAIL_W])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), NOTE_BG),
                ("LINEABOVE", (0, 0), (-1, 0), 2.5, GOLD),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    return t


def make_warning_box(text: str) -> Table:
    """Recuadro de advertencia: fondo rojo claro, borde superior rojo."""
    para = Paragraph(f"<b>Advertencia:</b> {text}", STYLES["warning_text"])
    t = Table([[para]], colWidths=[AVAIL_W])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), WARNING_BG),
                ("LINEABOVE", (0, 0), (-1, 0), 2.5, WARNING_BORDER),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    return t


def make_intro(text: str) -> Paragraph:
    return Paragraph(text, STYLES["intro"])


def make_bullet_list(items: list[str], style_key: str = "body") -> list:
    """Lista con vinetas simples."""
    bullet_style = ParagraphStyle(
        "bullet",
        parent=STYLES[style_key],
        leftIndent=18,
        bulletIndent=6,
        spaceBefore=2,
        spaceAfter=2,
    )
    return [Paragraph(f"<bullet>&bull;</bullet> {item}", bullet_style) for item in items]


# ---------------------------------------------------------------------------
# Portada y plantillas de pagina
# ---------------------------------------------------------------------------


def draw_cover(canvas: Canvas, doc, module_name: str, module_desc: str):
    """Dibuja portada completa en la pagina actual."""
    canvas.saveState()
    # Fondo oscuro
    canvas.setFillColor(DARK_NAVY)
    canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)

    # Nombre del modulo en dorado
    canvas.setFillColor(GOLD)
    canvas.setFont(FONT_BOLD, 28)
    canvas.drawCentredString(PAGE_W / 2, PAGE_H / 2 + 60, module_name)

    # Linea dorada
    line_y = PAGE_H / 2 + 40
    canvas.setStrokeColor(GOLD)
    canvas.setLineWidth(2)
    canvas.line(PAGE_W * 0.2, line_y, PAGE_W * 0.8, line_y)

    # Subtitulo empresa
    canvas.setFillColor(white)
    canvas.setFont(FONT, 14)
    canvas.drawCentredString(PAGE_W / 2, PAGE_H / 2 + 10, "ALTAMIRA LIGHT & SOUND")

    # Descripcion del modulo
    canvas.setFont(FONT_ITALIC, 11)
    canvas.setFillColor(HexColor("#94A3B8"))
    canvas.drawCentredString(PAGE_W / 2, PAGE_H / 2 - 20, module_desc)

    # Metadata
    canvas.setFont(FONT, 10)
    canvas.setFillColor(HexColor("#94A3B8"))
    canvas.drawCentredString(
        PAGE_W / 2,
        PAGE_H * 0.2,
        "Manual de Usuario  |  Version 1.0  |  Junio 2026",
    )

    canvas.restoreState()


def draw_header_footer(canvas: Canvas, doc, module_name: str):
    """Cabecera y pie para paginas internas."""
    canvas.saveState()

    # --- Cabecera ---
    bar_h = 22
    bar_y = PAGE_H - MARGIN_TB + 4
    canvas.setFillColor(DARK_NAVY)
    canvas.rect(MARGIN_LR, bar_y, AVAIL_W, bar_h, stroke=0, fill=1)
    canvas.setFillColor(white)
    canvas.setFont(FONT_BOLD, 8)
    canvas.drawString(MARGIN_LR + 8, bar_y + 7, "ALTAMIRA LIGHT & SOUND")
    canvas.drawRightString(MARGIN_LR + AVAIL_W - 8, bar_y + 7, module_name)

    # --- Pie ---
    foot_y = MARGIN_TB - 20
    canvas.setFillColor(HexColor("#64748B"))
    canvas.setFont(FONT, 7)
    canvas.drawString(MARGIN_LR, foot_y, "Manual de Usuario — Uso interno")
    page_num = canvas.getPageNumber()
    canvas.drawRightString(
        MARGIN_LR + AVAIL_W,
        foot_y,
        f"Pagina {page_num}",
    )

    canvas.restoreState()


# ---------------------------------------------------------------------------
# Construccion del documento
# ---------------------------------------------------------------------------


def build_doc(filepath: str, module_name: str, module_desc: str, flowables_fn):
    """
    Crea el PDF completo.
    flowables_fn: callable que devuelve list[Flowable] con el contenido.
    """

    def on_cover(canvas, doc):
        draw_cover(canvas, doc, module_name, module_desc)

    def on_content(canvas, doc):
        draw_header_footer(canvas, doc, module_name)

    content_frame = Frame(
        MARGIN_LR,
        MARGIN_TB,
        AVAIL_W,
        AVAIL_H - 28,  # restar alto de la barra de cabecera
        id="content",
        leftPadding=0,
        rightPadding=0,
        topPadding=0,
        bottomPadding=0,
    )

    cover_frame = Frame(
        0, 0, PAGE_W, PAGE_H,
        id="cover",
        leftPadding=0,
        rightPadding=0,
        topPadding=0,
        bottomPadding=0,
    )

    cover_template = PageTemplate(
        id="cover",
        frames=[cover_frame],
        onPage=on_cover,
    )
    content_template = PageTemplate(
        id="content",
        frames=[content_frame],
        onPage=on_content,
    )

    doc = BaseDocTemplate(
        filepath,
        pagesize=A4,
        leftMargin=MARGIN_LR,
        rightMargin=MARGIN_LR,
        topMargin=MARGIN_TB,
        bottomMargin=MARGIN_TB,
        title=f"Manual {module_name} — Altamira ERP",
        author="Altamira Light & Sound",
    )
    doc.addPageTemplates([cover_template, content_template])

    story = []
    # Portada (se dibuja via onPage; solo necesitamos un flowable vacio + salto)
    story.append(Spacer(1, 1))
    story.append(NextPageTemplate("content"))
    story.append(PageBreak())

    # Contenido del manual
    story.extend(flowables_fn())

    doc.build(story)


# ---------------------------------------------------------------------------
# Tabla de contenido simple
# ---------------------------------------------------------------------------


def make_toc(sections: list[str]) -> list:
    """Genera tabla de contenido simple (lista de secciones)."""
    elements = []
    elements.append(Paragraph("Contenido", STYLES["toc_title"]))
    elements.append(Spacer(1, 4))
    for sec in sections:
        elements.append(Paragraph(sec, STYLES["toc_item"]))
    elements.append(Spacer(1, 16))
    return elements


# ===========================================================================
# MANUAL 1: CONFIGURACION
# ===========================================================================


def manual_configuracion() -> list:
    e = []

    # -- TOC --
    e.extend(
        make_toc(
            [
                "1. Introduccion",
                "2. Usuarios — Lista",
                "3. Usuarios — Formulario",
                "4. Usuarios — Historial de Accesos",
                "5. Permisos",
                "6. Empresa",
            ]
        )
    )

    # -- Introduccion --
    e.extend(make_section_header("1. Introduccion"))
    e.append(
        make_intro(
            "El modulo de <b>Configuracion</b> permite administrar los aspectos fundamentales del sistema: "
            "cuentas de usuario, permisos de acceso por perfil y los datos de la empresa ante el SRI. "
            "Desde aqui usted puede crear y gestionar usuarios, definir que acciones puede realizar cada "
            "perfil en cada modulo y mantener actualizados los datos tributarios de su empresa."
        )
    )
    e.append(
        make_intro(
            "Es recomendable que solo personal autorizado (administradores) tenga acceso a este modulo, "
            "ya que los cambios realizados aqui afectan el comportamiento global del sistema."
        )
    )

    # -- 2. Usuarios - Lista --
    e.extend(make_section_header("2. Usuarios — Lista"))
    e.append(
        make_paragraph(
            "La pantalla de lista de usuarios muestra todas las cuentas de acceso registradas en el sistema. "
            "Desde aqui puede buscar, filtrar, activar o desactivar usuarios, y acceder a su historial de accesos."
        )
    )
    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Usuario", "Nombre completo y correo electronico del usuario."],
                ["Perfil", "Rol asignado (super admin, admin, vendedor, etc.)."],
                ["Empresa", "Empresa principal a la que pertenece el usuario."],
                ["Ultimo acceso", "Fecha y hora del ultimo inicio de sesion exitoso."],
                ["Estado", "Interruptor (toggle) que indica si el usuario esta activo o inactivo."],
                ["Acciones", "Botones para Editar el usuario o ver su Historial de accesos."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 8))

    e.extend(make_subsection_header("Filtros y busqueda"))
    e.append(
        make_paragraph(
            "Utilice el campo de busqueda para filtrar por nombre, correo electronico o nombre de usuario. "
            "Los resultados se actualizan de forma dinamica a medida que escribe."
        )
    )

    e.extend(make_subsection_header("Acciones disponibles"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nuevo Usuario", "Abre el formulario para crear una nueva cuenta de acceso."],
                ["Exportar Excel", "Descarga la lista visible de usuarios en formato Excel."],
                [
                    "Activar / Desactivar",
                    "Al cambiar el interruptor de estado se solicita confirmacion. "
                    "Un usuario desactivado no puede iniciar sesion.",
                ],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Al desactivar un usuario se le impide acceder al sistema de forma inmediata. "
            "La opcion Exportar Excel descarga unicamente la lista filtrada que se muestra en pantalla."
        )
    )

    # -- 3. Usuarios - Formulario --
    e.extend(make_section_header("3. Usuarios — Formulario"))
    e.append(
        make_paragraph(
            "Este formulario se utiliza tanto para crear nuevos usuarios como para editar los existentes. "
            "Los campos marcados con asterisco (*) son obligatorios."
        )
    )

    e.extend(make_subsection_header("Datos personales"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Nombre completo", "Si", "Nombre y apellido del usuario."],
                ["Email", "Si", "Correo electronico unico. Se usa para notificaciones."],
                ["Telefono", "No", "Numero de contacto del usuario."],
            ],
            [1.2, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acceso al sistema"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Username", "Si", "Nombre de usuario unico para iniciar sesion."],
                ["Perfil", "Si", "Selector del rol que determina los permisos del usuario."],
                ["Empresa principal", "Si", "Empresa a la que se asocia por defecto al iniciar sesion."],
                ["Centro de costo", "No", "Centro de costo al que pertenece, si aplica."],
                [
                    "Contrasena",
                    "Si (crear)",
                    "Requerida solo al crear. Debe cumplir requisitos de seguridad.",
                ],
                ["Confirmar contrasena", "Si (crear)", "Debe coincidir con el campo anterior."],
            ],
            [1.4, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Empresas con acceso"))
    e.append(
        make_paragraph(
            "Seleccione una o mas empresas a las que el usuario tendra acceso. "
            "Use los botones toggle para activar o desactivar cada empresa. "
            "El usuario solo podra cambiar entre las empresas seleccionadas aqui."
        )
    )

    e.extend(make_subsection_header("Codigo de aprobacion"))
    e.append(
        make_paragraph(
            "Este campo solo aparece cuando el perfil seleccionado es <b>admin</b> o <b>super_admin</b>. "
            "El PIN debe tener entre 4 y 6 digitos y se utiliza para aprobar operaciones especiales."
        )
    )
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["PIN", "Codigo numerico de 4 a 6 digitos para aprobaciones."],
                ["Confirmar PIN", "Debe coincidir con el PIN ingresado."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "El PIN de aprobacion se usa para aprobar descuentos, anulaciones y operaciones especiales. "
            "No lo comparta con otros usuarios."
        )
    )

    e.extend(make_subsection_header("Estado"))
    e.append(
        make_paragraph(
            "El interruptor 'Usuario activo' permite activar o desactivar la cuenta. "
            "Un usuario inactivo no podra iniciar sesion en el sistema."
        )
    )

    # -- 4. Historial de Accesos --
    e.extend(make_section_header("4. Usuarios — Historial de Accesos"))
    e.append(
        make_paragraph(
            "Esta pantalla muestra el registro completo de todos los intentos de acceso "
            "de un usuario especifico, incluyendo accesos exitosos, intentos fallidos, "
            "cierres de sesion y sesiones forzadas."
        )
    )
    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                [
                    "Tipo",
                    "Tipo de evento: Acceso exitoso, Intento fallido, Cierre de sesion o Sesion forzada.",
                ],
                ["IP", "Direccion IP desde la que se realizo el intento."],
                ["Fecha", "Fecha y hora exacta del evento."],
            ],
            [1.2, 3],
        )
    )

    # -- 5. Permisos --
    e.extend(make_section_header("5. Permisos"))
    e.append(
        make_paragraph(
            "La pantalla de permisos presenta una matriz de acceso que define que acciones puede "
            "realizar cada perfil en cada modulo del sistema. Se accede mediante pestanas horizontales, "
            "una por cada perfil disponible (super admin, admin, vendedor, etc.)."
        )
    )
    e.extend(make_subsection_header("Matriz de permisos"))
    e.append(
        make_field_table(
            ["Modulo", "Ver", "Crear", "Editar", "Eliminar", "Anular"],
            [
                ["(nombre del modulo)", "[ ]", "[ ]", "[ ]", "[ ]", "[ ]"],
            ],
            [2.5, 0.7, 0.7, 0.7, 0.7, 0.7],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_paragraph(
            "Cada casilla de verificacion otorga o revoca el permiso correspondiente. "
            "Las filas representan los modulos del sistema y las columnas las acciones permitidas."
        )
    )

    e.extend(make_subsection_header("Limites de descuento"))
    e.append(
        make_field_table(
            ["Parametro", "Descripcion"],
            [
                [
                    "% maximo sin aprobacion",
                    "Porcentaje de descuento que el perfil puede aplicar sin requerir aprobacion.",
                ],
                [
                    "Puede aprobar descuentos",
                    "Casilla que habilita al perfil para aprobar descuentos de otros usuarios.",
                ],
                [
                    "% maximo que puede aprobar",
                    "Tope maximo de descuento que este perfil puede autorizar.",
                ],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Los cambios en permisos se guardan automaticamente al marcar o desmarcar cada casilla. "
            "No es necesario presionar un boton de guardar."
        )
    )

    # -- 6. Empresa --
    e.extend(make_section_header("6. Empresa"))
    e.append(
        make_paragraph(
            "La pantalla de configuracion de empresa permite gestionar los datos generales, "
            "la informacion tributaria (SRI), los centros de costo y los secuenciales de documentos electronicos."
        )
    )

    e.extend(make_subsection_header("Datos generales"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Razon social", "Si", "Nombre legal de la empresa registrado en el SRI."],
                ["Nombre comercial", "Si", "Nombre con el que opera la empresa."],
                ["RUC", "Si", "Registro Unico de Contribuyentes (13 digitos)."],
                ["Direccion matriz", "No", "Direccion fisica de la oficina principal."],
                ["Email de notificaciones", "No", "Correo para envio de comprobantes electronicos."],
                ["Telefono", "No", "Numero de contacto de la empresa."],
            ],
            [1.5, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Datos SRI"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                [
                    "Ambiente de facturacion",
                    "Pruebas (1) para validacion sin efecto legal; Produccion (2) para comprobantes reales.",
                ],
                ["Codigo establecimiento", "Codigo numerico del establecimiento ante el SRI."],
                ["Punto de emision", "Codigo numerico del punto de emision."],
                [
                    "N. resolucion agente retencion",
                    "Numero de resolucion si la empresa es agente de retencion.",
                ],
                [
                    "Obligado a llevar contabilidad",
                    "Indica si la empresa esta obligada a llevar contabilidad.",
                ],
                ["Contribuyente especial", "Indica si la empresa tiene calificacion especial del SRI."],
            ],
            [1.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Centros de costo"))
    e.append(
        make_paragraph(
            "Lista de centros de costo asociados a la empresa. Cada centro tiene un nombre, "
            "un codigo identificador, un tipo y un estado activo/inactivo."
        )
    )

    e.extend(make_subsection_header("Secuenciales SRI"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Documento", "Tipo de comprobante electronico (factura, nota de credito, etc.)."],
                ["Establecimiento", "Codigo del establecimiento emisor."],
                ["Punto de emision", "Codigo del punto de emision."],
                ["Proximo numero", "Siguiente numero secuencial que se asignara al emitir."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Cambiar el ambiente a 'Produccion' hace que los comprobantes electronicos sean validos "
            "ante el SRI. Solo cambie a Produccion cuando este completamente seguro de que los datos "
            "de la empresa y los secuenciales son correctos."
        )
    )

    return e


# ===========================================================================
# MANUAL 2: PERSONAS
# ===========================================================================


def manual_personas() -> list:
    e = []

    # -- TOC --
    e.extend(
        make_toc(
            [
                "1. Introduccion",
                "2. Clientes — Lista",
                "3. Clientes — Formulario",
                "4. Proveedores — Lista",
                "5. Proveedores — Formulario",
                "6. Transportistas",
            ]
        )
    )

    # -- 1. Introduccion --
    e.extend(make_section_header("1. Introduccion"))
    e.append(
        make_intro(
            "El modulo de <b>Personas</b> centraliza la gestion de todos los terceros con los que "
            "interactua la empresa: clientes, proveedores y transportistas. Desde aqui puede crear, "
            "editar y administrar la informacion de contacto, credito y estado de cada registro."
        )
    )
    e.append(
        make_intro(
            "La informacion registrada en este modulo se utiliza en los procesos de ventas, compras, "
            "importaciones y despacho de mercaderia, por lo que es fundamental mantenerla actualizada."
        )
    )

    # -- 2. Clientes - Lista --
    e.extend(make_section_header("2. Clientes — Lista"))
    e.append(
        make_paragraph(
            "La pantalla de clientes muestra el listado completo de clientes registrados. "
            "Permite buscar, filtrar por estado y realizar acciones de gestion sobre cada registro."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["No", "Numero de fila secuencial."],
                [
                    "Identificacion",
                    "Tipo de documento (RUC, Cedula, Pasaporte, Consumidor Final) "
                    "mostrado como etiqueta de color, seguido del numero.",
                ],
                ["Nombre / Razon Social", "Nombre o razon social del cliente."],
                ["Canton", "Canton de residencia o domicilio fiscal."],
                ["Direccion", "Direccion principal registrada."],
                ["Telefono", "Numero de contacto principal."],
                ["Email", "Correo electronico del cliente."],
                ["Credito", "Dias de credito otorgados y cupo maximo asignado."],
                ["Estado", "Indica si el cliente esta activo o inactivo."],
            ],
            [1.3, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros"))
    e.append(
        make_field_table(
            ["Filtro", "Descripcion"],
            [
                [
                    "Busqueda",
                    "Busca por numero de identificacion o razon social. "
                    "Los resultados se actualizan con un breve retraso (debounce).",
                ],
                [
                    "Estado",
                    "Permite filtrar entre Todos, Activos o Inactivos.",
                ],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nuevo Cliente", "Abre el formulario de creacion de cliente."],
                ["Exportar PDF", "Genera un archivo PDF con la lista de clientes."],
                ["Exportar Excel", "Descarga la lista en formato Excel."],
                ["Editar", "Abre el formulario con los datos del cliente para modificarlos."],
                [
                    "Eliminar",
                    "Elimina el cliente tras solicitar confirmacion mediante un dialogo.",
                ],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Eliminar un cliente es una accion irreversible. Verifique que no tenga "
            "documentos asociados (facturas, notas de credito, etc.) antes de proceder."
        )
    )

    # -- 3. Clientes - Formulario --
    e.extend(make_section_header("3. Clientes — Formulario"))
    e.append(
        make_paragraph(
            "Formulario para crear o editar clientes. Los campos marcados con (*) son obligatorios."
        )
    )

    e.extend(make_subsection_header("Identificacion"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                [
                    "Tipo de identificacion",
                    "Si",
                    "04-RUC, 05-Cedula, 06-Pasaporte o 07-Consumidor Final.",
                ],
                ["Identificacion", "Si", "Numero de documento segun el tipo seleccionado."],
                ["Razon Social", "Si", "Nombre legal o nombre completo del cliente."],
                ["Nombre Comercial", "No", "Nombre comercial, si difiere de la razon social."],
            ],
            [1.5, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Contacto"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["Email", "Correo electronico del cliente."],
                ["Telefono", "Telefono fijo o convencional."],
                ["Celular", "Numero de telefono movil."],
                ["Direccion", "Direccion principal."],
                ["Ciudad", "Ciudad de residencia o domicilio."],
                ["Provincia", "Provincia."],
                ["Pais", "Pais de residencia."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Credito"))
    e.append(
        make_field_table(
            ["Campo", "Condicion", "Descripcion"],
            [
                [
                    "Tiene credito",
                    "—",
                    "Interruptor que habilita los campos de credito.",
                ],
                [
                    "Dias de credito",
                    "Si credito activo",
                    "Plazo en dias para el pago de facturas a credito.",
                ],
                [
                    "Cupo maximo",
                    "Si credito activo",
                    "Monto maximo de credito permitido al cliente.",
                ],
                [
                    "Es agente de retencion",
                    "—",
                    "Indica si el cliente es agente de retencion del SRI.",
                ],
            ],
            [1.3, 1, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Estado"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["Cliente activo", "Interruptor para activar o desactivar el registro del cliente."],
                [
                    "Es cliente nuevo",
                    "Interruptor que marca al cliente como reciente para fines de seguimiento.",
                ],
            ],
            [1.2, 3],
        )
    )

    # -- 4. Proveedores - Lista --
    e.extend(make_section_header("4. Proveedores — Lista"))
    e.append(
        make_paragraph(
            "Listado de todos los proveedores registrados. La pantalla incluye pestanas "
            "para filtrar entre Todos, Nacionales e Internacionales."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["No", "Numero de fila secuencial."],
                ["Tipo", "Nacional o Internacional."],
                [
                    "Identificacion",
                    "RUC, Cedula o Pasaporte mostrado con etiqueta de tipo, seguido del numero.",
                ],
                ["Razon Social", "Nombre legal del proveedor."],
                ["Pais", "Pais de origen. Para internacionales incluye la divisa."],
                ["Ciudad", "Ciudad de ubicacion del proveedor."],
                ["Telefono", "Numero de contacto."],
                ["Email", "Correo electronico."],
                ["Credito", "Dias de credito acordados con el proveedor."],
                ["Estado", "Activo o inactivo."],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nuevo Proveedor", "Abre el formulario de creacion."],
                ["PDF", "Exporta la lista en formato PDF."],
                ["Excel", "Exporta la lista en formato Excel."],
                ["Editar", "Modifica los datos del proveedor seleccionado."],
                ["Eliminar", "Elimina el proveedor con confirmacion previa."],
            ],
            [1.2, 3],
        )
    )

    # -- 5. Proveedores - Formulario --
    e.extend(make_section_header("5. Proveedores — Formulario"))
    e.append(
        make_paragraph(
            "Formulario con pestanas Nacional / Internacional para crear o editar proveedores."
        )
    )

    e.extend(make_subsection_header("Datos del proveedor"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                [
                    "Tipo identificacion",
                    "Si",
                    "Nacional: RUC o Cedula. Internacional: Tax ID o Pasaporte.",
                ],
                ["Identificacion", "Si", "Numero de documento segun tipo seleccionado."],
                ["Razon social", "Si", "Nombre legal del proveedor."],
                ["Nombre comercial", "No", "Nombre comercial, si difiere del legal."],
                ["Email", "No", "Correo electronico de contacto."],
                ["Telefono", "No", "Numero de telefono."],
                ["Direccion", "No", "Direccion fisica del proveedor."],
            ],
            [1.4, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Campos segun tipo"))
    e.append(
        make_field_table(
            ["Campo", "Nacional", "Internacional"],
            [
                ["Pais", "Fijo: ECUADOR", "Seleccionable (obligatorio)"],
                ["Ciudad", "Opcional", "Obligatorio"],
                ["Divisa", "No aplica", "USD, EUR, CNY o GBP (obligatorio)"],
            ],
            [1, 1.5, 1.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Credito y estado"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["Tiene credito", "Interruptor que habilita el campo de dias de credito."],
                ["Dias de credito", "Plazo en dias para pago (obligatorio si credito activo)."],
                ["Proveedor activo", "Interruptor para activar o desactivar el proveedor."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Para proveedores internacionales, seleccione la divisa correcta ya que "
            "se utilizara en los procesos de importacion."
        )
    )

    # -- 6. Transportistas --
    e.extend(make_section_header("6. Transportistas"))
    e.append(
        make_paragraph(
            "Pantalla para gestionar los transportistas que se utilizan en guias de remision "
            "y traslados de mercaderia. La gestion se realiza mediante un formulario modal (ventana emergente)."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["No", "Numero de fila secuencial."],
                ["Razon Social", "Nombre del transportista o empresa de transporte."],
                ["Identificacion", "RUC, cedula o numero de identificacion."],
                ["Placa", "Placa del vehiculo asignado."],
                ["Email", "Correo electronico de contacto."],
                ["Telefono", "Numero de telefono."],
                ["Estado", "Activo o inactivo."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtro"))
    e.append(
        make_paragraph(
            "Busqueda local por razon social o numero de identificacion. Los resultados se filtran "
            "directamente en la tabla sin consultar al servidor."
        )
    )

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nuevo Transportista", "Abre el formulario modal para registrar un nuevo transportista."],
                ["PDF", "Exporta la lista en formato PDF."],
                ["Excel", "Exporta la lista en formato Excel."],
                ["Editar", "Abre el modal con los datos del transportista para modificarlos."],
                ["Eliminar", "Elimina el transportista con confirmacion previa."],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Campos del formulario modal"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Razon social", "Si", "Nombre del transportista o razon social."],
                ["Identificacion", "No", "RUC o cedula del transportista."],
                ["Placa", "No", "Placa del vehiculo."],
                ["Email", "No", "Correo electronico."],
                ["Telefono", "No", "Numero de contacto."],
                ["Direccion", "No", "Direccion fisica."],
                ["Activo", "—", "Interruptor para activar o desactivar el registro."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Los transportistas se utilizan en guias de remision y traslados de mercaderia. "
            "Asegurese de registrar la placa correcta del vehiculo."
        )
    )

    return e


# ===========================================================================
# MANUAL 3: INVENTARIO
# ===========================================================================


def manual_inventario() -> list:
    e = []

    # -- TOC --
    e.extend(
        make_toc(
            [
                "1. Introduccion",
                "2. Productos — Lista",
                "3. Productos — Formulario",
                "4. Kardex de Movimientos",
                "5. Saldos de Inventario",
                "6. Ajuste de Inventario",
                "7. Traslados — Lista",
                "8. Traslados — Formulario",
                "9. Traslados — Detalle",
                "10. Activos Fijos — Lista",
                "11. Activos Fijos — Formulario",
                "12. Activos Fijos — Detalle",
                "13. Recepciones de Bodega — Lista",
                "14. Recepciones — Detalle",
                "15. Listas de Precio",
                "16. Configuracion — Bodegas",
                "17. Configuracion — Categorias",
                "18. Configuracion — Marcas",
            ]
        )
    )

    # -- 1. Introduccion --
    e.extend(make_section_header("1. Introduccion"))
    e.append(
        make_intro(
            "El modulo de <b>Inventario</b> gestiona todo el ciclo de vida de los productos, "
            "desde su registro inicial hasta el control de stock en multiples bodegas. "
            "Incluye funcionalidades de kardex, traslados entre bodegas, ajustes manuales, "
            "activos fijos, recepciones de mercaderia, listas de precio y la configuracion "
            "de bodegas, categorias y marcas."
        )
    )
    e.append(
        make_intro(
            "Es el modulo mas extenso del sistema y esta disenado para ofrecer trazabilidad completa "
            "de cada movimiento de inventario, costos promedio ponderados y alertas de stock critico."
        )
    )

    # -- 2. Productos - Lista --
    e.extend(make_section_header("2. Productos — Lista"))
    e.append(
        make_paragraph(
            "Pantalla principal de gestion de productos. Muestra el catalogo completo con opciones "
            "de busqueda, filtrado y acciones de gestion."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Codigo", "Codigo unico del producto en el sistema."],
                [
                    "Nombre",
                    "Nombre del producto. Si requiere numero de serie, muestra una etiqueta 'Serie'.",
                ],
                ["Marca", "Marca del producto."],
                ["Categoria", "Categoria a la que pertenece."],
                ["PVP", "Precio de venta al publico."],
                ["PVD", "Precio de venta a distribuidor."],
                [
                    "Tipo",
                    "Clasificacion: Producto, Servicio, Repuesto o Insumo. "
                    "Cada tipo se muestra con un color diferente.",
                ],
                ["Estado", "Activo o inactivo."],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros"))
    e.append(
        make_field_table(
            ["Filtro", "Descripcion"],
            [
                ["Busqueda", "Filtra por codigo o nombre del producto."],
                ["Marca", "Selector para filtrar por marca."],
                ["Categoria", "Selector para filtrar por categoria."],
                ["Tipo", "Selector: Producto, Servicio, Repuesto o Insumo."],
                ["Estado", "Filtrar entre activos, inactivos o todos."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nuevo Producto", "Abre el formulario de creacion (requiere permiso de crear)."],
                ["PDF", "Exporta el catalogo en formato PDF."],
                ["Excel", "Exporta el catalogo en formato Excel."],
                ["Editar", "Modifica el producto (requiere permiso de editar)."],
                ["Eliminar", "Elimina el producto con confirmacion (requiere permiso de eliminar)."],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Los permisos del modulo de inventario determinan que acciones puede realizar cada usuario. "
            "Si no ve un boton, consulte con su administrador sobre sus permisos."
        )
    )

    # -- 3. Productos - Formulario --
    e.extend(make_section_header("3. Productos — Formulario"))
    e.append(
        make_paragraph(
            "Formulario completo para crear o editar productos. Esta organizado en cuatro pestanas: "
            "General, Precios, Inventario y Contabilidad."
        )
    )

    e.extend(make_subsection_header("Pestana: General"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Codigo", "Si", "Codigo unico identificador del producto."],
                ["Nombre", "Si", "Nombre descriptivo del producto."],
                ["Tipo", "Si", "Producto, Servicio, Repuesto o Insumo."],
                ["Unidad", "Si", "Unidad de medida: Unidad, Par, Caja, Metro, Hora o Kit."],
                ["Marca", "No", "Marca del producto (selector)."],
                ["Categoria", "No", "Categoria del producto (selector)."],
                ["Descripcion", "No", "Descripcion detallada o notas adicionales."],
                [
                    "Requiere numero de serie",
                    "—",
                    "Interruptor. Si se activa, debera registrar seriales al ingresar stock.",
                ],
                ["Producto activo", "—", "Interruptor para activar o desactivar el producto."],
            ],
            [1.8, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Pestana: Precios"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["PVP", "Si", "Precio de venta al publico."],
                ["PVD", "Si", "Precio de venta a distribuidor."],
                ["Costo", "No", "Costo de adquisicion o ultimo costo registrado."],
                ["Descuento maximo (%)", "No", "Porcentaje maximo de descuento permitido sobre este producto."],
                ["IVA %", "No", "Tarifa de IVA aplicable: 0%, 5% o 15%."],
                ["ICE (%)", "No", "Impuesto a los Consumos Especiales, si aplica."],
                [
                    "Margen estimado",
                    "—",
                    "Calculado automaticamente en base al PVP y el costo.",
                ],
            ],
            [1.5, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Pestana: Inventario"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                [
                    "Stock minimo",
                    "Cantidad minima de stock. Solo numeros enteros. "
                    "Cuando el stock real alcanza este valor se muestra alerta.",
                ],
                ["Stock maximo", "Cantidad maxima sugerida (opcional, solo enteros)."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_note_box(
            "El stock se gestiona desde el modulo de Kardex. Aqui solo se configuran "
            "los umbrales de alerta para stock minimo y maximo."
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Pestana: Contabilidad"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["Cuenta de Inventario", "Cuenta contable para registrar el inventario del producto."],
                ["Cuenta Costo de Ventas", "Cuenta contable para el costo cuando se vende."],
                ["Cuenta de Ventas", "Cuenta contable donde se registran los ingresos por ventas."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Si activa 'Requiere numero de serie', debera registrar los seriales individuales "
            "de cada unidad al ingresar stock desde el modulo de Kardex."
        )
    )

    # -- 4. Kardex --
    e.extend(make_section_header("4. Kardex de Movimientos"))
    e.append(
        make_paragraph(
            "El Kardex muestra el historial completo de entradas y salidas de stock por producto. "
            "Cada movimiento queda registrado con su tipo, documento de origen, cantidades y costos."
        )
    )

    e.extend(make_subsection_header("Filtros"))
    e.append(
        make_field_table(
            ["Filtro", "Descripcion"],
            [
                ["Buscar", "Busqueda por codigo o nombre del producto."],
                ["Bodega", "Filtrar movimientos por bodega especifica."],
                ["Tipo", "Tipo de movimiento: Entrada, Salida, Traslado, Ajuste o Reserva."],
                ["Fecha desde", "Fecha inicial del rango de consulta."],
                ["Fecha hasta", "Fecha final del rango de consulta."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Estructura de la tabla"))
    e.append(
        make_paragraph(
            "Los movimientos se agrupan por producto. Cada bloque muestra el codigo y nombre "
            "del producto como encabezado, seguido de las filas de movimiento:"
        )
    )
    e.append(
        make_field_table(
            ["Grupo", "Columnas"],
            [
                ["Documento", "Numero, Fecha, Tipo, Documento No., Observaciones"],
                ["Transaccion", "Tipo de transaccion (Entrada, Salida, etc.)"],
                ["Ingresos (+)", "Cantidad, Costo unitario, Costo total"],
                ["Egresos (-)", "Cantidad, Costo unitario, Costo total"],
                ["Saldos", "Cantidad en existencia despues del movimiento"],
            ],
            [1, 3.5],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_paragraph(
            "La primera fila de cada bloque muestra el <b>SALDO ANTERIOR</b> y la ultima fila "
            "muestra el <b>TOTAL</b> acumulado. Los valores de ingreso se muestran en verde y "
            "los de egreso en rojo para facilitar la lectura."
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_note_box(
            "Para consultar el kardex, primero ingrese un termino de busqueda. "
            "Los valores en verde representan ingresos y los valores en rojo representan egresos."
        )
    )

    # -- 5. Saldos --
    e.extend(make_section_header("5. Saldos de Inventario"))
    e.append(
        make_paragraph(
            "Esta pantalla muestra el stock actual de cada producto desglosado por bodega, "
            "incluyendo el costo promedio y el valor total del inventario."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Codigo", "Codigo del producto."],
                ["Producto", "Nombre del producto."],
                ["Bodega", "Bodega donde se encuentra el stock."],
                [
                    "Cantidad",
                    "Stock disponible. Si es igual o menor al minimo configurado, "
                    "muestra etiqueta 'CRITICO' en rojo.",
                ],
                ["Costo Promedio", "Costo promedio ponderado por unidad."],
                ["Valor Total", "Cantidad multiplicada por el costo promedio."],
                ["Acciones", "Boton para registrar un ajuste de inventario."],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros"))
    e.append(
        make_field_table(
            ["Filtro", "Descripcion"],
            [
                ["Busqueda", "Filtra por codigo o nombre del producto."],
                ["Bodega", "Filtra por bodega especifica."],
                ["Solo criticos", "Casilla para mostrar unicamente productos con stock critico."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Ver Movimientos", "Navega al Kardex filtrado por ese producto."],
                ["Registrar Ajuste", "Abre el formulario de ajuste de inventario."],
                ["PDF", "Exporta los saldos en formato PDF."],
                ["Excel", "Exporta los saldos en formato Excel."],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Los productos con etiqueta 'CRITICO' tienen stock igual o menor al minimo configurado. "
            "Considere reabastecerlos a la brevedad para evitar desabastecimiento."
        )
    )

    # -- 6. Ajuste --
    e.extend(make_section_header("6. Ajuste de Inventario"))
    e.append(
        make_paragraph(
            "Permite registrar entradas o salidas manuales de stock cuando es necesario "
            "corregir discrepancias o registrar movimientos que no provienen de una compra o venta."
        )
    )

    e.extend(make_subsection_header("Campos del formulario"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                [
                    "Producto",
                    "Si",
                    "Seleccione mediante el buscador con ventana modal.",
                ],
                ["Bodega", "Si", "Bodega sobre la que se aplicara el ajuste."],
                [
                    "Stock disponible actual",
                    "—",
                    "Campo informativo que muestra la existencia en tiempo real.",
                ],
                [
                    "Tipo de ajuste",
                    "Si",
                    "Positivo (entrada de stock) o Negativo (salida de stock).",
                ],
                ["Cantidad", "Si", "Solo numeros enteros positivos."],
                [
                    "Costo unitario",
                    "Si (positivo)",
                    "Obligatorio solo en ajustes positivos. Recalcula el costo promedio ponderado.",
                ],
                ["Motivo", "Si", "Justificacion del ajuste (texto libre)."],
            ],
            [1.5, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Un ajuste negativo que supere el stock disponible sera rechazado por el servidor. "
            "El costo unitario en ajustes positivos recalcula el costo promedio ponderado del producto."
        )
    )

    # -- 7. Traslados - Lista --
    e.extend(make_section_header("7. Traslados — Lista"))
    e.append(
        make_paragraph(
            "Listado de todos los traslados de mercaderia entre bodegas. "
            "Cada traslado puede estar en estado Pendiente, Aceptado o Rechazado."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["#", "Numero de fila."],
                ["Numero", "Identificador unico del traslado."],
                ["Fecha", "Fecha en que se creo el traslado."],
                ["Origen", "Bodega de donde salen los productos."],
                ["Destino", "Bodega a donde llegan los productos."],
                ["Items", "Cantidad de lineas de producto en el traslado."],
                [
                    "Estado",
                    "Pendiente (amarillo), Aceptado (verde) o Rechazado (rojo).",
                ],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros y acciones"))
    e.append(
        make_field_table(
            ["Elemento", "Descripcion"],
            [
                ["Filtro: Estado", "Filtrar por Pendiente, Aceptado o Rechazado."],
                ["Filtro: Bodega origen", "Filtrar por la bodega de origen."],
                ["Filtro: Bodega destino", "Filtrar por la bodega de destino."],
                ["Nuevo Traslado", "Abre el formulario para crear un nuevo traslado."],
                ["Excel", "Exporta la lista de traslados."],
                ["Ver detalle", "Abre la vista detallada del traslado."],
            ],
            [1.3, 3],
        )
    )

    # -- 8. Traslados - Formulario --
    e.extend(make_section_header("8. Traslados — Formulario"))
    e.append(
        make_paragraph(
            "Formulario para crear un nuevo traslado de mercaderia entre bodegas."
        )
    )

    e.extend(make_subsection_header("Origen y destino"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Bodega origen", "Si", "Bodega desde la que se envian los productos."],
                ["Bodega destino", "Si", "Bodega que recibira los productos."],
                ["Observaciones", "No", "Notas adicionales sobre el traslado."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Productos a trasladar"))
    e.append(
        make_paragraph(
            "Tabla dinamica donde se agregan los productos que se van a trasladar. "
            "El buscador modal filtra productos que tengan stock disponible en la bodega origen."
        )
    )
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                [
                    "Producto",
                    "Nombre del producto seleccionado mediante buscador modal.",
                ],
                ["Cantidad", "Cantidad a trasladar (solo numeros enteros)."],
                ["Stock disponible", "Stock actual en la bodega origen (informativo)."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "La bodega origen y destino no pueden ser la misma. "
            "La cantidad a trasladar no puede superar el stock disponible. "
            "Verifique el stock antes de completar el traslado."
        )
    )

    # -- 9. Traslados - Detalle --
    e.extend(make_section_header("9. Traslados — Detalle"))
    e.append(
        make_paragraph(
            "Vista detallada de un traslado especifico. Muestra la informacion completa del traslado "
            "y permite a los usuarios autorizados confirmar la recepcion o rechazar el envio."
        )
    )

    e.extend(make_subsection_header("Informacion del traslado"))
    e.append(
        make_field_table(
            ["Dato", "Descripcion"],
            [
                ["Bodega origen / destino", "Bodegas involucradas en el traslado."],
                ["Estado", "Estado actual del traslado."],
                ["Numero", "Identificador del traslado."],
                ["Fecha", "Fecha de creacion del traslado."],
                ["Fecha recepcion", "Fecha en que se confirmo la recepcion (si aplica)."],
                ["Observaciones", "Notas ingresadas al crear el traslado."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Tabla de productos"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Producto", "Nombre del producto trasladado."],
                ["Codigo", "Codigo del producto."],
                ["Enviado", "Cantidad enviada desde la bodega origen."],
                ["Recibido", "Cantidad recibida (visible si el traslado fue aceptado)."],
                ["Diferencia", "Diferencia entre enviado y recibido (si aceptado)."],
                [
                    "Cant. recibida",
                    "Campo editable para ingresar la cantidad recibida (solo si pendiente).",
                ],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones (si el traslado esta pendiente)"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                [
                    "Confirmar recepcion",
                    "Acepta el traslado. Puede agregar observaciones. Requiere autorizacion.",
                ],
                [
                    "Rechazar traslado",
                    "Rechaza el envio. Es obligatorio ingresar un motivo de rechazo.",
                ],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "Al rechazar un traslado se libera el stock reservado en la bodega origen. "
            "Esta accion no se puede deshacer. Asegurese de verificar las cantidades antes de confirmar."
        )
    )

    # -- 10. Activos Fijos - Lista --
    e.extend(make_section_header("10. Activos Fijos — Lista"))
    e.append(
        make_paragraph(
            "Pantalla de gestion de activos fijos de la empresa. Muestra el registro de bienes "
            "con su valor contable y estado de depreciacion."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Codigo", "Codigo identificador del activo fijo."],
                ["Nombre", "Descripcion o nombre del activo."],
                ["Fecha Adquisicion", "Fecha en que se adquirio el bien."],
                ["Costo Adquisicion", "Valor original de compra del activo."],
                ["Depreciacion Acumulada", "Total depreciado hasta la fecha."],
                ["Valor en Libros", "Costo de adquisicion menos depreciacion acumulada."],
                ["Estado", "Activo, Baja o Vendido."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros y acciones"))
    e.append(
        make_field_table(
            ["Elemento", "Descripcion"],
            [
                ["Busqueda", "Filtrar por codigo o nombre del activo."],
                ["Estado", "Filtrar por Activo, Baja o Vendido."],
                ["Nuevo Activo", "Abre el formulario de registro."],
                ["PDF / Excel", "Exporta el listado."],
                ["Ver detalle", "Abre la ficha completa con historial de depreciacion."],
                ["Editar", "Modifica los datos del activo."],
                ["Eliminar", "Elimina el registro del activo."],
            ],
            [1.2, 3],
        )
    )

    # -- 11. Activos Fijos - Formulario --
    e.extend(make_section_header("11. Activos Fijos — Formulario"))
    e.append(
        make_paragraph(
            "Formulario para registrar o editar un activo fijo de la empresa."
        )
    )

    e.extend(make_subsection_header("Datos generales"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Codigo", "Si", "Codigo unico del activo fijo."],
                ["Nombre", "Si", "Nombre o descripcion del activo."],
                ["Descripcion", "No", "Detalle adicional del bien."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Valores y depreciacion"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Fecha de adquisicion", "Si", "Fecha en que se compro o recibio el bien."],
                ["Costo de adquisicion", "Si", "Valor de compra original."],
                ["Valor residual", "No", "Valor estimado al final de la vida util."],
                ["Vida util (anos)", "Si", "Numero de anos de vida util estimada."],
                [
                    "Metodo de depreciacion",
                    "—",
                    "Solo metodo lineal (no editable). El sistema aplica depreciacion uniforme.",
                ],
                [
                    "Depreciacion mensual estimada",
                    "—",
                    "Calculada automaticamente: (costo - residual) / (vida util x 12).",
                ],
            ],
            [1.8, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Contabilidad"))
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                [
                    "Cuenta contable",
                    "Cuenta del plan de cuentas donde se registra el activo fijo.",
                ],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_note_box(
            "El sistema calcula automaticamente la depreciacion mensual estimada "
            "basandose en el metodo lineal: (Costo - Valor residual) / (Vida util en meses)."
        )
    )

    # -- 12. Activos Fijos - Detalle --
    e.extend(make_section_header("12. Activos Fijos — Detalle"))
    e.append(
        make_paragraph(
            "Ficha detallada de un activo fijo con sus valores contables, progreso de "
            "depreciacion y el formulario para registrar depreciaciones periodicas."
        )
    )

    e.extend(make_subsection_header("Tarjetas de resumen"))
    e.append(
        make_field_table(
            ["Tarjeta", "Descripcion"],
            [
                ["Costo de adquisicion", "Valor original de compra del activo."],
                ["Depreciacion acumulada", "Total depreciado hasta la fecha actual."],
                ["Valor en libros", "Diferencia entre el costo y la depreciacion acumulada."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Barra de progreso"))
    e.append(
        make_paragraph(
            "Se muestra una barra de progreso visual que indica el porcentaje de depreciacion "
            "completado respecto al total por depreciar."
        )
    )

    e.extend(make_subsection_header("Formulario de depreciacion"))
    e.append(
        make_paragraph(
            "Disponible unicamente si el activo esta en estado 'Activo' y tiene valor pendiente "
            "por depreciar. Permite seleccionar el ano y mes, y registrar la depreciacion del periodo."
        )
    )
    e.append(
        make_field_table(
            ["Campo", "Descripcion"],
            [
                ["Ano", "Ano fiscal del periodo a depreciar."],
                ["Mes", "Mes del periodo a depreciar."],
                ["Boton: Registrar depreciacion", "Ejecuta el calculo y registro contable."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Historial de depreciaciones"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Ano", "Ano fiscal del registro."],
                ["Mes", "Mes del registro."],
                ["Monto", "Monto depreciado en el periodo."],
                ["Dep. Acumulada", "Total acumulado hasta ese periodo."],
                ["Valor Libro", "Valor en libros despues de la depreciacion."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "La depreciacion es una operacion contable. Asegurese de seleccionar "
            "el periodo correcto (ano y mes) antes de registrar. Esta operacion genera "
            "un asiento contable automatico."
        )
    )

    # -- 13. Recepciones - Lista --
    e.extend(make_section_header("13. Recepciones de Bodega — Lista"))
    e.append(
        make_paragraph(
            "Pantalla para gestionar la confirmacion del ingreso fisico de productos "
            "provenientes de facturas de compra. Permite verificar que la mercaderia recibida "
            "coincida con lo facturado."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["#", "Numero de fila."],
                ["Factura Compra", "Numero de la factura de compra asociada."],
                ["Proveedor", "Nombre del proveedor que despacho la mercaderia."],
                ["Bodega", "Bodega destino donde se recibiran los productos."],
                ["Estado", "Pendiente, Completada o Parcial."],
                ["Fecha Recepcion", "Fecha en que se registro o completo la recepcion."],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Crear nueva recepcion"))
    e.append(
        make_paragraph(
            "La creacion de una recepcion se realiza en dos pasos mediante una ventana modal:"
        )
    )
    e.append(
        make_step_table(
            [
                ("1", "Busque y seleccione la factura de compra que desea recibir."),
                (
                    "2",
                    "Confirme la bodega destino y las cantidades de cada producto a recibir.",
                ),
            ]
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros"))
    e.append(
        make_field_table(
            ["Filtro", "Descripcion"],
            [
                ["Estado", "Filtrar por Pendiente, Completada o Parcial."],
                ["Fecha desde", "Fecha inicial del rango de busqueda."],
                ["Fecha hasta", "Fecha final del rango de busqueda."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_note_box(
            "Solo aparecen facturas de compra activas que tengan productos asociados "
            "pendientes de recepcion."
        )
    )

    # -- 14. Recepciones - Detalle --
    e.extend(make_section_header("14. Recepciones — Detalle"))
    e.append(
        make_paragraph(
            "Vista detallada de una recepcion especifica. Incluye la informacion de la factura, "
            "el progreso de verificacion y el sistema de escaneo de etiquetas."
        )
    )

    e.extend(make_subsection_header("Informacion general"))
    e.append(
        make_field_table(
            ["Dato", "Descripcion"],
            [
                ["Factura", "Numero de la factura de compra asociada."],
                ["Estado", "Estado actual de la recepcion."],
                ["Proveedor", "Nombre del proveedor."],
                ["Bodega", "Bodega destino de la recepcion."],
                ["Fecha recepcion", "Fecha del registro."],
                ["Recibido por", "Usuario que realizo la recepcion."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Escaneo de etiquetas"))
    e.append(
        make_paragraph(
            "Si la recepcion esta en estado Pendiente, se muestra un campo para escanear "
            "codigos de barras. La barra de progreso indica cuantas etiquetas han sido verificadas."
        )
    )
    e.append(
        make_field_table(
            ["Elemento", "Descripcion"],
            [
                ["Campo de escaneo", "Ingrese o escanee el codigo de barras de cada etiqueta."],
                [
                    "Barra de progreso",
                    "Muestra 'X de Y etiquetas verificadas' con porcentaje visual.",
                ],
                [
                    "Lista de productos",
                    "Productos agrupados con etiquetas individuales (estado OK o Pendiente).",
                ],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                [
                    "Confirmar recepcion",
                    "Solo habilitado cuando todas las etiquetas han sido verificadas. "
                    "Solicita confirmacion mediante dialogo.",
                ],
                ["Volver", "Regresa a la lista de recepciones."],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "La confirmacion de recepcion no se puede deshacer. "
            "Asegurese de haber escaneado todas las etiquetas antes de confirmar."
        )
    )

    # -- 15. Listas de Precio --
    e.extend(make_section_header("15. Listas de Precio"))
    e.append(
        make_paragraph(
            "Pantalla para gestionar precios especiales de PVP y PVD por producto. "
            "La edicion se realiza directamente sobre la tabla (edicion inline) haciendo clic en la celda."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Codigo", "Codigo del producto."],
                ["Nombre", "Nombre del producto."],
                ["Marca", "Marca del producto."],
                ["PVP Base", "Precio de venta al publico base (del catalogo)."],
                ["PVP Lista", "Precio PVP especial de la lista (editable)."],
                ["Desc. PVP%", "Porcentaje de descuento sobre PVP (editable)."],
                ["PVD Base", "Precio a distribuidor base (del catalogo)."],
                ["PVD Lista", "Precio PVD especial de la lista (editable)."],
                ["Desc. PVD%", "Porcentaje de descuento sobre PVD (editable)."],
                ["Vigencia Desde", "Fecha de inicio de vigencia (editable)."],
                ["Vigencia Hasta", "Fecha de fin de vigencia (editable)."],
            ],
            [1.2, 3.5],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtros y acciones"))
    e.append(
        make_field_table(
            ["Elemento", "Descripcion"],
            [
                ["Busqueda", "Filtra por codigo o nombre del producto."],
                ["Marca", "Filtro por marca."],
                ["Categoria", "Filtro por categoria."],
                ["Descargar Excel", "Descarga la plantilla con los precios actuales."],
                ["Importar Excel", "Carga masiva de precios desde un archivo Excel."],
            ],
            [1.3, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_note_box(
            "Haga clic en cualquier celda editable para modificar el valor. "
            "Al salir de la fila, los cambios se guardan automaticamente. "
            "Presione Enter para guardar o Escape para cancelar la edicion."
        )
    )

    # -- 16. Bodegas --
    e.extend(make_section_header("16. Configuracion — Bodegas"))
    e.append(
        make_paragraph(
            "Administracion de las bodegas del sistema. Cada bodega tiene un tipo que define "
            "su proposito y puede estar asociada a un centro de costo."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Nombre", "Nombre identificador de la bodega."],
                [
                    "Tipo",
                    "General, Importacion, Taller, Reserva o Cuarentena.",
                ],
                ["Centro de Costo", "Centro de costo al que pertenece la bodega."],
                ["Estado", "Activa o inactiva."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Formulario modal"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Nombre", "Si", "Nombre de la bodega."],
                [
                    "Tipo",
                    "Si",
                    "Tipo de bodega: General, Importacion, Taller, Reserva o Cuarentena.",
                ],
                ["Centro de costo", "No", "Centro de costo asociado (selector)."],
                ["Virtual", "—", "Interruptor que indica si la bodega es virtual (no fisica)."],
                ["Activa", "—", "Interruptor para activar o desactivar la bodega."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))
    e.append(
        make_warning_box(
            "No se puede eliminar una bodega que tiene productos con stock. "
            "Primero traslade toda la mercaderia a otra bodega."
        )
    )

    # -- 17. Categorias --
    e.extend(make_section_header("17. Configuracion — Categorias"))
    e.append(
        make_paragraph(
            "Gestion del arbol de categorias de productos. El sistema soporta un maximo de "
            "dos niveles de profundidad (categoria padre y subcategorias)."
        )
    )

    e.extend(make_subsection_header("Estructura de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                [
                    "Categoria",
                    "Nombre de la categoria. Las categorias padre son expandibles "
                    "para mostrar sus subcategorias.",
                ],
                ["Estado", "Activa o inactiva."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nueva Categoria", "Crea una categoria raiz."],
                ["Agregar subcategoria", "Crea una categoria hija bajo la seleccionada."],
                ["Editar", "Modifica el nombre y estado de la categoria."],
                ["Eliminar", "Elimina la categoria (si no tiene productos asociados)."],
            ],
            [1.5, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Formulario modal"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Nombre", "Si", "Nombre de la categoria."],
                [
                    "Categoria padre",
                    "No",
                    "Selector que muestra las categorias raiz. "
                    "Si se selecciona, la nueva categoria sera una subcategoria.",
                ],
                ["Activa", "—", "Interruptor para activar o desactivar."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 4))
    e.append(
        make_note_box(
            "Maximo 2 niveles de profundidad. Solo se muestran las categorias raiz "
            "como opciones de 'Categoria padre'."
        )
    )

    # -- 18. Marcas --
    e.extend(make_section_header("18. Configuracion — Marcas"))
    e.append(
        make_paragraph(
            "Administracion del catalogo de marcas de productos. "
            "Interfaz sencilla con tabla, busqueda y formulario modal."
        )
    )

    e.extend(make_subsection_header("Columnas de la tabla"))
    e.append(
        make_field_table(
            ["Columna", "Descripcion"],
            [
                ["Nombre", "Nombre de la marca."],
                ["Estado", "Activo o inactivo."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Acciones"))
    e.append(
        make_field_table(
            ["Accion", "Descripcion"],
            [
                ["Nueva Marca", "Abre el formulario modal para registrar una nueva marca."],
                ["Editar", "Modifica el nombre y estado de la marca."],
                ["Eliminar", "Elimina la marca (si no tiene productos asociados)."],
            ],
            [1.2, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Formulario modal"))
    e.append(
        make_field_table(
            ["Campo", "Obligatorio", "Descripcion"],
            [
                ["Nombre", "Si", "Nombre de la marca."],
                ["Activo", "—", "Interruptor para activar o desactivar la marca."],
            ],
            [1.3, 0.8, 3],
        )
    )
    e.append(Spacer(1, 6))

    e.extend(make_subsection_header("Filtro"))
    e.append(
        make_paragraph("Busqueda por nombre de marca con filtrado en tiempo real.")
    )
    e.append(Spacer(1, 4))
    e.append(
        make_warning_box(
            "No se puede eliminar una marca que tiene productos asociados. "
            "Primero reasigne los productos a otra marca o eliminelos."
        )
    )

    return e


# ===========================================================================
# Ejecucion principal
# ===========================================================================


def main():
    # Determinar ruta raiz del proyecto
    script_dir = Path(__file__).resolve().parent
    project_root = script_dir.parent
    output_dir = project_root / "storage" / "app" / "public" / "manuales"
    output_dir.mkdir(parents=True, exist_ok=True)

    manuals = [
        {
            "filename": "Manual_Configuracion.pdf",
            "module_name": "CONFIGURACION",
            "module_desc": "Usuarios, Permisos y Datos de Empresa",
            "builder": manual_configuracion,
        },
        {
            "filename": "Manual_Personas.pdf",
            "module_name": "PERSONAS",
            "module_desc": "Clientes, Proveedores y Transportistas",
            "builder": manual_personas,
        },
        {
            "filename": "Manual_Inventario.pdf",
            "module_name": "INVENTARIO",
            "module_desc": "Productos, Kardex, Traslados, Activos Fijos y Configuracion",
            "builder": manual_inventario,
        },
    ]

    for m in manuals:
        filepath = str(output_dir / m["filename"])
        print(f"Generando {m['filename']}...", end=" ", flush=True)
        try:
            build_doc(filepath, m["module_name"], m["module_desc"], m["builder"])
            print("OK")
        except Exception as exc:
            print(f"ERROR: {exc}")
            raise

    print(f"\nManuales generados en: {output_dir}")


if __name__ == "__main__":
    main()
