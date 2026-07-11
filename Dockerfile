# PHP 8.3 con las extensiones que Laravel y PostgreSQL necesitan.
# Es la misma base que se usaría en un servidor Linux, así que lo que
# funcione aquí funcionará en producción.
FROM php:8.3-cli

# Dependencias del sistema para compilar las extensiones de PHP.
RUN apt-get update && apt-get install -y \
    git unzip zip libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP:
#   pdo_pgsql / pgsql → PostgreSQL (las que faltan en el PHP de Windows)
#   pdo_mysql         → por si se quiere volver a MySQL para comparar
#   zip, gd, mbstring, xml, bcmath → las que usa Laravel y las librerías del proyecto
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql pdo_mysql zip gd mbstring xml bcmath opcache

# Composer (para instalar las dependencias del proyecto dentro del contenedor).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
