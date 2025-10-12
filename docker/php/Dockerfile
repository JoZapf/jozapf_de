# ---------------------------------------------------------------
# Multi-Stage Dockerfile für PHP-FPM
# - base: gemeinsame Extensions
# - dev:  Xdebug installiert (per ENV XDEBUG_MODE steuerbar → default off)
# - prod: ohne Xdebug, schlank
# ---------------------------------------------------------------
ARG PHP_TAG=8.3-fpm-alpine
FROM php:${PHP_TAG} AS base

RUN apk add --no-cache $PHPIZE_DEPS git curl libzip-dev icu-dev libpng-dev libjpeg-turbo-dev oniguruma-dev libxml2-dev         && docker-php-ext-configure gd --with-jpeg         && docker-php-ext-install -j$(nproc) intl zip pdo pdo_mysql gd         && php -v

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM base AS dev
RUN pecl install xdebug && docker-php-ext-enable xdebug

FROM base AS prod
# Keine Xdebug-Installation in prod
