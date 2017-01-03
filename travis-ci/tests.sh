#!/usr/bin/env bash
mysql churchcrm_test < ../src/mysql/install/Install.sql -uroot;
mysql churchcrm_test < ../src/mysql/upgrade/update_config.sql -uroot;
