# PHP 8.3 con todo lo que PUNTA necesita para correr como en el servidor Linux.
#
# Incluye:
#   - Las extensiones de PHP (con pdo_pgsql, la que falta en el PHP de Windows).
#   - LibreOffice, que es el motor de conversión de documentos (Word/PDF) que usa
#     PdfConversorService. Sin él, la conversión falla y el reestructurador recibe
#     el texto mal extraído (las fracciones salen incompletas).
#   - poppler-utils (pdftotext) para extraer texto de PDFs.
FROM php:8.3-cli

# Dependencias del sistema.
#   libreoffice-writer → conversión de documentos (soffice). Es la pieza que usa
#       el conversor; se instala solo Writer (no toda la suite) para no inflar la
#       imagen: es lo único que hace falta para documentos de texto.
#   poppler-utils      → pdftotext, para extraer texto de PDFs.
#   fonts-liberation   → fuentes base, para que la conversión no salga con cajas.
RUN apt-get update && apt-get install -y \
    git unzip zip \
    libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    libreoffice-writer \
    poppler-utils \
    fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP:
#   pdo_pgsql / pgsql → PostgreSQL (las que faltan en el PHP de Windows)
#   pdo_mysql         → por si se quiere comparar contra MySQL
#   zip, gd, mbstring, xml, bcmath → las que usan Laravel y las librerías del proyecto
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql pdo_mysql zip gd mbstring xml bcmath opcache

# Composer, para instalar dependencias dentro del contenedor.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
