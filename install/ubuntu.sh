#!/usr/bin/env sh

# Error on unset variable or parameter and exit
set -u

DATABASE_NAME="$1"
DATABASE_USERNAME="$2"
DATABASE_PASSWORD="$3"

sudo apt-get update
sudo apt-get upgrade -y
sudo apt-get install -y \
  apache2 \
  curl \
  gawk \
  libapache2-mod-php \
  mariadb-client \
  mariadb-server \
  php \
  php-bcmath \
  php-cli \
  php-curl \
  php-dev \
  php-gd \
  php-intl \
  php-mbstring \
  php-mysql \
  php-soap \
  php-xml \
  php-zip \
  unzip \
  wget

cd /tmp
VERSION=$(curl -Is https://github.com/ChurchCRM/CRM/releases/latest | awk -F\/ '/^location:/ {sub(/\r$/, "", $NF); print $NF}')
wget "https://github.com/ChurchCRM/CRM/releases/download/$VERSION/ChurchCRM-$VERSION.zip" || exit
unzip "ChurchCRM-$VERSION.zip" && rm "ChurchCRM-$VERSION.zip"
sudo chown -R www-data:www-data churchcrm
sudo mv churchcrm /var/www/html/

sudo systemctl enable apache2.service mariadb.service

## Creating the database
sudo mariadb -uroot -p -e "CREATE DATABASE ${DATABASE_NAME} /*\!40100 DEFAULT CHARACTER SET utf8 */;
CREATE USER ${DATABASE_USERNAME}@'localhost' IDENTIFIED BY '${DATABASE_PASSWORD}';
GRANT ALL ON ${DATABASE_NAME}.* TO '${DATABASE_USERNAME}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;"

echo "Please make sure to secure your database server:"
echo " sudo mysql_secure_installation"

PHP_CONF_D_PATH="/etc/php/conf.d/churchcrm.ini"
PHP_VERSION=$(php -r 'echo phpversion();' | cut -d '.' -f 1,2)

if [ "$PHP_VERSION" = "8.3" ]
then
  PHP_CONF_D_PATH="/etc/php/8.3/apache2/conf.d/99-churchcrm.ini"
fi

# Set-up the required PHP configuration
sudo tee "$PHP_CONF_D_PATH" << 'TXT'
file_uploads = On
allow_url_fopen = On
short_open_tag = On
memory_limit = 256M
upload_max_filesize = 100M
max_execution_time = 360
TXT

# Set-up the required Apache configuration
sudo tee /etc/apache2/sites-available/churchcrm.conf << 'TXT'
<VirtualHost *:80>

ServerAdmin webmaster@localhost
DocumentRoot /var/www/html/churchcrm/
ServerName ChurchCRM

<Directory /var/www/html/churchcrm/>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

ErrorLog ${APACHE_LOG_DIR}/error.log
CustomLog ${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
TXT

# Enable apache rewrite module
sudo a2enmod rewrite

# Disable the default apache site and enable ChurchCRM
sudo a2dissite 000-default.conf
sudo a2ensite churchcrm.conf

# Restart apache to load new configuration
sudo systemctl restart apache2.service
