#!/usr/bin/env bash
sudo apt-get install apache2
sudo a2enmod rewrite
sudo sed -i 's/^\s*DocumentRoot.*$/DocumentRoot \/home\/travis\/build\/ChurchCRM\/CRM\/src/g' /etc/apache2/sites-enabled/000-default.conf
sudo service apache2 restart

cat /etc/apache2/sites-enabled/000-default.conf

curl http://localhost

exit 186