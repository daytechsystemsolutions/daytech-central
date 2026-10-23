# Central DayTech: PHP 8.4 + nginx num container só (serversideup/php), sem root, porta 8080.
FROM serversideup/php:8.4-fpm-nginx-alpine AS base
USER root
COPY docker/05-banco.sh /etc/entrypoint.d/05-banco.sh
RUN chmod 755 /etc/entrypoint.d/05-banco.sh
USER www-data

# Local: o usuário do container vira o seu (arquivos montados sem problema de dono).
FROM base AS dev
USER root
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
 && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data

# Servidor: código dentro da imagem; storage/ vira volume (banco e logs).
FROM base AS producao
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false
COPY --chown=www-data:www-data . /var/www/html
USER www-data
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
 && rm -rf /var/www/html/tests ~/.composer/cache
