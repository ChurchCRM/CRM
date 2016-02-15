#!/usr/bin/env bash

#=============================================================================
# DB Setup
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

CRM_DB_INSTALL_SCRIPT="/vagrant/churchinfo/mysql/install/Install.sql"
CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

echo "========================== DB   =========================================="

RET=1
while [[ RET -ne 0 ]]; do
    echo "Database: Waiting for confirmation of MySQL service startup"
    sleep 5
    sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "status" > /dev/null 2>&1
    RET=$?
done

echo "Database: mysql started"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE USER '$CRM_DB_USER'@'$DB_HOST' IDENTIFIED BY '$CRM_DB_PASS';"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'$DB_HOST' WITH GRANT OPTION;"

echo "Database: user created with needed PRIVILEGES"

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT

echo "Database: tables and metadata deployed"

echo "============================= Build ===================================="

cd /dev/churchcrm
composer self-update
composer install

echo "Build: Composer install"

cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/dist/ /var/www/public/skin/adminlte/dist/
cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/skin/adminlte/bootstrap/
cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/skin/adminlte/plugins/

echo "Build: AdminLTE copied"

cp -rf /dev/churchcrm/vendor/components/font-awesome/css/ /var/www/public/skin/font-awesome/css/
cp -rf /dev/churchcrm/vendor/components/font-awesome/fonts/ /var/www/public/skin/font-awesome/fonts/
cp -rf /dev/churchcrm/vendor/components/font-awesome/less/ /var/www/public/skin/font-awesome/less/
cp -rf /dev/churchcrm/vendor/components/font-awesome/scss/ /var/www/public/skin/font-awesome/scss/

echo "Build: font-awesome copied"

cp -rf /dev/churchcrm/vendor/driftyco/ionicons/css/ /var/www/public/skin/ionicons/css/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/fonts/ /var/www/public/skin/ionicons/fonts/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/less/ /var/www/public/skin/ionicons/less/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/png/ /var/www/public/skin/ionicons/png/

echo "Build: ionicons copied"

rm -rf /dev/churchcrm/vendor

#=============================================================================
# Help info

echo "============================================================================="
echo "======== Church CRM is now hosted @ http://192.168.33.10/      =============="
echo "======== CRM User Name: admin                                  =============="
echo "======== 1st time login password for admin: changeme           =============="
echo "======== churchCRM is active project source                    =============="
echo "============================================================================="
