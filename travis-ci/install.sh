#!/usr/bin/env bash

set -e

# setup for node 6/7 (v6.9 is an LTS release, so we'll support it for the next time)
sudo rm -rf ~/.nvm
curl -sL "https://deb.nodesource.com/setup_${NODE_VERSION}" | sudo -E bash -
sudo apt-get install -y nodejs

# install global npm dependencies
sudo npm install

# composer install
composer install
