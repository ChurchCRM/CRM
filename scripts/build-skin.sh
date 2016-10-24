#!/usr/bin/env bash

echo "============================= Building Skin =================================="

rm -rf /var/www/public/skin/adminlte
mkdir /var/www/public/skin/adminlte

cp -rf /vagrant/node_modules/admin-lte/dist/ /var/www/public/skin/adminlte/dist
cp -rf /vagrant/node_modules/admin-lte/bootstrap/ /var/www/public/skin/adminlte/
cp -rf /vagrant/node_modules/admin-lte/plugins/ /var/www/public/skin/adminlte/

echo "Build Skin: AdminLTE"

rm -rf /var/www/public/skin/font-awesome/
mkdir /var/www/public/skin/font-awesome/

cp -rf /vagrant/node_modules/font-awesome/css/ /var/www/public/skin/font-awesome/css/
cp -rf /vagrant/node_modules/font-awesome/fonts/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/node_modules/font-awesome/less/ /var/www/public/skin/font-awesome/
cp -rf /vagrant/node_modules/font-awesome/scss/ /var/www/public/skin/font-awesome/

echo "Build Skin: font-awesome"

rm -rf /var/www/public/skin/ionicons/
mkdir /var/www/public/skin/ionicons/

cp -rf /vagrant/node_modules/ionicons/css/ /var/www/public/skin/ionicons/css/
cp -rf /vagrant/node_modules/ionicons/fonts/ /var/www/public/skin/ionicons/
cp -rf /vagrant/node_modules/ionicons/less/ /var/www/public/skin/ionicons/
cp -rf /vagrant/node_modules/ionicons/png/ /var/www/public/skin/ionicons/

echo "Build Skin: ionicons"

wget -nv -O /vagrant/src/skin/adminlte/plugins/fastclick/fastclick.js https://raw.githubusercontent.com/ftlabs/fastclick/569732a7aa5861d428731b8db022b2d55abe1a5a/lib/fastclick.js
rm /vagrant/src/skin/adminlte/plugins/fastclick/fastclick.min.js

echo "FastClick Patched for iOS Select2 Bug"

cp -rf /vagrant/node_modules/fullcalendar/dist/ /var/www/public/skin/fullcalendar/

echo "Build Skin: Full Calendar"
