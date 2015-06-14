#!/bin/bash

/usr/bin/mysqld_safe > /dev/null 2>&1 &

RET=1
while [[ RET -ne 0 ]]; do
    echo "=> Waiting for confirmation of MySQL service startup"
    sleep 5
    mysql -uroot -e "status" > /dev/null 2>&1
    RET=$?
done

echo "=> Creating MySQL church CRM user with church CRM password"

mysql -uroot -e "CREATE DATABASE churchcrm"
mysql -uroot -e "CREATE USER 'churchcrm'@'%' IDENTIFIED BY 'churchcrm'"
mysql -uroot -e "GRANT ALL PRIVILEGES ON churchcrm.* TO 'churchcrm'@'%' WITH GRANT OPTION"

#Install churchcrm db
mysql churchcrm < install/Install.sql

mysqladmin -uroot shutdown
