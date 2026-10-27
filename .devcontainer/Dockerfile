FROM wordpress:7.1.3-php8.5-apache

RUN apt-get update && apt-get install -y subversion

RUN pecl install xdebug

RUN docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/
