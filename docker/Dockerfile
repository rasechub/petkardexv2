# ============================================================
# PetKardex v2 — PHP 8.2 / Apache image
# ============================================================
FROM php:8.2-apache

LABEL maintainer="PetKardex Team"
LABEL description="PHP 8.2 + Apache image for PetKardex ERP veterinario"

# ── System dependencies ──────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
        curl \
        git \
    && rm -rf /var/lib/apt/lists/*

# ── Configure & install PHP extensions ──────────────────────
RUN docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        exif \
        xml

# ── Enable Apache modules ────────────────────────────────────
RUN a2enmod rewrite headers expires

# ── PHP configuration ────────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/petkardex.ini

# ── Apache virtual host ──────────────────────────────────────
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# ── Install Composer ─────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Working directory ────────────────────────────────────────
WORKDIR /var/www/html

# ── Permissions ──────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
