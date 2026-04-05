FROM php:8.3-fpm-alpine

# ── System deps ───────────────────────────────────────────────────────────────
RUN apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        icu-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        ctype \
        fileinfo \
        bcmath \
        intl \
        opcache \
        gd \
        pcntl

# ── PHP config ─────────────────────────────────────────────────────────────────
COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/flamingdragon.ini"

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App ───────────────────────────────────────────────────────────────────────
WORKDIR /var/www

# Copy only dependency files first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy the rest of the application
COPY . .

# Finish composer (autoloader + scripts)
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true

# Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
