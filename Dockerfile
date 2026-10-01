# Stage 1: Build frontend assets (Node 22 LTS + npm 10+)
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP 8.4 & Apache Web Server
FROM php:8.4-apache

# Install system dependencies and PHP extensions (including SQLite)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer 2.x
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Copy compiled Vite/Tailwind assets from Stage 1
COPY --from=frontend /app/public/build /var/www/html/public/build

# Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Point Apache's document root to Laravel's /public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Prepare SQLite database file and assign write permissions to Apache
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run migrations and start Apache (dynamically binding Render's $PORT)
CMD ["sh", "-c", "php artisan optimize:clear && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database && chmod -R 775 /var/www/html/storage /var/www/html/database && php artisan migrate:fresh --seed --force && apache2-foreground"]