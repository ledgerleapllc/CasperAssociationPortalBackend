FROM php:8.0-fpm

RUN apt-get update -y && apt-get install -y libmcrypt-dev openssl git unzip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /project
COPY . /project

CMD composer install --no-interaction && php artisan serve --host=0.0.0.0 --port=8000
EXPOSE 8000