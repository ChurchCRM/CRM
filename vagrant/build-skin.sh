#!/usr/bin/env bash

echo "============================= Build Skin =================================="


echo "Cleaning old copies"

rm -rf /var/www/public/vendor/almasaeed2010/adminlte
rm -rf /var/www/public/vendor/components/font-awesome/
rm -rf /var/www/public/vendor/driftyco/ionicons/

echo "Moving AdminLTE"

mkdir /var/www/public/vendor/almasaeed2010/adminlte
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/dist/ /var/www/public/vendor/almasaeed2010/adminlte/dist
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/bootstrap/ /var/www/public/vendor/almasaeed2010/adminlte/
mv -f /vagrant/src/vendor/almasaeed2010/adminlte/plugins/ /var/www/public/vendor/almasaeed2010/adminlte/

rm -rf /vagrant/src/vendor/almasaeed2010

echo "Build: AdminLTE copied"

mkdir /var/www/public/vendor/components/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/css/ /var/www/public/vendor/components/font-awesome/css/
mv -f /vagrant/src/vendor/components/font-awesome/fonts/ /var/www/public/vendor/components/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/less/ /var/www/public/vendor/components/font-awesome/
mv -f /vagrant/src/vendor/components/font-awesome/scss/ /var/www/public/vendor/components/font-awesome/

rm -rf  /vagrant/src/vendor/components

echo "Build: font-awesome copied"

mkdir /var/www/public/vendor/driftyco/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/css/ /var/www/public/vendor/driftyco/ionicons/css/
mv -f /vagrant/src/vendor/driftyco/ionicons/fonts/ /var/www/public/vendor/driftyco/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/less/ /var/www/public/vendor/driftyco/ionicons/
mv -f /vagrant/src/vendor/driftyco/ionicons/png/ /var/www/public/vendor/driftyco/ionicons/

rm -rf /vagrant/src/vendor/driftyco

echo "Build: ionicons copied"

rm -f /vagrant/vendor
