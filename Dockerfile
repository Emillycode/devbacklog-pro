FROM php:8.2-apache

# Habilita módulos do Apache para segurança e suporte a .htaccess
RUN a2enmod rewrite headers

# Permite que as regras de segurança do .htaccess funcionem no container
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Ajusta limites de upload do PHP para suportar anexos de até 50MB (imagens e vídeos)
RUN printf "upload_max_filesize = 64M\npost_max_size = 64M\nmemory_limit = 256M\nmax_execution_time = 120\n" > /usr/local/etc/php/conf.d/uploads.ini

# Copia os arquivos da aplicação para o diretório público do Apache
WORKDIR /var/www/html
COPY . /var/www/html/

# Garante que a pasta de uploads exista e que o usuário www-data tenha permissão de escrita
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Suporte dinâmico à variável $PORT do Render (padrão 80)
CMD sh -c "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && apache2-foreground"
