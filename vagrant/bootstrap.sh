#!/usr/bin/env bash

#=============================================================================
# DB Setup
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

CRM_DB_INSTALL_SCRIPT="/vagrant/src/mysql/install/Install.sql"
CRM_DB_INSTALL_SCRIPT2="/vagrant/src/mysql/upgrade/update_config.sql"
CRM_DB_VAGRANT_SCRIPT="/vagrant/vagrant/vagrant.sql"
CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

echo "=========================================================="
echo "====================   DB Setup  ========================="
echo "=========================================================="

RET=1
while [[ RET -ne 0 ]]; do
    echo "Database: Waiting for confirmation of MySQL service startup"
    sleep 5
    sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "status" > /dev/null 2>&1
    RET=$?
done

echo "Database: mysql started"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $CRM_DB_NAME;"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP USER '$CRM_DB_USER';"
echo "Database: cleared"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE USER '$CRM_DB_USER'@'%' IDENTIFIED BY '$CRM_DB_PASS';"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'%' WITH GRANT OPTION;"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "FLUSH PRIVILEGES;"
echo "Database: user created with needed PRIVILEGES"

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT
sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT2

echo "Database: tables and metadata deployed"

CODE_VER=`grep \"version\" /vagrant/package.json | cut -d ',' -f1 | cut -d'"' -f4`

echo "=========================================================="
echo "===========  Setup Dev to version: $CODE_VER ============="
echo "=========================================================="

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_VAGRANT_SCRIPT
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "INSERT INTO churchcrm.version_ver (ver_version, ver_update_start) VALUES ('$CODE_VER', now());"
echo "Database: development seed data deployed"

echo "=========================================================="
echo "=================   Clearing Sessions  ==================="
echo "=========================================================="

sudo rm /var/lib/php/sessions/*

cp /vagrant/vagrant/Config.php /vagrant/src/Include/
cp /vagrant/BuildConfig.json.example /vagrant/BuildConfig.json
echo "copied Config.php "

cd /vagrant

echo "=========================================================="
echo "===============   NPM                    ================="
echo "=========================================================="

VERSION="$(npm --version)"
echo "Node vesrion: $VERSION"

# NPM does not function nicely with Vagrant's "shared folders," so 
# create a mountpoint to a folder in the guest-only filesystem

mountpoint /vagrant/node_modules/ > /dev/null
ISMOUNTPOINT=$?

if [ $ISMOUNTPOINT -eq 0 ]; then 
    echo "/vagrant/node_modules is a mountpoint - don't touch"
else
    echo "/vagrant/node_modules is not a mountpoint - nuke and mount"
    sudo rm -rf /vagrant/node_modules
    sudo mkdir /vagrant/node_modules
    mkdir -p /home/vagrant/node_modules /vagrant/node_modules
    sudo mount --bind /home/vagrant/node_modules/ /vagrant/node_modules
    echo "/home/vagrant/node_modules/ has been mounted to /vagrant/node_modules"
fi

sudo chown vagrant:vagrant /vagrant/node_modules
sudo chmod a+rw /vagrant/node_modules
sudo chmod a+rw  /home/vagrant/node_modules

npm install --unsafe-perm
grunt compress:demo
echo "=========================================================="
echo "===============   Composer PHP           ================="
echo "=========================================================="

sudo composer self-update
npm run composer-update

echo "================   Build ORM Classes    =================="

npm run orm-gen

echo "=========================================================="
echo "=================   MailCatcher Setup  ==================="
echo "=========================================================="

sudo pkill mailcatcher
mailcatcher --ip 0.0.0.0

echo "=========================================================="
echo "====================   Setup Tests  ======================"
echo "=========================================================="

npm run tests-install

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
