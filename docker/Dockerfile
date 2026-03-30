# Multi-stage Dockerfile for Laravel API (Saudi Legal Orchestrator)
# PHP 8.4 + Nginx

FROM php:8.4-fpm-alpine AS base

# Install system dependencies (including GD for mpdf)
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions (including GD for mpdf)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl opcache pcntl gd

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copy application
COPY --chown=www-data:www-data . .

# Install Composer dependencies (production only)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend (Tailwind/Vite need vendor and app for content paths)
RUN npm ci && npm run build && apk del nodejs npm

# Final stage
FROM base AS production

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm & nginx -g 'daemon off;'"]
