FROM php:8.2-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN apt-get update && apt-get install -y libpq-dev libicu-dev libzip-dev unzip git && docker-php-ext-install intl pdo pdo_pgsql pgsql zip && a2enmod rewrite && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction && chown -R www-data:www-data /var/www/html/writable && chmod -R 775 /var/www/html/writable
EXPOSE 80