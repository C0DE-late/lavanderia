# Imagen de la app de Lavandería: PHP 8.3 + Apache.
FROM php:8.3-apache

# Extensiones: PDO MySQL y GD (JPEG, PNG y WEBP para reducir las fotos).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*

# Configuración de PHP para producción y límites de subida de fotos.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "upload_max_filesize=8M\npost_max_size=64M\nmax_file_uploads=40\nexpose_php=Off\ndate.timezone=America/Bogota\n" \
       > "$PHP_INI_DIR/conf.d/lavanderia.ini"

# Permite que los .htaccess del proyecto bloqueen config, app/, instalar/ y almacen/.
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\nServerTokens Prod\nServerSignature Off\n' \
       > /etc/apache2/conf-enabled/lavanderia.conf

COPY --chown=www-data:www-data . /var/www/html/
RUN mkdir -p /var/www/html/almacen/fotos && chown -R www-data:www-data /var/www/html/almacen

EXPOSE 80
