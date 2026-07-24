#!/usr/bin/env python3
"""
Diagnóstico: ¿por qué el filtro de página descarta el catálogo?

Compara, para las páginas del catálogo, cómo ve los huecos de columna el texto SIN layout
(extract_text normal) frente al texto CON layout (extract_text(layout=True)). La hipótesis es
que el modo normal colapsa los espacios y el filtro no ve las columnas.

Uso:  python3 scripts/diagnostico_tablas.py RUTA_PDF
"""

import re
import sys

import pdfplumber


def huecos(texto):
    lineas = [l for l in texto.split("\n") if l.strip()]
    if not lineas:
        return 0, 0, 0.0
    con_hueco = sum(1 for l in lineas if re.search(r"\S {4,}\S", l))
    return len(lineas), con_hueco, con_hueco / len(lineas)


def main():
    ruta = sys.argv[1]
    pdf = pdfplumber.open(ruta)

    # Se examinan las páginas alrededor del catálogo (índice 0-based, así que la página 45 es 44).
    for n in [43, 44, 45, 46]:
        if n >= len(pdf.pages):
            continue
        p = pdf.pages[n]

        sin_layout = p.extract_text() or ""
        con_layout = p.extract_text(layout=True) or ""

        l1, h1, p1 = huecos(sin_layout)
        l2, h2, p2 = huecos(con_layout)

        print(f"=== pagina {n + 1} ===")
        print(f"  SIN layout: lineas={l1} con_hueco={h1} prop={p1:.2f} pasa={p1 >= 0.10}")
        print(f"  CON layout: lineas={l2} con_hueco={h2} prop={p2:.2f} pasa={p2 >= 0.10}")

        # ¿Qué extrae pdfplumber de esta página con los ajustes reales?
        ajustes = {
            "vertical_strategy": "text",
            "horizontal_strategy": "text",
            "text_keep_blank_chars": True,
            "min_words_vertical": 4,
            "min_words_horizontal": 1,
            "snap_tolerance": 4,
        }
        encontradas = p.find_tables(table_settings=ajustes)
        print(f"  pdfplumber encontró {len(encontradas)} tabla(s) en esta página")
        for i, tabla in enumerate(encontradas):
            filas = tabla.extract()
            print(f"    tabla {i}: {len(filas)} filas crudas")
            # Muestra las filas que tengan un 65
            for fila in filas:
                if fila and "65" in str(fila):
                    print(f"      fila con 65: {[ (c or '').strip() for c in fila ]}")


if __name__ == "__main__":
    main()
