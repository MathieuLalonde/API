# PHP 8.5 with FPM and PostgreSQL PDO
FROM php:8.5-fpm

# Install system dependencies, Postgres dev libs, git, zip/unzip
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (for convenience inside container)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Optional: set recommended PHP ini settings
# COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Expose FPM port (used by nginx service)
EXPOSE 9000
