#!/usr/bin/env bash

/usr/bin/mysqld_safe > /dev/null 2>&1 &

RET=1
while [[ RET -ne 0 ]]; do
    echo "=> Waiting for confirmation of MySQL service startup"
    sleep 5
    mysql -uroot -e "status" > /dev/null 2>&1
    RET=$?
done

echo "=> Creating MySQL churchinfo user with churchinfo password"

mysql -uroot -e "CREATE DATABASE churchinfo"
mysql -uroot -e "CREATE USER 'churchinfo'@'%' IDENTIFIED BY 'churchinfo'"
mysql -uroot -e "GRANT ALL PRIVILEGES ON churchinfo.* TO 'churchinfo'@'%' WITH GRANT OPTION"

#Install churhcinfo db
mysql churchinfo < /app/mysql/install/Install.sql

mysqladmin -uroot shutdown