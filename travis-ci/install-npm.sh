#!/usr/bin/env bash

echo "===============   npm  install -g       ================="

# install global npm dependencies
sudo npm install -g npm@latest --unsafe-perm
echo "===============   npm  install          ================="
npm install --unsafe-perm
