#!/usr/bin/env bash

echo "===============   Node & NPM       ================="
nvm install node 6.9
nvm alias default 6.9
npm install npm -g
echo "===============   npm  install          ================="
npm install -g
npm install
