FROM php:7.4-fpm-bullseye

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libmcrypt-dev \
    zlib1g-dev \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    zip \
    intl \
    mbstring \
    xml \
    curl \
    bcmath \
    opcache \
    exif \
    soap \
    pcntl

# Install Redis extension
RUN pecl install redis-5.3.7 && docker-php-ext-enable redis

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Docker CLI is intentionally NOT installed here. Admin privileged operations
# (container start/stop, mysql exec, nginx reload, Caddy admin POSTs) go to
# cid-broker over a unix socket at /run/broker/broker.sock. cid-php74 has no
# access to /var/run/docker.sock — that's what makes a PHP RCE survivable.

# Create log directory
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# Set timezone
RUN ln -sf /usr/share/zoneinfo/Asia/Bangkok /etc/localtime

WORKDIR /var/www/sites

# Match the nginx alpine image's nginx group (GID 101) so the FPM unix socket
# created with listen.group=nginx is readable by the cid-nginx container.
RUN groupadd -g 101 nginx

# The base image ships zz-docker.conf with [global] daemonize=no AND a [www]
# listen=9000 that overrides our www.conf (alphabetical load order). Replace
# it with the daemonize directive only — listen is set in our www.conf.
RUN printf '[global]\ndaemonize = no\n' > /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 9000
