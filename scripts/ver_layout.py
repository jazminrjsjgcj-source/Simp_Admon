#!/usr/bin/env python3
"""Vuelca el texto con layout de las páginas del catálogo, para diseñar el parser."""
import sys
import pdfplumber

pdf = pdfplumber.open(sys.argv[1])
# Páginas del catálogo. La 44 tiene artículos con VARIAS clases (58 con A,B,C,D).
for idx in [43, 44]:
    print(f"########## PÁGINA {idx + 1} ##########")
    p = pdf.pages[idx]
    texto = p.extract_text(layout=True) or ""
    for i, linea in enumerate(texto.split("\n")):
        if linea.strip():
            print(f"{i:3}| {linea}")
