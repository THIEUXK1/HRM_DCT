FROM php:8.3-fpm-bookworm
# Microsoft ODBC Driver 18 + PHP SQL Server drivers + sockets
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        curl \
        gnupg \
        ca-certificates \
        apt-transport-https \
        unixodbc-dev \
        libgssapi-krb5-2; \
    curl -fsSL -o /tmp/packages-microsoft-prod.deb https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb; \
    dpkg -i /tmp/packages-microsoft-prod.deb; \
    rm /tmp/packages-microsoft-prod.deb; \
    apt-get update; \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18; \
    docker-php-ext-install sockets; \
    pecl channel-update pecl.php.net; \
    pecl install sqlsrv pdo_sqlsrv; \
    docker-php-ext-enable sqlsrv pdo_sqlsrv; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd bcmath zip pdo_pgsql pdo_sqlite opcache sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install SQL Server drivers via PECL
RUN set -eux; \
    pecl channel-update pecl.php.net; \
    pecl install sqlsrv pdo_sqlsrv; \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js & NPM
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Set working directory
WORKDIR /var/www/hrm

EXPOSE 9000

CMD ["php-fpm"]
