# Multi-stage Dockerfile for ChurchCRM
# Build with: docker build --target dev|test -t churchcrm/crm:php8-debian-[dev|test] .

ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache AS base
LABEL maintainer="george@dawouds.com"

EXPOSE 80

# Install common PHP dependencies
RUN apt-get update && \
    apt-get install -y \
        libxml2-dev \
        gettext \
        locales \
        locales-all \
        libpng-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg-dev \
        git \
    && rm -rf /var/lib/apt/lists/*

# Install and configure PHP extensions
RUN docker-php-ext-install -j$(nproc) xml exif pdo_mysql gettext iconv mysqli zip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) gd

# Configure Apache
COPY ./apache/default.conf /etc/apache2/apache2.conf
RUN a2enmod rewrite

# Configure PHP settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i 's/^upload_max_filesize.*$/upload_max_filesize = 2M/g' $PHP_INI_DIR/php.ini && \
    sed -i 's/^post_max_size.*$/post_max_size = 2G/g' $PHP_INI_DIR/php.ini && \
    sed -i 's/^memory_limit.*$/memory_limit = 2G/g' $PHP_INI_DIR/php.ini && \
    sed -i 's/^max_execution_time.*$/max_execution_time = 120/g' $PHP_INI_DIR/php.ini

# Create non-root user
RUN groupadd -r www && useradd -r -g www www


# Test stage - minimal runtime environment for CI/CD
FROM base AS test
# Note: Apache binding to port 80 requires root privileges, so USER directive is omitted


# Dev stage - full development environment with build tools
FROM base AS dev

# Install development dependencies
RUN apt-get update && \
    apt-get install -y \
        make \
        python3 \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug for debugging
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Composer with checksum verification
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); }" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php

# Install NVM and Node.js 24 (aligned with CI and package.json engines)
RUN curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.5/install.sh -o /opt/node-install.sh && \
    chmod a+x /opt/node-install.sh && \
    /opt/node-install.sh && \
    rm /opt/node-install.sh && \
    /bin/bash -c "source /root/.nvm/nvm.sh && nvm install 24 && npm install -g node-gyp"
