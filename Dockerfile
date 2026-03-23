FROM php:8.2-apache

# Installation des dépendances pour PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Copie de vos fichiers dans le serveur web
COPY . /var/www/html/

# Configurer Apache pour utiliser process.php au lieu de index.php
RUN echo "DirectoryIndex process.php" >> /etc/apache2/apache2.conf

EXPOSE 80
