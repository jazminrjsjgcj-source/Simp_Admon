#!/usr/bin/env python3
"""
Extrae las tablas de un PDF y las devuelve como JSON estructurado.

═══════════════════════════════════════════════════════════════════════════
POR QUÉ EXISTE ESTE SCRIPT
═══════════════════════════════════════════════════════════════════════════

pdftotext -raw (lo que usa el sistema para el texto) DESTRUYE las tablas: las
aplana a texto en orden de flujo, y una cuadrícula pierde qué celda va con qué
fila. El catálogo de infracciones del Bando ("artículo 65 → Clase D") salía
como "65 D" suelto entre pies de página, ilegible para el buscador.

Este script usa una librería especializada (pdfplumber) que analiza la POSICIÓN
del texto en la página y reconstruye las filas y columnas. Es un proceso aparte,
en Python, porque las buenas herramientas de extracción de tablas son de ese
ecosistema; PHP no tiene equivalente.

═══════════════════════════════════════════════════════════════════════════
CONTRATO (lo que PHP espera de este script)
═══════════════════════════════════════════════════════════════════════════

Entrada:   argv[1] = ruta a un PDF
Salida:    JSON por stdout, SIEMPRE. Nunca texto suelto, nunca un traceback a
           pelo. Si algo falla, un JSON con la clave "error" y lista vacía de
           tablas, para que PHP lo lea sin romperse.

Formato de salida:
    {
      "ok": true,
      "herramienta": "pdfplumber",
      "tablas": [
        {
          "pagina": 42,
          "filas": [
            ["Infracciones contra", "Artículo", "Fracción", "Clase"],
            ["EL ORDEN PÚBLICO", "58", "-", "A"],
            ...
          ]
        }
      ]
    }

═══════════════════════════════════════════════════════════════════════════
LA HERRAMIENTA ESTÁ AISLADA A PROPÓSITO
═══════════════════════════════════════════════════════════════════════════

Toda la extracción vive en extraer_con_pdfplumber(). Si algún día pdfplumber se
queda corto con las tablas anidadas (las de zonas del art. 89, que son duras) y
hay que pasar a camelot, SOLO se cambia esa función. El contrato con PHP —recibe
ruta, devuelve JSON— no cambia. Esa frontera es lo que hace el cambio barato.
"""

import json
import sys


HERRAMIENTA = "pdfplumber"

# ── Supuesto de FORMA de la tabla (lo que se cambia para otro formato) ───────
#
# El catálogo del Bando usa clases de UNA sola letra, A–E. Es el supuesto más
# específico del script. Si otra ley clasifica con letras hasta la F, o con otro
# rango, se cambia AQUÍ, en un solo sitio, en vez de tocar un regex enterrado.
#
# Ojo con el alcance: esto configura el RANGO de letras, no la forma. Clases con
# nombres ("Grave", "Leve") o columnas dispuestas distinto serían otro formato y
# requerirían más que cambiar esta línea —empezando por extraer_con_pdfplumber().
CLASES_CATALOGO = "A-E"


def extraer_con_pdfplumber(ruta_pdf):
    """
    Extrae las tablas de clasificación (artículo → clase) parseando el TEXTO CON LAYOUT de las
    páginas que contienen una tabla.

    ── Por qué se parsea el layout y NO find_tables ──

    find_tables de pdfplumber es INCONSISTENTE entre páginas: en una página separa bien las
    columnas ("70" | "D"), y en la de al lado las funde en una sola celda ("65 D"), según cómo
    de juntas estén las columnas. Medido en el Bando: página 45 salía separada, página 46 fundida.

    El TEXTO CON LAYOUT (extract_text(layout=True)), en cambio, conserva las posiciones reales y
    muestra SIEMPRE el número y la clase separados por espacios:

            65                             D
            66                             D

    Eso es trivial y CONSISTENTE de parsear con una expresión regular. Es más robusto que
    depender de que find_tables acierte la separación de columnas página a página.

    ── Qué extrae ──

    Pares (artículo, clase) de las tablas de clasificación tipo el Catálogo de Infracciones del
    Bando. Es genérico para esa forma de tabla —número de artículo + letra de clase—, que es la
    que conecta conductas con sanciones y la que el buscador necesita para el cruce de cadenas.
    """
    import pdfplumber
    import re

    # Una línea del catálogo termina en una CLASE: una letra (del rango
    # CLASES_CATALOGO, ver arriba) AISLADA, precedida de un hueco de columna
    # (varios espacios). El hueco es lo que la distingue del final de un nombre de
    # sección: exigir 2+ espacios antes de la letra evita casar "...DECORO PÚBLICO"
    # como si su última letra fuera una clase.
    linea_con_clase = re.compile(rf"\s{{2,}}([{CLASES_CATALOGO}])\s*$")
    # Números de artículo que aparezcan en la línea (uno, o "76, 77").
    numeros_en_linea = re.compile(r"\b(\d{1,3})\b")

    tablas = []
    # El artículo vigente SE ARRASTRA ENTRE PÁGINAS del mismo catálogo: un artículo puede empezar
    # al final de una página y continuar sus clases al principio de la siguiente (el 69 empieza en
    # la 44 y sigue en la 45). Si se reiniciara por página, esas clases quedarían huérfanas.
    articulos_vigentes = []

    with pdfplumber.open(ruta_pdf) as pdf:
        catalogo_filas = []          # todas las filas del catálogo, a través de las páginas
        paginas_catalogo = []        # qué páginas aportaron

        for numero, pagina in enumerate(pdf.pages, start=1):
            if not _pagina_tiene_tabla(pagina):
                # Una página SIN tabla corta la continuidad: el artículo vigente ya no aplica
                # (empieza otra zona del documento). Se resetea para no arrastrar a una tabla
                # lejana que aparezca páginas después.
                articulos_vigentes = []
                continue

            texto = pagina.extract_text(layout=True) or ""
            hubo_fila = False

            for linea in texto.split("\n"):
                if not linea.strip():
                    continue

                m = linea_con_clase.search(linea)
                if not m:
                    nums = numeros_en_linea.findall(linea)
                    if nums and len(linea.strip()) <= 12:
                        articulos_vigentes = nums
                    continue

                clase = m.group(1).strip()

                nums = numeros_en_linea.findall(linea)
                if nums:
                    articulos_vigentes = nums

                # ¿Es un NOMBRE DE SECCIÓN que casualmente termina en letra de clase?
                # Lo es si tiene PALABRAS de prosa Y NO trae número de artículo. El "sin
                # número" es clave: "LA PROPIEDAD 75 - A" tiene palabras pero también el
                # artículo 75 → es fila real. En cambio "LA BUENA PRESTACIÓN…  A" no trae
                # número → es solo encabezado, y arrastraría el artículo anterior.
                # (Una clase suelta "  B" o fracciones "III, VII  C" no tienen palabras.)
                sin_clase = linea[:m.start()]
                palabras = re.findall(r"[A-Za-zÁÉÍÓÚÑÜáéíóúñü]+", sin_clase)
                es_encabezado = (not nums) and any(not re.fullmatch(r"[IVXLCDM]+", p) for p in palabras)
                if es_encabezado:
                    articulos_vigentes = []
                    continue

                for art in articulos_vigentes:
                    catalogo_filas.append([art, clase])
                    hubo_fila = True

            if hubo_fila:
                paginas_catalogo.append(numero)

        # Deduplicar manteniendo orden.
        vistos = set()
        filas_unicas = []
        for f in catalogo_filas:
            key = (f[0], f[1])
            if key not in vistos:
                vistos.add(key)
                filas_unicas.append(f)

        # Filtrar NÚMEROS DE PÁGINA colados como artículos. La tabla vive en las
        # páginas de `paginas_catalogo`; un pie de página con ese número (p. ej. la
        # "44") se cuela como ["44", "B"]. Los artículos del catálogo caen fuera del
        # rango de páginas, así que se descartan las filas cuyo número coincide con
        # una página. Riesgo aceptado: si un artículo real coincidiera con un número
        # de página, se perdería ese par —preferible a inyectar una clase falsa—.
        paginas_set = set(paginas_catalogo)
        filas_unicas = [
            f for f in filas_unicas
            if not (f[0].isdigit() and int(f[0]) in paginas_set)
        ]

        pares_articulo = sum(1 for f in filas_unicas if f[0].isdigit())
        if pares_articulo >= 2:
            tablas.append({
                "pagina": paginas_catalogo[0] if paginas_catalogo else 0,
                "paginas": paginas_catalogo,
                "filas": filas_unicas,
            })

    return tablas


def _pagina_tiene_tabla(pagina):
    """
    ¿Esta página contiene una tabla, o es texto normal? Filtro BARATO que se corre antes de la
    detección cara, para no aplicar el modo "text" (que trocea) a páginas de prosa.

    La firma de una página con tabla: muchas líneas CORTAS con grandes huecos de espacios en
    medio (columnas alineadas). Una página de prosa tiene líneas largas y llenas, sin huecos
    internos grandes. Se mide sobre el texto plano de la página.

    Es genérico: no busca "CATÁLOGO" ni nada del Bando. Detecta la geometría de una tabla
    —columnas separadas por espacios— sirva para el catálogo, una tarifa, o lo que venga.
    """
    # extract_text(layout=True) es CLAVE: el modo normal COLAPSA los espacios, así que los
    # huecos entre columnas desaparecen y ninguna página parecería tener tabla. Con layout=True
    # se conservan las posiciones, y los huecos de columna se ven. (Diagnosticado midiendo: sin
    # layout, prop=0.00 en todo el documento; con layout, la zona del catálogo sube a 0.15-0.47.)
    texto = pagina.extract_text(layout=True) or ""
    lineas = [ln for ln in texto.split("\n") if ln.strip() != ""]

    if len(lineas) < 3:
        return False

    # Una línea "de tabla" tiene un hueco grande de espacios en medio (el salto entre columnas).
    # Se cuenta cuántas líneas de la página tienen ese patrón.
    import re
    lineas_con_hueco = 0
    for ln in lineas:
        # Un hueco de 4+ espacios entre dos trozos de texto = salto entre columnas.
        if re.search(r"\S {4,}\S", ln):
            lineas_con_hueco += 1

    # Umbral 0.10: la página del catálogo del Bando mide 0.15 (parte de la página es tabla, parte
    # es texto y pies de página). Un umbral de 0.20 la descartaría —y con ella el catálogo—. 0.10
    # es tolerante: mejor examinar una página de más (el filtro de calidad depura después) que
    # perder una tabla. Medido sobre las páginas reales del Bando.
    return (lineas_con_hueco / len(lineas)) >= 0.10


def _caja_ya_vista(caja, vistas, margen=10):
    """¿Esta caja delimitadora solapa con una ya registrada? Evita duplicar una
    tabla que los dos modos de detección encuentran en el mismo sitio."""
    x0, top, x1, bottom = caja
    for vx0, vtop, vx1, vbottom in vistas:
        if (abs(x0 - vx0) < margen and abs(top - vtop) < margen
                and abs(x1 - vx1) < margen and abs(bottom - vbottom) < margen):
            return True
    return False


def _limpiar_filas(filas):
    """Normaliza las celdas: None → '', colapsa espacios y saltos de línea
    internos (pdfplumber a veces mete '\\n' dentro de una celda multi-línea),
    y descarta filas totalmente vacías."""
    limpias = []
    for fila in filas:
        celdas = []
        for celda in fila:
            texto = (celda or "").replace("\n", " ").strip()
            texto = " ".join(texto.split())  # colapsa espacios repetidos
            celdas.append(texto)

        # Fila totalmente vacía → fuera.
        if any(c != "" for c in celdas):
            limpias.append(celdas)

    return limpias


def _parece_tabla_de_datos(filas):
    """
    ¿Esto es una tabla de DATOS, o un párrafo/membrete que pdfplumber confundió con tabla?

    Se diseñó mirando el ruido real del Bando. pdfplumber en modo "text" detecta como
    "tabla" casi cada página: los membretes ("BANDO DE POLICÍA, BUEN GOBIERNO...") y el texto
    justificado parecen columnas. De 60 páginas salían 63 "tablas", casi todas basura.

    La firma que separa el grano de la paja NO es el número de filas (el ruido tiene tantas
    como el catálogo), sino la NATURALEZA DE LAS CELDAS:

      · Tabla de datos:   celdas cortas y atómicas — "65", "D", "A", "2.00", "III".
      · Párrafo/membrete: celdas que son frases largas o fragmentos de palabras partidas —
                          "BANDO DE POLICÍA, BUEN GOBIERNO", "H. AYUNTA", "MIENTO DE LA".

    Regla: una tabla de datos tiene una proporción alta de celdas cortas y una proporción baja
    de celdas-frase. Es genérica —no busca "clase A-D" ni nada del Bando—: sirve para el
    catálogo, para tarifas (números), para cualquier tabla cuyo contenido sean datos y no prosa.
    """
    total_celdas = 0
    celdas_cortas = 0   # 1-6 caracteres, sin espacios: el ADN de una celda-dato
    celdas_frase = 0    # con varias palabras: el ADN de un párrafo

    for fila in filas:
        for celda in fila:
            c = celda.strip()
            if c == "":
                continue
            total_celdas += 1

            palabras = c.split()
            if len(c) <= 6 and len(palabras) == 1:
                celdas_cortas += 1
            elif len(palabras) >= 4:
                # Cuatro o más palabras en una celda = frase = párrafo, no dato.
                celdas_frase += 1

    if total_celdas == 0:
        return False

    prop_cortas = celdas_cortas / total_celdas
    prop_frase  = celdas_frase / total_celdas

    # Una tabla de datos: al menos un cuarto de sus celdas son atómicas, y casi ninguna es
    # una frase. Los umbrales son deliberadamente tolerantes —mejor dejar pasar una tabla
    # dudosa que perder el catálogo—; el asistente y el servidor público filtran después.
    return prop_cortas >= 0.25 and prop_frase <= 0.15


def main():
    # El contrato: SIEMPRE se devuelve JSON por stdout, pase lo que pase. PHP
    # lee stdout y espera JSON; un traceback a pelo rompería esa lectura.
    if len(sys.argv) < 2:
        print(json.dumps({
            "ok": False,
            "error": "falta la ruta del PDF (uso: extraer_tablas.py archivo.pdf)",
            "tablas": [],
        }, ensure_ascii=False))
        return

    ruta_pdf = sys.argv[1]

    try:
        tablas = extraer_con_pdfplumber(ruta_pdf)
        print(json.dumps({
            "ok": True,
            "herramienta": HERRAMIENTA,
            "tablas": tablas,
        }, ensure_ascii=False))

    except ImportError:
        # pdfplumber no está instalado. PHP debe seguir sin tablas, no romperse.
        print(json.dumps({
            "ok": False,
            "error": "pdfplumber no está instalado (pip install pdfplumber)",
            "tablas": [],
        }, ensure_ascii=False))

    except Exception as e:
        # Cualquier otro fallo (PDF corrupto, sin permisos...). Se informa en
        # JSON, con lista vacía, para que el llamador siga como si no hubiera
        # tablas: es una mejora, nunca un requisito.
        print(json.dumps({
            "ok": False,
            "error": f"{type(e).__name__}: {e}",
            "tablas": [],
        }, ensure_ascii=False))


if __name__ == "__main__":
    main()
