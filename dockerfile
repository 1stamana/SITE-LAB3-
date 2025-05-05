# Базовый образ - Ubuntu 22.04
FROM ubuntu:22.04

# Установка временной зоны (Moscow)
ENV TZ=Europe/Moscow
# Создание симлинков для корректной работы временной зоны
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Установка PHP 8.1 и зависимостей
RUN apt-get update && \  # Обновление списка пакетов
    apt-get install -y nginx php-fpm php-mysql php-cli php-curl php-gd php-mbstring php-xml php-xmlrpc php-zip && \  # Установка пакетов
    apt-get clean && \  # Очистка кеша пакетов
    rm -rf /var/lib/apt/lists/*  # Удаление списков пакетов для уменьшения размера образа

# Настройка PHP-FPM (используем Unix-сокет вместо TCP)
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.1/fpm/php.ini && \  # Изменение параметра php.ini
    mkdir -p /run/php  # Создание директории для сокета PHP-FPM

# Копирование конфигурационных файлов
COPY nginx.conf /etc/nginx/sites-available/default  # Копирование конфига nginx
COPY ./html /var/www/html  # Копирование PHP файлов приложения

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html && \  # Изменение владельца файлов
    chmod -R 755 /var/www/html  # Установка прав доступа

# Открытие порта 80 для веб-сервера
EXPOSE 80

# Команда запуска при старте контейнера
CMD service php8.1-fpm start && nginx -g "daemon off;"  # Запуск PHP-FPM и nginx в foreground режиме
