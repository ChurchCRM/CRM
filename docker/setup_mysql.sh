#!/usr/bin/env bash

echo "=========================================================="
echo "====================   DB Setup  ========================="
echo "=========================================================="

/usr/bin/mysqld_safe > /dev/null 2>&1 &

RET=1
while [[ RET -ne 0 ]]; do
    echo "=> Waiting for confirmation of MySQL service startup"
    sleep 5
    mysql -uroot -e "status" > /dev/null 2>&1
    RET=$?
done

echo "=> Creating MySQL churchcrm user with churchcrm password"

mysql -uroot -e "CREATE DATABASE churchcrm"
mysql -uroot -e "CREATE USER 'churchcrm'@'%' IDENTIFIED BY 'churchcrm'"
mysql -uroot -e "GRANT ALL PRIVILEGES ON churchcrm.* TO 'churchcrm'@'%' WITH GRANT OPTION"

#Install churchcrm db
mysql churchcrm < /app/mysql/install/Install.sql

mysqladmin -uroot shutdown


echo "=========================================================="
echo "=========================================================="
echo "===   .o88b. db   db db    db d8888b.  .o88b. db   db  ==="
echo "===  d8P  Y8 88   88 88    88 88  '8D d8P  Y8 88   88  ==="
echo "===  8P      88ooo88 88    88 88oobY' 8P      88ooo88  ==="
echo "===  8b      88~~~88 88    88 88'8b   8b      88~~~88  ==="
echo "===  Y8b  d8 88   88 88b  d88 88 '88. Y8b  d8 88   88  ==="
echo "===   'Y88P' YP   YP ~Y8888P' 88   YD  'Y88P' YP   YP  ==="
echo "===                                                    ==="
echo "===                         .o88b. d8888b. .88b  d88.  ==="
echo "===                        d8P  Y8 88  '8D 88'YbdP'88  ==="
echo "===                        8P      88oobY' 88  88  88  ==="
echo "===                        8b      88'8b   88  88  88  ==="
echo "===                        Y8b  d8 88 '88. 88  88  88  ==="
echo "===                         'Y88P' 88   YD YP  YP  YP  ==="
echo "=========================================================="
echo "=========================================================="
echo "====== login username            : admin          ========"
echo "====== initial admin password    : changeme       ========"
echo "=========================================================="
echo "====== Dev Chat: https://gitter.im/ChurchCRM/CRM  ========"
echo "=========================================================="
