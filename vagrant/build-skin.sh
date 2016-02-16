#!/usr/bin/env bash

echo "============================= Build ===================================="

cd /dev/churchcrm
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