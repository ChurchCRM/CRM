#!/bin/bash

echo "=========================================================="
echo "====================  ChurchCRM DB Setup  ========================="
echo "=========================================================="

echo "=> Creating MySQL churchcrm user with churchcrm password"

mysql -uroot -e "CREATE DATABASE churchcrm"
mysql -uroot -e "CREATE USER 'churchcrm'@'%' IDENTIFIED BY 'churchcrm'"
mysql -uroot -e "GRANT ALL PRIVILEGES ON churchcrm.* TO 'churchcrm'@'%' WITH GRANT OPTION"

#Install churchcrm db
mysql churchcrm < /app/mysql/install/Install.sql

