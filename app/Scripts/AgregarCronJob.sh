#!/bin/sh
# Abortar parches cuando hay error
# - El único parametro es el directorio donde se debe ejecutar el comando git
sudo crontab -l | { cat; echo "*/1 * * * * php /var/www/html/api/artisan shedule:run"; } | crontab -
echo "Se agrego cron job"