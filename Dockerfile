FROM php:7.4-apache

# Build argument for environment
ARG BUILD_ENV=production

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        gd \
        zip \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Create data directory for SQLite
RUN mkdir -p /var/lib/simplemappr \
    && chown -R www-data:www-data /var/lib/simplemappr

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN if [ "$BUILD_ENV" = "production" ]; then \
        composer install --no-dev --optimize-autoloader --no-scripts; \
    else \
        composer install --no-scripts; \
    fi

# Copy application code
COPY --chown=www-data:www-data . .

# Run composer scripts after code is copied
RUN composer dump-autoload

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create temp upload directory
RUN mkdir -p /var/www/html/tmp \
    && chown www-data:www-data /var/www/html/tmp \
    && chmod 775 /var/www/html/tmp

EXPOSE 80

CMD ["apache2-foreground"]
