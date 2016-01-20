#!/usr/bin/env bash

#=============================================================================
# DB Setup
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

CRM_DB_INSTALL_SCRIPT="/vagrant/mysql/install/Install.sql"
CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

RET=1
while [[ RET -ne 0 ]]; do
    echo "Database: Waiting for confirmation of MySQL service startup"
    sleep 5
    sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "status" > /dev/null 2>&1
    RET=$?
done

echo "Database: mysql started"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE USER '$CRM_DB_USER'@'$DB_HOST' IDENTIFIED BY '$CRM_DB_PASS';"
sudo mysql -u"$DB_USER" -p"$DB_PASS" -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'$DB_HOST' WITH GRANT OPTION;"

echo "Database: user created with needed PRIVILEGES"

sudo mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT

echo "Database: tables and metadata deployed"


#=============================================================================
# Help info

echo "============================================================================="
echo "======== Church CRM is now hosted @ http://192.168.33.10/      =============="
echo "======== CRM User Name: admin                                  =============="
echo "======== 1st time login password for admin: changeme           =============="
echo "======== churchCRM is active project source                    =============="
echo "============================================================================="
