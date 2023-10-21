sudo apt update ; sudo apt upgrade -y 
sudo apt install unzip wget git -y
sudo apt install apache2 -y
sudo apt install mysql-server -y

########### un-comment if you are using php7.4####### 
#sudo apt install software-properties-common -y 
#sudo add-apt-repository ppa:ondrej/php -y
#sudo apt update
#sudo apt install php7.4 libapache2-mod-php7.4 php7.4-mysql php7.4-xml php7.4-zip php7.4-curl php7.4-gd php7.4-mbstring php7.4-cli -y
######################################################


sudo apt install php libapache2-mod-php -y
sudo apt install php-curl php-cli php-dev php-gd php-intl php-json php-mysql php-bcmath php-mbstring php-soap php-xml php-zip -y
cd /tmp
git clone https://github.com/ChurchCRM/CRM.git
cd /var/www
sudo rm -rf html
sudo cp -r /tmp/CRM/src /var/www/html
sudo rm -rf /tmp/CRM
cd /var/www/html/
sudo find . -exec chown www-data:www-data "{}" \;
sudo find . -type f -exec chmod 644 "{}" \;
sudo find . -type d -exec chmod 755 "{}" \;
sudo chmod 755 /var/www/html/Include
sudo chmod 755 /var/www/html/Images
sudo a2enmod rewrite
sudo systemctl restart apache2 

## Creating the database ##Please change the variables 
## Please make sure to secure your Mysql server 
BIN_MYSQL=$(which mysql)
DB_HOST='localhost'
DB_NAME='' ## Enter the database name 
DB_USER='' ## enter the database username 
DB_PASS='' ## enter the password 
sudo mysql -e "CREATE DATABASE ${DB_NAME} /*\!40100 DEFAULT CHARACTER SET utf8 */;"
sudo mysql -e "CREATE USER ${DB_USER}@localhost IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Define the domain name of the server
DOMAIN=""
# Set-up the required BookStack apache config
sudo tee /etc/apache2/sites-available/churchcrm.conf << 'TXT'
<VirtualHost *:80>

ServerAdmin webmaster@localhost
DocumentRoot /var/www/html/

<Directory /var/www/html/>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

ErrorLog \${APACHE_LOG_DIR}/error.log
CustomLog \${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
TXT

# Disable the default apache site and enable churchcrm
sudo a2dissite 000-default.conf
sudo a2ensite churchcrm.conf

# Restart apache to load new config
sudo systemctl restart apache2

