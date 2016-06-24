#!/usr/bin/env bash

echo "============================= Build Skin =================================="


echo "Cleaning old copies"

rm -rf /var/www/public/skin/adminlte
rm -rf /var/www/public/skin/font-awesome/
rm -rf /var/www/public/skin/ionicons/

echo "Moving AdminLTE"

mkdir /var/www/public/skin/adminlte
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/dist/ /var/www/public/skin/adminlte/dist
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/skin/adminlte/
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/skin/adminlte/

rm -rf /vagrant/src/vendor/almasaeed2010

echo "Build: AdminLTE copied"

mkdir /var/www/public/skin/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/css/ /var/www/public/skin/font-awesome/css/
mv -f /vagrant/src/vendor/components/font-awesome/fonts/ /var/www/public/skin/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/less/ /var/www/public/skin/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/scss/ /var/www/public/skin/font-awesome/

rm -rf  /vagrant/src/vendor/components

echo "Build: font-awesome copied"

mkdir /var/www/public/skin/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/css/ /var/www/public/skin/ionicons/css/
mv -f /vagrant/src/vendor/driftyco/ionicons/fonts/ /var/www/public/skin/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/less/ /var/www/public/skin/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/png/ /var/www/public/skin/ionicons/

rm -rf /vagrant/src/vendor/driftyco

echo "Build: ionicons copied"

rm -f /vagrant/vendor
