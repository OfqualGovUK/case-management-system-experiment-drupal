FROM serversideup/php:8.4-fpm-apache-debian

USER root

RUN apt-get update && apt-get install -y \
    git unzip curl gnupg \
    libicu-dev libpng-dev libjpeg-dev libxml2-dev \
    libonig-dev pkg-config default-mysql-client \
 && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
 && apt-get install -y nodejs \
 && docker-php-ext-install intl gd mbstring pdo pdo_mysql xml \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data ./web ./web
COPY --chown=www-data:www-data ./config ./config

RUN mkdir -p web/sites/default/files

RUN find /var/www/html/. -type d -not -perm 2775 -exec chmod 2775 {} \; && \ 
    find /var/www/html/. -type f -not -perm 0664 -exec chmod 0664 {} \; && \ 
    find /var/www/html/. ! -user www-data -exec chown www-data:www-data {} \;  

USER www-data

RUN composer install --no-dev --optimize-autoloader --prefer-dist

COPY --chmod=755 entrypoint.sh /etc/entrypoint.d/10-drupal-setup.sh