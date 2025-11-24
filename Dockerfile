FROM php:8.2-apache

# Instalează extensiile necesare pentru conectarea la MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Activează modul rewrite pentru URL-uri (opțional, dar util)
RUN a2enmod rewrite