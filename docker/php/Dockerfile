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

# Install Docker CLI (for admin dashboard) - static binary
RUN curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-28.0.1.tgz | tar xz -C /tmp \
    && mv /tmp/docker/docker /usr/local/bin/docker \
    && rm -rf /tmp/docker \
    && chmod +x /usr/local/bin/docker

# Create log directory
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# Set timezone
RUN ln -sf /usr/share/zoneinfo/Asia/Bangkok /etc/localtime

WORKDIR /var/www/sites

# Add www-data to docker group (GID 112 on host) for socket access
RUN groupadd -g 112 dockerhost && usermod -aG dockerhost www-data

EXPOSE 9000
