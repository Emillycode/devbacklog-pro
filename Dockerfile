FROM php:8.2-apache

# 1. Instala pacotes do sistema necessários para comunicação HTTPS (curl, openssl e certificados)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    libcurl4-openssl-dev \
    openssl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# 2. Habilita extensões nativas do PHP necessárias
RUN docker-php-ext-install curl

# 3. Habilita módulos do Apache para segurança e suporte a .htaccess
RUN a2enmod rewrite headers

# 4. Permite que as regras de segurança do .htaccess funcionem no container
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 5. Ajusta limites de upload do PHP para suportar anexos de até 64MB
RUN printf "upload_max_filesize = 64M\npost_max_size = 64M\nmemory_limit = 256M\nmax_execution_time = 120\n" > /usr/local/etc/php/conf.d/uploads.ini

# 6. Copia os arquivos da aplicação para o Apache
WORKDIR /var/www/html
COPY . /var/www/html/

# 7. Garante permissões na pasta de uploads
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# 8. Suporte dinâmico à porta do Render
CMD sh -c "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && apache2-foreground"