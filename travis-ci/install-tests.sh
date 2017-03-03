#!/usr/bin/env bash
#sudo apt-get install software-properties-common
#sudo add-apt-repository -y ppa:ondrej/php
#sudo rm -f /etc/apt/sources.list.d/ondrej-php5*
sudo apt-get update
sudo apt-get install -y apache2 
sudo apt-get install -y php7.0 php7.0-mysql php7.0-xml php7.0-curl php7.0-zip php7.0-mbstring php7.0-gd php7.0-mcrypt
sudo a2dismod php5
sudo a2enmod php7.0
sudo a2enmod rewrite
sudo cp /home/travis/build/ChurchCRM/CRM/travis-ci/000-default.conf /etc/apache2/sites-enabled/000-default.conf
sudo chmod -R a+rwx /home/travis/build/ChurchCRM/CRM/src
sudo service apache2 restart

cat /etc/apache2/sites-enabled/000-default.conf

curl http://localhost


ls -la /home/travis/build/ChurchCRM/CRM/src
exit 186