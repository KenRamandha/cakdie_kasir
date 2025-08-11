FROM php:8.4-fpm-alpine

# Install essential dependencies and PHP extensions
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring gd zip \
    && apk del libpng-dev libzip-dev oniguruma-dev

# Get latest Composer
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Configure PHP-FPM for low memory
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "pm = static" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 2" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_requests = 200" >> /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies with memory optimization
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-cache

# Copy application code
COPY . .

# Set permissions and optimize
RUN chown -R www-data:www-data storage bootstrap/cache \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 9000

CMD ["php-fpm"]
