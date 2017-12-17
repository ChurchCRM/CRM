#!/bin/bash

if [[ -z "${TRAVIS}" ]]; then
  echo "Not TravisCI - Manually Installing Sauce Connect"
  wget -q https://saucelabs.com/downloads/sc-4.4.6-linux.tar.gz -O /tmp/sc.tar.gz
  sudo rm -rf /tmp/sc
  mkdir /tmp/sc
  tar -xzf /tmp/sc.tar.gz -C /tmp/sc
fi

cd tests/
composer install
wget -q http://get.sensiolabs.org/security-checker.phar -O security-checker.phar