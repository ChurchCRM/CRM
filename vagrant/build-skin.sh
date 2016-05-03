#!/usr/bin/env bash

echo "============================= Build Skin =================================="

cd /vagrant
composer install

echo "Build: Composer install"

rm -rf /var/www/public/skin/adminlte
rm -rf /var/www/public/skin/font-awesome/
rm -rf /var/www/public/skin/ionicons/

echo "Cleaning old copies"

mkdir /var/www/public/skin/adminlte
cp -rf /vagrant/vendor/almasaeed2010/adminlte/dist/ /var/www/public/skin/adminlte/dist
cp -rf /vagrant/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/skin/adminlte/
cp -rf /vagrant/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/skin/adminlte/

echo "Build: AdminLTE copied"

mkdir /var/www/public/skin/font-awesome/
cp -rf /vagrant/vendor/components/font-awesome/css/ /var/www/public/skin/font-awesome/css/
cp -rf /vagrant/vendor/components/font-awesome/fonts/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/vendor/components/font-awesome/less/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/vendor/components/font-awesome/scss/ /var/www/public/skin/font-awesome/

echo "Build: font-awesome copied"

mkdir /var/www/public/skin/ionicons/
cp -rf /vagrant/vendor/driftyco/ionicons/css/ /var/www/public/skin/ionicons/css/
cp -rf /vagrant/vendor/driftyco/ionicons/fonts/ /var/www/public/skin/ionicons/
cp -rf /vagrant/vendor/driftyco/ionicons/less/ /var/www/public/skin/ionicons/
cp -rf /vagrant/vendor/driftyco/ionicons/png/ /var/www/public/skin/ionicons/

echo "Build: ionicons copied"

rm -rf /vagrant/vendor
