FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    bcmath \
    opcache

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]