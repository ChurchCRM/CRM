#!/usr/bin/env bash

echo "============================= Build ===================================="

cd /dev/churchcrm
composer install

echo "Build: Composer install"

cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/dist/ /var/www/public/skin/adminlte/
cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/skin/adminlte/
cp -rf /dev/churchcrm/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/skin/adminlte/

echo "Build: AdminLTE copied"

cp -rf /dev/churchcrm/vendor/components/font-awesome/css/ /var/www/public/skin/font-awesome/
cp -rf /dev/churchcrm/vendor/components/font-awesome/fonts/ /var/www/public/skin/font-awesome/
cp -rf /dev/churchcrm/vendor/components/font-awesome/less/ /var/www/public/skin/font-awesome/
cp -rf /dev/churchcrm/vendor/components/font-awesome/scss/ /var/www/public/skin/font-awesome/

echo "Build: font-awesome copied"

cp -rf /dev/churchcrm/vendor/driftyco/ionicons/css/ /var/www/public/skin/ionicons/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/fonts/ /var/www/public/skin/ionicons/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/less/ /var/www/public/skin/ionicons/
cp -rf /dev/churchcrm/vendor/driftyco/ionicons/png/ /var/www/public/skin/ionicons/

echo "Build: ionicons copied"

rm -rf /dev/churchcrm/vendor