#!/bin/bash
# Assure les permissions du dossier photos/ (nouveau contenu ajouté via volume)
chown -R www-data:www-data /var/www/html/photos 2>/dev/null || true

# Démarrer vsftpd en arrière-plan
vsftpd /etc/vsftpd.conf &

# Démarrer Apache au premier plan
exec apache2-foreground
