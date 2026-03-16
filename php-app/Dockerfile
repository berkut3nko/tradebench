FROM php:8.2-fpm

# Install system dependencies 
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libz-dev \
    libpq-dev \
    libssl-dev \
    pkg-config \
    zlib1g-dev \
    protobuf-compiler \
    && rm -rf /var/lib/apt/lists/*

# Install the PHP extension installer to handle complex extensions easily 
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install extensions. This tool is much faster and handles dependencies automatically 
# It also uses multiple CPU cores for compilation by default 
RUN install-php-extensions grpc protobuf pdo_pgsql

# Install Composer 
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]