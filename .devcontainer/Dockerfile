FROM wordpress:6.9.1-php8.3-apache

RUN pecl install xdebug

RUN docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/
