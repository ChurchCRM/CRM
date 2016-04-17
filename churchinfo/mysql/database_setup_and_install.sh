#!/usr/bin/env bash

###########################################################
########## Change to match the mysql server setup #########
###########################################################
DB_USER="root"
DB_PASS="root"
DB_HOST="localhost"

CRM_DB_INSTALL_SCRIPT="install/Install.sql"

#Church CRM DB
CRM_DB_USER="churchcrm"
CRM_DB_PASS="churchcrm"
CRM_DB_NAME="churchcrm"

# autodetect if mysql is installed through Mac homebrew
MYSQLPATH=`readlink $(which mysql) | cut -c-10`
if [[ "x$MYSQLPATH" != "x../Cellar/" ]]; then
  # it is not!
  SUDO="sudo"
  PASS="-p$DB_PASS"
fi

RET=1
while [[ 1 ]]; do
    echo "Database: Waiting for confirmation of MySQL service startup"
    $SUDO mysql -u"$DB_USER" $PASS -e "status" > /dev/null 2>&1
    RET=$?
    if [[ RET -ne 0 ]]; then
      # wait and try again
      sleep 5
    else
      # mysql server is running
      break
    fi
done

echo "Database: mysql started"

$SUDO mysql -u"$DB_USER" $PASS -e "CREATE DATABASE $CRM_DB_NAME CHARACTER SET utf8;"

echo "Database: created"

$SUDO mysql -u"$DB_USER" $PASS -e "CREATE USER '$CRM_DB_USER'@'$DB_HOST' IDENTIFIED BY '$CRM_DB_PASS';"
$SUDO mysql -u"$DB_USER" $PASS -e "GRANT ALL PRIVILEGES ON $CRM_DB_NAME.* TO '$CRM_DB_NAME'@'$DB_HOST' WITH GRANT OPTION;"

echo "Database: user created with needed PRIVILEGES"

$SUDO mysql -u"$CRM_DB_USER" -p"$CRM_DB_PASS" "$CRM_DB_NAME" < $CRM_DB_INSTALL_SCRIPT

echo "Database: tables and metadata deployed"
