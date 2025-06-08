FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libonig-dev libxml2-dev libzip-dev supervisor

RUN docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl

RUN pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/log/supervisor
COPY docker/php/supervisor.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www
