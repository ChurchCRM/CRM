#!/usr/bin/env bash
echo "=========================================================="
echo "===============   db script import       ================="
echo "=========================================================="
mysql -e "create database IF NOT EXISTS churchcrm_test;" -uroot;
mysql churchcrm_test < demo/ChurchCRM-Database.sql;
echo "===============   db script import  Done  ================"
