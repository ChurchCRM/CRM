#!/usr/bin/env bash
echo "=========================================================="
echo "===============   db script import       ================="
echo "=========================================================="
mysql -e "create database IF NOT EXISTS churchcrm_test;" -uroot;
mysql churchcrm_test < src/mysql/install/Install.sql -uroot;
mysql churchcrm_test < src/mysql/upgrade/update_config.sql -uroot;
echo "===============   db demo script import  ================"
mysql churchcrm_test < demo/ChurchCRM-Database.sql -uroot;
echo "===============   db script import  Done  ================"
