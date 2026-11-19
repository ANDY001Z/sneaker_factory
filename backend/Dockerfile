FROM php:8.2-fpm-alpine

# Instala dependencias y el driver PDO PostgreSQL
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www/html

#Usamos usuario no privilegiado para ejecutar la aplicación
USER www-data