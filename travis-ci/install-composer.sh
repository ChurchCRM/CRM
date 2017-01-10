#!/usr/bin/env bash

echo "=========================================================="
echo "===============   Composer Install/Setup ================="
echo "=========================================================="
# composer install
cd src
composer install
cd ../
src/vendor/bin/propel model:build --config-dir=propel
cd src
composer dump-autoload
