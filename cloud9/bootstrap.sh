#!/usr/bin/env bash

#=============================================================================
# DB Setup
DB_USER="root"
DB_HOST="localhost"

CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

echo "=========================================================="
echo "====================   DB Setup  ========================="
echo "=========================================================="

echo "Database: mysql started"

sudo mysql -u"$DB_USER" -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

sudo mysql -u"$DB_USER" -e "CREATE USER '$CRM_DB_USER'@'%' IDENTIFIED BY '$CRM_DB_PASS';"
sudo mysql -u"$DB_USER" -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'%' WITH GRANT OPTION;"
sudo mysql -u"$DB_USER" -e "FLUSH PRIVILEGES;"
echo "Database: user created with needed PRIVILEGES"

echo "=========================================================="
echo "===============   NPM                    ================="
echo "=========================================================="

. ~/.nvm/nvm.sh
nvm install 8
nvm alias default 8
npm install -g i18next-extract-gettext
npm install -g grunt-cli
npm install --unsafe-perm --no-bin-links

echo "=========================================================="
echo "===============   Composer PHP           ================="
echo "=========================================================="

npm run composer-update

echo "================   Build ORM Classes    =================="

npm run orm-gen

