#!/usr/bin/env bash
echo "=========================================================="
echo "===============   db script import       ================="
echo "=========================================================="
mysql -e "create database IF NOT EXISTS churchcrm_test;" -uroot;
echo "===============   db demo script import  ================"
mysql churchcrm_test < demo/ChurchCRM-Database.sql -uroot;
echo "===============   db script import  Done  ================"
