FROM php:8.2-apache

# Extensions GD (JPEG, PNG, WebP) pour generate_thumbs.php
RUN apt-get update && apt-get install -y \
    libjpeg-dev libpng-dev libwebp-dev libgif-dev \
    vsftpd \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite pour le .htaccess SPA
RUN a2enmod rewrite

# AllowOverride pour que .htaccess soit pris en compte
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Permettre à www-data de se connecter en FTP (shell + mot de passe)
RUN usermod -s /bin/bash www-data \
 && echo "www-data:ftppass" | chpasswd

# Configuration vsftpd simple avec users locaux
RUN echo "listen=YES" > /etc/vsftpd.conf \
 && echo "listen_ipv6=NO" >> /etc/vsftpd.conf \
 && echo "anonymous_enable=NO" >> /etc/vsftpd.conf \
 && echo "local_enable=YES" >> /etc/vsftpd.conf \
 && echo "write_enable=YES" >> /etc/vsftpd.conf \
 && echo "local_umask=022" >> /etc/vsftpd.conf \
 && echo "file_open_mode=0644" >> /etc/vsftpd.conf \
 && echo "chroot_local_user=YES" >> /etc/vsftpd.conf \
 && echo "allow_writeable_chroot=YES" >> /etc/vsftpd.conf \
 && echo "pasv_enable=YES" >> /etc/vsftpd.conf \
 && echo "pasv_address=127.0.0.1" >> /etc/vsftpd.conf \
 && echo "pasv_min_port=21100" >> /etc/vsftpd.conf \
 && echo "pasv_max_port=21110" >> /etc/vsftpd.conf \
 && echo "local_root=/var/www/html/photos" >> /etc/vsftpd.conf \
 && echo "userlist_enable=YES" >> /etc/vsftpd.conf \
 && echo "userlist_deny=NO" >> /etc/vsftpd.conf \
 && echo "userlist_file=/etc/vsftpd.userlist" >> /etc/vsftpd.conf \
 && echo "www-data" > /etc/vsftpd.userlist

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["/entrypoint.sh"]
