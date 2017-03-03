#!/usr/bin/env bash
sudo apt-get install apache2
sudo a2enmod rewrite
sudo cp 000-default.conf /etc/apache2/sites-enabled/000-default.conf
sudo chmod -R a+rw /home/travis/build/ChurchCRM/CRM/src
sudo service apache2 restart

cat /etc/apache2/sites-enabled/000-default.conf

curl http://localhost


ls -la /home/travis/build/ChurchCRM/CRM/src
exit 186