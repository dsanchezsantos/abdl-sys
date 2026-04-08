FROM php:8.4-fpm

# Arguments defined in docker-compose.yml
ARG user=www-data
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd intl zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Create system user to run Composer and Artisan Commands
# (Only if not using default www-data with mapped IDs, but helpful for permissions)
# Using default www-data for simplicity in this setup, ensuring permissions managed via volumes/host
# or simple UID mapping if needed. For now, running as root in container is often default in minimal setups,
# but let's stick to standard practice if possible or keep it simple.
# Given it's a dev setup, running as root is often easiest for file permissions on Windows mounts,
# but let's configure supervisor.

# Copy supervisor config
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install Nginx
RUN apt-get update && apt-get install -y nginx

# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Expose ports
EXPOSE 80

# Start Supervisor (which starts Nginx and PHP-FPM)
CMD ["/usr/bin/supervisord"]
