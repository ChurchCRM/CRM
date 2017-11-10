#!/bin/bash

if [[ -z "${TRAVIS}" ]]; then
  echo "Not TravisCI - Manually Installing Sauce Connect"
  wget -q https://saucelabs.com/downloads/sc-4.4.6-linux.tar.gz -O tests/sc.tar.gz
  rm -rf tests/sc
  mkdir tests/sc
  tar -xzf tests/sc.tar.gz -C /tmp/sc
fi

cd tests/ 
composer install
wget http://get.sensiolabs.org/security-checker.phar