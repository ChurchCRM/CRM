#!/usr/bin/env bash

#=============================================================================
# DB Setup
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

CRM_DB_INSTALL_SCRIPT="/vagrant/src/mysql/install/Install.sql"
CRM_DB_VAGRANT_SCRIPT="/vagrant/vagrant/vagrant.sql"
CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

echo "=========================================================="
echo "================+=   Apache Setup  ======================="
echo "=========================================================="
sudo sed -i 's/^upload_max_filesize.*$/upload_max_filesize = 2G/g' /etc/php5/apache2/php.ini
sudo sed -i 's/^post_max_size.*$/post_max_size = 2G/g' /etc/php5/apache2/php.ini
sudo service apache2 restart

echo "=========================================================="
echo "====================   DB Setup  ========================="
echo "=========================================================="
sudo sed -i 's/^bind-address.*$/bind-address=0.0.0.0/g' /etc/mysql/my.cnf
sudo service mysql restart
RET=1
while [[ RET -ne 0 ]]; do
    echo "Database: Waiting for confirmation of MySQL service startup"
    sleep 5
    sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "status" > /dev/null 2>&1
    RET=$?
done

echo "Database: mysql started"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE $CRM_DB_NAME;"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP USER '$CRM_DB_USER';"
echo "Database: cleared"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE USER '$CRM_DB_USER'@'%' IDENTIFIED BY '$CRM_DB_PASS';"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'%' WITH GRANT OPTION;"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "FLUSH PRIVILEGES;"
echo "Database: user created with needed PRIVILEGES"

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT

echo "Database: tables and metadata deployed"

echo "=========================================================="
echo "==============   Development DB Setup  ==================="
echo "=========================================================="

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_VAGRANT_SCRIPT

echo "Database: development seed data deployed"

echo "=========================================================="
echo "=================   MailCatcher Setup  ==================="
echo "=========================================================="

sudo pkill mailcatcher
sudo /home/vagrant/.rbenv/versions/2.2.2/bin/mailcatcher --ip 0.0.0.0

echo "=========================================================="
echo "=================   Composer Update    ==================="
echo "=========================================================="

sudo /usr/local/bin/composer self-update

echo "=========================================================="
echo "===============   Composer PHP & Skin    ================="
echo "=========================================================="

cd /vagrant/src
composer update

../vagrant/build-skin.sh

echo "=========================================================="
echo "===============  MV Config.php           ================="
echo "=========================================================="

cp /vagrant/vagrant/Config.php /vagrant/src/Include/

echo "=========================================================="
echo "=========================================================="
echo "===   .o88b. db   db db    db d8888b.  .o88b. db   db  ==="     
echo "===  d8P  Y8 88   88 88    88 88  '8D d8P  Y8 88   88  ==="  
echo "===  8P      88ooo88 88    88 88oobY' 8P      88ooo88  ==="  
echo "===  8b      88~~~88 88    88 88'8b   8b      88~~~88  ===" 
echo "===  Y8b  d8 88   88 88b  d88 88 '88. Y8b  d8 88   88  ===" 
echo "===   'Y88P' YP   YP ~Y8888P' 88   YD  'Y88P' YP   YP  ===" 
echo "===                                                    ==="
echo "===                         .o88b. d8888b. .88b  d88.  ==="
echo "===                        d8P  Y8 88  '8D 88'YbdP'88  ==="
echo "===                        8P      88oobY' 88  88  88  ==="
echo "===                        8b      88'8b   88  88  88  ==="
echo "===                        Y8b  d8 88 '88. 88  88  88  ==="
echo "===                         'Y88P' 88   YD YP  YP  YP  ==="                           
echo "=========================================================="
echo "=========================================================="
echo "====== Visit  http://192.168.33.10/               ========"
echo "====== login username            : admin          ========"
echo "====== initial admin password    : changeme       ========"
echo "=========================================================="
echo "====== Dev Chat: https://gitter.im/ChurchCRM/CRM  ========"
echo "=========================================================="
