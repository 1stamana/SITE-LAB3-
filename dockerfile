FROM ubuntu:22.04

# Установка временной зоны
ENV TZ=Europe/Moscow
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Установка PHP 8.1 и зависимостей
RUN apt-get update && \
    apt-get install -y nginx php-fpm php-mysql php-cli php-curl php-gd php-mbstring php-xml php-xmlrpc php-zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Настройка PHP-FPM (оставляем Unix-сокет)
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.1/fpm/php.ini && \
    mkdir -p /run/php

# Копирование конфигов
COPY nginx.conf /etc/nginx/sites-available/default
COPY ./html /var/www/html

# Права доступа
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80

# Запуск сервисов
CMD service php8.1-fpm start && nginx -g "daemon off;"
