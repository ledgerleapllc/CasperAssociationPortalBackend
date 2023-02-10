FROM php:8.0-fpm

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update -y &&\
    apt-get install -y --no-install-recommends libmcrypt-dev openssl libgmp-dev &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/* &&\
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer &&\
    docker-php-ext-install pdo pdo_mysql gmp pcntl

WORKDIR /project

COPY public ./public
COPY resources ./resources
COPY routes ./routes

EXPOSE 8000
CMD [ "/usr/local/bin/composer install --no-interaction && php artisan serve --host=0.0.0.0 --port=8000" ]
