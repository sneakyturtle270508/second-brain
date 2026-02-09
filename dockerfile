FROM php:8.2-apache

# Apache rewrite for Laravel/Statamic routes
RUN a2enmod rewrite

# System deps + PHP extensions
RUN apt-get update && apt-get install -y \
  git unzip libzip-dev \
  && docker-php-ext-install zip pdo pdo_mysql \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Point Apache web root to /public
RUN sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

# Permissions for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Cache (tåler at enkelte feiler hvis app key mangler i build)
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

EXPOSE 80
