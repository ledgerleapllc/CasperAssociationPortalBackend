
# TODO: Casper client install. Maybe not possible on Alpine. Do we really need it? Alternatives?
#   Maybe I can build it on a Rust container aside and use the gcompat layer on the final image. WIP
# TODO: Removing .env
#   Tom to add the change to handle .env file being present with a try catch.
# TODO: Release casper-client-rs as an Alpine package to use in this image
#   Release the amd64 binary to be used in Alpine as well with the gcompat layer
# TODO: Figure out extensions if needed in the running image after composer install
#   Tom to check if we need any extensions
# TODO: phpdoc removed. Binary file?
#   Don't need that in prod. Used in development environment.
# TODO: wizard.py?
#   Not neede here. Deleted.
# TODO: Crontab done automatically, but do I need to enable it like in the README?
#   There needs to be just one instance (separate service) that runs the crontab.
# TODO: Discuss running tests with composer? Laravel tests still accurate?
#   Tom to check the tests
# TODO: NGINX config migrate .htaccess to nginx.conf
#   Luca
# TODO: Lock versions of all packages and tools and make them easier to manage (add variable definitions)

FROM composer:2.5.4 AS build-php

WORKDIR /app
COPY . ./

ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/2.0.2/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd && \
    composer install --optimize-autoloader --no-interaction --no-progress --no-ansi

FROM rust:1.67.1-slim-bullseye as build-casper

# apk add --no-cache curl=7.87.0-r2 build-base autoconf automake libtool openssl-dev musl-dev && \
#    rm -rf /var/cache/apk/* && \

RUN apt-get update &&\
    apt-get install --no-install-recommends -y curl pkg-config libssl-dev build-essential libsodium-dev &&\
    apt-get clean &&\
    rm -rf /var/lib/apt/lists/* &&\
    curl -L https://github.com/casper-ecosystem/casper-client-rs/archive/refs/tags/v1.5.0.tar.gz -o casper-client.tar.gz && \
    tar -xvf casper-client.tar.gz -C /tmp && \
    rm casper-client.tar.gz

WORKDIR /tmp/casper-client-rs-1.5.0

# export RUSTFLAGS="-L /usr/lib" &&\
RUN cargo build --release

FROM trafex/php-nginx:3.0.0 AS serve

USER root

RUN apk add --no-cache gcompat &&\
    mkdir -p /app && \
    chown -R nginx:nginx /app &&\

COPY --chown=nginx --from=build-casper /tmp/casper-client-rs-1.5.0/target/release/casper-client /usr/local/bin
RUN chmod +x /usr/local/bin/casper-client
COPY --chown=nginx --from=build-php ./app/public /app/public
COPY --chown=nginx --from=build-php ./app/core.php /app/core.php
COPY --chown=nginx --from=build-php ./app/templates /app/templates
COPY --chown=nginx --from=build-php ./app/spreadsheets /app/spreadsheets
COPY --chown=nginx --from=build-php ./app/crontab /app/crontab
COPY --chown=nginx --from=build-php ./app/classes /app/classes

USER nobody