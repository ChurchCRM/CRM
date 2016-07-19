#!/usr/bin/env bash

echo "============================= Building Skin =================================="

rm -rf /var/www/public/skin/adminlte
mkdir /var/www/public/skin/adminlte

cp -rf /vagrant/src/vendor/almasaeed2010/adminlte/dist/ /var/www/public/skin/adminlte/dist
cp -rf /vagrant/src/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/skin/adminlte/
cp -rf /vagrant/src/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/skin/adminlte/

echo "Build: AdminLTE copied"

rm -rf /var/www/public/skin/font-awesome/
mkdir /var/www/public/skin/font-awesome/

cp -rf /vagrant/src/vendor/components/font-awesome/css/ /var/www/public/skin/font-awesome/css/
cp -rf /vagrant/src/vendor/components/font-awesome/fonts/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/src/vendor/components/font-awesome/less/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/src/vendor/components/font-awesome/scss/ /var/www/public/skin/font-awesome/

echo "Build: font-awesome copied"

rm -rf /var/www/public/skin/ionicons/
mkdir /var/www/public/skin/ionicons/

cp -rf /vagrant/src/vendor/driftyco/ionicons/css/ /var/www/public/skin/ionicons/css/
cp -rf /vagrant/src/vendor/driftyco/ionicons/fonts/ /var/www/public/skin/ionicons/
cp -rf /vagrant/src/vendor/driftyco/ionicons/less/ /var/www/public/skin/ionicons/
cp -rf /vagrant/src/vendor/driftyco/ionicons/png/ /var/www/public/skin/ionicons/

echo "Build: ionicons copied"
