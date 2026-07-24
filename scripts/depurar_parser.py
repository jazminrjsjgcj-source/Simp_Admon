#!/usr/bin/env python3
"""Depura el parseo del catálogo: muestra qué decide el parser en cada línea con clase."""
import re
import sys
import pdfplumber

linea_con_clase = re.compile(r"[A-E]\s*$")
numeros_en_linea = re.compile(r"\b(\d{1,3})\b")

pdf = pdfplumber.open(sys.argv[1])
for idx in [43, 44]:
    print(f"########## PÁGINA {idx + 1} ##########")
    p = pdf.pages[idx]
    texto = p.extract_text(layout=True) or ""
    articulos_vigentes = []

    for linea in texto.split("\n"):
        if not linea.strip():
            continue

        m = linea_con_clase.search(linea)
        if not m:
            nums = numeros_en_linea.findall(linea)
            if nums and len(linea.strip()) <= 12:
                articulos_vigentes = nums
                print(f"  [NUM-SOLO] arts={nums}  <- {linea.strip()[:40]}")
            continue

        clase = m.group(0).strip()
        nums = numeros_en_linea.findall(linea)
        if nums:
            articulos_vigentes = nums
        marca = "NUEVO-ART" if nums else "ARRASTRA"
        print(f"  [CLASE {clase}] {marca} arts={articulos_vigentes}  <- {linea.strip()[:45]}")
