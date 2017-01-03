#!/usr/bin/env bash

set -e

# setup for node 6/7 (v6.9 is an LTS release, so we'll support it for the next time)
sudo rm -rf ~/.nvm
curl -sL "https://deb.nodesource.com/setup_6.x" | sudo -E bash -
sudo apt-get install -y nodejs

# composer install
cd src
composer install
cd ../
src/vendor/bin/propel model:build --config-dir=propel
cd src
composer dump-autoload

cd ../
# install global npm dependencies
sudo npm install -g npm@latest --unsafe-perm --no-bin-links
npm install
